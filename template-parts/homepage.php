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
$pulse_classes = function_exists('technorapper_pulse_classes')
    ? technorapper_pulse_classes()
    : array('ai-newsroom-banner', 'tr-pulse', 'is-desktop-compact', 'is-mobile-minimal', 'show-desktop-dek', 'show-desktop-metrics', 'show-desktop-pipeline', 'show-desktop-cta', 'hide-mobile-dek', 'show-mobile-metrics', 'hide-mobile-pipeline', 'show-mobile-cta');
// On-site AI Wire explainer served by the AI Wire plugin (pretty permalink /ai-wire/).
$how_ai_url = home_url('/ai-wire/');
?>

<div class="home-shell">
  <section id="tr-pulse" class="<?php echo esc_attr(implode(' ', $pulse_classes)); ?>" aria-labelledby="tr-pulse-title">
    <div class="ai-newsroom-copy">
      <div class="tr-pulse-brand"><span class="tr-pulse-mark" aria-hidden="true">ϟ</span><span id="tr-pulse-title">TR <em>PULSE</em></span></div>
      <p class="tr-pulse-dek">Agent newsroom: discover → evaluate → verify → write → publish.</p>
    </div>

    <div class="ai-newsroom-metrics" aria-label="Last 30 days AI Wire metrics">
      <div class="ai-discovered">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 3h7l4 4v14H7z"></path><path d="M14 3v5h5M10 13h5M10 17h5"></path></svg>
        <strong><?php echo esc_html($ai_stats ? number_format_i18n($ai_stats['discovered']) : '—'); ?></strong><span>Discovered</span>
      </div>
      <i aria-hidden="true"></i>
      <div class="ai-evaluated">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m15.5 15.5 5 5"></path></svg>
        <strong><?php echo esc_html($ai_stats ? number_format_i18n($ai_stats['evaluated']) : '—'); ?></strong><span>Evaluated</span>
      </div>
      <i aria-hidden="true"></i>
      <div class="ai-published">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="m7.5 12 3 3 6-6"></path></svg>
        <strong><?php echo esc_html($ai_stats ? number_format_i18n($ai_stats['published']) : '—'); ?></strong><span>Published</span>
      </div>
      <i aria-hidden="true"></i>
      <div class="ai-rejected">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6m0-6-6 6"></path></svg>
        <strong><?php echo esc_html($ai_stats ? number_format_i18n($ai_stats['rejected']) : '—'); ?></strong><span>Rejected</span>
      </div>
    </div>

    <div class="ai-newsroom-agents" aria-label="AI Wire publishing pipeline">
      <span><b>1</b> Discover</span>
      <span><b>2</b> Evaluate</span>
      <span><b>3</b> Verify</span>
      <span><b>4</b> Write</span>
      <span><b>5</b> Review</span>
      <span><b>6</b> Publish</span>
    </div>

    <a class="ai-newsroom-how" href="<?php echo esc_url($how_ai_url); ?>">How AI Wire Works <span aria-hidden="true">→</span></a>
  </section>

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
        <span class="section-kicker">Fresh from TR Pulse</span>
        <h2>Latest Stories</h2>
      </div>
      <?php $news_term = technorapper_desk_term('tech'); ?>
      <a class="section-more" href="<?php echo esc_url($news_term ? get_category_link($news_term) : home_url('/')); ?>">View all <span aria-hidden="true">→</span></a>
    </div>

    <div class="latest-layout">
      <div class="latest-grid">
        <?php
        foreach (array_slice($latest, 0, 4) as $post) :
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

      <aside class="ai-wire-card" aria-label="AI Wire">
        <div class="ai-wire-icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><path d="M16 3 29 10 16 17 3 10 16 3Z"></path><path d="m3 16 13 7 13-7M3 22l13 7 13-7"></path></svg></div>
        <div>
          <h3 class="ai-wire-title">AI Wire</h3>
          <p class="ai-wire-intro">The agent desk behind TechnoRapper — research, verification, and quality gates before publish.</p>
          <ul>
            <li>Multiple trusted sources</li>
            <li>AI research &amp; verification</li>
            <li>Independent AI review &amp; quality gate</li>
          </ul>
          <a href="<?php echo esc_url($how_ai_url); ?>">How AI Wire Works <span aria-hidden="true">→</span></a>
        </div>
      </aside>
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
