<?php
// UM-01 registration controller.
// Flow: register.php -> [POST + CSRF check] -> this file -> registerUser() in
// services/userService.php -> userPDO.php / studentPDO.php -> SQLite (users, students)
require_once __DIR__ . '/../includes/auth.php';
requirePost();
verifyCsrfOrFail();

$input = [
    'full_name' => $_POST['full_name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? '',
    'confirm_password' => $_POST['confirm_password'] ?? '',
    'role' => $_POST['role'] ?? '',
];

try {
    registerUser(openDatabase(), $input);
    setFlash('success', 'Account created. Your account is Active. Please log in.');
    redirectTo('login.php');
} catch (InvalidArgumentException $e) {
    $_SESSION['form_errors'] = explode('. ', rtrim($e->getMessage(), '.'));
} catch (BusinessRuleException $e) {
    $_SESSION['form_errors'] = [$e->getMessage()];
} catch (AuthorizationException $e) {
    http_response_code(403);
    $_SESSION['form_errors'] = [$e->getMessage()];
} catch (Throwable $e) {
    error_log($e->getMessage());
    $_SESSION['form_errors'] = ['Unable to create the account right now.'];
}
// Never send passwords back to the form.
$_SESSION['old_input'] = ['full_name' => $input['full_name'], 'email' => $input['email'], 'role' => $input['role']];
redirectTo('register.php');
