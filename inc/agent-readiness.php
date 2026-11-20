<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent-facing text and OpenAPI representations for public site discovery.
 */
function technorapper_agent_accepts_markdown($accept_header)
{
    foreach (explode(',', (string) $accept_header) as $accepted_type) {
        $parts = explode(';', trim($accepted_type));
        $media_type = strtolower(trim($parts[0]));
        if (!in_array($media_type, array('text/markdown', 'text/x-markdown'), true)) {
            continue;
        }

        $quality = 1.0;
        foreach (array_slice($parts, 1) as $parameter) {
            if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', trim($parameter), $match)) {
                $quality = (float) $match[1];
                break;
            }
        }

        if ($quality > 0 && $quality <= 1) {
            return true;
        }
    }

    return false;
}

function technorapper_agent_is_home_request($request_uri)
{
    $parts = wp_parse_url((string) $request_uri);
    if (!is_array($parts)) {
        return false;
    }

    $request_path = untrailingslashit(isset($parts['path']) ? (string) $parts['path'] : '');
    $home_path = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    if ($request_path !== $home_path) {
        return false;
    }

    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    return !isset($query['s']) && !isset($query['rest_route']) && !isset($query['feed']);
}

function technorapper_agent_add_accept_vary($headers)
{
    if (!is_array($headers)) {
        $headers = array();
    }

    $vary = isset($headers['Vary']) ? (string) $headers['Vary'] : '';
    $values = array_filter(array_map('trim', explode(',', $vary)));
    $has_accept = false;
    foreach ($values as $value) {
        if (strcasecmp($value, 'Accept') === 0) {
            $has_accept = true;
            break;
        }
    }
    if (!$has_accept) {
        $values[] = 'Accept';
    }

    $headers['Vary'] = implode(', ', array_values(array_unique($values)));
    return $headers;
}

function technorapper_agent_home_vary_headers($headers, $wp = null)
{
    unset($wp);
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    if (!technorapper_agent_is_home_request($uri)) {
        return $headers;
    }

    return technorapper_agent_add_accept_vary($headers);
}
add_filter('wp_headers', 'technorapper_agent_home_vary_headers', 10, 2);

function technorapper_agent_home_markdown()
{
    return "# TechnoRapper\n\n"
        . "TechnoRapper is an AI-assisted technology news publication powered by AI Wire, with a public read-only TechnoRapper Agent API.\n\n"
        . "## Developer resources\n\n"
        . "- TechnoRapper API documentation: " . home_url('/api/') . "\n"
        . "- Agent guide: " . home_url('/agents/') . "\n"
        . "- OpenAPI specification: " . home_url('/openapi.json') . "\n"
        . "- Agent discovery: " . home_url('/.well-known/agent.json') . "\n"
        . "- llms.txt: " . home_url('/llms.txt') . "\n"
        . "- TechnoRapper CLI: " . home_url('/cli/') . "\n"
        . "- API versioning policy: " . home_url('/api-versioning/') . "\n"
        . "- GitHub: https://github.com/HaneetGH\n\n"
        . "## Use the API when\n\n"
        . "- Finding recent TechnoRapper technology coverage or searching by topic.\n"
        . "- Retrieving a published story and its canonical URL, categories, and provenance.\n"
        . "- Checking API status or listing available categories.\n\n"
        . "Use unauthenticated GET requests for low-volume reads. The API is read-only. "
        . "Respect rate-limit and cache headers and preserve canonical URLs and source attribution.\n";
}

function technorapper_agent_404_markdown_body()
{
    return "# Page not found\n\n"
        . "TechnoRapper could not find the requested page. The resource may have moved or may not exist.\n\n"
        . "Useful links:\n\n"
        . "- TechnoRapper API documentation: " . home_url('/api/') . "\n"
        . "- Agent instructions: " . home_url('/llms.txt') . "\n"
        . "- OpenAPI specification: " . home_url('/openapi.json') . "\n";
}

