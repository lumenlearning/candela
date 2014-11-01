<?php
/*
Plugin Name: Snapshot
Plugin URI: http://premium.wpmudev.org/project/snapshot
Description: This plugin allows you to take quick on-demand backup snapshots of your working WordPress database. You can select from the default WordPress tables as well as custom plugin tables within the database structure. All snapshots are logged, and you can restore the snapshot as needed.
Author: WPMU DEV
Version: 2.4.3.1
Author URI: http://premium.wpmudev.org/
Network: true
WDP ID: 257
Text Domain: snapshot
Domain Path: languages

Copyright 2012-2014 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
///////////////////////////////////////////////////////////////////////////

if (!defined('SNAPSHOT_I18N_DOMAIN'))
	define('SNAPSHOT_I18N_DOMAIN', 'snapshot');

/* Load important file functions (and everything that goes with it). */
require_once( ABSPATH . 'wp-admin/includes/admin.php' );

/* Load Snapshot debug library */
require_once( dirname(__FILE__) . '/lib/class_snapshot_debug.php');

/* Load the Database library. This contains all the logic to export/import the tables */
require_once( dirname(__FILE__) . '/lib/class_database_tools.php');
require_once( dirname(__FILE__) . '/lib/snapshot_utilities.php');
require_once( dirname(__FILE__) . '/lib/snapshot_admin_destinations_lib.php');
require_once( dirname(__FILE__) . '/lib/snapshot_logger_class.php');
require_once( dirname(__FILE__) . '/lib/snapshot_session_class.php');
require_once( dirname(__FILE__) . '/lib/snapshot_locker_class.php');

