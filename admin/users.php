<?php
require_once '../auth.php';
check_role('admin');
require_once '../db.php';

$success = '';
$error   = '';

// DELETE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $conn->prepare('DELETE FROM users WHERE id=?')->execute() ;
        $s = $conn->prepare('DELETE FROM users WHERE id=?');
        $s->bind_param('i',$id);
        $s->execute() ? $success = 'User deleted.' : $error = 'Delete failed.';
    }
}

// TOGGLE active/inactive
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $s = $conn->prepare('UPDATE users SET is_active = NOT is_active WHERE id=?');
    $s->bind_param('i',$id);
    $s->execute();
    $success = 'User status updated.';
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id  = intval($_POST['edit_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $dept_id  = intval($_POST['department_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } else {
        if ($edit_id > 0) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $s = $conn->prepare('UPDATE users SET name=?,email=?,role=?,department_id=?,password_hash=? WHERE id=?');
                $s->bind_param('sssisi',$name,$email,$role,$dept_id,$hash,$edit_id);
            } else {
                $s = $conn->prepare('UPDATE users SET name=?,email=?,role=?,department_id=? WHERE id=?');
                $s->bind_param('sssii',$name,$email,$role,$dept_id,$edit_id);
            }
            $s->execute() ? $success = 'User updated.' : $error = 'Update failed.';
        } else {
            if (empty($password)) { $error = 'Password is required for new users.'; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $s = $conn->prepare('INSERT INTO users (name,email,password_hash,role,department_id) VALUES (?,?,?,?,?)');
                $s->bind_param('ssssi',$name,$email,$hash,$role,$dept_id);
                $s->execute() ? $success = 'User added.' : $error = 'Could not add user. Email may already exist.';
            }
        }
    }
}

// Load editing user
$editing = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare('SELECT * FROM users WHERE id=?');
    $s->bind_param('i', intval($_GET['edit']));
    $s->execute();
    $editing = $s->get_result()->fetch_assoc();
}

$users       = $conn->query('SELECT u.*,d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.role,u.name');
$departments = $conn->query('SELECT * FROM departments ORDER BY name');
$pending_req = $conn->query("SELECT COUNT(*) AS c FROM asset_requests WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Users — Asset Management</title>
  <style>
    body{font-family:sans-serif;display:flex;min-height:100vh;margin:0;background:#f1f5f9;color:#1e293b}
    .sidebar{width:200px;background:#1e293b;padding:1rem 0;display:flex;flex-direction:column}
    .sidebar .brand{color:#fff;font-weight:700;padding:.75rem 1rem 1rem;border-bottom:1px solid #334155}
    .sidebar nav a{display:block;color:#94a3b8;text-decoration:none;padding:.5rem 1rem;font-size:.9rem}
    .sidebar nav a:hover,.sidebar nav a.active{background:#334155;color:#fff}
    .sidebar .logout{margin-top:auto;padding:1rem}
    .sidebar .logout a{display:block;text-align:center;background:#ef4444;color:#fff;padding:.5rem;border-radius:6px;text-decoration:none;font-size:.85rem}
    .main{flex:1;padding:1.5rem;overflow-x:auto}
    h2{margin-bottom:1rem}
    .alert{padding:.6rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.875rem}
    .alert.success{background:#f0fdf4;border:1px solid #86efac;color:#166534}
    .alert.error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
    .layout{display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start}
    .card{background:#fff;border-radius:8px;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07)}
    .card h3{font-size:.95rem;margin-bottom:1rem}
    .form-group{margin-bottom:.85rem}
    label{display:block;font-size:.8rem;font-weight:600;margin-bottom:3px;color:#374151}
    input,select{width:100%;padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem}
    .hint{font-size:.75rem;color:#94a3b8;margin-top:2px}
    .btn{padding:.5rem 1rem;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.875rem;width:100%;margin-top:.25rem}
    .btn:hover{background:#4338ca}
    .btn-cancel{display:block;text-align:center;font-size:.8rem;color:#64748b;margin-top:6px;text-decoration:none}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{background:#f8fafc;padding:.65rem .9rem;text-align:left;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600}
    td{padding:.65rem .9rem;border-bottom:1px solid #f1f5f9}
    tr:hover td{background:#f8fafc}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:500}
    .badge.admin{background:#ede9fe;color:#5b21b6}
    .badge.manager{background:#d1fae5;color:#065f46}
    .badge.staff{background:#dbeafe;color:#1d4ed8}
    .badge.active{background:#dcfce7;color:#166534}
    .badge.inactive{background:#fee2e2;color:#991b1b}
    a.act{font-size:.8rem;text-decoration:none;margin-right:6px}
    a.edit{color:#0ea5e9}a.toggle{color:#d97706}a.del{color:#ef4444}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">Asset Manager</div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="assets.php">Assets</a>
    <a href="requests.php">Requests <?= $pending_req > 0 ? "($pending_req)" : ''?></a>
    <a href="users.php" class="active">Users</a>
    <a href="maintenance.php">Maintenance</a>
  </nav>
  <div class="logout"><a href="../logout.php">Sign out</a></div>
</aside>

<main class="main">
  <h2>User Management</h2>

  <?php if($success): ?><div class="alert success"><?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>

  <div class="layout">
    <!-- Form -->
    <div class="card">
      <h3><?= $editing ? 'Edit User' : 'Add New User' ?></h3>
      <form method="POST">
        <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editing['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Password <?= $editing ? '(leave blank to keep current)' : '*' ?></label>
          <input type="password" name="password" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role">
            <?php foreach(['admin','manager','staff'] as $r): ?>
              <option value="<?=$r?>" <?= ($editing['role'] ?? 'staff')===$r?'selected':''?>><?=ucfirst($r)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Department</label>
          <select name="department_id">
            <option value="0">— None —</option>
            <?php while($d=$departments->fetch_assoc()): ?>
              <option value="<?=$d['id']?>" <?= ($editing['department_id'] ?? 0)==$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <button type="submit" class="btn"><?= $editing ? 'Update User' : 'Add User' ?></button>
        <?php if($editing): ?><a href="users.php" class="btn-cancel">Cancel</a><?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="card">
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if($users->num_rows===0): ?>
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8">No users found.</td></tr>
          <?php else: ?>
            <?php while($row=$users->fetch_assoc()): ?>
            <tr>
              <td><?=$row['id']?></td>
              <td><?=htmlspecialchars($row['name'])?></td>
              <td style="font-size:.8rem;color:#64748b"><?=htmlspecialchars($row['email'])?></td>
              <td><span class="badge <?=$row['role']?>"><?=ucfirst($row['role'])?></span></td>
              <td><?=htmlspecialchars($row['dept_name']??'—')?></td>
              <td><span class="badge <?=$row['is_active']?'active':'inactive'?>"><?=$row['is_active']?'Active':'Inactive'?></span></td>
              <td>
                <a href="users.php?edit=<?=$row['id']?>" class="act edit">Edit</a>
                <a href="users.php?toggle=<?=$row['id']?>" class="act toggle"><?=$row['is_active']?'Deactivate':'Activate'?></a>
                <?php if($row['id']!=$_SESSION['user_id']): ?>
                  <a href="users.php?delete=<?=$row['id']?>" class="act del" onclick="return confirm('Delete this user?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>