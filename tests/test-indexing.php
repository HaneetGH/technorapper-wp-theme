<?php
require_once __DIR__ . '/bootstrap.php';

$GLOBALS['technorapper_test_is_404'] = false;

function is_404() {
    return !empty($GLOBALS['technorapper_test_is_404']);
}

require_once dirname(__DIR__) . '/inc/indexing.php';

$failures = 0;
$passes = 0;

function tr_idx_assert($ok, $message) {
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "ok  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$message}\n";
}

$GLOBALS['technorapper_test_is_404'] = false;
$robots = technorapper_404_robots(array('max-image-preview' => 'large'));
tr_idx_assert(empty($robots['noindex']), 'indexable pages are not noindexed');
tr_idx_assert(empty($robots['follow']), 'indexable pages do not force follow');
tr_idx_assert(($robots['max-image-preview'] ?? '') === 'large', 'existing robots directives are preserved');
tr_idx_assert(technorapper_should_emit_og_fallback() === true, 'og fallback stays on real pages');

$GLOBALS['technorapper_test_is_404'] = true;
$robots = technorapper_404_robots(array('max-image-preview' => 'large'));
tr_idx_assert(!empty($robots['noindex']), '404 responses are noindexed');
tr_idx_assert(!empty($robots['follow']), '404 responses still allow following links');
tr_idx_assert(($robots['max-image-preview'] ?? '') === 'large', '404 noindex keeps other robots directives');
tr_idx_assert(technorapper_should_emit_og_fallback() === false, '404 pages do not get an unrelated og:image');

$robots = technorapper_404_robots('bogus');
tr_idx_assert($robots['noindex'] === true && $robots['follow'] === true, '404 robots still apply if the filter value is not an array');

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
