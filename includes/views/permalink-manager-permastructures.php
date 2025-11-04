<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class Permalink_Manager_Permastructs {

	public function __construct() {
		add_filter( 'permalink_manager_sections', array( $this, 'add_admin_section' ), 2 );
	}

	/**
	 * Add a new section to the Permalink Manager UI
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_admin_section( $admin_sections ) {
		$admin_sections['permastructs'] = array(
			'name'     => __( 'Permastructures', 'permalink-manager' ),
			'function' => array( 'class' => 'Permalink_Manager_Permastructs', 'method' => 'output' )
		);

		return $admin_sections;
	}

	/**
	 * Return an array of fields that will be used to adjust the permastructure settings
	 *
	 * @return array
	 */
	public function get_fields() {
		$post_types = Permalink_Manager_Helper_Functions::get_post_types_array( 'full' );
		$taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array( 'full' );

		// Display additional information in Permalink Manager Lite
		if ( ! Permalink_Manager_Admin_Functions::is_pro_active() ) {
			/* translators: %s: Permalink Manager Pro website */
			$pro_text = sprintf( __( 'To edit taxonomy permalinks, <a href="%s" target="_blank">Permalink Manager Pro</a> is required.', 'permalink-manager' ), PERMALINK_MANAGER_PROMO );
			$pro_text = sprintf( '<div class="alert info">%s</div>', $pro_text );
		}

		// 1. Get fields
		$fields = array(
			'post_types' => array(
				'section_name' => __( 'Post types', 'permalink-manager' ),
				'container'    => 'row',
				'fields'       => array()
			),
			'taxonomies' => array(
				'section_name'   => __( 'Taxonomies', 'permalink-manager' ),
				'container'      => 'row',
				'fields'         => array(),
				'append_content' => ( ! empty( $pro_text ) ) ? $pro_text : ''
			)
		);

		// 2. Add a separate section for WooCommerce content types
		if ( class_exists( 'WooCommerce' ) ) {
			$fields['woocommerce'] = array(
				'section_name'   => "<i class=\"woocommerce-icon woocommerce-cart\"></i> " . __( 'WooCommerce', 'permalink-manager' ),
				'container'      => 'row',
				'fields'         => array(),
				'append_content' => ( ! empty( $pro_text ) ) ? $pro_text : ''
			);
		}

		// 3A. Add permastructure fields for post types
		foreach ( $post_types as $post_type ) {
			if ( $post_type['name'] == 'shop_coupon' ) {
				continue;
			}

			$fields["post_types"]["fields"][ $post_type['name'] ] = self::get_single_permastructure_field( $post_type, false, false );
		}

		// 3B. Add permastructure fields for taxonomies
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_name = $taxonomy['name'];

			// Check if taxonomy exists
			if ( ! taxonomy_exists( $taxonomy_name ) ) {
				continue;
			}

			$fields["taxonomies"]["fields"][ $taxonomy_name ] = self::get_single_permastructure_field( $taxonomy, true, isset( $pro_text ) );
		}

		// 4. Separate WooCommerce CPT & custom taxonomies
		if ( class_exists( 'WooCommerce' ) ) {
			$woocommerce_fields     = array( 'product' => 'post_types', 'product_tag' => 'taxonomies', 'product_cat' => 'taxonomies' );
			$woocommerce_attributes = wc_get_attribute_taxonomies();

			foreach ( $woocommerce_attributes as $woocommerce_attribute ) {
				$woocommerce_fields["pa_{$woocommerce_attribute->attribute_name}"] = 'taxonomies';
			}

			foreach ( $woocommerce_fields as $field => $field_type ) {
				if ( empty( $fields[ $field_type ]["fields"][ $field ] ) ) {
					continue;
				}

				$fields["woocommerce"]["fields"][ $field ] = $fields[ $field_type ]["fields"][ $field ];
				unset( $fields[ $field_type ]["fields"][ $field ] );
			}
		}

		return apply_filters( 'permalink_manager_permastructs_fields', $fields );
	}

	/**
	 * Get the row of the permastructure field for single content type
	 *
	 * @param $content_type
	 * @param bool $is_tax
	 * @param bool $pro_alert
	 *
	 * @return string
	 */
	function get_single_permastructure_field( $content_type, $is_tax = false, $pro_alert = false ) {
		global $permalink_manager_permastructs;

		if ( empty( $content_type['name'] ) ) {
			return '';
		}

		$content_group      = ( $is_tax ) ? "taxonomies" : "post_types";
		$content_type_name  = $content_type['name'];
		$content_type_label = $content_type['label'];

		$siteurl           = Permalink_Manager_Permastructure_Functions::get_permalink_base();
		$tags_container_id = sprintf( 'permastruct-tags-%s-%s', $content_group, $content_type_name );
		$available_tags    = self::get_all_structure_tags( $content_type_name, $is_tax );

		// Get permastructures
		$permastructures     = ( ! empty( $permalink_manager_permastructs[ $content_group ] ) ) ? $permalink_manager_permastructs[ $content_group ] : array();
		$default_permastruct = trim( Permalink_Manager_Permastructure_Functions::get_default_permastruct( $content_type_name ), "/" );
		$current_permastruct = isset( $permastructures[ $content_type_name ] ) ? $permastructures[ $content_type_name ] : $default_permastruct;

		// Append extra attributes
		$field_atts = array(
			'value'       => $current_permastruct,
			'input_class' => 'permastruct-field',
			'disabled'    => $pro_alert,
			'placeholder' => $default_permastruct,
			'extra_atts'  => " data-default=\"{$default_permastruct}\""
		);

		$field_name = sprintf( '%s[%s]', $content_group, $content_type_name );
		$permastruct_field = sprintf( "<div class=\"permastruct-field-container\"><span><code>%s/</code></span><span>%s</span></div>", $siteurl, Permalink_Manager_UI_Elements::generate_option_field( $field_name, $field_atts ) );

		$buttons = sprintf( "<p class=\"permastruct-buttons\">
            <span><a href=\"#\" class=\"button button-small button-secondary extra-settings\"><span class=\"dashicons dashicons-admin-settings\"></span> %s</a></span>
            <span><a href=\"/?TB_inline&width=800&height=600&inlineId=%s\" class=\"button button-small button-secondary show-tags thickbox\"><span class=\"dashicons dashicons-tag\"></span> %s</a></span>
        </p>", __( "Extra settings", "permalink-manager" ), $tags_container_id, __( "Available tags", "permalink-manager" ) );

		$language_fields = '';
		$languages       = Permalink_Manager_Language_Plugins::get_all_languages( true );
		if ( $languages ) {
			$language_fields = sprintf( "<h4>%s</h4><p class=\"permastruct-instruction\">%s</p>", __( "Permastructure translations", "permalink-manager" ), __( "If you would like to translate the permastructures and set-up different permalink structure per language, please fill in the fields below. Otherwise the permastructure set for default language (see field above) will be applied.", "permalink-manager" ) );

			foreach ( $languages as $lang => $name ) {
				$current_lang_permastruct = isset( $permastructures["{$content_type_name}_{$lang}"] ) ? $permastructures["{$content_type_name}_{$lang}"] : '';

				$lang_field_atts = array_merge( $field_atts, array( 'value' => $current_lang_permastruct, 'extra_atts' => 'data-default=""', 'placeholder' => $current_permastruct ) );
				$lang_field_name = str_replace( "]", "_{$lang}]", $field_name );

				$language_fields .= sprintf( "<label>%s</label><div class=\"permastruct-field-container\"><span><code>%s/</code></span><span>%s</span></div>", $name, Permalink_Manager_Language_Plugins::prepend_lang_prefix( $siteurl, '', $lang ), Permalink_Manager_UI_Elements::generate_option_field( $lang_field_name, $lang_field_atts ) );
			}
		}

		$default_permastruct_row = sprintf( "<p class=\"default-permastruct-row columns-container\">
            <span class=\"column-2_4\"><strong>%s:</strong> %s</span>
            <span class=\"column-2_4\"><a href=\"#\" class=\"restore-default\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span>
        </p>", __( "Default permastructure", "permalink-manager" ), esc_html( $default_permastruct ), __( "Restore default permastructure", "permalink-manager" ) );

		$permastructure_settings = sprintf( "<h4>%s</h4><div class=\"settings-container\">%s</div>", __( "Permastructure settings", "permalink-manager" ), Permalink_Manager_UI_Elements::generate_option_field( "permastructure-settings[do_not_append_slug][$content_group][{$content_type_name}]", array( 'type' => 'single_checkbox', 'default' => 1, 'checkbox_label' => __( "Do not automatically append the slug", "permalink-manager" ) ) ) );

		// Combine all HTML chunks
		$html = sprintf( "<div class=\"permastruct-container\">%s%s</div>", $permastruct_field, $buttons );
		$html .= sprintf( "<div class=\"permastruct-settings\">%s%s%s</div>", $language_fields, $default_permastruct_row, $permastructure_settings );
		$html .= sprintf( '<div id="%s" style="display:none;">%s</div>', $tags_container_id, $available_tags );

		$label_tag = sprintf( "<th><label for=\"%s\">%s</label></th>", $field_name, $content_type_label );

		return sprintf( "<tr id=\"%s_%s\" data-field=\"%s\" class=\"%s\">%s<td><fieldset>%s</fieldset></td></tr>", esc_attr( $content_group ), esc_attr( $content_type_name ), esc_attr( $field_name ), 'field-container permastruct-row', $label_tag, $html );
	}


	/**
	 * Get the array with settings and render the HTML output
	 */
	public function output() {
		$sidebar = "<div class=\"notice notice-warning inline\"><p>";
		$sidebar .= __( 'The current permastructures settings will be automatically applied <strong>only to the new posts & terms</strong>.', 'permalink-manager' );
		$sidebar .= '<br />';
		/* translators: %s: Regenerate/reset admin URL */
		$sidebar .= sprintf( __( 'To apply the <strong>new format to existing posts and terms</strong>, please use "<a href="%s">Regenerate/reset</a>" tool after you update the permastructure settings below.', 'permalink-manager' ), admin_url( 'tools.php?page=permalink-manager&section=tools&subsection=regenerate_slugs' ) );
		$sidebar .= "</div>";

		return Permalink_Manager_UI_Elements::get_the_form( self::get_fields(), '', array( 'text' => __( 'Save permastructures', 'permalink-manager' ), 'class' => 'primary margin-top' ), $sidebar, array( 'action' => 'permalink-manager', 'name' => 'permalink_manager_permastructs' ) );
	}

	/**
	 * Get a list of all structure tags
	 *
	 * @param string $content_type
	 * @param bool $is_taxonomy
	 *
	 * @return string
	 */
	static function get_all_structure_tags( $content_type = '', $is_taxonomy = false ) {
		if ( empty( $content_type ) ) {
			return '';
		}

		$html = '';

		$tags_groups = array(
			'slug'          => array(
				'heading'     => __( 'Native slug & title', 'permalink-manager' ),
				'description' => __( 'The native slug is generated from the initial title and will not update automatically if the title is changed later. You can use the %native_title% tag to replace native slugs with actual titles, even if another content item has the same title.', 'permalink-manager' )
			),
			'meta'          => array(
				'heading'     => __( 'Meta data', 'permalink-manager' ),
				'description' => __( 'Using meta tags, you may add post-specific information like item IDs or author names to permalinks. This might be beneficial to news, events, and time-sensitive content where you can use date-based tags.', 'permalink-manager' )
			),
			'taxonomies'    => array(
				'heading'     => __( 'Taxonomies', 'permalink-manager' ),
				'description' => __( 'Custom permalinks can include taxonomy-based slugs such as categories, tags, and custom taxonomy terms. If a post belongs to multiple terms, the lowest-level one is used unless a specific term is selected as primary.', 'permalink-manager' )
			),
			'custom_fields' => array(
				'heading'     => __( 'Custom fields', 'permalink-manager' ),
				'description' => __( 'Permalinks can be modified with custom fields to dynamically include extra data. For example, you can append product SKUs to WooCommerce URLs or include geolocation details in your custom post types\' permalinks.', 'permalink-manager' ),
				'pro'         => true
			)
		);

		if ( ! $is_taxonomy ) {
			$post_type_tag        = Permalink_Manager_Permastructure_Functions::get_post_tag( $content_type );
			$post_type_taxonomies = get_taxonomies( array( 'object_type' => array( $content_type ) ), 'objects' );

			$tags_groups['slug']['tags'] = array(
				$post_type_tag,
				( $content_type !== 'post' ) ? '%postname%' : '',
				'%native_title%'
			);

			$tags_groups['meta']['tags'] = array(
				'%post_id%',
				'%author%',
				'%year%',
				'%monthnum%',
				'%monthname%',
				'%day%',
				'%hour%',
				'%minute%',
				'%second%',
				'%post_type%'
			);

			if ( ! empty( $post_type_taxonomies ) ) {
				$tags_groups['taxonomies']['tags'] = array();

				foreach ( $post_type_taxonomies as $post_type_taxonomy ) {
					$tags_groups['taxonomies']['tags'][] = sprintf('%%%s%%', $post_type_taxonomy->name);
				}
			}
		} else {
			$taxonomy_tag = sprintf( '%%%s%%', $content_type );

			$tags_groups['slug']['tags'] = array(
				$taxonomy_tag,
				'%term_name%',
				'%native_title%'
			);

			$tags_groups['meta']['tags'] = array(
				'%term_id%',
				'%taxonomy%'
			);
		}

		$tags_groups['custom_fields']['tags'] = array(
			'%__custom_field_name%'
		);

		foreach ( $tags_groups as $tags_group ) {
			if ( empty( $tags_group['tags'] ) ) {
				continue;
			}

			$html .= sprintf( '<h3>%s</h3>', $tags_group['heading'] );

			if ( ! empty( $tags_group['pro'] ) ) {
				$pro_text = Permalink_Manager_UI_Elements::pro_text( true );
				$html     .= ( ! empty( $pro_text ) ) ? sprintf( '<span class="notice notice-error inline">%s</span>', $pro_text ) : '';
			}

			$html .= '<div class="permastruct-tag-container">';
			$html .= sprintf( '<div><p class="description">%s</p></div>', $tags_group['description'] );

			$html .= '<div><div class="permastruct-tag-buttons">';
			foreach ( $tags_group['tags'] as $tag ) {
				$html .= ( ! empty( $tag ) ) ? sprintf( '<button type="button" class="button button-small button-secondary" data-original-text="%1$s">%1$s</button>', $tag ) : '';
			}
			$html .= '</div></div>';
			$html .= '</div>';
		}

		return sprintf( "<div class=\"structure-tags-list\">%s</div>", $html );
	}

}
