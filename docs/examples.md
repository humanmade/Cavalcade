# Example Use Cases

With Cavalcade, WP's cron system becomes a first-class citizen. Here's some of the things that Cavalcade works great for.

## Newsletters

Sites may want to send out a weekly newsletter to users. If these emails are highly customised (such as weekly metrics), this can lead to huge scaling issues. Particularly, if you have a multisite install, this means a lot of `switch_to_blog()` calls, which can be super expensive.

You may be using something like this currently:

```php
// Only run on primary site
if ( is_main_site() ) {
	wp_schedule_event( time(), 'weekly', 'send_newsletter' );
}

add_action( 'send_newsletter', function () {
	foreach ( wp_get_sites() as $site ) {
		// Prepare and send email
		switch_to_blog( $site->blog_id );
		$email = prepare_email( $site );
		$user = get_primary_user( $site );
		send_email( $user, $content );
	}
});
```

This will quickly lead to timeouts without any configuration in WordPress. However, even once you've moved cron tasks off to running via the command line, this will again hit an upper limit with memory, as WP and PHP both have memory leakage.

With regular cron, this is mostly unavoidable, as multisite cron is almost impossible to offload to the command line safely.

Cavalcade simplifies this by using a single daemon runner for all sites. When the event occurs and the job is run, WP-CLI is invoked to perform the job using the `--url` parameter directly. This avoids needing to switch sites and saves excessive database calls.

```php
// Run on every site
wp_schedule_event( time(), 'weekly', 'send_newsletter' );

add_action( 'send_newsletter', function () {
	// Prepare and send email
	$site = get_blog_details( get_current_blog_id() );
	$email = prepare_email( $site );
	$user = get_primary_user( $site );
	send_email( $user, $content );
});
```

Cavalcade's design ensures that the cron system can scale up to thousands of simultaneous tasks without breaking a sweat.


## Asynchronous Calls

Often, you'll want to call some long-running code asynchronously. For example, sending email notifications on post publish is slow, so pushing this off to an asynchronous call avoids blocking the request. However, WP cron reliability issues have meant that typically it wasn't a valid choice.

With Cavalcade, you can simply use `wp_schedule_single_event()` and forget worrying. These will scale up as you scale your servers horizontally, so you don't need to worry about another generic job queue or asynchronous processing utility.

Let's send an email to all users when you publish a post:

```php
add_action( 'wp_publish_post', function ( $post ) {
	wp_schedule_single_event( time(), 'send_notifications', $post );
});

add_action( 'send_notifications', function( $post ) {
	foreach ( get_users() as $user ) {
		send_notification( $user, $post );
	}
});
```

Thanks to WP cron's arguments simply being serialized data, this can be adapted generically to any action:

```php
function add_deferred_action( $hook, $callback, $priority, $num_args ) {
	add_action( $hook, function () {
		wp_schedule_single_event( time(), 'defer-' . $hook, func_get_args() );
	}, $priority, $num_args );
	add_action( 'defer-' . $hook, $callback );
}

// Then to use it, just replace your existing call...
# add_action( 'wp_publish_post', 'expensive_task_on_publish', 20, 2 );
// with the deferred one:
add_deferred_action( 'wp_publish_post', 'expensive_task_on_publish', 20, 2 );
```

As long as your callback doesn't rely on global state (apart from the current site), this is a quick-and-easy way to run expensive tasks.


## Get in touch!

Got a cool use case you solved using Cavalcade? [Let us know](https://github.com/humanmade/Cavalcade/issues/new)!
