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
            'description' => 'AI-assisted technology news publication with a public, read-only Agent API.',
            'website' => home_url('/'),
            'documentation' => home_url('/api/'),
            'openapi' => home_url('/openapi.json'),
            'llms_txt' => home_url('/llms.txt'),
            'cli' => home_url('/cli/'),
            'api_versioning' => home_url('/api-versioning/'),
            'api' => array(
                'base_url' => rest_url('tr/v1/'),
                'read_only' => true,
                'authentication' => 'none for public low-volume reads',
                'rate_limit' => '60 requests/minute/IP',
                'rate_limit_headers' => array(
                    'RateLimit-Policy',
                    'RateLimit',
                    'RateLimit-Limit',
                    'RateLimit-Remaining',
                    'RateLimit-Reset',
                    'Retry-After',
                ),
                'openapi' => home_url('/openapi.json'),
                'versioning_policy' => home_url('/api-versioning/'),
            ),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (in_array($path, array('/agents', '/api', '/developers'), true)) {
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
        echo technorapper_agent_developer_page_html($path);
        exit;
    }

    if ($path === '/cli') {
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
        echo technorapper_agent_cli_page_html();
        exit;
    }

    if ($path === '/api-versioning') {
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
        echo technorapper_agent_versioning_page_html();
        exit;
    }
}
add_action('template_redirect', 'technorapper_agent_discovery', 0);
