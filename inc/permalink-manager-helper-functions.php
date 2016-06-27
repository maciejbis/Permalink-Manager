<?php

/**
 * Additional functions used in classes and another subclasses
 */

class Permalink_Manager_Helper_Functions extends Permalink_Manager_Class {

  public function __construct() {}

	/**
	 * Display error/info message
	 */
	static function display_alert($alert_content, $alert_type, $before_tabs = false) {
    $output = sprintf( "<div class='{$alert_type} is-dismissible notice'><p> %s </p></div>", $alert_content );

    if($before_tabs) {
      add_filter('permalink-manager-before-tabs', function( $arg ) use ( $output ) {
				return $output;
			});
    } else {
		  return $output;
    }
	}

	/**
	 * Get post_types array
	 */
	static function get_post_types_array($format = null, $cpt = null) {
    $post_types = get_post_types( array('public' => true), 'objects' );

    $post_types_array = array();
    if($format == 'full') {
      foreach ( $post_types as $post_type ) {
  			$post_types_array[$post_type->name] = array('label' => $post_type->labels->name, 'name' => $post_type->name);
      }
    } else {
      foreach ( $post_types as $post_type ) {
  			$post_types_array[$post_type->name] = $post_type->labels->name;
      }
    }

    return (empty($cpt)) ? $post_types_array : $post_types_array[$cpt];
  }

	/**
   * Generate the fields
   */
	static public function generate_option_field($name, $args, $group = null) {
		// Load values from options if needed
		$saved_values = get_option('permalink-manager');

		// Reset $fields variable
		$fields = '';

		// Load default value
		$default_value = (isset($args['default'])) ? $args['default'] : '';
		$label = (isset($args['label'])) ? $args['label'] : '';
		$placeholder = (isset($args['placeholder'])) ? "placeholder=\"{$args['placeholder']}\"" : '';
		$input_class = (isset($args['input_class'])) ? "class=\"{$args['input_class']}\"" : '';
		$container_class = (isset($args['container_class'])) ? " class=\"{$args['container_class']} field-container\"" : " class=\"field-container\"";
    $input_name = ($group) ? "permalink-manager[{$group}][{$name}]" : $name;
    $desc = (isset($args['desc'])) ? "<p class=\"field-desc\">{$args['desc']}</p>" : "";

    switch($args['type']) {
			case 'checkbox' :
				$fields .= '<div class="checkboxes">';
				foreach($args['choices'] as $value => $checkbox_label) {
					$all_checked = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $args['default'];
					$checked = in_array($value, $all_checked) ? "checked='checked'" : "";
					$fields .= "<label for='{$input_name}[]'><input type='checkbox' {$input_class} value='{$value}' name='{$input_name}[]' {$checked} /> {$checkbox_label}</label>";
				}
				$fields .= '</div>';
				break;

      case 'radio' :
				$fields .= '<div class="radios">';
				foreach($args['choices'] as $value => $checkbox_label) {
					$all_checked = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $args['default'];
					$checked = in_array($value, $all_checked) ? "checked='checked'" : "";
					$fields .= "<label for='{$input_name}[]'><input type='radio' {$input_class} value='{$value}' name='{$input_name}[]' {$checked} /> {$checkbox_label}</label>";
				}
				$fields .= '</div>';
				break;

			case 'number' :
        $value = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $default_value;
      	$fields .= "<input type='number' {$input_class} value='{$value}' name='{$input_name}' />";
				break;

			case 'clearfix' :
      	return "<div class=\"clearfix\"></div>";

      default :
        $value = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $default_value;
        $fields .= "<input type='text' {$input_class} value='{$value}' name='{$input_name}' {$placeholder}/>";
		}

		// Get all variables into one final variable
		if(isset($group) && (in_array($group, array('regenerate_slugs', 'find-replace')))) {
			$output = "<div{$container_class}>";
			$output .= "<h4>{$label}</h4>";
			$output .= "<div class='metabox-prefs'><div class='{$name}-container'>{$fields}</div></div>";
			$output .= $desc;
			$output .= "</div>";
		} else if (isset($args['without_label']) && $args['without_label'] == true) {
      $output = $fields;
    } else {
			$output = "<tr><th><label for='{$input_name}'>{$args['label']}</label></th>";
			$output .= "<td>{$fields}</td>";
		}

    return $output;
  }

  /**
   * Display the permalink in a better way
   */
  static function get_correct_permalink($id) {
    $old_uris = get_option('permalink-manager-uris');
    $permalink = isset($old_uris[$id]) ? home_url('/') . $old_uris[$id] : get_permalink($id);

  	return $permalink;
  }

  /**
   * Check if the provided slug is unique and then update it with SQL query.
   */
  static function update_slug_by_id($slug, $id) {
    global $wpdb;

    // Update slug and make it unique
    $slug = (empty($slug)) ? sanitize_title(get_the_title($id)) : $slug;
    $new_slug = wp_unique_post_slug($slug, $id, get_post_status($id), get_post_type($id), null);
    $wpdb->query("UPDATE $wpdb->posts SET post_name = '$new_slug' WHERE ID = '$id'");

    return $new_slug;
  }

