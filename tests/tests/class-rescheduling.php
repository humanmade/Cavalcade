<?php
namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;

/**
 * Test rescheduling an event is successful.
 *
 * @ticket 64
 */
class Tests_Rescheduling extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
		// make sure the schedule is clear
		_set_cron_array(array());
	}

	function tearDown() {
		// make sure the schedule is clear
		_set_cron_array(array());
		parent::tearDown();
	}

	function test_recheduling() {
		$timestamp = time() + HOUR_IN_SECONDS;
		$key = md5( serialize( [] ) );

		wp_schedule_event( $timestamp, 'hourly', 'cavalcade_repeat' );
		wp_schedule_event( $timestamp, 'daily', 'cavalcade_repeat' );

		$next_scheduled = _get_cron_array()[ wp_next_scheduled( 'cavalcade_repeat' ) ];
		$expected = wp_get_schedules()['daily']['interval'];
		$actual = $next_scheduled['cavalcade_repeat'][ $key ]['interval'];

		$this->assertEquals( $expected, $actual );
	}
}
