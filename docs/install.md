# Installing Cavalcade

Cavalcade requires a little bit of setup, and is not recommended for the faint of heart. Keep in mind that it is an incredibly powerful system designed for high traffic, large installs. Don't install it on every site just for fun.

Installing Cavalcade is a two step process. The Cavalcade plugin needs to be added to your WordPress install, and the Cavalcade Runner daemon needs to be installed as a system-level service.

### WordPress Plugin

Clone or submodule this repository into your `mu-plugins` directory, and load it as an MU plugin. For example, create `mu-plugins/cavalcade.php` with the following code:

```php
require_once __DIR__ . '/cavalcade/plugin.php';
```


To start using it in your code, don't change anything. Simply use the normal wp-cron functions, such as `wp_schedule_event`, `wp_schedule_single_event` and `wp_next_scheduled`. Cavalcade integrates seamlessly into these, and the first events you see appear in your jobs table will likely be WP's normal core events such as update checks.

You'll also want to disable the built in WordPress cron in `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

### Runner

This is the more complex part. [Grab the Cavalcade runner from GitHub][runner] and run it. The first parameter passed to Cavalcade should be the relative path to your WordPress install (i.e. to the directory where your `wp-config.php` is). By default, this will use the current working directory; useful if you make `cavalcade` available in your path.

[runner]: https://github.com/humanmade/Cavalcade-Runner

The runner will remain in the foreground by itself; use your normal system daemonisation tools, or `nohup` with `&` to run it in the background. We recommend:

```sh
nohup bin/cavalcade > /var/log/cavalcade.log &
```

(Cavalcade outputs all relevant logging information to stdout, and only sends meta-information such as shutdown notices to stderr.)

Note: The runner has three additional requirements:

* **pcntl** - The [Process Control PHP extension](http://php.net/pcntl) must be installed. Cavalcade Runner uses this to spawn worker processes and keep monitor them.
* **pdo**/**pdo-mysql** - Unlike WordPress, Cavalcade-Runner uses PDO to connect to the database.
* **wp-cli** - wp-cli must be installed on your server and available in the PATH. Cavalcade Runner internally calls `wp cavalcade run <id>` to run the jobs.

The runner is an independent piece of Cavalcade, so writing your own runner is possible if you have alternative requirements.

If you're using Upstart (Ubuntu 12.04, 14.04) or systemd (Ubuntu 16.04+), we recommend using one of the existing service scripts included with Cavalcade-Runner.

Note that while the Runner does not require system-level user access (such as a root account), we don’t recommend using it on systems you don’t control (such as shared hosting).

### Usage

You can manually manage jobs via the `wp cavalcade` command, run `wp help cavalcade` for full documentation.
