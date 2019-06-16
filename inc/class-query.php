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
}
