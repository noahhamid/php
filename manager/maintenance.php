<?php
require_once '../auth.php';
check_role(['admin', 'manager']);
require_once '../db.php';

$role    = $_SESSION['user_role'];
$success = '';
$error   = '';

// UPDATE status
if (isset($_GET['resolve'])) {
    $id   = intval($_GET['resolve']);
    $cost = floatval($_GET['cost'] ?? 0);
    $s    = $conn->prepare('UPDATE maintenance_logs SET status="done", cost=?, resolved_date=CURDATE() WHERE id=?');
    $s->bind_param('di', $cost, $id);
    if ($s->execute()) {
        // Set asset back to available
        $a = $conn->prepare('UPDATE assets SET status="available" WHERE id=(SELECT asset_id FROM maintenance_logs WHERE id=?)');
        $a->bind_param('i', $id);
        $a->execute();
        $success = 'Maintenance marked as done. Asset set back to available.';
    } else {
        $error = 'Could not update.';
    }
}

if (isset($_GET['start'])) {
    $id = intval($_GET['start']);
    $s  = $conn->prepare('UPDATE maintenance_logs SET status="in_progress" WHERE id=?');
    $s->bind_param('i', $id);
    $s->execute();
    $success = 'Maintenance marked as in progress.';
}

// ADD manual log
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $reported = $_SESSION['user_id'];

    if (!$asset_id || empty($desc)) {
        $error = 'Asset and description are required.';
    } else {
        $s = $conn->prepare('INSERT INTO maintenance_logs (asset_id, reported_by, description, status) VALUES (?,?,?,"pending")');
        $s->bind_param('iis', $asset_id, $reported, $desc);
        if ($s->execute()) {
            // Mark asset as maintenance
            $conn->prepare('UPDATE assets SET status="maintenance" WHERE id=?')->execute();
            $u = $conn->prepare('UPDATE assets SET status="maintenance" WHERE id=?');
            $u->bind_param('i', $asset_id);
            $u->execute();
            $success = 'Maintenance log added.';
        } else {
            $error = 'Could not add log.';
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$where  = $filter !== 'all' ? "WHERE ml.status='".$conn->real_escape_string($filter)."'" : '';

$logs = $conn->query("
    SELECT ml.*, a.name AS asset_name, a.serial_number,
           u.name AS reporter_name
    FROM maintenance_logs ml
    JOIN assets a ON a.id = ml.asset_id
    JOIN users  u ON u.id = ml.reported_by
    $where
    ORDER BY ml.created_at DESC
");

$counts = $conn->query("
    SELECT
        SUM(status='pending')     AS pending,
        SUM(status='in_progress') AS in_progress,
        SUM(status='done')        AS done
    FROM maintenance_logs
")->fetch_assoc();

$all_assets  = $conn->query("SELECT id, name FROM assets WHERE status != 'retired' ORDER BY name");
$pending_req = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];

// Sidebar links based on role
$base = $role === 'admin' ? '' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Maintenance — Asset Management</title>
  <style>
    body{font-family:sans-serif;display:flex;min-height:100vh;margin:0;background:#f1f5f9;color:#1e293b}
    .sidebar{width:200px;background:<?= $role==='admin'?'#1e293b':'#0f172a'?>;padding:1rem 0;display:flex;flex-direction:column}
    .sidebar .brand{color:#fff;font-weight:700;padding:.75rem 1rem 1rem;border-bottom:1px solid #334155}
    .sidebar nav a{display:block;color:#94a3b8;text-decoration:none;padding:.5rem 1rem;font-size:.9rem}
    .sidebar nav a:hover,.sidebar nav a.active{background:#334155;color:#fff}
    .sidebar .logout{margin-top:auto;padding:1rem}
    .sidebar .logout a{display:block;text-align:center;background:#ef4444;color:#fff;padding:.5rem;border-radius:6px;text-decoration:none;font-size:.85rem}
    .main{flex:1;padding:1.5rem;overflow-x:auto}
    h2{margin-bottom:1rem}
    .stats{display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
    .stat{background:#fff;border-radius:8px;padding:.9rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);min-width:120px;text-align:center}
    .stat .num{font-size:1.6rem;font-weight:700}
    .stat .lbl{font-size:.78rem;color:#64748b;margin-top:2px}
    .stat.amber .num{color:#d97706}
    .stat.blue  .num{color:#2563eb}
    .stat.green .num{color:#16a34a}
    .layout{display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start}
    @media(max-width:900px){.layout{grid-template-columns:1fr}}
    .card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden}
    .card-pad{padding:1.25rem}
    .card-pad h3{font-size:.95rem;margin-bottom:1rem}
    .form-group{margin-bottom:.85rem}
    label{display:block;font-size:.8rem;font-weight:600;margin-bottom:3px;color:#374151}
    select,textarea{width:100%;padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;font-family:inherit}
    textarea{resize:vertical;min-height:80px}
    .btn{padding:.5rem 1rem;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.875rem;width:100%}
    .btn:hover{background:#4338ca}
    .filter-tabs{display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap}
    .filter-tabs a{padding:.35rem .9rem;border-radius:20px;font-size:.85rem;text-decoration:none;background:#e2e8f0;color:#475569;font-weight:500}
    .filter-tabs a.active{background:#4f46e5;color:#fff}
    .filter-tabs a.active.pending{background:#d97706}
    .filter-tabs a.active.in_progress{background:#2563eb}
    .filter-tabs a.active.done{background:#16a34a}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{background:#f8fafc;padding:.65rem .9rem;text-align:left;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600}
    td{padding:.65rem .9rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#f8fafc}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:500}
    .badge.pending    {background:#fef9c3;color:#854d0e}
    .badge.in_progress{background:#dbeafe;color:#1d4ed8}
    .badge.done       {background:#dcfce7;color:#166534}
    a.act{font-size:.8rem;text-decoration:none;margin-right:5px}
    a.start{color:#2563eb}
    a.resolve{color:#16a34a}
    .alert{padding:.6rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.875rem}
    .alert.success{background:#f0fdf4;border:1px solid #86efac;color:#166534}
    .alert.error  {background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
    .empty{text-align:center;padding:3rem;color:#94a3b8}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">Asset <?= $role==='admin'?'Manager':'Manager' ?></div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="assets.php">Assets</a>
    <a href="requests.php">Requests <?= $pending_req > 0 ? "($pending_req)" : '' ?></a>
    <?php if($role==='admin'): ?><a href="users.php">Users</a><?php endif; ?>
    <a href="maintenance.php" class="active">Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <h2>Maintenance Logs</h2>

  <?php if($success): ?><div class="alert success"><?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat amber">
      <div class="num"><?= $counts['pending'] ?? 0 ?></div>
      <div class="lbl">Pending</div>
    </div>
    <div class="stat blue">
      <div class="num"><?= $counts['in_progress'] ?? 0 ?></div>
      <div class="lbl">In Progress</div>
    </div>
    <div class="stat green">
      <div class="num"><?= $counts['done'] ?? 0 ?></div>
      <div class="lbl">Done</div>
    </div>
  </div>

  <div class="layout">

    <!-- Add log form -->
    <div class="card card-pad">
      <h3>+ Add Maintenance Log</h3>
      <form method="POST">
        <div class="form-group">
          <label>Asset</label>
          <select name="asset_id" required>
            <option value="">— Select asset —</option>
            <?php while($a=$all_assets->fetch_assoc()): ?>
              <option value="<?=$a['id']?>"><?=htmlspecialchars($a['name'])?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Description / Issue</label>
          <textarea name="description" placeholder="Describe the issue..." required></textarea>
        </div>
        <button type="submit" class="btn">Add Log</button>
      </form>
    </div>

    <!-- Logs table -->
    <div>
      <div class="filter-tabs">
        <a href="?status=all"         class="<?=$filter==='all'         ?'active':''?>">All</a>
        <a href="?status=pending"     class="<?=$filter==='pending'     ?'active pending':''?>">Pending</a>
        <a href="?status=in_progress" class="<?=$filter==='in_progress' ?'active in_progress':''?>">In Progress</a>
        <a href="?status=done"        class="<?=$filter==='done'        ?'active done':''?>">Done</a>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>#</th><th>Asset</th><th>Serial</th><th>Reported By</th><th>Description</th><th>Status</th><th>Cost</th><th>Resolved</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if($logs->num_rows===0): ?>
              <tr><td colspan="9" class="empty">No logs found.</td></tr>
            <?php else: ?>
              <?php while($row=$logs->fetch_assoc()): ?>
              <tr>
                <td><?=$row['id']?></td>
                <td><strong><?=htmlspecialchars($row['asset_name'])?></strong></td>
                <td style="font-size:.8rem;color:#64748b"><?=htmlspecialchars($row['serial_number']??'—')?></td>
                <td><?=htmlspecialchars($row['reporter_name'])?></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?=htmlspecialchars($row['description'])?>">
                  <?=htmlspecialchars($row['description'])?>
                </td>
                <td><span class="badge <?=$row['status']?>"><?=ucfirst(str_replace('_',' ',$row['status']))?></span></td>
                <td><?=$row['cost']?'$'.number_format($row['cost'],2):'—'?></td>
                <td><?=$row['resolved_date']?date('M d, Y',strtotime($row['resolved_date'])):'—'?></td>
                <td>
                  <?php if($row['status']==='pending'): ?>
                    <a href="?start=<?=$row['id']?>&status=<?=$filter?>" class="act start">Start</a>
                  <?php endif; ?>
                  <?php if($row['status']==='in_progress'): ?>
                    <a href="?resolve=<?=$row['id']?>&cost=0&status=<?=$filter?>"
                       class="act resolve"
                       onclick="let c=prompt('Enter repair cost (0 if none):','0');if(c===null)return false;this.href=this.href.replace('cost=0','cost='+c);return true;">
                      Mark Done
                    </a>
                  <?php endif; ?>
                  <?php if($row['status']==='done'): ?>
                    <span style="color:#94a3b8;font-size:.8rem">Completed</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>
</body>
</html>