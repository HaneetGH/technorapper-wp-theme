<?php
if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['technorapper_home_used_ids'] = array();

/**
 * Prefer AI Wire's explicitly curated homepage pool. Older imported posts
 * are allowed only as a fallback when a curated desk has too few stories.
 */
function technorapper_home_posts($args = array(), $allow_fallback = true)
{
    $base = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
    );
    $query = wp_parse_args($args, $base);
    $curated = wp_parse_args(array(
        'meta_key'   => '_tr_ai_wire_show_on_home',
        'meta_value' => '1',
    ), $query);
    $posts = get_posts($curated);

    if (!$allow_fallback || count($posts) >= (int) ($query['posts_per_page'] ?? 1)) {
        return $posts;
    }

    $fallback = get_posts($query);
    if (!$fallback) {
        return $posts;
    }

    $seen = array_map('intval', wp_list_pluck($posts, 'ID'));
    foreach ($fallback as $post) {
        if (!in_array((int) $post->ID, $seen, true)) {
            $posts[] = $post;
        }
        if (count($posts) >= (int) ($query['posts_per_page'] ?? 1)) {
            break;
        }
    }
    return $posts;
}

function technorapper_home_section($slug, $label, $limit = 4)
{
    $term = technorapper_desk_term($slug);
    if (!$term) {
        return;
    }

    $used = !empty($GLOBALS['technorapper_home_used_ids']) ? $GLOBALS['technorapper_home_used_ids'] : array();
    $posts = technorapper_home_posts(array(
        'posts_per_page'      => $limit,
        'category'            => $term->term_id,
        'post__not_in'        => $used,
    ));

    if (!$posts) {
        return;
    }
    ?>
    <section class="home-section">
      <div class="section-head">
        <div>
          <span class="section-kicker"><?php echo esc_html($label); ?></span>
          <h2><?php echo esc_html($term->name); ?></h2>
        </div>
        <a class="section-more" href="<?php echo esc_url(get_category_link($term)); ?>">View all <span aria-hidden="true">→</span></a>
      </div>
      <div class="home-cards">
        <?php foreach ($posts as $post) : $GLOBALS['post'] = $post; setup_postdata($post); ?>
          <article class="home-card">
            <a class="home-card-link" href="<?php the_permalink(); ?>">
              <div class="home-card-media">
                <?php $img = technorapper_first_image($post); if ($img) : ?>
                  <?php echo technorapper_img($img, get_the_title()); ?>
                <?php else : ?>
                  <span class="home-card-placeholder" aria-hidden="true"></span>
                <?php endif; ?>
              </div>
              <div class="home-card-copy">
                <span class="beat"><?php echo esc_html($term->name); ?></span>
                <h3><?php the_title(); ?></h3>
                <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
              </div>
            </a>
          </article>
        <?php endforeach; wp_reset_postdata(); ?>
      </div>
    </section>
    <?php
    foreach ($posts as $used_post) {
        $GLOBALS['technorapper_home_used_ids'][] = (int) $used_post->ID;
    }
}

$hero_posts = technorapper_home_posts(array(
    'posts_per_page' => 13,
), false);

$hero = $hero_posts ? $hero_posts[0] : null;
$side = array();
foreach (array('apple', 'ai', 'android') as $side_slug) {
    $side_term = technorapper_desk_term($side_slug);
    if (!$side_term) {
        continue;
    }
    $side_posts = technorapper_home_posts(array(
        'posts_per_page' => 1,
        'category'       => $side_term->term_id,
    ));
    if ($side_posts) {
        $side[] = $side_posts[0];
    }
}
if ($hero) {
    $GLOBALS['technorapper_home_used_ids'][] = (int) $hero->ID;
}
foreach ($side as $hero_side_post) {
    $GLOBALS['technorapper_home_used_ids'][] = (int) $hero_side_post->ID;
}
?>

