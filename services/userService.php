<?php
// Application tier for User Management (UM-01 .. UM-09).
// Business rules, password hashing/verification and transactions live here.
// Pages call these functions; SQL stays in the repositories.

require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/repositories/userPDO.php';
require_once dirname(__DIR__) . '/repositories/studentPDO.php';

// Thrown when the actor is not allowed to perform an action.
class AuthorizationException extends RuntimeException {}

// Thrown when an action is understood and authorized but a business rule blocks it.
class BusinessRuleException extends RuntimeException {}

function throwIfErrors(array $errors)
{
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
}

function assertAdministrator($actor)
{
    if (!$actor || ($actor['role'] ?? '') !== 'Administrator' || ($actor['status'] ?? '') !== 'Active') {
        throw new AuthorizationException('Administrator access is required.');
    }
}

// Fields that are safe to show; the hash never leaves the service.
function publicUserFields($user)
{
    if (!$user) {
        return false;
    }
    return [
        'user_id' => (int)$user['user_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'],
        'created_at' => $user['created_at'],
    ];
}

// Creates (or links) the STUDENTS profile row for a Student account.
function ensureStudentForUser($pdo, $user)
{
    $existing = findStudentByUserId($pdo, $user['user_id']);
    if ($existing) {
        return $existing;
    }
    $legacy = findStudentRowByEmail($pdo, $user['email']);
    if ($legacy && $legacy['user_id'] === null) {
        // Lab 6 rows were created without accounts; claim the row with the same email.
        linkStudentToUser($pdo, $legacy['student_id'], $user['user_id'], $user['full_name'], $user['email']);
    } elseif ($legacy) {
        throw new BusinessRuleException('That email is already linked to another student profile.');
    } else {
        insertStudentForUser($pdo, $user['user_id'], $user['full_name'], $user['email']);
    }
    return findStudentByUserId($pdo, $user['user_id']);
}

