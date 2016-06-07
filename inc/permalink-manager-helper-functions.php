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
	 * Display the permalink in a better way
	 */
	static function get_correct_permalink($id, $correct_slug, $highlight = false) {
		$output = get_permalink($id);

		// Get last part of URI
    $page_uri = explode('/', get_page_uri($id));
		$old_slug = end($page_uri);
		$correct_slug = ($highlight) ? "<code>{$correct_slug}</code>" : $correct_slug;
		$output = Permalink_Manager_Helper_Functions::str_lreplace($old_slug, $correct_slug, $output);

		return $output;
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
	 * Replace last occurence
	 */
	static function str_lreplace($search, $replace, $subject) {
  	$pos = strrpos($subject, $search);
    return ($pos !== false) ? substr_replace($subject, $replace, $pos, strlen($search)) : $subject;
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
    $input_name = ($group) ? "permalink-manager[{$group}][{$name}]" : $name;

    switch($args['type']) {
			case 'checkbox' :
				$fields .= '<div class="checkboxes">';
				foreach($args['choices'] as $value => $label) {
					$all_checked = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $args['default'];
					$checked = in_array($value, $all_checked) ? "checked='checked'" : "";
					$fields .= "<label for='{$input_name}[]'><input type='checkbox' {$input_class} value='{$value}' name='{$input_name}[]' {$checked} /> {$label}</label>";
				}
				$fields .= '</div>';
				break;

			case 'number' :
        $value = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $default_value;
      	$fields .= "<input type='number' {$input_class} value='{$value}' name='{$input_name}' />";
				break;

      default :
        $value = (isset($saved_values[$group][$name])) ? $saved_values[$group][$name] : $default_value;
        $fields .= "<input type='text' {$input_class} value='{$value}' name='{$input_name}' {$placeholder}/>";
		}

		// Get all variables into one final variable
		if(isset($group) && !($group === 'screen-options')) {
			$output = "<legend>{$label}</legend>";
			$output .= "<div class='metabox-prefs'><div class='{$name}-container'>{$fields}</div></div>";
		} else if (isset($args['without_label']) && $args['without_label'] == true) {
      $output = $fields;
    } else {
			$output = "<tr><th><label for='{$input_name}'>{$args['label']}</label></th>";
			$output .= "<td>{$fields}</td>";
		}

    return $output;
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
   * Save option
   */
  static function save_option($field = null, $value = null) {
    $options = get_option('permalink-manager', array());
    if($field) {
      $options[$field] = $value;

      // The snippet belows prevent duplicates in permastructures
      if($field == 'base-editor') {

        //print_r($value);

        // Algorithm below works like array_count_values(), but we also need to make the permastructs unique
        $unique_permastructures = array();
        foreach($value as $key => $permastruct) {
          // Trim whitespaces & slashes at first
          $permastruct = trim(trim($permastruct, "/"));

          // The permastruct is not unique!
          if(empty($permastruct)) {
            $permastruct = Permalink_Manager_Helper_Functions::get_permastruct($key, false, 'default_permastruct');
          } else if(isset($unique_permastructures[$permastruct])) {
            $unique_permastructures[$permastruct]++;
            // Alter the permastruct
            $permastruct = "{$key}/{$permastruct}";
          } else {
            $unique_permastructures[$permastruct] = 1;
          }

          // Trim one more time
          $permastruct = trim(trim($permastruct, "/"));

          // Alter the permastruct
          $options[$field][$key] = $permastruct;
        }

      }

      update_option('permalink-manager', $options);
    }
  }

  /**
   * Get permastruct
   */
  static function get_permastruct($post_type = 'page', $title_replace = false, $return = 'permastruct') {
    global $wp_rewrite;

    // Load permastruct from options
    $options = get_option('permalink-manager', array());
    $permastructs = isset($options['base-editor']) ? $options['base-editor'] : array();

    // Get default permastruct
    if($post_type == 'page') {
      $default_permastruct = $wp_rewrite->get_page_permastruct();
    } else if($post_type == 'post') {
      $default_permastruct = get_option('permalink_structure');
    } else {
      $default_permastruct = $wp_rewrite->get_extra_permastruct($post_type);
    }

    // If the permastruct is not saved for post type or empty return default permastruct
    $permastruct = (isset($permastructs[$post_type]) && $permastructs[$post_type]) ? $permastructs[$post_type] : $default_permastruct;

    // Remove post name to enable support for hierarchical post types
    $permastruct = ($title_replace) ? str_replace(array("%pagename%", "%postname%", "%{$post_type}%"), '', $permastruct) : "";

    return trim($$return, '/');
  }

  /**
   * Structure Tags & Rewrite functions
   */
  static function get_all_structure_tags($code = true, $seperator = ', ') {
    global $wp_rewrite;

    $tags = $wp_rewrite->rewritecode;
    $output = "";
    $last_tag_index = count($tags);
    $i = 1;

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
