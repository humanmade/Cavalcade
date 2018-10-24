<?php

namespace HM\Cavalcade\Plugin;

use WP_Error;

class Job {
	public $id;
	public $site;
	public $hook;
	public $args;
	public $start;
	public $nextrun;
	public $interval;
	public $schedule;
	public $status;

	public function __construct( $id = null ) {
		$this->id = $id;
	}

	/**
	 * Has this job been created yet?
	 *
	 * @return boolean
	 */
	public function is_created() {
		return (bool) $this->id;
	}

	/**
	 * Is this a recurring job?
	 *
	 * @return boolean
	 */
	public function is_recurring() {
		return ! empty( $this->interval );
	}

	public function save() {
		global $wpdb;

		$data = [
			'hook'    => $this->hook,
			'site'    => $this->site,
			'start'   => gmdate( MYSQL_DATE_FORMAT, $this->start ),
			'nextrun' => gmdate( MYSQL_DATE_FORMAT, $this->nextrun ),
			'args'    => serialize( $this->args ),
		];

		if ( $this->is_recurring() ) {
			$data['interval'] = $this->interval;
			if ( get_database_version() >= 2 ) {
				$data['schedule'] = $this->schedule;
			}
		}

		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		if ( $this->is_created() ) {
			$where = [
				'id' => $this->id,
			];
			$result = $wpdb->update( $this->get_table(), $data, $where, $this->row_format( $data ), $this->row_format( $where ) );
		} else {
			$result = $wpdb->insert( $this->get_table(), $data, $this->row_format( $data ) );
			$this->id = $wpdb->insert_id;
		}
	}

	public function delete( $options = [] ) {
		global $wpdb;
		$wpdb->show_errors();

		$defaults = [
			'delete_running' => false,
		];
		$options = wp_parse_args( $options, $defaults );

		if ( $this->status === 'running' && ! $options['delete_running'] ) {
			return new WP_Error( 'cavalcade.job.delete.still_running', __( 'Cannot delete running jobs', 'cavalcade' ) );
		}

		$where = [
			'id' => $this->id,
		];
		$result = $wpdb->delete( $this->get_table(), $where, $this->row_format( $where ) );

		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		return (bool) $result;

	}

	protected static function get_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'cavalcade_jobs';
	}

	/**
	 * Convert row data to Job instance
	 *
	 * @param stdClass $row Raw job data from the database.
	 * @return Job
	 */
	protected static function to_instance( $row ) {
		$job = new Job( $row->id );

		// Populate the object with row values
		$job->site     = $row->site;
		$job->hook     = $row->hook;
		$job->args     = unserialize( $row->args );
		$job->start    = mysql2date( 'G', $row->start );
		$job->nextrun  = mysql2date( 'G', $row->nextrun );
		$job->interval = $row->interval;
		$job->status   = $row->status;

		if ( ! $row->interval ) {
			// One off event.
			$job->schedule = false;
		} elseif ( ! empty( $row->schedule ) ) {
			$job->schedule = $row->schedule;
		} else {
			$job->schedule = get_schedule_by_interval( $row->interval );
		}

		return $job;
	}

	/**
	 * Convert list of data to Job instances
	 *
	 * @param stdClass[] $rows Raw mapping rows
	 * @return Job[]
	 */
	protected static function to_instances( $rows ) {
		return array_map( [ get_called_class(), 'to_instance' ], $rows );
	}

	/**
	 * Get job by job ID
	 *
	 * @param int|Job $job Job ID or instance
	 * @return Job|WP_Error|null Job on success, WP_Error if error occurred, or null if no job found
	 */
	public static function get( $job ) {
		global $wpdb;

		if ( $job instanceof Job ) {
			return $job;
		}

		$job = absint( $job );

		$suppress = $wpdb->suppress_errors();
		$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . static::get_table() . ' WHERE id = %d', $job ) );
		$wpdb->suppress_errors( $suppress );

		if ( ! $job ) {
			return null;
		}

		return static::to_instance( $job );
	}

	/**
	 * Get jobs by site ID
	 *
	 * @param int|stdClass $site Site ID, or site object from {@see get_blog_details}
	 * @param bool $include_completed Should we include completed jobs?
	 * @param bool $include_failed Should we include failed jobs?
	 * @return Job[]|WP_Error Jobs on success, error otherwise.
	 */
	public static function get_by_site( $site, $include_completed = false, $include_failed = false ) {
		global $wpdb;

		// Allow passing a site object in
		if ( is_object( $site ) && isset( $site->blog_id ) ) {
			$site = $site->blog_id;
		}

		if ( ! is_numeric( $site ) ) {
			return new WP_Error( 'cavalcade.job.invalid_site_id' );
		}

		if ( ! $include_completed && ! $include_failed ) {
			$results = wp_cache_get( 'jobs', 'cavalcade-jobs' );
		}

		if ( isset( $results ) && ! $results ) {
			$statuses = [ 'waiting', 'running' ];
			if ( $include_completed ) {
				$statuses[] = 'completed';
			}
			if ( $include_failed ) {
				$statuses[] = 'failed';
			}

			// Find all scheduled events for this site
			$table = static::get_table();

			$sql = "SELECT * FROM `{$table}` WHERE site = %d";
			$sql .= ' AND status IN(' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$query = $wpdb->prepare( $sql, array_merge( [ $site ], $statuses ) );
			$results = $wpdb->get_results( $query );

			if ( ! $include_completed && ! $include_failed ) {
				wp_cache_set( 'jobs', $results, 'cavalcade-jobs' );
			}
		}

		if ( empty( $results ) ) {
			return [];
		}

		return static::to_instances( $results );
	}

	/**
	 * Get the (printf-style) format for a given column.
	 *
	 * @param string $column Column to retrieve format for.
	 * @return string Format specifier. Defaults to '%s'
	 */
	protected static function column_format( $column ) {
		$columns = [
			'id'   => '%d',
			'site' => '%d',
			'hook' => '%s',
			'args' => '%s',
			'start' => '%s',
			'nextrun' => '%s',
			'interval' => '%d',
			'schedule' => '%s',
			'status' => '%s',
		];

		if ( isset( $columns[ $column ] ) ) {
			return $columns[ $column ];
		}

		return '%s';
	}

	/**
	 * Get the (printf-style) formats for an entire row.
	 *
	 * @param array $row Map of field to value.
	 * @return array List of formats for fields in the row. Order matches the input order.
	 */
	protected static function row_format( $row ) {
		$format = [];
		foreach ( $row as $field => $value ) {
			$format[] = static::column_format( $field );
		}
		return $format;
	}
}
