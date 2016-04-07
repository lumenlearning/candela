<div class="progress-content modal-content">
	<span class="close-progress-content close-progress-content-button">&times;</span>

	<div class="progress-header">
		<h2 class="progress-title"><?php _e( 'Uploading Media Library to S3', 'as3cf-pro' ); ?></h2>
		<span class="timer">00:00:00</span>
	</div>

	<div class="progress-info-wrapper clearfix">
		<div class="progress-text"><?php _e( 'Initiating upload', 'as3cf-pro' ); ?>&hellip;</div>
		<div class="upload-progress">0 <?php _e( 'Files Uploaded', 'as3cf-pro' ); ?></div>
	</div>
	<div class="clearfix"></div>
	<div class="progress-bar-wrapper">
		<div class="progress-bar"></div>
	</div>

	<div class="upload-controls">
		<span class="pause-resume button"><?php _ex( 'Pause', 'Temporarily stop uploading', 'as3cf-pro' ); ?></span>
		<span class="cancel button"><?php _ex( 'Cancel', 'Stop the upload', 'as3cf-pro' ); ?></span>
	</div>
	<div class="progress-errors">
		<div class="progress-errors-title">
			<span class="error-count">0</span>
			<span class="error-text"><?php _ex( 'Errors', 'Upload errors', 'as3cf-pro' ); ?></span>

			<a class="toggle-progress-errors" href="#"><?php _ex( 'Show', 'Show upload errors', 'as3cf-pro' ); ?></a>
		</div>
		<div class="progress-errors-detail">
			<ol></ol>
		</div>
	</div>
</div>