function technorapper_agent_openapi_document()
{
    $path = __DIR__ . '/../openapi.json';
    if (!is_readable($path)) {
        return null;
    }

    $document = json_decode((string) file_get_contents($path), true);
    if (!is_array($document)) {
        return null;
    }

    if (!isset($document['servers'][0]) || !is_array($document['servers'][0])) {
        $document['servers'] = array(array());
    }
    $document['servers'][0]['url'] = untrailingslashit(rest_url('tr/v1'));

    return $document;
}

function technorapper_agent_page_shell($title, $description, $canonical_path, $content)
{
    $canonical = home_url($canonical_path);
    $stylesheet = function_exists('get_stylesheet_uri') ? get_stylesheet_uri() : home_url('/wp-content/themes/technorapper/style.css');

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="description" content="' . esc_attr($description) . '"><link rel="canonical" href="' . esc_url($canonical) . '">'
        . '<meta property="og:type" content="website"><meta property="og:url" content="' . esc_url($canonical) . '">'
        . '<title>' . esc_html($title) . '</title><link rel="stylesheet" href="' . esc_url($stylesheet) . '">'
        . '<style>.agent-docs{padding:clamp(24px,5vw,64px) 0}.agent-docs__panel{max-width:72rem;margin:auto;padding:clamp(20px,4vw,40px);background:var(--card);border:1px solid var(--line);border-radius:18px}.agent-docs h1,.agent-docs h2{line-height:1.2}.agent-docs pre{overflow:auto;padding:16px;border:1px solid var(--line);border-radius:12px;background:var(--paper)}.agent-docs a{color:var(--accent)}.agent-docs__links{display:flex;flex-wrap:wrap;gap:12px}</style></head><body>'
        . '<header class="site-header"><div class="wrap mast"><a class="brand" href="' . esc_url(home_url('/')) . '" rel="home"><span><span class="wordmark">Techno<em>Rapper</em></span><span class="tag">Tech news, run by AI Wire agents</span></span></a></div></header>'
        . '<main id="content" class="agent-docs"><div class="wrap"><article class="agent-docs__panel">' . $content . '</article></div></main>'
        . '<footer class="site-footer"><div class="wrap"><nav aria-label="Footer">'
        . '<a href="' . esc_url(home_url('/api/')) . '">TechnoRapper API docs</a>'
        . '<a href="' . esc_url(home_url('/agents/')) . '">Agent instructions</a>'
        . '<a href="' . esc_url(home_url('/cli/')) . '">TechnoRapper CLI</a>'
        . '<a href="' . esc_url(home_url('/api-versioning/')) . '">API versioning</a>'
        . '<a href="https://github.com/HaneetGH">GitHub</a>'
        . '<a href="' . esc_url(home_url('/openapi.json')) . '">OpenAPI specification</a>'
        . '</nav><p class="copy">© ' . esc_html(gmdate('Y')) . ' TechnoRapper · Powered by AI Wire</p></div></footer></body></html>';
}

