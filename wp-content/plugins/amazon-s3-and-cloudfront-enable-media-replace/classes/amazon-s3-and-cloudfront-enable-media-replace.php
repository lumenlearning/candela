<?php

class Amazon_S3_And_CloudFront_Enable_Media_Replace {

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * @param string $plugin_file_path
	 */
	function __construct( $plugin_file_path ) {
		global $as3cfpro;
		$this->as3cf = $as3cfpro;

		/*
		 * Enable Media Replace
		 * https://wordpress.org/plugins/enable-media-replace/
		 */
		add_filter( 'as3cf_get_attached_file', array( $this, 'enable_media_replace_download_file' ), 10, 4 );
		add_filter( 'as3cf_update_attached_file', array( $this, 'enable_media_replace_update_attached_file' ), 10, 2 );
		add_filter( 'get_attached_file', array( $this, 'enable_media_replace_pre_replace_store_info' ), 11, 2 );
		add_filter( 'as3cf_pre_update_attachment_metadata', array( $this, 'enable_media_replace_update_attachment_metadata' ), 10, 4 );
		$this->enable_media_replace_download_file_wrapper();

		load_plugin_textdomain( 'as3cf-enable-media-replace', false, dirname( plugin_basename( $plugin_file_path ) ) . '/languages/' );
	}

	/**
	 * Until Enable Media Replace plugin implements the PR so we can use get_attached_file()
	 * we need to determine if we need to copy the local file back a different way
	 *
	 * https://github.com/mansj/enable-media-replace/pull/15
	 */
	function enable_media_replace_download_file_wrapper() {
		$wp_filter = $GLOBALS['wp_filter'];

		if ( ! empty( $wp_filter['emr_unfiltered_get_attached_file'] ) ) {
			// once the EMR PR has been merged we can move the following filter hook to the construct
			// and remove this whole method including doing_enable_media_replace()
			add_filter( 'emr_unfiltered_get_attached_file', '__return_false' );
		} else {
			add_action( 'admin_init', array( $this, 'doing_enable_media_replace' ) );
		}
	}

	/**
	 * A temporary alternative way to determine if the EMR is processing so we can copy back the S3 file
	 * to the server if the local file does not exist.
	 *
	 */
	function doing_enable_media_replace() {
		if ( $this->as3cf->plugin_compat->is_ajax() ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'media_replace_upload' == sanitize_key( $_GET['action'] ) ) {
			$attachment_id = (int) $_POST['ID'];
			// Manually call get_attached_file() so our filter hooks are triggered,
			// as EMR runs the method with filters suppressed
			get_attached_file( $attachment_id );
		}
	}

	/**
	 * Allow the Enable Media Replace plugin to copy the S3 file back to the local
	 * server when the file is missing on the server via get_attached_file()
	 *
	 * @param string $url
	 * @param string $file
	 * @param int    $attachment_id
	 * @param array  $s3_object
	 *
	 * @return string
	 */
	function enable_media_replace_download_file( $url, $file, $attachment_id, $s3_object ) {
		return $this->as3cf->plugin_compat->copy_image_to_server_on_action( 'media_replace_upload', false, $url, $file, $s3_object );
	}

	/**
	 * Allow the Enable Media Replace plugin to remove old images from S3 when performing a replace
	 *
	 * @param bool  $pre
	 * @param array $data
	 * @param int   $post_id
	 * @param array $s3object
	 *
	 * @return bool
	 */
	function enable_media_replace_update_attachment_metadata( $pre, $data, $post_id, $s3object = array() ) {
		if ( isset( $_GET['action'] ) && 'media_replace_upload' == sanitize_key( $_GET['action'] ) ) { // input var okay
			$this->as3cf->remove_attachment_files_from_s3( $post_id, $s3object );

			// abort the rest of the update_attachment_metadata hook,
			// as we will process via update_attached_file
			return true;
		}

		return $pre;
	}

	/**
	 * Store the original file path and meta for an attachment that is being replaced,
	 * so we can use it later for find and replace of S3 URLs
	 *
	 * EMR: Replace the file, use new file name and update all links
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 *
	 * @return string
	 */
	function enable_media_replace_pre_replace_store_info( $file, $attachment_id ) {
		if ( ! isset( $_GET['action'] ) || 'media_replace_upload' !== sanitize_key( $_GET['action'] ) ) { // input var okay
			return $file;
		}

		if ( ! isset( $_POST['replace_type'] ) || 'replace_and_search' !== sanitize_key( $_POST['replace_type'] ) ) { // input var okay
			return $file;
		}

		if ( ! $this->as3cf->get_attachment_s3_info( $attachment_id ) ) {
			return $file;
		}

		// get existing file path before replacing
		$old_file_path = get_attached_file( $attachment_id, true );
		// get existing attachment meta before replacing
		$old_meta = wp_get_attachment_metadata( $attachment_id, true );

		update_post_meta( $attachment_id, 'wpos3_old_file_path', $old_file_path );
		update_post_meta( $attachment_id, 'wpos3_old_meta', $old_meta );

		return $file;
	}

	/**
	 * Allow the Enable Media Replace to use update_attached_file() so it can
	 * replace the file on S3 and do S3 URL replacement in content where needed.
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 *
	 * @return string
	 */
	function enable_media_replace_update_attached_file( $file, $attachment_id ) {
		if ( isset( $_GET['action'] ) && 'media_replace_upload' == sanitize_key( $_GET['action'] ) ) { // input var okay
			// get existing S3 URL before we do the replacing
			$old_url = $this->as3cf->get_attachment_url( $attachment_id );

			// upload attachment to S3
			$this->as3cf->upload_attachment_to_s3( $attachment_id, null, $file );

			// if we are doing replace and search
			if ( isset( $_POST['replace_type'] ) && 'replace_and_search' == sanitize_key( $_POST['replace_type'] ) ) {
				// get old data
				$old_file_path = get_post_meta( $attachment_id, 'wpos3_old_file_path', true );
				$old_meta      = get_post_meta( $attachment_id, 'wpos3_old_meta', true );

				// get new data
				$file_path = get_attached_file( $attachment_id, true );
				$meta      = wp_get_attachment_metadata( $attachment_id, true );
				$new_url   = $this->as3cf->get_attachment_url( $attachment_id );

				// do replaces in content
				$this->as3cf->find_and_replace_urls( $file_path, $old_url, $new_url, $meta, $old_file_path, $old_meta );

				// clean up cached info
				delete_post_meta( $attachment_id, 'wpos3_old_file_path' );
				delete_post_meta( $attachment_id, 'wpos3_old_meta' );
			}
		}

		return $file;
	}
}