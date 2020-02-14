<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\Upgrade;

use const HM\Cavalcade\Plugin\DATABASE_VERSION;
use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;

/**
 * Update the Cavalcade database version if required.
 *
 * Checks the Cavalcade database version and runs the
 * upgrade routines as required.
 *
 * @return bool False if upgrade not required, true if run.
 */
function upgrade_database() {
	$database_version = (int) get_site_option( 'cavalcade_db_version' );

	if ( $database_version === DATABASE_VERSION ) {
		// No upgrade required.
		return false;
	}

	if ( $database_version < 2 ) {
		upgrade_database_2();
	}

	if ( $database_version < 3 ) {
		upgrade_database_3();
	}

	update_site_option( 'cavalcade_db_version', DATABASE_VERSION );

	Job::flush_query_cache();

	// Upgrade successful.
	return true;
}

/**
 * Upgrade Cavalcade database tables to version 2.
 *
 * Add and populate the `schedule` column in the jobs table.
 */
function upgrade_database_2() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD `schedule` varchar(255) DEFAULT NULL";

	$wpdb->query( $query );

	$schedules = Cavalcade\get_schedules_by_interval();

	foreach ( $schedules as $interval => $name ) {
		$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
				  SET `schedule` = %s
				  WHERE `interval` = %d
				  AND `status` NOT IN ( 'failed', 'completed' )";

		$wpdb->query(
			$wpdb->prepare( $query, $name, $interval )
		);
	}
}

/**
 * Upgrade Cavalcade database tables to version 3.
 *
 * Add indexes required for pre-flight filters.
 */
function upgrade_database_3() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD INDEX `site` (`site`),
			  ADD INDEX `hook` (`hook`),
			  ADD INDEX `nextrun` (`nextrun`)";

	$wpdb->query( $query );
}
