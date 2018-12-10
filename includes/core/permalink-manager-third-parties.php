<?php

/**
* Third parties integration
*/
class Permalink_Manager_Third_Parties extends Permalink_Manager_Class {

	public function __construct() {
		add_action('init', array($this, 'init_hooks'), 99);
	}

	function init_hooks() {
		global $sitepress_settings, $permalink_manager_options, $polylang;

		// 0. Stop redirect
		add_action('wp', array($this, 'stop_redirect'), 0);

		// 1. WPML & Polylang
		if($sitepress_settings || !empty($polylang->links_model->options)) {
			// Detect Post/Term function
			if(!empty($permalink_manager_options['general']['fix_language_mismatch'])) {
				add_filter('permalink-manager-detected-post-id', array($this, 'wpml_language_mismatch_fix'), 9, 3);
				add_filter('permalink-manager-detected-term-id', array($this, 'wpml_language_mismatch_fix'), 9, 3);
			}

			// URI Editor
			add_filter('permalink-manager-uri-editor-extra-info', array($this, 'wpml_lang_column_content_uri_editor'), 9, 3);

			if((isset($sitepress_settings['language_negotiation_type']) && in_array($sitepress_settings['language_negotiation_type'], array(1, 2))) || (isset($polylang->links_model->options['force_lang']) && in_array($polylang->links_model->options['force_lang'], array(1, 3)))) {
				add_filter('permalink-manager-detect-uri', array($this, 'wpml_detect_post'), 9, 3);
				add_filter('permalink_manager-filter-permalink-base', array($this, 'wpml_append_lang_prefix'), 9, 2);
				add_filter('template_redirect', array($this, 'wpml_redirect'), 0, 998 );
			} else if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 3) {
				add_filter('permalink-manager-detect-uri', array($this, 'wpml_ignore_lang_query_parameter'), 9);
			}

			// Translate slugs
			if(class_exists('WPML_Slug_Translation')) {
				add_filter('permalink_manager_filter_post_type_slug', array($this, 'wpml_translate_post_type_slug'), 9, 3);
				// add_filter('permalink_manager_filter_taxonomy_slug', array($this, 'wpml_translate_taxonomy_slug'), 9, 3);
			}
		}

		// 2. AMP
		if(defined('AMP_QUERY_VAR')) {
			// Detect AMP endpoint
			add_filter('permalink-manager-detect-uri', array($this, 'detect_amp'), 10, 2);
			add_filter('request', array($this, 'enable_amp'), 10, 1);
		}

		// 3. WooCommerce
		if(class_exists('WooCommerce')) {
			add_filter('request', array($this, 'woocommerce_detect'), 9, 1);
			add_filter('template_redirect', array($this, 'woocommerce_checkout_fix'), 9);

			if(class_exists('WooCommerce') && class_exists('Permalink_Manager_Pro_Functions')) {
				if(is_admin()){
					add_filter('woocommerce_coupon_data_tabs', 'Permalink_Manager_Pro_Functions::woocommerce_coupon_tabs');
					add_action('woocommerce_coupon_data_panels', 'Permalink_Manager_Pro_Functions::woocommerce_coupon_panel');
					add_action('woocommerce_coupon_options_save', 'Permalink_Manager_Pro_Functions::woocommerce_save_coupon_uri', 9, 2);
				}
				add_filter('request', 'Permalink_Manager_Pro_Functions::woocommerce_detect_coupon_code', 1, 1);
				add_filter('permalink-manager-disabled-post-types', 'Permalink_Manager_Pro_Functions::woocommerce_coupon_uris', 9, 1);
			}
		}

		// 4. Theme My Login
		if(class_exists('Theme_My_Login')) {
			add_filter('permalink_manager_filter_final_post_permalink', array($this, 'tml_keep_query_parameters'), 9, 3);
		}

		// 5. Yoast SEO
		add_filter('wpseo_xml_sitemap_post_url', array($this, 'yoast_fix_sitemap_urls'));

		// 6. WooCommerce Wishlist Plugin
		if(function_exists('tinv_get_option')) {
			add_filter('permalink-manager-detect-uri', array($this, 'ti_woocommerce_wishlist_uris'), 15, 3);
		}

