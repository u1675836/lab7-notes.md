<?php
// User Management tests UM-01 .. UM-10 against an isolated in-memory database.
// Run from the lab7 root: php tests/user-management-tests.php
require_once __DIR__ . '/test-helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';          // CLI: no session/headers started
require_once dirname(__DIR__) . '/services/requestService.php';

try {
    $pdo = makeTestDatabase();
} catch (Throwable $e) {
    echo 'SETUP FAILED: ' . $e->getMessage() . PHP_EOL;
    exit(2);
}

$student = ['full_name' => 'Alex Morgan', 'email' => 'alex@example.edu', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Student'];
$tutor = ['full_name' => 'Taylor Lee', 'email' => 'taylor@example.edu', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Tutor'];

echo '== UM-01 Create User Account ==' . PHP_EOL;
$alexId = registerUser($pdo, $student);
$alex = findUserById($pdo, $alexId);
check($alex && $alex['role'] === 'Student' && $alex['status'] === 'Active', 'UM-01 valid Student registration creates Active user');
check($alex['password_hash'] !== TEST_PASSWORD && password_verify(TEST_PASSWORD, $alex['password_hash']), 'UM-01 password stored as a hash, not plain text');
$alexStudent = findStudentByUserId($pdo, $alexId);
check($alexStudent && $alexStudent['student_email'] === 'alex@example.edu', 'UM-01 Student account gets a linked students row');
$taylorId = registerUser($pdo, $tutor);
check(findUserById($pdo, $taylorId)['role'] === 'Tutor', 'UM-01 valid Tutor registration');
check(findStudentByUserId($pdo, $taylorId) === false, 'UM-01 Tutor account has no students row');
$usersBefore = countRows($pdo, 'users');
$dup = $student; $dup['email'] = 'ALEX@example.edu';
expectException('BusinessRuleException', function () use ($pdo, $dup) { registerUser($pdo, $dup); }, 'UM-01 duplicate email rejected (case-insensitive)');
$admin = $student; $admin['email'] = 'sneaky@example.edu'; $admin['role'] = 'Administrator';
expectException('AuthorizationException', function () use ($pdo, $admin) { registerUser($pdo, $admin); }, 'UM-01 public registration cannot grant Administrator');
$mismatch = $student; $mismatch['email'] = 'new@example.edu'; $mismatch['confirm_password'] = 'SomethingElse99';
expectException('InvalidArgumentException', function () use ($pdo, $mismatch) { registerUser($pdo, $mismatch); }, 'UM-01 password mismatch rejected');
$short = $student; $short['email'] = 'short@example.edu'; $short['password'] = $short['confirm_password'] = 'short';
expectException('InvalidArgumentException', function () use ($pdo, $short) { registerUser($pdo, $short); }, 'UM-01 short password rejected');
check(countRows($pdo, 'users') === $usersBefore, 'UM-01 failed registrations write nothing');

// Lab 6 seed student (maria.lopez@example.com) has no account: registering claims that row.
$maria = ['full_name' => 'Maria Lopez', 'email' => 'maria.lopez@example.com', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Student'];
$studentsBefore = countRows($pdo, 'students');
$mariaId = registerUser($pdo, $maria);
check(countRows($pdo, 'students') === $studentsBefore && (int)findStudentByUserId($pdo, $mariaId)['student_id'] === 1, 'UM-01 existing Lab 6 student row is linked, not duplicated');

$adminId = createAdministrator($pdo, 'Test Administrator', 'admin@example.com', 'TestPassword123');
$adminUser = publicUserFields(findUserById($pdo, $adminId));
check($adminUser['role'] === 'Administrator' && $adminUser['status'] === 'Active', 'create-admin creates Active Administrator');
expectException('InvalidArgumentException', function () use ($pdo) { createAdministrator($pdo, 'X Admin', 'x@example.com', 'short'); }, 'create-admin rejects password under 12 characters');

echo PHP_EOL . '== UM-09 Log In ==' . PHP_EOL;
$alexSession = authenticateUser($pdo, 'Alex@Example.edu', TEST_PASSWORD);
check($alexSession['user_id'] === $alexId && !isset($alexSession['password_hash']), 'UM-09 valid credentials authenticate; hash not exposed');
expectException('InvalidArgumentException', function () use ($pdo) { authenticateUser($pdo, 'alex@example.edu', 'WrongPassword123'); }, 'UM-09 wrong password rejected');
expectException('InvalidArgumentException', function () use ($pdo) { authenticateUser($pdo, 'nobody@example.edu', TEST_PASSWORD); }, 'UM-09 unknown email rejected with same error type');
$_SESSION = [];
loginUser($alexSession);
check($_SESSION['user_id'] === $alexId && $_SESSION['role'] === 'Student' && strlen($_SESSION['csrf_token']) === 64, 'UM-09 valid login creates session data and CSRF token');
check(currentUser($pdo)['email'] === 'alex@example.edu', 'UM-09 currentUser() resolves the logged-in account');

echo PHP_EOL . '== UM-02 View Own Account ==' . PHP_EOL;
$profile = getOwnProfile($pdo, $alexSession);
check($profile['full_name'] === 'Alex Morgan' && $profile['role'] === 'Student' && $profile['status'] === 'Active', 'UM-02 own profile shows name, email, role, status');
check(!isset($profile['password_hash']), 'UM-02 profile never includes the password hash');
expectException('AuthorizationException', function () use ($pdo) { getOwnProfile($pdo, null); }, 'UM-02 no session -> denied');

echo PHP_EOL . '== UM-03 View and Search User Accounts ==' . PHP_EOL;
check(count(searchAccounts($pdo, $adminUser)) === 4, 'UM-03 admin sees all accounts');
$found = searchAccounts($pdo, $adminUser, 'alex');
check(count($found) === 1 && $found[0]['email'] === 'alex@example.edu', 'UM-03 search by name/email returns match');
check(count(searchAccounts($pdo, $adminUser, '', 'Tutor')) === 1, 'UM-03 role filter');
check(count(searchAccounts($pdo, $adminUser, 'zzz-no-match')) === 0, 'UM-03 no matches returns empty list');
check(count(searchAccounts($pdo, $adminUser, '%')) === 0, 'UM-03 LIKE wildcard in search treated literally');
expectException('AuthorizationException', function () use ($pdo, $alexSession) { searchAccounts($pdo, $alexSession); }, 'UM-03 student cannot search accounts');
expectException('InvalidArgumentException', function () use ($pdo, $adminUser) { searchAccounts($pdo, $adminUser, '', 'Superuser'); }, 'UM-03 invalid role filter rejected');

echo PHP_EOL . '== UM-04 Update Own Account ==' . PHP_EOL;
$updated = updateOwnProfile($pdo, $alexSession, ['full_name' => 'Alex M. Morgan', 'email' => 'alex.morgan@example.edu']);
check($updated['full_name'] === 'Alex M. Morgan' && $updated['email'] === 'alex.morgan@example.edu', 'UM-04 name/email update succeeds');
check(findStudentByUserId($pdo, $alexId)['student_email'] === 'alex.morgan@example.edu', 'UM-04 linked students row kept in sync');
expectException('AuthorizationException', function () use ($pdo, $alexSession) { updateOwnProfile($pdo, $alexSession, ['full_name' => 'Alex', 'email' => 'alex.morgan@example.edu', 'role' => 'Administrator']); }, 'UM-04 role modification denied');
check(findUserById($pdo, $alexId)['role'] === 'Student', 'UM-04 role unchanged after denied attempt');
expectException('BusinessRuleException', function () use ($pdo, $alexSession) { updateOwnProfile($pdo, $alexSession, ['full_name' => 'Alex', 'email' => 'taylor@example.edu']); }, 'UM-04 duplicate email rejected');
expectException('InvalidArgumentException', function () use ($pdo, $alexSession) { updateOwnProfile($pdo, $alexSession, ['full_name' => 'Alex', 'email' => 'bad']); }, 'UM-04 invalid email rejected');
check(findUserById($pdo, $alexId)['email'] === 'alex.morgan@example.edu', 'UM-04 failed updates leave the account unchanged');

echo PHP_EOL . '== UM-05 Change Password ==' . PHP_EOL;
$newPass = 'BrandNewPassw0rd!';
expectException('InvalidArgumentException', function () use ($pdo, $alexSession, $newPass) { changePassword($pdo, $alexSession, 'WrongCurrent123', $newPass, $newPass); }, 'UM-05 incorrect current password rejected');
expectException('InvalidArgumentException', function () use ($pdo, $alexSession, $newPass) { changePassword($pdo, $alexSession, TEST_PASSWORD, $newPass, 'Mismatch123456'); }, 'UM-05 new/confirm mismatch rejected');
check(changePassword($pdo, $alexSession, TEST_PASSWORD, $newPass, $newPass), 'UM-05 valid change succeeds');
check(authenticateUser($pdo, 'alex.morgan@example.edu', $newPass)['user_id'] === $alexId, 'UM-05 new password works');
expectException('InvalidArgumentException', function () use ($pdo) { authenticateUser($pdo, 'alex.morgan@example.edu', TEST_PASSWORD); }, 'UM-05 old password fails');

echo PHP_EOL . '== UM-06 Update User Role ==' . PHP_EOL;
expectException('AuthorizationException', function () use ($pdo, $alexSession, $taylorId) { changeUserRole($pdo, $alexSession, $taylorId, 'Administrator'); }, 'UM-06 unauthorized role change denied');
check(findUserById($pdo, $taylorId)['role'] === 'Tutor', 'UM-06 role unchanged after denial');
check(changeUserRole($pdo, $adminUser, $taylorId, 'Student') && findUserById($pdo, $taylorId)['role'] === 'Student', 'UM-06 admin changes Tutor -> Student');
check(findStudentByUserId($pdo, $taylorId) !== false, 'UM-06 new Student role gets a students row');
expectException('BusinessRuleException', function () use ($pdo, $adminUser, $adminId) { changeUserRole($pdo, $adminUser, $adminId, 'Student'); }, 'UM-06 admin cannot change own role');
expectException('InvalidArgumentException', function () use ($pdo, $adminUser, $taylorId) { changeUserRole($pdo, $adminUser, $taylorId, 'Owner'); }, 'UM-06 invalid role rejected');

echo PHP_EOL . '== UM-07 Activate or Deactivate Account ==' . PHP_EOL;
check(setAccountStatus($pdo, $adminUser, $alexId, 'Inactive'), 'UM-07 admin deactivates account');
expectException('AuthorizationException', function () use ($pdo, $newPass) { authenticateUser($pdo, 'alex.morgan@example.edu', $newPass); }, 'UM-07 inactive user cannot log in');
$_SESSION = []; loginUser($alexSession);
check(currentUser($pdo) === null, 'UM-07 existing session of a deactivated user is ended');
expectException('BusinessRuleException', function () use ($pdo, $adminUser, $alexId) { setAccountStatus($pdo, $adminUser, $alexId, 'Inactive'); }, 'UM-07 invalid transition (already Inactive) rejected');
expectException('BusinessRuleException', function () use ($pdo, $adminUser, $adminId) { setAccountStatus($pdo, $adminUser, $adminId, 'Inactive'); }, 'UM-07 admin cannot deactivate self / last admin');
$mariaSession = authenticateUser($pdo, 'maria.lopez@example.com', TEST_PASSWORD);
expectException('AuthorizationException', function () use ($pdo, $mariaSession, $alexId) { setAccountStatus($pdo, $mariaSession, $alexId, 'Active'); }, 'UM-07 student cannot change status');
check(setAccountStatus($pdo, $adminUser, $alexId, 'Active'), 'UM-07 admin reactivates account');
check(authenticateUser($pdo, 'alex.morgan@example.edu', $newPass)['status'] === 'Active', 'UM-07 reactivated user can log in');

echo PHP_EOL . '== UM-08 Delete User Account ==' . PHP_EOL;
$future = (new DateTimeImmutable('+7 days'))->format('Y-m-d');
submitRequestForUser($pdo, $alexSession, ['course' => 'IS 6465', 'reason' => 'Help with PHP sessions please.', 'topics' => ['Exam review'], 'preferreddate' => $future, 'preferredtime' => 'Morning']);
$reqBefore = countRows($pdo, 'tutoring_requests');
expectException('BusinessRuleException', function () use ($pdo, $adminUser, $alexId) { deleteUserAccount($pdo, $adminUser, $alexId); }, 'UM-08 deletion blocked by related tutoring requests');
check(findUserById($pdo, $alexId) !== false && countRows($pdo, 'tutoring_requests') === $reqBefore, 'UM-08 blocked delete changes nothing');
expectException('AuthorizationException', function () use ($pdo, $mariaSession, $taylorId) { deleteUserAccount($pdo, $mariaSession, $taylorId); }, 'UM-08 student cannot delete accounts');
expectException('BusinessRuleException', function () use ($pdo, $adminUser, $adminId) { deleteUserAccount($pdo, $adminUser, $adminId); }, 'UM-08 admin cannot delete self');
check(deleteUserAccount($pdo, $adminUser, $taylorId) && findUserById($pdo, $taylorId) === false, 'UM-08 account with no related records deleted');
check(findStudentByUserId($pdo, $taylorId) === false, 'UM-08 empty linked students row removed with the account');

echo PHP_EOL . '== UM-10 Log Out ==' . PHP_EOL;
$_SESSION = []; loginUser($mariaSession);
check(currentUser($pdo) !== null, 'UM-10 precondition: user is logged in');
logoutUser();
check(empty($_SESSION) && currentUser($pdo) === null, 'UM-10 logout clears session; protected access denied');
$_SESSION = ['user_id' => $mariaId, 'role' => 'Student', 'login_time' => time() - 4000, 'last_activity' => time() - 4000];
check(currentUser($pdo) === null, 'UM-10 expired (idle) session is rejected');

echo PHP_EOL . '== CSRF ==' . PHP_EOL;
$_SESSION = []; loginUser($mariaSession);
check(isValidCsrfToken($_SESSION['csrf_token']), 'CSRF valid token accepted');
check(!isValidCsrfToken('forged-token') && !isValidCsrfToken(null), 'CSRF missing/forged token rejected');

finishTests('User management');
