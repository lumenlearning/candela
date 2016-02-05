<div class="redirect-content modal-content">
	<span class="close-redirect-content close-redirect-content-button">&times;</span>

	<div class="redirect-header">
		<h2 class="redirect-title"><?php _e( 'What about links in your content?', 'as3cf-pro' ); ?></h2>
		<p class="redirect-desc"><?php _e( 'Your content (posts, pages, etc) has URLs to images and other files on your server. What would you like to do about those URLs?', 'as3cf-pro' ); ?></p>
	</div>

	<div class="redirect-options">
		<label class="replace">
			<input type="radio" name="existing-links" value="replace" checked="checked"> <?php _e( 'Find & Replace (recommended)', 'as3cf-pro' ); ?>
			<p><?php _e( 'Run a find & replace on all content, replacing old URLs with the new S3 URLs.', 'as3cf-pro' ); ?></p>
		</label>
		<label class="nothing">
			<input type="radio" name="existing-links" value="nothing"> <?php _e( 'Nothing', 'as3cf-pro' ); ?>
			<p><?php _e( 'Keep serving them from the server.', 'as3cf-pro' ); ?></p>
		</label>
		<div class="notice notice-warning inline">
			<p><strong><?php _e( 'Broken Images & Links', 'as3cf-pro' ); ?></strong> &mdash; <?php _e( 'Since you have <em>Remove Files From Server</em> turned on in your
settings, files on your server will be removed as they are uploaded to S3, resulting in broken images and links.', 'as3cf-pro' ); ?></p>
		</div>
	</div>

	<div class="redirect-controls">
		<span class="as3cf-start-upload button"><?php _ex( 'Start Upload', 'Start uploading media library', 'as3cf-pro' ); ?></span>
	</div>
</div>