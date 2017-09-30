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
				'duplicates' => array(
					'name'				=>	__('Permalink Duplicates', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Tools', 'method' => 'duplicates_output')
				),
				'find_and_replace' => array(
					'name'				=>	__('Find & Replace', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Tools', 'method' => 'find_and_replace_output')
				),
				'regenerate_slugs' => array(
					'name'				=>	__('Regenerate/Reset', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Tools', 'method' => 'regenerate_slugs_output')
				),
				'stop_words' => array(
					'name'				=>	__('Stop Words', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Admin_Functions', 'method' => 'pro_text')
				),
				'import' => array(
					'name'				=>	__('Custom Permalinks', 'permalink-manager'),
					'function'		=>	array('class' => 'Permalink_Manager_Admin_Functions', 'method' => 'pro_text')
				)
			)
		);

		return $admin_sections;
	}

	public function display_instructions() {
		return wpautop(__('<strong>A MySQL backup is highly recommended before using "<em>Native slugs</em>" mode!</strong>', 'permalink-manager'));
	}

	public function duplicates_output() {
		global $permalink_manager_uris, $permalink_manager_redirects;

		// Get the duplicates & another variables
		$all_duplicates = Permalink_Manager_Core_Functions::detect_duplicates();
		$home_url = trim(get_option('home'), "/");

		$html = sprintf("<h3>%s</h3>", __("List of duplicated permalinks", "permalink-manager"));

		if(!empty($all_duplicates)) {
			foreach($all_duplicates as $uri => $duplicates) {
				$html .= "<div class=\"permalink-manager postbox permalink-manager-duplicate-box\">";
				$html .= "<h4 class=\"heading\"><a href=\"{$home_url}/{$uri}\" target=\"_blank\">{$home_url}/{$uri} <span class=\"dashicons dashicons-external\"></span></a></h4>";
				$html .= "<table>";

				foreach($duplicates as $item_id) {
					$html .= "<tr>";

					// Detect duplicate type
					preg_match("/(redirect-([\d]+)_)?(?:(tax-)?([\d]*))/", $item_id, $parts);

					$redirect_type = (!empty($parts[1])) ? __('Extra Redirect', 'permalink-manager') : __('Custom URI', 'permalink-manager');
					$detected_id = $parts[4];
					$detected_index = $parts[2];
					$detected_term = (!empty($parts[3])) ? true : false;

					// Get term
					if($detected_term && !empty($detected_id)) {
						$term = get_term($detected_id);
						$title = $term->name;
						$edit_label = __("Edit term", "permalink-manager");
						$edit_link = get_edit_tag_link($term->term_id, $term->taxonomy);
					} else if(!empty($detected_id)) {
						$post = get_post($detected_id);
						$title = $post->post_title;
						$edit_label = __("Edit post", "permalink-manager");
						$edit_link = get_edit_post_link($post->ID);
					} else {
						continue;
					}

					$html .= sprintf(
						//'<td><a href="%1$s" target="_blank">%2$s</a>%3$s</td><td>%4$s</td><td class="actions"><a href="%1$s" target="_blank"><span class="dashicons dashicons-edit"></span> %5$s</a> | <a class="remove-duplicate-link" href="%6$s"><span class="dashicons dashicons-trash"></span> %7$s</a></td>',
						'<td><a href="%1$s" target="_blank">%2$s</a>%3$s</td><td>%4$s</td><td class="actions"><a href="%1$s" target="_blank"><span class="dashicons dashicons-edit"></span> %5$s</a></td>',
						$edit_link,
						$title,
						" <small>#{$detected_id}</small>",
						$redirect_type,
						$edit_label
					);
					$html .= "</tr>";
				}
				$html .= "</table>";
				$html .= "</div>";
			}
		} else {
			$html .= sprintf("<p class=\"alert notice-success notice\">%s</p>", __('Congratulations! No duplicated URIs or Redirects found!', 'permalink-manager'));
		}

		return $html;
	}

	public function find_and_replace_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('permalink-manager-tools-fields', array(
			'old_string' => array(
				'label' => __( 'Find ...', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				'input_class' => 'widefat'
			),
			'new_string' => array(
				'label' => __( 'Replace with ...', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				'input_class' => 'widefat'
			),
			'mode' => array(
				'label' => __( 'Mode', 'permalink-manager' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array('custom_uris' => __('Custom URIs', 'permalink-manager'), 'slugs' => __('Native slugs', 'permalink-manager')),
			),
			'content_type' => array(
				'label' => __( 'Select content type', 'permalink-manager' ),
				'type' => 'select',
				'disabled' => true,
				'pro' => true,
				'container' => 'row',
				'default' => 'post_types',
				'choices' => array('post_types' => __('Post types', 'permalink-manager'), 'taxonomies' => __('Taxonomies', 'permalink-manager')),
			),
			'post_types' => array(
				'label' => __( 'Select post types', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'taxonomies' => array(
				'label' => __( 'Select taxonomies', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'container_class' => 'hidden',
				'default' => array('category', 'post_tag'),
				'choices' => $all_taxonomies,
				'pro' => true,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Select post statuses', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			),
			'ids' => array(
				'label' => __( 'Select IDs', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				//'disabled' => true,
				'description' => __('To narrow the above filters you can type the post IDs (or ranges) here. Eg. <strong>1-8, 10, 25</strong>.', 'permalink-manager'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'find_and_replace');

		$sidebar = '<h3>' . __('Important notices', 'permalink-manager') . '</h3>';
		$sidebar .= self::display_instructions();

		$output = Permalink_Manager_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __('Find and replace', 'permalink-manager'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-manager', 'name' => 'find_and_replace'), true);

		return $output;
	}

	public function regenerate_slugs_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('permalink-manager-tools-fields', array(
			'mode' => array(
				'label' => __( 'Mode', 'permalink-manager' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array('custom_uris' => __('Custom URIs', 'permalink-manager'), 'slugs' => __('Restore native slugs', 'permalink-manager'), 'native' => __('Restore native permalinks', 'permalink-manager')),
			),
			'content_type' => array(
				'label' => __( 'Select content type', 'permalink-manager' ),
				'type' => 'select',
				'disabled' => true,
				'pro' => true,
				'container' => 'row',
				'default' => 'post_types',
				'choices' => array('post_types' => __('Post types', 'permalink-manager'), 'taxonomies' => __('Taxonomies', 'permalink-manager')),
			),
			'post_types' => array(
				'label' => __( 'Select post types', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'taxonomies' => array(
				'label' => __( 'Select taxonomies', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'container_class' => 'hidden',
				'default' => array('category', 'post_tag'),
				'choices' => $all_taxonomies,
				'pro' => true,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Select post statuses', 'permalink-manager' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			),
			'ids' => array(
				'label' => __( 'Select IDs', 'permalink-manager' ),
				'type' => 'text',
				'container' => 'row',
				//'disabled' => true,
				'description' => __('To narrow the above filters you can type the post IDs (or ranges) here. Eg. <strong>1-8, 10, 25</strong>.', 'permalink-manager'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'regenerate');

		$sidebar = '<h3>' . __('Important notices', 'permalink-manager') . '</h3>';
		$sidebar .= self::display_instructions();

		$output = Permalink_Manager_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __( 'Regenerate', 'permalink-manager' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-manager', 'name' => 'regenerate'), true);

		return $output;
	}
}
