<?php
if (!defined('ABSPATH')) {
    exit;
}
if (post_password_required()) {
    return;
}
?>
<div id="comments" class="comments">
  <?php if (defined('DISQUS_VERSION')) : ?>
    <div id="disqus_thread"></div>
  <?php else : ?>
    <?php
    if (have_comments()) {
        wp_list_comments(array('style' => 'div'));
    }
    if (comments_open()) {
        comment_form();
    }
    ?>
  <?php endif; ?>
</div>
