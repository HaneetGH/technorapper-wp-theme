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

function technorapper_agent_home_markdown()
{
    return "# TechnoRapper\n\n"
        . "TechnoRapper is an AI-assisted technology news publication powered by AI Wire. "
        . "Use its public, read-only API to discover stories, search coverage, browse categories, "
        . "and retrieve article details with canonical URLs and source provenance.\n\n"
        . "## Developer resources\n\n"
        . "- Agent guide: " . home_url('/agents/') . "\n"
        . "- OpenAPI specification: " . home_url('/openapi.json') . "\n"
        . "- Agent discovery: " . home_url('/.well-known/agent.json') . "\n"
        . "- llms.txt: " . home_url('/llms.txt') . "\n"
        . "- API status: " . rest_url('tr/v1/status') . "\n"
        . "- Article collection: " . rest_url('tr/v1/articles') . "\n"
        . "- Search: " . rest_url('tr/v1/search') . "\n"
        . "- Categories: " . rest_url('tr/v1/categories') . "\n\n"
        . "## Use the API when\n\n"
        . "- Finding recent TechnoRapper coverage or searching by topic.\n"
        . "- Retrieving a published story and its canonical URL, categories, and provenance.\n"
        . "- Checking the public API status or listing available categories.\n\n"
        . "The API accepts unauthenticated GET requests, is read-only, and is rate limited. "
        . "Respect HTTP 429 and cache headers. Preserve canonical URLs and source attribution.\n";
}

function technorapper_agent_404_markdown_body()
{
    return "# Page not found\n\n"
        . "TechnoRapper could not find the requested page. The resource may have moved or may not exist.\n\n"
        . "Useful links:\n\n"
        . "- Agent documentation: " . home_url('/agents/') . "\n"
        . "- Available content and API details: " . home_url('/llms.txt') . "\n"
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

    if (is_front_page()) {
        technorapper_agent_send_representation(
            technorapper_agent_home_markdown(),
            200,
            'text/markdown',
            'public, max-age=300'
        );
    }
}
add_action('template_redirect', 'technorapper_agent_readiness_response', -1);

function technorapper_agent_seo_plugin_active()
{
    return defined('AIOSEO_VERSION')
        || defined('WPSEO_VERSION')
        || defined('RANK_MATH_VERSION');
}

function technorapper_agent_home_metadata()
{
    if (!is_front_page() || technorapper_agent_seo_plugin_active()) {
        return;
    }

    $canonical = home_url('/');
    echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
    echo '<meta name="description" content="TechnoRapper is an AI-assisted technology news publication covering AI, mobile, computing, and consumer technology." />' . "\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($canonical) . '" />' . "\n";
}
add_action('wp_head', 'technorapper_agent_home_metadata', 1);

function technorapper_agent_home_schema()
{
    if (!is_front_page() || technorapper_agent_seo_plugin_active()) {
        return;
    }

    $url = home_url('/');
    $graph = array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => 'NewsMediaOrganization',
                '@id' => $url . '#organization',
                'name' => 'TechnoRapper',
                'alternateName' => 'TechnoRapper Technology News',
                'url' => $url,
                'description' => 'An AI-assisted technology news publication powered by AI Wire.',
                'knowsAbout' => array(
                    'Technology news',
                    'Artificial intelligence',
                    'Consumer technology',
                    'Mobile devices',
                    'Computing hardware',
                    'Automotive technology',
                ),
                'sameAs' => array('https://github.com/HaneetGH/technorapper-wp-theme'),
                'publishingPrinciples' => home_url('/editorial-standards/'),
                'correctionsPolicy' => home_url('/corrections/'),
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
        . "TechnoRapper is an AI-assisted technology news publication powered by AI Wire.\n\n"
        . "## Canonical resources\n\n"
        . "- Website: " . home_url('/') . "\n"
        . "- Agent documentation: " . home_url('/agents/') . "\n"
        . "- OpenAPI specification: " . home_url('/openapi.json') . "\n"
        . "- Agent discovery: " . home_url('/.well-known/agent.json') . "\n"
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
        . "- Check API availability before a larger read.\n\n"
        . "## API policy\n\n"
        . "- Public API access requires no authentication for low-volume reads.\n"
        . "- The API is read-only and returns published articles only.\n"
        . "- Collection endpoints default to 20 items and accept at most 50 items.\n"
        . "- Use GET requests only. No content creation or modification is available.\n"
        . "- Respect HTTP 429, Retry-After, Cache-Control, ETag, and Last-Modified.\n"
        . "- Follow pagination.next_url and pagination.prev_url.\n"
        . "- Preserve canonical URLs, source attribution, and AI disclosure.\n"
        . "- Errors use the WordPress REST JSON shape: code, message, and data.status; data.resolution links to documentation.\n"
        . "- Do not republish full article text as a substitute for licensed access.\n\n"
        . "Article records expose stable TechnoRapper IDs, timestamps, categories, author, and provenance. Article detail also includes HTML and plain-text content.\n\n"
        . "For licensing, corrections, or high-volume access, use the published Contact page.\n";
}
