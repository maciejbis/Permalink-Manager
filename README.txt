=== Permalink Manager Lite ===
Contributors: mbis
Donate link: https://www.paypal.me/Bismit
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: permalinks, custom permalinks, permalink, woocommerce permalinks, url editor
Requires at least: 4.4.0
Requires PHP: 5.4
Tested up to: 6.6.0
Stable tag: 2.4.3.3

Permalink Manager improves the built-in permalink settings and allows you to change the URLs of native and custom post types and taxonomies as needed.

== Description ==

Permalink Manager is a permalink plugin that allows users to adjust URLs for posts, pages, and custom post types (categories, tags and custom taxonomies are supported in Pro version).

Unlike the built-in WordPress permalink system, which only allows modifications to the last part of the URL, known as the "slug", the Permalink Manager lets you <a href="https://permalinkmanager.pro/docs/basics/uri-editor/">change each individual URL</a> whatever you like.

Following permalink customization, <a href="https://permalinkmanager.pro/docs/plugin-settings/canonical-redirects/#custom-permalinks">the old URLs will automatically redirect to the new addresses</a> in order to prevent 404 or duplicate content issues.

<a href="https://permalinkmanager.pro/docs/?utm_source=wordpressorg">Documentation</a> | <a href="https://permalinkmanager.pro/buy-permalink-manager-pro/?utm_source=wordpressorg">Buy Permalink Manager Pro</a>

The plugin works with all custom post types and taxonomies, as well as many popular third-party plugins like as WooCommerce, Yoast SEO, WPML, and Polylang.

= Features =

* **Edit the individual permalinks as you choose**<br/>For a consistent and SEO-friendly URL structure, you may customize and <a href="https://permalinkmanager.pro/docs/basics/change-permalinks/">change the permalink</a> of each post, page, and custom post type item.  *Categories, tags & custom taxonomies terms permalinks can be edited in Permalink Manager Pro.*
* **Edit URLs in bulk using permalink formats**<br/>In order to speed up the process of bulk URL modification, the plugin allows you to choose the default format for custom URLs using "Permastructures" settings. The new format will be applied automatically when a new post/term is added or once the old permalinks are regenerated.
* **Custom post types support**<br/>You may easily remove post type rewrite (base) slugs from your WordPress permalinks, for example. The plugin may be configured to filter just specified post types and taxonomies permalinks, excluding the rest of your content types.
* **Translate permalinks**<br/>If you have the WPML or Polylang plugins installed on your website, Permalink Manager allows you to translate the slug and specify different permalink format/structure for each language.
* **Remove parent slugs**<br/>Looking for a simple solution to shorten lengthy, hierarchical URL addresses? The plugin may be used to <a href="https://permalinkmanager.pro/docs/tutorials/wordpress-permalinks-structure/#remove-parent-slugs">remove parent slugs from WordPress permalinks</a>.
* **Add category slug to post permalinks**<br/>Do you want to <a href="https://permalinkmanager.pro/docs/tutorials/add-category-slug-wordpress-permalinks/">add category slugs in your post permalinks</a>? Permalink Manager is the most convenient way to create a silo structure for your URL addresses.
* **Auto-redirect old URLs**<br/>An old (original) URL is automatically forwarded to an updated URL to avoid the 404 error and to improve the user experience.

= Additional features available in Permalink Manager Pro =

The free version covers all the necessary functions, while the premium version adds a few handy functionalities that can improve the process of adjusting WordPress permalinks.

Click here for additional information and to purchase <a href="https://permalinkmanager.pro?utm_source=wordpress">Permalink Manager Pro</a>.

* **Taxonomies support**<br/>Taxonomies are fully supported in the premium version (categories, tags & custom taxonomies). You may adjust individual term permalinks or change them all at once using "Permastructures".
* **WooCommerce support**<br/>Permalink Manager Pro may be used to change the URL addresses of WooCommerce products, tags, categories, and attributes. For example, you may use the plugin to <a href="https://permalinkmanager.pro/docs/tutorials/remove-product-category-woocommerce-urls/">remove /product/ and /product-category/ from WooCommerce URL</a>.
* **Custom fields support**<br/>Only Permalink Manager makes it possible to <a href="https://permalinkmanager.pro/docs/tutorials/how-to-use-custom-fields-inside-wordpress-permalinks/">add custom fields to WordPress permalinks</a> without the need for any technical skills on the part of the user.
* **Extra redirects**<br/>You can define extra 301 redirects (aliases) for any post, page, or term. Additionally, you may assign a redirect URL to each post/term, which will take users to any external URL address. For each element, the redirect URLs might be specified separately.

