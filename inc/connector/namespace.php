<?php

namespace HM\Cavalcade\Plugin\Connector;

use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;

/**
 * Register hooks for WordPress.
 */
function bootstrap() {
	add_filter( 'pre_update_option_cron', __NAMESPACE__ . '\\update_cron_array', 10, 2 );
	add_filter( 'pre_option_cron',        __NAMESPACE__ . '\\get_cron_array' );
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
	$job->start = $job->nextrun = $event->timestamp;
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
	$job->start = $job->nextrun = $event->timestamp;
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
