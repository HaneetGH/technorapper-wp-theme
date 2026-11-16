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
    $today = gmdate('Y-m-d 00:00:00');
    $stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS discovered,
                SUM(CASE WHEN review_json IS NOT NULL AND review_json <> '' THEN 1 ELSE 0 END) AS evaluated,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS discovered_today,
                SUM(CASE WHEN created_at >= %s AND review_json IS NOT NULL AND review_json <> '' THEN 1 ELSE 0 END) AS evaluated_today,
                SUM(CASE WHEN created_at >= %s AND status = 'published' THEN 1 ELSE 0 END) AS published_today
             FROM {$items_table}
             WHERE created_at >= %s",
            $today,
            $today,
            $today,
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
        'discovered'       => (int) ($stats['discovered'] ?? 0),
        'evaluated'        => (int) ($stats['evaluated'] ?? 0),
        'rejected'         => $rejected,
        'published'        => (int) ($stats['published'] ?? 0),
        'discovered_today' => (int) ($stats['discovered_today'] ?? 0),
        'evaluated_today'  => (int) ($stats['evaluated_today'] ?? 0),
        'published_today'  => (int) ($stats['published_today'] ?? 0),
    );
}

/**
 * Compact evidence pill labels for the lead story.
 * Prefer short telegraph-style phrases over multi-line cards.
 */
