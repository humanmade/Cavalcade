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


## Documentation

**[View documentation →](https://github.com/humanmade/Cavalcade/tree/master/docs)**

* [Motivation](docs/motivation.md) - Why Cavalcade?
* [Installation](docs/install.md)
* [Example Use Cases](docs/examples.md)
* [Plugins](docs/plugins.md) - Extending the functionality of Cavalcade

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
