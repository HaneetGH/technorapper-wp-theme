<?php
get_header();
echo '<div class="fold">';
get_template_part('template-parts/rail');
get_template_part('template-parts/feed', null, array('title' => 'Search · ' . get_search_query()));
echo '</div>';
get_footer();
