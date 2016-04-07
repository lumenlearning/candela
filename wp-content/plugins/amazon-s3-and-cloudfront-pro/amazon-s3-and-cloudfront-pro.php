<?php
/*
Plugin Name: WP Offload S3 - Pro Upgrade
Plugin URI:  http://deliciousbrains.com/wp-offload-s3/
Description: Pro upgrade of WP Offload S3 for media uploads to Amazon S3 for storage and delivery.
Author: Delicious Brains
Version: 1.0.5
Author URI: http://deliciousbrains.com/
Network: True
Text Domain: as3cf-pro
Domain Path: /languages/

// Copyright (c) 2015 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************
//
*/

require_once dirname( __FILE__ ) . '/version.php';

$as3cf_plugin_version_required = '0.9.12';

require_once dirname( __FILE__ ) . '/classes/as3cf-pro-installer.php';
require_once dirname( __FILE__ ) . '/classes/as3cf-pro-plugin-installer.php';

global $as3cf_pro_compat_check;
$as3cf_pro_compat_check = new AS3CF_Pro_Installer( __FILE__, $as3cf_plugin_version_required );

function as3cf_pro_init( $aws ) {
	global $as3cf_pro_compat_check;
	if ( ! $as3cf_pro_compat_check->are_required_plugins_activated() ) {
		return;
	}

	if ( ! $as3cf_pro_compat_check->is_parent_plugin_at_version( '0.9' ) ) {
		// Ensure the version of WP Offload S3 is up to 0.9, as much of the code below requires it.
		return;
	}

	global $as3cfpro;
	$abspath = dirname( __FILE__ );

	require_once $abspath . '/vendor/deliciousbrains/autoloader.php';
	require_once $abspath . '/classes/as3cf-pro-licences-updates.php';
	require_once $abspath . '/classes/amazon-s3-and-cloudfront-pro.php';
	require_once $abspath . '/classes/as3cf-pro-plugin-compatibility.php';
	require_once $abspath . '/classes/as3cf-pro-utils.php';
	require_once $abspath . '/classes/as3cf-async-request.php';
	require_once $abspath . '/classes/as3cf-background-process.php';
	require_once $abspath . '/classes/async-requests/as3cf-init-settings-change.php';
	require_once $abspath . '/classes/background-processes/as3cf-find-replace.php';
	require_once $abspath . '/classes/background-processes/as3cf-media-actions.php';
	require_once $abspath . '/classes/background-processes/as3cf-settings-change.php';
	$as3cfpro = new Amazon_S3_And_CloudFront_Pro( __FILE__, $aws );
}

add_action( 'aws_init', 'as3cf_pro_init', 11 );
