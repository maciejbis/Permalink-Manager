<?php

/**
 * Display Bulk URI Editor
 */
class Permalink_Manager_URI_Editor {
	public $this_section = 'uri_editor';

	public function __construct() {
		add_filter( 'permalink_manager_sections', array( $this, 'add_admin_section' ), 0 );
		add_filter( 'screen_settings', array( $this, 'screen_options' ), 99, 2 );
	}

	/**
	 * Add a new section to the Permalink Manager UI
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_admin_section( $admin_sections ) {
		$admin_sections[ $this->this_section ] = array(
			'name' => __( 'URI Editor', 'permalink-manager' )
		);

		// Display separate section for each post type
		$post_types = Permalink_Manager_Helper_Functions::get_post_types_array( 'full' );
		foreach ( $post_types as $post_type_name => $post_type ) {
			// Check if post type exists
			if ( ! post_type_exists( $post_type_name ) ) {
				continue;
			}

			$icon = ( class_exists( 'WooCommerce' ) && $post_type_name == 'product' ) ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "";

			$admin_sections[ $this->this_section ]['subsections'][ $post_type_name ] = array(
				'name'     => "{$icon} {$post_type['label']}",
				'function' => array( 'class' => 'Permalink_Manager_URI_Editor_Post', 'method' => 'display_admin_section' )
			);
		}

		// Permalink Manager Pro: Display separate section for each taxonomy
		$taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array( 'full' );
		foreach ( $taxonomies as $taxonomy_name => $taxonomy ) {
			// Check if taxonomy exists
			if ( ! taxonomy_exists( $taxonomy_name ) ) {
				continue;
			}

			// Get the icon
			$icon = ( class_exists( 'WooCommerce' ) && in_array( $taxonomy_name, array( 'product_tag', 'product_cat' ) ) ) ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "<i class=\"dashicons dashicons-tag\"></i>";

			$admin_sections[ $this->this_section ]['subsections']["tax_{$taxonomy_name}"] = array(
				'name' => "{$icon} {$taxonomy['label']}",
				'html' => Permalink_Manager_UI_Elements::pro_text(),
				'pro'  => true
			);
		}

		// A little dirty hack to move wooCommerce product & taxonomies to the end of array
		if ( class_exists( 'WooCommerce' ) ) {
			foreach ( array( 'product', 'tax_product_tag', 'tax_product_cat' ) as $section_name ) {
				if ( empty( $admin_sections[ $this->this_section ]['subsections'][ $section_name ] ) ) {
					continue;
				}
				$section = $admin_sections[ $this->this_section ]['subsections'][ $section_name ];
				unset( $admin_sections[ $this->this_section ]['subsections'][ $section_name ] );
				$admin_sections[ $this->this_section ]['subsections'][ $section_name ] = $section;
			}
		}

		return $admin_sections;
	}

	/**
	 * Display "Screen options"
	 *
	 * @param string $html
	 * @param string $screen
	 *
	 * @return string
	 */
	public function screen_options( $html, $screen ) {
		global $active_section;

		// Display the screen options only in "Permalink Editor"
		if ( $active_section != $this->this_section ) {
			return $html;
		}

		$button = get_submit_button( __( 'Apply', 'permalink-manager' ), 'primary', 'screen-options-apply', false );
		$html   = "<fieldset class=\"permalink-manager-screen-options\">";

		$screen_options = array(
			'per_page'      => array(
				'type'        => 'number',
				'label'       => __( 'Per page', 'permalink-manager' ),
				'input_class' => 'settings-select'
			),
			'post_statuses' => array(
				'type'         => 'checkbox',
				'label'        => __( 'Post statuses', 'permalink-manager' ),
				'choices'      => Permalink_Manager_Helper_Functions::get_post_statuses(),
				'select_all'   => '',
				'unselect_all' => '',
			)
		);

		foreach ( $screen_options as $field_name => $field_args ) {
			$field_args['container'] = 'screen-options';
			$html                    .= Permalink_Manager_UI_Elements::generate_option_field( "screen-options[{$field_name}]", $field_args );
		}

		$html .= sprintf( "</fieldset>%s", $button );

		return $html;
	}

	/**
	 * Trigger SQL query to get all items
	 *
	 * @param $sql_parts
	 * @param $current_page
	 * @param $is_taxonomy
	 *
	 * @return array
	 */
	public static function prepare_sql_query( $sql_parts, $current_page, $is_taxonomy = false ) {
		global $permalink_manager_options, $wpdb;

		$per_page = is_numeric( $permalink_manager_options['screen-options']['per_page'] ) ? $permalink_manager_options['screen-options']['per_page'] : 10;
		$offset   = ( $current_page - 1 ) * $per_page;

		// Prepare the count SQL query
		$count_query_parts          = $sql_parts;
		$count_query_parts['start'] = preg_replace( '/(SELECT.*FROM)/', 'SELECT COUNT(*) FROM', $count_query_parts['start'] );
		$count_query                = apply_filters( 'permalink_manager_filter_uri_editor_query', implode( "", $count_query_parts ), $count_query_parts, $is_taxonomy );
		$total_items_raw            = $wpdb->get_var( $count_query );

		// Pagination support
		$sql_query = implode( "", $sql_parts );
		$sql_query .= sprintf( " LIMIT %d, %d", $offset, $per_page );

		// Get items
		$sql_query     = apply_filters( 'permalink_manager_filter_uri_editor_query', $sql_query, $sql_parts, $is_taxonomy );
		$all_items_raw = $wpdb->get_results( $sql_query, ARRAY_A );

		$total_items = ( is_numeric( $total_items_raw ) ) ? (int) $total_items_raw : 0;
		$all_items   = ( ! empty( $all_items_raw ) ) ? $all_items_raw : array();

		return array( $all_items, $total_items, $per_page );
	}

}
