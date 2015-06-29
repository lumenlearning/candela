			<section class="second-block-wrap">

				<!-- Login/Logout -->
				<div class="log-wrap">
					<?php if (! is_single()): ?>
							<?php if (!is_user_logged_in()): ?>
							<a href="<?php echo wp_login_url(); ?>" class=""><?php _e('login', 'pressbooks'); ?></a>
								<?php else: ?>
							<a href="<?php echo  wp_logout_url(); ?>" class=""><?php _e('logout', 'pressbooks'); ?></a>
							<?php if (is_super_admin() || is_user_member_of_blog()): ?>
							<a href="<?php echo get_option('home'); ?>/wp-admin/admin.php?page=pressbooks"><?php _e('Admin', 'pressbooks'); ?></a>
							<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
				</div>

				<div class="second-block clearfix">
						<div class="description-book-info">
							<?php $metadata = pb_get_book_information();?>
							<h2><?php _e('Book Description', 'pressbooks'); ?></h2>
								<?php if ( ! empty( $metadata['pb_about_unlimited'] ) ): ?>
									<p><?php
										$about_unlimited = pb_decode( $metadata['pb_about_unlimited'] );
										$about_unlimited = preg_replace( '/<p[^>]*>(.*)<\/p[^>]*>/i', '$1', $about_unlimited ); // Make valid HTML by removing first <p> and last </p>
										echo $about_unlimited; ?></p>
								<?php endif; ?>
							<!-- if there is a custom copyright description -->
							<?php if ( ! empty ($metadata['pb_custom_copyright'])) : ?>
									<h2><?php _e('Copyright', 'pressbooks') ;?></h2>
									<p><?php
										$custom_copyright = pb_decode( $metadata['pb_custom_copyright']);
										$custom_copyright = preg_replace( '/<p[^>]*>(.*)<\/p[^>]*>/i', '$1', $custom_copyright );
										echo $custom_copyright;?>
									</p>
							<?php endif; ?>
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
