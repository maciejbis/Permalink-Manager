<?php

/**
 * Functions used to create, edit and remove custom permalinks
 */
class Permalink_Manager_URI_Functions {

	public function __construct() {}

	/**
	 * Get the custom permalink's array key for specific post or term
	 *
	 * @param WP_Post|WP_Term|int|string $element
	 * @param bool $is_tax
	 *
	 * @return array
	 */
	public static function get_single_uri_key( $element, $is_tax = false ) {
		if ( ! empty( $element->term_id ) ) {
			$is_term    = true;
			$element_id = $element->term_id;
		} else if ( ! empty( $element->ID ) ) {
			$is_term    = false;
			$element_id = $element->ID;
		} else if ( is_string( $element ) || is_numeric( $element ) ) {
			$is_term    = ( strpos( $element, 'tax-' ) !== false ) ? true : $is_tax;
			$element_id = preg_replace( '/[^0-9]/', '', $element );
		} else {
			$element_id = "";
			$is_term = null;
		}

		$array_index = ( $is_term && ! empty( $element_id ) ) ? sprintf( 'tax-%s', $element_id ) : $element_id;

		return array( $element_id, $is_term, $array_index );
	}

	/**
	 * Get the single custom permalink
	 *
	 * @param WP_Post|WP_Term|int $element
	 * @param bool $native_uri
	 * @param bool $no_fallback
	 */
	public static function get_single_uri( $element, $native_uri = false, $no_fallback = false, $is_tax = false ) {
		// Get the element key
		list( $element_id, $is_term, $array_index ) = self::get_single_uri_key( $element, $is_tax );

		if ( $is_term ) {
			$final_uri = ( class_exists( 'Permalink_Manager_URI_Functions_Tax' ) ) ? Permalink_Manager_URI_Functions_Tax::get_term_uri( $element_id, $native_uri, $no_fallback ) : '';
		} else {
			$final_uri = Permalink_Manager_URI_Functions_Post::get_post_uri( $element_id, $native_uri, $no_fallback );
		}

		return $final_uri;
	}

	/**
	 * Save single custom permalink to the custom permalinks array
	 *
	 * @param int|string $element
	 * @param string $element_uri
	 * @param bool $is_tax
	 * @param bool $db_save
	 */
	public static function save_single_uri( $element, $element_uri = null, $is_tax = false, $db_save = false ) {
		global $permalink_manager_uris;

		// Get the element key
		list( $element_id, $is_term, $array_index ) = self::get_single_uri_key( $element, $is_tax );

		// Save the custom permalink if the URI is not empty
		if ( ! empty( $array_index ) && ! empty( $element_uri ) ) {
			$permalink_manager_uris[ $array_index ] = Permalink_Manager_Helper_Functions::sanitize_title( $element_uri, true );

			if ( $db_save ) {
				self::save_all_uris( $permalink_manager_uris );
			}
		}
	}

	/**
	 * Remove single custom permalink from the custom permalinks array
	 *
	 * @param int|string $element
	 * @param bool $is_tax
	 * @param bool $db_save
	 */
	public static function remove_single_uri( $element, $is_tax = false, $db_save = false ) {
		global $permalink_manager_uris;

		// Get the element key
		list( $element_id, $is_term, $array_index ) = self::get_single_uri_key( $element, $is_tax );

		// Check if the custom permalink is assigned to this element
		if ( ! empty( $array_index ) && isset( $permalink_manager_uris[ $array_index ] ) ) {
			unset( $permalink_manager_uris[ $array_index ] );
		}

		if ( $db_save ) {
			self::save_all_uris( $permalink_manager_uris );
		}
	}