function technorapper_agent_developer_page_html($path = '/api')
{
    $is_api_page = untrailingslashit((string) $path) === '/api';
    $title = $is_api_page ? 'TechnoRapper API — Developer Docs' : 'TechnoRapper Agent API — Developer Docs';
    $content = '<h1>' . esc_html($title) . '</h1>'
        . '<p>TechnoRapper publishes technology news and provides a public, read-only API for agents and developers.</p>'
        . '<h2>When to use TechnoRapper</h2><ul>'
        . '<li>Search recent technology coverage by topic.</li>'
        . '<li>Find published stories and browse their categories.</li>'
        . '<li>Retrieve a story with its canonical URL and source provenance.</li>'
        . '<li>Check API status before a larger read.</li></ul>'
        . '<h2>Developer resources</h2><ul>'
        . '<li><a href="' . esc_url(home_url('/openapi.json')) . '">OpenAPI 3.1 specification</a></li>'
        . '<li><a href="' . esc_url(home_url('/llms.txt')) . '">llms.txt agent instructions</a></li>'
        . '<li><a href="' . esc_url(home_url('/.well-known/agent.json')) . '">agent.json discovery document</a></li>'
        . '<li><a href="' . esc_url(home_url('/cli/')) . '">TechnoRapper CLI</a></li>'
        . '<li><a href="' . esc_url(home_url('/api-versioning/')) . '">API versioning and deprecation policy</a></li>'
        . '<li><a href="https://github.com/HaneetGH">TechnoRapper GitHub organization</a></li></ul>'
        . '<h2>API operations</h2><ul>'
        . '<li><a href="' . esc_url(rest_url('tr/v1/articles')) . '">GET articles</a> — use <code>page</code>, <code>per_page</code> (1–50), <code>category</code>, <code>after</code>, and <code>before</code>.</li>'
        . '<li><a href="' . esc_url(rest_url('tr/v1/articles/139414')) . '">GET article by ID</a> — substitute a numeric WordPress article ID.</li>'
        . '<li><a href="' . esc_url(rest_url('tr/v1/search')) . '">GET search</a> — requires a <code>q</code> query of at least two characters; supports <code>page</code> and <code>per_page</code>.</li>'
        . '<li><a href="' . esc_url(rest_url('tr/v1/categories')) . '">GET categories</a> — list non-empty categories.</li>'
        . '<li><a href="' . esc_url(rest_url('tr/v1/status')) . '">GET status</a> — check API availability and version.</li></ul>'
        . '<h2>Calling the API</h2><p>Each operation is a plain GET request and maps to a unique OpenAPI operation ID. Responses are JSON. Collections include bounded data arrays and pagination links; article details include HTML, plain text, canonical URLs, and provenance.</p>'
        . '<h2>Errors and rate limits</h2><p>Errors follow the WordPress REST JSON format. Respect <code>RateLimit-Policy</code>, <code>RateLimit</code>, compatibility <code>RateLimit-Limit</code>/<code>RateLimit-Remaining</code>/<code>RateLimit-Reset</code>, and HTTP 429 <code>Retry-After</code>. Preserve canonical URLs, source attribution, and AI disclosure. No write operations are available.</p>'
        . '<p>For licensing, corrections, or higher-volume access, use the published Contact page.</p>';

    return technorapper_agent_page_shell(
        $title,
        'TechnoRapper Agent API documentation for searching and retrieving published technology news.',
        '/api/',
        $content
    );
}

function technorapper_agent_cli_page_html()
{
    $title = 'TechnoRapper CLI — Command-line client for the TechnoRapper API';
    $content = '<h1>TechnoRapper CLI</h1>'
        . '<p>The official TechnoRapper command-line client reads published technology news through the TechnoRapper Agent API. It supports status, article lists, article detail, search, and categories. It does not modify content.</p>'
        . '<h2>Install from the source repository</h2>'
        . '<p>The CLI package is ready in the <a href="https://github.com/HaneetGH/technorapper-wp-theme/tree/main/cli">TechnoRapper GitHub repository</a>. npm registry publication is pending; until then, install the package from source:</p>'
        . '<pre><code>git clone https://github.com/HaneetGH/technorapper-wp-theme.git' . "\n" . 'cd technorapper-wp-theme/cli' . "\n" . 'npm install -g .</code></pre>'
        . '<p>After the package is published, the same client can be installed with <code>npm install -g technorapper-cli</code>.</p>'
        . '<h2>Examples</h2><pre><code>technorapper status' . "\n" . 'technorapper articles --per-page 5' . "\n" . 'technorapper search "iphone"' . "\n" . 'technorapper article 139414' . "\n" . 'technorapper categories</code></pre>'
        . '<p>Read the <a href="' . esc_url(home_url('/api/')) . '">TechnoRapper API documentation</a> and <a href="' . esc_url(home_url('/api-versioning/')) . '">versioning policy</a> before integrating.</p>';

    return technorapper_agent_page_shell(
        $title,
        'Install and use the read-only TechnoRapper CLI.',
        '/cli/',
        $content
    );
}

