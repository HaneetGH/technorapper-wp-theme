<?php
if (!defined('ABSPATH')) {
    exit;
}

function technorapper_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets'));
    add_theme_support('responsive-embeds');
    set_post_thumbnail_size(1160, 680, true);\n    add_image_size('technorapper-card', 720, 450, true);\n    add_image_size('technorapper-hero', 1160, 720, true);
    register_nav_menus(array(
        'primary' => 'Primary',
        'footer'  => 'Footer',
    ));
}
add_action('after_setup_theme', 'technorapper_setup');

function technorapper_widgets()
{
    $shared = array(
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    );
    register_sidebar(array_merge($shared, array(
        'name'        => 'Main Sidebar',
        'id'          => 'sidebar-1',
        'description' => 'Sidebar widgets.',
    )));
    register_sidebar(array_merge($shared, array(
        'name'        => 'Front-page Sidebar',
        'id'          => 'home-sidebar-widgets',
        'description' => 'Optional front-page widgets.',
    )));
    register_sidebar(array_merge($shared, array(
        'name'        => 'In-story advertisement',
        'id'          => 'after-content',
        'description' => 'Optional unit under the article.',
    )));
}
add_action('widgets_init', 'technorapper_widgets');

function technorapper_assets()
{
    wp_enqueue_style('technorapper', get_stylesheet_uri(), array(), '1.1.1');
    $client = technorapper_adsense_client();
    if ($client === '' || class_exists('Google\\Site_Kit\\Plugin')) {
        return;
    }
    wp_enqueue_script(
        'technorapper-adsense',
        'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode($client),
        array(),
        null,
        false
    );
}
add_action('wp_enqueue_scripts', 'technorapper_assets');

function technorapper_adsense_tag($tag, $handle)
{
    if ($handle === 'technorapper-adsense') {
        $client = technorapper_adsense_client();
        if ($client === '') {
            return '';
        }
        return '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($client) . '" crossorigin="anonymous"></script>' . "\n";
    }
    return $tag;
}
add_filter('script_loader_tag', 'technorapper_adsense_tag', 10, 2);

function technorapper_desk_term($slug)
{
    $term = get_term_by('slug', $slug, 'category');
    return ($term && !is_wp_error($term)) ? $term : null;
}

function technorapper_desks()
{
    $custom = trim((string) get_theme_mod('desk_slugs', ''));
    if ($custom !== '') {
        $desks = array();
        foreach (explode(',', $custom) as $slug) {
            $term = technorapper_desk_term(sanitize_title(trim($slug)));
            if ($term) {
                $desks[] = array($term->slug, $term->name);
            }
        }
        if ($desks) {
            return $desks;
        }
    }
    $defaults = array(
        array('apple', 'Apple'),
        array('ai', 'AI'),
        array('android', 'Android'),
        array('auto', 'Auto'),
        array('pc-hardware', 'PC and Hardware'),
        array('reviews', 'Reviews'),
        array('guides', 'Guides'),
        array('tech', 'News'),
    );
    $desks = array();
    foreach ($defaults as $desk) {
        if (technorapper_desk_term($desk[0])) {
            $desks[] = $desk;
        }
    }
    if ($desks) {
        return $desks;
    }
    $cats = get_categories(array(
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => 8,
        'hide_empty' => true,
    ));
    foreach ($cats as $cat) {
        $desks[] = array($cat->slug, $cat->name);
    }
    return $desks;
}

function technorapper_first_image($post = null)
{
    $post = get_post($post);
    if (!$post) {
        return '';
    }
    if (has_post_thumbnail($post)) {
        $url = get_the_post_thumbnail_url($post, 'technorapper-card');
        if ($url) {
            return $url;
        }
    }
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)/i', $post->post_content, $matches)) {
        foreach ($matches[1] as $src) {
            if (preg_match('/pixel|spacer|tracking|gravatar|emoji|1x1|data:image/i', $src)) {
                continue;
            }
            return strpos($src, '//') === 0 ? 'https:' . $src : $src;
        }
    }
    return '';
}

function technorapper_img($url, $alt = '')
{
    if (!$url) {
        return '';
    }
    if ($alt === '') {
        $alt = get_the_title();
    }
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async">';
}

