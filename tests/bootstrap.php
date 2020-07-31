<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * phpcs:disable PSR1.Files.SideEffects
 *
 * @package WordPress
*/

// Support for:
// 1. `WP_DEVELOP_DIR` environment variable
// 2. Plugin installed inside of WordPress.org developer checkout
// 3. Tests checked out to /tmp
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
}

if ( '1' === getenv( 'WP_MULTISITE' ) ) {
	define( 'MULTISITE', true );
	define( 'WP_TESTS_MULTISITE', true );
}

require $test_root . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require_once dirname( __DIR__ ) . '/plugin.php';
	// Call create tables before each run.
	HM\Cavalcade\Plugin\create_tables();
});

require $test_root . '/includes/bootstrap.php';
