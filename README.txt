=== Permalink Manager ===
Contributors: bis
Tags: urls, permalinks, slugs
Requires at least: 4.0
Tested up to: 4.4
Stable tag: 4.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Permalink Manager allows you to control and reset the permalinks (slugs) in all your post types.

== Description ==

It is a really simple plugin that helps managing the permalinks that are used for all your Posts, Pages and other Custom Post Types. To improve the experience of the manager, you can filter the table and display only selected `post type`s or posts from particular `post status`es.  

There are three main functionalities of this plugin:
1. You can manually adjust the slugs of selected posts/pages/custom post type items.
2. You can replace particular (sub)string that is a part of slug with another (sub)string.
3. You can regenerate/reset the slugs for your posts/pages/custom post types. This might be especially useful if your post titles are updated and slugs needs to be recreated.

= Example =

If you want to quickly replace a part of slug (eg. `krakow` with another word `gdansk`):
`http://example.com/hotels-in-krakow
http://example.com/restaurants-in-krakow
http://example.com/transport-in-krakow
http://example.com/blog/krakow-the-best-city-for-tourists
http://example.com/poland/cities/krakow
http://example.com/poland/cities/stalowa-wola
http://example.com/poland/cities/warszawa
http://example.com/poland/cities/poznan`

If you use the form from `Find and replace` section your URLs will be changed to:

`http://example.com/hotels-in-gdansk
http://example.com/restaurants-in-gdansk
http://example.com/transport-in-gdansk
http://example.com/blog/gdansk-the-best-city-for-tourists
http://example.com/poland/cities/gdansk
http://example.com/poland/cities/stalowa-wola
http://example.com/poland/cities/warszawa
http://example.com/poland/cities/poznan`

= Upcoming features =

In the next version of plugin more functionalities will be added:
* Support for taxonomies
* REGEX for `Find and replace` section
* Two-step updater, so you can double-check which permalinks will be changed before the change is applied.
* AJAX support.

== Installation ==

Go to `Plugins -> Add New` section from your admin account and search for `Permalink Manager`. 

You can also install this plugin manually:
1. Download the plugin's ZIP archive and unzip it. 
2. Copy the unzipped `permalink-manager` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

After the plugin is installed you can access its dashboard from this page:
`Tools -> Permalink Manager`.

== Screenshots ==

1. Main dashboard.
2. Find and replace section.
3. Regenerate section.

== Changelog ==

= 0.1 =
* A first initial version.

== Upgrade Notice ==

= 0.1 =
A first initial version.