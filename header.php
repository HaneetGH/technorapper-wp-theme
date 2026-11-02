<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#content">Skip to content</a>
<header>
  <div class="utils">
    <div class="wrap">
      <span><?php echo esc_html(wp_date('l, j F Y')); ?></span>
      <a href="<?php echo esc_url(home_url('/feed/')); ?>">RSS</a>
    </div>
  </div>
  <div class="wrap mast">
    <a class="home-btn" href="<?php echo esc_url(home_url('/')); ?>">Home</a>
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
      <span>
        <span class="wordmark">Techno<em>Rapper</em></span>
        <span class="tag">All about the tech world · since 2012</span>
      </span>
    </a>
    <form class="find" method="get" action="<?php echo esc_url(home_url('/')); ?>">
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
<main id="content"><div class="wrap">
<?php
if (!is_front_page() && !is_singular('post')) {
    technorapper_ad_header();
}
?>
