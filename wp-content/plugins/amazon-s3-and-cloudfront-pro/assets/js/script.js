(function( $, _, as3cfModal ) {
	var uploadComplete;
	var uploadCompleteEvents;
	var executeNextStep;
	var nonFatalErrors = false;
	var uploadError = false;
	var nextStepInUpload;
	var currentlyUploading = false;
	var uploadCompleted = false;
	var doingAjax = false;
	var uploadPaused = false;
	var uploadCancelled = false;
	var elapsedInterval;
	var timerCount;
	var adminUrl = ajaxurl.replace( '/admin-ajax.php', '' );
	var spinnerUrl = adminUrl + '/images/spinner';
	var $counterDisplay;
	var progressPercent = 0;
	var progressBytes = 0;
	var progressTotalBytes = 0;
	var progressCount = 0;
	var progressTotalCount = 0;
	var contentHeight = 0;
	var findAndReplace = true;
	var progressModalActive = false;
	var redirectModalActive = false;
	var doingLicenceRegistrationAjax = false;
	var savedSettings = null;

	if ( window.devicePixelRatio >= 2 ) {
		spinnerUrl += '-2x';
	}

	spinnerUrl += '.gif';

	window.onbeforeunload = function( e ) {
		if ( currentlyUploading ) {
			e = e || window.event;

			// For IE and Firefox prior to version 4
			if ( e ) {
				e.returnValue = as3cfpro.strings.sure;
			}

			// For Safari
			return as3cfpro.strings.sure;
		}
	};

	function displayCount() {
		var hours = Math.floor( timerCount / 3600 ) % 24;
		var minutes = Math.floor( timerCount / 60 ) % 60;
		var seconds = timerCount % 60;
		var display = pad( hours, 2, 0 ) + ':' + pad( minutes, 2, 0 ) + ':' + pad( seconds, 2, 0 );
		$counterDisplay.html( display );
	}

	function setupCounter() {
		timerCount = 0;
		$counterDisplay = $( '.timer' );

		elapsedInterval = setInterval( count, 1000 );
	}

	function pad( n, width, z ) {
		z = z || '0';
		n = n + '';
		return n.length >= width ? n : new Array( width - n.length + 1 ).join( z ) + n;
	}

	function count() {
		timerCount = timerCount + 1;
		displayCount();
	}

	function setPauseResumeButton( event ) {
		if ( true === uploadPaused ) {
			uploadPaused = false;
			doingAjax = true;
			$( '.upload-progress-ajax-spinner' ).show();
			$( '.pause-resume' ).html( as3cfpro.strings.pause );
			// resume the timer
			elapsedInterval = setInterval( count, 1000 );
			executeNextStep();
		}
		else {
			$( this ).off( event ); // Is re-bound at executeNextStep when upload is finally paused
			uploadPaused = true;
			doingAjax = false;
			$( '.pause-resume' ).html( as3cfpro.strings.pausing ).addClass( 'disabled' );
		}

		updateProgressMessage();
	}

	function sizeFormat( bytes ) {
		var thresh = 1024;
		var units = [ 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];

		if ( bytes < thresh ) {
			return bytes + ' B';
		}

		var unitsKey = -1;

		do {
			bytes = bytes / thresh;
			unitsKey++;
		} while ( bytes >= thresh );

		return bytes.toFixed( 1 ) + ' ' + units[ unitsKey ];
	}

	function updateProgress( amount, total, bytesSent, bytesToSend ) {
		progressBytes = bytesSent;
		progressTotalBytes = bytesToSend;
		progressCount = amount;
		progressTotalCount = total;

		// If file size available, use for percentage, else use file count
		if ( bytesToSend > 0 ) {
			progressPercent = Math.round( 100 * bytesSent / bytesToSend );
		}
		else {
			progressPercent = Math.round( 100 * progressCount / progressTotalCount );
		}

		$( '.progress-bar' ).width( progressPercent + '%' );

		updateProgressMessage();
	}

	function updateProgressMessage() {
		var uploadProgress = as3cfpro.strings.files_uploaded.replace( '%1$d', progressCount ).replace( '%2$d', progressTotalCount );
		var progressMessage = progressPercent + '% ' + as3cfpro.strings.complete;
		var bytesMessage = '';

		if ( progressTotalBytes > 0 ) {
			bytesMessage = '(' + sizeFormat( progressBytes ) + ' / ' + sizeFormat( progressTotalBytes ) + ')';
		}

		$( '.upload-progress' ).html( uploadProgress );

		if ( false === uploadPaused ) {
			$( '.progress-text' ).html( progressMessage + ' ' + bytesMessage );
		}
		else {
			$( '.progress-text' ).html( as3cfpro.strings.completing_current_request );
		}
	}

	/**
	 * Check if the URL settings have changed since page load
	 *
	 * @param {object} form
	 *
	 * @returns {boolean}
	 */
	function urlSettingsChanged( form ) {
		var formInputs = formInputsToObject( form );
		var settingsChanged = false;
		var whitelist = as3cfpro.settings.previous_url_whitelist;

		$.each( formInputs, function( name, value ) {
			// Only compare URL settings
			if ( -1 === $.inArray( name, whitelist ) ) {
				return;
			}

			// Compare values with originals
			if ( value !== savedSettings[ name ] ) {
				settingsChanged = true;
				return false;
			}
		} );

		return settingsChanged;
	}

	/**
	 * Convert form inputs to single level object
	 *
	 * @param {object} form
	 *
	 * @returns {object}
	 */
	function formInputsToObject( form ) {
		var formInputs = $( form ).serializeArray();
		var inputsObject = {};

		$.each( formInputs, function( index, input ) {
			inputsObject[ input.name ] = input.value;
		} );

		return inputsObject;
	}

	/**
	 * Extend the tabs toggle function to check the license
	 * if the support tab is clicked
	 *
	 * @type {as3cf.tabs.toggle}
	 */
	var orginalToggle = as3cf.tabs.toggle;
	as3cf.tabs.toggle = function( hash ) {
		orginalToggle( hash );
		if ( 'support' === hash ) {
			if ( '1' === as3cfpro.strings.has_licence ) {
				checkLicence();
			}
		} else {
			editcheckLicenseURL( hash );
		}
	};

	/**
	 * Extend the buckets set method to refresh the media upload notice
	 *
	 * @type {as3cf.buckets.set}
	 */
	var originalBucketSet = as3cf.buckets.set;
	as3cf.buckets.set = function( bucket, region, canWrite ) {
		// Store the active bucket before the selection has been made
		var activeBucket = $( '#' + as3cfModal.prefix + '-active-bucket' ).text();

		// Run the parent set bucket method
		originalBucketSet( bucket, region, canWrite );

		if ( 'as3cf' === as3cfModal.prefix && '' === activeBucket ) {
			// If we are setting the bucket for the first time,
			// trigger the refresh of the media to upload notice on the settings tab
			updateUploadNotices();
		}
	};

	/**
	 * Refresh the media to upload notice
	 */
	function updateUploadNotices() {
		$.ajax( {
			url     : ajaxurl,
			type    : 'POST',
			dataType: 'json',
			cache   : false,
			data    : {
				action: 'as3cfpro_update_upload_notices',
				nonce : as3cfpro.nonces.update_upload_notices
			},
			success : function( response ) {
				if ( true === response.success && 'undefined' !== typeof response.data ) {
					$( '.as3cf-pro-media-notice' ).remove();
					$( '#tab-' + as3cf.tabs.defaultTab ).prepend( response.data );
				}
			}
		} );
	}

	/**
	 * Edit the hash of the check license URL so we reload to the correct tab
	 *
	 * @param hash
	 */
	function editcheckLicenseURL( hash ) {
		if ( 'support' !== hash && $( '.as3cf-pro-check-again' ).length ) {
			var checkLicenseURL = $( '.as3cf-pro-check-again' ).attr( 'href' );

			if ( as3cf.tabs.defaultTab === hash ) {
				hash = '';
			}

			if ( '' !== hash ) {
				hash = '#' + hash;
			}

			var index = checkLicenseURL.indexOf('#');
			if ( 0 === index ) {
				index = checkLicenseURL.length;
			}

			checkLicenseURL = checkLicenseURL.substr( 0, index ) + hash;

			$( '.as3cf-pro-check-again' ).attr( 'href', checkLicenseURL );
		}
	}

	/**
	 * Check the license and return license info from deliciousbrains.com
	 *
	 * @param string licence
	 */
	function checkLicence( licence ) {
		if ( $( '.support-content' ).hasClass( 'checking-licence' ) ) {
			return;
		}

		$( '.support-content' ).addClass( 'checking-licence' );
		$( '.support-content p:first' ).append( '<img src="' + spinnerUrl + '" alt="" class="check-license-ajax-spinner general-spinner" />' );
		$( '.as3cf-pro-license-notice' ).remove();

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			cache: false,
			data: {
				action: 'as3cfpro_check_licence',
				licence: licence,
				nonce: as3cfpro.nonces.check_licence
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				alert( as3cfpro.strings.license_check_problem );
				$( '.support-content' ).removeClass( 'checking-licence' );
			},
			success: function( data ) {
				if ( 'undefined' !== typeof data.dbrains_api_down ) {
					$( '.support-content' ).empty().html( data.dbrains_api_down + data.message );
				}
				else if ( 'undefined' !== typeof data.errors ) {
					var msg = '';

					for ( var key in data.errors ) {
						msg += data.errors[ key ];
					}

					$( '.support-content' ).empty().html( msg );
				}
				else {
					$( '.support-content' ).empty().html( data.message );
				}

				if ( 'undefined' !== typeof data.pro_error && 0 === $( '.as3cf-pro-license-notice' ).length ) {
					$( 'h2.nav-tab-wrapper' ).after( data.pro_error );
				}

				$( '.support-content' ).removeClass( 'checking-licence' );
			}
		} );
	}

	$( document ).ready( function() {
		var hash = '';
		if ( window.location.hash ) {
			hash = window.location.hash.substring( 1 );
		}
		editcheckLicenseURL( hash );

		var $settingsForm = $( '#tab-' + as3cf.tabs.defaultTab + ' .as3cf-main-settings form' );
		var $redirectContent;
		var $progressContent;

		savedSettings = formInputsToObject( $settingsForm );

		var progressContentOriginal = $( '.progress-content' ).clone();
		var redirectContentOriginal = $( '.redirect-content' ).clone();

		$( '.progress-content' ).remove();
		$( '.redirect-content' ).remove();

		// Find and replace on settings change
		$( 'body' ).on( 'click', '#tab-' + as3cf.tabs.defaultTab + ' .as3cf-main-settings button[type="submit"]', function( e ) {
			if ( urlSettingsChanged( $settingsForm ) && $( '.as3cf-find-replace-container' ).length) {
				e.preventDefault();

				as3cfFindAndReplaceSettings.open( $settingsForm );
			}
		} );

		// Existing content modal
		$( 'body' ).on( 'click', 'a.as3cf-pro-upload', function( e ) {
			$redirectContent = redirectContentOriginal.clone();
			e.preventDefault();

			var docHeight = $( document ).height();

			$( 'body' ).append( '<div id="overlay"></div>' );

			$( '#overlay' )
				.height( docHeight )
				.css( {
					'position': 'fixed',
					'top': 0,
					'left': 0,
					'width': '100%',
					'z-index': 99999,
					'display': 'none'
				} );

			$( '#overlay' ).after( $redirectContent );
			$( '#overlay' ).show();

			// Display warning when nothing option selected
			$( '.redirect-options' ).on( 'change', 'input[name="existing-links"]', function( e ) {
				if ( 'nothing' === $( this ).val() && '1' === as3cfpro.settings.remove_local_file ) {
					$( '.redirect-options .nothing' ).next( '.notice-warning' ).addClass( 'show' );
				}
				else {
					$( '.redirect-options .nothing' ).next( '.notice-warning' ).removeClass( 'show' );
				}
			} );

			contentHeight = $redirectContent.outerHeight();
			$redirectContent.css( 'top', '-' + contentHeight + 'px' ).show().animate( { 'top': '0px' } );
			redirectModalActive = true;
		} );

		// Upload modal
		$( 'body' ).on( 'click', '.as3cf-start-upload', function( e ) {
			$progressContent = progressContentOriginal.clone();
			e.preventDefault();

			findAndReplace = ( 'replace' === $( 'input[name="existing-links"]:checked' ).val() ) ? true : false;

			// Hide redirect modal and show progress modal
			$redirectContent.animate( { 'top': '-' + contentHeight + 'px' }, 400, 'swing', function() {
				$( this ).remove();
				redirectModalActive = false;

				$( '#overlay' ).after( $progressContent );

				contentHeight = $progressContent.outerHeight();
				$progressContent.css( 'top', '-' + contentHeight + 'px' ).show().animate( { 'top': '0px' } );
				progressModalActive = true;

				$( '.upload-controls .cancel' ).after( '<img src="' + spinnerUrl + '" alt="" class="upload-progress-ajax-spinner general-spinner" />' );

				setupCounter();
			} );

			currentlyUploading = true;
			doingAjax = true;

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'as3cfpro_initiate_upload',
					nonce: as3cfpro.nonces.initiate_upload
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					ajaxError( jqXHR, textStatus, errorThrown );

					return;
				},
				success: function( data ) {
					if ( as3cfproError( data ) ) {
						return;
					}

					var progress = {
						bytes: 0,
						files: 0,
						total_bytes: 0,
						total_files: 0,
					};

					nextStepInUpload = { fn: calculateAttachmentsRecursive, args: [ data, progress ] };
					executeNextStep();
				}
			} );
		} );

		/**
		 * Recursively calculate total attachments
		 *
		 * @param {object} blogs
		 * @param {object} progress
		 */
		function calculateAttachmentsRecursive( blogs, progress ) {
			// All blogs processed
			if ( _.isEmpty( blogs ) ) {
				updateProgress( progress.files, progress.total_files, progress.bytes, progress.total_bytes );

				nextStepInUpload = { fn: uploadAttachmentRecursive, args: [ progress ] };
				executeNextStep();
				return;
			}

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'as3cfpro_calculate_attachments',
					blogs: blogs,
					progress: progress,
					nonce: as3cfpro.nonces.calculate_attachments,
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					ajaxError( jqXHR, textStatus, errorThrown );

					return;
				},
				success: function( data ) {
					if ( as3cfproError( data ) ) {
						return;
					}

					nextStepInUpload = { fn: calculateAttachmentsRecursive, args: [ data.blogs, data.progress ] };
					executeNextStep();
				}

			} );
		}

		function uploadAttachmentRecursive( progress ) {
			if ( progress.files >= progress.total_files ) {
				// finalise upload
				nextStepInUpload = { fn: uploadComplete, args: [ progress ] };
				executeNextStep();
				return;
			}

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'as3cfpro_upload_attachments',
					find_and_replace: findAndReplace,
					progress: progress,
					nonce: as3cfpro.nonces.upload_attachments
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					ajaxError( jqXHR, textStatus, errorThrown );

					return;
				},
				success: function( data ) {
					if ( as3cfproError( data ) ) {
						return;
					}

					if ( 'undefined' !== typeof data.errors ) {
						var $errorCount = $( '.progress-errors-title .error-count' );
						var count = parseInt( $errorCount.html() );
						$.each( data.errors, function( index, value ) {
							count++;
							$( '.progress-errors .progress-errors-detail ol' ).append( '<li>' + value + '</li>' );
							nonFatalErrors = true;
						} );
						if ( count > 0 ) {
							$( '.progress-errors' ).show();
						}
						$errorCount.html( count );
						if ( 1 === count ) {
							$( '.progress-errors-title .error-text' ).html( as3cfpro.strings.error );
						} else {
							$( '.progress-errors-title .error-text' ).html( as3cfpro.strings.errors );
						}
					}

					updateProgress( data.files, data.total_files, data.bytes, data.total_bytes );

					nextStepInUpload = { fn: uploadAttachmentRecursive, args: [ data ] };
					executeNextStep();
				}

			} );
		}

		uploadCompleteEvents = function() {
			if ( false === uploadError ) {
				if ( true === nonFatalErrors ) {
					$( '.progress-text' ).addClass( 'upload-error' );
					var message = as3cfpro.strings.completed_with_some_errors;
					if ( true === uploadCancelled ) {
						message = as3cfpro.strings.partial_complete_with_some_errors;
					}
					$( '.progress-text' ).html( message );
				}
			}

			// reset upload variables so consecutive uploads work correctly
			uploadError = false;
			currentlyUploading = false;
			uploadCompleted = true;
			uploadPaused = false;
			uploadCancelled = false;
			doingAjax = false;
			nonFatalErrors = false;

			$( '.progress-label' ).remove();
			$( '.upload-progress-ajax-spinner' ).remove();
			$( '.close-progress-content' ).show();
			$( '#overlay' ).css( 'cursor', 'pointer' );
			clearInterval( elapsedInterval );

			$.ajax( {
				url     : ajaxurl,
				type    : 'POST',
				dataType: 'json',
				cache   : false,
				data    : {
					action: 'as3cfpro_finish_upload',
					nonce : as3cfpro.nonces.finish_upload
				},
				success: function() {
					// Refresh upload notices on settings page behind modal
					updateUploadNotices();
				}
			} );
		};

		uploadComplete = function() {
			$( '.upload-controls' ).fadeOut();

			currentlyUploading = false;

			if ( false === uploadError ) {
				$( '.progress-text' ).append( '<div class="dashicons dashicons-yes"></div>' );
				uploadCompleteEvents();
			}
		};

		executeNextStep = function() {
			if ( true === uploadPaused ) {
				$( '.upload-progress-ajax-spinner' ).hide();
				// pause the timer
				clearInterval( elapsedInterval );
				$( '.progress-text' ).html( as3cfpro.strings.paused );
				// Re-bind Pause/Resume button to Resume when we are finally Paused
				$( 'body' ).on( 'click', '.pause-resume', function( event ) {
					setPauseResumeButton( event );
				} );
				$( '.pause-resume' ).html( as3cfpro.strings.resume ).removeClass( 'disabled' );

				return;
			}
			else if ( true === uploadCancelled ) {
				$( '.progress-text' ).html( as3cfpro.strings.upload_cancelled );
				uploadCompleteEvents();
			}
			else {
				nextStepInUpload.fn.apply( null, nextStepInUpload.args );
			}
		};

		function ajaxError( jqXHR, textStatus, errorThrown ) {
			$( '.progress-title' ).html( as3cfpro.strings.upload_failed );
			$( '.progress-text' ).not( '.media' ).html( jqXHR.responseText );
			$( '.progress-text' ).not( '.media' ).addClass( 'upload-error' );
			console.log( jqXHR );
			console.log( textStatus );
			console.log( errorThrown );
			uploadError = true;
			uploadCompleteEvents();
			doingAjax = false;
		}

		/**
		 * Perform common actions on error and display message
		 *
		 * @param {string} error
		 */
		function returnError( error ) {
			uploadError = true;
			uploadCompleteEvents();
			$( '.progress-title' ).html( as3cfpro.strings.upload_failed );
			$( '.progress-text' ).addClass( 'upload-error' );
			$( '.progress-text' ).html( error );
			$( '.upload-controls' ).fadeOut();
			doingAjax = false;
		}

		/**
		 * Check for certain errors from our init method
		 *
		 * @param {object} data
		 *
		 * @returns {boolean}
		 */
		function as3cfproError( data ) {
			if ( 'undefined' !== typeof data.as3cfpro_error && 1 === data.as3cfpro_error ) {
				returnError( data.body );

				return true;
			}

			if ( 'undefined' !== typeof data.success && false === data.success ) {
				returnError( data.data );

				return true;
			}

			return false;
		}

		$( 'body' ).on( 'click', '.pause-resume', function( event ) {
			setPauseResumeButton( event );
		} );

		$( 'body' ).on( 'click', '.cancel', function() {
			uploadCancelled = true;
			uploadPaused = false;
			$( '.upload-controls' ).fadeOut();
			$( '.upload-progress-ajax-spinner' ).show();
			$( '.progress-text' ).html( as3cfpro.strings.completing_current_request );

			if ( false === doingAjax ) {
				executeNextStep();
			}
		} );

		// close progress pop up once upload is completed
		$( 'body' ).on( 'click', '.close-progress-content-button, .close-redirect-content-button', function( e ) {
			hideOverlay();
		} );

		$( 'body' ).on( 'click', '#overlay', function() {
			if ( true === redirectModalActive || ( true === progressModalActive && true === uploadCompleted ) ) {
				hideOverlay();
			}
		} );

		$( 'body' ).on( 'click', '.toggle-progress-errors', function( e ) {
			e.preventDefault();
			var $toggle = $( this );
			var $details = $toggle.closest( '.progress-errors-title' ).siblings( '.progress-errors-detail' );

			$details.toggle( 0, function() {
				$toggle.html( $( this ).is( ':visible' ) ? as3cfpro.strings.hide : as3cfpro.strings.show );
			} );
			return false;
		} );

		$( 'body' ).on( 'click', 'a.as3cf-pro-notice', function( e ) {
			e.preventDefault();
			var $notice = $( this );
			$notice.closest( '.as3cf-notice' ).hide();
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'as3cfpro_dismiss_notice',
					nonce: as3cfpro.nonces.dismiss_notice,
					notice: $notice.data( 'notice' )
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					$notice.closest( '.as3cf-notice' ).show();
				},
				success: function( data ) {
					if ( false === data.success ) {
						$notice.closest( '.as3cf-notice' ).show();
					}
				}
			} );
		} );

		function hideOverlay() {
			$( '.modal-content' ).animate( { 'top': '-' + contentHeight + 'px' }, 400, 'swing', function() {
				$( '#overlay' ).remove();
				$( '.modal-content' ).remove();
			} );
			uploadCompleted = false;
			progressModalActive = false;
			redirectModalActive = false;
		}

		/**
		 * Navigate to the support tab when the activate license link is clicked
		 */
		$( '.enter-licence' ).click( function( e ) {
			e.preventDefault();
			as3cf.tabs.toggle( 'support' );
			window.location.hash = 'support';
			$( '.licence-input' ).focus();
		} );

		/**
		 * Finish license registration
		 *
		 * @param object data
		 * @param string licenceKey
		 */
		function enableProLicence( data, licenceKey ) {
			$( '.licence-input, .register-licence' ).remove();
			$( '.licence-not-entered' ).prepend( data.masked_licence );
			$( '.support-content' ).empty().html( '<p>' + as3cfpro.strings.fetching_license + '</p>' );
			// Trigger the refresh of the media to upload notice on the settings tab
			updateUploadNotices();
			checkLicence( licenceKey );
		}

		$( '.licence-form' ).submit( function( e ) {
			e.preventDefault();

			if ( doingLicenceRegistrationAjax ) {
				return;
			}

			$( '.licence-status' ).removeClass( 'notification-message error-notice success-notice' );

			var licenceKey = $.trim( $( '.licence-input' ).val() );

			if ( '' === licenceKey ) {
				$( '.licence-status' ).addClass( 'notification-message error-notice' );
				$( '.licence-status' ).html( as3cfpro.strings.enter_license_key );
				return;
			}

			$( '.as3cf-pro-license-notice' ).remove();
			$( '.licence-status' ).empty().removeClass( 'success' );
			doingLicenceRegistrationAjax = true;
			$( '.button.register-licence' ).attr( 'disabled', true );
			$( '.button.register-licence' ).after( '<img src="' + spinnerUrl + '" alt="" class="register-licence-ajax-spinner general-spinner" />' );

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'JSON',
				cache: false,
				data: {
					action: 'as3cfpro_activate_licence',
					licence_key: licenceKey,
					nonce: as3cfpro.nonces.activate_licence
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					doingLicenceRegistrationAjax = false;
					$( '.register-licence-ajax-spinner' ).remove();
					$( '.licence-status' ).html( as3cfpro.strings.register_license_problem );
					$( '.button.register-licence' ).attr( 'disabled', false );
				},
				success: function( data ) {
					doingLicenceRegistrationAjax = false;
					$( '.button.register-licence' ).attr( 'disabled', false );
					$( '.register-licence-ajax-spinner' ).remove();

					if ( 'undefined' !== typeof data.errors ) {
						var msg = '';
						for ( var key in data.errors ) {
							msg += data.errors[ key ];
						}

						$( '.licence-status' ).html( msg );

						if ( 'undefined' !== typeof data.masked_licence ) {
							enableProLicence( data, licenceKey );
						}
					}
					else if ( typeof data.wpmdb_error !== 'undefined' && typeof data.body !== 'undefined' ) {
						$( '.licence-status' ).html( data.body );
					}
					else {
						$( '.licence-status' ).html( as3cfpro.strings.license_registered ).delay( 5000 ).fadeOut( 1000 );
						$( '.licence-status' ).addClass( 'success notification-message success-notice' );
						enableProLicence( data, licenceKey );
						$( '.invalid-licence' ).hide();
					}

					if ( 'undefined' !== typeof data.pro_error && 0 === $( '.as3cf-pro-license-notice' ).length ) {
						$( 'h2.nav-tab-wrapper' ).after( data.pro_error );
					}
				}
			} );

		} );

		$( 'body' ).on( 'click', '.reactivate-licence', function( e ) {
			e.preventDefault();

			var $processing = $( '<div/>', { id: 'processing-licence' } ).html( as3cfpro.strings.attempting_to_activate_licence );
			$processing.append( '<img src="' + spinnerUrl + '" alt="" class="check-license-ajax-spinner general-spinner" />' );
			$( '.invalid-licence' ).hide().after( $processing );

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'as3cfpro_reactivate_licence',
					nonce: as3cfpro.nonces.reactivate_licence
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					$processing.remove();
					$( '.invalid-licence' ).show().html( as3cfpro.strings.activate_licence_problem );
					$( '.invalid-licence' ).append( '<br /><br />' + as3cfpro.strings.status + ': ' + jqXHR.status + ' ' + jqXHR.statusText + '<br /><br />' + as3cfpro.strings.response + '<br />' + jqXHR.responseText );
				},
				success: function( data ) {
					$processing.remove();

					if ( 'undefined' !== typeof data.as3cfpro_error && 1 === data.as3cfpro_error ) {
						$( '.invalid-licence' ).html( data.body ).show();
						return;
					}

					if ( 'undefined' !== typeof data.dbrains_api_down && 1 === data.dbrains_api_down ) {
						$( '.invalid-licence' ).html( as3cfpro.strings.temporarily_activated_licence );
						$( '.invalid-licence' ).append( data.body ).show();
						return;
					}

					$( '.invalid-licence' ).empty().html( as3cfpro.strings.licence_reactivated );
					$( '.invalid-licence' ).addClass( 'success notification-message success-notice' ).show();
					location.reload();
				}
			} );

		} );

		// Show support tab when 'support request' link clicked within compatibility notices
		$( 'body' ).on( 'click', '.support-tab-link', function( e ) {
			as3cf.tabs.toggle( 'support' );
		} );

	} );
})( jQuery, _, as3cfModal );