<?php
// Integration tests: Lab 6 UC01/UC02 regression + Lab 7 protected request workflows.
// Run from the lab7 root: php tests/integration-tests.php
require_once __DIR__ . '/test-helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/requestService.php';

try {
    $pdo = makeTestDatabase();
} catch (Throwable $e) {
    echo 'SETUP FAILED: ' . $e->getMessage() . PHP_EOL;
    exit(2);
}

echo '== Database structure ==' . PHP_EOL;
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
check($tables === ['students', 'tutoring_requests', 'users'], 'Tables are students, tutoring_requests, users');
$cols = array_column($pdo->query('PRAGMA table_info(students)')->fetchAll(), 'name');
check(in_array('user_id', $cols, true), 'students.user_id column added');
check((int)$pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Foreign keys enforced');

echo PHP_EOL . '== Lab 6 regression (UC01/UC02) ==' . PHP_EOL;
$sample = ['student_name' => 'Jordan Lee', 'student_email' => 'jordan@example.edu', 'course' => 'IS 6465', 'reason' => 'Please help with PHP form validation.', 'topics' => ['Exam review', 'Study plan'], 'preferreddate' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'), 'preferredtime' => 'Morning'];
$id = submitRequest($pdo, $sample); $r = findRequest($pdo, $id);
check($r !== false && $r['status'] === 'Open', 'UC01 create and Open');
check(decodeTopic($r['topic'])['topics'] === ['Exam review', 'Study plan'], 'Lab 4 checkbox values preserved');
$id2 = submitRequest($pdo, $sample);
check((int)$pdo->query("SELECT COUNT(*) FROM students WHERE student_email='jordan@example.edu'")->fetchColumn() === 1, 'Duplicate email reuses student');
check(count(listRequests($pdo, 'IS 6465', 'Open')) === 3, 'UC02 course and status filter (incl. seed row)');
$bad = $sample; $bad['reason'] = 'short';
$before = countRows($pdo, 'tutoring_requests');
expectException('InvalidArgumentException', function () use ($pdo, $bad) { submitRequest($pdo, $bad); }, 'Invalid reason rejected');
check(countRows($pdo, 'tutoring_requests') === $before, 'Invalid submission writes nothing');
$edit = $sample; $edit['reason'] = 'Updated request reason for the exam.';
check(reviseRequest($pdo, $id, $edit), 'Edit Open request');
check(decodeTopic(findRequest($pdo, $id)['topic'])['reason'] === $edit['reason'], 'Updated reason persisted');
check(cancelOpenRequest($pdo, $id), 'Cancel Open request');
check(findRequest($pdo, $id)['status'] === 'Cancelled', 'Cancelled row retained');
check(!cancelOpenRequest($pdo, $id), 'Second cancellation rejected');
check(!reviseRequest($pdo, $id, $edit), 'Edit of Cancelled request rejected');
check(findRequest($pdo, $id2)['status'] === 'Open', 'Unrelated request unchanged');
expectException('PDOException', function () use ($pdo) { $pdo->exec("INSERT INTO tutoring_requests(student_id,course,topic,preferred_date,preferred_time) VALUES(999,'IS 6465','x','2030-01-01','Morning')"); }, 'FK enforced');
check(count(listRequests($pdo, 'IS 6570', '')) === 0, 'UC02 empty filter result');

echo PHP_EOL . '== Lab 7 student workflow ==' . PHP_EOL;
$future = (new DateTimeImmutable('+5 days'))->format('Y-m-d');
$sId = registerUser($pdo, ['full_name' => 'Sam Student', 'email' => 'sam.student@example.edu', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Student']);
check(findUserById($pdo, $sId)['status'] === 'Active', 'Register student account');
$sam = authenticateUser($pdo, 'sam.student@example.edu', TEST_PASSWORD);
$_SESSION = []; loginUser($sam);
check(currentUser($pdo)['role'] === 'Student', 'Log in as student');
check(getOwnProfile($pdo, $sam)['email'] === 'sam.student@example.edu', 'View student profile');
$form = ['student_name' => 'Spoofed Name', 'student_email' => 'jordan@example.edu', 'course' => 'IS 6495', 'reason' => 'I need help with SQL joins please.', 'topics' => ['Practice problems'], 'preferreddate' => $future, 'preferredtime' => 'Evening'];
$myId = submitRequestForUser($pdo, $sam, $form);
$mine = findRequest($pdo, $myId);
check($mine['status'] === 'Open' && $mine['student_email'] === 'sam.student@example.edu', 'Create tutoring request: Open and linked to account (posted name/email ignored)');
$form['reason'] = 'Updated: SQL joins and subqueries.';
check(reviseRequestForUser($pdo, $sam, $myId, $form), 'Edit Open request');
check(decodeTopic(findRequest($pdo, $myId)['topic'])['reason'] === 'Updated: SQL joins and subqueries.', 'Updated request information persisted');
$myList = listRequestsForUser($pdo, $sam);
check(count($myList) === 1 && (int)$myList[0]['request_id'] === $myId, 'View my requests shows only own requests');
logoutUser();
check(currentUser($pdo) === null, 'Log out ends session');

echo PHP_EOL . '== Lab 7 authorization ==' . PHP_EOL;
$otherId = registerUser($pdo, ['full_name' => 'Riley Other', 'email' => 'riley@example.edu', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Student']);
$riley = authenticateUser($pdo, 'riley@example.edu', TEST_PASSWORD);
check(!canViewRequest($pdo, $riley, $mine), 'Another student cannot view my request');
expectException('AuthorizationException', function () use ($pdo, $riley, $myId, $form) { reviseRequestForUser($pdo, $riley, $myId, $form); }, 'Another student cannot edit my request');
expectException('AuthorizationException', function () use ($pdo, $riley, $myId) { cancelRequestForUser($pdo, $riley, $myId); }, 'Another student cannot cancel my request');
check(findRequest($pdo, $myId)['status'] === 'Open', 'My request unchanged after denied attempts');
check(listRequestsForUser($pdo, $riley) === [], 'New student with no requests sees empty list');
$tutorId = registerUser($pdo, ['full_name' => 'Tara Tutor', 'email' => 'tara@example.edu', 'password' => TEST_PASSWORD, 'confirm_password' => TEST_PASSWORD, 'role' => 'Tutor']);
$tara = authenticateUser($pdo, 'tara@example.edu', TEST_PASSWORD);
expectException('AuthorizationException', function () use ($pdo, $tara, $form) { submitRequestForUser($pdo, $tara, $form); }, 'Tutor cannot create tutoring requests');

echo PHP_EOL . '== Lab 7 administrator workflow ==' . PHP_EOL;
$aId = createAdministrator($pdo, 'Test Administrator', 'admin@example.com', 'TestPassword123');
$adm = authenticateUser($pdo, 'admin@example.com', 'TestPassword123');
check($adm['role'] === 'Administrator', 'Log in as administrator');
check(count(searchAccounts($pdo, $adm)) === 4, 'Admin views the user list');
$all = listRequestsForUser($pdo, $adm);
check(count($all) === countRows($pdo, 'tutoring_requests'), 'Admin views all tutoring requests');
check(canViewRequest($pdo, $adm, $mine), 'Admin can view a student request beyond own account');
check(!canModifyRequest($pdo, $adm, $mine), 'Admin view of requests is read only');
check(findStudentByUserId($pdo, $aId) === false, 'Administrator has no matching students row (LEFT JOIN null)');
$join = $pdo->query('SELECT u.user_id, s.student_id FROM users u LEFT JOIN students s ON u.user_id = s.user_id WHERE u.user_id = ' . (int)$aId)->fetch();
check($join['student_id'] === null, 'Part 10 LEFT JOIN returns admin with NULL student columns');

finishTests('Integration');
