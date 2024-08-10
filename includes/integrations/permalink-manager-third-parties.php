<?php

/**
 * Third parties integration
 */
class Permalink_Manager_Third_Parties {

	public function __construct() {
		add_action( 'init', array( $this, 'init_hooks' ), 99 );
		add_action( 'plugins_loaded', array( $this, 'init_early_hooks' ), 99 );
	}

	/**
	 * Add support for 3rd party plugins using their hooks
	 */
	function init_hooks() {
		global $permalink_manager_options;

		// Stop redirect
		add_action( 'wp', array( $this, 'stop_redirect' ), 2 );

		// AMP
		if ( defined( 'AMP_QUERY_VAR' ) ) {
			add_filter( 'permalink_manager_detect_uri', array( $this, 'detect_amp' ), 10, 2 );
			add_filter( 'request', array( $this, 'enable_amp' ), 10, 1 );
		}

		// AMP for WP
		if ( defined( 'AMPFORWP_AMP_QUERY_VAR' ) ) {
			add_filter( 'permalink_manager_filter_query', array( $this, 'detect_amp_for_wp' ), 5 );
		}

		// Theme My Login
		if ( class_exists( 'Theme_My_Login' ) ) {
			add_action( 'wp', array( $this, 'tml_ignore_custom_permalinks' ), 10 );
		}

		// Revisionize
		if ( defined( 'REVISIONIZE_ROOT' ) ) {
			add_action( 'revisionize_after_create_revision', array( $this, 'revisionize_keep_post_uri' ), 9, 2 );
			add_action( 'revisionize_before_publish', array( $this, 'revisionize_clone_uri' ), 9, 2 );
		}

		// WP All Import
		if ( class_exists( 'PMXI_Plugin' ) && ( ! empty( $permalink_manager_options['general']['pmxi_support'] ) ) ) {
			add_action( 'pmxi_extend_options_featured', array( $this, 'wpaiextra_uri_display' ), 9, 2 );
			add_filter( 'pmxi_options_options', array( $this, 'wpai_api_options' ) );
			add_filter( 'pmxi_addons', array( $this, 'wpai_api_register' ) );
			add_filter( 'wp_all_import_addon_parse', array( $this, 'wpai_api_parse' ) );
			add_filter( 'wp_all_import_addon_import', array( $this, 'wpai_api_import' ) );

			add_action( 'pmxi_saved_post', array( $this, 'wpai_save_redirects' ) );

			add_action( 'pmxi_after_xml_import', array( $this, 'wpai_schedule_regenerate_uris_after_xml_import' ), 10, 1 );
			add_action( 'wpai_regenerate_uris_after_import_event', array( $this, 'wpai_regenerate_uris_after_import' ), 10, 1 );
		}

		// WP All Export
		if ( class_exists( 'PMXE_Plugin' ) && ( ! empty( $permalink_manager_options['general']['pmxi_support'] ) ) ) {
			add_filter( 'wp_all_export_available_sections', array( $this, 'wpae_custom_uri_section' ), 9 );
			add_filter( 'wp_all_export_available_data', array( $this, 'wpae_custom_uri_section_fields' ), 9 );
			add_filter( 'wp_all_export_csv_rows', array( $this, 'wpae_export_custom_uri' ), 10, 2 );
		}

		// Duplicate Post
		if ( defined( 'DUPLICATE_POST_CURRENT_VERSION' ) ) {
			add_action( 'dp_duplicate_post', array( $this, 'duplicate_custom_uri' ), 100, 2 );
			add_action( 'dp_duplicate_page', array( $this, 'duplicate_custom_uri' ), 100, 2 );
		}

		// My Listing by 27collective
		if ( class_exists( '\MyListing\Post_Types' ) ) {
			add_filter( 'permalink_manager_filter_default_post_uri', array( $this, 'ml_listing_custom_fields' ), 5, 5 );
			add_action( 'mylisting/submission/save-listing-data', array( $this, 'ml_set_listing_uri' ), 100 );
			add_filter( 'permalink_manager_filter_query', array( $this, 'ml_detect_archives' ), 1, 2 );
		}

		// bbPress
		if ( class_exists( 'bbPress' ) && function_exists( 'bbp_get_edit_slug' ) ) {
			add_filter( 'permalink_manager_endpoints', array( $this, 'bbpress_endpoints' ), 9 );
			add_action( 'wp', array( $this, 'bbpress_detect_endpoints' ), 0 );
		}

		// Dokan
		if ( class_exists( 'WeDevs_Dokan' ) ) {
			add_action( 'wp', array( $this, 'dokan_detect_endpoints' ), 999 );
			add_filter( 'permalink_manager_endpoints', array( $this, 'dokan_endpoints' ) );
		}

		// GeoDirectory
		if ( class_exists( 'GeoDirectory' ) ) {
			add_filter( 'permalink_manager_filter_default_post_uri', array( $this, 'geodir_custom_fields' ), 5, 5 );
		}

		// BasePress
		if ( class_exists( 'Basepress' ) ) {
			add_filter( 'permalink_manager_filter_query', array( $this, 'kb_adjust_query' ), 5, 5 );
		}

		// Ultimate Member
		if ( class_exists( 'UM' ) && ! ( empty( $permalink_manager_options['general']['um_support'] ) ) ) {
			add_filter( 'permalink_manager_detect_uri', array( $this, 'um_detect_extra_pages' ), 20 );
		}

		// LearnPress
		if ( class_exists( 'LearnPress' ) ) {
			add_filter( 'permalink_manager_excluded_post_ids', array( $this, 'learnpress_exclude_pages' ) );
		}

		// Google Site Kit
		if ( class_exists( '\Google\Site_Kit\Plugin' ) ) {
			add_filter( 'request', array( $this, 'googlesitekit_fix_request' ), 10, 1 );
		}
	}

