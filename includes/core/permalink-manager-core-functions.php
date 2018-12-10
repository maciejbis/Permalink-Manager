<?php

/**
* Core function
*/
class Permalink_Manager_Core_Functions extends Permalink_Manager_Class {

	public function __construct() {
		add_action( 'init', array($this, 'init_hooks'), 99);
	}

	function init_hooks() {
		global $permalink_manager_options;

		// Trigger only in front-end
		if(!is_admin() && function_exists('is_customize_preview') && !is_customize_preview()) {
			// Use the URIs set in this plugin
			add_filter( 'request', array($this, 'detect_post'), 0, 1 );

			// Redirect from old URIs to new URIs  + adjust canonical redirect settings
			add_action( 'template_redirect', array($this, 'new_uri_redirect_and_404'), 1);
			add_action( 'parse_query', array($this, 'adjust_canonical_redirect'), 0, 1);

			// Case insensitive permalinks
			if(!empty($permalink_manager_options['general']['case_insensitive_permalinks'])) {
				add_action( 'parse_request', array($this, 'case_insensitive_permalinks'), 0);
			}
			// Force 404 on non-existing pagination pages
			if(!empty($permalink_manager_options['general']['pagination_redirect'])) {
				add_action( 'wp', array($this, 'fix_pagination_pages'), 0);
			}
		}

		// Trailing slashes
		add_filter( 'permalink_manager_filter_final_term_permalink', array($this, 'control_trailing_slashes'), 9);
		add_filter( 'permalink_manager_filter_final_post_permalink', array($this, 'control_trailing_slashes'), 9);
		add_filter( 'permalink_manager_filter_post_sample_permalink', array($this, 'control_trailing_slashes'), 9);

		// Replace empty placeholder tags & remove BOM
		add_filter( 'permalink_manager_filter_default_post_uri', array($this, 'replace_empty_placeholder_tags'), 10, 5 );
		add_filter( 'permalink_manager_filter_default_term_uri', array($this, 'replace_empty_placeholder_tags'), 10, 5 );
	}

