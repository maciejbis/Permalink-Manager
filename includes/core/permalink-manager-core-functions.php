<?php

/**
* Core function
*/
class Permalink_Manager_Core_Functions extends Permalink_Manager_Class {

	public function __construct() {
		add_action( 'init', array($this, 'init_hooks'), 99);
	}

	function init_hooks() {
		// Use the URIs set in this plugin + redirect from old URIs to new URIs + adjust canonical redirect settings
		add_filter( 'request', array($this, 'detect_post'), 0, 1 );

		// Trailing slashes
		add_filter( 'permalink_manager_filter_final_term_permalink', array($this, 'control_trailing_slashes'), 9);
		add_filter( 'permalink_manager_filter_final_post_permalink', array($this, 'control_trailing_slashes'), 9);
		add_filter( 'permalink_manager_filter_post_sample_permalink', array($this, 'control_trailing_slashes'), 9);

		// Redirects
		add_filter( 'redirect_canonical', array($this, 'fix_canonical_redirect'), 9, 2);
		add_action( 'template_redirect', array($this, 'redirect_to_new_uri'), 0);
		add_action( 'parse_request', array($this, 'disable_canonical_redirect'), 0, 1);

		// Case insensitive permalinks
		add_action( 'parse_request', array($this, 'case_insensitive_permalinks'), 0);
		add_action( 'parse_request', array($this, 'fix_pagination_pages'), 0);
	}

