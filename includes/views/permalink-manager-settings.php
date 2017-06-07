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
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array();

		$sections_and_fields = apply_filters('permalink-manager-settings-fields', array(
			'general' => array(
				'section_name' => __('General & Interface', 'permalink-manager'),
				'container' => 'row',
				'fields' => array(
					'auto_update_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Auto-update URIs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled the custom URIs will be automatically updated every time the post is saved or updated.', 'permalink-manager')
					),
					'force_custom_slugs' => array(
						'type' => 'single_checkbox',
						'label' => __('Force custom slugs', 'permalink-manager'),
						'input_class' => '',
						'description' => __('If enabled the native slugs in the defult URIs will be recreated from the post title.<br />This may cause URI duplicates when the post title is used more than once.', 'permalink-manager')
					)
				)
			),
			'miscellaneous' => array(
				'section_name' => __('Miscellaneous & SEO functions', 'permalink-manager'),
				'container' => 'row',
				'fields' => array(
					'yoast_primary_term' => array(
						'type' => 'single_checkbox',
						'label' => __('Primay term/category support', 'permalink-manager'),
						'input_class' => '',
						'description' => __('Used to generate default permalinks in pages, posts & custom post types. Works only when "Yoast SEO" plugin is enabled.', 'permalink-manager')
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
						'description' => __('If enabled - the visitors will be redirected from native permalinks to your custom permalinks. Please note that the redirects will work correctly only if native slug "post name" will not be changed.', 'permalink-manager')
					)
				)
			)
		));

		$output = Permalink_Manager_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-manager' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-manager', 'name' => 'permalink_manager_options'));
		return $output;
	}
}
