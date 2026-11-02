<?php
get_header();
$title = 'Latest';
if (is_category()) {
    $title = single_cat_title('', false);
} elseif (is_search()) {
    $title = 'Search · ' . get_search_query();
} elseif (is_date()) {
    $title = get_the_archive_title();
}
echo '<div class="fold">';
get_template_part('template-parts/rail');
get_template_part('template-parts/feed', null, array('title' => $title));
echo '</div>';
get_footer();
