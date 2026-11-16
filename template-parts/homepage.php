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
    $week_start = gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS));
    $stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS discovered,
                SUM(CASE WHEN review_json IS NOT NULL AND review_json <> '' THEN 1 ELSE 0 END) AS evaluated,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS discovered_week,
                SUM(CASE WHEN created_at >= %s AND review_json IS NOT NULL AND review_json <> '' THEN 1 ELSE 0 END) AS evaluated_week,
                SUM(CASE WHEN created_at >= %s AND status = 'published' THEN 1 ELSE 0 END) AS published_week
             FROM {$items_table}
             WHERE created_at >= %s",
            $week_start,
            $week_start,
            $week_start,
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
        'discovered'      => (int) ($stats['discovered'] ?? 0),
        'evaluated'       => (int) ($stats['evaluated'] ?? 0),
        'rejected'        => $rejected,
        'published'       => (int) ($stats['published'] ?? 0),
        'discovered_week' => (int) ($stats['discovered_week'] ?? 0),
        'evaluated_week'  => (int) ($stats['evaluated_week'] ?? 0),
        'published_week'  => (int) ($stats['published_week'] ?? 0),
    );
}

/**
 * Shorten a phrase for the homepage evidence pill.
 */
function technorapper_evidence_clip($text, $max = 34)
{
    $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $text)));
    $text = preg_replace('/^(?:still\s+)?(?:open|unclear|unknown)[:\s-]+/i', '', $text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '…', 'UTF-8');
    }
    if (strlen($text) > $max) {
        return rtrim(substr($text, 0, max(1, $max - 1))) . '…';
    }
    return $text;
}

/**
 * Reader-facing open question from AI Wire takeaways / review — never SEO gate nits.
 */
function technorapper_evidence_open_item($takeaways, $review, $source_count)
{
    $candidates = array();
    if (is_array($takeaways)) {
        // Prefer the last takeaway (usually "what to watch") then earlier ones.
        foreach (array_reverse($takeaways) as $item) {
            $line = trim(wp_strip_all_tags((string) $item));
            if ($line === '') {
                continue;
            }
            if (preg_match('/\b(watch|unclear|unknown|pending|still|remain|depend|expect|whether|until|timeline|rollout|availability|pricing|export|confirm|corroborat|independen)/i', $line)) {
                $candidates[] = $line;
            }
        }
        if (!$candidates && $takeaways) {
            $last = trim(wp_strip_all_tags((string) end($takeaways)));
            if ($last !== '') {
                $candidates[] = $last;
            }
        }
    }

    if (is_array($review)) {
        foreach ((array) ($review['coverage'] ?? array()) as $source) {
            if (!is_array($source)) {
                continue;
            }
            $rel = strtolower(trim((string) ($source['relationship'] ?? '')));
            if ($rel !== '' && preg_match('/\b(unconfirm|partial|single|press.?release|unverified|disput)/i', $rel)) {
                $candidates[] = $rel;
            }
        }
    }

    foreach ($candidates as $line) {
        $clipped = technorapper_evidence_clip($line, 34);
        if ($clipped !== '') {
            return $clipped;
        }
    }

    if ((int) $source_count <= 1) {
        return 'independent corroboration';
    }
    return 'follow-on details';
}

/**
 * Compact evidence pill from AI Wire review coverage + takeaways.
 * Confirmed = sources actually compared; Open = still-developing angle.
 */
