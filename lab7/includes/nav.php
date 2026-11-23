<?php
// Role-aware navigation. Links are a convenience only: every page still
// enforces requireLogin()/requireRole() on the server.
$navUser = function_exists('currentUser') ? currentUser() : null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="<?= $navUser ? 'dashboard.php' : 'login.php' ?>">Peer Tutoring Hub</a>
    <div class="navbar-nav me-auto">
      <?php if ($navUser): ?>
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <?php if ($navUser['role'] === 'Student'): ?>
          <a class="nav-link" href="requests.php">My Requests</a>
          <a class="nav-link" href="request-form.php">Request a Session</a>
        <?php elseif ($navUser['role'] === 'Administrator'): ?>
          <a class="nav-link" href="requests.php">All Requests</a>
          <a class="nav-link" href="manage-users.php">Manage Users</a>
        <?php endif; ?>
        <a class="nav-link" href="profile.php">My Profile</a>
      <?php endif; ?>
    </div>
    <div class="navbar-nav align-items-center">
      <?php if ($navUser): ?>
        <span class="navbar-text text-white me-3"><?= htmlspecialchars($navUser['full_name'] . ' (' . $navUser['role'] . ')', ENT_QUOTES, 'UTF-8') ?></span>
        <form method="post" action="logout.php" class="d-inline m-0 p-0 nav-logout">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm btn-light">Log Out</button>
        </form>
      <?php else: ?>
        <a class="nav-link" href="login.php">Log In</a>
        <a class="nav-link" href="register.php">Create Account</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php if (function_exists('takeFlashes')): foreach (takeFlashes() as $flash): ?>
  <div class="container mt-3"><div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endforeach; endif; ?>
