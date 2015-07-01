<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
<?php get_header(); ?>
<?php if (get_option('blog_public') == '1' || (get_option('blog_public') == '0' && current_user_can_for_blog($blog_id, 'read'))): ?>

			<main id="main-content">

			<?php the_title('<h1 class="entry-title">', '</h1>'); ?>

				<div id="post-<?php the_ID(); ?>" <?php post_class( pb_get_section_type( $post ) ); ?>>

					<div class="entry-content">

						<?php
						the_content();
						if ( get_post_type( $post->ID ) === 'part' ) {
							echo get_post_meta( $post->ID, 'pb_part_content', true );
						} ?>
					</div><!-- .entry-content -->
				</div><!-- #post-## -->

				<?php if ( $citation = CandelaCitation::renderCitation( $post->ID ) ): ?>
					<section role="contentinfo">
						<div class="post-citations sidebar">
							<div role="button" aria-pressed="false" id="citation-header-<?php print $post->ID; ?>" class="collapsed h3-styling"><?php _e('Licenses and Attributions'); ?></div>
							<div id="citation-list-<?php print $post->ID; ?>" style="display:none;">
								<?php print $citation ?>
							</div>
							<script>
								jQuery( document ).ready( function( $ ) {
									var pressed = false;
									$( "#citation-header-<?php print $post->ID;?>" ).click(function() {
										pressed = !pressed;
										$( "#citation-list-<?php print $post->ID;?>" ).slideToggle();
										$( "#citation-header-<?php print $post->ID;?>" ).toggleClass('expanded collapsed');
										$( "#citation-header-<?php print $post->ID;?>" ).attr('aria-pressed', pressed);
									});
								});
							</script>
						</div>
					</section>
				<?php endif; ?>

				</div><!-- #content -->

				<?php comments_template( '', true ); ?>
<?php else: ?>
<?php pb_private(); ?>
<?php endif; ?>
<?php get_footer(); ?>
<?php endwhile;?>