function technorapper_agent_versioning_page_html()
{
    $title = 'TechnoRapper API Versioning and Deprecation Policy';
    $content = '<h1>' . esc_html($title) . '</h1>'
        . '<h2>Versioning</h2><p>The public REST API uses major versions in its URL path. The current version is <code>/wp-json/tr/v1/</code>. Backward-compatible additions may be made within v1; a breaking change will use a new major version path.</p>'
        . '<h2>Deprecation</h2><p>No v1 endpoint currently has a scheduled sunset. If an endpoint or API version is deprecated, TechnoRapper will publish the affected resource, replacement, deprecation date, and planned sunset date here and in the API documentation.</p>'
        . '<p>Deprecated responses will use the <code>Deprecation</code> response field defined by RFC 9745, a <code>Sunset</code> HTTP-date as defined by RFC 8594 when a removal date is known, and a <code>Link</code> header with <code>rel="deprecation"</code> to this policy or a more specific notice. No fixed minimum notice period is promised at this time.</p>'
        . '<p>Standards: <a href="https://www.rfc-editor.org/rfc/rfc9745.html">RFC 9745 Deprecation</a>; <a href="https://www.rfc-editor.org/rfc/rfc8594.html">RFC 8594 Sunset</a>.</p>'
        . '<p>Current API documentation: <a href="' . esc_url(home_url('/api/')) . '">TechnoRapper API</a>; machine-readable schema: <a href="' . esc_url(home_url('/openapi.json')) . '">OpenAPI</a>.</p>';

    return technorapper_agent_page_shell(
        $title,
        'TechnoRapper Agent API versioning and deprecation policy.',
        '/api-versioning/',
        $content
    );
}

function technorapper_agent_virtual_page($path)
{
    $path = untrailingslashit((string) $path);
    if ($path === '/api') {
        return technorapper_agent_developer_page_html('/api');
    }
    if ($path === '/cli') {
        return technorapper_agent_cli_page_html();
    }
    if ($path === '/api-versioning') {
        return technorapper_agent_versioning_page_html();
    }
    return null;
}

function technorapper_agent_request_path($request_uri)
{
    $request_path = wp_parse_url((string) $request_uri, PHP_URL_PATH);
    $request_path = untrailingslashit((string) $request_path);
    $home_path = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));

    if ($request_path === $home_path) {
        return '/';
    }
    if ($home_path !== '' && strpos($request_path, $home_path . '/') === 0) {
        $request_path = substr($request_path, strlen($home_path));
    }

    return $request_path === '' ? '/' : $request_path;
}

function technorapper_agent_select_representation($request_uri, $accept, $is_404, $is_front_page)
{
    $path = technorapper_agent_request_path($request_uri);
    if ($path === '/openapi.json') {
        return 'openapi';
    }
    if (!technorapper_agent_accepts_markdown($accept)) {
        return null;
    }
    if ($is_404) {
        return 'markdown-404';
    }
    if (technorapper_agent_is_home_request($request_uri) || $is_front_page) {
        return 'markdown-home';
    }
    return null;
}