	/**
	* The most important Permalink Manager function
	*/
	function detect_post($query) {
		global $wpdb, $wp, $wp_rewrite, $permalink_manager_uris, $wp_filter, $permalink_manager_options, $pm_item_id;

		// Check if any custom URI is used
		if(!(is_array($permalink_manager_uris)) || empty($query)) return $query;

		// Used in debug mode & endpoints
		$old_query = $query;

		/**
		* 1. Prepare URL and check if it is correct
		*/
		$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://';
		$request_url = "{$protocol}{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$home_url = trim(rtrim(get_option('home'), '/'));
		$home_url = ($protocol == 'https://') ? str_replace("http://", "https://", $home_url) : str_replace("https://", "http://", $home_url); // Adjust prefix (it should be the same in both request & home_url)

		if(filter_var($request_url, FILTER_VALIDATE_URL)) {
			// Check if "Deep Detect" is enabled
			$deep_detect_enabled = apply_filters('permalink-manager-deep-uri-detect', $permalink_manager_options['general']['deep_detect']);

			// Remove .html suffix and domain name from URL and query (URLs ended with .html will work as aliases)
			$request_url = trim(str_replace($home_url, "", $request_url), "/");

			// Remove querystrings from URI
			$request_url = urldecode(strtok($request_url, '?'));

			// Get all the endpoints
			$endpoints = Permalink_Manager_Helper_Functions::get_endpoints();

			// Use default REGEX to detect post
			preg_match("/^(.+?)(?|\/({$endpoints})\/([^\/]+)|()\/([\d+]))?\/?$/i", $request_url, $regex_parts);
			$uri_parts['lang'] = false;
			$uri_parts['uri'] = (!empty($regex_parts[1])) ? $regex_parts[1] : "";
			$uri_parts['endpoint'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
			$uri_parts['endpoint_value'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";

			// Allow to filter the results by third-parties
			$uri_parts = apply_filters('permalink-manager-detect-uri', $uri_parts, $request_url, $endpoints);

			// Stop the function if $uri_parts is empty
			if(empty($uri_parts)) return $query;

			// Get the URI parts from REGEX parts
			$lang = $uri_parts['lang'];
			$uri = $uri_parts['uri'];
			$endpoint = $uri_parts['endpoint'];
			$endpoint_value = $uri_parts['endpoint_value'];

			// Trim slashes
			$uri = trim($uri, "/");

			// Decode both Request URI & URIs array
			/*$uri = urldecode($uri);
			foreach ($permalink_manager_uris as $key => $value) {
				$permalink_manager_uris[$key] = urldecode($value);
			}*/

			// Ignore URLs with no URI grabbed
			if(empty($uri)) return $query;

			/**
			* 2. Check if found URI matches any element from custom uris array
			*/
			$element_id = array_search($uri, $permalink_manager_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$element_id = (empty($element_id)) ? array_search("{$uri}.html",  $permalink_manager_uris) : $element_id;

			// Check again in case someone used post/tax IDs instead of slugs
			if($deep_detect_enabled && isset($old_query['page'])) {

				$new_item_id = array_search("{$uri}/{$endpoint_value}",  $permalink_manager_uris);
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

					// Make the redirects more clever - see redirect_to_new_uri() method
					$query['do_not_redirect'] = 1;
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
					// Fix for hierarchical CPT & pages
					if(!(empty($post_to_load->ancestors))) {
						foreach ($post_to_load->ancestors as $parent) {
							$parent = get_post( $parent );
							if($parent && $parent->post_name) {
								$final_uri = $parent->post_name . '/' . $final_uri;
							}
						}
					}

					// Alter query parameters
					if($post_to_load->post_type == 'page') {
						$query['pagename'] = $final_uri;
					} else if($post_to_load->post_type == 'post') {
						$query['name'] = $final_uri;
					} else if($post_to_load->post_type == 'attachment') {
						$query['attachment'] = $final_uri;
					} else {
						$query['name'] = $final_uri;
						$query['post_type'] = $post_type;
						$query[$post_type] = $final_uri;
					}

					// Make the redirects more clever - see redirect_to_new_uri() method
					$query['do_not_redirect'] = 1;
				} else {
					$broken_uri = true;
				}
			}

			/**
			 * 2C. Auto-remove removed term custom URI & redirects (works if enabled in plugin settings)
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
			* 2D. Endpoints
			*/
			if($element_id && (!empty($endpoint)) || !empty($endpoint_value)) {
				$endpoint = ($endpoint) ? str_replace(array('page', 'trackback'), array('paged', 'tb'), $endpoint) : "page";

				if($endpoint == 'feed') {
					$query[$endpoint] = 'feed';
				} elseif($endpoint == 'trackback') {
					$query[$endpoint] = 1;
				} else {
					$query[$endpoint] = $endpoint_value;
				}
			}

			/**
			 * 2D Endpoints - check if any endpoint is set with $_GET parameter
			 */
			if($deep_detect_enabled && !empty($_GET)) {
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
			 * Global with detected item id
			 */
			if(!empty($element_id)) {
				$pm_item_id = $element_id;
			}
		}

		// Debug mode
		if(isset($_REQUEST['debug_url'])) {
			$debug_info['old_query_vars'] = $old_query;
			$debug_info['new_query_vars'] = $query;

			$debug_txt = json_encode($debug_info);
			$debug_txt = "<textarea style=\"width:100%;height:300px\">{$debug_txt}</textarea>";
			wp_die($debug_txt);
		}

		return $query;
	}

	/**
	 * Trailing slash
	 */
	function control_trailing_slashes($permalink) {
		global $permalink_manager_options;

		$trailing_slash_setting = (!empty($permalink_manager_options['general']['trailing_slashes'])) ? $permalink_manager_options['general']['trailing_slashes'] : "";

		if($trailing_slash_setting == 1) {
			$permalink = trailingslashit($permalink);
		} else if($trailing_slash_setting > 1) {
			$permalink = untrailingslashit($permalink);
		}

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
    if(empty($post->ID)) { return; }

    // 3. Check if pagination is detected
    if(empty($wp_query->query_vars['page'])) { return; }

    // 4. Count post pages
    $num_pages = substr_count(strtolower($post->post_content), '<!--nextpage-->') + 1;
    if($wp_query->query_vars['page'] > $num_pages) {
			$wp_query->set('p', null);
			$wp_query->set('pagename', null);
			$wp_query->set('page_id', null);
			$wp_query->set_404();
    }
  }

	/**
	 * Redirects
	 */
	function redirect_to_new_uri() {
 		global $wp_query, $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_options, $wp;

		// Do not redirect on author pages & front page
    if(is_author() || is_front_page() || is_home()) { return false; }

 		// Sometimes $wp_query indicates the wrong object if requested directly
 		$queried_object = get_queried_object();

		// Get the redirection mode & trailing slashes settings
		$redirect_mode = (!empty($permalink_manager_options['general']['redirect'])) ? $permalink_manager_options['general']['redirect'] : false;
		$trailing_slashes_mode = (!empty($permalink_manager_options['general']['trailing_slashes'])) ? $permalink_manager_options['general']['trailing_slashes'] : false;

		// Get query string
		$query_string = $_SERVER['QUERY_STRING'];

		/**
		 * 1A. Custom redirects
		 */
		if(empty($wp_query->query['do_not_redirect']) && !empty($permalink_manager_redirects) && is_array($permalink_manager_redirects) && !empty($wp->request)) {
			$uri = urldecode(trim($wp->request, "/ "));

			// Filter endpoints
			$endpoints = apply_filters("permalink-manager-endpoints", "page|feed|embed|attachment|track");
			preg_match("/^(.+?)(?:\/($endpoints))?(?:\/([\d]+))?\/?$/i", $uri, $regex_parts);
			$uri = (!empty($regex_parts[1])) ? $regex_parts[1] : $uri;

			// Check if the URI is not assigned to any post/term's redirects
			foreach($permalink_manager_redirects as $element => $redirects) {
				if(is_array($redirects) && in_array($uri, $redirects)) {

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

		/**
		 * 1B. Enhance native redirect
		 */
 		if(empty($wp_query->query['do_not_redirect']) && $redirect_mode && !empty($queried_object) && empty($correct_permalink)) {
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
 			else if(!empty($queried_object->term_id) && isset($permalink_manager_uris["tax-{$queried_object->term_id}"])) {
				// Check if taxonomy is allowed
				if(Permalink_Manager_Helper_Functions::is_disabled($queried_object->taxonomy, "taxonomy")) { return ''; }

 				// Get the real URL
 				$correct_permalink = get_term_link($queried_object->term_id, $queried_object->taxonomy);
 			}
 		}

		/**
		 * 2. Check trailing slashes
		 */
		if($trailing_slashes_mode) {
			$old_request = strtok($_SERVER['REQUEST_URI'], "?");
			$ends_with_slash = (substr($old_request, -1) == "/") ? true : false;

			// Homepage should be ignored
			if($old_request != "/") {
				// 2A. Force trailing slashes
		    if($trailing_slashes_mode == 10 && $ends_with_slash == false) {
					$correct_permalink = (!empty($correct_permalink)) ? "{$correct_permalink}/" : rtrim(get_option('home'), "/") . $old_request . "/";
		    }
				// 2B. Remove trailing slashes
				else if($trailing_slashes_mode == 20 && $ends_with_slash == true) {
					$correct_permalink = (!empty($correct_permalink)) ? $correct_permalink : rtrim(get_option('home'), "/") . $old_request;
					$correct_permalink = rtrim($correct_permalink, "/");
				}
			}
		}

		/**
		 * 3. Ignore default URIs (or do nothing if redirects are disabled)
		 */
		if(!empty($correct_permalink) && !empty($redirect_mode)) {
			// Append query string
			$correct_permalink = (!empty($query_string)) ? "{$correct_permalink}?{$query_string}" : $correct_permalink;

			wp_safe_redirect($correct_permalink, $redirect_mode);
			exit();
		}
 	}

	function fix_canonical_redirect($redirect_url, $requested_url) {
		global $permalink_manager_options;

		// Trailing slash (use redirect_to_new_uri() function instead)
		if(substr($redirect_url, 0, -1) != '/' && $permalink_manager_options['general']['trailing_slashes'] > 1) {
			$redirect_url = false;
		}
		return $redirect_url;
	}

 	function disable_canonical_redirect() {
 		global $permalink_manager_options, $wp_filter, $wp;

 		if(!($permalink_manager_options['general']['canonical_redirect']) || !empty($wp->query_vars['do_not_redirect'])) {
 			remove_action('template_redirect', 'redirect_canonical');
 			add_filter('wpml_is_redirected', '__return_false', 99, 2);
 		}
 	}

	/**
	 * Case insensitive permalinks
	 */
	function case_insensitive_permalinks() {
		global $permalink_manager_options, $permalink_manager_uris;

		if(!empty($permalink_manager_options['general']['case_insensitive_permalinks']) && !empty($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = strtolower($_SERVER['REQUEST_URI']);
			$permalink_manager_uris = array_map('strtolower', $permalink_manager_uris);
		}
	}

}
