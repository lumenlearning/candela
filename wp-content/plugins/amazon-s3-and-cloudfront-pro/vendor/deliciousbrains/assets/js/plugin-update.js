(function( $ ) {

	var doing_check_licence = false;
	var fade_duration = 400;
	var admin_url = ajaxurl.replace( '/admin-ajax.php', '' );
	var spinner_url = admin_url + '/images/spinner';

	if ( window.devicePixelRatio >= 2 ) {
		spinner_url += '-2x';
	}
	spinner_url += '.gif';
	var spinner = $( '<img src="' + spinner_url + '" alt="" class="check-licence-spinner" />' );
	var prefix;

	$( document ).ready( function() {

		$( 'body' ).on( 'click', '.dbrains-check-my-licence-again', function( e ) {
			e.preventDefault();
			$( this ).blur();

			if ( doing_check_licence ) {
				return false;
			}

			doing_check_licence = true;

			var plugin = $( this ).closest( 'tr' ).prev().attr( 'id' );
			prefix     = dbrains.plugins[ plugin ].prefix;

			spinner.insertAfter( this );
			$( this ).hide();

			var check_again_link = ' <a class="dbrains-check-my-licence-again" href="#">' + dbrains.strings.check_license_again + '</a>';

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: dbrains.plugins[ plugin ].prefix + '_check_licence',
					nonce: dbrains.nonces.check_licence
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					doing_check_licence = false;
					display_notice( plugin, dbrains.strings.license_check_problem + check_again_link, true );
				},
				success: function( data ) {
					doing_check_licence = false;
					if ( 'undefined' !== typeof data.errors  ) {
						var msg = '';
						for ( var key in data.errors ) {
							msg += data.errors[key];
						}
						display_notice( plugin, msg + check_again_link, true );
					}
					else {
						// success
						// fade out, empty custom error content, swap back in the original wordpress upgrade message, fade in
						msg = $( '.' + prefix + '-original-update-row.' + plugin ).html();
						display_notice( plugin, msg );
						display_addon_notices();
					}
				}
			} );

			/**
			 * Fade out the license notice and replace with a custom message
			 *
			 * @param {string} plugin
			 * @param {string} msg
			 * @param {bool}   is_error
			 */
			function display_notice( plugin, msg, is_error ) {
				var notice_class = '-custom-visible';
				if ( is_error ) {
					notice_class = '-licence-error-notice';
				}

				$( '.check-licence-spinner' ).remove();
				$( '.' + prefix + notice_class + '.' + plugin ).fadeOut( fade_duration, function() {
					$( '.' + prefix + notice_class + '.' + plugin ).empty()
						.html( msg )
						.fadeIn( fade_duration );
				} );
				if ( is_error ) {
					$( '.dbrains-check-my-licence-again' ).show();
				}

			}

			/**
			 * Replace the license error notice with the original row for plugin addons
			 */
			function display_addon_notices() {
				$( '.' + prefix + '-original-update-row.addon' ).each( function( i, obj ) {
					var plugin = $( obj ).data( 'slug' );
					var msg    = $( '.' + prefix + '-original-update-row.' + plugin ).html();
					display_notice( plugin, msg );
				} );
			}

		} );

	} );

})( jQuery );
