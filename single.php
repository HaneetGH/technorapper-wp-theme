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
    <?php if ($desk) :
        $more = new WP_Query(array(
            'posts_per_page'      => 4,
            'post__not_in'        => array(get_the_ID()),
            'cat'                 => $desk->term_id,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ));
        if ($more->have_posts()) :
            ?>
      <div class="more">
        <div class="panel-h"><span>More in <?php echo esc_html($desk->name); ?></span></div>
        <?php
            while ($more->have_posts()) {
                $more->the_post();
                echo '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
            }
            wp_reset_postdata();
            ?>
      </div>
        <?php endif; endif; ?>
  </aside>
</article>
<?php
comments_template();
get_footer();
