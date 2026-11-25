<?php
// UM-07 Activate or Deactivate Account — WF-06 (Administrator only, with confirmation).
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$pdo = openDatabase();
$id = filter_input(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? INPUT_POST : INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid user ID'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfOrFail();
    try {
        if (($_POST['confirm'] ?? '') !== 'yes') {
            throw new InvalidArgumentException('Please confirm the status change.');
        }
        $newStatus = (string)($_POST['status'] ?? '');
        setAccountStatus($pdo, $admin, $id, $newStatus);
        setFlash('success', 'Account status changed to ' . $newStatus . '.');
        redirectTo('user-details.php?user_id=' . $id);
    } catch (InvalidArgumentException $e) { http_response_code(422); $error = $e->getMessage();
    } catch (BusinessRuleException $e) { http_response_code(409); $error = $e->getMessage();
    } catch (AuthorizationException $e) { http_response_code(403); $error = $e->getMessage();
    } catch (Throwable $e) { error_log($e->getMessage()); http_response_code(500); $error = 'Unable to change the status.'; }
}
$account = getAccountForAdmin($pdo, $admin, $id);
if (!$account) { http_response_code(404); exit('Account not found'); }
$newStatus = $account['status'] === 'Active' ? 'Inactive' : 'Active';
$pageTitle = 'Account Status';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
  <h1>Account Status</h1>
  <div class="card auth-card"><div class="card-body">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <p><strong>User:</strong> <?= e($account['full_name']) ?></p>
    <p><strong>Current status:</strong> <?= e($account['status']) ?></p>
    <p><strong>New status:</strong> <?= e($newStatus) ?></p>
    <?php if ($newStatus === 'Inactive'): ?><p class="text-muted">An inactive user cannot log in. Their records are kept.</p><?php endif; ?>
    <form method="post" action="account-status.php">
      <?= csrfField() ?>
      <input type="hidden" name="user_id" value="<?= (int)$account['user_id'] ?>">
      <input type="hidden" name="status" value="<?= e($newStatus) ?>">
      <input type="hidden" name="confirm" value="yes">
      <a class="btn btn-secondary" href="user-details.php?user_id=<?= (int)$account['user_id'] ?>">Cancel</a>
      <button class="btn btn-warning" type="submit">Confirm</button>
    </form>
  </div></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
