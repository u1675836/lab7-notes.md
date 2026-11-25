<?php
// UM-10 Log Out. POST + CSRF so another site cannot log the user out.
// Invalidates the session and its cookie, then redirects to the login page.
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $wasLoggedIn = !empty($_SESSION['user_id']);
    if ($wasLoggedIn) {
        verifyCsrfOrFail();
    }
    logoutUser();
    startSecureSession();               // fresh, empty session for the flash message
    setFlash($wasLoggedIn ? 'success' : 'info', $wasLoggedIn ? 'You have been logged out.' : 'Your session had already ended.');
    redirectTo('login.php');
}

// GET: show a confirmation button (logging out by link would allow CSRF logout).
if (!currentUser()) {
    setFlash('info', 'Your session had already ended.');
    redirectTo('login.php');
}
$pageTitle = 'Log Out';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4"><h1>Log Out</h1>
  <form method="post" action="logout.php" class="mt-3"><?= csrfField() ?><button class="btn btn-primary">Log out now</button></form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