<div class="home-shell">
  <?php if ($hero) : setup_postdata($hero); ?>
    <section class="home-hero" aria-label="Top stories">
      <article class="hero-main">
        <a href="<?php the_permalink(); ?>" class="hero-main-link">
          <div class="hero-media">
            <?php $img = technorapper_first_image($hero); if ($img) : ?>
              <?php echo technorapper_img($img, get_the_title()); ?>
            <?php endif; ?>
          </div>
          <div class="hero-copy">
            <span class="beat"><?php echo esc_html(($cat = get_the_category()) && isset($cat[0]) ? $cat[0]->name : 'Top Story'); ?></span>
            <h1><?php the_title(); ?></h1>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 30)); ?></p>
            <div class="hero-meta"><?php echo esc_html(get_the_date('M j, Y')); ?> · <?php echo esc_html(get_the_author()); ?></div>
          </div>
        </a>
      </article>

      <div class="hero-side">
        <?php foreach ($side as $side_post) : $GLOBALS['post'] = $side_post; setup_postdata($side_post); ?>
          <article class="hero-side-card">
            <a href="<?php the_permalink(); ?>">
              <div class="hero-side-media">
                <?php $img = technorapper_first_image($side_post); if ($img) echo technorapper_img($img, get_the_title()); ?>
              </div>
              <div class="hero-side-copy">
                <span class="beat"><?php echo esc_html(($cat = get_the_category()) && isset($cat[0]) ? $cat[0]->name : 'News'); ?></span>
                <h2><?php the_title(); ?></h2>
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j')); ?></time>
              </div>
            </a>
          </article>
        <?php endforeach; wp_reset_postdata(); ?>
      </div>
    </section>
  <?php wp_reset_postdata(); endif; ?>

  <div class="home-ad"><?php technorapper_ad_header(); ?></div>

  <section class="latest-section" aria-label="Latest technology news">
    <div class="section-head">
      <div>
        <span class="section-kicker">Fresh from the newsroom</span>
        <h2>Latest</h2>
      </div>
      <?php $news_term = technorapper_desk_term('tech'); ?>
      <a class="section-more" href="<?php echo esc_url($news_term ? get_category_link($news_term) : home_url('/')); ?>">All stories <span aria-hidden="true">→</span></a>
    </div>

    <div class="latest-grid">
      <?php
      $latest = array_slice($hero_posts, 4, 8);
      if (count($latest) < 8) {
          $latest = array_merge(
              $latest,
              technorapper_home_posts(array(
                  'posts_per_page' => 8 - count($latest),
                  'post__not_in'   => !empty($GLOBALS['technorapper_home_used_ids']) ? $GLOBALS['technorapper_home_used_ids'] : array(),
              ))
          );
          $latest = array_slice($latest, 0, 8);
      }
      foreach ($latest as $post) :
          $GLOBALS['post'] = $post;
          setup_postdata($post);
      ?>
        <article class="latest-item">
          <a href="<?php the_permalink(); ?>">
            <div class="latest-thumb">
              <?php $img = technorapper_first_image($post); if ($img) echo technorapper_img($img, get_the_title()); ?>
            </div>
            <div>
              <span class="beat"><?php echo esc_html(($cat = get_the_category()) && isset($cat[0]) ? $cat[0]->name : 'News'); ?></span>
              <h3><?php the_title(); ?></h3>
              <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 16)); ?></p>
              <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
            </div>
          </a>
        </article>
      <?php endforeach; wp_reset_postdata(); ?>
    </div>
  </section>

  <?php
  foreach ($latest as $used_post) {
      $GLOBALS['technorapper_home_used_ids'][] = (int) $used_post->ID;
  }
  foreach (array(
      array('ai', 'AI Desk'),
      array('apple', 'Apple Desk'),
      array('android', 'Android Desk'),
      array('reviews', 'Reviews'),
  ) as $section) {
      technorapper_home_section($section[0], $section[1], 4);
  }
  ?>

  <section class="home-newsletter" aria-label="Stay informed">
    <div>
      <span class="section-kicker">Stay in the loop</span>
      <h2>Technology worth knowing about.</h2>
      <p>Follow TechnoRapper for concise technology news, practical guides, reviews and analysis.</p>
    </div>
    <a href="<?php echo esc_url(home_url('/contact-us-page/')); ?>">Get in touch <span aria-hidden="true">→</span></a>
  </section>
</div>
