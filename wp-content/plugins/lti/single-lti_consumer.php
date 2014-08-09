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
          $author_id = get_the_author_meta('ID');
          $user = wp_get_current_user();

          // Skip displaying anything if the current user is not super admin or post author.
          if (!is_super_admin() && ($author_id != $user->ID)) {
            // break the loop; should we redirect?
            continue;
          }

          global $wpdb;
          $endpoint = get_site_url(1) . '/api/lti/BLOGID';
          echo '<div><label for="lti_consumer_endpoint">';
          _e( 'Endpoint' );
          echo ': </label>';
          echo '<strong id="lti_consumer_endpoint" name="lti_consumer_endpoint">' . $endpoint . '</strong>';
          echo '</div>';
          echo '<div class="description">Replace BLOGID with the site id or site slug the user should be redirected to.</div>';

		      if ( $key = get_post_meta( get_the_ID(), LTI_META_KEY_NAME, true ) ) {
            echo '<div><label for="lti_consumer_key">';
            _e( 'Key' );
            echo ': </label>';
            echo '<strong id="lti_consumer_key" name="lti_consumer_key">' . esc_attr( $key ) . '</strong>';
            echo '</div>';
		      }

		      if ( $secret = get_post_meta( get_the_ID(), LTI_META_SECRET_NAME, true ) ) {
            echo '<div><label for="lti_consumer_secret">';
            _e( 'Secret' );
            echo ': </label>';
            echo '<strong id="lti_consumer_secret" name="lti_consumer_secret">' . esc_attr( $secret ) . '</strong>';
            echo '</div>';
		      }
	      }
      }
      ?>
    </div><!-- #content -->

<?php
get_sidebar( 'content' );
get_sidebar();
get_footer();
