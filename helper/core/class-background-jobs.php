<?php
/**
 * Handle podcast player background and cron jobs.
 * 
 * Part of this code is inspired by https://deliciousbrains.com/background-processing-wordpress/
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Core;

/**
 * Handle background jobs to import episodes, download images and update feeds.
 *
 * @since 7.4.0
 *
 * @package Podcast_Player
 */
class Background_Jobs extends Singleton {

	/**
	 * Holds name of the pending actions queue.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $identifier = 'podcast_player_bg_jobs';

	/**
	 * Get the name of the queue.
	 *
	 * @since 7.4.0
	 *
	 * @return string
	 */
	public static function init() {
		$instance = self::get_instance();
		add_action( 'wp_ajax_' . $instance->identifier, array( $instance, 'maybe_handle' ) );
		add_action( 'wp_ajax_nopriv_' . $instance->identifier, array( $instance, 'maybe_handle' ) );
		add_action( 'shutdown', array( $instance, 'dispatch' ) );
	}

	/**
	 * Add task to the queue.
	 *
	 * @since 7.4.0
	 *
	 * @param string $identifier Unique identifier for the task, e.g., a feed URL or key.
     * @param string $task_type  Type of task to be performed, e.g., 'download_image', 'import_episodes'.
     * @param array  $data       Data required to perform the task.
     * @param int    $priority   Task priority, used for sorting tasks. Defaults to 10.
	 */
	public static function add_task( $identifier, $task_type, $data, $priority = 10 ) {
		$instance   = self::get_instance();
		$identifier = wp_http_validate_url( $identifier ) ? md5( $identifier ) : $identifier;
		$unique_id  = substr( md5( $identifier . $task_type ), 0, 12 );
		$queue      = $instance->get_tasks_queue( false );
		$task_data  = array(
			'identifier' => $identifier,
			'type'       => $task_type,
			'data'       => $data,
			'priority'   => $priority,
			'attempts'   => 0,
		);
		if ( isset( $queue[ $unique_id ] ) ) {
			$instance->maybe_add_repeating_task_to_queue( $unique_id, $queue, $task_data, $task_type );
		} else {
			$queue[ $unique_id ] = $task_data;
			$instance->set_tasks_queue( $queue );
		}
        return $instance;
    }

