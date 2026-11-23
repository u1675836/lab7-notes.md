<?php
// UM-01 Create User Account — WF-01 Registration (presentation only).
// Submits to process-register.php (the registration controller).
require_once __DIR__ . '/../includes/auth.php';
if (currentUser()) {
    redirectTo('dashboard.php');
}
$old = $_SESSION['old_input'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['old_input'], $_SESSION['form_errors']);
$pageTitle = 'Create Account';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Create Account</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="process-register.php" novalidate>
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label" for="full_name">Full Name</label>
        <input class="form-control" id="full_name" name="full_name" maxlength="120" required value="<?= e($old['full_name'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" maxlength="254" required autocomplete="username" value="<?= e($old['email'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password">
        <div class="form-text">At least 12 characters.</div></div>
      <div class="mb-3"><label class="form-label" for="confirm_password">Confirm Password</label>
        <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></div>
      <fieldset class="mb-3"><legend class="fs-6 fw-bold">Account type</legend>
        <?php foreach (SELF_REGISTER_ROLES as $r): ?>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="role" id="role_<?= e($r) ?>" value="<?= e($r) ?>" required <?= ($old['role'] ?? 'Student') === $r ? 'checked' : '' ?>>
          <label class="form-check-label" for="role_<?= e($r) ?>"><?= e($r) ?></label></div>
        <?php endforeach; ?>
      </fieldset>
      <button class="btn btn-primary w-100" type="submit">Create Account</button>
    </form>
    <p class="mt-3 mb-0 text-center">Already registered? <a href="login.php">Log in</a></p>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
