=== Permalink Manager Lite ===
Contributors: mbis
Donate link: https://www.paypal.me/Bismit
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: urls, permalinks, slugs, custom url, custom permalinks, uris, url, slug, permalink, woocommerce permalinks, seo
Requires at least: 4.0
Requires PHP: 5.4
Tested up to: 4.9
Stable tag: 2.0.5.1

Advanced plugin that allows to set-up custom permalinks (bulk editors included), slugs and permastructures (WooCommerce compatible).

== Description ==

A really intuitive and easy-to-use plugin that helps to manage the permalinks for all your posts, pages and other custom post types items.

Currently, the plugin allows to perform four main actions:

1. It allows to manually adjust permalinks (URIs) for all posts/pages/custom post type items.
2. It allows to bulk replace particular words used in permalinks (or native slugs) with another words (works also with substring).
3. It allows to bulk regenerate/reset permalinks (or native slugs). This might be especially useful if your post titles are updated and native slugs need to be recreated.
4. It allows to change the default permalink bases (permastructures) for all custom post types & posts and pages.
5. It allows to auto-update URIs to match the current permastructure settings after eg. post title or assigned primary category is changed.
6. It allows to control trailing slash behavior (remove or append slash to the end of permalink).

Additional features available in <a href="https://permalinkmanager.pro?utm_source=wordpress">Permalink Manager Pro</a> only.

1. Extra redirects that could be defined individually for each post and term.
2. Possibility to remove /product-category and /product from WooCommerce permalinks.
3. Custom fields inside the permalinks (works also with Advanced Custom Fields).
4. Case insensitive permalinks.
5. "Stop-words" auto removal - custom words and/or words from predefined lists (19 languages available).

To improve the user experience, each tool allows also to filter the permalinks by post types or post statuses.

= All features =

* "Permalink Editor" - list of your permalinks (groupped by post types).
* "Auto-update" URI
* "Regenerate/Reset" permalinks, custom and native URIs (slugs).
* "Find and replace" strings in permalinks, custom and native URIs (slugs).
* Support for "Primary Term" functionality implemented in "Yoast SEO" plugin.
* Optional redirect (301 or 302) from old (native) permalinks to new (custom) permalinks.
* Possibility to disable native canonical redirects.

= Additional features available in Permalink Manager Pro =

* Priority support
* Full support for taxonomies (categories, tags & custom taxonomies)
* Extra redirects
* Full support for WooCommerce (products, product tags, product categories)
* "Stop-words" to keep your permalinks short & clean (pre-defined "stop-words" lists are available in 21 languages)
* Add custom fields to the permalinks (Advanced Custom Fields compatible)
* Import permalinks from "Custom Permalinks" plugin

Buy <a href="https://permalinkmanager.pro?utm_source=wordpress">Permalink Manager Pro here</a>.

== Installation ==

Go to `Plugins -> Add New` section from your admin account and search for `Permalink Manager`.

You can also install this plugin manually:

1. Download the plugin's ZIP archive and unzip it.
2. Copy the unzipped `permalink-manager` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

= Bulk URI editor =
After the plugin is installed you can access its dashboard from this page: `Tools -> Permalink Manager`.

= Single URI editor =
To display the URI editor metabox click on gray "Permalink Editor" displayed below the post/page title.

== Frequently Asked Questions ==

= Q. Can I delete/disable Permalink Manager after the permalinks are updated? =
A. Yes, if you used Permalink Manager only to regenerate the slugs (native post names). Please note that if you use custom permalinks (that differ from the native ones), they will no longer be used after the plugin is disabled.

It is because Permalink Manager overwrites one of the core Wordpress functionalities to bypass the rewrite rules ("regular expressions" to detect the posts/pages/taxonomies/etc. and another parameters from the URL) by using the array of custom permalinks (you can check them in "Debug" tab) that are used only by my plugin.

= Q. Can I use Permalink Manager to alter the permalinks for taxonomies?
A. This feature will be available in Permalink Manager Pro.

= Q. Does this plugin support Buddypress?
A. Currently there is no 100% guarantee that Permalink Manager will work correctly with Buddypress.

== Screenshots ==

1.	"Permalink editor".
2.	"Find and replace" section.
3.	"Regenerate/Reset" section.
4.  "Permastructures" section.
5.  A list of updated posts.
6.  Editable URI box in Post/Page/CPT edit pagees.
7.  Settings section.


== Changelog ==

= 2.0.5.1 =
* Hotfix for REGEX rule

= 2.0.5 =
* Now, the duplicates and unused custom permalinks can be automatically removed
* Better support for endpoints
* "Disable slug appendix" field is no longer needed
* %{taxonomy}_flat% tag enhanced for post types permastructures
* Fix for WPML language prefixes in REGEX rule used to detect URIs
* Possibility to disable Permalink Manager functions for particular post types or taxonomies

= 2.0.4.3 =
* Hotfix for problem with custom URIs for new terms & posts

= 2.0.4.2 =
* Trailing slashes redirect adjustment

= 2.0.4.1 =
* Hotfix for Elementor and another visual editor plugins
* Support for endpoints parsed as $_GET parameters

= 2.0.4 =
* New settings field - "Deep detect"

= 2.0.3.1 =
* Fix for Custom Fields tag in permastructures

= 2.0.3 =
* Custom URI editor in "Quick Edit"
* "Quick/Bulk Edit" hotfix
* New permastrutcure tag %category_custom_uri%

= 2.0.2 =
* WooCommerce search redirect loop - hotfix

= 2.0.1 =
* WooCommerce endpoints hotfix
* Redirects save notices - hotfix

= 2.0.0 =
* Extra Redirects - possibility to define extra redirects for each post/term
* New "Tools" section - "Permalink Duplicates"
* UI improvements for taxonomies ("Custom URI" panel)
* Fixes for reported bugs

= 1.11.6.3 =
* Slug appendix fix
* Hotfix for WooCommerce checkkout

= 1.11.6 =
* Hotfix for taxonomy tags
* Hotfix for custom field tags
* Hotfix for Jetpack
* Suuport for WP All Import
* Support for Custom Permalinks

= 1.11.5.1 =
* Hotfix for "Custom URI" form
* Hotfix for Yoast SEO & Visual Composer
* Now it is possible to disable slugs appendix

= 1.11.4 =
* Hotfix for RSS feeds URLs

= 1.11.1 =
* Trailing slashes & Decode URIs - new settings
* Fix for "Bulk Edit" URI reset
* Partial code refactoring

= 1.11.0 =
* Hierarchical taxonomies fix
* New hook: "permalink_manager_filter_final_term_permalink"

= 1.10.2 =
* Taxonomies & permastructures fix

= 1.1.1 =
* Typo fix
* UI improvements
* Fix for canonical redirects in WPML

= 1.1.0 =
* Partial code refactoring
* "Auto-update" feature
* UI/UX improvements
* Support for AMP plugin by Automattic

= 1.0.3 =
* Another pagination issue - hotfix

= 1.0.2 =
* Post pagination fix
* Basic REGEX support
* 'permalink_manager_filter_final_post_permalink' filter added

= 1.0.1 =
* WPML support fixes

= 1.0.0 =
* Further refactoring
* WPML support added
* Some minor issues fixed
* "Sample permalink" support added

= 0.5.2/0.5.3 =
* Another hotfix

= 0.5.1 =
* Hotfix for "Settings" section

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
