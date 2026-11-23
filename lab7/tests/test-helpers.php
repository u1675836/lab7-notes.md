<?php
// Shared harness: isolated in-memory SQLite database + PASS/FAIL assertions.
// Never touches database/tutoring.db.

$passed = 0;
$failed = 0;

function check($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo 'PASS: ' . $label . PHP_EOL;
    } else {
        $failed++;
        echo 'FAIL: ' . $label . PHP_EOL;
    }
}

// Runs $fn and passes if it throws an exception of class $class.
function expectException($class, callable $fn, $label)
{
    try {
        $fn();
        check(false, $label . ' (no exception thrown)');
    } catch (Throwable $e) {
        check($e instanceof $class, $label . ($e instanceof $class ? '' : ' (got ' . get_class($e) . ': ' . $e->getMessage() . ')'));
    }
}

function makeTestDatabase()
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/setup-database.sql'));
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/upgrade-to-user-management.sql'));
    return $pdo;
}

function countRows($pdo, $table)
{
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function finishTests($title)
{
    global $passed, $failed;
    echo PHP_EOL . $title . ' results: ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
    exit($failed > 0 ? 1 : 0);
}

// Fixture values (test data only, never real people).
const TEST_PASSWORD = 'CorrectHorse42!';
