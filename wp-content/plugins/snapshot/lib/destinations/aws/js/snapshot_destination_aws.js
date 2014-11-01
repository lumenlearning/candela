jQuery(document).ready( function($) {
	
	jQuery("select#snapshot-destination-region").change(function() {
		// console.log('changed');
		if (jQuery(this).val() == 'other') {
			jQuery('div#snapshot-destination-region-other-container').show();
		} else {
			jQuery('div#snapshot-destination-region-other-container').hide();
		}
	});

	jQuery("button#snapshot-destination-test-connection").click(function() {

		var destination_type 		= jQuery('input#snapshot-destination-type').val();
	
		jQuery( "#snapshot-ajax-destination-test-result" ).html('');
		jQuery( "#snapshot-ajax-destination-test-result" ).addClass('snapshot-loading');
		jQuery( "#snapshot-ajax-destination-test-result" ).show();		

		var destination_info = new Object;

		destination_info['type'] 		= jQuery('input#snapshot-destination-type').val();
		if (destination_info['type'] == null)
			destination_info['type'] = '';
			
		destination_info['name'] 		= jQuery('input#snapshot-destination-name').val();
		if (destination_info['name'] == null)
			destination_info['name'] = '';

		destination_info['awskey'] 		= jQuery('input#snapshot-destination-awskey').val();
		if (destination_info['awskey'] == null)
			destination_info['awskey'] = '';

		destination_info['ssl'] 		= jQuery('select#snapshot-destination-ssl').val();
		if (destination_info['ssl'] == null)
			destination_info['ssl'] = '';

		destination_info['directory'] 	= jQuery('input#snapshot-destination-directory').val();
		if (destination_info['directory'] == null)
			destination_info['directory'] = '';

		destination_info['region'] 		= jQuery('select#snapshot-destination-region').val();
		if (destination_info['region'] == null)
			destination_info['region'] = '';

		destination_info['region-other'] 	= jQuery('input#snapshot-destination-region-other').val();
		if (destination_info['region-other'] == null)
			destination_info['region-other'] = '';

		destination_info['storage'] 	= jQuery('select#snapshot-destination-storage').val();
		if (destination_info['storage'] == null)
			destination_info['storage'] = '';

		destination_info['acl'] 	= jQuery('select#snapshot-destination-acl').val();
		if (destination_info['acl'] == null)
			destination_info['acl'] = '';

		destination_info['secretkey'] 	= jQuery('input#snapshot-destination-secretkey').val();
		if (destination_info['secretkey'] == null)
			destination_info['secretkey'] = '';

		if (jQuery('span#snapshot-destination-bucket-display').is(':visible')) {
			destination_info['bucket'] 		= jQuery('span#snapshot-destination-bucket-display').html();
		} else if (jQuery('select#snapshot-destination-bucket-list').is(':visible')) {
			destination_info['bucket'] 		= jQuery('select#snapshot-destination-bucket-list').val();
		}
		if (destination_info['bucket'] == null)
			destination_info['bucket'] = '';

		var data = {
			action: 'snapshot_destination_aws',
			snapshot_action: "connection-test",
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

	jQuery("button#snapshot-destination-aws-get-bucket-list").click(function() {

		var destination_info = new Object;

		destination_info['type'] 		= jQuery('input#snapshot-destination-type').val();
		if (destination_info['type'] == null)
			destination_info['type'] = '';
			
		destination_info['name'] 		= jQuery('input#snapshot-destination-name').val();
		if (destination_info['name'] == null)
			destination_info['name'] = '';

		destination_info['awskey'] 		= jQuery('input#snapshot-destination-awskey').val();
		if (destination_info['awskey'] == null)
			destination_info['awskey'] = '';

		destination_info['ssl'] 		= jQuery('select#snapshot-destination-ssl').val();
		if (destination_info['ssl'] == null)
			destination_info['ssl'] = '';

		destination_info['secretkey'] 	= jQuery('input#snapshot-destination-secretkey').val();
		if (destination_info['secretkey'] == null)
			destination_info['secretkey'] = '';

		destination_info['region'] 		= jQuery('select#snapshot-destination-region').val();
		if (destination_info['region'] == null)
			destination_info['region'] = '';

		destination_info['region-other'] 	= jQuery('input#snapshot-destination-region-other').val();
		if (destination_info['region-other'] == null)
			destination_info['region-other'] = '';

		if (jQuery('span#snapshot-destination-bucket-display').is(':visible')) {
			destination_info['bucket'] 		= jQuery('span#snapshot-destination-bucket-display').html();
		} else if (jQuery('select#snapshot-destination-bucket-list').is(':visible')) {
			destination_info['bucket'] 		= jQuery('select#snapshot-destination-bucket-list').val();
		}
		if (destination_info['bucket'] == null)
			destination_info['bucket'] = '';

		var data = {
			action: 'snapshot_destination_aws',
			snapshot_action: "aws-get-bucket-list",
			destination_info: destination_info
		};

		jQuery( "#snapshot-ajax-destination-bucket-error" ).html('');
		jQuery( "#snapshot-ajax-destination-bucket-error" ).addClass('snapshot-loading');
		jQuery( "#snapshot-ajax-destination-bucket-error" ).show();

	    jQuery.ajax({
		  	type: 'POST',
		  	url: ajaxurl,
	        data: data,
			dataType: 'json',
	        success: function(reply_data) {
				jQuery( "#snapshot-ajax-destination-bucket-error" ).removeClass('snapshot-loading');
		
				if (reply_data['errorStatus'] != undefined) {
			
					if (reply_data['errorStatus'] == false) {
						jQuery( "#snapshot-ajax-destination-bucket-error" ).hide();

						if (reply_data['responseArray']) {
							var message = reply_data['responseArray'].join('<br />');
							jQuery('#snapshot-destination-bucket-display').hide();
							jQuery('select#snapshot-destination-bucket-list').html(message);
							jQuery('select#snapshot-destination-bucket-list').show();
						}
					} else {
						if (reply_data['errorArray']) {
							var message = reply_data['errorArray'].join('<br />');
							//message = message+reply_data['errorArray'].join('<br />');
							jQuery( "#snapshot-ajax-destination-bucket-error" ).append('<p>'+message+'</p>');
							jQuery( "#snapshot-ajax-destination-bucket-error" ).show();
						}
					}
				}
		
//				jQuery('select#snapshot-destination-bucket-list').html(buckets_html);
//				jQuery('span#snapshot-destination-bucket-display').hide();
//				jQuery('select#snapshot-destination-bucket-list').show();
			}
		});
		return false;
	});

});
