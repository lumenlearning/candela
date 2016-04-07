<?php
/*
Plugin Name: WP Offload S3 - Enable Media Replace Addon
Plugin URI: http://deliciousbrains.com/wp-offload-s3/#enable-media-replace-addon
Description: WP Offload S3 addon to integrate Enable Media Replace with Amazon S3. Requires Pro Upgrade.
Author: Delicious Brains
Version: 1.0
Author URI: http://deliciousbrains.com
Network: True

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

$as3cfpro_plugin_version_required = '1.0';

require dirname( __FILE__ ) . '/classes/wp-aws-compatibility-check.php';
global $as3cf_enable_media_replace_compat_check;
$as3cf_enable_media_replace_compat_check = new WP_AWS_Compatibility_Check(
	'WP Offload S3 - Enable Media Replace Addon',
	'amazon-s3-and-cloudfront-enable-media-replace',
	__FILE__,
	'WP Offload S3 - Pro Upgrade',
	'amazon-s3-and-cloudfront-pro',
	$as3cfpro_plugin_version_required,
	null,
	false,
	'https://deliciousbrains.com/wp-offload-s3/'
);

function as3cf_enable_media_replace_init( $aws ) {
	global $as3cf_enable_media_replace_compat_check;
	if ( ! $as3cf_enable_media_replace_compat_check->is_compatible() ) {
		return;
	}

	global $as3cfenable_media_replace;
	$abspath = dirname( __FILE__ );
	require_once $abspath . '/classes/amazon-s3-and-cloudfront-enable-media-replace.php';
	$as3cfenable_media_replace = new Amazon_S3_And_CloudFront_Enable_Media_Replace( __FILE__ );
}

add_action( 'aws_init', 'as3cf_enable_media_replace_init', 12 );