	/**
	 * Some hooks must be called shortly after all the plugins are loaded
	 */
	public function init_early_hooks() {
		// WP Store Locator
		if ( class_exists( 'WPSL_CSV' ) ) {
			add_action( 'added_post_meta', array( $this, 'wpsl_regenerate_after_import' ), 10, 4 );
			add_action( 'updated_post_meta', array( $this, 'wpsl_regenerate_after_import' ), 10, 4 );
		}
	}

	/**
	 * Stop canonical redirect if specific query variables are set
	 */
	public static function stop_redirect() {
		global $wp_query, $post;

		if ( ! empty( $wp_query->query ) ) {
			$query_vars = $wp_query->query;

			// WordPress Photo Seller Plugin
			if ( ! empty( $query_vars['image_id'] ) && ! empty( $query_vars['gallery_id'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // Ultimate Member
			else if ( ! empty( $query_vars['um_user'] ) || ! empty( $query_vars['um_tab'] ) || ( ! empty( $query_vars['provider'] ) && ! empty( $query_vars['state'] ) ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // Mailster
			else if ( ! empty( $query_vars['_mailster_page'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // WP Route
			else if ( ! empty( $query_vars['WP_Route'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // WooCommerce Wishlist
			else if ( ! empty( $query_vars['wishlist-action'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // UserPro
			else if ( ! empty( $query_vars['up_username'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // The Events Calendar
			else if ( ! empty( $query_vars['eventDisplay'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // Groundhogg
			else if ( class_exists( '\Groundhogg\Plugin' ) && ! empty( $query_vars['subpage'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // MyListing theme
			else if ( ! empty( $query_vars['explore_tab'] ) || ! empty( $query_vars['explore_region'] ) || ! empty( $_POST['submit_job'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // GeoDirectory
			else if ( function_exists( 'geodir_location_page_id' ) && ! empty( $post->ID ) && geodir_location_page_id() == $post->ID ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // RankMath Pro
			else if ( isset( $query_vars['schema-preview'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // Theme.co - Pro Theme
			else if ( ! empty( $_POST['_cs_nonce'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // Tutor LMS
			else if ( ! empty( $query_vars['tutor_dashboard_page'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			} // AMP
			else if ( function_exists( 'amp_get_slug' ) && array_key_exists( amp_get_slug(), $query_vars ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}

			// LearnPress
			if ( ! empty( $query_vars['view'] ) && ! empty( $query_vars['page_id'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
		}

		// WPForo
		if ( defined( 'WPFORO_VERSION' ) ) {
			$forum_page_id = get_option( 'wpforo_pageid' );

			if ( ! empty( $forum_page_id ) && ! empty( $post->ID ) && $forum_page_id == $post->ID ) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
		}
	}

	/**
	 * AMP hooks (support for older versions)
	 *
	 * @param array $uri_parts
	 * @param string $request_url
	 *
	 * @return array
	 */
	function detect_amp( $uri_parts, $request_url ) {
		global $amp_enabled;

		if ( defined( 'AMP_QUERY_VAR' ) ) {
			$amp_query_var = AMP_QUERY_VAR;

			// Check if AMP should be triggered
			preg_match( "/^(.+?)\/({$amp_query_var})?\/?$/i", $uri_parts['uri'], $regex_parts );
			if ( ! empty( $regex_parts[2] ) ) {
				$uri_parts['uri'] = $regex_parts[1];
				$amp_enabled      = true;
			}
		}

		return $uri_parts;
	}

	/**
	 * AMP hooks
	 *
	 * @param array $query
	 *
	 * @return array
	 */
	function enable_amp( $query ) {
		global $amp_enabled;

		if ( ! empty( $amp_enabled ) && defined( 'AMP_QUERY_VAR' ) ) {
			$query[ AMP_QUERY_VAR ] = 1;
		}

		return $query;
	}

	/**
	 * AMP for WP hooks (support for older versions)
	 *
	 * @param array $query
	 *
	 * @return array
	 */
	function detect_amp_for_wp( $query ) {
		global $wp_rewrite, $pm_query;

		if ( defined( 'AMPFORWP_AMP_QUERY_VAR' ) ) {
			$amp_endpoint   = AMPFORWP_AMP_QUERY_VAR;
			$paged_endpoint = $wp_rewrite->pagination_base;

			if ( ! empty( $pm_query['endpoint'] ) && strpos( $pm_query['endpoint_value'], "{$paged_endpoint}/" ) !== false ) {
				$paged_val = preg_replace( "/({$paged_endpoint}\/)([\d]+)/", '$2', $pm_query['endpoint_value'] );

				if ( ! empty( $paged_val ) ) {
					$query[ $amp_endpoint ] = 1;
					$query['paged']         = $paged_val;
				}
			}
		}

		return $query;
	}

	/**
	 * Parse Custom Permalinks import
	 */
	public static function custom_permalinks_uris() {
		global $wpdb;

		$custom_permalinks_uris = array();

		// List tags/categories
		$table = get_option( 'custom_permalink_table' );
		if ( $table && is_array( $table ) ) {
			foreach ( $table as $permalink => $info ) {
				$custom_permalinks_uris[] = array(
					'id'  => "tax-" . $info['id'],
					'uri' => trim( $permalink, "/" )
				);
			}
		}

		// List posts/pages
		$query = "SELECT p.ID, m.meta_value FROM $wpdb->posts AS p LEFT JOIN $wpdb->postmeta AS m ON (p.ID = m.post_id)  WHERE m.meta_key = 'custom_permalink' AND m.meta_value != '';";
		$posts = $wpdb->get_results( $query );
		foreach ( $posts as $post ) {
			$custom_permalinks_uris[] = array(
				'id'  => $post->ID,
				'uri' => trim( $post->meta_value, "/" ),
			);
		}

		return $custom_permalinks_uris;
	}

	/**
	 * Import the URIs from the Custom Permalinks plugin.
	 */
	static public function import_custom_permalinks_uris() {
		global $permalink_manager_before_sections_html;

		$custom_permalinks_plugin = 'custom-permalinks/custom-permalinks.php';

		if ( is_plugin_active( $custom_permalinks_plugin ) && ! empty( $_POST['disable_custom_permalinks'] ) ) {
			deactivate_plugins( $custom_permalinks_plugin );
		}

		// Get a list of imported URIs
		$custom_permalinks_uris = self::custom_permalinks_uris();

		if ( ! empty( $custom_permalinks_uris ) && count( $custom_permalinks_uris ) > 0 ) {
			foreach ( $custom_permalinks_uris as $item ) {
				$item_uri = $item['uri'];

				// Decode custom permalink if contains percent-encoded characters
				if ( preg_match( '/%[0-9A-F]{2}/i', $item_uri ) ) {
					$item_uri = urldecode( $item_uri );
				}

				Permalink_Manager_URI_Functions::save_single_uri( $item['id'], $item_uri, false, false );
			}

			$permalink_manager_before_sections_html .= Permalink_Manager_UI_Elements::get_alert_message( __( '"Custom Permalinks" URIs were imported!', 'permalink-manager' ), 'updated' );
			Permalink_Manager_URI_Functions::save_all_uris();
		} else {
			$permalink_manager_before_sections_html .= Permalink_Manager_UI_Elements::get_alert_message( __( 'No "Custom Permalinks" URIs were imported!', 'permalink-manager' ), 'error' );
		}
	}

	/**
	 * Do not use custom permalinks if any action is triggered inside Theme My Login plugin
	 */
	function tml_ignore_custom_permalinks() {
		global $wp, $permalink_manager_ignore_permalink_filters;

		if ( isset( $wp->query_vars['action'] ) || ! empty( $_GET['redirect_to'] ) ) {
			$permalink_manager_ignore_permalink_filters = true;

			// Allow the canonical redirect (if blocked earlier by Permalink Manager)
			if ( ! empty( $wp_query->query_vars['do_not_redirect'] ) ) {
				$wp_query->query_vars['do_not_redirect'] = 0;
			}
		}
	}

	/**
	 * Copy the custom URI from original post and apply it to the new temp. revision post (Revisionize)
	 *
	 * @param int $old_id
	 * @param int $new_id
	 */
	function revisionize_keep_post_uri( $old_id, $new_id ) {
		$old_uri = Permalink_Manager_URI_Functions::get_single_uri( $old_id, false, true );

		if ( ! empty( $old_uri ) ) {
			Permalink_Manager_URI_Functions::save_single_uri( $new_id, $old_uri, false, true );
		}
	}

	/**
	 * Copy the custom URI from revision post and apply it to the original post
	 *
	 * @param int $old_id
	 * @param int $new_id
	 */
	function revisionize_clone_uri( $old_id, $new_id ) {
		$new_uri = Permalink_Manager_URI_Functions::get_single_uri( $new_id, false, true );

		if ( ! empty( $new_uri ) ) {
			Permalink_Manager_URI_Functions::save_single_uri( $old_id, $new_uri, false, true );
			Permalink_Manager_URI_Functions::remove_single_uri( $new_id, false, true );
		}
	}

	/**
	 * Add a new section to the WP All Import interface
	 *
	 * @param string $content_type The type of content being imported
	 * @param array $current_values An array of the current values for the post
	 */
	function wpaiextra_uri_display( $content_type, $current_values ) {
		// Check if post type is supported
		if ( $content_type !== 'taxonomies' && Permalink_Manager_Helper_Functions::is_post_type_disabled( $content_type ) ) {
			return;
		}

		// Get custom URI format
		$custom_uri = ( ! empty( $current_values['custom_uri'] ) ) ? sanitize_text_field( $current_values['custom_uri'] ) : "";

		$html = '<div class="wpallimport-collapsed closed wpallimport-section">';
		$html .= '<div class="wpallimport-content-section">';
		$html .= sprintf( '<div class="wpallimport-collapsed-header"><h3>%s</h3></div>', __( 'Permalink Manager', 'permalink-manager' ) );
		$html .= '<div class="wpallimport-collapsed-content">';

		$html .= '<div class="template_input">';
		$html .= Permalink_Manager_UI_Elements::generate_option_field( 'custom_uri', array( 'extra_atts' => 'style="width:100%; line-height: 25px;"', 'placeholder' => __( 'Custom URI', 'permalink-manager' ), 'value' => $custom_uri ) );
		$html .= wpautop( sprintf( __( 'If empty, a default permalink based on your current <a href="%s" target="_blank">permastructure settings</a> will be used.', 'permalink-manager' ), Permalink_Manager_Admin_Functions::get_admin_url( '&section=permastructs' ) ) );
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		echo $html;
	}

	/**
	 * Add a new field to the list of WP All Import options
	 *
	 * @param array $all_options The array of all options that are currently set
	 *
	 * @return array The array of all options plus the custom_uri option
	 */
	function wpai_api_options( $all_options ) {
		return $all_options + array( 'custom_uri' => null );
	}

	/**
	 * Add Permalink Manager plugin to the WP All Import API
	 *
	 * @param array $addons
	 *
	 * @return array
	 */
	function wpai_api_register( $addons ) {
		if ( empty( $addons[ PERMALINK_MANAGER_PLUGIN_SLUG ] ) ) {
			$addons[ PERMALINK_MANAGER_PLUGIN_SLUG ] = 1;
		}

		return $addons;
	}

	/**
	 * Register function that parses Permalink Manager plugin data in WP All Import API data feed
	 *
	 * @param array $functions
	 *
	 * @return array
	 */
	function wpai_api_parse( $functions ) {
		$functions[ PERMALINK_MANAGER_PLUGIN_SLUG ] = array( $this, 'wpai_api_parse_function' );

		return $functions;
	}

	/**
	 * Register function that saves Permalink Manager plugin data extracted from WP All Import API data feed
	 *
	 * @param array $functions
	 *
	 * @return array
	 */
	function wpai_api_import( $functions ) {
		$functions[ PERMALINK_MANAGER_PLUGIN_SLUG ] = array( $this, 'wpai_api_import_function' );

		return $functions;
	}

	/**
	 * Parse Permalink Manager plugin data in WP All Import API data feed
	 *
	 * @param array $data
	 *
	 * @return array
	 * @throws XmlImportException
	 */
	function wpai_api_parse_function( $data ) {
		extract( $data );

		$data        = array(); // parsed data
		$option_name = 'custom_uri';

		if ( ! empty( $import->options[ $option_name ] ) && class_exists( 'XmlImportParser' ) ) {
			$cxpath    = $xpath_prefix . $import->xpath;
			$tmp_files = array();

			if ( isset( $import->options[ $option_name ] ) && $import->options[ $option_name ] != '' ) {
				if ( $import->options[ $option_name ] == "xpath" ) {
					$data[ $option_name ] = XmlImportParser::factory( $xml, $cxpath, (string) $import->options['xpaths'][ $option_name ], $file )->parse();
				} else {
					$data[ $option_name ] = XmlImportParser::factory( $xml, $cxpath, (string) $import->options[ $option_name ], $file )->parse();
				}

				$tmp_files[] = $file;
			} else {
				$data[ $option_name ] = array_fill( 0, $count, "" );
			}

			foreach ( $tmp_files as $file ) {
				unlink( $file );
			}
		}

		return $data;
	}

	/**
	 * Save the Permalink Manager plugin data extracted from WP All Import API data feed
	 *
	 * @param array $importData
	 * @param array $parsedData
	 */
	function wpai_api_import_function( $importData, $parsedData ) {
		// Check if the array with $parsedData is not empty
		if ( empty( $parsedData ) || empty( $importData['post_type'] ) ) {
			return;
		}

		// Check if the imported elements are terms
		if ( $importData['post_type'] == 'taxonomies' ) {
			$is_term = true;
		} else if ( Permalink_Manager_Helper_Functions::is_post_type_disabled( $importData['post_type'] ) ) {
			return;
		}

		// Get the parsed custom URI
		$index = ( isset( $importData['i'] ) ) ? $importData['i'] : false;
		$pid   = ( ! empty( $importData['pid'] ) ) ? (int) $importData['pid'] : false;

		if ( isset( $index ) && ! empty( $pid ) && ! empty( $parsedData['custom_uri'][ $index ] ) ) {
			$new_uri = Permalink_Manager_Helper_Functions::sanitize_title( $parsedData['custom_uri'][ $index ] );

			if ( ! empty( $new_uri ) ) {
				if ( ! empty( $is_term ) ) {
					$default_uri = Permalink_Manager_URI_Functions_Tax::get_default_term_uri( $pid );
					$native_uri  = Permalink_Manager_URI_Functions_Tax::get_default_term_uri( $pid, true );
					$custom_uri  = Permalink_Manager_URI_Functions_Tax::get_term_uri( $pid, false, true );
					$old_uri     = ( ! empty( $custom_uri ) ) ? $custom_uri : $native_uri;

					if ( $new_uri !== $old_uri ) {
						Permalink_Manager_URI_Functions::save_single_uri( $pid, $new_uri, true, true );
						do_action( 'permalink_manager_updated_term_uri', $pid, $new_uri, $old_uri, $native_uri, $default_uri, $single_update = true, $uri_saved = true );
					}
				} else {
					$default_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $pid );
					$native_uri  = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $pid, true );
					$custom_uri  = Permalink_Manager_URI_Functions_Post::get_post_uri( $pid, false, true );
					$old_uri     = ( ! empty( $custom_uri ) ) ? $custom_uri : $native_uri;

					if ( $new_uri !== $old_uri ) {
						Permalink_Manager_URI_Functions::save_single_uri( $pid, $new_uri, false, true );
						do_action( 'permalink_manager_updated_post_uri', $pid, $new_uri, $old_uri, $native_uri, $default_uri, $single_update = true, $uri_saved = true );
					}
				}
			}
		}
	}

	/**
	 * Copy the external redirect from the "external_redirect" custom field to the data model used by Permalink Manager Pro
	 *
	 * @param int $pid The post ID of the post being imported.
	 */
	function wpai_save_redirects( $pid ) {
		$external_url = get_post_meta( $pid, '_external_redirect', true );
		$external_url = ( empty( $external_url ) ) ? get_post_meta( $pid, 'external_redirect', true ) : $external_url;

		if ( $external_url && class_exists( 'Permalink_Manager_Pro_Functions' ) ) {
			Permalink_Manager_Pro_Functions::save_external_redirect( $external_url, $pid );
		}
	}

	/**
	 * Use the import ID to extract all the post IDs that were imported, then splits them into chunks and schedule a single regenerate permalink event for each chunk
	 *
	 * @param int $import_id The ID of the import.
	 */
	function wpai_schedule_regenerate_uris_after_xml_import( $import_id ) {
		global $wpdb;

		$post_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->prefix}pmxi_posts WHERE import_id = {$import_id}" );
		$chunks   = array_chunk( $post_ids, 200 );

		// Schedule URI regenerate and split into bulks
		foreach ( $chunks as $i => $chunk ) {
			wp_schedule_single_event( time() + ( $i * 30 ), 'wpai_regenerate_uris_after_import_event', array( $chunk ) );
		}
	}

	/**
	 * Regenerate the custom permalinks for all imported posts
	 *
	 * @param array $post_ids An array of post IDs that were just imported.
	 */
	function wpai_regenerate_uris_after_import( $post_ids ) {
		if ( ! is_array( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $id ) {
			$current_uri = Permalink_Manager_URI_Functions::get_single_uri( $id, false, true, false );

			if ( ! empty( $current_uri ) ) {
				continue;
			}

			$post_object = get_post( $id );

			// Check if post is allowed
			if ( empty( $post_object->post_type ) || Permalink_Manager_Helper_Functions::is_post_excluded( $post_object, true ) ) {
				continue;
			}

			$default_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $id );
			Permalink_Manager_URI_Functions::save_single_uri( $id, $default_uri );
		}

		Permalink_Manager_URI_Functions::save_all_uris();
	}

	/**
	 * Add a new section to the WP All Export interface
	 *
	 * @param array $sections
	 *
	 * @return array
	 */
	function wpae_custom_uri_section( $sections ) {
		if ( is_array( $sections ) ) {
			$sections['permalink_manager'] = array(
				'title'   => __( 'Permalink Manager', 'permalink-manager' ),
				'content' => 'permalink_manager_fields'
			);
		}

		return $sections;
	}

	/**
	 * Add a new field to the "Permalink Manager" section of the WP All Export interface
	 *
	 * @param array $fields The fields to be displayed in the section.
	 *
	 * @return array
	 */
	function wpae_custom_uri_section_fields( $fields ) {
		if ( is_array( $fields ) ) {
			$fields['permalink_manager_fields'] = array(
				array(
					'label' => 'custom_uri',
					'name'  => 'Custom URI',
					'type'  => 'custom_uri'
				)
			);
		}

		return $fields;
	}

	/**
	 * Add a new column to the export file with the custom permalink for each post/term
	 *
	 * @param array $articles The array of articles to be exported.
	 * @param array $options an array of options for the export.
	 *
	 * @return array
	 */
	function wpae_export_custom_uri( $articles, $options ) {
		if ( ( ! empty( $options['selected_post_type'] ) && $options['selected_post_type'] == 'taxonomies' ) || ! empty( $options['is_taxonomy_export'] ) ) {
			$is_term = true;
		} else {
			$is_term = false;
		}

		foreach ( $articles as &$article ) {
			if ( ! empty( $article['id'] ) ) {
				$item_id = $article['id'];
			} else if ( ! empty( $article['ID'] ) ) {
				$item_id = $article['ID'];
			} else if ( ! empty( $article['Term ID'] ) ) {
				$item_id = $article['Term ID'];
			} else {
				continue;
			}

			if ( ! empty( $is_term ) ) {
				$article['Custom URI'] = Permalink_Manager_URI_Functions_Tax::get_term_uri( $item_id );
			} else {
				$article['Custom URI'] = Permalink_Manager_URI_Functions_Post::get_post_uri( $item_id );
			}
		}

		return $articles;
	}

	/**
	 * Check if the "custom_uri" is not blacklisted in the "duplicate_post_blacklist" option and if it's not, clone the custom permalink of the original post to the new one
	 *
	 * @param int $new_post_id The ID of the newly created post.
	 * @param WP_Post $old_post The post object of the original post.
	 */
	function duplicate_custom_uri( $new_post_id, $old_post ) {
		$duplicate_post_blacklist  = get_option( 'duplicate_post_blacklist', false );
		$duplicate_custom_uri_bool = ( ! empty( $duplicate_post_blacklist ) && strpos( $duplicate_post_blacklist, 'custom_uri' ) !== false ) ? false : true;

		if ( ! empty( $old_post->ID ) && $duplicate_custom_uri_bool ) {
			$old_post_uri = Permalink_Manager_URI_Functions::get_single_uri( $old_post->ID, false, true, false );

			// Clone custom permalink (if set for cloned post/page)
			if ( ! empty( $old_post_uri ) ) {
				$new_post_uri = preg_replace( '/(.+?)(\.[^\.]+$|$)/', '$1-2$2', $old_post_uri );

				Permalink_Manager_URI_Functions::save_single_uri( $new_post_id, $new_post_uri, false, true );
			}
		}
	}

	/**
	 * Replace in the default permalink format the unique permastructure tags available for My Listing theme
	 *
	 * @param string $default_uri The default permalink for the element.
	 * @param string $native_slug The native slug of the post type.
	 * @param WP_Post $element The post object.
	 * @param string $slug The slug of the post type.
	 * @param bool $native_uri
	 *
	 * @return string
	 */
	public function ml_listing_custom_fields( $default_uri, $native_slug, $element, $slug, $native_uri ) {
		// Use only for "listing" post type & custom permalink
		if ( empty( $element->post_type ) || $element->post_type !== 'job_listing' ) {
			return $default_uri;
		}

		// A1. Listing type
		if ( strpos( $default_uri, '%listing-type%' ) !== false || strpos( $default_uri, '%listing_type%' ) !== false ) {
			if ( class_exists( 'MyListing\Src\Listing' ) ) {
				$listing_type_post = MyListing\Src\Listing::get( $element );
				$listing_type      = ( is_object( $listing_type_post ) && ! empty( $listing_type_post->type ) ) ? $listing_type_post->type->get_permalink_name() : '';
			} else {
				$listing_type_slug = get_post_meta( $element->ID, '_case27_listing_type', true );
				$listing_type_post = get_page_by_path( $listing_type_slug, OBJECT, 'case27_listing_type' );

				if ( ! empty( $listing_type_post ) ) {
					$listing_type_post_settings = get_post_meta( $listing_type_post->ID, 'case27_listing_type_settings_page', true );
					$listing_type_post_settings = ( is_serialized( $listing_type_post_settings ) ) ? unserialize( $listing_type_post_settings ) : array();

					$listing_type = ( ! empty( $listing_type_post_settings['permalink'] ) ) ? $listing_type_post_settings['permalink'] : $listing_type_post->post_name;
				}
			}

			if ( ! empty( $listing_type ) ) {
				$default_uri = str_replace( array( '%listing-type%', '%listing_type%' ), Permalink_Manager_Helper_Functions::sanitize_title( $listing_type, true ), $default_uri );
			}
		}

		// A2. Listing type (slug)
		if ( strpos( $default_uri, '%listing-type-slug%' ) !== false || strpos( $default_uri, '%listing_type_slug%' ) !== false || strpos( $default_uri, '%case27_listing_type%' ) !== false ) {
			$listing_type = get_post_meta( $element->ID, '_case27_listing_type', true );

			if ( ! empty( $listing_type ) ) {
				$listing_type = Permalink_Manager_Helper_Functions::sanitize_title( $listing_type, true );
				$default_uri  = str_replace( array( '%listing-type-slug%', '%listing_type_slug%', '%case27_listing_type%' ), $listing_type, $default_uri );
			}
		}

		// B. Listing location
		if ( strpos( $default_uri, '%listing-location%' ) !== false || strpos( $default_uri, '%listing_location%' ) !== false ) {
			$listing_location = get_post_meta( $element->ID, '_job_location', true );

			if ( ! empty( $listing_location ) ) {
				$listing_location = Permalink_Manager_Helper_Functions::sanitize_title( $listing_location, true );
				$default_uri      = str_replace( array( '%listing-location%', '%listing_location%' ), $listing_location, $default_uri );
			}
		}

		// C. Listing region
		if ( strpos( $default_uri, '%listing-region%' ) !== false || strpos( $default_uri, '%listing_region%' ) !== false ) {
			$listing_region_terms = wp_get_object_terms( $element->ID, 'region' );
			$listing_region_term  = ( ! is_wp_error( $listing_region_terms ) && ! empty( $listing_region_terms ) && is_object( $listing_region_terms[0] ) ) ? Permalink_Manager_Helper_Functions::get_lowest_element( $listing_region_terms[0], $listing_region_terms ) : "";

			if ( ! empty( $listing_region_term ) ) {
				$listing_region = Permalink_Manager_Helper_Functions::get_term_full_slug( $listing_region_term, $listing_region_terms, 2 );
				$listing_region = Permalink_Manager_Helper_Functions::sanitize_title( $listing_region, true );

				$default_uri = str_replace( array( '%listing-region%', '%listing_region%' ), $listing_region, $default_uri );
			}
		}

		// D. Listing category
		if ( strpos( $default_uri, '%listing-category%' ) !== false || strpos( $default_uri, '%listing_category%' ) !== false ) {
			$listing_category_terms = wp_get_object_terms( $element->ID, 'job_listing_category' );
			$listing_category_term  = ( ! is_wp_error( $listing_category_terms ) && ! empty( $listing_category_terms ) && is_object( $listing_category_terms[0] ) ) ? Permalink_Manager_Helper_Functions::get_lowest_element( $listing_category_terms[0], $listing_category_terms ) : "";

			if ( ! empty( $listing_category_term ) ) {
				$listing_category = Permalink_Manager_Helper_Functions::get_term_full_slug( $listing_category_term, $listing_category_terms, 2 );
				$listing_category = Permalink_Manager_Helper_Functions::sanitize_title( $listing_category, true );

				$default_uri = str_replace( array( '%listing-category%', '%listing_category%' ), $listing_category, $default_uri );
			}
		}

		return $default_uri;
	}

	/**
	 * Set the default custom permalink for the listing item when it is created
	 *
	 * @param int $post_id
	 */
	function ml_set_listing_uri( $post_id ) {
		$default_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $post_id );

		if ( $default_uri ) {
			Permalink_Manager_URI_Functions::save_single_uri( $post_id, $default_uri, false, true );
		}
	}

	/**
	 * If the user is on a MyListing archive page submitting a job, redirect the user to the Explore Listings page
	 *
	 * @param array $query The query object.
	 * @param array $old_query The original query array.
	 *
	 * @return array The new query array is being returned if the explore_tab property is present. Otherwise, the original query is returned.
	 */
	function ml_detect_archives( $query, $old_query ) {
		if ( function_exists( 'mylisting_custom_taxonomies' ) && empty( $_POST['submit_job'] ) ) {
			$explore_page_id = get_option( 'options_general_explore_listings_page', false );
			if ( empty( $explore_page_id ) ) {
				return $query;
			}

			// Set-up new query array variable
			$new_query = array(
				"page_id" => $explore_page_id
			);

			// Check if any custom MyListing taxonomy was detected
			$ml_taxonomies = mylisting_custom_taxonomies();

			if ( ! empty( $ml_taxonomies ) && is_array( $ml_taxonomies ) ) {
				$ml_taxonomies = array_keys( $ml_taxonomies );

				foreach ( $ml_taxonomies as $taxonomy ) {
					if ( ! empty( $query[ $taxonomy ] ) && empty( $_GET[ $taxonomy ] ) ) {
						$new_query["explore_tab"]         = $taxonomy;
						$new_query["explore_{$taxonomy}"] = $query['term'];
					}
				}
			}

			// Check if any MyListing query var was detected
			$ml_query_vars = array(
				'explore_tag'      => 'tags',
				'explore_region'   => 'regions',
				'explore_category' => 'categories'
			);

			foreach ( $ml_query_vars as $query_var => $explore_tab ) {
				if ( ! empty( $old_query[ $query_var ] ) && empty( $_GET[ $query_var ] ) ) {
					$new_query[ $query_var ]  = $old_query[ $query_var ];
					$new_query["explore_tab"] = $explore_tab;
				}
			}
		}

		return ( ! empty( $new_query["explore_tab"] ) ) ? $new_query : $query;
	}

	/**
	 * Add the bbPress endpoints to the list of endpoints that are supported by the plugin
	 *
	 * @param string $endpoints
	 * @param bool $all Whether to return all endpoints or just the bbPress ones
	 *
	 * @return string|array
	 */
	function bbpress_endpoints( $endpoints, $all = true ) {
		$bbpress_endpoints   = array();
		$bbpress_endpoints[] = bbp_get_edit_slug();

		return ( $all ) ? $endpoints . "|" . implode( "|", $bbpress_endpoints ) : $bbpress_endpoints;
	}

	/**
	 * If the query contains the edit endpoint, then set the appropriate bbPress query variable
	 */
	function bbpress_detect_endpoints() {
		global $wp_query;

		if ( ! empty( $wp_query->query ) ) {
			$edit_endpoint = bbp_get_edit_slug();

			if ( isset( $wp_query->query[ $edit_endpoint ] ) ) {
				if ( isset( $wp_query->query['forum'] ) ) {
					$wp_query->bbp_is_forum_edit = true;
				} else if ( isset( $wp_query->query['topic'] ) ) {
					$wp_query->bbp_is_topic_edit = true;
				} else if ( isset( $wp_query->query['reply'] ) ) {
					$wp_query->bbp_is_reply_edit = true;
				}
			}
		}
	}

	/**
	 * Add the endpoint "edit" used by Dokan to the endpoints array supported by Permalink Manager
	 *
	 * @param string $endpoints
	 *
	 * @return string
	 */
	function dokan_endpoints( $endpoints ) {
		return "{$endpoints}|edit|edit-account";
	}

	/**
	 * Check if the current page is a Dokan page, and if so, adjust the query variables to disable the canonical redirect
	 */
	function dokan_detect_endpoints() {
		global $post, $wp_query, $wp, $pm_query;

		// Check if Dokan is activated
		if ( ! function_exists( 'dokan_get_option' ) || is_admin() || empty( $pm_query['id'] ) ) {
			return;
		}

		// Get Dokan dashboard page id
		$dashboard_page = dokan_get_option( 'dashboard', 'dokan_pages' );

		// Stop the redirect
		if ( ! empty( $dashboard_page ) && ! empty( $post->ID ) && ( $post->ID == $dashboard_page ) ) {
			$wp_query->query_vars['do_not_redirect'] = 1;

			// Detect Dokan shortcode
			if ( empty( $pm_query['endpoint'] ) ) {
				$wp->query_vars['page'] = 1;
			} else if ( isset( $wp->query_vars['page'] ) ) {
				unset( $wp->query_vars['page'] );
			}
		}

		// Support "Edit Product" pages
		if ( isset( $wp_query->query_vars['edit'] ) ) {
			$wp_query->query_vars['edit']            = 1;
			$wp_query->query_vars['do_not_redirect'] = 1;
		}
	}

	/**
	 * Replace in the default permalink format the unique permastructure tags available for GeoDirectory plugin
	 *
	 * @param string $default_uri The default permalink for the element.
	 * @param string $native_slug The native slug of the post type.
	 * @param WP_Post $element The post object.
	 * @param string $slug The slug of the post type.
	 * @param bool $native_uri
	 *
	 * @return string
	 */
	public function geodir_custom_fields( $default_uri, $native_slug, $element, $slug, $native_uri ) {
		// Use only for GeoDirectory post types & custom permalinks
		if ( empty( $element->post_type ) || ( strpos( $element->post_type, 'gd_' ) === false ) || $native_uri || ! function_exists( 'geodir_get_post_info' ) ) {
			return $default_uri;
		}

		// Get place info
		$place_data = geodir_get_post_info( $element->ID );

		// A. Category
		if ( strpos( $default_uri, '%category%' ) !== false ) {
			$place_category_terms = wp_get_object_terms( $element->ID, 'gd_placecategory' );
			$place_category_term  = ( ! is_wp_error( $place_category_terms ) && ! empty( $place_category_terms ) && is_object( $place_category_terms[0] ) ) ? Permalink_Manager_Helper_Functions::get_lowest_element( $place_category_terms[0], $place_category_terms ) : "";

			if ( ! empty( $place_category_term ) && is_a( $place_category_term, 'WP_Term' ) ) {
				$place_category = Permalink_Manager_Helper_Functions::get_term_full_slug( $place_category_term, '', 2 );
				$place_category = Permalink_Manager_Helper_Functions::sanitize_title( $place_category, true );

				$default_uri = str_replace( '%category%', $place_category, $default_uri );
			}
		}

		// B. Country
		if ( strpos( $default_uri, '%country%' ) !== false && ! empty( $place_data->country ) ) {
			$place_country = Permalink_Manager_Helper_Functions::sanitize_title( $place_data->country, true );
			$default_uri   = str_replace( '%country%', $place_country, $default_uri );
		}

		// C. Region
		if ( strpos( $default_uri, '%region%' ) !== false && ! empty( $place_data->region ) ) {
			$place_region = Permalink_Manager_Helper_Functions::sanitize_title( $place_data->region, true );
			$default_uri  = str_replace( '%region%', $place_region, $default_uri );
		}

		// D. City
		if ( strpos( $default_uri, '%city%' ) !== false && ! empty( $place_data->city ) ) {
			$place_city  = Permalink_Manager_Helper_Functions::sanitize_title( $place_data->city, true );
			$default_uri = str_replace( '%city%', $place_city, $default_uri );
		}

		return $default_uri;
	}

	/**
	 * Adjust the query if BasePress page is detected
	 *
	 * @param array $query The query object.
	 * @param array $old_query The original query array.
	 * @param array $uri_parts An array of the URI parts.
	 * @param array $pm_query
	 * @param string $content_type
	 *
	 * @return array
	 */
	function kb_adjust_query( $query, $old_query, $uri_parts, $pm_query, $content_type ) {
		$knowledgebase_options = get_option( 'basepress_settings' );
		$knowledgebase_page    = ( ! empty( $knowledgebase_options['entry_page'] ) ) ? $knowledgebase_options['entry_page'] : '';

		// A. Knowledgebase category
		if ( isset( $query['knowledgebase_cat'] ) && ! empty( $pm_query['id'] ) ) {
			$kb_category = $query['knowledgebase_cat'];

			$query = array(
				'post_type'           => 'knowledgebase',
				'knowledgebase_items' => $kb_category
			);

			// Disable the canonical redirect function included in BasePress
			add_filter( 'basepress_canonical_redirect', '__return_false' );
		} // B. Knowledgebase main page
		else if ( ! empty( $knowledgebase_page ) && ! empty( $pm_query['id'] ) && $pm_query['id'] == $knowledgebase_page ) {
			$query = array(
				'page_id' => $knowledgebase_page
			);
		}

		return $query;
	}

	/**
	 * Detect the extra pages created by Ultimate Member plugin
	 *
	 * @param array $uri_parts
	 *
	 * @return array
	 */
	public function um_detect_extra_pages( $uri_parts ) {
		if ( ! function_exists( 'UM' ) ) {
			return $uri_parts;
		}

		$request_url = trim( "{$uri_parts['uri']}/{$uri_parts['endpoint_value']}", "/" );
		$um_pages    = array(
			'user'    => 'um_user',
			'account' => 'um_tab',
		);

		// Detect UM permalinks
		foreach ( $um_pages as $um_page => $query_var ) {
			$um_page_id = UM()->config()->permalinks[ $um_page ];

			// Support for WPML/Polylang
			$um_page_id  = ( ! empty( $uri_parts['lang'] ) ) ? apply_filters( 'wpml_object_id', $um_page_id, 'page', true, $uri_parts['lang'] ) : $um_page_id;
			$um_page_uri = ( ! empty( $um_page_id ) ) ? Permalink_Manager_URI_Functions::get_single_uri( $um_page_id, false, true, false ) : '';

			if ( ! empty( $um_page_id ) && ! empty( $um_page_uri ) ) {
				$user_page_uri = preg_quote( $um_page_uri, '/' );
				preg_match( "/^({$user_page_uri})\/([^\/]+)?$/", $request_url, $parts );

				if ( ! empty( $parts[2] ) ) {
					$uri_parts['uri']            = $parts[1];
					$uri_parts['endpoint']       = $query_var;
					$uri_parts['endpoint_value'] = Permalink_Manager_Helper_Functions::sanitize_title( $parts[2], null, null, false );
				}
			}
		}

		return $uri_parts;
	}

	/**
	 * Excluding the LearnPress pages from Permalink Manager
	 *
	 * @param array $excluded_ids
	 *
	 * @return array
	 */
	function learnpress_exclude_pages( $excluded_ids ) {
		if ( is_array( $excluded_ids ) && function_exists( 'learn_press_get_page_id' ) ) {
			$learnpress_pages = array( 'profile', 'courses', 'checkout', 'become_a_teacher' );

			foreach ( $learnpress_pages as $page ) {
				$learnpress_page_id = learn_press_get_page_id( $page );

				if ( empty( $learnpress_page_id ) || in_array( $learnpress_page_id, $excluded_ids ) ) {
					continue;
				}

				$excluded_ids[] = $learnpress_page_id;
			}
		}

		return $excluded_ids;
	}

	/**
	 * Regenerate the default permalink for the post after the custom permalink is imported by Store Locator - CSV Manager
	 *
	 * @param int $meta_id The ID of the metadata entry.
	 * @param int $post_id The ID of the post that the metadata is for.
	 * @param string $meta_key The meta key of the metadata being updated.
	 * @param mixed $meta_value The value of the meta key.
	 */
	public function wpsl_regenerate_after_import( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( strpos( $meta_key, 'wpsl_' ) !== false && isset( $_POST['wpsl_csv_import_nonce'] ) ) {
			$default_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $post_id );

			if ( $default_uri ) {
				Permalink_Manager_URI_Functions::save_single_uri( $post_id, $default_uri, false, true );
			}
		}
	}

	/**
	 * Support custom permalinks in query functions used by Google Site Kit plugin
	 *
	 * @param $request
	 *
	 * @return array
	 */
	function googlesitekit_fix_request( $request ) {
		if ( ! empty( $_GET['permaLink'] ) && ! empty( $_GET['page'] ) && $_GET['page'] === 'googlesitekit-dashboard' ) {
			global $pm_query;

			$old_url   = trim( esc_url_raw( $_GET['permaLink'] ), '/' );
			$new_query = Permalink_Manager_Core_Functions::detect_post( array(), $old_url );

			if ( ! empty( $new_query ) && ! empty( $pm_query['id'] ) ) {
				$request = $new_query;
			}
		}

		return $request;
	}

}
