=== Permalink Manager ===
Contributors: mbis
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: urls, permalinks, slugs, custom url, custom permalinks, uris, url, slug, permalink
Requires at least: 4.0
Tested up to: 4.6.1
Stable tag: 0.5.0

Permalink Manager helps to maintain & list your permalinks, slugs and URIs. It also allows to bulk regenerate or find and replace any word in your permalinks or native slugs.

== Description ==

A really intuitive and easy-to-use plugin that helps to manage the permalinks for all your posts, pages and other custom post types items.

Currently, the plugin allows to perform four main actions:

1. It allows to manually adjust selected permalinks (URIs or native slugs) for all posts/pages/custom post type items.
2. It allows to bulk replace particular words used in permalinks (or native slugs) with another words (works also with substring).
3. It allows to bulk regenerate/reset permalinks (or native slugs). This might be especially useful if your post titles are updated and native slugs need to be recreated.
4. It allows to change the default permalink bases (permastructures) for all custom post types & posts and pages.

To improve the user experience, each tool allows also to filter the permalinks by post types or post statuses.

= "Find and replace" usage example =

Word "krakow" should be replaced with "gdansk" in all your permalinks.

`http://example.com/krakow/hotels-in-krakow === [changed] ===> http://example.com/gdansk/hotels-in-gdansk
http://example.com/krakow/restaurants-in-krakow === [changed] ===> http://example.com/gdansk/restaurants-in-gdansk
http://example.com/krakow/transport-in-krakow === [changed] ===> http://example.com/gdansk/transport-in-gdansk
http://example.com/blog/krakow-the-best-city-for-tourists === [changed] ===> http://example.com/blog/gdansk-the-best-city-for-tourists
http://example.com/poland/cities/krakow === [changed] ===> http://example.com/poland/cities/gdansk
http://example.com/poland/cities/stalowa-wola === [not changed] ===> http://example.com/cities/stalowa-wola
http://example.com/poland/cities/warszawa === [not changed] ===> http://example.com/poland/cities/warszawa
http://example.com/poland/cities/poznan === [not changed] ===> http://example.com/poland/cities/poznan`

= All features =

* "Permalink Editor" - list of your permalinks (groupped by post types).
* "Regenerate/Reset" permalinks, custom and native URIs (slugs).
* "Find and replace" strings in permalinks, custom and native URIs (slugs).
* Support for "Primary Term" functionality implemented in "Yoast SEO" plugin.
* Optional redirect (301 or 302) from old (native) permalinks to new (custom) permalinks.
* Possibility to disable native canonical redirects.

= Planned functionalities =

* REGEX for `Find and replace` section
* Support for WPML and another language plugins
* AJAX support.

== Installation ==

Go to `Plugins -> Add New` section from your admin account and search for `Permalink Manager`.

You can also install this plugin manually:

1. Download the plugin's ZIP archive and unzip it.
2. Copy the unzipped `permalink-manager` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

After the plugin is installed you can access its dashboard from this page: `Tools -> Permalink Manager`.

== Screenshots ==

1.	"Permalink editor".
2.	"Find and replace" section.
3.	"Regenerate/Reset" section.
4.  "Permastructures" section.
5.  A list of updated posts.
6.  Editable URI box in Post/Page/CPT edit pagees.
7.  Settings section.
8.  Developer section.

== Frequently Asked Questions ==

= Q. Does the plugin support WPML/qTranslate
= A. Unfortunately not, the WPML/qTranslate support will be added in next versions.

== Changelog ==

= 0.5.0 =
* Code refactoring completed
* Interface changes
* Hooks enabled

= 0.4.9 =
* Hook for removed posts (their URI is now automatically removed)

= 0.4.8 =
* Pagination bug - SQL formula fix (offset variable)

= 0.4.7 =
* Strict standards - fix for arrays with default values

= 0.4.6 =
* 302 redirect fix.
* Code optimization.

= 0.4.5 =
* Bug with infinite loop fixed.
* Bug with revisions ID fixed.

= 0.4.4 =
* Redirect for old URIs added.
* Debug tools added.

= 0.4.3 =
* Hotfix for "Screen Options" save process.

= 0.4.2 =
* Hotfix for bulk actions' functions - additional conditional check for arrays added.

= 0.4.1 =
* Hotfix for "Edit Post" URI input (the URIs were reseted after "Update" button was pressed).

= 0.4 =
* Rewrite rules are no longer used (SQL queries are optimized). The plugin uses now 'request' filter to detect the page/post that should be loaded instead.
* Now full URI (including slug) is editable.
* A few major improvements applied.
* Partial code optimization.

= 0.3.4 =
* Hotfix for not working custom taxonomies tags.
* Now the rewrite rules for custom post types are stored in different way.

= 0.3.3 =
* Hotfix for bug with dynamic function names in PHP7.

= 0.3.2 =
* Hotfix for front-end permalinks. The custom permastructures worked only in wp-admin.

= 0.3.1 =
* Hotfix for Posts & Pages permastructures

= 0.3 =
* Now all permalink parts can be edited - new "Permalink Base Editor" section added.
* Code optimization.
* Bugfixes for Screen Options & Edit links.

= 0.2 =
* First public version.

= 0.1 =
* A first initial version.
