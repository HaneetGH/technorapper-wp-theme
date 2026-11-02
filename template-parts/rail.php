<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="side">
  <?php technorapper_ad_box(); ?>
  <aside class="rail" aria-label="Desks">
    <h2>Desks</h2>
    <div class="rail-row">
      <?php foreach (technorapper_desks() as $desk) :
          $term = technorapper_desk_term($desk[0]);
          if (!$term) {
              continue;
          }
          $latest = get_posts(array(
              'posts_per_page' => 1,
              'category'       => $term->term_id,
              'no_found_rows'  => true,
          ));
          $on = is_category($term->term_id) ? ' aria-current="page"' : '';
          ?>
        <a class="desk-link" href="<?php echo esc_url(get_category_link($term)); ?>"<?php echo $on; ?>>
          <span class="name"><?php echo esc_html($desk[1]); ?></span>
          <span class="count"><?php echo esc_html(number_format_i18n((int) $term->count)); ?></span>
          <span class="latest"><?php echo $latest ? esc_html(get_the_title($latest[0])) : ''; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
