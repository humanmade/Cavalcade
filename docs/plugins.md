# Plugins

Cavalcade is fully extensible. For additional functionality for the plugin side of Cavalcade, you can use the existing hooks and system in WordPress.

Plugins in Cavalcade Runner work a little differently. While most functionality can be handled in WordPress, meta-level reporting and logging of jobs is best done in the Runner.

Since the Runner is a separate, non-WordPress daemon, it includes its own plugin system. This system will be familiar to anyone who has written a WordPress plugin before.

## Writing a Plugin

The only file Cavalcade loads from your project is `wp-config.php`, so all plugin code for Cavalcade needs to be registered before your `require 'wp-settings.php'` line.

To add a hook, call `HM\Cavalcade\Runner::instance()->hooks->register()`. This function is almost identical to the `add_filter()` function in WordPress:

```php
/**
 * Register a callback for a hook.
 *
 * @param string $hook Hook to register callback for.
 * @param callable $callback Function to call when hook is triggered.
 * @param int $priority Priority to register at.
 */
public function register( $hook, $callback, $priority = 10 );
```

## Hook Naming

The best place to find hooks to use is to read the source code directly.

Hooks are named `Class.method.action`, where Class is the class name excluding the `HM\Cavalcade\Runner`, and with `\` replaced with `.`. This ensures you know exactly where a hook is defined.

## Adding Your Own Hooks

You can add your own hooks to your plugins, if you want to allow others to extend them:

```php
/**
 * Run a hook's callbacks.
 *
 * @param string $hook Hook to run.
 * @param mixed $value Main value to pass.
 * @param mixed ...$args Other arguments to pass.
 * @return mixed Filtered value after running through callbacks.
 */
public function run( $hook, $value = null, ...$args );
```
