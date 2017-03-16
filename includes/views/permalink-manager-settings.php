<?php

/**
* Display the settings page
*/
class Permalink_Manager_Settings extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
		add_filter( 'permalink-manager-options', array($this, 'default_settings'), 9 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['settings'] = array(
			'name'				=>	__('Settings', 'permalink-manager'),
			'function'    => array('class' => 'Permalink_Manager_Settings', 'method' => 'output')
		);

		return $admin_sections;
	}

	/**
	* Set the initial/default settings
	*/
	public function default_settings($settings) {
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		$default_settings = apply_filters('permalink-manager-default-options', array(
			'screen-options' => array(
				'per_page' => 20,
				'post_statuses' => array('publish'),
				'post_types' => $all_post_types,
				'taxonomies' => $all_taxonomies
			),
			'miscellaneous' => array(
				'yoast_primary_term' => 1,
				'redirect' => "302",
				'canonical_redirect' => 1,
			)
		));

		// Apply the default settings (if empty values) in all settings sections
		$final_settings = array();
		foreach($default_settings as $section => $fields) {
			$final_settings[$section] = (isset($settings[$section])) ? array_replace($fields, $settings[$section]) : $fields;
		}

		return $final_settings;
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		$sections_and_fields = apply_filters('permalink-manager-settings-fields', array(
			'screen-options' => array(
				'section_name' => __('Display settings', 'permalink-manager'),
				'description' => __('Adjust the data displayed in "Permalink Editor" section.', 'permalink-manager'),
				'container' => 'row',
				'fields' => array(
					'per_page' => array(
						'type' => 'number',
						'label' => __('Per page', 'permalink-manager'),
						'input_class' => 'settings-select'
					),
					'post_statuses' => array(
						'type' => 'checkbox',
						'label' => __('Post statuses', 'permalink-manager'),
						'choices' => $all_post_statuses_array,
						'select_all' => '',
						'unselect_all' => '',
					),
					'post_types' => array(
						'type' => 'checkbox',
						'label' => __('Post types', 'permalink-manager'),
						'choices' => $all_post_types,
						'select_all' => '',
						'unselect_all' => '',
					)
				)
			),
			'miscellaneous' => array(
				'section_name' => __('Miscellaneous & SEO functions', 'permalink-manager'),
				'container' => 'row',
				'fields' => array(
					'yoast_primary_term' => array(
						'type' => 'select',
						'label' => __('Primay term/category support', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(1 => __('Enable', 'permalink-manager'), 0 => __('Disable', 'permalink-manager')),
						'description' => __('Used to generate default permalinks in pages, posts & custom post types. Works only when "Yoast SEO" plugin is enabled.', 'permalink-manager')
					),
					'redirect' => array(
						'type' => 'select',
						'label' => __('Redirect', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Disable', 'permalink-manager'), "301" => __('Enable "301 redirect"', 'permalink-manager'), "302" => __('Enable "302 redirect"', 'permalink-manager')),
						'description' => __('If enabled - the visitors will be redirected from native permalinks to your custom permalinks. Please note that the redirects will work correctly only if native slug "post name" will not be changed.', 'permalink-manager')
					),
					'canonical_redirect' => array(
						'type' => 'select',
						'label' => __('Canonical redirect', 'permalink-manager'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Disable', 'permalink-manager'), 1 => __('Enable', 'permalink-manager')),
						'description' => __('This function allows Wordpress to correct the URLs used by the visitors.', 'permalink-manager')
					)
				)
			)
		));
						
		$output = Permalink_Manager_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-manager' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-manager', 'name' => 'permalink_manager_options'));
		return $output;
	}
}
