jQuery(document).ready( function($) {
	
	function snapshot_log_viewer(action, item, data_item, log_position) {

		var snapshot_log_viewer_polling = setInterval(function() {

			var data = {
				action: action,
				'snapshot-item': item,
				'snapshot-data-item': data_item,
				'snapshot-log-position': log_position
			}
		
			snapshot_ajax_hdl_xhr = jQuery.ajax({
			  	type: 'POST',
			  	url: ajaxurl,
		        data: data,
				dataType: 'json',
		    	error: function(jqXHR, textStatus, errorThrown) {
					alert("ERROR");
				},
			    success: function(reply_data) {
					if (reply_data['position'] != undefined)
						snapshot_log_position = reply_data['position'];
					
					if (reply_data['payload'] != undefined)
						jQuery('#snapshot-log-viewer').append(reply_data['payload'])
		    	}
			});
		
		}, 1000);
		
	}

	/* Spin through each table checkbox group. Update the 'Select all'/'Deselect all' link label if not all checkboxes are set */
	function check_checkbox_state() {
		jQuery('a.snapshot-table-select-all').each(function() {
			var link_state = jQuery(this).html();
		
			var unchecked_items = jQuery(this).parent().parent().find('ul input:checkbox:not(:checked)').length;
			if (unchecked_items == 0) {
				jQuery(this).html('Unselect all');
			} else {
				jQuery(this).html('Select all');				
			}		
		});
	}
	
/*
	jQuery(window).resize(function() {
		if (jQuery('#TB_window').length) {
			if (jQuery('#snapshot-log-viewer').length) {
				var doc_width 	= jQuery(document).width() * .80 ;
				jQuery('#TB_window').width(doc_width);

				var doc_height 	= jQuery(document).height() * .60;
				jQuery('#TB_window').height(doc_height);
			}
		}
	});
*/
	
	jQuery('a.snapshot-thickbox').click(function(){
		var doc_width 	= 700; //jQuery(document).width() * .80 ;
		var doc_height 	= 600; //jQuery(document).height() * .60;
		
		tb_show('Snapshot Log Viewer', '#TB_inline?height='+doc_height+'&width='+doc_width+'&inlineId=snapshot-log-view-container');
		jQuery('#snapshot-log-viewer').html('Loading...<br />');

		var snapshot_href_params = tb_parseQuery( jQuery(this).attr('href') );

		var snapshot_log_position = 0;
		var snapshot_log_viewer_polling = setInterval(function() {

			if (jQuery('#TB_window').length) {
								
				var data = {
					action: 'snapshot_view_log_ajax',
					'snapshot-item': snapshot_href_params['snapshot-item'],
					'snapshot-data-item': snapshot_href_params['snapshot-data-item'],
					'snapshot-log-position': snapshot_log_position
				}
		
				snapshot_ajax_hdl_xhr = jQuery.ajax({
				  	type: 'POST',
				  	url: ajaxurl,
					cache: false,
			        data: data,
					dataType: 'json',
			    	error: function(jqXHR, textStatus, errorThrown) {
						clearInterval(snapshot_log_viewer_polling);				
					},
				    success: function(reply_data) {
						if ((reply_data != undefined) && (reply_data['payload'] != undefined)) {
							if (snapshot_log_position == 0) {
								jQuery('#snapshot-log-viewer').html(reply_data['payload']);							
							} else {
								jQuery('#snapshot-log-viewer').append(reply_data['payload']);
							}
							
							if (snapshot_href_params['live'] == '1') {
								if (jQuery('#TB_window').length) {
									jQuery('#TB_ajaxContent').scrollTop(jQuery('#TB_ajaxContent')[0].scrollHeight);									
								}
							} else {
								clearInterval(snapshot_log_viewer_polling);	
							}
						}

						if ((reply_data != undefined) && (reply_data['position'] != undefined)) {
							if (snapshot_log_position != reply_data['position']) {
								snapshot_log_position = reply_data['position'];
							}
						}
			    	}
				});
			} else {
				clearInterval(snapshot_log_viewer_polling);				
			}
		}, 1700);


		return false;		
	});
	
	jQuery('a.snapshot-abort-item').click(function() {
		var item_info = jQuery(this).attr('href');
		
		var data = {
			action: 'snapshot_item_abort_ajax',
			snapshot_item_info: item_info
		};

	    jQuery.ajax({
		  	type: 'POST',
		  	url: ajaxurl,
	        data: data,
			dataType: 'json',
	        success: function(reply_data) {
				if (reply_data['errorStatus'] != undefined) {
					if (reply_data['errorStatus'] == false) {
						if (reply_data['responseText'] != undefined) {
							alert(reply_data['responseText']);
							window.location.reload();
						}

					} else {

						if (reply_data['errorText'] != undefined) {
							alert(reply_data['errorText']);
						}
					}
				}
			}
		});
		
		
		return false;		
	});
	
	
	
	/* When a table name checkbox is checked or unchecked we need to update the 'Select all'/'Deselect all' link labels */
	jQuery('input.snapshot-table-item').click(function () {
		check_checkbox_state();
	});
	check_checkbox_state();	// Call on pagew load to reset our displayed link labels. 
	
	/* Used on the 'Add New Snapshot' panel. Handles the Select All/Deselect All for the tables checkboxes */
	jQuery('a.snapshot-table-select-all').click(function () {
		var link_state = jQuery(this).html();
		if (link_state == "Select all")
		{
			jQuery(this).html('Unselect all');
			jQuery(this).parent().parent().find('ul input:checkbox').attr('checked', true);
		}
		else if (link_state == "Unselect all")
		{
			jQuery(this).html('Select all');
			jQuery(this).parent().parent().find('ul input:checkbox').attr('checked', false);
		}
		
		return false;
	});

	/* Used on the 'Add New Snapshot' panel. Handles the backup All vs Backup Selected radio button 
	set to show/hide backup sub-options */
	jQuery('input.snapshot-backup-options').click(function () {
		var options_val = jQuery(this).val();
		if (options_val == 'all') {
			jQuery('div.snapshot-form-fiels-backup-options-select').slideUp('slow');
		} else if (options_val == "selected") {
			jQuery('div.snapshot-form-fiels-backup-options-select').slideDown('slow');
		}
	});

	/* This section controls the Submit button on the Restore form. The button is disabled until the user selects a snapshot to restore */
	jQuery('input:radio[name="snapshot-restore-file"]').click(function () {
		if (jQuery('input:radio[name="snapshot-restore-file"]').is(":checked")) {
			jQuery('input#snapshot-form-restore-submit').removeAttr('disabled');
		} else {
			jQuery('input#snapshot-form-restore-submit').attr('disabled', 'disabled');
		}
	});

	/* Used on the 'All Snapshots' and 'Activity log' panels. Used to show/hide the WP tables container */
	$('a.snapshot-list-table-global-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-global-container').is(":visible"))
		{
			$(this).parent().parent().find('p.snapshot-list-table-global-container').slideUp();

		} else {
			
			$(this).parent().parent().find('p.snapshot-list-table-global-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-global-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
		}
		return false;
	});
	$('a.snapshot-list-table-wp-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-wp-container').is(":visible"))
		{
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').slideUp();

		} else {
			
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
		}
		return false;
	});

	/* Used on the 'All Snapshots' and 'Activity log' panels. Used to show/hide the Non-WP tables container */
	$('a.snapshot-list-table-non-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-non-container').is(":visible"))
		{
			var link_state = $(this).html().replace('hide', 'show');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-non-container').slideUp();
			
		} else {

			var link_state = $(this).html().replace('show', 'hide');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-non-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-non-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
		}

		return false;
	});

	$('a.snapshot-list-table-other-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-other-container').is(":visible"))
		{
			var link_state = $(this).html().replace('hide', 'show');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-other-container').slideUp();
			
		} else {

			var link_state = $(this).html().replace('show', 'hide');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-other-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-other-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
		}

		return false;
	});

	jQuery("form#snapshot-add-update select#snapshot-destination").change(function() {
		var destination_value = jQuery(this).val();
		if (destination_value != '') {
			var destination_option = jQuery('form#snapshot-add-update select#snapshot-destination option[value="'+destination_value+'"]');
			if (jQuery(destination_option).hasClass('google-drive')) {
				jQuery('div#snapshot-destination-directory-description').hide();
				jQuery('div#snapshot-destination-directory-description-google-drive').show();
			} else {
				jQuery('div#snapshot-destination-directory-description').show();
				jQuery('div#snapshot-destination-directory-description-google-drive').hide();
			}
	 	} else {
			jQuery('div#snapshot-destination-directory-description').show();
			jQuery('div#snapshot-destination-directory-description-google-drive').hide();
	 	}
	});



	/*  On a Multisite install there is a dropdown on the Add New Snapshot form. This dropdown allows the admin to select a blog to backup.
		When a blog is selected we update the table checkboxes displayed */
	jQuery("form#snapshot-add-update select#snapshot-blog-id").change(function() {

		var blog_id = jQuery(this).val();
		if (blog_id > 0) {

			var data = {
				action: 'snapshot_show_blog_tables',
				snapshot_blog_id: blog_id
			};

		    jQuery.ajax({
			  	type: 'POST',
			  	url: ajaxurl,
		        data: data,
				dataType: 'json',
		        success: function(reply_data) {

					if (reply_data['tables'] != undefined) {
						json_tables_data = reply_data['tables'];

						/* The structure of the data is the same as the function get_database_tables */
						for (var table_group in json_tables_data) {
							if (!json_tables_data.hasOwnProperty(table_group)) continue;
							var table_set = json_tables_data[table_group];

							/* for each set of tables within the group process */
							snapshot_build_list_checkbox(table_group, table_set)
						}
					}
					
					// Update the media upload path on the File option display. 
					if (reply_data['upload_path'] != undefined) {
						//alert('upload_path=['+reply_data['upload_path']+']');
						jQuery('span.snapshot-media-upload-path').html(reply_data['upload_path']);
					}					

					// IF we are running Multisite and this is the main site OR if not Multisite then we provide select options
					// to include themes, plugins, WP core, etc files. If not main site then only the media upload folder is included. 
					if (reply_data['is_main_site'] != undefined) {
						if (reply_data['is_main_site'] == "YES") {
							jQuery('li.snapshot-backup-files-sections-main-only').show();
							jQuery('span.snapshot-backup-files-sections-main-only').show();
						} else {
							jQuery('li.snapshot-backup-files-sections-main-only').hide();
							jQuery('span.snapshot-backup-files-sections-main-only').hide();
							if (jQuery('input#snapshot-files-option-selected:checked').val() == "selected") {
								jQuery('input#snapshot-files-option-all').attr('checked', 'checked');
							} 
						}
					}					
				}
			});
		}
	});
	
	jQuery("form#snapshot-add-update button#snapshot-blog-id-lookup").click(function() {
		var blog_id_search = jQuery("form#snapshot-add-update input#snapshot-blog-id-search").val();
		// console.log("blog_id_search=["+blog_id_search+"]");
		if (blog_id_search != undefined) {

			jQuery('div#snapshot-blog-search span#snapshot-blog-search-error').hide();
			jQuery('div#snapshot-blog-search span.spinner').show();

			var data = {
				action: 'snapshot_show_blog_tables',
				snapshot_blog_id_search: blog_id_search
			};

		    jQuery.ajax({
			  	type: 'POST',
			  	url: ajaxurl,
		        data: data,
				dataType: 'json',
		        success: function(reply_data) {
					jQuery('div#snapshot-blog-search span.spinner').hide();
					
					if ((reply_data['blog'] != undefined) && (reply_data['blog']['blog_id'] != undefined)) {
						var blog_id = parseInt(reply_data['blog']['blog_id']);
						if (blog_id > 0) {
							jQuery("form#snapshot-add-update input#snapshot-form-save-submit").removeAttr('disabled');
							
							
							//var blog_name = reply_data['blog']['blogname']+" ("+reply_data['blog']['domain']+")";
							var blog_name = reply_data['blog']['blogname']+" ("+reply_data['blog']['domain'];
							if (reply_data['mapped_domain'] != undefined) {
								blog_name += " / "+reply_data['mapped_domain']+" ";
							}
							blog_name += ")";
							
							
							
							jQuery('div#snapshot-blog-search-success span#snapshot-blog-name').html(blog_name);
							jQuery('div#snapshot-blog-search-success').show();
							jQuery('div#snapshot-blog-search').hide();
						
							jQuery('input#snapshot-blog-id').val(blog_id);

							if (reply_data['tables'] != undefined) {
								json_tables_data = reply_data['tables'];

								/* The structure of the data is the same as the function get_database_tables */
								for (var table_group in json_tables_data) {
									if (!json_tables_data.hasOwnProperty(table_group)) continue;
									var table_set = json_tables_data[table_group];

									/* for each set of tables within the group process */
									snapshot_build_list_checkbox(table_group, table_set)
								}
							}

							// Update the media upload path on the File option display. 
							if (reply_data['upload_path'] != undefined) {
								//alert('upload_path=['+reply_data['upload_path']+']');
								jQuery('span.snapshot-media-upload-path').html(reply_data['upload_path']);
							}					

							// IF we are running Multisite and this is the main site OR if not Multisite then we provide select options
							// to include themes, plugins, WP core, etc files. If not main site then only the media upload folder is included. 
							if (reply_data['is_main_site'] != undefined) {
								if (reply_data['is_main_site'] == "YES") {
									jQuery('li.snapshot-backup-files-sections-main-only').show();
									jQuery('span.snapshot-backup-files-sections-main-only').show();
								} else {
									jQuery('li.snapshot-backup-files-sections-main-only').hide();
									jQuery('span.snapshot-backup-files-sections-main-only').hide();
									if (jQuery('input#snapshot-files-option-selected:checked').val() == "selected") {
										jQuery('input#snapshot-files-option-all').attr('checked', 'checked');
									} 
								}
							}	
						} else {
							jQuery("form#snapshot-add-update input#snapshot-form-save-submit").attr('disabled', 'disabled');
						}
					} else {
						jQuery('div#snapshot-blog-search span#snapshot-blog-search-error').show();
						jQuery("form#snapshot-add-update input#snapshot-form-save-submit").attr('disabled', 'disabled');
					}														
				}
			});
		}
		return false;
	});
	
	jQuery("form#snapshot-add-update div#snapshot-blog-search-success button#snapshot-blog-id-change").click(function() {
		jQuery('div#snapshot-blog-search-success').hide();
		jQuery('div#snapshot-blog-search').show();		
		return false;
	});

	if (jQuery("form#snapshot-edit-restore input#snapshot-blog-id").length) {
		var blog_id = jQuery("form#snapshot-edit-restore input#snapshot-blog-id").val();
		if (blog_id == '') {
			jQuery("form#snapshot-edit-restore input#snapshot-form-restore-submit").attr('disabled', 'disabled');			
		} else {
			jQuery("form#snapshot-edit-restore input#snapshot-form-restore-submit").removeAttr('disabled');
		}
	}

	if (jQuery("form#snapshot-add-update input#snapshot-blog-id").val() == '') {
		jQuery("form#snapshot-add-update input#snapshot-form-save-submit").attr('disabled', 'disabled');
	}


	jQuery("form#snapshot-edit-restore button#snapshot-blog-id-lookup").click(function() {
		var blog_id_search = jQuery("form#snapshot-edit-restore input#snapshot-blog-id-search").val();
		//console.log("blog_id_search=["+blog_id_search+"]");
		
		if ((blog_id_search != undefined) && (blog_id_search != '')) {

			jQuery('#snapshot-blog-search span#snapshot-blog-search-error').hide();
			jQuery('#snapshot-blog-search span.spinner').show();

			var data = {
				action: 'snapshot_get_blog_restore_info',
				snapshot_blog_id_search: blog_id_search
			};

		    jQuery.ajax({
			  	type: 'POST',
			  	url: ajaxurl,
		        data: data,
				dataType: 'json',
		        success: function(reply_data) {
					jQuery('#snapshot-blog-search span.spinner').hide();
					
					if ((reply_data['blog'] != undefined) && (reply_data['blog']['blog_id'] != undefined)) {
						var blog_name = reply_data['blog']['blogname']+" ("+reply_data['blog']['domain'];
						if (reply_data['mapped_domain'] != undefined) {
							blog_name += " / "+reply_data['mapped_domain']+" ";
						}
						blog_name += ")";
						
						jQuery('#snapshot-blog-search-success span#snapshot-blog-name').html(blog_name);
						jQuery('#snapshot-blog-search-success').show();
						jQuery('#snapshot-blog-search').hide();
						
						if ((reply_data['blog'] != undefined) && (reply_data['blog']['blog_id'] != undefined)) {
							jQuery('input#snapshot-blog-id').val(reply_data['blog']['blog_id']);
							jQuery('span#snapshot-new-blog-id').html(reply_data['blog']['blog_id']);
							jQuery("form#snapshot-edit-restore input#snapshot-form-restore-submit").removeAttr('disabled');
							
						}

						if (reply_data['WP_DB_NAME'] != undefined) {
							jQuery('span#snapshot-new-db-name').html(reply_data['WP_DB_NAME']);
						}					

						if (reply_data['WP_DB_BASE_PREFIX'] != undefined) {
							jQuery('span#snapshot-new-db-base-prefix').html(reply_data['WP_DB_BASE_PREFIX']);
						}					

						if (reply_data['WP_DB_PREFIX'] != undefined) {
							jQuery('span#snapshot-new-db-prefix').html(reply_data['WP_DB_PREFIX']);
						}					

						// Update the media upload path on the File option display. 
						if (reply_data['WP_UPLOAD_PATH'] != undefined) {
							jQuery('span#snapshot-new-upload-path').html(reply_data['WP_UPLOAD_PATH']);
						}					

						if (reply_data['WP_DB_PREFIX'] != reply_data['WP_DB_BASE_PREFIX']) {
							jQuery('input#snapshot-files-option-config').attr('checked', false);
							jQuery('input#snapshot-files-option-config').prop('disabled',true);

							jQuery('input#snapshot-files-option-htaccess').attr('checked', false);
							jQuery('input#snapshot-files-option-htaccess').prop('disabled',true);
						} else {
							jQuery('input#snapshot-files-option-config').prop('disabled',false);
							jQuery('input#snapshot-files-option-htaccess').prop('disabled',false);
							
						}

					} else {
						jQuery('#snapshot-blog-search span#snapshot-blog-search-error').show();
					}														
				}
			});
		}
		return false;
	});

	jQuery("button#snapshot-blog-id-cancel").click(function() {
		// console.log('in cancel');
		jQuery('#snapshot-blog-search-success').show();
		jQuery('#snapshot-blog-search').hide();
		
		return false;
	});
		

	jQuery("form#snapshot-edit-restore #snapshot-blog-search-success button#snapshot-blog-id-change").click(function() {
		jQuery('#snapshot-blog-search-success').hide();
		jQuery('#snapshot-blog-search').show();		
		return false;
	});

	

	jQuery("form#snapshot-add-update select#snapshot-interval").change(function() {
		var interval = jQuery(this).val();

		jQuery('#interval-offset div.interval-offset-hourly').hide();
		jQuery('#interval-offset div.interval-offset-daily').hide();
		jQuery('#interval-offset div.interval-offset-weekly').hide();
		jQuery('#interval-offset div.interval-offset-monthly').hide();
		jQuery('#interval-offset div.interval-offset-none').hide();			
		
		if (interval == "snapshot-hourly") {
			jQuery('#interval-offset div.interval-offset-hourly').show();				
		} else if ((interval == "snapshot-daily") || (interval == "snapshot-twicedaily")) {
			jQuery('#interval-offset div.interval-offset-daily').show();				
		} else if ((interval == "snapshot-weekly") || (interval == "snapshot-twiceweekly")) {
			jQuery('#interval-offset div.interval-offset-weekly').show();
		} else if ((interval == "snapshot-monthly") || (interval == "snapshot-twicemonthly")) {
			jQuery('#interval-offset div.interval-offset-monthly').show();
		} else if ((interval == "") || (interval == "immediate") || (interval == "snapshot-5minutes")) {
			jQuery('#interval-offset div.interval-offset-none').show();			
		}		
	});
	
	jQuery("input.snapshot-tables-option").click(function() {
		var backup_database_options = jQuery('input.snapshot-tables-option:checked').val();
		if ((backup_database_options == "none") || (backup_database_options == "all")) {
			jQuery('div#snapshot-selected-tables-container').slideUp('fast');
			
		} else {
			jQuery('div#snapshot-selected-tables-container').slideDown('slow');
		}
	});

	jQuery("input.snapshot-files-option").click(function() {
		var backup_database_options = jQuery('input.snapshot-files-option:checked').val();
		if (backup_database_options == "none") {
			jQuery('div#snapshot-selected-files-container').slideUp('fast');
			jQuery('div#snapshot-selected-files-sync-container').slideUp('fast');
			
		} else if (backup_database_options == "all") {
			jQuery('div#snapshot-selected-files-container').slideUp('fast');
			jQuery('div#snapshot-selected-files-sync-container').slideDown('slow');			
			
		} else if (backup_database_options == "selected") {
			jQuery('div#snapshot-selected-files-container').slideDown('slow');
			jQuery('div#snapshot-selected-files-sync-container').slideDown('slow');			
		}
	});

	/* Called to replace the checkboxes by section on the Snapshot add new form */
	function snapshot_build_list_checkbox(table_type, table_set) {

		/* First we remove all existing <li> items */

		if (!jQuery('div#snapshot-tables-'+table_type+'-set').length)
			return;

		if (!jQuery('div#snapshot-tables-'+table_type+'-set ul#snapshot-table-list-'+table_type).length) {
			jQuery('div#snapshot-tables-'+table_type+'-set').append('<ul id="snapshot-table-list-'+table_type+'" class="snapshot-table-list"></ul>');
		} else {
			jQuery('div#snapshot-tables-'+table_type+'-set ul#snapshot-table-list-'+table_type+' li').each(function(n,item) {
				jQuery(item).remove();
			});
		}

		// IF we actually has items to add. 
		if (table_set != undefined) {

			var tables_count = 0;
			
			for (var table_name in table_set) {
				if (!table_set.hasOwnProperty(table_name)) continue;
				tables_count = tables_count + 1;
				
				var table_checked = table_set[table_name];
				if (table_checked == "checked")
					table_checked = ' checked="checked" ';
				else
					table_checked = '';
				
				jQuery('div#snapshot-tables-'+table_type+'-set ul#snapshot-table-list-'+table_type).append('<li><input id="snapshot-tables-'+table_name+'" '+table_checked+' class="snapshot-table-item" type="checkbox" name="snapshot-tables['+table_type+']['+table_name+']" value="'+table_name+'"> <label for="snapshot-tables-">'+table_name+'</label></li>');
			}
			
			if (tables_count > 0) {
				jQuery('a#snapshot-table-'+table_type+'-select-all').show();
				jQuery('#snapshot-tables-'+table_type+'-set').show();
				
			} else {

				// If not then add a message and hide the select all link. 
				jQuery('ul#snapshot-table-list-'+table_type).append('<li>No tables</li>');
				jQuery('a#snapshot-table-'+table_type+'-select-all').hide();
				jQuery('#snapshot-tables-'+table_type+'-set').hide();
				
			}
		} else {
			jQuery('#snapshot-tables-'+table_type+'-set').hide();
		}
	}