	/**
	 * Find the ID(s) of the element(s) by its custom permalink
	 *
	 * @param string $search_query
	 * @param bool $strict_search
	 * @param string $content_type
	 *
	 * @return bool|string|array
	 */
	public static function find_uri( $search_query, $strict_search = true, $content_type = null ) {
		$custom_permalinks = self::get_all_uris();
		$found             = false;

		if ( $strict_search ) {
			$all_uris = array_flip( $custom_permalinks );

			$found = ( ! empty( $all_uris[ $search_query ] ) ) ? $all_uris[ $search_query ] : false;
		} else {
			$search_query = preg_quote( $search_query, '/' );

			foreach ( $custom_permalinks as $id => $uri ) {
				if ( preg_match( "/\b$search_query\b/i", $uri ) ) {
					if ( $content_type == 'taxonomies' && ( strpos( $id, 'tax-' ) !== false ) ) {
						$found[] = (int) abs( filter_var( $id, FILTER_SANITIZE_NUMBER_INT ) );
					} else if ( $content_type == 'posts' && is_numeric( $id ) ) {
						$found[] = (int) filter_var( $id, FILTER_SANITIZE_NUMBER_INT );
					} else if ( empty( $content_type ) ) {
						$found[] = $id;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Check if a single URI is duplicated
	 *
	 * @param string $uri
	 * @param int $element_id
	 * @param array $duplicated_ids
	 *
	 * @return bool
	 */
	public static function is_uri_duplicated( $uri, $element_id, $duplicated_ids = array() ) {
		$custom_permalinks = Permalink_Manager_URI_Functions::get_all_uris();

		if ( empty( $uri ) || empty( $element_id ) || empty( $custom_permalinks ) ) {
			return false;
		}

		$uri        = trim( trim( sanitize_text_field( $uri ) ), "/" );
		$element_id = sanitize_text_field( $element_id );

		// Keep the URIs in a separate array just here
		if ( ! empty( $duplicated_ids ) ) {
			$all_duplicates = $duplicated_ids;
		} else if ( in_array( $uri, $custom_permalinks ) ) {
			$all_duplicates = array_keys( $custom_permalinks, $uri );
		}

		if ( ! empty( $all_duplicates ) ) {
			// Get the language code of current element
			$this_uri_lang = apply_filters( 'permalink_manager_get_language_code', '', $element_id );

			foreach ( $all_duplicates as $key => $duplicated_id ) {
				// Ignore custom redirects
				if ( strpos( $key, 'redirect-' ) !== false ) {
					unset( $all_duplicates[ $key ] );
					continue;
				}

				if ( $this_uri_lang ) {
					$duplicated_uri_lang = apply_filters( 'permalink_manager_get_language_code', '', $duplicated_id );
				}

				// Ignore the URI for requested element and other elements in other languages to prevent the false alert
				if ( ( ! empty( $duplicated_uri_lang ) && $duplicated_uri_lang !== $this_uri_lang ) || $element_id == $duplicated_id ) {
					unset( $all_duplicates[ $key ] );
				}
			}

			return ( count( $all_duplicates ) > 0 ) ? true : false;
		} else {
			return false;
		}
	}

	/**
	 * Get the array (or statistics) with custom permalinks
	 *
	 * @param bool $stats
	 *
	 * @return array
	 */
	public static function get_all_uris( $stats = false ) {
		global $permalink_manager_uris;

		if ( $stats ) {
			$custom_permalinks_size  = strlen( serialize( $permalink_manager_uris ) );
			$custom_permalinks_count = count( $permalink_manager_uris );

			return array( $custom_permalinks_size, $custom_permalinks_count );
		} else {
			return $permalink_manager_uris;
		}
	}

	/**
	 * Save the array with custom permalinks
	 *
	 * @param array $updated_uris
	 */
	public static function save_all_uris( $updated_uris = null ) {
		if ( is_null( $updated_uris ) ) {
			global $permalink_manager_uris;
			$updated_uris = $permalink_manager_uris;
		}

		if ( is_array( $updated_uris ) && ! empty( $updated_uris ) ) {
			update_option( 'permalink-manager-uris', $updated_uris, false );
		}
	}

}
