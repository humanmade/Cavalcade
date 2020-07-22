<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\Connector;

use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;

/**
 * Register hooks for WordPress.
 */
function bootstrap() {
	add_filter( 'pre_update_option_cron', __NAMESPACE__ . '\\update_cron_array', 10, 2 );
	add_filter( 'pre_option_cron', __NAMESPACE__ . '\\get_cron_array' );

	// Filters introduced in WP 5.1.
	add_filter( 'pre_schedule_event', __NAMESPACE__ . '\\pre_schedule_event', 10, 2 );
	add_filter( 'pre_reschedule_event', __NAMESPACE__ . '\\pre_reschedule_event', 10, 2 );
	add_filter( 'pre_unschedule_event', __NAMESPACE__ . '\\pre_unschedule_event', 10, 4 );
	add_filter( 'pre_clear_scheduled_hook', __NAMESPACE__ . '\\pre_clear_scheduled_hook', 10, 3 );
	add_filter( 'pre_unschedule_hook', __NAMESPACE__ . '\\pre_unschedule_hook', 10, 2 );
	add_filter( 'pre_get_scheduled_event', __NAMESPACE__ . '\\pre_get_scheduled_event', 10, 4 );
	add_filter( 'pre_get_ready_cron_jobs', __NAMESPACE__ . '\\pre_get_ready_cron_jobs' );
}

/**
 * Schedule an event with Cavalcade.
 *
 * @param null|bool $pre   Value to return instead. Default null to continue adding the event.
 * @param stdClass  $event {
 *     An object containing an event's data.
 *
 *     @type string       $hook      Action hook to execute when the event is run.
 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
 *     @type string|false $schedule  How often the event should subsequently recur.
 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
 * }
 * @return null|bool True if event successfully scheduled. False for failure.
 */
function pre_schedule_event( $pre, $event ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	// First check if the job exists already.
	$query = [
		'hook' => $event->hook,
		'timestamp' => $event->timestamp,
		'args' => $event->args,
	];

	if ( $event->schedule === false ) {
		// Search ten minute range to test for duplicate events.
		if ( $event->timestamp < time() + 10 * MINUTE_IN_SECONDS ) {
			$min_timestamp = 0;
		} else {
			$min_timestamp = $event->timestamp - 10 * MINUTE_IN_SECONDS;
		}

		if ( $event->timestamp < time() ) {
			$max_timestamp = time() + 10 * MINUTE_IN_SECONDS;
		} else {
			$max_timestamp = $event->timestamp + 10 * MINUTE_IN_SECONDS;
		}

		$query['timestamp'] = [
			$min_timestamp,
			$max_timestamp,
		];
	}

	$jobs = Job::get_jobs_by_query( $query );
	if ( is_wp_error( $jobs ) ) {
		return false;
	}

	// The job does not exist.
	if ( empty( $jobs ) ) {
		/** This filter is documented in wordpress/wp-includes/cron.php */
		$event = apply_filters( 'schedule_event', $event );

		// A plugin disallowed this event.
		if ( ! $event ) {
			return false;
		}

		schedule_event( $event );
		return true;
	}

	// The job exists.
	$existing = $jobs[0];

	$schedule_match = Cavalcade\get_database_version() >= 2 && $existing->schedule === $event->schedule;

	if ( $schedule_match && $existing->interval === null && ! isset( $event->interval ) ) {
		// Unchanged or duplicate single event.
		return false;
	} elseif ( $schedule_match && $existing->interval === $event->interval ) {
		// Unchanged recurring event.
		return false;
	} else {
		// Event has changed. Update it.
		if ( Cavalcade\get_database_version() >= 2 ) {
			$existing->schedule = $event->schedule;
		}
		if ( isset( $event->interval ) ) {
			$existing->interval = $event->interval;
		} else {
			$existing->interval = null;
		}
		$existing->save();
		return true;
	}
}

/**
 * Reschedules a recurring event.
 *
 * Note: The Cavalcade reschedule behaviour is intentionally different to WordPress's.
 * To avoid drift of cron schedules, Cavalcade adds the interval to the next scheduled
 * run time without checking if this time is in the past.
 *
 * To ensure the next run time is in the future, it is recommended you delete and reschedule
 * a job.
 *
 * @param null|bool $pre   Value to return instead. Default null to continue adding the event.
 * @param stdClass  $event {
 *     An object containing an event's data.
 *
 *     @type string       $hook      Action hook to execute when the event is run.
 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
 *     @type string|false $schedule  How often the event should subsequently recur.
 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
 * }
 * @return bool True if event successfully rescheduled. False for failure.
 */
