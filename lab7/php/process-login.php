<?php
// UM-09 login controller.
// Flow: login.php -> [POST + CSRF check] -> this file -> authenticateUser() in
// services/userService.php (password_verify + status) -> userPDO.php findUserByEmail()
// -> SQLite users. On success loginUser() in includes/auth.php regenerates the session ID.
require_once __DIR__ . '/../includes/auth.php';
requirePost();
verifyCsrfOrFail();

$email = (string)($_POST['email'] ?? '');
try {
    $user = authenticateUser(openDatabase(), $email, $_POST['password'] ?? '');
    loginUser($user);
    setFlash('success', 'Welcome, ' . $user['full_name'] . '.');
    redirectTo('dashboard.php');
} catch (InvalidArgumentException $e) {
    $_SESSION['form_errors'] = ['Invalid email or password.'];
} catch (AuthorizationException $e) {
    $_SESSION['form_errors'] = [$e->getMessage()];
} catch (Throwable $e) {
    error_log($e->getMessage());
    $_SESSION['form_errors'] = ['Unable to log in right now.'];
}
$_SESSION['old_input'] = ['email' => $email];
redirectTo('login.php');
