<table width="100%">
	<tr>
		<td align="left" width="70">
			<strong>Cavalcade</strong><br />
			A better wp-cron. Horizontally scalable,
			works perfectly with multisite.
		</td>
		<td align="right" width="20%">
			<a href="https://travis-ci.org/humanmade/Cavalcade">
				<img src="https://travis-ci.org/humanmade/Cavalcade.svg?branch=master" alt="Build status">
			</a>
			<a href="http://codecov.io/github/humanmade/Cavalcade?branch=master">
				<img src="http://codecov.io/github/humanmade/Cavalcade/coverage.svg?branch=master" alt="Coverage via codecov.io" />
			</a>
		</td>
	</tr>
	<tr>
		<td>
			A <strong><a href="https://hmn.md/">Human Made</a></strong> project. Maintained by @rmccue.
		</td>
		<td align="center">
			<img src="https://hmn.md/content/themes/hmnmd/assets/images/hm-logo.svg" width="100" />
		</td>
	</tr>
</table>

## What?

Cavalcade is a scalable job system, designed as a drop-in replacement for
WordPress's built-in pseudo-cron system.

![Flowchart of how Cavalcade works](http://i.imgur.com/nyTFDfR.png)

From the WordPress side, none of your code needs to change. Cavalcade
transparently integrates with the existing wp-cron functions to act as a full
replacement. Cavalcade pushes these jobs off into their own database table for
efficient storage.

At the core of Cavalcade is the job runner. The runner is a daemon that
supervises the entire system. The runner constantly checks the database for new
jobs, and is responsible for spawning and managing workers to handle the jobs
when they're ready.

The runner spawns workers, which perform the actual tasks themselves. This is
done by running a special WP-CLI command.

## Why?

### Guaranteed Running

wp-cron is not actually a real task scheduler, and doesn't actually operate like
cron. Instead, it's a pseudo-cron system, which is run as a loopback HTTP call
when you access a page on the site (essentially, the page "forks" itself to run
scheduled tasks).

This is fine for high traffic single-sites, but lower traffic sites might not
have their cron activated if the site isn't viewed. There are workarounds for
this, but they typically don't allow second-level granulaity or don't work
with multisite.

### Designed for Multisite

wp-cron was originally designed for single sites, and has had multisite grafted
on to it. For large multisite installations, this simply doesn't scale. One of
the tricks to ensure wp-cron runs is to ping a page on the site in a real cron
task, but this needs to be done once-per-site.

Cavalcade however contains full support from the ground up for multisite.
Firstly, rather than storing tasks per-site, they're stored all together with
the site ID as part of the data. This ensures that all sites are treated the
same, regardless of traffic.

Secondly, workers are localised to sites when they're started (via WP-CLI's
`--url` argument), allowing per-site plugins and themes to be loaded properly.
Since it starts with this data, it also runs through the normal sunrise process,
removing the need for complicated switches and conditionals in your code.

### Horizontally Scalable

One of the best ways of handling high traffic sites is to horizontally scale
your WordPress install. This involves having multiple application servers
running WordPress, and a load-balancer to spread out the requests. However,
traditional wp-cron cannot handle this due to the above limitations.

Cavalcade is designed to be inherently parallel. When you horizontally scale
your servers, simply have one runner per server. This means that as you scale
your site and server stack, Cavalcade will scale with you.

### Parallel Processing

Typically, wp-cron runs every scheduled event in a loop giving you
**sequential** processing of your tasks. If you have a long-running task, this
will block processing of other events, as wp-cron uses a global lock.

Cavalcade instead uses one-lock-per-task, allowing **parallel** processing of
tasks instead. By default, Cavalcade uses four worker processes to run your
tasks, however, this is configurable. This means that if you have a long-running
task, it will continue to execute in the background while the runner continues
to process the rest of the remaining tasks.

### Status Monitoring

Unlike wp-cron, which simply runs an action and forgets about it, Cavalcade
monitors the status of your tasks. If you have a fatal error, Cavalcade will log
the failure in the database, and automatically pause that event from running in
the future. If you want to restart it, the schedule will be resumed, and will
continue running on schedule. (For example, if you have an event run on Mondays
and it fails, restarting it will continue to run it on Mondays.)

## How?

Cavalcade requires a little bit of setup, and is not recommended for the faint
of heart. Keep in mind that it is an incredibly powerful system designed for
high traffic, large installs. Don't install it on every site just for fun.

### WordPress Plugin

Clone or submodule this repository into your `mu-plugins` directory, and load it
as an MU plugin. For example, create `mu-plugins/cavalcade.php` with the
following code:

```
<?php
require_once __DIR__ . '/cavalcade/plugin.php';
```

To start using it in your code, don't change anything. Simply use the normal
wp-cron functions, such as `wp_schedule_event`, `wp_schedule_single_event` and
`wp_next_scheduled`. Cavalcade integrates seamlessly into these, and the first
events you see appear in your jobs table will likely be WP's normal core events
such as update checks.

Disable the built in WordPress cron in `wp-config.php`:

```
define( 'DISABLE_WP_CRON', true );
```

### WP-CLI commands

There are three commands for WP-CLI bundled in Cavalcade. You can type `wp cavalcade` to see the commands at any time.

```
usage: wp cavalcade jobs [--format=<format>] [--id=<job-id>] [--site=<site-id>] [--hook=<hook>] [--status=<status>]
   or: wp cavalcade log [--format=<format>] [--fields=<fields>] [--job=<job-id>] [--hook=<hook>]
   or: wp cavalcade run <id>
```

1. `wp cavalcade jobs` will list all of the jobs. This command is useful for showing jobs that are queued. e.g. `wp cavalcade jobs --status=waiting`
2. `wp cavalcade log` shows logs of completed jobs.
3. `wp cavalcade run` will run a job.

### Runner

This is the more complex part. Grab the Cavalcade runner from
https://github.com/humanmade/Cavalcade-Runner and run it. The first parameter
passed to Cavalcade should be the relative path to your WordPress install
(i.e. to the directory where your `wp-config.php` is). By default, this will
use the current working directory; useful if you make `cavalcade` available in
your path.


The runner will remain in the foreground by itself; use your normal system
daemonisation tools, or `nohup` with `&` to run it in the background.
We recommend:

```
nohup bin/cavalcade > /var/log/cavalcade.log &
```

(Cavalcade outputs all relevant logging information to stdout, and only sends
meta-information such as shutdown notices to stderr.)

Note: The runner has three additional requirements:

* **pcntl** - The [Process Control PHP extension](http://php.net/pcntl) must be installed. Cavalcade Runner uses this to spawn worker processes and keep monitor them.
* **pdo** - The [PHP Data Objects (PDO)](http://php.net/pdo) must be installed. Cavalcade Runner uses this to access WordPress database.
* **wp-cli** - wp-cli must be installed on your server and available in the PATH. Cavalcade Runner internally calls `wp cavalcade run <id>` to run the jobs.

The runner is an independent piece of Cavalcade, so writing your own runner is possible if you have alternative requirements.

## License

Cavalcade is [licensed under the GPLv2 or later](LICENSE.txt).

## Who?

Created by Human Made for high volume and large-scale sites, such as
[Happytables](http://happytables.com/). We run Cavalcade on sites with millions
of monthly page views, and thousands of sites, including
[The Tab](http://thetab.com/), and the
[United Influencers](http://unitedinfluencers.se/) network.

Maintained by [Ryan McCue](https://github.com/rmccue).

Interested in joining in on the fun?
[Join us, and become human!](https://hmn.md/is/hiring/)
