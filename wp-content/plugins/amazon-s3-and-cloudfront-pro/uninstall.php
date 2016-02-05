<?php
/**
 * Uninstall WP Offload S3 - Pro Addon
 *
 * @package     amazon-s3-and-cloudfront-pro
 * @subpackage  uninstall
 * @copyright   Copyright (c) 2015, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require dirname( __FILE__ ) . '/classes/as3cf-pro-utils.php';
require dirname( __FILE__ ) . '/classes/wp-aws-uninstall.php';
// We cannot require the Pro class, as it will cause a fatal error if WPOS3 is not installed.

$options = array(
	'as3cfpro_licence_issue_type',
);

$postmeta = array(
	'wpos3_old_file_path',
	'wpos3_old_meta',
);

$keys = AS3CF_Pro_Utils::get_batch_job_keys();
// Delete wildcard options
AS3CF_Pro_Utils::delete_wildcard_options( $keys );

$crons = array(
	'wpos3_find_replace_cron',
	'wpos3_media_actions_cron',
	'wpos3_settings_change_cron',
);

$transients = array(
	'as3cfpro_installer_notices',
	'dbrains_api_down',
	'as3cfpro_addons',
	'as3cfpro_help_message',
	'as3cfpro_upgrade_data',
	'as3cfpro_addons_available',
	'as3cfpro_licence_response',
	'as3cfpro_temporarily_disable_ssl',
	'as3cfpro_media_library_total',
	'as3cfpro_licence_media_check',
	'wpos3_find_replace_process_lock',
	'wpos3_media_actions_process_lock',
	'wpos3_settings_change_process_lock',
	'wpos3_legacy_upload',
	'as3cfpro_plugins_to_install_installer',
	'as3cfpro_plugins_to_install_addons',
);

$as3cf_uninstall = new WP_AWS_Uninstall( $options, $postmeta, $crons, $transients );
