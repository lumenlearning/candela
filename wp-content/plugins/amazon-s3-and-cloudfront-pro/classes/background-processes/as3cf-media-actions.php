<?php

class AS3CF_Media_Actions extends AS3CF_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'media_actions';

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

		$this->as3cf->find_and_replace_attachment_urls( $item['attachment_id'], $item['upload'] );

		// Remove local file
		if ( 'copy' === $item['action'] && $this->as3cf->get_setting( 'remove-local-file' ) ) {
			$paths = $this->as3cf->get_attachment_file_paths( $item['attachment_id'] );

			$this->as3cf->remove_local_files( $paths );
		}

		// Remove file from S3
		if ( 'remove' === $item['action'] ) {
			$this->as3cf->delete_attachment( $item['attachment_id'], true );
		}

		$this->as3cf->restore_current_blog( $item['blog_id'] );

		return false; // Remove from queue
	}

}