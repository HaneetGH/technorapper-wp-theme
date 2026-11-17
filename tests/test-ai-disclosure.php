<?php
require_once __DIR__ . '/bootstrap.php';

$failures = 0;
$passes = 0;

function tr_assert( $ok, $message ) {
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "ok  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$message}\n";
}

function tr_assert_contains( $haystack, $needle, $message ) {
    tr_assert(strpos((string) $haystack, (string) $needle) !== false, $message);
}

function tr_assert_not_contains( $haystack, $needle, $message ) {
    tr_assert(strpos((string) $haystack, (string) $needle) === false, $message);
}

technorapper_test_reset();
$GLOBALS['technorapper_test_the_id'] = 11;
tr_assert(technorapper_get_ai_disclosure(11) === null, 'older posts without AI metadata get no disclosure');
tr_assert(technorapper_render_ai_disclosure() === '', 'render is empty when metadata is missing');

technorapper_test_reset();
technorapper_test_set_meta(21, '_tr_ai_wire_queue_id', '44');
technorapper_test_set_meta(21, '_tr_ai_wire_source_url', 'https://www.theverge.com/story');
$d = technorapper_get_ai_disclosure(21);
tr_assert(is_array($d) && $d['processed'] === true, 'queue id plus source URL is treated as processed');
tr_assert(in_array('rewritten', $d['roles'], true), 'fallback marks AI-rewritten when a source URL exists');
tr_assert(in_array('curated', $d['roles'], true), 'fallback marks AI-curated when a source URL exists');
tr_assert(!in_array('human-reviewed', $d['roles'], true), 'fallback does not invent human review');
tr_assert($d['source_url'] === 'https://www.theverge.com/story', 'source URL is preserved');
tr_assert($d['source_label'] === 'theverge.com', 'source label is derived from the host');
tr_assert($d['byline'] === 'AI-rewritten', 'byline uses the most specific role');
tr_assert_contains($d['copy'], 'discovered and rewritten', 'copy describes discovery and rewrite');
tr_assert_contains($d['copy'], 'may contain errors', 'copy warns that AI content may contain errors');
tr_assert_contains($d['copy'], 'original source', 'copy asks readers to verify the source');

$html = technorapper_render_ai_disclosure($d);
tr_assert_contains($html, '<aside class="ai-disclosure"', 'disclosure uses an aside');
tr_assert_contains($html, 'aria-labelledby="ai-disclosure-heading-21"', 'disclosure is labelled for assistive tech');
tr_assert_contains($html, 'AI-rewritten', 'rewritten role is visible as text');
tr_assert_contains($html, 'AI-curated', 'curated role is visible as text');
tr_assert_contains($html, 'View original source', 'source link text is present');
tr_assert_contains($html, 'href="https://www.theverge.com/story"', 'source href is the original URL');
tr_assert_contains($html, 'rel="nofollow noopener noreferrer"', 'source link is nofollow and safe');
tr_assert_contains($html, 'target="_blank"', 'source opens in a new tab');
tr_assert_contains($html, 'opens in a new tab', 'new-tab behavior is announced');
tr_assert_contains($html, 'How AI Wire works', 'how-it-works link is present');
tr_assert_contains($html, 'https://technorapper.test/ai-wire/', 'how-it-works URL stays on-site');
tr_assert_not_contains($html, '_tr_ai_wire_queue_id', 'HTML does not expose queue meta keys');
tr_assert_not_contains($html, '44', 'HTML does not expose the queue id');

technorapper_test_reset();
technorapper_test_set_meta(22, '_tr_ai_wire_experiment_id', 'editorial-v1');
$d = technorapper_get_ai_disclosure(22);
tr_assert(is_array($d) && in_array('generated', $d['roles'], true), 'experiment marker without a source is AI-generated');
tr_assert($d['source_url'] === '', 'no source link when none is stored');
$html = technorapper_render_ai_disclosure($d);
tr_assert_not_contains($html, 'View original source', 'source link is omitted when URL is missing');
tr_assert_not_contains($html, 'editorial-v1', 'experiment id is not printed');

