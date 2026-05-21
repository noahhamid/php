<?php
require_once '../auth.php';
check_role('staff');
require_once '../db.php';

$user_id = $_SESSION['user_id'];

$filter = $_GET['status'] ?? 'all';
$where  = $filter !== 'all' ? "AND ar.status = '".$conn->real_escape_string($filter)."'" : '';

$requests = $conn->query("
    SELECT ar.id, a.name AS asset_name, a.serial_number,
           ar.request_type, ar.status, ar.reason,
           ar.requested_at, ar.reviewed_at,
           rev.name AS reviewer_name
    FROM asset_requests ar
    JOIN assets a ON a.id = ar.asset_id
    LEFT JOIN users rev ON rev.id = ar.reviewed_by
    WHERE ar.user_id = $user_id $where
    ORDER BY ar.requested_at DESC
");

$counts = $conn->query("
    SELECT
        SUM(status='pending')  AS pending,
        SUM(status='approved') AS approved,
        SUM(status='rejected') AS rejected
    FROM asset_requests WHERE user_id = $user_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Requests — Asset Management</title>
  <style>
    body{font-family:sans-serif;display:flex;min-height:100vh;margin:0;background:#f1f5f9;color:#1e293b}
    .sidebar{width:200px;background:#1e3a5f;padding:1rem 0;display:flex;flex-direction:column}
    .sidebar .brand{color:#fff;font-weight:700;padding:.75rem 1rem 1rem;border-bottom:1px solid #2d5282}
    .sidebar nav a{display:block;color:#93c5fd;text-decoration:none;padding:.5rem 1rem;font-size:.9rem}
    .sidebar nav a:hover,.sidebar nav a.active{background:#2d5282;color:#fff}
    .sidebar .logout{margin-top:auto;padding:1rem}
    .sidebar .logout a{display:block;text-align:center;background:#ef4444;color:#fff;padding:.5rem;border-radius:6px;text-decoration:none;font-size:.85rem}
    .main{flex:1;padding:1.5rem;overflow-x:auto}
    h2{margin-bottom:1rem}
    .stats{display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
    .stat{background:#fff;border-radius:8px;padding:.9rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);min-width:120px;text-align:center}
    .stat .num{font-size:1.6rem;font-weight:700}
    .stat .lbl{font-size:.78rem;color:#64748b;margin-top:2px}
    .stat.amber .num{color:#d97706}
    .stat.green .num{color:#16a34a}
    .stat.red   .num{color:#dc2626}
    .filter-tabs{display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap}
    .filter-tabs a{padding:.35rem .9rem;border-radius:20px;font-size:.85rem;text-decoration:none;background:#e2e8f0;color:#475569;font-weight:500}
    .filter-tabs a.active{background:#4f46e5;color:#fff}
    .filter-tabs a.active.pending{background:#d97706}
    .filter-tabs a.active.approved{background:#16a34a}
    .filter-tabs a.active.rejected{background:#dc2626}
    .card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{background:#f8fafc;padding:.65rem .9rem;text-align:left;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600}
    td{padding:.65rem .9rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#f8fafc}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:500}
    .badge.pending {background:#fef9c3;color:#854d0e}
    .badge.approved{background:#dcfce7;color:#166534}
    .badge.rejected{background:#fee2e2;color:#991b1b}
    .badge.borrow  {background:#e0e7ff;color:#3730a3}
    .badge.return  {background:#f0fdf4;color:#166534}
    .badge.repair  {background:#fff7ed;color:#9a3412}
    .empty{text-align:center;padding:3rem;color:#94a3b8}
    .new-btn{display:inline-block;margin-bottom:1rem;padding:.5rem 1.1rem;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none;font-size:.875rem;font-weight:500}
    .new-btn:hover{background:#4338ca}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">Asset Portal</div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="my_assets.php">My Assets</a>
    <a href="request.php">New Request</a>
    <a href="my_requests.php" class="active">My Requests</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <h2>My Requests</h2>

  <!-- Summary stats -->
  <div class="stats">
    <div class="stat amber">
      <div class="num"><?= $counts['pending'] ?? 0 ?></div>
      <div class="lbl">Pending</div>
    </div>
    <div class="stat green">
      <div class="num"><?= $counts['approved'] ?? 0 ?></div>
      <div class="lbl">Approved</div>
    </div>
    <div class="stat red">
      <div class="num"><?= $counts['rejected'] ?? 0 ?></div>
      <div class="lbl">Rejected</div>
    </div>
  </div>

  <a href="request.php" class="new-btn">+ New Request</a>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <a href="?status=all"      class="<?= $filter==='all'      ? 'active'          : '' ?>">All</a>
    <a href="?status=pending"  class="<?= $filter==='pending'  ? 'active pending'  : '' ?>">Pending</a>
    <a href="?status=approved" class="<?= $filter==='approved' ? 'active approved' : '' ?>">Approved</a>
    <a href="?status=rejected" class="<?= $filter==='rejected' ? 'active rejected' : '' ?>">Rejected</a>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Asset</th>
          <th>Serial</th>
          <th>Type</th>
          <th>Status</th>
          <th>Reason</th>
          <th>Requested</th>
          <th>Reviewed By</th>
          <th>Reviewed At</th>
        </tr>
      </thead>
      <tbody>
        <?php if($requests->num_rows === 0): ?>
          <tr><td colspan="9" class="empty">No <?= $filter !== 'all' ? $filter : '' ?> requests found.</td></tr>
        <?php else: ?>
          <?php while($row = $requests->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><strong><?= htmlspecialchars($row['asset_name']) ?></strong></td>
            <td style="font-size:.8rem;color:#64748b"><?= htmlspecialchars($row['serial_number'] ?? '—') ?></td>
            <td><span class="badge <?= $row['request_type'] ?>"><?= ucfirst($row['request_type']) ?></span></td>
            <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="<?= htmlspecialchars($row['reason'] ?? '') ?>">
              <?= htmlspecialchars($row['reason'] ?? '—') ?>
            </td>
            <td><?= date('M d, Y', strtotime($row['requested_at'])) ?></td>
            <td><?= htmlspecialchars($row['reviewer_name'] ?? '—') ?></td>
            <td><?= $row['reviewed_at'] ? date('M d, Y', strtotime($row['reviewed_at'])) : '—' ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>