function pre_reschedule_event( $pre, $event ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	// First check if the job exists already.
	$jobs = Job::get_jobs_by_query( [
		'hook' => $event->hook,
		'timestamp' => $event->timestamp,
		'args' => $event->args,
	] );

	if ( is_wp_error( $jobs ) || empty( $jobs ) ) {
		// The job does not exist.
		return false;
	}

	$job = $jobs[0];

	// Now we assume something is wrong (single job?) and fail to reschedule
	if ( 0 === $event->interval && 0 === $job->interval ) {
		return false;
	}

	$job->nextrun = $job->nextrun + $event->interval;
	$job->interval = $event->interval;
	$job->schedule = $event->schedule;
	$job->save();

	// Rescheduled.
	return true;
}

/**
 * Unschedule a previously scheduled event.
 *
 * The $timestamp and $hook parameters are required so that the event can be
 * identified.
 *
 * @param null|bool $pre       Value to return instead. Default null to continue unscheduling the event.
 * @param int       $timestamp Timestamp for when to run the event.
 * @param string    $hook      Action hook, the execution of which will be unscheduled.
 * @param array     $args      Arguments to pass to the hook's callback function.
 * @return null|bool True if event successfully unscheduled. False for failure.
 */
function pre_unschedule_event( $pre, $timestamp, $hook, $args ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	// First check if the job exists already.
	$jobs = Job::get_jobs_by_query( [
		'hook' => $hook,
		'timestamp' => $timestamp,
		'args' => $args,
	] );

	if ( is_wp_error( $jobs ) || empty( $jobs ) ) {
		// The job does not exist.
		return false;
	}

	$job = $jobs[0];

	// Delete it.
	$job->delete();

	return true;
}

/**
 * Unschedules all events attached to the hook with the specified arguments.
 *
 * Warning: This function may return Boolean FALSE, but may also return a non-Boolean
 * value which evaluates to FALSE. For information about casting to booleans see the
 * {@link https://php.net/manual/en/language.types.boolean.php PHP documentation}. Use
 * the `===` operator for testing the return value of this function.
 *
 * @param null|array $pre  Value to return instead. Default null to continue unscheduling the event.
 * @param string     $hook Action hook, the execution of which will be unscheduled.
 * @param array|null $args Arguments to pass to the hook's callback function, null to clear all
 *                         events regardless of arugments.
 * @return bool|int  On success an integer indicating number of events unscheduled (0 indicates no
 *                   events were registered with the hook and arguments combination), false if
 *                   unscheduling one or more events fail.
*/
function pre_clear_scheduled_hook( $pre, $hook, $args ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	// First check if the job exists already.
	$jobs = Job::get_jobs_by_query( [
		'hook' => $hook,
		'args' => $args,
		'limit' => 100,
		'__raw' => true,
	] );

	if ( is_wp_error( $jobs ) ) {
		return false;
	}

	if ( empty( $jobs ) ) {
		return 0;
	}

	$ids = wp_list_pluck( $jobs, 'id' );

	global $wpdb;

	// Clear all scheduled events for this site
	$table = Job::get_table();

	$sql = "DELETE FROM `{$table}` WHERE site = %d";
	$sql_params[] = get_current_blog_id();

	$sql .= ' AND id IN(' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
	$sql_params = array_merge( $sql_params, $ids );

	$query = $wpdb->prepare( $sql, $sql_params );
	$results = $wpdb->query( $query );

	// Flush the caches.
	Job::flush_query_cache();
	foreach ( $ids as $id ) {
		wp_cache_delete( "job::{$id}", 'cavalcade-jobs' );
	}

	return $results;
}

/**
 * Unschedules all events attached to the hook.
 *
 * Can be useful for plugins when deactivating to clean up the cron queue.
 *
 * Warning: This function may return Boolean FALSE, but may also return a non-Boolean
 * value which evaluates to FALSE. For information about casting to booleans see the
 * {@link https://php.net/manual/en/language.types.boolean.php PHP documentation}. Use
 * the `===` operator for testing the return value of this function.
 *
 * @param null|array $pre  Value to return instead. Default null to continue unscheduling the hook.
 * @param string     $hook Action hook, the execution of which will be unscheduled.
 * @return bool|int On success an integer indicating number of events unscheduled (0 indicates no
 *                  events were registered on the hook), false if unscheduling fails.
 */