// ---------------------------------------------------------------------
// UM-01 Create User Account (self-registration: Student or Tutor only)
// ---------------------------------------------------------------------
function registerUser($pdo, array $input)
{
    $name = trim((string)($input['full_name'] ?? ''));
    $email = normalizeEmail($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';
    $role = (string)($input['role'] ?? '');

    $errors = [];
    if ($m = validateFullName($name)) $errors[] = $m;
    if ($m = validateEmailAddress($email)) $errors[] = $m;
    if ($m = validatePassword($password)) $errors[] = $m;
    if ($m = validatePasswordConfirmation($password, $confirm)) $errors[] = $m;
    if ($role === 'Administrator') {
        throw new AuthorizationException('Administrator accounts cannot be created through public registration.');
    }
    if ($m = validateSelfRegistrationRole($role)) $errors[] = $m;
    throwIfErrors($errors);

    if (findUserByEmail($pdo, $email)) {
        throw new BusinessRuleException('An account with that email already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $userId = insertUser($pdo, $name, $email, $hash, $role, 'Active');
        if ($role === 'Student') {
            ensureStudentForUser($pdo, findUserById($pdo, $userId));
        }
        $pdo->commit();
        return $userId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && stripos($e->getMessage(), 'UNIQUE') !== false) {
            throw new BusinessRuleException('An account with that email already exists.');
        }
        throw $e;
    }
}

// CLI only (database/create-admin.php). Not reachable from any web page.
function createAdministrator($pdo, $fullName, $email, $password)
{
    $name = trim((string)$fullName);
    $email = normalizeEmail($email);
    $errors = [];
    if ($m = validateFullName($name)) $errors[] = $m;
    if ($m = validateEmailAddress($email)) $errors[] = $m;
    if ($m = validatePassword($password)) $errors[] = $m;
    throwIfErrors($errors);
    if (findUserByEmail($pdo, $email)) {
        throw new InvalidArgumentException('An account with that email already exists.');
    }
    return insertUser($pdo, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'Administrator', 'Active');
}

// ---------------------------------------------------------------------
// UM-09 Log In: verify hash and status. Session work happens in auth.php.
// Returns the public user array or throws.
// ---------------------------------------------------------------------
function dummyPasswordHash()
{
    static $hash = null;
    if ($hash === null) {
        $hash = password_hash('timing-equalizer-not-a-real-password', PASSWORD_DEFAULT);
    }
    return $hash;
}

function authenticateUser($pdo, $email, $password)
{
    $email = normalizeEmail($email);
    $password = (string)$password;
    $user = ($email !== '' && strlen($password) <= 1024) ? findUserByEmail($pdo, $email) : false;

    // Verify against a dummy hash when the email is unknown so response time
    // does not reveal which emails are registered.
    $valid = password_verify($password, $user ? $user['password_hash'] : dummyPasswordHash());
    if (!$user || !$valid) {
        throw new InvalidArgumentException('Invalid email or password.');
    }
    if ($user['status'] !== 'Active') {
        throw new AuthorizationException('This account is inactive. Contact an administrator.');
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        updateUserPasswordHash($pdo, $user['user_id'], password_hash($password, PASSWORD_DEFAULT));
    }
    return publicUserFields($user);
}

// ---------------------------------------------------------------------
// UM-02 View Own Account
// ---------------------------------------------------------------------
function getOwnProfile($pdo, $actor)
{
    if (!$actor) {
        throw new AuthorizationException('Please log in.');
    }
    $user = findUserById($pdo, $actor['user_id']);
    if (!$user) {
        throw new AuthorizationException('Account not found.');
    }
    return publicUserFields($user);
}

// ---------------------------------------------------------------------
// UM-03 View and Search User Accounts (Administrator)
// ---------------------------------------------------------------------
function searchAccounts($pdo, $actor, $search = '', $role = '', $status = '')
{
    assertAdministrator($actor);
    $search = trim((string)$search);
    if (mb_strlen($search) > 100) {
        throw new InvalidArgumentException('Search text is too long.');
    }
    if ($role !== '' && validateUserRole($role)) {
        throw new InvalidArgumentException('Invalid role filter.');
    }
    if ($status !== '' && validateUserStatus($status)) {
        throw new InvalidArgumentException('Invalid status filter.');
    }
    return searchUsers($pdo, $search, $role, $status);
}

function getAccountForAdmin($pdo, $actor, $userId)
{
    assertAdministrator($actor);
    $user = findUserById($pdo, (int)$userId);
    if (!$user) {
        return false;
    }
    $details = publicUserFields($user);
    $details['request_count'] = countRequestsForUser($pdo, $details['user_id']);
    return $details;
}

// ---------------------------------------------------------------------
// UM-04 Update Own Account (name and email only; role/status ignored)
// ---------------------------------------------------------------------
function updateOwnProfile($pdo, $actor, array $input)
{
    if (!$actor) {
        throw new AuthorizationException('Please log in.');
    }
    if (array_key_exists('role', $input) || array_key_exists('status', $input)) {
        throw new AuthorizationException('Role and status cannot be changed from your profile.');
    }
    $userId = (int)$actor['user_id'];
    $name = trim((string)($input['full_name'] ?? ''));
    $email = normalizeEmail($input['email'] ?? '');

    $errors = [];
    if ($m = validateFullName($name)) $errors[] = $m;
    if ($m = validateEmailAddress($email)) $errors[] = $m;
    throwIfErrors($errors);

    if (emailInUseByOtherUser($pdo, $email, $userId) || studentEmailInUseByOther($pdo, $email, $userId)) {
        throw new BusinessRuleException('That email is already in use.');
    }

    $pdo->beginTransaction();
    try {
        updateUserProfile($pdo, $userId, $name, $email);
        updateStudentForUser($pdo, $userId, $name, $email); // keeps STUDENTS in sync
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return publicUserFields(findUserById($pdo, $userId));
}

// ---------------------------------------------------------------------
// UM-05 Change Password
// ---------------------------------------------------------------------
function changePassword($pdo, $actor, $currentPassword, $newPassword, $confirmPassword)
{
    if (!$actor) {
        throw new AuthorizationException('Please log in.');
    }
    $user = findUserById($pdo, $actor['user_id']);
    if (!$user || !password_verify((string)$currentPassword, $user['password_hash'])) {
        throw new InvalidArgumentException('Current password is incorrect.');
    }
    $errors = [];
    if ($m = validatePassword($newPassword)) $errors[] = $m;
    if ($m = validatePasswordConfirmation($newPassword, $confirmPassword)) $errors[] = $m;
    if (!$errors && hash_equals((string)$currentPassword, (string)$newPassword)) {
        $errors[] = 'New password must be different from the current password.';
    }
    throwIfErrors($errors);
    return updateUserPasswordHash($pdo, $user['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
}

// ---------------------------------------------------------------------
// UM-06 Update User Role (Administrator)
// ---------------------------------------------------------------------
function changeUserRole($pdo, $actor, $targetUserId, $newRole)
{
    assertAdministrator($actor);
    if ($m = validateUserRole($newRole)) {
        throw new InvalidArgumentException($m);
    }
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new InvalidArgumentException('Account not found.');
    }
    if ((int)$target['user_id'] === (int)$actor['user_id']) {
        throw new BusinessRuleException('You cannot change your own role.');
    }
    if ($target['role'] === $newRole) {
        throw new BusinessRuleException('The account already has that role.');
    }
    if ($target['role'] === 'Administrator' && $target['status'] === 'Active' && countActiveAdministrators($pdo) <= 1) {
        throw new BusinessRuleException('The last active administrator cannot be removed.');
    }
    $pdo->beginTransaction();
    try {
        updateUserRole($pdo, $target['user_id'], $newRole);
        if ($newRole === 'Student') {
            ensureStudentForUser($pdo, findUserById($pdo, $target['user_id']));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return true;
}

// ---------------------------------------------------------------------
// UM-07 Activate or Deactivate Account (Administrator)
// ---------------------------------------------------------------------
function setAccountStatus($pdo, $actor, $targetUserId, $newStatus)
{
    assertAdministrator($actor);
    if ($m = validateUserStatus($newStatus)) {
        throw new InvalidArgumentException($m);
    }
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new InvalidArgumentException('Account not found.');
    }
    if ($target['status'] === $newStatus) {
        throw new BusinessRuleException('The account is already ' . $newStatus . '.');
    }
    if ((int)$target['user_id'] === (int)$actor['user_id']) {
        throw new BusinessRuleException('You cannot change the status of your own account.');
    }
    if ($newStatus === 'Inactive' && $target['role'] === 'Administrator' && countActiveAdministrators($pdo) <= 1) {
        throw new BusinessRuleException('The last active administrator cannot be deactivated.');
    }
    return updateUserStatus($pdo, $target['user_id'], $newStatus);
}

// ---------------------------------------------------------------------
// UM-08 Delete User Account (Administrator)
// Retention policy is still OPEN in the design, so this implementation takes the
// safe option: deletion is BLOCKED whenever related tutoring requests exist.
// Dependent records are never silently removed. Deactivate instead.
// ---------------------------------------------------------------------
function deleteUserAccount($pdo, $actor, $targetUserId)
{
    assertAdministrator($actor);
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new InvalidArgumentException('Account not found.');
    }
    if ((int)$target['user_id'] === (int)$actor['user_id']) {
        throw new BusinessRuleException('You cannot delete your own account.');
    }
    if ($target['role'] === 'Administrator' && $target['status'] === 'Active' && countActiveAdministrators($pdo) <= 1) {
        throw new BusinessRuleException('The last active administrator cannot be deleted.');
    }

    $pdo->beginTransaction();
    try {
        // Recheck relationships inside the transaction (step 2 of UM-08).
        $related = countRequestsForUser($pdo, $target['user_id']);
        if ($related > 0) {
            throw new BusinessRuleException('Deletion blocked: this account has ' . $related
                . ' related tutoring request(s). Under the current retention policy, deactivate the account instead.');
        }
        deleteStudentForUser($pdo, $target['user_id']);
        deleteUserRow($pdo, $target['user_id']);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
