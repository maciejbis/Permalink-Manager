<?php

/**
* Third parties integration
*/
class Permalink_Manager_Third_Parties extends Permalink_Manager_Class {

	public function __construct() {
		add_action('init', array($this, 'wpml_hooks'), 99);
		add_action('init', array($this, 'amp_hooks'), 99);
	}

	/**
	 * 1. WPML filters
	 */
	function wpml_hooks() {
		global $sitepress_settings;

		// Detect Post/Term function
		add_filter('permalink-manager-detected-post-id', array($this, 'wpml_language_mismatch_fix'), 9, 2);
		add_filter('permalink-manager-detected-term-id', array($this, 'wpml_language_mismatch_fix'), 9, 2);

		// URI Editor
		add_filter('permalink-manager-uri-editor-columns', array($this, 'wpml_lang_column_uri_editor'), 9, 1);
		add_filter('permalink-manager-uri-editor-column-content', array($this, 'wpml_lang_column_content_uri_editor'), 9, 3);

		// Split the current URL into subparts (check if WPML is active)
		if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 1) {
			add_filter('permalink-manager-detect-uri', array($this, 'wpml_detect_post'), 9, 2);
			add_filter('permalink-manager-post-permalink-prefix', array($this, 'wpml_element_lang_prefix'), 9, 3);
			add_filter('permalink-manager-term-permalink-prefix', array($this, 'wpml_element_lang_prefix'), 9, 3);
		}
	}

	function wpml_language_mismatch_fix($item_id, $uri_parts) {
		$lang_details = apply_filters('wpml_post_language_details', NULL, $item_id);

		if(is_array($lang_details) && !empty($uri_parts['lang'])) {
			$language_code = (!empty($lang_details['language_code'])) ? $lang_details['language_code'] : '';
			if($uri_parts['lang'] && ($uri_parts['lang'] != $language_code)) {
				$wpml_item_id = apply_filters('wpml_object_id', $item_id);
				$item_id = (is_numeric($wpml_item_id)) ? $wpml_item_id : $item_id;
			}
		}

		return $item_id;
	}

	function wpml_detect_post($uri_parts, $request_url) {
		preg_match("/^(?:(\w{2})\/)?(.+?)\/?(page|feed|embed|attachment|track)?(?:\/([\d+]))?\/?$/i", $request_url, $regex_parts);

		$uri_parts['lang'] = (!empty($regex_parts[1])) ? $regex_parts[1] : "";
		$uri_parts['uri'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
		$uri_parts['endpoint'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";
		$uri_parts['endpoint_value'] = (!empty($regex_parts[4])) ? $regex_parts[4] : "";

		return $uri_parts;
	}

	function wpml_element_lang_prefix($prefix, $element, $edit_uri_box = false) {
		global $sitepress_settings;

		if(isset($element->post_type)) {
			$post = (is_integer($element)) ? get_post($element) : $element;
			$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $post->ID, 'element_type' => $post->post_type));
		} else {
			$term = (is_numeric($element)) ? get_term(intval($element)) : $element;
			$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $term->term_id, 'element_type' => $term->taxonomy));
		}

		$prefix = (!empty($lang_details->language_code)) ? $lang_details->language_code : '';

		if($edit_uri_box) {
			// Last instance - use language paramater from &_GET array
			$prefix = (empty($prefix) && !empty($_GET['lang'])) ? $_GET['lang'] : $prefix;
		}

		// Append slash to the end of language code if it is not empty
		if(!empty($prefix)) {
			$prefix = "{$prefix}/";

			// Hide language code if "Use directory for default language" option is enabled
			$default_language = Permalink_Manager_Helper_Functions::get_language();
			if(isset($sitepress_settings['urls']['directory_for_default_language']) && ($sitepress_settings['urls']['directory_for_default_language'] == 0) && ($default_language == $lang_details->language_code)) {
				$prefix = "";
			}
		}

		return $prefix;
	}

	function wpml_lang_column_uri_editor($columns) {
		if(class_exists('SitePress')) {
			$columns['post_lang'] = __('Language', 'permalink-manager');
		}

		return $columns;
	}

	function wpml_lang_column_content_uri_editor($output, $column, $item) {
		if($column == 'post_lang') {
			if(isset($item->post_type)) {
				$post = (is_integer($item)) ? get_post($item) : $item;
				$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $post->ID, 'element_type' => $post->post_type));
			} else {
				$term = (is_integer($item)) ? get_term(intval($item)) : $item;
				$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $term->term_id, 'element_type' => $term->taxonomy));
			}

			return (!empty($lang_details->language_code)) ? $lang_details->language_code : "-";
		}

		return $output;
	}

	/**
	 * 2. AMP hooks
	 */
	function amp_hooks() {
		if(defined('AMP_QUERY_VAR')) {
			// Detect AMP endpoint
			add_filter('permalink-manager-detect-uri', array($this, 'detect_amp'), 10, 2);
			add_filter('request', array($this, 'enable_amp'), 10, 1);
		}
	}

	function detect_amp($uri_parts, $request_url) {
		global $amp_enabled;
		$amp_query_var = AMP_QUERY_VAR;

		// Check if AMP should be triggered
		preg_match("/^(.+?)\/?({$amp_query_var})?\/?$/i", $uri_parts['uri'], $regex_parts);
		if(!empty($regex_parts[2])) {
			$uri_parts['uri'] = $regex_parts[1];
			$amp_enabled = true;
		}

		return $uri_parts;
	}

	function enable_amp($query) {
		global $amp_enabled;

		if(!empty($amp_enabled)) {
			$query[AMP_QUERY_VAR] = 1;
		}

		return $query;
	}

}
?>