  /**
   * Get permastruct
   */
   static function get_default_permastruct($post_type = 'page', $remove_post_tag = false) {
     global $wp_rewrite;

     // Get default permastruct
     if($post_type == 'page') {
       $permastruct = $wp_rewrite->get_page_permastruct();
     } else if($post_type == 'post') {
       $permastruct = get_option('permalink_structure');
     } else {
       $permastruct = $wp_rewrite->get_extra_permastruct($post_type);
     }

     return ($remove_post_tag) ? trim(str_replace(array("%postname%", "%pagename%", "%{$post_type}%"), "", $permastruct), "/") : $permastruct;
  }

  static function get_uri($post_id, $get_default = false, $remove_slug = true) {
    // Load all bases & post
		$all_uris = get_option('permalink-manager-uris');
    $all_permastructures = get_option('permalink-manager-permastructs');
    $options = get_option('permalink-manager');

    $post = isset($post_id->post_type) ? $post_id : get_post($post_id);
    $post_id = $post->ID;
    $post_type = $post->post_type;
    $post_name = $post->post_name;

    if($get_default) {

      if($all_permastructures) {
        $permastruct = isset($all_permastructures[$post_type]) ? $all_permastructures[$post_type] : Permalink_Manager_Helper_Functions::get_default_permastruct($post_type);
      } else {
        $permastruct = isset($options['base-editor'][$post_type]) ? $options['base-editor'][$post_type] : Permalink_Manager_Helper_Functions::get_default_permastruct($post_type);
      }

      // Get options
      if($permastruct) {
        $default_base = trim($permastruct, '/');
      }

      // Get the date
      $date = explode(" ",date('Y m d H i s', strtotime($post->post_date)));

      // Get the category (if needed)
      $category = '';
      if ( strpos($default_base, '%category%') !== false ) {
        $cats = get_the_category($post->ID);
        if ( $cats ) {
          usort($cats, '_usort_terms_by_ID'); // order by ID
          $category_object = apply_filters( 'post_link_category', $cats[0], $cats, $post );
          $category_object = get_term( $category_object, 'category' );
          $category = $category_object->slug;
          if ( $parent = $category_object->parent )
            $category = get_category_parents($parent, false, '/', true) . $category;
        }
        // show default category in permalinks, without having to assign it explicitly
        if ( empty($category) ) {
          $default_category = get_term( get_option( 'default_category' ), 'category' );
          $category = is_wp_error( $default_category ) ? '' : $default_category->slug;
        }
      }

      // Get the author (if needed)
      $author = '';
      if ( strpos($default_base, '%author%') !== false ) {
        $authordata = get_userdata($post->post_author);
        $author = $authordata->user_nicename;
      }

      // Fix for hierarchical CPT (start)
      $full_slug = get_page_uri($post);
      $post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);

      // Do the replacement (post tag is removed now to enable support for hierarchical CPT)
      $tags = array('%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%category%', '%author%', $post_type_tag);
      $replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $category, $author, '');
      $default_uri = str_replace($tags, $replacements, "{$default_base}/{$full_slug}");

      // Replace custom taxonomies
      $terms = get_taxonomies( array('public' => true, '_builtin' => false), 'names', 'and' );
      $taxonomies = $terms;
      if ( $taxonomies ) {
        foreach($taxonomies as $taxonomy) {
          $tag = "%{$taxonomy}%";
          $terms = wp_get_object_terms($post->ID, $taxonomy);
          if (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) {
            $replacement = $terms[0]->slug;
            $default_uri = str_replace($tag, $replacement, $default_uri);
          }
        }
      }
    } else {
      $default_uri = str_replace(home_url("/"), "", get_permalink($post_id));
    }

    $uri = isset($all_uris[$post_id]) ? $all_uris[$post_id] : $default_uri;
    // Remove post_name from base (last part of string)
    $uri = ($remove_slug) ? str_replace($post_name, '', $uri) : $uri;

    $final_uri = ($get_default) ? $default_uri : $uri;

    // Clean URI
    $final_uri = preg_replace('/\s+/', '', $final_uri);
    $final_uri = str_replace('//', '/', $final_uri);
    $final_uri = trim($final_uri, "/");

    return $final_uri;
  }

  /**
   * Structure Tags & Rewrite functions
   */
  static function get_all_structure_tags($code = true, $seperator = ', ', $hide_slug_tags = true) {
    global $wp_rewrite;

    $tags = $wp_rewrite->rewritecode;
    $output = "";
    $last_tag_index = count($tags);
    $i = 1;

    // Hide slug tags
    if($hide_slug_tags) {
      $post_types = Permalink_Manager_Helper_Functions::get_post_types_array();
      foreach($post_types as $post_type => $post_type_name) {
        $post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);
        // Find key with post type tag from rewritecode
        $key = array_search($post_type_tag, $tags);
        if($key) { unset($tags[$key]); }
      }
    }

    foreach($tags as $tag) {
      $sep = ($last_tag_index == $i) ? "" : $seperator;
      $output .= ($code) ? "<code>{$tag}</code>{$sep}" : "{$tag}{$sep}";
      $i++;
    }

    return $output;
  }

  static function get_post_tag($post_type) {
    // Get the post type (with fix for posts & pages)
		if($post_type == 'page') {
			$post_type_tag = '%pagename%';
		} else if ($post_type == 'post') {
			$post_type_tag = '%postname%';
		} else {
			$post_type_tag = "%{$post_type}%";
		}
    return $post_type_tag;
  }

}
