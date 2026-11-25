<?php
// Landing page after login. Content depends on role.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Dashboard</h1>
  <p class="mt-3">Signed in as <strong><?= e($user['full_name']) ?></strong> (<?= e($user['role']) ?>).</p>
  <div class="row g-3">
    <?php if ($user['role'] === 'Student'): ?>
      <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">Request tutoring</h2><p>Submit a new tutoring request.</p><a class="btn btn-primary" href="request-form.php">Request a Session</a></div></div></div>
      <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">My requests</h2><p>View, edit or cancel your Open requests.</p><a class="btn btn-primary" href="requests.php">My Requests</a></div></div></div>
    <?php elseif ($user['role'] === 'Administrator'): ?>
      <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">Manage users</h2><p>Search accounts, change roles and status.</p><a class="btn btn-primary" href="manage-users.php">Manage Users</a></div></div></div>
      <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">All requests</h2><p>View tutoring requests from every student.</p><a class="btn btn-primary" href="requests.php">All Requests</a></div></div></div>
    <?php else: ?>
      <div class="col-md-8"><div class="alert alert-info">Tutor features will be added in a future lab. You can manage your profile and password.</div></div>
    <?php endif; ?>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">My profile</h2><p>View or update your account.</p><a class="btn btn-outline-primary" href="profile.php">My Profile</a></div></div></div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