		// 7. Revisionize
		if(defined('REVISIONIZE_ROOT')) {
			add_action('revisionize_after_create_revision', array($this, 'revisionize_keep_post_uri'), 9, 2);
			add_action('revisionize_before_publish', array($this,'revisionize_clone_uri'), 9, 2);
		}

		// 8. WP All Import
		if(class_exists('PMXI_Plugin') && (empty($permalink_manager_options['general']['pmxi_import_support']))) {
			add_action('pmxi_extend_options_featured', array($this, 'wpaiextra_uri_display'), 9, 2);
			add_filter('pmxi_options_options', array($this, 'wpai_api_options'));
			add_filter('pmxi_addons', array($this, 'wpai_api_register'));
			add_filter('wp_all_import_addon_parse', array($this, 'wpai_api_parse'));
			add_filter('wp_all_import_addon_import', array($this, 'wpai_api_import'));
			add_action('pmxi_saved_post', array($this, 'wpai_save_redirects'));
		}
	}

	/**
	 * 0. Stop redirect
	 */
	public static function stop_redirect() {
		global $wp_query;

		if(!empty($wp_query->query)) {
			$query_vars = $wp_query->query;

			// WordPress Photo Seller Plugin
			if(!empty($query_vars['image_id']) && !empty($query_vars['gallery_id'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// Ultimate member
			else if(!empty($query_vars['um_user']) && !empty($query_vars['um_user'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
		}
	}

	/**
	 * 1. WPML filters
	 */
	public static function wpml_get_language_code($element) {
		if(isset($element->post_type)) {
			$element_id = $element->ID;
			$element_type = $element->post_type;
		} else if(isset($element->taxonomy)) {
			$element_id = $element->term_taxonomy_id;
			$element_type = $element->taxonomy;
		} else {
			return false;
		}

		$lang_code = apply_filters('wpml_element_language_code', null, array('element_id' => $element_id, 'element_type' => $element_type));

		// Use default language if nothing detected
		return ($lang_code) ? $lang_code : self::get_default_language();
	}

	public static function get_default_language() {
		global $sitepress;

		if(function_exists('pll_default_language')) {
			$def_lang = pll_default_language('slug');
		} else if(is_object($sitepress)) {
			$def_lang = $sitepress->get_default_language();
		} else {
			$def_lang = '';
		}

		return $def_lang;
	}

	function wpml_language_mismatch_fix($item_id, $uri_parts, $is_term = false) {
		global $wp, $language_code;

		if($is_term) {
			$element = get_term($item_id);
			if(!empty($element) && !is_wp_error($element)) {
				$element_id = $element->term_taxonomy_id;
			} else {
				return false;
			}
		} else {
			$element = get_post($item_id);

			if(!empty($element->post_type)) {
				$element_type = $element->post_type;
				$element_id = $item_id;
			}
		}

		// Stop if no term or post is detected
		if(empty($element)) { return false; }

		$language_code = self::wpml_get_language_code($element);

		if(!empty($uri_parts['lang']) && ($uri_parts['lang'] != $language_code)) {
			$wpml_item_id = apply_filters('wpml_object_id', $element_id);
			$item_id = (is_numeric($wpml_item_id)) ? $wpml_item_id : $item_id;
		}

		return $item_id;
	}

	function wpml_detect_post($uri_parts, $request_url, $endpoints) {
		global $sitepress, $sitepress_settings, $polylang;

		if(!empty($sitepress_settings['active_languages'])) {
			$languages_list = implode("|", $sitepress_settings['active_languages']);
			$default_language = $sitepress->get_default_language();
		} elseif(function_exists('pll_languages_list')) {
			$languages_array = pll_languages_list();
			$languages_list = (is_array($languages_array)) ? implode("|", $languages_array) : "";
			$default_language = pll_default_language();
		}

		// Fix for multidomain language configuration
		if((isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 2) || (!empty($polylang->options['force_lang']) && $polylang->options['force_lang'] == 3)) {
			if(!empty($polylang->options['domains'])) {
				$domains = (array) $polylang->options['domains'];
			} else if(!empty($sitepress_settings['language_domains'])) {
				$domains = (array) $sitepress_settings['language_domains'];
			}

			foreach($domains as &$domain) {
				$domain = preg_replace('/((http(s)?:\/\/(www\.)?)|(www\.))?(.+?)\/?$/', 'http://$6', $domain);
			}

			$request_url = trim(str_replace($domains, "", $request_url), "/");
		}

		if(!empty($languages_list)) {
			//preg_match("/^(?:({$languages_list})\/)?(.+?)(?|\/({$endpoints})[\/$]([^\/]*)|\/()([\d+]))?\/?$/i", $request_url, $regex_parts);
			preg_match("/^(?:({$languages_list})\/)?(.+?)(?|\/({$endpoints})(?|\/(.*)|$)|\/()([\d]+)\/?)?$/i", $request_url, $regex_parts);

			$uri_parts['lang'] = (!empty($regex_parts[1])) ? $regex_parts[1] : $default_language;
			$uri_parts['uri'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
			$uri_parts['endpoint'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";
			$uri_parts['endpoint_value'] = (!empty($regex_parts[4])) ? $regex_parts[4] : "";
		}

		return $uri_parts;
	}

	function wpml_append_lang_prefix($base, $element) {
		global $sitepress_settings, $polylang, $permalink_manager_uris;

		$language_code = self::wpml_get_language_code($element);
		$default_language_code = self::get_default_language();

		// Last instance - use language paramater from &_GET array
		if(is_admin()) {
			$language_code = (empty($language_code) && !empty($_GET['lang'])) ? $_GET['lang'] : $language_code;
		}

		// Adjust URL base
		if(!empty($language_code)) {
			// A. Different domain per language
			if((isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 2) || (!empty($polylang->options['force_lang']) && $polylang->options['force_lang'] == 3)) {
				if(!empty($polylang->options['domains'])) {
					$domains = $polylang->options['domains'];
				} else if(!empty($sitepress_settings['language_domains'])) {
					$domains = $sitepress_settings['language_domains'];
				}

				$is_term = (!empty($element->term_taxonomy_id)) ? true : false;
				$element_id = ($is_term) ? "tax-{$element->term_taxonomy_id}" : $element->ID;

				// Filter only custom permalinks
				if(empty($permalink_manager_uris[$element_id]) || empty($domains)) { return $base; }

				// Replace the domain name
				if(!empty($domains[$language_code])) {
					$base = trim($domains[$language_code], "/");

					// Append URL scheme
					if(!preg_match("~^(?:f|ht)tps?://~i", $base)) {
						$home_url = get_home_url();
						$scehme = parse_url($home_url, PHP_URL_SCHEME);
						$base = "{$scehme}://{$base}";
			    }
				}
			}
			// B. Append prefix
			else {
				// Hide language code if "Use directory for default language" option is enabled
				$hide_prefix_for_default_lang = ((isset($sitepress_settings['urls']['directory_for_default_language']) && $sitepress_settings['urls']['directory_for_default_language'] != 1) || !empty($polylang->links_model->options['hide_default'])) ? true : false;

				if($hide_prefix_for_default_lang && ($default_language_code == $language_code)) {
					return $base;
				} else {
					$base .= "/{$language_code}";
				}
			}
		}

		return $base;
	}

	function wpml_translate_post_type_slug($post_type_slug, $element, $post_type) {
		$post = (is_integer($element)) ? get_post($element) : $element;
		$language_code = self::wpml_get_language_code($post);

		$post_type_slug = apply_filters('wpml_get_translated_slug', $post_type_slug, $post_type, $language_code);

		// Translate %post_type% tag in custom permastructures
		return $post_type_slug;
	}

	function wpml_lang_column_uri_editor($columns) {
		if(class_exists('SitePress') || class_exists('Polylang')) {
			$columns['post_lang'] = __('Language', 'permalink-manager');
		}

		return $columns;
	}

	function wpml_lang_column_content_uri_editor($output, $column, $element) {
		$language_code = self::wpml_get_language_code($element);
		$output .= (!empty($language_code)) ? sprintf(" | <span><strong>%s:</strong> %s</span>", __("Language"), $language_code) : "";

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
		preg_match("/^(.+?)\/({$amp_query_var})?\/?$/i", $uri_parts['uri'], $regex_parts);
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
	 * Parse Custom Permalinks
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
	 * 3. WooCommerce
	 */
	function woocommerce_detect($query) {
		global $woocommerce, $pm_query;

		$shop_page_id = get_option('woocommerce_shop_page_id');

		// WPML - translate shop page id
		$shop_page_id = apply_filters('wpml_object_id', $shop_page_id, 'page', TRUE);

		// Fix shop page
		if(!empty($pm_query['id']) && is_numeric($pm_query['id']) && $shop_page_id == $pm_query['id']) {
			$query['post_type'] = 'product';
			unset($query['pagename']);
		}

		// Fix WooCommerce pages
		if(!empty($woocommerce->query->query_vars)) {
			$query_vars = $woocommerce->query->query_vars;

			foreach($query_vars as $key => $val) {
				if(isset($query[$key])) {
					$woocommerce_page = true;
					$query['do_not_redirect'] = 1;
					break;
				}
			}
		}

		return $query;
	}

	function woocommerce_checkout_fix() {
		global $wp_query, $pm_query, $permalink_manager_options;

		// Redirect from Shop archive to selected page
		if(is_shop() && empty($pm_query['id'])) {
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
		if(is_checkout() || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url())) {
			$wp_query->query_vars['do_not_redirect'] = 1;
		}
	}

	/**
	 * 4. Theme My Login
	 */
	function tml_keep_query_parameters($permalink, $post, $old_permalink) {
		// Get the query string from old permalink
		$get_parameters = (($pos = strpos($old_permalink, "?")) !== false) ? substr($old_permalink, $pos) : "";

		return $permalink . $get_parameters;
	}

	/**
	 * 5. Fix Yoast's homepage URL
	 */
	function yoast_fix_sitemap_urls($permalink) {
		if(class_exists('WPSEO_Utils')) {
			$home_url = WPSEO_Utils::home_url();
			$home_protocol = parse_url($home_url, PHP_URL_SCHEME);

			$permalink = preg_replace("/http(s)?/", $home_protocol, $permalink);
		}

		return $permalink;
	}

	/**
	 * 6. Support WooCommerce Wishlist Plugin
	 */
	function ti_woocommerce_wishlist_uris($uri_parts, $request_url, $endpoints) {
		global $permalink_manager_uris, $wp;

		$wishlist_pid = tinv_get_option('general', 'page_wishlist');

		// Find the Wishlist page URI
		if(is_numeric($wishlist_pid) && !empty($permalink_manager_uris[$wishlist_pid])) {
			$wishlist_uri = preg_quote($permalink_manager_uris[$wishlist_pid], '/');

			// Extract the Wishlist ID
			preg_match("/^({$wishlist_uri})\/([^\/]+)\/?$/", $uri_parts['uri'], $output_array);

			if(!empty($output_array[2])) {
				$uri_parts['uri'] = $output_array[1];
				$uri_parts['endpoint'] = 'tinvwlID';
				$uri_parts['endpoint_value'] = $output_array[2];
			}
		}

		return $uri_parts;
	}

	/**
	 * 7. Revisionize
	 */
	function revisionize_keep_post_uri($old_id, $new_id) {
		global $permalink_manager_uris;

		// Copy the custom URI from original post and apply it to the new temp. revision post
		if(!empty($permalink_manager_uris[$old_id])) {
			$permalink_manager_uris[$new_id] = $permalink_manager_uris[$old_id];

			update_option('permalink-manager-uris', $permalink_manager_uris);
		}
	}

	function revisionize_clone_uri($old_id, $new_id) {
		global $permalink_manager_uris;

		if(!empty($permalink_manager_uris[$new_id])) {
			// Copy the custom URI from revision post and apply it to the original post
			$permalink_manager_uris[$old_id] = $permalink_manager_uris[$new_id];
			unset($permalink_manager_uris[$new_id]);

			update_option('permalink-manager-uris', $permalink_manager_uris);
		}
	}

	/**
	 * 8. WP All Import
	 */
	function wpaiextra_uri_display($post_type, $current_values) {

		// Check if post type is supported
		if(Permalink_Manager_Helper_Functions::is_disabled($post_type)) { return; }

		// Get custom URI format
		$custom_uri = (!empty($current_values['custom_uri'])) ? sanitize_text_field($current_values['custom_uri']) : "";

		$html = '<div class="wpallimport-collapsed closed wpallimport-section">';
		$html .= '<div class="wpallimport-content-section">';
		$html .= sprintf('<div class="wpallimport-collapsed-header"><h3>%s</h3></div>', __('Permalink Manager', 'permalink-manager'));
		$html .= '<div class="wpallimport-collapsed-content">';

		$html .= '<div class="template_input">';
		$html .= Permalink_Manager_Admin_Functions::generate_option_field('custom_uri', array('extra_atts' => 'style="width:100%; line-height: 25px;"', 'placeholder' => __('Custom URI', 'permalink-manager'), 'value' => $custom_uri));
		$html .= wpautop(sprintf(__('If empty, a default permalink based on your current <a href="%s" target="_blank">permastructure settings</a> will be used.', 'permalink-manager'), Permalink_Manager_Admin_Functions::get_admin_url('&section=permastructs')));
		$html .= '</div>';

		// $html .= print_r($current_values, true);

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		echo $html;
	}

	function wpai_api_options($all_options) {
		return $all_options + array('custom_uri' => null);
	}

	function wpai_api_register($addons) {
		if(empty($addons[PERMALINK_MANAGER_PLUGIN_SLUG])) {
			$addons[PERMALINK_MANAGER_PLUGIN_SLUG] = 1;
		}
		return $addons;
	}

	function wpai_api_parse($functions) {
		$functions[PERMALINK_MANAGER_PLUGIN_SLUG] = array($this, 'wpai_api_parse_function');
		return $functions;
	}
	function wpai_api_import($functions) {
		$functions[PERMALINK_MANAGER_PLUGIN_SLUG] = array($this, 'wpai_api_import_function');
		return $functions;
	}

	function wpai_api_parse_function($data) {
		extract($data);

		$data = array(); // parsed data
		$option_name = 'custom_uri';

		if(!empty($import->options[$option_name])) {
			$this->logger = $data['logger'];
			$cxpath = $xpath_prefix . $import->xpath;
			$tmp_files = array();

			if(isset($import->options[$option_name]) && $import->options[$option_name] != '') {
				if($import->options[$option_name] == "xpath") {
					if ($import->options[$this->slug]['xpaths'][$option_name] == "") {
						$count and $this->data[$option_name] = array_fill(0, $count, "");
					} else {
						$data[$option_name] = XmlImportParser::factory($xml, $cxpath, (string) $import->options['xpaths'][$option_name], $file)->parse();
						$tmp_files[] = $file;
					}
				} else {
					$data[$option_name] = XmlImportParser::factory($xml, $cxpath, (string) $import->options[$option_name], $file)->parse();
					$tmp_files[] = $file;
				}
			} else {
				$data[$option_name] = array_fill(0, $count, "");
			}

			foreach ($tmp_files as $file) {
				unlink($file);
			}
		}

		return $data;
	}

	function wpai_api_import_function($importData, $parsedData) {
		global $permalink_manager_uris;

		// Check if post type is disabled
		if(empty($parsedData) || Permalink_Manager_Helper_Functions::is_disabled($importData['post_type'], 'post_type')) { return; }

		// Get the parsed custom URI
		$index = (!empty($importData['i'])) ? $importData['i'] : false;
		$pid = (!empty($importData['pid'])) ? $importData['pid'] : false;

		if($index && $pid && !empty($parsedData['custom_uri'][$index])) {
			$custom_uri = Permalink_Manager_Helper_Functions::sanitize_title($parsedData['custom_uri'][$index]);

			if(!empty($custom_uri)) {
				$permalink_manager_uris[$pid] = $custom_uri;
				update_option('permalink-manager-uris', $permalink_manager_uris);
			}
		}
	}

	function wpai_save_redirects($pid) {
		global $permalink_manager_external_redirects, $permalink_manager_uris;

		$external_url = get_post_meta($pid, '_external_redirect', true);
		$external_url = (empty($external_url)) ? get_post_meta($pid, 'external_redirect', true) : $external_url;

		if($external_url && class_exists('Permalink_Manager_Pro_Functions')) {
			Permalink_Manager_Pro_Functions::save_external_redirect($external_url, $pid);
		}

		// Save custom URI only if the post type is not disabled (third parameter in get_default_post_uri() function)
		if(empty($permalink_manager_uris[$pid])) {
			$custom_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri($pid, false, true);

			if(!empty($custom_uri)) {
				$permalink_manager_uris[$pid] = $custom_uri;
				update_option('permalink-manager-uris', $permalink_manager_uris);
			}
		}
	}

}
?>
