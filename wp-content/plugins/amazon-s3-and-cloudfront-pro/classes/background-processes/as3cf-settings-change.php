<?php

class AS3CF_Settings_Change_Background_Process extends AS3CF_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'settings_change';

	/**
	 * @var int
	 */
	protected $total_processed = 0;

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
		$count       = 0;
		$total       = $item['total_attachments'] - $item['processed_attachments'];
		$batch_limit = $this->as3cf->get_batch_limit( 500, 'as3cf_settings_change_batch_limit' );

		// Batch limit should never exceed total items in queue
		if ( $total < $batch_limit ) {
			$batch_limit = $total;
		}

		if ( ! isset( $item['blogs'] ) ) {
			// No blogs left to process
			return false;
		}

		foreach ( $item['blogs'] as $blog_id => $blog ) {
			// Get batch of attachments
			$offset      = $blog['processed_attachments'];
			$limit       = $batch_limit - $count;
			$attachments = $this->as3cf->get_all_s3_attachments( $blog['prefix'], false, $limit, $offset );

			$this->as3cf->switch_to_blog( $blog_id );

			foreach ( $attachments as $attachment ) {
				$this->as3cf->process_find_replace_attachment( $attachment, $item['previous_settings'], $item['new_settings'] );

				$item['blogs'][ $blog_id ]['processed_attachments'] ++;
				$item['processed_attachments'] ++;
				$count ++;

				if ( $this->time_exceeded() || $this->memory_exceeded() ) {
					// Batch limits reached
					break;
				}
			}

			// Remove blog from queue if completed
			$blog_processed = (int) $item['blogs'][ $blog_id ]['processed_attachments'];
			$blog_total     = (int) $item['blogs'][ $blog_id ]['total_attachments'];

			if ( $blog_processed >= $blog_total ) {
				unset( $item['blogs'][ $blog_id ] );
			}

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				// Batch limits reached
				break;
			}
		}

		$this->as3cf->restore_current_blog( $blog_id );

		// Remove job from queue if completed
		$job_processed = (int) $item['processed_attachments'];
		$job_total     = (int) $item['total_attachments'];

		if ( $job_processed >= $job_total ) {
			$this->total_processed = $job_processed;

			return false;
		}

		return $item;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		$count   = number_format_i18n( $this->total_processed );
		$message = __( '<strong>Find & Replace Complete</strong> &mdash; %s URLs have been updated in your content to reflect the new URL settings.', 'as3cf-pro' );

		$this->as3cf->notices->add_notice( sprintf( $message, $count ) );
		$this->as3cf->notices->remove_notice_by_id( 'as3cf-notice-running-find-replace' );
	}

}