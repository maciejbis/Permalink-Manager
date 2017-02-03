<?php

/**
 * Register all action hooks
 */
class Permalink_Manager_Uri_Actions extends Permalink_Manager_Class {

  public function __construct() {
    add_action('admin_init', array($this, 'trigger_action'), 0);

    add_action( 'save_post', array($this, 'update_single_uri'), 10, 3 );
    add_action( 'wp_trash_post', array($this, 'remove_single_uri'), 10, 3 );
  }

  /**
   * Trigger the specific action
   */
  function trigger_action() {
    global $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

    // Triggered in "Permalink Editor" section
    if(isset($_POST['slug_editor']) && wp_verify_nonce($_POST['slug_editor'], 'uri_actions')) {
      $updated_list = $this->posts_update_all_permalinks();

      $updated_slugs_count = (isset($updated_list['updated_count']) && $updated_list['updated_count'] > 0) ? $updated_list['updated_count'] : false;
      $updated_slugs_array = ($updated_slugs_count) ? $updated_list['updated'] : '';
    }
    // Triggered in "Regenerate/Rest" section
    else if(isset($_POST['regenerate_posts']) && wp_verify_nonce($_POST['regenerate_posts'], 'uri_actions')) {
      $updated_list = $this->posts_regenerate_all_permalinks();

      $updated_slugs_count = (isset($updated_list['updated_count']) && $updated_list['updated_count'] > 0) ? $updated_list['updated_count'] : false;
      $updated_slugs_array = ($updated_slugs_count) ? $updated_list['updated'] : '';
    }
    // Triggered in "Find and Replace" section
    else if(isset($_POST['find_and_replace']) && wp_verify_nonce($_POST['find_and_replace'], 'uri_actions')) {
      $updated_list = $this->posts_find_and_replace();

      $updated_slugs_count = (isset($updated_list['updated_count']) && $updated_list['updated_count'] > 0) ? $updated_list['updated_count'] : false;
      $updated_slugs_array = ($updated_slugs_count) ? $updated_list['updated'] : '';
    }

    // 2. Display the slugs table (and append the globals)
    if(isset($updated_slugs_count)) {
      if($updated_slugs_count > 0) {
        $alert_content = sprintf( _n( '<strong>%d</strong> slug was updated!', '<strong>%d</strong> slugs were updated!', $updated_slugs_count, 'permalink-manager' ), $updated_slugs_count ) . ' ';
        $alert_content .= sprintf( __( '<a href="%s">Click here</a> to go to the list of updated slugs', 'permalink-manager' ), '#updated-list');

        $permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message($alert_content, 'updated');
        $permalink_manager_after_sections_html .= Permalink_Manager_Admin_Functions::display_updated_slugs($updated_slugs_array);
      } else {
        $alert_content = __( '<strong>No slugs</strong> were updated!', 'permalink-manager' );
        $permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message($alert_content, 'error');
      }
    }
  }

  /**
   * Find & replace (bulk action)
   */
   static function posts_find_and_replace() {
     global $wpdb, $permalink_manager_uris;

     // Reset variables
     $updated_slugs_count = 0;
     $updated_array = array();
     $alert_type = $alert_content = $errors = '';

     // Prepare default variables from $_POST object
     $old_string = esc_sql($_POST['old_string']);
     $new_string = esc_sql($_POST['new_string']);
     $mode = isset($_POST['mode']) ? $_POST['mode'] : array('both');
     $post_types_array = ($_POST['post_types']);
     $post_statuses_array = ($_POST['post_statuses']);
     $post_types = implode("', '", $post_types_array);
     $post_statuses = implode("', '", $post_statuses_array);

     // Save the rows before they are updated to an array
     $posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}')", ARRAY_A);

