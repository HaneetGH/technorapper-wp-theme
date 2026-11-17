<?php
$suite = __DIR__ . '/test-ai-disclosure.php';
$status = 0;
passthru('php ' . escapeshellarg($suite), $status);
exit($status);
