<?php
if (!defined('ABSPATH')) {
    exit;
}
$lead = !empty($args['lead']);
$img  = technorapper_first_image();
$cats = get_the_category();
$desk = $cats ? $cats[0]->name : '';
$class = 'card';
if ($lead && $img) {
    $class .= ' lead';
} elseif (!$img) {
    $class .= ' textonly';
}
?>
<article class="<?php echo esc_attr($class); ?>">
  <a class="card-link" href="<?php the_permalink(); ?>">
    <?php if ($img) : ?>
      <span class="pic"><?php echo technorapper_img($img, ''); ?></span>
    <?php endif; ?>
    <span class="copy">
      <span class="beat"><?php echo esc_html(trim($desk . ' · ' . get_the_date('j M Y'), ' ·')); ?></span>
      <strong><?php the_title(); ?></strong>
      <span class="line"><?php echo esc_html(technorapper_card_text(get_post(), 28)); ?></span>
    </span>
  </a>
</article>
