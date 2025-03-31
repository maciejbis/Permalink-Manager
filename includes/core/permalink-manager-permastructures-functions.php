<?php

/**
 * Helper functions used for Permastructures (permalink formats)
 */
class Permalink_Manager_Permastructure_Functions {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 5 );
	}

	/**
	 * Add hooks used by plugin to filter the custom permalinks
	 */
	function init() {
		// Replace empty placeholder tags & remove BOM
		add_filter( 'permalink_manager_filter_default_post_uri', array( $this, 'replace_empty_placeholder_tags' ), 10, 5 );
		add_filter( 'permalink_manager_filter_default_term_uri', array( $this, 'replace_empty_placeholder_tags' ), 10, 5 );
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
	 * Check if any of slug tags is present inside Permastructure settings
	 *
	 * @param $default_uri
	 * @param $slug_tags
	 * @param $content_element
	 *
	 * @return bool|null
	 */
	public static function is_slug_tag_present( $default_uri, $slug_tags, $content_element ) {
		global $permalink_manager_options;

		// Check if any post tag is present in custom permastructure
		if ( ! empty( $content_element->post_type ) ) {
			$content_type     = $content_element->post_type;
			$content_type_key = 'post_types';
		} else if ( ! empty( $content_element->taxonomy ) ) {
			$content_type     = $content_element->taxonomy;
			$content_type_key = 'taxonomies';
		} else {
			return null;
		}

		$permastructure_settings = ( ! empty( $permalink_manager_options['permastructure-settings'] ) ) ? $permalink_manager_options['permastructure-settings'] : array();
		$do_not_append_settings  = ( ! empty( $permastructure_settings['do_not_append_slug'] ) ) ? $permastructure_settings['do_not_append_slug'] : array();
		$do_not_append_slug      = ( ! empty( $do_not_append_settings[ $content_type_key ] ) && ! empty( $do_not_append_settings[ $content_type_key ][ $content_type ] ) ) ? true : false;
		$do_not_append_slug      = apply_filters( "permalink_manager_do_not_append_slug", $do_not_append_slug, $content_type, $content_element );

		if ( ! $do_not_append_slug ) {
			foreach ( $slug_tags as $tag ) {
				if ( strpos( $default_uri, $tag ) !== false ) {
					$do_not_append_slug = true;
					break;
				}
			}
		}

		// 3F. Replace the post tags with slugs or append the slug if no post tag is defined
		if ( ! empty( $do_not_append_slug ) ) {
			return true;
		} else {
			return false;
		}
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
}