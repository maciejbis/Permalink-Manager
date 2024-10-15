<?php

/**
 * Support for Gutenberg editor
 */
class Permalink_Manager_Gutenberg {

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'init' ) );

		add_action( 'wp_ajax_pm_get_uri_editor', array( $this, 'get_uri_editor' ) );
		add_action( 'wp_ajax_nopriv_pm_get_uri_editor', array( $this, 'get_uri_editor' ) );
	}

	/**
	 * Add URI Editor meta box to Gutenberg editor
	 */
	public function init() {
		global $current_screen, $post;

		// Get displayed post type
		if ( empty( $current_screen->post_type ) || empty( $post->post_type ) ) {
			return;
		}

		// Stop the hook (if needed)
		$show_uri_editor = apply_filters( "permalink_manager_show_uri_editor_post", true, $post, $post->post_type );
		if ( ! $show_uri_editor ) {
			return;
		}

		// Check the user capabilities
		if ( Permalink_Manager_Admin_Functions::current_user_can_edit_uris() === false ) {
			return;
		}

		// Check if the post is excluded
		if ( ! empty( $post->ID ) && Permalink_Manager_Helper_Functions::is_post_excluded( $post ) ) {
			return;
		}

		add_meta_box( 'permalink-manager', __( 'Permalink Manager', 'permalink-manager' ), array( $this, 'get_uri_editor' ), '', 'side', 'high' );

		// wp_enqueue_script( 'permalink-manager-gutenberg', PERMALINK_MANAGER_URL . '/out/permalink-manager-gutenberg.js', array( 'wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-element' ) );
		// wp_enqueue_style( 'permalink-manager-gutenberg', PERMALINK_MANAGER_URL . '/out/permalink-manager-gutenberg.css', array(), PERMALINK_MANAGER_VERSION );
	}

	/**
	 * Display the URI Editor for specific post
	 *
	 * @param WP_Post|int $post
	 */
	public function get_uri_editor( $post = null ) {
		if ( empty( $post->ID ) && empty( $_REQUEST['post_id'] ) ) {
			return;
		} else if ( ! empty( $_REQUEST['post_id'] ) && is_numeric( $_REQUEST['post_id'] ) ) {
			$post = get_post( $_REQUEST['post_id'] );
		}

		// Check if the user can edit this post
		if ( ! empty( $post->ID ) && current_user_can( 'edit_post', $post->ID ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo ( $post ) ? Permalink_Manager_UI_Elements::display_uri_box( $post, true ) : '';
		}

		if ( wp_doing_ajax() ) {
			die();
		}
	}

}