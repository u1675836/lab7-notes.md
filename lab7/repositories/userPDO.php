<?php
// Data tier for the USER entity. SQL only: no HTML, no business rules.
// Every submitted value is passed through a prepared statement.

function insertUser($pdo, $fullName, $email, $passwordHash, $role, $status = 'Active')
{
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fullName, $email, $passwordHash, $role, $status]);
    return (int)$pdo->lastInsertId();
}

function findUserByEmail($pdo, $email)
{
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, status, created_at FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function findUserById($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, status, created_at FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function emailInUseByOtherUser($pdo, $email, $exceptUserId)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
    $stmt->execute([$email, $exceptUserId]);
    return (int)$stmt->fetchColumn() > 0;
}

// UM-03: search by name/email text plus optional role and status filters.
function searchUsers($pdo, $search = '', $role = '', $status = '')
{
    $sql = 'SELECT u.user_id, u.full_name, u.email, u.role, u.status, u.created_at
            FROM users u WHERE 1 = 1';
    $args = [];
    if ($search !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $sql .= " AND (u.full_name LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\')";
        $args[] = $like;
        $args[] = $like;
    }
    if ($role !== '') {
        $sql .= ' AND u.role = ?';
        $args[] = $role;
    }
    if ($status !== '') {
        $sql .= ' AND u.status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY u.full_name, u.user_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function updateUserProfile($pdo, $userId, $fullName, $email)
{
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
    $stmt->execute([$fullName, $email, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserPasswordHash($pdo, $userId, $passwordHash)
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $stmt->execute([$passwordHash, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserRole($pdo, $userId, $role)
{
    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
    $stmt->execute([$role, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserStatus($pdo, $userId, $status)
{
    $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
    $stmt->execute([$status, $userId]);
    return $stmt->rowCount() === 1;
}

function deleteUserRow($pdo, $userId)
{
    $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->rowCount() === 1;
}

function countActiveAdministrators($pdo)
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active'")->fetchColumn();
}

// UM-08: related tutoring requests owned through the linked student profile.
function countRequestsForUser($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tutoring_requests r JOIN students s ON r.student_id = s.student_id WHERE s.user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
