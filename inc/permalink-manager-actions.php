<?php

/**
 * Additional functions used in classes and another subclasses
 */

class Permalink_Manager_Actions extends Permalink_Manager_Class {

  public function __construct() {}

    /**
     * Find & replace (bulk action)
     */
     static function find_replace($find_and_replace_fields) {
       global $wpdb;

       // Reset variables
       $updated_slugs_count = 0;
       $updated_array = array();
       $alert_type = $alert_content = $errors = $main_content = '';
       $old_uris = get_option('permalink-manager-uris');

       // Prepare default variables from $_POST object
       $old_string = esc_sql($_POST['permalink-manager']['find-replace']['old_string']);
       $new_string = esc_sql($_POST['permalink-manager']['find-replace']['new_string']);
       $mode = isset($_POST['permalink-manager']['find-replace']['variant']) ? $_POST['permalink-manager']['find-replace']['variant'] : array('slugs');
       $post_types_array = ($_POST['permalink-manager']['find-replace']['post_types']);
       $post_statuses_array = ($_POST['permalink-manager']['find-replace']['post_statuses']);
       $post_types = implode("', '", $post_types_array);
       $post_statuses = implode("', '", $post_statuses_array);

       // Save the rows before they are updated to an array
       //$posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_name LIKE '%{$old_string}%' AND post_type IN ('{$post_types}')", ARRAY_A);
       $posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}')", ARRAY_A);

       // Now if the array is not empty use IDs from each subarray as a key
       if($posts_to_update && empty($errors)) {
         foreach ($posts_to_update as $row) {

           // Prepare variables
           $old_post_name = $row['post_name'];
           $old_uri = (isset($old_uris[$row['ID']])) ? $old_uris[$row['ID']] : Permalink_Manager_Helper_Functions::get_uri($post_id, true);
           $old_slug = (strpos($old_uri, '/') !== false) ? substr($old_uri, strrpos($old_uri, '/') + 1) : $old_uri;
           $old_base = (strpos($old_uri, '/') !== false) ? substr($old_uri, 0, strrpos( $old_uri, '/') ) : '';

           // Process slug & URI
           $new_slug = str_replace($old_string, $new_string, $old_slug);
           $new_base = str_replace($old_string, $new_string, $old_base);
           $new_uri = (in_array('both', $mode)) ? trim("{$new_base}/{$new_slug}", "/") : trim("{$old_base}/{$new_slug}", "/");
           $new_post_name = (in_array('post_names', $mode)) ? str_replace($old_string, $new_string, $old_post_name) : $old_post_name; // Post name is changed only in first mode

           // Check if native slug should be changed
           if(in_array('post_names', $mode) && ($old_post_name != $new_post_name)) {
             Permalink_Manager_Helper_Functions::update_slug_by_id($new_post_name, $row['ID']);
           }

           if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
             $old_uris[$row['ID']] = $new_uri;
             $updated_array[] = array('post_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
             $updated_slugs_count++;
           }
         }

         // Filter array before saving
         $old_uris = array_filter($old_uris);
         update_option('permalink-manager-uris', $old_uris);

         $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
         wp_reset_postdata();
       }

