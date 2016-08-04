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

		// Handle SIGTERM calls as we don't want to kill a running job
		pcntl_signal( SIGTERM, SIG_IGN );

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

		$log_table = $wpdb->base_prefix . 'cavalcade_logs';
		$job_table = $wpdb->base_prefix . 'cavalcade_jobs';

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

		$where = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT $log_table.*, $job_table.hook,$job_table.args FROM {$wpdb->base_prefix}cavalcade_logs INNER JOIN $job_table ON $log_table.job = $job_table.id $where";

		if ( $data ) {
			$query = $wpdb->prepare( $query, $data );
		}

		$logs = $wpdb->get_results( $query );

		\WP_CLI\Utils\format_items( $assoc_args['format'], $logs, explode( ',', $assoc_args['fields'] ) );
	}

	/**
	 * Show jobs.
	 *
	 * @synopsis [--format=<format>] [--id=<job-id>] [--site=<site-id>] [--hook=<hook>] [--status=<status>]
	 */
	public function jobs( $args, $assoc_args  ) {

		global $wpdb;

		$assoc_args = wp_parse_args( $assoc_args, array(
			'format'  => 'table',
			'fields'  => 'id,site,hook,start,nextrun,status',
			'id'      => null,
			'site'    => null,
			'hook'    => null,
			'status'  => null,
		));

		$where = array();
		$data  = array();

		if ( $assoc_args['id'] ) {
			$where[] = "id = %d";
			$data[]  = $assoc_args['id'];
		}

		if ( $assoc_args['site'] ) {
			$where[] = "site = %d";
			$data[]  = $assoc_args['site'];
		}

		if ( $assoc_args['hook'] ) {
			$where[] = "hook = %s";
			$data[] = $assoc_args['hook'];
		}

		if ( $assoc_args['status'] ) {
			$where[] = "status = %s";
			$data[] = $assoc_args['status'];
		}

		$where = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs $where";

		if ( $data ) {
			$query = $wpdb->prepare( $query, $data );
		}

		$logs = $wpdb->get_results( $query );

		if ( empty( $logs ) ) {
			\WP_CLI::error( 'No Cavalcade jobs found.' );
		} else {
			\WP_CLI\Utils\format_items( $assoc_args['format'], $logs, explode( ',', $assoc_args['fields'] ) );
		}

	}
}