	/**
	* The most important Permalink Manager function
	*/
	public static function detect_post($query, $request_url = false, $return_object = false) {
		global $wpdb, $wp, $wp_rewrite, $permalink_manager_uris, $wp_filter, $permalink_manager_options, $pm_query, $pm_uri_parts;

		// Check if any custom URI is used and we are not in WP-Admin dashboard
		if(!(is_array($permalink_manager_uris)) || (empty($query) && empty($request_url))) return $query;

		// Used in debug mode & endpoints
		$old_query = $query;

		/**
		* 1. Prepare URL and check if it is correct (make sure that both requested URL & home_url share the same protoocl and get rid of www prefix)
		*/
		$request_url = (!empty($request_url)) ? parse_url($request_url, PHP_URL_PATH) : $_SERVER['REQUEST_URI'];
		$request_url = strtok($request_url, "?");

		$request_url = sprintf("http://%s%s", str_replace("www.", "", $_SERVER['HTTP_HOST']), $request_url);
		$raw_home_url = trim(get_option('home'));
		$home_url = preg_replace("/http(s)?:\/\/(www\.)?(.+?)\/?$/", "http://$3", $raw_home_url);

		if(filter_var($request_url, FILTER_VALIDATE_URL)) {
			// Check if "Deep Detect" is enabled
			$deep_detect_enabled = apply_filters('permalink-manager-deep-uri-detect', $permalink_manager_options['general']['deep_detect']);

			// Keep only the URI
			$request_url = trim(str_replace($home_url, "", $request_url), "/");

			// Get all the endpoints & pattern
			$endpoints = Permalink_Manager_Helper_Functions::get_endpoints();
			//$pattern = "/^(.+?)(?|\/({$endpoints})\/?([^\/]*)|()\/([\d+]))?\/?$/i";
			//$pattern = "/^(.+?)(?|\/({$endpoints})[\/$]([^\/]*)|()\/([\d+]))?\/?$/i";
			$pattern = "/^(.+?)(?|\/({$endpoints})(?|\/(.*)|$)|\/()([\d]+)\/?)?$/i";

			// Use default REGEX to detect post
			preg_match($pattern, $request_url, $regex_parts);
			$uri_parts['lang'] = false;
			$uri_parts['uri'] = (!empty($regex_parts[1])) ? $regex_parts[1] : "";
			$uri_parts['endpoint'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
			$uri_parts['endpoint_value'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";

			// Allow to filter the results by third-parties + store the URI parts with $pm_query global
			$uri_parts = $pm_query = apply_filters('permalink-manager-detect-uri', $uri_parts, $request_url, $endpoints);

			// Stop the function if $uri_parts is empty
			if(empty($uri_parts)) return $query;

			// Get the URI parts from REGEX parts
			$lang = $uri_parts['lang'];
			$uri = $uri_parts['uri'];
			$endpoint = $uri_parts['endpoint'];
			$endpoint_value = $uri_parts['endpoint_value'];

			// Trim slashes
			$uri = trim($uri, "/");

			// Decode both request URI & URIs array & make them lowercase (and save in a separate variable)
			$uri = strtolower(urldecode($uri));
			$all_uris = array();
			foreach ($permalink_manager_uris as $key => $value) {
				$all_uris[$key] = strtolower(urldecode($value));
			}

			// Ignore URLs with no URI grabbed
			if(empty($uri)) return $query;

			/**
			* 2. Check if found URI matches any element from custom uris array
			*/
			$element_id = array_search($uri, $all_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$element_id = (empty($element_id)) ? array_search("{$uri}.html",  $all_uris) : $element_id;

			// Check again in case someone used post/tax IDs instead of slugs
			if($deep_detect_enabled && (isset($old_query['page']))) {
				$new_item_id = array_search("{$uri}/{$endpoint_value}",  $all_uris);
				if($new_item_id) {
					$element_id = $new_item_id;
					$endpoint_value = $endpoint = "";
				}
			}

			// Check again for attachment custom URIs
			if((isset($old_query['attachment']))) {
				$new_item_id = array_search("{$uri}/{$endpoint}/{$endpoint_value}",  $all_uris);
				if($new_item_id) {
					$element_id = $new_item_id;
					$endpoint_value = $endpoint = "";
				}
			}

			// Allow to filter the item_id by third-parties after initial detection
			$element_id = apply_filters('permalink-manager-detected-initial-id', $element_id, $uri_parts, $request_url);

			// Clear the original query before it is filtered
			$query = ($element_id) ? array() : $query;

			/**
			* 3A. Custom URI assigned to taxonomy
			*/
			if(strpos($element_id, 'tax-') !== false) {
				// Remove the "tax-" prefix
				$term_id = intval(preg_replace("/[^0-9]/", "", $element_id));

				// Filter detected post ID
				$term_id = apply_filters('permalink-manager-detected-term-id', intval($term_id), $uri_parts, true);

				// Get the variables to filter wp_query and double-check if taxonomy exists
				$term = get_term($term_id);
				$term_taxonomy = (!empty($term->taxonomy)) ? $term->taxonomy : false;

				// Check if taxonomy is allowed
				$disabled = (Permalink_Manager_Helper_Functions::is_disabled($term_taxonomy, 'taxonomy')) ? true : false;

				// Proceed only if the term is not removed and its taxonomy is not disabled
				if(!$disabled && $term_taxonomy) {
					// Get some term data
					if($term_taxonomy == 'category') {
						$query_parameter = 'category_name';
					} else if($term_taxonomy == 'post_tag') {
						$query_parameter = 'tag';
					} else {
						$query["taxonomy"] = $term_taxonomy;
						$query_parameter = $term_taxonomy;
					}
					$term_ancestors = get_ancestors($term_id, $term_taxonomy);
					$final_uri = $term->slug;

					// Fix for hierarchical terms
					if(empty($term_ancestors)) {
						foreach ($term_ancestors as $parent) {
							$parent = get_term($parent, $term_taxonomy);
							if(!empty($parent->slug)) {
								$final_uri = $parent->slug . '/' . $final_uri;
							}
						}
					}

					$query["term"] = $final_uri;
					$query[$query_parameter] = $final_uri;
				} else {
					$broken_uri = true;
				}
			}
			/**
			* 3B. Custom URI assigned to post/page/cpt item
			*/
			else if(isset($element_id) && is_numeric($element_id)) {
				// Fix for revisions
				$is_revision = wp_is_post_revision($element_id);
				$element_id = ($is_revision) ? $is_revision : $element_id;

				// Filter detected post ID
				$element_id = apply_filters('permalink-manager-detected-post-id', $element_id, $uri_parts);

				$post_to_load = get_post($element_id);
				$final_uri = (!empty($post_to_load->post_name)) ? $post_to_load->post_name : false;
				$post_type = (!empty($post_to_load->post_type)) ? $post_to_load->post_type : false;

				// Check if post type is allowed
				$disabled = (Permalink_Manager_Helper_Functions::is_disabled($post_type, 'post_type')) ? true : false;

				// Proceed only if the term is not removed and its taxonomy is not disabled
				if(!$disabled && $post_type) {
					$post_type_object = get_post_type_object($post_type);

					// Fix for hierarchical CPT & pages
					if(!(empty($post_to_load->ancestors)) && !empty($post_type_object->hierarchical)) {
						foreach ($post_to_load->ancestors as $parent) {
							$parent = get_post( $parent );
							if($parent && $parent->post_name) {
								$final_uri = $parent->post_name . '/' . $final_uri;
							}
						}
					}

					// Alter query parameters + support drafts URLs
					if($post_to_load->post_status == 'draft') {
						$query['p'] = $element_id;
						$query['preview'] = true;
						$query['post_type'] = $post_type;
					} else if($post_type == 'page') {
						$query['pagename'] = $final_uri;
					} else if($post_type == 'post') {
						$query['name'] = $final_uri;
					} else if($post_type == 'attachment') {
						$query['attachment'] = $final_uri;
					} else {
						// Get the query var
						$query_var = (!empty($post_type_object->query_var)) ? $post_type_object->query_var : $post_type;

						$query['name'] = $final_uri;
						$query['post_type'] = $post_type;
						$query[$query_var] = $final_uri;
					}
				} else {
					$broken_uri = true;
				}
			}

			/**
			 * 4. Auto-remove removed term custom URI & redirects (works if enabled in plugin settings)
			 */
			if(!empty($broken_uri) && !empty($permalink_manager_options['general']['auto_remove_duplicates'])) {
				$remove_broken_uri = Permalink_Manager_Actions::clear_single_element_uris_and_redirects($element_id);

				// Reload page if success
				if($remove_broken_uri) {
					header("Refresh:0");
					exit();
				}
			}

			/**
			* 5A. Endpoints
			*/
			if(!empty($element_id) && (!empty($endpoint) || !empty($endpoint_value))) {
				if(is_array($endpoint)) {
					foreach($endpoint as $endpoint_name => $endpoint_value) {
						$query[$endpoint_name] = $endpoint_value;
					}
				} else if($endpoint == 'feed') {
					$query[$endpoint] = 'feed';
				} else if($endpoint == 'page') {
					$endpoint = 'paged';
					$query[$endpoint] = $endpoint_value;
				} else if($endpoint == 'trackback') {
					$endpoint = 'tb';
					$query[$endpoint] = 1;
				} else if(!$endpoint && is_numeric($endpoint_value)) {
					$query['page'] = $endpoint_value;
				} else {
					$query[$endpoint] = $endpoint_value;
				}

				// Fix for attachments
				if(!empty($query['attachment'])) {
					$query = array('attachment' => $query['attachment'], 'do_not_redirect' => 1);
				}
			}

			/**
			 * 5B. Endpoints - check if any endpoint is set with $_GET parameter
			 */
			if(!empty($element_id) && $deep_detect_enabled && !empty($_GET)) {
				$get_endpoints = array_intersect($wp->public_query_vars, array_keys($_GET));

				if(!empty($get_endpoints)) {
					// Append query vars from $_GET parameters
					foreach($get_endpoints as $endpoint) {
						// Numeric endpoints
						$endpoint_value = (in_array($endpoint, array('page', 'paged', 'attachment_id'))) ? filter_var($_GET[$endpoint], FILTER_SANITIZE_NUMBER_INT) : $_GET[$endpoint];
						$query[$endpoint] = sanitize_text_field($endpoint_value);
					}
				}
			}

			/**
			 * 6. WWW prefix mismatch detect
			 */
			$home_url_has_www = (strpos($raw_home_url, 'www.') !== false) ? true : false;
			$requested_url_has_www = (strpos($_SERVER['HTTP_HOST'], 'www.') !== false) ? true : false;

			if($home_url_has_www != $requested_url_has_www) {
				unset($query['do_not_redirect']);
			}

			/**
			 * 7. Set global with detected item id
			 */
			if(!empty($element_id)) {
				$pm_query['id'] = $element_id;

				// Make the redirects more clever - see new_uri_redirect_and_404() method
				$query['do_not_redirect'] = 1;
			}
		}

		/**
		 * 8. Debug mode
		 */
		if(isset($_REQUEST['debug_url'])) {
			$debug_info['old_query_vars'] = $old_query;
			$debug_info['new_query_vars'] = $query;
			$debug_info['detected_id'] = (!empty($pm_query['id'])) ? $pm_query['id'] : "-";

			if(isset($post_type)) {
				$debug_info['post_type'] = $post_type;
			} else if(isset($term_taxonomy)) {
				$debug_info['taxonomy'] = $term_taxonomy;
			}

			$debug_txt = json_encode($debug_info);
			$debug_txt = "<textarea style=\"width:100%;height:300px\">{$debug_txt}</textarea>";
			wp_die($debug_txt);
		}

		if($return_object && !empty($term)) {
			return $term;
		} else if($return_object && !empty($post_to_load)) {
			return $post_to_load;
		} else {
			return $query;
		}
	}

	/**
	 * Trailing slash & remove BOM and double slashes
	 */
	function control_trailing_slashes($permalink) {
		global $permalink_manager_options;

		// Ignore empty permalinks
		if(empty($permalink)) { return $permalink; }

		$trailing_slash_setting = (!empty($permalink_manager_options['general']['trailing_slashes'])) ? $permalink_manager_options['general']['trailing_slashes'] : "";

		// Do not append the trailing slash if permalink contains hashtag or get parameters
		$url_parsed = parse_url($permalink);

		if(!empty($url_parsed['query']) || !empty($url_parsed['fragment']) || preg_match("/.*\.([a-zA-Z]{3,4})\/?$/", $permalink)) {
			$permalink = untrailingslashit($permalink);
		} else if(in_array($trailing_slash_setting, array(1, 10))) {
			$permalink = trailingslashit($permalink);
		} else if(in_array($trailing_slash_setting, array(2, 20))) {
			$permalink = untrailingslashit($permalink);
		}

		// Remove double slashes
		$permalink = preg_replace('/([^:])(\/{2,})/', '$1/', $permalink);

		// Remove trailing slashes from URLs with extensions
		$permalink = preg_replace("/(\.[a-z]{3,4})\/$/i", "$1", $permalink);

		return $permalink;
	}

	/**
   * Display 404 if requested page does not exist in pagination
   */
  function fix_pagination_pages() {
    global $wp_query;

    // 1. Get the post object
    $post = get_queried_object();

		// 2. Check if post object is defined
    if(empty($post->post_type) || (empty($post->post_content) && $post->post_type != 'attachment')) { return; }

    // 3. Check if pagination is detected
    $current_page = (!empty($wp_query->query_vars['page'])) ? $wp_query->query_vars['page'] : 1;
		$current_page = (empty($wp_query->query_vars['page']) && !empty($wp_query->query_vars['paged'])) ? $wp_query->query_vars['paged'] : $current_page;

    // 4. Count post pages
    $num_pages = substr_count(strtolower($post->post_content), '<!--nextpage-->') + 1;

		// 5. Block non-existent pages (Force 404 error)
    if($current_page > 1 && ($current_page > $num_pages)) {
			$wp_query->is_404 = true;
			$wp_query->query = $wp_query->queried_object = $wp_query->queried_object_id = null;
			$wp_query->set_404();

			status_header(404);
			nocache_headers();
			include(get_query_template('404'));

			die();
    }
  }

	/**
	 * Redirects
	 */
	function new_uri_redirect_and_404() {
 		global $wp_query, $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_external_redirects, $permalink_manager_options, $wp, $pm_query, $pm_uri_parts;

		if(isset($_GET['debug'])) {
			echo '#1';
		}

		// Do not redirect on author pages & front page
    if(is_author() || is_front_page() || is_home() || is_feed()) { return false; }

		// Unset 404 if custom URI is detected
		if(isset($pm_query['id'])) {
			$wp_query->is_404 = false;
		}

		if(isset($_GET['debug'])) {
			echo '#2';
		}

 		// Sometimes $wp_query indicates the wrong object if requested directly
 		$queried_object = get_queried_object();

		// Get the redirection mode & trailing slashes settings
		$redirect_mode = (!empty($permalink_manager_options['general']['redirect'])) ? $permalink_manager_options['general']['redirect'] : false;
		$trailing_slashes_mode = (!empty($permalink_manager_options['general']['trailing_slashes'])) ? $permalink_manager_options['general']['trailing_slashes'] : false;
		$trailing_slashes_redirect_mode = (!empty($permalink_manager_options['general']['trailing_slashes_redirect'])) ? $permalink_manager_options['general']['trailing_slashes_redirect'] : 301;

		// Get query string & URI
		$query_string = $_SERVER['QUERY_STRING'];
		$old_uri = $_SERVER['REQUEST_URI'];

		if(isset($_GET['debug'])) {
			echo '#3';
			print_r($queried_object);
			print_r($_SERVER);
		}

		// Get home URL
		$home_url = rtrim(get_option('home'), "/");
		$home_dir = parse_url($home_url, PHP_URL_PATH);

		// Fix for WP installed in directories (remove the directory name from the URI)
		if(!empty($home_dir)) {
			$home_dir_regex = preg_quote(trim($home_dir), "/");
			$old_uri = preg_replace("/{$home_dir_regex}/", "", $old_uri, 1);
		}

		if(isset($_GET['debug'])) {
			echo '#4';
			print_r($pm_query);
		}

		/**
		 * 1A. External redirect
		 */
		if(!empty($pm_query['id']) && !empty($permalink_manager_external_redirects[$pm_query['id']])) {
			$external_url = $permalink_manager_external_redirects[$pm_query['id']];

			if(filter_var($external_url, FILTER_VALIDATE_URL)) {
				// Allow redirect
				$wp_query->query_vars['do_not_redirect'] = 0;

				wp_redirect($external_url, 301);
				exit();
			}
		}

		if(isset($_GET['debug'])) {
			echo '#5';
			print_r($wp->request);
		}

		/**
		 * 1B. Custom redirects
		 */
		if(empty($wp_query->query_vars['do_not_redirect']) && !empty($permalink_manager_redirects) && is_array($permalink_manager_redirects) && !empty($wp->request) && !empty($pm_query['uri'])) {
			$uri = $pm_query['uri'];

			// Make sure that URIs with non-ASCII characters are also detected
			$decoded_url = urldecode($uri);

			// Check if the URI is not assigned to any post/term's redirects
			foreach($permalink_manager_redirects as $element => $redirects) {
				if(is_array($redirects) && (in_array($uri, $redirects) || in_array($decoded_url, $redirects))) {

					// Post is detected
					if(is_numeric($element)) {
						$correct_permalink = get_permalink($element);
					}
					// Term is detected
					else {
						$term_id = intval(preg_replace("/[^0-9]/", "", $element));
						$correct_permalink = get_term_link($term_id);
					}
				}
			}
		}

		// Ignore WP-Content links
		if(!empty($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], '/wp-content') !== false)) { return false; }

		if(isset($_GET['debug'])) {
			echo '#5A';
			var_dump(empty($wp_query->query_vars['do_not_redirect']));
			var_dump($redirect_mode);
			var_dump(!empty($queried_object));
			var_dump(empty($correct_permalink));
		}

		/**
		 * 1C. Enhance native redirect
		 */
 		if(empty($wp_query->query_vars['do_not_redirect']) && $redirect_mode && !empty($queried_object) && empty($correct_permalink)) {

 			// Affect only posts with custom URI and old URIs
 			if(!empty($queried_object->ID) && isset($permalink_manager_uris[$queried_object->ID]) && empty($wp_query->query['preview'])) {
 				// Ignore posts with specific statuses
 				if(!(empty($queried_object->post_status)) && in_array($queried_object->post_status, array('draft', 'pending', 'auto-draft', 'future'))) {
 					return '';
 				}

				// Check if post type is allowed
				if(Permalink_Manager_Helper_Functions::is_disabled($queried_object->post_type, 'post_type')) { return ''; }

 				// Get the real URL
 				$correct_permalink = get_permalink($queried_object->ID);
 			}
 			// Affect only terms with custom URI and old URIs
 			else if(!empty($queried_object->term_id) && isset($permalink_manager_uris["tax-{$queried_object->term_id}"]) && defined('PERMALINK_MANAGER_PRO')) {

				if(isset($_GET['debug'])) {
					echo '#8';
					print_r($permalink_manager_uris);
				}

				// Check if taxonomy is allowed
				if(Permalink_Manager_Helper_Functions::is_disabled($queried_object->taxonomy, "taxonomy")) { return ''; }

 				// Get the real URL
 				$correct_permalink = get_term_link($queried_object->term_id, $queried_object->taxonomy);
 			}
 		}

		/**
		 * 2. Check trailing slashes (ignore links with query parameters)
		 */
		if($trailing_slashes_mode && empty($_SERVER['QUERY_STRING']) && !empty($_SERVER['REQUEST_URI'])) {
			// Check if $old_uri ends with slash or not
			$ends_with_slash = (substr($old_uri, -1) == "/") ? true : false;
			$trailing_slashes_mode = (preg_match("/.*\.([a-zA-Z]{3,4})\/?$/", $old_uri) && $trailing_slashes_mode == 10) ? 20 : $trailing_slashes_mode;

			// Ignore empty URIs
			if($old_uri != "/") {
				// Remove the trailing slashes (and add them again if needed below)
				$old_uri = trim($old_uri, "/");
				$correct_permalink = (!empty($correct_permalink)) ? trim($correct_permalink, "/") : "";

				// 2A. Force trailing slashes
		    if($trailing_slashes_mode == 10 && $ends_with_slash == false) {
					$correct_permalink = (!empty($correct_permalink)) ? "{$correct_permalink}/" : "{$home_url}/{$old_uri}/";
		    }
				// 2B. Remove trailing slashes
				else if($trailing_slashes_mode == 20 && $ends_with_slash == true) {
					$correct_permalink = (!empty($correct_permalink)) ? $correct_permalink : "{$home_url}/{$old_uri}";
					$correct_permalink = trim($correct_permalink, "/");
				}

				// Use redirect mode set for trailing slash redirect
				if(($trailing_slashes_mode == 20 && $ends_with_slash == true) || ($trailing_slashes_mode == 10 && $ends_with_slash == false)) {
					$redirect_mode = $trailing_slashes_redirect_mode;
				}
			}
		}

		/**
		 * 3. Check if URL contains duplicated slashes
		 */
		if(!empty($old_uri) && ($old_uri != '/') && preg_match('/\/{2,}/', $old_uri)) {
			$new_uri = ltrim(preg_replace('/\/{2,}/', '/', $old_uri), "/");
			$correct_permalink = "{$home_url}/{$new_uri}";
		}

		/**
		 * 3. Ignore default URIs (or do nothing if redirects are disabled)
		 */
		if(!empty($correct_permalink) && !empty($redirect_mode)) {
			// Allow redirect
			$wp_query->query_vars['do_not_redirect'] = 0;

			// Append query string
			$correct_permalink = (!empty($query_string)) ? "{$correct_permalink}?{$query_string}" : $correct_permalink;

			// Remove double slash
			$correct_permalink = preg_replace('~(?<!https:|http:)[/\\\\]+~', "/", trim($correct_permalink));

			wp_safe_redirect($correct_permalink, $redirect_mode);
			exit();
		}
 	}

 	function adjust_canonical_redirect() {
 		global $permalink_manager_options, $permalink_manager_uris, $wp, $wp_rewrite;

		// Adjust rewrite settings for trailing slashes
		$trailing_slash_setting = (!empty($permalink_manager_options['general']['trailing_slashes'])) ? $permalink_manager_options['general']['trailing_slashes'] : "";
		if(in_array($trailing_slash_setting, array(1, 10))) {
			$wp_rewrite->use_trailing_slashes = true;
		} else if(in_array($trailing_slash_setting, array(2, 20))) {
			$wp_rewrite->use_trailing_slashes = false;
		}

		// Get endpoints
		$endpoints = Permalink_Manager_Helper_Functions::get_endpoints();
		$endpoints_array = ($endpoints) ? explode("|", $endpoints) : array();

		// Check if any endpoint is called (fix for feed and similar endpoints)
		foreach($endpoints_array as $endpoint) {
			if(!empty($wp->query_vars[$endpoint])) {
				$wp->query_vars['do_not_redirect'] = 1;
				break;
			}
		}

		// Do nothing for posts and terms without custom URIs (when canonical redirect is enabled)
		if(is_singular() || is_tax() || is_category() || is_tag()) {
			$element = get_queried_object();
			if(!empty($element->ID)) {
				$custom_uri = (!empty($permalink_manager_uris[$element->ID])) ? $permalink_manager_uris[$element->ID] : "";
			} else if(!empty($element->term_id)) {
				$custom_uri = (!empty($permalink_manager_uris["tax-{$element->term_id}"])) ? $permalink_manager_uris["tax-{$element->term_id}"] : "";
			}
		}

		//if(empty($custom_uri) && !empty($permalink_manager_options['general']['canonical_redirect'])) { return; }
		if(!empty($permalink_manager_options['general']['canonical_redirect'])) { return; }

 		if(!($permalink_manager_options['general']['canonical_redirect']) || !empty($wp->query_vars['do_not_redirect'])) {
			remove_action('template_redirect', 'wp_old_slug_redirect');
 			remove_action('template_redirect', 'redirect_canonical');
 			add_filter('wpml_is_redirected', '__return_false', 99, 2);
 			add_filter('pll_check_canonical_url', '__return_false', 99, 2);
 		}
 	}

	/**
	 * Case insensitive permalinks
	 */
	function case_insensitive_permalinks() {
		global $permalink_manager_options, $permalink_manager_uris;

		if(!empty($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = strtolower($_SERVER['REQUEST_URI']);
			$permalink_manager_uris = array_map('strtolower', $permalink_manager_uris);
		}
	}

	/**
	 * Replace empty placeholder tags & remove BOM
	 */
	public static function replace_empty_placeholder_tags($default_uri, $native_slug = "", $element = "", $slug = "", $native_uri = "") {
		// Do not affect native URIs
		if($native_uri == true) { return $default_uri; }

		// Remove the BOM
		$default_uri = str_replace(array("\xEF\xBB\xBF", "%ef%bb%bf"), '', $default_uri);

		// Encode the URI before placeholders are removed
		$chunks = explode('/', $default_uri);
		foreach ($chunks as &$chunk) {
			if(preg_match("/^(%.+?%)$/", $chunk) == false) {
				$chunk = urldecode($chunk);
			}
		}
		$default_uri = implode("/", $chunks);

		$empty_tag_replacement = apply_filters('permalink_manager_empty_tag_replacement', null, $element);
		$default_uri = ($empty_tag_replacement || is_null($empty_tag_replacement)) ? str_replace("//", "/", preg_replace("/%(.+?)%/", $empty_tag_replacement, $default_uri)) : $default_uri;

		return trim($default_uri, "/");
	}

}
