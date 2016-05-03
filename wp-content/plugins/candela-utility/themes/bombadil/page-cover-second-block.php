<section class="second-block-wrap">

<!-- Login/Logout -->
<div class="log-wrap">
	<?php if (! is_single()) : ?>
		<?php if (!is_user_logged_in()) : ?>
			<a href="<?php echo wp_login_url(); ?>" class=""><?php _e('login', 'pressbooks'); ?></a>
		<?php else: ?>
			<a href="<?php echo  wp_logout_url(); ?>" class=""><?php _e('logout', 'pressbooks'); ?></a>
			<?php if (is_super_admin() || is_user_member_of_blog()) : ?>
				<a href="<?php echo get_option('home'); ?>/wp-admin"><?php _e('Admin', 'pressbooks'); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>

<div class="second-block clearfix">
		<div class="description-book-info">
			<?php $metadata = \Candela\Utility\candela_get_book_info_meta(); ?>
			<h2><?php _e('Copyright', 'pressbooks'); ?></h2>

			<p class="copyright-text">
				This courseware includes resources copyrighted and openly licensed by
				multiple individuals and organizations. Click the words "Licenses and
				Attributions" at the bottom of each page for copyright and licensing
				information specific to the material on that page. If you believe that
				this courseware violates your copyright,
				please <a target="_blank" href="http://lumenlearning.com/copyright/">contact us</a>.
			</p>

			<?php if ( ! empty( $metadata['attribution-type'] ) ) : ?>
				<p class="copyright-text">
					<?php $license = \Candela\Utility\the_attribution_license( $metadata['attribution-licensing'] ); ?>

					Cover Image:

					<?php if ( ! empty( $metadata['attribution-description'] ) ) : ?>
						"<?php echo $metadata['attribution-description']; ?>."
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-author'] ) ) : ?>
						Authored by: <?php echo $metadata['attribution-author']; ?>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-organization'] ) ) : ?>
						Provided by: <?php echo $metadata['attribution-organization']; ?>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-url'] ) ) : ?>
						Located at: <a target="_blank" href=<?php echo esc_url( $metadata['attribution-url'] ); ?>><?php echo $metadata['attribution-url']; ?></a>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-project'] ) ) : ?>
						Project: <?php echo $metadata['attribution-project']; ?>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-type'] ) ) : ?>
						Content Type: <?php echo \Candela\Utility\the_attribution_type( $metadata['attribution-type'] ); ?>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-licensing'] ) ) : ?>
						License: <a target="_blank" href=<?php echo esc_url( $license['link'] ); ?>>
											 <?php echo $license['label']; ?></a>.
					<?php endif; ?>

					<?php if ( ! empty( $metadata['attribution-license-terms'] ) ) : ?>
						License Terms: <?php echo $metadata['attribution-license-terms']; ?>.
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<h2><?php _e('Lumen Learning', 'pressbooks'); ?></h2>

			<p class="about-text">
				Lumen Learning provides a simple, supported path for faculty members
				to adopt and teach effectively with open educational resources (OER).
				Read more about what we do
				<a target="_blank" href="http://lumenlearning.com/open-courses-overview/">here</a>.
			</p>

		</div>

				<?php	$args = $args = array(
						    'post_type' => 'back-matter',
						    'tax_query' => array(
						        array(
						            'taxonomy' => 'back-matter-type',
						            'field' => 'slug',
						            'terms' => 'about-the-author'
						        )
						    )
						); ?>


				<div class="author-book-info">

  						<?php $loop = new WP_Query( $args );
							while ( $loop->have_posts() ) : $loop->the_post(); ?>
						    <h4><a href="<?php the_permalink(); ?>"><?php the_title();?></a></h4>
							<?php  echo '<div class="entry-content">';
						    the_excerpt();
						    echo '</div>';
							endwhile; ?>
				</div>
	</div><!-- end .secondary-block -->
</section> <!-- end .secondary-block -->
