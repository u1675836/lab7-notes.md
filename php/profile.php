<?php
// UM-02 View Own Account — WF-02 Profile. Shows only the signed-in user's account.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();
$profile = getOwnProfile(openDatabase(), $user);
$pageTitle = 'My Profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>My Profile</h1>
  <div class="card auth-card"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-4">Name</dt><dd class="col-8"><?= e($profile['full_name']) ?></dd>
      <dt class="col-4">Email</dt><dd class="col-8"><?= e($profile['email']) ?></dd>
      <dt class="col-4">Role</dt><dd class="col-8"><?= e($profile['role']) ?> <span class="text-muted small">(read only)</span></dd>
      <dt class="col-4">Status</dt><dd class="col-8"><?= e($profile['status']) ?> <span class="text-muted small">(read only)</span></dd>
      <dt class="col-4">Member since</dt><dd class="col-8"><?= e($profile['created_at']) ?></dd>
    </dl>
    <a class="btn btn-primary mt-3" href="edit-profile.php">Edit Profile</a>
    <a class="btn btn-outline-secondary mt-3" href="change-password.php">Change Password</a>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
