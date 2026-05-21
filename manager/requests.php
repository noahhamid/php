<?php
// ============================================================
//  manager/requests.php
//  Place at: C:/xampp/htdocs/asset_management/manager/requests.php
// ============================================================
require_once '../auth.php';
check_role(['admin', 'manager']);
require_once '../db.php';

$reviewer_id = $_SESSION['user_id'];
$success     = '';
$error       = '';

// ── Handle approve / reject action ───────────────────────────
if (isset($_GET['action'], $_GET['id'])) {
    $action     = $_GET['action'];
    $request_id = intval($_GET['id']);

    if (!in_array($action, ['approve', 'reject'])) {
        $error = 'Invalid action.';
    } else {
        // Fetch the request
        $stmt = $conn->prepare('SELECT ar.*, a.status AS asset_status FROM asset_requests ar JOIN assets a ON a.id = ar.asset_id WHERE ar.id = ?');
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$req) {
            $error = 'Request not found.';
        } elseif ($req['status'] !== 'pending') {
            $error = 'This request has already been reviewed.';
        } else {
            $new_status = ($action === 'approve') ? 'approved' : 'rejected';
            $now        = date('Y-m-d H:i:s');

            // Update request status
            $upd = $conn->prepare('UPDATE asset_requests SET status=?, reviewed_by=?, reviewed_at=? WHERE id=?');
            $upd->bind_param('sisi', $new_status, $reviewer_id, $now, $request_id);
            $upd->execute();
            $upd->close();

            // If approved, update asset status too
            if ($action === 'approve') {
                if ($req['request_type'] === 'borrow') {
                    // Mark asset as in_use and assign to requester
                    $upd2 = $conn->prepare('UPDATE assets SET status="in_use", assigned_to=? WHERE id=?');
                    $upd2->bind_param('ii', $req['user_id'], $req['asset_id']);
                    $upd2->execute();
                    $upd2->close();
                } elseif ($req['request_type'] === 'return') {
                    // Mark asset as available and unassign
                    $upd2 = $conn->prepare('UPDATE assets SET status="available", assigned_to=NULL WHERE id=?');
                    $upd2->bind_param('i', $req['asset_id']);
                    $upd2->execute();
                    $upd2->close();
                } elseif ($req['request_type'] === 'repair') {
                    // Mark asset as under maintenance
                    $upd2 = $conn->prepare('UPDATE assets SET status="maintenance" WHERE id=?');
                    $upd2->bind_param('i', $req['asset_id']);
                    $upd2->execute();
                    $upd2->close();

                    // Also create a maintenance log entry
                    $log = $conn->prepare('INSERT INTO maintenance_logs (asset_id, reported_by, description, status) VALUES (?, ?, ?, "pending")');
                    $desc = 'Repair requested by staff via asset request #' . $request_id;
                    $log->bind_param('iis', $req['asset_id'], $req['user_id'], $desc);
                    $log->execute();
                    $log->close();
                }
                $success = 'Request #' . $request_id . ' has been approved.';
            } else {
                $success = 'Request #' . $request_id . ' has been rejected.';
            }
        }
    }
}

// ── Filters ───────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter_status, $allowed_filters)) $filter_status = 'pending';

$where = $filter_status !== 'all' ? "WHERE ar.status = '$filter_status'" : '';

