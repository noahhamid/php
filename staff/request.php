<?php
// ============================================================
//  staff/request.php
//  Place at: C:/xampp/htdocs/asset_management/staff/request.php
// ============================================================
require_once '../auth.php';
check_role('staff');
require_once '../db.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id    = intval($_POST['asset_id'] ?? 0);
    $req_type    = $_POST['request_type'] ?? '';
    $reason      = trim($_POST['reason'] ?? '');
    $valid_types = ['borrow', 'return', 'repair'];

    if (!$asset_id || !in_array($req_type, $valid_types)) {
        $error = 'Please select a valid asset and request type.';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for your request.';
    } else {
        // Check asset exists
        $chk = $conn->prepare('SELECT id, status, assigned_to FROM assets WHERE id = ?');
        $chk->bind_param('i', $asset_id);
        $chk->execute();
        $asset = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$asset) {
            $error = 'Asset not found.';
        } elseif ($req_type === 'borrow' && $asset['status'] !== 'available') {
            $error = 'This asset is not available for borrowing.';
        } elseif ($req_type === 'return' && $asset['assigned_to'] != $user_id) {
            $error = 'You can only return assets assigned to you.';
        } else {
            // Check for duplicate pending request
            $dup = $conn->prepare('SELECT id FROM asset_requests WHERE user_id=? AND asset_id=? AND status="pending"');
            $dup->bind_param('ii', $user_id, $asset_id);
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $error = 'You already have a pending request for this asset.';
            } else {
                $stmt = $conn->prepare('INSERT INTO asset_requests (user_id, asset_id, request_type, reason) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iiss', $user_id, $asset_id, $req_type, $reason);
                if ($stmt->execute()) {
                    $success = 'Your request has been submitted and is awaiting approval.';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
                $stmt->close();
            }
            $dup->close();
        }
    }
}

// ── Pre-select asset if coming from dashboard ─────────────────
$preselect_id = intval($_GET['asset_id'] ?? 0);

