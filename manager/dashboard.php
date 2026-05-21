<?php
// ============================================================
//  manager/dashboard.php
//  Place at: C:/xampp/htdocs/asset_management/manager/dashboard.php
// ============================================================
require_once '../auth.php';
check_role('manager');
require_once '../db.php';

// ── Stats ────────────────────────────────────────────────────
$total_assets = $conn->query("SELECT COUNT(*) AS c FROM assets")->fetch_assoc()['c'];
$available    = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status='available'")->fetch_assoc()['c'];
$in_use       = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status='in_use'")->fetch_assoc()['c'];
$pending_req  = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];

// ── Pending requests (manager acts on these) ─────────────────
$requests = $conn->query("
    SELECT ar.id, u.name AS user_name, a.name AS asset_name,
           ar.request_type, ar.status, ar.reason, ar.requested_at
    FROM asset_requests ar
    JOIN users  u ON u.id = ar.user_id
    JOIN assets a ON a.id = ar.asset_id
    WHERE ar.status = 'pending'
    ORDER BY ar.requested_at ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard — Asset Management</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; display: flex; min-height: 100vh; }

    .sidebar {
      width: 230px; min-height: 100vh; background: #0f172a;
      display: flex; flex-direction: column; padding: 1.5rem 0; flex-shrink: 0;
    }
    .sidebar .brand { color: #fff; font-size: 1.05rem; font-weight: 600; padding: 0 1.25rem 1.5rem; border-bottom: 1px solid #1e293b; }
    .sidebar .brand span { color: #34d399; }
    .sidebar nav { padding: 1rem 0; flex: 1; }
    .sidebar nav a {
      display: flex; align-items: center; gap: 10px;
      color: #94a3b8; text-decoration: none; font-size: 0.9rem;
      padding: 0.6rem 1.25rem; transition: background 0.15s, color 0.15s;
    }
    .sidebar nav a:hover, .sidebar nav a.active { background: #1e293b; color: #fff; border-radius: 6px; }
    .sidebar .logout { padding: 0 1.25rem 1rem; }
    .sidebar .logout a {
      display: block; text-align: center; padding: 0.55rem;
      background: #ef4444; color: #fff; border-radius: 8px;
      text-decoration: none; font-size: 0.875rem; font-weight: 500;
    }
    .sidebar .logout a:hover { background: #dc2626; }

    .main { flex: 1; padding: 2rem; overflow-x: hidden; }
    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; }
    .topbar h2 { font-size: 1.3rem; font-weight: 600; }
    .topbar .user-badge { background: #d1fae5; color: #065f46; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

    .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border-radius: 10px; padding: 1.25rem 1rem; box-shadow: 0 1px 6px rgba(0,0,0,0.06); text-align: center; }
    .stat-card .num { font-size: 2rem; font-weight: 700; }
    .stat-card .lbl { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
    .stat-card.blue  .num { color: #4f46e5; }
    .stat-card.green .num { color: #16a34a; }
    .stat-card.amber .num { color: #d97706; }
    .stat-card.red   .num { color: #dc2626; }

    .section-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.9rem; }
    .table-wrap { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; }
    td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
    tr:last-child td { border-bottom: none; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
    .badge.borrow  { background: #e0e7ff; color: #3730a3; }
    .badge.return  { background: #f0fdf4; color: #166534; }
    .badge.repair  { background: #fff7ed; color: #9a3412; }
    .badge.pending { background: #fef9c3; color: #854d0e; }

    .action-btns { display: flex; gap: 6px; }
    .btn-approve {
      padding: 4px 12px; background: #16a34a; color: #fff;
      border: none; border-radius: 6px; font-size: 0.8rem;
      cursor: pointer; text-decoration: none;
    }
    .btn-approve:hover { background: #15803d; }
    .btn-reject {
      padding: 4px 12px; background: #dc2626; color: #fff;
      border: none; border-radius: 6px; font-size: 0.8rem;
      cursor: pointer; text-decoration: none;
    }
    .btn-reject:hover { background: #b91c1c; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">Asset <span>Manager</span></div>
  <nav>
    <a href="dashboard.php" class="active">&#9632; Dashboard</a>
    <a href="assets.php">&#9632; Assets</a>
    <a href="requests.php">&#9632; Requests <?= $pending_req > 0 ? "($pending_req)" : '' ?></a>
    <a href="maintenance.php">&#9632; Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <h2>Dashboard</h2>
    <span class="user-badge">&#9679; Manager — <?= htmlspecialchars($_SESSION['user_name']) ?></span>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card blue">
      <div class="num"><?= $total_assets ?></div>
      <div class="lbl">Total Assets</div>
    </div>
    <div class="stat-card green">
      <div class="num"><?= $available ?></div>
      <div class="lbl">Available</div>
    </div>
    <div class="stat-card amber">
      <div class="num"><?= $in_use ?></div>
      <div class="lbl">In Use</div>
    </div>
    <div class="stat-card red">
      <div class="num"><?= $pending_req ?></div>
      <div class="lbl">Pending Requests</div>
    </div>
  </div>

  <!-- Pending requests -->
  <div class="section-title">Pending Requests — Action Required</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Staff</th>
          <th>Asset</th>
          <th>Type</th>
          <th>Reason</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($requests->num_rows === 0): ?>
          <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem">No pending requests.</td></tr>
        <?php else: ?>
          <?php while ($row = $requests->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['user_name']) ?></td>
            <td><?= htmlspecialchars($row['asset_name']) ?></td>
            <td><span class="badge <?= $row['request_type'] ?>"><?= ucfirst($row['request_type']) ?></span></td>
            <td><?= htmlspecialchars($row['reason'] ?? '—') ?></td>
            <td><?= date('M d, Y', strtotime($row['requested_at'])) ?></td>
            <td>
              <div class="action-btns">
                <a href="requests.php?action=approve&id=<?= $row['id'] ?>" class="btn-approve">Approve</a>
                <a href="requests.php?action=reject&id=<?= $row['id'] ?>"  class="btn-reject">Reject</a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

</body>
</html>