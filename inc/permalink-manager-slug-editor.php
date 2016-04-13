<?php
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Permalink_Manager_Slug_Editor extends WP_List_Table {
  public $screen_options_fields;

	function __construct() {
 	  global $status, $page;

 	  parent::__construct(array(
			'singular'	=> 'slug',
			'plural'	=> 'slugs',
			'ajax'		=> true
		));
 	}

	/**
	* Override the parent columns method. Defines the columns to use in your listing table
	*/
	public function get_columns() {
		$columns = array(
			//'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
			'post_title'		=> __('Title', 'permalink-manager'),
			'post_name'			=> __('Slug', 'permalink-manager'),
			'post_date_gmt'		=> __('Date', 'permalink-manager'),
			'post_permalink'	=> __('Permalink', 'permalink-manager'),
			'post_status'		=> __('Post Status', 'permalink-manager'),
			'post_type'			=> __('Post Type', 'permalink-manager')
		);

		return $columns;
	}

	/**
	 * Hidden columns
	 */
	public function get_hidden_columns() {
		return array('post_date_gmt');
	}

	/**
	 * Sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'post_title' => array('post_title', false),
			'post_name' => array('post_name', false),
			'post_status' => array('post_status', false),
			'post_type' => array('post_type', false),
		);
	}

	/**
	 * Data inside the columns
	 */
	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'post_type':
				$post_type_labels = Permalink_Manager_Helper_Functions::get_post_types_array('full', $item['post_type']);
				return "{$post_type_labels['label']}<br /><small>({$post_type_labels['name']})</small>";

			case 'post_status':
				$post_statuses_array = get_post_statuses();
				return "{$post_statuses_array[$item[ $column_name ]]}<br /><small>({$item[ $column_name ]})</small>";

			case 'post_permalink':
				return ($item['post_status'] != 'publish') ? '-' : Permalink_Manager_Helper_Functions::get_correct_permalink($item[ 'ID' ], $item[ 'post_name' ], true) . ' <a href="' . Permalink_Manager_Helper_Functions::get_correct_permalink($item[ 'ID' ], $item[ 'post_name' ]) . '" target="_blank"><span class="dashicons dashicons-admin-links"></span></a>';

			case 'post_name':
				$field_name = "slug[{$item['ID']}]";
				$field_args = array('type' => 'text', 'default' => $item[ 'post_name' ], 'without_label' => true, 'input_class' => 'widefat');
				return Permalink_Manager_Helper_Functions::generate_option_field($field_name, $field_args);

			case 'post_title':
				$edit_post = $item[ 'post_title' ] . '<div class="row-actions">';
				$edit_post .= '<span class="edit"><a target="_blank" href="' . home_url() . '/wp-admin/post.php?post=' . $item[ 'ID' ] . '&amp;action=edit" title="' . __('Edit', 'permalink-manager') . '">' . __('Edit', 'permalink-manager') . '</a> | </span>';
				$edit_post .= '<span class="view"><a target="_blank" href="' . Permalink_Manager_Helper_Functions::get_correct_permalink($item[ 'ID' ], $item[ 'post_name' ]) . '" title="' . __('View', 'permalink-manager') . ' ' . $item[ 'post_title' ] . '" rel="permalink">' . __('View', 'permalink-manager') . '</a> | </span>';
				$edit_post .= '<span class="id">#' . $item[ 'ID' ] . '</span>';
				$edit_post .= '</div>';
				return $edit_post;

			default:
				return $item[ $column_name ];
		}
	}

	/**
	 * Sort the data
	 */
	private function sort_data( $a, $b ) {
		// Set defaults
		$orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'post_title';
		$order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
		$result = strnatcasecmp( $a[$orderby], $b[$orderby] );

		return ($order === 'asc') ? $result : -$result;
	}

	/**
	* The button that allows to save updated slugs
	*/
	function extra_tablenav( $which ) {
		$button_top = __( 'Update all slugs below', 'permalink-manager' );
		$button_bottom = __( 'Update all slugs above', 'permalink-manager' );

		echo '<div class="alignleft actions">';
		submit_button( ${"button_$which"}, 'primary', "update_all_slugs[{$which}]", false, array( 'id' => 'doaction', 'value' => 'update_all_slugs' ) );
		echo '</div>';
	}

	/**
	 * Prepare the items for the table to process
	 */
	public function prepare_items($posts_table) {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$currentPage = $this->get_pagenum();

		global $wpdb;

		// Load options and fields
		$saved_options = get_option('permalink-manager');
		$saved_options = isset($saved_options['screen-options']) ? $saved_options['screen-options'] : array();
		$screen_options_fields = $this->screen_options_fields;
		$per_page = isset($saved_options['per_page']) ? $saved_options['per_page'] : $screen_options_fields['per_page']['default'];
		$post_types_array = isset($saved_options['post_types']) ? $saved_options['post_types'] : $screen_options_fields['post_types']['default'];
		$post_types = "'" . implode("', '", $post_types_array) . "'";
		$post_statuses_array = isset($saved_options['post_statuses']) ? $saved_options['post_statuses'] : $screen_options_fields['post_statuses']['default'];
		$post_statuses = "'" . implode("', '", $post_statuses_array) . "'";

		// Will be used in pagination settings
		$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $posts_table WHERE post_status IN ($post_statuses) AND post_type IN ($post_types)");

		// SQL query parameters
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'ID';
		$offset = ($currentPage - 1) * $per_page;

		// Grab posts from database
		$sql_query = "SELECT * FROM $posts_table WHERE post_status IN ($post_statuses) AND post_type IN ($post_types) ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
		$data = $wpdb->get_results($sql_query, ARRAY_A);

		// Sort posts and count all posts
		usort( $data, array( &$this, 'sort_data' ) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		));

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}

	/**
	* This variable is assigned in permalink-manager.php before prepare_items() function is triggered, see permalinks_table_html() function
	*/
	public function set_screen_option_fields($fields) {
		$this->screen_options_fields = $fields;
	}

}
?>
