<?php
/**
 * Pro Plugin Compatibility
 *
 * @package     amazon-s3-and-cloudfront-pro
 * @subpackage  Classes/Plugin-Compatibility
 * @copyright   Copyright (c) 2015, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.8.3
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS3CF_Pro_Plugin_Compatibility Class
 *
 * This class handles compatibility code for third party plugins used in conjunction with AS3CF Pro
 *
 * @since 0.8.3
 */
class AS3CF_Pro_Plugin_Compatibility extends AS3CF_Plugin_Compatibility {

	/**
	 * @var
	 */
	protected $plugin_functions_abort_upload;

	/**
	 * @var AS3CF_Pro_Plugin_Installer
	 */
	protected $plugin_installer;

	/**
	 * @param Amazon_S3_And_CloudFront_Pro $as3cf
	 */
	function __construct( $as3cf ) {
		parent::__construct( $as3cf );

		add_filter( 'wpos3_compat_addons_notice', array( $this, 'compatibility_addon_notice' ), 10, 2 );
		add_filter( 'wpos3_compat_addons_notice_args', array( $this, 'compatibility_addon_notice_args' ) );

		$this->plugin_installer = new AS3CF_Pro_Plugin_Installer( 'addons', $this->as3cf->get_plugin_slug( true ), $this->as3cf->get_plugin_file_path() );
	}

	/**
	 * Change the addon compatibility notice
	 *
	 * @param string $notice
	 * @param array  $addons_to_install
	 *
	 * @return string
	 */
	function compatibility_addon_notice( $notice, $addons_to_install ) {
		$available_addons   = array();
		$unavailable_addons = array();

		$valid_licence = $this->as3cf->is_valid_licence();
		if ( false === $valid_licence ) {
			$notice        = $this->render_addon_list( $addons_to_install );
			$support_email = 'nom@deliciousbrains.com';
			$support_link  = sprintf( '<a href="mailto:%1$s">%1$s</a>', $support_email );
			$notice .= '<p>';
			$notice .= sprintf( __( "You must have a valid license to install these addons. If you're having trouble determining whether or not you need the addons, send an email to %s.", 'as3cf-pro' ), $support_link );
			$notice .= '</p>';

			return $notice;
		}

		$support_url  = $this->as3cf->get_plugin_page_url( array( 'hash' => 'support' ) );
		$support_link = sprintf( '<a href="%s" class="support-tab-link">%s</a>', $support_url, __( 'support request', 'as3cf-pro' ) );

		$help_text = '<p>';
		$help_text .= sprintf( __( "If you're having trouble determining whether or not you need the addons, submit a %s and we'll help you out.", 'as3cf-pro' ), $support_link );
		$help_text .= '</p>';

		if ( false === get_site_transient( 'as3cfpro_addons_available' ) ) {
			// Addons available for license data not stored, don't show notice
			return false;
		}

		$licence_addons = $this->as3cf->get_plugin_addons();

		foreach ( $licence_addons as $base => $addon ) {
			if ( ! isset( $addons_to_install[ $addon['slug'] ] ) ) {
				continue;
			}

			if ( false === $addon['available'] ) {
				$unavailable_addons[ $addon['slug'] ] = $addons_to_install[ $addon['slug'] ];
			} else {
				$available_addons[ $addon['slug'] ] = $addons_to_install[ $addon['slug'] ];
			}
		}

		$available_singular_text = __( 'Install & Activate Addon', 'as3cf-pro' );
		$available_plural_text   = __( 'Install & Activate Addons', 'as3cf-pro' );

		$available = $this->render_addon_list( $available_addons );
		if ( empty( $unavailable_addons ) ) {
			$available .= $help_text;
		}
		$available .= '<p style="margin-bottom: 10px;">';
		$available .= '<a href="#" class="button button-primary button-large install-plugins" data-process="' . $this->plugin_installer->process_key . '">';
		$available .= _n( $available_singular_text, $available_plural_text, count( $available_addons ) );
		$available .= '</a></p>';


		if ( ! empty( $available_addons ) ) {
			// Clean the plugin names
			$plugins_to_install = array();
			foreach ( $available_addons as $slug => $addon ) {
				$plugins_to_install[ $slug ] = $addon['title'] . ' ' . __( 'Addon', 'as3cf-pro' );
			}
			// Fire up the plugin installer
			$this->plugin_installer->set_plugins_to_install( $plugins_to_install );
			$this->plugin_installer->load_installer_assets();
		}

		if ( empty( $unavailable_addons ) ) {
			// All addons available
			return $available;
		}

		$unavailable_singular_text = __( 'Unfortunately, your current license does not give you access to the following addon. You need to upgrade your license to get this addon.', 'as3cf-pro' );
		$unavailable_plural_text   = __( 'Unfortunately, your current license does not give you access to the following addons. You need to upgrade your license to get these addons.', 'as3cf-pro' );

		$unavailable = '<p>' . _n( $unavailable_singular_text, $unavailable_plural_text, count( $unavailable_addons ) ) . '</p>';
		$unavailable .= $this->render_addon_list( $unavailable_addons );
		$unavailable .= $help_text;
		$account_url = 'https://deliciousbrains.com/my-account/';
		$unavailable .= '<p><a href="' . $account_url . '" class="button button-large">';
		$unavailable .= __( 'View License Upgrades', 'as3cf-pro' );
		$unavailable .= '</a></p>';

		if ( empty( $available_addons ) ) {
			// All addons unavailable
			return $unavailable;
		}

		// Split addon availability
		return $available . $unavailable;
	}

	/**
	 * Change the class for the compat addon notice
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function compatibility_addon_notice_args( $args ) {
		$args['class'] = 'as3cf-pro-installer';

		return $args;
	}

	/**
	 * Register the compatibility hooks
	 */
	function compatibility_init() {
		$plugin_functions_abort_upload = array(
			'gambit_otf_regen_thumbs_media_downsize', // https://wordpress.org/plugins/otf-regenerate-thumbnails/
			'ewww_image_optimizer_resize_from_meta_data', // https://wordpress.org/plugins/ewww-image-optimizer/
		);

		$this->plugin_functions_abort_upload = apply_filters( 'wpos3_plugin_functions_to_abort_upload', $plugin_functions_abort_upload );

		add_filter( 'as3cf_pre_update_attachment_metadata', array( $this, 'abort_update_attachment_metadata' ), 10, 3 );
	}

	/**
	 * Abort our upload to S3 on wp_attachment_metadata_update from different plugins
	 * as as we have used the stream wrapper to do any uploading to S3.
	 *
	 * @param bool  $pre
	 * @param array $data
	 * @param int   $post_id
	 *
	 * @return bool
	 */
	function abort_update_attachment_metadata( $pre, $data, $post_id ) {
		$callers = debug_backtrace();
		foreach ( $callers as $caller ) {
			if ( isset( $caller['function'] ) && in_array( $caller['function'], $this->plugin_functions_abort_upload ) ) {
				if ( $this->as3cf->get_setting( 'remove-local-file' ) || ! file_exists( get_attached_file( $post_id, true  ) ) ) {
					// abort the rest of the update_attachment_metadata hook
					// if the file doesn't exist on the server, as the stream wrapper
					// has taken care of the rest.
					return true;
				}
			}
		}

		return $pre;
	}
}