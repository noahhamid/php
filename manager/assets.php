<?php
require_once '../auth.php';
check_role('manager');
require_once '../db.php';

$search        = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$filter_cat    = intval($_GET['category'] ?? 0);

$where_parts = [];
if ($search)              $where_parts[] = "(a.name LIKE '%".$conn->real_escape_string($search)."%' OR a.serial_number LIKE '%".$conn->real_escape_string($search)."%')";
if ($filter_status !== 'all') $where_parts[] = "a.status = '".$conn->real_escape_string($filter_status)."'";
if ($filter_cat > 0)      $where_parts[] = "a.category_id = $filter_cat";
$where = $where_parts ? 'WHERE '.implode(' AND ', $where_parts) : '';

$assets      = $conn->query("SELECT a.*,c.name AS category_name,u.name AS assigned_user FROM assets a LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN users u ON u.id=a.assigned_to $where ORDER BY a.name ASC");
$categories  = $conn->query('SELECT * FROM categories ORDER BY name');
$pending_req = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assets — Asset Management</title>
  <style>
    body{font-family:sans-serif;display:flex;min-height:100vh;margin:0;background:#f1f5f9;color:#1e293b}
    .sidebar{width:200px;background:#0f172a;padding:1rem 0;display:flex;flex-direction:column}
    .sidebar .brand{color:#fff;font-weight:700;padding:.75rem 1rem 1rem;border-bottom:1px solid #1e293b}
    .sidebar nav a{display:block;color:#94a3b8;text-decoration:none;padding:.5rem 1rem;font-size:.9rem}
    .sidebar nav a:hover,.sidebar nav a.active{background:#1e293b;color:#fff}
    .sidebar .logout{margin-top:auto;padding:1rem}
    .sidebar .logout a{display:block;text-align:center;background:#ef4444;color:#fff;padding:.5rem;border-radius:6px;text-decoration:none;font-size:.85rem}
    .main{flex:1;padding:1.5rem;overflow-x:auto}
    h2{margin-bottom:1rem}
    .filter-bar{display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap}
    .filter-bar input,.filter-bar select{padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem}
    .filter-bar input{flex:1;min-width:150px}
    .btn-search{padding:.5rem 1rem;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.875rem}
    .btn-clear{padding:.5rem 1rem;background:#e2e8f0;color:#475569;border:none;border-radius:6px;cursor:pointer;font-size:.875rem;text-decoration:none}
    .card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{background:#f8fafc;padding:.65rem .9rem;text-align:left;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600}
    td{padding:.65rem .9rem;border-bottom:1px solid #f1f5f9}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#f8fafc}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:500}
    .badge.available{background:#dcfce7;color:#166534}
    .badge.in_use{background:#dbeafe;color:#1d4ed8}
    .badge.maintenance{background:#fef9c3;color:#854d0e}
    .badge.retired{background:#f1f5f9;color:#64748b}
    .empty{text-align:center;padding:3rem;color:#94a3b8}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">Asset Manager</div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="assets.php" class="active">Assets</a>
    <a href="requests.php">Requests <?= $pending_req > 0 ? "($pending_req)" : '' ?></a>
    <a href="maintenance.php">Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <h2>All Assets</h2>

  <form method="GET">
    <div class="filter-bar">
      <input type="text" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Search name or serial...">
      <select name="status">
        <option value="all" <?=$filter_status==='all'?'selected':''?>>All Statuses</option>
        <?php foreach(['available','in_use','maintenance','retired'] as $s): ?>
          <option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=ucfirst(str_replace('_',' ',$s))?></option>
        <?php endforeach; ?>
      </select>
      <select name="category">
        <option value="0">All Categories</option>
        <?php while($cat=$categories->fetch_assoc()): ?>
          <option value="<?=$cat['id']?>" <?=$filter_cat==$cat['id']?'selected':''?>><?=htmlspecialchars($cat['name'])?></option>
        <?php endwhile; ?>
      </select>
      <button type="submit" class="btn-search">Search</button>
      <a href="assets.php" class="btn-clear">Clear</a>
    </div>
  </form>

  <div class="card">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Category</th><th>Serial</th><th>Status</th><th>Assigned To</th><th>Location</th><th>Purchase Date</th><th>Price</th></tr>
      </thead>
      <tbody>
        <?php if($assets->num_rows===0): ?>
          <tr><td colspan="9" class="empty">No assets found.</td></tr>
        <?php else: ?>
          <?php while($row=$assets->fetch_assoc()): ?>
          <tr>
            <td><?=$row['id']?></td>
            <td><strong><?=htmlspecialchars($row['name'])?></strong></td>
            <td><?=htmlspecialchars($row['category_name']??'—')?></td>
            <td style="font-size:.8rem;color:#64748b"><?=htmlspecialchars($row['serial_number']??'—')?></td>
            <td><span class="badge <?=$row['status']?>"><?=ucfirst(str_replace('_',' ',$row['status']))?></span></td>
            <td><?=htmlspecialchars($row['assigned_user']??'—')?></td>
            <td><?=htmlspecialchars($row['location']??'—')?></td>
            <td><?=$row['purchase_date']?date('M d, Y',strtotime($row['purchase_date'])):'—'?></td>
            <td><?=$row['purchase_price']?'$'.number_format($row['purchase_price'],2):'—'?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>