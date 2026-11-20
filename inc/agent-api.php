<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TechnoRapper Agent API.
 *
 * Public endpoints are deliberately read-only and expose a bounded,
 * cache-friendly representation of published editorial content.
 */

function technorapper_agent_api_register()
{
    register_rest_route('tr/v1', '/articles', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'technorapper_agent_articles',
        'permission_callback' => '__return_true',
        'args'                => array(
            'page' => array(
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) { return (int) $value >= 1; },
            ),
            'per_page' => array(
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) { return (int) $value >= 1 && (int) $value <= 50; },
            ),
            'category' => array(
                'default'           => '',
                'sanitize_callback' => function ($value) {
                    return sanitize_title(is_scalar($value) ? (string) $value : '');
                },
            ),
            'after' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'before' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));

    register_rest_route('tr/v1', '/articles/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'technorapper_agent_article',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('tr/v1', '/search', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'technorapper_agent_search',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) { return strlen(trim((string) $value)) >= 2; },
            ),
            'page' => array(
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) { return (int) $value >= 1 && (int) $value <= 50; },
            ),
        ),
    ));

    register_rest_route('tr/v1', '/categories', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'technorapper_agent_categories',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('tr/v1', '/status', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'technorapper_agent_status',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'technorapper_agent_api_register');

function technorapper_agent_rate_limit_header_values($remaining, $reset)
{
    $remaining = max(0, min(60, (int) $remaining));
    $reset = max(1, (int) $reset);

    return array(
        'RateLimit-Policy'     => '"default";q=60;w=60',
        'RateLimit'            => '"default";r=' . $remaining . ';t=' . $reset,
        'RateLimit-Limit'      => '60;w=60',
        'RateLimit-Remaining'  => (string) $remaining,
        'RateLimit-Reset'      => (string) $reset,
    );
}

function technorapper_agent_rate_limit()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $bucket = 'tr_agent_' . md5($ip . '|' . gmdate('YmdHi'));
    $count = (int) get_transient($bucket);
    $reset = max(1, 60 - ((int) time() % 60));

    if ($count >= 60) {
        $GLOBALS['technorapper_agent_rate_limit_state'] = array(
            'remaining' => 0,
            'reset' => $reset,
        );
        return new WP_Error(
            'tr_agent_rate_limited',
            'Too many requests. Please retry shortly.',
            array('status' => 429, 'retry_after' => $reset)
        );
    }

    set_transient($bucket, $count + 1, 70);
    $GLOBALS['technorapper_agent_rate_limit_state'] = array(
        'remaining' => max(0, 59 - $count),
        'reset' => $reset,
    );
    return true;
}

function technorapper_agent_error_response_data($data, $status)
{
    if (!is_array($data) || (int) $status < 400) {
        return $data;
    }

    if (!isset($data['data']) || !is_array($data['data'])) {
        $data['data'] = array();
    }
    if (!isset($data['data']['status'])) {
        $data['data']['status'] = (int) $status;
    }
    $data['data']['resolution'] = array(
        'documentation' => home_url('/api/'),
        'openapi'       => home_url('/openapi.json'),
    );

    return $data;
}

function technorapper_agent_headers($response, $server, $request)
{
    if ($response instanceof WP_REST_Response && strpos($request->get_route(), '/tr/v1/') === 0) {
        $response->header('Cache-Control', 'private, no-cache, must-revalidate');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Vary', 'Accept-Encoding');

        $rate_state = isset($GLOBALS['technorapper_agent_rate_limit_state']) && is_array($GLOBALS['technorapper_agent_rate_limit_state'])
            ? $GLOBALS['technorapper_agent_rate_limit_state']
            : array('remaining' => 0, 'reset' => 60);
        foreach (technorapper_agent_rate_limit_header_values($rate_state['remaining'], $rate_state['reset']) as $header_name => $header_value) {
            $response->header($header_name, $header_value);
        }

        $status = (int) $response->get_status();
        if ($status >= 400) {
            $data = technorapper_agent_error_response_data($response->get_data(), $status);
            $response->set_data($data);
            if ($status === 429 && !empty($data['data']['retry_after'])) {
                $response->header('Retry-After', (string) max(1, (int) $data['data']['retry_after']));
            }
        }
    }
    return $response;
}
add_filter('rest_post_dispatch', 'technorapper_agent_headers', 10, 3);

function technorapper_agent_pre_dispatch($result, $server, $request)
{
    if (strpos($request->get_route(), '/tr/v1/') === 0) {
        $limit = technorapper_agent_rate_limit();
        if (is_wp_error($limit)) {
            return $limit;
        }
    }
    return $result;
}
add_filter('rest_pre_dispatch', 'technorapper_agent_pre_dispatch', 10, 3);

