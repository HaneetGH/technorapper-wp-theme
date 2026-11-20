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
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (!function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri() {
        return 'https://technorapper.test/wp-content/themes/technorapper/style.css';
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0) {
        return json_encode($data, $flags);
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        public $headers = array();
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = (int) $status;
        }
        public function header($name, $value) {
            $this->headers[$name] = $value;
        }
        public function get_status() {
            return $this->status;
        }
        public function get_data() {
            return $this->data;
        }
        public function set_data($data) {
            $this->data = $data;
        }
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $route;
        public function __construct($route) {
            $this->route = $route;
        }
        public function get_route() {
            return $this->route;
        }
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
tr_ar_assert(technorapper_agent_is_home_request('/'), 'root URL is recognized as the homepage request');
tr_ar_assert(technorapper_agent_is_home_request('/?utm_source=agent'), 'tracking query does not hide the homepage');
tr_ar_assert(!technorapper_agent_is_home_request('/?s=iphone'), 'search results are not treated as the homepage');
tr_ar_assert(!technorapper_agent_is_home_request('/?rest_route=/wp/v2/posts'), 'REST requests are not treated as the homepage');
tr_ar_assert(!technorapper_agent_is_home_request('/?feed=rss2'), 'feeds are not treated as the homepage');
$_SERVER['REQUEST_URI'] = '/';
$home_headers = technorapper_agent_home_vary_headers(array('Vary' => 'Accept-Encoding'));
$home_vary_tokens = array_map('strtolower', array_map('trim', explode(',', $home_headers['Vary'])));
tr_ar_assert(in_array('accept', $home_vary_tokens, true), 'homepage response varies by Accept');
tr_ar_assert(in_array('accept-encoding', $home_vary_tokens, true), 'homepage Vary preserves Accept-Encoding');
$_SERVER['REQUEST_URI'] = '/?s=iphone';
$search_headers = technorapper_agent_home_vary_headers(array('Vary' => 'Accept-Encoding'), null);
$search_vary_tokens = array_map('strtolower', array_map('trim', explode(',', $search_headers['Vary'])));
tr_ar_assert(!in_array('accept', $search_vary_tokens, true), 'search response is not varied as homepage content');
unset($_SERVER['REQUEST_URI']);
tr_ar_assert(technorapper_agent_select_representation('/', 'text/markdown', false, false) === 'markdown-home', 'homepage negotiates Markdown for an explicit Accept header');
tr_ar_assert(technorapper_agent_select_representation('/', 'text/html', false, true) === null, 'HTML Accept keeps the homepage HTML representation');
tr_ar_assert(technorapper_agent_select_representation('/not-found', 'text/markdown', true, false) === 'markdown-404', 'Markdown 404 negotiation selects an error body');
tr_ar_assert(technorapper_agent_select_representation('/api/', 'text/markdown', false, false) === null, 'Markdown negotiation does not replace API documentation pages');
tr_ar_assert(technorapper_agent_request_path('/api/') === '/api', 'virtual documentation paths normalize their trailing slash');
$api_page = technorapper_agent_virtual_page('/api/');
$cli_page = technorapper_agent_virtual_page('/cli/');
$versioning_page = technorapper_agent_virtual_page('/api-versioning/');
tr_ar_contains($api_page, '<title>TechnoRapper API', 'API route serves its own titled developer docs');
tr_ar_contains($cli_page, 'TechnoRapper CLI', 'CLI route serves its own documentation');
tr_ar_contains($versioning_page, 'RFC 9745', 'versioning route serves deprecation policy');
tr_ar_assert(technorapper_agent_virtual_page('/unrelated') === null, 'unrelated paths are not intercepted');
$vary_headers = technorapper_agent_add_accept_vary(array('Vary' => 'Accept-Encoding'));
tr_ar_assert(stripos($vary_headers['Vary'], 'Accept') !== false, 'homepage Vary adds Accept');
tr_ar_assert(stripos($vary_headers['Vary'], 'Accept-Encoding') !== false, 'homepage Vary preserves Accept-Encoding');

$home_markdown = technorapper_agent_home_markdown();
tr_ar_contains($home_markdown, '# TechnoRapper', 'homepage Markdown identifies the brand');
tr_ar_contains($home_markdown, 'OpenAPI specification', 'homepage Markdown links the API schema');
tr_ar_contains($home_markdown, 'Article collection', 'homepage Markdown links the article API');
tr_ar_assert(strlen(technorapper_agent_404_markdown_body()) > 80, 'Markdown 404 gives a useful explanation');
tr_ar_contains(technorapper_agent_404_markdown_body(), 'TechnoRapper API documentation', 'Markdown 404 links developer documentation');
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
foreach (($document['paths'] ?? array()) as $path => $path_item) {
    $operation = $path_item['get'] ?? array();
    tr_ar_assert(!empty($operation['description']), 'OpenAPI operation is described: ' . $path);
    tr_ar_assert(!empty($operation['operationId']), 'OpenAPI operation has an ID: ' . $path);
    tr_ar_assert(isset($operation['responses']['200']['content']['application/json']['schema']), 'OpenAPI operation has a typed JSON response: ' . $path);
}

$instructions = technorapper_agent_llms_text();
tr_ar_contains($instructions, '## When to use TechnoRapper', 'llms.txt says when to use the API');
tr_ar_contains($instructions, home_url('/openapi.json'), 'llms.txt links OpenAPI');
tr_ar_contains($instructions, 'data.resolution', 'llms.txt explains JSON error resolution');
tr_ar_contains($instructions, 'RateLimit-Policy', 'llms.txt documents rate-limit headers');
tr_ar_contains($instructions, '/api-versioning/', 'llms.txt links the deprecation policy');
tr_ar_contains($instructions, '/cli/', 'llms.txt links the CLI tool page');

$rate_headers = technorapper_agent_rate_limit_header_values(59, 30);
tr_ar_assert(($rate_headers['RateLimit-Policy'] ?? '') === '"default";q=60;w=60', 'rate-limit policy uses quoted structured field syntax');
tr_ar_assert(($rate_headers['RateLimit'] ?? '') === '"default";r=59;t=30', 'rate-limit response reports remaining quota and reset delay');
tr_ar_assert(($rate_headers['RateLimit-Remaining'] ?? '') === '59', 'compatibility remaining header reports the current quota');
$bounded = technorapper_agent_bounded_posts(array('one', 'two', 'three'), 1);
tr_ar_assert($bounded === array('one'), 'collection items are clamped to the requested page size');
tr_ar_assert(technorapper_agent_bounded_posts(array('one'), 0) === array(), 'collection page size cannot over-deliver when zero');

$GLOBALS['technorapper_agent_rate_limit_state'] = array('remaining' => 7, 'reset' => 12);
$rest_response = technorapper_agent_headers(
    new WP_REST_Response(array('ok' => true), 200),
    null,
    new WP_REST_Request('/tr/v1/status')
);
tr_ar_assert(($rest_response->headers['RateLimit-Policy'] ?? '') === '"default";q=60;w=60', 'REST responses include the rate-limit policy header');
tr_ar_assert(($rest_response->headers['RateLimit'] ?? '') === '"default";r=7;t=12', 'REST responses include the remaining quota header');
tr_ar_assert(($rest_response->headers['Cache-Control'] ?? '') === 'private, no-cache, must-revalidate', 'per-client rate-limit values are not shared-cached');
$limited_response = technorapper_agent_headers(
    new WP_REST_Response(array(
        'code' => 'tr_agent_rate_limited',
        'message' => 'Too many requests.',
        'data' => array('status' => 429, 'retry_after' => 8),
    ), 429),
    null,
    new WP_REST_Request('/tr/v1/status')
);
tr_ar_assert(($limited_response->headers['Retry-After'] ?? '') === '8', '429 REST responses include Retry-After');
$error = technorapper_agent_error_response_data(
    array('code' => 'tr_article_not_found', 'message' => 'Article not found.', 'data' => array('status' => 404)),
    404
);
tr_ar_assert(($error['data']['status'] ?? 0) === 404, 'JSON error keeps its HTTP status');
tr_ar_assert(($error['data']['resolution']['documentation'] ?? '') === home_url('/api/'), 'JSON error links API developer documentation');
tr_ar_assert(($error['data']['resolution']['openapi'] ?? '') === home_url('/openapi.json'), 'JSON error links the machine-readable schema');

$rate_headers = technorapper_agent_rate_limit_header_values(12, 30);
tr_ar_assert(($rate_headers['RateLimit-Remaining'] ?? '') === '12', 'legacy RateLimit-Remaining reports remaining quota');
tr_ar_assert(($rate_headers['RateLimit-Reset'] ?? '') === '30', 'legacy RateLimit-Reset reports seconds');
tr_ar_contains($rate_headers['RateLimit-Policy'], 'q=60', 'current RateLimit-Policy advertises the quota');
tr_ar_contains($rate_headers['RateLimit'], 'r=12;t=30', 'current RateLimit field reports remaining and reset');

$GLOBALS['technorapper_test_is_front_page'] = true;
ob_start();
technorapper_agent_home_metadata();
$metadata = ob_get_clean();
tr_ar_contains($metadata, '<link rel="canonical"', 'homepage emits a canonical URL');
tr_ar_contains($metadata, 'property="og:type" content="website"', 'homepage emits og:type website');
tr_ar_contains($metadata, 'name="keywords"', 'homepage metadata exposes product search terms');
tr_ar_contains($metadata, 'TechnoRapper Agent API', 'homepage metadata includes the product name');
ob_start();
technorapper_agent_home_schema();
$schema = ob_get_clean();
tr_ar_contains($schema, 'NewsMediaOrganization', 'homepage schema identifies a news organization');
tr_ar_contains($schema, 'WebSite', 'homepage schema describes the website');
tr_ar_contains($schema, 'TechnoRapper', 'homepage schema includes the exact brand name');
tr_ar_contains($schema, 'contactus@technorapper.com', 'Organization schema includes the published contact email');
tr_ar_contains($schema, '+91 720 674 5474', 'Organization schema includes the published contact phone');
tr_ar_contains($schema, 'PostalAddress', 'Organization schema includes a PostalAddress');
tr_ar_contains($schema, 'Haryana', 'Organization schema uses the published region');
tr_ar_contains($schema, 'TechnoRapper Agent API', 'Organization schema includes agent API product terms');
tr_ar_contains($schema, 'contactus@technorapper.com', 'Organization schema includes published contact email');
tr_ar_contains($schema, '+91 720 674 5474', 'Organization schema includes published contact phone');
tr_ar_contains($schema, 'PostalAddress', 'Organization schema includes a PostalAddress');
tr_ar_contains($schema, 'Haryana', 'Organization schema uses the published region');
tr_ar_contains($schema, 'TechnoRapper Agent API', 'Organization schema includes agent API product terms');

$api_page = technorapper_agent_developer_page_html('/api');
tr_ar_contains($api_page, '<title>TechnoRapper API', 'API docs page has a product-specific title');
tr_ar_contains($api_page, '<h1>TechnoRapper API', 'API docs page has a product-specific heading');
$cli_page = technorapper_agent_cli_page_html();
tr_ar_contains($cli_page, '<title>TechnoRapper CLI', 'CLI page has a tool-specific title');
$versioning_page = technorapper_agent_versioning_page_html();
tr_ar_contains($versioning_page, 'Deprecation', 'versioning page documents deprecation');
tr_ar_contains($versioning_page, 'Sunset', 'versioning page documents sunset notices');

$footer = file_get_contents(dirname(__DIR__) . '/footer.php');
tr_ar_contains($footer, 'TechnoRapper API docs', 'homepage footer links the API docs');
tr_ar_contains($footer, 'TechnoRapper CLI', 'homepage footer links the CLI page');
tr_ar_contains($footer, 'github.com/HaneetGH', 'homepage footer links the GitHub organization');
tr_ar_contains($footer, 'OpenAPI specification', 'homepage footer links the API schema');
tr_ar_contains($footer, "home_url('/api/')", 'homepage footer links the API documentation page');
tr_ar_contains($footer, "home_url('/cli/')", 'homepage footer links the CLI page');

$static_llms = file_get_contents(dirname(__DIR__) . '/llms.txt');
tr_ar_contains($static_llms, 'When to use TechnoRapper', 'static llms.txt includes usage guidance');
tr_ar_contains($static_llms, 'openapi.json', 'static llms.txt links OpenAPI');
tr_ar_contains($static_llms, 'When to use TechnoRapper', 'static llms.txt gives concrete usage guidance');
tr_ar_contains($static_llms, 'api-versioning', 'static llms.txt links versioning policy');

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
