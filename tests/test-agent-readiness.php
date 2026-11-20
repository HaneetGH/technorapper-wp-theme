<?php
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        unset($hook, $callback, $priority, $accepted_args);
    }
}
if (!function_exists('is_front_page')) {
    function is_front_page() {
        return !empty($GLOBALS['technorapper_test_is_front_page']);
    }
}
if (!function_exists('is_404')) {
    function is_404() {
        return !empty($GLOBALS['technorapper_test_is_404']);
    }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'https://technorapper.test/wp-json/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('untrailingslashit')) {
    function untrailingslashit($value) {
        return rtrim((string) $value, '/');
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0) {
        return json_encode($data, $flags);
    }
}
require_once dirname(__DIR__) . '/inc/agent-readiness.php';
require_once dirname(__DIR__) . '/inc/agent-api.php';

$failures = 0;
$passes = 0;

function tr_ar_assert($ok, $message)
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "ok  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$message}\n";
}

function tr_ar_contains($haystack, $needle, $message)
{
    tr_ar_assert(strpos((string) $haystack, (string) $needle) !== false, $message);
}

tr_ar_assert(technorapper_agent_accepts_markdown('text/markdown'), 'explicit Markdown Accept header is recognized');
tr_ar_assert(technorapper_agent_accepts_markdown('text/html, text/markdown;q=0.7'), 'Markdown is recognized among multiple accepted types');
tr_ar_assert(!technorapper_agent_accepts_markdown('text/markdown;q=0'), 'q=0 disables Markdown negotiation');
tr_ar_assert(!technorapper_agent_accepts_markdown('*/*'), 'wildcard browser Accept does not replace HTML with Markdown');
tr_ar_assert(!technorapper_agent_accepts_markdown('application/json'), 'JSON Accept does not request Markdown');

$home_markdown = technorapper_agent_home_markdown();
tr_ar_contains($home_markdown, '# TechnoRapper', 'homepage Markdown identifies the brand');
tr_ar_contains($home_markdown, 'OpenAPI specification', 'homepage Markdown links the API schema');
tr_ar_contains($home_markdown, 'Article collection', 'homepage Markdown links the article API');
tr_ar_assert(strlen(technorapper_agent_404_markdown_body()) > 80, 'Markdown 404 gives a useful explanation');
tr_ar_contains(technorapper_agent_404_markdown_body(), 'Agent documentation', 'Markdown 404 links agent documentation');
tr_ar_contains(technorapper_agent_404_markdown_body(), 'llms.txt', 'Markdown 404 links discovery instructions');

$document = technorapper_agent_openapi_document();
tr_ar_assert(is_array($document) && ($document['openapi'] ?? '') === '3.1.0', 'OpenAPI 3.1 document loads');
tr_ar_assert(($document['servers'][0]['url'] ?? '') === 'https://technorapper.test/wp-json/tr/v1', 'OpenAPI server URL follows the active WordPress host');
$operations = array();
foreach (($document['paths'] ?? array()) as $path => $path_item) {
    tr_ar_assert(isset($path_item['get']), 'OpenAPI path has a GET operation: ' . $path);
    if (isset($path_item['get']['operationId'])) {
        $operations[] = $path_item['get']['operationId'];
    }
}
tr_ar_assert(count($operations) === 5, 'OpenAPI documents all five public API operations');
tr_ar_assert(count(array_unique($operations)) === count($operations), 'OpenAPI operation IDs are unique');
tr_ar_assert(isset($document['paths']['/articles']['get']['parameters']), 'article list query parameters are typed');
tr_ar_assert(isset($document['paths']['/search']['get']['parameters']), 'search query parameters are typed');
tr_ar_assert(isset($document['components']['schemas']['ErrorResponse']), 'OpenAPI describes JSON errors');
tr_ar_assert(isset($document['components']['schemas']['Article']), 'OpenAPI describes article detail shape');

$instructions = technorapper_agent_llms_text();
tr_ar_contains($instructions, '## When to use TechnoRapper', 'llms.txt says when to use the API');
tr_ar_contains($instructions, home_url('/openapi.json'), 'llms.txt links OpenAPI');
tr_ar_contains($instructions, 'data.resolution', 'llms.txt explains JSON error resolution');

$bounded = technorapper_agent_bounded_posts(array('one', 'two', 'three'), 1);
tr_ar_assert($bounded === array('one'), 'collection items are clamped to the requested page size');
tr_ar_assert(technorapper_agent_bounded_posts(array('one'), 0) === array(), 'collection page size cannot over-deliver when zero');

$error = technorapper_agent_error_response_data(
    array('code' => 'tr_article_not_found', 'message' => 'Article not found.', 'data' => array('status' => 404)),
    404
);
tr_ar_assert(($error['data']['status'] ?? 0) === 404, 'JSON error keeps its HTTP status');
tr_ar_assert(($error['data']['resolution']['documentation'] ?? '') === home_url('/agents/'), 'JSON error links human-readable documentation');
tr_ar_assert(($error['data']['resolution']['openapi'] ?? '') === home_url('/openapi.json'), 'JSON error links the machine-readable schema');

$GLOBALS['technorapper_test_is_front_page'] = true;
ob_start();
technorapper_agent_home_metadata();
$metadata = ob_get_clean();
tr_ar_contains($metadata, '<link rel="canonical"', 'homepage emits a canonical URL');
tr_ar_contains($metadata, 'property="og:type" content="website"', 'homepage emits og:type website');
ob_start();
technorapper_agent_home_schema();
$schema = ob_get_clean();
tr_ar_contains($schema, 'NewsMediaOrganization', 'homepage schema identifies a news organization');
tr_ar_contains($schema, 'WebSite', 'homepage schema describes the website');
tr_ar_contains($schema, 'TechnoRapper', 'homepage schema includes the exact brand name');

$footer = file_get_contents(dirname(__DIR__) . '/footer.php');
tr_ar_contains($footer, 'Agent documentation', 'homepage footer links agent documentation');
tr_ar_contains($footer, 'OpenAPI specification', 'homepage footer links the API schema');

$static_llms = file_get_contents(dirname(__DIR__) . '/llms.txt');
tr_ar_contains($static_llms, 'When to use TechnoRapper', 'static llms.txt includes usage guidance');
tr_ar_contains($static_llms, 'openapi.json', 'static llms.txt links OpenAPI');

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
