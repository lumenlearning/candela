<?php

class Amazon_S3_And_CloudFront_Pro extends Amazon_S3_And_CloudFront {

	/**
	 * @var array
	 */
	protected $messages;

	/**
	 * @var array
	 */
	protected $previous_url_whitelist = array( 'cloudfront', 'domain', 'ssl' );

	/**
	 * @var AS3CF_Pro_Licences_Updates
	 */
	protected $licence;

	/**
	 * @var AS3CF_Init_Settings_Change
	 */
	protected $init_settings_change_request;

	/**
	 * @var AS3CF_Find_Replace
	 */
	protected $find_replace_process;

	/**
	 * @var AS3CF_Media_Actions
	 */
	protected $media_actions_process;

	/**
	 * @var string
	 */
	protected $legacy_upload_lock_key = 'wpos3_legacy_upload';

	/**
	 * @param string              $plugin_file_path
	 * @param Amazon_Web_Services $aws aws plugin
	 */
	function __construct( $plugin_file_path, $aws ) {
		parent::__construct( $plugin_file_path, $aws, 'amazon-s3-and-cloudfront-pro' );
	}

	/**
	 * Plugin initialization
	 *
	 * @param string $plugin_file_path
	 */
	function init( $plugin_file_path ) {
		// licence and updates handler
		$this->licence = new AS3CF_Pro_Licences_Updates( $this );

		// add our custom CSS classes to <body>
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		// load assets
		add_action( 'aws_admin_menu', array( $this, 'aws_admin_menu' ), 11 );

		load_plugin_textdomain( 'as3cf-pro', false, dirname( plugin_basename( $plugin_file_path ) ) . '/languages/' );

		// Only enable the plugin if compatible,
		// so we don't disable the license and updates functionality when disabled
		if ( self::is_compatible() ) {
			$this->enable_plugin();
		}
	}

	/**
	 * aws_admin_menu event handler.
	 */
	function aws_admin_menu() {
		global $as3cf;
		add_action( 'load-' . $as3cf->hook_suffix, array( $this, 'load_assets' ), 11 );
	}