function technorapper_social_image()
{
    if (is_singular()) {
        $img = technorapper_first_image(get_queried_object_id());
        if ($img) {
            return $img;
        }
    }
    $latest = get_posts(array(
        'posts_per_page'      => 8,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
    foreach ($latest as $post) {
        $img = technorapper_first_image($post);
        if ($img) {
            return $img;
        }
    }
    return '';
}

function technorapper_social_tags($tags)
{
    if (!is_array($tags)) {
        return $tags;
    }
    $img = technorapper_social_image();
    if (!$img) {
        return $tags;
    }
    $has_image = false;
    foreach ($tags as $key => $value) {
        $property = is_string($key) ? $key : '';
        if (is_array($value) && isset($value['property'])) {
            $property = (string) $value['property'];
        }
        if (in_array($property, array('og:image', 'twitter:image', 'og:image:url'), true)) {
            $has_image = true;
            break;
        }
    }
    if ($has_image) {
        return $tags;
    }
    if (array_key_exists('og:title', $tags) || array_key_exists('og:type', $tags) || array_key_exists('og:url', $tags)) {
        $tags['og:image'] = $img;
        $tags['og:image:secure_url'] = $img;
        $tags['og:image:alt'] = wp_strip_all_tags(wp_get_document_title());
    } elseif (array_key_exists('twitter:card', $tags) || array_key_exists('twitter:title', $tags)) {
        $tags['twitter:image'] = $img;
        $tags['twitter:card'] = 'summary_large_image';
    } else {
        $tags['og:image'] = $img;
        $tags['twitter:image'] = $img;
    }
    return $tags;
}
add_filter('aioseo_facebook_tags', 'technorapper_social_tags');
add_filter('aioseo_twitter_tags', 'technorapper_social_tags');

function technorapper_og_fallback()
{
    if (defined('AIOSEO_VERSION')) {
        return;
    }
    $img = technorapper_social_image();
    if (!$img) {
        return;
    }
    echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
    echo '<meta property="og:image:secure_url" content="' . esc_url($img) . '" />' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr(wp_strip_all_tags(wp_get_document_title())) . '" />' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($img) . '" />' . "\n";
}
add_action('wp_head', 'technorapper_og_fallback', 40);

function technorapper_focus_keyphrase_text($title, $slug = '')
{
    $title = html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES, 'UTF-8');
    $title = preg_replace('/\s+/u', ' ', trim($title));
    $title = preg_replace('/\s*[\|\x{2013}\x{2014}]\s*TechnoRapper.*$/iu', '', $title);
    $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > 8) {
        $words = array_slice($words, 0, 8);
    }
    $phrase = implode(' ', $words);
    if ($phrase === '') {
        $phrase = trim(str_replace('-', ' ', (string) $slug));
    }
    return $phrase;
}

function technorapper_aioseo_focus_keyphrase($post_id, $post)
{
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
        return;
    }
    if (!class_exists('\AIOSEO\Plugin\Common\Models\Post')) {
        return;
    }
    $phrase = technorapper_focus_keyphrase_text($post->post_title, $post->post_name);
    if ($phrase === '') {
        return;
    }
    $row = \AIOSEO\Plugin\Common\Models\Post::getPost($post_id);
    $kp = $row->keyphrases;
    if (is_string($kp)) {
        $kp = json_decode($kp);
    }
    if (is_object($kp) && !empty($kp->focus->keyphrase)) {
        return;
    }
    if (!is_object($kp)) {
        $kp = \AIOSEO\Plugin\Common\Models\Post::getKeyphrasesDefaults();
    }
    if (!isset($kp->focus) || !is_object($kp->focus)) {
        $kp->focus = (object) array(
            'keyphrase' => '',
            'score'     => 0,
            'analysis'  => (object) array(),
        );
    }
    $kp->focus->keyphrase = $phrase;
    $row->keyphrases = $kp;
    $row->save();
}
add_action('save_post_post', 'technorapper_aioseo_focus_keyphrase', 99, 2);

function technorapper_content_img_alts($content)
{
    $content = preg_replace('/<img[^>]*\bbrief\b[^>]*>/i', '', $content, 1);
    $content = preg_replace('/<img[^>]*align=["\']left["\'][^>]*>/i', '', $content, 1);
    $title = wp_strip_all_tags(get_the_title());
    if ($title === '') {
        return $content;
    }
    return preg_replace_callback('/<img\b[^>]*>/i', function ($matches) use ($title) {
        $tag = $matches[0];
        if (preg_match('/\balt\s*=\s*(["\'])([^"\']*)\1/', $tag, $alt) && trim(html_entity_decode($alt[2], ENT_QUOTES, 'UTF-8')) !== '') {
            return $tag;
        }
        $tag = preg_replace('/\salt=("[^"]*"|\'[^\']*\')/', '', $tag);
        return preg_replace('/<img\b/i', '<img alt="' . esc_attr($title) . '"', $tag, 1);
    }, $content);
}
add_filter('the_content', 'technorapper_content_img_alts', 20);

function technorapper_adsense_client()
{
    $client = apply_filters('technorapper_adsense_client', get_theme_mod('adsense_client', ''));
    return preg_replace('/[^a-z0-9-]/i', '', (string) $client);
}

function technorapper_adsense_slot($which)
{
    $slot = apply_filters('technorapper_adsense_slot_' . $which, get_theme_mod('adsense_slot_' . $which, ''));
    return preg_replace('/[^0-9]/', '', (string) $slot);
}

function technorapper_ad_placeholder($kind = 'box')
{
    if (get_theme_mod('hide_ad_placeholders')) {
        return '';
    }
    $class = ($kind === 'banner' || $kind === 'inarticle') ? 'ad banner placeholder' : 'ad placeholder';
    $html  = '<aside class="' . esc_attr($class) . '" aria-label="Advertisement">';
    $html .= '<span>Advertisement</span>';
    $html .= '<small>Set your ads in Appearance → Customize → Ads</small>';
    $html .= '</aside>';
    return $html;
}

