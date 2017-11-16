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
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array(null, null, true);
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array(false, false, false, true);
		$content_types  = (defined('PERMALINK_MANAGER_PRO')) ? array('post_types' => $all_post_types, 'taxonomies' => $all_taxonomies) : array('post_types' => $all_post_types);

		$sections_and_fields = apply_filters('permalink-manager-settings-fields', array(
			'general' => array(
				'section_name' => __('General settings', 'permalink-manager'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'auto_update_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Auto-update permalinks', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the custom permalinks will be automatically updated every time the post is saved or updated.', 'permalink-manager')
					),
					'case_insensitive_permalinks' => array(
						'type' => 'single_checkbox',
						'label' => __('Case insensitive permalinks', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Make all the permalinks case-insensitive.', 'permalink-manager')
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
						'label' => __('Primary term/category support', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Used to generate default permalinks in pages, posts & custom post types. Works only when "Yoast SEO" plugin is enabled.', 'permalink-manager')
					),
					'yoast_attachment_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Attachment redirect support', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Redirect attachment URLs to their parent posts. Works only when "Yoast SEO Premium" plugin is enabled.', 'permalink-manager')
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
						'description' => __('If enabled - the visitors will be redirected from native permalinks to your custom permalinks.<br /><strong>Only native permalinks & extra redirects will be redirected to new custom permalinks</strong>.', 'permalink-manager')
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
						'label' => __('Auto-create "Extra Redirects" for old permalinks', 'permalink-manager'),
						'input_class' => '',
						'pro' => true,
						'disabled' => true,
						'description' => __('If enabled, the redirects will be automatially created for old custom permalinks, after posts or terms are updated.', 'permalink-manager')
					),
					'auto_remove_duplicates' => array(
						'type' => 'single_checkbox',
						'label' => __('Automatically remove duplicates', 'permalink-manager'),
						'input_class' => '',
						'description' => sprintf(__('If enabled, the duplicated redirects & custom permalinks will be automatically removed if they were previously assigned to removed posts or terms.<br />To manually remove the duplicates please go <a href="%s">to this page</a>.', 'permalink-manager'), admin_url('tools.php?page=permalink-manager&section=tools&subsection=duplicates'))
					),
					'force_custom_slugs' => array(
						'type' => 'single_checkbox',
						'label' => __('Force custom slugs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the slugs in the default custom permalinks will be recreated from the post titles.<br />This may cause permalinks duplicates when the post or term title is used more than once.', 'permalink-manager')
					),
					/*'deep_detect' => array(
						'type' => 'single_checkbox',
						'label' => __('Enable "Deep detect"', 'permalink-manager'),
						'description' => __('Please keep it enabled if your custom URIs end with numerals, e.g. <strong>example.com/projects/20171025</strong> or if $_GET parameters should be detected as endpoints e.g. <strong>?page=1</strong>.', 'permalink-manager')
					),
					'decode_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Decode URIs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled, the permalinks with non-ASCII characters may not be recognized in older browsers versions (advanced users only).', 'permalink-manager')
					),*/
					'trailing_slashes' => array(
						'type' => 'select',
						'label' => __('Trailing slashes', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Use default settings', 'permalink-manager'), 1 => __('Add trailing slashes', 'permalink-manager'), 10 => __('Add trailing slashes (+ auto-redirect links without them)', 'permalink-manager'), 2 => __('Remove trailing slashes', 'permalink-manager'), 20 => __('Remove trailing slashes (+ auto-redirect links with them)', 'permalink-manager'),),
						'description' => __('This option can be used to alter the native settings and control if trailing slash should be added or removed from the end of posts & terms permalinks.', 'permalink-manager')
					),
					'partial_disable' => array(
						'type' => 'checkbox',
						'label' => __('Disable Permalink Manager functionalities', 'permalink-manager'),
						'choices' => $content_types,
						'description' => __('Select the post types & taxonomies where the functionalities of Permalink Manager should be completely disabled.', 'permalink-manager')
					),
				)
			)
		));

		$output = Permalink_Manager_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-manager' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-manager', 'name' => 'permalink_manager_options'));
		return $output;
	}
}
