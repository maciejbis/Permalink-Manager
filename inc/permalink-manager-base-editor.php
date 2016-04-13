<?php
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Permalink_Manager_Base_Editor extends WP_List_Table {
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
			'post_type'									=> __('Post Type', 'permalink-manager'),
			'post_permalink_base'				=> __('Permalink Base/Permastructure', 'permalink-manager'),
			'post_type_structure_tag'		=> __('Permalink Base Should End With', 'permalink-manager'),
			'post_type_default_base'		=> __('Default Permalink Base/Permastructure', 'permalink-manager'),
			//'post_sample_permalink'		=> __('Sample Permalink', 'permalink-manager')
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
			'post_type' => array('post_title', false),
		);
	}

	/**
	 * Data inside the columns
	 */
	public function column_default( $item, $column_name ) {
		$permastruct = Permalink_Manager_Helper_Functions::get_permastruct($item['name'], false);
		$default_permastruct = Permalink_Manager_Helper_Functions::get_permastruct($item['name'], false, 'default_permastruct');

		switch( $column_name ) {
			case 'post_type':
				return "{$item['label']}<br /><small>({$item['name']})</small>";

			case 'post_permalink_base':
				$placeholder = $default_permastruct;
				$field_args = array('type' => 'text', 'default' => $permastruct, 'without_label' => true, 'input_class' => 'widefat', 'placeholder' => $placeholder);
				return Permalink_Manager_Helper_Functions::generate_option_field($item['name'], $field_args, 'base-editor');

			case 'post_type_default_base':
				return "<code>{$default_permastruct}</code>";

			case 'post_type_structure_tag':
				return "<code>" . Permalink_Manager_Helper_Functions::get_post_tag($item['name']) . "</code>";

			default:
				return '';
		}
	}

	/**
	 * Sort the data
	 */
	private function sort_data( $a, $b ) {
		// Set defaults
		$order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
		$result = strnatcasecmp( $a['name'], $b['name'] );

		return ($order === 'asc') ? $result : -$result;
	}

	/**
	* The button that allows to save updated slugs
	*/
	function extra_tablenav( $which ) {
		$save_button = __( 'Save settings', 'permalink-manager' );
		$flush_button = __( 'Flush rewrite rules', 'permalink-manager' );

		echo '<div class="alignleft actions">';
		submit_button( $save_button, 'primary', "save_permalink_structures[{$which}]", false, array( 'id' => 'doaction', 'value' => 'save_permalink_structures' ) );
		submit_button( $flush_button, 'primary', "flush_rewrite_rules[{$which}]", false, array( 'id' => 'doaction', 'value' => 'flush_rewrite_rules' ) );
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
		$screen_options_fields = $this->screen_options_fields;
		$per_page = isset($saved_options['per_page']) ? $saved_options['per_page'] : $screen_options_fields['per_page']['default'];

		// Load all post types
		$data = Permalink_Manager_Helper_Functions::get_post_types_array('full');

		// Attachments are excluded
		unset($data['attachment']);

		// Will be used in pagination settings
		$total_items = count($data);

		// SQL query parameters
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'ID';
		$offset = ($currentPage - 1) * $per_page;

		// Sort posts and count all posts
		usort( $data, array( &$this, 'sort_data' ) );

		// Pagination
		$data = array_slice($data, $offset, $per_page);

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