     // Now if the array is not empty use IDs from each subarray as a key
     if($posts_to_update && empty($errors)) {
       foreach ($posts_to_update as $row) {

         // Prepare variables
         $old_post_name = $row['post_name'];
         $native_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($row['ID'], true);
         $default_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($row['ID']);
         $old_uri = (isset($permalink_manager_uris[$row['ID']])) ? $permalink_manager_uris[$row['ID']] : $default_uri;
         $old_slug = (strpos($old_uri, '/') !== false) ? substr($old_uri, strrpos($old_uri, '/') + 1) : $old_uri;
         $old_base = (strpos($old_uri, '/') !== false) ? substr($old_uri, 0, strrpos( $old_uri, '/') ) : '';

         // Process URI & slug
         $new_slug = str_replace($old_string, $new_string, $old_slug);
         $new_base = str_replace($old_string, $new_string, $old_base);
         $new_uri = (in_array($mode, array('both'))) ? trim("{$new_base}/{$new_slug}", "/") : trim("{$old_base}/{$new_slug}", "/");
         $new_post_name = (in_array($mode, array('post_names'))) ? str_replace($old_string, $new_string, $old_post_name) : $old_post_name; // Post name is changed only in first mode

         //print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

         // Check if native slug should be changed
         if(in_array($mode, array('post_names')) && ($old_post_name != $new_post_name)) {
           Permalink_Manager_Post_URI_Functions::update_slug_by_id($new_post_name, $row['ID']);
         }

         if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
           $permalink_manager_uris[$row['ID']] = $new_uri;
           $updated_array[] = array('post_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
           $updated_slugs_count++;
         }

         // Do not store default values
         if(isset($permalink_manager_uris[$row['ID']]) && ($new_uri == $native_uri)) {
           unset($permalink_manager_uris[$row['ID']]);
         }
       }

       // Filter array before saving
       $permalink_manager_uris = array_filter($permalink_manager_uris);
       update_option('permalink-manager-uris', $permalink_manager_uris);

       $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
       wp_reset_postdata();
     }