function technorapper_ad_markup($slot, $kind = 'box')
{
    $client = technorapper_adsense_client();
    $slot = preg_replace('/[^0-9]/', '', (string) $slot);
    $class = ($kind === 'banner' || $kind === 'inarticle') ? 'ad banner' : 'ad';
    if ($client !== '' && $slot !== '') {
        $html  = '<aside class="' . esc_attr($class) . '">';
        $html .= '<ins class="adsbygoogle" style="display:block;width:100%" data-ad-client="' . esc_attr($client) . '" data-ad-slot="' . esc_attr($slot) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>';
        $html .= '<script>(window.adsbygoogle = window.adsbygoogle || []).push({});</script>';
        $html .= '</aside>';
        return $html;
    }
    return technorapper_ad_placeholder($kind);
}

function technorapper_ad_unit($slot, $kind = 'box')
{
    echo technorapper_ad_markup($slot, $kind);
}

function technorapper_ad_box()
{
    technorapper_ad_unit(technorapper_adsense_slot('box'), 'box');
}

function technorapper_ad_leader()
{
    technorapper_ad_unit(technorapper_adsense_slot('article'), 'inarticle');
}

function technorapper_ad_header()
{
    technorapper_ad_leader();
}

function technorapper_content_ads($content)
{
    if (is_admin() || is_feed() || !is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $slot = technorapper_adsense_slot('article');
    $live = technorapper_adsense_client() !== '' && $slot !== '';
    $end  = $live ? technorapper_ad_markup($slot, 'inarticle') : technorapper_ad_placeholder('inarticle');
    if (!$live) {
        return $content . $end;
    }
    $mid = technorapper_ad_markup($slot, 'inarticle');
    $parts = preg_split('/<\/p>/i', $content, 3);
    if (count($parts) >= 3) {
        $content = $parts[0] . '</p>' . $parts[1] . '</p>' . $mid . $parts[2];
    }
    return $content . $end;
}
add_filter('the_content', 'technorapper_content_ads', 30);

function technorapper_customize_register($wp_customize)
{
    $wp_customize->add_section('technorapper_ads', array(
        'title'       => 'Ads',
        'description' => 'Paste your own ad client and slot IDs. Leave them empty to show labeled placeholders.',
    ));
    $wp_customize->add_setting('adsense_client', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('adsense_client', array(
        'label'       => 'Ad client',
        'description' => 'Example: ca-pub-xxxxxxxxxxxxxxxx',
        'section'     => 'technorapper_ads',
        'type'        => 'text',
    ));
    foreach (array('box' => 'Sidebar slot', 'article' => 'In-article slot') as $key => $label) {
        $id = 'adsense_slot_' . $key;
        $wp_customize->add_setting($id, array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control($id, array(
            'label'   => $label,
            'section' => 'technorapper_ads',
            'type'    => 'text',
        ));
    }
    $wp_customize->add_setting('hide_ad_placeholders', array(
        'default'           => false,
        'sanitize_callback' => 'wp_validate_boolean',
    ));
    $wp_customize->add_control('hide_ad_placeholders', array(
        'label'   => 'Hide ad placeholders until units are connected',
        'section' => 'technorapper_ads',
        'type'    => 'checkbox',
    ));
    $wp_customize->add_section('technorapper_desks', array(
        'title' => 'Desks',
    ));
    $wp_customize->add_setting('desk_slugs', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('desk_slugs', array(
        'label'       => 'Desk category slugs',
        'description' => 'Comma-separated category slugs, in order. Leave empty to use matching defaults or your busiest categories.',
        'section'     => 'technorapper_desks',
        'type'        => 'text',
    ));
}
add_action('customize_register', 'technorapper_customize_register');

function technorapper_assign_menus()
{
    $locations = get_theme_mod('nav_menu_locations', array());
    if (!empty($locations['primary'])) {
        return;
    }
    $menus = wp_get_nav_menus();
    foreach ($menus as $menu) {
        $name = strtolower($menu->name . ' ' . $menu->slug);
        if (strpos($name, 'primary') !== false || strpos($name, 'main') !== false) {
            $locations['primary'] = (int) $menu->term_id;
            set_theme_mod('nav_menu_locations', $locations);
            break;
        }
    }
}
add_action('after_switch_theme', 'technorapper_assign_menus');
add_action('after_switch_theme', 'flush_rewrite_rules');

function technorapper_content_width()
{
    $GLOBALS['content_width'] = 760;
}
add_action('after_setup_theme', 'technorapper_content_width', 0);

function technorapper_menu_fallback()
{
    echo '<ul>';
    foreach (technorapper_desks() as $desk) {
        $term = technorapper_desk_term($desk[0]);
        if (!$term) {
            continue;
        }
        $on = is_category($term->term_id) ? ' aria-current="page"' : '';
        echo '<li><a href="' . esc_url(get_category_link($term)) . '"' . $on . '>' . esc_html($desk[1]) . '</a></li>';
    }
    $contact = get_page_by_path('contact-us-page');
    if ($contact) {
        echo '<li><a href="' . esc_url(get_permalink($contact)) . '">Contact Us</a></li>';
    }
    echo '</ul>';
}