/*
	jQuery('select#snapshot-interval').change(function(){
		
		var snapshot_interval = jQuery(this).val();
		if (snapshot_interval == "") {
			jQuery('select#snapshot-destination').val('');
			jQuery('select#snapshot-destination').attr('disabled', 'disabled');
			jQuery('input#snapshot-add-button').val('Create Snapshot');			
			
			jQuery('input#snapshot-files-media').removeAttr('checked');
			jQuery('input#snapshot-files-media').attr('disabled', 'disabled');

			jQuery('input#snapshot-files-database-only').attr('checked', 'checked');
			jQuery('input#snapshot-files-database-only').attr('disabled', 'disabled');
			
			
		} else {
			jQuery('select#snapshot-destination').removeAttr('disabled');
			jQuery('input#snapshot-add-button').val('Schedule Snapshot');
			
			jQuery('input#snapshot-files-media').removeAttr('disabled');
			jQuery('input#snapshot-files-database-only').removeAttr('disabled');
		}
		
	});
*/	
	
	jQuery('select#snapshot-destination').change(function() {
		var destination_type = jQuery(this).find("option:selected").parent().attr("label");
		if (destination_type == "Dropbox") {
			jQuery('input#snapshot-destination-sync-mirror').attr('disabled', false);
		} else {
			jQuery('input#snapshot-destination-sync-mirror').attr('disabled', 'disabled');
			jQuery('input#snapshot-destination-sync-archive').attr('checked', 'checked');
		}
	});
	
	
	/* Handler for Backup/Restore user Aborts */
	
	var snapshot_ajax_hdl_xhr = null;
	var snapshot_ajax_user_aborted = false;
	
	function snapshot_button_abort_proc() {	
		snapshot_ajax_hdl_xhr.abort();

		snapshot_ajax_user_aborted = true;
		
		jQuery( '#snapshot-progress-bar-container').hide();

		/* Write a message to the progress text container shown below the actual bar. Just information what table name and what table count */
		jQuery( "#snapshot-ajax-warning" ).html('<p>Snapshot backup aborted.</p>');
		jQuery( "#snapshot-ajax-warning" ).show();
		
		return false;
	}
	
	
	/* Used on the 'Add New Snapshot' panel. Handles the form submit to backup one table per request. Seems this was taking too long on some servers. */
	jQuery("form#snapshot-add-update").submit(function() {

		var snapshot_form_files_sections = [];	
		var snapshot_form_files_option = jQuery('input.snapshot-files-option:checked').val();
		var snapshot_form_destination_sync = 'archive';
		var snapshot_form_files_ignore = '';
		
		if (snapshot_form_files_option == "none") {
		} else {
			if (snapshot_form_files_option == "all") {

			} else if (snapshot_form_files_option == "selected") {
				jQuery('input.snapshot-backup-sub-options:checked', this).each( function() {
					var cb_value = jQuery(this).attr('value');
					snapshot_form_files_sections[snapshot_form_files_sections.length] = cb_value;
				});	
			
				// Do we have tables to process?
				if (snapshot_form_files_sections.length == 0) {

					/* If the user didn't select any sub=options show this warning */
					jQuery( "#snapshot-ajax-warning" ).html('<p>You must select at least one Files backup option</p>');
					jQuery( "#snapshot-ajax-warning" ).show();
					return false;
				}
			}
			snapshot_form_files_ignore = jQuery('textarea#snapshot-files-ignore').val();
			
			var snapshot_form_destination_sync = jQuery('input.snapshot-destination-sync:checked').val();			
		}

		/* Build and array of the checked tables to backup */
		var snapshot_form_tables_array = [];
		var snapshot_form_tables_option = jQuery('input.snapshot-tables-option:checked').val();

		if (snapshot_form_tables_option == "selected") {

			jQuery('input.snapshot-table-item:checked', this).each( function(){
				var cb_value = jQuery(this).attr('value');
				snapshot_form_tables_array[snapshot_form_tables_array.length] = cb_value;
			});			

			// Do we have tables to process?
			if (snapshot_form_tables_array.length == 0) {

				/* If the user didn't select any tables show this warning */
				jQuery( "#snapshot-ajax-warning" ).html('<p>You must select at least one table</p>');
				jQuery( "#snapshot-ajax-warning" ).show();
				return false;			
			}

		} else if (snapshot_form_tables_option == "all") {
			jQuery('input.snapshot-table-item', this).each( function(){
				var cb_value = jQuery(this).attr('value');
				snapshot_form_tables_array[snapshot_form_tables_array.length] = cb_value;
			});			
		}
		
		
		if ((snapshot_form_files_option == "none") && (snapshot_form_tables_option == "none")) {

			/* If the user didn't select any tables show this warning */
			jQuery( "#snapshot-ajax-warning" ).html('<p>You must select which Files and/or Tables to backup in this Snapshot</p>');
			jQuery( "#snapshot-ajax-warning" ).show();
			return false;
		} 
		
		var snapshot_form_action = jQuery('input#snapshot-action', this).val();
		if (snapshot_form_action == "add") {
			var snapshot_form_item = jQuery('input#snapshot-item', this).val();
		} else if (snapshot_form_action == "update") {
			var snapshot_form_item = jQuery('input#snapshot-item', this).val();
		}

		var snapshot_form_data_item = jQuery('input#snapshot-data-item', this).val();

		if (snapshot_form_item == "") {
			jQuery( "#snapshot-ajax-warning" ).html('<p>ERROR: The Snapshot timekey is not set. Try reloading the page.</p>');
			jQuery( "#snapshot-ajax-warning" ).show();
			
			return false;
		}
		
		/* If the interval is not empty then the user is attempting to set a scheduled snapshot. So return true to allow the form submit */
		var snapshot_form_interval = jQuery('select#snapshot-interval', this).val();
		if (snapshot_form_interval != "immediate") {
			return true;
		}
		var snapshot_form_archive_count = jQuery('input#snapshot-archive-count', this).val();
		
		
		var snapshot_form_destination = jQuery('select#snapshot-destination', this).val();
		var snapshot_form_destination_directory = jQuery('input#snapshot-destination-directory', this).val();
		
		
		/* From the form grab the Name and Notes field values. */
		var snapshot_form_blog_id = 0;
		if (jQuery('select#snapshot-blog-id', this).length > 0) {
			snapshot_form_blog_id 			= jQuery('select#snapshot-blog-id', this).val();
		} else {
			snapshot_form_blog_id 			= jQuery('input#snapshot-blog-id', this).val();
		}
		
		var snapshot_form_name 				= jQuery('input#snapshot-name', this).val();
		var snapshot_form_notes 			= jQuery('textarea#snapshot-notes', this).val();
		//var snapshot_form_files_option		= jQuery('input:radio["name=snapshot-files-option"]:checked').val();
		var snapshot_form_files_option		= jQuery('input:radio[name=snapshot-files-option]:checked', this).val();

		// Clear out the progress text and warning containers 
		jQuery( "#snapshot-ajax-warning" ).html();
		jQuery( "#snapshot-ajax-warning" ).hide();		
		
										
		/* Hide the form while processing */
		jQuery('#poststuff').hide();

		var table_memory_info = '<div id="snapshot-memory-info"><h3>Memory:</h3><div class="label"><span class="text">Limit</span><span class="number memory-limit">0.00M</span></div><div class="label"><span class="text">Usage:</span><span class="number memory-usage">0.00M</span></div><div class="label"><span class="text">Peak:</span><span class="number memory-peak">0.00M</span></div></div><div style="clear:both"></div>';
		jQuery('#snapshot-progress-bar-container').before(table_memory_info);

		
		var table_name = "init";
		var table_text = "Snapshot initializing";
		var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort button-secondary">Abort</button><div class="snapshot-text">'+table_text+'</div></div>';
		jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);

//		for (var table_key in snapshot_form_tables_sections) {
//			var table_name = snapshot_form_tables_array[table_key];
//			
//			var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">Database: '+table_name+'</div></div>';
//			jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
//		}

		for (var table_key in snapshot_form_tables_array) {
			if (!snapshot_form_tables_array.hasOwnProperty(table_key)) continue;
			var table_name = snapshot_form_tables_array[table_key];
	
			var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">Database: '+table_name+'</div></div>';
			jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
		}

//		for (var file_set_key in snapshot_form_files_sections) {
//			var file_set = snapshot_form_files_sections[file_set_key];
//			for (var file_key in file_set) {

//				var file_name = file_set[file_key];
			if ((snapshot_form_files_option == "all") || (snapshot_form_files_option == "selected")) {
			
				var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-file"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">Files: <span class="snapshot-filename" style="font-style: italic;"></span></div></div>';
				jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
//				break;
			}
//			break;
//		}

		var table_name = "finish";
		var table_text = "Snapshot Finishing";
		var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">'+table_text+'</div></div>';
		jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
			
		// Add/Show the progerss bars. 
		jQuery( '#snapshot-progress-bar-container' ).show();
		
		var	tablesArray	= [];
		var filesArray	= [];
		
		function snapshot_backup_tables_proc(proc_action, idx) {

			if (proc_action == "init") {

				var table_name = proc_action;
				
				var table_text = jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-text' ).html();					
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-text' ).html(table_text+' (gathing information from tables)');

				var data = {
					action: 'snapshot_backup_ajax',
					'snapshot-proc-action': proc_action,
					'snapshot-action': snapshot_form_action,
					'snapshot-item': snapshot_form_item,
					'snapshot-data-item': snapshot_form_data_item,
					'snapshot-blog-id': snapshot_form_blog_id,
					'snapshot-name': snapshot_form_name,
					'snapshot-notes': snapshot_form_notes,	
					'snapshot-files-option': snapshot_form_files_option,					
					'snapshot-files-sections': snapshot_form_files_sections,
					'snapshot-files-ignore': snapshot_form_files_ignore,
					'snapshot-tables-option': snapshot_form_tables_option,
					'snapshot-tables-array': snapshot_form_tables_array,
					'snapshot-interval': snapshot_form_interval,
					'snapshot-archive-count': snapshot_form_archive_count,
					'snapshot-destination': snapshot_form_destination,
					'snapshot-destination-directory': snapshot_form_destination_directory,
					'snapshot-destination-sync': snapshot_form_destination_sync
				};
				
				// console.log( data );
				
			    snapshot_ajax_hdl_xhr = jQuery.ajax({
				  	type: 'POST',
				  	url: ajaxurl,
			        data: data,
					dataType: 'json',
					error: function(jqXHR, textStatus, errorThrown) {
						if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
							jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
							jQuery( "#snapshot-ajax-error" ).show();
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();																					
						}
					},
			        success: function(reply_data) {

						if ((reply_data == undefined) || (reply_data['errorStatus'] == undefined)) {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();
																					
						} else if (reply_data['errorStatus'] != false) {
							if (reply_data['errorText'] == undefined) {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();														
							} else {
								jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
								jQuery( "#snapshot-ajax-error" ).show();
							}
							
						} else if ((reply_data['errorStatus'] == false) || (snapshot_ajax_user_aborted == false)) {
							var table_name = proc_action; // Init
							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('100%');
							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('100%');
							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();

							/* IF no error we start on the files. */
							if (snapshot_ajax_user_aborted == false) {
							
								if (reply_data['files_data'] != undefined) {
									filesArray = reply_data['files_data'];
								}
							
								if (reply_data['table_data'] != undefined) {
									tablesArray = reply_data['table_data'];
									snapshot_backup_tables_proc('table', 0);
								}
								
								if (reply_data['MEMORY'] != undefined) {
									if (reply_data['MEMORY']['memory_limit'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-limit' ).html(reply_data['MEMORY']['memory_limit']);
									}
									if (reply_data['MEMORY']['memory_usage_current'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-usage' ).html(reply_data['MEMORY']['memory_usage_current']);
									}
									if (reply_data['MEMORY']['memory_usage_peak'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-peak' ).html(reply_data['MEMORY']['memory_usage_peak']);
									}
								}								
							}
						}
			        }
			    });
				
			} else if (proc_action == 'table') {

				var table_idx = parseInt(idx);						
				var table_count = table_idx+1;

				/* If we reached the end of the tables send the finish. */
				if (table_count > tablesArray.length)
				{
					if (filesArray.length > 0) {
						snapshot_backup_tables_proc('file', 0);
					} else {
						snapshot_backup_tables_proc('finish', 0);						
					}
					return;
				} else {

					var table_data = tablesArray[table_idx];
					var table_name = table_data['table_name'];

					jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' button.snapshot-button-abort' ).show();
					jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' button.snapshot-button-abort' ).click(function() { 
						snapshot_button_abort_proc();
					});
					
					//var offset = jQuery('#snapshot-item-table-'+table_name).position().top;
					//if (offset < 0 || offset > jQuery('#snapshot-item-table-'+table_name).parent().height()) {
					//	if (offset < 0)
					//		offset = jQuery('#snapshot-item-table-'+table_name).parent()..scrollTop() + offset;
					//	jQuery('#snapshot-item-table-'+table_name).parent()..animate({ scrollTop: offset }, 300);
					//}
					
					var data = {
						action: 'snapshot_backup_ajax',
						'snapshot-proc-action': proc_action,
						'snapshot-action': snapshot_form_action,
						'snapshot-item': snapshot_form_item,
						'snapshot-data-item': snapshot_form_data_item,
						'snapshot-blog-id': snapshot_form_blog_id,
						'snapshot-table-data-idx': table_idx
					};

			    	snapshot_ajax_hdl_xhr = jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
						dataType: 'json',
						error: function(jqXHR, textStatus, errorThrown) {
							if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
								jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
								jQuery( "#snapshot-ajax-error" ).show();
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();																					
							}
						},
				        success: function(reply_data) {

							if ((reply_data == undefined) || (reply_data['errorStatus'] == undefined)) {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();

							} else if (reply_data['errorStatus'] != false) {
								if (reply_data['errorText'] == undefined) {
									jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
									jQuery( "#snapshot-ajax-error" ).show();														
								} else {
									jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
									jQuery( "#snapshot-ajax-error" ).show();
								}

							} else if ((reply_data['errorStatus'] == false) || (snapshot_ajax_user_aborted == false)) {

								if (reply_data['table_data'] == undefined) {
									if (reply_data['errorText'] != undefined) {
										jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
										jQuery( "#snapshot-ajax-error" ).show();
									} else {
										jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
										jQuery( "#snapshot-ajax-error" ).show();																								
									}
									
								} else {
									table_data = reply_data['table_data'];

									var rows_complete = parseInt(table_data['rows_start']) + parseInt(table_data['rows_end']);
									if (rows_complete > 0) {
										var snapshot_percent = Math.ceil((rows_complete/table_data['rows_total'])*100);

										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .bar' ).width(snapshot_percent+'%');
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .percent' ).html(snapshot_percent+'% (rows '+rows_complete+'/'+table_data['rows_total']+')');

									} else {

										var snapshot_percent = 100;
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .bar' ).width(snapshot_percent+'%');
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .percent' ).html(snapshot_percent+'% (rows '+rows_complete+'/'+table_data['rows_total']+')');
									}
																		
									// Are we at 100%? Hide the Abort button
									if (snapshot_percent == 100) {
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();
									}

									if (reply_data['MEMORY'] != undefined) {
										if (reply_data['MEMORY']['memory_limit'] != undefined) {
											jQuery( '#snapshot-memory-info span.memory-limit' ).html(reply_data['MEMORY']['memory_limit']);
										}
										if (reply_data['MEMORY']['memory_usage_current'] != undefined) {
											jQuery( '#snapshot-memory-info span.memory-usage' ).html(reply_data['MEMORY']['memory_usage_current']);
										}
										if (reply_data['MEMORY']['memory_usage_peak'] != undefined) {
											jQuery( '#snapshot-memory-info span.memory-peak' ).html(reply_data['MEMORY']['memory_usage_peak']);
										}
									}								

								}
								snapshot_backup_tables_proc('table', table_count);
							}
				        }
				    });
				}
			} else if (proc_action == "file") {

				var file_idx = parseInt(idx);						
				var file_count = file_idx+1;
				var table_name = proc_action;
				
				/* If we reached the end of the tables send the finish. */
				if (file_count > filesArray.length)
				{
					snapshot_backup_tables_proc('finish', 0);					
					return false;
					
				} else {

					var file_data_key = filesArray[file_idx];
					jQuery( '#snapshot-item-'+table_name+' .snapshot-filename' ).html(": "+file_data_key);

					jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' button.snapshot-button-abort' ).show();
					jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' button.snapshot-button-abort' ).click(function() { 
						snapshot_button_abort_proc();
					});
					
					var data = {

						action: 'snapshot_backup_ajax',
						'snapshot-proc-action': proc_action,
						'snapshot-action': snapshot_form_action,
						'snapshot-item': snapshot_form_item,
						'snapshot-data-item': snapshot_form_data_item,
						'snapshot-blog-id': snapshot_form_blog_id,
						'snapshot-file-data-key': file_data_key
					};

			    	snapshot_ajax_hdl_xhr = jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
						dataType: 'json',
						error: function(jqXHR, textStatus, errorThrown) {
							if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
								jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
								jQuery( "#snapshot-ajax-error" ).show();
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();																					
							}
						},
				        success: function(reply_data) {

							if ((reply_data == undefined) || (reply_data['errorStatus'] == undefined)) {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();

							} else if (reply_data['errorStatus'] != false) {
								if (reply_data['errorText'] == undefined) {
									jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
									jQuery( "#snapshot-ajax-error" ).show();														
								} else {
									jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
									jQuery( "#snapshot-ajax-error" ).show();
								}

							} else if ((reply_data['errorStatus'] == false) || (snapshot_ajax_user_aborted == false)) {

								if (file_count < filesArray.length) {

									var snapshot_percent = Math.ceil((file_count/filesArray.length)*100);

									jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .bar' ).width(snapshot_percent+'%');
									jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .percent' ).html(snapshot_percent+'% (files '+file_count+'/'+filesArray.length+')');

								} else {

									var snapshot_percent = 100;
									jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .bar' ).width(snapshot_percent+'%');
									jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .percent' ).html(snapshot_percent+'% (rows '+file_count+'/'+filesArray.length+')');
										
								}
									
								// Are we at 100%? Hide the Abort button
								if (snapshot_percent == 100) {
									jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .snapshot-button-abort' ).hide();
									jQuery( '#snapshot-item-'+table_name+' .snapshot-filename' ).html("");
								}
								
								if (reply_data['MEMORY'] != undefined) {
									if (reply_data['MEMORY']['memory_limit'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-limit' ).html(reply_data['MEMORY']['memory_limit']);
									}
									if (reply_data['MEMORY']['memory_usage_current'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-usage' ).html(reply_data['MEMORY']['memory_usage_current']);
									}
									if (reply_data['MEMORY']['memory_usage_peak'] != undefined) {
										jQuery( '#snapshot-memory-info span.memory-peak' ).html(reply_data['MEMORY']['memory_usage_peak']);
									}
								}								
								
								snapshot_backup_tables_proc('file', file_count);
							}
						}
					});
				}
					
			} else if (proc_action == 'finish') {

				var table_name = proc_action;
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('0%');
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('0%');

				var table_text = jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-text' ).html();
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-text' ).html(table_text+' (creating zip archive of tables)');

				var data = {
					action: 'snapshot_backup_ajax',
					'snapshot-proc-action': proc_action,
					'snapshot-action': snapshot_form_action,
					'snapshot-item': snapshot_form_item,
					'snapshot-data-item': snapshot_form_data_item,
					'snapshot-blog-id': snapshot_form_blog_id,
				};

			    snapshot_ajax_hdl_xhr = jQuery.ajax({
				  	type: 'POST',
				  	url: ajaxurl,
			        data: data,
					dataType: 'json',
					error: function(jqXHR, textStatus, errorThrown) {
						if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
							jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
							jQuery( "#snapshot-ajax-error" ).show();
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();																					
						}
					},
			        success: function(reply_data) {

						if ((reply_data == undefined) || (reply_data['errorStatus'] == undefined)) {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();

						} else if (reply_data['errorStatus'] != false) {
							if (reply_data['errorText'] == undefined) {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();														
							} else {
								jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
								jQuery( "#snapshot-ajax-error" ).show();
							}

						} else if ((reply_data['errorStatus'] == false) || (snapshot_ajax_user_aborted == false)) {

							var table_name = "finish";
							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('100%');
							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('100%');

							if (reply_data['MEMORY'] != undefined) {
								if (reply_data['MEMORY']['memory_limit'] != undefined) {
									jQuery( '#snapshot-memory-info span.memory-limit' ).html(reply_data['MEMORY']['memory_limit']);
								}
								if (reply_data['MEMORY']['memory_usage_current'] != undefined) {
									jQuery( '#snapshot-memory-info span.memory-usage' ).html(reply_data['MEMORY']['memory_usage_current']);
								}
								if (reply_data['MEMORY']['memory_usage_peak'] != undefined) {
									jQuery( '#snapshot-memory-info span.memory-peak' ).html(reply_data['MEMORY']['memory_usage_peak']);
								}
							}								

							if (reply_data['responseText'] != undefined) {
								jQuery( "#snapshot-ajax-warning" ).html('<p>'+reply_data['responseText']+'</p>');
								jQuery( "#snapshot-ajax-warning" ).show();
							}
						}
					}
				});
			}
		}
					
		/* Make an AJAX call with 'init' to setup the Session backup filename and other items */
		snapshot_backup_tables_proc('init', 0);
					
		return false;
	});

	jQuery("form#snapshot-edit-restore").submit(function() {
		
		/* From the form grab the Name and Notes field values. */
		var snapshot_item_key = jQuery('input[name="item"]').val();
		
		var snapshot_restore_plugin = jQuery('input#snapshot-restore-option-plugins', this).attr('checked');
		if (snapshot_restore_plugin == "checked") {
			snapshot_restore_plugin = "yes";
		} else {
			snapshot_restore_plugin = "no";
		}

		var snapshot_restore_theme = jQuery('input[name="restore-option-theme"]:checked', this).val();

		var snapshot_form_files_sections = [];	
		var snapshot_form_files_option = jQuery('input.snapshot-files-option:checked').val();
		if (snapshot_form_files_option == "all") {

		} else if (snapshot_form_files_option == "selected") {
			jQuery('input.snapshot-backup-sub-options:checked', this).each( function() {
				var cb_value = jQuery(this).attr('value');
				snapshot_form_files_sections[snapshot_form_files_sections.length] = cb_value;
			});	
			
			// Do we have tables to process?
			if (snapshot_form_files_sections.length == 0) {
				snapshot_form_files_option = "all";
			}
		}

		/* Build and array of the checked tables to backup */
		var snapshot_form_tables_array = [];
		var snapshot_form_tables_option = jQuery('input.snapshot-tables-option:checked').val();

		var snapshot_form_blog_id = jQuery('input#snapshot-blog-id').val();
		
		

		if (snapshot_form_tables_option == "selected") {

			jQuery('input.snapshot-table-item:checked', this).each( function(){
				var cb_value = jQuery(this).attr('value');
				snapshot_form_tables_array[snapshot_form_tables_array.length] = cb_value;
			});			

			// Do we have tables to process?
			if (snapshot_form_tables_array.length == 0) {
				snapshot_form_tables_option == "all";
			}

		} else if (snapshot_form_tables_option == "all") {
			jQuery('input.snapshot-table-item', this).each( function(){
				var cb_value = jQuery(this).attr('value');
				snapshot_form_tables_array[snapshot_form_tables_array.length] = cb_value;
			});			
		}

		/* Hide the form while processing */
		jQuery('#poststuff').hide();
		jQuery('p.snapshot-restore-description').hide();

		// Clear the yellow warning box
		jQuery( "#snapshot-ajax-warning" ).html('');
		jQuery( "#snapshot-ajax-warning" ).hide();

		// Show the progress bar container.
		jQuery( '#snapshot-progress-bar-container' ).show();
		
		var	tablesArray	= [];
		var filesArray	= [];
				
		var snapshot_item_data = jQuery('input:radio[name="snapshot-restore-file"]').val();

		function snapshot_restore_tables_proc(action, idx) {

			if (action == "init") {

				var table_name = action;
				var table_text = "Snapshot determining tables/files to restore";
				var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">'+table_text+'</div></div>';
				jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);

				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('0%');
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('0%');

				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).show();
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' button.snapshot-button-abort' ).click(function() { 
					snapshot_button_abort_proc();
				});
				
				var data = {
					'action': 'snapshot_restore_ajax',
					'snapshot_action': action,
					'item_key': snapshot_item_key,
					'item_data': snapshot_item_data,
					'snapshot-blog-id': snapshot_form_blog_id,
					'snapshot-files-option': snapshot_form_files_option,					
					'snapshot-files-sections': snapshot_form_files_sections,
					'snapshot-tables-option': snapshot_form_tables_option,
					'snapshot-tables-array': snapshot_form_tables_array,
				};

			    snapshot_ajax_hdl_xhr = jQuery.ajax({
				  	type: 'POST',
				  	url: ajaxurl,
			        data: data,
					dataType: 'json',
					error: function(jqXHR, textStatus, errorThrown) {
						if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
							jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
							jQuery( "#snapshot-ajax-error" ).show();
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();																					
						}
					},
			        success: function(reply_data) {

						if (reply_data != null) {
							if (reply_data['errorStatus'] != undefined) {
							
								if (reply_data['errorStatus'] == false) {

									if (snapshot_ajax_user_aborted == false) {

										var table_name = "init";
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('100%');
										jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('100%');

										if ((reply_data['MANIFEST']['TABLES'] != undefined) && (Object.keys(reply_data['MANIFEST']['TABLES']).length)) {
							
											for (var table_name in reply_data['MANIFEST']['TABLES']) {
												if (!reply_data['MANIFEST']['TABLES'].hasOwnProperty(table_name)) continue;
												var table_set = reply_data['MANIFEST']['TABLES'][table_name];

												var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_set['table_name']+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">'+table_set['label']+'</div></div>';
												jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
											}
											tablesArray = reply_data['MANIFEST']['TABLES-DATA'];
										}

										if ((reply_data['MANIFEST']['FILES-DATA'] != undefined) && (Object.keys(reply_data['MANIFEST']['FILES-DATA']).length)) {
											filesArray = reply_data['MANIFEST']['FILES-DATA'];
										
											var table_name = "file";
											var table_text = "Files";
											var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">'+table_text+' <span class="snapshot-filename" style="font-style: italic;"></span></div></div>';
											jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
										}	

										var table_name = "finish";
										var table_text = "Snapshot Finishing";
										var snapshot_item_html = '<div class="snapshot-item" id="snapshot-item-table-'+table_name+'"><div class="progress"><div class="percent">0%</div><div class="bar" style="width: 0px;"></div></div><button style="display: none;" class="snapshot-button-abort">Abort</button><div class="snapshot-text">'+table_text+'</div></div>';
										jQuery('#snapshot-progress-bar-container').append(snapshot_item_html);
								
										snapshot_restore_tables_proc('table', 0);
									}
								} else {
									if (reply_data['errorText'] != undefined) {
										jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
										jQuery( "#snapshot-ajax-error" ).show();
									}								
								}
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot restore attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();							
							}
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot restore attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();							
						}
			        }
			    });
			} else if (action == "table") {

				var table_idx 		= parseInt(idx);						
				var table_count 	= table_idx+1;
				
				/* If we reached the end of the tables send the finish. */
				if (table_count > tablesArray.length) {

					if (filesArray.length > 0) {
						snapshot_restore_tables_proc('file', 0);
					} else {
						snapshot_restore_tables_proc('finish', 0);
					}
					return;
					
				} else {

					var table_data = tablesArray[table_idx];
					var table_name = table_data['table_name'];

					jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' button.snapshot-button-abort' ).show();
					jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' button.snapshot-button-abort' ).click(function() { 
						snapshot_button_abort_proc();
					});

					var data = {
						'action': 'snapshot_restore_ajax',
						'snapshot_action': action,
						'item_key': snapshot_item_key,
						'item_data': snapshot_item_data,						
						'snapshot-blog-id': snapshot_form_blog_id,						
						'snapshot_table': table_name,
						'table_data': table_data
					};
				
					snapshot_ajax_hdl_xhr = jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
						timeout: 600000,
						dataType: 'json',
						error: function(jqXHR, textStatus, errorThrown) {
							if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
								jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
								jQuery( "#snapshot-ajax-error" ).show();
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();																					
							}
						},
				        success: function(reply_data) {

							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();

							if (reply_data['errorStatus'] != undefined) {
								
								if (reply_data['errorStatus'] == false) {

									if (snapshot_ajax_user_aborted == false) {
										
										if (reply_data['table_data'] != undefined) {
											table_data = reply_data['table_data'];
											var rows_complete = parseInt(table_data['rows_start']) + parseInt(table_data['rows_end']);
											if (rows_complete > 0) {
												var snapshot_percent = Math.ceil((rows_complete/table_data['rows_total'])*100);

												jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .bar' ).width(snapshot_percent+'%');
												jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .percent' ).html(snapshot_percent+'% (rows '+rows_complete+'/'+table_data['rows_total']+')');

											} else {

												var snapshot_percent = 100;
												jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .bar' ).width(snapshot_percent+'%');
												jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_data['table_name']+' .progress .percent' ).html(snapshot_percent+'% (rows '+rows_complete+'/'+table_data['rows_total']+')');
												
											}

											// Are we at 100%
											if (snapshot_percent == 100) {
												jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();
											}
										}
										
										snapshot_restore_tables_proc('table', table_count);
									}
									
								} else {
									jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
									jQuery( "#snapshot-ajax-error" ).show();
								}
								
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot restore attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();
							}
				        }
				    });
				}
			} else if (action == "file") {

				var file_idx 	= parseInt(idx);						
				var file_count 	= file_idx+1;
				var table_name = action;
				
				/* If we reached the end of the tables send the finish. */
				if (file_count > filesArray.length) {

					if (filesArray.length > 0) {
						snapshot_restore_tables_proc('finish', 0);
					}
					return;
					
				} else {

					var file_name = filesArray[file_idx];
					file_name = basename(file_name);					
					jQuery( '#snapshot-item-'+table_name+' .snapshot-filename' ).html(": "+file_name);

					jQuery( '#snapshot-progress-bar-container #snapshot-item-file button.snapshot-button-abort' ).show();
					jQuery( '#snapshot-progress-bar-container #snapshot-item-file button.snapshot-button-abort' ).click(function() { 
						snapshot_button_abort_proc();
					});

					var data = {
						'action': 'snapshot_restore_ajax',
						'snapshot_action': action,
						'item_key': snapshot_item_key,
						'item_data': snapshot_item_data,
						'file_data_idx': file_idx,
						'snapshot-blog-id': snapshot_form_blog_id,						
						
					};
				
					snapshot_ajax_hdl_xhr = jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
						timeout: 600000,
						dataType: 'json',
						error: function(jqXHR, textStatus, errorThrown) {
							if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
								jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
								jQuery( "#snapshot-ajax-error" ).show();
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();																					
							}
						},
				        success: function(reply_data) {

							jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .snapshot-button-abort' ).hide();

							if (reply_data['errorStatus'] != undefined) {
								
								if (reply_data['errorStatus'] == false) {

									if (snapshot_ajax_user_aborted == false) {
										

										if (reply_data['file_data'] != undefined) {

											if (file_count < filesArray.length) {

												var snapshot_percent = Math.ceil((file_count/filesArray.length)*100);

												jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .bar' ).width(snapshot_percent+'%');
												jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .percent' ).html(snapshot_percent+'% (files '+file_count+'/'+filesArray.length+')');

											} else {

												var snapshot_percent = 100;
												jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .bar' ).width(snapshot_percent+'%');
												jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .progress .percent' ).html(snapshot_percent+'% (rows '+file_count+'/'+filesArray.length+')');
												
											}
											
											// Are we at 100%? Hide the Abort button
											if (snapshot_percent == 100) {
												jQuery( '#snapshot-progress-bar-container #snapshot-item-'+table_name+' .snapshot-button-abort' ).hide();
												jQuery( '#snapshot-item-'+table_name+' .snapshot-filename' ).html("");
											}
										}
										//snapshot_backup_tables_proc('file', file_count);
										snapshot_restore_tables_proc('file', file_count);
									}
									
								} else {
									jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
									jQuery( "#snapshot-ajax-error" ).show();
								}
								
							} else {
								jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot restore attempt. Aborting. Double check Snapshot settings.</p>');
								jQuery( "#snapshot-ajax-error" ).show();
							}
				        }
				    });
				}

				
			} else if (action == "finish") {

				var table_name = action;
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('0%');
				jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('0%');

				var data = {
					'action': 'snapshot_restore_ajax',
					'snapshot_action': action,
					'item_key': snapshot_item_key,
					'item_data': snapshot_item_data,
					'snapshot-blog-id': snapshot_form_blog_id,						
					'snapshot_restore_plugin': snapshot_restore_plugin,
					'snapshot_restore_theme': snapshot_restore_theme					
				};
				
				snapshot_ajax_hdl_xhr = jQuery.ajax({
				  	type: 'POST',
				  	url: ajaxurl,
			        data: data,
					dataType: 'json',
					error: function(jqXHR, textStatus, errorThrown) {
						if ((jqXHR['responseText'] != false) && (jqXHR['responseText'] != "")) {
							jQuery( "#snapshot-ajax-error" ).html(jqXHR['responseText']);
							jQuery( "#snapshot-ajax-error" ).show();
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot backup attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();																					
						}
					},
			        success: function(reply_data) {
						if (reply_data['errorStatus'] != undefined) {
							
							if (reply_data['errorStatus'] == false) {

								if (snapshot_ajax_user_aborted == false) {

									jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .bar' ).width('100%');
									jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+table_name+' .progress .percent' ).html('100%');
						
									/* Write a message to the progress text container shown below the actul bar. Just information what table name and what table count */
									jQuery( "#snapshot-ajax-warning" ).append('<p>'+reply_data['responseText']+'</p>');
									jQuery( "#snapshot-ajax-warning" ).show();
								}
								
							} else {
								jQuery( "#snapshot-ajax-error" ).append(reply_data['errorText']);
								jQuery( "#snapshot-ajax-error" ).show();
							}
							
						} else {
							jQuery( "#snapshot-ajax-error" ).html('<p>An unknown response returned from Snapshot restore attempt. Aborting. Double check Snapshot settings.</p>');
							jQuery( "#snapshot-ajax-error" ).show();
						}
			        }
			    });
			}			
		}
		snapshot_restore_tables_proc('init', 0);
		
		return false;
	});


	/* Credit: http://www.erichynds.com/javascript/a-recursive-settimeout-pattern/ */
