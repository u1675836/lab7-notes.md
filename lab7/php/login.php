<?php
// UM-09 Log In — WF-08 Login (presentation only). Submits to process-login.php.
require_once __DIR__ . '/../includes/auth.php';
if (currentUser()) {
    redirectTo('dashboard.php');
}
$old = $_SESSION['old_input'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['old_input'], $_SESSION['form_errors']);
$pageTitle = 'Log In';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Log In</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" action="process-login.php">
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" required autocomplete="username" value="<?= e($old['email'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary w-100" type="submit">Log In</button>
    </form>
    <p class="mt-3 mb-0 text-center">New here? <a href="register.php">Create an account</a></p>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
