<?php

/**
* Display the page where the slugs could be regenerated or replaced
*/
class Permalink_Manager_Tools extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
	}

	public function add_admin_section($admin_sections) {

		$admin_sections['tools'] = array(
			'name'				=>	__('Tools', 'permalink-manager'),
			'subsections' => array(
				'find_and_replace' => array(
					'name'				=>	__('Find and replace', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Tools', 'method' => 'find_and_replace_output')
				),
				'regenerate_slugs' => array(
					'name'				=>	__('Regenerate/Reset', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Tools', 'method' => 'regenerate_slugs_output')
				)
			)
		);

		return $admin_sections;
	}

	public function display_instructions() {
		return wpautop(__('<strong>A MySQL backup is highly recommended before using "<em>Custom & native slugs (post names)</em>" mode!</strong>.', 'permalink-manager'));
	}

	public function find_and_replace_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		$fields = array(
			'old_string' => array(
				'label' => __( 'Find ...', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				'container_class' => 'column column-1_2',
				'input_class' => 'widefat'
			),
			'new_string' => array(
				'label' => __( 'Replace with ...', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				'container_class' => 'column column-1_2',
				'input_class' => 'widefat'
			),
			'mode' => array(
				'label' => __( 'Select mode', 'permalink-manager' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array('both' => '<strong>' . __('Full URIs', 'permalink-manager') . '</strong>', 'post_names' => '<strong>' . __('Custom & native slugs (post names)', 'permalink-manager') . '</strong>'),
				'default' => array('both'),
			),
			'post_types' => array(
				'label' => __( 'Filter by post types', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Filter by post statuses', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			)
		);

		$sidebar = '<h3>' . __('Important notices', 'permalink-manager') . '</h3>';
		$sidebar .= self::display_instructions();

		$output = Permalink_Manager_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __( 'Find and replace', 'permalink-manager' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-manager', 'name' => 'find_and_replace'));

		return $output;
	}

	public function regenerate_slugs_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		$fields = array(
			'mode' => array(
				'label' => __( 'Select mode', 'permalink-manager' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array('both' => '<strong>' . __('Full URIs', 'permalink-manager') . '</strong>', 'post_names' => '<strong>' . __('Custom & native slugs (post names)', 'permalink-manager') . '</strong>'),
			),
			'post_types' => array(
				'label' => __( 'Filter by post types', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Filter by post statuses', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			)
		);

		$sidebar = '<h3>' . __('Important notices', 'permalink-manager') . '</h3>';
		$sidebar .= self::display_instructions();
		$output = Permalink_Manager_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __( 'Regenerate', 'permalink-manager' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-manager', 'name' => 'regenerate'));

		return $output;
	}
}