	/**
	 * Enable the complete plugin when compatible
	 */
	function enable_plugin() {
		add_action( 'as3cf_pre_tab_render', array( $this, 'media_to_upload_notices' ), 100 );
		add_action( 'as3cf_post_settings_render', array( $this, 'upload_modal' ) );
		add_action( 'as3cf_post_settings_render', array( $this, 'upload_redirects_modal' ) );

		add_action( 'load-upload.php', array( $this, 'load_media_assets' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_attachment_assets' ), 11 );

		// Find and replace on settings change
		add_action( 'as3cf_post_settings_render', array( $this, 'post_settings_render' ) );
		add_action( 'as3cf_form_hidden_fields', array( $this, 'settings_form_hidden_fields' ) );
		add_action( 'as3cf_pre_save_settings', array( $this, 'pre_save_settings' ) );

		// Find and replace on media page and attachment page
		add_action( 'admin_footer-upload.php', array( $this, 'find_and_replace_render' ) );
		add_action( 'admin_footer-post.php', array( $this, 'find_and_replace_render' ) );

		// pro customisations
		add_filter( 'as3cf_settings_page_title', array( $this, 'settings_page_title' ) );
		add_filter( 'as3cf_settings_tabs', array( $this, 'settings_tabs' ) );
		add_filter( 'as3cf_lost_files_notice', array( $this, 'lost_files_notice' ) );

		// media row actions
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'enrich_attachment_model' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'add_media_row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'maybe_display_media_action_message' ) );
		add_action( 'admin_init', array( $this, 'process_media_actions' ) );
		// attachment edit
		add_action( 'add_meta_boxes', array( $this, 'attachment_s3_meta_box' ) );

		// ajax handlers
		add_action( 'wp_ajax_as3cfpro_initiate_upload', array( $this, 'ajax_initiate_upload' ) );
		add_action( 'wp_ajax_as3cfpro_calculate_attachments', array( $this, 'ajax_calculate_attachments' ) );
		add_action( 'wp_ajax_as3cfpro_upload_attachments', array( $this, 'ajax_upload_attachments' ) );
		add_action( 'wp_ajax_as3cfpro_finish_upload', array( $this, 'ajax_finish_upload' ) );
		add_action( 'wp_ajax_as3cfpro_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_as3cfpro_update_upload_notices', array( $this, 'ajax_update_upload_notices' ) );
		add_action( 'wp_ajax_as3cfpro_process_media_action', array( $this, 'ajax_process_media_action' ) );
		add_action( 'wp_ajax_as3cfpro_get_attachment_s3_details', array( $this, 'ajax_get_attachment_s3_details' ) );

		// Settings link on the plugins page
		add_filter( 'plugin_action_links', array( $this, 'plugin_actions_settings_link' ), 10, 2 );
		// Diagnostic info
		add_action( 'as3cf_diagnostic_info', array( $this, 'diagnostic_info' ) );

		// include compatibility code for other plugins
		$this->plugin_compat = new AS3CF_Pro_Plugin_Compatibility( $this );

		// Init settings change request
		$this->init_settings_change_request = new AS3CF_Init_Settings_Change( $this );

		// Find and replace background process
		$this->find_replace_process = new AS3CF_Find_Replace( $this );

		// Media actions background process
		$this->media_actions_process = new AS3CF_Media_Actions( $this );
	}

	/**
	 * Is this plugin compatible with its required plugin?
	 *
	 * @return bool
	 */
	public static function is_compatible() {
		global $as3cf_pro_compat_check;

		return $as3cf_pro_compat_check->is_compatible();
	}

	/**
	 * Load the scripts and styles required for the plugin
	 */
	function load_assets() {
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$src = plugins_url( 'assets/css/styles.css', $this->plugin_file_path );
		wp_enqueue_style( 'as3cf-pro-styles', $src, array( 'as3cf-styles' ), $version );

		$src = plugins_url( 'assets/js/find-replace-settings' . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( 'as3cf-pro-find-replace-settings', $src, array( 'jquery', 'as3cf-modal' ), $version, true );

		$src = plugins_url( 'assets/js/script' . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( 'as3cf-pro-script', $src, array( 'jquery', 'underscore', 'as3cf-pro-find-replace-settings' ), $version, true );

		wp_localize_script( 'as3cf-pro-script',
			'as3cfpro',
			array(
				'settings' => array(
					'remove_local_file'      => $this->get_setting( 'remove-local-file' ),
					'previous_url_whitelist' => $this->previous_url_whitelist,
				),
				'strings' => apply_filters( 'as3cfpro_js_strings', array(
					'pause'                             => _x( 'Pause', 'Temporarily stop uploading', 'as3cf-pro' ),
					'complete'                          => _x( 'Complete', 'Upload finished', 'as3cf-pro' ),
					'upload_paused'                     => _x( 'Upload Paused', 'The upload has been temporarily stopped', 'as3cf-pro' ),
					'resume'                            => _x( 'Resume', 'Restart uploading after it was paused', 'as3cf-pro' ),
					'upload_failed'                     => _x( 'Upload failed', 'Copy of data to S3 did not complete', 'as3cf-pro' ),
					'zero_files_uploaded'               => _x( 'Files Uploaded', 'Number of files uploaded to S3', 'as3cf-pro' ),
					'files_uploaded'                    => _x( '%1$d of %2$d Files Uploaded', 'Number of files out of total uploaded to S3', 'as3cf-pro' ),
					'completed_with_some_errors'        => __( 'Upload completed with some errors', 'as3cf-pro' ),
					'partial_complete_with_some_errors' => __( 'Upload partially completed with some errors', 'as3cf-pro' ),
					'cancelling_upload'                 => _x( 'Cancelling upload', 'The upload is being cancelled', 'as3cf-pro' ),
					'completing_current_request'        => __( 'Completing current media upload batch', 'as3cf-pro' ),
					'paused'                            => _x( 'Paused', 'The upload has been temporarily stopped', 'as3cf-pro' ),
					'pausing'                           => _x( 'Pausing&hellip;', 'The upload is being paused', 'as3cf-pro' ),
					'upload_cancellation_failed'        => __( 'Upload cancellation failed', 'as3cf-pro' ),
					'upload_cancelled'                  => _x( 'Upload cancelled', 'The upload has been cancelled', 'as3cf-pro' ),
					'finalizing_upload'                 => _x( 'Finalizing upload', 'The upload is in the last stages', 'as3cf-pro' ),
					'sure'                              => _x( 'Are you sure you want to leave whilst uploading to S3?', 'Confirmation required', 'as3cf-pro' ),
					'hide'                              => _x( 'Hide', 'Hide upload errors', 'as3cf-pro' ),
					'show'                              => _x( 'Show', 'Show upload errors', 'as3cf-pro' ),
					'errors'                            => _x( 'Errors', 'Upload errors', 'as3cf-pro' ),
					'error'                             => _x( 'Error', 'Upload error', 'as3cf-pro' ),
				) ),
				'nonces' => apply_filters( 'as3cfpro_js_nonces', array(
					'initiate_upload'       => wp_create_nonce( 'initiate-upload' ),
					'calculate_attachments' => wp_create_nonce( 'calculate-attachments' ),
					'upload_attachments'    => wp_create_nonce( 'upload-attachments' ),
					'finish_upload'         => wp_create_nonce( 'finish-upload' ),
					'dismiss_notice'        => wp_create_nonce( 'dismiss-notice' ),
					'update_upload_notices' => wp_create_nonce( 'update-upload-notices' ),
				) ),
			)
		);
	}

	/**
	 * Load the media assets
	 */
	function load_media_assets() {
		if ( ! $this->verify_media_actions() ) {
			return;
		}

		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$src = plugins_url( 'assets/css/media.css', $this->plugin_file_path );
		wp_enqueue_style( 'as3cf-pro-media-styles', $src, array( 'as3cf-modal' ), $version );

		wp_enqueue_script( 'as3cf-pro-find-replace-media' );

		$src = plugins_url( 'assets/js/media' . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( 'as3cf-pro-media-script', $src, array( 'jquery', 'as3cf-pro-find-replace-media', 'media-views', 'media-grid' ), $version, true );

		wp_localize_script( 'as3cf-pro-media-script',
			'as3cfpro_media',
			array(
				'strings' => $this->get_media_action_strings(),
				'nonces' => array(
					'copy_media'                => wp_create_nonce( 'copy-media' ),
					'remove_media'              => wp_create_nonce( 'remove-media' ),
					'download_media'            => wp_create_nonce( 'download-media' ),
					'get_attachment_s3_details' => wp_create_nonce( 'get-attachment-s3-details' ),
				)
			)
		);
	}

	/**
	 * Load the attachment assets only when editing an attachment
	 *
	 * @param $hook_suffix
	 */
	function load_attachment_assets( $hook_suffix ) {
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register script for later use
		$src = plugins_url( 'assets/js/find-replace-media' . $suffix . '.js', $this->plugin_file_path );
		wp_register_script( 'as3cf-pro-find-replace-media', $src, array( 'jquery', 'as3cf-modal' ), $version, true );

		global $post;
		if ( 'post.php' != $hook_suffix || 'attachment' != $post->post_type ) {
			return;
		}

		$src = plugins_url( 'assets/css/attachment.css', $this->plugin_file_path );
		wp_enqueue_style( 'as3cf-pro-attachment-styles', $src, array( 'as3cf-modal' ), $version );

		wp_enqueue_script( 'as3cf-pro-find-replace-media' );

		$src = plugins_url( 'assets/js/attachment' . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( 'as3cf-pro-attachment-script', $src, array( 'jquery', 'as3cf-pro-find-replace-media' ), $version, true );

		wp_localize_script( 'as3cf-pro-attachment-script',
			'as3cfpro_media',
			array(
				'local_warning' => $this->get_media_action_strings( 'local_warning' )
			)
		);
	}

	/**
	 * Get all strings or a specific string used for the media actions
	 *
	 * @param null|string $string
	 *
	 * @return array|string
	 */
	function get_media_action_strings( $string = null ) {
		$strings = array(
			'copy'               => __( 'Copy to S3', 'as3cf-pro' ),
			'remove'             => __( 'Remove from S3', 'as3cf-pro' ),
			'download'           => __( 'Copy to Server from S3', 'as3cf-pro' ),
			'local_warning'      => __( 'This file does not exist locally so removing it from S3 will result in broken links on your site. Are you sure you want to continue?', 'as3cf-pro' ),
			'bulk_local_warning' => __( 'Some files do not exist locally so removing them from S3 will result in broken links on your site. Are you sure you want to continue?', 'as3cf-pro' ),
			'bucket'             => _x( 'Bucket', 'Amazon S3 bucket', 'as3cf-pro' ),
			'key'                => _x( 'Path', 'Path to file on Amazon S3', 'as3cf-pro' ),
			'region'             => _x( 'Region', 'Location of Amazon S3 bucket', 'as3cf-pro' ),
			'acl'                => _x( 'Access', 'Access control list of the file on Amazon S3', 'as3cf-pro' ),
			'amazon_s3'          => __( 'Amazon S3', 'as3cf-pro' ),
		);

		if ( ! is_null( $string ) ) {
			return isset( $strings[ $string ] ) ? $strings[ $string ] : '';
		}

		return $strings;
	}

	/**
	 * Add custom classes to the HTML body tag
	 *
	 * @param $classes
	 *
	 * @return string
	 */
	function admin_body_class( $classes ) {
		if ( ! $classes ) {
			$classes = array();
		} else {
			$classes = explode( ' ', $classes );
		}

		$classes[] = 'as3cf-pro';

		// Recommended way to target WP 3.8+
		// http://make.wordpress.org/ui/2013/11/19/targeting-the-new-dashboard-design-in-a-post-mp6-world/
		if ( version_compare( $GLOBALS['wp_version'], '3.8-alpha', '>' ) ) {
			if ( ! in_array( 'mp6', $classes ) ) {
				$classes[] = 'mp6';
			}
		}

		return implode( ' ', $classes );
	}

	/**
	 * Accessor for plugin slug to make sure we don't add pro suffix
	 * when comparing the plugin slug in the settings form
	 *
	 * @param bool $true_slug
	 *
	 * @return string
	 */
	public function get_plugin_slug( $true_slug = false ) {
		if ( $true_slug ) {
			return $this->plugin_slug;
		}

		global $as3cf;

		return $as3cf->get_plugin_slug();
	}

	/**
	 * Add find and replace modal to settings page
	 */
	function post_settings_render() {
		if ( ! $this->is_plugin_setup() ) {
			return;
		}

		$this->render_view( 'find-and-replace-settings' );
	}

	/**
	 * Add find and replace hidden form field
	 */
	function settings_form_hidden_fields() {
		echo '<input type="hidden" name="find_replace" value="0" />';
	}

	/**
	 * Customise the S3 and CloudFront settings page title
	 *
	 * @param string $title
	 *
	 * @return string
	 */
	function settings_page_title( $title ) {
		return $title . ' Pro';
	}

	/**
	 * Override the settings tabs
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	function settings_tabs( $tabs ) {
		if ( isset( $tabs['support'] ) ) {
			$tabs['support'] = _x( 'License & Support', 'Show the license and support tab', 'as3cf-pro' );
		}

		return $tabs;
	}

	/**
	 * Add bulk action explanation to lost files notice
	 *
	 * @param string $notice
	 *
	 * @return string
	 */
	function lost_files_notice( $notice ) {
		return $notice . ' ' . __( 'Alternatively, use the Media Library bulk action <strong>Copy to Server from S3</strong> to ensure the local files exist.', 'as3cf-pro' );
	}

	/**
	 * Initiate find and replace on settings update.
	 */
	function pre_save_settings() {
		if ( ! isset( $_POST['find_replace'] ) || 0 === (int) $_POST['find_replace'] ) {
			// Find and replace not required
			return;
		}

		$data = array();

		foreach ( $this->previous_url_whitelist as $key ) {
			$data['previous'][ $key ] = $this->get_setting( $key );
			$data['new'][ $key ]      = sanitize_text_field( $_POST[ $key ] ); // input var ok
		}

		$this->notices->add_notice(
			__( '<strong>Running Find & Replace</strong> &mdash; URLs within your content are being updated in the background. This may take a while depending on how many items you have in your Media Library.', 'as3cf-pro' ),
			array( 'custom_id' => 'as3cf-notice-running-find-replace', 'flash' => false )
		);

		// Dispatch background request to process replacements
		$this->init_settings_change_request->data( $data )->dispatch();
	}

	/**
	 * Render find and replace modal
	 */
	function find_and_replace_render() {
		if ( ! $this->is_plugin_setup() ) {
			return;
		}

		$this->render_view( 'find-and-replace-media' );
	}

	/**
	 * Render a view template file specific to child class
	 * or use parent view as a fallback
	 *
	 * @param string $view View filename without the extension
	 * @param array  $args Arguments to pass to the view
	 */
	function render_view( $view, $args = array() ) {
		extract( $args );
		$view_file = $this->plugin_dir_path . '/view/' . $view . '.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			global $as3cf;
			include $as3cf->plugin_dir_path . '/view/' . $view . '.php';
		}
	}

	/**
	 * Are we currently doing a legacy media upload?
	 *
	 * @return bool
	 */
	function is_uploading_existing_media() {
		return (bool) get_site_transient( $this->legacy_upload_lock_key );
	}

	/**
	 * Display notices when there are media items not uploaded to S3
	 *
	 * @param string $tab
	 */
	public function media_to_upload_notices( $tab ) {
		if ( 'media' !== $tab ) {
			return;
		}

		if ( ! $this->is_plugin_setup() ) {
			return;
		}

		if ( $this->is_uploading_existing_media() ) {
			// Don't show upload notice if already doing upload
			return;
		}

		// Don't show upload banner if bucket isn't writable
		$can_write = $this->check_write_permission();
		if ( ! $can_write || is_wp_error( $can_write ) ) {
			return;
		}

		$to_upload_stats = $this->get_media_to_upload_stats();

		// Don't show upload banner if media library empty
		if ( 0 === $to_upload_stats['total_media'] ) {
			return;
		}

		$uploaded_percentage = ( $to_upload_stats['total_media'] - $to_upload_stats['total_to_upload'] ) / $to_upload_stats['total_media'];
		$human_percentage    = round( $uploaded_percentage * 100 );

		// Percentage of library needs uploading
		if ( 0 === (int) $human_percentage ) {
			$human_percentage = 1;
		}
		$message            = sprintf( __( "%s%% of your Media Library has been uploaded to S3.", 'as3cf-pro' ), $human_percentage );
		$show_upload        = true;
		$upload_button_text = __( 'Upload Remaining Now', 'as3cf-pro' );

		// Entire media library uploaded
		if ( 1 === $uploaded_percentage ) {
			$message     = __( '100% of your Media Library has been uploaded to S3, congratulations!', 'as3cf-pro' );
			$show_upload = false;

			// Remove previous errors
			if ( $this->get_setting( 'bulk_upload_errors', false ) ) {
				$this->remove_setting( 'bulk_upload_errors' );
				$this->save_settings();
			}
		}

		// Entire library needs uploading
		if ( 0 === $uploaded_percentage ) {
			$message            = __( 'Your Media Library needs to be uploaded to S3.', 'as3cf-pro' );
			$upload_button_text = __( 'Upload Now', 'as3cf-pro' );
		}

		$args = array(
			'message'            => $message,
			'show_upload'        => $show_upload,
			'upload_button_text' => $upload_button_text,
		);

		$this->render_view( 'upload-notice', $args );

		// Show errors notice only when upload button visible
		if ( $show_upload ) {
			$this->media_to_upload_errors_notice();
		}
	}

	/**
	 * Display an error notice displaying the upload errors
	 */
	function media_to_upload_errors_notice() {
		$upload_errors = $this->get_setting( 'bulk_upload_errors', null );

		// No errors to show
		if ( empty( $upload_errors ) ) {
			// Remove setting is empty value saved
			if ( ! is_null( $upload_errors ) ) {
				$this->remove_setting( 'bulk_upload_errors' );
				$this->save_settings();
			}

			return;
		}

		// User has dismissed display of errors
		if ( 1 === $this->get_setting( 'dismiss-upload-errors', 0 ) ) {
			return;
		}

		$args = array(
			'message' => __( 'Previous attempts at uploading your media library have resulted in errors.', 'as3cf-pro' ),
			'errors'  => $upload_errors,
		);

		$this->render_view( 'upload-errors', $args );
	}

	/**
	 * Get all the blogs of the site (only one if single site)
	 * Returning    - table prefix
	 *              - last_attachment: flag to record if we have processed all attachments for the blog
	 *              - processed: record last post id process to be used as an offset in the next batch for the blog
	 *
	 * @return array
	 */
	function get_blogs_data() {
		global $wpdb;

		$blogs = array();

		$blogs[1] = array(
			'prefix' => $wpdb->prefix,
		);

		if ( is_multisite() ) {
			$blog_ids = $this->get_blog_ids();

			foreach ( $blog_ids as $blog_id ) {
				$blogs[ $blog_id ] = array(
					'prefix' => $wpdb->get_blog_prefix( $blog_id ),
				);
			}
		}

		return $blogs;
	}

	/**
	 * Find the counts of media not uploaded to S3 and overall total of media
	 *
	 *  - total_to_upload
	 *  - total_media
	 *
	 * @return float
	 */
	function get_media_to_upload_stats() {

		$blogs = $this->get_blogs_data();

		$total_media     = 0;
		$total_to_upload = 0;

		foreach ( $blogs as $blog_id => $blogs ) {
			$total_media += $this->get_total_attachments( $blogs['prefix'] );
			$total_to_upload += $this->get_attachments_to_upload( $blogs['prefix'], $blog_id, true, null, null );
		}

		return compact( 'total_to_upload', 'total_media' );
	}

	/**
	 * Get the total off attachments in the media library
	 *
	 * @param $prefix table prefix for multisite support
	 *
	 * @return mixed
	 */
	function get_total_attachments( $prefix ) {
		global $wpdb;
		$sql = "SELECT COUNT(*)
				FROM `{$prefix}posts`
				WHERE `{$prefix}posts`.`post_type` = 'attachment'";

		$total = $wpdb->get_var( $sql );

		return $total;
	}

	/**
	 * Get all attachments uploaded to S3
	 *
	 * @param string $prefix Table prefix for multisite support
	 * @param bool   $count
	 * @param bool   $limit
	 * @param int    $offset
	 *
	 * @return mixed
	 */
	function get_all_s3_attachments( $prefix, $count = false, $limit = false, $offset = 0 ) {
		global $wpdb;

		$sql = " FROM `{$prefix}postmeta`
		        WHERE `meta_key` = 'amazonS3_info'";

		if ( $count ) {
			$sql    = 'SELECT COUNT(*)' . $sql;
			$result = $wpdb->get_var( $sql );

			return ( ! is_null( $result ) ) ? $result : 0;
		}

		$sql = 'SELECT *' . $sql;

		if ( false !== $limit ) {
			$sql .= ' LIMIT %d OFFSET %d';
			$sql = $wpdb->prepare( $sql, $limit, $offset );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get the attachments not uploaded to S3
	 *
	 * @param string $prefix  table prefix for multisite support
	 * @param int    $blog_id
	 * @param bool   $count   if enabled just returns a count of attachments
	 * @param null   $limit   limit number of attachments returned
	 * @param null   $offset  set last attachment id from previous batch as a starting point
	 * @param null   $exclude array of attachment ids to exclude when they have previously had errors during upload
	 *
	 * @return array|int
	 */
	function get_attachments_to_upload( $prefix, $blog_id, $count = false, $limit = null, $offset = null, $exclude = null ) {
		global $wpdb;

		$limit_sql = $offset_sql = $join_sql = $where_sql = '';

		if ( $count ) {
			$select_sql = 'SELECT COUNT(*)';
			$action     = 'get_var';
		} else {
			$select_sql = "SELECT p.`ID`, pm2.`meta_value` as 'data', {$blog_id} AS 'blog_id'";
			$join_sql   = "LEFT OUTER JOIN `{$prefix}postmeta` pm2
			            ON p.`ID` = pm2.`post_id`
			            AND pm2.`meta_key` = '_wp_attachment_metadata'";
			if ( ! is_null( $offset ) ) {
				$offset = absint( $offset );
				$offset_sql .= "AND p.`ID` > {$offset}
								ORDER BY p.`ID`";
			}
			if ( ! is_null( $limit ) ) {
				$limit     = absint( $limit );
				$limit_sql = "LIMIT {$limit}";
			}
			if ( ! is_null( $exclude ) ) {
				$post__not_in = implode( ',', array_map( 'absint', (array) $exclude ) );
				$where_sql    = "AND p.`ID` not in ({$post__not_in})";
			}

			$action = 'get_results';
		}

		$sql = $select_sql . ' ';
		$sql .= "FROM `{$prefix}posts` p
				LEFT OUTER JOIN `{$prefix}postmeta` pm
				ON p.`ID` = pm.`post_id`
				AND pm.`meta_key` = 'amazonS3_info'";
		$sql .= ' ' . $join_sql . ' ';
		$sql .= "WHERE p.`post_type` = 'attachment'
				AND pm.`post_id` IS NULL";
		$sql .= ' ' . $where_sql;
		$sql .= ' ' . $offset_sql;
		$sql .= ' ' . $limit_sql;

		$results = $wpdb->$action( $sql );

		return $results;
	}

	/**
	 * Add the Upload modal view to the AS3CF settings page
	 */
	function upload_modal() {
		$this->render_view( 'upload-progress' );
	}

	/**
	 * Add the redirects modal view to the AS3CF settings page
	 */
	function upload_redirects_modal() {
		$this->render_view( 'upload-redirects' );
	}

	/**
	 * Get the original local URL for attachment
	 *
	 * This is a direct copy of wp_get_attachment_url() from /wp-includes/post.php
	 * as we filter the URL in AS3CF and can't remove this filter using the current implementation
	 * of globals for class instances.
	 *
	 * @param int $post_id
	 *
	 * @return bool|mixed|string
	 */
	function get_local_attachment_url( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post = get_post( $post_id ) ) {
			return false;
		}

		if ( 'attachment' != $post->post_type ) {
			return false;
		}

		$url = '';
		// Get attached file.
		if ( $file = get_post_meta( $post->ID, '_wp_attached_file', true ) ) {
			// Get upload directory.
			if ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) {
				// Check that the upload base exists in the file location.
				if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
					// Replace file location with url location.
					$url = str_replace( $uploads['basedir'], $uploads['baseurl'], $file );
				} elseif ( false !== strpos( $file, 'wp-content/uploads' ) ) {
					$url = $uploads['baseurl'] . substr( $file, strpos( $file, 'wp-content/uploads' ) + 18 );
				} else {
					// It's a newly-uploaded file, therefore $file is relative to the basedir.
					$url = $uploads['baseurl'] . "/$file";
				}
			}
		}

		/*
		 * If any of the above options failed, Fallback on the GUID as used pre-2.7,
		 * not recommended to rely upon this.
		 */
		if ( empty( $url ) ) {
			$url = get_the_guid( $post->ID );
		}

		if ( empty( $url ) ) {
			return false;
		}

		// Set correct domain on multisite subdomain installs
		if ( is_multisite() ) {
			$siteurl         = trailingslashit( get_option( 'siteurl' ) );
			$network_siteurl = trailingslashit( network_site_url() );

			if ( 0 !== strpos( $url, $siteurl ) ) {
				// URL already using site URL, no replacement needed
				$url = str_replace( $network_siteurl, $siteurl, $url );
			}
		}

		return $url;
	}

	/**
	 * Find and replace embedded URLs for an attachment
	 *
	 * @param int        $attachment_id
	 * @param bool       $upload if TRUE then we are swapping local URLs with S3 URLs for an upload,
	 *                           if FALSE then we are removing/downloading from S3 therefore we are
	 *                           swapping the S3 URLs with local URLs in content.
	 * @param array|null $meta attachment meta data
	 */
	function find_and_replace_attachment_urls( $attachment_id, $upload = true, $meta = null ) {
		if ( is_null( $meta ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id, true );
		}

		$file_path = get_attached_file( $attachment_id, true );

		$local_url = $this->get_local_attachment_url( $attachment_id );
		$s3_url    = $this->get_attachment_url( $attachment_id, null, null, $meta );

		$old_url = ( $upload ) ? $local_url : $s3_url;
		$new_url = ( $upload ) ? $s3_url : $local_url;

		$this->find_and_replace_urls( $file_path, $old_url, $new_url, $meta );

		// On legacy MS installs (pre 3.5) we need to also search for attachment GUID
		// as paths were rewritten to exclude '/wp-content/blogs.dir/'
		if ( is_multisite() && false !== strpos( $local_url, '/blogs.dir/' ) ) {
			$old_url = get_the_guid( $attachment_id );
			$this->find_and_replace_urls( $file_path, $old_url, $new_url, $meta );
		}
	}

	/**
	 * Find and replace embedded URLs
	 *
	 * @param string      $file_path base file path
	 * @param string      $old_url
	 * @param string      $new_url
	 * @param array       $meta
	 * @param string|null $old_filepath - Used when replacing URLs with different filenames
	 * @param array       $old_meta     - Used when replacing URLs with different filenames
	 *
	 */
	function find_and_replace_urls( $file_path, $old_url, $new_url, $meta = array(), $old_filepath = null, $old_meta = array() ) {
		$file_name = basename( $file_path );

		$old_filename = $file_name;
		if ( ! is_null( $old_filepath ) ) {
			$old_filename = basename( $old_filepath );
		}

		$find_replace_pairs = array();

		$find_replace_pairs[] = array(
			'old_path' => $file_path,
			'old_url'  => $old_url,
			'new_url'  => $new_url,
		);

		// do for thumb and image sizes
		if ( isset( $meta['thumb'] ) && $meta['thumb'] ) {
			// Replace URLs for legacy thumbnail of image
			$old_meta_filename = isset( $old_meta['thumb'] ) ? $old_meta['thumb'] : $meta['thumb'];

			$find_replace_pairs[] = array(
				'old_path' => str_replace( $file_name, $meta['thumb'], $file_path ),
				'old_url'  => str_replace( $old_filename, $old_meta_filename, $old_url ),
				'new_url'  => str_replace( $file_name, $meta['thumb'], $new_url ),
			);
		} elseif ( ! empty( $meta['sizes'] ) ) {
			// Replace URLs for intermediate sizes of image
			foreach ( $meta['sizes'] as $key => $size ) {
				if ( ! isset( $size['file'] ) ) {
					continue;
				}
				$old_meta_filename = isset( $old_meta['sizes'][$key]['file'] ) ? $old_meta['sizes'][$key]['file'] : $size['file'];

				$find_replace_pairs[] = array(
					'old_path' => str_replace( $file_name, $size['file'], $file_path ),
					'old_url'  => str_replace( $old_filename, $old_meta_filename, $old_url ),
					'new_url'  => str_replace( $file_name, $size['file'], $new_url ),
				);
			}
		}

		if ( $this->get_setting( 'hidpi-images' ) ) {
			// Replace URLs for @2x images used by most HiDPI plugins
			$hidpi_images = array();

			foreach ( $find_replace_pairs as $image ) {
				$hidpi_path     = $this->get_hidpi_file_path( $image['old_path'] );
				$hidpi_file     = basename( $hidpi_path );

				$old_hidpi_file = $hidpi_file;
				if ( ! is_null( $old_filepath ) ) {
					$existing_path  = str_replace( basename( $image['new_url'] ), basename( $image['old_url'] ), $image['old_path'] );
					$old_hidpi_path = $this->get_hidpi_file_path( $existing_path );
					$old_hidpi_file = basename( $old_hidpi_path );
				}

				$hidpi_images[] = array(
					'old_url' => str_replace( $old_filename, $old_hidpi_file, $old_url ),
					'new_url' => str_replace( $file_name, $hidpi_file, $new_url ),
				);
			}

			$find_replace_pairs = array_merge( $find_replace_pairs, $hidpi_images );
		}

		// take the pairs and do the magic on the database
		$this->process_pair_replacement( $find_replace_pairs );
	}

	/**
	 * Perform the find and replace in the database of old and new URLs
	 *
	 * Scope of replacement:
	 *  - wp_posts.post_content
	 *
	 * @param array $find_replace_pairs multidimensional array containing pairs of
	 *                                  old and new URLs for replacement
	 */
	function process_pair_replacement( $find_replace_pairs = array() ) {
		global $wpdb;

		foreach ( $find_replace_pairs as $pair ) {
			if ( ! isset( $pair['old_url'] ) || ! isset( $pair['new_url'] ) ) {
				// we need both URLs for the find and replace
				continue;
			}

			// this could be built up with nested replace() but initially keep as is
			// unless performance and scale becomes an issue after v1.0
			$post_content_sql = "UPDATE $wpdb->posts SET `post_content` = replace(post_content, '{$pair['old_url']}', '{$pair['new_url']}');";
			// run the sql
			$wpdb->query( $post_content_sql );
		}
	}

	/**
	 * Get the file size of an attachment and all it's versions.
	 *
	 * @param int $attachment_id
	 * @param array|bool $file_meta
	 *
	 * @return int Bytes
	 */
	function get_attachment_file_size( $attachment_id, $file_meta = false ) {
		$bytes = 0;
		$paths = $this->get_attachment_file_paths( $attachment_id, true, $file_meta );

		foreach ( $paths as $path ) {
			$bytes += filesize( $path );
		}

		return $bytes;
	}

	/**
	 * AJAX handler for initiating the upload
	 *
	 * @return array $return
	 */
	function ajax_initiate_upload() {
		check_ajax_referer( 'initiate-upload', 'nonce' );

		// Check for the upload lock
		if ( $this->is_uploading_existing_media() ) {
			wp_send_json_error( __( 'Upload already in process.', 'as3cf-pro' ) );
		}

		// Lock upload and cleanup after 5 minutes
		set_site_transient( $this->legacy_upload_lock_key, true, MINUTE_IN_SECONDS * 5 );

		// Clear previous queue items
		AS3CF_Pro_Utils::delete_wildcard_options( 'wpos3_legacy_upload_%' );

		// Clear previous errors
		$this->get_settings();
		$this->remove_setting( 'bulk_upload_errors' );
		$this->save_settings();

		$blogs = $this->get_blogs_data();

		wp_send_json( $blogs );
	}

	/**
	 * AJAX handler for the recursive calculation of attachments
	 */
	function ajax_calculate_attachments() {
		check_ajax_referer( 'calculate-attachments', 'nonce' );

		if ( ! isset( $_POST['blogs'] ) || ! isset( $_POST['progress'] ) ) {
			wp_die();
		}

		$blogs       = $_POST['blogs'];
		$progress    = $_POST['progress'];
		$limit       = apply_filters( 'as3cfpro_calculate_batch_limit', 100 );
		$finish_time = time() + apply_filters( 'as3cfpro_calculate_batch_time', 5 ); // Seconds;
		$files       = array();

		// Loop over each blog
		foreach ( $blogs as $id => $blog ) {
			$this->switch_to_blog( $id );

			$count = 0;
			$total = $this->get_attachments_to_upload( $blogs[ $id ]['prefix'], $id, true );

			if ( ! isset( $blogs[ $id ]['last_attachment'] ) ) {
				$blogs[ $id ]['last_attachment'] = null;
			}

			// Process attachments in batches
			do {
				$attachments = $this->get_attachments_to_upload( $blogs[ $id ]['prefix'], $id, false, $limit, $blogs[ $id ]['last_attachment'] );

				if ( empty( $attachments ) ) {
					// No attachments remaining to process, remove blog from queue
					unset( $blogs[ $id ] );

					break;
				}

				foreach ( $attachments as $attachment ) {
					$size = $this->get_attachment_file_size( $attachment->ID, maybe_unserialize( $attachment->data ) );

					$files[ $id ][ $attachment->ID ] = $size;
					$progress['total_bytes'] += $size;
					$progress['total_files']++;
					$count++;

					$blogs[ $id ]['last_attachment'] = $attachment->ID;

					if ( time() >= $finish_time ) {
						// Time limit exceeded
						break 3;
					}
				}
			} while ( $count <= $total );

		}

		$this->restore_current_blog( $id );

		// No files to process, gracefully die
		if ( 0 === (int) $progress['total_files'] ) {
			wp_send_json_error( __( 'No files to upload.', 'as3cf-pro' ) );
		}

		$data = array(
			'blogs'    => $blogs,
			'progress' => $progress,
		);

		// Save to options table
		$unique = md5( microtime() . rand() );
		$key    = substr( 'wpos3_legacy_upload_' . $unique, 0, 64 );
		update_site_option( $key, $files );

		wp_send_json( $data );
	}

	/**
	 * AJAX handler for the recursive upload of attachments
	 */
	function ajax_upload_attachments() {
		check_ajax_referer( 'upload-attachments', 'nonce' );

		if ( ! isset( $_POST['progress'] ) ) {
			return;
		}

		// Update the lock transient expiry for the batch
		set_site_transient( $this->legacy_upload_lock_key, true, MINUTE_IN_SECONDS * 5 );

		$progress         = $_POST['progress'];
		$find_and_replace = filter_var( $_POST['find_and_replace'], FILTER_VALIDATE_BOOLEAN );

		$batch_limit = apply_filters( 'as3cfpro_upload_batch_limit', 10 ); // number of attachments
		$batch_time  = apply_filters( 'as3cfpro_upload_batch_time', 10 ); // seconds
		$batch_count = 0;
		$finish_time = time() + $batch_time;

		$errors        = array();
		$upload_errors = $this->get_setting( 'bulk_upload_errors', array() );

		// Count queue items
		$queues      = $this->count_legacy_uploads();
		$queue_count = 0;

		// Loop over each batch
		do {
			$uploads = $this->get_legacy_uploads();

			if ( ! $uploads ) {
				// Queue empty
				break;
			}

			// Loop over each blog
			foreach ( $uploads->data as $blog_id => $attachments ) {
				$this->switch_to_blog( $blog_id );

				// Loop over each attachment
				foreach ( $attachments as $attachment_id => $size ) {
					$remove_local_file = ! $find_and_replace;

					// Skip upload if attachment already on S3
					if ( ! $this->get_attachment_s3_info( $attachment_id ) ) {
						$s3object = $this->upload_attachment_to_s3( $attachment_id, null, null, false, $remove_local_file );

						// Perform find and replace of attachment URLs
						if ( false === is_wp_error( $s3object ) && true === $find_and_replace ) {
							$data = array(
								'attachment_id' => $attachment_id,
								'blog_id'       => $blog_id,
							);
							$this->find_replace_process->push_to_queue( $data );
						}

						// Build error message
						if ( is_wp_error( $s3object ) ) {
							$file                                        = get_post_meta( $attachment_id, '_wp_attached_file', true );
							$error_msg                                   = sprintf( __( 'Could not upload file %s to S3 - %s' ), $file, $s3object->get_error_message() );
							$errors[]                                    = $error_msg;
							$upload_errors[ $blog_id ][ $attachment_id ] = $error_msg;
						}
					}

					$progress['bytes'] += $size;
					$progress['files']++;

					// Remove attachment from queue
					unset( $uploads->data[ $blog_id ][ $attachment_id ] );

					if ( time() >= $finish_time || $batch_count >= $batch_limit ) {
						// Time limit exceeded or attachment limit exceeded
						break 2;
					}
				}

				// Remove blog from queue
				unset( $uploads->data[ $blog_id ] );
			}

			$this->restore_current_blog( $blog_id );

			if ( ! empty( $uploads->data ) ) {
				update_site_option( $uploads->key, $uploads->data );
			} else {
				delete_site_option( $uploads->key );
			}

			$queue_count++;
		} while ( $queue_count < $queues );

		// Save queue and dispatch background process
		$this->find_replace_process->save()->dispatch();

		// Un-hide errors notice if new errors have occurred
		if ( count( $errors ) ) {
			$this->set_setting( 'dismiss-upload-errors', 0 );
		}

		// Save errors
		$this->set_setting( 'bulk_upload_errors', $upload_errors );
		$this->save_settings();

		$progress['errors'] = $errors;

		wp_send_json( $progress );
	}

	/**
	 * Count legacy uploads
	 *
	 * @return null|string
	 */
	function count_legacy_uploads() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", 'wpos3_legacy_upload_%' ) );

		return $count;
	}

	/**
	 * Get legacy uploads
	 *
	 * @return bool|stdClass
	 */
	function get_legacy_uploads() {
		global $wpdb;

		$table        = $wpdb->options;
		$column       = 'option_name';
		$key_column   = 'option_id';
		$value_column = 'option_value';

		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$key_column   = 'meta_id';
			$value_column = 'meta_value';
		}

		$query = $wpdb->get_row( $wpdb->prepare( "
			SELECT *
			FROM {$table}
			WHERE {$column} LIKE %s
			ORDER BY {$key_column} ASC
			LIMIT 1
		", 'wpos3_legacy_upload_%' ) );

		if ( is_null( $query ) ) {
			return false;
		}

		$batch       = new stdClass();
		$batch->key  = $query->$column;
		$batch->data = maybe_unserialize( $query->$value_column );

		return $batch;
	}

	/**
	 * Perform any actions when the upload has finished:
	 *      success
	 *      error
	 *      cancel
	 */
	function ajax_finish_upload() {
		check_ajax_referer( 'finish-upload', 'nonce' );

		// Remove legacy upload lock
		delete_site_transient( $this->legacy_upload_lock_key );
	}

	/**
	 * Handle hiding upload notices
	 */
	function ajax_dismiss_notice() {
		check_ajax_referer( 'dismiss-notice', 'nonce' );

		if ( ! isset( $_POST['notice'] ) ) { // input var okay
			wp_send_json_error();
		}

		$notice_whitelist = array(
			'dismiss-upload-errors',
		);

		$notice = sanitize_key( $_POST['notice'] ); // input var okay

		if ( ! in_array( $notice, $notice_whitelist ) ) {
			wp_send_json_error();
		}

		$this->get_settings();
		$this->set_setting( $notice, 1 );
		$this->save_settings();

		wp_send_json_success();
	}

	/**
	 * Handle refreshing of upload notices on settings page behind upload modal
	 */
	function ajax_update_upload_notices() {
		check_ajax_referer( 'update-upload-notices', 'nonce' );

		ob_start();
		$this->media_to_upload_notices( 'media' );
		$notice_html = ob_get_contents();
		ob_end_clean();

		wp_send_json_success( $notice_html );
	}

	/**
	 * Handle S3 actions applied to attachments via the Backbone JS
	 * in the media grid and edit attachment modal
	 */
	function ajax_process_media_action() {
		if ( ! isset( $_POST['s3_action'] ) && ! isset( $_POST['ids'] ) ) {
			return;
		}

		$action = sanitize_key( $_POST['s3_action'] ); // input var okay

		check_ajax_referer( $action . '-media', '_nonce' );

		$ids = array_map( 'intval', $_POST['ids'] ); // input var okay

		$do_find_and_replace = isset( $_POST['find_and_replace'] ) && $_POST['find_and_replace'] ? true : false;

		// process the S3 action for the attachments
		$return = $this->maybe_do_s3_action( $action, $ids, true, $do_find_and_replace );

		$message_html = '';

		if ( $return ) {
			$message_html = $this->get_media_action_result_message( $action, $return['count'], $return['errors'] );
		}

		wp_send_json_success( $message_html );
	}

	/**
	 * Handle retieving the S3 actions that can be applied to an attachment
	*/
	function ajax_get_attachment_s3_details() {
		if ( ! isset( $_POST['id'] ) ) {
			return;
		}

		check_ajax_referer( 'get-attachment-s3-details', '_nonce' );

		$id = intval( $_POST['id'] );

		// get the actions available for the attachment
		$data = array(
			'links'    => $this->add_media_row_actions( array(), $id ),
			's3object' => $this->get_formatted_s3_info( $id ),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Calculate batch limit based on the amount of registered image sizes
	 *
	 * @param int         $max
	 * @param string|null $filter_handle
	 *
	 * @return float
	 */
	function get_batch_limit( $max, $filter_handle = null ) {
		if ( ! is_null( $filter_handle ) ) {
			$max = apply_filters( $filter_handle, $max );
		}

		$sizes = count( get_intermediate_image_sizes() );

		return floor( $max / $sizes );
	}

	/**
	 * Process find and replace attachment
	 *
	 * @param array $attachment
	 * @param array $previous_settings
	 * @param array $new_settings
	 */
	function process_find_replace_attachment( $attachment, $previous_settings, $new_settings ) {
		$s3_info   = maybe_unserialize( $attachment['meta_value'] );
		$file_path = get_attached_file( $attachment['post_id'], true );
		$old_url   = $this->get_custom_attachment_url( $s3_info, $previous_settings );
		$new_url   = $this->get_custom_attachment_url( $s3_info, $new_settings );
		$meta      = wp_get_attachment_metadata( $attachment['post_id'], true );

		$this->find_and_replace_urls( $file_path, $old_url, $new_url, $meta );
	}

	/**
	 * Get the S3 attachment url, based on the provided URL settings.
	 *
	 * @param array $attachment
	 * @param array $args
	 *
	 * @return string
	 */
	function get_custom_attachment_url( $attachment, $args ) {
		$scheme  = $this->get_s3_url_scheme( $args['ssl'] );
		$expires = null;

		// Force use of secured url when ACL has been set to private
		if ( isset( $attachment['acl'] ) && self::PRIVATE_ACL === $attachment['acl'] ) {
			$expires = self::DEFAULT_EXPIRES;
		}

		$domain = $this->get_s3_url_domain( $attachment['bucket'], $attachment['region'], $expires, $args );

		return $scheme . '://' . $domain . '/' . $attachment['key'];
	}

	/**
	 * Enrich the attachment model attributes used in JS
	 *
	 * @param array      $response   Array of prepared attachment data.
	 * @param int|object $attachment Attachment ID or object.
	 *
	 * @return array
	 */
	function enrich_attachment_model( $response, $attachment ) {
		$file = get_attached_file( $attachment->ID, true );

		// flag if the attachment file doesn't exist locally
		// so we can ask for confirmation when removing from S3
		$response['bulk_local_warning'] = ! file_exists( $file );

		return $response;
	}

	/**
	 * Add the S3 meta box to the attachment screen
	 */
	function attachment_s3_meta_box() {
		add_meta_box( 's3-actions', __( 'Amazon S3', 'as3cf-pro' ), array( $this, 'attachment_s3_actions_meta_box' ), 'attachment', 'side', 'core' );
	}

	/**
	 * Return a formatted S3 info with display friendly defaults
	 *
	 * @param int        $id
	 * @param array|null $s3object
	 *
	 * @return array
	 */
	function get_formatted_s3_info( $id, $s3object = null ) {
		if ( is_null( $s3object ) ) {
			if ( ! ( $s3object = $this->get_attachment_s3_info( $id ) ) ) {
				return false;
			}
		}

		if ( ! isset( $s3object['acl'] ) ) {
			$s3object['acl'] = $this::DEFAULT_ACL;
		}

		$s3object['acl'] = $this->get_acl_display_name( $s3object['acl'] );

		$regions = $this->get_aws_regions();

		if ( isset( $s3object['region'] ) && '' == $s3object['region'] ) {
			$s3object['region'] = self::DEFAULT_REGION;
		}

		if ( isset( $regions[ $s3object['region'] ] ) ) {
			$s3object['region'] = $regions[ $s3object['region'] ];
		}

		return $s3object;
	}

	/**
	 * Render the S3 attachment meta box
	 */
	function attachment_s3_actions_meta_box() {
		global $post;
		$file = get_attached_file( $post->ID, true );

		$args = array(
			's3object'                 => $this->get_formatted_s3_info( $post->ID ),
			'post'                     => $post,
			'local_file_exists'        => file_exists( $file ),
			'user_can_perform_actions' => $this->verify_media_actions(),
			'sendback'                 => 'post.php?post=' . $post->ID . '&action=edit',
		);

		$this->render_view( 'attachment-metabox', $args );
	}

	/**
	 * Check we can do the media actions
	 *
	 * @return bool
	 */
	function verify_media_actions() {
		if ( ! $this->is_plugin_setup() ) {
			return false;
		}

		if ( ! current_user_can( apply_filters( 'as3cfpro_media_actions_capability', 'manage_options' ) ) ) {
			// Abort if the user doesn't have desired capabilities
			return false;
		}

		return true;
	}

	/**
	 * Conditionally adds copy, remove and download S3 action links for an
	 * attachment on the Media library list view
	 *
	 * @param array       $actions
	 * @param WP_Post|int $post
	 *
	 * @return array
	 */
	function add_media_row_actions( $actions = array(), $post ) {
		if ( ! $this->verify_media_actions() ) {
			return $actions;
		}

		$post_id = ( is_object( $post ) ) ? $post->ID : $post;

		$file = get_attached_file( $post_id, true );

		if ( ( $file_exists = file_exists( $file ) ) ) {
			// show the copy link if the file exists, even if the copy main setting is off
			$text = $this->get_media_action_strings( 'copy' );
			$this->add_media_row_action( $actions, $post_id, 'copy', $text );
		}

		if ( $this->get_attachment_s3_info( $post_id ) ) {
			// only show the remove link if media has been previously copied
			$text = $this->get_media_action_strings( 'remove' );
			$this->add_media_row_action( $actions, $post_id, 'remove', $text, ! $file_exists );

			if ( ! $file_exists ) {
				// only show download link if the file does not exist locally
				$text = $this->get_media_action_strings( 'download' );
				$this->add_media_row_action( $actions, $post_id, 'download', $text );
			}
		}

		return $actions;
	}

	/**
	 * Add an action link to the media actions array
	 *
	 * @param array  $actions
	 * @param int    $post_id
	 * @param string $action
	 * @param string $text
	 * @param bool   $show_warning
	 */
	function add_media_row_action( &$actions, $post_id, $action, $text, $show_warning = false ) {
		$url   = $this->get_media_action_url( $action, $post_id );
		$class = $action;
		if ( $show_warning ) {
			$class .= ' local-warning';
		}

		$actions[ 'as3cfpro_' . $action ] = '<a href="' . $url . '" class="'. $class .'" title="' . esc_attr( $text ) . '">' . esc_html( $text ) . '</a>';
	}

	/**
	 * Generate the URL for performing S3 media actions
	 *
	 * @param string      $action
	 * @param int         $post_id
	 * @param null|string $sendback_path
	 *
	 * @return string
	 */
	function get_media_action_url( $action, $post_id, $sendback_path = null ) {
		$args = array(
			'action' => $action,
			'ids'    => $post_id,
		);

		if ( ! is_null( $sendback_path ) ) {
			$args['sendback'] = urlencode( admin_url( $sendback_path ) );
		}

		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
		$url = wp_nonce_url( $url, 'as3cfpro-' . $action );

		return esc_url( $url );
	}

	/**
	 * Handler for single and bulk media actions
	 */
	function process_media_actions() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		global $pagenow;
		if ( 'upload.php' != $pagenow ) {
			return;
		}

		if ( ! $this->verify_media_actions() ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) ) { // input var okay
			return;
		}

		if ( ! empty( $_REQUEST['action2'] ) && '-1' != $_REQUEST['action2'] ) {
			// Handle bulk actions from the footer bulk action select
			$action = sanitize_key( $_REQUEST['action2'] ); // input var okay
		} else {
			$action = sanitize_key( $_REQUEST['action'] ); // input var okay
		}

		if ( false === strpos( $action, 'bulk_as3cfpro_' ) ) {
			$referrer          = 'as3cfpro-' . $action;
			$doing_bulk_action = false;
			if ( ! isset( $_GET['ids'] ) ) {
				return;
			}
			$ids = explode( ',', $_GET['ids'] ); // input var okay
		} else {
			$action            = str_replace( 'bulk_as3cfpro_', '', $action );
			$referrer          = 'bulk-media';
			$doing_bulk_action = true;
			if ( ! isset( $_REQUEST['media'] ) ) {
				return;
			}
			$ids = $_REQUEST['media']; // input var okay
		}

		$ids = array_map( 'intval', $ids );

		check_admin_referer( $referrer );

		$sendback = isset( $_GET['sendback'] ) ? $_GET['sendback'] : admin_url( 'upload.php' );

		$do_find_and_replace = isset( $_GET['find_and_replace'] ) && $_GET['find_and_replace'] ? true : false;

		$args = array(
			'as3cfpro-action' => $action,
		);

		$result = $this->maybe_do_s3_action( $action, $ids, $doing_bulk_action, $do_find_and_replace );

		if ( ! $result ) {
			unset( $args['as3cfpro-action'] );
			$result = array();
		}

		$args = array_merge( $args, $result );
		$url  = add_query_arg( $args, $sendback );

		wp_redirect( esc_url_raw( $url ) );
		exit();
	}

	/**
	 * Wrapper for S3 actions
	 *
	 * @param       $action              type of S3 action, copy, remove, download
	 * @param array $ids                 attachment IDs
	 * @param bool  $doing_bulk_action   flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment
	 * @param bool  $do_find_and_replace flag specifying if we need to run find and replace
	 *
	 * @return bool|array on success array with success count and error count
	 */
	function maybe_do_s3_action( $action, $ids, $doing_bulk_action, $do_find_and_replace ) {
		switch ( $action ) {
			case 'copy':
				$result = $this->maybe_upload_attachments_to_s3( $ids, $doing_bulk_action, $do_find_and_replace );
				break;
			case 'remove':
				$result = $this->maybe_delete_attachments_from_s3( $ids, $doing_bulk_action, $do_find_and_replace );
				break;
			case 'download':
				$result = $this->maybe_download_attachments_from_s3( $ids, $doing_bulk_action, $do_find_and_replace );
				break;
			default:
				// not one of our actions, remove
				$result = false;
				break;
		}

		return $result;
	}

	/**
	 * Display notices after processing media actions
	 */
	function maybe_display_media_action_message() {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'upload.php', 'post.php' ) ) ) {
			return;
		}

		if ( isset( $_GET['as3cfpro-action'] ) && isset( $_GET['errors'] ) && isset( $_GET['count'] ) ) {
			$action     = sanitize_key( $_GET['as3cfpro-action'] ); // input var okay

			$error_count = absint( $_GET['errors'] ); // input var okay
			$count       = absint( $_GET['count'] ); // input var okay

			$message_html = $this->get_media_action_result_message( $action, $count, $error_count );

			if ( false !== $message_html ) {
				echo $message_html;
			}
		}
	}

	/**
	 * Get the result message after an S3 action has been performed
	 *
	 * @param string $action      type of S3 action
	 * @param int    $count       count of successful processes
	 * @param int    $error_count count of errors
	 *
	 * @return bool|string
	 */
	function get_media_action_result_message( $action, $count = 0, $error_count = 0 ) {
		$class = 'updated';
		$type  = 'success';

		if ( 0 === $count && 0 === $error_count ) {
			// don't show any message if no attachments processed
			// i.e. they haven't met the checks for bulk actions
			return false;
		}

		if ( $error_count > 0 ) {
			$type = $class = 'error';
		}

		if ( $count > 0 ) {
			// we have processed some successfully but there are errors
			if ( $error_count > 0 ) {
				$type = 'partial';
			}
		}

		$message = $this->get_message( $action, $type );

		// can't find a relevant message, abort
		if ( ! $message ) {
			return false;
		}

		$message = sprintf( '<div class="notice as3cf-notice %s is-dismissible"><p>%s</p></div>', $class, $message );

		return $message;
	}

	/**
	 * Retrieve all the media action related notice messages
	 *
	 * @return array
	 */
	function get_messages() {
		if ( is_null( $this->messages ) ) {
			$this->messages = array(
				'copy'     => array(
					'success' => __( 'Media successfully copied to S3', 'as3cf-pro' ),
					'partial' => __( 'Media copied to S3 with some errors', 'as3cf-pro' ),
					'error'   => __( 'There were errors when copying the media to S3', 'as3cf-pro' ),
				),
				'remove'   => array(
					'success' => __( 'Media successfully removed from S3', 'as3cf-pro' ),
					'partial' => __( 'Media removed from S3, with some errors', 'as3cf-pro' ),
					'error'   => __( 'There were errors when removing the media from S3', 'as3cf-pro' ),
				),
				'download' => array(
					'success' => __( 'Media successfully downloaded from S3', 'as3cf-pro' ),
					'partial' => __( 'Media downloaded from S3, with some errors', 'as3cf-pro' ),
					'error'   => __( 'There were errors when downloading the media from S3', 'as3cf-pro' ),
				),
			);
		}

		return $this->messages;
	}

	/**
	 * Get a specific media action notice message
	 *
	 * @param string $action type of action, e.g. copy, remove, download
	 * @param string $type if the action has resulted in success, error, partial (errors)
	 *
	 * @return string|bool
	 */
	function get_message( $action = 'copy', $type = 'success' ) {
		$messages = $this->get_messages();
		if ( isset( $messages[ $action ][ $type ] ) ) {
			return $messages[ $action ][ $type ];
		}

		return false;
	}

	/**
	 * Wrapper for uploading multiple attachments to S3
	 *
	 * @param array $post_ids            attachment IDs
	 * @param bool  $doing_bulk_action   flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment to make sure the
	 *                                   file exists locally before uploading to S3
	 * @param bool  $do_find_and_replace flag specifying if we need to run find and replace
	 *
	 * @return bool
	 */
	function maybe_upload_attachments_to_s3( $post_ids, $doing_bulk_action = false, $do_find_and_replace = false ) {
		$error_count    = 0;
		$uploaded_count = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $doing_bulk_action ) {
				// if bulk action check the file exists
				$file = get_attached_file( $post_id, true );
				// if the file doesn't exist locally we can't copy
				if ( ! file_exists( $file ) ) {
					continue;
				}
			}

			// Upload the attachment to S3
			$remove_local_file = ! $do_find_and_replace;
			$result            = $this->upload_attachment_to_s3( $post_id, null, null, $doing_bulk_action, $remove_local_file );

			if ( is_wp_error( $result ) ) {
				$error_count++;
				continue;
			}

			// Update local URLs in content to S3 URLs
			if ( $do_find_and_replace ) {
				global $blog_id;

				$data = array(
					'action'        => 'copy',
					'attachment_id' => $post_id,
					'blog_id'       => $blog_id,
					'upload'        => true
				);
				$this->media_actions_process->push_to_queue( $data );
			}

			$uploaded_count ++;
		}

