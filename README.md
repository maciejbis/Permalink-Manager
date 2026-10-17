# Permalink Manager

Permalink Manager is a WordPress plugin that lets you edit URLs directly from the admin dashboard. WordPress allows you to change a post's slug and choose a permalink structure for blog posts, but more advanced changes, such as removing /portfolio/ from custom post type URLs or adding a category name before a post slug, usually require custom rewrite rules.

Permalink Manager lets you make these changes without writing code. You can edit the full URL of any content item, define a separate URL structure for each content type, and automatically redirect visitors from previous URLs to the new ones.

This repository contains the source of **Permalink Manager Lite**, the free version published on [WordPress.org](https://wordpress.org/plugins/permalink-manager/) and developed by [Maciej Bis](https://maciejbis.net/?utm_source=github&utm_medium=readme).

[Quickstart guide](https://permalinkmanager.pro/docs/basics/custom-permalinks/?utm_source=github&utm_medium=readme) · [Documentation](https://permalinkmanager.pro/docs/?utm_source=github&utm_medium=readme) · [Lite vs Pro](https://permalinkmanager.pro/features/?utm_source=github&utm_medium=readme) · [Changelog](https://permalinkmanager.pro/changelog/?utm_source=github&utm_medium=readme)

## Start with these guides

If you are new to the plugin, these three guides explain how it works:

- **[How to set up custom permalinks in WordPress](https://permalinkmanager.pro/docs/basics/custom-permalinks/?utm_source=github&utm_medium=readme)**: the two ways of working with the plugin, editing one URL at a time or changing the format for a whole content type, and where each option is in the dashboard.
- **[How to edit permalink structures for post types and taxonomies](https://permalinkmanager.pro/docs/basics/permastructures/?utm_source=github&utm_medium=readme)**: build a URL format from tags such as the post slug, a category or a custom taxonomy term, and apply it to new or existing content.
- **[How redirects work in Permalink Manager](https://permalinkmanager.pro/docs/basics/redirect-functions/?utm_source=github&utm_medium=readme)**: what happens to the old address when a permalink changes, and how visitors and search engines are sent to the new one.

## What you can do with it

| If you want to… | Read |
|---|---|
| Remove the base from custom post type or taxonomy URLs (`/portfolio/my-project/` → `/my-project/`) | [Remove custom post type and taxonomy slugs](https://permalinkmanager.pro/docs/tutorials/rewrite-base-pretty-permalinks/?utm_source=github&utm_medium=readme) |
| Put a category or custom taxonomy term in custom post type URLs | [Add taxonomy slugs to custom post type permalinks](https://permalinkmanager.pro/docs/tutorials/add-category-slug-wordpress-permalinks/?utm_source=github&utm_medium=readme) |
| Shorten nested URLs by removing parent slugs | [Remove the parent slug from child permalinks](https://permalinkmanager.pro/docs/tutorials/wordpress-permalinks-structure/?utm_source=github&utm_medium=readme) |
| Use a different URL structure for each language (WPML, Polylang) | [Multilingual permalinks](https://permalinkmanager.pro/docs/tutorials/how-to-translate-permalinks/?utm_source=github&utm_medium=readme) |
| Get rid of the "-2" WordPress adds to repeated slugs | [Allow duplicate slugs](https://permalinkmanager.pro/docs/seo-tips/duplicate-the-slugs/?utm_source=github&utm_medium=readme) |
| Change WooCommerce product and category URLs | [WooCommerce permalinks guide](https://permalinkmanager.pro/docs/tutorials/woocommerce-permalinks-manager/?utm_source=github&utm_medium=readme) and [removing /product/ and /product-category/](https://permalinkmanager.pro/docs/tutorials/remove-product-category-woocommerce-urls/?utm_source=github&utm_medium=readme) |
| Add custom field values to URLs (ACF, Pods, Toolset, Meta Box) | [Custom fields in WordPress permalinks](https://permalinkmanager.pro/docs/tutorials/how-to-use-custom-fields-inside-wordpress-permalinks/?utm_source=github&utm_medium=readme) |

## Lite and Pro

The free version covers posts, pages and custom post types. It includes single-URL editing, permalink structures, removing post type bases, taxonomy terms in custom post type URLs, translated permalinks and automatic redirects.

[Permalink Manager Pro](https://permalinkmanager.pro/?utm_source=github&utm_medium=readme) adds:

- URLs for categories, tags and custom taxonomy terms.
- Full WooCommerce support: product categories, tags and attributes, SKUs in URLs and coupon URLs.
- Custom fields in permalinks.
- Extra and external redirects.
- REST API endpoints.

The [feature comparison](https://permalinkmanager.pro/features/?utm_source=github&utm_medium=readme) lists every difference.

## Installation

**From WordPress.org (recommended):** in your dashboard, go to `Plugins → Add New`, search for "Permalink Manager", then install and activate it.

**From this repository:**

1. Download the repository as a ZIP file and unzip it.
2. Rename the folder to `permalink-manager`. GitHub names it `Permalink-Manager-master` by default, and keeping the WordPress.org folder name means future updates go to the same plugin.
3. Copy it to `/wp-content/plugins/` and activate it from the `Plugins` screen.

After activation, the plugin's tools and settings are under `Tools → Permalink Manager`.

**Requirements:** WordPress 5.3 or newer and PHP 5.4 or newer. The current release is tested up to WordPress 7.1.

## For developers

- **[API functions](https://permalinkmanager.pro/docs/filters-hooks/custom-permalink-functions/?utm_source=github&utm_medium=readme)**: save, update and regenerate custom permalinks from your own code.
- **[Filters for custom permalinks](https://permalinkmanager.pro/docs/filters-hooks/how-to-filter-the-default-custom-permalinks/?utm_source=github&utm_medium=readme)**: change the default permalink format for selected posts or terms and work with the stored permalink and redirect arrays.
- **[REST API](https://permalinkmanager.pro/docs/developer/rest-api-support/?utm_source=github&utm_medium=readme)** (Pro): read and update custom permalinks and redirects over the WordPress REST API.
- **[Performance and limitations](https://permalinkmanager.pro/docs/basics/performance/?utm_source=github&utm_medium=readme)**: custom permalinks are stored in a single database row, which keeps lookups fast. Read this before using the plugin on a site with tens of thousands of URLs.

It also works with Yoast SEO, Rank Math and SEOPress, keeping canonical URLs, sitemaps and breadcrumbs in line with your custom permalinks ([details](https://permalinkmanager.pro/docs/seo-plugins/?utm_source=github&utm_medium=readme)). Compatibility with other plugins and themes is covered in the [third-party support guide](https://permalinkmanager.pro/docs/basics/third-party-plugins-themes-support/?utm_source=github&utm_medium=readme).

## Support and bug reports

- First, check the [list of common issues](https://permalinkmanager.pro/docs/troubleshooting/common-issues/?utm_source=github&utm_medium=readme).
- For help with the free version, use the [WordPress.org support forum](https://wordpress.org/support/plugin/permalink-manager/).
- If you have found a bug in the code, you are welcome to open an issue here. Include your WordPress, PHP and plugin versions and the steps to reproduce it.
- Pro users can [contact the developer directly](https://permalinkmanager.pro/contact/?utm_source=github&utm_medium=readme).

## License

Permalink Manager is free software released under the [GNU General Public License v2](LICENSE.txt).
