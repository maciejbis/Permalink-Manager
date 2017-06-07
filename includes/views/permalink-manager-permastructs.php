<?php

/**
* Display the page where the slugs could be regenerated or replaced
*/
class Permalink_Manager_Permastructs extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
	}

	public function add_admin_section($admin_sections) {

		$admin_sections['permastructs'] = array(
			'name'				=>	__('Permastructures', 'permalink-manager'),
			'function'    => array('class' => 'Permalink_Manager_Permastructs', 'method' => 'output')
		);

		return $admin_sections;
	}

	public function get_fields() {
		global $permalink_manager_permastructs;

		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array('full');
		$woocommerce_icon = "<i class=\"woocommerce-icon woocommerce-cart\"></i>";

		// 1. Get notes
		$post_types_notes = wpautop(sprintf(__('All available <a href="%s" target="_blank">structure tags</a> for <strong>post types</strong> allowed are listed below.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags"));
		$post_types_notes .= Permalink_Manager_Helper_Functions::get_all_structure_tags();
		$post_types_notes .= wpautop(sprintf(__('Please note that some of them can be used only for particular post types\' settings.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags"));

		// 2. Get fields
		$fields = array(
			'post_types' => array(
				'section_name' => __('Post types', 'permalink-manager'),
				'container' => 'row',
				'append_content' => $post_types_notes,
				'fields' => array()
			),
			'taxonomies' => array(
				'section_name' => __('Taxonomies', 'permalink-manager'),
				'container' => 'row',
				'append_content' => Permalink_Manager_Admin_Functions::pro_text(),
				'fields' => array()
			)
		);

		// 2. Woocommerce support
		if(class_exists('WooCommerce')) {
			$fields['woocommerce'] = array(
				'section_name' => "{$woocommerce_icon} " . __('WooCommerce', 'permalink-manager'),
				'container' => 'row',
				'append_content' => Permalink_Manager_Admin_Functions::pro_text(),
				'fields' => array()
			);
		}

		// 3. Append fields for all post types
		foreach($all_post_types as $post_type) {

			$default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type['name'], true);
			$current_permastruct = isset($permalink_manager_permastructs['post_types'][$post_type['name']]) ? $permalink_manager_permastructs['post_types'][$post_type['name']] : $default_permastruct;

			$fields["post_types"]["fields"][$post_type['name']] = array(
				'label' => $post_type['label'],
				'container' => 'row',
				'input_class' => '',
				'value' => $current_permastruct,
				'placeholder' => $default_permastruct,
				'type' => 'permastruct'
			);
		}

		return apply_filters('permalink-manager-permastructs-fields', $fields);
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		global $permalink_manager_permastructs;

		$output = wpautop(sprintf(__('This tool allows to overwrite the native permalink settings (permastructures) that can be edited <a href="%s" target="_blank">here</a>.', 'bis'), "#"));
		$output .= wpautop(__('This tool <strong>automatically appends the slug to the end of permastructure</strong>, so there is no need to use them within the fields.', 'bis'));
		$output .= wpautop(__('Each permastructure should be unique to prevent the problem with overlapping URLs.', 'bis'));
		$output .= Permalink_Manager_Admin_Functions::get_the_form(self::get_fields(), '', array('text' => __( 'Save permastructures', 'permalink-manager' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-manager', 'name' => 'permalink_manager_permastructs'));

		return $output;
	}

}
