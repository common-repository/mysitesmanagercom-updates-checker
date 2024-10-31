=== Plugin Name ===
Tags: manage, upgrade, updates 
Requires at least: 3.1
Tested up to: 3.9.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

www.mysitesmanager.com is the easy way to manage multiple WordPress sites. We take the headache out of managing lots of sites by giving you a simple dashboard that lists your sites and any updates that are available. Built for security, we do not store any login data. We do not store your login details or any other passwords or sensitive data. The way the system works is you add our plugin to your site. The plugin creates an xml file that lists any out of date app versions, plugins or themes. Our system reads this xml file and displays the data on your dashboard.
 
How does the plugin work?
The plugin will run a daily cron job to check for updates and then write the details to the xml file. We then read this xml file from our system and write the data to your dashboard. 

The plugin is not standalone you need a free account with www.mysitesmanager.com to use it.

The plugin is based on code from the WP Updates Notifier plugin (http://wordpress.org/plugins/wp-updates-notifier/) by Scott Cariss but we have substantially modified the code.

*Features*

- Runs a daily check for updates to plugins, themes and Wordpress. Then writes the details to an xml file.

== Installation ==

* Unzip and upload contents to your plugins directory (usually wp-content/plugins/).
* Activate plugin
* Visit Settings page under Settings in your WordPress Administration Area
* Configure plugin settings

== Screenshots ==

1. Settings page

== Changelog ==
= 1.0.1 =

Plug will return updates for all plugins and themes, not only active ones. 
Improoved XML randomized file name for better security.

= 1.0 =
* Initial release


