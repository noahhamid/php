<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Fetch user by email
        $stmt = $conn->prepare('SELECT id, name, role, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            // Login success — store session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'manager':
                    header('Location: manager/dashboard.php');
                    break;
                default:
                    header('Location: staff/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Asset Management</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f1f5f9;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 400px;
    }

    .logo {
      text-align: center;
      margin-bottom: 1.75rem;
    }

    .logo h1 {
      font-size: 1.4rem;
      font-weight: 600;
      color: #1e293b;
    }

    .logo p {
      font-size: 0.875rem;
      color: #64748b;
      margin-top: 4px;
    }

    .form-group {
      margin-bottom: 1.1rem;
    }

    label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: #374151;
      margin-bottom: 6px;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 0.6rem 0.85rem;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.95rem;
      color: #1e293b;
      outline: none;
      transition: border-color 0.2s;
    }

    input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }

    .btn {
      width: 100%;
      padding: 0.7rem;
      background: #4f46e5;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      margin-top: 0.5rem;
      transition: background 0.2s;
    }

    .btn:hover { background: #4338ca; }

    .error {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #b91c1c;
      padding: 0.65rem 0.9rem;
      border-radius: 8px;
      font-size: 0.875rem;
      margin-bottom: 1.1rem;
    }

    .hint {
      margin-top: 1.25rem;
      padding-top: 1.25rem;
      border-top: 1px solid #f1f5f9;
      font-size: 0.8rem;
      color: #94a3b8;
      text-align: center;
      line-height: 1.7;
    }
  </style>
</head>
<body>

<div class="card">
  <div class="logo">
    <h1>Asset Management</h1>
    <p>Sign in to your account</p>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="you@company.com" required autofocus>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="••••••••" required>
    </div>

    <button type="submit" class="btn">Sign in</button>
  </form>

  <div class="hint">
    Test accounts (password: <strong>password123</strong>)<br>
    admin@company.com &nbsp;|&nbsp; manager@company.com &nbsp;|&nbsp; staff@company.com
  </div>
</div>

</body>
</html>