function pre_unschedule_hook( $pre, $hook ) {
	return pre_clear_scheduled_hook( $pre, $hook, null );
}

/**
 * Retrieve a scheduled event.
 *
 * Retrieve the full event object for a given event, if no timestamp is specified the next
 * scheduled event is returned.
 *
 * @param null|bool $pre       Value to return instead. Default null to continue retrieving the event.
 * @param string    $hook      Action hook of the event.
 * @param array     $args      Array containing each separate argument to pass to the hook's callback function.
 *                             Although not passed to a callback, these arguments are used to uniquely identify the
 *                             event.
 * @param int|null  $timestamp Unix timestamp (UTC) of the event. Null to retrieve next scheduled event.
 * @return bool|object The event object. False if the event does not exist.
 */
function pre_get_scheduled_event( $pre, $hook, $args, $timestamp ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	$jobs = Job::get_jobs_by_query( [
		'hook' => $hook,
		'timestamp' => $timestamp,
		'args' => $args,
	] );

	if ( is_wp_error( $jobs ) || empty( $jobs ) ) {
		return false;
	}

	$job = $jobs[0];

	$value = (object) [
		'hook'      => $job->hook,
		'timestamp' => $job->nextrun,
		'schedule'  => $job->schedule,
		'args'      => $job->args,
	];

	if ( isset( $job->interval ) ) {
		$value->interval = (int) $job->interval;
	}

	return $value;
}

/**
 * Retrieve cron jobs ready to be run.
 *
 * Returns the results of _get_cron_array() limited to events ready to be run,
 * ie, with a timestamp in the past.
 *
 * @param null|array $pre Array of ready cron tasks to return instead. Default null
 *                        to continue using results from _get_cron_array().
 * @return array Cron jobs ready to be run.
 */
function pre_get_ready_cron_jobs( $pre ) {
	// Allow other filters to do their thing.
	if ( $pre !== null ) {
		return $pre;
	}

	$results = Job::get_jobs_by_query( [
		'timestamp' => 'past',
		'limit' => 100,
	] );
	$crons = [];

	foreach ( $results as $result ) {
		$timestamp = $result->nextrun;
		$hook = $result->hook;
		$key = md5( serialize( $result->args ) );
		$value = [
			'schedule'  => $result->schedule,
			'args'      => $result->args,
			'_job'      => $result,
		];

		if ( isset( $result->interval ) ) {
			$value['interval'] = (int) $result->interval;
		}

		// Build the array up.
		if ( ! isset( $crons[ $timestamp ] ) ) {
			$crons[ $timestamp ] = [];
		}
		if ( ! isset( $crons[ $timestamp ][ $hook ] ) ) {
			$crons[ $timestamp ][ $hook ] = [];
		}
		$crons[ $timestamp ][ $hook ][ $key ] = $value;
	}

	ksort( $crons, SORT_NUMERIC );

	return $crons;
}

/**
 * Schedule an event with Cavalcade
 *
 * Note on return value: Although `false` can be returned to shortcircuit the
 * filter, this causes the calling function to return false. Plugins checking
 * this return value will hence think that the function has failed. Instead, we
 * hijack the save event in {@see update_cron} to simply skip saving to the DB.
 *
 * @param stdClass $event {
 *     @param string $hook Hook to fire
 *     @param int $timestamp
 *     @param array $args
 *     @param string|bool $schedule How often the event should occur (key from {@see wp_get_schedules})
 *     @param int|null $interval Time in seconds between events (derived from `$schedule` value)
 * }
 * @return stdClass Event object passed in (as we aren't hijacking it)
 */
function schedule_event( $event ) {
	global $wpdb;

	if ( ! empty( $event->schedule ) ) {
		return schedule_recurring_event( $event );
	}

	$job = new Job();
	$job->hook = $event->hook;
	$job->site = get_current_blog_id();
	$job->nextrun = $event->timestamp;
	$job->start = $job->nextrun;
	$job->args = $event->args;

	$job->save();
}

