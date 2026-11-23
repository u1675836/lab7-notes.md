<?php
// Validation tests: Lab 4/6 request validation (unchanged cases) + Lab 7 account validation.
// Run from the lab7 root: php tests/validation-tests.php
require_once __DIR__ . '/test-helpers.php';
require_once dirname(__DIR__) . '/includes/validation.php';

echo '== Lab 6 request validation ==' . PHP_EOL;
$tests = [
    ['Valid time', validatePreferredTime('Morning'), ''],
    ['Missing time', validatePreferredTime(''), 'Choose a valid preferred time.'],
    ['Altered value', validatePreferredTime('Midnight'), 'Choose a valid preferred time.'],
    ['Multiple valid topics', validateTopics(['Exam review', 'Study plan']), ''],
    ['Missing topics', validateTopics([]), 'Select at least one valid topic.'],
    ['Altered topic', validateTopics(['Free tutoring']), 'Select at least one valid topic.'],
    ['Minimum length', validateReason('1234567890'), ''],
    ['Maximum length', validateReason(str_repeat('a', 500)), ''],
    ['Too short', validateReason('123456789'), 'Describe your request using 10 to 500 characters.'],
    ['Too long', validateReason(str_repeat('a', 501)), 'Describe your request using 10 to 500 characters.'],
];
foreach ($tests as $t) {
    check($t[1] === $t[2], $t[0]);
}

echo PHP_EOL . '== Lab 7 account validation ==' . PHP_EOL;
check(validateFullName('Alex Morgan') === '', 'UM-01 valid full name accepted');
check(validateFullName(' ') !== '', 'UM-01 blank full name rejected');
check(validateFullName(str_repeat('x', 121)) !== '', 'UM-01 121-character name rejected');
check(validateEmailAddress('alex@example.edu') === '', 'UM-01 valid email accepted');
check(validateEmailAddress('not-an-email') !== '', 'UM-01 invalid email rejected');
check(validateEmailAddress('') !== '', 'UM-01 missing email rejected');
check(normalizeEmail('  Alex@Example.EDU ') === 'alex@example.edu', 'Email normalized to trimmed lowercase');
check(validatePassword(str_repeat('a', 12)) === '', 'Password of exactly 12 characters accepted');
check(validatePassword(str_repeat('a', 11)) !== '', 'Password of 11 characters rejected');
check(validatePassword(str_repeat('a', 73)) !== '', 'Password over 72 bytes rejected');
check(validatePasswordConfirmation('LongPassword1', 'LongPassword1') === '', 'Matching confirmation accepted');
check(validatePasswordConfirmation('LongPassword1', 'LongPassword2') !== '', 'Mismatched confirmation rejected');
check(validateSelfRegistrationRole('Student') === '', 'Self-registration as Student allowed');
check(validateSelfRegistrationRole('Tutor') === '', 'Self-registration as Tutor allowed');
check(validateSelfRegistrationRole('Administrator') !== '', 'Self-registration as Administrator rejected');
check(validateSelfRegistrationRole('student') !== '', 'Altered role value rejected (case sensitive)');
check(validateUserRole('Administrator') === '', 'Administrator is a valid role for admin updates');
check(validateUserRole('Superuser') !== '', 'Unknown role rejected');
check(validateUserStatus('Inactive') === '', 'Inactive is a valid status');
check(validateUserStatus('Suspended') !== '', 'Unknown status rejected');

finishTests('Validation');
