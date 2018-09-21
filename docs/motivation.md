# Why Cavalcade?

We created Cavalcade to serve our needs, as none of the existing options for scheduled tasks in WordPress was a good fit. Here's why.

### Guaranteed Running

wp-cron is not actually a real task scheduler, and doesn't actually operate like cron. Instead, it's a pseudo-cron system, which is run as a loopback HTTP call when you access a page on the site (essentially, the page "forks" itself to run scheduled tasks).

This is fine for high traffic single-sites, but lower traffic sites might not have their cron activated if the site isn't viewed. There are workarounds for this, but they typically don't allow second-level granulaity or don't work with multisite.

### Designed for Multisite

wp-cron was originally designed for single sites, and has had multisite grafted on to it. For large multisite installations, this simply doesn't scale. One of the tricks to ensure wp-cron runs is to ping a page on the site in a real cron task, but this needs to be done once-per-site.

Cavalcade however contains full support from the ground up for multisite. Firstly, rather than storing tasks per-site, they're stored all together with the site ID as part of the data. This ensures that all sites are treated the same, regardless of traffic.

Secondly, workers are localised to sites when they're started (via WP-CLI's `--url` argument), allowing per-site plugins and themes to be loaded properly. Since it starts with this data, it also runs through the normal sunrise process, removing the need for complicated switches and conditionals in your code.

### Horizontally Scalable

One of the best ways of handling high traffic sites is to horizontally scale your WordPress install. This involves having multiple application servers running WordPress, and a load-balancer to spread out the requests. However, traditional wp-cron cannot handle this due to the above limitations.

Cavalcade is designed to be inherently parallel. When you horizontally scale your servers, simply have one runner per server. This means that as you scale your site and server stack, Cavalcade will scale with you.

### Parallel Processing

Typically, wp-cron runs every scheduled event in a loop giving you **sequential** processing of your tasks. If you have a long-running task, this will block processing of other events, as wp-cron uses a global lock.

Cavalcade instead uses one-lock-per-task, allowing **parallel** processing of tasks instead. By default, Cavalcade uses four worker processes to run your tasks, however, this is configurable. This means that if you have a long-running task, it will continue to execute in the background while the runner continues to process the rest of the remaining tasks.

### Status Monitoring

Unlike wp-cron, which simply runs an action and forgets about it, Cavalcade monitors the status of your tasks. If you have a fatal error, Cavalcade will log the failure in the database, and automatically pause that event from running in the future. If you want to restart it, the schedule will be resumed, and will continue running on schedule. (For example, if you have an event run on Mondays and it fails, restarting it will continue to run it on Mondays.)
