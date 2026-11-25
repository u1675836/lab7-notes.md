<?php
// UM-08 Delete User Account — WF-07 (Administrator only).
// Policy: deletion is blocked when related tutoring requests exist; dependent
// records are never removed silently. See deleteUserAccount() in userService.php.
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$pdo = openDatabase();
$id = filter_input(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? INPUT_POST : INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid user ID'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfOrFail();
    try {
        if (($_POST['confirm'] ?? '') !== 'DELETE') {
            throw new InvalidArgumentException('Deletion cancelled: confirmation was not given.');
        }
        deleteUserAccount($pdo, $admin, $id);
        setFlash('success', 'Account deleted.');
        redirectTo('manage-users.php');
    } catch (InvalidArgumentException $e) { http_response_code(422); $error = $e->getMessage();
    } catch (BusinessRuleException $e) { http_response_code(409); $error = $e->getMessage();
    } catch (AuthorizationException $e) { http_response_code(403); $error = $e->getMessage();
    } catch (Throwable $e) { error_log($e->getMessage()); http_response_code(500); $error = 'Unable to delete the account.'; }
}
$account = getAccountForAdmin($pdo, $admin, $id);
if (!$account) { http_response_code(404); exit('Account not found'); }
$pageTitle = 'Delete Account';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Delete Account</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <p>Delete account for <strong><?= e($account['full_name']) ?></strong>?</p>
    <p>Related tutoring requests must be checked. This account has <strong><?= (int)$account['request_count'] ?></strong> related request(s).</p>
    <?php if ($account['request_count'] > 0): ?>
      <div class="alert alert-warning">Deletion is blocked by the current retention policy because related tutoring requests exist. Deactivate the account instead.</div>
      <a class="btn btn-secondary" href="user-details.php?user_id=<?= (int)$account['user_id'] ?>">Cancel</a>
      <a class="btn btn-warning" href="account-status.php?user_id=<?= (int)$account['user_id'] ?>">Go to Account Status</a>
    <?php else: ?>
      <form method="post" action="delete-user.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$account['user_id'] ?>">
        <input type="hidden" name="confirm" value="DELETE">
        <a class="btn btn-secondary" href="user-details.php?user_id=<?= (int)$account['user_id'] ?>">Cancel</a>
        <button class="btn btn-danger" type="submit">Confirm Delete</button>
      </form>
    <?php endif; ?>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
