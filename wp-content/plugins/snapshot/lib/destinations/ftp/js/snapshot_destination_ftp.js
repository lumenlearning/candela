jQuery(document).ready( function($) {
	
	jQuery("button#snapshot-destination-test-connection").click(function() {

		var destination_type 		= jQuery('input#snapshot-destination-type').val();
	
		jQuery( "#snapshot-ajax-destination-test-result" ).html('');
		jQuery( "#snapshot-ajax-destination-test-result" ).addClass('snapshot-loading');
		jQuery( "#snapshot-ajax-destination-test-result" ).show();		
		
		var destination_info = new Object;
		destination_info['name'] 		= jQuery('input#snapshot-destination-name').val();
		if (destination_info['type'] == null)
			destination_info['type'] = '';

		destination_info['address'] 	= jQuery('input#snapshot-destination-address').val();
		if (destination_info['address'] == null)
			destination_info['address'] = '';

		destination_info['protocol'] 		= jQuery('select#snapshot-destination-protocol').val();
		if (destination_info['protocol'] == null)
			destination_info['protocol'] = 'ftp';

		//destination_info['ssl'] 		= jQuery('select#snapshot-destination-ssl').val();
		//if (destination_info['ssl'] == null)
		//	destination_info['ssl'] = '';

		destination_info['passive'] 	= jQuery('select#snapshot-destination-passive').val();
		if (destination_info['passive'] == null)
			destination_info['passive'] = '';

		destination_info['port'] 		= jQuery('input#snapshot-destination-port').val();
		if (destination_info['port'] == null)
			destination_info['port'] = '';

		destination_info['timeout'] 	= jQuery('input#snapshot-destination-timeout').val();
		if (destination_info['timeout'] == null)
			destination_info['timeout'] = '';

		destination_info['username'] 	= jQuery('input#snapshot-destination-username').val();
		if (destination_info['username'] == null)
			destination_info['username'] = '';

		destination_info['password'] 	= jQuery('input#snapshot-destination-password').val();
		if (destination_info['password'] == null)
			destination_info['password'] = '';

		destination_info['directory'] 	= jQuery('input#snapshot-destination-directory').val();
		if (destination_info['directory'] == null)
			destination_info['directory'] = '';

		var data = {
			action: 'snapshot_destination_ftp',
			snapshot_action: 'connection-test',
			destination_info: destination_info
		};

	    jQuery.ajax({
		  	type: 'POST',
		  	url: ajaxurl,
	        data: data,
			dataType: 'json',
	        success: function(reply_data) {
				jQuery( "#snapshot-ajax-destination-test-result" ).removeClass('snapshot-loading');
		
				if (reply_data['errorStatus'] != undefined) {
			
					if (reply_data['errorStatus'] == false) {

						if (reply_data['responseArray']) {
							var message = reply_data['responseArray'].join('<br />');
							jQuery( "#snapshot-ajax-destination-test-result" ).append('<p>'+message+'</p>');
							jQuery( "#snapshot-ajax-destination-test-result" ).show();
						}
				
					} else {
						if (reply_data['errorArray']) {
							var message = reply_data['responseArray'].join('<br />');
							message = message+reply_data['errorArray'].join('<br />');
							jQuery( "#snapshot-ajax-destination-test-result" ).append('<p>'+message+'</p>');
							jQuery( "#snapshot-ajax-destination-test-result" ).show();
						}
					}
				}
			}
		});
	
		return false;
	});

});
