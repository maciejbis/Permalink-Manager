<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API support for Permalink Manager.
 *
 * Exposes a single "permalink-manager" field on every REST-enabled post type and taxonomy so that custom permalinks (URIs), extra/standard redirects,
 * external redirects and the permastructure format can be read and edited from the Block (Gutenberg) editor
 */
class Permalink_Manager_REST_API {

	/**
	 * The REST field / meta box key. Kept identical to the metabox id used
	 * elsewhere so the Gutenberg panel and the field share one namespace.
	 */
	const FIELD = 'permalink_manager';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ), 20 );
	}

	/**
	 * Detect whether the REST request originates from the WordPress admin dashboard (an "internal" request) rather than an external API client.
	 *
	 * @return bool
	 */
	public static function is_internal_request() {
		$nonce = '';

		if ( ! empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		return ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) ? true : false;
	}

	/**
	 * In Lite, only internal requests pass.
	 *
	 * @return bool
	 */
	public static function access_permitted() {
		return ( ( defined( 'PERMALINK_MANAGER_PRO' ) && PERMALINK_MANAGER_PRO ) || self::is_internal_request() );
	}

	/**
	 * Capability check for reading/writing the field of a single element.
	 *
	 * @param int $object_id Post or term ID.
	 * @param bool $is_term
	 *
	 * @return bool
	 */
	public static function current_user_can_manage( $object_id, $is_term = false ) {
		// Respect the plugin-wide "who can edit URIs" capability setting.
		if ( class_exists( 'Permalink_Manager_Admin_Functions' ) && ! Permalink_Manager_Admin_Functions::current_user_can_edit_uris() ) {
			return false;
		}

		if ( $is_term ) {
			$term = get_term( $object_id );
			if ( empty( $term ) || is_wp_error( $term ) ) {
				return false;
			}

			$tax = get_taxonomy( $term->taxonomy );
			$cap = ( ! empty( $tax->cap->edit_terms ) ) ? $tax->cap->edit_terms : 'manage_categories';

			return current_user_can( $cap, $object_id );
		}

		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Normalize the REST object (post array or term array) into the identifiers used across Permalink Manager.
	 *
	 * @param array $object Prepared REST response array for a post or term.
	 *
	 * @return array|null [ object_id, is_term, array_index, type_name, group ] or null.
	 */
	protected static function resolve_element( $object ) {
		if ( empty( $object['id'] ) ) {
			return null;
		}

		$object_id = absint( $object['id'] );

		if ( ! empty( $object['taxonomy'] ) ) {
			return array(
				'object_id'   => $object_id,
				'is_term'     => true,
				'array_index' => "tax-{$object_id}",
				'type_name'   => $object['taxonomy'],
				'group'       => 'taxonomies',
			);
		}

		$post_type = ( ! empty( $object['type'] ) ) ? $object['type'] : get_post_type( $object_id );

		return array(
			'object_id'   => $object_id,
			'is_term'     => false,
			'array_index' => $object_id,
			'type_name'   => $post_type,
			'group'       => 'post_types',
		);
	}

	/**
	 * Get the permastructure format (string) for a post type / taxonomy.
	 *
	 * @param string $type_name
	 * @param WP_Post|WP_Term $element
	 * @param bool $is_term
	 *
	 * @return string
	 */
	public static function get_permastructure_string( $type_name, $element, $is_term = false ) {
		if ( class_exists( 'Permalink_Manager_Permastructure_Functions' ) ) {
			$permastructure = Permalink_Manager_Permastructure_Functions::get_permastructure( $type_name, $is_term );
		} else {
			$permastructure = '';
		}

		return apply_filters( 'permalink_manager_filter_permastructure', $permastructure, $element );
	}

	/**
	 * Get the "auto-update custom permalink" setting for an element.
	 *
	 * Mirrors the per-element meta used by the classic URI Editor
	 * (get_post_meta()/get_term_meta( $id, 'auto_update_uri', true )). Returns an
	 * integer digit representing the update state.
	 *
	 * @param int $object_id
	 * @param bool $is_term
	 *
	 * @return int
	 */
	public static function get_auto_update( $object_id, $is_term = false ) {
		$value = ( $is_term ) ? get_term_meta( $object_id, 'auto_update_uri', true ) : get_post_meta( $object_id, 'auto_update_uri', true );

		return ( $value !== '' && $value !== false ) ? (int) $value : 0;
	}

	/**
	 * ---------------------------------------------------------------------
	 * Field registration
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register the "permalink-manager" REST field on every eligible post type
	 * and taxonomy.
	 */
	public function register_rest_fields() {
		$args = array(
			'get_callback'    => array( $this, 'get_field' ),
			'update_callback' => array( $this, 'update_field' ),
			'schema'          => $this->get_field_schema(),
		);

		// Post types (only those exposed to the REST API & not disabled by the plugin)
		foreach ( $this->get_supported_post_types() as $post_type ) {
			register_rest_field( $post_type, self::FIELD, $args );
		}

		// Taxonomies
		foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
			register_rest_field( $taxonomy, self::FIELD, $args );
		}
	}

	/**
	 * List of post types that should receive the field.
	 *
	 * @return array
	 */
	protected function get_supported_post_types() {
		$rest_post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		if ( class_exists( 'Permalink_Manager_Helper_Functions' ) ) {
			$enabled         = array_keys( (array) Permalink_Manager_Helper_Functions::get_post_types_array() );
			$rest_post_types = array_intersect( $rest_post_types, $enabled );
		}

		return apply_filters( 'permalink_manager_rest_post_types', array_values( $rest_post_types ) );
	}

	/**
	 * List of taxonomies that should receive the field.
	 *
	 * @return array
	 */
	protected function get_supported_taxonomies() {
		$rest_taxonomies = get_taxonomies( array( 'show_in_rest' => true ), 'names' );

		if ( class_exists( 'Permalink_Manager_Helper_Functions' ) ) {
			$enabled         = array_keys( (array) Permalink_Manager_Helper_Functions::get_taxonomies_array() );
			$rest_taxonomies = array_intersect( $rest_taxonomies, $enabled );
		}

		return apply_filters( 'permalink_manager_rest_taxonomies', array_values( $rest_taxonomies ) );
	}

	/**
	 * The JSON schema describing the field (used by the REST index & clients).
	 *
	 * @return array
	 */
	protected function get_field_schema() {
		return array(
			'description' => __( 'Permalink Manager custom permalink, redirects and permastructure data.', 'permalink-manager' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit' ),
			'properties'  => array(
				'custom-permalink'  => array(
					'description' => __( 'Custom permalink assigned to this element.', 'permalink-manager' ),
					'type'        => 'string',
				),
				'redirects'         => array(
					'description' => __( 'List of extra/standard redirects pointing to this element.', 'permalink-manager' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
				),
				'external_redirect' => array(
					'description' => __( 'External URL this element should redirect to.', 'permalink-manager' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'permastructure'    => array(
					'description' => __( 'Permastructure format applied to this element\'s post type / taxonomy.', 'permalink-manager' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'auto_update'       => array(
					'description' => __( 'Auto-update URI setting (-2, -1, 0, 1, 2) assigned to this element.', 'permalink-manager' ),
					'type'        => 'integer',
					'enum'        => array( - 2, - 1, 0, 1, 2 ),
				),
			),
		);
	}

	/**
	 * ---------------------------------------------------------------------
	 * Field callbacks
	 * ---------------------------------------------------------------------
	 */

	/**
	 * GET callback - assemble the "permalink-manager" object for a post/term.
	 *
	 * @param array $object Prepared post/term array.
	 * @param string $field_name
	 * @param WP_REST_Request $request
	 *
	 * @return array|null
	 */
	public function get_field( $object, $field_name, $request ) {
		global $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_external_redirects;

		$element = self::resolve_element( $object );
		if ( is_null( $element ) ) {
			return null;
		}

		// Access gate: in Lite, external queries are blocked; capability always required.
		if ( ! self::access_permitted() || ! self::current_user_can_manage( $element['object_id'], $element['is_term'] ) ) {
			return null;
		}

		$index = $element['array_index'];

		$uri       = ( ! empty( $permalink_manager_uris[ $index ] ) ) ? $permalink_manager_uris[ $index ] : '';
		$redirects = ( ! empty( $permalink_manager_redirects[ $index ] ) && is_array( $permalink_manager_redirects[ $index ] ) ) ? array_values( array_filter( $permalink_manager_redirects[ $index ] ) ) : array();
		$external  = ( ! empty( $permalink_manager_external_redirects[ $index ] ) ) ? $permalink_manager_external_redirects[ $index ] : '';

		// Fetch the WP_Post or WP_Term object
		$wp_object = $element['is_term'] ? get_term( $element['object_id'] ) : get_post( $element['object_id'] );

		$data = array(
			'custom_permalink'  => $uri,
			'redirects'         => $redirects,
			'external_redirect' => $external,
			'permastructure'    => self::get_permastructure_string( $element['type_name'], $wp_object, $element['is_term'] ),
			'auto_update'       => self::get_auto_update( $element['object_id'], $element['is_term'] ),
		);

		return apply_filters( 'permalink_manager_rest_get_field', $data, $element, $request );
	}

	/**
	 * UPDATE callback - persist submitted values.
	 *
	 * @param mixed $value Submitted "permalink-manager" object.
	 * @param object $object WP_Post or WP_Term being saved.
	 * @param string $field_name
	 *
	 * @return bool|WP_Error
	 */
	public function update_field( $value, $object, $field_name ) {
		// Normalize $object (WP_Post|WP_Term) into a lightweight array for resolve_element().
		if ( isset( $object->term_id ) ) {
			$object_array = array( 'id' => $object->term_id, 'taxonomy' => $object->taxonomy );
		} elseif ( isset( $object->ID ) ) {
			$object_array = array( 'id' => $object->ID, 'type' => $object->post_type );
		} else {
			return new WP_Error( 'permalink_manager_rest_invalid_object', __( 'Invalid object supplied.', 'permalink-manager' ), array( 'status' => 400 ) );
		}

		$element = self::resolve_element( $object_array );
		if ( is_null( $element ) ) {
			return new WP_Error( 'permalink_manager_rest_invalid_object', __( 'Invalid object supplied.', 'permalink-manager' ), array( 'status' => 400 ) );
		}

		// Access gate: Lite blocks external writes; capability always required.
		if ( ! self::access_permitted() ) {
			return new WP_Error( 'permalink_manager_rest_forbidden', __( 'External REST access to Permalink Manager fields is available only in the Pro version.', 'permalink-manager' ), array( 'status' => 403 ) );
		}

		if ( ! self::current_user_can_manage( $element['object_id'], $element['is_term'] ) ) {
			return new WP_Error( 'permalink_manager_rest_cannot_edit', __( 'You are not allowed to edit Permalink Manager data for this element.', 'permalink-manager' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( ! is_array( $value ) ) {
			return true;
		}

		// 1. Custom permalink (URI)
		if ( array_key_exists( 'custom_permalink', $value ) ) {
			$this->save_uri( $element, $value['custom_permalink'] );
		}

		// 2. Extra / standard redirects (array)
		if ( array_key_exists( 'redirects', $value ) ) {
			$this->save_redirects( $element, $value['redirects'] );
		}

		// 3. External redirect (single URL)
		if ( array_key_exists( 'external_redirect', $value ) ) {
			$external_url = $value['external_redirect'];

			// Validate the external URL if it is not empty
			if ( ! empty( $external_url ) && filter_var( $external_url, FILTER_VALIDATE_URL ) === false ) {
				return new WP_Error( 'permalink_manager_rest_invalid_url', __( 'The provided external redirect URL is invalid.', 'permalink-manager' ), array( 'status' => 400 ) );
			}

			$this->save_external_redirect( $element, $external_url );
		}

		// 4. Auto-update flag (per-element meta, maps to digits -2, -1, 0, 1, 2)
		if ( array_key_exists( 'auto_update', $value ) ) {
			$this->save_auto_update( $element, $value['auto_update'] );
		}

		do_action( 'permalink_manager_rest_updated_field', $element, $value );

		return true;
	}

	/**
	 * Save (or clear) the custom permalink for the element.
	 *
	 * @param array $element
	 * @param string $uri
	 */
	protected function save_uri( $element, $uri ) {
		if ( ! class_exists( 'Permalink_Manager_URI_Functions' ) ) {
			return;
		}

		$uri = is_string( $uri ) ? trim( $uri ) : '';

		if ( $uri === '' && ! empty( $element['object_id'] ) ) {
			if ( empty( $element['is_term'] ) ) {
				$new_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri( $element['object_id'] );
			} else if ( class_exists( 'Permalink_Manager_URI_Functions_Tax' ) ) {
				$new_uri = Permalink_Manager_URI_Functions_Tax::get_default_term_uri( $element['object_id'] );
			} else {
				return;
			}

			Permalink_Manager_URI_Functions::save_single_uri( $element['array_index'], $new_uri, $element['is_term'], true );
		} else {
			Permalink_Manager_URI_Functions::save_single_uri( $element['array_index'], $uri, $element['is_term'], true );
		}
	}

	/**
	 * Save the array of extra/standard redirects for the element.
	 *
	 * @param array $element
	 * @param array $redirects
	 */
	protected function save_redirects( $element, $redirects ) {
		global $permalink_manager_redirects;

		if ( ! is_array( $permalink_manager_redirects ) ) {
			$permalink_manager_redirects = array();
		}

		$index = $element['array_index'];

		if ( ! is_array( $redirects ) ) {
			$redirects = array();
		}

		// Sanitize each redirect URI the same way the classic handler does.
		$clean = array();
		foreach ( $redirects as $redirect ) {
			if ( ! is_string( $redirect ) ) {
				continue;
			}

			$redirect = rawurldecode( $redirect );

			if ( class_exists( 'Permalink_Manager_Helper_Functions' ) ) {
				$redirect = Permalink_Manager_Helper_Functions::sanitize_title( $redirect, true );
			} else {
				$redirect = trim( $redirect, '/' );
			}

			if ( $redirect !== '' ) {
				$clean[] = $redirect;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( empty( $clean ) ) {
			unset( $permalink_manager_redirects[ $index ] );
		} else {
			$permalink_manager_redirects[ $index ] = $clean;
		}

		// Drop any duplicate that matches the element's current URI.
		if ( class_exists( 'Permalink_Manager_Actions' ) && method_exists( 'Permalink_Manager_Actions', 'clear_single_element_duplicated_redirect' ) ) {
			$current_uri = ( ! empty( $clean ) ) ? Permalink_Manager_URI_Functions::get_single_uri( $index, false, true, $element['is_term'] ) : '';
			Permalink_Manager_Actions::clear_single_element_duplicated_redirect( $index, false, $current_uri );
		}

		update_option( 'permalink-manager-redirects', array_filter( $permalink_manager_redirects ) );
	}

	/**
	 * Save (or clear) the external redirect URL for the element.
	 *
	 * @param array $element
	 * @param string $url
	 */
	protected function save_external_redirect( $element, $url ) {
		global $permalink_manager_external_redirects;

		if ( ! is_array( $permalink_manager_external_redirects ) ) {
			$permalink_manager_external_redirects = array();
		}

		$index = $element['array_index'];

		if ( empty( $url ) ) {
			unset( $permalink_manager_external_redirects[ $index ] );
		} else {
			$permalink_manager_external_redirects[ $index ] = esc_url_raw( $url );
		}

		update_option( 'permalink-manager-external-redirects', array_filter( $permalink_manager_external_redirects ) );
	}

	/**
	 * Save (or clear) the per-element "auto-update custom permalink" flag.
	 *
	 * 0  -> remove the meta so the element inherits the global default
	 * Otherwise -> store the provided valid digit
	 *
	 * @param array $element
	 * @param int $state
	 */
	protected function save_auto_update( $element, $state ) {
		$state = (int) $state;

		if ( $element['is_term'] ) {
			if ( $state === 0 ) {
				delete_term_meta( $element['object_id'], 'auto_update_uri' );
			} else {
				update_term_meta( $element['object_id'], 'auto_update_uri', $state );
			}
		} else {
			if ( $state === 0 ) {
				delete_post_meta( $element['object_id'], 'auto_update_uri' );
			} else {
				update_post_meta( $element['object_id'], 'auto_update_uri', $state );
			}
		}
	}
}