=== Permalink Manager Lite ===
Contributors: mbis
Donate link: https://www.paypal.me/Bismit
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: permalinks, custom permalinks, permalink, woocommerce permalinks, custom permalink
Requires at least: 5.3.0
Requires PHP: 5.4
Tested up to: 7.1
Stable tag: 2.6.1

Permalink Manager extends WordPress’s native permalink functionality, giving you more control over URL structures for custom post types, and taxonomies.

== Description ==

Permalink Manager gives you more control over WordPress URLs, allowing you to customize URLs directly from the admin dashboard without requiring custom code. You can define a custom permalink structure for any content type or edit individual permalinks separately.

The plugin supports all custom post types and taxonomies and works with popular plugins such as WooCommerce, ACF, WPML, and Polylang.

The free version lets you customize permalinks for **posts, pages, and custom post types**. With the Pro version, you can also manage permalinks for **categories, tags, and custom taxonomies**.

Permalink Manager <a href="https://permalinkmanager.pro/docs/plugin-settings/canonical-redirects/">automatically redirects old and duplicate URLs</a> to the correct page, helping prevent broken links and 404 errors.

[Buy Permalink Manager Pro](https://permalinkmanager.pro/pricing/?utm_source=wordpressorg&utm_medium=readme) | [Quickstart guide](https://permalinkmanager.pro/docs/basics/custom-permalinks/?utm_source=wordpressorg&utm_medium=readme) | [Documentation](https://permalinkmanager.pro/docs/?utm_source=wordpressorg&utm_medium=readme)

= What You Can Do With Permalink Manager =

* **Change Single Permalinks**<br/>For a consistent and SEO-friendly URL structure, you may customize and <a href="https://permalinkmanager.pro/docs/basics/custom-permalinks/?utm_source=wordpressorg&utm_medium=readme">change the permalink</a> of each post, page, and custom post type item. *Categories, tags & custom taxonomies terms permalinks can be edited in Permalink Manager Pro.*
* **Customize URL Formats**<br/>The [Permastructures](https://permalinkmanager.pro/docs/basics/permastructures/?utm_source=wordpressorg&utm_medium=readme) settings let you override the default permalink structure and define a custom URL format using available tags, including `%custom_post_type%` and `%custom_taxonomy%`.
* **Remove the Post Type or Taxonomy Slug**<br />The plugin lets you [remove the base slug](https://permalinkmanager.pro/docs/tutorials/rewrite-base-pretty-permalinks/?utm_source=wordpressorg&utm_medium=readme), such as `/portfolio/` or `/event/`, from custom post type and taxonomy URLs.
* **Add Taxonomies to CPT URLs**<br />Add [category-based permalinks](https://permalinkmanager.pro/docs/tutorials/add-category-slug-wordpress-permalinks/?utm_source=wordpressorg&utm_medium=readme) to posts and custom post types, with support for primary categories set in your SEO plugin.
* **Translate Permalinks**<br/>With WPML or Polylang active, Permalink Manager allows you to [translate slugs and use a different URL structure for each language](https://permalinkmanager.pro/docs/tutorials/how-to-translate-permalinks/?utm_source=wordpressorg&utm_medium=readme).
* **Control Parent Slugs**<br/>[Remove or keep parent slugs](https://permalinkmanager.pro/docs/tutorials/wordpress-permalinks-structure/?utm_source=wordpressorg&utm_medium=readme) in permalinks for pages and other hierarchical post types.

= Additional Features Available in Permalink Manager Pro =

The free version includes all the essential features. Permalink Manager Pro adds **full support for custom taxonomies and WooCommerce**, along with a few additional functionalities. [See all Pro features](https://permalinkmanager.pro/features/?utm_source=wordpressorg&utm_medium=readme).

* **Custom Taxonomies Support**<br/>Taxonomies are fully supported in the premium version (categories, tags & custom taxonomies). You may adjust individual term permalinks or change them all at once using "Permastructures".
* **Full WooCommerce Support**<br/>Permalink Manager Pro may be used to change the URL addresses of WooCommerce products, tags, categories, and attributes. For example, you can [remove /product/ and /product-category/ from WooCommerce URLs](https://permalinkmanager.pro/docs/tutorials/remove-product-category-woocommerce-urls/?utm_source=wordpressorg&utm_medium=readme) or [add SKUs to product permalinks](https://permalinkmanager.pro/docs/woocommerce/sku-product-permalinks/?utm_source=wordpressorg&utm_medium=readme).
* **Custom Fields Support**<br/>[Add custom field values to your URLs](https://permalinkmanager.pro/docs/tutorials/how-to-use-custom-fields-inside-wordpress-permalinks/?utm_source=wordpressorg&utm_medium=readme) from ACF and other custom field plugins, with no coding required.
* **Extra Redirects**<br/>Define extra redirects (aliases) for any post, page, or term. Additionally, you may assign a redirect URL to each post/term, which will take users to any external URL address.
* **REST API**<br/>[Read and update custom permalinks and redirects](https://permalinkmanager.pro/docs/developer/rest-api-support/?utm_source=wordpressorg&utm_medium=readme) through the WordPress REST API.

== Installation ==

Go to `Plugins -> Add New` section from your admin account and search for `Permalink Manager`.

You can also install this plugin manually:

1. Download the plugin's ZIP archive and unzip it.
2. Copy the unzipped `permalink-manager` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Will the old URLs redirect to the new permalinks? =
 Yes. Permalink Manager automatically redirects the native permalinks (the URLs WordPress uses before the plugin is activated or after it is disabled) to the custom permalinks. Read more about the [plugin's redirect functions](https://permalinkmanager.pro/docs/basics/redirect-functions/?utm_source=wordpressorg&utm_medium=readme&utm_content=faq).

= How do I remove the post type slug from custom post type URLs? =
Set a new format without the base slug for that post type in Permastructures, then regenerate the existing permalinks. The [step-by-step tutorial](https://permalinkmanager.pro/docs/tutorials/rewrite-base-pretty-permalinks/?utm_source=wordpressorg&utm_medium=readme&utm_content=faq) walks through the process.

= How do I remove /product/ and /product-category/ from WooCommerce URLs? =
 This requires Permalink Manager Pro, which supports WooCommerce products, categories, tags and attributes. See [how to remove /product/ and /product-category/ from WooCommerce URLs](https://permalinkmanager.pro/docs/tutorials/remove-product-category-woocommerce-urls/?utm_source=wordpressorg&utm_medium=readme&utm_content=faq).

= Can I use a different permalink format for each language? =
 Yes, if you use WPML or Polylang. You can translate the slugs and set a separate Permastructure for each language. [Full instructions for multilingual permalinks](https://permalinkmanager.pro/docs/tutorials/how-to-translate-permalinks/?utm_source=wordpressorg&utm_medium=readme&utm_content=faq).

= Is Permalink Manager suitable for large websites? =
 Permalink Manager is optimized for standard WordPress websites that typically have fewer than a few thousand URLs. It stores all custom permalinks in a single database row, so the plugin can process URLs with just one database query and keep the impact on page load times to a minimum.

= Can I define different permalink formats per each language.
Yes, it is possible if you are using either WPML or Polylang. You can find <a href="https://permalinkmanager.pro/docs/tutorials/how-to-translate-permalinks/">the full instructions here</a>.

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

= 2.6.1 =
* Dev - Updated Permalink_Manager_Helper_Functions::encode_uri() so that percent-encoded characters are lowercased in the same way as native permalinks
* Dev - Minor code improvements

= 2.6.0 (August 9, 2026) =
* Security - Added an extra auth check for bulk tools, as suggested by Mo Akbari
* Dev - Refactored the Permalink_Manager_Permastructure_Functions() class
* Dev - Added REST API support (available in Permalink Manager Pro only)

= 2.5.5 (August 2, 2026) =
* Security - Fixed a reflected XSS vulnerability in the "Regenerate/reset" tool reported by Tijmen Kivit
* Fix - Taxonomy tags missing from "Additional Tags" in Permastructures editor when a taxonomy was registered for multiple post types
* Fix - Custom permalinks for MyListing taxonomies correctly map to the dedicated listing templates

= 2.5.4 (July 6, 2026) =
* Dev - Added "quick setup" wizard for first-time configuration

= 2.5.3.6 (July 2, 2026) =
* Fix - "Coupon Link" metabox is now hidden if "Coupons" post type is disabled in the plugin settings
* Fix - Fixed 'Warning: Undefined array key "show" in permalink-manager-admin-functions.php' file
* Dev - Sanitized and unslashed request inputs and resolved Plugin Check (PHPCS) warnings

= 2.5.3.5 (June 4, 2026) =
* Fix - Fixed 'Warning: Undefined array key "ID"' PHP warning in URI Editor for taxonomies
* Dev - "URI Editor" UI changes

= 2.5.3.4 (May 13, 2026) =
* Security - Patched a XSS vulnerability (CVE-2026-8494) in the "URI Editor" admin section
* Fix - Fixed a CSS bug causing misaligned input fields in WordPress 7.0

= 2.5.3.3 (May 6, 2026) =
* Dev - Tested for WordPress 7.0

= 2.5.3.2 (March 11, 2026) =
* Fix - The progress alert is no longer duplicated in "Regenerate/reset" tool
* Fix - Addressed a PHP deprecated warning when CLI is used

= 2.5.3.1 (February 16, 2026) =
* Dev - Updated "Force unique custom permalinks" function to respect language codes
* Fix - Resolved the "seems_utf8()" deprecation warning introduced in version 6.9.0.
* Fix - Addressed a PHP fatal error occurring when exporting via WP All Export in the Lite version

= 2.5.3 (February 10, 2026) =
* Dev - New “Select by language” filter in Tools, allowing custom permalinks to be regenerated for specific languages
* Dev - Partially refactored codebase to align with WordPress Coding Standards.
* Fix - The non-Latin characters are now correctly lowercased when the custom permalinks are sanitized
* Fix - The "Force unique custom permalinks" function is now triggered at the proper time
* Fix - Fixed a "Broken Access Control" security issue and added a "Debug Mode" toggle in the plugin settings

= 2.5.2.3/2.5.2.4 (January 7, 2026) =
* Fix - Hotfix for "Admin Columns Pro" - the custom permalinks are no longer overwritten
* Dev - Minor code improvements

= 2.5.2.2 (November 25, 2025) =
* Fix - Hotfix for "View Page" link in Block (Gutenberg) Editor
* Fix - PHP 8.4 warnings fixed (deprecated methods)
* Dev - Minor code improvements

= 2.5.2.1 (November 7, 2025) =
* Fix - Hotfix for Rankmath sitemap support (broken URLs)

= 2.5.2 (November 3, 2025) =
* Dev - New setting field - "Force unique custom permalinks"
* Dev - Minor code improvements
* Dev - Pro version code refactoring
* Fix - Hotfix for "Quick Edit" form
* Fix - Custom slugs function refactored

= 2.5.1.4 (September 3, 2025) =
* Fix - Restricted debug data access for custom fields in the admin dashboard

= 2.5.1.3 (July 22, 2025) =
* Fix - The url_to_postid() function works also when the language code is provided with URL
* Fix - Improvements for WPML's Advanced Translation Editor integration
* Dev - New setting field - "Edit permalinks in WPML's Translation Editor"
* Dev - Minor code improvements

= 2.5.1.2 (May 26, 2025) =
* Dev - WPML's Translation Editor compatibility code refactored
* Dev - The slashes in titles are now changed to dashes when custom permalinks are sanitized, just like they are in default WordPress slugs
* Fix - Improvements for WooCommerce REST API integration

= 2.5.1/2.5.1.1 (March 31, 2025) =
* Fix - Fixed fatal error for Bulk URI Editor with TranslatePress activated
* Dev - Minor code improvements
* Dev - Improved support for ACF's Relationship field
* Dev - Improved support for Polylang if multiple domains configured for different languages

= 2.5.0 (February 5, 2025) =
* Dev - Permastructure settings UI improvements
* Dev - Improved support for WP All Import
* Dev - Language filter added for URI Editor (WPML/Polylang)
* Fix - Improvements for MyListing theme integration

= 2.4.4.3 (December 16, 2024) =
* Dev - Code refactoring for "Bulk URI Editor"
* Dev - Code adjustments for MyListing support
* Dev - Minor code improvements
* Fix - Fallback for get_primary_term() function in older versions of The SEO Framework plugin

= 2.4.4.2 (October 16, 2024) =
* Dev - Improved support for REGEX formulas in "Find & Replace"
* Dev - Code adjustments to improve WPCS compliance
* Fix - Fixed search functionality bug in Bulk URI Editor

= 2.4.4.1 (August 28, 2024) =
* Fix - Fixed the deprecated warning for The SEO Framework plugin
* Fix - Fixed taxonomies' custom permalink import feature for WP All Import in Permalink Manager Lite
* Dev - Code adjustments for WPForo integration
* Dev - Minor code improvements

= 2.4.4 (July 24, 2024) =
* Fix - The native slug is updated when changed from permalink editor via Gutenberg/Block Editor
* Fix - The URLs with empty "attachment" endpoint returns 404 error
* Dev - The translated taxonomies' slugs are used when %taxonomy% tag is used in Permastructures
* Dev - Code refactoring & further improvements

= 2.4.3.4/2.4.3.5 (June 6, 2024) =
* Fix - Security fix for Permalink_Manager_UI_Elements->get_the_form() function
* Dev - The breadcrumbs support can only be enabled for content items that have custom permalinks

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