function technorapper_agent_readiness_response()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $path = technorapper_agent_request_path($uri);
    $page = technorapper_agent_virtual_page($path);
    if ($page !== null) {
        technorapper_agent_send_representation($page, 200, 'text/html', 'public, max-age=300');
    }

    $accept = isset($_SERVER['HTTP_ACCEPT']) ? wp_unslash($_SERVER['HTTP_ACCEPT']) : '';
    $representation = technorapper_agent_select_representation($uri, $accept, is_404(), is_front_page());

    if ($representation === 'openapi') {
        $document = technorapper_agent_openapi_document();
        if (is_array($document)) {
            technorapper_agent_send_representation(
                wp_json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                200,
                'application/json',
                'public, max-age=3600'
            );
        }
    }

    if ($representation === 'markdown-404') {
        technorapper_agent_send_representation(
            technorapper_agent_404_markdown_body(),
            404,
            'text/markdown',
            'no-store'
        );
    }

    if ($representation === 'markdown-home') {
        technorapper_agent_send_representation(
            technorapper_agent_home_markdown(),
            200,
            'text/markdown',
            'public, max-age=300'
        );
    }
}
add_action('template_redirect', 'technorapper_agent_readiness_response', -1);

function technorapper_agent_send_representation($body, $status, $content_type, $cache_control)
{
    status_header((int) $status);
    header('Content-Type: ' . $content_type . '; charset=utf-8');
    header('Cache-Control: ' . $cache_control);
    header('X-Content-Type-Options: nosniff');
    header('Vary: Accept, Accept-Encoding');
    echo $body;
    exit;
}

function technorapper_agent_readiness_response()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $path = wp_parse_url($uri, PHP_URL_PATH);
    $path = untrailingslashit((string) $path);

    if ($path === '/openapi.json') {
        $document = technorapper_agent_openapi_document();
        if (is_array($document)) {
            technorapper_agent_send_representation(
                wp_json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                200,
                'application/json',
                'public, max-age=3600'
            );
        }
    }

    $accept = isset($_SERVER['HTTP_ACCEPT']) ? wp_unslash($_SERVER['HTTP_ACCEPT']) : '';
    if (!technorapper_agent_accepts_markdown($accept)) {
        return;
    }

    if (is_404()) {
        technorapper_agent_send_representation(
            technorapper_agent_404_markdown_body(),
            404,
            'text/markdown',
            'no-store'
        );
    }

    if (technorapper_agent_is_home_request($uri) || is_front_page()) {
        technorapper_agent_send_representation(
            technorapper_agent_home_markdown(),
            200,
            'text/markdown',
            'public, max-age=300'
        );
    }
}
add_action('template_redirect', 'technorapper_agent_readiness_response', -1);

function technorapper_agent_home_metadata()
{
    if (!is_front_page()) {
        return;
    }

    $canonical = home_url('/');
    echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
    echo '<meta name="description" content="TechnoRapper publishes AI-assisted technology news and offers a read-only Agent API for agents and developers." />' . "\n";
    echo '<meta name="keywords" content="TechnoRapper Agent API, technology news API, AI technology news, TechnoRapper CLI" />' . "\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($canonical) . '" />' . "\n";
}
add_action('wp_head', 'technorapper_agent_home_metadata', 99);

function technorapper_agent_home_schema()
{
    if (!is_front_page()) {
        return;
    }

    $url = home_url('/');
    $graph = array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => array('Organization', 'NewsMediaOrganization'),
                '@id' => $url . '#organization',
                'name' => 'TechnoRapper',
                'alternateName' => 'TechnoRapper Technology News',
                'url' => $url,
                'description' => 'TechnoRapper publishes AI-assisted technology news and provides a read-only API and CLI for searching and retrieving published stories.',
                'keywords' => array(
                    'TechnoRapper Agent API',
                    'technology news API',
                    'AI agent technology news',
                    'TechnoRapper CLI',
                ),
                'knowsAbout' => array(
                    'TechnoRapper Agent API',
                    'Technology news API',
                    'AI agent technology news',
                    'Artificial intelligence',
                    'Consumer technology',
                    'Mobile devices',
                    'Computing hardware',
                    'Automotive technology',
                    'TechnoRapper CLI',
                ),
                'sameAs' => array(
                    'https://github.com/HaneetGH',
                    'https://github.com/HaneetGH/technorapper-wp-theme',
                ),
                'publishingPrinciples' => home_url('/editorial-standards/'),
                'correctionsPolicy' => home_url('/corrections/'),
                'contactPoint' => array(
                    array(
                        '@type' => 'ContactPoint',
                        'contactType' => 'customer support',
                        'email' => 'contactus@technorapper.com',
                        'telephone' => '+91 720 674 5474',
                        'areaServed' => 'IN',
                    ),
                    array(
                        '@type' => 'ContactPoint',
                        'contactType' => 'office',
                        'telephone' => '+91 904 142 2652',
                        'areaServed' => 'IN',
                    ),
                ),
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressRegion' => 'Haryana',
                    'addressCountry' => 'IN',
                ),
            ),
            array(
                '@type' => 'WebSite',
                '@id' => $url . '#website',
                'name' => 'TechnoRapper',
                'url' => $url,
                'publisher' => array('@id' => $url . '#organization'),
                'potentialAction' => array(
                    '@type' => 'SearchAction',
                    'target' => home_url('/?s={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ),
            ),
        ),
    );

    echo '<script type="application/ld+json">'
        . wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . '</script>' . "\n";
}
add_action('wp_head', 'technorapper_agent_home_schema', 25);

