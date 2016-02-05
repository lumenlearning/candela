<div class="licence-info support support-section">
	<h3><?php _e( 'Email Support', 'as3cf-pro' ); ?></h3>

	<div class="support-content">
		<?php if ( ! empty( $licence ) ) : ?>
			<p><?php _e( 'Fetching support form for your license, please wait...', 'as3cf-pro' ); ?></p>
		<?php else : ?>
			<p>
				<?php _e( 'We couldn\'t find your license information.', 'as3cf-pro' ); ?>
				<?php _e( 'Please enter a valid license key.', 'as3cf-pro' ); ?>
			</p>
			<p><?php _e( 'Once entered, you can view your support details.', 'as3cf-pro' ); ?></p>
		<?php endif; ?>
	</div>
</div>