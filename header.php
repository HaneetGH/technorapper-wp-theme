<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (class_exists('TechnoRapper_AI_Wire')) : ?>
  <?php
  add_filter('pre_get_document_title', function ($title) {
      if (is_front_page()) {
          return 'TechnoRapper — The Smart Tech Newsroom';
      }
      if (is_singular('post')) {
          return get_the_title() . ' | TechnoRapper — Smart Tech Newsroom';
      }
      return $title;
  }, 20);
  ?>
  <?php endif; ?>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#content">Skip to content</a>
<header class="site-header">
  <div class="utils">
    <div class="wrap">
      <span><?php echo esc_html(wp_date('l, j F Y')); ?></span>
      <?php if (class_exists('TechnoRapper_AI_Wire')) : ?>
      <span class="ai-newsroom-status"><span aria-hidden="true">●</span> AI NEWSROOM</span>
      <?php endif; ?>
      <a href="<?php echo esc_url(home_url('/feed/')); ?>">RSS</a>
    </div>
  </div>
  <div class="wrap mast">
    <button class="menu-toggle" type="button" aria-controls="site-drawer" aria-expanded="false">
      <span class="menu-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
      <span class="screen-reader-text">Open menu</span>
    </button>
    <a class="home-btn" href="<?php echo esc_url(home_url('/')); ?>">Home</a>
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
      <span>
        <span class="wordmark">Techno<em>Rapper</em></span>
        <span class="tag">Smart technology news · AI newsroom · since 2012</span>
      </span>
    </a>
    <?php if (class_exists('TechnoRapper_AI_Wire')) : ?>
    <a class="ai-newsroom-link" href="<?php echo esc_url(home_url('/#ai-newsroom')); ?>">AI Newsroom <span aria-hidden="true">→</span></a>
    <?php endif; ?>
    <form class="find" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <label class="screen-reader-text" for="s">Search stories</label>
      <input id="s" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search stories">
      <button type="submit">Search</button>
    </form>
  </div>
  <nav class="nav" aria-label="Sections">
    <div class="wrap">
      <?php
      wp_nav_menu(array(
          'theme_location' => 'primary',
          'container'      => false,
          'fallback_cb'    => 'technorapper_menu_fallback',
      ));
      ?>
    </div>
  </nav>
</header>
<?php technorapper_drawer(); ?>
<main id="content"><div class="wrap">
<?php
if (!is_front_page() && !is_singular('post')) {
    technorapper_ad_header();
}
?>
