<?php

namespace HM\Cavalcade\Plugin;

use WP_CLI;

/**
 * Bootstrap the plugin and get it started!
 */
function bootstrap() {
	register_cache_groups();

	if ( ! is_installed() ) {
		create_tables();
	}
}

/**
 * Register the cache groups
 */
function register_cache_groups() {
	wp_cache_add_global_groups( [ 'cavalcade' ] );
	wp_cache_add_non_persistent_groups( [ 'cavalcade-jobs' ] );
}

/**
 * Register the WP-CLI command
 */
function register_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	require __DIR__ . '/class-command.php';
	WP_CLI::add_command( 'cavalcade', __NAMESPACE__ . '\\Command' );
}

/**
 * Is the plugin installed?
 *
 * Used during the plugin's bootstrapping process to create the table. This
 * should return true pretty much all the time.
 *
 * @return boolean
 */
function is_installed() {
	global $wpdb;

	if ( wp_cache_get( 'installed', 'cavalcade' ) ) {
		return true;
	}

	$installed = ( count( $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->base_prefix}cavalcade_%'" ) ) === 2 );

	if ( $installed ) {
		// Don't check again :)
		wp_cache_set( 'installed', $installed, 'cavalcade' );
	}

	return $installed;
}

function create_tables() {
	global $wpdb;
	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}cavalcade_jobs` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`site` bigint(20) unsigned NOT NULL,

		`hook` varchar(255) NOT NULL,
		`args` longtext NOT NULL,

		`start` datetime NOT NULL,
		`nextrun` datetime NOT NULL,
		`interval` int unsigned DEFAULT NULL,
		`status` varchar(255) NOT NULL DEFAULT 'waiting',
		`schedule` varchar(255) DEFAULT NULL,

		PRIMARY KEY (`id`),
		KEY `status` (`status`)
	) ENGINE=InnoDB;\n";

	// TODO: check return value
	$wpdb->query( $query );

	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}cavalcade_logs` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`job` bigint(20) NOT NULL,
		`status` varchar(255) NOT NULL DEFAULT '',
		`timestamp` datetime NOT NULL,
		`content` longtext NOT NULL,
		PRIMARY KEY (`id`),
		KEY `job` (`job`),
		KEY `status` (`status`)
	) ENGINE=InnoDB;\n";

	$wpdb->query( $query );

	update_site_option( 'cavalcade_db_version', DATABASE_VERSION );
}

/**
 * Get jobs for the specified site.
 *
 * @param int|stdClass $site Site ID or object (from {@see get_blog_details}) to get jobs for. Null for current site.
 * @return Job[] List of jobs on the site.
 */
function get_jobs( $site = null ) {
	global $wpdb;

	if ( empty( $site ) ) {
		$site = get_current_blog_id();
	}

	return Job::get_by_site( $site );
}

/**
 * Get the WP Cron schedule names by interval.
 *
 * This is used as a fallback when Cavalcade does not have the
 * schedule name stored in the database to make a best guest as
 * the schedules name.
 *
 * Interval collisions caused by two plugins registering the same
 * interval with different names are unified into a single name.
 *
 * @return array Cron Schedules indexed by interval.
 */
function get_schedules_by_interval() {
	$schedules = [];

	foreach ( wp_get_schedules() as $name => $schedule ) {
		$schedules[ (int) $schedule['interval'] ] = $name;
	}

	return $schedules;
}

/**
 * Helper function to get a schedule name from a specific interval.
 *
 * @param int $interval Cron schedule interval.
 * @return string Cron schedule name.
 */
function get_schedule_by_interval( $interval = null ) {
	if ( empty( $interval ) ) {
		return '__fake_schedule';
	}

	$schedules = get_schedules_by_interval();

	if ( ! empty ( $schedules[ (int) $interval ] ) ) {
		return $schedules[ (int) $interval ];
	}

	return '__fake_schedule';
}

/**
 * Get the current Cavalcade database schema version.
 *
 * @return int Database schema version.
 */
function get_database_version() {
	$version = (int) get_site_option( 'cavalcade_db_version' );

	// Normalise schema version for unset option.
	if ( $version < 2 ) {
		$version = 1;
	}

	return $version;
}
