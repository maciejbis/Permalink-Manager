<?php

/**
* Extend WP_List_Table with two subclasses for post types and taxonomies
*/
class Permalink_Manager_URI_Editor_Post extends WP_List_Table {

	public $displayed_post_types, $displayed_post_statuses;

	public function __construct() {
		global $status, $page, $permalink_manager_options, $active_subsection;

		parent::__construct(array(
			'singular'	=> 'slug',
			'plural'	=> 'slugs'
		));

		$this->displayed_post_statuses = (isset($permalink_manager_options['screen-options']['post_statuses'])) ? "'" . implode("', '", $permalink_manager_options['screen-options']['post_statuses']) . "'" : "'no-post-status'";
		$this->displayed_post_types = ($active_subsection && $active_subsection == 'all') ? "'" . implode("', '", $permalink_manager_options['screen-options']['post_types']) . "'" : "'{$active_subsection}'";
	}

	/**
	* Get the HTML output with the WP_List_Table
	*/
	public function display_admin_section() {
		global $wpdb;

		$output = "<form id=\"permalinks-post-types-table\" class=\"slugs-table\" method=\"post\">";
		$output .= wp_nonce_field('permalink-manager', 'uri_editor', true, true);

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
		return apply_filters('permalink-manager-uri-editor-columns', array(
			//'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
			'post_title'		=> __('Post title', 'permalink-manager'),
			'post_name'	=> __('Post name (native slug)', 'permalink-manager'),
			//'post_date_gmt'		=> __('Date', 'permalink-manager'),
			'uri'	=> __('Full URI & Permalink', 'permalink-manager'),
			'post_status'		=> __('Post status', 'permalink-manager'),
		));
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
		$uri = Permalink_Manager_URI_Functions_Post::get_post_uri($item['ID'], true);
		$field_args_base = array('type' => 'text', 'value' => $uri, 'without_label' => true, 'input_class' => '');
		$permalink = get_permalink($item['ID']);

		$output = apply_filters('permalink-manager-uri-editor-column-content', '', $column_name, get_post($item['ID']));
		if(!empty($output)) { return $output; }

		switch( $column_name ) {
			case 'post_status':
				$post_statuses_array = get_post_statuses();
				return "<span title=\"{$item[$column_name]}\">{$post_statuses_array[$item[$column_name]]}</span>";

			case 'post_name':
				$output = $item[ 'post_name' ];
				return $output;

			case 'uri':
				$output = Permalink_Manager_Admin_Functions::generate_option_field("uri[{$item['ID']}]", $field_args_base);
				$output .= "<a class=\"small post_permalink\" href=\"{$permalink}\" target=\"_blank\"><span class=\"dashicons dashicons-admin-links\"></span> {$permalink}</a>";
				return $output;

			case 'post_title':
				$output = $item[ 'post_title' ];
				$output .= '<div class="row-actions">';
				$output .= '<span class="edit"><a target="_blank" href="' . home_url() . '/wp-admin/post.php?post=' . $item[ 'ID' ] . '&amp;action=edit" title="' . __('Edit', 'permalink-manager') . '">' . __('Edit', 'permalink-manager') . '</a> | </span>';
				$output .= '<span class="view"><a target="_blank" href="' . $permalink . '" title="' . __('View', 'permalink-manager') . ' ' . $item[ 'post_title' ] . '" rel="permalink">' . __('View', 'permalink-manager') . '</a> | </span>';
				$output .= '<span class="id">#' . $item[ 'ID' ] . '</span>';
				$output .= '</div>';
				return $output;

			default:
				return $item[$column_name];
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
		global $wpdb, $active_section, $active_subsection;

		$button_top = __( 'Update all the URIs below', 'permalink-manager' );
		$button_bottom = __( 'Update all the URIs above', 'permalink-manager' );

		$html = "<div class=\"alignleft actions\">";
		$html .= get_submit_button( ${"button_$which"}, 'primary alignleft', "update_all_slugs[{$which}]", false, array( 'id' => 'doaction', 'value' => 'update_all_slugs' ) );

    if ($which == "top") {
			$months = $wpdb->get_results("SELECT DISTINCT month(post_date) AS m, year(post_date) AS y FROM {$wpdb->posts} WHERE post_status IN ($this->displayed_post_statuses) AND post_type IN ($this->displayed_post_types) ORDER BY post_date DESC", ARRAY_A);
			if($months) {

				$month_key = 'month';
				$screen = get_current_screen();
				$current_url = add_query_arg( array(
			    'page' => PERMALINK_MANAGER_PLUGIN_SLUG,
			    'section' => $active_section,
			    'subsection' => $active_subsection
				), admin_url($screen->parent_file));

				$html .= "<div id=\"months-filter\" class=\"alignright hide-if-no-js\" data-filter-url=\"{$current_url}\">";
				$html .= "<select id=\"months-filter-select\" name=\"{$month_key}\">";
				$html .= sprintf("<option value=\"\">%s</option>", __("All dates", "permalink-manager"));
				foreach($months as $month) {
					$month_raw = "{$month['y']}-{$month['m']}";
					$month_human_name = date_i18n("F Y", strtotime($month_raw));

					$selected = (!empty($_REQUEST[$month_key])) ? selected($_REQUEST[$month_key], $month_raw, false) : "";
					$html .= "<option value=\"{$month_raw}\" {$selected}>{$month_human_name}</option>";
				}
				$html .= "</select>";
				$html .= sprintf("<input id=\"months-filter-button\" class=\"button\" value=\"%s\" type=\"submit\">", __("Filter", "permalink-manager"));
				$html .= "</div>";
			}
    }
		$html .= "</div>";

		echo $html;
	}

	/**
	* Prepare the items for the table to process
	*/
	public function prepare_items() {
		global $wpdb, $permalink_manager_options;

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$current_page = $this->get_pagenum();

		// Get query variables
		$per_page = $permalink_manager_options['screen-options']['per_page'];

		// SQL query parameters
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';
		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'ID';
		$offset = ($current_page - 1) * $per_page;

		// Extra filters
		$extra_filters = '';
		if(!empty($_GET['month'])) {
			$month = date("n", strtotime($_GET['month']));
			$year = date("Y", strtotime($_GET['month']));

			$extra_filters .= "AND month(post_date) = {$month} AND year(post_date) = {$year}";
		}

		// Grab posts from database
		//$sql_query = "SELECT * FROM {$wpdb->posts} WHERE post_status IN ($this->displayed_post_statuses) AND post_type IN ($this->displayed_post_types) ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
		$sql_query = "SELECT * FROM {$wpdb->posts} WHERE post_status IN ($this->displayed_post_statuses) AND post_type IN ($this->displayed_post_types) {$extra_filters} ORDER BY $orderby $order";
		$all_data = $wpdb->get_results($sql_query, ARRAY_A);

		// How many items?
		$total_items = $wpdb->num_rows;

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
