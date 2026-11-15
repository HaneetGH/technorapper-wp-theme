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

  <?php get_template_part('template-parts/ai-newsroom'); ?>

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


<style>
/* 1.5.0 visual refresh — homepage reference treatment */
:root{--paper:#fff;--card:#fff;--ink:#171326;--muted:#6b6578;--line:#ded9e7;--accent:#7c2cff;--accent-bright:#d6b7ff;--bar:#24102f}
body{background:#fff;color:var(--ink)}
.site-header{background:linear-gradient(180deg,#2a1236 0%,#210d2c 100%);box-shadow:0 2px 12px rgba(35,12,47,.16)}
.utils{background:transparent;color:#c8b9d0}
.utils .wrap{max-width:1160px;padding:8px 20px 0;justify-content:flex-end;gap:22px}
.utils .wrap>span:first-child{order:3}.utils .wrap .ai-newsroom-status{order:2}.utils .wrap>a{order:1}
.mast{max-width:1160px;padding:10px 20px 12px;gap:18px;min-height:62px}
.menu-toggle{width:28px;height:36px;order:0}.home-btn{display:none!important}
.brand{order:1;flex:1}.brand:before{content:"TR";display:inline-flex;align-items:center;justify-content:center;width:46px;height:34px;margin-right:10px;color:#fff;background:linear-gradient(135deg,#9b43ff,#6b21d9);font-weight:900;font-size:21px;letter-spacing:-.08em;clip-path:polygon(0 0,100% 0,82% 100%,18% 100%)}
.wordmark{font-size:31px}.tag{font-size:10px;color:#c4b6cd;letter-spacing:.04em;text-transform:none}.ai-newsroom-link{order:2;background:#151b18;border:1px solid #6aef9a;border-radius:999px;padding:7px 11px;color:#fff!important;font-size:10px;letter-spacing:.04em}.ai-newsroom-link:before{content:"";width:7px;height:7px;border-radius:50%;background:#58ed91;box-shadow:0 0 8px #58ed91}.find{order:3}.find input{border-color:#5b4664}.find button{background:transparent;color:#fff;border:0;padding:8px;font-size:0}.find button:after{content:"⌕";font-size:22px;color:#fff}
.nav{border-top:1px solid #40274a}.nav .wrap{max-width:1160px}.nav a{padding:14px 15px 13px;font-size:13px;letter-spacing:.01em;color:#ddd5e4;border-bottom:3px solid transparent}.nav a[aria-current=page],.nav a:hover{color:#fff;border-bottom-color:#b55cff}.nav ul{display:flex;align-items:center;gap:2px;list-style:none;margin:0;padding:0}.nav li{list-style:none}
main#content{padding:24px 0 56px}.home-shell{max-width:1160px;padding:0 20px 56px}.home-ad{min-height:0;margin:0 0 24px}.home-ad .ad{min-height:0}
.home-hero{grid-template-columns:minmax(0,1.62fr) minmax(320px,.86fr);gap:14px}.hero-main{border-radius:9px;overflow:hidden}.hero-media{aspect-ratio:16/9}.hero-copy{padding:19px 24px 22px}.hero-copy h1{font-size:clamp(32px,3.8vw,47px);line-height:1.02}.hero-copy p{font-size:15px}.hero-side{gap:12px}.hero-side-card{border:0;border-radius:8px;overflow:hidden;box-shadow:0 0 0 1px #e6e1ea}.hero-side-card>a{grid-template-columns:39% 61%;background:#fff}.hero-side-media{aspect-ratio:16/10}.hero-side-copy{padding:12px}.hero-side-copy h2{font-size:16px}.beat{color:#7c2cff}
.latest-section{margin-top:28px}.section-head{border-bottom:2px solid #d9d4df;padding-bottom:9px;margin-bottom:12px}.section-head:after{content:"";display:block}.section-kicker{font-size:10px;color:#7c2cff}.section-head h2{font-size:27px}.section-more{color:#7c2cff;text-transform:none;letter-spacing:0;font-size:11px}.latest-grid{grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}.latest-item{border:0;padding:0}.latest-item>a{display:flex;flex-direction:column;gap:0}.latest-thumb{width:100%;aspect-ratio:16/10;border-radius:7px}.latest-item h3{font-size:15px;line-height:1.2;margin:8px 0 5px}.latest-item p{display:none}.latest-item time{font-size:9px}.latest-item:hover h3{color:#7c2cff}
.ai-signal-section{margin-top:34px;border-radius:9px;padding:24px 26px 22px;background:linear-gradient(135deg,#24102f 0%,#351344 100%);box-shadow:0 8px 28px rgba(39,14,51,.12)}.ai-signal-section:before{background:radial-gradient(circle at 85% 15%,rgba(157,70,255,.28),transparent 45%)}.ai-signal-intro h2{font-size:clamp(28px,3.5vw,42px)}.ai-signal-intro p{font-size:14px}.ai-signal-how{background:#8d32ff;color:#fff;padding:10px 14px;border-radius:5px;text-transform:none;letter-spacing:0}.ai-signal-how:hover{background:#a14cff;color:#fff}.ai-signal-board{margin-top:20px}.ai-signal-number{color:#d9b7ff}.ai-rejection strong{color:#ff6b8f}.ai-agent-strip span{border:1px solid #6d3d7f;border-radius:6px;margin-right:8px;padding:9px 12px;color:#fff;background:rgba(117,47,157,.16)}.ai-agent-strip span:last-child{margin-right:0}.ai-signal-footer{text-align:center;font-size:20px;font-style:italic}.ai-signal-footer span{color:#d1c5d8}
.home-section{margin-top:36px}.home-cards{grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.home-card-media{aspect-ratio:16/9;border-radius:7px}.home-card-copy h3{font-size:16px}.home-newsletter{border-radius:8px}
@media(max-width:980px){.home-shell{padding-left:16px;padding-right:16px}.latest-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.nav a{padding-left:9px;padding-right:9px}.mast{padding-left:16px;padding-right:16px}}
@media(max-width:640px){.utils .wrap{padding-left:12px;padding-right:12px}.mast{padding-left:12px;padding-right:12px}.brand:before{width:38px;height:29px;font-size:18px}.wordmark{font-size:25px}.tag{display:none}.ai-newsroom-link{display:none}.nav .wrap{overflow-x:auto}.nav ul{width:max-content}.nav a{padding:12px 10px;font-size:12px}.home-shell{padding-left:12px;padding-right:12px}.latest-grid{grid-template-columns:1fr 1fr}.latest-item h3{font-size:14px}.latest-item time{font-size:8px}.home-hero{gap:10px}.hero-copy h1{font-size:31px}.ai-signal-section{padding:20px 16px}.home-cards{grid-template-columns:1fr 1fr}}
</style>