function technorapper_lead_evidence($post)
{
    $source_count = 0;
    $open = '';
    $takeaways = array();
    $review = array();

    if ($post && !empty($post->ID)) {
        $publishers = array();

        $primary = esc_url_raw((string) get_post_meta((int) $post->ID, '_tr_ai_wire_source_url', true));
        if ($primary !== '') {
            $host = wp_parse_url($primary, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $publishers[strtolower(preg_replace('/^www\./i', '', $host))] = true;
            } else {
                $publishers['primary'] = true;
            }
        }

        $raw_review = get_post_meta((int) $post->ID, '_tr_ai_wire_review', true);
        if (is_string($raw_review) && $raw_review !== '') {
            $decoded = json_decode($raw_review, true);
            if (is_array($decoded)) {
                $review = $decoded;
            }
        } elseif (is_array($raw_review)) {
            $review = $raw_review;
        }

        foreach ((array) ($review['coverage'] ?? array()) as $source) {
            if (!is_array($source)) {
                continue;
            }
            $publisher = trim((string) ($source['publisher'] ?? ''));
            $url = esc_url_raw((string) ($source['url'] ?? ''));
            $key = $publisher !== '' ? strtolower($publisher) : '';
            if ($key === '' && $url !== '') {
                $host = wp_parse_url($url, PHP_URL_HOST);
                $key = is_string($host) ? strtolower(preg_replace('/^www\./i', '', $host)) : '';
            }
            if ($key !== '') {
                $publishers[$key] = true;
            }
        }

        $gate = get_post_meta((int) $post->ID, '_tr_ai_wire_prepublish_gate', true);
        if (is_array($gate) && !empty($gate['sources_verified']) && is_array($gate['sources_verified'])) {
            foreach ($gate['sources_verified'] as $src) {
                $line = trim(wp_strip_all_tags((string) $src));
                if ($line === '') {
                    continue;
                }
                $host = wp_parse_url($line, PHP_URL_HOST);
                $key = is_string($host) && $host !== ''
                    ? strtolower(preg_replace('/^www\./i', '', $host))
                    : strtolower($line);
                $publishers[$key] = true;
            }
        }

        $source_count = count($publishers);

        $raw_takeaways = json_decode((string) get_post_meta((int) $post->ID, '_tr_ai_wire_takeaways', true), true);
        if (is_array($raw_takeaways)) {
            $takeaways = $raw_takeaways;
        }

        $open = technorapper_evidence_open_item($takeaways, $review, $source_count);
    }

    if ($source_count < 1) {
        $source_count = 1;
    }
    if ($open === '') {
        $open = $source_count <= 1 ? 'independent corroboration' : 'follow-on details';
    }

    return array(
        'checked_label' => __('Checked', 'technorapper'),
        'open_label'    => __('Open', 'technorapper'),
        'confirmed'     => sprintf(_n('%d source', '%d sources', $source_count, 'technorapper'), $source_count),
        'unclear'       => $open,
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

$checked_week = $ai_stats ? (int) $ai_stats['discovered_week'] : null;
$validated_week = $ai_stats ? (int) $ai_stats['evaluated_week'] : null;
$published_week = $ai_stats ? (int) $ai_stats['published_week'] : null;
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

    <aside class="tr-brief-today" aria-label="AI Wire last week">
      <div class="tr-brief-today__mesh" aria-hidden="true"></div>
      <div class="tr-brief-today__label">AI Wire <span aria-hidden="true">•</span> Last week</div>
      <dl class="tr-brief-today__stats">
        <div>
          <dt>Checked</dt>
          <dd><?php echo esc_html($checked_week !== null ? number_format_i18n($checked_week) : '—'); ?></dd>
        </div>
        <div>
          <dt>Validated</dt>
          <dd><?php echo esc_html($validated_week !== null ? number_format_i18n($validated_week) : '—'); ?></dd>
        </div>
        <div>
          <dt>Published</dt>
          <dd><?php echo esc_html($published_week !== null ? number_format_i18n($published_week) : '—'); ?></dd>
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
        <p class="tr-evidence-pill" aria-label="AI Wire evidence">
          <span class="tr-evidence-pill__icon" aria-hidden="true">i</span>
          <span class="tr-evidence-pill__part"><span class="tr-evidence-pill__k"><?php echo esc_html($evidence['checked_label']); ?></span> <?php echo esc_html($evidence['confirmed']); ?></span>
          <span class="tr-evidence-pill__sep" aria-hidden="true">·</span>
          <span class="tr-evidence-pill__part"><span class="tr-evidence-pill__k"><?php echo esc_html($evidence['open_label']); ?></span> <?php echo esc_html($evidence['unclear']); ?></span>
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
