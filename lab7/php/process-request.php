<?php
// UC01 controller (Lab 7): logged-in Student + CSRF required.
// The request is linked to the student profile of the signed-in account.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';
requirePost();
$user = requireRole(['Student']);
verifyCsrfOrFail();
if (($_POST['form_name'] ?? '') !== 'tutoring_request') {
    http_response_code(400);
    exit('Invalid form');
}
try {
    $id = submitRequestForUser(openDatabase(), $user, $_POST);
    setFlash('success', 'Tutoring request #' . $id . ' submitted with status Open.');
    header('Location: request-details.php?request_id=' . $id, true, 303);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $pageTitle = 'Request not submitted';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/nav.php';
    echo '<main class="container py-4"><h1>Request could not be submitted</h1><div class="alert alert-danger mt-3">' . escapeHtml($e->getMessage()) . '</div><a class="btn btn-secondary" href="request-form.php">Return to form</a></main>';
    require __DIR__ . '/../includes/footer.php';
} catch (AuthorizationException $e) {
    http_response_code(403);
    exit(escapeHtml($e->getMessage()));
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to save request');
}
