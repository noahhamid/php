<?php
// ============================================================
//  admin/assets.php
//  Place at: C:/xampp/htdocs/asset_management/admin/assets.php
// ============================================================
require_once '../auth.php';
check_role('admin');
require_once '../db.php';

$success = '';
$error   = '';

// ── DELETE asset ──────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $del = $conn->prepare('DELETE FROM assets WHERE id = ?');
    $del->bind_param('i', $del_id);
    $del->execute() ? $success = 'Asset deleted.' : $error = 'Could not delete asset.';
    $del->close();
}

// ── ADD / EDIT asset ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id       = intval($_POST['edit_id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $serial        = trim($_POST['serial_number'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $status        = $_POST['status'] ?? 'available';
    $location      = trim($_POST['location'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? null;
    $price         = floatval($_POST['purchase_price'] ?? 0);
    $notes         = trim($_POST['notes'] ?? '');
    $valid_statuses = ['available','in_use','maintenance','retired'];

    if (empty($name)) {
        $error = 'Asset name is required.';
    } elseif (!in_array($status, $valid_statuses)) {
        $error = 'Invalid status.';
    } else {
        if ($edit_id > 0) {
            // UPDATE
            $stmt = $conn->prepare('UPDATE assets SET name=?, serial_number=?, category_id=?, status=?, location=?, purchase_date=?, purchase_price=?, notes=? WHERE id=?');
            $stmt->bind_param('ssisssdi', $name, $serial, $category_id, $status, $location, $purchase_date, $price, $notes, $edit_id);
            $stmt->execute() ? $success = 'Asset updated successfully.' : $error = 'Update failed.';
            $stmt->close();
        } else {
            // INSERT
            $stmt = $conn->prepare('INSERT INTO assets (name, serial_number, category_id, status, location, purchase_date, purchase_price, notes) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->bind_param('ssisssds', $name, $serial, $category_id, $status, $location, $purchase_date, $price, $notes);
            $stmt->execute() ? $success = 'Asset added successfully.' : $error = 'Could not add asset.';
            $stmt->close();
        }
    }
}

// ── Load asset being edited ───────────────────────────────────
$editing = null;
if (isset($_GET['edit'])) {
    $e = $conn->prepare('SELECT * FROM assets WHERE id = ?');
    $e->bind_param('i', intval($_GET['edit']));
    $e->execute();
    $editing = $e->get_result()->fetch_assoc();
    $e->close();
}

// ── Search / filter ───────────────────────────────────────────
$search         = trim($_GET['q'] ?? '');
$filter_status  = $_GET['status'] ?? 'all';
$filter_cat     = intval($_GET['category'] ?? 0);

$where_parts = [];
if ($search)                        $where_parts[] = "(a.name LIKE '%".  $conn->real_escape_string($search)."%' OR a.serial_number LIKE '%".$conn->real_escape_string($search)."%')";
if ($filter_status !== 'all')       $where_parts[] = "a.status = '".$conn->real_escape_string($filter_status)."'";
if ($filter_cat > 0)                $where_parts[] = "a.category_id = $filter_cat";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$assets = $conn->query("
    SELECT a.*, c.name AS category_name, u.name AS assigned_user
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN users      u ON u.id = a.assigned_to
    $where
    ORDER BY a.created_at DESC
");

// ── Load categories for dropdowns ────────────────────────────
$categories = $conn->query('SELECT * FROM categories ORDER BY name ASC');

// ── Pending count for sidebar ─────────────────────────────────
$pending_req = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assets — Asset Management</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; display: flex; min-height: 100vh; }

    .sidebar {
      width: 230px; min-height: 100vh; background: #1e293b;
      display: flex; flex-direction: column; padding: 1.5rem 0; flex-shrink: 0;
    }
    .sidebar .brand { color: #fff; font-size: 1.05rem; font-weight: 600; padding: 0 1.25rem 1.5rem; border-bottom: 1px solid #334155; }
    .sidebar .brand span { color: #818cf8; }
    .sidebar nav { padding: 1rem 0; flex: 1; }
    .sidebar nav a {
      display: flex; align-items: center; gap: 10px;
      color: #94a3b8; text-decoration: none; font-size: 0.9rem;
      padding: 0.6rem 1.25rem; transition: background 0.15s, color 0.15s;
    }
    .sidebar nav a:hover, .sidebar nav a.active { background: #334155; color: #fff; border-radius: 6px; }
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
    .topbar .user-badge { background: #e0e7ff; color: #4338ca; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

    /* Layout: form left, table right on wide screens */
    .layout { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 1000px) { .layout { grid-template-columns: 1fr; } }

    /* Form card */
    .form-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); padding: 1.5rem; position: sticky; top: 2rem; }
    .form-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 1.1rem; color: #1e293b; }
    .form-group { margin-bottom: 1rem; }
    label { display: block; font-size: 0.8rem; font-weight: 500; color: #374151; margin-bottom: 4px; }
    input[type="text"], input[type="number"], input[type="date"],
    select, textarea {
      width: 100%; padding: 0.55rem 0.8rem;
      border: 1px solid #d1d5db; border-radius: 8px;
      font-size: 0.875rem; color: #1e293b; outline: none;
      transition: border-color 0.2s; font-family: inherit;
    }
    input:focus, select:focus, textarea:focus {
      border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
    }
    textarea { resize: vertical; min-height: 70px; }
    .btn-submit {
      width: 100%; padding: 0.65rem; background: #4f46e5; color: #fff;
      border: none; border-radius: 8px; font-size: 0.925rem;
      font-weight: 500; cursor: pointer; transition: background 0.2s; margin-top: 0.25rem;
    }
    .btn-submit:hover { background: #4338ca; }
    .btn-cancel {
      display: block; text-align: center; margin-top: 8px;
      color: #64748b; font-size: 0.8rem; text-decoration: none;
    }
    .btn-cancel:hover { color: #dc2626; }

    /* Right side */
    .right-side {}

    /* Search / filter bar */
    .filter-bar { display: flex; gap: 8px; margin-bottom: 1rem; flex-wrap: wrap; }
    .filter-bar input[type="text"] { flex: 1; min-width: 160px; }
    .filter-bar select { width: auto; }
    .btn-search {
      padding: 0.55rem 1rem; background: #4f46e5; color: #fff;
      border: none; border-radius: 8px; font-size: 0.875rem; cursor: pointer;
    }
    .btn-search:hover { background: #4338ca; }
    .btn-clear {
      padding: 0.55rem 1rem; background: #e2e8f0; color: #475569;
      border: none; border-radius: 8px; font-size: 0.875rem; cursor: pointer; text-decoration: none;
    }
    .btn-clear:hover { background: #cbd5e1; }

    /* Table */
    .table-wrap { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 0.7rem 1rem; border-bottom: 1px solid #e2e8f0; }
    td { padding: 0.7rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f8fafc; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
    .badge.available   { background: #dcfce7; color: #166534; }
    .badge.in_use      { background: #dbeafe; color: #1d4ed8; }
    .badge.maintenance { background: #fef9c3; color: #854d0e; }
    .badge.retired     { background: #f1f5f9; color: #64748b; }

    .action-btns { display: flex; gap: 5px; }
    .btn-edit {
      padding: 4px 10px; background: #0ea5e9; color: #fff;
      border-radius: 6px; font-size: 0.78rem; text-decoration: none; font-weight: 500;
    }
    .btn-edit:hover { background: #0284c7; }
    .btn-del {
      padding: 4px 10px; background: #ef4444; color: #fff;
      border-radius: 6px; font-size: 0.78rem; text-decoration: none; font-weight: 500;
    }
    .btn-del:hover { background: #dc2626; }

    .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
    .alert.success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert.error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    .empty { text-align: center; color: #94a3b8; padding: 3rem 1rem; font-size: 0.9rem; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">Asset <span>Manager</span></div>
  <nav>
    <a href="dashboard.php">&#9632; Dashboard</a>
    <a href="assets.php" class="active">&#9632; Assets</a>
    <a href="requests.php">&#9632; Requests <?= $pending_req > 0 ? "($pending_req)" : '' ?></a>
    <a href="users.php">&#9632; Users</a>
    <a href="maintenance.php">&#9632; Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <h2><?= $editing ? 'Edit Asset' : 'Assets' ?></h2>
    <span class="user-badge">&#9679; Admin — <?= htmlspecialchars($_SESSION['user_name']) ?></span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="layout">

    <!-- ADD / EDIT FORM -->
    <div class="form-card">
      <h3><?= $editing ? '&#9998; Edit Asset' : '+ Add New Asset' ?></h3>
      <form method="POST" action="assets.php<?= $editing ? '?edit='.$editing['id'] : '' ?>">
        <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">

        <div class="form-group">
          <label>Asset Name *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? $_POST['name'] ?? '') ?>" placeholder="e.g. Dell Latitude 5520" required>
        </div>

        <div class="form-group">
          <label>Serial Number</label>
          <input type="text" name="serial_number" value="<?= htmlspecialchars($editing['serial_number'] ?? $_POST['serial_number'] ?? '') ?>" placeholder="e.g. SN-001-DELL">
        </div>

        <div class="form-group">
          <label>Category</label>
          <select name="category_id">
            <option value="">— Select category —</option>
            <?php
              $categories->data_seek(0);
              while ($cat = $categories->fetch_assoc()):
                $sel = ($editing['category_id'] ?? $_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '';
            ?>
              <option value="<?= $cat['id'] ?>" <?= $sel ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <?php foreach (['available','in_use','maintenance','retired'] as $s):
              $sel = ($editing['status'] ?? $_POST['status'] ?? 'available') === $s ? 'selected' : '';
            ?>
              <option value="<?= $s ?>" <?= $sel ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Location</label>
          <input type="text" name="location" value="<?= htmlspecialchars($editing['location'] ?? $_POST['location'] ?? '') ?>" placeholder="e.g. IT Room">
        </div>

        <div class="form-group">
          <label>Purchase Date</label>
          <input type="date" name="purchase_date" value="<?= htmlspecialchars($editing['purchase_date'] ?? $_POST['purchase_date'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Purchase Price ($)</label>
          <input type="number" name="purchase_price" step="0.01" min="0" value="<?= htmlspecialchars($editing['purchase_price'] ?? $_POST['purchase_price'] ?? '') ?>" placeholder="0.00">
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" placeholder="Optional notes..."><?= htmlspecialchars($editing['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-submit"><?= $editing ? 'Update Asset' : 'Add Asset' ?></button>
        <?php if ($editing): ?>
          <a href="assets.php" class="btn-cancel">Cancel editing</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- ASSET LIST -->
    <div class="right-side">

      <!-- Search & filter -->
      <form method="GET" action="assets.php">
        <div class="filter-bar">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or serial...">
          <select name="status">
            <option value="all" <?= $filter_status==='all' ? 'selected' : '' ?>>All Statuses</option>
            <?php foreach (['available','in_use','maintenance','retired'] as $s): ?>
              <option value="<?= $s ?>" <?= $filter_status===$s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="category">
            <option value="0">All Categories</option>
            <?php
              $categories->data_seek(0);
              while ($cat = $categories->fetch_assoc()):
            ?>
              <option value="<?= $cat['id'] ?>" <?= $filter_cat===$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endwhile; ?>
          </select>
          <button type="submit" class="btn-search">Search</button>
          <a href="assets.php" class="btn-clear">Clear</a>
        </div>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Category</th>
              <th>Serial</th>
              <th>Status</th>
              <th>Assigned To</th>
              <th>Location</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assets->num_rows === 0): ?>
              <tr><td colspan="8" class="empty">No assets found.</td></tr>
            <?php else: ?>
              <?php while ($row = $assets->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                <td style="font-size:0.8rem;color:#64748b"><?= htmlspecialchars($row['serial_number'] ?? '—') ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
                <td><?= htmlspecialchars($row['assigned_user'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
                <td>
                  <div class="action-btns">
                    <a href="assets.php?edit=<?= $row['id'] ?>" class="btn-edit">Edit</a>
                    <a href="assets.php?delete=<?= $row['id'] ?>" class="btn-del"
                       onclick="return confirm('Delete this asset? This cannot be undone.')">Delete</a>
                  </div>
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