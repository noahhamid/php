<?php
require_once '../auth.php';
check_role('staff');
require_once '../db.php';

$user_id = $_SESSION['user_id'];

$assets = $conn->query("
    SELECT a.*, c.name AS category_name
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.assigned_to = $user_id
    ORDER BY a.name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Assets — Asset Management</title>
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
    .card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{background:#f8fafc;padding:.65rem .9rem;text-align:left;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600}
    td{padding:.65rem .9rem;border-bottom:1px solid #f1f5f9}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#f8fafc}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:500}
    .badge.available  {background:#dcfce7;color:#166534}
    .badge.in_use     {background:#dbeafe;color:#1d4ed8}
    .badge.maintenance{background:#fef9c3;color:#854d0e}
    .badge.retired    {background:#f1f5f9;color:#64748b}
    .empty{text-align:center;padding:3rem;color:#94a3b8}
    .action-link{font-size:.8rem;text-decoration:none;color:#4f46e5}
    .action-link:hover{text-decoration:underline}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">Asset Portal</div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="my_assets.php" class="active">My Assets</a>
    <a href="request.php">New Request</a>
    <a href="my_requests.php">My Requests</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <h2>My Assets</h2>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Asset Name</th>
          <th>Category</th>
          <th>Serial Number</th>
          <th>Status</th>
          <th>Location</th>
          <th>Notes</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if($assets->num_rows === 0): ?>
          <tr><td colspan="8" class="empty">No assets assigned to you yet.</td></tr>
        <?php else: ?>
          <?php while($row = $assets->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
            <td><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
            <td style="font-size:.8rem;color:#64748b"><?= htmlspecialchars($row['serial_number'] ?? '—') ?></td>
            <td><span class="badge <?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
            <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="<?= htmlspecialchars($row['notes'] ?? '') ?>">
              <?= htmlspecialchars($row['notes'] ?? '—') ?>
            </td>
            <td>
              <a href="request.php?asset_id=<?= $row['id'] ?>" class="action-link">Return / Repair</a>
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