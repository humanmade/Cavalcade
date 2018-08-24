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
		// Make the current job id available for hooks run by this job
		define( 'CAVALCADE_JOB_ID', $job->id );

		// Handle SIGTERM calls as we don't want to kill a running job
		pcntl_signal( SIGTERM, SIG_IGN );

		// Set the wp-cron constant for plugin and theme interactions
		defined( 'DOING_CRON' ) or define( 'DOING_CRON', true );

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

		$assoc_args = wp_parse_args( $assoc_args, [
			'format'  => 'table',
			'fields'  => 'job,hook,timestamp,status',
			'hook'    => null,
			'job'     => null,
		]);

		$where = [];
		$data  = [];

		if ( $assoc_args['job'] ) {
			$where[] = 'job = %d';
			$data[]  = $assoc_args['job'];
		}

		if ( $assoc_args['hook'] ) {
			$where[] = 'hook = %s';
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
	 * @synopsis [--format=<format>] [--id=<job-id>] [--site=<site-id>] [--hook=<hook>] [--status=<status>] [--limit=<limit>] [--page=<page>]
	 */
	public function jobs( $args, $assoc_args  ) {

		global $wpdb;

		$assoc_args = wp_parse_args( $assoc_args, [
			'format'  => 'table',
			'fields'  => 'id,site,hook,start,nextrun,status',
			'id'      => null,
			'site'    => null,
			'hook'    => null,
			'status'  => null,
			'limit'   => 20,
			'page'    => 1,
		]);

		$where = [];
		$data  = [];

		if ( $assoc_args['id'] ) {
			$where[] = 'id = %d';
			$data[]  = $assoc_args['id'];
		}

		if ( $assoc_args['site'] ) {
			$where[] = 'site = %d';
			$data[]  = $assoc_args['site'];
		}

		if ( $assoc_args['hook'] ) {
			$where[] = 'hook = %s';
			$data[] = $assoc_args['hook'];
		}

		if ( $assoc_args['status'] ) {
			$where[] = 'status = %s';
			$data[] = $assoc_args['status'];
		}

		$where = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$limit = 'LIMIT %d';
		$data[] = absint( $assoc_args['limit'] );
		$offset = 'OFFSET %d';
		$data[] = absint( ( $assoc_args['page'] - 1 ) * $assoc_args['limit'] );

		$query = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs $where $limit $offset";

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

	/**
	 * Upgrade to the latest database schema.
	 */
	public function upgrade() {
		if ( Upgrade\upgrade_database() ) {
			WP_CLI::success( 'Database version upgraded.' );
			return;
		}

		WP_CLI::success( 'Database upgrade not required.' );
	}
}
