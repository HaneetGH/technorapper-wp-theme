<?php
get_header();
echo '<div class="fold">';
get_template_part('template-parts/rail');
get_template_part('template-parts/feed', null, array('title' => 'Latest'));
echo '</div>';
get_footer();
