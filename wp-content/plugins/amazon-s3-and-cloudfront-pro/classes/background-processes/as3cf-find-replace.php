<?php

class AS3CF_Find_Replace extends AS3CF_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'find_replace';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$this->as3cf->switch_to_blog( $item['blog_id'] );

		// Perform find and replace
		$this->as3cf->find_and_replace_attachment_urls( $item['attachment_id'] );

		// Remove local files
		if ( $this->as3cf->get_setting( 'remove-local-file' ) ) {
			$paths = $this->as3cf->get_attachment_file_paths( $item['attachment_id'] );

			if ( ! empty( $paths ) ) {
				$this->as3cf->remove_local_files( $paths );
			}
		}

		$this->as3cf->restore_current_blog( $item['blog_id'] );

		return false; // Remove from queue
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		$this->as3cf->notices->add_notice( __( '<strong>Find & Replace Complete</strong> &mdash; Media items within your content have been updated to use the S3 URLs.', 'as3cf-pro' ) );
	}

}