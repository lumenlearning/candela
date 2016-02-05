var as3cfFindAndReplaceSettings = (function ( $, as3cfModal ) {

	var modal = {
		selector: '.as3cf-find-replace-container',
		form: {}
	};

	/**
	 * Open modal
	 *
	 * @param {object} form
	 */
	modal.open = function ( form ) {
		modal.form = form;
		as3cfModal.open( modal.selector, null, 'settings-find-replace' );
	};

	// Setup click handlers
	$( document ).ready( function () {

		$( 'body' ).on( 'click', '.settings-find-replace [data-find-replace]', function( e ) {
			var value = $( this ).data( 'find-replace' );

			$( 'input[name=find_replace]' ).val( value );
			$( '[data-find-replace]' ).prop( 'disabled', true ).siblings( '.spinner' ).css( 'visibility', 'visible' ).show();

			as3cfModal.setLoadingState( true );

			modal.form.submit();
		} );

	} );

	return modal;

})( jQuery, as3cfModal );