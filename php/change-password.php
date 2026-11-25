<?php
// UM-05 Change Password — WF-04. Verifies the current password, stores only a new hash.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfOrFail();
    try {
        changePassword(openDatabase(), $user, $_POST['current_password'] ?? '', $_POST['new_password'] ?? '', $_POST['confirm_password'] ?? '');
        session_regenerate_id(true);   // credentials changed: rotate the session ID
        setFlash('success', 'Password updated.');
        redirectTo('profile.php');
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $errors[] = 'Unable to update the password right now.';
    }
}
$pageTitle = 'Change Password';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Change Password</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" action="change-password.php">
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label" for="current_password">Current Password</label>
        <input class="form-control" id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
      <div class="mb-3"><label class="form-label" for="new_password">New Password</label>
        <input class="form-control" id="new_password" name="new_password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></div>
      <div class="mb-3"><label class="form-label" for="confirm_password">Confirm New Password</label>
        <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></div>
      <button class="btn btn-primary" type="submit">Update Password</button>
      <a class="btn btn-secondary" href="profile.php">Cancel</a>
    </form>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
