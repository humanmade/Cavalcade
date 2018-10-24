<?php

namespace HM\Cavalcade\Plugin\Upgrade;

use HM\Cavalcade\Plugin as Cavalcade;
use const HM\Cavalcade\Plugin\DATABASE_VERSION;

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

	update_site_option( 'cavalcade_db_version', DATABASE_VERSION );

	wp_cache_delete( 'jobs', 'cavalcade-jobs' );

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
