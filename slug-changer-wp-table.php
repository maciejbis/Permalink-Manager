<?php
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class SlugsListTable extends WP_List_Table {
	
	/**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
	public function get_columns() {
		$columns = array(
			'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
			'ID'          		=> __('ID', 'slug-changer'),
			'post_name'			=> __('Slug', 'slug-changer'),
			'post_title'		=> __('Title', 'slug-changer'),
			'post_date_gmt'		=> __('Date', 'slug-changer'),
			'post_status'		=> __('Post Status', 'slug-changer'),
			'post_type'			=> __('Post Type', 'slug-changer')
		);
		
		return $columns;
	}
	
	/**
     * [REQUIRED] You must declare constructor and give some basic params
     */
    function __construct() {
        global $status, $page;

        parent::__construct(array(
            'singular'	=> 'slug',
            'plural'	=> 'slugs',
			'ajax'		=> true
        ));
    }
	
	/**
     * [REQUIRED] this is how checkbox column renders
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['ID']
        );
    }
	
	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns() {
		return array('post_date_gmt');
	}
	
	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns() {
		return array(
			'post_title' => array('post_title', false),
			'post_name' => array('post_name', false),
			'post_type' => array('post_type', false),
			'ID' => array('ID', false)
		);
	}
	
	/**
	* Get the table data
	*
	* @return Array
	*/
	
	public function column_id($item) {
		return $item['ID'];
	}
	
	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  Array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'post_type':
			case 'post_status':
				return $item[ $column_name ];
				
			case 'ID':
				return '#' . $item[ $column_name ];
				
				case 'post_name':
				$edit_slug = '<input type="text" name="slug[' . $item[ 'ID' ] . ']" id="slug[' . $item[ 'ID' ] . ']" value="' . $item[ 'post_name' ] . '">';
				return $edit_slug;
			
			case 'post_title':
				$edit_post = $item[ 'post_title' ] . '<div class="row-action">
				<span class="edit"><a target="_blank" href="http://maciejbis.net/wp-admin/post.php?post=' . $item[ 'ID' ] . '&amp;action=edit" title="' . __('Edit', 'slug-changer') . '">' . 'Edit' . '</a> | 
				</span><span class="view"><a target="_blank" href="' . get_permalink($item[ 'ID' ]) . '" title="' . __('View', 'slug-changer') . ' “' . $item[ 'post_title' ] . '”" rel="permalink">' . __('View', 'slug-changer') . '</a></span></div>';
				return $edit_post;
	
			default:
				return print_r( $item, true ) ;
		}
	}
	
	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @return Mixed
	 */
	private function sort_data( $a, $b ) {
		// Set defaults
		$orderby = 'post_title';
		$order = 'asc';
	
		// If orderby is set, use this as the sort column
		if(!empty($_GET['orderby'])) {
			$orderby = $_GET['orderby'];
		}
	
		// If order is set use this as the order
		if(!empty($_GET['order'])) {
			$order = $_GET['order'];
		}
	
		// case sensitive
		//$result = strnatcmp( $a[$orderby], $b[$orderby] );
		// non-case sensitive
		$result = strnatcasecmp( $a[$orderby], $b[$orderby] );
	
		if($order === 'asc') {
			return $result;
		}
	
		return -$result;
	}
	
	/**
     * Return array of bult actions if has any
     *
     * @return array
     */
    function get_bulk_actions() {
        $actions = array(
			'update_slug'		=> __('Update Selected Post(s) Slug(s)', 'slug-changer'),
			'update_all_slugs'	=> __('Update All Posts\' Slugs on this Page', 'slug-changer')
        );
        return $actions;
    }
	
	/**
     * Check if the provided slug is unique and then update it with SQL query.
	 *
	 * @return string
     */
	public function update_slug_by_id($posts_table, $slug, $id) {
		global $wpdb;
		// update slug and make it unique
		$new_slug = wp_unique_post_slug($slug, $id, get_post_status($id), get_post_type($id), null);
		$wpdb->query("UPDATE $posts_table SET post_name = '$new_slug' WHERE ID = '$id'");
		return $new_slug;
	}

    /**
     * This method processes bulk actions
     * it can be outside of class
     * it can not use wp_redirect coz there is output already
     * in this example we are processing delete action
     * message about successful deletion will be shown on page in next part
     */
    function process_bulk_action($posts_table) {
        global $wpdb;

        switch ($this->current_action()) {  
		
			case 'update_slug' :
				$ids = isset($_POST['id']) ? $_POST['id'] : array();
				$slugs = isset($_POST['slug']) ? $_POST['slug'] : array();
				
				// double check if the slugs and ids are stored in arrays
				if (!is_array($ids)) $ids = explode(',', $ids);
				if (!is_array($slugs)) $slugs = explode(',', $slugs);
				
				if (!empty($ids)) {
					foreach($ids as $id) {
						// update slugs
						$this->update_slug_by_id($posts_table, $slugs[$id], $id);
					}
				}
				break;	
			
			case 'update_all_slugs' :
				$slugs = isset($_POST['slug']) ? $_POST['slug'] : array();
				
				// double check if the slugs and ids are stored in arrays
				if (!is_array($slugs)) $slugs = explode(',', $slugs);
				
				if (!empty($slugs)) {
					foreach($slugs as $id => $slug) {
						// update slugs
						$this->update_slug_by_id($posts_table, $slug, $id);
					}
				}
				break;	
			
		}
		
    }
	
	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items($posts_table) {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$currentPage = $this->get_pagenum();
		
		global $wpdb;
		
		$this->process_bulk_action($posts_table);
		
		// will be used in pagination settings
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $posts_table WHERE post_status NOT IN ('inherit', 'auto-draft', 'trash') AND post_type NOT IN ('wpcf7_contact_form')");
		
		// SQL query parameters
		$per_page = 10;
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'ID';
		$offset = ($currentPage - 1) * $per_page;
		
		// grab posts from database
        $data = $wpdb->get_results("SELECT * FROM $posts_table WHERE post_status NOT IN ('inherit', 'auto-draft', 'trash') AND post_type NOT IN ('wpcf7_contact_form') ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A);
	
		// sort posts and count all posts
		usort( $data, array( &$this, 'sort_data' ) );
		$totalItems = count($data);
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		));
	
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}
	
}
?>