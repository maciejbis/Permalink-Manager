<?php

/**
* Display the settings page
*/
class Permalink_Manager_Settings extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['settings'] = array(
			'name'				=>	__('Settings', 'permalink-manager'),
			'function'    => array('class' => 'Permalink_Manager_Settings', 'method' => 'output')
		);

		return $admin_sections;
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array(false, false, true);
		$content_types  = (defined('PERMALINK_MANAGER_PRO')) ? array_merge($all_post_types, $all_taxonomies) : $all_post_types;

		$sections_and_fields = apply_filters('permalink-manager-settings-fields', array(
			'general' => array(
				'section_name' => __('General settings', 'permalink-manager'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'auto_update_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Auto-update URIs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the custom URIs will be automatically updated every time the post is saved or updated.', 'permalink-manager')
					),
					'case_insensitive_permalinks' => array(
						'type' => 'single_checkbox',
						'label' => __('Case insensitive URIs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Make the permalinks case-insensitive.', 'permalink-manager')
					)
				)
			),
			'miscellaneous' => array(
				'section_name' => __('SEO functions', 'permalink-manager'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'yoast_primary_term' => array(
						'type' => 'single_checkbox',
						'label' => __('Primay term/category support', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Used to generate default permalinks in pages, posts & custom post types. Works only when "Yoast SEO" plugin is enabled.', 'permalink-manager')
					),
					'yoast_attachment_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Attachment redirect support', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Support for redirect attachment URLs to parent post URL. Works only when "Yoast SEO Premium" plugin is enabled.', 'permalink-manager')
					),
					'canonical_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Canonical redirect', 'permalink-manager'),
						'input_class' => '',
						'description' => __('This function allows Wordpress to correct the URLs used by the visitors.', 'permalink-manager')
					),
					'redirect' => array(
						'type' => 'select',
						'label' => __('Redirect', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Disable', 'permalink-manager'), "301" => __('Enable "301 redirect"', 'permalink-manager'), "302" => __('Enable "302 redirect"', 'permalink-manager')),
						'description' => __('If enabled - the visitors will be redirected from native permalinks to your custom permalinks.<br /><strong>Only native permalinks & extra redirects will be redirected to new custom URIs</strong>.', 'permalink-manager')
					)
				)
			),
			'advanced' => array(
				'section_name' => __('Advanced settings', 'permalink-manager'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'setup_redirects' => array(
						'type' => 'single_checkbox',
						'label' => __('Add redirects for old URIs', 'permalink-manager'),
						'input_class' => '',
						'pro' => true,
						'disabled' => true,
						'description' => __('If enabled, the redirects will be automatially created for old custom permalinks.', 'permalink-manager')
					),
					'auto_remove_duplicates' => array(
						'type' => 'single_checkbox',
						'label' => __('Automatically remove duplicates', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the duplicated redirects & custom URIs will be automatically removed.', 'permalink-manager')
					),
					'force_custom_slugs' => array(
						'type' => 'single_checkbox',
						'label' => __('Force custom slugs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the native slugs in the defult URIs will be recreated from the post title.<br />This may cause URI duplicates when the post title is used more than once.', 'permalink-manager')
					),
					'disable_slug_appendix' => array(
						'type' => 'checkbox',
						'label' => __('Disable slug appendix', 'permalink-manager'),
						'choices' => $content_types,
						'description' => __('The slugs will not be automatically apended to the end of permastructure in the default permalinks for selected post types & taxonomies.<br />Works correctly only if <strong>"Force custom slugs" is disabled!</strong>', 'permalink-manager')
					),
					'decode_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Decode URIs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the permalinks with non-ASCII characters may not be recognized in older browsers versions (advanced users only).', 'permalink-manager')
					),
					'trailing_slashes' => array(
						'type' => 'select',
						'label' => __('Trailing slashes', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Use default settings', 'permalink-manager'), 1 => __('Always add trailing slashes', 'permalink-manager'), 2 => __('Always remove trailing slashes', 'permalink-manager')),
						'description' => __('This option can be used to alter the native settings and control if trailing slash should be added or removed from the end of posts & terms permalinks.', 'permalink-manager')
					)
				)
			)
		));

		$output = Permalink_Manager_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-manager' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-manager', 'name' => 'permalink_manager_options'));
		return $output;
	}
}