== Installation ==

Go to `Plugins -> Add New` section from your admin account and search for `Permalink Manager`.

You can also install this plugin manually:

1. Download the plugin's ZIP archive and unzip it.
2. Copy the unzipped `permalink-manager` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

= Bulk URI editor =
After the plugin is installed you can access its dashboard from this page: `Tools -> Permalink Manager`.

= Single URI editor =
To display the URI editor metabox click on gray "Permalink Editor" button displayed below the post/page title.

== Frequently Asked Questions ==

= Can I use the plugin to edit the category permalinks?
This feature is available only in Permalink Manager Pro.

= Is it possible to use Permalink Manager on large websites?
When the Permalink Manager was first designed, it was supposed to be used for a typical WordPress website, which usually has <strong>less than a few thousand subpages</strong>. As a result, all custom permalinks are <a href="https://permalinkmanager.pro/docs/filters-hooks/how-the-custom-uris-and-redirects-are-stored/">saved in a single row in the database</a> in order to avoid slowing down the pageload with multiple SQL queries to the database. This is the most effective approach for small and medium-sized websites, without affecting site speed.

While this data structure works for the vast majority of WordPress sites, it may not be optimal if you want to use the plugin to rewrite <strong>tens of thousands of permalinks</strong>. What works well for a smaller website may not scale well for a megasite. When the number of addresses on your site exceeds tens of thousands, the custom permalinks array may become quite huge, and any operations on it can have an effect on pageload time.

To summarize, the plugin is suitable for small and medium-sized websites. It will not slow down your pageload time or affect its usability in any way. However, if you want to use it on a much bigger website with thousands of permalinks (more than 60.000), please consider excluding content types that do not require customized permalink format in order to lower the custom permalinks array. For further details on the plugin's performance, please <a href="https://permalinkmanager.pro/docs/basics/performance/">visit this post</a>.

= Can I define different permalink formats per each language.
Yes, it is possible if you are using either WPML or Polylang. You can find <a href="https://permalinkmanager.pro/docs/tutorials/how-to-translate-permalinks/">the full instructions here</a>.

= Will the old permalink automatically redirect the new ones?
Yes, Permalink Manager will automatically redirect the native permalinks (used when the plugin is disabled, or before it was activated) to the actual, custom permalinks.

= Does this plugin support Buddypress?
Currently, there is no 100% guarantee that Permalink Manager will work correctly with Buddypress.

= Can I remove the plugin after the permalinks are updated? =
Yes, if you used Permalink Manager only to regenerate the slugs (native post names). Please note that if you use custom permalinks (that differ from the native ones), they will no longer be used after the plugin is disabled.

It is because Permalink Manager overwrites one of the core WordPress functionalities to bypass the rewrite rules ("regular expressions" to detect the posts/pages/taxonomies/etc. and another parameters from the URL) by using the array of custom permalinks (you can check them in "Debug" tab) that are used only by the plugin.

== Screenshots ==

1.	Permalink URI editor.
2.	Permalink URI editor in Gutenberg.
3.	"Find & replace" tool.
4.	"Regenerate/Reset" tool.
5.	A list of updated posts after the permalinks are regenerated.
6.	Permastructure settings.
7.	Permastructure settings (different permalink structure per language).
8.	Permalink Manager settings.

== Changelog ==

= 2.4.3.3 (May 16, 2024) =
* Dev - Optimization of "Permalink_Manager_Core_Functions::fix_pagination_pages"
* Dev - The canonical redirect function has been improved to fully handle the "/page/1" and "/1/" endpoints as well as the "p", "page_id", and "name" query parameters in URLs
* Fix - The plugin may save the native slug for "draft" posts and pages even if WordPress has not generated it yet
* Fix - "Customize URL" in the admin toolbar works now correctly also for categories, and custom taxonomies
* Fix - "Auto-update mode" is now respected in Advanced Translation Editor (WPML)

