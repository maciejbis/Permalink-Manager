<?php

/**
* Additional functions used in classes and another subclasses
*/
class Permalink_Manager_Helper_Functions extends Permalink_Manager_Class {

	public function __construct() { }

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
	static function get_primary_term($post_id, $taxonomy) {
		global $permalink_manager_options;

		if($permalink_manager_options['miscellaneous']['yoast_primary_term'] == 1 && class_exists('WPSEO_Primary_Term')) {
			$primary_term = new WPSEO_Primary_Term($taxonomy, $post_id);
			$primary_term = get_term($primary_term->get_primary_term());
			return (!is_wp_error($primary_term)) ? $primary_term->slug : "";
		} else {
			return '';
		}
	}

	/**
	* Get post_types array
	*/
	static function get_post_types_array($format = null, $cpt = null) {
		$post_types = apply_filters('permalink-manager-post-types', get_post_types( array('public' => true), 'objects'));

		$post_types_array = array();
		if($format == 'full') {
			foreach ( $post_types as $post_type ) {
				$post_types_array[$post_type->name] = array('label' => $post_type->labels->name, 'name' => $post_type->name);
			}
		} else {
			foreach ( $post_types as $post_type ) {
				$post_types_array[$post_type->name] = $post_type->labels->name;
			}
		}

		return (empty($cpt)) ? $post_types_array : $post_types_array[$cpt];
	}

	/**
	* Get array with all taxonomies
	*/
	static function get_taxonomies_array($format = null, $tax = null) {
		$taxonomies = apply_filters('permalink-manager-taxonomies', get_taxonomies(array('public' => true, 'rewrite' => true), 'objects'));

		$taxonomies_array = array();
		if($format == 'full') {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomies_array[$taxonomy->name] = array('label' => $taxonomy->labels->name, 'name' => $taxonomy->name);
			}
		} else {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomies_array[$taxonomy->name] = $taxonomy->labels->name;
			}
		}

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
	 * Get default language (WPML & Polylang)
	 */
	static function get_language() {
		global $sitepress;
		$def_lang = '';

		if(function_exists('pll_default_language')) {
			$def_lang = pll_default_language('slug');
		} else if(is_object($sitepress)) {
			$def_lang = $sitepress->get_default_language();
		}

		return $def_lang;
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

}
