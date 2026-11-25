<?php
// UM-03 View and Search User Accounts — WF-03 Manage Users (Administrator only).
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$search = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');
$status = (string)($_GET['status'] ?? '');
$error = '';
try {
    $users = searchAccounts(openDatabase(), $admin, $search, $role, $status);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $error = $e->getMessage();
    $users = [];
}
$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Manage Users</h1>
  <form method="get" action="manage-users.php" class="row g-2 my-3">
    <div class="col-md-5"><label class="visually-hidden" for="q">Search</label>
      <input class="form-control" id="q" name="q" maxlength="100" placeholder="Search name or email" value="<?= e($search) ?>"></div>
    <div class="col-md-2"><label class="visually-hidden" for="role">Role</label><select class="form-select" id="role" name="role">
      <option value="">All roles</option><?php foreach (USER_ROLES as $r): ?><option value="<?= e($r) ?>" <?= $r === $role ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="visually-hidden" for="status">Status</label><select class="form-select" id="status" name="status">
      <option value="">All statuses</option><?php foreach (USER_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary" type="submit">Search</button> <a class="btn btn-outline-secondary" href="manage-users.php">Reset</a></div>
  </form>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div>
  <?php elseif (!$users): ?><div class="alert alert-info">No matching accounts.</div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Manage</th></tr></thead>
    <tbody><?php foreach ($users as $u): ?><tr>
      <td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><?= e($u['status']) ?></td>
      <td><a href="user-details.php?user_id=<?= (int)$u['user_id'] ?>">Manage</a></td>
    </tr><?php endforeach; ?></tbody>
  </table>
  <p class="text-muted"><?= count($users) ?> account(s) shown.</p>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
