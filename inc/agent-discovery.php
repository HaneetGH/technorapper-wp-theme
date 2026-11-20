<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight agent discovery documents served from the canonical WordPress host.
 */
function technorapper_agent_discovery()
{
    $path = isset($_SERVER['REQUEST_URI'])
        ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
        : '';
    $path = untrailingslashit((string) $path);

    if ($path === '/llms.txt') {
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/plain; charset=utf-8');
        echo technorapper_agent_llms_text();
        exit;
    }

    if ($path === '/.well-known/agent.json') {
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(array(
            'name' => 'TechnoRapper',
            'description' => 'AI-assisted technology news publication powered by AI Wire.',
            'website' => home_url('/'),
            'documentation' => home_url('/agents/'),
            'openapi' => home_url('/openapi.json'),
            'llms_txt' => home_url('/llms.txt'),
            'api' => array(
                'base_url' => rest_url('tr/v1/'),
                'read_only' => true,
                'authentication' => 'none for public low-volume reads',
                'rate_limit' => '60 requests/minute/IP',
                'openapi' => home_url('/openapi.json'),
            ),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($path === '/agents') {
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="index,follow"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Developer documentation for the read-only TechnoRapper Agent API."><title>TechnoRapper Agent API — Developer Docs</title></head><body>';
        echo '<main><h1>TechnoRapper Agent API — Developer Docs</h1>';
        echo '<p>Use TechnoRapper machine-readable resources to find technology coverage, retrieve published articles, inspect categories, and preserve source attribution. The public API is read-only and requires no authentication for low-volume reads.</p>';
        echo '<h2>When to use TechnoRapper</h2><ul>';
        echo '<li>Find recent coverage or search stories by topic.</li><li>Retrieve a published article with its canonical URL and provenance.</li><li>Browse available categories or check API status.</li>';
        echo '</ul><h2>Developer resources</h2><ul>';
        echo '<li><a href="' . esc_url(home_url('/openapi.json')) . '">OpenAPI 3.1 specification</a></li>';
        echo '<li><a href="' . esc_url(home_url('/llms.txt')) . '">llms.txt agent instructions</a></li>';
        echo '<li><a href="' . esc_url(home_url('/.well-known/agent.json')) . '">agent.json discovery document</a></li>';
        echo '<li><a href="https://github.com/HaneetGH/technorapper-wp-theme">TechnoRapper theme source</a></li>';
        echo '</ul><h2>API operations</h2><ul>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/articles')) . '">GET articles</a> — published article summaries; use <code>page</code>, <code>per_page</code> (1–50), <code>category</code>, <code>after</code>, and <code>before</code>.</li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/articles/1')) . '">GET article by ID</a> — replace the example ID with the article ID; stable IDs use the <code>tr-&lt;wordpress_id&gt;</code> form in responses.</li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/search')) . '">GET search</a> — requires a <code>q</code> query of at least two characters; supports <code>page</code> and <code>per_page</code>.</li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/categories')) . '">GET categories</a> — available non-empty categories.</li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/status')) . '">GET status</a> — public API availability and version.</li>';
        echo '</ul><h2>Calling the API</h2><p>Each operation is a plain GET request and maps directly to an OpenAPI operation ID, so it can be used as a function-calling tool. Responses are JSON. Collection responses include a bounded <code>data</code> array and pagination links. Article details include HTML, plain text, canonical URL, and provenance.</p>';
        echo '<h2>Errors and limits</h2><p>Errors follow the WordPress REST JSON format with <code>code</code>, <code>message</code>, and <code>data.status</code>; the data includes a documentation resolution link. A rate-limited response returns HTTP 429 and <code>Retry-After</code>. Respect cache validators and preserve canonical URLs, source attribution, and AI disclosure.</p>';
        echo '<p>The API exposes published content only. It does not provide WordPress write capabilities. For licensing, corrections, or higher-volume access, use the Contact page.</p></main></body></html>';
        exit;
    }
}
add_action('template_redirect', 'technorapper_agent_discovery', 0);
