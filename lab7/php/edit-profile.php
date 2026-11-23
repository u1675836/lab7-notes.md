<?php
// UM-04 Update Own Account — WF-02 Edit. Name and email only.
// The account is always the session user; no user_id is accepted from the form.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();
$pdo = openDatabase();
$errors = [];
$values = getOwnProfile($pdo, $user);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfOrFail();
    $input = ['full_name' => $_POST['full_name'] ?? '', 'email' => $_POST['email'] ?? ''];
    // Any attempt to post role/status is passed through so the service can deny it.
    foreach (['role', 'status'] as $blocked) {
        if (isset($_POST[$blocked])) $input[$blocked] = $_POST[$blocked];
    }
    try {
        updateOwnProfile($pdo, $user, $input);
        setFlash('success', 'Profile updated.');
        redirectTo('profile.php');
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (BusinessRuleException $e) {
        $errors[] = $e->getMessage();
    } catch (AuthorizationException $e) {
        http_response_code(403);
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $errors[] = 'Unable to update the profile right now.';
    }
    $values['full_name'] = $input['full_name'];
    $values['email'] = $input['email'];
}
$pageTitle = 'Edit Profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Edit Profile</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" action="edit-profile.php">
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label" for="full_name">Name</label>
        <input class="form-control" id="full_name" name="full_name" maxlength="120" required value="<?= e($values['full_name']) ?>"></div>
      <div class="mb-3"><label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" maxlength="254" required value="<?= e($values['email']) ?>"></div>
      <p class="mb-1">Role: <?= e($user['role']) ?> <span class="text-muted small">(read only)</span></p>
      <p>Status: <?= e($user['status']) ?> <span class="text-muted small">(read only)</span></p>
      <button class="btn btn-primary" type="submit">Save Changes</button>
      <a class="btn btn-secondary" href="profile.php">Cancel</a>
    </form>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
