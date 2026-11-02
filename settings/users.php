<?php
// settings/users.php - User Accounts & Role-Based Access Control
$page_title = 'Users & Permissions';
require_once __DIR__ . '/../includes/header.php';

requireRole(['Super Administrator']);

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$branches = getBranches($pdo);

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $name = sanitize($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role_id = (int)$_POST['role_id'];
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;

    if (empty($name) || empty($username) || empty($password) || $role_id <= 0) {
        flash('danger', 'Name, Username, Password and Role are required.');
        redirect(BASE_URL . 'settings/users.php');
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (role_id, branch_id, name, username, email, password, status)
                               VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$role_id, $branch_id, $name, $username, $email, $hash]);
        $new_uid = $pdo->lastInsertId();

        logAudit($pdo, 'create', 'users', $new_uid, "Created user account {$username}");
        flash('success', "User '{$name}' ({$username}) created successfully.");
        redirect(BASE_URL . 'settings/users.php');

    } catch (Exception $e) {
        flash('danger', 'Error creating user: ' . $e->getMessage());
        redirect(BASE_URL . 'settings/users.php');
    }
}

$users = $pdo->query("SELECT u.*, r.name as role_name, b.name as branch_name 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.id 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      ORDER BY u.id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-person-badge text-primary me-2"></i>Users & Roles Management</h5>
        <small class="text-muted">Staff accounts, role assignments and branch restriction security</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-user">
        <i class="bi bi-person-plus me-1"></i> Add System User
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Assigned Role</th>
                        <th>Branch Access</th>
                        <th>Account Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($u['role_name']) ?></span>
                            </td>
                            <td>
                                <?php if ($u['branch_id']): ?>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($u['branch_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border">All Branches (Global)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getStatusBadge($u['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: CREATE USER (Zero Modals Rule) -->
<div id="panel-new-user" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Register New Staff Account</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Staff Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Asad Mehmood" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Login Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="asad_sales" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="asad@cellpro.local">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Role *</label>
                <select name="role_id" class="form-select" required>
                    <option value="">-- Choose Role --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Assigned Branch (Leave empty for All Branches)</label>
                <select name="branch_id" class="form-select">
                    <option value="">All Branches (Super Admin Scope)</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Staff Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