	/**
	 * Dispatch an async request to start processing the queue.
	 *
	 * @since 7.4.0
	 */
	public function dispatch() {
		$instance = self::get_instance();
		if ( $this->is_processing() || $this->is_queue_empty() ) {
			return;
		}

		$url  = add_query_arg( $instance->get_query_args(), $instance->get_query_url() );
		$args = $instance->get_post_args();

		return wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Conditionally add repeating task to the queue.
	 *
	 * @since 7.4.0
	 *
	 * @param string $unique_id Unique ID of the task
	 * @param array  $queue     Queue of tasks
	 * @param array  $task_data Task data
	 * @param string $task_type Task type
	 */
	private function maybe_add_repeating_task_to_queue( $unique_id, $queue, $task_data, $task_type ) {
		if ( in_array( $task_type, array( 'download_image', 'import_episodes' ), true ) ) {
			$prev_task = $queue[ $unique_id ];
			$prev_data = $prev_task['data'];
			$new_data  = array_merge( $prev_data, $task_data['data'] );
			$queue[ $unique_id ] = array_merge( $prev_task, array( 'data' => $new_data ) );
		} else {
			$queue[ $unique_id ] = $task_data;
		}
		$this->set_tasks_queue( $queue );
	}

	/**
	 * Maybe handle a dispatched request.
	 *
	 * Check for correct nonce and pass to handler.
	 *
	 * @since 7.4.0
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing.
		session_write_close();

		if ( ! headers_sent() ) {
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		}

		if ( PHP_VERSION_ID >= 70016 && function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		check_ajax_referer( $this->identifier, 'nonce' );

		if ( $this->is_processing() || $this->is_queue_empty() ) {
			wp_die();
		}

		if ( $this->memory_exceeded() ) {
			// Run it after a few seconds.
			sleep( 5 );
			$this->dispatch();
			wp_die();
		}

		$this->handle();
		wp_die();
	}

	/**
	 * Handle a dispatched request.
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 */
	private function handle() {
		$this->lock_process();
		$queue           = $this->get_tasks_queue();
		$current_task_id = array_key_first( $queue );
		$current_task    = $queue[ $current_task_id ];
		$error           = false;

		try {
			list( $status, $data ) = apply_filters(
				"podcast_player_bg_task_{$current_task['type']}",
				array( false, false ),
				$current_task
			);

			if ( $status ) {
				if ( $status instanceof \WP_Error ) {
					$error = $status->get_error_message();
				} elseif ( $data instanceof \WP_Error ) {
					$error = $data->get_error_message();
				} else {
					$this->update_tasks_queue( $current_task_id, $data );
				}
			}
		} catch ( \Exception $e ) {
			$error = $e->getMessage();
		}

		if ( $error ) {
			$current_task['attempts'] = $current_task['attempts'] + 1;
			$new_queue                = $this->get_tasks_queue();
			if ( $current_task['attempts'] < 3 ) {
				$new_queue[ $current_task_id ] = $current_task;
			} else {
				unset( $new_queue[ $current_task_id ] );

				// If error is in image download. Let's disable image download to prevent infinite loop.
				if ( 'download_image' === $current_task['type'] ) {
					$options = get_option( 'pp-common-options', array() );
					$options['img_save'] = 'no';
					update_option( 'pp-common-options', $options );
				}
			}
			$this->set_tasks_queue( $new_queue );
			$this->log_error( $error, $current_task['data'] );
		}

		// Let the server sleep for a few seconds.
		sleep( 5 );
		$this->unlock_process();
	}

	/**
	 * Get pending tasks from the queue.
	 *
	 * @since 7.4.0
	 *
	 * @param bool $sorted If tasks should be sorted.
	 */
	private function get_tasks_queue( $sorted = true ) {
		$queue = get_option( $this->identifier );
		$queue = ! empty( $queue ) ? $queue : array();

		// Sort tasks by priority.
		if ( $sorted ) {
			uasort( $queue, function( $a, $b ) {
				$a_priority = isset( $a['priority'] ) ? $a['priority'] : 0;
				$b_priority = isset( $b['priority'] ) ? $b['priority'] : 0;
				return $a_priority > $b_priority ? 1 : -1;
			});
		}

		return $queue;
	}

	/**
	 * Update tasks queue.
	 *
	 * @since 7.4.0
	 *
	 * @param int   $task_id Id of the task.
	 * @param array $data    Data to update the task.
	 */
	private function update_tasks_queue( $task_id, $data ) {
		$queue = $this->get_tasks_queue();
		if ( ! isset( $queue[ $task_id ] ) ) {
			return;
		}
		$task_args = $queue[ $task_id ];
		$type      = $task_args['type'];
		if ( in_array( $type, array( 'download_image', 'import_episodes' ), true ) ) {
			$prev_data = $task_args['data'];
			if ( 'download_image' === $type ) {
				$new_data  = array_diff_key( $prev_data, $data );
			} else {
				$new_data = array_diff( $prev_data, $data );
			}

			if ( empty( $new_data ) ) {
				unset( $queue[ $task_id ] );
			} else {
				$queue[ $task_id ]['data'] = $new_data;
			}
		} else {
			unset( $queue[ $task_id ] );
		}
		$this->set_tasks_queue( $queue );
	}

	/**
	 * Set pending tasks to the queue.
	 *
	 * @since 7.4.0
	 *
	 * @param array $tasks
	 */
	private function set_tasks_queue( $tasks ) {
		update_option( $this->identifier, $tasks, 'no' );
	}

	/**
	 * Get query args.
	 *
	 * @return array
	 */
	private function get_query_args() {
		return apply_filters( $this->identifier . '_query_args', array(
			'action' => $this->identifier,
			'nonce'  => wp_create_nonce( $this->identifier ),
		) );
	}

	/**
	 * Get query URL.
	 *
	 * @return string
	 */
	private function get_query_url() {
		return apply_filters( $this->identifier . '_query_url', admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Get post args.
	 *
	 * @return array
	 */
	private function get_post_args() {
		return apply_filters( $this->identifier . '_post_args', array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => array(),
			'cookies'   => $_COOKIE, // Passing cookies ensures request is performed as initiating user.
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // Local requests, fine to pass false.
		) );
	}

	/**
	 * Is the background process currently running?
	 *
	 * @return bool
	 */
	public function is_processing() {
		if ( get_transient( $this->identifier . '_process_lock' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Is queue empty?
	 *
	 * @return bool
	 */
	private function is_queue_empty() {
		return empty( $this->get_tasks_queue( false ) );
	}

	/**
	 * Memory exceeded?
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	private function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int
	 */
	private function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Lock process.
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 */
	private function lock_process() {
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', 30 );
		set_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
	}

	/**
	 * Unlock process.
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @return $this
	 */
	private function unlock_process() {
		delete_transient( $this->identifier . '_process_lock' );
		return $this;
	}

	/**
	 * Log Error Message.
	 *
	 * @since 7.4.0
	 *
	 * @param string $message Error message.
	 * @param array  $data    Task data.
	 */
	private function log_error( $message, $data ) {
		// Log the error message.
	}
}
