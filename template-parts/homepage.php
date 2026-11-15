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

  <?php if ($ai_stats) : ?>
  <section id="ai-newsroom" class="ai-signal-section" aria-labelledby="ai-signal-title">
    <div class="ai-signal-intro">
      <div>
        <span class="section-kicker">TechnoRapper AI Newsroom</span>
        <h2 id="ai-signal-title">We don't publish everything we find.</h2>
        <p>TechnoRapper monitors the technology world continuously, then puts every candidate through specialized AI agents before it earns a place in the newsroom.</p>
      </div>
      <a class="ai-signal-how" href="<?php echo esc_url(home_url('/?tr_ai_wire_how_it_works=1')); ?>">See how the AI newsroom works <span aria-hidden="true">→</span></a>
    </div>

    <div class="ai-signal-board">
      <div class="ai-signal-flow">
        <div class="ai-signal-step">
          <span class="ai-signal-number"><?php echo esc_html(number_format_i18n($ai_stats['discovered'])); ?></span>
          <strong>Discovered</strong>
          <small>Stories found</small>
        </div>
        <span class="ai-signal-arrow" aria-hidden="true">→</span>
        <div class="ai-signal-step">
          <span class="ai-signal-number"><?php echo esc_html(number_format_i18n($ai_stats['evaluated'])); ?></span>
          <strong>Evaluated</strong>
          <small>Worth pursuing</small>
        </div>
        <span class="ai-signal-arrow" aria-hidden="true">→</span>
        <div class="ai-signal-step">
          <span class="ai-signal-number"><?php echo esc_html(number_format_i18n($ai_stats['published'])); ?></span>
          <strong>Published</strong>
          <small>Made the cut</small>
        </div>
      </div>

      <div class="ai-rejection">
        <span class="ai-rejection-kicker">The stories that didn't make it</span>
        <strong><?php echo esc_html(number_format_i18n($ai_stats['rejected'])); ?></strong>
        <p>Rejected by the editorial quality gate in the last 30 days.</p>
      </div>
    </div>

    <div class="ai-agent-strip" aria-label="AI newsroom agents">
      <span><b>01</b> Discover Agent</span>
      <span><b>02</b> Research Agent</span>
      <span><b>03</b> Evaluate Agent</span>
      <span><b>04</b> Verify Agent</span>
      <span><b>05</b> Write Agent</span>
      <span><b>06</b> Quality Gate</span>
    </div>

    <p class="ai-signal-footer">Less noise. More signal. <span>Being first isn't enough. Being worth reading is.</span></p>
  </section>
  <?php endif; ?>

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

<style>
.ai-signal-section{margin-top:44px;background:var(--bar);color:#fff;padding:30px 30px 26px;position:relative;overflow:hidden}
.ai-signal-section:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(91,77,255,.22),transparent 42%);pointer-events:none}
.ai-signal-intro,.ai-signal-board,.ai-agent-strip,.ai-signal-footer{position:relative;z-index:1}
.ai-signal-intro{display:flex;align-items:flex-end;justify-content:space-between;gap:24px}
.ai-signal-intro h2{font-size:clamp(27px,4vw,44px);line-height:1.02;letter-spacing:-.045em;margin:5px 0 10px}
.ai-signal-intro p{max-width:720px;color:#cfc6e4;margin:0;font-family:Georgia,"Iowan Old Style",Palatino,serif;font-size:16px;line-height:1.5}
.ai-signal-how{color:var(--accent-bright);font-weight:800;font-size:12px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.ai-signal-how:hover{color:#fff}
.ai-signal-board{display:grid;grid-template-columns:minmax(0,1fr) 260px;gap:0;margin-top:28px;border-top:1px solid #383154;border-bottom:1px solid #383154}
.ai-signal-flow{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:stretch}
.ai-signal-step{padding:22px 18px 20px;min-width:0}
.ai-signal-step strong{display:block;margin-top:3px;font-size:15px}
.ai-signal-step small{display:block;color:#9f96b8;font-size:11px;margin-top:2px}
.ai-signal-number{display:block;color:var(--accent-bright);font-size:34px;font-weight:800;letter-spacing:-.04em;font-variant-numeric:tabular-nums}
.ai-signal-arrow{display:flex;align-items:center;color:#655b82;font-size:22px}
.ai-rejection{border-left:1px solid #383154;padding:20px 20px 18px;display:flex;flex-direction:column;justify-content:center}
.ai-rejection-kicker{color:#cfc6e4;font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.ai-rejection strong{font-size:52px;line-height:1;margin:7px 0;color:#fff;letter-spacing:-.05em;font-variant-numeric:tabular-nums}
.ai-rejection p{color:#9f96b8;font-size:12px;line-height:1.4;margin:0}
.ai-agent-strip{display:flex;gap:0;margin-top:18px;overflow-x:auto;scrollbar-width:none}
.ai-agent-strip::-webkit-scrollbar{display:none}
.ai-agent-strip span{flex:1 0 auto;padding:8px 14px;border-right:1px solid #383154;color:#bcb3cf;font-family:ui-monospace,Menlo,monospace;font-size:10px;letter-spacing:.04em;white-space:nowrap}
.ai-agent-strip span:first-child{padding-left:0}
.ai-agent-strip span:last-child{border-right:0}
.ai-agent-strip b{color:var(--accent-bright);margin-right:5px}
.ai-signal-footer{margin:18px 0 0;color:#fff;font-size:15px;font-weight:800}
.ai-signal-footer span{color:#9f96b8;font-weight:500;margin-left:8px}
@media(max-width:800px){
  .ai-signal-intro{align-items:flex-start;flex-direction:column}
  .ai-signal-how{white-space:normal}
  .ai-signal-board{grid-template-columns:1fr}
  .ai-rejection{border-left:0;border-top:1px solid #383154}
}
@media(max-width:640px){
  .ai-signal-section{padding:24px 18px 22px}
  .ai-signal-intro h2{font-size:30px}
  .ai-signal-board{margin-top:22px}
  .ai-signal-flow{grid-template-columns:1fr 1fr 1fr}
  .ai-signal-arrow{display:none}
  .ai-signal-step{padding:18px 10px}
  .ai-signal-step:first-child{padding-left:0}
  .ai-signal-step:last-child{padding-right:0}
  .ai-signal-number{font-size:27px}
  .ai-signal-footer{font-size:13px}
  .ai-signal-footer span{display:block;margin:4px 0 0}
}
</style>
