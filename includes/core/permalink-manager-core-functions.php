<?php

/**
 * Core functions
 */
class Permalink_Manager_Core_Functions {

	public function __construct() {
		add_action( 'init', array( $this, 'init_hooks' ), 99 );
	}

	/**
	 * Add hooks used by plugin to change the way the permalinks are detected
	 *
	 * @return false|void
	 */
	function init_hooks() {
		global $permalink_manager_options;

		// Trailing slashes
		add_filter( 'permalink_manager_filter_final_term_permalink', array( $this, 'control_trailing_slashes' ), 9 );
		add_filter( 'permalink_manager_filter_final_post_permalink', array( $this, 'control_trailing_slashes' ), 9 );
		add_filter( 'permalink_manager_filter_post_sample_uri', array( $this, 'control_trailing_slashes' ), 9 );
		add_filter( 'wpseo_canonical', array( $this, 'control_trailing_slashes' ), 9 );
		add_filter( 'wpseo_opengraph_url', array( $this, 'control_trailing_slashes' ), 9 );
		add_filter( 'paginate_links', array( $this, 'control_trailing_slashes' ), 9 );

		/**
		 * Detect & canonical URL/redirect functions
		 */
		// Do not trigger in back-end
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		// Do not trigger if Customizer is loaded
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		// Use the URIs set in this plugin
		add_filter( 'request', array( $this, 'detect_post' ), 0, 1 );

		// Redirect from old URIs to new URIs  + adjust canonical redirect settings
		add_action( 'template_redirect', array( $this, 'new_uri_redirect_and_404' ), 1 );
		add_action( 'wp', array( $this, 'adjust_canonical_redirect' ), 1 );

		// Case-insensitive permalinks
		if ( ! empty( $permalink_manager_options['general']['case_insensitive_permalinks'] ) ) {
			add_action( 'parse_request', array( $this, 'case_insensitive_permalinks' ), 0 );
		}
		// Force 404 on non-existing pagination pages
		if ( ! empty( $permalink_manager_options['general']['pagination_redirect'] ) ) {
			add_action( 'wp', array( $this, 'fix_pagination_pages' ), 0 );
		}
	}

