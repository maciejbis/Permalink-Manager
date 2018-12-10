<?php

/**
* Additional functions used in classes and another subclasses
*/
class Permalink_Manager_Helper_Functions extends Permalink_Manager_Class {

	public function __construct() {
		add_action('plugins_loaded', array($this, 'init'), 5);
	}

	public function init() {
		// Clear the final default URIs
		add_filter( 'permalink_manager_filter_default_term_uri', array('Permalink_Manager_Helper_Functions', 'clear_single_uri'), 20);
		add_filter( 'permalink_manager_filter_default_post_uri', array('Permalink_Manager_Helper_Functions', 'clear_single_uri'), 20);
	}

	/**
	 * Support for multidimensional arrays - array_map()
	 */
	static function multidimensional_array_map($function, $input) {
		$output = array();

		if(is_array($input)) {
			foreach ($input as $key => $val) {
				$output[$key] = (is_array($val) ? self::multidimensional_array_map($function, $val) : $function($val));
			}
		} else {
			$output = $function($input);
		}

		return $output;
	}

	/**
	 * Get primary term (by Yoast SEO)
	 */
	static function get_primary_term($post_id, $taxonomy, $slug_only = true) {
		global $permalink_manager_options;

		$primary_term_enabled = apply_filters('permalink-manager-primary-term', true);

		if($primary_term_enabled && class_exists('WPSEO_Primary_Term')) {
			$primary_term = new WPSEO_Primary_Term($taxonomy, $post_id);
			$primary_term = get_term($primary_term->get_primary_term());

			if(!is_wp_error($primary_term)) {
				return ($slug_only) ? $primary_term->slug : $primary_term;
			}
		}
		return '';
	}

	/**
	 * Get lowest level term/post
	 */
	static function get_lowest_element($first_element, $elements) {
		if(!empty($elements) && !empty($first_element)) {
			// Get the ID of first element
			if(!empty($first_element->term_id)) {
				$first_element_id = $first_element->term_id;
				$parent_key = 'parent';
			} else if(!empty($first_element->ID)) {
				$first_element_id = $first_element->ID;
				$parent_key = 'post_parent';
			} else if(is_numeric($first_element)) {
				$first_element_id = $first_element;
				$parent_key = 'post_parent';
			} else {
				return false;
			}

			$children = wp_filter_object_list($elements, array($parent_key => $first_element_id));
			if(!empty($children)) {
				// Get the first term
				$child_term = reset($children);
				$first_element = self::get_lowest_element($child_term, $elements);
			}
		}
		return $first_element;
	}

	/**
	 * Get term full slug
	 */
	static function get_term_full_slug($term, $terms, $mode = false, $native_uri = false) {
		global $permalink_manager_uris;

		// Check if term is not empty
		if(empty($term->taxonomy)) { return ''; }

		// Get taxonomy
		$taxonomy = $term->taxonomy;

		// Check if mode is set
		if(empty($mode)) {
			$mode = (is_taxonomy_hierarchical($taxonomy)) ? 2 : 4;
		}

		// A. Get permalink base from the term's custom URI
		if($mode == 1) {
			$term_slug = $permalink_manager_uris["tax-{$term->term_id}"];
		}
		// B. Hierarhcical taxonomy base
		else if($mode == 2) {
			$ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
			$hierarchical_slugs = array();

			foreach((array) $ancestors as $ancestor) {
				$ancestor_term = get_term($ancestor, $taxonomy);
				$hierarchical_slugs[] = ($native_uri) ? $ancestor_term->slug : self::force_custom_slugs($ancestor_term->slug, $ancestor_term);
			}
			$hierarchical_slugs = array_reverse($hierarchical_slugs);
			$term_slug = implode('/', $hierarchical_slugs);

			// Append the term slug now
			$last_term_slug = ($native_uri) ? $term->slug : self::force_custom_slugs($term->slug, $term);
			$term_slug = "{$term_slug}/{$last_term_slug}";
		}
		// C. Force flat taxonomy base - get highgest level term (if %taxonomy_flat% tag is used)
		else if($mode == 4) {
			foreach($terms as $single_term) {
				if($single_term->parent == 0) {
					$term_slug = self::force_custom_slugs($single_term->slug, $single_term);
					break;
				}
			}
		}
		// D. Flat/non-hierarchical taxonomy base - get primary term (if set) or first term
		else if(!empty($term->slug)) {
			$term_slug = ($native_uri) ? $term->slug : Permalink_Manager_Helper_Functions::force_custom_slugs($term->slug, $term);
		}

		return (!empty($term_slug)) ? $term_slug : "";
	}

