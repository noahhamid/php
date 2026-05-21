<?php

require_once '../auth.php';
check_role('staff');
require_once '../db.php';

$user_id = $_SESSION['user_id'];

// ── Stats for this staff member only ─────────────────────────
$my_assets  = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE assigned_to = $user_id")->fetch_assoc()['c'];
$my_pending = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE user_id = $user_id AND status='pending'")->fetch_assoc()['c'];
$my_approved= $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE user_id = $user_id AND status='approved'")->fetch_assoc()['c'];

// ── Assets assigned to this staff member ─────────────────────
$assigned = $conn->query("
    SELECT a.id, a.name, a.serial_number, a.status, a.location, c.name AS category
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.assigned_to = $user_id
    ORDER BY a.name ASC
");

// ── This staff member's recent requests ──────────────────────
$my_requests = $conn->query("
    SELECT ar.id, a.name AS asset_name, ar.request_type,
           ar.status, ar.reason, ar.requested_at
    FROM asset_requests ar
    JOIN assets a ON a.id = ar.asset_id
    WHERE ar.user_id = $user_id
    ORDER BY ar.requested_at DESC
    LIMIT 8
");

// ── Available assets (staff can request these) ───────────────
$available_assets = $conn->query("
    SELECT a.id, a.name, a.serial_number, a.location, c.name AS category
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'available'
    ORDER BY a.name ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard — Asset Management</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; display: flex; min-height: 100vh; }

    /* Sidebar */
    .sidebar {
      width: 230px; min-height: 100vh; background: #1e3a5f;
      display: flex; flex-direction: column; padding: 1.5rem 0; flex-shrink: 0;
    }
    .sidebar .brand { color: #fff; font-size: 1.05rem; font-weight: 600; padding: 0 1.25rem 1.5rem; border-bottom: 1px solid #2d5282; }
    .sidebar .brand span { color: #60a5fa; }
    .sidebar nav { padding: 1rem 0; flex: 1; }
    .sidebar nav a {
      display: flex; align-items: center; gap: 10px;
      color: #93c5fd; text-decoration: none; font-size: 0.9rem;
      padding: 0.6rem 1.25rem; transition: background 0.15s, color 0.15s;
    }
    .sidebar nav a:hover, .sidebar nav a.active { background: #2d5282; color: #fff; border-radius: 6px; }
    .sidebar .logout { padding: 0 1.25rem 1rem; }
    .sidebar .logout a {
      display: block; text-align: center; padding: 0.55rem;
      background: #ef4444; color: #fff; border-radius: 8px;
      text-decoration: none; font-size: 0.875rem; font-weight: 500;
    }
    .sidebar .logout a:hover { background: #dc2626; }

    /* Main */
    .main { flex: 1; padding: 2rem; overflow-x: hidden; }
    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; }
    .topbar h2 { font-size: 1.3rem; font-weight: 600; }
    .topbar .user-badge { background: #dbeafe; color: #1d4ed8; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

    /* Stats */
    .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border-radius: 10px; padding: 1.25rem 1rem; box-shadow: 0 1px 6px rgba(0,0,0,0.06); text-align: center; }
    .stat-card .num { font-size: 2rem; font-weight: 700; }
    .stat-card .lbl { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
    .stat-card.blue   .num { color: #4f46e5; }
    .stat-card.amber  .num { color: #d97706; }
    .stat-card.green  .num { color: #16a34a; }

    /* Grid layout for two columns */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

    .section-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.9rem; color: #1e293b; }
    .table-wrap { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 0.7rem 1rem; border-bottom: 1px solid #e2e8f0; }
    td { padding: 0.7rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
    tr:last-child td { border-bottom: none; }

    /* Badges */
    .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
    .badge.available   { background: #dcfce7; color: #166534; }
    .badge.in_use      { background: #dbeafe; color: #1d4ed8; }
    .badge.maintenance { background: #fef9c3; color: #854d0e; }
    .badge.retired     { background: #f1f5f9; color: #64748b; }
    .badge.pending     { background: #fef9c3; color: #854d0e; }
    .badge.approved    { background: #dcfce7; color: #166534; }
    .badge.rejected    { background: #fee2e2; color: #991b1b; }
    .badge.borrow      { background: #e0e7ff; color: #3730a3; }
    .badge.return      { background: #f0fdf4; color: #166534; }
    .badge.repair      { background: #fff7ed; color: #9a3412; }

    /* Request button */
    .btn-request {
      padding: 3px 10px; background: #4f46e5; color: #fff;
      border: none; border-radius: 6px; font-size: 0.78rem;
      cursor: pointer; text-decoration: none;
    }
    .btn-request:hover { background: #4338ca; }

    /* New request CTA */
    .cta-bar {
      background: #4f46e5; color: #fff; border-radius: 10px;
      padding: 1rem 1.25rem; margin-bottom: 2rem;
      display: flex; justify-content: space-between; align-items: center;
    }
    .cta-bar p { font-size: 0.9rem; }
    .cta-bar p strong { display: block; font-size: 1rem; margin-bottom: 2px; }
    .cta-bar a {
      background: #fff; color: #4f46e5; padding: 0.5rem 1.1rem;
      border-radius: 8px; text-decoration: none; font-size: 0.875rem; font-weight: 600;
      white-space: nowrap;
    }
    .cta-bar a:hover { background: #e0e7ff; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">Asset <span>Portal</span></div>
  <nav>
    <a href="dashboard.php" class="active">&#9632; Dashboard</a>
    <a href="my_assets.php">&#9632; My Assets</a>
    <a href="request.php">&#9632; New Request</a>
    <a href="my_requests.php">&#9632; My Requests</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
    <span class="user-badge">&#9679; Staff</span>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card blue">
      <div class="num"><?= $my_assets ?></div>
      <div class="lbl">My Assets</div>
    </div>
    <div class="stat-card amber">
      <div class="num"><?= $my_pending ?></div>
      <div class="lbl">Pending Requests</div>
    </div>
    <div class="stat-card green">
      <div class="num"><?= $my_approved ?></div>
      <div class="lbl">Approved Requests</div>
    </div>
  </div>

  <!-- CTA -->
  <div class="cta-bar">
    <p><strong>Need a device or equipment?</strong>Browse available assets and submit a borrow request.</p>
    <a href="request.php">+ New Request</a>
  </div>

  <!-- Two column grid -->
  <div class="grid-2">

    <!-- My assigned assets -->
    <div>
      <div class="section-title">My Assigned Assets</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Asset</th><th>Category</th><th>Status</th><th>Location</th></tr>
          </thead>
          <tbody>
            <?php if ($assigned->num_rows === 0): ?>
              <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem">No assets assigned.</td></tr>
            <?php else: ?>
              <?php while ($row = $assigned->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
                <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Available assets to request -->
    <div>
      <div class="section-title">Available Assets</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Asset</th><th>Category</th><th>Location</th><th></th></tr>
          </thead>
          <tbody>
            <?php if ($available_assets->num_rows === 0): ?>
              <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem">No assets available.</td></tr>
            <?php else: ?>
              <?php while ($row = $available_assets->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
                <td><a href="request.php?asset_id=<?= $row['id'] ?>" class="btn-request">Request</a></td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- My recent requests -->
  <div class="section-title">My Recent Requests</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Asset</th><th>Type</th><th>Status</th><th>Reason</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php if ($my_requests->num_rows === 0): ?>
          <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:1.5rem">No requests yet.</td></tr>
        <?php else: ?>
          <?php while ($row = $my_requests->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['asset_name']) ?></td>
            <td><span class="badge <?= $row['request_type'] ?>"><?= ucfirst($row['request_type']) ?></span></td>
            <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
            <td><?= htmlspecialchars($row['reason'] ?? '—') ?></td>
            <td><?= date('M d, Y', strtotime($row['requested_at'])) ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

</body>
</html>