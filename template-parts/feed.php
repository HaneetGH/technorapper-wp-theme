<?php
if (!defined('ABSPATH')) {
    exit;
}
$title = !empty($args['title']) ? $args['title'] : 'Latest';
?>
<section class="track" aria-label="<?php echo esc_attr($title); ?>">
  <h2><?php echo esc_html($title); ?></h2>
  <?php if (have_posts()) : ?>
    <p class="archive-meta">
      <?php
      global $wp_query;
      $paged = max(1, (int) get_query_var('paged'));
      $per   = (int) $wp_query->get('posts_per_page');
      $from  = ($paged - 1) * $per + 1;
      $to    = min($from + $wp_query->post_count - 1, (int) $wp_query->found_posts);
      echo esc_html(sprintf('Showing %s–%s of %s', number_format_i18n($from), number_format_i18n($to), number_format_i18n((int) $wp_query->found_posts)));
      ?>
    </p>
    <div class="feed">
      <?php
      $i = 0;
      while (have_posts()) {
          the_post();
          get_template_part('template-parts/card', null, array('lead' => $i === 0 && $paged === 1));
          $i++;
      }
      ?>
    </div>
    <?php
    the_posts_pagination(array(
        'mid_size'  => 2,
        'prev_text' => 'Previous',
        'next_text' => 'Next',
    ));
    ?>
  <?php else : ?>
    <div class="note"><p>No stories here yet.</p></div>
  <?php endif; ?>
</section>
