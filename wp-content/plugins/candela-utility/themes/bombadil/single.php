<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
	<?php get_header(); ?>
	<?php if (get_option('blog_public') == '1' || (get_option('blog_public') == '0' && current_user_can_for_blog($blog_id, 'read'))): ?>

		<?php
			if (get_post_type($post->ID) !== 'part') {
				include('single_page.php');
			}
			else {
				include('single_study_plan.php');
			}
		?>

	<?php else: ?>
		<?php pb_private(); ?>
	<?php endif; ?>
	<?php get_footer(); ?>
<?php endwhile;?>
