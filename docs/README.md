# Cavalcade Documentation

Cavalcade is a scalable job system, designed as a drop-in replacement for WordPress's built-in pseudo-cron system.

From the WordPress side, none of your code needs to change. Cavalcade transparently integrates with the existing wp-cron functions to act as a full replacement. Cavalcade pushes these jobs off into their own database table for efficient storage.

At the core of Cavalcade is the job runner. The runner is a daemon that supervises the entire system. The runner constantly checks the database for new jobs, and is responsible for spawning and managing workers to handle the jobs when they're ready.

The runner spawns workers, which perform the actual tasks themselves. This is done by running a special WP-CLI command.

* [Motivation](motivation.md) - Why Cavalcade?
* [Installation](install.md)
* [Example Use Cases](examples.md)
* [Plugins](plugins.md) - Extending the functionality of Cavalcade
