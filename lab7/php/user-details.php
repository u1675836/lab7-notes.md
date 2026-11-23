<?php
// UM-03 step 4: show permitted account details, with links to UM-06/07/08.
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid user ID'); }
$account = getAccountForAdmin(openDatabase(), $admin, $id);
if (!$account) { http_response_code(404); exit('Account not found'); }
$isSelf = $account['user_id'] === (int)$admin['user_id'];
$pageTitle = 'Account Details';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Account: <?= e($account['full_name']) ?></h1>
  <div class="card my-3" style="max-width:640px"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-4">User ID</dt><dd class="col-8"><?= (int)$account['user_id'] ?></dd>
      <dt class="col-4">Name</dt><dd class="col-8"><?= e($account['full_name']) ?></dd>
      <dt class="col-4">Email</dt><dd class="col-8"><?= e($account['email']) ?></dd>
      <dt class="col-4">Role</dt><dd class="col-8"><?= e($account['role']) ?></dd>
      <dt class="col-4">Status</dt><dd class="col-8"><?= e($account['status']) ?></dd>
      <dt class="col-4">Created</dt><dd class="col-8"><?= e($account['created_at']) ?></dd>
      <dt class="col-4">Tutoring requests</dt><dd class="col-8"><?= (int)$account['request_count'] ?></dd>
    </dl>
  </div></div>
  <?php if ($isSelf): ?>
    <div class="alert alert-info">This is your own account. Administrators cannot change their own role or status, or delete themselves.</div>
  <?php else: ?>
    <a class="btn btn-primary" href="update-role.php?user_id=<?= (int)$account['user_id'] ?>">Update Role</a>
    <a class="btn btn-warning" href="account-status.php?user_id=<?= (int)$account['user_id'] ?>"><?= $account['status'] === 'Active' ? 'Deactivate' : 'Activate' ?></a>
    <a class="btn btn-outline-danger" href="delete-user.php?user_id=<?= (int)$account['user_id'] ?>">Delete Account</a>
  <?php endif; ?>
  <a class="btn btn-secondary" href="manage-users.php">Back to Manage Users</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