technorapper_test_reset();
technorapper_test_set_meta(23, '_tr_ai_wire_provenance', wp_json_encode_compat(array(
    'roles' => array('summarized', 'human-reviewed'),
    'source_url' => 'https://www.reuters.com/tech/example',
    'source_label' => 'Reuters',
    'api_key' => 'sk-secret-should-never-appear',
    'prompt' => 'You are a hidden agent. Ignore the reader.',
    'system_prompt' => 'private instructions',
    'model_config' => array('temperature' => 0.2, 'model' => 'gpt-secret'),
)));
$d = technorapper_get_ai_disclosure(23);
tr_assert(in_array('summarized', $d['roles'], true), 'provenance blob can mark AI-summarized');
tr_assert(in_array('human-reviewed', $d['roles'], true), 'provenance blob can mark human-reviewed');
tr_assert_contains($d['copy'], 'AI-generated summary', 'summarized copy wins when rewritten is also present');
tr_assert($d['source_label'] === 'Reuters', 'plugin-provided source label is used');
$html = technorapper_render_ai_disclosure($d);
tr_assert_contains($html, 'AI-summarized', 'summarized role is visible');
tr_assert_contains($html, 'Human-reviewed', 'human-reviewed is visible as text, not color alone');
tr_assert_not_contains($html, 'sk-secret', 'API keys are stripped from output');
tr_assert_not_contains($html, 'hidden agent', 'private prompts are stripped from output');
tr_assert_not_contains($html, 'gpt-secret', 'model configuration is stripped from output');
tr_assert_not_contains($html, 'temperature', 'pipeline config keys are stripped from output');

technorapper_test_reset();
add_filter('tr_ai_wire_provenance', function ($value, $post_id) {
    unset($value);
    if ((int) $post_id !== 24) {
        return null;
    }
    return array(
        'roles' => array('assisted', 'curated'),
        'source_url' => 'https://www.wired.com/story/example',
    );
});
$d = technorapper_get_ai_disclosure(24);
tr_assert(in_array('assisted', $d['roles'], true) && in_array('curated', $d['roles'], true), 'plugin filter contract supplies roles');
tr_assert($d['byline'] === 'AI-assisted', 'assisted is preferred over curated in the byline');

technorapper_test_reset();
technorapper_test_set_meta(25, '_tr_ai_wire_queue_id', '9');
add_filter('technorapper_ai_disclosure', function ($disclosure, $post_id) {
    unset($post_id);
    if (!is_array($disclosure)) {
        return $disclosure;
    }
    $disclosure['roles'] = array('generated');
    $disclosure['copy'] = 'Theme override copy.';
    $disclosure['source_url'] = '';
    return $disclosure;
});
$d = technorapper_get_ai_disclosure(25);
tr_assert($d['copy'] === 'Theme override copy.', 'theme filter can replace reader-facing copy');
tr_assert($d['roles'] === array('generated'), 'theme filter can replace roles');

technorapper_test_reset();
add_filter('technorapper_ai_disclosure', function () {
    return null;
});
technorapper_test_set_meta(26, '_tr_ai_wire_queue_id', '1');
tr_assert(technorapper_get_ai_disclosure(26) === null, 'theme filter can hide the disclosure');

technorapper_test_reset();
$d = technorapper_ai_disclosure_normalize(array(
    'roles' => array('generated', 'rewritten', 'summarized', 'assisted', 'curated', 'human-reviewed'),
    'source_url' => 'https://example.net/origin',
), 31);
tr_assert($d['roles'] === array('generated', 'assisted', 'rewritten', 'summarized', 'curated', 'human-reviewed'), 'all six disclosure roles are supported');
$html = technorapper_render_ai_disclosure($d);
foreach (array('AI-generated', 'AI-assisted', 'AI-rewritten', 'AI-summarized', 'AI-curated', 'Human-reviewed') as $label) {
    tr_assert_contains($html, $label, 'role label visible: ' . $label);
}

