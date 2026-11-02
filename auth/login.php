<?php
// auth/login.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . 'index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare("SELECT u.*, r.name as role_name 
                                   FROM users u 
                                   JOIN roles r ON u.role_id = r.id 
                                   WHERE u.username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact administrator.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_role_id'] = $user['role_id'];
                    $_SESSION['user_role_name'] = $user['role_name'];
                    $_SESSION['user_branch_id'] = $user['branch_id'];
                    $_SESSION['active_branch_id'] = $user['branch_id'] ?: 1;

                    logAudit($pdo, 'login', 'users', $user['id'], 'User logged in successfully');
                    flash('success', 'Welcome back, ' . $user['name'] . '!');
                    redirect(BASE_URL . 'index.php');
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - CellPro ERP</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="container" style="max-width: 440px;">
    <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px;">
                <i class="bi bi-phone fs-2"></i>
            </div>
            <h4 class="fw-bold m-0">CellPro ERP</h4>
            <p class="text-muted small">Multi-Branch Mobile Shop Management</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="admin" required autofocus value="admin">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required value="admin123">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Store
            </button>
        </form>

        <div class="border-top pt-3 text-center">
            <small class="text-muted d-block">Demo Credentials:</small>
            <span class="badge bg-light text-dark border">User: <strong>admin</strong></span>
            <span class="badge bg-light text-dark border">Pass: <strong>admin123</strong></span>
        </div>
    </div>
</div>

</body>
</html>
