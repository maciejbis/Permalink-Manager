<?php

/**
* Third parties integration
*/
class Permalink_Manager_Third_Parties extends Permalink_Manager_Class {

	public function __construct() {
		add_action('init', array($this, 'init_hooks'), 99);
	}

	function init_hooks() {
		global $sitepress_settings, $permalink_manager_options;

		// 1. WPML
		if($sitepress_settings) {
			// Detect Post/Term function
			add_filter('permalink-manager-detected-post-id', array($this, 'wpml_language_mismatch_fix'), 9, 3);
			add_filter('permalink-manager-detected-term-id', array($this, 'wpml_language_mismatch_fix'), 9, 3);

			// URI Editor
			add_filter('permalink-manager-uri-editor-extra-info', array($this, 'wpml_lang_column_content_uri_editor'), 9, 3);

			// Split the current URL into subparts (check if WPML is active)
			if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 1) {
				add_filter('permalink-manager-detect-uri', array($this, 'wpml_detect_post'), 9, 3);
				add_filter('permalink-manager-post-permalink-prefix', array($this, 'wpml_element_lang_prefix'), 9, 3);
				add_filter('permalink-manager-term-permalink-prefix', array($this, 'wpml_element_lang_prefix'), 9, 3);
				add_filter('template_redirect', array($this, 'wpml_redirect'), 0, 998 );
			} else if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 3) {
				add_filter('permalink-manager-detect-uri', array($this, 'wpml_ignore_lang_query_parameter'), 9);
			}
		}

		// 2. AMP
		if(defined('AMP_QUERY_VAR')) {
			// Detect AMP endpoint
			add_filter('permalink-manager-detect-uri', array($this, 'detect_amp'), 10, 2);
			add_filter('request', array($this, 'enable_amp'), 10, 1);
		}

		// 3. Yoast SEO
		if(class_exists('WPSEO_Options')) {
			$yoast_permalink_options = get_option('wpseo_permalinks');

			// Redirect attachment to parent post/page enabled
			if(!empty($yoast_permalink_options['redirectattachment']) && !empty($permalink_manager_options['general']['yoast_attachment_redirect'])) {
				add_filter('permalink-manager-detected-initial-id', array($this, 'yoast_detect_attachment'), 9, 3);
			}
		}

		// 5. WP All Import
		add_action('pmxi_after_xml_import', array($this, 'pmxi_fix_permalinks'), 10);

		// 6. WooCommerce
		if(class_exists('WooCommerce')) {
			add_filter('permalink-manager-endpoints', array($this, 'woocommerce_endpoints'), 0, 1);
			add_filter('request', array($this, 'woocommerce_detect'), 9, 1);
			add_filter('template_redirect', array($this, 'woocommerce_checkout_fix'), 9);
		}
	}

	/**
	 * 1. WPML filters
	 */
	function wpml_language_mismatch_fix($item_id, $uri_parts, $is_term = false) {
		global $wp, $language_code;

		if($is_term) {
			$current_term = get_term($item_id);
			$element_type = (!empty($current_term) && !is_wp_error($current_term)) ? $current_term->taxonomy : "";
		} else {
			$element_type = get_post_type($item_id);
		}
		$language_code = apply_filters('wpml_element_language_code', null, array('element_id' => $item_id, 'element_type' => $element_type));

		if(!empty($uri_parts['lang']) && ($uri_parts['lang'] != $language_code)) {
			$wpml_item_id = apply_filters('wpml_object_id', $item_id);
			$item_id = (is_numeric($wpml_item_id)) ? $wpml_item_id : $item_id;
		}

		return $item_id;
	}

	function wpml_detect_post($uri_parts, $request_url, $endpoints) {
		//preg_match("/^(?:(\w{2})\/)?(.+?)(?:\/({$endpoints}))?(?:\/([\d+]))?\/?$/i", $request_url, $regex_parts);
		preg_match("/^(?:(\w{2})\/)?(.+?)(?:\/({$endpoints}))?(?:\/([\d]+))?\/?$/i", $request_url, $regex_parts);

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
			if(isset($sitepress_settings['urls']['directory_for_default_language']) && isset($lang_details->language_code) && ($sitepress_settings['urls']['directory_for_default_language'] == 0) && ($default_language == $lang_details->language_code)) {
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
		if(isset($item->post_type)) {
			$post = (is_integer($item)) ? get_post($item) : $item;
			$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $post->ID, 'element_type' => $post->post_type));
		} else {
			$term = (is_integer($item)) ? get_term(intval($item)) : $item;
			$lang_details = apply_filters('wpml_element_language_details', NULL, array('element_id' => $term->term_id, 'element_type' => $term->taxonomy));
		}

		$output .= (!empty($lang_details->language_code)) ? sprintf(" | <span><strong>%s:</strong> %s</span>", __("Language"), $lang_details->language_code) : "";

		return $output;
	}

	function wpml_ignore_lang_query_parameter($uri_parts) {
		global $permalink_manager_uris;

		foreach($permalink_manager_uris as &$uri) {
			$uri = trim(strtok($uri, '?'), "/");
		}

		return $uri_parts;
	}

	function wpml_redirect() {
		global $language_code, $wp_query;

		if(!empty($language_code) && defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE != $language_code && !empty($wp_query->query['do_not_redirect'])) {
			unset($wp_query->query['do_not_redirect']);
		}
	}

	/**
	 * 2. AMP hooks
	 */
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

	/**
	 * 3. Yoast SEO
	 */
	function yoast_detect_attachment($item_id, $uri_parts, $request_url) {
		global $wpdb, $permalink_manager_uris;

		$uri = (!empty($uri_parts['uri'])) ? $uri_parts['uri'] : "";

		if(empty($item_id) && $uri) {
			$slug = basename($uri_parts['uri']);

			// Check if slug is already used by any post or term
			$used_by_post = $wpdb->get_row($wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_name = %s", "publish", $slug ), ARRAY_A);
			$used_by_term = ($used_by_post) ? true : $wpdb->get_row($wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s", $slug ), ARRAY_A);

			if(empty($used_by_post) && empty($used_by_term)) {
				$attachment = $wpdb->get_row($wpdb->prepare( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s", "attachment", $slug ), ARRAY_A);
				$item_id = (!empty($attachment['ID'])) ? $attachment['ID'] : $item_id;
			}
		}

		return $item_id;
	}

	/**
	 * 4. Custom Permalinks
	 */
	public static function custom_permalinks_uris() {
		global $wpdb;

		$custom_permalinks_uris = array();

	  // 1. List tags/categories
	  $table = get_option('custom_permalink_table');
	  if($table && is_array($table)) {
	    foreach ( $table as $permalink => $info ) {
	      $custom_permalinks_uris[] = array(
					'id' => "tax-" . $info['id'],
					'uri' => trim($permalink, "/")
				);
	    }
	  }

	  // 2. List posts/pages
	  $query = "SELECT p.ID, m.meta_value FROM $wpdb->posts AS p LEFT JOIN $wpdb->postmeta AS m ON (p.ID = m.post_id)  WHERE m.meta_key = 'custom_permalink' AND m.meta_value != '';";
	  $posts = $wpdb->get_results($query);
	  foreach($posts as $post) {
	    $custom_permalinks_uris[] = array(
				'id' => $post->ID,
				'uri' => trim($post->meta_value, "/"),
			);
	  }

		return $custom_permalinks_uris;
	}

	static public function import_custom_permalinks_uris() {
		global $permalink_manager_uris, $permalink_manager_before_sections_html;

		$custom_permalinks_plugin = 'custom-permalinks/custom-permalinks.php';

		if(is_plugin_active($custom_permalinks_plugin) && !empty($_POST['disable_custom_permalinks'])) {
			deactivate_plugins($custom_permalinks_plugin);
		}

		// Get a list of imported URIs
		$custom_permalinks_uris = self::custom_permalinks_uris();

		if(!empty($custom_permalinks_uris) && count($custom_permalinks_uris) > 0) {
			foreach($custom_permalinks_uris as $item) {
				$permalink_manager_uris[$item['id']] = $item['uri'];
			}

			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( '"Custom Permalinks" URIs were imported!', 'permalink-manager' ), 'updated');
			update_option('permalink-manager-uris', $permalink_manager_uris);
		} else {
			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'No "Custom Permalinks" URIs were imported!', 'permalink-manager' ), 'error');
		}
	}

	/**
	 * 5. WP All Import
	 */
	function pmxi_fix_permalinks($import_id) {
		global $permalink_manager_uris, $wpdb;

		$post_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}pmxi_posts WHERE import_id = %s", $import_id));

 		// Just in case
 		sleep(3);

 		if(array($post_ids)) {
 			foreach($post_ids as $id) {
 				// Get default post URI
 			  $new_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri($id);
 			  $permalink_manager_uris[$id] = $new_uri;
 			}
 		}

 	  update_option('permalink-manager-uris', $permalink_manager_uris);
 	}

	/**
	 * WooCommerce
	 */
	function woocommerce_endpoints($endpoints) {
		global $woocommerce;

		if(!empty($woocommerce->query->query_vars)) {
			$query_vars = $woocommerce->query->query_vars;

			foreach($query_vars as $key => $val) {
				$endpoints .= "|{$val}";
			}
		}

		return $endpoints;
	}

	function woocommerce_detect($query) {
		global $woocommerce, $pm_item_id;

		// Fix shop page
		if(is_numeric($pm_item_id) && get_option('woocommerce_shop_page_id') == $pm_item_id) {
			$query['post_type'] = 'product';
			unset($query['pagename']);
		}

		return $query;
	}

	function woocommerce_checkout_fix() {
		global $wp_query, $pm_item_id, $permalink_manager_options;

		// Redirect from Shop archive to selected page
		if(is_shop() && empty($pm_item_id)) {
			$redirect_mode = (!empty($permalink_manager_options['general']['redirect'])) ? $permalink_manager_options['general']['redirect'] : false;
			$redirect_shop = apply_filters('permalink-manager-redirect-shop-archive', false);
			$shop_page = get_option('woocommerce_shop_page_id');

			if($redirect_mode && $redirect_shop && $shop_page && empty($wp_query->query_vars['s'])) {
				$shop_url = get_permalink($shop_page);
				wp_safe_redirect($shop_url, $redirect_mode);
				exit();
			}
		}

		// Do not redirect "thank you" & another WooCommerce pages
		if(is_checkout() || is_wc_endpoint_url()) {
			$wp_query->query_vars['do_not_redirect'] = 1;
		}
	}


}
?>
