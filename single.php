<?php
get_header();
the_post();
$cats = get_the_category();
$desk = $cats ? $cats[0] : null;
$img  = technorapper_first_image();
?>
<article class="article">
  <div>
    <?php if ($desk) : ?>
      <a class="kicker" href="<?php echo esc_url(get_category_link($desk)); ?>"><?php echo esc_html($desk->name); ?></a>
    <?php endif; ?>
    <h1><?php the_title(); ?></h1>
    <p class="meta"><?php echo esc_html(get_the_date('j M Y')); ?></p>
    <?php if (has_excerpt()) : ?>
      <p class="standfirst"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p>
    <?php endif; ?>
    <?php if ($img) : ?>
      <div class="photo ratio"><?php echo technorapper_img($img); ?></div>
    <?php endif; ?>
    <div class="body">
      <?php the_content(); ?>
    </div>
    <p class="permalink">Permanent link · <a href="<?php the_permalink(); ?>"><?php the_permalink(); ?></a></p>
  </div>
  <aside>
    <?php technorapper_ad_box(); ?>
    <?php
    if ($desk) :
        $more = technorapper_more_stories(get_the_ID(), $desk->term_id);
        if ($more) :
            ?>
      <div class="more">
        <div class="panel-h"><span>More in <?php echo esc_html($desk->name); ?></span></div>
        <?php foreach ($more as $related) : ?>
          <a href="<?php echo esc_url($related['url']); ?>"><?php echo esc_html($related['title']); ?></a>
        <?php endforeach; ?>
      </div>
            <?php
        endif;
    endif;
    ?>
  </aside>
</article>
<?php
comments_template();
get_footer();
