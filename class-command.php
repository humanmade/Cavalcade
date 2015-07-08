<?php

namespace HM\Cavalcade\Plugin;

use WP_CLI;
use WP_CLI_Command;

class Command extends WP_CLI_Command {
	/**
	 * Run a job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of the job to run.
	 *
	 * @synopsis <id>
	 */
	public function run( $args, $assoc_args ) {
		$job = Job::get( $args[0] );
		if ( empty( $job ) ) {
			WP_CLI::error( 'Invalid job ID' );
		}

		/**
		 * Fires scheduled events.
		 *
		 * @ignore
		 *
		 * @param string $hook Name of the hook that was scheduled to be fired.
		 * @param array  $args The arguments to be passed to the hook.
		 */
		do_action_ref_array( $job->hook, $job->args );
	}

	/**
	 * Show logs on completed jobs
	 *
	 * @synopsis [--format=<format>] [--fields=<fields>] [--job=<job-id>] [--hook=<hook>]
	 */
	public function log( $args, $assoc_args  ) {

		global $wpdb;

		$log_table = $wpdb->prefix . 'cavalcade_logs';
		$job_table = $wpdb->prefix . 'cavalcade_jobs';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'format'  => 'table',
			'fields'  => 'job,hook,timestamp,status',
			'hook'    => null,
			'job'     => null,
		));

		$where = array();
		$data  = array();

		if ( $assoc_args['job'] ) {
			$where[] = "job = %d";
			$data[]  = $assoc_args['job'];
		}

		if ( $assoc_args['hook'] ) {
			$where[] = "hook = %s";
			$data[] = $assoc_args['hook'];
		}

		$query = "SELECT $log_table.*, $job_table.hook,$job_table.args FROM {$wpdb->prefix}cavalcade_logs INNER JOIN $job_table ON $log_table.job = $job_table.id WHERE " . implode( ' AND ', $where );

		if ( $data ) {
			$query = $wpdb->prepare( $query, $data );
		}

		$logs = $wpdb->get_results( $query );

		\WP_CLI\Utils\format_items( $assoc_args['format'], $logs, explode( ',', $assoc_args['fields'] ) );
	}
}
