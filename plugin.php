<?php
/**
 * Cavalcade!
 */

namespace HM\Cavalcade\Plugin;

use WP_CLI;

const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';

require __DIR__ . '/inc/namespace.php';
require __DIR__ . '/inc/class-job.php';
require __DIR__ . '/inc/connector/namespace.php';

add_action( 'plugins_loaded',         __NAMESPACE__ . '\\bootstrap' );
add_action( 'plugins_loaded',         __NAMESPACE__ . '\\register_cli_commands' );
add_action( 'plugins_loaded',         __NAMESPACE__ . '\\Connector\\bootstrap' );

// Register cache groups as early as possible, as some plugins may use cron functions before plugins_loaded
if ( function_exists( 'wp_cache_add_global_groups' ) ) {
	register_cache_groups();
}
