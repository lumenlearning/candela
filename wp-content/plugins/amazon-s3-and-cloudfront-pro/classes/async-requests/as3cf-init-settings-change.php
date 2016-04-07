<?php

class AS3CF_Init_Settings_Change extends AS3CF_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'init_settings_change';

	/**
	 * @var AS3CF_Settings_Change_Background_Process
	 */
	protected $settings_change_process;

	/**
	 * Initiate new async request
	 *
	 * @param Amazon_S3_And_CloudFront_Pro $as3cf Instance of calling class
	 */
	public function __construct( $as3cf ) {
		parent::__construct( $as3cf );

		// Settings change background process
		$this->settings_change_process = new AS3CF_Settings_Change_Background_Process( $as3cf );
	}

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		if ( ! isset( $_POST['previous'] ) || ! isset( $_POST['new'] ) ) {
			wp_die();
		}

		$data = $this->determine_total_media();

		$this->settings_change_process->push_to_queue( $data )->save()->dispatch();
	}

	/**
	 * Determine total media
	 *
	 * Calculates the total amount of media items across all blogs.
	 *
	 * @return array
	 */
	function determine_total_media() {
		$blogs = $this->as3cf->get_blogs_data();
		$job   = array();
		$count = 0;

		// Count all uploaded S3 attachments
		foreach ( $blogs as $blog_id => $blog ) {
			$blog_count = $this->as3cf->get_all_s3_attachments( $blog['prefix'], true );
			$count += $blog_count;

			$job['blogs'][ $blog_id ]['prefix']                = $blog['prefix'];
			$job['blogs'][ $blog_id ]['total_attachments']     = $blog_count;
			$job['blogs'][ $blog_id ]['processed_attachments'] = 0;
		}

		// Append to job queue
		$job['total_attachments']     = $count;
		$job['processed_attachments'] = 0;
		$job['previous_settings']     = array_map( 'sanitize_text_field', $_POST['previous'] ); // input var okay
		$job['new_settings']          = array_map( 'sanitize_text_field', $_POST['new'] ); // input var okay

		return $job;
	}

}