(function( $, _, as3cfFindAndReplaceMedia ) {

	// Local reference to the WordPress media namespace.
	var media = wp.media;

	// Store ids of attachments selected for bulk grid actions
	var selection_ids = [];

	/**
	 * A button for S3 actions
	 *
	 * @constructor
	 * @augments wp.media.view.Button
	 * @augments wp.media.View
	 * @augments wp.Backbone.View
	 * @augments Backbone.View
	 */
	media.view.s3Button = media.view.Button.extend( {
		defaults: {
			text: '',
			style: 'primary',
			size: 'large',
			disabled: false
		},
		initialize: function( options ) {
			if ( options ) {
				this.options = options;
				this.defaults.text = as3cfpro_media.strings[ this.options.action ];
			}

			this.options = _.extend( {}, this.defaults, this.options );

			media.view.Button.prototype.initialize.apply( this, arguments );

			this.listenTo( this.controller, 'selection:toggle', this.toggleDisabled );

			_.bindAll( this, 'findAndReplaceResult' );
			$( 'body' ).off( 'as3cf-find-and-replace' ).on( 'as3cf-find-and-replace', '.as3cf-find-replace-container', this.findAndReplaceResult );
		},

		toggleDisabled: function() {
			this.model.set( 'disabled', !this.controller.state().get( 'selection' ).length );
		},

		click: function( e ) {
			e.preventDefault();
			if ( this.$el.hasClass( 'disabled' ) ) {
				return;
			}

			var selection = this.controller.state().get( 'selection' );

			if ( !selection.length ) {
				return;
			}

			var askConfirm = false;

			var that = this;
			var ids = [];
			selection.each( function( model ) {
				ids.push( model.id );

				if ( !askConfirm && that.options.confirm ) {
					if ( model.attributes[ that.options.confirm ] ) {
						askConfirm = true;
					}
				}
			} );

			// Add ids to the unique array
			selection_ids = _.union( selection_ids, ids );

			if ( this.options.confirm && askConfirm ) {
				if ( !confirm( as3cfpro_media.strings[ this.options.confirm ] ) ) {
					return;
				}
			}

			var nonce = as3cfpro_media.nonces[ this.options.action + '_media' ];

			var payload = {
				_nonce   : nonce,
				s3_action: this.options.action,
				ids      : ids
			};

			if ( 'download' === this.options.action ) {
				// Don't find and replace URLs for copying to server from S3
				this.startS3Action();
				this.fireS3Action( payload );

				return;
			}

			if ( ids.length > 1 ) {
				as3cfFindAndReplaceMedia.setBulk( true );
			}

			as3cfFindAndReplaceMedia.open( null, payload );
		},

		startS3Action: function() {
			$( '.media-toolbar .spinner' ).css( 'visibility', 'visible' ).show();
			$( '.media-toolbar-secondary .button' ).addClass( 'disabled' );
		},

		findAndReplaceResult: function( event, findAndReplace, payload ) {
			this.startS3Action();
			as3cfFindAndReplaceMedia.startLoading();

			payload.find_and_replace = findAndReplace;

			this.fireS3Action( payload );
		},

		fireS3Action: function ( payload ) {
			wp.ajax.send( 'as3cfpro_process_media_action', { data: payload } ).done( _.bind( this.returnS3Action, this ) );
		},

		returnS3Action: function( response ) {
			if ( response && '' !== response ) {
				$( '.as3cf-notice' ).remove();
				$( '#wp-media-grid h2' ).after( response );
			}

			this.controller.trigger( 'selection:action:done' );
			$( '.media-toolbar .spinner' ).hide();
			$( '.media-toolbar-secondary .button' ).removeClass( 'disabled' );
			as3cfFindAndReplaceMedia.stopLoading();
			as3cfFindAndReplaceMedia.close();
		},

		render: function() {
			media.view.Button.prototype.render.apply( this, arguments );
			if ( this.controller.isModeActive( 'select' ) ) {
				this.$el.addClass( 's3-actions-selected-button' );
			} else {
				this.$el.addClass( 's3-actions-selected-button hidden' );
			}
			this.toggleDisabled();
			return this;
		}
	} );

	/**
	 * Show and hide the S3 buttons for the grid view only
	 */
	// Local instance of the SelectModeToggleButton to extend
	var wpSelectModeToggleButton = media.view.SelectModeToggleButton;

	/**
	 * Extend the SelectModeToggleButton functionality to show and hide
	 * the S3 buttons when the Bulk Select button is clicked
	 */
	media.view.SelectModeToggleButton = wpSelectModeToggleButton.extend( {
		toggleBulkEditHandler: function() {
			wpSelectModeToggleButton.prototype.toggleBulkEditHandler.call( this, arguments );
			var toolbar = this.controller.content.get().toolbar;

			if ( this.controller.isModeActive( 'select' ) ) {
				toolbar.$( '.s3-actions-selected-button' ).removeClass( 'hidden' );
			} else {
				toolbar.$( '.s3-actions-selected-button' ).addClass( 'hidden' );
			}
		}
	} );

	// Local instance of the AttachmentsBrowser
	var wpAttachmentsBrowser = media.view.AttachmentsBrowser;

	/**
	 * Extend the Attachments browser toolbar to add the S3 buttons
	 */
	media.view.AttachmentsBrowser = wpAttachmentsBrowser.extend( {
		createToolbar: function() {
			wpAttachmentsBrowser.prototype.createToolbar.call( this );

			this.toolbar.set( 'copyS3SelectedButton', new media.view.s3Button( {
				action: 'copy',
				controller: this.controller,
				priority: -60
			} ).render() );

			this.toolbar.set( 'removeS3SelectedButton', new media.view.s3Button( {
				action: 'remove',
				controller: this.controller,
				priority: -60,
				confirm: 'bulk_local_warning'
			} ).render() );

			this.toolbar.set( 'downloadS3SelectedButton', new media.view.s3Button( {
				action: 'download',
				controller: this.controller,
				priority: -60
			} ).render() );

		}
	} );

	// Local instance of the Attachment Details TwoColumn used in the edit attachment modal view
	var wpAttachmentDetailsTwoColumn = media.view.Attachment.Details.TwoColumn;

	media.view.Attachment.Details.TwoColumn = wpAttachmentDetailsTwoColumn.extend( {
		events: function() {
			return _.extend( {}, wpAttachmentDetailsTwoColumn.prototype.events, {
				'click .local-warning'      : 'confirmS3Removal',
				'click .s3-actions a.copy'  : 'confirmFindAndReplace',
				'click .s3-actions a.remove': 'confirmFindAndReplace'
			} );
		},

		initialize: function() {
			// clear up any previous notices
			$( '.as3cf-notice' ).remove();

			var id = this.model.get( 'id' );

			// If the attachment has been previously selected in a bulk action
			if ( _.contains( selection_ids, id ) ) {
				// Get the attachment model
				var attachment = media.model.Attachment.get( id );
				var old_url = attachment.attributes.url;
				var that = this;
				$.when(
					// Refresh attachment after S3 actions
					attachment.fetch()
				).then( function() {
						var new_url = attachment.attributes.url;
						// Check S3 action has been processed
						if ( old_url !== new_url ) {
							// Remove attachment from selection
							selection_ids = _.without( selection_ids, id );
						}

						// Display attachment view
						wpAttachmentDetailsTwoColumn.prototype.initialize.apply( that, arguments );
						// Update the URL just in case it hasn't been updated
						$( '.attachment-info .settings' ).children( 'label[data-setting="url"]' ).children( 'input' ).val( new_url );

						return;
					} );
			}

			wpAttachmentDetailsTwoColumn.prototype.initialize.apply( this, arguments );
		},

		render: function() {

			// retrieve the S3 details for the attachment
			// before we render the view
			this.fetchS3Details( this.model.get( 'id' ) );
		},

		fetchS3Details: function( id ) {
			wp.ajax.send( 'as3cfpro_get_attachment_s3_details', {
				data: {
					_nonce: as3cfpro_media.nonces.get_attachment_s3_details,
					id: id
				}
			} ).done( _.bind( this.renderView, this ) );
		},

		renderView: function( response ) {
			// render parent media.view.Attachment.Details
			wpAttachmentDetailsTwoColumn.prototype.render.apply( this );

			this.renderActionLinks( response );
			this.renderS3Details( response );
		},

		renderActionLinks: function( response ) {
			var links = ( response && response.links ) || [];
			var $actionsHtml = this.$el.find( '.actions' );
			var $s3Actions = $( '<div />', {
				'class': 's3-actions'
			} );

			var s3Links = [];
			_( links ).each( function( link ) {
				s3Links.push( link );
			} );

			$s3Actions.append( s3Links.join( ' | ' ) );
			$actionsHtml.append( $s3Actions );
		},

		confirmS3Removal: function( event ) {
			if ( !confirm( as3cfpro_media.strings.local_warning ) ) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return false;
			}
		},

		confirmFindAndReplace: function( event ) {
			event.preventDefault();
			as3cfFindAndReplaceMedia.open( $( event.target ).attr( 'href' ) );
		},

		renderS3Details: function( response ) {
			if ( !response || !response.s3object ) {
				return;
			}
			var $detailsHtml = this.$el.find( '.attachment-info .details' );
			var html = this.generateDetails( response.s3object, [ 'bucket', 'key', 'region', 'acl' ] );
			$detailsHtml.append( html );
		},

		generateDetails: function( s3object, keys ) {
			var html = '';

			_( keys ).each( function( key ) {
				if ( s3object[ key ] ) {
					html += '<div class="' + key + '"><strong>' + as3cfpro_media.strings[ key ] + ':</strong> ' + s3object[ key ] + '</div>';
				}
			} );

			return html;
		}
	} );

	$( document ).ready( function() {
		/**
		 * Add bulk action to the select
		 *
		 * @param string action
		 */
		function addBulkAction( action ) {
			var bulkAction = '<option value="bulk_as3cfpro_' + action + '">' + as3cfpro_media.strings[ action ] + '</option>';

			$( 'select[name^="action"] option:last-child' ).after( bulkAction );
		}

		/**
		 * Add new items to the Bulk Actions using Javascript.
		 *
		 * A last minute change to the "bulk_actions-xxxxx" filter in 3.1 made it not
		 * possible to add items using that filter.
		 */
		function addMediaBulkActions() {
			addBulkAction( 'copy' );
			addBulkAction( 'remove' );
			addBulkAction( 'download' );
		}

		// Load up the bulk actions
		addMediaBulkActions();

		// Ask for confirmation when trying to remove attachment from S3 when the local file is missing
		$( 'body' ).on( 'click', '.as3cfpro_remove a.local-warning', function( event ) {
			if ( confirm( as3cfpro_media.strings.local_warning ) ) {
				return true;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			return false;
		} );

		// Ask for confirmation on bulk action removal from S3
		$( 'body' ).on( 'click', '.bulkactions #doaction', function( e ) {

			var action = $( "#bulk-action-selector-top" ).val();
			if ( 'bulk_as3cfpro_remove' !== action ) {
				// No need to do anything when not removing from S3
				return true;
			}

			var continueRemoval = false;
			var mediaChecked = 0;

			// Show warning if we have selected attachments to remove that have missing local files
			$( 'input:checkbox[name="media[]"]:checked' ).each( function() {
				var $titleTh = $( this ).parent().siblings( '.column-title' );

				if ( $titleTh.find( '.row-actions span.as3cfpro_remove a' ).hasClass( 'local-warning' ) ) {
					mediaChecked++;

					if ( confirm( as3cfpro_media.strings.bulk_local_warning ) ) {
						continueRemoval = true;
					}

					// Break out of loop early
					return continueRemoval;
				}
			} );

			if ( mediaChecked > 0 ) {
				// If media selected that have local files missing, return the outcome of the confirmation
				return continueRemoval;
			}

			// No media selected continue form submit
			return true;
		} );

		// Setup find and replace modal for list mode bulk actions
		$( '#posts-filter' ).on( 'submit', function( e ) {
			if ( $( '#bulk-action-selector-top,#bulk-action-selector-bottom' ).find( 'option:selected[value^="bulk_as3cfpro_"]' ).length ) {
				if ( 'bulk_as3cfpro_download' === $('#posts-filter select[name="action"]' ).val() ) {
					// Don't find and replace URLs for copying to server from S3
					return;
				}

				e.preventDefault();
				as3cfFindAndReplaceMedia.setBulk( true );
				as3cfFindAndReplaceMedia.open( window.location.origin + window.location.pathname + '?' + $( '#posts-filter' ).serialize() );
			}
		} );

		// Setup find and replace modal for list mode
		$( 'body' ).on( 'click', '.row-actions .as3cfpro_copy a,.row-actions .as3cfpro_remove a', function( e ) {
			e.preventDefault();
			as3cfFindAndReplaceMedia.open( $( this ).attr( 'href' ) );
		} );

	} );

})( jQuery, _, as3cfFindAndReplaceMedia );