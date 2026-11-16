<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read-only AI Wire newsroom metrics. The theme intentionally treats the
 * plugin as optional: when AI Wire is inactive, this section is not rendered.
 */
function technorapper_ai_wire_home_stats()
{
    if (!class_exists('TechnoRapper_AI_Wire')) {
        return null;
    }

    global $wpdb;
    $items_table = $wpdb->prefix . TechnoRapper_AI_Wire::TABLE_SUFFIX;
    $quarantine_table = $wpdb->prefix . TechnoRapper_AI_Wire::QUARANTINE_TABLE_SUFFIX;

    $items_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($items_table)));
    $quarantine_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($quarantine_table)));
    if ($items_exists !== $items_table) {
        return null;
    }

    $since = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
    $stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS discovered,
                SUM(CASE WHEN review_json IS NOT NULL AND review_json <> '' THEN 1 ELSE 0 END) AS evaluated,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published
             FROM {$items_table}
             WHERE created_at >= %s",
            $since
        ),
        ARRAY_A
    );

    $rejected = 0;
    if ($quarantine_exists === $quarantine_table) {
        $rejected = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$quarantine_table} WHERE created_at >= %s",
                $since
            )
        );
    }

    if (!$stats) {
        return null;
    }

    return array(
        'discovered' => (int) ($stats['discovered'] ?? 0),
        'evaluated'  => (int) ($stats['evaluated'] ?? 0),
        'rejected'   => $rejected,
        'published'  => (int) ($stats['published'] ?? 0),
    );
}

function technorapper_home_section($slug, $label, $posts)
{
    $term = technorapper_desk_term($slug);
    if (!$term || !$posts) { return; }
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
                <p><?php echo esc_html(technorapper_card_text($post, 20)); ?></p>
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
              </div>
            </a>
          </article>
        <?php endforeach; wp_reset_postdata(); ?>
      </div>
    </section>
    <?php
}

$home = technorapper_home_layout(technorapper_recent_posts(100), technorapper_desks());
$hero = $home['hero'];
$side = $home['side'];
$latest = $home['latest'];
$ai_stats = technorapper_ai_wire_home_stats();
?>

<div class="home-shell">
  <?php if ($ai_stats) : ?>
  <section id="ai-newsroom" class="ai-newsroom-banner" aria-labelledby="ai-signal-title">
    <div class="ai-newsroom-copy">
      <span class="section-kicker">TechnoRapper AI Newsroom</span>
      <h2 id="ai-signal-title">We don't publish everything we find.</h2>
      <p>Every candidate is discovered, researched, evaluated, verified, written and quality-checked before publication.</p>
    </div>

    <div class="ai-newsroom-metrics" aria-label="Last 30 days newsroom metrics">
      <div><strong><?php echo esc_html(number_format_i18n($ai_stats['discovered'])); ?></strong><span>Discovered</span></div>
      <i aria-hidden="true">→</i>
      <div><strong><?php echo esc_html(number_format_i18n($ai_stats['evaluated'])); ?></strong><span>Evaluated</span></div>
      <i aria-hidden="true">→</i>
      <div><strong><?php echo esc_html(number_format_i18n($ai_stats['published'])); ?></strong><span>Published</span></div>
      <i aria-hidden="true">→</i>
      <div class="ai-rejected"><strong><?php echo esc_html(number_format_i18n($ai_stats['rejected'])); ?></strong><span>Rejected</span></div>
    </div>

    <div class="ai-newsroom-agents" aria-label="AI newsroom pipeline">
      <span><b>01</b> Discover</span>
      <span><b>02</b> Research</span>
      <span><b>03</b> Evaluate</span>
      <span><b>04</b> Verify</span>
      <span><b>05</b> Write</span>
      <span><b>06</b> Quality Gate</span>
    </div>

    <a class="ai-newsroom-how" href="<?php echo esc_url(home_url('/?tr_ai_wire_how_it_works=1')); ?>">See how it works <span aria-hidden="true">→</span></a>
  </section>
  <?php endif; ?>

  <?php if ($hero) : $GLOBALS['post'] = $hero; setup_postdata($hero); ?>
    <section class="home-hero" aria-label="Top stories">
      <article class="hero-main">
        <a href="<?php the_permalink(); ?>" class="hero-main-link">
          <div class="hero-media">
            <?php $img = technorapper_first_image($hero); if ($img) echo technorapper_img($img, get_the_title(), true); ?>
          </div>
          <div class="hero-copy">
            <span class="beat"><?php echo esc_html(($cat = get_the_category()) && isset($cat[0]) ? $cat[0]->name : 'Top Story'); ?></span>
            <h1><?php the_title(); ?></h1>
            <p><?php echo esc_html(technorapper_card_text($hero, 30)); ?></p>
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
              <p><?php echo esc_html(technorapper_card_text($post, 16)); ?></p>
              <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
            </div>
          </a>
        </article>
      <?php endforeach; wp_reset_postdata(); ?>
    </div>
  </section>


  <?php
  foreach ($home['sections'] as $section) {
      $slug = $section[0];
      $term = technorapper_desk_term($slug);
      if (!$term) { continue; }
      technorapper_home_section($slug, $term->name . ' Desk', $section[1]);
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
