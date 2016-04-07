<form class="licence-form support-section" method="post" action="#support">
	<h3><?php _e( 'Your License', 'as3cf-pro' ); ?></h3>
	<?php
	if ( $this->licence->is_licence_constant() ) : ?>
		<p>
			<?php _e( 'The license key is currently defined in wp-config.php.', 'as3cf-pro' ); ?>
		</p>
	<?php else : ?>
		<?php
		$license = $this->licence->get_licence_key();
		if ( ! empty( $license ) ) :
			echo $this->licence->get_formatted_masked_licence();
		else : ?>
			<div class="licence-not-entered">
				<input type="text" class="licence-input" autocomplete="off" />
				<button class="button register-licence" type="submit"><?php _e( 'Activate License', 'as3cf-pro' ); ?></button>
				<p class="licence-status"></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</form>