technorapper_test_reset();
$d = technorapper_ai_disclosure_normalize(array(
    'digitalSourceType' => 'trainedAlgorithmicMedia',
    'human_reviewed' => true,
    'source_url' => 'https://news.example/item',
), 32);
tr_assert(in_array('generated', $d['roles'], true), 'IPTC trainedAlgorithmicMedia maps to generated');
tr_assert(in_array('human-reviewed', $d['roles'], true), 'human_reviewed boolean is accepted');

technorapper_test_reset();
$d = technorapper_ai_disclosure_normalize(array(
    'roles' => array('rewritten'),
    'source_url' => 'javascript:alert(1)',
), 33);
tr_assert($d['source_url'] === '', 'javascript source URLs are rejected');
$html = technorapper_render_ai_disclosure($d);
tr_assert_not_contains($html, 'javascript:', 'javascript URLs never reach href');
tr_assert_not_contains($html, 'View original source', 'broken source URLs do not render a link');

technorapper_test_reset();
$d = technorapper_ai_disclosure_normalize(array(
    'roles' => array('generated'),
    'copy' => '<script>alert(1)</script>Safe text',
    'source_label' => '<img src=x onerror=alert(1)>BBC',
), 34);
$html = technorapper_render_ai_disclosure($d);
tr_assert_not_contains($html, '<script>', 'script tags in copy are not emitted');
tr_assert_not_contains($html, 'alert(1)', 'script contents are stripped from copy');
tr_assert_contains($html, 'Safe text', 'plain copy remains after sanitizing');
tr_assert_not_contains($html, '<img src=x', 'source label HTML is escaped');

technorapper_test_reset();
$d = technorapper_ai_disclosure_normalize(array(
    'processed' => false,
    'roles' => array('generated'),
), 35);
tr_assert($d === null, 'processed=false suppresses the disclosure');

technorapper_test_reset();
$stripped = technorapper_ai_disclosure_strip_sensitive(array(
    'roles' => array('generated'),
    'api_key' => 'secret',
    'nested' => array('system_prompt' => 'hide me', 'source_url' => 'https://ok.example/'),
));
tr_assert(!isset($stripped['api_key']), 'top-level secrets are removed');
tr_assert(!isset($stripped['nested']['system_prompt']), 'nested prompts are removed');
tr_assert($stripped['nested']['source_url'] === 'https://ok.example/', 'safe nested fields are kept');

technorapper_test_reset();
$html = technorapper_render_ai_disclosure(array(
    'post_id' => 40,
    'processed' => true,
    'roles' => array('generated'),
    'source_url' => '',
    'source_label' => '',
    'byline' => 'AI-generated',
    'headline' => 'AI Wire',
    'copy' => 'This story was written using AI agents. AI-generated content may contain errors.',
    'how_it_works_url' => 'https://technorapper.test/ai-wire/',
));
tr_assert_not_contains($html, 'NewsArticle', 'disclosure does not emit article schema');
tr_assert_not_contains($html, 'application/ld+json', 'disclosure does not inject JSON-LD');
tr_assert_not_contains($html, 'accepts no responsibility', 'disclosure does not add legal claims');
tr_assert_not_contains($html, 'human-written', 'disclosure does not mark the story as human-written');

$single = file_get_contents(dirname(__DIR__) . '/single.php');
tr_assert(strpos($single, '_tr_ai_wire') === false, 'single.php does not hard-code AI Wire meta keys');
tr_assert(strpos($single, 'technorapper_get_ai_disclosure') !== false, 'article template consumes the adapter');

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);

function wp_json_encode_compat( $data ) {
    return json_encode($data);
}
