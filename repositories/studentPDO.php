<?php
// Data tier for STUDENTS. Lab 6 functions are unchanged; Lab 7 adds user_id links.

function findStudentByEmail($pdo, $email) {
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email FROM students WHERE student_email = ?');
    $stmt->execute([$email]); return $stmt->fetch();
}
function insertStudentIfAbsent($pdo, $name, $email) {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO students(student_name,student_email) VALUES(?,?)');
    $stmt->execute([$name,$email]);
}

// ---- Lab 7 additions ----

function findStudentByUserId($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function findStudentRowByEmail($pdo, $email)
{
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE student_email = ? COLLATE NOCASE');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function insertStudentForUser($pdo, $userId, $name, $email)
{
    $stmt = $pdo->prepare('INSERT INTO students (student_name, student_email, user_id) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $userId]);
    return (int)$pdo->lastInsertId();
}

// Links a pre-existing (Lab 6) student row that has no account yet.
function linkStudentToUser($pdo, $studentId, $userId, $name, $email)
{
    $stmt = $pdo->prepare('UPDATE students SET user_id = ?, student_name = ?, student_email = ? WHERE student_id = ? AND user_id IS NULL');
    $stmt->execute([$userId, $name, $email, $studentId]);
    return $stmt->rowCount() === 1;
}

function updateStudentForUser($pdo, $userId, $name, $email)
{
    $stmt = $pdo->prepare('UPDATE students SET student_name = ?, student_email = ? WHERE user_id = ?');
    $stmt->execute([$name, $email, $userId]);
    return $stmt->rowCount();
}

function studentEmailInUseByOther($pdo, $email, $userId)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE student_email = ? COLLATE NOCASE AND (user_id IS NULL OR user_id <> ?)');
    $stmt->execute([$email, $userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function deleteStudentForUser($pdo, $userId)
{
    $stmt = $pdo->prepare('DELETE FROM students WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}