= 2.4.3.2 (March 18, 2024) =
* Fix - Further security improvements for AJAX functions and "Bulk Tools"
* Dev - Minor code improvements

= 2.4.3.1 (February 12, 2024) =
* Fix - Security fix for Permalink_Manager_Actions->ajax_detect_duplicates() function
* Dev - Minor code improvements

= 2.4.3 (February 6, 2024) =
* Fix - Code refactoring and optimization
* Fix - Minor improvements for RankMath redirection hooks
* Enhancement - Improvements for "Force 404 on non-existing pagination pages" functionality
* Enhancement - The "Regenerate/reset" & "Find/replace" now can be used in preview mode without saving the changes
* Dev - New filter hooks - "permalink_manager_pre_update_post_uri" & "permalink_manager_pre_update_term_uri"
* Dev - Support for translated "page" endpoint in Polylang Pro
* Dev - Support for primary terms controlled by All In One SEO Pro

= 2.4.2 (January 9, 2024) =
* Fix - The Permalink_Manager_Helper_Functions::replace_empty_placeholder_tags() no longer decodes invalid ASCII characters
* Fix - The old slug ("_wp_old_slug") is now saved correctly in Block Editor (Gutenberg)
* Dev - New 'permalink_manager_sanitize_title' filter is added
* Dev - Duplicated dashes are now removed from default permalinks unless "Strip special characters" is disabled in the plugin settings
* Dev - Minor fixes and improvements

= 2.4.1.6 (November 6, 2023) =
* Dev - Refactoring & minor code improvements

= 2.4.1.4/2.4.1.5 (September 25, 2023) =
* Enhancement - Support for "Primary category" set with SmartCrawler plugin
* Enhancement - Partial support for Site Kit by Google plugin
* Dev - Minor code improvements

= 2.4.1.3 (August 7, 2023) =
* Dev - Code refactoring
* Fix - Fixed /feed/ endpoint support

= 2.4.1.2 (June 28, 2023) =
* Dev - Draft posts no longer automatically generate custom permalinks, but users may set them manually if necessary, or they will be generated when the post is published
* Fix - Duplicated REST API calls from Gutenberg JS functions are now ignored when custom permalinks are generated
* Fix - The 'High-Performance order storage (COT)' declaration for the WooCommerce has been fixed

= 2.4.1 (May 22, 2023) =
* Dev - The function that adds the "Permalink Manager" button via 'get_sample_permalink_html' filter has been updated
* Dev - The function that controls permalink trailing slashes has been refactored and improved
* Dev - When WPML is enabled, Permalink Manager uses "term_taxonomy_id" instead of "term_id" for language mismatch functions to avoid compatibility issues
* Dev - To avoid problems with other 3rd party plugins, the function that places the "Permalink Manager" button below the title editor field in Classic Editor mode no longer overwrites the whole HTML
* Enhancement - The plugin interface's text descriptions and label names have been simplified for readability
* Enhancement - Added new section "Exclusion settings" with a field to manually enter IDs of posts/terms to be ignored by Permalink Manager
* Fix - The compatability problem that caused "fatal error" for some RankMath users has been resolved

= 2.4.0 (April 12, 2023) =
* Dev - Improved custom permalink detection function
* Dev - Minor code improvements for the breadcrumbs filter function
* Dev - Minor CSS changes
* Dev - New filter added - 'permalink_manager_excluded_element_id'
* Dev - New filter added - 'permalink_manager_duplicate_uri_policy'
* Dev - Now users may select in "WPML/Polylang fix language mismatch" settings field between loading translation or triggering the canonical redirect to the detected item
* Dev - Support for WooCommerce 'High-Performance order storage (COT)' declared
* Fix - The RankMath redirection function is disabled if custom permalink is detected to prevent redirect loop
* Fix - The "Exclude drafts & pending posts" setting field has been changed to allow for greater control in generating and editing custom permalinks for draft and pending posts

= 2.3.1.1 (February 16, 2023) =
* Dev - Hotfix for "Quick Edit" URI editor

