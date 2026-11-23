<?php
// UM-06 Update User Role — WF-05 (Administrator only).
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$pdo = openDatabase();
$id = filter_input(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? INPUT_POST : INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid user ID'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfOrFail();
    try {
        changeUserRole($pdo, $admin, $id, (string)($_POST['role'] ?? ''));
        setFlash('success', 'Role updated.');
        redirectTo('user-details.php?user_id=' . $id);
    } catch (InvalidArgumentException $e) { http_response_code(422); $error = $e->getMessage();
    } catch (BusinessRuleException $e) { http_response_code(409); $error = $e->getMessage();
    } catch (AuthorizationException $e) { http_response_code(403); $error = $e->getMessage();
    } catch (Throwable $e) { error_log($e->getMessage()); http_response_code(500); $error = 'Unable to update the role.'; }
}
$account = getAccountForAdmin($pdo, $admin, $id);
if (!$account) { http_response_code(404); exit('Account not found'); }
$pageTitle = 'Update Role';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Update Role</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <p><strong>User:</strong> <?= e($account['full_name']) ?></p>
    <p><strong>Current role:</strong> <?= e($account['role']) ?></p>
    <form method="post" action="update-role.php">
      <?= csrfField() ?>
      <input type="hidden" name="user_id" value="<?= (int)$account['user_id'] ?>">
      <label class="form-label" for="role">New role</label>
      <select class="form-select mb-3" id="role" name="role" required>
        <?php foreach (USER_ROLES as $r): ?><option value="<?= e($r) ?>" <?= $r === $account['role'] ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
      </select>
      <a class="btn btn-secondary" href="user-details.php?user_id=<?= (int)$account['user_id'] ?>">Cancel</a>
      <button class="btn btn-primary" type="submit">Save Role</button>
    </form>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