function technorapper_lead_evidence($post)
{
    $source_count = 0;
    $unclear = '';

    if ($post && !empty($post->ID)) {
        $sources = get_post_meta((int) $post->ID, '_tr_ai_wire_sources', true);
        if (is_array($sources)) {
            $source_count = count($sources);
        } elseif (is_string($sources) && $sources !== '') {
            $decoded = json_decode($sources, true);
            if (is_array($decoded)) {
                $source_count = count($decoded);
            }
        }

        $takeaways = json_decode((string) get_post_meta((int) $post->ID, '_tr_ai_wire_takeaways', true), true);
        if (!$source_count && is_array($takeaways)) {
            $source_count = min(3, count($takeaways));
        }

        $gate = get_post_meta((int) $post->ID, '_tr_ai_wire_prepublish_gate', true);
        if (is_array($gate)) {
            foreach (array('warnings', 'blocking_issues', 'soft_issues') as $key) {
                if (empty($gate[$key]) || !is_array($gate[$key])) {
                    continue;
                }
                foreach ($gate[$key] as $issue) {
                    $line = trim(wp_strip_all_tags((string) $issue));
                    if ($line === '') {
                        continue;
                    }
                    // Skip internal quality notes — they read poorly on the homepage.
                    if (preg_match('/\b(title|headline|slug|seo|malformed|incomplete|truncated)\b/i', $line)) {
                        continue;
                    }
                    $unclear = $line;
                    break 2;
                }
            }
        }

        if ($unclear === '' && get_post_meta((int) $post->ID, '_tr_ai_wire_queue_id', true)) {
            $unclear = 'follow-on claims';
        }
    }

    if ($source_count < 1) {
        $source_count = 1;
    }
    if ($unclear === '') {
        $unclear = 'secondary implications';
    }

    // Keep the unclear clause short enough for one pill line.
    $unclear = preg_replace('/\s+/', ' ', $unclear);
    if (function_exists('mb_strimwidth')) {
        $unclear = mb_strimwidth($unclear, 0, 28, '…', 'UTF-8');
    } elseif (strlen($unclear) > 28) {
        $unclear = rtrim(substr($unclear, 0, 25)) . '…';
    }

    return array(
        'confirmed' => sprintf(_n('%d source', '%d sources', $source_count, 'technorapper'), $source_count),
        'unclear'   => $unclear,
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
$ai_stats = technorapper_ai_wire_home_stats();
$how_ai_url = home_url('/ai-wire/');
$evidence = technorapper_lead_evidence($hero);
$desk_row = array(
    array('apple', 'Apple'),
    array('android', 'Android'),
    array('auto', 'Auto'),
    array('guides', 'Guides'),
);
$desk_posts = array();
foreach ($desk_row as $desk) {
    $slug = $desk[0];
    $post = null;
    foreach ($home['sections'] as $section) {
        if ($section[0] === $slug && !empty($section[1][0])) {
            $post = $section[1][0];
            break;
        }
    }
    if (!$post) {
        $term = technorapper_desk_term($slug);
        if ($term) {
            $found = get_posts(array(
                'numberposts' => 1,
                'category'    => (int) $term->term_id,
                'post_status' => 'publish',
            ));
            $post = $found ? $found[0] : null;
        }
    }
    $desk_posts[] = array($slug, $desk[1], $post);
}

$sources_today = $ai_stats ? max((int) $ai_stats['discovered_today'], (int) $ai_stats['evaluated_today']) : null;
$claims_today = $ai_stats ? (int) $ai_stats['evaluated_today'] : null;
$review_label = 'Standing by';
if ($ai_stats) {
    if ((int) $ai_stats['published_today'] > 0) {
        $review_label = 'Complete';
    } elseif ((int) $ai_stats['evaluated_today'] > 0) {
        $review_label = 'In progress';
    }
}
?>

<div class="home-shell home-shell--brief">
  <section class="tr-brief-hero" aria-labelledby="tr-brief-title">
    <div class="tr-brief-hero__glow" aria-hidden="true"></div>
    <div class="tr-brief-hero__main">
      <p class="tr-brief-eyebrow"><span class="tr-brief-eyebrow__dash" aria-hidden="true"></span> AI-assisted. Editor-reviewed.</p>
      <h1 id="tr-brief-title">Technology, with the signal separated from the noise.</h1>
      <p class="tr-brief-dek">The AI Wire newsroom compares trusted sources, checks claims against the record, and keeps human editorial standards in the loop before a story lands on TechnoRapper.</p>
      <div class="tr-brief-actions">
        <a class="tr-brief-btn tr-brief-btn--primary" href="#today-brief">Explore today’s brief <span aria-hidden="true">→</span></a>
        <a class="tr-brief-link" href="<?php echo esc_url($how_ai_url); ?>">How AI Wire works</a>
      </div>
    </div>

    <aside class="tr-brief-today" aria-label="AI Wire today">
      <div class="tr-brief-today__mesh" aria-hidden="true"></div>
      <div class="tr-brief-today__label">AI Wire <span aria-hidden="true">•</span> Today</div>
      <dl class="tr-brief-today__stats">
        <div>
          <dt>Sources compared</dt>
          <dd><?php echo esc_html($sources_today !== null ? number_format_i18n($sources_today) : '—'); ?></dd>
        </div>
        <div>
          <dt>Claims checked</dt>
          <dd><?php echo esc_html($claims_today !== null ? number_format_i18n($claims_today) : '—'); ?></dd>
        </div>
        <div>
          <dt>Editor reviewed</dt>
          <dd class="tr-brief-today__status<?php echo $review_label === 'Complete' ? ' is-complete' : ''; ?>">
            <?php if ($review_label === 'Complete') : ?>
              <span class="tr-brief-check" aria-hidden="true"></span>
              <span class="screen-reader-text"><?php echo esc_html($review_label); ?></span>
            <?php else : ?>
              <?php echo esc_html($review_label); ?>
            <?php endif; ?>
          </dd>
        </div>
      </dl>
    </aside>
  </section>

  <?php if ($hero) : $GLOBALS['post'] = $hero; setup_postdata($hero); $lead_cat = get_the_category(); ?>
  <section class="tr-lead" id="today-brief" aria-label="Lead story">
    <article class="tr-lead-card">
      <a class="tr-lead-media" href="<?php the_permalink(); ?>">
        <?php
        $img = technorapper_first_image($hero);
        if ($img) {
            echo technorapper_img($img, get_the_title(), true);
        } else {
            echo '<span class="tr-lead-placeholder" aria-hidden="true"></span>';
        }
        ?>
      </a>
      <div class="tr-lead-copy">
        <div class="tr-lead-meta">
          <span class="tr-lead-desk"><?php echo esc_html(($lead_cat && isset($lead_cat[0])) ? $lead_cat[0]->name : 'Top Story'); ?></span>
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
        </div>
        <h2 class="tr-lead-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <p class="tr-lead-summary"><?php echo esc_html(technorapper_card_text($hero, 36)); ?></p>
        <p class="tr-evidence-pill" aria-label="Evidence">
          <span class="tr-evidence-pill__icon" aria-hidden="true">i</span>
          <span class="tr-evidence-pill__part"><span class="tr-evidence-pill__k">Confirmed</span> <?php echo esc_html($evidence['confirmed']); ?></span>
          <span class="tr-evidence-pill__sep" aria-hidden="true">·</span>
          <span class="tr-evidence-pill__part"><span class="tr-evidence-pill__k">Unclear</span> <?php echo esc_html($evidence['unclear']); ?></span>
        </p>
      </div>
    </article>
  </section>
  <?php wp_reset_postdata(); endif; ?>

  <section class="tr-desk-row" aria-label="Desks">
    <?php foreach ($desk_posts as $desk_item) :
        $slug = $desk_item[0];
        $label = $desk_item[1];
        $post = $desk_item[2];
        if (!$post) {
            continue;
        }
        $GLOBALS['post'] = $post;
        setup_postdata($post);
        ?>
      <article class="tr-desk-card">
        <a href="<?php the_permalink(); ?>">
          <div class="tr-desk-card__media">
            <?php
            $img = technorapper_first_image($post);
            if ($img) {
                echo technorapper_img($img, get_the_title());
            } else {
                echo '<span class="tr-desk-card__placeholder" aria-hidden="true"></span>';
            }
            ?>
          </div>
          <div class="tr-desk-card__copy">
            <span class="tr-desk-card__desk"><?php echo esc_html($label); ?></span>
            <h3><?php the_title(); ?></h3>
            <p><?php echo esc_html(technorapper_card_text($post, 18)); ?></p>
          </div>
        </a>
      </article>
    <?php endforeach; wp_reset_postdata(); ?>
  </section>

  <section class="home-ad-rail" aria-label="Advertisement">
    <div class="home-ad-rail__chrome">
      <span class="home-ad-rail__mark"><span class="home-ad-rail__dash" aria-hidden="true"></span> Advertisement</span>
    </div>
    <div class="home-ad-banner" role="note">
      <div class="home-ad-banner__visual" aria-hidden="true"></div>
      <div class="home-ad-banner__copy">
        <p class="home-ad-banner__eyebrow">Sponsored</p>
        <h2>Discover more / Compare phones.</h2>
      </div>
      <div class="home-ad-banner__actions">
        <a class="tr-brief-btn tr-brief-btn--primary" href="<?php echo esc_url(home_url('/category/guides/')); ?>">Compare phones <span aria-hidden="true">→</span></a>
      </div>
    </div>
    <div class="home-ad"><?php technorapper_ad_header(); ?></div>
  </section>

  <?php
  $featured_slugs = array('apple', 'android', 'auto', 'guides');
  foreach ($home['sections'] as $section) {
      $slug = $section[0];
      if (in_array($slug, $featured_slugs, true)) {
          continue;
      }
      $term = technorapper_desk_term($slug);
      if (!$term || empty($section[1])) {
          continue;
      }
      technorapper_home_section($slug, $term->name . ' Desk', $section[1]);
  }
  ?>

  <section class="home-newsletter" aria-label="Stay informed">
    <div>
      <span class="section-kicker">Stay in the loop</span>
      <h2>Technology worth knowing about.</h2>
      <p>Follow TechnoRapper for concise technology news with transparent AI assistance and editor review.</p>
    </div>
    <a href="<?php echo esc_url(home_url('/contact-us-page/')); ?>">Get in touch <span aria-hidden="true">→</span></a>
  </section>
</div>