		$this->media_actions_process->save()->dispatch();

		$result = array(
			'errors' => $error_count,
			'count'  => $uploaded_count,
		);

		return $result;
	}

	/**
	 * Wrapper for removing multiple attachments from S3
	 *
	 * @param array $post_ids            attachment IDs
	 * @param bool  $doing_bulk_action   flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment to make sure it has
	 *                                   been uploaded to S3 before trying to delete it
	 * @param bool  $do_find_and_replace flag specifying if we need to run find and replace
	 *
	 * @return bool
	 */
	function maybe_delete_attachments_from_s3( $post_ids, $doing_bulk_action = false, $do_find_and_replace = false ) {
		$error_count   = 0;
		$deleted_count = 0;

		foreach ( $post_ids as $post_id ) {
			// if bulk action check has been uploaded to S3
			if ( $doing_bulk_action && ! $this->get_attachment_s3_info( $post_id ) ) {
				continue;
			}

			if ( $do_find_and_replace ) {
				global $blog_id;

				// Push task to background process
				$data = array(
					'action'        => 'remove',
					'attachment_id' => $post_id,
					'blog_id'       => $blog_id,
					'upload'        => false,
				);
				$this->media_actions_process->push_to_queue( $data );
			} else {
				// Delete attachment from S3
				$this->delete_attachment( $post_id, $doing_bulk_action );
				if ( $this->get_attachment_s3_info( $post_id ) ) {
					$error_count ++;
					continue;
				}

				$deleted_count ++;
			}
		}

		// Dispatch background process
		$this->media_actions_process->save()->dispatch();

		$result = array(
			'errors' => $error_count,
			'count'  => $deleted_count,
		);

		return $result;
	}

	/**
	 * Wrapper for downloading multiple attachments from S3
	 *
	 * @param array $post_ids            attachment IDs
	 * @param bool  $doing_bulk_action   flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment to make sure it has
	 *                                   been uploaded to S3 and does not exist locally before
	 *                                   trying to download it
	 * @param bool  $do_find_and_replace flag specifying if we need to run find and replace
	 *
	 * @return bool
	 */
	function maybe_download_attachments_from_s3( $post_ids, $doing_bulk_action = false, $do_find_and_replace = false ) {
		$error_count    = 0;
		$download_count = 0;

		foreach ( $post_ids as $post_id ) {
			$file = get_attached_file( $post_id, true );
			$file_exists_locally = false;

			if ( $doing_bulk_action ) {
				// if bulk action check has been uploaded to S3
				if ( ! $this->get_attachment_s3_info( $post_id ) ) {
					continue;
				}
				$file_exists_locally = file_exists( $file );
			}

			if ( ! $file_exists_locally ) {
				// Download the attachment from S3
				$this->download_attachment_from_s3( $post_id, $doing_bulk_action );
				if ( ! file_exists( $file ) ) {
					$error_count ++;
					continue;
				}
			}

			// Update S3 URLs in content to local URLs
			if ( $do_find_and_replace ) {
				$data = array(
					'action'        => 'download',
					'attachment_id' => $post_id,
					'upload'        => false,
				);
				$this->media_actions_process->push_to_queue( $data );
			}

			$download_count ++;
		}

		$this->media_actions_process->save()->dispatch();

		$result = array(
			'errors' => $error_count,
			'count'  => $download_count,
		);

		return $result;
	}

	/**
	 * Download attachment and associated files from S3 to local
	 *
	 * @param int  $post_id             attachment ID
	 * @param bool $force_new_s3_client if we are downloading in bulk, force new S3 client
	 *                                  to cope with possible different regions
	 */
	function download_attachment_from_s3( $post_id, $force_new_s3_client = false ) {
		if ( ! $this->is_plugin_setup() ) {
			return;
		}

		if ( ! ( $s3object = $this->get_attachment_s3_info( $post_id ) ) ) {
			return;
		}

		$region = $this->get_s3object_region( $s3object );
		if ( is_wp_error( $region ) ) {
			$region = false;
		}

		$s3client   = $this->get_s3client( $region, $force_new_s3_client );
		$prefix     = trailingslashit( dirname( $s3object['key'] ) );
		$file_paths = $this->get_attachment_file_paths( $post_id, false );
		$downloads  = array();

		foreach ( $file_paths as $file_path ) {
			if ( ! file_exists( $file_path ) ) {
				$file_name    = basename( $file_path );
				$hidpi_suffix = apply_filters( 'as3cf_hidpi_suffix', '@2x' );
				$hidpi_flag   = ( false !== strpos( $file_name, $hidpi_suffix ) ) ? true : false;

				$downloads[] = array(
					'Key'    => $prefix . $file_name,
					'SaveAs' => $file_path,
					'hidpi'  => $hidpi_flag,
				);
			}
		}

		foreach ( $downloads as $download ) {
			// Save object to a file
			$download['Bucket'] = $s3object['bucket'];

			// Make sure the local directory exists
			if ( ! is_dir( dirname( $download['SaveAs'] ) ) ) {
				wp_mkdir_p( dirname( $download['SaveAs'] ) );
			}

			try {
				$s3client->getObject( $download );
			} catch ( Exception $e ) {
				if ( false === $download['hidpi'] ) {
					// only log errors for non HiDPi files
					error_log( 'Error downloading ' . $download['Key'] . ' from S3: ' . $e->getMessage() );
				}
				// if S3 file doesn't exist an empty local file will be created
				// clean it up
				@unlink( $download['SaveAs'] );
			}
		}
	}

	/**
	 * Checks whether the saved licence has expired or not.
	 * Interfaces to the $licence object instead of making it public.
	 *
	 * @param bool $skip_transient_check
	 *
	 * @return bool
	 */
	public function is_valid_licence( $skip_transient_check = false ) {
		return $this->licence->is_valid_licence( $skip_transient_check );
	}

	/**
	 * Get the addons for the plugin with license information
	 *
	 * @return array
	 */
	public function get_plugin_addons() {
		return $this->licence->addons;
	}

	/**
	 * Check to see if the plugin is setup
	 *
	 * @return bool
	 */
	function is_plugin_setup() {
		if ( isset( $this->licence ) ) {
			if ( ! $this->licence->is_valid_licence() ) {
				// Empty, invalid or expired license
				return false;
			}

			if ( $this->licence->is_licence_over_media_limit() ) {
				// License key over the media library total license limit
				return false;
			}
		}

		return parent::is_plugin_setup();
	}

	/**
	 * Get the media library total for the site
	 *
	 * @param bool $skip_transient Ignore transient total
	 *
	 * @return int
	 */
	function get_media_library_total( $skip_transient = false ) {
		if ( $skip_transient || false === ( $library_total = get_site_transient( $this->licence->plugin->prefix . '_media_library_total' ) ) ) {
			$library_total = 0;
			$table_prefixes = $this->get_all_blog_table_prefixes();

			foreach ( $table_prefixes as $blog_id => $table_prefix ) {
				$total = $this->count_site_attachments( $table_prefix );
				$library_total += $total;
			}

			set_site_transient( $this->licence->plugin->prefix . '_media_library_total', $library_total, HOUR_IN_SECONDS );
		}

		return $library_total;
	}

	/**
	 * Count the attachments in a site
	 *
	 * @param string $prefix
	 *
	 * @return null|string
	 */
	function count_site_attachments( $prefix ) {
		global $wpdb;
		$sql   = "SELECT COUNT(*) FROM `{$prefix}posts` WHERE `post_type` = 'attachment'";
		$count = $wpdb->get_var( $sql );

		return $count;
	}

	/**
	 * Pro specific diagnostic info
	 */
	function diagnostic_info() {
		echo 'Pro Upgrade: ';
		echo "\r\n";
		echo 'License Status: ';
		$status      = $this->licence->is_licence_expired();
		$status_text = 'Valid';
		if ( isset( $status['errors'] ) ) {
			reset( $status['errors'] );
			$status_text = key( $status['errors'] );
		}
		echo ucwords( str_replace( '_', ' ', $status_text ) );
		echo "\r\n";
		echo 'License Constant: ';
		echo $this->licence->is_licence_constant() ? 'On' : 'Off';
		echo "\r\n\r\n";

		// Background processing jobs
		echo 'Background Jobs: ';
		$job_keys = AS3CF_Pro_Utils::get_batch_job_keys();

		global $wpdb;
		$table        = $wpdb->options;
		$column       = 'option_name';
		$value_column = 'option_value';

		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$value_column = 'meta_value';
		}

		foreach ( $job_keys as $key ) {
			$jobs = $wpdb->get_results( $wpdb->prepare( "
				SELECT * FROM {$table}
				WHERE {$column} LIKE %s
			", $key ) );

			if ( empty( $jobs ) ) {
				continue;
			}

			foreach ( $jobs as $job ) {
				echo $job->{$column};
				echo "\r\n";
				print_r( maybe_unserialize( $job->{$value_column} ) );
				echo "\r\n";
			}
		}

		echo "\r\n\r\n";
	}

}