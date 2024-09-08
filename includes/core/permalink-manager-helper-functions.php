<?php

/**
 * Helper functions used in classes and another subclasses
 */
class Permalink_Manager_Helper_Functions {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 5 );
	}

	/**
	 * Add hooks used by plugin to filter the custom permalinks
	 */
	public function init() {
		// Replace empty placeholder tags & remove BOM
		add_filter( 'permalink_manager_filter_default_post_uri', array( $this, 'replace_empty_placeholder_tags' ), 10, 5 );
		add_filter( 'permalink_manager_filter_default_term_uri', array( $this, 'replace_empty_placeholder_tags' ), 10, 5 );

		// Clear the final default URIs
		add_filter( 'permalink_manager_filter_default_term_uri', array( $this, 'clear_single_uri' ), 20 );
		add_filter( 'permalink_manager_filter_default_post_uri', array( $this, 'clear_single_uri' ), 20 );

		// Reload the globals when the blog is switched (multisite)
		add_action( 'switch_blog', array( $this, 'reload_globals_in_network' ), 9 );
	}

	/**
	 * Get the primary term for the specific post
	 *
	 * @param int $post_id
	 * @param string $taxonomy
	 * @param bool $slug_only
	 *
	 * @return array|string|WP_Term
	 */
	static function get_primary_term( $post_id, $taxonomy, $slug_only = true ) {
		global $permalink_manager_options;

		$primary_term_enabled = ( isset( $permalink_manager_options['general']['primary_category'] ) ) ? (bool) $permalink_manager_options['general']['primary_category'] : true;
		$primary_term_enabled = apply_filters( 'permalink_manager_primary_term', $primary_term_enabled );

		if ( ! $primary_term_enabled ) {
			return '';
		}

		// A. Yoast SEO
		if ( class_exists( 'WPSEO_Primary_Term' ) ) {
			$yoast_primary_term_label = sprintf( 'yoast_wpseo_primary_%s_term', $taxonomy );

			// Hotfix: Yoast SEO saves the primary term using 'save_post' hook with the highest priority, so the primary term ID is taken directly from $_POST
			if ( ! empty( $_POST[ $yoast_primary_term_label ] ) ) {
				$yoast_primary_term_id = filter_input( INPUT_POST, $yoast_primary_term_label, FILTER_SANITIZE_NUMBER_INT );
			} else {
				$yoast_primary_term    = new WPSEO_Primary_Term( $taxonomy, $post_id );
				$yoast_primary_term_id = $yoast_primary_term->get_primary_term();
			}

			$primary_term = ( is_numeric( $yoast_primary_term_id ) ) ? get_term( $yoast_primary_term_id, $taxonomy ) : '';
		} // B. The SEO Framework
		else if ( function_exists( 'the_seo_framework' ) || function_exists( 'tsf' ) ) {
			$primary_term = ( function_exists( 'tsf' ) ) ? tsf()->data()->plugin()->post()->get_primary_term( $post_id, $taxonomy ) : the_seo_framework()->get_primary_term( $post_id, $taxonomy );
		} // C. RankMath
		else if ( class_exists( 'RankMath' ) ) {
			$primary_cat_id = get_post_meta( $post_id, "rank_math_primary_{$taxonomy}", true );
			$primary_term   = ( ! empty( $primary_cat_id ) ) ? get_term( $primary_cat_id, $taxonomy ) : '';
		} // D. SEOPress
		else if ( function_exists( 'seopress_init' ) && $taxonomy == 'category' ) {
			$primary_cat_id = get_post_meta( $post_id, '_seopress_robots_primary_cat', true );
			$primary_term   = ( ! empty( $primary_cat_id ) ) ? get_term( $primary_cat_id, 'category' ) : '';
		} // E. SmartCrawler
		else if ( class_exists( '\SmartCrawl\SmartCrawl' )  ) {
			$primary_cat_id = get_post_meta( $post_id, "wds_primary_{$taxonomy}", true );
			$primary_term   = ( ! empty( $primary_cat_id ) ) ? get_term( $primary_cat_id, $taxonomy ) : '';
		} // F. All In One SEO Pro
		else if ( class_exists( '\AIOSEO\Plugin\Pro\Standalone\PrimaryTerm' ) ) {
			$primary_term_class = new \AIOSEO\Plugin\Pro\Standalone\PrimaryTerm();
			$primary_term       = ( method_exists( $primary_term_class, 'getPrimaryTerm' ) ) ? $primary_term_class->getPrimaryTerm( $post_id, $taxonomy ) : '';
		}

		if ( ! empty( $primary_term ) && ! is_wp_error( $primary_term ) ) {
			return ( $slug_only ) ? $primary_term->slug : $primary_term;
		} else {
			return '';
		}
	}

	/**
	 * Get the lowest level term/post in the specific array
	 *
	 * @param WP_Post|WP_Term|int $first_element
	 * @param array $elements
	 *
	 * @return WP_Post|WP_Term|int
	 */
	static function get_lowest_element( $first_element, $elements ) {
		if ( ! empty( $elements ) && ! empty( $first_element ) ) {
			// Get the ID of first element
			if ( ! empty( $first_element->term_id ) ) {
				$first_element_id = $first_element->term_id;
				$parent_key       = 'parent';
			} else if ( ! empty( $first_element->ID ) ) {
				$first_element_id = $first_element->ID;
				$parent_key       = 'post_parent';
			} else if ( is_numeric( $first_element ) ) {
				$first_element_id = $first_element;
				$parent_key       = 'post_parent';
			} else {
				return false;
			}

			$children = wp_filter_object_list( $elements, array( $parent_key => $first_element_id ) );
			if ( ! empty( $children ) ) {
				// Get the first term
				$child_term    = reset( $children );
				$first_element = self::get_lowest_element( $child_term, $elements );
			}
		}

		return $first_element;
	}

	/**
	 * Get the full (hierarchical) slug for specific term object
	 *
	 * @param WP_Term $term
	 * @param mixed|WP_Term[] $terms
	 * @param bool $mode
	 * @param bool $native_uri
	 *
	 * @return string
	 */
	static function get_term_full_slug( $term, $terms, $mode = false, $native_uri = false ) {
		global $permalink_manager_uris;

		// Check if term is not empty
		if ( empty( $term->taxonomy ) ) {
			return '';
		}

		// Get taxonomy
		$taxonomy = $term->taxonomy;

		// Check if mode is set
		if ( empty( $mode ) ) {
			$mode = ( is_taxonomy_hierarchical( $taxonomy ) ) ? 2 : 4;
		}

		// A. Inherit the custom permalink from the term
		if ( $mode == 1 ) {
			$term_slug = ( ! empty( $permalink_manager_uris["tax-{$term->term_id}"] ) ) ? $permalink_manager_uris["tax-{$term->term_id}"] : '';
		} // B. Hierarchical taxonomy base
		else if ( $mode == 2 ) {
			$ancestors          = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );
			$hierarchical_slugs = array();

			foreach ( $ancestors as $ancestor ) {
				$ancestor_term        = get_term( $ancestor, $taxonomy );
				$hierarchical_slugs[] = ( $native_uri ) ? $ancestor_term->slug : self::force_custom_slugs( $ancestor_term->slug, $ancestor_term );
			}
			$hierarchical_slugs = array_reverse( $hierarchical_slugs );
			$term_slug          = implode( '/', $hierarchical_slugs );

			// Append the term slug now
			$last_term_slug = ( $native_uri ) ? $term->slug : self::force_custom_slugs( $term->slug, $term );
			$term_slug      = "{$term_slug}/{$last_term_slug}";
		} // C. Force flat taxonomy base - get the highest level term (if %taxonomy_top% tag is used)
		else if ( $mode == 3 ) {
			if ( ! empty( $term->parent ) ) {
				$ancestors = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );

				if ( is_array( $ancestors ) ) {
					$top_ancestor      = end( $ancestors );
					$top_ancestor_term = get_term( $top_ancestor, $taxonomy );
					$single_term       = ( ! empty( $top_ancestor_term->slug ) ) ? $top_ancestor_term : $term;
				}
			}

			$term_slug = ( ! empty( $single_term->slug ) ) ? self::force_custom_slugs( $single_term->slug, $single_term ) : $term->slug;
		} // D. Force flat taxonomy base - get primary or lowest level term (if term is non-hierarchical or %taxonomy_flat% tag is used)
		else {
			if ( ! empty( $term->slug ) ) {
				$term_slug = ( $native_uri ) ? $term->slug : Permalink_Manager_Helper_Functions::force_custom_slugs( $term->slug, $term );
			} else if ( ! empty ( $terms ) ) {
				foreach ( $terms as $single_term ) {
					if ( $single_term->parent == 0 ) {
						$term_slug = self::force_custom_slugs( $single_term->slug, $single_term );
						break;
					}
				}
			}
		}

		return ( ! empty( $term_slug ) ) ? $term_slug : "";
	}

	/**
	 * Allow to disable post types and taxonomies
	 */
	static function get_disabled_post_types( $include_user_excluded = true ) {
		global $wp_post_types, $permalink_manager_options;

		$disabled_post_types = array(
			'revision',
			'nav_menu_item',
			'algolia_task',
			'fl_builder',
			'fl-builder',
			'fl-builder-template',
			'fl-theme-layout',
			'fusion_tb_layout',
			'fusion_tb_section',
			'fusion_template',
			'fusion_element',
			'wc_product_tab',
			'wc_voucher',
			'wc_voucher_template',
			'sliders',
			'thirstylink',
			'elementor_library',
			'elementor_menu_item',
			'cms_block',
			'nooz_coverage'
		);

		// 1. Disable post types that are not publicly_queryable
		foreach ( $wp_post_types as $post_type ) {
			if ( ! is_post_type_viewable( $post_type ) || ( empty( $post_type->query_var ) && empty( $post_type->rewrite ) && empty( $post_type->_builtin ) && ! empty( $permalink_manager_options['general']['partial_disable_strict'] ) ) ) {
				$disabled_post_types[] = $post_type->name;
			}
		}

		// 2. Add post types disabled by user
		if ( $include_user_excluded ) {
			$disabled_post_types = ( ! empty( $permalink_manager_options['general']['partial_disable']['post_types'] ) ) ? array_merge( (array) $permalink_manager_options['general']['partial_disable']['post_types'], $disabled_post_types ) : $disabled_post_types;
		}

		return apply_filters( 'permalink_manager_disabled_post_types', $disabled_post_types );
	}

	/**
	 * Get the array of all (including/excluding user selected) disabled taxonomies
	 *
	 * @param bool $include_user_excluded
	 *
	 * @return array
	 */
	static function get_disabled_taxonomies( $include_user_excluded = true ) {
		global $wp_taxonomies, $permalink_manager_options;

		$disabled_taxonomies = array(
			'product_shipping_class',
			'post_status',
			'fl-builder-template-category',
			'post_format',
			'nav_menu',
			'language'
		);

		// 1. Disable taxonomies that are not publicly_queryable
		foreach ( $wp_taxonomies as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) || ( empty( $taxonomy->query_var ) && empty( $taxonomy->rewrite ) && empty( $taxonomy->_builtin ) && ! empty( $permalink_manager_options['general']['partial_disable_strict'] ) ) ) {
				$disabled_taxonomies[] = $taxonomy->name;
			}
		}

		// 2. Add taxonomies disabled by user
		if ( $include_user_excluded ) {
			$disabled_taxonomies = ( ! empty( $permalink_manager_options['general']['partial_disable']['taxonomies'] ) ) ? array_merge( (array) $permalink_manager_options['general']['partial_disable']['taxonomies'], $disabled_taxonomies ) : $disabled_taxonomies;
		}

		return apply_filters( 'permalink_manager_disabled_taxonomies', $disabled_taxonomies );
	}

	/**
	 * Check if the post type should be ignored by Permalink Manager
	 *
	 * @param string $post_type
	 * @param bool $check_if_exists
	 *
	 * @return bool
	 */
	static public function is_post_type_disabled( $post_type, $check_if_exists = true ) {
		$disabled_post_types = self::get_disabled_post_types();
		$post_type_exists    = ( $check_if_exists ) ? post_type_exists( $post_type ) : true;

		return ( ( is_array( $disabled_post_types ) && in_array( $post_type, $disabled_post_types ) ) || empty( $post_type_exists ) ) ? true : false;
	}

	/**
	 * Check if the taxonomy should be ignored by Permalink Manager
	 *
	 * @param string $taxonomy
	 * @param bool $check_if_exists
	 *
	 * @return bool
	 */
	static public function is_taxonomy_disabled( $taxonomy, $check_if_exists = true ) {
		$disabled_taxonomies = self::get_disabled_taxonomies();
		$taxonomy_exists     = ( $check_if_exists ) ? taxonomy_exists( $taxonomy ) : true;

		return ( ( is_array( $disabled_taxonomies ) && in_array( $taxonomy, $disabled_taxonomies ) ) || empty( $taxonomy_exists ) ) ? true : false;
	}

	/**
	 * Get a list of all excluded posts' IDs
	 *
	 * @return array
	 */
	public static function get_excluded_post_ids() {
		global $permalink_manager_options;

		if ( ! empty( $permalink_manager_options["general"]["exclude_post_ids"] ) ) {
			$excluded_post_ids_raw = $permalink_manager_options["general"]["exclude_post_ids"];

			$excluded_post_ids = self::get_ids_from_string( $excluded_post_ids_raw );
		} else {
			$excluded_post_ids = array();
		}

		// Allow to filter the array via filter
		$excluded_post_ids = apply_filters( 'permalink_manager_excluded_post_ids', $excluded_post_ids );

		// Only numeric IDs are allowed
		return ( ! empty( $excluded_post_ids ) ) ? array_filter( $excluded_post_ids, 'is_numeric' ) : array();
	}

	/**
	 * Check if specific post should be ignored by Permalink Manager
	 *
	 * @param WP_Post|int $post
	 * @param bool $draft_check
	 * @param bool $strict_draft_check
	 *
	 * @return bool
	 */
	public static function is_post_excluded( $post = null, $draft_check = false, $strict_draft_check = false ) {
		$post = ( is_integer( $post ) ) ? get_post( $post ) : $post;

		// 1. Check if post type is disabled
		if ( ! empty( $post->post_type ) && self::is_post_type_disabled( $post->post_type ) ) {
			return true;
		}

		// 2. Get list of post IDs excluded in the plugin settings or via the filter
		$excluded_post_ids = self::get_excluded_post_ids();

		// 3. Exclude post IDs excluded via the filter or in the plugin settings
		if ( is_array( $excluded_post_ids ) && ! empty( $post->ID ) && in_array( $post->ID, $excluded_post_ids ) ) {
			return true;
		}

		// D. Check if post is a "draft", "pending", removed
		if ( $draft_check ) {
			return self::is_draft_excluded( $post, $strict_draft_check );
		}

		return false;
	}

	/**
	 * Check if specific post is a draft (or pending)
	 *
	 * @param WP_Post|int $post
	 * @param bool $strict_draft_check
	 *
	 * @return bool
	 */
	public static function is_draft_excluded( $post = null, $strict_draft_check = false ) {
		global $permalink_manager_options;

		$post = ( is_integer( $post ) ) ? get_post( $post ) : $post;

		// Check if post is a "draft", "pending" or moved to trash
		if ( ! empty( $post->post_status ) ) {
			if ( ! empty( $permalink_manager_options["general"]["ignore_drafts"] ) || $strict_draft_check ) {
				$post_statuses = ( $permalink_manager_options["general"]["ignore_drafts"] == 2 ) ? array( 'draft', 'pending' ) : array( 'draft' );

				if ( in_array( $post->post_status, $post_statuses ) ) {
					return true;
				}
			}

			if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ) ) || ( strpos( $post->post_name, 'revision-v1' ) !== false ) || ( ! empty( $post->post_name ) && $post->post_name == 'auto-draft' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a list of all excluded terms' IDs
	 *
	 * @return array
	 */
	public static function get_excluded_term_ids() {
		global $permalink_manager_options;

		if ( ! empty( $permalink_manager_options["general"]["exclude_term_ids"] ) ) {
			$excluded_term_ids_raw = $permalink_manager_options["general"]["exclude_term_ids"];

			$excluded_term_ids = self::get_ids_from_string( $excluded_term_ids_raw );
		} else {
			$excluded_term_ids = array();
		}

		// Allow to filter the array via filter
		$excluded_term_ids = apply_filters( 'permalink_manager_excluded_term_ids', $excluded_term_ids );

		// Only numeric IDs are allowed
		return ( ! empty( $excluded_term_ids ) ) ? array_filter( $excluded_term_ids, 'is_numeric' ) : array();
	}

	/**
	 * Check if specific term should be ignored by Permalink Manager
	 *
	 * @param WP_Term $term
	 *
	 * @return bool
	 */
	public static function is_term_excluded( $term = null ) {
		$term = ( is_numeric( $term ) ) ? get_term( $term ) : $term;

		// 1. Check if taxonomy is disabled
		if ( ! empty( $term->taxonomy ) && self::is_taxonomy_disabled( $term->taxonomy ) ) {
			return true;
		}

		// 2. Get list of term IDs excluded in the plugin settings or via the filter
		$excluded_term_ids = self::get_excluded_term_ids();

		// 3. Exclude term IDs excluded via the filter or in the plugin settings
		if ( is_array( $excluded_term_ids ) && ! empty( $term->term_id ) && in_array( $term->term_id, $excluded_term_ids ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all post types supported by Permalink Manager
	 *
	 * @param string $format
	 * @param string $cpt
	 * @param bool $include_user_excluded
	 *
	 * @return array
	 */
	static function get_post_types_array( $format = null, $cpt = null, $include_user_excluded = false ) {
		global $wp_post_types;

		$post_types_array    = array();
		$disabled_post_types = self::get_disabled_post_types( ! $include_user_excluded );

		foreach ( $wp_post_types as $post_type ) {
			if ( $format == 'full' ) {
				$post_types_array[ $post_type->name ] = array( 'label' => $post_type->labels->name, 'name' => $post_type->name );
			} else if ( $format == 'archive_slug' ) {
				// Ignore non-public post types
				if ( ! is_post_type_viewable( $post_type ) || empty( $post_type->has_archive ) ) {
					continue;
				}

				if ( ! $post_type->has_archive ) {
					$archive_slug = $post_type->has_archive;
				} else if ( is_array( $post_type->rewrite ) && ! empty( $post_type->rewrite['slug'] ) ) {
					$archive_slug = $post_type->rewrite['slug'];
				} else {
					$archive_slug = $post_type->name;
				}

				$post_types_array[ $post_type->name ] = $archive_slug;
			} else {
				$post_types_array[ $post_type->name ] = $post_type->labels->name;
			}
		}

		if ( is_array( $disabled_post_types ) ) {
			foreach ( $disabled_post_types as $post_type ) {
				if ( ! empty( $post_types_array[ $post_type ] ) ) {
					unset( $post_types_array[ $post_type ] );
				}
			}
		}

		return ( empty( $cpt ) ) ? $post_types_array : $post_types_array[ $cpt ];
	}

	/**
	 * Get all taxonomies supported by Permalink Manager
	 *
	 * @param string $format
	 * @param string $tax
	 * @param bool $include_user_excluded
	 *
	 * @return array
	 */
	static function get_taxonomies_array( $format = null, $tax = null, $include_user_excluded = false ) {
		global $wp_taxonomies;

		$taxonomies_array    = array();
		$disabled_taxonomies = self::get_disabled_taxonomies( ! $include_user_excluded );

		foreach ( $wp_taxonomies as $taxonomy ) {
			$taxonomy_name = ( ! empty( $taxonomy->labels->name ) ) ? $taxonomy->labels->name : '-';

			$taxonomies_array[ $taxonomy->name ] = ( $format == 'full' ) ? array( 'label' => $taxonomy->labels->name, 'name' => $taxonomy->name ) : $taxonomy_name;
		}

		if ( is_array( $disabled_taxonomies ) ) {
			foreach ( $disabled_taxonomies as $taxonomy ) {
				if ( ! empty( $taxonomies_array[ $taxonomy ] ) ) {
					unset( $taxonomies_array[ $taxonomy ] );
				}
			}
		}

		ksort( $taxonomies_array );

		return ( empty( $tax ) ) ? $taxonomies_array : $taxonomies_array[ $tax ];
	}

	/**
	 * Get all post statuses supported by Permalink Manager
	 */
	static function get_post_statuses() {
		$post_statuses = get_post_statuses();

		return apply_filters( 'permalink_manager_post_statuses', $post_statuses );
	}

	/**
	 * Get the default permalink format for specific post type
	 *
	 * @param string $post_type
	 * @param bool $remove_post_tag
	 *
	 * @return string
	 */
	static function get_default_permastruct( $post_type = 'page', $remove_post_tag = false ) {
		global $wp_rewrite;

		// Get default permastruct
		if ( $post_type == 'page' ) {
			$permastruct = $wp_rewrite->get_page_permastruct();
		} else if ( $post_type == 'post' ) {
			$permastruct = get_option( 'permalink_structure' );
		} else {
			$permastruct = $wp_rewrite->get_extra_permastruct( $post_type );
		}

		return ( $remove_post_tag ) ? trim( str_replace( array( "%postname%", "%pagename%", "%{$post_type}%" ), "", $permastruct ), "/" ) : $permastruct;
	}

	/**
	 * Get all the endpoints registered for WP_Rewrite object
	 */
	static function get_endpoints() {
		global $wp_rewrite;

		$pagination_endpoint = ( ! empty( $wp_rewrite->pagination_base ) ) ? $wp_rewrite->pagination_base : 'page';

		// Start with default endpoints
		$endpoints = "{$pagination_endpoint}|feed|embed|attachment|trackback|filter";

		if ( ! empty( $wp_rewrite->endpoints ) ) {
			foreach ( $wp_rewrite->endpoints as $endpoint ) {
				$endpoints .= "|{$endpoint[1]}";
			}
		}

		return apply_filters( "permalink_manager_endpoints", str_replace( "/", "\/", $endpoints ) );
	}

	/**
	 * Get a list of all structure tags
	 *
	 * @param bool $code
	 * @param string $separator
	 * @param bool $hide_slug_tags
	 *
	 * @return string
	 */
	static function get_all_structure_tags( $code = true, $separator = ', ', $hide_slug_tags = true ) {
		global $wp_rewrite;

		$tags = $wp_rewrite->rewritecode;

		// Hide slug tags
		if ( $hide_slug_tags ) {
			$post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
			foreach ( $post_types as $post_type => $post_type_name ) {
				$post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag( $post_type );
				// Find key with post type tag from rewrite code
				$key = array_search( $post_type_tag, $tags );
				if ( $key ) {
					unset( $tags[ $key ] );
				}
			}
		}

		// Extra tags
		$tags[] = '%taxonomy%';
		$tags[] = '%post_type%';
		$tags[] = '%term_id%';
		$tags[] = '%monthname%';

		foreach ( $tags as &$tag ) {
			$tag = ( $code ) ? "<code>{$tag}</code>" : "{$tag}";
		}
		$output = implode( $separator, $tags );

		return "<span class=\"structure-tags-list\">{$output}</span>";
	}

	/**
	 * Get the post name permastructure tag for specific post type
	 *
	 * @param string $post_type
	 *
	 * @return string
	 */
	static function get_post_tag( $post_type ) {
		// Get the post type (with fix for posts & pages)
		if ( $post_type == 'page' ) {
			$post_type_tag = '%pagename%';
		} else if ( $post_type == 'post' ) {
			$post_type_tag = '%postname%';
		} else {
			$post_type_tag = "%{$post_type}%";
		}

		return $post_type_tag;
	}

	/**
	 * Get the permalink base (home URL) for custom permalink
	 *
	 * @param string|int|WP_Post|WP_Term $element
	 *
	 * @return string
	 */
	public static function get_permalink_base( $element = null ) {
		return apply_filters( 'permalink_manager_filter_permalink_base', trim( get_option( 'home' ), "/" ), $element );
	}

	/**
	 * Check if the specific post is selected as a front-page
	 *
	 * @param int $page_id
	 *
	 * @return bool
	 */
	static function is_front_page( $page_id ) {
		$front_page_id = get_option( 'page_on_front' );
		$bool          = ( ! empty( $front_page_id ) && $page_id == $front_page_id ) ? true : false;

		return apply_filters( 'permalink_manager_is_front_page', $bool, $page_id, $front_page_id );
	}

	/**
	 * Check if the advanced mode is turned on
	 *
	 * @return bool
	 */
	static function is_advanced_mode_on() {
		global $permalink_manager_options;

		$bool = ( ! empty( $permalink_manager_options["general"]["advanced_mode"] ) && $permalink_manager_options["general"]["advanced_mode"] == 1 ) ? true : false;

		return apply_filters( 'permalink_manager_advanced_mode_on', $bool );
	}

	/**
	 * Sanitize the multidimensional array
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	static function sanitize_array( $data = array() ) {
		if ( ! is_array( $data ) || ! count( $data ) ) {
			return array();
		}

		foreach ( $data as $k => $v ) {
			if ( ! is_array( $v ) && ! is_object( $v ) ) {
				$data[ $k ] = htmlspecialchars( trim( $v ) );
			}
			if ( is_array( $v ) ) {
				$data[ $k ] = self::sanitize_array( $v );
			}
		}

		return $data;
	}

	/**
	 * Convert the text containing IDs and/or ID ranges to an array
	 *
	 * @param string $data
	 *
	 * @return array
	 */
	static function get_ids_from_string( $data ) {
		// Remove whitespaces and other invalid characters
		$raw_ids = esc_sql( preg_replace( '/[^\d,-]/', '', $data ) );

		// Convert the string into the array with IDs and/or ranges
		preg_match_all( "/([\d]+(?:-?[\d]+)?)/x", $raw_ids, $groups );

		$ids = array();

		if ( ! empty( $groups[1] ) ) {
			foreach ( $groups[1] as $group ) {
				if ( is_numeric( $group ) ) {
					$ids[] = $group;
				} else if ( preg_match( '/([\d]+)-([\d]+)/', $group, $range_limits ) ) {
					$range_start = (int) $range_limits[1];
					$range_end = (int) $range_limits[2];

					if ( $range_start < $range_end ) {
						$ids = array_merge( $ids, range( $range_start, $range_end ) );
					}
				}
			}

			$ids = array_unique( $ids );
		}

		return $ids;
	}

	/**
	 * Encode URI and keep special characters
	 *
	 * @param string $uri
	 *
	 * @return string
	 */
	static function encode_uri( $uri ) {
		return str_replace( array( '%2F', '%2C', '%7C', '%2B' ), array( '/', ',', '|', '+' ), urlencode( $uri ) );
	}

	/**
	 * Sanitize the given string to URI-safe format
	 *
	 * @param string $str
	 * @param bool $keep_percent_sign
	 * @param bool $force_lowercase
	 * @param bool $sanitize_slugs
	 *
	 * @return string
	 */
	public static function sanitize_title( $str, $keep_percent_sign = false, $force_lowercase = null, $sanitize_slugs = null ) {
		global $permalink_manager_options;

		// Force lowercase & hyphens
		$force_lowercase = ( ! is_null( $force_lowercase ) ) ? $force_lowercase : apply_filters( 'permalink_manager_force_lowercase_uris', true );

		if ( is_null( $sanitize_slugs ) ) {
			$sanitize_slugs = ( ! empty( $permalink_manager_options['general']['disable_slug_sanitization'] ) ) ? false : true;
		}

		// Allow to filter the slug before it is sanitized
		$str = apply_filters( 'permalink_manager_pre_sanitize_title', $str, $keep_percent_sign, $force_lowercase, $sanitize_slugs );

		// Remove accents & entities
		$clean = ( empty( $permalink_manager_options['general']['keep_accents'] ) ) ? remove_accents( $str ) : $str;
		$clean = str_replace( array( '&lt', '&gt', '&amp' ), '', $clean );

		$percent_sign   = ( $keep_percent_sign ) ? "\%" : "";
		$sanitize_regex = apply_filters( "permalink_manager_sanitize_regex", "/[^\p{Xan}a-zA-Z0-9{$percent_sign}\/_\.|+, -]/ui", $percent_sign );
		$clean          = preg_replace( $sanitize_regex, '', $clean );
		$clean          = ( $force_lowercase ) ? strtolower( $clean ) : $clean;

		// Remove ampersand
		$clean = str_replace( array( '%26', '&' ), '', $clean );

		// Remove special characters
		if ( $sanitize_slugs !== false ) {
			$clean = preg_replace( "/[\s|+-]+/", "-", $clean );
			$clean = preg_replace( "/[,]+/", "", $clean );
			$clean = preg_replace( '/([\.]+)(?![a-z]{3,4}$)/i', '', $clean );
			$clean = preg_replace( '/([-\s+]\/[-\s+])/', '-', $clean );
			$clean = preg_replace( '|-+|', '-', $clean );
		} else {
			$clean = preg_replace( "/[\s]+/", "-", $clean );
		}

		// Remove widow & duplicated slashes
		$clean = preg_replace( '/([-]*[\/]+[-]*)/', '/', $clean );
		$clean = preg_replace( '/([\/]+)/', '/', $clean );

		// Trim slashes, dashes and whitespaces
		$clean = trim( $clean, " /-" );

		// Allow to filter the slug after it is sanitized
		return apply_filters( 'permalink_manager_sanitize_title', $clean, $str, $keep_percent_sign, $force_lowercase, $sanitize_slugs );
	}

	/**
	 * Replace empty placeholder tags & remove BOM
	 *
	 * @param string $default_uri
	 * @param string $native_slug
	 * @param string $element
	 * @param string $slug
	 * @param bool $native_uri
	 *
	 * @return string
	 */
	public static function replace_empty_placeholder_tags( $default_uri, $native_slug = "", $element = "", $slug = "", $native_uri = false ) {
		// Remove the BOM
		$default_uri = str_replace( array( "\xEF\xBB\xBF", "%ef%bb%bf" ), '', $default_uri );

		// Encode the URI before placeholders are removed
		$chunks = explode( '/', $default_uri );
		foreach ( $chunks as &$chunk ) {
			if ( ! preg_match( "/^(%.+?%)$/", $chunk ) && preg_match( '/%[A-F0-9]{2}%[A-F0-9]{2}/i', $chunk ) ) {
				$chunk = rawurldecode( $chunk );
			}
		}
		$default_uri = implode( "/", $chunks );

		$empty_tag_replacement = apply_filters( 'permalink_manager_empty_tag_replacement', '', $element );
		$default_uri           = preg_replace( "/%(.+?)%/", $empty_tag_replacement, $default_uri );
		$default_uri           = str_replace( "//", "/", $default_uri );

		return trim( $default_uri, "/" );
	}

	/**
	 * Sanitize the final custom permalink URI
	 *
	 * @param string $uri
	 *
	 * @return string
	 */
	public static function clear_single_uri( $uri ) {
		return self::sanitize_title( $uri, true );
	}

	/**
	 * Remove all slashes from given string
	 *
	 * @param string $uri
	 *
	 * @return array|string|string[]|null
	 */
	public static function remove_slashes( $uri ) {
		return preg_replace( "/[\/]+/", "", $uri );
	}

	/**
	 * Replace the given slug with the actual title or custom permalink of specific post or term
	 *
	 * @param string $slug
	 * @param WP_Post|WP_Term $object
	 * @param bool $flat
	 *
	 * @return string
	 */
	public static function force_custom_slugs( $slug, $object, $flat = false, $force_custom_slugs = null ) {
		global $permalink_manager_options;

		if ( empty( $force_custom_slugs ) ) {
			$force_custom_slugs = ( ! empty( $permalink_manager_options['general']['force_custom_slugs'] ) ) ? $permalink_manager_options['general']['force_custom_slugs'] : false;
			$force_custom_slugs = apply_filters( 'permalink_manager_force_custom_slugs', $force_custom_slugs, $slug, $object );
		}

		if ( $force_custom_slugs ) {
			// A. Custom slug (title)
			if ( $force_custom_slugs == 1 ) {
				if ( ! empty( $object->name ) && ! empty( $object->taxonomy ) ) {
					$title = $object->name;
				} else if ( ! empty( $object->post_title ) && ! empty( $object->post_type ) ) {
					$title = $object->post_title;
				} else {
					return $slug;
				}

				$title = strip_tags( $title );
				$title = self::remove_slashes( $title );

				$new_slug = self::sanitize_title( $title );
			} // B. Custom slug (custom permalink)
			else {
				$new_slug = basename( Permalink_Manager_URI_Functions::get_single_uri( $object, false, true ) );
			}

			$slug = ( ! empty( $new_slug ) ) ? preg_replace( '/([^\/]+)$/', $new_slug, $slug ) : $slug;
		}

		if ( $flat ) {
			$slug = preg_replace( "/([^\/]+)(.*)/", "$1", $slug );
		}

		return $slug;
	}

	/**
	 * Reload the globals when the blog is switched (multisite)
	 *
	 * @param int $new_blog_id
	 */
	public function reload_globals_in_network( $new_blog_id ) {
		global $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_external_redirects;

		if ( function_exists( 'get_blog_option' ) ) {
			$permalink_manager_uris               = get_blog_option( $new_blog_id, 'permalink-manager-uris', array() );
			$permalink_manager_redirects          = get_blog_option( $new_blog_id, 'permalink-manager-redirects', array() );
			$permalink_manager_external_redirects = get_blog_option( $new_blog_id, 'permalink-manager-external-redirects', array() );
		}
	}

}