// ── Load available assets for borrow ─────────────────────────
$available = $conn->query("
    SELECT a.id, a.name, a.serial_number, a.location, c.name AS category
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'available'
    ORDER BY a.name ASC
");

// ── Load assets assigned to this staff (for return/repair) ────
$mine = $conn->query("
    SELECT a.id, a.name, a.serial_number, a.status, c.name AS category
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Request — Asset Management</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; display: flex; min-height: 100vh; }

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

    .main { flex: 1; padding: 2rem; }
    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; }
    .topbar h2 { font-size: 1.3rem; font-weight: 600; }
    .topbar .user-badge { background: #dbeafe; color: #1d4ed8; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

    .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); padding: 2rem; max-width: 680px; }

    .form-group { margin-bottom: 1.2rem; }
    label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px; }
    select, textarea {
      width: 100%; padding: 0.6rem 0.85rem;
      border: 1px solid #d1d5db; border-radius: 8px;
      font-size: 0.925rem; color: #1e293b; outline: none;
      transition: border-color 0.2s; font-family: inherit;
    }
    select:focus, textarea:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }
    textarea { resize: vertical; min-height: 100px; }

    /* Request type tabs */
    .type-tabs { display: flex; gap: 10px; margin-bottom: 1.5rem; }
    .type-tab {
      flex: 1; padding: 0.7rem; border: 2px solid #e2e8f0;
      border-radius: 10px; text-align: center; cursor: pointer;
      font-size: 0.875rem; font-weight: 500; color: #64748b;
      transition: all 0.15s; user-select: none;
    }
    .type-tab:hover { border-color: #6366f1; color: #4f46e5; }
    .type-tab.active { border-color: #4f46e5; background: #eef2ff; color: #4f46e5; }
    .type-tab .icon { font-size: 1.4rem; display: block; margin-bottom: 4px; }

    .asset-section { display: none; }
    .asset-section.visible { display: block; }

    .btn {
      padding: 0.65rem 1.5rem; background: #4f46e5; color: #fff;
      border: none; border-radius: 8px; font-size: 0.95rem;
      font-weight: 500; cursor: pointer; transition: background 0.2s;
    }
    .btn:hover { background: #4338ca; }

    .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
    .alert.success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert.error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    .hint { font-size: 0.78rem; color: #94a3b8; margin-top: 4px; }

    /* Asset option cards in select */
    select option { padding: 6px; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">Asset <span>Portal</span></div>
  <nav>
    <a href="dashboard.php">&#9632; Dashboard</a>
    <a href="my_assets.php">&#9632; My Assets</a>
    <a href="request.php" class="active">&#9632; New Request</a>
    <a href="my_requests.php">&#9632; My Requests</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <h2>New Request</h2>
    <span class="user-badge">&#9679; Staff — <?= htmlspecialchars($_SESSION['user_name']) ?></span>
  </div>

  <div class="card">

    <?php if ($success): ?>
      <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Request type selector -->
    <div class="form-group">
      <label>Request Type</label>
      <div class="type-tabs">
        <div class="type-tab active" data-type="borrow" onclick="selectType('borrow', this)">
          <span class="icon">&#128196;</span> Borrow
        </div>
        <div class="type-tab" data-type="return" onclick="selectType('return', this)">
          <span class="icon">&#8617;</span> Return
        </div>
        <div class="type-tab" data-type="repair" onclick="selectType('repair', this)">
          <span class="icon">&#128295;</span> Repair
        </div>
      </div>
    </div>

    <form method="POST" action="request.php">
      <input type="hidden" name="request_type" id="request_type" value="borrow">

      <!-- BORROW — available assets -->
      <div class="asset-section visible" id="section-borrow">
        <div class="form-group">
          <label for="asset_borrow">Select Asset to Borrow</label>
          <select name="asset_id" id="asset_borrow">
            <option value="">— Choose an available asset —</option>
            <?php while ($row = $available->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>"
                <?= ($preselect_id === $row['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['name']) ?>
                (<?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?>)
                — <?= htmlspecialchars($row['location'] ?? 'No location') ?>
              </option>
            <?php endwhile; ?>
          </select>
          <p class="hint">Only available assets are listed here.</p>
        </div>
      </div>

      <!-- RETURN / REPAIR — assigned assets -->
      <div class="asset-section" id="section-return">
        <div class="form-group">
          <label for="asset_mine">Select Asset to Return / Repair</label>
          <select name="asset_id" id="asset_mine" disabled>
            <option value="">— Choose one of your assets —</option>
            <?php
              // Reset pointer
              $mine->data_seek(0);
              while ($row = $mine->fetch_assoc()):
            ?>
              <option value="<?= $row['id'] ?>">
                <?= htmlspecialchars($row['name']) ?>
                (<?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?>)
                — <?= ucfirst($row['status']) ?>
              </option>
            <?php endwhile; ?>
          </select>
          <p class="hint">Only assets currently assigned to you are shown.</p>
        </div>
      </div>

      <!-- Reason -->
      <div class="form-group">
        <label for="reason">Reason / Notes</label>
        <textarea id="reason" name="reason" placeholder="Explain why you need this asset, or describe the issue..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn">Submit Request</button>
    </form>

  </div>
</main>

<script>
  function selectType(type, el) {
    // Update hidden input
    document.getElementById('request_type').value = type;

    // Update tab styles
    document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');

    // Show/hide asset sections
    document.getElementById('section-borrow').classList.remove('visible');
    document.getElementById('section-return').classList.remove('visible');

    const borrowSelect = document.getElementById('asset_borrow');
    const mineSelect   = document.getElementById('asset_mine');

    if (type === 'borrow') {
      document.getElementById('section-borrow').classList.add('visible');
      borrowSelect.disabled = false;
      mineSelect.disabled   = true;
      borrowSelect.name     = 'asset_id';
      mineSelect.name       = 'asset_id_off';
    } else {
      document.getElementById('section-return').classList.add('visible');
      mineSelect.disabled   = false;
      borrowSelect.disabled = true;
      mineSelect.name       = 'asset_id';
      borrowSelect.name     = 'asset_id_off';
    }
  }

  // Init on page load
  selectType('borrow', document.querySelector('.type-tab[data-type="borrow"]'));
</script>

</body>
</html>