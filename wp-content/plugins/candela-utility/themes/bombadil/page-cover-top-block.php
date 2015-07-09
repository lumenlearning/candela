<section id="post-<?php the_ID(); ?>" <?php post_class( array( 'top-block', 'clearfix', 'home-post' ) ); ?>>

	<?php pb_get_links(false); ?>
	<?php $metadata = pb_get_book_information();?>

			<div class="book-info">
				<!-- Book Title -->
				<h1 class="entry-title"><a href="<?php echo home_url( '/' ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>

				<?php if ( ! empty( $metadata['pb_author'] ) ): ?>
				<p class="book-author vcard author"><span class="fn"><?php echo $metadata['pb_author']; ?></span></p>
			     	<span class="stroke"></span>
		     	<?php endif; ?>


				<?php if ( ! empty( $metadata['pb_about_140'] ) ) : ?>
					<p class="sub-title"><?php echo $metadata['pb_about_140']; ?></p>
					<span class="detail"></span>
				<?php endif; ?>

				<?php if ( ! empty( $metadata['pb_about_50'] ) ): ?>
					<p><?php echo pb_decode( $metadata['pb_about_50'] ); ?></p>
				<?php endif; ?>

			</div> <!-- end .book-info -->

				<?php if ( ! empty( $metadata['pb_cover_image'] ) ): ?>
				<div class="book-cover">

						<img src="<?php echo $metadata['pb_cover_image']; ?>" alt="book-cover" title="<?php bloginfo( 'name' ); ?> book cover" />

				</div>
				<?php endif; ?>

	</section> <!-- end .top-block -->