	static function content_types_disabled_by_default($is_taxonomy = false) {
		if($is_taxonomy) {
			return array('product_shipping_class');
		} else {
			return array('revision', 'algolia_task', 'fl_builder', 'fl-builder', 'fl-theme-layout', 'wc_product_tab', 'wc_voucher', 'wc_voucher_template');
		}
	}

	/**
	 * Allow to disable post types and taxonomies
	 */
	static function get_disabled_post_types() {
		global $permalink_manager_options, $wp_post_types, $wp_rewrite;

		$initial_disabled_post_types = self::content_types_disabled_by_default();

		// Disable post types that are not publicly_queryable
		if(!empty($wp_rewrite)){
			foreach($wp_post_types as $post_type) {
				$is_publicly_queryable = (empty($post_type->publicly_queryable) && empty($post_type->public)) ? false : true;

				if(!$is_publicly_queryable && !in_array($post_type->name, array('post', 'page', 'attachment'))) {
					$initial_disabled_post_types[] = $post_type->name;
				}
			}
		}

		$disabled_post_types = (!empty($permalink_manager_options['general']['partial_disable']['post_types'])) ? array_merge((array) $permalink_manager_options['general']['partial_disable']['post_types'], $initial_disabled_post_types) : $initial_disabled_post_types;

		return apply_filters('permalink-manager-disabled-post-types', $disabled_post_types);
	}

	static function get_disabled_taxonomies() {
		global $permalink_manager_options;

		$initial_disabled_taxonomies = self::content_types_disabled_by_default(true);

		$disabled_taxonomies = (!empty($permalink_manager_options['general']['partial_disable']['taxonomies'])) ? array_merge((array) $permalink_manager_options['general']['partial_disable']['taxonomies'], $initial_disabled_taxonomies) : array();

		return apply_filters('permalink-manager-disabled-taxonomies', $disabled_taxonomies);
	}

	static public function is_disabled($content_name, $content_type = 'post_type', $check_if_exists = true) {
		$out = false;

		if($content_type == 'post_type') {
			$disabled_post_types = self::get_disabled_post_types();
			$post_type_exists = ($check_if_exists) ? post_type_exists($content_name) : true;
			$out = ((is_array($disabled_post_types) && in_array($content_name, $disabled_post_types)) || empty($post_type_exists)) ? true : false;
		} else {
			$disabled_taxonomies = self::get_disabled_taxonomies();
			$taxonomy_exists = ($check_if_exists) ? taxonomy_exists($content_name) : true;
			$out = ((is_array($disabled_taxonomies) && in_array($content_name, $disabled_taxonomies)) || empty($taxonomy_exists)) ? true : false;
		}

		return $out;
	}

