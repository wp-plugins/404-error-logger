=== 404 Error Logger ===
Contributors: solarissmoke
Tags: error, log, 404
Requires at least: 3.2
Tested up to: 3.5
Stable tag: trunk

Logs 404 (Page Not Found) errors on your WordPress site.

== Description ==

Logs 404 (Page Not Found) errors on your WordPress site. This can be useful for tracking down broken links or missing content. You can optionally record additional information such as IP addresses, user agents and referrers.

**IMPORTANT: This plugin will not work if you use a caching/performance plugin that caches 404 responses. Please check that this isn't the case before reporting a bug.**

If you come across any bugs or have suggestions, please use the plugin support forums or contact me at [rayofsolaris.net](http://rayofsolaris.net). I can't fix it if I don't know it's broken!

== Frequently Asked Questions ==

= Will this plugin work if a caching/performance plugin is active ? =

The plugin has been tested with the following caching plugins, and should work even if they are active:

- W3 Total Cache
- WP Super Cache

I have not tested it with other caching plugins, and there is a high chance that it **will not work** with many of them. Most of them will cache the response so that it cannot be logged.

== Changelog ==

= 0.1.3 =
* Add support for popular caching plugins.

= 0.1.2 =
* Fix Javascript in log viewer
* Remove some redundant code

= 0.1.1 =
* Bugfix: old entries were not properly deleted

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin settings can be accessed via the 'Tools' menu in the administration area, under '404 Error Logger'
 
