</div></main>
<footer>
  <div class="wrap">
    <nav aria-label="Footer">
      <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
      <a href="<?php echo esc_url(home_url('/feed/')); ?>">RSS</a>
      <?php
      foreach (technorapper_desks() as $desk) {
          $term = technorapper_desk_term($desk[0]);
          if ($term) {
              echo '<a href="' . esc_url(get_category_link($term)) . '">' . esc_html($desk[1]) . '</a>';
          }
      }
      foreach (array('contact-us-page', 'privacy-policy', 'support-center', 'work-with-us', 'it-services-consulting') as $slug) {
          $page = get_page_by_path($slug);
          if ($page) {
              echo '<a href="' . esc_url(get_permalink($page)) . '">' . esc_html(get_the_title($page)) . '</a>';
          }
      }
      ?>
    </nav>
    <p class="copy">© TechnoRapper · Since 2012</p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
