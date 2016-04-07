<div class="as3cf-notice inline subtle as3cf-pro-media-notice">
	<p class="progress-errors-title dashicons-before dashicons-admin-media">
		<?php echo $message; // xss ok ?>

		<?php if ( isset( $upload ) ) : ?>
			<a class="as3cf-pro-upload" href="#"><?php _e( 'Upload Now', 'as3cf-pro' ); ?></a>
		<?php endif; ?>
		<span class="actions <?php echo isset( $upload ) ? 'upload' : ''; ?>">
			<a class="toggle-progress-errors" href="#">
				<?php _ex( 'Show', 'Show upload errors', 'as3cf-pro' ); ?></a> |
			<a class="as3cf-pro-notice" data-notice="dismiss-upload-errors" href="#">
				<?php _e( 'Dismiss', 'as3cf-pro' ); ?>
			</a>
		</span>
	</p>

	<div class="bulk-upload-errors progress-errors-detail">
		<ol>
			<?php if ( isset( $errors ) ) :
				foreach ( $errors as $blog_id => $errors ) :
					foreach ( $errors as $attachment_id => $error ) :?>
						<li><?php echo $error; ?> <?php printf( '<a target="_blank" href="%s">%s</a>', get_admin_url( $blog_id, 'post.php?post=' . $attachment_id . '&action=edit' ), $attachment_id ); ?></li>
					<?php endforeach;
				endforeach;
			endif; ?>
		</ol>
	</div>
</div>