<?php
require_once dirname(__DIR__) . '/bootstrap.php';

function fixture_article($title, $disclosure, $date = '17 Nov 2026')
{
    $article_class = $disclosure ? 'article article--ai-wire' : 'article';
    $byline = $disclosure ? $disclosure['byline'] : '';
    ob_start();
    ?>
<article class="<?php echo esc_attr($article_class); ?>">
  <div>
    <a class="kicker" href="#">PC and Hardware</a>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="meta">
      <?php echo esc_html($date); ?>
      <?php if ($disclosure) : ?>
        <span class="meta-sep" aria-hidden="true">·</span>
        <span class="meta-ai"><?php echo esc_html($byline); ?></span>
      <?php endif; ?>
    </p>
    <?php echo technorapper_render_ai_disclosure($disclosure ?: null); ?>
    <p class="standfirst">Microsoft links a Microsoft 365 outage to a dormant cloud gateway bug, shifting traffic and reduced capacity. Here is what customers should watch next.</p>
    <div class="photo ratio" style="background:#0F172A;min-height:180px"></div>
    <div class="body">
      <p>Microsoft said a previously dormant gateway bug caused the disruption. The company shifted traffic while capacity recovered, and customers were asked to wait on official status updates before treating early social reports as fact.</p>
      <p>This fixture is only used to review the theme disclosure. It is not published as an article.</p>
    </div>
  </div>
  <aside>
    <div class="more">
      <div class="panel-h"><span>More in PC and Hardware</span></div>
      <a href="#">Related story one</a>
      <a href="#">Related story two</a>
    </div>
  </aside>
</article>
    <?php
    return ob_get_clean();
}

$with = technorapper_ai_disclosure_normalize(array(
    'roles' => array('rewritten', 'curated'),
    'source_url' => 'https://www.theverge.com/story/example',
    'source_label' => 'The Verge',
), 101);

$reviewed = technorapper_ai_disclosure_normalize(array(
    'roles' => array('summarized', 'human-reviewed'),
    'source_url' => 'https://www.reuters.com/technology/example',
    'source_label' => 'Reuters',
), 102);

$generated = technorapper_ai_disclosure_normalize(array(
    'roles' => array('generated'),
), 103);

$all = technorapper_ai_disclosure_normalize(array(
    'roles' => array('generated', 'assisted', 'rewritten', 'summarized', 'curated', 'human-reviewed'),
    'source_url' => 'https://www.wired.com/story/example',
    'source_label' => 'WIRED',
), 104);

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI disclosure visual fixtures</title>
  <link rel="stylesheet" href="../../style.css">
  <style>
    body { margin: 0; }
    .fixture {
      border-bottom: 1px solid var(--line);
      padding: 28px 0 36px;
    }
    .fixture-label {
      margin: 0 0 16px;
      color: var(--accent-bright);
      font-family: ui-monospace, Menlo, monospace;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .14em;
      text-transform: uppercase;
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="utils"><div class="wrap"><span>Tuesday, 17 November 2026</span><a href="#">RSS</a></div></div>
    <div class="wrap mast">
      <a class="brand" href="#"><span><span class="wordmark">Techno<em>Rapper</em></span><span class="tag">Tech news, run by AI Wire agents</span></span></a>
    </div>
  </header>
  <main id="content"><div class="wrap">
    <section class="fixture" id="with-metadata">
      <p class="fixture-label">Article with AI metadata</p>
      <?php echo fixture_article('Microsoft 365 Outage: How a Cloud Gateway Bug Escalated', $with); ?>
    </section>
    <section class="fixture" id="human-reviewed">
      <p class="fixture-label">AI-summarized and human-reviewed</p>
      <?php echo fixture_article('Reuters summary of a chip export rule change', $reviewed); ?>
    </section>
    <section class="fixture" id="generated-only">
      <p class="fixture-label">AI-generated without a source URL</p>
      <?php echo fixture_article('An AI Wire desk note with no original source', $generated); ?>
    </section>
    <section class="fixture" id="all-roles">
      <p class="fixture-label">All six disclosure roles</p>
      <?php echo fixture_article('Every disclosure role on one story', $all); ?>
    </section>
    <section class="fixture" id="without-metadata">
      <p class="fixture-label">Older article without AI metadata</p>
      <?php echo fixture_article('A 2019 review written by the human desk', null, '12 Mar 2019'); ?>
    </section>
  </div></main>
</body>
</html>
