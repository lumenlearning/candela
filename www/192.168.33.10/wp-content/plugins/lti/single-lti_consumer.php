<?php
/**
 * The Template for displaying single lti_consumer
 */

get_header(); ?>

  <div id="primary" class="content-area">
    <div id="content" class="site-content" role="main">
      <?php
        // Start the Loop.
        while ( have_posts() ) {
          the_post();
          if ( get_post_meta( get_the_ID(), LTI_META_KEY_NAME, true) ) {
            echo '<h2>Key: ';
            echo get_post_meta( get_the_ID(), LTI_META_KEY_NAME, true);
            echo '</h2>';
          }

          if (get_post_meta( get_the_ID(), LTI_META_SECRET_NAME, true) ) {
              echo '<h2>Secret: ';
              echo get_post_meta( get_the_ID(), LTI_META_SECRET_NAME, true);
              echo '</h2>';
          }
        }
      ?>
    </div><!-- #content -->
  </div><!-- #primary -->

<?php
get_sidebar( 'content' );
get_sidebar();
get_footer();
