<?php
// Lab 7 Part 3: create an Administrator account from the command line.
// Usage (from lab7/database):
//   php create-admin.php "Test Administrator" admin@example.com "TestPassword123"
// Public registration can never grant Administrator (design rule), so this
// CLI script is the only way to create the first administrator.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/services/userService.php';

if ($argc !== 4) {
    fwrite(STDERR, 'Usage: php create-admin.php "Full Name" email@example.com "Password (12+ characters)"' . PHP_EOL);
    exit(1);
}

try {
    $pdo = openDatabase();
    $userId = createAdministrator($pdo, $argv[1], $argv[2], $argv[3]);
    $user = findUserById($pdo, $userId);
    echo 'Administrator account created.' . PHP_EOL;
    echo '  user_id: ' . $user['user_id'] . PHP_EOL;
    echo '  name:    ' . $user['full_name'] . PHP_EOL;
    echo '  email:   ' . $user['email'] . PHP_EOL;
    echo '  role:    ' . $user['role'] . PHP_EOL;
    echo '  status:  ' . $user['status'] . PHP_EOL;
    exit(0);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Administrator not created: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Administrator not created: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Did you run setup-database.sql and upgrade-to-user-management.sql first?' . PHP_EOL);
    exit(1);
}
