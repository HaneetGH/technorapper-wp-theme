<?php
if (!defined('ABSPATH')) {
    exit;
}

function technorapper_agent_schema()
{
    if (!is_singular('post')) {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post || $post->post_type !== 'post') {
        return;
    }

    $description = has_excerpt($post->ID)
        ? get_the_excerpt($post->ID)
        : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 40);

    $article = array(
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        '@id' => get_permalink($post->ID) . '#article',
        'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => get_permalink($post->ID)),
        'headline' => wp_strip_all_tags(get_the_title($post->ID)),
        'description' => wp_strip_all_tags($description),
        'url' => get_permalink($post->ID),
        'datePublished' => get_post_time('c', true, $post),
        'dateModified' => get_post_modified_time('c', true, $post),
        'author' => array(
            '@type' => 'Person',
            'name' => get_the_author_meta('display_name', $post->post_author),
        ),
        'publisher' => array(
            '@type' => 'Organization',
            'name' => 'TechnoRapper',
            'url' => home_url('/'),
        ),
        'isAccessibleForFree' => true,
    );

    $image = technorapper_first_image($post);
    if ($image) {
        $article['image'] = array($image);
    }

    $cats = get_the_category($post->ID);
    if ($cats) {
        $article['articleSection'] = array_map(function ($cat) {
            return $cat->name;
        }, $cats);
    }

    $disclosure = function_exists('technorapper_get_ai_disclosure')
        ? technorapper_get_ai_disclosure($post->ID)
        : null;
    if ($disclosure) {
        $article['keywords'] = array('AI-assisted', 'AI Wire', 'TechnoRapper');
        if (!empty($disclosure['source_url'])) {
            $article['isBasedOn'] = $disclosure['source_url'];
        }
    }

    echo '<script type="application/ld+json">' .
        wp_json_encode($article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
        '</script>' . "\n";
}
add_action('wp_head', 'technorapper_agent_schema', 25);
