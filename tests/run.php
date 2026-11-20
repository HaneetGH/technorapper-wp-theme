<?php
$suites = array(
    __DIR__ . '/test-ai-disclosure.php',
    __DIR__ . '/test-indexing.php',
    __DIR__ . '/test-agent-readiness.php',
);
$status = 0;
foreach ($suites as $suite) {
    passthru('php ' . escapeshellarg($suite), $suite_status);
    if ($suite_status !== 0) {
        $status = $suite_status;
    }
}
exit($status);
