<?php
if (!defined('ABSPATH')) {
    exit;
}
$pool = new WP_Query(array(
    'posts_per_page'      => 24,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
));
if (!$pool->have_posts()) {
    return;
}
$picked = array();
while ($pool->have_posts()) {
    $pool->the_post();
    $img = technorapper_first_image();
    if (!$img) {
        continue;
    }
    $picked[] = array('id' => get_the_ID(), 'img' => $img);
    if (count($picked) >= 8) {
        break;
    }
}
wp_reset_postdata();
if (!$picked) {
    return;
}
$n = 0;
echo '<div class="board">';
foreach ($picked as $row) {
    $post = get_post($row['id']);
    $GLOBALS['post'] = $post;
    setup_postdata($post);
    $img = $row['img'];
    $cats = get_the_category($post);
    $desk = $cats ? $cats[0]->name : '';
    $kind = $n === 0 ? ' hero' : '';
    $n++;
    echo '<a class="tile' . esc_attr($kind) . '" href="' . esc_url(get_permalink()) . '">';
    if ($img) {
        echo '<img src="' . esc_url($img) . '" alt="">';
    }
    echo '<span class="cap"><span class="beat">' . esc_html($desk) . '</span><strong>' . esc_html(get_the_title()) . '</strong></span>';
    echo '</a>';
}
echo '</div>';
wp_reset_postdata();