/*	var snapshotStatusPoller = {

		// number of failed requests
		failed: 0,

		// starting interval - 5 seconds
		interval: 2000,

		timeoutProc: null,
		statusFile: null,
		statusTable: null,

		// kicks off the setTimeout
		init: function(){
			setTimeout(
				$.proxy(this.getStatus, this), // ensures 'this' is the poller obj inside getData, not the window object
				this.interval
			);
		},

		stop: function() {
			clearTimeout(this.timeoutProc);
		},

		// get AJAX data + respond to it
		getStatus: function(){
			var self = this;

			var data = {
				action: 'snapshot_status_ajax',
				statusFile: this.statusFile
			};
			
			jQuery.ajax({
			  	type: 'POST',
			  	url: ajaxurl,
		        data: data,
				cache: false,
				dataType: 'json',
				success: function( reply_data ) {
					if (reply_data != undefined) {
						if (reply_data['errorStatus'] != undefined) {
						
							if (reply_data['errorStatus'] == false) {
														
								if (reply_data['table'] != undefined) {

									//jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+reply_data['table']+' .progress .bar' ).width('100%');
									var snapshot_percent = Math.ceil((reply_data['rows']/reply_data['total_rows'])*100);

									jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+reply_data['table']+' .progress .percent' ).html(snapshot_percent+'%');
									jQuery( '#snapshot-progress-bar-container #snapshot-item-table-'+reply_data['table']+' .snapshot-text' ).html(reply_data['table']+' ('+reply_data['rows']+'/'+reply_data['total_rows']+')');


								}
								self.init();
							
							} else {
								self.errorHandler();
							}
						}
					} else {
						self.init();
					}
				},

				// 'this' inside the handler won't be this poller object
				// unless we proxy it.  you could also set the 'context'
				// property of $.ajax.
				error: $.proxy(self.errorHandler, self)
			});
		},

		// handle errors
		errorHandler: function(){
			if( ++this.failed < 10 ){

				// give the server some breathing room by
				// increasing the interval
				this.interval += 1000;

				// recurse
				this.init();
			}
		}
	};
*/	
	
function basename(path) {
	if ((path != undefined) && (path.length)) {
		return path.replace(/\\/g,'/').replace( /.*\//, '' );
	} else {
		return path;
	}
}

function dirname(path) {
	return path.replace(/\\/g,'/').replace(/\/[^\/]*$/, '');;
}
});
