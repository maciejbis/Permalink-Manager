<?php

/**
* Display slug editor for Posts, Pages & Custom Post Types
*/
class Permalink_Manager_Uri_Editor extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
	}

	/**
	* Add the section to the Permalink Manager admin page
	*/
	public function add_admin_section($admin_sections) {
		global $permalink_manager_options;

		$admin_sections['slug_editor'] = array(
			'name'				=>	__('Permalink editor', 'permalink-manager')
		);

		// Display separate section for each post type
		$post_types = Permalink_Manager_Helper_Functions::get_post_types_array('full');
		foreach($post_types as $post_type_name => $post_type) {
			// Check if post type exists
			if(!post_type_exists($post_type_name)) { continue; }

			$icon = (class_exists('WooCommerce') && in_array($post_type_name, array('product'))) ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "";

			$admin_sections['slug_editor']['subsections'][$post_type_name] = array(
				'name' => "{$icon} {$post_type['label']}",
				'function'    => array('class' => 'Permalink_Manager_URI_Editor_Post', 'method' => 'display_admin_section')
			);
		}

		// Permalink Manager Pro: Display separate section for each taxonomy
		/*$taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array('full');
		foreach($taxonomies as $taxonomy_name => $taxonomy) {
			// Check if taxonomy exists
			if(!taxonomy_exists($taxonomy_name)) { continue; }

			// Get the icon
			$icon = (class_exists('WooCommerce') && in_array($taxonomy_name, array('product_tag', 'product_cat'))) ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "<i class=\"dashicons dashicons-tag\"></i>";

			$admin_sections['slug_editor']['subsections']["tax_{$taxonomy_name}"] = array(
				'name' => "{$icon} {$taxonomy['label']}",
				'html' => Permalink_Manager_Admin_Functions::pro_text(),
				'pro' => true
			);
		}

		// A little dirty hack to move wooCommerce product & taxonomies to the end of array
		if(class_exists('WooCommerce')) {
			foreach(array('product', 'tax_product_tag', 'tax_product_cat') as $section_name) {
				if(empty($admin_sections['slug_editor']['subsections'][$section_name])) { continue; }
				$section = $admin_sections['slug_editor']['subsections'][$section_name];
				unset($admin_sections['slug_editor']['subsections'][$section_name]);
				$admin_sections['slug_editor']['subsections'][$section_name] = $section;
			}
		}*/

		return $admin_sections;
	}

}