= 2.3.1 (February 13, 2023) =
* Dev - Bulk tools ("Regenerate/Reset" and "Find & replace") and "Quick Edit" code was refactored
* Dev - Minor code improvements
* Dev - New filter field - 'permalink_manager_ate_uri_editor'
* Dev - Improved compatibility with WPML's Advanced Translation Editor
* Fix - The /feed/ endpoint returns 404 error if 'feeds' in rewrite property of requested post type object is set to false
* Fix - The canonical redirect is no longer forced for LearnPress front-end pages

= 2.3.0 (December 14, 2022) =
* Dev - For improved readability, the plugin's code has been reformatted and more comments have been added to match WordPress PHP Coding Standards
* Dev - To simplify the codebase, redundant functions and variables were removed
* Fix - The post/term titles in Bulk URI Editor are protected from XSS (Cross-site scripting) attacks by sanitizing the displayed titles
* Fix - Improved compatibility with Groundhogg plugin
* Fix - Improved compatibility with BasePress plugin
* Fix - Minor improvements for WPML compatibility
* Fix - The bug that caused the message "You are not allowed to remove Permalink Manager data!" to show up randomly in the admin dashboard has been fixed

= 2.2.20.4 (November 23, 2022) =
* Fix - The "URI Editor" for individual term pages is now called later to ensure that all custom taxonomies are registered
* Dev - The "nonce" field has been renamed for clarity
* Dev - New filter added - 'permalink_manager_get_language_code'

= 2.2.20.2/2.2.20.3 (November 15, 2022) =
* Fix - A nonce field has been added to debug tools code for increased security
* Fix - The "Fix language mismatch" function now functions exactly the same way in Polylang as it does in WPML

= 2.2.20.1 (October 31, 2022) =
* Fix - Security fix for BAC vulnerability found in the debug function that allowed unauthorized removal of single URIs