     return ($output) ? $output : "";
   }

   /**
    * Regenerate slugs & bases (bulk action)
    */
   static function posts_regenerate_all_permalinks() {
     global $wpdb, $permalink_manager_uris, $permalink_manager_permastructs;

     // Setup needed variables
     $updated_slugs_count = 0;
     $updated_array = array();
     $alert_type = $alert_content = $errors = '';

     $post_types_array = ($_POST['post_types']) ? ($_POST['post_types']) : '';
     $post_statuses_array = ($_POST['post_statuses']) ? $_POST['post_statuses'] : '';
     $post_types = implode("', '", $post_types_array);
     $post_statuses = implode("', '", $post_statuses_array);
     $mode = isset($_POST['mode']) ? $_POST['mode'] : 'both';

     // Save the rows before they are updated to an array
     $posts_to_update = $wpdb->get_results("SELECT post_title, post_name, post_type, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}')", ARRAY_A);

     // Now if the array is not empty use IDs from each subarray as a key
     if($posts_to_update && empty($errors)) {
       foreach ($posts_to_update as $row) {
         $updated = 0;

         // Prepare variables
         $old_post_name = $row['post_name'];
         $native_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($row['ID'], true);
         $default_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($row['ID']);
         $old_uri = isset($permalink_manager_uris[$row['ID']]) ? trim($permalink_manager_uris[$row['ID']], "/") : $native_uri;
         $old_slug = (strpos($old_uri, '/') !== false) ? substr($old_uri, strrpos($old_uri, '/') + 1) : $old_uri;
         $correct_slug = sanitize_title($row['post_title']);

         // Process URI & slug
         $new_slug = wp_unique_post_slug($correct_slug, $row['ID'], get_post_status($row['ID']), get_post_type($row['ID']), null);
         $new_post_name = (in_array($mode, array('post_names'))) ? $new_slug : $old_post_name; // Post name is changed only in first mode
         $new_uri = (in_array($mode, array('both'))) ? $default_uri : str_replace($old_slug, $new_slug, $old_uri);

         //print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

         // Check if native slug should be changed
         if(in_array($mode, array('post_names')) && ($old_post_name != $new_post_name)) {
           Permalink_Manager_Post_URI_Functions::update_slug_by_id($new_post_name, $row['ID']);
         }

         if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
           $permalink_manager_uris[$row['ID']] = $new_uri;
           $updated_array[] = array('post_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
           $updated_slugs_count++;
         }

         // Do not store default values
         if(isset($permalink_manager_uris[$row['ID']]) && ($new_uri == $native_uri)) {
           unset($permalink_manager_uris[$row['ID']]);
         }
       }

       // Filter array before saving
       $permalink_manager_uris = array_filter($permalink_manager_uris);
       update_option('permalink-manager-uris', $permalink_manager_uris);

       $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
       wp_reset_postdata();
     }

     return (!empty($output)) ? $output : "";
   }

  /**
   * Update all slugs & bases (bulk action)
   */
  public function posts_update_all_permalinks() {
    global $permalink_manager_uris;

    // Setup needed variables
    $updated_slugs_count = 0;
    $updated_array = array();

    $old_uris = $permalink_manager_uris;
    $new_uris = isset($_POST['uri']) ? $_POST['uri'] : array();

    // Double check if the slugs and ids are stored in arrays
    if (!is_array($new_uris)) $new_uris = explode(',', $new_uris);

    if (!empty($new_uris)) {
      foreach($new_uris as $id => $new_uri) {
        // Prepare variables
        $this_post = get_post($id);
        $updated = '';

        // Get default & native URL
        $native_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($id, true);
        $default_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($id);

        $old_uri = isset($old_uris[$id]) ? trim($old_uris[$id], "/") : $native_uri;

        // Process new values - empty entries will be treated as default values
        $new_uri = preg_replace('/\s+/', '', $new_uri);
        $new_uri = (!empty($new_uri)) ? trim($new_uri, "/") : $default_uri;
        $new_slug = (strpos($new_uri, '/') !== false) ? substr($new_uri, strrpos($new_uri, '/') + 1) : $new_uri;

        //print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

        // Do not store native URIs
        if($new_uri == $native_uri) {
          unset($old_uris[$id]);
        }

        if($new_uri != $old_uri) {
          $old_uris[$id] = $new_uri;
          $updated_array[] = array('post_title' => get_the_title($id), 'ID' => $id, 'old_uri' => $old_uri, 'new_uri' => $new_uri);
          $updated_slugs_count++;
        }

      }

      // Filter array before saving & append the global
      $old_uris = $permalink_manager_uris = array_filter($old_uris);
      update_option('permalink-manager-uris', $old_uris);

      $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
    }

    return ($output) ? $output : "";
  }

  /**
   * Remove URI from options array after post is moved to the trash
   */
  function clear_uris($post_id) {
    $uris = $this->permalink_manager_uris;

    foreach($uris as $post_id => $uri) {
      $post_status = get_post_status($post_id);
      if(in_array($post_status, array('auto-draft', 'trash', ''))) {
        unset($uris[$post_id]);
      }
    }

    update_option('permalink-manager-uris', $uris);
  }

  /**
   * Update permastructs
   */
  static function update_permastructs() {
    // Setup needed variables
    $alert_type = $alert_content = $errors = '';
    $permastructs = get_option('permalink-manager-permastructs', array());
    $new_permastructs = array_filter($_POST['permalink-manager']['custom-permastructs']);

    foreach($new_permastructs as $post_type => $new_permstruct) {
      $default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type, true);
      $permastructs[$post_type] = trim(preg_replace('/\s+/', '', $new_permstruct), "/");

      // Do not save default permastructs
      if($default_permastruct == $new_permstruct) {
        unset($permastructs[$post_type]);
      }
    }

    update_option('permalink-manager-permastructs', $permastructs);

    return "";
  }

  /**
	 * Update URI from "Edit Post" admin page
	 */
	function update_single_uri($post_id, $post, $update) {
    global $permalink_manager_uris;

		// Ignore trashed items
		if($post->post_status == 'trash') return;

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		$native_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($post, true);
		$old_uri = (isset($permalink_manager_uris[$post->ID])) ? $permalink_manager_uris[$post->ID] : $native_uri;
		$new_uri = '';

		// Check if user changed URI (available after post is saved)
		if(isset($_POST['custom_uri'])) {
			$new_uri = trim($_POST['custom_uri'], "/");
		}

		// A little hack (if user removes whole URI from input) ...
		$new_uri = ($new_uri) ? $new_uri : Permalink_Manager_Post_URI_Functions::get_post_uri($post);

		// Do not store default values
		if(isset($permalink_manager_uris[$post->ID]) && ($new_uri == $native_uri)) {
			unset($permalink_manager_uris[$post->ID]);
		}
    // Save only changed URIs
    else if (($new_uri != $native_uri) && ($new_uri != $old_uri)) {
			$permalink_manager_uris[$post->ID] = $new_uri;
		}

		update_option('permalink-manager-uris', $permalink_manager_uris);
	}

	/**
	 * Remove URI from options array after post is moved to the trash
	 */
	function remove_single_uri($post_id) {
		global $permalink_manager_uris;

		// Check if the custom permalink is assigned to this post
		if(isset($permalink_manager_uris[$post_id])) {
			unset($permalink_manager_uris[$post_id]);
		}

		update_option('permalink-manager-uris', $permalink_manager_uris);
	}

}