if (!class_exists('WPMUDEVSnapshot')) {
	class WPMUDEVSnapshot {

		var $DEBUG;
		private $_pagehooks = array();	// A list of our various nav items. Used when hooking into the page load actions.
		private $_messages 	= array();	// Message set during the form processing steps for add, edit, udate, delete, restore actions
		private $_settings	= array();	// These are global dynamic settings NOT stores as part of the config options
		private $_admin_header_error;	// Set during processing will contain processing errors to display back to the user
		private $snapshot_logger;
		private $_session;

		var $_snapshot_admin_panels;
		var $_snapshot_admin_metaboxes;

		/**
		 * The PHP5 Class constructor. Used when an instance of this class is needed.
		 * Sets up the initial object environment and hooks into the various WordPress
		 * actions and filters.
		 *
		 * @since 1.0.0
		 * @uses $this->_settings array of our settings
		 * @uses $this->_messages array of admin header message texts.
		 * @param none
		 * @return self
		 */
		function __construct() {

			$this->DEBUG									= false;
			$this->_settings['SNAPSHOT_VERSION'] 			= '2.4.3.0';

			if (is_multisite())
				$this->_settings['SNAPSHOT_MENU_URL'] 		= network_admin_url() . 'admin.php?page=';
			else
				$this->_settings['SNAPSHOT_MENU_URL'] 		= get_admin_url() . 'admin.php?page=';

			$this->_settings['SNAPSHOT_PLUGIN_URL']			= trailingslashit(WP_PLUGIN_URL) . basename( dirname(__FILE__) );
			$this->_settings['SNAPSHOT_PLUGIN_BASE_DIR']	= dirname(__FILE__);
			$this->_settings['admin_menu_label']			= __( "Snapshots", SNAPSHOT_I18N_DOMAIN ); // Used as the 'option_name' for wp_options table

			$this->_settings['options_key']					= "wpmudev_snapshot";

			$this->_settings['recover_table_prefix']		= "_snapshot_recover_";

			$this->_settings['backupBaseFolderFull']		= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['backupBackupFolderFull']		= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['backupRestoreFolderFull']		= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['destinationClasses']			= array(); 	// Will be set during page load


			$this->_settings['backupLogFolderFull']			= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['backupSessionFolderFull']		= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['backupLockFolderFull']		= ""; 	// Will be set during page load in $this->set_backup_folder();

			$this->_settings['backupURLFull']				= ""; 	// Will be set during page load in $this->set_backup_folder();
			$this->_settings['backupLogURLFull']			= ""; 	// Will be set during page load in $this->set_backup_folder();

			$this->_settings['backup_cron_hook']			= "snapshot_backup_cron"; // Used to identify WP Cron items
			$this->_settings['remote_file_cron_hook']		= "snapshot_remote_file_cron"; // Used to identify WP Cron items
			//$this->_settings['remote_file_cron_interval']	= "snapshot-15minutes";
			$this->_settings['remote_file_cron_interval']	= "snapshot-5minutes";
			$this->_admin_header_error 						= "";

			// Add support for new WPMUDEV Dashboard Notices
			global $wpmudev_notices;
			$wpmudev_notices[] = array( 'id'=> 257, 'name'=> 'Snapshot', 'screens' => array(
				'toplevel_page_snapshots_edit_panel-network',
				'snapshots_page_snapshots_new_panel-network',
				'snapshots_page_snapshots_destinations_panel-network',
				'snapshots_page_snapshots_import_panel-network',
				'snapshots_page_snapshots_settings_panel-network'
				)
			);

			include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php' );

			add_action('admin_notices', array(&$this, 'snapshot_admin_notices_proc') );
			add_action('network_admin_notices', array(&$this, 'snapshot_admin_notices_proc') );

			/* Setup the tetdomain for i18n language handling see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain */
	        load_plugin_textdomain( SNAPSHOT_I18N_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			/* Standard activation hook for all WordPress plugins see http://codex.wordpress.org/Function_Reference/register_activation_hook */
	        register_activation_hook( __FILE__, array( &$this, 'snapshot_plugin_activation_proc' ) );
	        register_deactivation_hook( __FILE__, array( &$this, 'snapshot_plugin_deactivation_proc' ) );
			//add_action('plugins_loaded', array( &$this, 'snapshot_plugin_activation_proc' ) );


			/* Register admin actions */
			add_action( 'init', array(&$this, 'snapshot_init_proc') );
			add_action( 'admin_init', array(&$this, 'snapshot_admin_init_proc') );

			add_action( 'network_admin_menu', array(&$this, 'snapshot_admin_menu_proc') );
			add_action( 'admin_menu', array(&$this, 'snapshot_admin_menu_proc') );

			/* Hook into the WordPress AJAX systems. */
			add_action('wp_ajax_snapshot_backup_ajax', array(&$this, 'snapshot_ajax_backup_proc') );
			add_action('wp_ajax_snapshot_show_blog_tables', array(&$this, 'snapshot_ajax_show_blog_tables') );
			add_action('wp_ajax_snapshot_get_blog_restore_info', array(&$this, 'snapshot_get_blog_restore_info') );
			add_action('wp_ajax_snapshot_restore_ajax', array(&$this, 'snapshot_ajax_restore_proc') );

			add_action('wp_ajax_snapshot_view_log_ajax', array(&$this, 'snapshot_ajax_view_log_proc') );
			add_action('wp_ajax_snapshot_item_abort_ajax', array(&$this, 'snapshot_ajax_item_abort_proc') );

			/* Cron related functions */
			add_filter( 'cron_schedules', 'snapshot_utility_add_cron_schedules', 99);
			add_action( $this->_settings['backup_cron_hook'], array( &$this, 'snapshot_backup_cron_proc' ) );
			add_action( $this->_settings['remote_file_cron_hook'], array( &$this, 'snapshot_remote_file_cron_proc' ) );

			/* Snapshot Destination AJAX */
			add_action( 'snapshot_register_destination', array(&$this, 'destination_register_proc'));

			// Load our Admin Panels object to handle all Admin screens
			require( 'lib/snapshot_admin_panels.php' );
			$this->_snapshot_admin_panels = new wpmudev_snapshot_admin_panels();

			// Fix home path when integrating with Domain Mapping
			add_filter( 'snapshot_home_path', array( &$this, 'snapshot_check_home_path' ) );

			// Fix DOMAIN_CURRENT_SITE if not configured
			add_filter( 'snapshot_current_domain', array( &$this, 'snapshot_check_current_domain' ) );

			// Fix PATH_CURRENT_SITE if not configured
			add_filter( 'snapshot_current_path', array( &$this, 'snapshot_check_current_path' ) );

		}

		/**
		 * The old-style PHP Class constructor. Used when an instance of this class
	 	 * is needed. If used (PHP4) this function calls the PHP5 version of the constructor.
		 *
		 * @since 1.0.0
		 * @param none
		 * @return self
		 */
	    function WPMUDEVSnapshot() {
	        $this->__construct();
	    }

		function snapshot_check_home_path( $path ) {
			if ( '/' == $path || 2 > strlen( $path ) ) {
				$path = ABSPATH;
			}
			return $path;
		}

		function snapshot_check_current_domain( $path ) {
			if( !defined( $path ) ) {
				$path = preg_replace('/(http|https):\/\/|\/$/', '', network_home_url() );
			}
			return $path;
		}

		function snapshot_check_current_path( $path ) {
			if( !defined( $path ) ) {
				$blog_details = get_blog_details();
				$path = $blog_details->path;
			}
			return $path;
		}


		function snapshot_init_proc() {

			if (!is_multisite()) {
				$role = get_role( 'administrator' );

				if ($role) {
					$role->add_cap( 'manage_snapshots_items' );
					$role->add_cap( 'manage_snapshots_destinations' );
					$role->add_cap( 'manage_snapshots_settings' );
					$role->add_cap( 'manage_snapshots_import' );
				}

				$this->load_config();
				$this->set_backup_folder();
				$this->set_log_folders();

				snapshot_destination_loader();
			} else {
				global $current_site, $current_blog;
				if ($current_site->blog_id == $current_blog->blog_id) {

					$this->load_config();
					$this->set_backup_folder();
					$this->set_log_folders();

					snapshot_destination_loader();
				}
			}
		}

		/**
		 * Called from WordPress when the admin page init process is invoked.
		 * Sets up other action and filter needed within the admin area for
		 * our page display.
		 * @since 1.0.0
		 *
		 * @param none
		 * @return unknown
		 */
		function snapshot_admin_init_proc() {

			if (is_multisite()) {
				if (!is_super_admin()) return;
				if (!is_network_admin()) return;
			} else if (current_user_can( 'manage_snapshots_items' )) {

				/* Hook into the Plugin listing display logic. This will call the function which adds the 'Settings' link on the row for our plugin. */
				add_filter( 'plugin_action_links_'. basename( dirname( __FILE__ ) ) .'/'. basename( __FILE__ ),
					array(&$this,'snapshot_plugin_settings_link_proc') );

				/* Hook into the admin bar display logic. So we can add our plugin to the admin bar menu */
				add_action( 'wp_before_admin_bar_render', array(&$this, 'snapshot_admin_bar_proc') );
			}
		}


		/**
		 * Hook to add the Snapshots menu option to the new WordPress admin
		 * bar. This function will our a menu option to the admin menu
		 * named 'Snapshots' which will link to the Tools > Snapshots page.
		 *
		 * @since 1.0.0
		 * @uses $wp_admin_bar
		 * @uses $this->_settings
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_bar_proc() {

			global $wp_admin_bar;

			$wp_admin_bar->add_menu(
				array(
					'parent' 	=> 'new-content',
					'id' 		=> 'snapshot-admin-menubar',
					'title' 	=> $this->_settings['admin_menu_label'],
					'href' 		=> 'admin.php?page=snapshots_new_panel',
					'meta' 		=> false
				)
			);
		}


		/**
		 * Called when when our plugin is activated. Sets up the initial settings
		 * and creates the initial Snapshot instance.
		 *
		 * @since 1.0.0
		 * @uses $this->config_data Our class-level config data
		 * @see $this->__construct() when the action is setup to reference this function
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_plugin_activation_proc() {
			if (((is_multisite()) && (is_main_site())) || (!is_multisite())) {

				$this->load_config();
				$this->set_backup_folder();
				$this->set_log_folders();

				$this->snapshot_scheduler();
			}
			return;
		}

		function snapshot_plugin_deactivation_proc() {

			$this->load_config();
			$this->set_backup_folder();
			$this->set_log_folders();

			$crons = _get_cron_array();
			if ($crons) {
				foreach($crons as $cron_time => $cron_set) {
					foreach($cron_set as $cron_callback_function => $cron_item) {
						if ($cron_callback_function == "snapshot_backup_cron") {
							foreach($cron_item as $cron_key => $cron_details) {
								if (isset($cron_details['args'][0])) {
									$item_key = intval($cron_details['args'][0]);
									$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($item_key)) );
									wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($item_key)) );
								}
							}
						} else if ($cron_callback_function == $this->_settings['remote_file_cron_hook']) {
							$timestamp = wp_next_scheduled( $this->_settings['remote_file_cron_hook'] );
							wp_unschedule_event($timestamp, $this->_settings['remote_file_cron_hook'] );
						}
					}
				}
			}
		}

		/**
		 * Display our message on the Snapshot page(s) header for actions taken
		 *
		 * @since 1.0.0
		 * @uses $this->_messages Set in form processing functions
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_notices_proc($local_type = '', $local_message = '') {

			// IF set during the processing logic setsp for add, edit, restore
			if ( (isset($_REQUEST['message'])) && (isset($this->_messages[$_REQUEST['message']])) ) {
				?><div id='snapshot-warning' class='updated fade'><p><?php echo $this->_messages[$_REQUEST['message']]; ?></p></div><?php
			} else if (strlen($this->_admin_header_error)) {
				// IF we set an error display in red box
				?><div id='snapshot-error' class='error'><p><?php echo $this->_admin_header_error; ?></p></div><?php
			} else if ((!empty($local_type)) && (!empty($local_message))) {
				if ($local_type == "warning") {
					?><div id='snapshot-warning' class='updated fade'><?php echo $local_message; ?></div><?php
				} else if ($local_type == "error") {
					?><div id='snapshot-error' class='error'><?php echo $local_message; ?></div><?php
				}
			}
		}


		/**
		 * Adds a 'settings' link on the plugin row
		 *
		 * @since 1.0.0
		 * @see $this->admin_init_proc where this function is referenced
		 *
		 * @param array links The default links for this plugin.
		 * @return array the same links array as was passed into function but with possible changes.
		 */
		function snapshot_plugin_settings_link_proc( $links ) {

			$settings_link = '<a href="'. $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_settings_panel">'
				. __( 'Settings', SNAPSHOT_I18N_DOMAIN ) .'</a>';
			array_unshift( $links, $settings_link );

			return $links;
		}


		/**
		 * Add the new Menu to the Tools section in the WordPress main nav
		 *
		 * @since 1.0.0
		 * @uses $this->_pagehooks
		 * @see $this->__construct where this function is referenced
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_menu_proc() {

			if (is_multisite()) {
				if (!is_super_admin()) return;
				if (!is_network_admin()) return;
			}

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_items';
			else $menu_role_cap = 'export';
			add_menu_page( 	_x("Snapshots", 'page label', SNAPSHOT_I18N_DOMAIN),
							_x("Snapshots", 'menu label', SNAPSHOT_I18N_DOMAIN),
							$menu_role_cap,
							'snapshots_edit_panel',
							array($this->_snapshot_admin_panels, 'snapshot_admin_show_items_panel'),
							plugin_dir_url( __FILE__ ) .'images/icon/greyscale-16.png'
			);

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_items';
			else $menu_role_cap = 'export';
			$this->_pagehooks['snapshots-edit'] = add_submenu_page( 'snapshots_edit_panel',
				_x('All Snapshots','page label', SNAPSHOT_I18N_DOMAIN),
				_x('All Snapshots', 'menu label', SNAPSHOT_I18N_DOMAIN),
				$menu_role_cap,
				'snapshots_edit_panel',
				array(&$this->_snapshot_admin_panels, 'snapshot_admin_show_items_panel')
			);

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_items';
			else $menu_role_cap = 'export';
			$this->_pagehooks['snapshots-new'] 	= add_submenu_page( 'snapshots_edit_panel',
				_x('Add New Snapshot', 'page label', SNAPSHOT_I18N_DOMAIN),
				_x('Add New', 'menu label', 'menu label', SNAPSHOT_I18N_DOMAIN),
				$menu_role_cap,
				'snapshots_new_panel',
				array(&$this->_snapshot_admin_panels, 'snapshot_admin_show_add_panel')
			);

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_destinations';
			else $menu_role_cap = 'export';
			$this->_pagehooks['snapshots-destinations'] = add_submenu_page('snapshots_edit_panel',
				_x('Snapshot Destinations', 'page label', SNAPSHOT_I18N_DOMAIN),
				_x('Destinations', 'menu label', SNAPSHOT_I18N_DOMAIN),
				$menu_role_cap,
				'snapshots_destinations_panel',
				'snapshot_destination_listing_panel'
				//array(&$this->_snapshot_admin_panels, 'snapshot_admin_show_destinations_all_panel')
			);

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_import';
			else $menu_role_cap = 'export';
			$this->_pagehooks['snapshots-import'] = add_submenu_page('snapshots_edit_panel',
				_x('Snapshots Import', 'page label', SNAPSHOT_I18N_DOMAIN),
				_x('Import', 'menu label', SNAPSHOT_I18N_DOMAIN),
				$menu_role_cap,
				'snapshots_import_panel',
				array(&$this->_snapshot_admin_panels, 'snapshot_admin_show_import_panel')
			);

			if (!is_multisite()) $menu_role_cap = 'manage_snapshots_settings';
			else $menu_role_cap = 'export';
			$this->_pagehooks['snapshots-settings'] = add_submenu_page('snapshots_edit_panel',
				_x('Snapshots Settings', 'page label', SNAPSHOT_I18N_DOMAIN),
				_x('Settings', 'menu label', SNAPSHOT_I18N_DOMAIN),
				$menu_role_cap,
				'snapshots_settings_panel',
				array(&$this->_snapshot_admin_panels, 'snapshot_admin_show_settings_panel')
			);

			// Hook into the WordPress load page action for our new nav items. This is better then checking page query_str values.
			add_action( 'load-'. $this->_pagehooks['snapshots-new'], 			array(&$this, 'snapshot_on_load_panels') );
			add_action( 'load-'. $this->_pagehooks['snapshots-edit'], 			array(&$this, 'snapshot_on_load_panels') );
			add_action( 'load-'. $this->_pagehooks['snapshots-settings'], 		array(&$this, 'snapshot_on_load_panels_settings') );
			add_action( 'load-'. $this->_pagehooks['snapshots-destinations'], 	array(&$this, 'snapshot_on_load_destination_panels') );
			add_action( 'load-'. $this->_pagehooks['snapshots-import'], 		array(&$this, 'snapshot_on_load_panels') );
		}


		/**
		 * Set up the common items used on all Snapshot pages.
		 *
		 * @since 1.0.0
		 * @uses none
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_on_load_panels()
		{
			/* These messages are displayed as part of the admin header message see 'admin_notices' WordPress action */
			$this->_messages['success-update'] 				= __( "The Snapshot has been updated.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-add'] 				= __( "The Snapshot has been created.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-delete'] 				= __( "The Snapshot has been deleted.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-restore'] 			= __( "The Snapshot has been restored.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-settings'] 			= __( "Settings have been update.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-runonce'] 			= __( "Item scheduled to run.", SNAPSHOT_I18N_DOMAIN );

			if ((isset($_GET['snapshot-action'])) && ($_GET['snapshot-action'] == "item-archives")) {
				require_once( dirname(__FILE__) . '/lib/class_snapshot_archives_table.php');
				$this->archives_data_items_table = new Snapshot_Archives_Data_Items_Table();

			} else if ((isset($_GET['page'])) && ($_GET['page'] == "snapshots_edit_panel")) {
				require_once( dirname(__FILE__) . '/lib/class_snapshot_items_table.php');
				$this->items_table = new Snapshot_Items_Table( $this );
			}

			$this->snapshot_scheduler();
			$this->snapshot_process_actions();
			$this->snapshot_admin_plugin_help();

			add_thickbox();

			/* enqueue our plugin styles */
			wp_enqueue_style( 'snapshots-admin-stylesheet', plugins_url( '/css/snapshots-admin-styles.css', __FILE__ ),
				false, $this->_settings['SNAPSHOT_VERSION']);

			wp_enqueue_script('snapshot-admin', plugins_url( '/js/snapshot-admin.js', __FILE__ ),
				array('jquery'), $this->_settings['SNAPSHOT_VERSION']);

			add_action('admin_footer', array(&$this, 'snapshot_admin_panels_footer'));
		}

		function snapshot_admin_panels_footer() {
			?><div style="display: none;" id="snapshot-log-view-container"><div id="snapshot-log-viewer"></div><br /><br /></div><?php
		}

		/**
		 * Set up the page with needed items for the Settings metaboxes.
		 *
		 * @since 1.0.0
		 * @uses none
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_on_load_panels_settings() {

			// Load common items first.
			$this->snapshot_on_load_panels();

			// For the Settings panel/pagew we want to use the WordPres metabox concept. This will allow for multiple
			// sections of content which are small. Plus the user can hide/close items as needed.

			// These script files are required by WordPress to enable the metaboxes to be dragged, closed, opened, etc.
			wp_enqueue_script('common');
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('postbox');

			require( 'lib/snapshot_admin_metaboxes.php' );
			$_snapshot_metaboxes = new wpmudev_snapshot_admin_metaboxes( );


			// Now add our metaboxes
			add_meta_box('snapshot-display-settings-panel-general',
				__('Folder Location', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metabox_show_folder_location'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-segment-size',
				__('Database Segment Size', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_segment_size'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-server-info',
				__('Server Info', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_server_info'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-memory-limit',
				__('Memory Limit', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_memory_limit'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			//add_meta_box('snapshot-display-settings-panel-archive-import',
			//	__('Archive Import', SNAPSHOT_I18N_DOMAIN),
			//	array($_snapshot_metaboxes, 'snapshot_metaboxes_show_archives_import'),
			//	$this->_pagehooks['snapshots-settings'],
			//	'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-global-file-excludes',
				__('Global File Exclusions', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_global_file_excludes'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-global-error-reporting',
				__('Error Reporting', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_global_error_reporting'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');

			add_meta_box('snapshot-display-settings-panel-global-zip-library',
				__('Compression Library', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_zip_library'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');
/*
			add_meta_box('snapshot-display-settings-panel-destinations-items',
				__('Destination Items', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_destination_items'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');
*/
/*
			add_meta_box('snapshot-display-settings-panel-config-export',
				__('Configuration Export', SNAPSHOT_I18N_DOMAIN),
				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_config_export'),
				$this->_pagehooks['snapshots-settings'],
				'normal', 'core');
*/


	//		if ( (is_multisite()) && (is_super_admin()) && (is_network_admin()) ) {
	//
	//			add_meta_box('snapshot-display-settings-panel-migration',
	//				__('Snapshot Migration', SNAPSHOT_I18N_DOMAIN),
	//				array($_snapshot_metaboxes, 'snapshot_metaboxes_show_migration'),
	//				$this->_pagehooks['snapshots-settings'],
	//				'normal', 'core');
	//
	//		} else

			if (!is_multisite()) {

				$config_data = get_option( 'snapshot_1.0' );
				if ($config_data) {

					add_meta_box('snapshot-display-settings-panel-migration',
						__('Snapshot Migration', SNAPSHOT_I18N_DOMAIN),
						array($snapshot_metaboxes, 'snapshot_metaboxes_show_migration'),
						$this->_pagehooks['snapshots-settings'],
						'normal', 'core');
				}
			}
		}

		/**
		 * Set up the page with needed items for the Destinations metaboxes.
		 *
		 * @since 1.0.7
		 * @uses none
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_on_load_destination_panels() {

//			if ( ! current_user_can( 'export' ) )
//				wp_die( __( 'Cheatin&#8217; uh?' ) );

//			if (!is_super_admin()) return;

			// These messages are displayed as part of the admin header message see 'admin_notices' WordPress action
			$this->_messages['success-update'] 				= __( "The Destination has been updated.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-add'] 				= __( "The Destination has been added.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-delete'] 				= __( "The Destination has been deleted.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-restore'] 			= __( "The Destination has been restored.", SNAPSHOT_I18N_DOMAIN );
			$this->_messages['success-settings'] 			= __( "Settings have been update.", SNAPSHOT_I18N_DOMAIN );

			$this->process_snapshot_destination_actions();
			$this->snapshot_admin_plugin_help();

			// enqueue our plugin styles
			wp_enqueue_style( 'snapshots-admin-stylesheet', plugins_url('/css/snapshots-admin-styles.css', __FILE__),
				false, $this->_settings['SNAPSHOT_VERSION']);

			wp_enqueue_script('snapshot-admin', plugins_url('/js/snapshot-admin.js', __FILE__),
				array('jquery'), $this->_settings['SNAPSHOT_VERSION']);
		}

		/**
		 * Plugin main action processing function. Will filter the action called then
		 * pass on to other sub-functions
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST global PHP object
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_process_actions() {

			if (is_multisite()) {
				if (!is_super_admin()) return;
			} else {
				if (!current_user_can( 'manage_snapshots_items' )) return;
			}

			//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
			//echo "_GET<pre>"; print_r($_GET); echo "</pre>";
			//die();

			$ACTION_FOUND = false;

			if (isset($_REQUEST['snapshot-action'])) {
				$snapshot_action = sanitize_text_field($_REQUEST['snapshot-action']);

				switch($snapshot_action) {

					case 'add':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'], 'snapshot-add'))
					   		return;
						else
							$this->snapshot_add_update_action_proc($_POST);

						$ACTION_FOUND = true;
						break;


					case 'delete-bulk':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-delete') )
					   		return;
						else if ( (isset($_POST['delete-bulk'])) && (count($_POST['delete-bulk'])) ) {
							$this->snapshot_delete_bulk_action_proc();
							$ACTION_FOUND = true;
						}

						break;


					case 'delete-item':
						if ( empty($_GET) || !wp_verify_nonce($_GET['snapshot-noonce-field'],'snapshot-delete-item') )
							return;
						else {
							$this->snapshot_delete_item_action_proc();
							$ACTION_FOUND = true;
						}

						break;


					case 'update':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-update') )
							die();
						else {
							$this->snapshot_add_update_action_proc($_POST);
							$ACTION_FOUND = true;
						}

						break;

					case 'runonce':
						if ( empty($_GET) || !wp_verify_nonce($_GET['snapshot-noonce-field'],'snapshot-runonce') ) {
							return;
						} else {
							if (!isset($_GET['item'])) return;

							$this->snapshot_item_run_immediate(intval($_GET['item']));

							$return_url = wp_get_referer();
							if (!isset($_GET['page'])) $_GET['page'] = 'snapshots_edit_panel';
							if ($_GET['page'] == 'snapshots_edit_panel') {
								$return_url = remove_query_arg( array('item'), $return_url );
							}
							$return_url = remove_query_arg( array('snapshot-action', 'snapshot-noonce-field'), $return_url );

							$return_url = add_query_arg('page', sanitize_text_field($_GET['page']), $return_url);
							$return_url = add_query_arg('message', 'success-runonce', $return_url);
							if ($return_url) {
								wp_redirect($return_url);
							}
							die();
						}
						break;

					case 'settings-update':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-settings') )
					   		return;
						else {
							$this->snapshot_settings_config_update();
							$ACTION_FOUND = true;
						}

						break;


					case 'download-archive':
					case 'download-log':

						if ((isset($_GET['snapshot-item'])) && (isset($_GET['snapshot-data-item']))) {
							$item_key 		= intval($_GET['snapshot-item']);
							if (isset($this->config_data['items'][$item_key])) {
								$item = $this->config_data['items'][$item_key];

								$data_item_key 	= intval($_GET['snapshot-data-item']);
								if (isset($item['data'][$data_item_key])) {

									$data_item = $item['data'][$data_item_key];

									if ($snapshot_action == 'download-archive') {

										if (isset($data_item['filename'])) {

											if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {

												$current_backupFolder = $this->snapshot_get_item_destination_path($item, $data_item);

											} else {
												$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
											}
											if (empty($current_backupFolder)) {
												$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
											}

											$current_backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
											if (file_exists($current_backupFile)) {

												header('Content-Description: Snapshot Archive File');
												header('Content-Type: application/zip');
												header('Content-Disposition: attachment; filename='. $data_item['filename']);
												header('Content-Transfer-Encoding: binary');
												header('Expires: 0');
												header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
												header('Pragma: public');
												header('Content-Length: ' . filesize($current_backupFile));

											    snapshot_utility_file_output_stream_chunked($current_backupFile);
												flush();
											  	die();
											}
										}
									} else if ($snapshot_action == 'download-log') {

										$backupLogFileFull = trailingslashit($this->snapshot_get_setting('backupLogFolderFull'))
											. $item['timestamp'] ."_". $data_item['timestamp'] .".log";

										if (file_exists($backupLogFileFull)) {

											header('Content-Description: Snapshot Log File');
											header('Content-Type: application/text');
											header('Content-Disposition: attachment; filename='. basename($backupLogFileFull));
											header('Content-Transfer-Encoding: text');
											header('Expires: 0');
											header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
											header('Pragma: public');
											header('Content-Length: ' . filesize($backupLogFileFull));

										    snapshot_utility_file_output_stream_chunked($backupLogFileFull);
											flush();
										  	die();
										}

									}
								}
							}
						}
						$ACTION_FOUND = false;

						break;


					case 'item-archives':

						$CONFIG_CHANGED = false;

						$item_key 		= intval($_GET['item']);
						if (isset($this->config_data['items'][$item_key])) {
							$item = $this->config_data['items'][$item_key];

							$action = '';
							if ((isset($_GET['action'])) && ($_GET['action'] != "-1"))
								$action = sanitize_text_field($_GET['action']);
							else if ((isset($_GET['action2'])) && ($_GET['action2'] != "-1"))
								$action = sanitize_text_field($_GET['action2']);

							//echo "action=[". $action ."]<br />";
							switch($action) {
								case 'resend':

									if ($item['destination-sync'] == "mirror") {
										$snapshot_sync_files_option = 'wpmudev_snapshot_sync_files_'. $item['timestamp'];
										delete_option($snapshot_sync_files_option);

									} else {
										$resend_data_items = intval($_REQUEST['data-item']);
										if (!is_array($resend_data_items))
											$resend_data_items = array($resend_data_items);

										foreach($resend_data_items as $data_item_key) {
											if (!isset($item['data'][$data_item_key])) {
												continue;
											}

											$data_item = $item['data'][$data_item_key];

											if (isset($data_item['filename'])) {
												$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
												$current_backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];

												if (!file_exists($current_backupFile)) {
													continue;
												}

												$data_item['destination-status'] = array();
											}
											$this->config_data['items'][$item_key]['data'][$data_item_key] = $data_item;
											$CONFIG_CHANGED = true;
										}
									}
									break;

								case 'delete':
									$delete_data_items = $_REQUEST['data-item'];
									if (!is_array($delete_data_items))
										$delete_data_items = array($delete_data_items);

									foreach($delete_data_items as $data_item_key) {
										$data_item_key = intval($data_item_key);
										if (!isset($item['data'][$data_item_key])) {
											continue;
										}

										$data_item = $item['data'][$data_item_key];

										// Delete the archive file
										if (isset($data_item['filename'])) {
											$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
											$current_backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
											if (file_exists($current_backupFile)) {
												unlink($current_backupFile);
											}
										}

										// Delete the log file
										$backupLogFileFull = trailingslashit($this->snapshot_get_setting('backupLogFolderFull'))
											. $item['timestamp'] ."_". $data_item['timestamp'] .".log";
										if (file_exists($backupLogFileFull)) {
											unlink($backupLogFileFull);
										}

										// Delete the data_item itself
										unset($this->config_data['items'][$item_key]['data'][$data_item_key]);
										$CONFIG_CHANGED = true;
									}
									break;
							}
						}
						if ($CONFIG_CHANGED == true) {
							$this->save_config();
						}

						$per_page = 20;

						if ((isset($_POST['wp_screen_options']['option']))
						 && ($_POST['wp_screen_options']['option'] == "toplevel_page_snapshots_edit_panel_network_per_page")) {

							if (isset($_POST['wp_screen_options']['value'])) {
								$per_page = intval($_POST['wp_screen_options']['value']);
								if ((!$per_page) || ($per_page < 1)) {
									$per_page = 20;
								}
								update_user_meta(get_current_user_id(), 'snapshot_data_items_per_page', $per_page);
							}
						}
						//$this->archives_data_items_table = new Snapshot_Archives_Data_Items_Table( $this );
						add_screen_option( 'per_page', array('label' => __('per Page', SNAPSHOT_I18N_DOMAIN ), 'default' => $per_page) );

						$ACTION_FOUND = true;

						break;

/*
					case 'archives-import':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-settings') )
							die();
						else {
							$this->snapshot_archives_import_proc();
							$ACTION_FOUND = true;
						}

						break;
*/
					default:

						break;
				}
			}

			if (!$ACTION_FOUND) {
				$per_page = 20;

				if ((isset($_POST['wp_screen_options']['option']))
			 	&& ($_POST['wp_screen_options']['option'] == "toplevel_page_snapshots_edit_panel_network_per_page")) {

					if (isset($_POST['wp_screen_options']['value'])) {
						$per_page = intval($_POST['wp_screen_options']['value']);
						if ((!$per_page) || ($per_page < 1)) {
							$per_page = 20;
						}
						update_user_meta(get_current_user_id(), 'snapshot_items_per_page', $per_page);
					}
				}

				add_screen_option( 'per_page', array('label' => __('per Page', SNAPSHOT_I18N_DOMAIN ), 'default' => $per_page) );

			}
		}

		/**
		 * Plugin main action processing function. Will filter the destination action called then
		 * pass on to other sub-functions
		 *
		 * @since 1.0.2
		 * @uses $_REQUEST global PHP object
		 *
		 * @param none
		 * @return none
		 */

		function process_snapshot_destination_actions() {

			//if (!is_super_admin()) return;
			if (is_multisite()) {
				if (!is_super_admin()) return;
			} else {
				if (!current_user_can( 'manage_snapshots_items' )) return;
			}

			if (isset($_REQUEST['snapshot-action'])) {

				switch(sanitize_text_field($_REQUEST['snapshot-action'])) {

					case 'delete-bulk':
						if ((!isset($_POST['snapshot-destination-type'])) || (empty($_POST['snapshot-destination-type'])))
							return;

						$destination_type = sanitize_text_field($_POST['snapshot-destination-type']);
						if (empty($_POST['snapshot-noonce-field-'. $destination_type]))
							return;

						if (!wp_verify_nonce($_POST['snapshot-noonce-field-'. $destination_type], 'snapshot-delete-destination-bulk-'. $destination_type))
					   		return;
						else
							$this->snapshot_delete_bulk_destination_proc();
						break;


					case 'delete':
						if ( empty($_GET) || !wp_verify_nonce($_GET['snapshot-noonce-field'],'snapshot-delete-destination') )
							return;
						else
							$this->snapshot_delete_destination_proc();

						break;


					case 'update':

						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-update-destination') )
					   		return;
						else
							$this->snapshot_update_destination_proc();

						break;


					case 'add':
						if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'], 'snapshot-add-destination') )
					   		return;
						else
							$this->snapshot_add_destination_proc();

						break;

					default:
						break;
				}
			}
		}

		function snaphot_show_destination_item_count($destination_key) {
			if (isset($this->config_data['items'])) {
				$destination_count = 0;
				foreach($this->config_data['items'] as $snapshot_item) {
					if ((isset($snapshot_item['destination'])) && ($snapshot_item['destination'] == $destination_key)) {
						$destination_count += 1;
					}
				}
				if ($destination_count) {
					?><a href="<?php echo $this->_settings['SNAPSHOT_MENU_URL']
					 ?>snapshots_edit_panel&amp;destination=<?php echo $destination_key; ?>"><?php echo $destination_count ?></a><?php
				} else {
					echo "0";
				}
			} else {
				echo "0";
			}
		}
		/**
		 * Setup the context help instances for the user
		 *
		 * @since 1.0.0
		 * @uses $screen global screen instance
		 * @uses $screen->add_help_tab function to add the help sections
		 * @see $this->on_load_main_page where this function is referenced
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_plugin_help() {

			global $wp_version;

			$screen = get_current_screen();
			//echo "screen<pre>"; print_r($screen); echo "</pre>";

			$screen_help_text = array();
			$screen_help_text['snapshot-help-overview'] = '<p>' . __( 'The Snapshot plugin provides the ability to create quick on-demand snapshot of your WordPress site database and files. You can create as many snapshots as needed. The Snapshot plugin also provides the ability to restore a snapshot backup.', SNAPSHOT_I18N_DOMAIN ) . '</p>';

			$screen_help_text['snapshots_new_panel'] = '<p>'. __('<strong>Name</strong> - Provide a custom name for this snapshot. Default name is "snapshot".', SNAPSHOT_I18N_DOMAIN ) .'</p>
			<p>' . __('<strong>Notes</strong> - Add some optional notes about the snapshot. Maybe some details on what plugins or theme were active. Or some note before you activate some new plugin.',SNAPSHOT_I18N_DOMAIN ) .'</p>
			<p>' . __('<strong>What to Backup</strong> - This section lists all tables for your site. Select the table you want to include in the backup. The tables are grouped by WordPress Core and Other tables. These Other tables could have been created and used by some of the plugins you installed.', SNAPSHOT_I18N_DOMAIN ) .'</p>
			<p>' . __( '<strong>When to Archive</strong> - This section shows a dropdown where you can select how often to create a backup of the selected tables. The default is "Manual". If selected will create a one time on demand backup. You can also select to schedule the backup by selecting one of the many options available. If the backup is scheduled you will also be able to set the number of archives to keep.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __('<strong>Where to save the Archive</strong> - The only available option at this time is local. This means the files will be stored on the local server. Future options will be remote systems like Dropbox, Amazon S3, FTP, etc.', SNAPSHOT_I18N_DOMAIN ) .'</p>';


			$screen_help_text['snapshots_edit_panel']['edit'] = '<p>' . __( 'On the Edit Snapshot panel you can rename or add notes to the snapshot item. Also provided is a link to the snapshot file which you can download and archive to your local system.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Name</strong> - Provide a custom name for this snapshot. Default name is "snapshot".', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Notes</strong> - Add some optional notes about the snapshot. Maybe some details on what plugins or theme were active. Or some note before you activate some new plugin.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>When to Archive</strong> - This section shows a dropdown where you can select how often to create a backup of the selected tables. The default is "Manual". If selected will create a one time on demand backup. You can also select to schedule the backup by selecting one of the many options available.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Tables in Archive</strong> - This sections lists the tables included in the snapshot archives. The table selection is set when you create a new snapshot configuration.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __('<strong>Where to save the Archive</strong> - The only available option at this time is local. This means the files will be stored on the local server. Future options will be remote systems like Dropbox, Amazon S3, FTP, etc.', SNAPSHOT_I18N_DOMAIN ) .'</p>
			<p>' . __('<strong>All Archives</strong> - This section lists the various archive files creates from this snapshot configuration. Here you can click the archive filename to download. On the same row you will also see a link to view the log entries related to the creation of this archive instance. At the bottom is a link to download the full snapshot log file.', SNAPSHOT_I18N_DOMAIN ) .'</p>';



			$screen_help_text['snapshots_edit_panel']['restore-panel'] = '<p>' . __( 'From this screen you can restore a snapshot. The restore will reload the database export into you current live site. Each table selected during the snapshot creation will be emptied before the snapshot information is loaded. It is important to understand this restore will be removing and new information added since the snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( 'On the restore screen you will see a section for "Restore Option". The details for each option are discussed below', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Turn off all plugins</strong> - As part of the restore process you can automatically deactivate all plugins. This is helpful if you had trouble with a plugin and are trying to return your site back to some stable state.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Set a theme to active</strong> - Similar to the Plugins option you can select to have a specific theme set to active as part of the restore process. Again, this is helpful if you installed a new theme that broke your site and you want to return your site back to a stable state.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __('<strong>All Archives</strong> - This section lists the various archive files creates from this snapshot configuration. From the listing select the archive to be used for the restore.', SNAPSHOT_I18N_DOMAIN ) .'</p>';



			$screen_help_text['snapshots_edit_panel']['default'] = '<p>' . __( 'All of your snapshots are listed here. Within the listing there are a number of options you can take.', SNAPSHOT_I18N_DOMAIN ) . '</p><p>' . __( '<strong>Delete</strong> - On each row you will see a checkbox. To delete one or more existing Snapshots click checkbox then click the "Delete Snapshots" button below the listing.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Edit/Restore/Delete</strong> - Hover over the Name to reveal options for Edit, Restore, Delete. The Edit option will show the Snapshot detail form where you can change many of the configuration options. The Restore option will show a form where you can select from the various restore options. The Delete option will delete this snapshot only', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Notes</strong> - The Notes columns shows the description you assigned to the Snapshot. Also in this column are the tabls included in this snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Interval</strong> - The Interval column shows how often the snapshot will be generated. When you created the snapshot instance you had the option to create a manual snapshot or schedule the snapshot to be created on certain interval (once an hour, once a day, etc.). If the interval is scheduled this column will show the estimated time for the next backup.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Destination</strong> - The Destinations column shows where the archive is stored. This can be local, Amazon S3, Dropbox, or any custom destination.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( '<strong>Archive</strong> - The Archives column shows the last snapshot archive created.', SNAPSHOT_I18N_DOMAIN ) . '</p>';



			$screen_help_text['snapshots_settings_panel'] = '<p>' . __( 'The Settings panel provides access to a number of configuration settings you can customize Snapshot to meet you site needs.', SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( "<strong>Folder Location</strong> - By default the snapshot files are stored under your site's /wp-content/uploads/ directory in a new folder named 'snapshots'. If for some reason you already use a folder of this name you can set a different folder name to be used. If you change the folder name after some snapshots have been generated these files will be moved to the new folder. Note you cannot move the folder outside the /wp-content/uploads/ directory.", SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>' . __( "<strong>Database Segment Size</strong> - The Segment Size can be defined as the number of rows to backup per table per request. The Segment Size controls the backup processing when you create a new snapshot. During the backup processing Snapshot will make a request to the server to backup each table. You can see this in the progress meters when you create a new snapshot. In most situations this backup process will attempt to backup the table in one step. But on some server configurations the timeout is set very low or the table size is very large and prevents the backup process from finishing. To control this the Snapshot backup process will breakup the requests into smaller 'chunks of work' requested to the server. ", SNAPSHOT_I18N_DOMAIN ) . '</p>
			<p>'. __("<strong>Server Info</strong> - This section provides useful details about your site configuration and should be used when contacting support.", SNAPSHOT_I18N_DOMAIN) ."</p><p>". __("<strong>Memory Limit</strong> - This section can control the amount of memory used/needed by Snapshot when created/restoring an archive.", SNAPSHOT_I18N_DOMAIN) ."</p>
			<p>". __("<strong>Archive Import</strong> - Do you have some snapshot zip file from an older version that somehow became disconnected with the settings. You can now import the zip file and snapshot will add it to the listing.", SNAPSHOT_I18N_DOMAIN) ."</p>";

			if ( version_compare( $wp_version, '3.3.0', '>' ) ) {

				$screen->add_help_tab( array(
					'id'		=> 'snapshot-help-overview',
					'title'		=> __('Overview', SNAPSHOT_I18N_DOMAIN ),
					'content'	=> $screen_help_text['snapshot-help-overview']
		    		)
				);

				if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_new_panel")) {

					$screen->add_help_tab( array(
						'id'		=> 'snapshot-help-new',
						'title'		=> __('New Snapshot', SNAPSHOT_I18N_DOMAIN ),
						'content'	=>  $screen_help_text['snapshots_new_panel']
				    	)
					);
				}

				else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_edit_panel")) {

					// Are we showing the edit form?
					if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'edit'))
					{
						$screen->add_help_tab( array(
							'id'		=> 'snapshot-help-edit',
							'title'		=> __('Edit Snapshot', SNAPSHOT_I18N_DOMAIN ),
							'content'	=> $screen_help_text['snapshots_edit_panel']['edit']
						    )
						);
					} else if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == "restore-panel")) {

						$screen->add_help_tab( array(
							'id'		=> 'snapshot-help-edit',
							'title'		=> __('Restore Snapshot', SNAPSHOT_I18N_DOMAIN ),
							'content'	=>	$screen_help_text['snapshots_edit_panel']['restore-panel']
						    )
						);

					} else {
						$screen->add_help_tab( array(
							'id'		=> 'snapshot-help-listing',
							'title'		=> __('All Snapshots', SNAPSHOT_I18N_DOMAIN ),
							'content'	=> $screen_help_text['snapshots_edit_panel']['default']
						    )
						);
					}
				} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_activity_panel")) {

					$screen->add_help_tab( array(
						'id'		=> 'snapshot-help-activity',
						'title'		=> __('Activity Log', SNAPSHOT_I18N_DOMAIN ),
						'content'	=> $screen_help_text['snapshots_activity_panel']
					    )
					);
				} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_settings_panel")) {

					$screen->add_help_tab( array(
						'id'		=> 'snapshot-help-settings',
						'title'		=> __('Settings', SNAPSHOT_I18N_DOMAIN ),
						'content'	=> $screen_help_text['snapshots_settings_panel']
					    )
					);
				}
			} else {

				if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_new_panel")) {

					add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_new_panel']);
				}
				else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_edit_panel")) {

					// Are we showing the edit form?
					if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'edit'))
					{
						add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_edit_panel']['edit']);

					} else if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == "restore-panel")) {

						add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_edit_panel']['restore-panel']);

					} else {
						add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_edit_panel']['default']
						);
					}
				} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_activity_panel")) {

					add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_activity_panel']);

				} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_settings_panel")) {

					add_contextual_help($screen, $screen_help_text['snapshot-help-overview'] . $screen_help_text['snapshots_settings_panel']);
				}

			}
		}


		/**
		 * Processing 'delete' action from form post to delete a select Snapshot.
		 * Called from $this->snapshot_process_actions()
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST['delete']
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_delete_bulk_action_proc() {

			if (!isset($_REQUEST['delete-bulk'])) {
				wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
				die();
			}

			$CONFIG_CHANGED = false;
			foreach($_REQUEST['delete-bulk'] as $snapshot_key) {

				//echo "snapshot_key=[". $snapshot_key ."]<br />";

				if ($this->snapshot_delete_item_action_proc($snapshot_key, true))
					$CONFIG_CHANGED = true;
			}

			if ($CONFIG_CHANGED) {
				$this->save_config();

				$location = add_query_arg('message', 'success-delete', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
				if ($location) {
					wp_redirect($location);
					die();
				}
			}

			wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
			die();
		}

		/**
		 * Processing 'delete-item' action from form post to delete a select Snapshot.
		 * Called from $this->snapshot_process_actions()
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST['delete']
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_delete_item_action_proc($snapshot_item_key=0, $DEFER_LOG_UPDATE=false) {

			$CONFIG_CHANGED = false;

			if (!$snapshot_item_key) {
				if (isset($_REQUEST['item'])) {
					$snapshot_item_key = intval($_REQUEST['item']);
				}
			}

			if (array_key_exists($snapshot_item_key, $this->config_data['items'])) {

				$item = $this->config_data['items'][$snapshot_item_key];
				if (isset($item['data'])) {
					foreach($item['data'] as $item_data_key => $item_data) {

						if (isset($item_data['filename'])) {
							$backupFile = trailingslashit($this->_settings['backupBaseFolderFull']) . $item_data['filename'];

							if (file_exists($backupFile))
								@unlink($backupFile);
						}

						if (isset($item_data['timestamp'])) {
							$backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull']) . $snapshot_item_key ."_". $item_data['timestamp'] .".log";
							if (file_exists($backupLogFileFull))
								@unlink($backupLogFileFull);
						}
					}
				}

				$backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull']) . $snapshot_item_key ."_backup.log";
				if (file_exists($backupLogFileFull))
					@unlink($backupLogFileFull);

				$backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull']) . $snapshot_item_key ."_restore.log";
				if (file_exists($backupLogFileFull))
					@unlink($backupLogFileFull);

				$backupLockFileFull = trailingslashit($this->_settings['backupLockFolderFull']) . $snapshot_item_key .".lock";
				if (file_exists($backupLockFileFull))
					@unlink($backupLockFileFull);

				// Note we don't check the interval because we shouldn't need to. Just unschdule the event.
				$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($snapshot_item_key)) );
				if ($timestamp) {
					wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($snapshot_item_key)) );
				}

				unset($this->config_data['items'][$snapshot_item_key]);
				$CONFIG_CHANGED = true;
			}

			if (!$DEFER_LOG_UPDATE) {
				if ($CONFIG_CHANGED) {
					$this->save_config();

					$location = add_query_arg('message', 'success-delete', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
					if ($location) {
						wp_redirect($location);
						die();
					}
				}

				wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
				die();

			} else {

				return $CONFIG_CHANGED;
			}
		}

		function snapshot_item_run_immediate($item_key) {
			wp_remote_post(get_option('siteurl'). '/wp-cron.php',
				array(
					'timeout' 		=> 	3,
					'blocking' 		=> 	false,
					'sslverify' 	=> 	false,
					'body'			=>	array(
							'nonce' => wp_create_nonce('WPMUDEVSnapshot'),
							'type'			=>	'start'
						),
					'user-agent'	=>	'WPMUDEVSnapshot'
				)
			);
			wp_schedule_single_event( time(), $this->_settings['backup_cron_hook'], array(intval($item_key)) );
		}

		/**
		 * Processing 'add' action from form post to create a new Snapshot.
		 * Called from $this->snapshot_process_actions()
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST['add']
		 *
		 * @param bool $redirect In normal cases we want to redirect to the listing
		 * page after adding a new snapshot. But when activating the plugin we do
		 * not want to redirect from the main plugin listing.
		 *
		 * @return none
		 */
		function snapshot_add_update_action_proc($_post_array) {

			//echo "_post_array<pre>"; print_r($_post_array); echo "</pre>";
			//die();

			$CONFIG_CHANGED = false;

			if ($_post_array['snapshot-action'] == "add") {
				$item = array();

				if (isset($_post_array['snapshot-item'])) {
					$item['timestamp']		= 	intval($_post_array['snapshot-item']);
				} else {
					$time_key = time();
					$item['timestamp']		= 	$time_key;
				}

				if (isset($_post_array['snapshot-blog-id']))
					$item['blog-id'] 	= intval($_post_array['snapshot-blog-id']);
				else
					$item['blog-id'] 	= 0;

			} else if ($_POST['snapshot-action'] == "update") {
				$item_key = intval($_post_array['snapshot-item']);
				if (!isset($this->config_data['items'][$item_key]))
					die();

				$item = $this->config_data['items'][$item_key];

				if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
					if (isset($_post_array['snapshot-blog-id']))
						$item['blog-id'] 	= intval($_post_array['snapshot-blog-id']);
				}

			}

			if (isset($_post_array['snapshot-name']))
				$item['name']		= 	sanitize_text_field($_post_array['snapshot-name']);
			else
				$item['name']		= 	"";

			if (isset($_post_array['snapshot-notes']))
				$item['notes']		= 	esc_textarea($_post_array['snapshot-notes']);
			else
				$item['notes']		= 	"";

			$current_user 	= wp_get_current_user();
			if ((isset($current_user->ID)) && (intval($current_user->ID)))
				$item['user']		=	$current_user->ID;
			else
				$item['user']		=	0;


			$item['tables-option'] 	= 	"none";
			$item['tables-sections'] = 	array();
			$item['tables-count'] 	= 	0;

			if (isset($_post_array['snapshot-tables-option'])) {

				$item['tables-option'] 	= 	$_post_array['snapshot-tables-option'];
				if ($item['tables-option'] == "none") {
					// Nothing to see here.
				} else if ($item['tables-option'] == "all") {
					// Nothing to see here.
				} else if ($item['tables-option'] == "selected") {

					// The form submit when not immediate will be this form element.
					if (isset($_post_array['snapshot-tables'])) {
						$snapshot_tables_array = array();
						foreach($_post_array['snapshot-tables'] as $table_section => $table_set) {
							$snapshot_tables_array = array_merge($snapshot_tables_array, $table_set);
						}
						$_post_array['snapshot-tables-array'] = $snapshot_tables_array;
					}

					// snapshot-tables-array will either be populated from above OR from the AJAX form processing
					if (isset($_post_array['snapshot-tables-array'])) {

						$item['tables-sections'] = array();

						$tables_sections = snapshot_utility_get_database_tables($item['blog-id']);
						if ($tables_sections) {
							foreach($tables_sections as $section => $tables) {
								if (count($tables)) {
									$item['tables-sections'][$section] = array_intersect($tables, $_post_array['snapshot-tables-array']);
								}
								else
									$item['tables-sections'][$section] = array();
							}
						}

					} else if (isset($_post_array['snapshot-tables-sections'])) {

						$item['tables-sections'] = $_post_array['snapshot-tables-sections'];
					}
				}
			}

			$item['files-option'] 	= 	"none";
			$item['files-sections'] = 	array();
			$item['files-ignore'] 	= 	array();
			$item['files-count'] 	= 	0;

			if (isset($_post_array['snapshot-files-option'])) {

				$item['files-option'] 	= 	$_post_array['snapshot-files-option'];
				if ($item['files-option'] == 'none') {
					// Nothing to see here.
				} else if ($item['files-option'] == 'all') {
					if (is_main_site($item['blog-id'])) {
						$item['files-sections'] = 	array('themes', 'plugins', 'media');
					} else {
						$item['files-sections'] = 	array('media');
					}
				} else if ($item['files-option'] == 'selected') {

					if (is_main_site($item['blog-id'])) {
						if (isset($_post_array['snapshot-files-sections'])) {
							$item['files-sections'] = $_post_array['snapshot-files-sections'];
						} else {
							$item['files-sections'] = 	array('themes', 'plugins', 'media');
						}
					} else {
						if (isset($_post_array['snapshot-files-sections'])) {
							$item['files-sections'] = $_post_array['snapshot-files-sections'];
						} else {
							$item['files-sections'] = 	array('media');
						}
					}
				}

				if ((isset($_post_array['snapshot-files-ignore'])) && (strlen($_post_array['snapshot-files-ignore']))) {
					$files_ignore = explode("\n", $_post_array['snapshot-files-ignore']);
					if ((is_array($files_ignore)) && (count($files_ignore))) {
						foreach($files_ignore as $file_ignore) {
							$file_ignore = esc_attr(strip_tags(trim($file_ignore)));
							if (!empty($file_ignore))
								$item['files-ignore'][] = $file_ignore;
						}
					}
				}
			}

			$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($item['timestamp'])) );
			if ($timestamp)
				wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($item['timestamp'])) );

			if (isset($_post_array['snapshot-interval'])) {
				$item['interval'] 	= 	sanitize_text_field($_post_array['snapshot-interval']);

				$item['interval-offset']	= "";
				if ($item['interval'] == "snapshot-5minutes") {
					$item['interval-offset']	= "";
				} else if ($item['interval'] == "snapshot-hourly") {
					if (isset($_post_array['snapshot-interval-offset']['snapshot-hourly'])) {
						$item['interval-offset']['snapshot-hourly'] = $_post_array['snapshot-interval-offset']['snapshot-hourly'];
					}
				} else if (($item['interval'] == "snapshot-daily") || ($item['interval'] == "snapshot-twicedaily")) {
					if (isset($_post_array['snapshot-interval-offset']['snapshot-daily'])) {
						$item['interval-offset']['snapshot-daily'] = $_post_array['snapshot-interval-offset']['snapshot-daily'];
					}
				} else if (($item['interval'] == "snapshot-weekly") || ($item['interval'] == "snapshot-twiceweekly")) {
					if (isset($_post_array['snapshot-interval-offset']['snapshot-weekly'])) {
						$item['interval-offset']['snapshot-weekly'] = $_post_array['snapshot-interval-offset']['snapshot-weekly'];
					}
				} else if (($item['interval'] == "snapshot-monthly") || ($item['interval'] == "snapshot-twicemonthly")) {
					if (isset($_post_array['snapshot-interval-offset']['snapshot-monthly'])) {
						$item['interval-offset']['snapshot-monthly'] = $_post_array['snapshot-interval-offset']['snapshot-monthly'];
					}
				}
			} else {
				$item['interval'] 			= 	"";
				$item['interval-offset']	= 	"";
			}

			if ((!isset($_post_array['snapshot-destination'])) || (empty($_post_array['snapshot-destination'])))
				$_post_array['snapshot-destination'] = 'local';
			else
				$_post_array['snapshot-destination'] = sanitize_text_field($_post_array['snapshot-destination']);

			// If the form destination is empty then we are storing locally. So check the destination-directory
			// value and move the local file to that location
			if ($_post_array['snapshot-destination'] == "local") {

	//			if (!isset($item['destination-directory']))
	//				$item['destination-directory'] = '';

				$item_tmp = array();
				$item_tmp['destination'] 			= sanitize_text_field($_post_array['snapshot-destination']);
				$item_tmp['blog-id'] 				= $item['blog-id'];
				$item_tmp['timestamp'] 				= intval($_post_array['snapshot-item']);
				$item_tmp['destination-directory'] 	= sanitize_text_field(trim($_post_array['snapshot-destination-directory']));
				//echo "item_tmp<pre>"; print_r($item_tmp); echo "</pre>";

				$new_backupFolder = $this->snapshot_get_item_destination_path($item_tmp);
				if (!strlen($new_backupFolder))
					$new_backupFolder = $this->_settings['backupBaseFolderFull'];

				if ((isset($item['data'])) && (count($item['data']))) {

					foreach($item['data'] as $data_item_idx => $data_item) {

						if ( (!isset($data_item['destination'])) || ($data_item['destination'] != $item_tmp['destination']) )
							continue;

						if (!isset($data_item['destination-directory']))
							$data_item['destination-directory'] = '';

						if ($data_item['destination-directory'] !== $item_tmp['destination-directory']) {
							$current_backupFolder = $this->snapshot_get_item_destination_path($item_tmp, $data_item, false);
							if (empty($current_backupFolder))
								$current_backupFolder = $this->_settings['backupBaseFolderFull'];

							// If destination is empty then this is a local file.
							if (empty($item['destination'])) {
								$currentFile 	= trailingslashit($current_backupFolder) . $data_item['filename'];
								$newFile		= trailingslashit($new_backupFolder) . $data_item['filename'];

								if ((file_exists($currentFile)) && (!file_exists($newFile))) {
									$rename_ret = rename($currentFile, $newFile);
									if ($rename_ret === true) {
										$item['data'][$data_item_idx]['destination-directory'] = $item_tmp['destination-directory'];
									}
								}
							} else {
								// Else we just set the directoy of the remote destination item. It is up to the user
								// to update/move the remote files to the new path.
								$item['data'][$data_item_idx]['destination-directory'] = $item_tmp['destination-directory'];
							}
						}
					}
				}

				$item['destination-directory'] 	= 	sanitize_text_field(trim($_post_array['snapshot-destination-directory']));
				$item['destination'] 			= 	sanitize_text_field($_post_array['snapshot-destination']);
				$item['destination-sync']		=	'archive';

			} else {

				$item_tmp = array();
				$item_tmp = array();
				$item_tmp['destination'] 			= sanitize_text_field($_post_array['snapshot-destination']);

				if (isset($_post_array['snapshot-blog-id'])) {
					$item_tmp['blog-id'] 				= intval($_post_array['snapshot-blog-id']);
				}

				$item_tmp['timestamp'] 				= intval($_post_array['snapshot-item']);
				$item_tmp['destination-directory'] 	= "";

				$new_backupFolder = $this->_settings['backupBaseFolderFull'];

				if ((isset($item['data'])) && (count($item['data']))) {

					foreach($item['data'] as $data_item_idx => $data_item) {

						//if ($data_item['destination'] != $item_tmp['destination'])
						//	continue;

						if (!isset($data_item['destination-directory']))
							$data_item['destination-directory'] = '';

						if ($data_item['destination-directory'] !== $item_tmp['destination-directory']) {
							$current_backupFolder = $this->snapshot_get_item_destination_path($item_tmp, $data_item, false);
							if (empty($current_backupFolder))
								$current_backupFolder = $this->_settings['backupBaseFolderFull'];

							// If destination is empty then this is a local file.
							if (($item['destination']) || ($item['destination'] == "local")) {
								$currentFile 	= trailingslashit($current_backupFolder) . $data_item['filename'];
								$newFile		= trailingslashit($new_backupFolder) . $data_item['filename'];

								if ((file_exists($currentFile)) && (!file_exists($newFile))) {
									$rename_ret = rename($currentFile, $newFile);
									if ($rename_ret === true) {
										$item['data'][$data_item_idx]['destination-directory'] = $item_tmp['destination-directory'];
									}
								}
							} else {
								// Else we just set the directoy of the remote destination item. It is up to the user
								// to update/move the remote files to the new path.
								$item['data'][$data_item_idx]['destination-directory'] = $item_tmp['destination-directory'];
							}
						}
					}
				}
				$item['destination-directory'] 	= 	sanitize_text_field(trim($_post_array['snapshot-destination-directory']));
				$item['destination'] 			= 	sanitize_text_field($_post_array['snapshot-destination']);

				$item['destination-sync']		=	'archive';
				$item['destination-sync']		=	'archive';
				if (isset($this->config_data['destinations'][$item['destination']])) {
					$destination = $this->config_data['destinations'][$item['destination']];
					if ((isset($destination['type'])) && ($destination['type'] == "dropbox")) {
						$item['destination-sync']		=	sanitize_text_field($_post_array['snapshot-destination-sync']);
					}
				}
			}

			if (isset($_POST['snapshot-archive-count']))
				$item['archive-count'] 	= 	intval($_post_array['snapshot-archive-count']);
			else
				$item['archive-count'] 	= 	"0";

			$item['destination-directory'] = str_replace('\\', '/', stripslashes($item['destination-directory']));
			//echo "item<pre>"; print_r($item); echo "</pre>";
			//die();

			// Saves the selected tables to our config. So next time the user goes to make a snapshot these will be pre-selected.
			// if (count($item['tables-sections']))
			//	$this->config_data['config']['tables_last'][$item['blog-id']] = $item['tables-sections'];

			//$this->config_data['items'][$item['timestamp']] = $item;
			//$this->save_config();
			$this->add_update_config_item($item['timestamp'], $item);

			//if ($item['interval'] == "immediate") {
			//	$this->snapshot_item_run_immediate($item['timestamp']);
			//}

	  		if (defined('DOING_AJAX') && DOING_AJAX) {
				return $item['timestamp'];
			} else {
				$location = add_query_arg('message', 'success-add', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
				if ($location) {
					wp_redirect($location);
				}
			}
		}


		/**
		 * Processing 'settings-update' action from form post to to update plugin global settings.
		 * Called from $this->snapshot_process_actions()
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST['backupFolder']
		 * @uses $this->config_data['config']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_settings_config_update()
		{
			$CONFIG_CHANGED = false;

			//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
			//die();

			if (isset($_REQUEST['backupFolder'])) {

				$_oldbackupFolderFull = trailingslashit(sanitize_text_field($this->_settings['backupBaseFolderFull']));

				// Because this needs to be universal we convert Windows paths entered be the user into proper PHP forward slash '/'
				$_REQUEST['backupFolder'] = str_replace('\\', '/', stripslashes(sanitize_text_field($_REQUEST['backupFolder'])));

				if ((substr($_REQUEST['backupFolder'], 0, 1) == "/")
				 || (substr($_REQUEST['backupFolder'], 1, 2) == ":/")) { // Setting Absolute path!

					$backupFolder = sanitize_text_field($_REQUEST['backupFolder']);
					$_newbackupFolderFull = $backupFolder;
				} else {
					$this->config_data['config']['absoluteFolder'] = false;

					$backupFolder = esc_attr(basename(untrailingslashit($_REQUEST['backupFolder'])));

					$wp_upload_dir = wp_upload_dir();
					$wp_upload_dir['basedir'] = str_replace('\\', '/', $wp_upload_dir['basedir']);
					$_newbackupFolderFull = trailingslashit($wp_upload_dir['basedir']) . $backupFolder;

					if (file_exists($_newbackupFolderFull)) {
						/* If here we cannot create the folder. So report this via the admin header message and return */
						$this->_admin_header_error .= __("ERROR: The new Snapshot folder already exists. ",
						 SNAPSHOT_I18N_DOMAIN) ." ". $_newbackupFolderFull;
						return;
					}
				}

				if ((isset($backupFolder)) && (strlen($backupFolder))) {
					if ($_oldbackupFolderFull != $_newbackupFolderFull) {
						$rename_ret = rename($_oldbackupFolderFull, $_newbackupFolderFull);
						if ($rename_ret === true) {
							$CONFIG_CHANGED = true;

							// Now that the physical files have been changed update our settings.
							$this->config_data['config']['backupFolder'] = $backupFolder;
							$this->set_backup_folder();
							$this->set_log_folders();
						}
					}
				}
			}


			if (isset($_REQUEST['segmentSize'])) {

				$segmentSize = intval($_REQUEST['segmentSize']);
				if (($segmentSize > 0) && ($segmentSize !== $this->config_data['config']['segmentSize'])) {
					$this->config_data['config']['segmentSize'] = $segmentSize;
					$CONFIG_CHANGED = true;
				}
			}

			if ((isset($_REQUEST['snapshot-sub-action'])) && ($_REQUEST['snapshot-sub-action'] == "memoryLimit")) {

				if (isset($_REQUEST['memoryLimit'])) {

					$this->config_data['config']['memoryLimit'] = sanitize_text_field($_REQUEST['memoryLimit']);
					$CONFIG_CHANGED = true;
				}
			}

			if (isset($_REQUEST['filesIgnore'])) {

				$files_ignore = explode("\n", $_REQUEST['filesIgnore']);
				if ((is_array($files_ignore)) && (count($files_ignore))) {
					foreach($files_ignore as $idx => $file_ignore) {
						$file_ignore = sanitize_text_field(trim($file_ignore));
						if (!empty($file_ignore)) {
							$files_ignore[$idx] = $file_ignore;
						}
					}

					$this->config_data['config']['filesIgnore'] = $files_ignore;
					$CONFIG_CHANGED = true;
				}
			}

			if (isset($_REQUEST['migration'])) {
				global $wpdb;

				$CONFIG_CHANGED = $this->snapshot_migrate_config_proc($wpdb->blogid);
			}

			if ((isset($_REQUEST['snapshot-sub-action'])) && ($_REQUEST['snapshot-sub-action'] == "errorReporting")) {

				if ( (isset($_REQUEST['errorReporting'])) && (count($_REQUEST['errorReporting'])) ) {
					$this->config_data['config']['errorReporting'] = $_REQUEST['errorReporting'];
					$CONFIG_CHANGED = true;
				} else if ( (isset($this->config_data['config']['errorReporting'])) && (count($this->config_data['config']['errorReporting'])) ) {
					$this->config_data['config']['errorReporting'] = array();
					$CONFIG_CHANGED = true;
				}
			}

			if ((isset($_REQUEST['zipLibrary'])) && ($_REQUEST['snapshot-sub-action'] == "zipLibrary")) {

				if (isset($_REQUEST['zipLibrary'])) {
					$this->config_data['config']['zipLibrary'] = sanitize_text_field($_REQUEST['zipLibrary']);
					$CONFIG_CHANGED = true;
				}
			}


			if ($CONFIG_CHANGED) {
				$this->save_config();
			}

			$location = add_query_arg('message', 'success-settings', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_settings_panel');
			if ($location) {
				wp_redirect($location);
				die();
			}
			die();
		}

		/**
		 * Utility function to read our config array from the WordPress options table. This
		 * function also will initialize needed instances of the array if needed.
		 *
		 * @since 1.0.0
		 * @uses $this->_settings
		 * @uses $this->config_data
		 *
		 * @return none
		 */
		function load_config() {

			global $wpdb;

			if (is_multisite()) {
				//$this->config_data = get_blog_option($wpdb->blogid, $this->_settings['options_key']);
				$blog_prefix = $wpdb->get_blog_prefix( $wpdb->blogid );
				$row = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$blog_prefix}options
						WHERE option_name = %s", $this->_settings['options_key'] ) );
				if ($row)
					$this->config_data = unserialize($row[0]);

			} else {
				//$this->config_data = get_option($this->_settings['options_key']);
				$row = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM $wpdb->options
					WHERE option_name = %s LIMIT 1", $this->_settings['options_key'] ) );
				if ($row)
					$this->config_data = unserialize($row[0]);
			}

			if (empty($this->config_data)) {

				$snapshot_legacy_versions = array('2.0.3', '2.0.2', '2.0.1', '2.0', '1.0.2');
				foreach($snapshot_legacy_versions as $snapshot_legacy_version) {
					$snapshot_options_key = "snapshot_". $snapshot_legacy_version;

					if (is_multisite())
						$this->config_data = get_blog_option( $wpdb->blogid, $snapshot_options_key );
					else
						$this->config_data = get_option( $snapshot_options_key );

					if (!empty($this->config_data)) {
						$this->config_data['version'] = $snapshot_legacy_version;
						break;
					}
				}
			}

			if (!isset($this->config_data['items']))
				$this->config_data['items'] = array();
			else
				krsort($this->config_data['items']); /* If we do have items sort them here instead of later. */

			if (!isset($this->config_data['config']))
				$this->config_data['config'] = array();

			if (!isset($this->config_data['config']['segmentSize']))
				$this->config_data['config']['segmentSize'] = 1000;

			if ($this->config_data['config']['segmentSize'] < 1)
				$this->config_data['config']['segmentSize'] = 1000;

			if ((!isset($this->config_data['config']['memoryLimit'])) || (empty($this->config_data['config']['memoryLimit']))) {

				$memory_limits 					= array();
				$memory_limit = ini_get('memory_limit');
				$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);

				$memory_limit = WP_MEMORY_LIMIT;
				$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);

				$memory_limit = WP_MAX_MEMORY_LIMIT;
				$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);

				arsort($memory_limits);
				foreach($memory_limits as $memory_key => $memory_value) {
					$this->config_data['config']['memoryLimit'] = $memory_key;
					break;
				}
			}

			if (!isset($this->config_data['config']['errorReporting'])) {
				$this->config_data['config']['errorReporting'] = array();
				$this->config_data['config']['errorReporting'][E_ERROR] = array();
				$this->config_data['config']['errorReporting'][E_ERROR]['stop'] = true;
				$this->config_data['config']['errorReporting'][E_ERROR]['log'] = true;

				$this->config_data['config']['errorReporting'][E_WARNING] = array();
				$this->config_data['config']['errorReporting'][E_WARNING]['log'] = true;

				$this->config_data['config']['errorReporting'][E_NOTICE] = array();
				$this->config_data['config']['errorReporting'][E_NOTICE]['log'] = true;
			}

			if (!isset($this->config_data['config']['zipLibrary']))
				$this->config_data['config']['zipLibrary'] = 'PclZip';
			if (($this->config_data['config']['zipLibrary'] == 'ZipArchive') && (!class_exists('ZipArchive')))
				$this->config_data['config']['zipLibrary'] = 'PclZip';


			if (!isset($this->config_data['config']['absoluteFolder']))
				$this->config_data['config']['absoluteFolder'] = false;

			if ( (!isset($this->config_data['config']['backupFolder'])) || (!strlen($this->config_data['config']['backupFolder'])) )
				$this->config_data['config']['backupFolder'] = "snapshots";

			// Container for Destinations S3, FTP, etc.
			if (!isset($this->config_data['destinations']))
				$this->config_data['destinations'] = array();

			if (!isset($this->config_data['destinations']['local'])) {
				$this->config_data['destinations']['local'] = array(
					'name'	=> 	__('Local Server', SNAPSHOT_I18N_DOMAIN),
					'type'	=>	'local'
				);
			}

			/* Set the default table to be part of the snapshot */
			if (!isset($this->config_data['config']['tables_last']))
				$this->config_data['config']['tables_last'] = array();

			// Remove the activity section. No longer used.
			if (isset($this->config_data['activity'])) {
				unset($this->config_data['activity']);
			}

			// The tables needs to be converted. In earlier versions of this plugin the table array was not aware of the blog_id.
			// We need to keep a set for each blog_id. So assume the current version is for the current blog.
			if (isset($this->config_data['config']['tables_last'][0])) {
				$tables_last = $this->config_data['config']['tables_last'];
				unset($this->config_data['config']['tables_last']);
				$this->config_data['config']['tables_last'] = array();
				$this->config_data['config']['tables_last'][$wpdb->blogid] = $tables_last;
			}

			// If we don't have the 'version' config then assume it is the previous version.
			if (!isset($this->config_data['version']))
				$this->config_data['version'] = "1.0.2";

			if ( version_compare( $this->config_data['version'], $this->_settings['SNAPSHOT_VERSION'], '<' ) ) {

				//echo "config version<pre>"; print_r($this->config_data['version']); echo "</pre>";
				//echo "plugin version<pre>"; print_r($this->_settings['SNAPSHOT_VERSION']); echo "</pre>";
				//die();

				$this->set_backup_folder();
				$this->set_log_folders();

				// During the conversion we needs to update the manifest.txt file within the archive. Tricky!
				$restoreFolder = trailingslashit($this->_settings['backupRestoreFolderFull']) ."_imports";
				wp_mkdir_p($restoreFolder);

				/*
				if ($this->config_data['version'] == "1.0.2") {
					foreach($this->config_data['items'] as $item_idx => $item) {

						// We change blog_id to blog-id
						if (!isset($item['blog-id'])) {
							if (isset($item['blog_id'])) {
								$item['blog-id'] = $item['blog_id'];
								unset($item['blog_id']);
							}
						}

						$all_tables_sections = snapshot_utility_get_database_tables($item['blog-id']);

						if (!isset($item['tables-option'])) {
							$item['tables-count'] = '';

							if ($all_tables_sections) {
								$all_tables_option = true;
								foreach($all_tables_sections as $section => $section_tables) {
									if (count($section_tables)) {
										$item['tables-sections'][$section] = array_intersect_key($section_tables, $item['tables']);
										$item['tables-count'] += count($item['tables-sections'][$section]);

										if (count($item['tables-sections'][$section]) != count($section_tables))
											$all_tables_option = false;
									}
									else
										$item['tables_sections'][$section] = array();
								}

								if ($all_tables_option == true) {
									$item['tables-option'] = 'all';
								} else {
									$item['tables-option'] = 'selected';
								}
							}
						}

						$item['files-option'] 	= "none";
						$item['files-sections'] = array();
						$item['files-count']	= 0;

						if (!isset($item['destination']))
							$item['destination'] = 'local';
						if (!isset($item['destination-directory']))
							$item['destination-directory'] = '';

						unset($item['tables']);

						if (!isset($item['interval']))
							$item['interval'] = '';


						if (isset($item['data'])) {
							foreach($item['data'] as $item_data_idx => $item_data) {

								if (!isset($item_data['blog-id'])) {
									if (isset($item_data['blog_id'])) {
										$item_data['blog-id'] = $item_data['blog_id'];
										unset($item_data['blog_id']);
									}
								}

								if (!isset($item_data['destination']))
									$item_data['destination'] = 'local';
								if (!isset($item_data['destination-directory']))
									$item_data['destination-directory'] = '';

								if (!isset($item_data['tables-option'])) {
									$item_data['tables-count'] = '';

									if ($all_tables_sections) {
										$all_tables_option = true;

										foreach($all_tables_sections as $section => $section_tables) {
											if (count($section_tables)) {
												$item_data['tables-sections'][$section] = array_intersect_key($section_tables, $item_data['tables']);
												$item_data['tables-count'] += count($item['tables-sections'][$section]);

												if (count($item_data['tables-sections'][$section]) != count($section_tables))
													$all_tables_option = false;
											}
											else
												$item_data['tables-sections'][$section] = array();
										}

										if ($all_tables_option == true) {
											$item_data['tables-option'] = 'all';
										} else {
											$item_data['tables-option'] = 'selected';
										}
									}
								}
								unset($item_data['tables']);

								$item_data['files-option'] 	= "none";
								$item_data['files-sections'] = array();
								$item_data['files-count']	= 0;

								if ((isset($item_data['filename'])) && (strlen($item_data['filename']))) {
									$backupZipFile = trailingslashit($this->_settings['backupBaseFolderFull']) . $item_data['filename'];
									if (file_exists($backupZipFile)) {

										// Get the file size
										$item_data['file_size'] = filesize($backupZipFile);

										// Now we do a hard task and extract the minifest.txt file then convert it to the new format. Tricky X 2!
										if (!defined('PCLZIP_TEMPORARY_DIR'))
											define('PCLZIP_TEMPORARY_DIR', trailingslashit($this->_settings['backupBackupFolderFull']) . $item_key."/");
										if (!class_exists('class PclZip'))
											require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');

										$zipArchive = new PclZip($backupZipFile);
										$zip_contents = $zipArchive->listContent();
										if (($zip_contents) && (!empty($zip_contents))) {

											foreach($zip_contents as $zip_index => $zip_file_info) {
												if ($zip_file_info['stored_filename'] == "snapshot_manifest.txt") {

													snapshot_utility_recursive_rmdir($restoreFolder);
													$extract_files = $zipArchive->extractByIndex($zip_index, $restoreFolder);
													if ($extract_files) {

														$snapshot_manifest_file = trailingslashit($restoreFolder) . 'snapshot_manifest.txt';
														if (file_exists($snapshot_manifest_file)) {

															$manifest_data = snapshot_utility_consume_archive_manifest($snapshot_manifest_file);

															$manifest_data['SNAPSHOT_VERSION'] = $this->_settings['SNAPSHOT_VERSION'];

															$manifest_data['WP_UPLOAD_PATH'] = snapshot_utility_get_blog_upload_path(intval($item['blog-id']));

															$item_tmp = $item;
															unset($item_tmp['data']);
															$item_tmp['data'] = array();
															$item_tmp['data'][$item_data_idx] = $item_data;
															$manifest_data['ITEM'] = $item_tmp;

															$manifest_data['TABLES'] = $item_data['tables-sections'];
															//echo "manifest_data<pre>"; print_r($manifest_data); echo "</pre>";

															if (snapshot_utility_create_archive_manifest($manifest_data, $snapshot_manifest_file)) {
																$zipArchive->deleteByIndex($zip_index);

																$archiveFiles = array($snapshot_manifest_file);
																$zipArchive->add($archiveFiles,
																	PCLZIP_OPT_REMOVE_PATH, $restoreFolder,
																	PCLZIP_OPT_TEMP_FILE_THRESHOLD, 10);

																foreach($archiveFiles as $archiveFile) {
																	@unlink($archiveFile);
																}
															}
														}
													}
													break;
												}
											}
										}
									}
								}

								$item['data'][$item_data_idx] = $item_data;
							}
							krsort($item['data']);
						}

						// Convert the logs...

						$backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull']) . $item['timestamp'] ."_backup.log";
						if (file_exists($backupLogFileFull)) {
							$log_entries = snapshot_utility_get_archive_log_entries($backupLogFileFull);
							if (($log_entries) && (count($log_entries))) {
								foreach($log_entries as $log_key => $log_data) {
									foreach($item['data'] as $item_data_idx => $item_data) {
										if ($log_key == $item_data['filename']) {
											$new_backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull']) .
												$item['timestamp'] ."_". $item_data_idx .".log";
											file_put_contents($new_backupLogFileFull, implode("\r\n", $log_data));
										}
									}
								}
							}
							@unlink($backupLogFileFull);
						}

						$this->config_data['items'][$item_idx] = $item;
					}

					// Now convert the Last Tables config section
					if (isset($this->config_data['config']['tables_last'])) {
						foreach ($this->config_data['config']['tables_last'] as $blog_id => $item_tables) {

							$all_tables_sections = snapshot_utility_get_database_tables($blog_id);
							if ($all_tables_sections) {
								$item_section_tables = array();
								foreach($all_tables_sections as $section => $section_tables) {
									if (count($section_tables))
										$item_section_tables[$section] = array_intersect_key($section_tables, $item_tables);
									else
										$item_section_tables[$section] = array();
								}
							}
							$this->config_data['config']['tables_last'][$blog_id] = $item_section_tables;
						}
					}
				}
				*/
/*
				foreach($this->config_data['items'] as $item_idx => $item) {
					if (!isset($item['data'])) continue;

					foreach($item['data'] as $item_data_idx => $item_data) {
						if (!isset($item_data['destination-status'])) continue;

						foreach($item_data['destination-status'] as $destination_idx => $destination_status) {
							if (isset($destination_status['sendFileStatus'])) continue;

							if ( (isset($destination_status['responseArray'])) && (count($destination_status['responseArray']))
							  && (isset($destination_status['errorStatus'])) && ($destination_status['errorStatus'] != true) ) {

								// Assumed! Since we have responseArray items and the errorStatus is NOT set. Assume success
								$this->config_data['items'][$item_idx]['data'][$item_data_idx]['destination-status'][$destination_idx]['sendFileStatus'] = true;
							}
						}
					}
				}
*/
				//echo "config_data<pre>"; print_r($this->config_data['items']); echo "</pre>";

				//die();

				$this->config_data['version'] = $this->_settings['SNAPSHOT_VERSION'];
				$this->save_config();
			}
		}


		/**
		 * Utility function to save our config array to the WordPress options table.
		 *
		 * @since 1.0.0
		 * @uses $this->_settings
		 * @uses $this->config_data
		 *
		 * @param bool $force_save if set to true will first delete the option from the
		 * global options array then re-add it. This is needed after a restore action where
		 * the restored table my be the wp_options. In this case we need to re-add out own
		 * plugins config array. When we call update_option() WordPress will not see a change
		 * when it compares our config data to its own internal version so the INSERT will be skipped.
		 * If we first delete the option from the WordPress internal version this will force
		 * WordPress to re-insert our plugin option to the MySQL table.
		 * @return none
		 */
		function save_config($force_save = false) {

			global $wpdb;

			// Note below for multisite we hard code the blog id to '1'. This is because the plugin should only ever
			// save to the primary site.
			if ($force_save) {
				if (is_multisite())
					delete_blog_option($wpdb->blogid, $this->_settings['options_key']);
				else
					delete_option($this->_settings['options_key']);
			}

			if (is_multisite())
				$ret = update_blog_option($wpdb->blogid, $this->_settings['options_key'], $this->config_data);
			else
				$ret = update_option( $this->_settings['options_key'], $this->config_data);
		}

		function add_update_config_item($item_key, $item) {
			$this->load_config();
			$this->config_data['items'][$item_key] = $item;
			$this->save_config();
		}

		/**
		 * Utility function to pull the snapshot item from the config_data based on
		 * the $_REQUEST['item] value
		 *
		 * @since 1.0.0
		 * @uses $this->config_data
		 *
		 * @param array $item if found this array is the found snapshot item.
		 * @return none
		 */
		function snapshot_get_edit_item($item_key) {
	//		if (!isset($_REQUEST['item']))
	//			return;

			// If the config_data[items] array has not yet been initialized or is empty return.
			if ((!isset($this->config_data['items'])) || (!count($this->config_data['items'])))
				return;

			//$item_key = esc_attr($_REQUEST['item']);

			if (isset($this->config_data['items'][$item_key]))
				return $this->config_data['items'][$item_key];
		}


		/**
		 * Utility function to setup our destination folder to store snapshot output
		 * files. The folder destination will be inside the site's /wp-content/uploads/
		 * folder tree. The default folder name will be 'snapshots'
		 *
		 * @since 1.0.0
		 * @see wp_upload_dir()
		 *
		 * @param none
		 * @return none
		 */
		function set_backup_folder($is_moving = false) {

			global $current_site;

			if (is_multisite()) {
				switch_to_blog( $current_site->blog_id );
			}

			$wp_upload_dir = wp_upload_dir();
			$wp_upload_dir['basedir'] = str_replace('\\', '/', $wp_upload_dir['basedir']);

			$this->config_data['config']['backupFolder'] = str_replace('\\', '/', $this->config_data['config']['backupFolder']);

			// Are we dealing with Abolute or relative path?
			if ((substr($this->config_data['config']['backupFolder'], 0, 1) == "/")
			 || (substr($this->config_data['config']['backupFolder'], 1, 2) == ":/")) {

				// If absolute set a flag so we don't need to keep checking the substr();
				$this->config_data['config']['absoluteFolder'] = true;
				$_backupFolderFull = trailingslashit($this->config_data['config']['backupFolder']);

			} else {

				// If relative unset a flag so we don't need to keep checking the substr();
				$this->config_data['config']['absoluteFolder'] = false;

				// If relative then we store the files into the /uploads/ folder tree.
				$_backupFolderFull = trailingslashit($wp_upload_dir['basedir']) . $this->config_data['config']['backupFolder'];
			}

			if (!file_exists($_backupFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot folder. Check that the parent folder is writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupFolderFull;
					return;
				}
			}

			//echo "_backupFolderFull=[". $_backupFolderFull ."]<br />";
			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupFolderFull, 0775 );
				if (!is_writable($_backupFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot destination folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupFolderFull;
				}
			}

			$this->_settings['backupBaseFolderFull'] 	= $_backupFolderFull;
			if ($this->config_data['config']['absoluteFolder'] != true) {

				$this->_settings['backupURLFull'] = trailingslashit($wp_upload_dir['baseurl']) . $this->config_data['config']['backupFolder'];

			} else {
				$this->_settings['backupURLFull']		= '';
			}

			if (is_multisite()) {
				restore_current_blog();
			}
		}

		function set_log_folders() {

			snapshot_utility_secure_folder($this->_settings['backupBaseFolderFull']);

			$_backupBackupFolderFull	= trailingslashit($this->_settings['backupBaseFolderFull']) .'_backup';
			if (!file_exists($_backupBackupFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupBackupFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot Log folder. Check that the parent folder is writeable",
					 	SNAPSHOT_I18N_DOMAIN) ." ". $_backupBackupFolderFull;
					return;
				}
			}

			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupBackupFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupBackupFolderFull, 0775 );
				if (!is_writable($_backupBackupFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot destination folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupBackupFolderFull;
				}
			}
			snapshot_utility_secure_folder($_backupBackupFolderFull);
			$this->_settings['backupBackupFolderFull'] = $_backupBackupFolderFull;



			$_backupRestoreFolderFull	= trailingslashit($this->_settings['backupBaseFolderFull']) .'_restore';
			if (!file_exists($_backupRestoreFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupRestoreFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot Restore folder. Check that the parent folder is writeable",
					 	SNAPSHOT_I18N_DOMAIN) ." ". $_backupRestoreFolderFull;
					return;
				}
			}

			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupRestoreFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupRestoreFolderFull, 0775 );
				if (!is_writable($_backupRestoreFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot restore folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupRestoreFolderFull;
				}
			}
			snapshot_utility_secure_folder($_backupRestoreFolderFull);
			$this->_settings['backupRestoreFolderFull'] = $_backupRestoreFolderFull;



			$_backupLogFolderFull	= trailingslashit($this->_settings['backupBaseFolderFull']) .'_logs';
			if (!file_exists($_backupLogFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupLogFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot Log folder. Check that the parent folder is writeable",
					 	SNAPSHOT_I18N_DOMAIN) ." ". $_backupLogFolderFull;
					return;
				}
			}

			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupLogFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupFolderFull, 0775 );
				if (!is_writable($_backupLogFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot destination folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupLogFolderFull;
				}
			}
			snapshot_utility_secure_folder($_backupLogFolderFull);
			$this->_settings['backupLogFolderFull'] = $_backupLogFolderFull;



			// Setup our own version of _SESSION save path. This is because some servers just don't have standard PHP _SESSIONS setup properly.
			$_backupSessionsFolderFull	= trailingslashit($this->_settings['backupLogFolderFull']) .'_sessions';
			if (!file_exists($_backupSessionsFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupSessionsFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot Log folder. Check that the parent folder is writeable",
					 	SNAPSHOT_I18N_DOMAIN) ." ". $_backupSessionsFolderFull;
					return;
				}
			}

			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupSessionsFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupSessionsFolderFull, 0775 );
				if (!is_writable($_backupSessionsFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot destination folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupSessionsFolderFull;
				}
			}
			snapshot_utility_secure_folder($_backupSessionsFolderFull);
			$this->_settings['backupSessionFolderFull'] = $_backupSessionsFolderFull;



			if ($this->config_data['config']['absoluteFolder'] != true) {

				//$relative_path = substr($_backupLogFolderFull, strlen(ABSPATH));
				//$this->_settings['backupLogURLFull']		= site_url($relative_path);
				$this->_settings['backupLogURLFull'] = trailingslashit($this->_settings['backupURLFull']).'_logs';
			} else {
				$this->_settings['backupLogURLFull']		= '';
			}






			/* Setup the _locks folder. Used by scheduled tasks */

			$_backupLockFolderFull	= trailingslashit($this->_settings['backupBaseFolderFull']) .'_locks';
			if (!file_exists($_backupLockFolderFull)) {

				/* If the destination folder does not exist try and create it */
				if (wp_mkdir_p($_backupLockFolderFull, 0775) === false) {

					/* If here we cannot create the folder. So report this via the admin header message and return */
					$this->_admin_header_error .= __("ERROR: Cannot create snapshot Lock folder. Check that the parent folder is writeable",
					 	SNAPSHOT_I18N_DOMAIN) ." ". $_backupLockFolderFull;
					return;
				}
			}

			/* If here the destination folder is present. But is it writeable by our process? */
			if (!is_writable($_backupLockFolderFull)) {

				/* Try updating the folder perms */
				@ chmod( $_backupLockFolderFull, 0775 );
				if (!is_writable($_backupLockFolderFull)) {

					/* Appears it is still not writeable then report this via the admin heder message and return */
					$this->_admin_header_error .= __("ERROR: The Snapshot locks folder is not writable", SNAPSHOT_I18N_DOMAIN)
						." ". $_backupLockFolderFull;
				}
			}
			$this->_settings['backupLockFolderFull'] = $_backupLockFolderFull;
		}

		/**
		 * AJAX Gateway to adding a new snapshot. Seems the simple form post is too much given
		 * the number of tables possibly selected. So instead we intercept the form submit with
		 * jQuery and process each selected table as its own HTTP POST into this gateway.
		 *
		 * The process starts with the 'init' which sets up the session backup filename based on
		 * the session id. Next each 'table' is called. Last a 'finish' action is called to move
		 * the temp file into the final location and add a record about the backup to the activity log
		 *
		 * @since 1.0.0
		 * @see
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_ajax_backup_proc()
		{
			global $wpdb;
			//echo "_settings<pre>"; print_r($this->_settings); echo "</pre>";

			// When zlib compression is turned on we get errors from this shutdown action setup by WordPress. So we disabled.
			$zlib_compression = ini_get('zlib.output_compression');
			if ($zlib_compression)
				remove_action( 'shutdown',	'wp_ob_end_flush_all', 1);

			@ini_set('html_errors', 'Off');
			@ini_set('zlib.output_compression', 'Off');
			@set_time_limit(0);

			$old_error_handler = set_error_handler(array( &$this, 'snapshot_ErrorHandler' ));

			if ((isset($this->config_data['config']['memoryLimit'])) && (!empty($this->config_data['config']['memoryLimit']))) {
				@ini_set('memory_limit', $this->config_data['config']['memoryLimit']);
			}

			// Need the item_key and data_item_key before init of the Logger
			if (isset($_POST['snapshot-item'])) {
				$item_key	= 	intval($_POST['snapshot-item']);
			}
			if (isset($_POST['snapshot-data-item'])) {
				$data_item_key	= 	intval($_POST['snapshot-data-item']);
			}

			$this->snapshot_logger = new SnapshotLogger($this->_settings['backupLogFolderFull'], $item_key, $data_item_key);

			snapshot_utility_set_error_reporting($this->config_data['config']['errorReporting']);

			/* Needed to create the archvie zip file */
			if ($this->config_data['config']['zipLibrary'] == "PclZip") {
				if (!defined('PCLZIP_TEMPORARY_DIR'))
					define('PCLZIP_TEMPORARY_DIR', trailingslashit($this->_settings['backupBackupFolderFull']) . $item_key."/");
				if (!class_exists('class PclZip'))
					require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');
			}

			switch($_REQUEST['snapshot-proc-action'])
			{
				case 'init':

					$this->snapshot_logger->log_message('Backup: init');

					// Start/load our sessions file
					$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key, true);

					if (isset($_POST['snapshot-action'])) {
						if ($_POST['snapshot-action'] == "add") {
							$this->snapshot_logger->log_message("adding new snapshot: ". $item_key);
						} else if ($_POST['snapshot-action'] == "update") {
							$this->snapshot_logger->log_message("updating snapshot: ". $item_key);
						}
						$this->snapshot_add_update_action_proc($_POST);
					}

					if (!isset($this->config_data['items'][$item_key]))
						die();

					$item = $this->config_data['items'][$item_key];

					$blog_id = 0;
					if (is_multisite()) {
						if (isset($item['blog-id'])) {
							$blog_id = intval($item['blog-id']);
							if ($blog_id != $wpdb->blogid) {
								$original_blog_id = $wpdb->blogid;
								switch_to_blog($blog_id);
							}
						}
					}

					ob_start();
					$error_array = $this->snapshot_ajax_backup_init($item, $_POST);
					$function_output = ob_get_contents();
					ob_end_clean();

					if ((is_multisite()) && (isset($original_blog_id)) && ($original_blog_id > 0)) {
						switch_to_blog($original_blog_id);
					}

					if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
						// We have a problem.

						$this->snapshot_logger->log_message("init: _POST". print_r($_POST, true));
						$this->snapshot_logger->log_message("init: error_array". print_r($error_array, true));
						$this->snapshot_logger->log_message("init: _SESSION". print_r($this->_session->data, true));
						$this->snapshot_logger->log_message("init: output:". $function_output);

						$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
							": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
							": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

						$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
						$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

						$error_array['MEMORY'] = array();
						$error_array['MEMORY']['memory_limit'] = ini_get('memory_limit');
						$error_array['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
						$error_array['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

						echo json_encode($error_array);

						die();
					}
					break;

				case 'table':

					if (!isset($this->config_data['items'][$item_key]))
						die();

					$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);

					$item = $this->config_data['items'][$item_key];

					$blog_id = 0;
					if (is_multisite()) {
						if (isset($_POST['snapshot-blog-id'])) {
							$blog_id = intval($_POST['snapshot-blog-id']);
							if ($blog_id != $wpdb->blogid) {
								$original_blog_id = $wpdb->blogid;
								switch_to_blog($blog_id);
							}
						}
					}

					ob_start();
					$error_array = $this->snapshot_ajax_backup_table($item, $_POST);
					$function_output = ob_get_contents();
					ob_end_clean();

					if ((is_multisite()) && (isset($original_blog_id)) && ($original_blog_id > 0)) {
						switch_to_blog($original_blog_id);
					}

					if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
						// We have a problem.

						$this->snapshot_logger->log_message("table: _POST". print_r($_POST, true));
						$this->snapshot_logger->log_message("table: error_array". print_r($error_array, true));
						$this->snapshot_logger->log_message("table: _SESSION". print_r($this->_session->data, true));
						$this->snapshot_logger->log_message("table: output:". $function_output);

						$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
							": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
							": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

						$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
						$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

						$error_array['MEMORY'] = array();
						$error_array['MEMORY']['memory_limit'] = ini_get('memory_limit');
						$error_array['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
						$error_array['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

						echo json_encode($error_array);

						die();
					}

					break;

				case 'file':

					if (isset($_POST['snapshot-file-data-key'])) {

						$file_data_key = esc_attr($_POST['snapshot-file-data-key']);

						$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);

						if (isset($this->_session->data['files_data']['included'][$file_data_key])) {

							if (!isset($this->config_data['items'][$item_key]))
								die();

							$item = $this->config_data['items'][$item_key];
							$this->snapshot_logger->log_message("file: section: ". $file_data_key);

							ob_start();

							$error_array = $this->snapshot_ajax_backup_file($item, $_POST);
							$function_output = ob_get_contents();
							ob_end_clean();

							if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("file: _POST". print_r($_POST, true));
								$this->snapshot_logger->log_message("file: error_array". print_r($error_array, true));
								$this->snapshot_logger->log_message("file: _SESSION". print_r($this->_session->data, true));
								$this->snapshot_logger->log_message("file: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
								$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

								$error_array['MEMORY'] = array();
								$error_array['MEMORY']['memory_limit'] = ini_get('memory_limit');
								$error_array['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
								$error_array['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

								echo json_encode($error_array);

								die();
							}
						}
					}

					break;

				case 'finish':

					if (!isset($this->config_data['items'][$item_key]))
						die();

					$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);
					//echo "_session<pre>"; print_r($this->_session); echo "</pre>";

					$item = $this->config_data['items'][$item_key];
					ob_start();
					$error_array = $this->snapshot_ajax_backup_finish($item, $_POST);
					$function_output = ob_get_contents();
					ob_end_clean();

					if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
						// We have a problem.

						$this->snapshot_logger->log_message("finish: error_array:". print_r($error_array, true));
						$this->snapshot_logger->log_message("finish: _SESSION:". print_r($this->_session->data, true));
						$this->snapshot_logger->log_message("finish: item:". print_r($item, true));
						$this->snapshot_logger->log_message("finish: output:". $function_output);

						$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
							": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
							": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

						$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
						$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

						$error_array['MEMORY'] = array();
						$error_array['MEMORY']['memory_limit'] = ini_get('memory_limit');
						$error_array['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
						$error_array['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

						echo json_encode($error_array);

						die();
					} else {
						$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;

						//echo "_session<pre>"; print_r($this->_session); echo "</pre>";
					}

					$this->snapshot_logger->log_message("finish: ". basename($error_array['responseFile']));
					$this->purge_archive_limit($item_key);
					wp_remote_post(get_option('siteurl'). '/wp-cron.php',
						array(
							'timeout' 		=> 	3,
							'blocking' 		=> 	false,
							'sslverify' 	=> 	false,
							'body'			=>	array(
									'nonce' => wp_create_nonce('WPMUDEVSnapshot'),
									'type'			=>	'start'
								),
							'user-agent'	=>	'WPMUDEVSnapshot'
						)
					);

					break;

				default:
					break;
			}

			$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);


