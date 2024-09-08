<?php

/**
 * Display the settings page
 */
class Permalink_Manager_Settings {

	public function __construct() {
		add_filter( 'permalink_manager_sections', array( $this, 'add_admin_section' ), 3 );
	}

	/**
	 * Add a new section to the Permalink Manager UI
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_admin_section( $admin_sections ) {
		$admin_sections['settings'] = array(
			'name'     => __( 'Settings', 'permalink-manager' ),
			'function' => array( 'class' => 'Permalink_Manager_Settings', 'method' => 'output' )
		);

		return $admin_sections;
	}

	/**
	 * Get the array with settings and render the HTML output
	 *
	 * @return string
	 */
	public function output() {
		// Get all registered post types & taxonomies
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array( null, null, true );
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array( false, false, true );
		$content_types  = ( defined( 'PERMALINK_MANAGER_PRO' ) ) ? array( 'post_types' => $all_post_types, 'taxonomies' => $all_taxonomies ) : array( 'post_types' => $all_post_types );

		$sections_and_fields = apply_filters( 'permalink_manager_settings_fields', array(
			'general'       => array(
				'section_name' => __( 'General settings', 'permalink-manager' ),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'auto_update_uris'   => array(
						'type'        => 'select',
						'label'       => __( 'Permalink update', 'permalink-manager' ),
						'input_class' => '',
						'choices'     => array( 0 => __( 'Don\'t auto-update custom permalinks (default mode)', 'permalink-manager' ), 1 => __( 'Auto-update custom permalinks', 'permalink-manager' ), 2 => __( 'Disable custom permalinks for new posts/terms', 'permalink-manager' ) ),
						'description' => sprintf( '<strong>%s</strong><br />%s<br />%s', __( 'Custom permalinks in Permalink Manager will not be updated automatically to avoid overwriting individual modifications. If necessary, you can opt to change them every time a post/term is saved to match the Permastructure settings default format.', 'permalink-manager' ), __( 'If you select the third option, Permalink Manager will not generate new custom permalinks for newly added items. This lets you choose which pages will have custom permalinks and which will continue to use the original WordPress permalinks.', 'permalink-manager' ), __( 'The Permalink Manager editor allows you to select a different mode and override this global settings for specific posts and terms.', 'permalink-manager' ) )
					),
					'force_custom_slugs' => array(
						'type'        => 'select',
						'label'       => __( 'Slugs mode', 'permalink-manager' ),
						'input_class' => 'settings-select',
						'choices'     => array( 0 => __( 'Use WordPress slugs (default mode)', 'permalink-manager' ), 1 => __( 'Use actual titles instead of WordPress slugs', 'permalink-manager' ), 2 => __( 'Inherit parents\' slugs', 'permalink-manager' ) ),
						'description' => sprintf( '%s<br />%s<br />%s', __( '<strong>Permalink Manager can generate custom permalinks using either WordPress slugs or actual titles.</strong>', 'permalink-manager' ), __( 'A slug is a permalink component that identifies a certain page. For example, "<em>shop</em>" and "<em>sample-product</em>" are two slugs in the permalink "<em>shop/sample-product</em>".', 'permalink-manager' ), __( 'WordPress slugs are generated automatically from the first title and remain the same even if the title is changed.', 'permalink-manager' ) )
					),
					'trailing_slashes'   => array(
						'type'        => 'select',
						'label'       => __( 'Trailing slashes', 'permalink-manager' ),
						'input_class' => 'settings-select',
						'choices'     => array( 0 => __( 'Use default settings', 'permalink-manager' ), 1 => __( 'Add trailing slashes', 'permalink-manager' ), 2 => __( 'Remove trailing slashes', 'permalink-manager' ) ),
						'description' => sprintf( '<strong>%s</strong><br />%s<br />%s', __( 'This option can be used to alter the native settings and control if trailing slash should be added or removed from the end of posts & terms permalinks.', 'permalink-manager' ), __( 'You can use this feature to either add or remove the slashes from end of WordPress permalinks.', 'permalink-manager' ), __( 'Please go to "<em>Redirect settings -> Trailing slashes redirect</em>" to force the trailing slashes mode with redirect.', 'permalink-manager' ) )
					)
				)
			),
			'redirect'      => array(
				'section_name' => __( 'Redirect settings', 'permalink-manager' ),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'canonical_redirect'                         => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Canonical redirect', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br />%s', __( '<strong>Canonical redirect allows WordPress to "correct" the requested URL and redirect visitor to the canonical permalink.</strong>', 'permalink-manager' ), __( 'Permalink Manager uses canonical redirect to avoid "duplicate content" SEO issues by redirecting different permalinks that lead to the same content to a custom permalink set in the plugin.', 'permalink-manager' ) )
					),
					/*'endpoint_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Redirect with endpoints', 'permalink-manager'),
						'input_class' => '',
						'description' => sprintf('%s',
							__('<strong>Please enable this option if you would like to copy the endpoint from source URL to the target URL during the canonical redirect.</strong>', 'permalink-manager')
						)
					),*/ 'old_slug_redirect' => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Old slug redirect', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br />%s', __( '<strong>Old slug redirect is used by WordPress to provide a fallback for old version of slugs after they are changed.</strong>', 'permalink-manager' ), __( 'If enabled, the visitors trying to access the URL with the old slug will be redirected to the canonical permalink.', 'permalink-manager' ) )
					),
					'extra_redirects'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Extra redirects (aliases)', 'permalink-manager' ),
						'input_class' => '',
						'pro'         => true,
						'disabled'    => true,
						'description' => sprintf( '%s<br /><strong>%s</strong>', __( 'Please enable this option if you would like to manage additional custom redirects (aliases) in URI Editor for individual posts & terms.', 'permalink-manager' ), __( 'You can disable this feature if you use another plugin to control the redirects, eg. Yoast SEO Premium or Redirection.', 'permalink-manager' ) )
					),
					'setup_redirects'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Save old custom permalinks as extra redirects', 'permalink-manager' ),
						'input_class' => '',
						'pro'         => true,
						'disabled'    => true,
						'description' => sprintf( '%s<br /><strong>%s</strong>', __( 'If enabled, Permalink Manager will save the "extra redirect" for earlier version of custom permalink after you change it (eg. with URI Editor or Regenerate/reset tool).', 'permalink-manager' ), __( 'Please note that the new redirects will be saved only if "Extra redirects (aliases)" option is turned on above.', 'permalink-manager' ) )
					),
					'trailing_slashes_redirect'                  => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Trailing slashes redirect', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br /><strong>%s</strong>', __( 'Permalink Manager can force the trailing slashes settings in the custom permalinks with redirect.', 'permalink-manager' ), __( 'Please go to "<em>General settings -> Trailing slashes</em>" to choose if trailing slashes should be added or removed from WordPress permalinks.', 'permalink-manager' ) )
					),
					'copy_query_redirect'                        => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Redirect with query parameters', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br />%s', __( 'If enabled, the query parameters will be copied to the target URL when the redirect is triggered.', 'permalink-manager' ), __( 'Example: <em>https://example.com/product/old-product-url/<strong>?discount-code=blackfriday</strong></em> => <em>https://example.com/new-product-url/<strong>?discount-code=blackfriday</strong></em>', 'permalink-manager' ) )
					),
					'sslwww_redirect'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Force HTTPS/WWW', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br />%s', __( '<strong>You can use Permalink Manager to force SSL or "www" prefix in WordPress permalinks.</strong>', 'permalink-manager' ), __( 'Please disable it if you encounter any redirect loop issues.', 'permalink-manager' ) )
					),
					'redirect'                                   => array(
						'type'        => 'select',
						'label'       => __( 'Redirect mode', 'permalink-manager' ),
						'input_class' => 'settings-select',
						'choices'     => array( 0 => __( 'Disable (Permalink Manager redirect functions)', 'permalink-manager' ), "301" => __( '301 redirect', 'permalink-manager' ), "302" => __( '302 redirect', 'permalink-manager' ) ),
						'description' => sprintf( '%s<br /><strong>%s</strong>', __( 'Permalink Manager includes a set of hooks that allow to extend the redirect functions used natively by WordPress to avoid 404 errors.', 'permalink-manager' ), __( 'You can disable this feature if you do not want Permalink Manager to trigger any additional redirect functions at all.', 'permalink-manager' ) )
					)
				)
			),
			'exclusion'     => array(
				'section_name' => __( 'Exclusion settings', 'permalink-manager' ),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'partial_disable'                        => array(
						'type'        => 'checkbox',
						'label'       => __( 'Exclude content types', 'permalink-manager' ),
						'choices'     => $content_types,
						'description' => __( 'Permalink Manager will ignore and not filter the custom permalinks of all selected above post types & taxonomies.', 'permalink-manager' )
					),
					'partial_disable_strict'                 => array(
						'type'        => 'single_checkbox',
						'label'       => __( '"Exclude content types" strict mode', 'permalink-manager' ),
						'description' => __( 'If this option is enabled, any custom post types and taxonomies with the "<strong>query_var</strong>" and "<strong>rewrite</strong>" attributes set to "<em>false</em>" will be excluded from the plugin and hence will not be shown in the "<em>Exclude content types</em>" options.', 'permalink-manager' )
					),
					'exclude_post_ids'                       => array(
						'type'        => 'text',
						'label'       => __( 'Exclude posts/pages by ID', 'permalink-manager' ),
						'input_class' => 'widefat',
						'description' => sprintf( '%s<br />%s', __( 'Specify the IDs of posts/pages for which you want to preserve the original WordPress URLs instead of applying custom permalinks.', 'permalink-manager' ), __( 'Enter single IDs (e.g., "<em>4, 8, 15, 16</em>"), ID ranges (e.g., "<em>23-42</em>"), or a combination of both.', 'permalink-manager' ) )
					),
					'exclude_term_ids'                       => array(
						'type'        => 'text',
						'label'       => __( 'Exclude terms by ID', 'permalink-manager' ),
						'input_class' => 'widefat',
						'pro'         => true,
						'disabled'    => true,
						'description' => sprintf( '%s<br />%s', __( 'Specify the IDs of categories/terms for which you want to preserve the original WordPress URLs instead of applying custom permalinks.', 'permalink-manager' ), __( 'Enter single IDs (e.g., "<em>4, 8, 15, 16</em>"), ID ranges (e.g., "<em>23-42</em>"), or a combination of both.', 'permalink-manager' ) )
					),
					/*'exclude_query_vars'     => array(
						'type'        => 'text',
						'label'       => __( 'Non-redirectable query variables', 'permalink-manager' ),
						'placeholder' => 'e.g. um_user, um_tab',
						'input_class' => 'widefat',
						'description' => __( 'Use this field to exclude specific query variables from triggering a redirect when Permalink Manager detects permalinks. To prevent the redirect on dynamic sections (e.g. profile tabs), you can enter the variable used by the third-party plugin (e.g. <em>um_user</em> for Ultimate Member plugin).', 'permalink-manager' )
					),*/ 'ignore_drafts' => array(
						'type'        => 'select',
						'label'       => __( 'Exclude drafts & pending posts', 'permalink-manager' ),
						'choices'     => array( 0 => __( 'Do not exclude', 'permalink-manager' ), 1 => __( 'Exclude drafts', 'permalink-manager' ), 2 => __( 'Exclude drafts & pending posts', 'permalink-manager' ) ),
						'description' => __( 'If enabled, custom permalinks for posts marked as "draft" or "pending" will not be created.', 'permalink-manager' )
					)
				)
			),
			'third_parties' => array(
				'section_name' => __( 'Third party plugins', 'permalink-manager' ),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'fix_language_mismatch' => array(
						'type'         => 'select',
						'label'        => __( 'WPML/Polylang fix language mismatch', 'permalink-manager' ),
						'input_class'  => '',
						'choices'      => array( 0 => __( 'Disable', 'permalink-manager' ), 1 => __( 'Load the language variant of the requested page', 'permalink-manager' ), 2 => __( 'Redirect to the language variant of the requested page', 'permalink-manager' ) ),
						'class_exists' => array( 'SitePress', 'Polylang' ),
						'description'  => __( 'The plugin may load the relevant translation or trigger the canonical redirect when a custom permalink is detected, but the URL language code does not match the detected item\'s language code. ', 'permalink-manager' )
					),
					'wpml_support'          => array(
						'type'         => 'single_checkbox',
						'label'        => __( 'WPML compatibility functions', 'permalink-manager' ),
						'input_class'  => '',
						'class_exists' => array( 'SitePress' ),
						'description'  => __( 'Please disable this feature if the language code in the custom permalinks is incorrect.', 'permalink-manager' )
					),
					'pmxi_support'          => array(
						'type'         => 'single_checkbox',
						'label'        => __( 'WP All Import/Export support', 'permalink-manager' ),
						'input_class'  => '',
						'class_exists' => array( 'PMXI_Plugin', 'PMXE_Plugin' ),
						'description'  => __( 'If disabled, the custom permalinks <strong>will not be saved</strong> for the posts imported with WP All Import plugin.', 'permalink-manager' )
					),
					'um_support'            => array(
						'type'         => 'single_checkbox',
						'label'        => __( 'Ultimate Member support', 'permalink-manager' ),
						'input_class'  => '',
						'class_exists' => 'UM',
						'description'  => __( 'If enabled, Permalink Manager will detect the additional Ultimate Member pages (eg. "account" sections).', 'permalink-manager' )
					),
					'rankmath_redirect'     => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'RankMath\'s "Redirections" fix redirect conflict', 'permalink-manager' ),
						'input_class' => '',
						'description' => sprintf( '%s<br />%s', __( 'If enabled, the Permalink Manager plugin <strong>will stop a redirect set with the RankMath SEO plugin\'s "Source URLs"</strong> if this URL is already being used as a custom permalink by any post or term.', 'permalink-manager' ), __( 'This prevents redirect loops when both plugins manage redirects on the same URL.', 'permalink-manager' ) )
					),
					'yoast_breadcrumbs'     => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Breadcrumbs support', 'permalink-manager' ),
						'input_class' => '',
						'description' => __( 'If enabled, the HTML breadcrumbs will be filtered by Permalink Manager to mimic the current URL structure.<br />Works with: <strong>WooCommerce, Yoast SEO, Slim Seo, RankMath and SEOPress</strong> breadcrumbs.', 'permalink-manager' )
					),
					'primary_category'      => array(
						'type'        => 'single_checkbox',
						'label'       => __( '"Primary category" support', 'permalink-manager' ),
						'input_class' => '',
						'description' => __( 'If enabled, Permalink Manager will use the "primary category" for the default post permalinks.<br />Works with: <strong>Yoast SEO, The SEO Framework, RankMath and SEOPress</strong>.', 'permalink-manager' )
					),
				)
			),
			'advanced'      => array(
				'section_name' => __( 'Advanced settings', 'permalink-manager' ),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'show_native_slug_field'    => array(
						'type'  => 'single_checkbox',
						'label' => __( 'Show "Native slug" field in URI Editor', 'permalink-manager' )
					),
					'pagination_redirect' => array(
						'type'        => 'select',
						'label'       => __( 'Handling non-existent pagination pages', 'permalink-manager' ),
						'choices'     => array( 0 => __( 'Stop canonical redirect without forcing "404" status code', 'permalink-manager' ), 1 => __( 'Stop canonical redirect and force "404" status code', 'permalink-manager' ), 2 => __( 'Allow canonical redirect without forcing "404" status code', 'permalink-manager' ) ),
						'description' => __( 'Decide if you would like the plugin to force a "404" error or allow canonical redirect for pagination pages that do not exist.<br /><strong>If you experience any issues with pagination pages, please select the first option.</strong>', 'permalink-manager' )
					),
					'disable_slug_sanitization' => array(
						'type'        => 'select',
						'label'       => __( 'Strip special characters', 'permalink-manager' ),
						'input_class' => 'settings-select',
						'choices'     => array( 0 => __( 'Yes, use native settings', 'permalink-manager' ), 1 => __( 'No, keep special characters (.,|_+) in the slugs', 'permalink-manager' ) ),
						'description' => __( 'If enabled only alphanumeric characters, underscores and dashes will be allowed for post/term slugs.', 'permalink-manager' )
					),
					'keep_accents'              => array(
						'type'        => 'select',
						'label'       => __( 'Convert accented letters', 'permalink-manager' ),
						'input_class' => 'settings-select',
						'choices'     => array( 0 => __( 'Yes, use native settings', 'permalink-manager' ), 1 => __( 'No, keep accented letters in the slugs', 'permalink-manager' ) ),
						'description' => __( 'If enabled, all the accented letters will be replaced with their non-accented equivalent (eg. Å => A, Æ => AE, Ø => O, Ć => C).', 'permalink-manager' )
					),
					'edit_uris_cap'             => array(
						'type'        => 'select',
						'label'       => __( 'URI Editor role capability', 'permalink-manager' ),
						'choices'     => array( 'edit_theme_options' => __( 'Administrator (edit_theme_options)', 'permalink-manager' ), 'publish_pages' => __( 'Editor (publish_pages)', 'permalink-manager' ), 'publish_posts' => __( 'Author (publish_posts)', 'permalink-manager' ), 'edit_posts' => __( 'Contributor (edit_posts)', 'permalink-manager' ) ),
						'description' => sprintf( __( 'Only the users who have selected capability will be able to access URI Editor.<br />The list of capabilities <a href="%s" target="_blank">can be found here</a>.', 'permalink-manager' ), 'https://wordpress.org/support/article/roles-and-capabilities/#capability-vs-role-table' )
					),
					'auto_fix_duplicates'       => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Automatically fix broken URIs', 'permalink-manager' ),
						'description' => sprintf( '%s', __( 'Enable this option if you would like to automatically remove redundant permalinks & duplicated redirects during page load.', 'permalink-manager' ) )
					),
					'debug_mode'       => array(
						'type'        => 'single_checkbox',
						'label'       => __( 'Debug mode', 'permalink-manager' ),
						'description' => sprintf( '%s', __( 'This option activates permalink detection and redirect debug functions. To access them add "<em>?debug_url=1</em>" and "<em>?debug_redirect=1</em>" to the checked URLs.', 'permalink-manager' ) )
					)
				)
			)
		) );

		return Permalink_Manager_UI_Elements::get_the_form( $sections_and_fields, 'tabs', array( 'text' => __( 'Save settings', 'permalink-manager' ), 'class' => 'primary margin-top' ), '', array( 'action' => 'permalink-manager', 'name' => 'permalink_manager_options' ) );
	}
}
