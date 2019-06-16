<?php

namespace HM\Cavalcade\Plugin;

use WP_Date_Query;

/**
 * Query the Cavalcade Jobs table.
 */
class Query {
	/**
	 * SQL query clauses.
	 *
	 * @var array
	 */
	protected $sql_clauses = array(
		'select'  => '',
		'from'    => '',
		'where'   => array(),
		'groupby' => '',
		'orderby' => '',
		'limits'  => '',
	);

	/**
	 * Date query container.
	 *
	 * @var object WP_Date_Query
	 */
	public $date_query = false;

	/**
	 * Query vars set by the user.
	 *
	 * @var array
	 */
	public $query_vars;

	/**
	 * Default values for query vars.
	 *
	 * @var array
	 */
	public $query_var_defaults;

	/**
	 * List of jobs located by the query.
	 *
	 * @var array
	 */
	public $jobs;

	/**
	 * The number of found jobs for the current query.
	 *
	 * @var int
	 */
	public $found_jobs = 0;

	/**
	 * Set up the Job query, based on the parameters passed.
	 *
	 * @param string|array $query {
	 *     Optional. Array or query string of site query parameters. Default empty.
	 *
	 *     @type string   $fields   Fields to return, accepts `all` or `ids`. Default: `all`.
	 *     @type int[]    $job_ids  Specific Job IDs to search for. Default: all matching jobs.
	 *     @type int      $site_id  The site on which jobs run. Required.
	 *                              Default current site.
	 *     @type string   $hook     The job's hook name.
	 *     @type array    $args     The job's arguments array. Default: [].
	 *     @type array    $nextrun  Date query to limit jobs by. Default: future
	 *                              jobs. See WP_Date_Query.
	 *     @type int      $interval The frequency in seconds jobs run at.
	 *     @type string   $schedule The named schedule on which jobs run.
	 *     @type string[] $status   Array of statuses to search for, accepts
	 *                              `waiting` (default), `failed`, `completed` or `running`.
	 * }
	 */
	function __construct( $query = [] ) {
		$this->query_var_defaults = [
			'fields' => 'all',
			'job_ids' => null,
			'site_id' => get_current_blog_id(),
			'hook' => null,
			'args' => [],
			// See WP_Date_Query
			'nextrun' => [
				[
					'after' => 'now',
					'inclusive' => false,
				],
			],
			'interval' => null,
			'schedule' => null,
			'status' => [
				'waiting',
			],
		];

		$this->query( $query );
	}

	/**
	 * Set up the query for retrieving jobs.
	 *
	 * @param array $query Array of query parameters, see __construct().
	 * @return array An array of Job instances or integers if fields is set to `ids`.
	 */
	function query( $query = [] ) {
		$this->query_vars = wp_parse_args( $query, $this->query_var_defaults );
		return $this->get_jobs();
	}

	/**
	 * Gets jobs based on the query arguments.
	 *
	 * @return array An array of Job instances or integers if fields is set to `ids`.
	 */
	function get_jobs() {
		$job_ids = $this->get_job_ids();

		if ( $this->query_vars === 'ids' ) {
			return $job_ids;
		}
	}

	/**
	 * Get job IDs baseed on the query arguments.
	 *
	 * @return array Array of Job IDs.
	 */
	function get_job_ids() {
		$this->parse_query();
	}

	/**
	 * Parse the passed query arguments with the defaults.
	 *
	 * @return void
	 */
	function parse_query() {
		$query = &$this->query_vars;

		$query['fields'] = strtolower( $query['fields'] );
		if ( ! in_array( $query['fields'], ['ids', 'all' ], true ) ) {
			$query['fields'] = 'all';
		}

		if ( ! empty( $query['jobs'] ) ) {
			$query['jobs'] = array_filter( (array) $query['jobs'], 'is_int' );
		}

		$query['site_id'] = filter_var( $query['site_id'], FILTER_VALIDATE_INT );
		if ( empty( $query['site_id' ] ) ) {
			$query['site_id'] = $this->query_var_defaults['site_id'];
		}
	}
}