	/**
	 * Change the request array used by WordPress to load specific content item (post, term, archive, etc.)
	 *
	 * @param array $query
	 * @param bool $request_url
	 * @param bool $return_object
	 *
	 * @return array|WP_Post|WP_Term
	 */
	public static function detect_post( $query, $request_url = false, $return_object = false ) {
		global $wp, $wp_rewrite, $permalink_manager_uris, $permalink_manager_options, $pm_query;

		// Check if the array with custom URIs is set
		if ( ! ( is_array( $permalink_manager_uris ) ) ) {
			return $query;
		}

		// Used in debug mode & endpoints
		$old_query = $query;

		/**
		 * 1. Prepare URL and check if it is correct (make sure that both requested URL & home_url share the same protocol and get rid of www prefix)
		 */
		$request_url = ( ! empty( $request_url ) ) ? parse_url( $request_url, PHP_URL_PATH ) : $_SERVER['REQUEST_URI'];
		$request_url = ( ! empty( $request_url ) ) ? strtok( $request_url, "?" ) : $request_url;

		// Make sure that either $_SERVER['SERVER_NAME'] or $_SERVER['HTTP_HOST'] are set
		if ( empty( $_SERVER['HTTP_HOST'] ) && empty( $_SERVER['SERVER_NAME'] ) ) {
			return $query;
		}

		$http_host    = ( ! empty( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : preg_replace( '/www\./i', '', $_SERVER['SERVER_NAME'] );
		$request_url  = sprintf( "http://%s%s", str_replace( "www.", "", $http_host ), $request_url );
		$raw_home_url = trim( get_option( 'home' ) );
		$home_url     = preg_replace( "/http(s)?:\/\/(www\.)?(.+?)\/?$/", "http://$3", $raw_home_url );

		if ( parse_url( $request_url, PHP_URL_HOST ) ) {
			// Check if "Deep Detect" is enabled
			$deep_detect_enabled = apply_filters( 'permalink_manager_deep_uri_detect', true );

			// Sanitize the URL
			// $request_url = filter_var($request_url, FILTER_SANITIZE_URL);

			// Keep only the URI
			$request_url = str_replace( $home_url, "", $request_url );

			// Hotfix for language plugins
			if ( filter_var( $request_url, FILTER_VALIDATE_URL ) ) {
				$request_url = parse_url( $request_url, PHP_URL_PATH );
			}

			$request_url = trim( $request_url, "/" );

			// Get all the endpoints & pattern
			$endpoints = Permalink_Manager_Helper_Functions::get_endpoints();
			$pattern   = "/^(.+?)(?|\/({$endpoints})(?|\/(.*)|$)|\/()([\d]+)\/?)?$/i";

			// Use default REGEX to detect post
			preg_match( $pattern, $request_url, $regex_parts );
			$uri_parts['lang']           = false;
			$uri_parts['uri']            = ( ! empty( $regex_parts[1] ) ) ? $regex_parts[1] : "";
			$uri_parts['endpoint']       = ( ! empty( $regex_parts[2] ) ) ? $regex_parts[2] : "";
			$uri_parts['endpoint_value'] = ( ! empty( $regex_parts[3] ) ) ? $regex_parts[3] : "";

			// Allow to filter the results by third-parties + store the URI parts with $pm_query global
			$uri_parts = apply_filters( 'permalink_manager_detect_uri', $uri_parts, $request_url, $endpoints );

			// Support comment pages
			preg_match( "/(.*)\/{$wp_rewrite->comments_pagination_base}-([\d]+)/", $uri_parts['uri'], $regex_parts );
			if ( ! empty( $regex_parts[2] ) ) {
				$uri_parts['uri']            = $regex_parts[1];
				$uri_parts['endpoint']       = 'cpage';
				$uri_parts['endpoint_value'] = $regex_parts[2];
			}

			// Support pagination endpoint
			if ( $uri_parts['endpoint'] == $wp_rewrite->pagination_base ) {
				$uri_parts['endpoint'] = 'page';
			}

			// Stop the function if $uri_parts is empty
			if ( empty( $uri_parts ) ) {
				return $query;
			}

			// Store the URI parts in a separate global variable
			$pm_query = $uri_parts;

			// Get the URI parts from REGEX parts
			// $lang           = $uri_parts['lang'];
			$uri            = $uri_parts['uri'];
			$endpoint       = $uri_parts['endpoint'];
			$endpoint_value = $uri_parts['endpoint_value'];

			// Trim slashes
			$uri = trim( $uri, "/" );

			// Ignore URLs with no URI grabbed
			if ( empty( $uri ) ) {
				return $query;
			}

			// Check what content type should be loaded in case of duplicate ("posts" or "terms")
			$duplicates_priority = apply_filters( 'permalink_manager_duplicates_priority', false );

			/**
			 * 2. Check if the requested URI matches any custom permalink assigned to a post or term
			 */
			$uri_query_iteration = 1;
			$element_object      = '';
			$excluded_ids        = array();

			do {
				// Store an array with custom permalinks in a separate variable
				$all_uris = $permalink_manager_uris;

				// Remove empty rows
				$all_uris = array_filter( $all_uris );

				// In case of multiple elements using the same URI, the function will follow the "permalink_manager_duplicates_priority" filter value to determine whether terms or posts should be ignored
				if ( $duplicates_priority ) {
					$duplicated_uris    = array_keys( $all_uris, $uri );
					$duplicates_removed = 0;
					$duplicates_count   = count( $duplicated_uris );

					if ( $duplicates_count > 1 ) {
						foreach ( $duplicated_uris as $duplicated_uri_id ) {
							if ( ( $duplicates_priority == 'posts' && ! is_numeric( $duplicated_uri_id ) ) || ( $duplicates_priority !== 'posts' && is_numeric( $duplicated_uri_id ) ) ) {
								$duplicates_removed++;

								if ( $duplicates_removed < $duplicates_count ) {
									$excluded_ids[] = $duplicated_uri_id;
								}
							}
						}
					}
				}

				// If the element was excluded in the previous iteration add it to the array
				if ( ! empty( $excluded ) ) {
					$excluded_ids[] = $excluded;
				}
				$excluded = '';

				// Exclude all the element detected in the previous iterations
				if ( ! empty( $excluded_ids ) ) {
					$excluded_ids = array_unique( $excluded_ids );
					foreach ( $excluded_ids as $excluded_element ) {
						unset( $all_uris[ $excluded_element ] );
					}
				}

				// Flip array for better performance
				$all_uris = array_flip( $all_uris );

				// Attempt 1.
				// Find the element ID
				$element_id = isset( $all_uris[ $uri ] ) ? $all_uris[ $uri ] : false;

				// Attempt 2.
				// Decode both request URI & URIs array & make them lowercase (and save in a separate variable)
				if ( empty( $element_id ) ) {
					$uri = strtolower( urldecode( $uri ) );

					foreach ( $all_uris as $raw_uri => $uri_id ) {
						$raw_uri              = strtolower( urldecode( $raw_uri ) );
						$all_uris[ $raw_uri ] = $uri_id;
					}

					$element_id = isset( $all_uris[ $uri ] ) ? $all_uris[ $uri ] : $element_id;
				}

				// Attempt 3.
				// Check again in case someone used post/tax IDs instead of slugs
				if ( $deep_detect_enabled && is_numeric( $endpoint_value ) && isset( $all_uris["{$uri}/{$endpoint_value}"] ) ) {
					$element_id     = $all_uris["{$uri}/{$endpoint_value}"];
					$endpoint_value = $endpoint = "";
				}

				// Attempt 4.
				// Check again for attachment custom URIs
				if ( empty( $element_id ) && isset( $old_query['attachment'] ) ) {
					$element_id = isset( $all_uris["{$uri}/{$endpoint}/{$endpoint_value}"] ) ? $all_uris["{$uri}/{$endpoint}/{$endpoint_value}"] : $element_id;

					if ( $element_id ) {
						$endpoint_value = $endpoint = "";
					}
				}

				// Allow to filter the item_id by third-parties after initial detection
				$element_id = apply_filters( 'permalink_manager_detected_element_id', $element_id, $uri_parts, $request_url );

				// Clear the original query before it is filtered
				$query = ( $element_id ) ? array() : $query;

				/**
				 * 2A. Custom URI assigned to taxonomy
				 */
				if ( strpos( $element_id, 'tax-' ) !== false ) {
					// Remove the "tax-" prefix
					$term_element_id = intval( preg_replace( "/[^0-9]/", "", $element_id ) );

					// Filter detected post ID
					$term_element_id = apply_filters( 'permalink_manager_detected_term_id', $term_element_id, $uri_parts, true );

					// Get the variables to filter wp_query and double-check if taxonomy exists
					$term                 = $element_object = ( ! empty( $term_element_id ) && is_numeric( $term_element_id ) ) ? get_term( $term_element_id ) : false;
					$term_taxonomy        = ( ! empty( $term->taxonomy ) ) ? $term->taxonomy : false;
					$term_taxonomy_object = ( ! empty( $term_taxonomy ) ) ? get_taxonomy( $term_taxonomy ) : '';

					// Check if term is allowed
					$disabled = ( $term_taxonomy_object && Permalink_Manager_Helper_Functions::is_term_excluded( $term ) ) ? true : false;

					// Proceed only if the term is not removed and its taxonomy is not disabled
					if ( ! $disabled && $term_taxonomy_object ) {
						$term_ancestors = get_ancestors( $element_id, $term_taxonomy );
						$final_uri      = $term->slug;

						// Fix for hierarchical terms
						if ( ! empty( $term_ancestors ) ) {
							foreach ( $term_ancestors as $parent_id ) {
								$parent = get_term( $parent_id, $term_taxonomy );
								if ( ! empty( $parent->slug ) ) {
									$final_uri = $parent->slug . '/' . $final_uri;
								}
							}
						}

						if ( empty( $term_taxonomy_object->query_var ) ) {
							$query["taxonomy"] = $term_taxonomy;
							$query["term"]     = $term->slug;
						} else {
							$query[ $term_taxonomy_object->query_var ] = $term->slug;
						}
					} else if ( $disabled ) {
						$broken_uri = true;
						$query      = $old_query;
						$excluded   = $element_id;
					} else {
						$query    = $old_query;
						$excluded = $element_id;
					}
				}
				/**
				 * 2B. Custom URI assigned to post/page/CPT item
				 */
				else if ( isset( $element_id ) && is_numeric( $element_id ) ) {
					// Fix for revisions
					$is_revision = wp_is_post_revision( $element_id );
					if ( $is_revision ) {
						$revision_id = $element_id;
						$element_id  = $is_revision;
					}

					// Filter detected post ID
					$post_element_id = apply_filters( 'permalink_manager_detected_post_id', $element_id, $uri_parts );

					$post_to_load = $element_object = ( ! empty( $post_element_id ) && is_numeric( $post_element_id ) ) ? get_post( $post_element_id ) : false;
					$final_uri    = ( ! empty( $post_to_load->post_name ) ) ? $post_to_load->post_name : false;
					$post_type    = ( ! empty( $post_to_load->post_type ) ) ? $post_to_load->post_type : false;

					// Check if post is allowed
					$disabled = ( $post_type && Permalink_Manager_Helper_Functions::is_post_excluded( $post_to_load, true ) ) ? true : false;

					// Proceed only if the term is not removed and its taxonomy is not disabled
					if ( ! $disabled && $post_type ) {
						$post_type_object = get_post_type_object( $post_type );

						// Fix for hierarchical CPT & pages
						if ( ! ( empty( $post_to_load->ancestors ) ) && ! empty( $post_type_object->hierarchical ) ) {
							foreach ( $post_to_load->ancestors as $parent ) {
								$parent = get_post( $parent );
								if ( $parent && $parent->post_name ) {
									$final_uri = $parent->post_name . '/' . $final_uri;
								}
							}
						}

						// Alter the final query array
						if ( $post_to_load->post_status == 'private' && ( ! is_user_logged_in() || current_user_can( 'read_private_posts', $element_id ) !== true ) ) {
							$element_id = 0;
							$query      = $old_query;
						} else if ( $post_to_load->post_status == 'draft' || empty( $final_uri ) ) {
							// A. The draft permalinks should be allowed for logged-in users
							if ( is_user_logged_in() ) {
								if ( $post_type == 'page' ) {
									$query['page_id'] = $element_id;
								} else {
									$query['p'] = $element_id;
								}

								$query['preview']   = true;
								$query['post_type'] = $post_type;
							} // B. The draft permalinks should be disabled for non-logged-in visitors
							else if ( $post_to_load->post_status == 'draft' ) {
								$query['pagename'] = '-';
								$query['error']    = '404';

								$element_id = 0;
							} else {
								$query    = $old_query;
								$excluded = $element_id;
							}
						} else if ( $post_type == 'page' ) {
							$query['pagename'] = $final_uri;
							// $query['post_type'] = $post_type;
						} else if ( $post_type == 'post' ) {
							$query['name'] = $final_uri;
						} else if ( $post_type == 'attachment' ) {
							$query['attachment'] = $final_uri;
						} else {
							// Get the query var
							$query_var = ( ! empty( $post_type_object->query_var ) ) ? $post_type_object->query_var : $post_type;

							$query['name']       = $final_uri;
							$query['post_type']  = $post_type;
							$query[ $query_var ] = $final_uri;
						}
					} else if ( $disabled ) {
						$broken_uri = true;
						$query      = $old_query;
						$excluded   = $element_id;
					} else {
						$query    = $old_query;
						$excluded = $element_id;
					}
				}

				// Auto-remove removed term custom URI & redirects (works if enabled in plugin settings)
				if ( ! empty( $broken_uri ) && ( ! empty( $permalink_manager_options['general']['auto_fix_duplicates'] ) ) && $permalink_manager_options['general']['auto_fix_duplicates'] == 1 ) {
					// Do not trigger if WP Rocket cache plugin is turned on
					if ( ! defined( 'WP_ROCKET_VERSION' ) && is_array( $permalink_manager_uris ) ) {
						$broken_element_id = ( ! empty( $revision_id ) ) ? $revision_id : $element_id;
						$remove_broken_uri = ( ! empty( $broken_element_id ) ) ? Permalink_Manager_Actions::force_clear_single_element_uris_and_redirects( $broken_element_id ) : '';

						// Reload page if success
						if ( $remove_broken_uri && ! headers_sent() ) {
							header( "Refresh:0" );
							exit();
						}
					}
				}

				// Overwrite the detect function and decide whether to exclude the detected item
				$excluded = apply_filters( 'permalink_manager_excluded_element_id', $excluded, $element_object, $old_query, $pm_query );

				// Make sure the loop does not execute infinitely (limit it to 10 iterations)
				$uri_query_iteration ++;
				if ( $uri_query_iteration === 10 ) {
					break;
				}
			} // If the detected element was excluded repeat the URI query and try to find a new one
			while ( ! empty( $excluded ) );

			/**
			 * 3A. Endpoints
			 */
			if ( ! empty( $element_id ) && empty( $disabled ) && ( ! empty( $endpoint ) || ! empty( $endpoint_value ) ) ) {
				if ( is_array( $endpoint ) ) {
					foreach ( $endpoint as $endpoint_name => $endpoint_value ) {
						$query[ $endpoint_name ] = $endpoint_value;
					}
				} else if ( $endpoint == 'feed' ) {
					$feed_rewrite = true;

					// Check if /feed/ endpoint is allowed for selected post type or taxonomy
					if ( ! empty( $post_type_object ) && is_array( $post_type_object->rewrite ) && empty( $post_type_object->rewrite['feeds'] ) ) {
						$feed_rewrite = false;
					}

					if ( $feed_rewrite ) {
						$query[ $endpoint ] = 'feed';
					} else {
						$element_id = '';
						$query      = array(
							'error' => 404
						);
					}
				} else if ( $endpoint == 'embed' ) {
					$query[ $endpoint ] = true;
				} else if ( $endpoint == 'page' ) {
					$endpoint = 'paged';
					if ( is_numeric( $endpoint_value ) ) {
						$query[ $endpoint ] = $endpoint_value;
					} else {
						$query = $old_query;
					}
				} else if ( $endpoint == 'trackback' ) {
					$endpoint           = 'tb';
					$query[ $endpoint ] = 1;
				} else if ( empty( $endpoint ) && is_numeric( $endpoint_value ) ) {
					$query['page'] = $endpoint_value;
				} else {
					$query[ $endpoint ] = $endpoint_value;
				}

				// Fix for attachments
				if ( ! empty( $query['attachment'] ) ) {
					$query = array( 'attachment' => $query['attachment'], 'do_not_redirect' => 1 );
				} else if ( isset( $query['attachment'] ) ) {
					$query = $old_query;
					$element_id = false;
				}
			}

			/**
			 * 3B. Endpoints - check if any endpoint is set with $_GET parameter
			 */
			if ( ! empty( $element_id ) && $deep_detect_enabled && ! empty( $_GET ) ) {
				$get_endpoints = array_intersect( $wp->public_query_vars, array_keys( $_GET ) );

				if ( ! empty( $get_endpoints ) ) {
					// Append query vars from $_GET parameters
					foreach ( $get_endpoints as $endpoint ) {
						// Numeric endpoints
						$endpoint_value = ( in_array( $endpoint, array( 'page', 'paged', 'attachment_id' ) ) ) ? filter_var( $_GET[ $endpoint ], FILTER_SANITIZE_NUMBER_INT ) : $_GET[ $endpoint ];

						// Ignore page endpoint if its value is empty or equal to 1
						if ( in_array( $endpoint, array( 'page', 'paged' ) ) && ( empty( $endpoint_value ) || $endpoint_value == 1 ) ) {
							continue;
						}

						// Replace whitespaces with '+' (for YITH WooCommerce Ajax Product Filter URLs only) and sanitize the value
						$endpoint_value     = ( isset( $_GET['yith_wcan'] ) ) ? preg_replace( '/\s+/', '+', $endpoint_value ) : $endpoint_value;
						$query[ $endpoint ] = sanitize_text_field( $endpoint_value );
					}
				}
			}

			/**
			 * 4. Set global with detected item id
			 */
			if ( ! empty( $element_id ) && empty( $disabled ) && empty( $excluded ) ) {
				if ( ! empty( $element_object->taxonomy ) ) {
					$pm_query['id'] = $element_object->term_id;
					$content_type = "Taxonomy: {$element_object->taxonomy}";
				} else if ( ! empty( $element_object->post_type ) ) {
					$pm_query['id'] = $element_object->ID;
					$content_type = "Post type: {$element_object->post_type}";
				}

				// If language mismatch is detected do not set 'do_not_redirect' to allow canonical redirect
				if ( empty( $pm_query['flag'] ) || $pm_query['flag'] !== 'language_mismatch' ) {
					$query['do_not_redirect'] = 1;
				}
			}
		}

		/**
		 * 5. Debug data
		 */
		if ( empty ( $element_object ) || empty ( $content_type ) ) {
			$content_type = $element_object = '';
		}

		$uri_parts = ( ! empty( $uri_parts ) ) ? $uri_parts : '';
		$query     = apply_filters( 'permalink_manager_filter_query', $query, $old_query, $uri_parts, $pm_query, $content_type, $element_object );

		if ( $return_object && ! empty( $element_object ) ) {
			return $element_object;
		} else {
			return $query;
		}
	}

	/**
	 * Trailing slash & remove BOM and double slashes
	 *
	 * @param string $permalink
	 *
	 * @return string
	 */
	static function control_trailing_slashes( $permalink ) {
		global $permalink_manager_options;

		// Ignore empty & numeric permalinks
		if ( empty( $permalink ) || is_numeric( $permalink ) ) {
			return $permalink;
		}

		// Keep the original permalink in a separate variable
		$original_permalink = $permalink;

		$trailing_slash_mode = ( ! empty( $permalink_manager_options['general']['trailing_slashes'] ) ) ? $permalink_manager_options['general']['trailing_slashes'] : "";

		// Ignore homepage URLs
		if ( ( filter_var( $permalink, FILTER_VALIDATE_URL ) && trim( parse_url( $permalink, PHP_URL_PATH ), '/' ) == '' ) ) {
			return $permalink;
		}

		// Always remove trailing slashes from URLs/URIs that end with file extension (e.g. html)
		if ( preg_match( '/(http(?:s)\:\/\/[^\/]+\/)?.*\.([a-zA-Z]{3,4})[\/]*(\?[^\/]+|$)/', $permalink ) ) {
			$trailing_slash_mode = 2;
		}

		// Add trailing slashes
		if ( in_array( $trailing_slash_mode, array( 1, 10 ) ) ) {
			$permalink = preg_replace( '/(.+?)([\/]*)([\?\#][^\/]+|$)/', '$1/$3', $permalink ); // Instead of trailingslashit()
		} // Remove trailing slashes
		else if ( in_array( $trailing_slash_mode, array( 2, 20 ) ) ) {
			$permalink = preg_replace( '/(.+?)([\/]*)([\?\#][^\/]+|$)/', '$1$3', $permalink ); // Instead of untrailingslashit()
		} // Default settings
		else {
			$permalink = user_trailingslashit( $permalink );
		}

		// Remove double slashes
		$permalink = preg_replace( '/(?<!:)(\/{2,})/', '/', $permalink );

		// Remove trailing slashes from URLs that end with query string or anchors
		$permalink = preg_replace( '/([\?\#]{1}[^\/]+)([\/]+)$/', '$1', $permalink );

		return apply_filters( 'permalink_manager_control_trailing_slashes', $permalink, $original_permalink );
	}

	/**
	 * Display 404 if requested page does not exist in pagination or the pagination format is incorrect
	 */
	function fix_pagination_pages() {
		global $wp_query, $pm_query, $post, $permalink_manager_options;

		// 1. Check if the custom permalink was detected
		if ( empty( $pm_query['id'] ) ) {
			return;
		}

		// 2. Get the queried object
		$object = get_queried_object();

		if ( ! empty( $object ) && ! empty( $object->taxonomy ) ) {
			$term = $object;
		} else if ( ! empty( $object->post_type ) ) {
			$post = $object;
		} else if ( empty( $object ) && ! empty( $wp_query->post ) ) {
			$post = $wp_query->post;
		} else {
			return;
		}

		// 3.1. Validate the pages count
		if ( ( ! empty( $post->post_type ) && isset( $post->post_content ) ) || ( isset( $wp_query->max_num_pages ) && ! empty( $term->taxonomy ) ) ) {
			$current_page = ( ! empty( $wp_query->query_vars['page'] ) ) ? $wp_query->query_vars['page'] : 1;
			$current_page = ( empty( $wp_query->query_vars['page'] ) && ! empty( $wp_query->query_vars['paged'] ) ) ? $wp_query->query_vars['paged'] : $current_page;

			// 2.1B. Count post pages
			$post_content = ( ! empty( $post->post_content ) ) ? $post->post_content : '';
			$num_pages    = ( is_home() || is_archive() || is_search() ) ? $wp_query->max_num_pages : substr_count( strtolower( $post_content ), '<!--nextpage-->' ) + 1;

			$is_404 = ( $current_page > 1 && ( $current_page > $num_pages ) ) ? true : false;
		} // 3.2. Force 404 if no posts are loaded
		else if ( ! empty( $wp_query->query['paged'] ) && $wp_query->post_count == 0 ) {
			$is_404 = true;
		}

		// 3.4. Force 404 if endpoint value is not set or not numeric
		if ( ! empty( $pm_query['endpoint'] ) && $pm_query['endpoint'] == 'page' && ( empty( $pm_query['endpoint_value'] ) || ! is_numeric( $pm_query['endpoint_value'] ) ) ) {
			$is_404 = true;
		}

		// 4. Block non-existent pages (Force 404 error or allow canonical redirect)
		if ( ! empty( $is_404 ) ) {
			$pagination_mode = ( ! empty( $permalink_manager_options['general']['pagination_redirect'] ) ) ? $permalink_manager_options['general']['pagination_redirect'] : false;

			// Make sure that canonical redirect is not disabled in adjust_canonical_redirect() method
			if ( $pagination_mode == 2 ) {
				$wp_query->query_vars['do_not_redirect'] = 0;
			} else {
				$wp_query->query = $wp_query->queried_object = $wp_query->queried_object_id = $pm_query = $post = null;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
			}

			$pm_query = '';
		}
	}

	/**
	 * Enhance the existing canonical redirect functionality, allowing users define custom redirects and support custom permalinks
	 */
	function new_uri_redirect_and_404() {
		global $wp_query, $wp, $wp_rewrite, $wpdb, $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_external_redirects, $permalink_manager_options, $pm_query;

		// Get the redirection mode & trailing slashes settings
		$redirect_mode             = ( ! empty( $permalink_manager_options['general']['redirect'] ) ) ? $permalink_manager_options['general']['redirect'] : false;
		$trailing_slashes_mode     = ( ! empty( $permalink_manager_options['general']['trailing_slashes'] ) ) ? $permalink_manager_options['general']['trailing_slashes'] : false;
		$trailing_slashes_redirect = ( ! empty( $permalink_manager_options['general']['trailing_slashes_redirect'] ) ) ? $permalink_manager_options['general']['trailing_slashes_redirect'] : false;
		$extra_redirects           = ( ! empty( $permalink_manager_options['general']['extra_redirects'] ) ) ? $permalink_manager_options['general']['extra_redirects'] : false;
		$canonical_redirect        = ( ! empty( $permalink_manager_options['general']['canonical_redirect'] ) ) ? $permalink_manager_options['general']['canonical_redirect'] : false;
		$old_slug_redirect         = ( ! empty( $permalink_manager_options['general']['old_slug_redirect'] ) ) ? $permalink_manager_options['general']['old_slug_redirect'] : false;
		$endpoint_redirect         = ( ! empty( $permalink_manager_options['general']['endpoint_redirect'] ) ) ? $permalink_manager_options['general']['endpoint_redirect'] : false;
		$copy_query_redirect       = ( ! empty( $permalink_manager_options['general']['copy_query_redirect'] ) ) ? $permalink_manager_options['general']['copy_query_redirect'] : false;
		$redirect_type             = '-';

		// Get home URL
		$home_url = rtrim( get_option( 'home' ), "/" );
		$home_dir = parse_url( $home_url, PHP_URL_PATH );

		// Set up $correct_permalink variable
		$correct_permalink = '';

		// Get query string & URI
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$query_string = ( $copy_query_redirect && ! empty( $_SERVER['QUERY_STRING'] ) ) ? $_SERVER['QUERY_STRING'] : '';
		$old_uri      = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$old_uri      = $old_uri_abs = ( empty( $old_uri ) ) ? strtok( $_SERVER["REQUEST_URI"], '?' ) : $old_uri;

		// Fix for WP installed in directories (remove the directory name from the URI)
		if ( ! empty( $home_dir ) ) {
			$home_dir_regex = preg_quote( trim( $home_dir ), "/" );
			$old_uri        = preg_replace( "/{$home_dir_regex}/", "", $old_uri, 1 );
		}

		// Do not use custom redirects on author pages, search & front page
		if ( ! is_author() && ! is_front_page() && ! is_home() && ! is_feed() && ! is_search() && empty( $_GET['s'] ) ) {
			// Sometimes $wp_query indicates the wrong object if requested directly
			$queried_object = get_queried_object();

			// Unset 404 if custom URI is detected
			if ( ! empty( $pm_query['id'] ) && ( empty( $queried_object->post_status ) || $queried_object->post_status !== 'private' ) ) {
				$wp_query->is_404 = false;
			}

			/**
			 * 1A. External redirect
			 */
			if ( ! empty( $pm_query['id'] ) && ! empty( $permalink_manager_external_redirects[ $pm_query['id'] ] ) ) {
				$external_url = $permalink_manager_external_redirects[ $pm_query['id'] ];

				if ( filter_var( $external_url, FILTER_VALIDATE_URL ) ) {
					// Allow redirect
					$wp_query->query_vars['do_not_redirect'] = 0;

					wp_redirect( $external_url, 301, PERMALINK_MANAGER_PLUGIN_NAME );
					exit();
				}
			}

			/**
			 * 1B. Custom redirects
			 */
			if ( empty( $wp_query->query_vars['do_not_redirect'] ) && $extra_redirects && ! empty( $permalink_manager_redirects ) && is_array( $permalink_manager_redirects ) && ! empty( $wp->request ) && ! empty( $pm_query['uri'] ) ) {
				$uri            = $pm_query['uri'];
				$endpoint_value = $pm_query['endpoint_value'];

				// Make sure that URIs with non-ASCII characters are also detected + Check the URLs that end with number
				$decoded_url  = urldecode( $uri );
				$endpoint_url = "{$uri}/{$endpoint_value}";

				// Convert to lowercase to make case-insensitive
				$force_lowercase = apply_filters( 'permalink_manager_force_lowercase_uris', true );

				if ( $force_lowercase ) {
					$uri          = strtolower( $uri );
					$decoded_url  = strtolower( $decoded_url );
					$endpoint_url = strtolower( $endpoint_url );
				}

				// Check if the URI is not assigned to any post/term's redirects
				foreach ( $permalink_manager_redirects as $element => $redirects ) {
					if ( ! is_array( $redirects ) ) {
						continue;
					}

					if ( in_array( $uri, $redirects ) || in_array( $decoded_url, $redirects ) || ( is_numeric( $endpoint_value ) && in_array( $endpoint_url, $redirects ) ) ) {
						// Post is detected
						if ( is_numeric( $element ) ) {
							$correct_permalink = get_permalink( $element );
						} // Term is detected
						else {
							$term_id           = intval( preg_replace( "/[^0-9]/", "", $element ) );
							$correct_permalink = get_term_link( $term_id );
						}

						// The custom redirect is found so there is no need to query the rest of array
						break;
					}
				}

				$redirect_type = ( ! empty( $correct_permalink ) ) ? 'custom_redirect' : $redirect_type;
			}

			// Ignore WP-Content links
			if ( strpos( $_SERVER['REQUEST_URI'], '/wp-content' ) !== false ) {
				return;
			}

			/**
			 * 1C. Enhance native redirect
			 */
			if ( $canonical_redirect && empty( $wp_query->query_vars['do_not_redirect'] ) && ! empty( $queried_object ) && empty( $correct_permalink ) ) {
				// Affect only posts with custom URI and old URIs
				if ( ! empty( $queried_object->ID ) && isset( $permalink_manager_uris[ $queried_object->ID ] ) && empty( $wp_query->query['preview'] ) ) {
					// Ignore posts with specific statuses
					if ( ! ( empty( $queried_object->post_status ) ) && in_array( $queried_object->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ) ) ) {
						return;
					}

					// Check if the post is excluded
					if ( Permalink_Manager_Helper_Functions::is_post_excluded( $queried_object ) ) {
						return;
					}

					// Get the real URL
					$correct_permalink = get_permalink( $queried_object->ID );
				} // Affect only terms with custom URI and old URIs
				else if ( ! empty( $queried_object->term_id ) && isset( $permalink_manager_uris["tax-{$queried_object->term_id}"] ) && defined( 'PERMALINK_MANAGER_PRO' ) ) {
					// Check if the term is excluded
					if ( Permalink_Manager_Helper_Functions::is_term_excluded( $queried_object ) ) {
						return;
					}

					// Get the real URL
					$correct_permalink = get_term_link( $queried_object->term_id, $queried_object->taxonomy );
				}

				$redirect_type = ( ! empty( $correct_permalink ) ) ? 'native_redirect' : $redirect_type;
			}

			/**
			 * 1D. Old slug redirect
			 */
			if ( $old_slug_redirect && ! empty( $pm_query['uri'] ) && empty( $wp_query->query_vars['do_not_redirect'] ) && is_404() && empty( $correct_permalink ) ) {
				$slug = basename( $pm_query['uri'] );

				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug' AND meta_value = %s", $slug ) );
				if ( ! empty( $post_id ) ) {
					$correct_permalink = get_permalink( $post_id );
					$redirect_type     = 'old_slug_redirect';
				}
			}

			/**
			 * 2. Prevent redirect loop
			 */
			if ( ! empty( $correct_permalink ) && is_string( $correct_permalink ) && ! empty( $wp->request ) && $redirect_type != 'slash_redirect' ) {
				$current_uri  = trim( $wp->request, "/" );
				$redirect_uri = trim( parse_url( $correct_permalink, PHP_URL_PATH ), "/" );

				$correct_permalink = ( $redirect_uri == $current_uri ) ? null : $correct_permalink;
			}

			/**
			 * 3. Add endpoints to redirect URL
			 */
			if ( ! empty( $correct_permalink ) && $endpoint_redirect && ( ! empty( $pm_query['endpoint_value'] ) || ! empty( $pm_query['endpoint'] ) ) ) {
				$endpoint_value = $pm_query['endpoint_value'];

				if ( empty( $pm_query['endpoint'] ) && is_numeric( $endpoint_value ) ) {
					$correct_permalink = sprintf( "%s/%d", trim( $correct_permalink, "/" ), $endpoint_value );
				} else if ( isset( $pm_query['endpoint'] ) && ! empty( $endpoint_value ) ) {
					if ( $pm_query['endpoint'] == 'cpage' ) {
						$correct_permalink = sprintf( "%s/%s-%s", trim( $correct_permalink, "/" ), $wp_rewrite->comments_pagination_base, $endpoint_value );
					} else {
						$correct_permalink = sprintf( "%s/%s/%s", trim( $correct_permalink, "/" ), $pm_query['endpoint'], $endpoint_value );
					}
				} else {
					$correct_permalink = sprintf( "%s/%s", trim( $correct_permalink, "/" ), $pm_query['endpoint'] );
				}
			}
		} else {
			$queried_object = '-';
		}

		/**
		 * 4. Check trailing & duplicated slashes (ignore links with query parameters)
		 */
		if ( ( ( $trailing_slashes_mode && $trailing_slashes_redirect ) || preg_match( '/\/{2,}/', $old_uri ) ) && empty( $_POST ) && empty( $correct_permalink ) && empty( $query_string ) && ! empty( $old_uri ) && $old_uri !== "/" ) {
			$trailing_slash = ( substr( $old_uri, - 1 ) == "/" ) ? true : false;
			$obsolete_slash = ( preg_match( '/\/{2,}/', $old_uri ) || preg_match( "/.*\.([a-zA-Z]{3,4})\/$/", $old_uri ) );

			if ( ( $trailing_slashes_mode == 1 && ! $trailing_slash ) || ( $trailing_slashes_mode == 2 && $trailing_slash ) || $obsolete_slash ) {
				$new_uri = self::control_trailing_slashes( $old_uri );

				if ( $new_uri !== $old_uri ) {
					$correct_permalink = sprintf( "%s/%s", $home_url, ltrim( $new_uri, '/' ) );
					$redirect_type     = 'slash_redirect';
				}
			}
		}

		/**
		 * 5. WWW prefix | SSL mismatch redirect
		 */
		if ( ! empty( $permalink_manager_options['general']['sslwww_redirect'] ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$home_url_has_www      = ( strpos( $home_url, 'www.' ) !== false ) ? true : false;
			$requested_url_has_www = ( strpos( $_SERVER['HTTP_HOST'], 'www.' ) !== false ) ? true : false;
			$home_url_has_ssl      = ( strpos( $home_url, 'https' ) !== false ) ? true : false;

			if ( ( $home_url_has_www !== $requested_url_has_www ) || ( ! is_ssl() && $home_url_has_ssl !== false ) ) {
				$new_uri           = ltrim( $old_uri, '/' );
				$correct_permalink = sprintf( "%s/%s", $home_url, $new_uri );

				$redirect_type = 'www_redirect';
			}
		}

		/**
		 * 6. Debug redirect
		 */
		$correct_permalink = apply_filters( 'permalink_manager_filter_redirect', $correct_permalink, $redirect_type, $queried_object, $old_uri );

		/**
		 * 7. Ignore default URIs (or do nothing if redirects are disabled)
		 */
		if ( ! empty( $correct_permalink ) && is_string( $correct_permalink ) && ! empty( $redirect_mode ) ) {
			// Allow redirect
			$wp_query->query_vars['do_not_redirect'] = 0;

			// Append query string
			$correct_permalink = ( ! empty( $query_string ) ) ? sprintf( "%s?%s", strtok( $correct_permalink, "?" ), $query_string ) : $correct_permalink;

			// Adjust trailing slashes
			$correct_permalink = self::control_trailing_slashes( $correct_permalink );

			// Prevent redirect loop
			$rel_old_uri = wp_make_link_relative( $old_uri_abs );
			$rel_new_uri = wp_make_link_relative( $correct_permalink );

			if ( $redirect_type === 'www_redirect' || $rel_old_uri !== $rel_new_uri ) {
				wp_safe_redirect( $correct_permalink, $redirect_mode, PERMALINK_MANAGER_PLUGIN_NAME );
				exit();
			}
		}
	}

	/**
	 * Control how the canonical redirect function in WordPress and other popular plugins works
	 */
	function adjust_canonical_redirect() {
		global $permalink_manager_options, $wp, $wp_query, $wp_rewrite;

		// Adjust rewrite settings for trailing slashes
		$trailing_slash_setting = ( ! empty( $permalink_manager_options['general']['trailing_slashes'] ) ) ? $permalink_manager_options['general']['trailing_slashes'] : "";
		if ( in_array( $trailing_slash_setting, array( 1, 10 ) ) ) {
			$wp_rewrite->use_trailing_slashes = true;
		} else if ( in_array( $trailing_slash_setting, array( 2, 20 ) ) ) {
			$wp_rewrite->use_trailing_slashes = false;
		}

		// Get endpoints
		$endpoints       = Permalink_Manager_Helper_Functions::get_endpoints();
		$endpoints_array = ( $endpoints ) ? explode( "|", $endpoints ) : array();

		// Check if any endpoint is called (fix for endpoints)
		foreach ( $endpoints_array as $endpoint ) {
			if ( ! empty( $wp->query_vars[ $endpoint ] ) && ! in_array( $endpoint, array( 'attachment', 'page', 'paged', 'feed' ) ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
				break;
			}
		}

		// Allow canonical redirect for ../1/ and ../page/1/
		if ( ( ! empty( $wp->query_vars['paged'] ) && $wp->query_vars['paged'] == 1 ) || ( ! empty( $wp->query_vars['page'] ) && $wp->query_vars['page'] == 1 ) ) {
			$wp_query->query_vars['do_not_redirect'] = 0;
		} // Allow canonical redirect for URL with specific query parameters
		else if ( ( is_single() && ( ! empty( $_GET['p'] ) || ! empty( $_GET['name'] ) ) ) || ( is_page() && ! empty( $_GET['page_id'] ) ) ) {
			$wp_query->query_vars['do_not_redirect'] = 0;
		}

		if ( empty( $permalink_manager_options['general']['canonical_redirect'] ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
		}

		if ( empty( $permalink_manager_options['general']['old_slug_redirect'] ) ) {
			remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		}

		if ( ! empty( $wp_query->query_vars['do_not_redirect'] ) ) {
			if ( function_exists( 'rank_math' ) && ! empty( $permalink_manager_options['general']['rankmath_redirect'] ) ) {
				$rank_math_instance = rank_math();

				if ( ! empty( $rank_math_instance->manager ) && is_object( $rank_math_instance->manager ) && method_exists( $rank_math_instance->manager, 'get_module' ) ) {
					$rank_math_redirections_module = $rank_math_instance->manager->get_module( 'redirections' );

					if ( ! empty( $rank_math_redirections_module ) ) {
						remove_action( 'template_redirect', array( $rank_math_redirections_module, 'do_redirection' ), 11 );
						remove_action( 'wp', array( $rank_math_redirections_module, 'do_redirection' ), 11 );
					}
				}
			}

			// SEOPress
			remove_action( 'template_redirect', 'seopress_category_redirect', 1 );

			remove_action( 'template_redirect', 'wp_old_slug_redirect' );
			remove_action( 'template_redirect', 'redirect_canonical' );
			add_filter( 'wpml_is_redirected', '__return_false', 99, 2 );
			add_filter( 'pll_check_canonical_url', '__return_false', 99, 2 );
		}
	}

	/**
	 * Enable case-insensitive permalinks mode
	 */
	function case_insensitive_permalinks() {
		global $permalink_manager_uris;

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = strtolower( $_SERVER['REQUEST_URI'] );
			$permalink_manager_uris = array_map( 'strtolower', $permalink_manager_uris );
		}
	}

}
