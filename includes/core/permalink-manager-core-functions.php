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
		add_action( 'template_redirect', array($this, 'redirect_to_new_uri'), 999);
		add_action( 'parse_request', array($this, 'disable_canonical_redirect'), 0, 1);

		// Case insensitive permalinks
		add_action( 'parse_request', array($this, 'case_insensitive_permalinks'), 0);
	}

	/**
	* The most important Permalink Manager function
	*/
	function detect_post($query) {
		global $wpdb, $permalink_manager_uris, $wp_filter, $permalink_manager_options, $pm_item_id;

		// Check if any custom URI is used
		if(!(is_array($permalink_manager_uris)) || empty($query)) return $query;

		// Used in debug mode
		$old_query = $query;

		/**
		* 1. Prepare URL and check if it is correct
		*/
		$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://';
		$request_url = "{$protocol}{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$home_url = trim(rtrim(get_option('home'), '/'));
		$home_url = ($protocol == 'https://') ? str_replace("http://", "https://", $home_url) : str_replace("https://", "http://", $home_url); // Adjust prefix (it should be the same in both request & home_url)

		if (filter_var($request_url, FILTER_VALIDATE_URL)) {
			/**
			* 1. Process URL & find the URI
			*/
			// Remove .html suffix and domain name from URL and query (URLs ended with .html will work as aliases)
			$request_url = trim(str_replace($home_url, "", $request_url), "/");

			// Remove querystrings from URI
			$request_url = urldecode(strtok($request_url, '?'));

			// Filter endpoints
			$endpoints = apply_filters("permalink-manager-endpoints", "page|feed|embed|attachment|track");

			// Use default REGEX to detect post
			preg_match("/^(.+?)(?:\/($endpoints))?(?:\/([\d]+))?\/?$/i", $request_url, $regex_parts);
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
			$uri = urldecode($uri);
			foreach ($permalink_manager_uris as $key => $value) {
				$permalink_manager_uris[$key] = urldecode($value);
			}

			// Ignore URLs with no URI grabbed
			if(empty($uri)) return $query;

			/**
			* 2. Check if found URI matches any element from custom uris array
			*/
			$item_id = array_search($uri, $permalink_manager_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$item_id = (empty($item_id)) ? array_search("{$uri}.html",  $permalink_manager_uris) : $item_id;

			// Check again in case someone used post/tax IDs instead of slugs
			$deep_detect_enabled = apply_filters('permalink-manager-deep-uri-detect', false);
			if($deep_detect_enabled && isset($old_query['page'])) {
				$new_item_id = array_search("{$uri}/{$endpoint_value}",  $permalink_manager_uris);
				if($new_item_id) {
					$item_id = $new_item_id;
					$endpoint_value = $endpoint = "";
				}
			}

			// Allow to filter the item_id by third-parties after initial detection
			$item_id = apply_filters('permalink-manager-detected-initial-id', $item_id, $uri_parts, $request_url);

			// Clear the original query before it is filtered
			$query = ($item_id) ? array() : $query;

			/**
			* 3A. Custom URI assigned to taxonomy
			*/
			if(strpos($item_id, 'tax-') !== false) {
				// Remove the "tax-" prefix
				$item_id = preg_replace("/[^0-9]/", "", $item_id);

				// Filter detected post ID
				$item_id = apply_filters('permalink-manager-detected-term-id', intval($item_id), $uri_parts, true);

				// Get the variables to filter wp_query and double-check if tax exists
				$term = get_term(intval($item_id));
				if(empty($term->taxonomy)) { return $query; }

				// Get some term data
				if($term->taxonomy == 'category') {
					$query_parameter = 'category_name';
				} else if($term->taxonomy == 'post_tag') {
					$query_parameter = 'tag';
				} else {
					$query_parameter = $term->taxonomy;
				}
				$term_ancestors = get_ancestors($item_id, $term->taxonomy);
				$final_uri = $term->slug;

				// Fix for hierarchical CPT & pages
				if(empty($term_ancestors)) {
					foreach ($term_ancestors as $parent) {
						$parent = get_term($parent, $term->taxonomy);
						if(!empty($parent->slug)) {
							$final_uri = $parent->slug . '/' . $final_uri;
						}
					}
				}

				// Make the redirects more clever - see redirect_to_new_uri() method
				$query['do_not_redirect'] = 1;
				$query[$query_parameter] = $final_uri;
			}
			/**
			* 3B. Custom URI assigned to post/page/cpt item
			*/
			else if(isset($item_id) && is_numeric($item_id)) {
				// Fix for revisions
				$is_revision = wp_is_post_revision($item_id);
				$item_id = ($is_revision) ? $is_revision : $item_id;

				// Filter detected post ID
				$item_id = apply_filters('permalink-manager-detected-post-id', $item_id, $uri_parts);

				$post_to_load = get_post($item_id);
				$final_uri = $post_to_load->post_name;
				$post_type = $post_to_load->post_type;

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
			}

			/**
			* 2C. Endpoints
			*/
			if($item_id && (!empty($endpoint)) || !empty($endpoint_value)) {
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
			 * Global with detected item id
			 */
			if(!empty($item_id)) {
				$pm_item_id = $item_id;
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
	 * Redirects
	 */
	function redirect_to_new_uri() {
 		global $wp_query, $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_options, $wp;

		// Do not redirect on author pages
    if(is_author()) { return false; }

 		// Sometimes $wp_query indicates the wrong object if requested directly
 		$queried_object = get_queried_object();

		// Get the redirection mode
		$redirect_mode = (!empty($permalink_manager_options['general']['redirect'])) ? $permalink_manager_options['general']['redirect'] : false;

		// A. Custom redirects
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
						$redirect_to = get_permalink($element);
					}
					// Term is detected
					else {
						$term_id = intval(preg_replace("/[^0-9]/", "", $element));
						$redirect_to = get_term_link($term_id);
					}

					if(!empty($redirect_to)) {
						wp_safe_redirect($redirect_to, $redirect_mode);
						exit();
					}
				}
			}
		}

		// B. Native redirect
 		if($redirect_mode && !empty($queried_object)) {
 			// Affect only posts with custom URI and old URIs
 			if(!empty($queried_object->ID) && isset($permalink_manager_uris[$queried_object->ID]) && empty($wp_query->query['do_not_redirect']) && empty($wp_query->query['preview'])) {
 				// Ignore posts with specific statuses
 				if(!(empty($queried_object->post_status)) && in_array($queried_object->post_status, array('draft', 'pending', 'auto-draft', 'future'))) {
 					return '';
 				}

 				// Get the real URL
 				$correct_permalink = get_permalink($queried_object->ID);
 			}
 			// Affect only terms with custom URI and old URIs
 			else if(!empty($queried_object->term_id) && isset($permalink_manager_uris["tax-{$queried_object->term_id}"]) && empty($wp_query->query['do_not_redirect'])) {
 				// Get the real URL
 				$correct_permalink = get_term_link($queried_object->term_id, $queried_object->taxonomy);
 			}

 			// Ignore default URIs (or do nothing if redirects are disabled)
 			if(!empty($correct_permalink) && !empty($redirect_mode)) {
 				wp_safe_redirect($correct_permalink, $redirect_mode);
 				exit();
 			}
 		}
 	}

	function fix_canonical_redirect($redirect_url, $requested_url) {
		global $permalink_manager_options;

		// Trailing slash
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

	/**
	 * Detect duplicates
	 */
	public static function detect_duplicates() {
		global $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_options, $wpdb;

		// Make sure that both variables are arrays
		$all_uris = (is_array($permalink_manager_uris)) ? $permalink_manager_uris : array();
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
				$duplicates_array = array_keys($all_uris, $duplicated_uri);

				// Remove the URIs for removed posts & terms
				if(!empty($permalink_manager_options['general']['auto_remove_duplicates'])) {
					foreach($duplicates_array as $index => $raw_item_id) {
						$item_id = preg_replace("/(?:redirect-[\d]+_)?(.*)/", "$1", $raw_item_id);

						if(strpos($item_id, 'tax-') !== false) {
							$term_id = intval(preg_replace("/[^0-9]/", "", $item_id));
							$element_exists = $wpdb->get_var( "SELECT * FROM {$wpdb->prefix}terms WHERE term_id = {$term_id}" );
						} else {
							$element_exists = $wpdb->get_var( "SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$item_id} AND post_status NOT IN ('auto-draft', 'trash') AND post_type != 'nav_menu_item'" );
						}

						if(empty($element_exists)) {
							// Detect the type of URI
							preg_match("/(redirect-([\d]+)_)?((?:tax-)?[\d]*)/", $raw_item_id, $parts);

							$detected_redirect = $parts[1];
							$detected_id = $parts[3];
							$detected_index = $parts[2];

							// A. Redirect
							if($detected_redirect && $detected_index && $detected_id) {
								unset($permalink_manager_redirects[$detected_id][$detected_index]);
							}
							// B. Custom URI
							else if($detected_id) {
								unset($permalink_manager_uris[$detected_id]);
							}

							if($detected_id) {
								unset($duplicates_array[$index]);
								$duplicates_removed++;
							}
						}
					}
				}

				$duplicates_groups[$duplicated_uri] = $duplicates_array;
			}

			// Save cleared URIs & Redirects
			if($duplicates_removed > 0 && !empty($permalink_manager_options['general']['auto_remove_duplicates'])) {
				update_option('permalink-manager-uris', $permalink_manager_uris);
				update_option('permalink-manager-redirects', $permalink_manager_redirects);
			}
		}

		return $duplicates_groups;
	}

}
?>