// ── Load requests ─────────────────────────────────────────────
$requests = $conn->query("
    SELECT ar.id, u.name AS user_name, a.name AS asset_name,
           ar.request_type, ar.status, ar.reason,
           ar.requested_at, ar.reviewed_at,
           rev.name AS reviewer_name
    FROM asset_requests ar
    JOIN users  u   ON u.id  = ar.user_id
    JOIN assets a   ON a.id  = ar.asset_id
    LEFT JOIN users rev ON rev.id = ar.reviewed_by
    $where
    ORDER BY ar.requested_at DESC
");

// Pending count for sidebar badge
$pending_count = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requests — Asset Management</title>
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

    /* Filter tabs */
    .filter-tabs { display: flex; gap: 8px; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .filter-tabs a {
      padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem;
      text-decoration: none; font-weight: 500;
      background: #e2e8f0; color: #475569; transition: all 0.15s;
    }
    .filter-tabs a:hover { background: #cbd5e1; }
    .filter-tabs a.active { background: #4f46e5; color: #fff; }
    .filter-tabs a.amber  { }
    .filter-tabs a.active.pending  { background: #d97706; }
    .filter-tabs a.active.approved { background: #16a34a; }
    .filter-tabs a.active.rejected { background: #dc2626; }

    .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
    .alert.success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert.error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    .table-wrap { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; }
    td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f8fafc; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
    .badge.pending  { background: #fef9c3; color: #854d0e; }
    .badge.approved { background: #dcfce7; color: #166534; }
    .badge.rejected { background: #fee2e2; color: #991b1b; }
    .badge.borrow   { background: #e0e7ff; color: #3730a3; }
    .badge.return   { background: #f0fdf4; color: #166534; }
    .badge.repair   { background: #fff7ed; color: #9a3412; }

    .action-btns { display: flex; gap: 6px; }
    .btn-approve {
      padding: 5px 14px; background: #16a34a; color: #fff;
      border: none; border-radius: 6px; font-size: 0.8rem;
      cursor: pointer; text-decoration: none; font-weight: 500;
    }
    .btn-approve:hover { background: #15803d; }
    .btn-reject {
      padding: 5px 14px; background: #dc2626; color: #fff;
      border: none; border-radius: 6px; font-size: 0.8rem;
      cursor: pointer; text-decoration: none; font-weight: 500;
    }
    .btn-reject:hover { background: #b91c1c; }

    .empty { text-align: center; color: #94a3b8; padding: 3rem 1rem; font-size: 0.9rem; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">Asset <span>Manager</span></div>
  <nav>
    <a href="dashboard.php">&#9632; Dashboard</a>
    <a href="assets.php">&#9632; Assets</a>
    <a href="requests.php" class="active">&#9632; Requests <?= $pending_count > 0 ? "($pending_count)" : '' ?></a>
    <a href="maintenance.php">&#9632; Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <h2>Asset Requests</h2>
    <span class="user-badge">&#9679; <?= ucfirst($_SESSION['user_role']) ?> — <?= htmlspecialchars($_SESSION['user_name']) ?></span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <a href="?status=pending"  class="<?= $filter_status==='pending'  ? 'active pending'  : '' ?>">&#9679; Pending <?= $pending_count > 0 ? "($pending_count)" : '' ?></a>
    <a href="?status=approved" class="<?= $filter_status==='approved' ? 'active approved' : '' ?>">&#10003; Approved</a>
    <a href="?status=rejected" class="<?= $filter_status==='rejected' ? 'active rejected' : '' ?>">&#10007; Rejected</a>
    <a href="?status=all"      class="<?= $filter_status==='all'      ? 'active'          : '' ?>">All Requests</a>
  </div>

  <!-- Requests table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Staff</th>
          <th>Asset</th>
          <th>Type</th>
          <th>Reason</th>
          <th>Requested</th>
          <th>Status</th>
          <?php if ($filter_status === 'pending' || $filter_status === 'all'): ?>
          <th>Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($requests->num_rows === 0): ?>
          <tr><td colspan="8" class="empty">No <?= $filter_status !== 'all' ? $filter_status : '' ?> requests found.</td></tr>
        <?php else: ?>
          <?php while ($row = $requests->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['user_name']) ?></td>
            <td><?= htmlspecialchars($row['asset_name']) ?></td>
            <td><span class="badge <?= $row['request_type'] ?>"><?= ucfirst($row['request_type']) ?></span></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="<?= htmlspecialchars($row['reason'] ?? '') ?>">
              <?= htmlspecialchars($row['reason'] ?? '—') ?>
            </td>
            <td><?= date('M d, Y', strtotime($row['requested_at'])) ?></td>
            <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
            <?php if ($filter_status === 'pending' || $filter_status === 'all'): ?>
            <td>
              <?php if ($row['status'] === 'pending'): ?>
                <div class="action-btns">
                  <a href="?action=approve&id=<?= $row['id'] ?>&status=<?= $filter_status ?>" class="btn-approve"
                     onclick="return confirm('Approve this request?')">Approve</a>
                  <a href="?action=reject&id=<?= $row['id'] ?>&status=<?= $filter_status ?>"  class="btn-reject"
                     onclick="return confirm('Reject this request?')">Reject</a>
                </div>
              <?php else: ?>
                <span style="color:#94a3b8;font-size:0.8rem">
                  <?= ucfirst($row['status']) ?> by <?= htmlspecialchars($row['reviewer_name'] ?? '—') ?>
                </span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

</body>
</html>