<?php

function validatePreferredTime($preferredTime)
{
    $allowedTimes = ['Morning', 'Afternoon', 'Evening'];
    if (!in_array($preferredTime, $allowedTimes, true)) {
        return 'Choose a valid preferred time.';
    }
    return '';
}

function validateTopics($topics)
{
    $allowedTopics = ["Exam review", "Practice problems", "Study plan"];

    if ($topics === []) {
        return "Select at least one valid topic.";
    }

    foreach ($topics as $topic) {
        if (!in_array($topic, $allowedTopics, true)) {
            return "Select at least one valid topic.";
        }
    }

    return "";
}

function validateReason($reason)
{
    $length = mb_strlen(trim($reason));

    if ($length < 10 || $length > 500) {
        return "Describe your request using 10 to 500 characters.";
    }

    return "";
}

// ---------------------------------------------------------------------
// Lab 7 user-management validation (server side). Each returns '' when valid.
// ---------------------------------------------------------------------

const USER_ROLES = ['Student', 'Tutor', 'Administrator'];
const SELF_REGISTER_ROLES = ['Student', 'Tutor'];   // approved: no public Administrator
const USER_STATUSES = ['Active', 'Inactive'];
const PASSWORD_MIN_LENGTH = 12;                     // Lab 7: at least 12 characters
const PASSWORD_MAX_BYTES = 72;                      // bcrypt ignores bytes after 72

function normalizeEmail($email)
{
    return strtolower(trim((string)$email));
}

function validateFullName($name)
{
    $length = mb_strlen(trim((string)$name));
    if ($length < 2 || $length > 120) {
        return 'Full name must be 2 to 120 characters.';
    }
    return '';
}

function validateEmailAddress($email)
{
    $email = trim((string)$email);
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    return '';
}

function validatePassword($password)
{
    $password = (string)$password;
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return 'Password must be no longer than ' . PASSWORD_MAX_BYTES . ' bytes.';
    }
    return '';
}

function validatePasswordConfirmation($password, $confirmation)
{
    if (!is_string($password) || !is_string($confirmation) || !hash_equals($password, $confirmation)) {
        return 'Passwords do not match.';
    }
    return '';
}

function validateSelfRegistrationRole($role)
{
    if (!in_array($role, SELF_REGISTER_ROLES, true)) {
        return 'Choose Student or Tutor.';
    }
    return '';
}

function validateUserRole($role)
{
    if (!in_array($role, USER_ROLES, true)) {
        return 'Choose a valid role.';
    }
    return '';
}

function validateUserStatus($status)
{
    if (!in_array($status, USER_STATUSES, true)) {
        return 'Choose a valid status.';
    }
    return '';
}