function technorapper_agent_llms_text()
{
    return "# TechnoRapper\n\n"
        . "TechnoRapper publishes AI-assisted technology news and provides a public, read-only TechnoRapper Agent API.\n\n"
        . "## Canonical resources\n\n"
        . "- Website: " . home_url('/') . "\n"
        . "- TechnoRapper API documentation: " . home_url('/api/') . "\n"
        . "- Agent documentation: " . home_url('/agents/') . "\n"
        . "- OpenAPI specification: " . home_url('/openapi.json') . "\n"
        . "- Agent discovery: " . home_url('/.well-known/agent.json') . "\n"
        . "- TechnoRapper CLI docs and source: " . home_url('/cli/') . "\n"
        . "- API versioning policy: " . home_url('/api-versioning/') . "\n"
        . "- Agent API base: " . rest_url('tr/v1/') . "\n"
        . "- Articles: " . rest_url('tr/v1/articles') . "\n"
        . "- Article details: " . rest_url('tr/v1/articles/{id}') . "\n"
        . "- Search: " . rest_url('tr/v1/search') . "\n"
        . "- Categories: " . rest_url('tr/v1/categories') . "\n"
        . "- Status: " . rest_url('tr/v1/status') . "\n\n"
        . "## When to use TechnoRapper\n\n"
        . "- Search recent technology coverage by topic.\n"
        . "- Discover published stories and categories.\n"
        . "- Retrieve an article with its canonical URL, summary, and provenance.\n"
        . "- Check API availability before a larger read.\n"
        . "- Use the CLI for scripted status, search, article, and category reads after npm publication.\n\n"
        . "## API policy\n\n"
        . "- Public API access requires no authentication for low-volume reads.\n"
        . "- The API is read-only and returns published articles only.\n"
        . "- Collection endpoints default to 20 items and accept at most 50 items.\n"
        . "- Use GET requests only. No content creation or modification is available.\n"
        . "- Respect RateLimit-Policy, RateLimit, RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Cache-Control, ETag, and Last-Modified.\n"
        . "- On HTTP 429, wait for Retry-After before retrying.\n"
        . "- Follow pagination.next_url and pagination.prev_url.\n"
        . "- Preserve canonical URLs, source attribution, and AI disclosure.\n"
        . "- Errors use the WordPress REST JSON shape: code, message, and data.status; data.resolution links to documentation.\n"
        . "- The API uses URL versioning. See the deprecation policy for Deprecation and Sunset notices.\n"
        . "- Do not republish full article text as a substitute for licensed access.\n\n"
        . "Article records expose stable TechnoRapper IDs, timestamps, categories, author, and provenance. Article detail also includes HTML and plain-text content.\n\n"
        . "For licensing, corrections, or high-volume access, use the published Contact page.\n";
}
