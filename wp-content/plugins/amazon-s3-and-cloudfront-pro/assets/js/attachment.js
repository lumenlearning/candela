(function( $, as3cfFindAndReplaceMedia ) {

	$( document ).ready( function() {

		// Setup find and replace modal
		$( 'body' ).on( 'click', '.s3-actions a', function( e ) {
			e.preventDefault();
			as3cfFindAndReplaceMedia.open( $( this ).attr( 'href' ) );
		} );

		// Ask for confirmation when trying to remove attachment from S3 when the local file is missing
		$( 'body' ).on( 'click', '.s3-actions a.local-warning', function( e ) {
			if ( confirm( as3cfpro_media.local_warning ) ) {
				return true;
			}

			return false;
		} );

	} );

})( jQuery, as3cfFindAndReplaceMedia );