= 2.2.20 (October 10, 2022) =
* Fix - The URLs with duplicated slashes (eg. example.com/sample-page////) are now handled correctly and forwarded to the canonical URL
* Fix - The redirect problem was resolved with WPForo versions after 2.0.1
* Dev - Improved compatibility with the WP All Import plugin functions
* Dev - Improved compatibility with Polylang plugin
* Dev - Better support for ACF Relationship fields
* Dev - The plugin no longer (by default) supports custom post types & taxonomies that do not have the "query_var" and "rewrite" properties
* Enhancement - In "Exclude drafts" mode, the URI Editor field in the "Quick Edit" section becomes "read-only" for the "Draft" posts

= 2.2.19.3 (August 11, 2022) =
* Dev - New filter added - 'permalink_manager_pre_sanitize_title'
* Fix - The old slugs are saved in the '_wp_old_slug' meta key even if the native slugs are changed in the URI Editor in the Gutenberg mode.
* Fix - Extra security check in the "Debug" section to prevent unauthorized users (CSRF) from removing the plugin's data.

= 2.2.19.2 (July 8, 2022) =
* Fix - JS conflict fixed ("Cannot read properties of null (reading 'isSavingMetaBoxes')")

= 2.2.19.1 (June 27, 2022) =
* Fix - JS conflict fixed ("Cannot read property 'isSavingPost' of null")

= 2.2.19 (June 27, 2022) =
* Fix - The term custom permalink is now returned in the correct language
* Fix - In Gutenberg mode, the custom permalinks are saved correctly and are not changed back to the default format ("URI Editor" is now only reloaded once the post has been saved and the metaboxes have been refreshed)
* Enhancement - Old URIs are saved as "extra redirects" if content is updated with WP All Import
* Dev - Additional minor improvements in code (including changes to make it work with PHP 8.1)

= 2.2.18 (May 18, 2022) =
* Fix - The "permalink_manager_filter_permastructure" filter can now also be used before the "Permastructure" settings are saved in the database
* Enhancement - Improved support for RankMath breadcrumbs
* Dev - License notification function has been improved (Permalink Manager Pro)
* Dev - Additional minor improvements in code

= 2.2.17 (March 22, 2022) =
* Fix - Permalink Manager supports WPML's "Post Types & Taxonomy Translation" settings and returns the permalink of the fallback post/term with the correct language code
* Fix - When the auto-update mode for categories is disabled, the manually adjusted permalinks are no longer overwritten by the default ones
* Enhancement - Permalink Manager now allows you to rewrite just chosen articles and terms while leaving the rest untouched (See '"Auto-update" permalinks' settings field)
* Enhancement - Improved support for SEOPress breadcrumbs
* Enhancement - "Auto-update permalinks" setting is now replaced with "URI update mode" to give users better control on how Permalink Manager generates and saves the custom permalinks
* Dev - Additional minor improvements in code

= 2.2.16 (January 23, 2022) =
* Enhancement - Improved support for "Primary category" feature included in Yoast SEO
* Enhancement - Added support for Avia/Enfold breadcrumbs filter
* Enhancement - Further optimisation and improvements for Permalink_Manager_Core_Functions->new_uri_redirect_and_404() function
* Fix - Permalink Manager now recognises the "Explore" listing page in MyListing theme properly

= 2.2.15.1 (January 14, 2022) =
* Fix - "Regenerate/reset" tool works correctly again in Permalink Manager Lite

= 2.2.15 (January 12, 2022) =
* Enhancement - UI Improvements for Regenerate/reset tool
* Dev - WPML_URL_Filters->permalink_filter() hook is also used by Permalink Manager to filter custom permalinks
* Enhancement - wp_make_link_relative() function is used to prevent redirect loops in new_uri_redirect_and_404() (suggested by mgussekloo)
* Fix - Adjustments to the debug function's security to prevent XSS injection

= 2.2.14 (October 20, 2021) =
* Enhancement - Improvements for Gutenberg Editor
* Dev - Tippy.js (by atomiks) updated to version 6.3.2
* Fix - From now on, the user role selected in “URI Editor role capability” is respected in “Quick Edit” box hooks (reported by @lozeone)
* Dev - Further security improvements inside WP-Admin dashboard (reported by Vlad Vector)

= 2.2.13.1 (September 20, 2021) =
* Dev - Minor security improvements inside WP-Admin dashboard
* Fix - Allow canonical redirect for default language if "Hide URL language information for default language" is turned on in Polylang settings
* Enhancement - New settings field - "Primary category support"
* Enhancement - "Force 404 on non-existing pagination pages" works now with archive pages

= 2.2.12 (August 17, 2021) =
* Dev - New filters added - 'permalink_manager_excluded_post_ids' & 'permalink_manager_excluded_term_ids'
* Dev - Additional minor changes in the codebase
* Fix - Canonical permalinks for blog pagination is now correctly filtered (if Yoast SEO is used)
* Fix - Better support for 'private' posts & pages

= 2.2.11 (June 24, 2021) =
* Fix - The function that automatically removes the broken URIs is no longer triggered when WP Rocket is turned on and non-logged-in user tries to access the broken URL.

= 2.2.10 (June 7, 2021) =
* Enhancement - New settings field - "Copy query parameters to redirect target URL" & "Extra redirects (aliases)"
* Enhancement - UI improvements in settings section
* Dev - Improved support for WPML's Classic Translation Editor
* Dev - Additional minor changes in the codebase

= 2.2.9.9 (April 26, 2021) =
* Fix - Hotfix for AMP WP integration

= 2.2.9.8 (April 26, 2021) =
* Fix - The old native slug is now correctly saved after it is changed in URI Editor.
* Enhancement - The post type archives are now also added to the filtered breadcrumbs trail
* Enhancement - Basic support added for WP All Export plugin
* Enhancement - Basic support added for AMP for WP
* Dev - (Permalink Manager Pro only) "Plugin Update Checker" by YahnisElsts library updated to 4.11 version

= 2.2.9.7 (March 11, 2021) =
* Enhancement - Support for WooCommerce CSV Product Importer/Exporter added
* Enhancement - Better support for relationship field (ACF)
* Fix - The custom redirects are now case-insensitive

= 2.2.9.6 (February 8, 2021) =
* Fix - Hotfix for WooCommerce coupon related functions

= 2.2.9.5 (February 8, 2021) =
* Fix - The custom permalink is generated properly if the product is duplicated in WooCommerce dashboard
* Enhancement - New settings field - "Exclude drafts"
* Enhancement - Minor code improvements

<a href="https://permalinkmanager.pro/changelog/">Full changelog is available here.</a>