//			$this->snapshot_item_run_immediate($item_key);

			$error_array['MEMORY'] = array();
			$error_array['MEMORY']['memory_limit'] = ini_get('memory_limit');
			$error_array['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
			$error_array['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

			echo json_encode($error_array);
			die();
		}


		/**
		 * This 'init' process begins the user's backup via AJAX. Creates the session backup file.
		 *
		 * @since 1.0.0
		 * @see
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_ajax_backup_init($item, $_post_array) {
			global $wpdb;

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			$error_status 								= array();
			$error_status['errorStatus'] 				= false;
			$error_status['errorText'] 					= "";
			$error_status['responseText'] 				= "";
			$error_status['table_data'] 				= array();
			$error_status['files_data'] 				= array();

			if (isset($this->_session->data))
				unset($this->_session->data);

			$sessionItemBackupFolder = trailingslashit($this->_settings['backupBackupFolderFull']);
			$sessionItemBackupFolder = trailingslashit($sessionItemBackupFolder) . intval($item['timestamp']);

			if (!file_exists($sessionItemBackupFolder)) {
				wp_mkdir_p($sessionItemBackupFolder);
			}

			if (!is_writable($sessionItemBackupFolder)) {
				$error_status['errorStatus'] = true;
				$error_status['errorText'] = 	"<p>". __("ERROR: Snapshot backup aborted.<br />The Snapshot folder is not writeable. Check the settings.",
				 	SNAPSHOT_I18N_DOMAIN) ." ". $sessionItemBackupFolder ."</p>";
				return $error_status;
			}

			// Cleanup any files from a previous backup attempt
			if ($dh = opendir($sessionItemBackupFolder)) {
				while (($file = readdir($dh)) !== false) {
					if (($file == '.') || ($file == '..'))
						continue;

					@unlink(trailingslashit($sessionItemBackupFolder) . $file);
				}
				closedir($dh);
			}
			$this->_session->data['backupItemFolder'] = $sessionItemBackupFolder;

			if (isset($this->_session->data['table_data']))
				unset($this->_session->data['table_data']);

			if (isset($item['tables-option'])) {

				if ($item['tables-option'] == "none") {

				} else if ($item['tables-option'] == "all") {
					$tables_sections = snapshot_utility_get_database_tables($item['blog-id']);

				} else if ($item['tables-option'] == "selected") {
					// This should already be set from the Add/Update form post
					$tables_sections = $item['tables-sections'];
				}
			}
			//echo "tables_sections<pre>"; print_r($tables_sections); echo "</pre>";
			//die();

			if ((isset($tables_sections)) && (count($tables_sections))) {

				foreach($tables_sections as $section => $tables_set) {
					foreach($tables_set as $table_name) {
						$_set = array();

						if ($section == "global") {
							//echo "table_name[". $table_name ."]<br />";
							if (($table_name == $wpdb->base_prefix."users") || ($table_name == $wpdb->base_prefix."usermeta")) {

								if (!isset($this->_session->data['global_user_ids'])) {
									$this->_session->data['global_user_ids'] = array();
									$sql_str = "SELECT user_id FROM ". $wpdb->base_prefix ."usermeta WHERE meta_key='primary_blog' AND meta_value='". $item['blog-id'] ."'";
									//echo "sql_str=[". $sql_str ."]<br />";
									$user_ids = $wpdb->get_col($sql_str);
									if ($user_ids) {
										$this->_session->data['global_user_ids'] = $user_ids;
									}
								}

								if ((isset($this->_session->data['global_user_ids']))
							 	 && (is_array($this->_session->data['global_user_ids']))
								 && (count($this->_session->data['global_user_ids']))) {
									 if ($table_name == $wpdb->base_prefix."users") {
	 									$tables_segment = snapshot_utility_get_table_segments($table_name,
															intval($this->config_data['config']['segmentSize']),
															'WHERE ID IN ('. implode(',', $this->_session->data['global_user_ids']) .');');

									 } else if ($table_name == $wpdb->base_prefix."usermeta") {
	 									$tables_segment = snapshot_utility_get_table_segments($table_name,
															intval($this->config_data['config']['segmentSize']),
															'WHERE user_id IN ('. implode(',', $this->_session->data['global_user_ids']) .');');
									 }
									 //echo "tables_segment<pre>"; print_r($tables_segment); echo "</pre>";
									 if (($tables_segment['segments']) && (count($tables_segment['segments']))) {

										foreach($tables_segment['segments'] as $segment_idx => $_set) {

											$_set['table_name'] 	= $tables_segment['table_name'];
											$_set['rows_total'] 	= $tables_segment['rows_total'];
											$_set['segment_idx'] 	= intval($segment_idx) + 1;
											$_set['segment_total'] 	= count($tables_segment['segments']);

											$error_status['table_data'][] = $_set;
										}
									}
								 }
							 }
						} else {
							$tables_segment = snapshot_utility_get_table_segments($table_name, intval($this->config_data['config']['segmentSize']));
							if (($tables_segment['segments']) && (count($tables_segment['segments']))) {

								foreach($tables_segment['segments'] as $segment_idx => $_set) {

									$_set['table_name'] 	= $tables_segment['table_name'];
									$_set['rows_total'] 	= $tables_segment['rows_total'];
									$_set['segment_idx'] 	= intval($segment_idx) + 1;
									$_set['segment_total'] 	= count($tables_segment['segments']);

									$error_status['table_data'][] = $_set;
								}
							} else {
								$_set['table_name'] 		= $tables_segment['table_name'];
								$_set['rows_total'] 		= $tables_segment['rows_total'];
								$_set['segment_idx'] 		= 1;
								$_set['segment_total'] 		= 1;
								$_set['rows_start'] 		= 0;
								$_set['rows_end'] 			= 0;

								$error_status['table_data'][] = $_set;
							}
						}
					}
				}

				if ((isset($tables_sections)) && (count($tables_sections))) {
					$this->_session->data['tables_sections'] 	= $tables_sections;
				} else {
					$this->_session->data['tables_sections']	= array();
				}

				if (isset($error_status['table_data'])) {
					$this->_session->data['table_data'] 			= $error_status['table_data'];
				}
			}
			//echo "table_data<pre>"; print_r($this->_session->data['table_data']); echo "</pre>";
			//die();

			if (!isset($item['destination-sync'])) $item['destination-sync'] = "archive";

			if ($item['destination-sync'] == "archive") {
				//echo "_post_array<pre>"; print_r($_post_array); echo "</pre>";
				//echo "item<pre>"; print_r($item); echo "</pre>";


				$error_status['files_data'] = $this->snapshot_gather_item_files($item);
				//echo "files_data<pre>"; print_r($error_status['files_data']); echo "</pre>";
				//die();

				if ((isset($error_status['files_data']['included'])) && (count($error_status['files_data']['included']))) {
					$files_data = array();

					foreach($error_status['files_data']['included'] as $_section => $_files) {

						if (!count($_files)) continue;

						switch($_section) {
							/*
							case 'home':
								$_path = $home_path;
								if (($_post_array['snapshot-action']) && ($_post_array['snapshot-action'] == "cron"))
									$_max_depth=0;
								else
									$_max_depth=2;
								break;
							*/
							case 'media':
								$_path = trailingslashit( $home_path ) . snapshot_utility_get_blog_upload_path($item['blog-id']) ."/";
								//$_path = snapshot_utility_get_blog_upload_path($item['blog-id']) ."/";
								if (($_post_array['snapshot-action']) && ($_post_array['snapshot-action'] == "cron"))
									$_max_depth=0;
								else
									$_max_depth=2;
								break;


							case 'plugins':
							/* case 'mu-plugins': */
								$_path = trailingslashit(WP_CONTENT_DIR) . 'plugins/';
								//$_max_depth=0;
								if (($_post_array['snapshot-action']) && ($_post_array['snapshot-action'] == "cron"))
									$_max_depth=0;
								else
									$_max_depth=0;
								break;


							case 'themes':
								$_path = trailingslashit(WP_CONTENT_DIR) . 'themes/';
								if (($_post_array['snapshot-action']) && ($_post_array['snapshot-action'] == "cron"))
									$_max_depth=0;
								else
									$_max_depth=0;
								//$_max_depth=0;
							 	break;

							default:
								$_path = '';
								$_max_depth=0;
								break;
						}

						if ( ($_max_depth > 0) && (!empty($_path)) ) {
							foreach($_files as $_idx => $_file) {
								$_new_file = str_replace($_path, '', $_file);
								$_slash_parts = split('/', $_new_file);
								if (count($_slash_parts) > $_max_depth) {

									// We first remove the file from this section...
									unset($error_status['files_data']['included'][$_section][$_idx]);

									// ... then we add a new section for this group of files.
									$_new_section = '';
									foreach($_slash_parts as $_slash_idx => $slash_part) {
										if ($_slash_idx > ($_max_depth - 1)) break;

										if (strlen($_new_section)) $_new_section .= "/";
										$_new_section .= $slash_part;

										unset($_slash_parts[$_slash_idx]);
									}
									$_new_file = implode('/', array_values($_slash_parts));

									if (!isset($error_status['files_data']['included'][$_section ."/". $_new_section])) {
										$error_status['files_data']['included'][$_section ."/". $_new_section] = array();
									}
									$error_status['files_data']['included'][$_section ."/". $_new_section][] = $_file;
								}
							}

							if (empty($error_status['files_data']['included'][$_section])) {
								unset($error_status['files_data']['included'][$_section]);
							}
						}
					}
					ksort($error_status['files_data']['included']);

					if (($_post_array['snapshot-action']) && ($_post_array['snapshot-action'] == "cron")) {
						$all_files = array(
							'all_files' => array()
						);

						foreach($error_status['files_data']['included'] as $section => $files) {
							$all_files['all_files'] = array_merge($all_files['all_files'], $files);
						}
						$this->_session->data['files_data']['included'] = $all_files;
					} else {
						$this->_session->data['files_data']['included'] = $error_status['files_data']['included'];
					}
					$error_status['files_data'] = array_keys($this->_session->data['files_data']['included']);
				}
			} else {
				$this->_session->data['files_data'] = '';
				$error_status['files_data'] = '';
			}
			$this->_session->data['snapshot_time_start'] 	= time();

			$error_status['errorStatus'] 	= false;
			$error_status['responseText'] 	= "Init Start";
			//echo "DEBUG: In: ". __FUNCTION__ ."  Line:". __LINE__ ."<br />";
			//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
			//echo "_session<pre>"; print_r($this->_session->data); echo "</pre>";
			//die();
			return $error_status;
		}

		/**
		 * This 'table' process is called from JS for each table selected. The contents of the SQL table
		 * are appended to the session backup file.
		 *
		 * @since 1.0.0
		 * @see
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_ajax_backup_table($item, $_post_array) {
			$error_status 					= array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			if (!isset($_post_array['snapshot-table-data-idx'])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "table_data_idx not set";

				return $error_status;
			}

			$table_data_idx = intval($_post_array['snapshot-table-data-idx']);
			$table_data = array();

			if ((isset($this->_session->data['table_data'])) && (isset($this->_session->data['table_data'][$table_data_idx]))) {
				$table_data = $this->_session->data['table_data'][$table_data_idx];
			}

			if (!$table_data) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "table_data not set. This normally means that PHP on your server is not properly setup to handle _SESSION. Check with your hosting company.";

				return $error_status;
			}

			if ((!isset($table_data['rows_start'])) || (!isset($table_data['rows_end']))) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "table_data rows_start or rows_end not set";

				return $error_status;
			}

			$this->snapshot_logger->log_message("table: ". $table_data['table_name'] .
				" segment: ". $table_data['segment_idx'] ."/". $table_data['segment_total']);

			if (isset($this->_session->data['backupItemFolder'])) {
				$backupTable = sanitize_text_field($table_data['table_name']);
				$backupFile = trailingslashit($this->_session->data['backupItemFolder']) . $backupTable .".sql";

				$fp = @fopen($backupFile, 'a');
				if ($fp) {

					fseek($fp, 0, SEEK_END);
					$table_data['ftell_before'] = ftell($fp);
					$backup_db = new SnapshotBackupDatabase( );
					$backup_db->set_fp( $fp ); // Set our file point so the object can write to out output file.

					if (intval($table_data['segment_idx']) == intval($table_data['segment_total'])) {
						if (isset($table_data['sql']))
							$sql = $table_data['sql'];
						else
							$sql = '';

						// If we are at the end ot the table's segments we now just pass a large number for the table end.
						// This will force MySQL to use the table_start as the offset then we read the rest of the rows in the table.
						$number_rows_segment = $backup_db->backup_table($backupTable, $table_data['rows_start'],
							$table_data['rows_total'] * 3, $table_data['rows_total'] * 3, $sql);
					} else {
						// Else we just ready the table segment of rows.
						$number_rows_segment = $backup_db->backup_table($backupTable, $table_data['rows_start'],
							$table_data['rows_end'], $table_data['rows_total']);
					}

					if (count($backup_db->errors))
					{
						$error_status['errorStatus'] = true;
						$error_messages = implode('</p><p>', $backup_db->errors);
						$error_status['errorText'] = "<p>". __('ERROR: Snapshot backup aborted.', SNAPSHOT_I18N_DOMAIN) . $error_messages ."</p>";

						return $error_status;
					}

					unset($backup_db);
					$table_data['ftell_after'] = ftell($fp);
					fclose($fp);

					$error_status['table_data'] = $table_data;
					$this->_session->data['table_data'][$table_data_idx] = $table_data;

					//if (($table_data['rows_start'] + $table_data['rows_end']) == $table_data['rows_total']) {
					if (intval($table_data['segment_idx']) == intval($table_data['segment_total'])) {

						$archiveFiles[] = $backupFile;

						$backupZipFile = trailingslashit($this->_session->data['backupItemFolder']) .'snapshot-backup.zip';

						if ($this->config_data['config']['zipLibrary'] == "PclZip") {
							$zipArchive = new PclZip($backupZipFile);
							try {
								$zip_add_ret = $zipArchive->add($archiveFiles,
													PCLZIP_OPT_REMOVE_PATH, $this->_session->data['backupItemFolder'],
													PCLZIP_OPT_TEMP_FILE_THRESHOLD, 10,
													PCLZIP_OPT_ADD_TEMP_FILE_ON );
								if (!$zip_add_ret) {
									$error_status['errorStatus'] 	= true;
									$error_status['errorText'] 		= "ERROR: PclZIP table: ". $table_data .": add failed : ".
										$zipArchive->errorCode() .": ". $zipArchive->errorInfo() ."]";
									return $error_status;
								}

							} catch (Exception $e) {
								$error_status['errorStatus'] 	= true;
								$error_status['errorText'] 		= "ERROR: PclZIP table:". $table_data['table_name'] ." : add failed : ".
									$zipArchive->errorCode() .": ". $zipArchive->errorInfo() ."]";

								$error_status['MEMORY']['memory_limit'] = ini_get('memory_limit');
								$error_status['MEMORY']['memory_usage_current'] = snapshot_utility_size_format(memory_get_usage(true));
								$error_status['MEMORY']['memory_usage_peak'] = snapshot_utility_size_format(memory_get_peak_usage(true));

								return $error_status;
							}
						} else if ($this->config_data['config']['zipLibrary'] == "ZipArchive") {
							$zipArchive = new ZipArchive();
							if ($zipArchive) {
								if (!file_exists($backupZipFile))
									$zip_flags = ZIPARCHIVE::CREATE;
								else
									$zip_flags = null;
								$zip_hdl = $zipArchive->open($backupZipFile, $zip_flags);
								if ($zip_hdl !== TRUE) {
									$error_status['errorStatus'] 	= true;
									$error_status['errorText'] 		= "ERROR: ZipArchive table:". $table_data['table_name'] ." : add failed: ".
										ZipArchiveStatusString($zip_hdl);

									return $error_status;
								}

								foreach($archiveFiles as $file) {
									$file = str_replace('\\', '/', $file);
									$zipArchive->addFile($file, str_replace($this->_session->data['backupItemFolder'] .'/', '', $file));
								}
								$zipArchive->close();
							}
						}
						if (isset($zipArchive)) unset($zipArchive);

						foreach($archiveFiles as $archiveFile) {
							@unlink($archiveFile);
						}
					}
					//$error_status['archiveFile'] = $archiveFile;
					return $error_status;
				}
			} else {

				if (!isset($this->_session->data['backupItemFolder'])) {

					$error_status['errorStatus'] 	= true;
					$error_status['errorText'] 		= "_SESSION backupFolder not set";

					return $error_status;
				}
				if (!isset($_post_array['snapshot_table'])) {

					$error_status['errorStatus'] 	= true;
					$error_status['errorText'] 		= "post_array snapshot_table not set";

					return $error_status;
				}
			}
		}


		/**
		 * This 'file' process
		 *
		 * @since 1.0.6
		 * @see
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_ajax_backup_file($item, $_post_array) {

			$error_status 					= array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (isset($_post_array['snapshot-file-data-key'])) {
				$file_data_key = sanitize_text_field($_post_array['snapshot-file-data-key']);
				if (isset($this->_session->data['files_data']['included'][$file_data_key])) {

					@set_time_limit( 0 );

					$backupZipFile = trailingslashit($this->_session->data['backupItemFolder']) .'snapshot-backup.zip';

					if ($this->config_data['config']['zipLibrary'] == "PclZip") {

						$zipArchive = new PclZip($backupZipFile);
						try {
							$zip_add_ret = $zipArchive->add($this->_session->data['files_data']['included'][$file_data_key],
								PCLZIP_OPT_REMOVE_PATH, $home_path,
								PCLZIP_OPT_ADD_PATH, 'www',
								PCLZIP_OPT_TEMP_FILE_THRESHOLD, 10,
								PCLZIP_OPT_ADD_TEMP_FILE_ON );
							if (!$zip_add_ret) {
								$error_status['errorStatus'] 	= true;
								$error_status['errorText'] 		= "ERROR: PcLZIP file:". $file_data_key ." add failed ".
									$zipArchive->errorCode() .": ". $zipArchive->errorInfo();

								return $error_status;
							}
						} catch (Exception $e) {
							$error_status['errorStatus'] 	= true;
							$error_status['errorText'] 		= "ERROR: PclZIP file:". $file_data_key ." : add failed : ".
								$zipArchive->errorCode() .": ". $zipArchive->errorInfo();
							return $error_status;
						}

					} else if ($this->config_data['config']['zipLibrary'] == "ZipArchive") {
						$zipArchive = new ZipArchive();
						if ($zipArchive) {
							if (!file_exists($backupZipFile))
								$zip_flags = ZIPARCHIVE::CREATE;
							else
								$zip_flags = null;
							$zip_hdl = $zipArchive->open($backupZipFile, $zip_flags);
							if ($zip_hdl !== TRUE) {
								$error_status['errorStatus'] 	= true;
								$error_status['errorText'] 		= "ERROR: ZipArchive file:". $file_data_key ." : add failed: ".
									ZipArchiveStatusString($zip_hdl);
								return $error_status;
							}
							$fileCount = 0;
							foreach($this->_session->data['files_data']['included'][$file_data_key] as $file) {
								$file = str_replace('\\', '/', $file);
								$zipArchive->addFile($file, str_replace($home_path, 'www/', $file));

								// Per some PHP documentation.
								/*
									When a file is set to be added to the archive, PHP will attempt to lock the file and it is
									only released once the ZIP operation is done. In short, it means you can first delete an
									added file after the archive is closed. Related to this there is a limit to the number of
									files that can be added at once. So we are setting a limit of 200 files per add session.
									Then we close the archive and re-open.
								*/
								$fileCount += 1;
								if ($fileCount >= 200) {
									$zipArchive->close();
									$zip_hdl = $zipArchive->open($backupZipFile, $zip_flags);
									$fileCount = 0;
								}
							}
							$zipArchive->close();
						}
					}
					if (isset($zipArchive)) unset($zipArchive);

					foreach($this->_session->data['files_data']['included'][$file_data_key] as $idx => $filename) {
						$filename = str_replace($home_path, '', $filename);
						$this->snapshot_logger->log_message("file: ". $filename);
					}

				}
			}
			return $error_status;
		}

		/**
		 * This 'finish' process is called from JS when all selected tables have been archived. This process
		 * renames the session backup file to the final location and writes an activity log record.
		 *
		 * @since 1.0.0
		 * @see
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_ajax_backup_finish($item, $_post_array) {

			global $wpdb;

			//echo "item<pre>"; print_r($item); echo "</pre>";
			//echo "_post_array<pre>"; print_r($_post_array); echo "</pre>";
			//die();

			$error_status = array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			$manifest_array = array();

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (isset($this->_session->data['backupItemFolder'])) {

				$item_key = $item['timestamp'];
				if (isset($_post_array['snapshot-data-item']))
					$data_item_key = intval($_post_array['snapshot-data-item']);
				else
					$data_item_key = time();

				$data_item = array();
				$data_item['timestamp']	=	$data_item_key;

				if (isset($item['tables-option']))
					$data_item['tables-option']			=	$item['tables-option'];

				if (isset($this->_session->data['tables_sections'])) {
					$data_item['tables-sections']		=	$this->_session->data['tables_sections'];
					$item['tables-sections'] 			=	$this->_session->data['tables_sections'];
				}

				if (isset($item['files-option']))
					$data_item['files-option']			=	$item['files-option'];

				if ($data_item['files-option'] == "all") {

					if (is_main_site($item['blog-id'])) {
						$data_item['files-sections'] = 	array('themes', 'plugins', 'media');
					} else {
						$data_item['files-sections'] = 	array('media');
					}

				} else if ($data_item['files-option'] == "selected") {

					if (is_main_site($item['blog-id'])) {
						if (isset($item['files-sections']))
							$data_item['files-sections']		=	$item['files-sections'];
					} else {
						$data_item['files-sections']		=	'';
					}
				}

				if (isset($this->_session->data['files_data']['included'])) {

					$session_files_data = array();
					foreach($this->_session->data['files_data']['included'] as $files_section => $files_set) {

						if ($files_section == "plugins") {
							if ( !function_exists( 'get_plugins' ) )
								require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

							$manifest_array['FILES-DATA-THEMES-PLUGINS'] = get_plugins();
						}
						else if ($files_section == "themes") {
							$themes = wp_get_themes();
							$manifest_array['FILES-DATA-THEMES'] = get_plugins();
						} else {
							// Nothing
						}

						$session_files_data = array_merge($session_files_data, $files_set);
					}

					$item['files-count'] 				= count($session_files_data);
					$data_item['files-count'] 			= count($session_files_data);
				} else {
					$item['files-count'] 				= 0;
					$data_item['files-count'] 			= 0;
				}

				// If the master item destination is not empty, means we are connected to some external system (FTP, S3, Dropbox)
				if ((empty($item['destination'])) || ($item['destination'] == "local")) {
					// Else if the master item destination is empty..
					$data_item['destination']			=	'local';

					// We assume the local archive folder for this item is set to something non-standard.
					if ((isset($item['destination-directory'])) && (strlen($item['destination-directory'])))
						$data_item['destination-directory']	=	$item['destination-directory'];
					else
						$data_item['destination-directory'] =	'';
				} else {
					// In that case we don't want to set the destination and path until the file has been transmitted.
					$data_item['destination']			=	'';
					$data_item['destination-directory'] =	'';
				}

				if (isset($item['destination-sync']))
					$data_item['destination-sync'] = $item['destination-sync'];

				if (isset($this->_session->data['snapshot_time_start'])) {
					$data_item['time-start']			= $this->_session->data['snapshot_time_start'];
					$data_item['time-end']				= time();

					unset($this->_session->data['snapshot_time_start']);
				}

				$manifest_array['SNAPSHOT_VERSION'] = $this->_settings['SNAPSHOT_VERSION'];

				if ( ( (!isset($item['blog-id'])) && (empty($item['blog-id']) ) ) && (isset($_post_array['snapshot-blog-id'])))
					$item['blog-id'] = intval($_post_array['snapshot-blog-id']);

				$manifest_array['WP_BLOG_ID'] = $item['blog-id'];

				if (is_multisite()) {
					$manifest_array['WP_MULTISITE'] = 1;

					if (is_main_site(intval($item['blog-id'])))
						$manifest_array['WP_MULTISITE_MAIN_SITE'] = 1;

					$manifest_array['WP_HOME'] 		= get_blog_option( intval($item['blog-id']), 'home' );
					$manifest_array['WP_SITEURL'] 	= get_blog_option( intval($item['blog-id']), 'siteurl' );

					$blog_details = get_blog_details( intval($item['blog-id']) );
					if (isset($blog_details->blogname))
						$manifest_array['WP_BLOG_NAME'] = $blog_details->blogname;

					if (isset($blog_details->domain))
						$manifest_array['WP_BLOG_DOMAIN'] = $blog_details->domain;

					if (isset($blog_details->path))
						$manifest_array['WP_BLOG_PATH'] = $blog_details->path;

					if ( defined( 'UPLOADBLOGSDIR' ) ) {
						$manifest_array['WP_UPLOADBLOGSDIR'] = UPLOADBLOGSDIR;
					}

					// We can't use the 'UPLOADS' defined because it is set via the live site and does ot changes when using switch blog
					//if ( defined( 'UPLOADS' ) ) {
					//	$manifest_array['WP_UPLOADS'] = UPLOADS;
					//}

				} else {
					$manifest_array['MULTISITE'] 	= 0;
					$manifest_array['WP_HOME'] 		= get_option( 'home' );
					$manifest_array['WP_BLOG_NAME'] = get_option( 'blogname' );

					$home_url_parts = parse_url($manifest_array['WP_HOME']);
					if (isset($home_url_parts['host']))
						$manifest_array['WP_BLOG_DOMAIN'] = $home_url_parts['host'];
					if (isset($home_url_parts['path']))
						$manifest_array['WP_BLOG_PATH'] = $home_url_parts['path'];

					$manifest_array['WP_SITEURL'] 	= get_option( 'siteurl' );
				}
				global $wp_version, $wp_db_version;

				$manifest_array['WP_VERSION'] = $wp_version;
				$manifest_array['WP_DB_VERSION'] = $wp_db_version;

				$manifest_array['WP_DB_NAME'] = snapshot_utility_get_db_name();
				$manifest_array['WP_DB_BASE_PREFIX'] = $wpdb->base_prefix;
				$manifest_array['WP_DB_PREFIX'] = $wpdb->get_blog_prefix( intval($item['blog-id']) );
				$manifest_array['WP_UPLOAD_PATH'] = snapshot_utility_get_blog_upload_path(intval($item['blog-id']), 'basedir');

				$manifest_array['WP_UPLOAD_URLS'] = snapshot_utility_get_blog_upload_path(intval($item['blog-id']), 'baseurl');
				//if (is_multisite()) && (!is_main_site()) {
				//$manifest_array['WP_UPLOAD_URL_UNFILTERED'] = snapshot_utility_get_blog_upload_path(intval($item['blog-id']), 'baseurl', false);
				//}

				$manifest_array['SEGMENT_SIZE'] = intval($this->config_data['config']['segmentSize']);

				$item_tmp = $item;
				if (isset($item_tmp['data']))
					unset($item_tmp['data']);
				$item_tmp['data'] = array();

				$item_tmp['data'][$data_item_key] = $data_item;
				$manifest_array['ITEM'] = $item_tmp;

				if (isset($this->_session->data['tables_sections'])) {
					//fwrite($fp, "TABLES:". serialize($this->_session->data['tables_sections']) ."\r\n");
					$manifest_array['TABLES'] = $this->_session->data['tables_sections'];
				}

				if (isset($this->_session->data['table_data'])) {
					//fwrite($fp, "TABLES-DATA:". serialize($this->_session->data['table_data']) ."\r\n");
					$manifest_array['TABLES-DATA'] = $this->_session->data['table_data'];
				}

				if (isset($session_files_data)) {
					// We want to remove the ABSPATH from the stored file items.

					foreach($session_files_data as $file_item_idx => $file_item) {
						$session_files_data[$file_item_idx] = str_replace($home_path, '', $file_item);
					}
					//fwrite($fp, "FILES-DATA:". serialize($this->_session->data['files_data']) ."\r\n");
					//$manifest_array['FILES-DATA'] = $session_files_data;
				}

				// Let's actually create the zip file from the files_array. We strip off the leading path (3rd param)
				$backupZipFile = trailingslashit($this->_session->data['backupItemFolder']) .'snapshot-backup.zip';
				//if (file_exists($backupZipFile)) {

					/* Create a zip manifest file */
					$manifestFile = trailingslashit($this->_session->data['backupItemFolder']) . 'snapshot_manifest.txt';
					if (snapshot_utility_create_archive_manifest($manifest_array, $manifestFile)) {

						$archiveFiles = array();
						$archiveFiles[] = $manifestFile;

						// Let's actually create the zip file from the files_array. We strip off the leading path (3rd param)
						$backupZipFile = trailingslashit($this->_session->data['backupItemFolder']) .'snapshot-backup.zip';

						if ($this->config_data['config']['zipLibrary'] == "PclZip") {

							$zipArchive = new PclZip($backupZipFile);
							$zipArchive->add($archiveFiles,
											PCLZIP_OPT_REMOVE_PATH, $this->_session->data['backupItemFolder'],
											PCLZIP_OPT_TEMP_FILE_THRESHOLD, 10,
											PCLZIP_OPT_ADD_TEMP_FILE_ON);
							unset($zipArchive);

						} else if ($this->config_data['config']['zipLibrary'] == "ZipArchive") {
							$zipArchive = new ZipArchive();
							if ($zipArchive) {
								if (!file_exists($backupZipFile))
									$zip_flags = ZIPARCHIVE::CREATE;
								else
									$zip_flags = null;
								$zip_hdl = $zipArchive->open($backupZipFile, $zip_flags);
								if ($zip_hdl !== TRUE) {
									$error_status['errorStatus'] 	= true;

									$error_status['errorText'] 		= "ERROR: ZipArchive file:". baename($manifestFile) ." : add failed: ".
										ZipArchiveStatusString($zip_hdl);
									return $error_status;
								}

								foreach($archiveFiles as $file) {
									$file = str_replace('\\', '/', $file);
									$zipArchive->addFile($file, str_replace($this->_session->data['backupItemFolder'].'/', '', $file));
								}
								$zipArchive->close();
							}
						}

						foreach($archiveFiles as $archiveFile) {
							@unlink($archiveFile);
						}
					}

					$checksum = snapshot_utility_get_file_checksum($backupZipFile);
					//$date_key = date('ymd-His', $item_key); // This timestamp format is used for the filename on disk.
					$date_key = date('ymd-His', $data_item_key); // This timestamp format is used for the filename on disk.
					$backupZipFilename = 'snapshot-'. $item_key .'-'. $date_key . '-'. $checksum .'.zip';

					$data_item['filename'] = $backupZipFilename;
					$data_item['file_size'] = filesize($backupZipFile);

					//$backupZipFolder = $this->snapshot_get_item_destination_path($item, $data_item, true);

					if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {
						$backupZipFolder = $this->snapshot_get_item_destination_path($item, $data_item, true);
						$this->snapshot_logger->log_message('backupZipFolder['. $backupZipFolder .']');

						if (empty($backupZipFolder)) {
							$backupZipFolder = $this->_settings['backupBaseFolderFull'];
						}
					} else {
						$backupZipFolder = $this->_settings['backupBaseFolderFull'];
					}

					$backupZipFileFinal = trailingslashit($backupZipFolder) . $backupZipFilename;
					if (file_exists($backupZipFileFinal))
						@unlink($backupZipFileFinal);

					$this->snapshot_logger->log_message('rename: backupZipFile['. $backupZipFile .'] backupZipFileFinal['. $backupZipFileFinal .']');

					// Remove the destination file if it exists. If should not but just in case.
					if (file_exists($backupZipFileFinal)) {
						@unlink( $backupZipFileFinal );
					}

					//$rename_ret = @rename($backupZipFile, $backupZipFileFinal);
					$rename_ret = rename($backupZipFile, $backupZipFileFinal);
					if ($rename_ret === false) {
						//$this->snapshot_logger->log_message('rename: failed: error:'. print_r(error_get_last(), true) .'');

						// IF for some reason the destination path is not our default snapshot backups folder AND we could not not rename to that
						// alternate path. We then try the default snapshot destination.
						if ( trailingslashit($this->_settings['backupBaseFolderFull']) != trailingslashit(dirname($backupZipFileFinal)) ) {

							$backupZipFileTMP = trailingslashit($this->_settings['backupBaseFolderFull']) . basename($backupZipFileFinal);
							$this->snapshot_logger->log_message('rename: backupZipFile['. $backupZipFile .'] backupZipFileFinal['. $backupZipFileTMP .']');
							$rename_ret = rename($backupZipFile, $backupZipFileTMP);
							if ($rename_ret !== false) {
								$this->snapshot_logger->log_message('rename: success');
								$error_status['responseFile'] = basename($backupZipFileFinal);

								$data_item['destination-directory'] = '';
							}
						}
					} else {
						$error_status['responseFile'] = basename($backupZipFileFinal);
					}

					$error_status['responseFile'] = basename($backupZipFileFinal);

					// echo out the finished message so the user knows we are done.
					$error_status['responseText'] =  __("SUCCESS: Created Snapshot: ", SNAPSHOT_I18N_DOMAIN) . basename($backupZipFileFinal) ."<br />".
						'<a href="'. $this->_settings['SNAPSHOT_MENU_URL'].'snapshots_new_panel">'. __("Add Another Snapshot") .'</a>';

				//}

				if (!isset($item['data']))
					$item['data'] 						= 	array();

				// Add the file entry to the data section of out snapshot item
				$item['data'][$data_item_key] = $data_item;
				ksort($item['data']);

				$this->config_data['items'][$item_key] = $item;

				if ((isset($this->_session->data['tables_sections']))
				 && (isset($this->_session->data['table_data'])) && (count($this->_session->data['table_data']))) {
					if (!isset($this->config_data['config']['tables_last'][$item['blog-id']]))
						$this->config_data['config']['tables_last'][$item['blog-id']] = array();

					$this->config_data['config']['tables_last'][$item['blog-id']] = $this->_session->data['tables_sections'];
				}

				//unset($this->_session->data);

				return $error_status;
			}
		}

		/**
		 * AJAX callback function from the snapshot add new form. Used to update the blog tables listing
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param $_POST['blog_id'] designates the blog to show tables for.
		 * @return JSON formatted array of tables. This is a multi-dimensional array containing groups for 'wp' - WordPress core, 'other' - Non core tables
		 */

		function snapshot_ajax_show_blog_tables() {
			global $wpdb;

			//echo "POST<pre>"; print_r($_POST); echo "</pre>";
			$blog_id = 0;
			$json_data = array();

			if (isset($_POST['snapshot_blog_id_search'])) {
				$snapshot_blog_id_search = esc_attr($_POST['snapshot_blog_id_search']);
				$PHP_URL_SCHEME = parse_url($snapshot_blog_id_search, PHP_URL_SCHEME);

				if (!empty($PHP_URL_SCHEME)) {
					$snapshot_blog_id_search = str_replace($PHP_URL_SCHEME."://", '', $snapshot_blog_id_search);
				}

				if (intval($snapshot_blog_id_search) != 0) {
					$blog_id = intval($snapshot_blog_id_search);
				} else {

					global $wpdb;

					$current_domain = apply_filters( 'snapshot_current_domain',  DOMAIN_CURRENT_SITE );
					$current_path = apply_filters( 'snapshot_current_path',  PATH_CURRENT_SITE );

					if (is_subdomain_install()) {
						if (!empty($snapshot_blog_id_search)) {
							$full_domain = $snapshot_blog_id_search .".". $current_domain;
							// $full_domain = $snapshot_blog_id_search .".". $current_domain.$current_path;
						} else {
							$full_domain = $current_domain;
							// $full_domain = $current_domain.current_path;
						}
						$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s LIMIT 1", $full_domain);
					} else {
						$snapshot_blog_id_search_path = trailingslashit($snapshot_blog_id_search);
						if( '/' == $snapshot_blog_id_search_path ) {
							$snapshot_blog_id_search_path = '';
						}

						$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s LIMIT 1",
							$current_domain, $current_path . $snapshot_blog_id_search_path );
					}

					//echo "sql_str=[". $sql_str ."]<br />";
					$blog = $wpdb->get_row( $sql_str );
					if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
						$blog_id = intval($blog->blog_id);
					} else if (!$blog) {
						if ((function_exists('is_plugin_active')) && (is_plugin_active('domain-mapping/domain-mapping.php'))) {
							$sql_str = $wpdb->prepare("SELECT blog_id FROM ". $wpdb->prefix ."domain_mapping WHERE domain = %s LIMIT 1",
								$snapshot_blog_id_search);
							//echo "sql_str=[". $sql_str ."]<br />";
							$blog = $wpdb->get_row( $sql_str );
							if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
								$blog_id = intval($blog->blog_id);
							}
						}
					}
				}
			}

			if ($blog_id > 0) {
				$json_data['blog'] = get_blog_details($blog_id);

				if ((function_exists('is_plugin_active')) && (is_plugin_active('domain-mapping/domain-mapping.php'))) {
					$sql_str = $wpdb->prepare("SELECT domain FROM ". $wpdb->prefix ."domain_mapping WHERE blog_id = %d AND active=1 LIMIT 1",
						$blog_id);
					//echo "sql_str=[". $sql_str ."]<br />";
					$mapped_domain = $wpdb->get_row( $sql_str );
					if ((isset($mapped_domain->domain)) && (!empty($mapped_domain->domain))) {
						$json_data['mapped_domain'] = $mapped_domain->domain;
					}
				}


				$tables = snapshot_utility_get_database_tables($blog_id);
				if ($tables) {

					/* Grab the last set of tables for this blog_id */
					$last_tables = array();
					if (isset($this->config_data['config']['tables_last'][$blog_id])) {
						$last_tables = $this->config_data['config']['tables_last'][$blog_id];
					}

					foreach($tables as $table_key => $table_set) {

						foreach($table_set as $table_name => $table_val) {

							/* If this table was in the last_tables for this blog set the value to on so it will be checked for the user */
							if (array_search($table_name, $last_tables) !== false) {
								$table_set[$table_name] = "checked";
							} else {
								$table_set[$table_name] = "";
							}
						}
						ksort($table_set);
						$tables[$table_key] = $table_set;
					}
					$json_data['tables'] = $tables;

					$upload_path = snapshot_utility_get_blog_upload_path($blog_id);
					$json_data['upload_path'] = $upload_path;

					if (is_multisite()) {
						if (is_main_site($blog_id))
							$json_data['is_main_site'] = "YES";
						else
							$json_data['is_main_site'] = "NO";

					} else {
						$json_data['is_main_site'] = "YES";
					}
				}
			}
			echo json_encode($json_data);
			die();
		}

		function snapshot_get_blog_restore_info() {
			global $wpdb;

			//echo "POST<pre>"; print_r($_POST); echo "</pre>";
			$blog_id = 0;
			$json_data = array();

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (isset($_POST['snapshot_blog_id_search'])) {
				$snapshot_blog_id_search = esc_attr($_POST['snapshot_blog_id_search']);
				$PHP_URL_SCHEME = parse_url($snapshot_blog_id_search, PHP_URL_SCHEME);
				if (!empty($PHP_URL_SCHEME)) {
					$snapshot_blog_id_search = str_replace($PHP_URL_SCHEME."://", '', $snapshot_blog_id_search);
				}

				if (intval($snapshot_blog_id_search) != 0) {
					$blog_id = intval($snapshot_blog_id_search);
				} else {

					$current_domain = apply_filters( 'snapshot_current_domain',  DOMAIN_CURRENT_SITE );
					$current_path = apply_filters( 'snapshot_current_path',  PATH_CURRENT_SITE );

					if (is_subdomain_install()) {
						if (!empty($snapshot_blog_id_search)) {
							// $full_domain = $snapshot_blog_id_search . "." . $current_domain . $current_path;
							$full_domain = $snapshot_blog_id_search . "." . $current_domain;
						} else {
							// $full_domain = $current_domain . $current_path;
							$full_domain = $current_domain;
						}

						$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s LIMIT 1", $full_domain);
					} else {
						$snapshot_blog_id_search_path = trailingslashit($snapshot_blog_id_search);
						if( '/' == $snapshot_blog_id_search_path ) {
							$snapshot_blog_id_search_path = '';
						}
						$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s LIMIT 1",
							$current_domain, $current_path . $snapshot_blog_id_search_path );
					}
					//echo "sql_str=[". $sql_str ."]<br />";
					$blog = $wpdb->get_row( $sql_str );
					if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
						$blog_id = intval($blog->blog_id);
					} else if (!$blog) {
						if ((function_exists('is_plugin_active')) && (is_plugin_active('domain-mapping/domain-mapping.php'))) {
							$sql_str = $wpdb->prepare("SELECT blog_id FROM ". $wpdb->prefix ."domain_mapping WHERE domain = %s LIMIT 1",
								$snapshot_blog_id_search);
							//echo "sql_str=[". $sql_str ."]<br />";
							$blog = $wpdb->get_row( $sql_str );
							if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
								$blog_id = intval($blog->blog_id);
							}
						}
					}
				}
			}

			if ($blog_id > 0) {
				$json_data['blog'] = get_blog_details($blog_id);

				if ((function_exists('is_plugin_active')) && (is_plugin_active('domain-mapping/domain-mapping.php'))) {
					$sql_str = $wpdb->prepare("SELECT domain FROM ". $wpdb->prefix ."domain_mapping WHERE blog_id = %d AND active=1 LIMIT 1",
						$blog_id);
					//echo "sql_str=[". $sql_str ."]<br />";
					$mapped_domain = $wpdb->get_row( $sql_str );
					if ((isset($mapped_domain->domain)) && (!empty($mapped_domain->domain))) {
						$json_data['mapped_domain'] = $mapped_domain->domain;
					}
				}

				switch_to_blog(intval($blog_id));

				$json_data['WP_DB_BASE_PREFIX'] = $wpdb->base_prefix;
				$json_data['WP_DB_PREFIX'] = $wpdb->get_blog_prefix( $blog_id );
				$json_data['WP_DB_NAME'] = snapshot_utility_get_db_name();

				$uploads = wp_upload_dir();

				if (isset($uploads['basedir'])) {
					$uploads['basedir'] = str_replace('\\', '/', $uploads['basedir']);
					$json_data['WP_UPLOAD_PATH'] = str_replace($home_path, '', $uploads['basedir']);
				}

				restore_current_blog();
			}
			echo json_encode($json_data);
			die();
		}

		/**
		 * AJAX callback function from the snapshot restore form.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param none
		 * @return JSON formatted array status.
		 */

		function snapshot_ajax_restore_proc() {
			// When zlib compression is turned on we get errors from this shutdown action setup by WordPress. So we disabled.
			$zlib_compression = ini_get('zlib.output_compression');
			if ($zlib_compression)
				remove_action( 'shutdown',	'wp_ob_end_flush_all', 1);

			@ini_set('html_errors', 'Off');
			@ini_set('zlib.output_compression', 'Off');
			@set_time_limit(0);

			if (isset($_POST['item_key'])) {
				$item_key = intval($_POST['item_key']);
			}

			if (isset($_POST['item_data'])) {
				$data_item_key	= 	intval($_POST['item_data']);
			}
			$this->snapshot_logger = new SnapshotLogger($this->_settings['backupLogFolderFull'], $item_key, $data_item_key);

			$old_error_handler = set_error_handler(array( &$this, 'snapshot_ErrorHandler' ));

			snapshot_utility_set_error_reporting($this->config_data['config']['errorReporting']);

			if ((isset($this->config_data['config']['memoryLimit'])) && (!empty($this->config_data['config']['memoryLimit']))) {
				@ini_set('memory_limit', $this->config_data['config']['memoryLimit']);
			}

			if ((isset($item_key)) && (isset($data_item_key))) {

				if (isset($this->config_data['items'][$item_key])) {
					$item = $this->config_data['items'][$item_key];

					/*
					$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
						": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
						": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );
					*/
					switch(sanitize_text_field($_REQUEST['snapshot_action']))
					{
						case 'init':
							$this->snapshot_logger->log_message('restore: init');

							// Start/load our sessions file
							$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key, true);

							ob_start();
							$error_array = $this->snapshot_ajax_restore_init($item);
							$function_output = ob_get_contents();
							ob_end_clean();

							if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("init: _POST". print_r($_POST, true));
								$this->snapshot_logger->log_message("init: error_array". print_r($error_array, true));
								$this->snapshot_logger->log_message("init: _SESSION". print_r($this->_session->data, true));
								$this->snapshot_logger->log_message("init: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								echo json_encode($error_array);

								die();
							}

							break;


						case 'table':

							// Start/load our sessions file
							$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);

							ob_start();
							$result = $this->snapshot_ajax_restore_table($item);
							$error_array = $result;
							$function_output = ob_get_contents();
							ob_end_clean();
							if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("table: _POST". print_r($_POST, true));
								$this->snapshot_logger->log_message("table: error_array". print_r($error_array, true));
								$this->snapshot_logger->log_message("table: _SESSION". print_r($this->_session, true));
								$this->snapshot_logger->log_message("table: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								echo json_encode($error_array);

								die();
							}
							break;

						case 'file':

							// Start/load our sessions file
							$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);

							ob_start();
							$error_array = $this->snapshot_ajax_restore_file($item);
							$function_output = ob_get_contents();
							ob_end_clean();
							if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("file: _POST". print_r($_POST, true));
								$this->snapshot_logger->log_message("file: error_array". print_r($error_array, true));
								$this->snapshot_logger->log_message("file: _SESSION". print_r($this->_session, true));
								$this->snapshot_logger->log_message("file: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								echo json_encode($error_array);

								die();
							}
							break;


						case 'finish':

							$this->snapshot_logger->log_message('restore: finish:');

							// Start/load our sessions file
							$this->_session = new SnapshotSessions(trailingslashit($this->_settings['backupSessionFolderFull']), $item_key);

							ob_start();
							$error_array = $this->snapshot_ajax_restore_finish($item);
							$function_output = ob_get_contents();
							ob_end_clean();
							if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("finish: _POST". print_r($_POST, true));
								$this->snapshot_logger->log_message("finish: error_array". print_r($error_array, true));
								$this->snapshot_logger->log_message("finish: _SESSION". print_r($this->_session, true));
								$this->snapshot_logger->log_message("finish: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								echo json_encode($error_array);

								die();
							}
							$this->snapshot_logger->log_message("restore: memory_limit: ". ini_get('memory_limit'));

							break;

						default:
							break;
					}
					/*
					$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
						": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
						": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );
					*/
				}
			}
			$this->save_config(true);

			if (isset($error_array))
				echo json_encode($error_array);

			die();
		}

		/**
		 * AJAX callback function from the snapshot restore form. This is the first
		 * step of the restore. This step will unzip the archive and retrieve the
		 * the MANIFEST file content.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param none
		 * @return JSON formatted array status.
		 */

		function snapshot_ajax_restore_init($item) {
			global $wpdb, $current_blog;

			$error_status = array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (!isset($_POST['item_data'])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "<p>". __("ERROR: The Snapshot missing 'item_data' key",
					SNAPSHOT_I18N_DOMAIN)  ."</p>";

				return $error_status;
			}

			$item_data = intval($_POST['item_data']);

			if (!isset($item['data'][$item_data])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "<p>". __("ERROR: The Snapshot incorrect 'item_data' [". $data_item ."] key",
					SNAPSHOT_I18N_DOMAIN)  ."</p>";

				return $error_status;
			}
			//$error_status['data'] = $item['data'];
			$data_item = $item['data'][$item_data];
			//echo "data_item<pre>"; print_r($data_item); echo "</pre>";

			$backupZipFolder = $this->snapshot_get_item_destination_path($item, $data_item, false);
			//echo "backupZipFolder[". $backupZipFolder ."]<br />";
			//die();
			$restoreFile = trailingslashit($backupZipFolder) . $data_item['filename'];
			$error_status['restoreFile'] = $restoreFile;
			if (!file_exists($restoreFile)) {
				$error_status_errorText 		= "<p>". __("ERROR: The Snapshot file not found:",
					SNAPSHOT_I18N_DOMAIN) . " ". $restoreFile ."</p>";

				$restoreFile = trailingslashit($this->_settings['backupBaseFolderFull']) . $data_item['filename'];
				$error_status['restoreFile'] = $restoreFile;

				if (!file_exists($restoreFile)) {
					$error_status['errorStatus'] 	= true;
					$error_status['errorText'] 		= $error_status_errorText ."<p>". __("ERROR: The Snapshot file not found:",
						SNAPSHOT_I18N_DOMAIN) . " ". $restoreFile ."</p>";

					return $error_status;
				}
			}

			// Create a unique folder for our restore processing. Will later need to remove it.
			$sessionRestoreFolder = trailingslashit($this->_settings['backupRestoreFolderFull']);
			wp_mkdir_p($sessionRestoreFolder);
			if (!is_writable($sessionRestoreFolder)) {
				$error_status['errorStatus'] = true;
				$error_status['errorText'] = "<p>". __("ERROR: The Snapshot folder is not writeable. Check the settings",
				SNAPSHOT_I18N_DOMAIN) . " ". $sessionRestoreFolder ."</p>";

				return $error_status;
			}

			// Cleanup any files from a previous restore attempt
			if ($dh = opendir($sessionRestoreFolder)) {
				while (($file = readdir($dh)) !== false) {
					if (($file == '.') || ($file == '..'))
						continue;

					snapshot_utility_recursive_rmdir($sessionRestoreFolder . $file);
				}
				closedir($dh);
			}

			if ($this->config_data['config']['zipLibrary'] == "PclZip") {
				if (!defined('PCLZIP_TEMPORARY_DIR'))
					define('PCLZIP_TEMPORARY_DIR', trailingslashit($this->_settings['backupBackupFolderFull']) . $item['timestamp']. "/");
				if (!class_exists('class PclZip'))
					require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');
				$zipArchive = new PclZip($restoreFile);
				$zip_contents = $zipArchive->listContent();
				if ($zip_contents) {
					$extract_files = $zipArchive->extract(PCLZIP_OPT_PATH, $sessionRestoreFolder);
					if ($extract_files) {
						$this->_session->data['restoreFolder'] = $sessionRestoreFolder;
					}
				}

			} else {
				$zip = new ZipArchive;
				$res = $zip->open($restoreFile);
				if ($res === TRUE) {
				    $extract_ret = $zip->extractTo($sessionRestoreFolder);
					if ($extract_ret !== false) {
						$this->_session->data['restoreFolder'] = $sessionRestoreFolder;
					}
				}
			}

			$error_status['MANIFEST'] = array();
			$snapshot_manifest_file = trailingslashit($sessionRestoreFolder) . 'snapshot_manifest.txt';
			if (file_exists($snapshot_manifest_file)) {
				$error_status['MANIFEST'] = snapshot_utility_consume_archive_manifest($snapshot_manifest_file);
				//unlink($snapshot_manifest_file);
			}

			if (isset($error_status['MANIFEST']['SNAPSHOT_VERSION'])) {
				if (($error_status['MANIFEST']['SNAPSHOT_VERSION'] == "1.0") && (!isset($error_status['MANIFEST']['TABLES-DATA']))) {

					$backupFile = trailingslashit($sessionRestoreFolder) . 'snapshot_backups.sql';
					$table_segments = snapshot_utility_get_table_segments_from_single($backupFile);
					if ($table_segments) {
						$error_status['MANIFEST']['TABLES-DATA'] = $table_segments;
						unlink($backupFile);
					}
				}
			}

			if (is_multisite()) {
				//echo "item<pre>"; print_r($item); echo "</pre>";
				//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
				//echo "_POST<pre>"; print_r($_POST); echo "</pre>";

				//switch_to_blog( $item['blog-id'] );
				$error_status['MANIFEST']['RESTORE']['SOURCE'] 							= array();
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_BLOG_ID'] 			= $error_status['MANIFEST']['WP_BLOG_ID'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'] 			= $error_status['MANIFEST']['WP_DB_PREFIX'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'] 	= $error_status['MANIFEST']['WP_DB_BASE_PREFIX'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_SITEURL'] 			= $error_status['MANIFEST']['WP_SITEURL'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR']			= $error_status['MANIFEST']['WP_UPLOAD_PATH'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_UPLOAD_URLS'] 		= $error_status['MANIFEST']['WP_UPLOAD_URLS'];

				switch_to_blog( $_POST['snapshot-blog-id'] );

				$error_status['MANIFEST']['RESTORE']['DEST']							= array();
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] 				= $_POST['snapshot-blog-id'];
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX'] 			= $wpdb->get_blog_prefix( $_POST['snapshot-blog-id'] );
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_BASE_PREFIX'] 		= $wpdb->base_prefix;
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_SITEURL'] 				= get_site_url( $_POST['snapshot-blog-id'] );

				$wp_upload_dir = wp_upload_dir();
				//echo "wp_upload_dir<pre>"; print_r($wp_upload_dir); echo "</pre>";
				//die();

				$wp_upload_dir['basedir'] = str_replace('\\', '/', $wp_upload_dir['basedir']);
				$error_status['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']				= str_replace($home_path, '', $wp_upload_dir['basedir']);

				//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
				//die();

			} else {
				$error_status['MANIFEST']['RESTORE']['SOURCE'] 							= array();
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_BLOG_ID'] 			= $error_status['MANIFEST']['WP_BLOG_ID'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'] 			= $error_status['MANIFEST']['WP_DB_PREFIX'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'] 	= $error_status['MANIFEST']['WP_DB_BASE_PREFIX'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_SITEURL'] 			= $error_status['MANIFEST']['WP_SITEURL'];
				$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_UPLOAD_URLS'] 		= $error_status['MANIFEST']['WP_UPLOAD_URLS'];

				$wp_upload_dir = wp_upload_dir();
				$wp_upload_dir['basedir'] = str_replace('\\', '/', $wp_upload_dir['basedir']);
				$error_status['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR']			= $wp_upload_dir['basedir'];

				$error_status['MANIFEST']['RESTORE']['DEST']							= array();
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] 				= $error_status['MANIFEST']['WP_BLOG_ID'];
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX'] 			= $wpdb->prefix;
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_BASE_PREFIX'] 		= $wpdb->base_prefix;
				$error_status['MANIFEST']['RESTORE']['DEST']['WP_SITEURL'] 				= get_site_url( $error_status['MANIFEST']['WP_BLOG_ID'] );

				$error_status['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']				= $error_status['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'];

			}

			if (!isset($_POST['snapshot-tables-option']))
				$_POST['snapshot-tables-option'] = "none";

			if ($_POST['snapshot-tables-option'] == "none") {

				unset($error_status['MANIFEST']['TABLES']);
				$error_status['MANIFEST']['TABLES'] = array();

			} else if ($_POST['snapshot-tables-option'] == "selected") {

				if (isset($_POST['snapshot-tables-array']))
					$error_status['MANIFEST']['TABLES'] = $_POST['snapshot-tables-array'];

			} else if ($_POST['snapshot-tables-option'] == "all") {

				$manifest_tables = array();
				foreach($error_status['MANIFEST']['TABLES'] as $table_set_key => $table_set) {

					// Per the instructions on the page. When selecting 'all' we do not include the global tables: users and usermeta
					if ($table_set_key == 'global') {
						continue;
					}
					$manifest_tables = array_merge($manifest_tables, array_values($table_set));
				}
				//echo "manifest_tables<pre>"; print_r($manifest_tables); echo "</pre>";
				//die();

				$error_status['MANIFEST']['TABLES'] = $manifest_tables;
			}

			//echo "RESTORE<pre>"; print_r($error_status['MANIFEST']['RESTORE']); echo "</pre>";
			//echo "TABLES<pre>"; print_r($error_status['MANIFEST']['TABLES']); echo "</pre>";
			//echo "MANIFEST<pre>"; print_r($error_status['MANIFEST']); echo "</pre>";
			//echo "wpdb<pre>"; print_r($wpdb); echo "</pre>";
			//die();

			// upload_path wp-content/blogs.dir/7/files

			if ((isset($error_status['MANIFEST']['TABLES'])) && (count($error_status['MANIFEST']['TABLES']))) {
				$tables_array = array();

				foreach($error_status['MANIFEST']['TABLES'] as $table_name) {
					$table_info = array();
					$table_info['table_name'] = $table_name;

					if (strncasecmp($table_name, $error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'],
					 	strlen($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'])) == 0) {
							$table_info['table_name_base'] = str_replace($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'], '', $table_name);

							$table_info['table_name_restore'] = $this->_settings['recover_table_prefix'] . str_replace(
								$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'],
								$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX'],
								$table_name);

							$table_name_dest = str_replace($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'],
								$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX'], $table_name);

					} else if (strncasecmp($table_name, $error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'],
						strlen($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'])) == 0) {
							$table_info['table_name_base'] = str_replace($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'], '', $table_name);

							$table_info['table_name_restore'] = $this->_settings['recover_table_prefix'] . str_replace(
								$error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'],
								$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_BASE_PREFIX'],
								$table_name);

							$table_name_dest = str_replace($error_status['MANIFEST']['RESTORE']['SOURCE']['WP_DB_BASE_PREFIX'],
								$error_status['MANIFEST']['RESTORE']['DEST']['WP_DB_BASE_PREFIX'], $table_name);
					} else {
						// If the table name is not using the DB_PREFIX or DB_BASE_PREFIX then don't convert it.
						$table_info['table_name_base'] = $table_name;
						$table_info['table_name_restore'] = $table_name;
						$table_name_dest = $table_name;
					}

					$table_info['label'] 				= $table_name ." > ". $table_name_dest;
					$table_info['table_name_dest'] 		= $table_name_dest;

					$tables_array[$table_name] 			= $table_info;
				}
				$error_status['MANIFEST']['TABLES'] = $tables_array;
				//echo "MANIFEST<pre>"; print_r($error_status['MANIFEST']['TABLES']); echo "</pre>";
				//die();
			}

			if ((isset($error_status['MANIFEST']['TABLES-DATA'])) && (count($error_status['MANIFEST']['TABLES-DATA']))) {
				$tables_data_sets = array();
				foreach($error_status['MANIFEST']['TABLES-DATA'] as $table_set) {
					if (!isset($table_set['table_name'])) continue;
					//echo "table_set table_name[". $table_set['table_name'] ."]<br />";

					if (array_key_exists($table_set['table_name'], $error_status['MANIFEST']['TABLES']) !== false) {
						$tables_data_sets[] = $table_set;
					} else {
						//echo "Table[". $table_set['table_name'] ."] not found in tables<br />";
					}
				}
				$error_status['MANIFEST']['TABLES-DATA'] = $tables_data_sets;
			}
			//echo "MANIFEST<pre>"; print_r($error_status['MANIFEST']['TABLES']); echo "</pre>";
			//echo "MANIFEST<pre>"; print_r($error_status['MANIFEST']['TABLES-DATA']); echo "</pre>";
			//die();


			if (!isset($_POST['snapshot-files-option']))
				$_POST['snapshot-files-option'] = "none";

			if ($_POST['snapshot-files-option'] == "none") {

				unset($error_status['MANIFEST']['FILES-DATA']);
				$error_status['MANIFEST']['FILES-DATA'] = array();

			} else if ($_POST['snapshot-files-option'] == "selected") {
				if (isset($_POST['snapshot-files-sections'])) {
					$error_status['MANIFEST']['FILES-DATA'] = $_POST['snapshot-files-sections'];
				}
			} else if ($_POST['snapshot-files-option'] == "all") {
				if (isset($error_status['MANIFEST']['ITEM']['data'])) {
					$data_item = snapshot_utility_latest_data_item($error_status['MANIFEST']['ITEM']['data']);
					if (isset($data_item['files-sections'])) {
						$error_status['MANIFEST']['FILES-DATA'] = array_values($data_item['files-sections']);

						$array_idx = array_search('config', $error_status['MANIFEST']['FILES-DATA']);
						if ($array_idx !== false) {
							unset($error_status['MANIFEST']['FILES-DATA'][$array_idx]);
						}

						$array_idx = array_search('htaccess', $error_status['MANIFEST']['FILES-DATA']);
						if ($array_idx !== false) {
							unset($error_status['MANIFEST']['FILES-DATA'][$array_idx]);
						}
					}
				}
			}

			//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
			//echo "MANIFEST<pre>"; print_r($error_status['MANIFEST']); echo "</pre>";
			//echo "MANIFEST RESTORE<pre>"; print_r($error_status['MANIFEST']['RESTORE']); echo "</pre>";
			//die();

			$this->_session->data['MANIFEST'] = $error_status['MANIFEST'];
			return $error_status;
		}

		/**
		 * AJAX callback function from the snapshot restore form. This is the second
		 * step of the restore. This step will receives a table name to restore.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param array $item from the snapshot history.
		 * @param string $_POST['snapshot_table] send from AJAX for table name to restore.
		 * @return JSON formatted array status.
		 */

		function snapshot_ajax_restore_table($item) {
			global $wpdb, $current_blog;

			$error_status = array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			if ((is_multisite()) && ($current_blog->blog_id != $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'])) {
				$wpdb->set_blog_id( $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] );
			}

			if ((isset($_POST['snapshot_table'])) && (isset($_POST['table_data']))) {

				$this->snapshot_logger->log_message('restore: table: '. $_POST['snapshot_table'] .' ('. $_POST['table_data']['segment_idx'] .'/'.
				 	$_POST['table_data']['segment_total'].')');

				$table_data = $_POST['table_data'];
				//echo "table_data<pre>"; print_r($table_data); echo "</pre>";
				$table_name = $table_data['table_name'];

				if (isset($this->_session->data['MANIFEST']['TABLES'][$table_name])) {
					$table_set = $this->_session->data['MANIFEST']['TABLES'][$table_name];
				} else {
					echo "table_set for [". $table_name ."] not found<br />";
					echo "TABLES<pre>"; print_r($this->_session->data['MANIFEST']['TABLES']); echo "</pre>";

					die();
					//return;
				}
				//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
				//echo "MANIFEST<pre>"; print_r($this->_session->data['MANIFEST']); echo "</pre>";
				//die();
				$restoreFile = trailingslashit($this->_session->data['restoreFolder']) . esc_attr($_POST['snapshot_table']) . ".sql";
				if (file_exists($restoreFile)) {

					$fp = fopen($restoreFile, 'r');
					if ($fp) {
						fseek($fp, $_POST['table_data']['ftell_before']);
						$backup_file_content = fread($fp, $table_data['ftell_after'] - $table_data['ftell_before']);

						$source_table_name 	= $_POST['snapshot_table'];
						$dest_table_name	= $table_set['table_name_restore'];

						if ((!empty($source_table_name)) && (!empty($dest_table_name))) {
							$backup_file_content = str_replace("`". $source_table_name ."`", "`". $dest_table_name ."`", $backup_file_content);
						}

						@set_time_limit( 300 );
						$backup_db = new SnapshotBackupDatabase( );
						$backup_db->restore_databases($backup_file_content);

						// Check if there were any processing errors during the backup
						if (count($backup_db->errors)) {

							// If yes then append to our admin header error and return.
							foreach($backup_db->errors as $error) {
								$this->_admin_header_error .= $error;
							}
						}
						unset($backup_db);
					}

					//if ($table_data['rows_total'] == ($table_data['rows_start']+$table_data['rows_end'])) {
					//	$this->snapshot_ajax_restore_convert_db_content($table_data);
					//}

					if ($table_data['segment_idx'] == $table_data['segment_total']) {
						//echo "table_data<pre>"; print_r($table_data); echo "</pre>";
						//die();

						$this->snapshot_ajax_restore_convert_db_content($table_data);
					}

				} else {
					$error_status['errorStatus'] = true;
					$error_status['errorText'] = "<p>". __("ERROR: Unable to locate table restore file from archive: ",
						SNAPSHOT_I18N_DOMAIN) . " ". basename($restoreFile) ."</p>";

					return $error_status;
				}
			}
			$error_status['table_data'] = $_POST['table_data'];

			return $error_status;
		}


		/**
		 * AJAX callback function from the snapshot restore form. This is the third
		 * step of the restore. This step will restore a single file to the original location.
		 *
		 * @since 1.0.7
		 * @see
		 *
		 * @param none
		 * @return JSON formatted array status.
		 */
		function snapshot_ajax_restore_file($item) {

			$error_status = array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "";

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (!isset($_POST['file_data_idx'])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "<p>". __("ERROR: The Snapshot missing 'file_data_idx' key", SNAPSHOT_I18N_DOMAIN)  ."</p>";

				return $error_status;

			} else {
				$file_data_idx = intval($_POST['file_data_idx']);
			}

			if (!isset($this->_session->data['MANIFEST']['FILES-DATA'])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "<p>". __("ERROR: The Snapshot missing session 'FILES-DATA' object.",
					SNAPSHOT_I18N_DOMAIN)  ."</p>";

				return $error_status;
			}

			if (!isset($this->_session->data['MANIFEST']['FILES-DATA'][$file_data_idx])) {
				$error_status['errorStatus'] 	= true;
				$error_status['errorText'] 		= "<p>". __("ERROR: The Snapshot missing restore file at idx [". $file_data_idx ."]",
					SNAPSHOT_I18N_DOMAIN)  ."</p>";

				return $error_status;
			}

			$this->snapshot_logger->log_message('restore: file-section: '. $this->_session->data['MANIFEST']['FILES-DATA'][$file_data_idx]);
			$restoreFilesBase = trailingslashit($this->_session->data['restoreFolder']) .'www/';
			$restoreFilesSet = array();

			$src_basedir 	= '';
			$dest_basedir 	= '';

			//echo "restoreFolder[". $this->_session->data['restoreFolder'] ."]<br />";
			//die();


			switch($this->_session->data['MANIFEST']['FILES-DATA'][$file_data_idx]) {
				case 'themes':
					$restoreFilesPath = $this->_session->data['restoreFolder'] . "www/wp-content/themes";
					if (is_dir($restoreFilesPath)) {
						$restoreFilesSet = snapshot_utility_scandir($restoreFilesPath);
					}
					break;

				case 'plugins':
					$restoreFilesPath = $restoreFilesBase . "wp-content/plugins";

					if (is_dir($restoreFilesPath)) {

						// Make sure the Snapshot plugin is NOT restored.
						// We don't want to restore a different version which might break the restore processing. D'OH!
						$snapshot_plugin_dir = $restoreFilesPath. "/snapshot";
						snapshot_utility_recursive_rmdir($snapshot_plugin_dir);

						$restoreFilesSet = snapshot_utility_scandir($restoreFilesPath);
					}

					break;

				case 'media':
					$restoreFilesPath = $restoreFilesBase . $this->_session->data['MANIFEST']['WP_UPLOAD_PATH'];
					if (is_dir($restoreFilesPath)) {
						$restoreFilesSet = snapshot_utility_scandir($restoreFilesPath);
					}
					break;


				case 'config':
					$wp_config_file = $restoreFilesBase ."wp-config.php";
					if (file_exists($wp_config_file)) {
						$restoreFilesSet[] = $wp_config_file;
					}
					break;


				case 'htaccess':
					$wp_htaccess_file = $restoreFilesBase .".htaccess";
					if (file_exists($wp_htaccess_file)) {
						$restoreFilesSet[] = $wp_htaccess_file;
					}
					break;

				default:
					break;
			}

			if (count($restoreFilesSet)) {

				foreach($restoreFilesSet as $restoreFileFull) {

					$file_relative = str_replace( $restoreFilesBase, '', $restoreFileFull);

					if ($this->_session->data['MANIFEST']['FILES-DATA'][$file_data_idx] == "media") {

						if ((isset($this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR']))
						 && (!empty($this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR']))
						 && (isset($this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']))
						 && (!empty($this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']))) {
							$file_relative = str_replace( $this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'],
							 					$this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR'],
												$file_relative);
						}
					}

					$destinationFileFull = trailingslashit($home_path) . $file_relative;

					if (file_exists($destinationFileFull)) {
						unlink($destinationFileFull);
						rename($restoreFileFull, $destinationFileFull);
					} else {
						$currentFileDir = dirname($destinationFileFull);
						if (!is_dir($currentFileDir)) {
							if (wp_mkdir_p($currentFileDir) === false) {
								$error_status['errorStatus'] 	= true;
								$error_status['errorText'] 		=
								"<p>". sprintf( __( 'Unable to create directory %s. Make sure the parent folder is writeable.',
									SNAPSHOT_I18N_DOMAIN ), $currentFileDir) ."</p>";

								return $error_status;
							}
						}
						rename($restoreFileFull, $destinationFileFull);
					}
				}
			}
			$error_status['file_data'] = $this->_session->data['MANIFEST']['FILES-DATA'][$file_data_idx];

			return $error_status;
		}

		/**
		 * AJAX callback function from the snapshot restore form. This is the third
		 * step of the restore. This step will performs the cleanup of the unzipped
		 * archive and writes an entry to the activity log about the restore.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param none
		 * @return JSON formatted array status.
		 */

		function snapshot_ajax_restore_finish($item) {
			$this->snapshot_ajax_restore_rename_restored_tables($item);

			if (is_multisite())
				$this->snapshot_ajax_restore_convert_db_global_tables($item);

			if (isset($_POST['snapshot_restore_theme']))
			{
				$snapshot_restore_theme = esc_attr($_REQUEST['snapshot_restore_theme']);
				if ($snapshot_restore_theme)
				{
					$themes = snapshot_utility_get_blog_active_themes($item['blog-id']);
					if (($themes) && (isset($themes[$snapshot_restore_theme]))) {

						if (is_multisite()) {

							delete_blog_option($item['blog-id'], 'current_theme');
							add_blog_option($item['blog-id'], 'current_theme', $themes[$snapshot_restore_theme]);

						} else {

							delete_option('current_theme');
							add_option('current_theme', $themes[$snapshot_restore_theme]);
						}
					}
				}
			}

			if ((isset($_REQUEST['snapshot_restore_plugin'])) && (esc_attr($_REQUEST['snapshot_restore_plugin']) == "yes"))
			{
				$_plugin_file = basename(dirname(__FILE__)) ."/". basename(__FILE__);
				$_plugins = array($_plugin_file);
				if (is_multisite()) {

					delete_blog_option($item['blog-id'], 'active_plugins');
					add_blog_option($item['blog-id'], 'active_plugins', $_plugins);

				} else {

					delete_option('active_plugins');
					add_option('active_plugins', $_plugins);
				}
			}

			// Cleanup any files from restore in case any files were left
			if ($dh = opendir($this->_session->data['restoreFolder'])) {
				while (($file = readdir($dh)) !== false) {
					if (($file == '.') || ($file == '..'))
						continue;

					snapshot_utility_recursive_rmdir($this->_session->data['restoreFolder'] . $file);
				}
				closedir($dh);
			}
			flush_rewrite_rules();

			$error_status = array();
			$error_status['errorStatus'] 	= false;
			$error_status['errorText'] 		= "";
			$error_status['responseText'] 	= "<p>". __("SUCCESS: Snapshot Restore complete! ", SNAPSHOT_I18N_DOMAIN) ."</p>";

			return $error_status;
		}


		function snapshot_ajax_restore_rename_restored_tables($item) {
			global $wpdb;

			$tables = array();
			$tables_sections = snapshot_utility_get_database_tables($this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID']);
			$blog_prefix = $wpdb->get_blog_prefix( $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] );

			if ($tables_sections) {
				foreach($tables_sections as $_section => $_tables) {
					if ($_section != "global") {
						$tables = array_merge($_tables, $tables);
					} else {
						// The 'global' tables will generally be set so the table name uses the base prefix. For restore we want to use
						// the blog prefix. Since we will need to process the rows and not replace the original file.
						if (!empty($_tables)) {
							foreach($_tables as $_table) {
								$table_dest = str_replace($wpdb->base_prefix, $blog_prefix, $_table);
								if ($table_dest != $_table) {
									if (isset($this->_session->data['MANIFEST']['TABLES'][$_table]['table_name_dest'])) {
										$this->_session->data['MANIFEST']['TABLES'][$_table]['table_name_dest'] = $table_dest;
									}
								}
							}
						}
					}
				}
			}

			foreach($this->_session->data['MANIFEST']['TABLES'] as $table_set) {

				if (isset($tables[$table_set['table_name_restore']]))
					unset($tables[$table_set['table_name_restore']]);

				if (isset($tables[$table_set['table_name_dest']])) {
					$sql_str = "DROP TABLE `". $table_set['table_name_dest'] ."`;";
					$this->snapshot_logger->log_message('drop original table: '. $sql_str);
					$wpdb->query($sql_str);
				}

				$sql_str = "ALTER TABLE `". $table_set['table_name_restore'] ."` RENAME `". $table_set['table_name_dest'] ."`;";
				$this->snapshot_logger->log_message('rename restored table: '. $sql_str);
				$wpdb->query($sql_str);
			}
		}


		function snapshot_ajax_restore_convert_db_content($table_data) {
			global $wpdb;

			//echo "table_data<pre>"; print_r($table_data); echo "</pre>";
			if (!defined('SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE'))
				define('SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE', 500);

			if (empty($table_data))	return;

			if (!isset($table_data['table_name'])) return;
			$table_name = $table_data['table_name'];

			if (isset($this->_session->data['MANIFEST']['TABLES'][$table_name])) {
				$table_set = $this->_session->data['MANIFEST']['TABLES'][$table_name];
			} else {
				return;
			}

			if (!isset( $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_SITEURL'] )) return;
			if ( $this->_session->data['MANIFEST']['WP_HOME'] == $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_SITEURL']
			   && $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] == $this->_session->data['MANIFEST']['RESTORE']['SOURCE']['WP_BLOG_ID'] ) {
				return;
			}

			$blog_prefix = $wpdb->get_blog_prefix( $_POST['snapshot-blog-id'] );
			$_old_siteurl = str_replace('http://', '://', $this->_session->data['MANIFEST']['WP_HOME']);
			$_old_siteurl = str_replace('https://', '://', $_old_siteurl);

			$_new_siteurl = str_replace('http://', '://', $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_SITEURL']);
			$_new_siteurl = str_replace('https://', '://', $_new_siteurl);

			//echo "_old_siteurl[". $_old_siteurl ."]<br />";
			//echo "_new_siteurl[". $_new_siteurl ."]<br />";
			//echo "MANIFEST<pre>"; print_r($this->_session->data['MANIFEST']); echo "</pre>";
			//die();

			$replacement_strs = array();

			// First we add the fill image path '://www.somesite.com/wp-content/uploads/sites/2/2012/10/image.gif
			$old_str = trailingslashit($_old_siteurl) . $this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'];
			$new_str = trailingslashit($_new_siteurl) . $this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR'];
			//$old_str = $this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'];
			//$new_str = $this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR'];
			$replacement_strs[$old_str] = $new_str;


		 	// If here we may have URLs in posts which are http://www.somesite.com/files/2012/10/image.gif instead of
		 	// http://www.somesite.com/wp-content/uploads/sites/2/2012/10/image.gif
			if (( !defined( 'BLOGUPLOADDIR' ))
			 && (isset($this->_session->data['MANIFEST']['WP_UPLOADBLOGSDIR'])) && (!empty($this->_session->data['MANIFEST']['WP_UPLOADBLOGSDIR']))) {
				 $old_str = trailingslashit($_old_siteurl) . 'files';
				 $new_str = trailingslashit($_new_siteurl) . $this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR'];
				 $replacement_strs[$old_str] = $new_str;
			}

			// Now add our base old/new domains as a final check.
			// $replacement_strs[$_old_siteurl] = $_new_siteurl;
			//echo "replacement_strs<pre>"; print_r($replacement_strs); echo "</pre>";
			//error_log(__FUNCTION__ .": replacement_strs<pre>". print_r($replacement_strs, true) ."</pre>");

			$this->snapshot_logger->log_message('restore: table: '. $table_data['table_name'] .' converting URLs from ['. $_old_siteurl .'] -> ['. $_new_siteurl .']');

			switch($table_set['table_name_base']) {

				case 'options':
					// Options table
					$limit_start = 0;
					$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {
						$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` LIMIT %d,%d", $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						//error_log(__FUNCTION__ .": sql[". $sql_str ."]");

						$db_rows = $wpdb->get_results($sql_str);
						if (!empty($db_rows)) {

							foreach($db_rows as $row) {
								//echo "row<pre>"; print_r($row); echo "</pre>";
								$new_value = $row->option_value;
								foreach($replacement_strs as $_old_str => $_new_str) {
									$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
								}
								if ($new_value != $row->option_value) {
									$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_value=%s WHERE option_id=%d",
										$new_value, $row->option_id);
									//error_log(__FUNCTION__ .": sql[". $sql_str ."]");
									$wpdb->query($sql_str);
								}
							}

							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}

					// Options - user_roles
					$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE option_name=%s LIMIT 1",
						$this->_session->data['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX']."user_roles");
					//echo "sql_str=[". $sql_str ."]<br />";
					//error_log(__FUNCTION__ .": sql[". $sql_str ."]");

					$db_row = $wpdb->get_row($sql_str);
					//echo "db_row<pre>"; print_r($db_row); echo "</pre>";
					//error_log(__FUNCTION__ .": db_row<pre>". print_r($db_row, true). "</pre>");
					if (!empty($db_row)) {
						$new_value = $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX']."user_roles";

						$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_name=%s WHERE option_id=%d",
							$new_value, $db_row->option_id);
						//echo "sql_str=[". $sql_str ."]<br />";
						//error_log(__FUNCTION__ .": sql[". $sql_str ."]");
						$wpdb->query($sql_str);
					}

					// Options - upload_path
					$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE option_name=%s LIMIT 1", 'upload_path');
					//echo "sql_str=[". $sql_str ."]<br />";
					$db_row = $wpdb->get_row($sql_str);
					//echo "db_row<pre>"; print_r($db_row); echo "</pre>";
					if (!empty($db_row)) {
						$new_value = snapshot_utility_replace_value ( $db_row->option_value,
										$this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'],
										$this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']);

						if ($new_value != $db_row->option_value) {
							$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_value=%s WHERE option_id=%d",
								$new_value, $db_row->option_id);

							$wpdb->query($sql_str);
						}
					}

					// Options - upload_url_path
					$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE option_name=%s LIMIT 1", 'upload_url_path');
					//echo "sql_str=[". $sql_str ."]<br />";
					$db_row = $wpdb->get_row($sql_str);
					//echo "db_row<pre>"; print_r($db_row); echo "</pre>";
					if (!empty($db_row)) {
						$new_value = snapshot_utility_replace_value ( $db_row->option_value,
										$this->_session->data['MANIFEST']['RESTORE']['SOURCE']['UPLOAD_DIR'],
										$this->_session->data['MANIFEST']['RESTORE']['DEST']['UPLOAD_DIR']);

						if ($new_value != $db_row->option_value) {
							$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_value=%s WHERE option_id=%d",
								$new_value, $db_row->option_id);

							$wpdb->query($sql_str);
						}
					}

					// Options - siteurl
					$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE option_name=%s LIMIT 1", 'siteurl');
					//echo "sql_str=[". $sql_str ."]<br />";
					$db_row = $wpdb->get_row($sql_str);
					//echo "db_row<pre>"; print_r($db_row); echo "</pre>";
					if (!empty($db_row)) {
						$new_value = snapshot_utility_replace_value ( $db_row->option_value,
										$this->_session->data['MANIFEST']['RESTORE']['SOURCE']['WP_SITEURL'],
										$this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_SITEURL']);

						if ($new_value != $db_row->option_value) {
							$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_value=%s WHERE option_id=%d",
								$new_value, $db_row->option_id);
							$wpdb->query($sql_str);
						}
					}

					// Options - home
					$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE option_name=%s LIMIT 1", 'home');
					//echo "sql_str=[". $sql_str ."]<br />";
					$db_row = $wpdb->get_row($sql_str);
					//echo "db_row<pre>"; print_r($db_row); echo "</pre>";
					if (!empty($db_row)) {

						if( is_subdomain_install() ) {
							$home_url = untrailingslashit($this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_SITEURL']);
						} else {
							switch_to_blog( $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_BLOG_ID'] );
							$home_url = home_url();
							restore_current_blog();
						}

						$new_value = snapshot_utility_replace_value ( $db_row->option_value,
										$this->_session->data['MANIFEST']['WP_HOME'],
										$home_url );

						if ($new_value != $db_row->option_value) {
							$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET option_value=%s WHERE option_id=%d",
								$new_value, $db_row->option_id);
							$wpdb->query($sql_str);
						}
					}

					break;

				case 'posts':
					// Posts table
					$limit_start = 0;
					$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {
						$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` LIMIT %d,%d", $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						$db_rows = $wpdb->get_results($sql_str);
						if (!empty($db_rows)) {

							//echo "dp_rows<pre>"; print_r($db_rows); echo "</pre>";
							foreach($db_rows as $row) {

								// Update post_title
								if (!empty($row->post_title)) {
									$new_value = $row->post_title;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->post_title) {
										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET post_title=%s WHERE ID=%d",
											$new_value, $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}

								// Update post_content
								if (!empty($row->post_content)) {
									$new_value = $row->post_content;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->post_content) {
										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET post_content=%s WHERE ID=%d",
											$new_value, $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}

								// Update post_content_filtered
								if (!empty($row->post_content_filtered)) {
									$new_value = $row->post_content_filtered;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->post_content_filtered) {
										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET post_content_filtered=%s WHERE ID=%d", $new_value,  $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}

								// Update post_excerpt
								if (!empty($row->post_excerpt)) {
									$new_value = $row->post_excerpt;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->post_excerpt) {

										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET post_excerpt=%s WHERE ID=%d",
											$new_value, $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}

								// Update guid
								if (!empty($row->guid)) {
									$new_value = $row->guid;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->guid) {

										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET guid=%s WHERE ID=%d",
											$new_value, $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}
								// Update pinged
								if (!empty($row->pinged)) {
									$new_value = $row->pinged;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->guid) {

										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET pinged=%s WHERE ID=%d",
											$new_value, $row->ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}
							}

							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}
					break;

				case 'postmeta':
					// Posts Meta table
					$limit_start = 0;
					$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {

						$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` LIMIT %d,%d", $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						$db_rows = $wpdb->get_results($sql_str);
						if (!empty($db_rows)) {
							//echo "dp_rows<pre>"; print_r($db_rows); echo "</pre>";
							foreach($db_rows as $row) {
								$new_value = $row->meta_value;
								foreach($replacement_strs as $_old_str => $_new_str) {
									$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
								}
								if ($new_value != $row->meta_value) {
									//echo "postmeta [". $row->meta_name ."] [". $row->meta_value ."] [". $new_value ."]<br />";

									$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET meta_value=%s WHERE meta_id=%d",
										$new_value, $row->meta_id);
									//echo "sql_str=[". $sql_str ."]<br />";
									$wpdb->query($sql_str);
								}
							}

							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}
					break;

				case 'comments':
					// Comments table
					$limit_start = 0;
					$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {

						$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` LIMIT %d,%d", $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						$db_rows = $wpdb->get_results($sql_str);
						//echo "dp_rows<pre>"; print_r($db_rows); echo "</pre>";
						if (!empty($db_rows)) {
							foreach($db_rows as $row) {

								// Update comment_content
								if (!empty($row->comment_content)) {
									$new_value = $row->comment_content;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->comment_content) {
										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET comment_content=%s WHERE comment_ID=%d",
											$new_value, $row->comment_ID);
										$wpdb->query($sql_str);
									}
								}

								// Update comment_author_url
								if (!empty($row->comment_author_url)) {
									$new_value = $row->comment_author_url;
									foreach($replacement_strs as $_old_str => $_new_str) {
										$new_value = snapshot_utility_replace_value ( $new_value, $_old_str, $_new_str );
									}
									if ($new_value != $row->comment_author_url) {
										$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET comment_author_url=%s WHERE comment_ID=%d",
											$new_value, $row->comment_ID);
										//echo "sql_str=[". $sql_str ."]<br />";
										$wpdb->query($sql_str);
									}
								}
							}

							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}
					break;

				case 'commentmeta':
					// Comment Meta table
					$limit_start 	= 0;
					$limit_end		= $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {
						$sql_str = $wpdb->prepare("SELECT * FROM `". $table_set['table_name_restore'] ."` LIMIT %d,%d", $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						$db_rows = $wpdb->get_results($sql_str);
						if (!empty($db_rows)) {
							//echo "dp_rows<pre>"; print_r($db_rows); echo "</pre>";
							foreach($db_rows as $row) {
								$new_value = $row->meta_value;
								foreach($replacement_strs as $_old_str => $_new_str) {
									$new_value = snapshot_utility_replace_value( $new_value, $_old_str, $_new_str );
								}
								if ($new_value != $row->meta_value) {
									$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET meta_value=%s WHERE meta_id=%d",
										$new_value, $row->meta_id);
									//echo "sql_str=[". $sql_str ."]<br />";
									$wpdb->query($sql_str);
								}
							}
							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}
					break;

				case 'usermeta':
					$limit_start 	= 0;
					$limit_end		= $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;
					while(true) {
						$sql_str = sprintf("SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE meta_key like '".
					 		$this->_session->data['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'] ."%s' LIMIT %d,%d", '%', $limit_start, $limit_end);
						//echo "sql_str=[". $sql_str ."]<br />";
						//error_log(__FUNCTION__ .": sql[". $sql_str ."]");

						//$this->snapshot_logger->log_message('restore: table: '. $table_data['table_name'] .' sql_str ['. $sql_str .']');

						$db_rows = $wpdb->get_results($sql_str);
						if (!empty($db_rows)) {
							//echo "dp_rows<pre>"; print_r($db_rows); echo "</pre>";
							//die();

							foreach($db_rows as $row) {
								$new_value = str_replace( $this->_session->data['MANIFEST']['RESTORE']['SOURCE']['WP_DB_PREFIX'],
								 $this->_session->data['MANIFEST']['RESTORE']['DEST']['WP_DB_PREFIX'], $row->meta_key );

								$sql_str = $wpdb->prepare("UPDATE `". $table_set['table_name_restore'] ."` SET meta_key=%s WHERE umeta_id=%d",
									$new_value, $row->umeta_id);
								//$this->snapshot_logger->log_message('restore: table: '. $table_data['table_name'] .' sql_str ['. $sql_str .']');
								//echo "sql_str=[". $sql_str ."]<br />";
								//error_log(__FUNCTION__ .": sql[". $sql_str ."]");
								$wpdb->query($sql_str);
							}

							$limit_start = $limit_end;
							$limit_end = $limit_start+SNAPSHOT_RESTORE_MIGRATION_LIMIT_SIZE;

						} else {
							break;
						}
					}
					break;

				case 'users':
					if (!is_multisite()) {

						// For non-Multisite we want to drop the extra columns from the users table. But we don't
						// know if the archive was from a regular or Miltisite.
						$sql_str = "SELECT * FROM `". $table_set['table_name_restore'] ."` WHERE 1=1 LIMIT 1";
						$db_rows = $wpdb->get_row($sql_str);
						if ($db_rows) {
							$alter_tables = array();
							if (isset($db_rows->spam)) {
							  $alter_tables[] = "DROP `spam`";
							}
							if (isset($db_rows->deleted)) {
							  $alter_tables[] = "DROP `deleted`";
							}
							if (count($alter_tables)) {
								$sql_str_alter = "ALTER TABLE `". $table_set['table_name_restore'] ."` " .implode(',', $alter_tables);
								$wpdb->query($sql_str_alter);
							}
						}
					}
					break;

				default:
					break;
			}
		}

		function snapshot_ajax_restore_convert_db_global_tables($item) {
			global $wpdb, $current_blog, $current_user;

			if ((is_multisite()) && ($current_blog->blog_id != $_POST['snapshot-blog-id'])) {
				$wpdb->set_blog_id( $_POST['snapshot-blog-id'] );
			}

			//echo "MANIFEST<pre>"; print_r($this->_session->data['MANIFEST']); echo "</pre>";
			//echo "RESTORE<pre>"; print_r($this->_session->data['MANIFEST']['RESTORE']); echo "</pre>";
			//die();

			$table_prefix_org 	= $this->_session->data['MANIFEST']['WP_DB_PREFIX'];
			//echo "table_prefix_org[". $table_prefix_org ."]<br />";

			$blog_prefix 		= $wpdb->get_blog_prefix( $_POST['snapshot-blog-id'] );
			//echo "blog_prefix[". $blog_prefix ."]<br />";

			$tables = array();
			$table_results = $wpdb->get_results( 'SHOW TABLES' );
			foreach( $table_results as $table){
				$obj = 'Tables_in_' . DOMAIN_CURRENT_SITE;
				$tables[] = $table->$obj;
			}

			$users_restore = false;
			$users_table = $blog_prefix . 'users';

			// Avoid PHP Notice when prefix_[ID]_users don't exist.
			if( in_array( $users_table, $tables ) ) {
				$sql_str = "SELECT * FROM ". $users_table;
				$users_restore = $wpdb->get_results($sql_str);
			}

			if ($users_restore) {
				//echo "users_restore<pre>"; print_r($users_restore); echo "</pre>";
				foreach($users_restore as $user_restore) {

					// We purposely skip the user running the restore. This is for security plus we don't want to accedentially create a password change!
					if ($user_restore->user_login === $current_user->user_login)
						continue;

					//echo "user_restore<pre>"; print_r($user_restore); echo "</pre>";
					//echo "user_restore [". $user_restore->ID ."] [". $user_restore->user_login ."] [". $user_restore->user_email ."]<br />";

					$user_local_id  = username_exists( $user_restore->user_login );
					//echo "user_local_id=[". $user_local_id ."]<br />";
					if ($user_local_id) {
						$user_local = get_userdata($user_local_id);
						//echo "user_local<pre>"; print_r($user_local); echo "</pre>";
					}

					if (!isset($user_local)) {
						//echo "HERE: Need to create new user<br />";
						//die();
						if (is_multisite()) {
							if (!isset($user_restore->spam))
								$user_restore->spam = 0;
							if (!isset($user_restore->deleted))
								$user_restore->deleted = 0;
						}
						$sql_insert_user = "INSERT INTO $wpdb->users VALUES (0, '$user_restore->user_login', '$user_restore->user_pass', '$user_restore->user_nicename', '$user_restore->user_email', '$user_restore->user_url', '$user_restore->user_registered', '$user_restore->user_activation_key',  '$user_restore->user_status', '$user_restore->display_name', $user_restore->spam, $user_restore->deleted)";
						//echo "sql_insert_user[". $sql_insert_user ."]<br />";
						$wpdb->get_results($sql_insert_user);
						if (!$wpdb->insert_id) {
							echo "ERROR: Failed to insert user record for User ID[". $user_restore->ID ."] WP Error[". $wpdb->last_error ."]<br />";
						} else {
							$user_restore_new_id = $wpdb->insert_id;
							$sql_usermeta_str = "SELECT * FROM ". $blog_prefix ."usermeta WHERE user_id=". $user_restore->ID;
							//echo "sql_usermeta_str=[". $sql_usermeta_str ."]<br />";
							$usermeta_restore = $wpdb->get_results($sql_usermeta_str);

							if (($usermeta_restore) && (count($usermeta_restore))) {
								//$meta_sql_str = '';
								foreach($usermeta_restore as $meta) {
									$meta_sql_str = "INSERT into $wpdb->usermeta VALUES(0, '$user_restore_new_id', '$meta->meta_key', '$meta->meta_value')";
									//echo "meta_sql_str=[". $meta_sql_str ."]<br />";
									$ret = $wpdb->query($meta_sql_str);
									//echo "ret[". $ret ."] wpdb<pre>"; print_r($wpdb); echo "</pre>";
								}
								update_user_meta($user_restore_new_id, $blog_prefix.'old_user_id', $user_restore->ID);

								if (is_multisite())
									add_user_meta($user_restore_new_id, 'primary_blog', $_POST['snapshot-blog-id']);
							}

							// Update the Posts post_author field
							$sql_posts_str = $wpdb->prepare("UPDATE $wpdb->posts SET post_author = %d WHERE post_author = %d", $user_restore_new_id, $user_restore->ID);
							$wpdb->query($sql_posts_str);

							// Update the Comments user_id field
							$sql_comments_str = $wpdb->prepare("UPDATE $wpdb->comments SET user_id = %d WHERE user_id = %d", $user_restore_new_id, $user_restore->ID);
							$wpdb->query($sql_comments_str);
						}
						continue;

					} else {
						//echo "HERE User exists!<br />";
						//die();

						// If the user an exact match?
						if (($user_local->ID == $user_restore->ID) && ($user_local->user_login == $user_restore->user_login)) {

							// Now we have the user, we need to add the user meta. We only add meta keys which do not already exist.
							$sql_usermeta_str = "SELECT * FROM ". $blog_prefix ."usermeta WHERE user_id=". $user_local->ID;
							//echo "sql_usermeta_str=[". $sql_usermeta_str ."]<br />";
							$usermeta_restore = $wpdb->get_results($sql_usermeta_str);
							if (($usermeta_restore) && (count($usermeta_restore))) {
								foreach($usermeta_restore as $meta) {
									if (!get_user_meta($user_local->ID, $meta->meta_key)) {
										$meta_sql_str = "INSERT into $wpdb->usermeta VALUES(0, '$user_restore->ID', '$meta->meta_key', '$meta->meta_value');";
										//echo "meta_sql_str=[". $meta_sql_str ."]<br />";
										$wpdb->query($meta_sql_str);
									}
								}
								if (is_multisite()) {
									if (!get_user_meta($user_local->ID, 'primary_blog')) {
										add_user_meta($user_local->ID, 'primary_blog', $_POST['snapshot-blog-id']);
									}
								}
							}
							continue;

						} else {
							//echo "HERE: Need to copy restored user, usermeta, post, comments<br />";

							// IF here we need to copy the usermeta records to the new local_user ID. Copy the usermeta records over to the main table
							$sql_usermeta_str = "SELECT * FROM ". $blog_prefix ."usermeta WHERE user_id=". $user_restore->ID;
							$usermeta_restore = $wpdb->get_results($sql_usermeta_str);
							if (($usermeta_restore) && (count($usermeta_restore))) {
								//echo "usermeta_restore<pre>"; print_r($usermeta_restore); echo "</pre>";
								foreach($usermeta_restore as $meta) {
									if (!get_user_meta($user_local->ID, $meta->meta_key)) {
										$meta_sql_str = "INSERT into $wpdb->usermeta VALUES(0, '$user_local->ID', '$meta->meta_key', '$meta->meta_value'); ";
										//echo "meta_sql_str=[". $meta_sql_str ."]<br />";
										$wpdb->query($meta_sql_str);
									}
								}
								update_user_meta($user_local->ID, $blog_prefix.'old_user_id', $user_restore->ID);

								if (is_multisite()) {
									if (!get_user_meta($user_local->ID, 'primary_blog')) {
										add_user_meta($user_local->ID, 'primary_blog', $_POST['snapshot-blog-id']);
									}
								}
							}

							// Update the Posts post_author field
							$sql_posts_str = $wpdb->prepare("UPDATE $wpdb->posts SET post_author = %d WHERE post_author = %d", $user_local->ID, $user_restore->ID);
							$wpdb->query($sql_posts_str);

							// Update the Comments user_id field
							$sql_comments_str = $wpdb->prepare("UPDATE $wpdb->comments SET user_id = %d WHERE user_id = %d", $user_local->ID, $user_restore->ID);
							$wpdb->query($sql_comments_str);

						}
					}
				}
			}
			//echo "FIN<br />";
			//die();
			return;

			// We are done with the temp users and usermeta table. Remove them for good cleanup.
			if (is_multisite()) {
				$sql_str = "DROP TABLE ". $blog_prefix ."users, ". $blog_prefix ."usermeta;";
				$wpdb->query($sql_str);
			}
		}

		/**
		 * Uninstall/Delete plugin action. Called from uninstall.php file. This function removes file and options setup by plugin.
		 *
		 * @since 1.0.0
		 * @see
		 *
		 * @param int UNIX timestamp from time()
		 * @return none
		 */

		function uninstall_snapshot() {

			$this->load_config();
			$this->set_backup_folder();

			if ((isset($this->_settings['backupBaseFolderFull'])) && (strlen($this->_settings['backupBaseFolderFull'])))
				snapshot_utility_recursive_rmdir($this->_settings['backupBaseFolderFull']);

			delete_option($this->_settings['options_key']);
		}

		/**
		 * Utility function to migrate previous snapshot items to new data structure and filename format
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param blog_id
		 * @param IS_SUPER_BLOG true is for multisite primary blog ID 1
		 * @return none
		 */

		function snapshot_migrate_config_proc($blog_id=0) {

	//		if (is_multisite()) {
	//			if (!$blog_id) return;
	//
	//			$blog_id = intval($blog_id);
	//			if ($IS_SUPER_BLOG == true) {
	//
	//				// If this is the Primary Blog and we already have the updated
	//				$config_data = get_blog_option($blog_id, $this->_settings['options_key']);
	//				if ($config_data)
	//					return;
	//			}
	//
	//		} else {
	//
	//			// Single WordPress (Not Multisite)
	//			$config_data = get_option($this->_settings['options_key']);
	//			if ($config_data)
	//				return;
	//		}
	//
	//		// else we need to pull the previous version and convert.
	//		if (is_multisite())
	//			$config_data = get_blog_option($blog_id, 'snapshot_1.0');
	//		else
	//			$config_data = get_option( 'snapshot_1.0');

			$ret_value = false;
			$config_data = get_option( 'snapshot_1.0' );
			if ($config_data) {

				foreach($config_data['items'] as $item_key => $item) {

					// Need to update filename to reflect new filename formats
					$backupFile = trailingslashit($this->_settings['backupBaseFolderFull']) . $item['file'];

					if (file_exists($backupFile)) {
						$path_filename = pathinfo($item['file'], PATHINFO_FILENAME);
						if (!$path_filename) { // PHP Not supported. Do it old school

							$path_filename = substr($item['file'], 0, strlen($item['file'])-4);
						}

						$snapshot_file_parts = explode('-', $path_filename);
						if (count($snapshot_file_parts) == 3) {
							// Old style snapshot-yymmdd-hhmms format
							// Need to convert to snapshot-{blog_id}-yymmdd-hhmmss-{checksum}.zip

							while(true) {
								$checksum = snapshot_utility_get_file_checksum($backupFile);
								$snapshot_new_filename = $snapshot_file_parts[0] .'-'. $item_key .'-'. $snapshot_file_parts[1] .'-'.
									$snapshot_file_parts[2] .'-'. $checksum .'.zip';

								$masterFile = trailingslashit($this->_settings['backupBaseFolderFull']) . $snapshot_new_filename;

								// File does not exist so break and save file.
								if (!file_exists($masterFile))
									break;
							}
							rename($backupFile, $masterFile);

							$item['blog-id'] = $blog_id;
							$item['interval'] = '';

							unset($item['file']);

							$item_data = array();
							$item_data['filename'] 		= $snapshot_new_filename;
							$item_data['timestamp'] 	= $item_key;
							$item_data['tables'] 		= $item['tables'];

							$item['data'] = array();
							$item['data'][$item_key] 	= $item_data;

							$this->config_data['items'][$item_key]	= $item;
						}
					}
				}
				//echo "config_data<pre>"; print_r($this->config_data); echo "</pre>";
				//exit;

				if (isset($config_data['config']['tables']))
					$this->config_data['config']['tables_last'][$blog_id] = $config_data['config']['tables'];

				// Now we want to archive the old style options to get them out of the database.
				$configFile = trailingslashit($this->_settings['backupBaseFolderFull']) . "_configs";
				wp_mkdir_p($configFile);
				$configFile = trailingslashit($this->_settings['backupBaseFolderFull']) . "_configs/blog_". $blog_id .".conf";
				$fp = fopen($configFile, 'w');
				fwrite($fp, serialize($config_data));
				fclose($fp);
				$ret_value = true;
				delete_option('snapshot_1.0');
			}
			return $ret_value;
		}


		/**
		 * Interface function provide access to the private _settings array to outside classes.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param string $settings key to $this->_settings array item
		 * @return string value of setting
		 */

		function snapshot_get_setting($setting) {
			//echo "_settings<pre>"; print_r($this->_settings); echo "</pre>";
			if (isset($this->_settings[$setting]))
				return $this->_settings[$setting];
		}

		/**
		 * Interface function provide access to the private _settings array to outside classes.
		 *
		 * @since 1.0.7
		 * @see
		 *
		 * @param string $settings key to $this->_settings array item
		 * @return string value of setting
		 */
		function snapshot_update_setting($setting, $_value) {
			//echo "_settings<pre>"; print_r($this->_settings); echo "</pre>";
			if (isset($this->_settings[$setting])) {
				$this->_settings[$setting] = $_value;
				return true;
			}
		}

		/**
		 * Interface function provide access to the private _pagehooks array to outside classes.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param string $settings key to $this->_pagehooks array item
		 * @return string value of pagehook
		 */

		function snapshot_get_pagehook($setting) {
			if (isset($this->_pagehooks[$setting]))
				return $this->_pagehooks[$setting];
		}

		function snapshot_add_destination_proc() {

			$CONFIG_CHANGED = false;

			if (isset($_POST['snapshot-destination'])) {

				$form_destination_info 	= $_POST['snapshot-destination'];

				if (!isset($form_destination_info['type'])) {
				 	return;
				}
				$destination_type = $form_destination_info['type'];

				// IF the 'type' is not found in the list of loaded destinationClasses then abort.
				if (!isset($this->_settings['destinationClasses'][$destination_type])) {
				 	return;
				}

				$location_redirect_url = '';
				$destination_type_object = $this->_settings['destinationClasses'][$destination_type];
				$destination_info = $destination_type_object->validate_form_data($form_destination_info);
				//echo "destination_info<pre>"; print_r($destination_info); echo "</pre>";
				//echo "form_errors<pre>"; print_r($destination_type_object->form_errors); echo "</pre>";
				//die();

				if (($destination_info !== false) && (is_array($destination_info))) {

					if ( (isset($form_destination_info['name'])) && (strlen($form_destination_info['name'])) ) {

						if (!isset($this->config_data['destinations']))
							$this->config_data['destinations'] = array();

						$destination_slug_tmp = sanitize_title( $form_destination_info['name'] );
						$destination_slug = $destination_slug_tmp;
						$counter = 0;

						while(true) {

							// If we have more than 99 destinations we probably have bigger issues.
							if ($counter > 99)
							break;

							if (isset($this->config_data['destinations'][$destination_slug])) {
								$counter += 1;
								$destination_slug = $destination_slug_tmp ."-". $counter;
							} else {
								break;
							}
						}

						$this->config_data['destinations'][$destination_slug] = $form_destination_info;

						if ((isset($destination_info['form-step-url'])) && (!empty($destination_info['form-step-url']))) {
							$location_redirect_url = add_query_arg('item', $destination_slug, $destination_info['form-step-url']);
							$location_redirect_url = add_query_arg('message', 'success-add', $location_redirect_url);
							//$location_redirect_url = add_query_arg('snapshot-action', 'edit', $location_redirect_url);
						}

						//$this->save_config();

						$CONFIG_CHANGED = true;
					} else {
						echo "ERROR: Name required<br />";
						exit;
					}
				}
			}

			if ($CONFIG_CHANGED == true) {

				$this->save_config();

				if (empty($location_redirect_url)) {
					$location_redirect_url = $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel';
				}
				$location = add_query_arg('message', 'success-add', $location_redirect_url);
				//echo "location=[". $location ."]<br />";
				//die();
				if ($location) {
					wp_redirect($location);
				}
			}
		}


		function snapshot_update_destination_proc() {

			// For the form post we need both elements to continue;
			if ((!isset($_POST['snapshot-destination'])) || (!isset($_POST['item'])))
				return;

			$destination_key = $_POST['item'];

			// IF not a valid destination key the abort.
			if (!isset($this->config_data['destinations'][$destination_key])) {
			 	return;
			}

			$destination = $this->config_data['destinations'][$destination_key];

			$form_destination_info 	= $_POST['snapshot-destination'];

			// If the post form does not have the type item then abort
			if (!isset($form_destination_info['type'])) {
			 	return;
			}

			$destination_type = $form_destination_info['type'];

			// IF the 'type' is not found in the list of loaded destinationClasses then abort.
			if (!isset($this->_settings['destinationClasses'][$destination_type])) {
			 	return;
			}

			$location_redirect_url = '';
			$destination_type_object = $this->_settings['destinationClasses'][$destination_type];
			$destination_info = $destination_type_object->validate_form_data($form_destination_info);
			//echo "destination_info<pre>"; print_r($destination_info); echo "</pre>";
			//echo "form_errors<pre>"; print_r($destination_type_object->form_errors); echo "</pre>";
			//die();

			if (($destination_info !== false) && (is_array($destination_info))) {
				$this->config_data['destinations'][$destination_key] = $destination_info;
				//echo "destinations<pre>"; print_r($this->config_data['destinations']); echo "</pre>";
				//die();
				$this->save_config();

				//echo "destination_info<pre>"; print_r($destination_info); echo "</pre>";
				if ((isset($destination_info['form-step-url'])) && (!empty($destination_info['form-step-url']))) {
					$location_redirect_url = add_query_arg('item', $destination_key, $destination_info['form-step-url']);
					$location_redirect_url = add_query_arg('message', 'success-add', $location_redirect_url);
					//$location_redirect_url = add_query_arg('snapshot-action', 'edit', $location_redirect_url);
				}

				if (empty($location_redirect_url)) {
					$location_redirect_url = $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel';
				}
				$location = add_query_arg('message', 'success-add', $location_redirect_url);
				//echo "location[". $location ."]<br />";
				//die();

				if ($location) {
					wp_redirect($location);
				}
			}
		}

		/**
		 * Processing 'delete' action from form post to delete a select Snapshot destination.
		 *
		 * @since 1.0.0
		 * @uses $_REQUEST['delete']
		 * @uses $this->config_data['destinations']
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_delete_bulk_destination_proc() {

			if (!isset($_REQUEST['delete-bulk-destination'])) {
				wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel');
				die();
			}

			$CONFIG_CHANGED = false;
			foreach($_REQUEST['delete-bulk-destination'] as $key => $val) {
				if ($this->snapshot_delete_destination_proc($key, true))
					$CONFIG_CHANGED = true;
			}

			if ($CONFIG_CHANGED) {

				$this->save_config();

				$location = add_query_arg('message', 'success-delete', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel');
				if ($location) {
					wp_redirect($location);
					die();
				}
			}

			wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel');
			die();
		}


		function snapshot_delete_destination_proc($item_key=0, $DEFER_LOG_UPDATE=false) {

			$CONFIG_CHANGED = false;

			if (!$item_key) {
				if (isset($_GET['item'])) {
					$item_key = $_GET['item'];
				}
			}

			if (array_key_exists($item_key, $this->config_data['destinations'])) {

				unset($this->config_data['destinations'][$item_key]);
				$CONFIG_CHANGED = true;
			}

			if (!$DEFER_LOG_UPDATE) {
				if ($CONFIG_CHANGED) {
					$this->save_config();

					$location = add_query_arg('message', 'success-delete', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel');
					if ($location) {
						wp_redirect($location);
						die();
					}
				}

				wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_destinations_panel');
				die();

			} else {
				return $CONFIG_CHANGED;
			}
		}



		/**
		 * Utility function loop through existing Snapshot items and make sure they are
		 * setup in the WP Cron facility. Also, in case there are some left over cron
		 * entries a secondary process will loop through the WP Cron entries to make
		 * the entries related to Snapshot are valid and current.
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_scheduler() {

			$HAVE_SCHEDULED_EVENTS = false;
			// A two-step process.

			// 1. First any items needing to be schduled we make sure they are added.
			if ((isset($this->config_data['items'])) && (count($this->config_data['items']))) {

				$scheds = (array) wp_get_schedules();

				foreach($this->config_data['items'] as $key_slug => $item) {

					if ((isset($item['interval'])) && ($item['interval'] != "")) {

						if (isset($scheds[$item['interval']])) {

							$next_timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($key_slug)) );

							if (!$next_timestamp) {
								//$interval_offset = $scheds[$item['interval']]['interval'];
								//$offset_timestamp = time() - $interval_offset;
								//wp_schedule_event($offset_timestamp, $item['interval'], $this->_settings['backup_cron_hook'], array(intval($key_slug)) );
								wp_schedule_event(time() + snapshot_utility_calculate_interval_offset_time($item['interval'], $item['interval-offset']),
								 	$item['interval'], $this->_settings['backup_cron_hook'], array(intval($key_slug)) );
								$HAVE_SCHEDULED_EVENTS = true;
							}
						}
					}
				}
			}

			// 2. Go through the WP cron entries. Any snapshot items not matching to existing items or items without proper intervals unschedule.
			$crons = _get_cron_array();
			if ($crons) {
				foreach($crons as $cron_time => $cron_set) {
					foreach($cron_set as $cron_callback_function => $cron_item) {
						if ($cron_callback_function == "snapshot_backup_cron") {
							foreach($cron_item as $cron_key => $cron_details) {
								if (isset($cron_details['args'][0])) {
									$item_key = intval($cron_details['args'][0]);
									if (!isset($this->config_data['items'][$item_key])) {
										$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($item_key)) );
										if ($timestamp) {
											wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($item_key)) );
										} else {
											wp_unschedule_event($cron_time, $this->_settings['backup_cron_hook'], array(intval($item_key)) );
										}
									}
								}
							}
						} else if ($cron_callback_function == $this->_settings['remote_file_cron_hook']) {
							foreach($cron_item as $cron_key => $cron_details) {
								if ($cron_details['schedule'] !== $this->_settings['remote_file_cron_interval']) {
									$timestamp = wp_next_scheduled( $this->_settings['remote_file_cron_hook'] );
									wp_unschedule_event($timestamp, $this->_settings['remote_file_cron_hook'] );
								}
							}
						}

					}
				}
			}

			// We only need the remote file cron if we have destinations defined
			if ( (isset($this->config_data['destinations'])) && (count($this->config_data['destinations'])) ) {

				$timestamp = wp_next_scheduled( $this->_settings['remote_file_cron_hook'] );
				if (!$timestamp) {
					wp_schedule_event(time(), $this->_settings['remote_file_cron_interval'], $this->_settings['remote_file_cron_hook'] );
					$HAVE_SCHEDULED_EVENTS = true;

				}
			}

			if ($HAVE_SCHEDULED_EVENTS == true) {
				wp_remote_post(get_option('siteurl'). '/wp-cron.php',
					array(
						'timeout' 		=> 	3,
						'blocking' 		=> 	false,
						'sslverify' 	=> 	false,
						'body'			=>	array(
								'nonce' => wp_create_nonce('WPMUDEVSnapshot'),
								'type'			=>	'start'
							),
						'user-agent'	=>	'WPMUDEVSnapshot'
					)
				);
			}
		}

		/**
		 * Utility function called by WPCron scheduling dispatch. The parameter passed in is the
		 * config item key to an existing entry. If a match is found and verified it will be processed
		 *
		 * @since 1.0.2
		 * @see
		 *
		 * @param int $item_key - Match to an item in the $this->config_data['items'] array.
		 * @return none
		 */
		function snapshot_backup_cron_proc($item_key) {

			global $wpdb;

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			@set_time_limit( 0 );

			$old_error_handler = set_error_handler(array( $this, 'snapshot_ErrorHandler' ));

			if ((isset($this->config_data['config']['memoryLimit'])) && (!empty($this->config_data['config']['memoryLimit']))) {
				@ini_set('memory_limit', $this->config_data['config']['memoryLimit']);
			}

			$item_key = intval($item_key);

			// If we are somehow called for an item_key not in our list then remove any future cron calls then die
			if ((!defined('SNAPSHOT_DOING_CRON')) || (SNAPSHOT_DOING_CRON != true)) {
				if (!isset($this->config_data['items'][$item_key])) {
					$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($item_key)) );
					if ($timestamp) {
						wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($item_key)) );
					}
					die();
				}
			}

			$item = $this->config_data['items'][$item_key];

			$data_item_key = time();

			if (!isset($item['destination-sync']))
				$item['destination-sync'] = "archive";

			// If we are syncing/mirroring file and we don't have and database files. Then no need going through the
			// process of creating a new data_item entry.
			$_has_incomplete = false;
			if ($item['destination-sync'] == "mirror") {
				if ( (isset($item['data'])) && (count($item['data'])) ) {
					$data_item = snapshot_utility_latest_data_item($item['data']);
					//echo "data_item<pre>"; print_r($data_item); echo "</pre>";
					if ((isset($data_item['destination-status'])) && (count($data_item['destination-status']))) {
						$dest_item = snapshot_utility_latest_data_item($data_item['destination-status']);
						if ((!isset($dest_item['sendFileStatus'])) || ($dest_item['sendFileStatus'] !== true)) {
							$_has_incomplete = true;
						}
					}
				}
			}

			if (($item['destination-sync'] != "mirror")
			 || ($_has_incomplete == false)
			 || ($item['tables-option'] == "all") || ($item['tables-option'] == "selected") ) {

				if (!isset($this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status']))
					$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'] = array();

				if ((!defined('SNAPSHOT_DOING_CRON')) || (SNAPSHOT_DOING_CRON != true)) {

					// If we have a valid time_key but the item's interval is not a scheduled item then remove future cron and die
					//if ((isset($item['interval'])) && ($item['interval'] == "")) {
					//	$timestamp = wp_next_scheduled( $this->_settings['backup_cron_hook'], array(intval($item_key)) );
					//
					//	if ($timestamp) {
					//		wp_unschedule_event($timestamp, $this->_settings['backup_cron_hook'], array(intval($item_key)) );
					//	}
					//	die();
					//}
				}

				$snapshot_locker = new SnapshotLocker($this->_settings['backupLockFolderFull'], $item_key);

				// If we can't lock the locker then abort.
				if (!$snapshot_locker->is_locked()) return;

				$locket_info = array(
					'doing'				=>	__('Creating Archive', SNAPSHOT_I18N_DOMAIN),
					'item_key'			=>	$item_key,
					'data_item_key'		=>	$data_item_key,
					'time_start' 		=>	time()
				);
				$snapshot_locker->set_locker_info($locket_info);

				$this->snapshot_logger = new SnapshotLogger($this->_settings['backupLogFolderFull'], $item_key, $data_item_key);

				snapshot_utility_set_error_reporting($this->config_data['config']['errorReporting']);

				/* Needed to create the archvie zip file */
				if ($this->config_data['config']['zipLibrary'] == "PclZip") {
					if (!defined('PCLZIP_TEMPORARY_DIR'))
						define('PCLZIP_TEMPORARY_DIR', trailingslashit($this->_settings['backupBackupFolderFull']) . $item_key."/");

					//$this->snapshot_logger->log_message("init: Using PclZip PCLZIP_TEMPORARY_DIR". PCLZIP_TEMPORARY_DIR);

					if (!class_exists('class PclZip'))
						require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');
				}

				$this->snapshot_logger->log_message('init');

				$_post_array['snapshot-proc-action'] 		= "init";
				$_post_array['snapshot-action'] 			= "cron";
				$_post_array['snapshot-blog-id']			= $item['blog-id'];
				$_post_array['snapshot-item']				= $item_key;
				$_post_array['snapshot-data-item']			= $data_item_key;
				$_post_array['snapshot-interval']			= $item['interval'];
				$_post_array['snapshot-tables-option'] 		= $item['tables-option'];
				$_post_array['snapshot-destination-sync'] 	= $item['destination-sync'];

				$_post_array['snapshot-tables-array'] 		= array();
				if ($_post_array['snapshot-tables-option'] == "none") {
					// Nothing to process here.

				} else if ($_post_array['snapshot-tables-option'] == "all") {

					$tables_sections = snapshot_utility_get_database_tables($item['blog-id']);
					//$this->_session->data['tables_sections'] = $tables_sections;
					if ($tables_sections) {
						foreach($tables_sections as $section => $tables) {
							$_post_array['snapshot-tables-array'] = array_merge($_post_array['snapshot-tables-array'], $tables);
						}
					}
				}
				else if ($_post_array['snapshot-tables-option'] == "selected") {

					if (isset($item['tables-sections'])) {
						$this->_session->data['tables-sections'] = $item['tables-sections'];

						foreach($item['tables-sections'] as $section => $tables) {
							$_post_array['snapshot-tables-array'] = array_merge($_post_array['snapshot-tables-array'], $tables);
						}
					}
				}

				if ($item['destination-sync'] == "archive") {
					$_post_array['snapshot-files-option'] 		= $item['files-option'];
					$_post_array['snapshot-files-sections'] 	= array();
					if ($_post_array['snapshot-files-option'] == "none") {

					} else if ($_post_array['snapshot-files-option'] == "all") {

						if (is_main_site($item['blog-id'])) {
							$_post_array['snapshot-files-sections'] = 	array('themes', 'plugins', 'media');
						} else {
							$_post_array['snapshot-files-sections'] = 	array('media');
						}

					} else if ($_post_array['snapshot-files-option'] == "selected") {

						if (isset($item['files-sections'])) {
							$_post_array['snapshot-files-sections'] = $item['files-sections'];
						}
					}
				} else {
					$_post_array['snapshot-files-option'] = "none";
				}

				ob_start();
				$error_array = $this->snapshot_ajax_backup_init($item, $_post_array);
				$function_output = ob_get_contents();
				ob_end_clean();

				if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {

					$this->snapshot_logger->log_message("init: error_array". print_r($error_array, true));
					$this->snapshot_logger->log_message("init: item". print_r($item, true));
					$this->snapshot_logger->log_message("init: output:". $function_output);

					$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
						": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
						": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

					$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
					$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

					unset($snapshot_locker);
					die();
				}

				if ((isset($error_array['table_data'])) && (count($error_array['table_data']))) {

					// Switch to the blog site we are attempting to backup. This will ensure it should work properly
					if (is_multisite()) {
						$current_blogid = $wpdb->blogid;
						switch_to_blog(intval($item['blog-id']));
					}

					foreach($error_array['table_data'] as $idx => $table_item) {

						unset($_post_array);
						$_post_array['snapshot-proc-action'] 		= "table";
						$_post_array['snapshot-action'] 			= "cron";
						$_post_array['snapshot-blog-id']			= $item['blog-id'];
						$_post_array['snapshot-item']				= $item_key;
						$_post_array['snapshot-data-item']			= $data_item_key;
						$_post_array['snapshot-table-data-idx']		= $idx;

						$this->snapshot_logger->log_message("table: ". $table_item['table_name'] .
							" segment: ". $table_item['segment_idx'] ."/". $table_item['segment_total']);

						ob_start();
						$error_array_table = $this->snapshot_ajax_backup_table($item, $_post_array);
						$function_output = ob_get_contents();
						ob_end_clean();

						if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
							// We have a problem.

							$this->snapshot_logger->log_message("table: ". $table_item['table_name'] .": error_array". print_r($error_array_table, true));
							$this->snapshot_logger->log_message("table: ". $table_item['table_name'] .": _SESSION". print_r($this->_session, true));
							$this->snapshot_logger->log_message("table: ". $table_item['table_name'] .": item". print_r($item, true));
							$this->snapshot_logger->log_message("table: output:". $function_output);

							$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
								": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
								": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

							$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
							$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

							unset($snapshot_locker);

							die();
						}
					}

					if (is_multisite()) {
						if (isset($current_blogid))
						switch_to_blog(intval($current_blogid));
					}
				} else {
					$this->snapshot_logger->log_message("table: non selected");
				}

				if ($item['destination-sync'] == "archive") {

					if ( (isset($error_array['files_data'])) && (count($error_array['files_data'])) ) {

						foreach($error_array['files_data'] as $file_set_key) {

							unset($_post_array);
							$_post_array['snapshot-proc-action'] 	= "file";
							$_post_array['snapshot-action'] 		= "cron";
							$_post_array['snapshot-blog-id']		= $item['blog-id'];
							$_post_array['snapshot-item']			= $item_key;
							$_post_array['snapshot-file-data-key']	= $file_set_key;

							ob_start();
							$error_array_file = $this->snapshot_ajax_backup_file($item, $_post_array);
							$function_output = ob_get_contents();
							ob_end_clean();

							if ((isset($error_array_file['errorStatus'])) && ($error_array_file['errorStatus'] == true)) {
								// We have a problem.

								$this->snapshot_logger->log_message("file: _post_array:". print_r($_post_array, true));
								$this->snapshot_logger->log_message("file: error_array:". print_r($error_array_file, true));
								$this->snapshot_logger->log_message("file: _SESSION:". print_r($this->_session, true));
								$this->snapshot_logger->log_message("file: item:". print_r($item, true));
								$this->snapshot_logger->log_message("file: output:". $function_output);

								$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
									": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
									": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

								$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
								$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

								unset($snapshot_locker);

								die();
							}
						}

						if ((isset($error_array['files_data']['excluded']['pattern'])) && (count($error_array['files_data']['excluded']['pattern']))) {

							$this->snapshot_logger->log_message(__("file: The following files are excluded due to match exclusion patterns.",
								SNAPSHOT_I18N_DOMAIN));

							foreach($error_array['files_data']['excluded']['pattern'] as $idx => $filename) {
								$filename = str_replace($home_path, '', $filename);
								$this->snapshot_logger->log_message("file: excluded:  ". $filename);
							}
						}

						if ((isset($error_array['files_data']['excluded']['error'])) && (count($error_array['files_data']['excluded']['error']))) {

							$this->snapshot_logger->log_message(__("file: The following files are excluded because snapshot cannot open them. Check file permissions or locks", SNAPSHOT_I18N_DOMAIN));

							foreach($error_array['files_data']['excluded']['error'] as $idx => $filename) {
								$filename = str_replace($home_path, '', $filename);
								$this->snapshot_logger->log_message("file: error: ". $filename);
							}
						}

					} else {
						$this->snapshot_logger->log_message("file: non selected");
					}
				} else {
					$this->snapshot_logger->log_message("file: mirroring enabled. Files are synced during send to destination.");
				}

				$_post_array['snapshot-proc-action'] 	= "finish";
				$_post_array['snapshot-action'] 		= "cron";
				$_post_array['snapsho-blog-id']			= $item['blog-id'];
				$_post_array['snapshot-item']			= $item_key;
				$_post_array['snapshot-data-item']		= $data_item_key;

				ob_start();
				$error_array = $this->snapshot_ajax_backup_finish($item, $_post_array);
				$function_output = ob_get_contents();
				ob_end_clean();

				if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
					// We have a problem.

					$this->snapshot_logger->log_message("finish: error_array:". print_r($error_array, true));
					$this->snapshot_logger->log_message("finish: _SESSION:". print_r($this->_session, true));
					$this->snapshot_logger->log_message("finish: item:". print_r($item, true));
					$this->snapshot_logger->log_message("finish: output:". $function_output);

					$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
						": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
						": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );

					$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
					$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

					unset($snapshot_locker);

					die();
				} else {

				}

				$this->snapshot_logger->log_message("memory limit: ". ini_get('memory_limit') .
					": memory usage current: ". snapshot_utility_size_format(memory_get_usage(true)) .
					": memory usage peak: ". snapshot_utility_size_format(memory_get_peak_usage(true)) );


				if (isset($error_array['responseFile']))
					$this->snapshot_logger->log_message("finish: ". basename($error_array['responseFile']));

				$this->config_data['items'][$item_key]['data'][$data_item_key]['archive-status'][time()] = $error_array;
				$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);

				// Checking for Archive Account purge
				$this->purge_archive_limit($item_key);

				unset($snapshot_locker);
			}
			$this->process_item_remote_files($item_key);
		}


		function purge_archive_limit($item_key) {

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if (!isset($this->config_data['items'][$item_key])) return;

			$item = $this->config_data['items'][$item_key];

			if ((isset($item['archive-count'])) && (intval($item['archive-count']))) {
				$archive_count = intval($item['archive-count']);
				if ((isset($this->config_data['items'][$item_key]['data']))
				 && (count($this->config_data['items'][$item_key]['data']) > $archive_count)) {

					$this->snapshot_logger->log_message("archive cleanup: max archive:". intval($item['archive-count'])
						." number of archives: ". count($this->config_data['items'][$item_key]['data']));

					$item_data = $this->config_data['items'][$item_key]['data'];
					ksort($item_data);
					krsort($item_data);
					$data_keep = array_slice($item_data, 0, $archive_count, true);

					if ($data_keep) {
						ksort($data_keep);
						$this->config_data['items'][$item_key]['data'] = $data_keep;

						$this->add_update_config_item($item_key, $this->config_data['items'][$item_key]);
					}

					$data_purge = array_slice($item_data, $archive_count);

					if ($data_purge) {
						foreach($data_purge as $data_item) {
							//if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {
								if (isset($data_item['filename'])) {

									$current_backupFolder = $this->snapshot_get_item_destination_path($item, $data_item);
									if (empty($current_backupFolder)) {
										$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
									}
									$this->snapshot_logger->log_message("archive cleanup: DEBUG :". $current_backupFolder);

									$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];

									if (!file_exists($backupFile)) {
										$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
										$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
									}
									$this->snapshot_logger->log_message("DEBUG: backupFile=[". $backupFile ."]");

									if (file_exists($backupFile)) {
										@unlink($backupFile);
										$this->snapshot_logger->log_message("archive cleanup: filename: ". str_replace($home_path, '', $backupFile) ." removed");
									} else {
										$this->snapshot_logger->log_message("archive cleanup: filename: ". str_replace($home_path, '', $backupFile) ." not found");
									}
								}
							//}

							$backupLogFileFull = trailingslashit($this->_settings['backupLogFolderFull'])
								. $item['timestamp'] ."_". $data_item['timestamp'] .".log";

							if (file_exists($backupLogFileFull)) {
								@unlink($backupLogFileFull);
							}
						}
					}
				}
			}

		}

		function process_item_remote_files($item_key) {

			$item_key = intval($item_key);

			// reload the config.
			$this->load_config();

			// If we are somehow called for an item_key not in our list then remove any future cron calls then die
			if (!isset($this->config_data['items'][$item_key])) {
				return;
			}

			$item = $this->config_data['items'][$item_key];
			//echo "item<pre>"; print_r($item); echo "</pre>";
			//die();

			// If the item destination is not set or is empty then the file stay local.
			if ((!isset($item['destination'])) || (empty($item['destination'])) || ($item['destination'] == "local") )  return;

			// If the destination is set but not found in the destinations array we can't process. Abort.
			if (!isset($this->config_data['destinations'][$item['destination']])) return;

			// If the item doesn't have data. Abort.
			if ((!isset($item['data'])) && (!count($item['data']))) return;

			$snapshot_locker = new SnapshotLocker($this->_settings['backupLockFolderFull'], $item_key);

			if ($snapshot_locker->is_locked()) {
				ksort($item['data']);	// Earliest first. Since those need/should be processed first!
				foreach($item['data'] as $data_item_key => $data_item) {

					$data_item_key 	= $data_item['timestamp'];

					if ((isset($item['destination-sync'])) && ($item['destination-sync'] == "mirror"))
						$doing_message = __('Syncing Files', SNAPSHOT_I18N_DOMAIN);
					else
						$doing_message = __('Sending Archive', SNAPSHOT_I18N_DOMAIN);

					$locker_info = array(
						'doing'				=>	$doing_message,
						'item_key'			=>	$item_key,
						'data_item_key'		=>	$data_item_key,
						'time_start' 		=>	time()
					);
					$snapshot_locker->set_locker_info($locker_info);

					$data_item_new = $this->process_item_send_archive($item, $data_item, $snapshot_locker);

					if (($data_item_new) && (is_array($data_item_new))) {
						$item['data'][$data_item_key] = $data_item_new;
						$this->add_update_config_item($item_key, $item);
					}
				}
				unset($snapshot_locker);
			}
		}

		function process_item_send_archive($item, $data_item, $snapshot_locker) {
			$item_key		= $item['timestamp'];
			$data_item_key 	= $data_item['timestamp'];

			// Create a logged for each item/data_item combination because that is how the log files are setup
			if (isset($snapshot_logger)) unset($snapshot_logger);
				$snapshot_logger = new SnapshotLogger($this->_settings['backupLogFolderFull'], $item_key, $data_item_key);

			// If the file has already been transmitted the move to the next one.
			if ( (isset($data_item['destination-status'])) && (count($data_item['destination-status'])) ) {
				$destination_status = snapshot_utility_latest_data_item($data_item['destination-status']);
				//$snapshot_logger->log_message("destination_status<pre>: ". print_r($destination_status, true) ."</pre>");
				//echo "destination_status<pre>"; print_r($destination_status); echo "</pre>";
				//die();

				// If we have a positive 'sendFileStatus' continue on
				if ( (isset($destination_status['sendFileStatus'])) && ($destination_status['sendFileStatus'] == true) ) {
					//echo "finished item[". $item_key ."] data_item_key[". $data_item_key ."] file[". $data_item['filename'] ."]<br />";
					return;
				}

				 /*
				else if ( (isset($destination_status['responseArray'])) && (count($destination_status['responseArray']))
					     && (isset($destination_status['errorStatus'])) && ($destination_status['errorStatus'] != true) ) {
					return;
				} */
			}

			//echo "DEBUG: processing item[". $item_key ."] data_item[". $data_item_key ."] file[". $data_item['filename'] ."]<br />";
			//return

			// Get the archive folder
			$current_backupFolder = $this->snapshot_get_item_destination_path($item, $data_item);
			if (empty($current_backupFolder)) {
				$current_backupFolder = $this->snapshot_get_setting('backupBaseFolderFull');
			}
			//echo "DEBUG: current_backupFolder=[". $current_backupFolder ."]<br />";

			// If the data_item destination is not empty...
			if ((isset($data_item['destination'])) && (!empty($data_item['destination']))) {

				// We make sure to check it against the item master. If they don't match it means
				// the data_item archive was sent to the data_item destination. We probably don't
				// have the archive file to resent.
//				echo "destination data_item[". $data_item['destination']."] item[". $item['destination'] ."]<br />";
//				if ($data_item['destination'] !== $item['destination']) {
//					return;
//				}
			}

			$destination_key = $item['destination'];
			if (!isset($this->config_data['destinations'][$destination_key])) {
				return;
			}

			$destination = $this->config_data['destinations'][$destination_key];
			if (!isset($destination['type'])) {
				return;
			}

			if (!isset($this->_settings['destinationClasses'][$destination['type']]))
				return;

			$destination_object = $this->_settings['destinationClasses'][$destination['type']];

			$new_backupFolder = $this->snapshot_get_item_destination_path($item);
			if (($new_backupFolder) && (strlen($new_backupFolder))) {
				$destination['directory'] = $new_backupFolder;
			}

			if (!isset($data_item['destination-sync']))
				$data_item['destination-sync'] = "archive";

			$files_sync = array();
			if ($data_item['destination-sync'] == "archive") {

				// If the data item is there but no final archive filename (probably stopped in an error). Abort
				if ((!isset($data_item['filename'])) || (empty($data_item['filename']))) return;

				// See if we still have the archive file.
				// First check where we originally placed it.
				$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
				if (!file_exists($backupFile)) {

					// Then check is the detail Snapshot archive folder
					$current_backupFolder = $this->_settings['backupBaseFolderFull'];
					$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
					if (!file_exists($backupFile)) {
						return;;
					}
				}

				$snapshot_logger->log_message("Sending Archive: ". basename($backupFile) ." ". snapshot_utility_size_format(filesize($backupFile)));
				$snapshot_logger->log_message("Destination: ". $destination['type'] .": ". stripslashes($destination['name']));

				$locker_info = $snapshot_locker->get_locker_info();
				$locker_info['file_name'] = $backupFile;
				$locker_info['file_size'] = filesize($backupFile);
				$snapshot_locker->set_locker_info($locker_info);

				$destination_object->snapshot_logger = $snapshot_logger;
				$destination_object->snapshot_locker = $snapshot_locker;

				$error_array = $destination_object->sendfile_to_remote($destination, $backupFile);
				//echo "error_array<pre>"; print_r($error_array); echo "</pre>";

				//$snapshot_logger->log_message("DEBUG: error_array<pre>". print_r($error_array, true)."</pre>");

				if ( (isset($error_array['responseArray'])) && (count($error_array['responseArray'])) ) {
					foreach($error_array['responseArray'] as $message) {
						$snapshot_logger->log_message($message);
					}
				}

				if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
					if ( (isset($error_array['errorArray'])) && (count($error_array['errorArray'])) ) {
						foreach($error_array['errorArray'] as $message) {
							$snapshot_logger->log_message("ERROR: ". $message);
						}
					}
				}

				if (!isset($data_item['destination-status']))
					$data_item['destination-status'] = array();


				$data_item['destination-status'][time()] = $error_array;
				//echo "destination-status<pre>"; print_r($data_item['destination-status']); echo "</pre>";
				//die();

				krsort($data_item['destination-status']);
				if (count($data_item['destination-status']) > 5) {
					$data_item['destination-status'] = array_slice($data_item['destination-status'], 0, 5, true);
				}
				$data_item['destination'] = $item['destination'];
				$data_item['destination-directory'] = $item['destination-directory'];

//				echo "data_item<pre>"; print_r($data_item); echo "</pre>";
//				die();

			} else {

				// We create an option to store the list of files we are sending. This is better than adding to the config data
				// for snapshot. Less loading of the master array. The list of files is a reference we pass to the sender function
				// of the destination. As files are sent they are removed from the array and the option is updated. So if something
				// happens we don't start from the first of the list. Could probably use a local file...
				$snapshot_sync_files_option = 'wpmudev_snapshot_sync_files_'. $item_key;
				$snapshot_sync_files = get_option($snapshot_sync_files_option);

				if (!$snapshot_sync_files) $snapshot_sync_files = array();

				$last_sync_timestamp = time();

				//$snapshot_logger->log_message("DEBUG: going to snapshot_gather_item_files");
				//$snapshot_logger->log_message("DEBUG: data_item<pre>". print_r($data_item, true), "</pre>");
				if (!isset($data_item['blog-id']))
					$data_item['blog-id'] = $item['blog-id'];
				$gather_files_sync = $this->snapshot_gather_item_files($data_item);
				foreach($data_item['files-sections'] as $file_section) {
					if (($file_section == "config") || ($file_section == "config"))
						$file_section = "files";

					if (isset($gather_files_sync['included'][$file_section])) {
						if (!isset($snapshot_sync_files['last-sync'][$file_section]))
							$snapshot_sync_files['last-sync'][$file_section] = 0;

						foreach($gather_files_sync['included'][$file_section] as $_file_idx => $_file) {
							if (filemtime($_file) < $snapshot_sync_files['last-sync'][$file_section]) {
								unset($gather_files_sync['included'][$file_section][$_file_idx]);
							}
						}

						if (!isset($snapshot_sync_files['included'][$file_section]))
							$snapshot_sync_files['included'][$file_section] = array();

						if (count($gather_files_sync['included'][$file_section])) {
							$snapshot_sync_files['included'][$file_section] = array_merge($snapshot_sync_files['included'][$file_section],
							 	$gather_files_sync['included'][$file_section]);

							$snapshot_sync_files['included'][$file_section] = array_unique($snapshot_sync_files['included'][$file_section]);
							$snapshot_sync_files['included'][$file_section] = array_values($snapshot_sync_files['included'][$file_section]);
						}
						$snapshot_sync_files['last-sync'][$file_section] = $last_sync_timestamp;
					}
				}

				$destination_object->snapshot_logger = $snapshot_logger;
				$destination_object->snapshot_locker = $snapshot_locker;

				update_option($snapshot_sync_files_option, $snapshot_sync_files);
				$error_array = $destination_object->syncfiles_to_remote($destination, $snapshot_sync_files, $snapshot_sync_files_option);

				if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
					if ( (isset($error_array['errorArray'])) && (count($error_array['errorArray'])) ) {
						foreach($error_array['errorArray'] as $message) {
							$snapshot_logger->log_message("ERROR: ". $message);
						}
					}
				}

				if (!isset($data_item['destination-status']))
					$data_item['destination-status'] = array();

				$data_item['destination-status'][time()] = $error_array;
				krsort($data_item['destination-status']);
				if (count($data_item['destination-status']) > 5) {
					$data_item['destination-status'] = array_slice($data_item['destination-status'], 0, 5);
				}
				$data_item['destination'] = $item['destination'];
				$data_item['destination-directory'] = $item['destination-directory'];

				// See if we still have the archive file.
				// First check where we originally placed it.
				if (strlen($data_item['filename'])) {
					$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
					if (!file_exists($backupFile)) {

						// Then check is the detail Snapshot archive folder
						$current_backupFolder = $this->_settings['backupBaseFolderFull'];
						$backupFile = trailingslashit($current_backupFolder) . $data_item['filename'];
						if (!file_exists($backupFile)) {
							return $data_item;;
						}
					}

					//echo "backupFile=[". $backupFile ."]<br />";

					$snapshot_logger->log_message("Sending Archive: ". basename($backupFile));
					$snapshot_logger->log_message("Destination: ". $destination['type'] .": ". stripslashes($destination['name']));

					$error_array = $destination_object->sendfile_to_remote($destination, $backupFile);
					//$snapshot_logger->log_message("DEBUG: error_array<pre>". print_r($error_array, true)."</pre>");

					if ( (isset($error_array['responseArray'])) && (count($error_array['responseArray'])) ) {
						foreach($error_array['responseArray'] as $message) {
							$snapshot_logger->log_message($message);
						}
					}

					if ((isset($error_array['errorStatus'])) && ($error_array['errorStatus'] == true)) {
						if ( (isset($error_array['errorArray'])) && (count($error_array['errorArray'])) ) {
							foreach($error_array['errorArray'] as $message) {
								$snapshot_logger->log_message("ERROR: ". $message);
							}
						}
					}

					if (!isset($data_item['destination-status']))
						$data_item['destination-status'] = array();

					$data_item['destination-status'][time()] = $error_array;
					krsort($data_item['destination-status']);
					if (count($data_item['destination-status']) > 5) {
						$data_item['destination-status'] = array_slice($data_item['destination-status'], 0, 5);
					}
					$data_item['destination'] = $item['destination'];
					$data_item['destination-directory'] = $item['destination-directory'];
				}
			}
			return $data_item;
		}

		/**
		 * Utility function called by WPCron scheduling dispatch. This function handles forwarding of files
		 * to remote destination.
		 *
		 * @since 1.0.7
		 * @see
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_remote_file_cron_proc() {

			global $wpdb;

			@ini_set('html_errors', 'Off');
			@ini_set('zlib.output_compression', 'Off');
			@set_time_limit(0);

			$old_error_handler = set_error_handler(array( &$this, 'snapshot_ErrorHandler' ));

			if ((isset($this->config_data['config']['memoryLimit'])) && (!empty($this->config_data['config']['memoryLimit']))) {
				@ini_set('memory_limit', $this->config_data['config']['memoryLimit']);
			}

			// If we are somehow called for an item_key not in our list then remove any future cron calls then die
			if ((!isset($this->config_data['items'])) || (!count($this->config_data['items']))) {
				return;
			}

			// If we don't have any remote destinations...why are we here.
			if ((!isset($this->config_data['destinations'])) || (!count($this->config_data['destinations']))) {
				return;
			}

			foreach($this->config_data['items'] as $item_key => $item) {
				$this->process_item_remote_files($item_key);
			}
		}

		/**
		 * Custom Error handler to trap critical error and log them
		 *
		 * @since 1.0.4
		 * @see
		 *
		 * @param errno, errstr, errfile, errline all provided by PHP
		 * @return none
		 */

		function snapshot_ErrorHandler($errno, $errstr, $errfile, $errline)
		{
			//echo "errno[". $errno ."]<br />";
			//echo "errstr[". $errstr ."]<br />";
			//echo "errfile[". $errfile ."]<br />";
			//echo "errline[". $errline ."]<br />";

			$errType = '';
			if ( (defined('E_ERROR')) && ($errno == E_ERROR) )
				$errType = "Error";
			else if ((defined('E_WARNING')) && ($errno == E_WARNING))
				$errType = "Warning";
			else if ((defined('E_PARSE')) && ($errno == E_PARSE))
				$errType = "Parse";
			else if ((defined('E_NOTICE')) && ($errno == E_NOTICE))
				$errType = "Notice";
			else if ((defined('E_CORE_ERROR')) && ($errno == E_CORE_ERROR))
				$errType = "Error (core)";
			else if ((defined('E_CORE_WARNING')) && ($errno == E_CORE_WARNING))
				$errType = "Warning (core)";
			else if ((defined('E_COMPILE_ERROR')) && ($errno == E_COMPILE_ERROR))
				$errType = "Error (compile)";
			else if ((defined('E_COMPILE_WARNING')) && ($errno == E_COMPILE_WARNING))
				$errType = "Warning (compile)";
			else if ((defined('E_USER_ERROR')) && ($errno == E_USER_ERROR))
				$errType = "Error (user)";
			else if ((defined('E_USER_WARNING')) && ($errno == E_USER_WARNING))
				$errType = "Warning (user)";
			else if ((defined('E_USER_NOTICE')) && ($errno == E_USER_NOTICE))
				$errType = "Notice (user)";
			else if ((defined('E_STRICT')) && ($errno == E_STRICT))
				$errType = "Strict";
			else if ((defined('E_RECOVERABLE_ERROR')) && ($errno == E_RECOVERABLE_ERROR))
				$errType = "Error (recoverable)";
			else if ((defined('E_DEPRECATED')) && ($errno == E_DEPRECATED))
				$errType = "Deprecated";
			else if ((defined('E_USER_DEPRECATED')) && ($errno == E_USER_DEPRECATED))
				$errType = "Deprecated (user)";
			else
				$errType = "Unknown";

			if (isset($this->config_data['config']['errorReporting'][$errno]['log'])) {

				// We need to check the logger because there might be an error BEFORE it is ready.
				if (is_object($this->snapshot_logger)) {
					// Build the error reporting message
					$error_string = $errType .": errno:". $errno ." ". $errstr ." ". $errfile ." on line ". $errline;
					$this->snapshot_logger->log_message($error_string);
				}
			}

			//if (!isset($this->config_data['config']['errorReporting'][$errno]['stop'])) {
			//	return;
			//}

	        // This error code is not included in error_reporting
			if (!(error_reporting() & $errno)) {
	        	return;
	    	}

			$error_array = array();
			$error_array['errorStatus'] 	= true;
			$error_array['errorText'] 		= "<p>". $error_string ."</p>";
			$error_array['responseText'] 	= "";

			echo json_encode($error_array);
			die();
		}

		function snapshot_get_item_destination_path($item=array(), $data_item=array(), $create_folder=true) {

			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			// If not destination in the data_item we can't process.
			if (!isset($data_item['destination'])) {
				if (!isset($item['destination'])) {
					return;
				} else {
					$data_item['destination'] = $item['destination'];
				}
			}

			if (!isset($data_item['destination-directory'])) {
				if (isset($item['destination-directory'])) {
					$data_item['destination-directory'] = $item['destination-directory'];
				} else {
					$data_item['destination-directory'] = '';
				}
			}

			if (empty($data_item['destination-directory']))
				return;

			$backupFolder = trim($data_item['destination-directory']);
			//echo "backupFolder=[". $backupFolder ."]<br />";

			if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {
				$backupFolder = str_replace('[DEST_PATH]', $this->_settings['backupBaseFolderFull'], $backupFolder);
			} else {
				$destination_key = $data_item['destination'];
				//echo "destination_key[". $destination_key ."]<br />";
				if (isset($this->config_data['destinations'][$destination_key]['directory'])) {
					$d_directory = $this->config_data['destinations'][$destination_key]['directory'];
					//echo "d_directory[". $d_directory ."]<br />";
					$backupFolder = str_replace('[DEST_PATH]', $d_directory, $backupFolder);
					//echo "#1 backupFolder[". $backupFolder ."]<br />";
				} else {
					$backupFolder = str_replace('[DEST_PATH]', '', $backupFolder);
					//echo "#2 backupFolder[". $backupFolder ."]<br />";
				}
				//die();
			}

			if (is_multisite()) {
				$blog_info = get_blog_details($item['blog-id']);
				if ($blog_info->domain) {
					$domain = $blog_info->domain;
				}
			}
			else {
				$siteurl = get_option( 'siteurl' );
				if ($siteurl) {
					$domain = parse_url($siteurl, PHP_URL_HOST);
				}
			}

			if (!isset($domain))
				$domain = '';

			$backupFolder = str_replace('[SITE_DOMAIN]', $domain, $backupFolder);
			$backupFolder = str_replace('[SNAPSHOT_ID]', $item['timestamp'], $backupFolder);
			//echo "#3 backupFolder[". $backupFolder ."]<br />";
			//echo "this<pre>"; print_r($this); echo "</pre>";

			// Only for local destination. If the destination path does not start with a leading slash (for absolute paths), then prepend
			// the site root path.
			if (((empty($data_item['destination'])) || ($data_item['destination'] == "local")) && (!empty($backupFolder))) {
				if (substr($backupFolder, 0, 1) != "/") {
					$backupFolder = trailingslashit($home_path) . $backupFolder;
				}
				//echo "create_folder[". $create_folder ."]<br />";
				if ($create_folder) {
					if (!file_exists($backupFolder)) {
						@wp_mkdir_p($backupFolder);
					}
				}
			}
			//echo "#4 backupFolder[". $backupFolder ."]<br />";
			//die();
			return $backupFolder;
		}

		function snapshot_ajax_view_log_proc() {

			if ((isset($_REQUEST['snapshot-item'])) && (isset($_REQUEST['snapshot-data-item']))) {
				$item_key 		= intval($_REQUEST['snapshot-item']);
				if (isset($this->config_data['items'][$item_key])) {
					$item = $this->config_data['items'][$item_key];

					$data_item_key 	= intval($_REQUEST['snapshot-data-item']);
					if (isset($this->config_data['items'][$item_key]['data'][$data_item_key])) {
						$data_item = $this->config_data['items'][$item_key]['data'][$data_item_key];

						$backupLogFileFull = trailingslashit($this->snapshot_get_setting('backupLogFolderFull'))
							. $item['timestamp'] ."_". $data_item['timestamp'] .".log";

						if (file_exists($backupLogFileFull)) {

							if (isset($_POST['snapshot-log-position']))
								$log_position = intval($_POST['snapshot-log-position']);
							else
								$log_position = 0;

							$log_file_information = array();
							$log_file_information['payload'] = '';
							$log_file_information['position'] = $log_position;

							$handle = @fopen($backupLogFileFull, "r");
							if ($handle) {
								fseek($handle, $log_position);
						    	while (($buffer = fgets($handle, 4096)) !== false) {
						        	$log_file_information['payload'] .= $buffer ."<br />";
						    	}
						    	//if (!feof($handle)) {
						        //	$log_file_information['payload'][] = "Error: unexpected fgets() fail\n";
						    	//}
								//$log_file_information['payload'] = fread($handle, 10000);
								//$log_file_information['payload'] = nl2br($log_file_information['payload']);
								$log_file_information['position'] = ftell($handle);
						    	fclose($handle);
								echo json_encode($log_file_information);
								die();
							}
							echo "<br /><br />";

						}
					}
				}
			}
			die();
		}

		function snapshot_gather_item_files($item) {
			global $wpdb, $site_id;

			$item_files = array();
			$home_path = apply_filters( 'snapshot_home_path', get_home_path() );

			if ((!isset($item['files-option'])) || (!count($item['files-option'])))
				return $item_files;

			if ($item['files-option'] == "none") {
				if ((isset($item['files-sections'])) && (count($item['files-sections']))) {
					unset($item['files-sections']);
					$item['files-sections'] = array();
				}
			} else if ($item['files-option'] == "all") {
				if (is_main_site($item['blog-id'])) {
					$files_sections = array('themes', 'plugins', 'media');
				} else {
					$files_sections = array('media');
				}
			} else if ($item['files-option'] == "selected") {
				$files_sections = $item['files-sections'];
			}

			if ((!isset($files_sections)) || (!count($files_sections)))
			 	return $item_files;

			//global $is_IIS;
			//echo "is_IIS[". $is_IIS ."]<br />";
			//echo "iis7_supports_permalinks[". iis7_supports_permalinks() ."]<br />";
			//echo "files_sections<pre>"; print_r($files_sections); echo "</pre>";

			foreach($files_sections as $file_section) {

				switch($file_section) {
					case 'media':

						$_path = $home_path . snapshot_utility_get_blog_upload_path($item['blog-id']);
						$_path = str_replace('\\', '/', $_path);

						//echo "_path[". $_path ."]<br />";
						$item_files['media'] = snapshot_utility_scandir($_path);
						//echo "media files<pre>"; print_r($item_files['media']); echo "</pre>";
						//die();

						break;


					case 'plugins':
						$_path = trailingslashit(WP_CONTENT_DIR) . 'plugins';
						$_path = str_replace('\\', '/', $_path);
						$item_files['plugins'] = snapshot_utility_scandir($_path);
						break;

/*
					case 'mu-plugins':
						$_path = trailingslashit(WP_CONTENT_DIR) . 'mu-plugins';
						$_path = str_replace('\\', '/', $_path);
						$item_files['mu-plugins'] = snapshot_utility_scandir($_path);
						break;
*/

					case 'themes':
						$_path = trailingslashit(WP_CONTENT_DIR) . 'themes';
						$_path = str_replace('\\', '/', $_path);
						$item_files['themes'] = snapshot_utility_scandir($_path);
					 	break;


					case 'config':
						$wp_config_file = trailingslashit($home_path) ."wp-config.php";
						//$wp_config_file = str_replace('\\', '/', $wp_config_file);

						if (file_exists($wp_config_file)) {

							if (!isset($item_files['files']))
								$item_files['files'] = array();

							$item_files['files'][] = $wp_config_file;
						}
						break;


					case 'htaccess':
						$wp_htaccess_file = trailingslashit($home_path) .".htaccess";
						//$wp_htaccess_file = str_replace('\\', '/', $wp_htaccess_file);
						if (file_exists($wp_htaccess_file)) {

							if (!isset($item_files['files']))
								$item_files['files'] = array();

							$item_files['files'][] = $wp_htaccess_file;
						}

						$web_config_file = trailingslashit($home_path) ."web.config";
						//$web_config_file = str_replace('\\', '/', $web_config_file);
						if (file_exists($web_config_file)) {

							if (!isset($item_files['files']))
								$item_files['files'] = array();

							$item_files['files'][] = $web_config_file;
						}

						break;


					default:
						break;
				}
			}

			//echo "item_files<pre>"; print_r($item_files); echo "</pre>";
			//die();

			if (!count($item_files))
				return $item_files;

			// Exclude files.
			$item_ignore_files 		= array();

			// With WP 3.5 fresh installs we have a slight issue. In prior versions of WP the main site upload folder and
			// related sub-site were seperate. Main site was typically /wp-content/uploads/ while sub-sites were
			// /wp-content/blogs.dir/X/files/
			// But in 3.5 when doing a fresh install, not upgrade, the sub-site upload path is beneath the main site.
			// main site /wp-content/uploads/ and sub-site wp-content/uploads/sites/X
			// So we have this added fun to try and exclude the sub-site from the main site's media. ug.
			$blog_id = intval($item['blog-id']);
			if ((is_multisite()) && (is_main_site($blog_id))) {

				$main_site_upload_path = snapshot_utility_get_blog_upload_path( $blog_id );
				$sql_str = $wpdb->prepare("SELECT blog_id FROM ". $wpdb->base_prefix ."blogs WHERE blog_id != %d AND site_id=%d LIMIT 5", $blog_id, $site_id);
				$blog_ids = $wpdb->get_col($sql_str);
				if (!empty($blog_ids)) {
					foreach($blog_ids as $blog_id_tmp) {
						$sub_site_upload_path = snapshot_utility_get_blog_upload_path( $blog_id_tmp );
						if (!empty($sub_site_upload_path)) {
							if (($sub_site_upload_path !== $main_site_upload_path)
 						  	 && (substr($sub_site_upload_path, 0, strlen($main_site_upload_path)) == $main_site_upload_path)) {
								$item_ignore_files[] = dirname($sub_site_upload_path);
							}
							break;
						}
					}
				}
			}


			//We auto exclude the snapshot tree. Plus any entered exclude entries from the form.
			$item_ignore_files[] 	= trailingslashit($this->_settings['backupBaseFolderFull']);
			$item_ignore_files[] 	= trailingslashit($this->_settings['SNAPSHOT_PLUGIN_BASE_DIR']);

			// Then we add any global excludes
			if ((isset($this->config_data['config']['filesIgnore'])) && (count($this->config_data['config']['filesIgnore'])))
				$item_ignore_files 		= array_merge($item_ignore_files, $this->config_data['config']['filesIgnore']);

			// Then item excludes
			if ((isset($item['files-ignore'])) && (count($item['files-ignore'])))
				$item_ignore_files 		= array_merge($item_ignore_files, $item['files-ignore']);

			$item_section_files = array();
			// Need to exclude the user ignore patterns as well as our Snapshot base folder. No backup of the backups
			foreach($item_files as $item_set_key => $item_set_files) {
				if ((!is_array($item_set_files)) || (!count($item_set_files))) continue;

				foreach($item_set_files as $item_set_files_key => $item_set_files_file) {

					// We spin through all the files. They will fall into one of three sections...

					// If the file is not readable we ignore
					if (!is_readable($item_set_files_file)) {

						if (!isset($item_section_files['error'][$item_set_key]))
							$item_section_files['error'][$item_set_key] = array();

						$item_section_files['error'][$item_set_key][] = $item_set_files_file;

					} else {

						$EXCLUDE_THIS_FILE = false;
						foreach($item_ignore_files as $item_ignore_file) {
							// Make sure we don't have any blank entries.
							$item_ignore_file = trim($item_ignore_file);
							if (empty($item_ignore_file)) continue;



							//echo "item_set_files_file<pre>"; print_r($item_set_files_file); echo "</pre>";
							//echo "item_ignore_file[". $item_ignore_file ."]<br />";
							$stristr_ret = stristr($item_set_files_file, $item_ignore_file);
							if ($stristr_ret !== false) {
								$EXCLUDE_THIS_FILE = true;
								break;
							}
						}

						if ($EXCLUDE_THIS_FILE == false) {
							// If file is valid we keep it
							if (!isset($item_section_files['included'][$item_set_key]))
								$item_section_files['included'][$item_set_key] = array();

							$item_section_files['included'][$item_set_key][] = $item_set_files_file;

						} else {
							if (!isset($item_section_files['excluded']['pattern']))
								$item_section_files['excluded']['pattern'] = array();

							$item_section_files['excluded']['pattern'][] = $item_set_files_file;
						}
					}
				}
			}
			//echo "item_section_files<pre>"; print_r($item_section_files); echo "</pre>";
			//die();
			return $item_section_files;
		}

		function destination_register_proc($name_class) {

	//		echo "name_class=[". $name_class ."]<br />";
	//		if (class_exists($name_class)) {

				$classObject = new $name_class;
				if (isset($classObject->name_slug)) {
					if (!isset($this->_settings['destinationClasses'][$classObject->name_slug])) {
						$this->_settings['destinationClasses'][$classObject->name_slug] = $classObject;
					}
				}
	//		}
		}

		function snapshot_ajax_item_abort_proc() {

			$error_array = array();
			$error_array['errorStatus'] 	= false;
			$error_array['errorText'] 		= "";
			$error_array['responseText'] 	= "";

			$item_info = array();
			if (isset($_POST['snapshot_item_info'])) {
				$post_info = explode('&', $_POST['snapshot_item_info']);

				foreach($post_info as $post_info_item) {
					$_parts = explode('=', $post_info_item);
					if ( (isset($_parts[0])) && (!empty($_parts[0]))
					  && (isset($_parts[1])) && (!empty($_parts[1])) ) {
						$item_info[$_parts[0]] = $_parts[1];
					}
				}
			}


			if ( (isset($item_info['pid'])) && (!empty($item_info['pid']))
			  && (isset($item_info['item'])) && (!empty($item_info['item']))
			  && (current_user_can( 'manage_snapshots_items' )) ) {

				$snapshot_locker = new SnapshotLocker($this->_settings['backupLockFolderFull'], $item_info['item']);
				if (!$snapshot_locker->is_locked()) {
					$locker_info = $snapshot_locker->get_locker_info();
					if (intval($locker_info['pid']) === intval($item_info['pid'])) {
						posix_kill(intval($item_info['pid']), 9);
						$error_array['responseText'] = " Aborted Item. Page will reload.";

						$snapshot_logger = new SnapshotLogger($this->_settings['backupLogFolderFull'],
							$locker_info['item_key'], $locker_info['data_item_key']);

						$current_user = wp_get_current_user();
						//echo "display_name=[". $current_user->display_name ."]<br />";
						//echo "current_user<pre>"; print_r($current_user); echo "</pre>";

						$snapshot_logger->log_message('Process ['. $item_info['pid'] .'] ABORT by user: '. $current_user->display_name);

					}
				}

			} else {

			}

			echo json_encode($error_array);
			die();
		}
	}
}
$wpmudev_snapshot = new WPMUDEVSnapshot();