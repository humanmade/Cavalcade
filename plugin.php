<?php
/**
 * Cavalcade!
 */

namespace HM\Cavalcade\Plugin;

use WP_CLI;

const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';

add_action( 'plugins_loaded',         __NAMESPACE__ . '\\bootstrap' );
add_action( 'plugins_loaded',         __NAMESPACE__ . '\\register_cli_commands' );

require __DIR__ . '/class-job.php';
require __DIR__ . '/connector.php';

/**
 * Bootstrap the plugin and get it started!
 */
function bootstrap() {
	if ( ! is_installed() ) {
		create_table();
	}
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

	return (bool) $wpdb->query( "SHOW TABLES LIKE '{$wpdb->base_prefix}cavalcade_jobs'" );
}

function create_table() {
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