function technorapper_agent_post($post)
{
    $id = (int) $post->ID;
    $categories = array();
    foreach (get_the_category($id) as $cat) {
        $categories[] = array(
            'id'   => (int) $cat->term_id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'url'  => get_category_link($cat),
        );
    }

    $content = apply_filters('the_content', $post->post_content);
    $excerpt = has_excerpt($id)
        ? get_the_excerpt($id)
        : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45);

    $disclosure = function_exists('technorapper_get_ai_disclosure')
        ? technorapper_get_ai_disclosure($id)
        : null;

    $provenance = array(
        'ai_wire'       => (bool) $disclosure,
        'ai_disclosure' => $disclosure ? wp_strip_all_tags((string) $disclosure['byline']) : null,
        'source'        => $disclosure && !empty($disclosure['source_label']) ? $disclosure['source_label'] : null,
        'source_url'    => $disclosure && !empty($disclosure['source_url']) ? $disclosure['source_url'] : null,
    );

    return array(
        'id'            => 'tr-' . $id,
        'wordpress_id'  => $id,
        'type'          => 'article',
        'title'         => get_the_title($id),
        'url'           => get_permalink($id),
        'canonical_url' => get_permalink($id),
        'published_at'  => get_post_time('c', true, $post),
        'updated_at'    => get_post_modified_time('c', true, $post),
        'excerpt'       => wp_strip_all_tags($excerpt),
        'content_html'  => $content,
        'content_text'  => wp_strip_all_tags($content),
        'categories'    => $categories,
        'image'         => technorapper_first_image($post),
        'author'        => array(
            'id'   => (int) $post->post_author,
            'name' => get_the_author_meta('display_name', $post->post_author),
        ),
        'provenance'    => $provenance,
        'links'         => array(
            'self'      => rest_url('tr/v1/articles/' . $id),
            'canonical' => get_permalink($id),
        ),
    );
}

function technorapper_agent_page_url($request, $page)
{
    if (!($request instanceof WP_REST_Request)) {
        return null;
    }

    $query_args = $request->get_query_params();
    unset($query_args['rest_route']);
    $query_args['page'] = (int) $page;

    return add_query_arg($query_args, rest_url(ltrim($request->get_route(), '/')));
}

function technorapper_agent_bounded_posts($posts, $per_page)
{
    return array_slice((array) $posts, 0, max(0, (int) $per_page));
}

function technorapper_agent_collection_response($posts, $page, $per_page, $total, $request = null)
{
    $items = array();
    foreach (technorapper_agent_bounded_posts($posts, $per_page) as $post) {
        $item = technorapper_agent_post($post);
        unset($item['content_html'], $item['content_text']);
        $items[] = $item;
    }

    $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;
    return new WP_REST_Response(array(
        'data' => $items,
        'pagination' => array(
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
            'total'       => (int) $total,
            'total_pages' => $pages,
            'has_next'    => (int) $page < $pages,
            'next_url'    => (int) $page < $pages
                ? technorapper_agent_page_url($request, (int) $page + 1)
                : null,
            'prev_url'    => (int) $page > 1
                ? technorapper_agent_page_url($request, (int) $page - 1)
                : null,
        ),
        'meta' => array(
            'api_version' => '1',
            'read_only'   => true,
            'generated_at'=> gmdate('c'),
        ),
    ), 200);
}

function technorapper_agent_articles($request)
{
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(50, max(1, (int) $request->get_param('per_page')));

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $category = sanitize_title((string) $request->get_param('category'));
    if ($category !== '') {
        $args['category_name'] = $category;
    }

    $after = trim((string) $request->get_param('after'));
    $before = trim((string) $request->get_param('before'));
    if ($after !== '' || $before !== '') {
        $date_query = array('inclusive' => true);
        if ($after !== '') {
            $date_query['after'] = $after;
        }
        if ($before !== '') {
            $date_query['before'] = $before;
        }
        $args['date_query'] = array($date_query);
    }

    $query = new WP_Query($args);
    return technorapper_agent_collection_response(
        $query->posts,
        $page,
        $per_page,
        (int) $query->found_posts,
        $request
    );
}

function technorapper_agent_article($request)
{
    $id = absint($request['id']);
    $post = get_post($id);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
        return new WP_Error('tr_article_not_found', 'Article not found.', array('status' => 404));
    }

    $data = technorapper_agent_post($post);
    $response = new WP_REST_Response(array(
        'data' => $data,
        'meta' => array(
            'api_version' => '1',
            'read_only'   => true,
        ),
    ), 200);

    $etag = '"' . md5($data['id'] . '|' . $data['updated_at']) . '"';
    $response->header('ETag', $etag);
    $response->header('Last-Modified', gmdate('D, d M Y H:i:s', strtotime($data['updated_at'])) . ' GMT');
    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
    if ($if_none_match === $etag) {
        $not_modified = new WP_REST_Response(null, 304);
        $not_modified->header('ETag', $etag);
        $not_modified->header('Cache-Control', 'public, max-age=60, s-maxage=300, stale-while-revalidate=600');
        return $not_modified;
    }
    return $response;
}

function technorapper_agent_search($request)
{
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(50, max(1, (int) $request->get_param('per_page')));

    $query = new WP_Query(array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        's'              => $request->get_param('q'),
        'orderby'        => 'relevance',
        'order'          => 'DESC',
    ));

    return technorapper_agent_collection_response(
        $query->posts,
        $page,
        $per_page,
        (int) $query->found_posts,
        $request
    );
}

function technorapper_agent_categories()
{
    $terms = get_categories(array(
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ));

    $data = array();
    foreach ($terms as $term) {
        $data[] = array(
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => wp_strip_all_tags($term->description),
            'count'       => (int) $term->count,
            'url'         => get_category_link($term),
        );
    }

    return new WP_REST_Response(array(
        'data' => $data,
        'meta' => array('api_version' => '1', 'read_only' => true),
    ), 200);
}

function technorapper_agent_status()
{
    return new WP_REST_Response(array(
        'name'         => 'TechnoRapper Agent API',
        'version'      => '1',
        'status'       => 'ok',
        'read_only'    => true,
        'documentation'=> home_url('/agents/'),
        'llms_txt'     => home_url('/llms.txt'),
        'updated_at'   => gmdate('c'),
    ), 200);
}