	/**
	* Get post_types array
	*/
	static function get_post_types_array($format = null, $cpt = null, $all = false) {
		global $wp_post_types;

		$post_types = get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => false), 'objects', 'AND');
		$disabled_post_types = self::get_disabled_post_types();

		// Include native post types
		foreach(array('post', 'page', 'attachment') as $post_type_name) {
			$post_types[$post_type_name] = $wp_post_types[$post_type_name];
		}

		$post_types_array = array();
		foreach($post_types as $post_type) {
			$value = ($format == 'full') ? array('label' => $post_type->labels->name, 'name' => $post_type->name) : $post_type->labels->name;
			$post_types_array[$post_type->name] = $value;
		}

		// Soft exclude disabled post types
		if(!$all && is_array($disabled_post_types)) {
			foreach($disabled_post_types as $post_type) {
				if(!empty($post_types_array[$post_type])) { unset($post_types_array[$post_type]); }
			}
		}

		// Hard exclude disabled post types
		$hard_disabled_post_types = self::content_types_disabled_by_default();
		if(is_array($hard_disabled_post_types)) {
			foreach($hard_disabled_post_types as $post_type) {
				if(!empty($post_types_array[$post_type])) { unset($post_types_array[$post_type]); }
			}
		}

		return (empty($cpt)) ? $post_types_array : $post_types_array[$cpt];
	}

	/**
	* Get array with all taxonomies
	*/
	static function get_taxonomies_array($format = null, $tax = null, $prefix = false, $all = false, $settings = false) {
		$taxonomies = get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects', 'AND');
		$disabled_taxonomies = self::get_disabled_taxonomies();

		$taxonomies_array = array();

		foreach($taxonomies as $taxonomy) {
			$key = ($prefix) ? "tax-{$taxonomy->name}" : $taxonomy->name;
			if($format == 'full') {
				$taxonomies_array[$taxonomy->name] = array('label' => $taxonomy->labels->name, 'name' => $taxonomy->name);
			} else {
				$taxonomies_array[$key] = $taxonomy->labels->name;
			}
		}

		// Soft exclude taxonomies
		if(!$all && is_array($disabled_taxonomies)) {
			foreach($disabled_taxonomies as $taxonomy) {
				if(!empty($taxonomies_array[$taxonomy])) { unset($taxonomies_array[$taxonomy]); }
			}
		}

		// Hard exclude disabled taxonomies
		$hard_disabled_taxonomies = self::content_types_disabled_by_default(true);
		if(is_array($hard_disabled_taxonomies)) {
			foreach($hard_disabled_taxonomies as $taxonomy) {
				if(!empty($taxonomies_array[$taxonomy])) { unset($taxonomies_array[$taxonomy]); }
			}
		}

		ksort($taxonomies_array);

		return (empty($tax)) ? $taxonomies_array : $taxonomies_array[$tax];
	}

	/**
	* Get permastruct
	*/
	static function get_default_permastruct($post_type = 'page', $remove_post_tag = false) {
		global $wp_rewrite;

		// Get default permastruct
		if($post_type == 'page') {
			$permastruct = $wp_rewrite->get_page_permastruct();
		} else if($post_type == 'post') {
			$permastruct = get_option('permalink_structure');
		} else {
			$permastruct = $wp_rewrite->get_extra_permastruct($post_type);
		}

		return ($remove_post_tag) ? trim(str_replace(array("%postname%", "%pagename%", "%{$post_type}%"), "", $permastruct), "/") : $permastruct;
	}

	/**
	 * Get all endpoints
	 */
	static function get_endpoints() {
		global $wp_rewrite;

		$pagination_endpoint = (!empty($wp_rewrite->pagination_base)) ? $wp_rewrite->pagination_base : 'page';

		// Start with default endpoints
		$endpoints = "{$pagination_endpoint}|feed|embed|attachment|trackback|filter";

		if(!empty($wp_rewrite->endpoints)) {
			foreach($wp_rewrite->endpoints as $endpoint) {
				$endpoints .= "|{$endpoint[1]}";
			}
		}

		return apply_filters("permalink-manager-endpoints", str_replace("/", "\/", $endpoints));
	}

	/**
	* Remove post tag from permastructure
	*/
	static function remove_post_tag($permastruct) {
		$post_types = self::get_post_types_array('full');

		// Get all post tags
		$post_tags = array("%postname%", "%pagename%");
		foreach($post_types as $post_type) {
			$post_tags[] = "%{$post_type['name']}%";
		}

		$permastruct = str_replace($post_tags, "", $permastruct);
		return trim($permastruct, "/");
	}

	/**
	* Structure Tags & Rewrite functions
	*/
	static function get_all_structure_tags($code = true, $seperator = ', ', $hide_slug_tags = true) {
		global $wp_rewrite;

		$tags = $wp_rewrite->rewritecode;

		// Hide slug tags
		if($hide_slug_tags) {
			$post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
			foreach($post_types as $post_type => $post_type_name) {
				$post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);
				// Find key with post type tag from rewritecode
				$key = array_search($post_type_tag, $tags);
				if($key) { unset($tags[$key]); }
			}
		}

		// Extra tags
		$tags[] = '%taxonomy%';
		$tags[] = '%post_type%';

		foreach($tags as &$tag) {
			$tag = ($code) ? "<code>{$tag}</code>" : "{$tag}";
		}
		$output = implode($seperator, $tags);

		return "<span class=\"structure-tags-list\">{$output}</span>";
	}

	/**
	* Get endpoint used to mark the postname or its equivalent for custom post types and pages in permastructures
	*/
	static function get_post_tag($post_type) {
		// Get the post type (with fix for posts & pages)
		if($post_type == 'page') {
			$post_type_tag = '%pagename%';
		} else if ($post_type == 'post') {
			$post_type_tag = '%postname%';
		} else {
			$post_type_tag = "%{$post_type}%";
		}
		return $post_type_tag;
	}

	/**
	* Find taxonomy name using "term_id"
	*/
	static function get_tax_name($term, $term_by = 'id') {
		$term_object = get_term_by($term_by, $term);
		return (isset($term_object->taxonomy)) ? $term_object->taxonomy : '';
	}

	/**
	 * Get permalink base (home URL)
	 */
	public static function get_permalink_base($element = null) {
		return apply_filters('permalink_manager-filter-permalink-base', trim(get_option('home'), "/"), $element);
	}

	/**
	 * Sanitize multidimensional array
	 */
	static function sanitize_array($data = array()) {
		if (!is_array($data) || !count($data)) {
			return array();
		}

		foreach ($data as $k => $v) {
			if (!is_array($v) && !is_object($v)) {
				$data[$k] = htmlspecialchars(trim($v));
			}
			if (is_array($v)) {
				$data[$k] = self::sanitize_array($v);
			}
		}
		return $data;
	}

	/**
	 * Encode URI and keep slashes
	 */
	static function encode_uri($uri) {
		return str_replace("%2F", "/", urlencode($uri));
	}

	/**
	 * Slugify function
	 */
	public static function sanitize_title($str, $keep_percent_sign = false, $force_lowercase = null, $sanitize_slugs = null) {
		global $permalink_manager_options;

		// Foree lowercase & hyphens
		$force_lowercase = (!is_null($force_lowercase)) ? $force_lowercase : apply_filters('permalink-manager-force-lowercase-uris', true);
		if(!is_null($sanitize_slugs)) {
			$sanitize_slugs = ($permalink_manager_options['general']['force_custom_slugs'] == 2) ? false : true;
		}

		// Trim slashes & whitespaces
		$clean = trim($str, " /");

		// Remove accents
		$clean = remove_accents($clean);

		// $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
		$percent_sign = ($keep_percent_sign) ? "\%" : "";
		$clean = preg_replace("/[^\p{Thai}\p{Xwd}a-zA-Z0-9{$percent_sign}\/_\.|+ -]/u", '', $clean);
		$clean = ($force_lowercase) ? strtolower(trim($clean, '-')) : trim($clean, '-');

		// Remove special characters
		if($sanitize_slugs !== false) {
			$clean = preg_replace("/[\s_|+-]+/", "-", $clean);
			$clean = preg_replace("/[\.]+/", "", $clean);
			$clean = preg_replace('/([-\s+]\/[-\s+])/', '-', $clean);
		} else {
			$clean = preg_replace("/[\s]+/", "-", $clean);
		}

		return $clean;
	}

	/**
	 * Clear the URI
	 */
	public static function clear_single_uri($uri) {
		$uri = preg_replace("/[\s_|+-]+/", "-", $uri);
		$uri = preg_replace('/([-\s+]\/[-\s+])/', '-', $uri);
		$uri = str_replace(array('-/', '/-', '//'), '/', $uri);
		$uri = trim($uri, "/");

		return $uri;
	}

	/**
	 * Remove all slashes
	 */
	public static function remove_slashes($uri) {
		$uri = preg_replace("/[\/]+/", "", $uri);

		return $uri;
	}

	/**
	 * Force custom slugs
	 */
	public static function force_custom_slugs($slug, $object, $flat = false) {
		global $permalink_manager_options;

		if(!empty($permalink_manager_options['general']['force_custom_slugs'])) {
			$title = (!empty($object->name)) ? $object->name : $object->post_title;
			$title = self::remove_slashes($title);

			$old_slug = basename($slug);
			$new_slug = self::sanitize_title($title, false, null, true);

			$slug = ($old_slug != $new_slug) ? str_replace($old_slug, $new_slug, $slug) : $slug;
		}

		if($flat) {
			$slug = preg_replace("/([^\/]+)(.*)/", "$1", $slug);
		}

		return $slug;
	}

	public static function element_exists($element_id) {
		global $wpdb;

		if(strpos($element_id, 'tax-') !== false) {
			$term_id = intval(preg_replace("/[^0-9]/", "", $element_id));
			$element_exists = $wpdb->get_var( "SELECT * FROM {$wpdb->prefix}terms WHERE term_id = {$term_id}" );
		} else {
			$element_exists = $wpdb->get_var( "SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$element_id} AND post_status NOT IN ('auto-draft', 'trash') AND post_type != 'nav_menu_item'" );
		}

		return (!empty($element_exists)) ? $element_exists : false;
	}

	/**
	 * Detect duplicates
	 */
	public static function get_all_duplicates($include_custom_uris = true) {
		global $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_options, $wpdb;

		// Make sure that both variables are arrays
		$all_uris = ($include_custom_uris && is_array($permalink_manager_uris)) ? $permalink_manager_uris : array();
		$permalink_manager_redirects = (is_array($permalink_manager_redirects)) ? $permalink_manager_redirects : array();

		// Convert redirects list, so it can be merged with $permalink_manager_uris
		foreach($permalink_manager_redirects as $element_id => $redirects) {
			if(is_array($redirects)) {
				foreach($redirects as $index => $uri) {
					$all_uris["redirect-{$index}_{$element_id}"] = $uri;
				}
			}
		}

		// Count duplicates
		$duplicates_removed = 0;
		$duplicates_groups = array();
		$duplicates_list = array_count_values($all_uris);
		$duplicates_list = array_filter($duplicates_list, function ($x) { return $x >= 2; });

		// Assign keys to duplicates (group them)
		if(count($duplicates_list) > 0) {
			foreach($duplicates_list as $duplicated_uri => $count) {
				$duplicates_groups[$duplicated_uri] = array_keys($all_uris, $duplicated_uri);
			}
		}

		return $duplicates_groups;
	}

	/**
	 * Check if a single URI is duplicated
	 */
	public static function is_uri_duplicated($uri, $element_id) {
		global $permalink_manager_uris;

 		if(empty($uri) || empty($element_id)) { return false; }

 		$uri = trim(trim(sanitize_text_field($uri)), "/");
 		$element_id = sanitize_text_field($element_id);

 		// Keep the URIs in a separate array just here & unset the URI for requested element to prevent false alert
 		$all_uris = $permalink_manager_uris;
 		unset($permalink_manager_uris[$element_id]);

 		return (in_array($uri, $permalink_manager_uris)) ? 1 : 0;
 	}

	/**
	 * URI Search
	 */
	public static function search_uri($search_query, $content_type = null) {
		global $permalink_manager_uris;

		$found = array();
		$search_query = preg_quote($search_query, '/');

		foreach($permalink_manager_uris as $id => $uri) {
			if(preg_match("/\b$search_query\b/i", $uri)) {
				if($content_type && $content_type == 'taxonomies' && (strpos($id, 'tax-') !== false)) {
					$found[] = (int) abs(filter_var($id, FILTER_SANITIZE_NUMBER_INT));
				} else if($content_type && $content_type == 'posts' && is_numeric($id)) {
					$found[] = (int) filter_var($id, FILTER_SANITIZE_NUMBER_INT);
				} else {
					$found[] = $id;
				}
			}
		}

		return $found;
	}

}