function schedule_recurring_event( $event ) {
	global $wpdb;

	$schedules = wp_get_schedules();
	$schedule = $event->schedule;

	$job = new Job();
	$job->hook = $event->hook;
	$job->site = get_current_blog_id();
	$job->nextrun = $event->timestamp;
	$job->start = $job->nextrun;
	$job->interval = $event->interval;
	$job->args = $event->args;

	if ( Cavalcade\get_database_version() >= 2 ) {
		$job->schedule = $event->schedule;
	}

	$job->save();
}

/**
 * Hijack option update call for cron
 *
 * We force this to not save to the database by always returning the old value.
 *
 * @param array $value Cron array to save
 * @param array $old_value Existing value (actually hijacked via {@see get_cron})
 * @return array Existing value, to shortcircuit saving
 */
function update_cron_array( $value, $old_value ) {
	// Ignore the version
	$stored = $old_value;
	unset( $stored['version'] );
	unset( $value['version'] );

	// Massage so we can compare
	$massager = function ( $crons ) {
		$new = [];

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $groups ) {
				foreach ( $groups as $key => $item ) {
					// Workaround for https://core.trac.wordpress.org/ticket/33423
					if ( $timestamp === 'wp_batch_split_terms' ) {
						$timestamp = $hook;
						$hook = 'wp_batch_split_terms';
					}

					$real_key = $timestamp . $hook . $key;

					if ( isset( $item['interval'] ) ) {
						$real_key .= (string) $item['interval'];
					}

					$real_key = sha1( $real_key );
					$new[ $real_key ] = [
						'timestamp' => $timestamp,
						'hook' => $hook,
						'key' => $key,
						'value' => $item,
					];
				}
			}
		}

		return $new;
	};

	$original = $massager( $stored );
	$new = $massager( $value );

	// Any new or changed?
	$added = array_diff_key( $new, $original );
	foreach ( $added as $key => $item ) {
		// Skip new ones, as these are handled in schedule_event/schedule_recurring_event
		if ( isset( $original[ $key ] ) ) {
			// Skip existing events, we handle them below
			continue;
		}

		// Added new event
		$event = (object) [
			'hook'      => $item['hook'],
			'timestamp' => $item['timestamp'],
			'args'      => $item['value']['args'],
		];
		if ( ! empty( $item['value']['schedule'] ) ) {
			$event->schedule = $item['value']['schedule'];
			$event->interval = $item['value']['interval'];
		}

		schedule_event( $event );
	}

	// Any removed?
	$removed = array_diff_key( $original, $new );
	foreach ( $removed as $key => $item ) {
		$job = $item['value']['_job'];

		if ( isset( $new[ $key ] ) ) {
			// Changed events: the only way to change an event without changing
			// its key is to change the schedule or interval
			$job->interval = $new['value']['interval'];
			$job->save();

			continue;
		}

		// Remaining keys are removed values only
		$job->delete();
	}

	// Cancel the DB update
	return $old_value;
}

/**
 * Get cron array.
 *
 * This is constructed based on our database values, rather than being actually
 * stored like this.
 *
 * @param array|boolean $value Value to override with. False by default, truthy if another plugin has already overridden.
 * @return array Overridden cron array.
 */
function get_cron_array( $value ) {
	if ( ! empty( $value ) ) {
		// Something else is trying to filter the value, let it
		return $value;
	}

	// Massage into the correct format
	$crons = [];
	$results = Cavalcade\get_jobs();
	foreach ( $results as $result ) {
		$timestamp = $result->nextrun;
		$hook = $result->hook;
		$key = md5( serialize( $result->args ) );
		$value = [
			'schedule' => $result->schedule,
			'args'     => $result->args,
			'_job'     => $result,
		];

		if ( isset( $result->interval ) ) {
			$value['interval'] = $result->interval;
		}

		// Build the array up, urgh
		if ( ! isset( $crons[ $timestamp ] ) ) {
			$crons[ $timestamp ] = [];
		}
		if ( ! isset( $crons[ $timestamp ][ $hook ] ) ) {
			$crons[ $timestamp ][ $hook ] = [];
		}
		$crons[ $timestamp ][ $hook ][ $key ] = $value;
	}

	ksort( $crons, SORT_NUMERIC );

	// Set the version too
	$crons['version'] = 2;

	return $crons;
}
