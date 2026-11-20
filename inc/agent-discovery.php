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
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo "TechnoRapper\n\n";
        echo "TechnoRapper is an AI-assisted technology news publication powered by AI Wire.\n";
        echo "Content is curated and published with source attribution and AI disclosure where applicable.\n\n";
        echo "Primary resources\n";
        echo "- Home: " . home_url('/') . "\n";
        echo "- Agent documentation: " . home_url('/agents/') . "\n";
        echo "- Agent API: " . rest_url('tr/v1/') . "\n";
        echo "- Article API: " . rest_url('tr/v1/articles') . "\n";
        echo "- Search API: " . rest_url('tr/v1/search') . "\n";
        echo "- Categories API: " . rest_url('tr/v1/categories') . "\n";
        echo "- RSS: " . home_url('/feed/') . "\n\n";
        echo "API policy\n";
        echo "- Public API is read-only.\n";
        echo "- Maximum page size is 50 items.\n";
        echo "- Respect HTTP 429, Cache-Control and ETag headers.\n";
        echo "- Prefer API metadata over scraping HTML.\n";
        echo "- Preserve canonical URLs and source attribution.\n";
        echo "- Do not use the API to modify WordPress content.\n\n";
        echo "Article data includes stable TechnoRapper IDs, timestamps, summaries, categories, canonical URLs and provenance metadata.\n";
        echo "For licensing, corrections or high-volume access, use the published Contact page.\n";
        exit;
    }

    if ($path === '/.well-known/agent.json') {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(array(
            'name' => 'TechnoRapper',
            'description' => 'AI-assisted technology news publication powered by AI Wire.',
            'website' => home_url('/'),
            'documentation' => home_url('/agents/'),
            'llms_txt' => home_url('/llms.txt'),
            'api' => array(
                'base_url' => rest_url('tr/v1/'),
                'read_only' => true,
                'authentication' => 'none for public low-volume reads',
                'rate_limit' => '60 requests/minute/IP',
            ),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($path === '/agents/') {
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="index,follow"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TechnoRapper for AI Agents</title></head><body>';
        echo '<main><h1>TechnoRapper for AI Agents</h1>';
        echo '<p>Machine-readable access to TechnoRapper content, with read-only APIs, source attribution and AI Wire provenance.</p>';
        echo '<h2>API</h2><ul>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/articles')) . '">Articles</a></li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/search')) . '">Search</a></li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/categories')) . '">Categories</a></li>';
        echo '<li><a href="' . esc_url(rest_url('tr/v1/status')) . '">Status</a></li>';
        echo '</ul><h2>Discovery</h2><ul>';
        echo '<li><a href="' . esc_url(home_url('/llms.txt')) . '">llms.txt</a></li>';
        echo '<li><a href="' . esc_url(home_url('/.well-known/agent.json')) . '">agent.json</a></li>';
        echo '</ul><h2>Usage</h2><p>The API is read-only. Maximum page size is 50. Clients should respect rate limits, caching headers, canonical URLs and source attribution.</p></main></body></html>';
        exit;
    }
}
add_action('template_redirect', 'technorapper_agent_discovery', 0);
