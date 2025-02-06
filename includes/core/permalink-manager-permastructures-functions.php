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