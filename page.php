<?php
get_header();
the_post();
?>
<div class="page-h"><h1><?php the_title(); ?></h1></div>
<div class="page-body">
  <?php the_content(); ?>
</div>
<?php
get_footer();
