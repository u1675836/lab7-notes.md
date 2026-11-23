<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';
$user = requireRole(['Student', 'Administrator']);
$id = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid request ID');
}
$pdo = openDatabase();
$r = findRequest($pdo, $id);
// Return 404 (not 403) for other students' requests so IDs cannot be probed.
if (!$r || !canViewRequest($pdo, $user, $r)) {
    http_response_code(404);
    exit('Request not found');
}
$canModify = canModifyRequest($pdo, $user, $r);
$topic = decodeTopic($r['topic']);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Request #<?= (int)$id ?></h1>
    <dl>
        <dt>Student</dt>
        <dd><?= escapeHtml($r['student_name']) ?></dd>
        <dt>Course</dt>
        <dd><?= escapeHtml($r['course']) ?></dd>
        <dt>Request details</dt>
        <dd><?= escapeHtml($topic['reason']) ?></dd>
        <dt>Topics</dt>
        <dd><?= escapeHtml(implode(', ', $topic['topics'])) ?></dd>
        <dt>Preferred date</dt>
        <dd><?= escapeHtml($r['preferred_date']) ?></dd>
        <dt>Preferred time</dt>
        <dd><?= escapeHtml($r['preferred_time']) ?></dd>
        <dt>Status</dt>
        <dd><?= escapeHtml($r['status']) ?></dd>
    </dl>
    <?php if ($canModify && $r['status'] === 'Open'): ?><a class="btn btn-primary" href="edit-request.php?request_id=<?= $id ?>">Edit</a> <a class="btn btn-outline-danger" href="delete-request.php?request_id=<?= $id ?>">Cancel request</a><?php endif; ?> <a href="requests.php" class="btn btn-secondary"><?= $user['role'] === 'Administrator' ? 'All requests' : 'My requests' ?></a>
</main><?php require __DIR__ . '/../includes/footer.php'; ?>