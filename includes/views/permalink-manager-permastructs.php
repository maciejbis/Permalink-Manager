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
		$post_types_notes = wpautop(sprintf(__('All allowed <a href="%s" target="_blank">structure tags</a> are listed below.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags"));
		$post_types_notes .= Permalink_Manager_Helper_Functions::get_all_structure_tags();
		$post_types_notes .= wpautop(sprintf(__('Please note that some of them can be used only for particular post types permastructures.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags"));
		$post_types_notes .= __('<h5>Custom fields inside permastructures <small>(Permalink Manager Pro only)</small></h5>', 'permalink-manager');
		$post_types_notes .= wpautop(__('To use the custom fields inside the permalink, please use following tag <code>%__custom_field_key%</code> and replace "<em>custom_field_key</em>" with the full name of your custom field key.', 'permalink-manager'));

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

			$default_permastruct = trim(Permalink_Manager_Helper_Functions::get_default_permastruct($post_type['name']), "/");
			$current_permastruct = isset($permalink_manager_permastructs['post_types'][$post_type['name']]) ? $permalink_manager_permastructs['post_types'][$post_type['name']] : $default_permastruct;

			$fields["post_types"]["fields"][$post_type['name']] = array(
				'label' => $post_type['label'],
				'container' => 'row',
				'input_class' => 'permastruct-field',
				'after_description' => self::restore_default_row($default_permastruct),
				'extra_atts' => "data-default=\"{$default_permastruct}\"",
				'value' => $current_permastruct,
				'placeholder' => $default_permastruct,
				'type' => 'permastruct'
			);
		}

		return apply_filters('permalink-manager-permastructs-fields', $fields);
	}

	/**
	 * Restore default permastructure row
	 */
	public static function restore_default_row($default_permastruct) {
		return sprintf(
			"<p class=\"default-permastruct-row columns-container\"><span class=\"column-2_4\"><strong>%s:</strong> %s</span><span class=\"column-2_4\"><a href=\"#\" class=\"restore-default\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span></p>",
			__("Default permastructure", "permalink-manager"), esc_html($default_permastruct),
			__("Restore to Default Permastructure", "permalink-manager")
		);
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		global $permalink_manager_permastructs;

		$sidebar = '<h3>' . __('Important notices', 'permalink-manager') . '</h3>';
		$sidebar .= wpautop(__('This tool <strong>automatically appends the slug to the end of permastructure</strong>, so there is no need to use them within the fields. To prevent the overlapping URLs problem please keep the permastructures unique.'));
		$sidebar .= sprintf(wpautop(__('The current permastructures settings will be applied <strong>only to the new posts & terms</strong>. To apply the <strong>new permastructures to old posts & terms</strong>, please use "Regenerate/reset" tool available <a href="%s">here</a>.', 'bis')), admin_url('tools.php?page=permalink-manager&section=tools&subsection=regenerate_slugs'));

		return Permalink_Manager_Admin_Functions::get_the_form(self::get_fields(), '', array('text' => __( 'Save permastructures', 'permalink-manager' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-manager', 'name' => 'permalink_manager_permastructs'));
	}

}
