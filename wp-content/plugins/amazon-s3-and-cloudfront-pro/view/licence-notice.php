<div class="as3cf-pro-license-notice notice-info notice as3cf-notice important">
	<p>
		<strong><?php echo $title; ?></strong> &mdash; <?php echo $message; ?>
	</p>
	<?php if ( $extra ) : ?>
		<p>
			<?php echo $extra; ?>
		</p>
	<?php endif; ?>

	<?php if ( $dashboard || 'no_licence' !== $type ) : ?>
	<p>
	<?php endif; ?>
		<?php if ( 'no_licence' !== $type ) : ?>
			<a href="<?php echo esc_url( $check_url ); ?>" class="as3cf-pro-check-again"><?php _e( 'Check again', 'as3cf-pro' ); ?></a>
		<?php endif; ?>
		<?php if ( $dashboard && 'no_licence' !== $type ) : ?>
			|
		<?php endif; ?>
		<?php if ( $dashboard ) : ?>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="as3cf-pro-dismiss-notice"><?php _e( 'Dismiss', 'as3cf-pro' ); ?></a>
		<?php endif; ?>
	<?php if ( $dashboard || 'no_licence' !== $type ) : ?>
	</p>
	<?php endif; ?>
</div>