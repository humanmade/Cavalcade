<?php

namespace HM\Cavalcade\Plugin;

use WP_CLI;
use WP_CLI_Command;

class Command extends WP_CLI_Command {
	/**
	 * Run a job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of the job to run.
	 *
	 * @synopsis <id>
	 */
	public function run( $args, $assoc_args ) {
		$job = Job::get( $args[0] );
		if ( empty( $job ) ) {
			WP_CLI::error( 'Invalid job ID' );
		}

		/**
		 * Fires scheduled events.
		 *
		 * @ignore
		 *
		 * @param string $hook Name of the hook that was scheduled to be fired.
		 * @param array  $args The arguments to be passed to the hook.
		 */
		do_action_ref_array( $job->hook, $job->args );

		// Assume it succeeded if we got this far :)
		$job->mark_completed();
	}
}
