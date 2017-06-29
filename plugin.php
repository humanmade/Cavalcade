<?php
/**
 * Cavalcade!
 */

namespace HM\Cavalcade\Plugin;

use WP_CLI;

const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';

add_action( 'plugins_loaded',         __NAMESPACE__ . '\\bootstrap' );
add_action( 'plugins_loaded',         __NAMESPACE__ . '\\register_cli_commands' );

require __DIR__ . '/inc/class-job.php';
require __DIR__ . '/inc/connector/namespace.php';

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
	wp_cache_add_global_groups( array( 'cavalcade' ) );
	wp_cache_add_non_persistent_groups( array( 'cavalcade-jobs' ) );
}

// Register cache groups as early as possible, as some plugins may use cron functions before plugins_loaded
if ( function_exists( 'wp_cache_add_global_groups' ) ) {
	register_cache_groups();
}

/**
 * Register the WP-CLI command
 */
function register_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	require __DIR__ . '/inc/class-command.php';
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
