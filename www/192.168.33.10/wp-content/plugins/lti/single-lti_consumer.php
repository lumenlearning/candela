<?php
/**
 * The Template for displaying single lti_consumer
 */

get_header(); ?>

      <?php
      // restrict access to the page
      	 if ( current_user_can_for_blog( $blog_id, 'edit_posts' ) || is_super_admin() ) {

	      // Start the Loop.
	      while ( have_posts() ) {
		      the_post();
		      if ( get_post_meta( get_the_ID(), LTI_META_KEY_NAME, true ) ) {
			      echo '<h2>Key: ';
			      echo get_post_meta( get_the_ID(), LTI_META_KEY_NAME, true );
			      echo '</h2>';
		      }

		      if ( get_post_meta( get_the_ID(), LTI_META_SECRET_NAME, true ) ) {
			      echo '<h2>Secret: ';
			      echo get_post_meta( get_the_ID(), LTI_META_SECRET_NAME, true );
			      echo '</h2>';
		      }
	      }
      }
      ?>
    </div><!-- #content -->

<?php
get_sidebar( 'content' );
get_sidebar();
get_footer();
