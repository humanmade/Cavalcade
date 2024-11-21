<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\Upgrade;

use const HM\Cavalcade\Plugin\DATABASE_VERSION;
use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;
use WP_CLI;

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

	if ( $database_version < 4 ) {
		upgrade_database_4();
	}

	if ( $database_version < 5 ) {
		upgrade_database_5();
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
			  ADD INDEX `hook` (`hook`)";

	$wpdb->query( $query );
}

/**
 * Upgrade Cavalcade database tables to version 4.
 *
 * Remove nextrun index as it negatively affects performance.
 */
function upgrade_database_4() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  DROP INDEX `nextrun`";

	$wpdb->query( $query );
}

/**
 * Upgrade Cavalcade database tables to version 5.
 *
 * Add unique index to prevent duplicate entries being created.
 */
function upgrade_database_5() {
	global $wpdb;

	$queries = [
		// Add generated stored hash column.
		"ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			ADD COLUMN `hash` BINARY(32) GENERATED ALWAYS AS (
			  	UNHEX(SHA2(CONCAT_WS(
					'-', `site`, `hook`, `args`, `nextrun`, `schedule`
				), 256))
			) STORED;",
		// Remove full group by mode requirement if set.
		"SET @sql_mode_tmp = (SELECT @@sql_mode);",
		"SET sql_mode = (SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));",
		// Remove any current duplicates.
		"DELETE t1 FROM `{$wpdb->base_prefix}cavalcade_jobs` t1
			INNER JOIN (
				SELECT MIN(`id`) as `id`, `hash`
				FROM `{$wpdb->base_prefix}cavalcade_jobs`
				GROUP BY `hash`
				HAVING COUNT(*) > 1
			) t2
			ON t1.`hash` = t2.`hash`
				AND t1.id != t2.id;",
		// Add a unique index on the hash column.
		"ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs` ADD UNIQUE INDEX `uniq` (`hash`);",
		// Restore the SQL mode.
		"SET sql_mode = (SELECT @sql_mode_tmp);",
	];

	foreach ( $queries as $query ) {
		$wpdb->query( $query );

		if ( defined( 'WP_CLI' ) && WP_CLI && ! empty( $wpdb->last_error ) ) {
			WP_CLI::error( $wpdb->last_error, false );
		}
	}
}
