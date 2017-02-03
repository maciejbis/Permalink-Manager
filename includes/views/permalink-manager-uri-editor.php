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
       'name'				=>	__('Permalink editor', 'permalink-manager'),
       'function'    => array('class' => 'Permalink_Manager_Uri_Editor_Table', 'method' => 'display_admin_section')
     );

     // Display separate section for each post type
     $post_types = Permalink_Manager_Helper_Functions::get_post_types_array('full');
     foreach($permalink_manager_options['screen-options']['post_types'] as $post_type) {
       $admin_sections['slug_editor']['subsections'][$post_type] = array(
         'name' => $post_types[$post_type]['label']
       );
     }

     return $admin_sections;
   }

 }

class Permalink_Manager_Uri_Editor_Table extends WP_List_Table {

  public function __construct() {
    global $status, $page;

 	  parent::__construct(array(
			'singular'	=> 'slug',
			'plural'	=> 'slugs'
		));
  }

  /**
   * Get the HTML output with the WP_List_Table
   */
  public function display_admin_section() {
    global $wpdb;

    $output = "<form id=\"permalinks-post-types-table\" class=\"slugs-table\" method=\"post\">";
    $output .= wp_nonce_field('uri_actions', 'slug_editor', true, true);

    // Bypass
    ob_start();

    $this->prepare_items();
    $this->display();
    $output .= ob_get_contents();

    ob_end_clean();

    $output .= "</form>";

    return $output;
  }

	/**
	* Override the parent columns method. Defines the columns to use in your listing table
	*/
	public function get_columns() {
		$columns = array(
			//'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
			'post_title'		=> __('Post title', 'permalink-manager'),
			'post_name'	=> __('Post name (native slug)', 'permalink-manager'),
			//'post_date_gmt'		=> __('Date', 'permalink-manager'),
			'uri'	=> __('Full URI & Permalink', 'permalink-manager'),
			'post_status'		=> __('Post status', 'permalink-manager'),
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
		);
	}

	/**
	 * Data inside the columns
	 */
	public function column_default( $item, $column_name ) {

		$base = Permalink_Manager_Post_URI_Functions::get_post_uri($item[ 'ID' ], false, false);
		$field_args_base = array('type' => 'text', 'value' => $base, 'without_label' => true, 'input_class' => '');
    $permalink = Permalink_Manager_Post_URI_Functions::get_correct_permalink($item['ID']);

		switch( $column_name ) {
			case 'post_status':
				$post_statuses_array = get_post_statuses();
				return "<span title=\"{$item[$column_name]}\">{$post_statuses_array[$item[$column_name]]}</span>";

			case 'post_name':
				$output = $item[ 'post_name' ];
        return $output;

			case 'uri':
        $output = ($item['post_status'] != 'publish') ? '-' : Permalink_Manager_Admin_Functions::generate_option_field("uri[{$item['ID']}]", $field_args_base);
        $output .= "<a class=\"small post_permalink\" href=\"{$permalink}\" target=\"_blank\"><span class=\"dashicons dashicons-admin-links\"></span> {$permalink}</a>";
				return $output;;

			case 'post_title':
				$output = $item[ 'post_title' ];
				$output .= '<div class="row-actions">';
				$output .= '<span class="edit"><a target="_blank" href="' . home_url() . '/wp-admin/post.php?post=' . $item[ 'ID' ] . '&amp;action=edit" title="' . __('Edit', 'permalink-manager') . '">' . __('Edit', 'permalink-manager') . '</a> | </span>';
        $output .= '<span class="view"><a target="_blank" href="' . $permalink . '" title="' . __('View', 'permalink-manager') . ' ' . $item[ 'post_title' ] . '" rel="permalink">' . __('View', 'permalink-manager') . '</a> | </span>';
        $output .= '<span class="id">#' . $item[ 'ID' ] . '</span>';
				$output .= '</div>';
				return $output;

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
		$button_top = __( 'Update all the URIs below', 'permalink-manager' );
		$button_bottom = __( 'Update all the URIs above', 'permalink-manager' );

		echo '<div class="alignleft actions">';
		submit_button( ${"button_$which"}, 'primary', "update_all_slugs[{$which}]", false, array( 'id' => 'doaction', 'value' => 'update_all_slugs' ) );
		echo '</div>';
	}

	/**
	 * Prepare the items for the table to process
	 */
	public function prepare_items() {
		global $wpdb, $permalink_manager_options, $active_subsection;

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$current_page = $this->get_pagenum();

    // Get query variables
		$per_page = $permalink_manager_options['screen-options']['per_page'];
		$post_types_array = $permalink_manager_options['screen-options']['post_types'];
		$post_types = ($active_subsection && $active_subsection != 'all') ? "'{$active_subsection}'" : "'" . implode("', '", $post_types_array) . "'";
		$post_statuses_array = $permalink_manager_options['screen-options']['post_statuses'];;
		$post_statuses = "'" . implode("', '", $post_statuses_array) . "'";

		// Will be used in pagination settings
		$total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->posts} WHERE post_status IN ($post_statuses) AND post_type IN ($post_types)");

		// SQL query parameters
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'ID';
		$offset = ($current_page - 1) * $per_page;

		// Grab posts from database
		//$sql_query = "SELECT * FROM {$wpdb->posts} WHERE post_status IN ($post_statuses) AND post_type IN ($post_types) ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
		$sql_query = "SELECT * FROM {$wpdb->posts} WHERE post_status IN ($post_statuses) AND post_type IN ($post_types) ORDER BY $orderby $order";
		$all_data = $wpdb->get_results($sql_query, ARRAY_A);

		// Sort posts and count all posts
		usort( $all_data, array( &$this, 'sort_data' ) );

		$data = array_slice($all_data, $offset, $per_page);

		// Debug SQL query
		$debug_txt = "<textarea style=\"width:100%;height:300px\">{$sql_query} \n\nOffset: {$offset} \nPage: {$current_page}\nPer page: {$per_page} \nTotal: {$total_items}</textarea>";
		if(isset($_REQUEST['debug_editor_sql'])) { wp_die($debug_txt); }

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		));

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}

}
