var as3cfFindAndReplaceMedia = (function ( $, as3cfModal ) {

	var modal = {
		selector: '.as3cf-find-replace-container',
		isBulk: false,
		link: null,
		payload: {}
	};

	/**
	 * Open modal
	 *
	 * @param {string} link
	 * @param {mixed}  payload
	 */
	modal.open = function ( link, payload ) {
		if ( typeof link !== 'undefined' ) {
			modal.link = link;
		}
		if ( typeof payload !== 'undefined' ) {
			modal.payload = payload;
		}

		as3cfModal.open( modal.selector, null, 'media-find-replace' );

		$( modal.selector ).find( '.single-file' ).show();
		$( modal.selector ).find( '.multiple-files' ).hide();
		if ( modal.isBulk ) {
			$( modal.selector ).find( '.single-file' ).hide();
			$( modal.selector ).find( '.multiple-files' ).show();
		}
	};

	/**
	 * Close modal
	 */
	modal.close = function () {
		as3cfModal.close( modal.selector );
	};

	/**
	 * Set the isBulk flag
	 */
	modal.setBulk = function ( isBulk ) {
		modal.isBulk = isBulk;
	};

	/**
	 * Create the loading state
	 */
	modal.startLoading = function () {
		as3cfModal.setLoadingState( true );
		$( modal.selector + ' [data-find-replace]' ).prop( 'disabled', true ).siblings( '.spinner' ).css( 'visibility', 'visible' ).show();
	};

	/**
	 * Remove the loading state
	 */
	modal.stopLoading = function () {
		as3cfModal.setLoadingState( false );
		$( modal.selector + ' [data-find-replace]' ).prop( 'disabled', false ).siblings( '.spinner' ).css( 'visibility', 'hidden' ).hide();
	};

	// Setup click handlers
	$( document ).ready( function () {

		$( 'body' ).on( 'click', '.media-find-replace [data-find-replace]', function ( e ) {
			var findAndReplace = $( this ).data( 'find-replace' );

			if ( !modal.link ) {
				// If there is no link set then this must be an AJAX
				// request so trigger an event instead
				$( modal.selector ).trigger( 'as3cf-find-and-replace', [ findAndReplace, modal.payload ] );
				return;
			}

			if ( findAndReplace ) {
				modal.link += '&find_and_replace=1';
			}

			modal.startLoading();

			window.location = modal.link;
		} );

		$( 'body' ).on( 'as3cf-modal-close', function ( e ) {
			modal.isBulk = false;
			modal.link = null;
			modal.payload = {};
		} );

	} );

	return modal;

})( jQuery, as3cfModal );