       return ($output) ? $output : "";
     }

     /**
      * Regenerate slugs & bases (bulk action)
      */
     static function regenerate_all_permalinks() {
       // Setup needed variables
       $updated_slugs_count = 0;
       $updated_array = array();
       $alert_type = $alert_content = $errors = $main_content = '';

       $post_types_array = ($_POST['permalink-manager']['regenerate_slugs']['post_types']);
       $post_statuses_array = ($_POST['permalink-manager']['regenerate_slugs']['post_statuses']);
       $mode = isset($_POST['permalink-manager']['regenerate_slugs']['variant']) ? $_POST['permalink-manager']['regenerate_slugs']['variant'] : array('slugs');

       $old_uris = get_option('permalink-manager-uris');
       $all_permastructs = get_option('permalink-manager-permastructs');

       // Reset query
       $reset_query = new WP_Query( array( 'post_type' => $post_types_array, 'post_status' => $post_statuses_array, 'posts_per_page' => -1 ) );

       // The Loop
       if ( $reset_query->have_posts() ) {
         while ( $reset_query->have_posts() ) {
           $reset_query->the_post();
           $post_id = get_the_ID();
           $this_post = get_post($post_id);
           $updated = 0;

           // Prepare permastructs
           $default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($this_post->post_type);
           $custom_permastruct = isset($all_permastructs[$this_post->post_type]) ? $all_permastructs[$this_post->post_type] : $default_permastruct;

           // Prepare variables
           $old_default_uri = trim(str_replace(home_url("/"), "", get_permalink($post_id)), "/");
           $new_default_uri = Permalink_Manager_Helper_Functions::get_uri($post_id, true);
           $old_post_name = $this_post->post_name;
           $old_uri = isset($old_uris[$post_id]) ? trim($old_uris[$post_id], "/") : $new_default_uri;
           $old_slug = (strpos($old_uri, '/') !== false) ? substr($old_uri, strrpos($old_uri, '/') + 1) : $old_uri;

           // Process slug & URI
           $correct_slug = sanitize_title(get_the_title($post_id));
           $new_slug = wp_unique_post_slug($correct_slug, $post_id, get_post_status($post_id), get_post_type($post_id), null);
           $new_post_name = (in_array('post_names', $mode)) ? $new_slug : $old_post_name; // Post name is changed only in first mode
           $new_uri = (in_array('both', $mode)) ? $new_default_uri : str_replace($old_slug, $new_slug, $old_uri);

           // Check if native slug should be changed
           if(in_array('post_names', $mode) && ($old_post_name != $new_post_name)) {
             Permalink_Manager_Helper_Functions::update_slug_by_id($new_post_name, $post_id);
           }

           if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
             $old_uris[$post_id] = $new_uri;
             $updated_array[] = array('post_title' => get_the_title(), 'ID' => $post_id, 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
             $updated_slugs_count++;
           }

           // Do not store default values
           if($new_uri == $old_default_uri) {
             unset($old_uris[$post_id]);
           }
         }

         // Filter array before saving
         $old_uris = array_filter($old_uris);
         update_option('permalink-manager-uris', $old_uris);

         $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
         wp_reset_postdata();
       }

       return ($output) ? $output : "";
     }

    /**
    * Update all slugs & bases (bulk action)
    */
    static function update_all_permalinks() {
      // Setup needed variables
      $updated_slugs_count = 0;
      $updated_array = array();
      $alert_type = $alert_content = $errors = $main_content = '';

      $old_uris = get_option('permalink-manager-uris');
      $new_uris = isset($_POST['uri']) ? $_POST['uri'] : array();

      // Double check if the slugs and ids are stored in arrays
      if (!is_array($new_uris)) $new_uris = explode(',', $new_uris);

      if (!empty($new_uris)) {
        foreach($new_uris as $id => $new_uri) {
          // Prepare variables
          $this_post = get_post($id);
          $updated = '';

          // Prepare old values
          $old_default_uri = trim(str_replace(home_url("/"), "", get_permalink($id)), "/");
          $new_default_uri = Permalink_Manager_Helper_Functions::get_uri($id, true);
          $old_uri = isset($old_uris[$id]) ? trim($old_uris[$id], "/") : $new_default_uri;

          // Process slug & URI; Empty entries will be treated as default values
          $new_uri = preg_replace('/\s+/', '', $new_uri);
          $new_uri = ($new_uri) ? trim($new_uri, "/") : $new_default_uri;
          $new_slug = (strpos($new_uri, '/') !== false) ? substr($new_uri, strrpos($new_uri, '/') + 1) : $new_uri;

          // Neither base nor slug was changed - continue
          if($new_uri == $old_uri) continue;

          if($new_uri != $old_uri) {
            $old_uris[$id] = $new_uri;
            $updated_array[] = array('post_title' => get_the_title($id), 'ID' => $id, 'old_uri' => $old_uri, 'new_uri' => $new_uri);
            $updated_slugs_count++;
          }

          // Do not store default values
          if($new_uri == $old_default_uri) {
            unset($old_uris[$id]);
          }

        }

        // Filter array before saving
        $old_uris = array_filter($old_uris);
        update_option('permalink-manager-uris', $old_uris);

        $output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
      }

      return ($output) ? $output : "";
    }

    /**
    * Update permastructs
    */
    static function update_permastructs() {
      // Setup needed variables
      $alert_type = $alert_content = $errors = $main_content = '';
      $old_permastructs = get_option('permalink-manager-permastructs');
      $new_permastructs = array_filter($_POST['permalink-manager']['custom-permastructs']);

      foreach($new_permastructs as $post_type => $new_permstruct) {
        $default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type, true);
        $old_permastruct = $old_permastructs[$post_type];
        $new_permastructs[$post_type] = trim(preg_replace('/\s+/', '', $new_permstruct), "/");

        // Do not save default permastructs
        if($default_permastruct == $new_permstruct) {
          unset($new_permastructs[$post_type]);
        }
      }

      update_option('permalink-manager-permastructs', $new_permastructs);

      return "";
    }

}
