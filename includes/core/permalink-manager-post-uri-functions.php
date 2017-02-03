<?php

/**
 * Additional functions used in classes and another subclasses
 */
class Permalink_Manager_Post_URI_Functions extends Permalink_Manager_Class {

  public function __construct() {
		add_filter( 'request', array($this, 'detect_post'), 0, 1 );
		add_filter( '_get_page_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'page_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'post_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'post_type_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'permalink-manager-uris', array($this, 'exclude_homepage'), 999, 2);
  }

  /**
	 * Change permalinks for posts, pages & custom post types
	 */
	function custom_permalinks($permalink, $post) {
		global $wp_rewrite, $permalink_manager_uris;

		$post = (is_integer($post)) ? get_post($post) : $post;
		$post_type = $post->post_type;

		// Do not change permalink of frontpage
		if(get_option('page_on_front') == $post->ID) { return $permalink; }
		if(isset($permalink_manager_uris[$post->ID])) $permalink = home_url('/') . $permalink_manager_uris[$post->ID];

		return $permalink;
	}

  /**
   * Display the permalink in a better way
   */
  static function get_correct_permalink($id) {
    global $permalink_manager_uris;
    $permalink = isset($permalink_manager_uris[$id]) ? home_url('/') . $permalink_manager_uris[$id] : get_permalink($id);

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
   * Get the active URI
   */
  static function get_post_uri($post_id) {
    global $permalink_manager_uris;

    $final_uri = isset($permalink_manager_uris[$post_id]) ? $permalink_manager_uris[$post_id] : Permalink_Manager_Post_URI_Functions::get_default_post_uri($post_id);
    return $final_uri;
  }

  /**
   * Get the default (not overwritten by the user) or native URI (unfiltered)
   */
  static function get_default_post_uri($post, $native_uri = false) {
    global $permalink_manager_options, $permalink_manager_uris, $permalink_manager_permastructs;

    // Load all bases & post
    $post = is_object($post) ? $post : get_post($post);
    $post_id = $post->ID;
    $post_type = $post->post_type;
    $post_name = $post->post_name;

    // Get the permastruct
    $default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type);

    if($native_uri) {
      $permastruct = $default_permastruct;
    } else if($permalink_manager_permastructs) {
      $permastruct = isset($permalink_manager_permastructs[$post_type]) ? $permalink_manager_permastructs[$post_type] : $default_permastruct;
    } else {
      $permastruct = isset($permalink_manager_options['base-editor'][$post_type]) ? $permalink_manager_options['base-editor'][$post_type] : $default_permastruct;
    }
    $default_base = (!empty($permastruct)) ? trim($permastruct, '/') : "";

    // 1A. Get the date
    $date = explode(" ",date('Y m d H i s', strtotime($post->post_date)));

    // 1B. Get the category slug (if needed)
    $category = '';
    if ( strpos($default_base, '%category%') !== false ) {

      // I. Try to use Yoast SEO Primary Term
      $category = (Permalink_Manager_Helper_Functions::get_primary_term($post->ID, 'category'));

      // II. Get the first assigned category
      if(empty($category)) {
        $cats = get_the_category($post->ID);
        if ($cats) {
          usort($cats, '_usort_terms_by_ID'); // order by ID
          $category_object = apply_filters( 'post_link_category', $cats[0], $cats, $post );
          $category_object = get_term( $category_object, 'category' );
          $category = $category_object->slug;
          if ( $parent = $category_object->parent )
            $category = get_category_parents($parent, false, '/', true) . $category;
        }
      }

      // III. Show default category in permalinks, without having to assign it explicitly
      if(empty($category)) {
        $default_category = get_term( get_option( 'default_category' ), 'category' );
        $category = is_wp_error( $default_category ) ? '' : $default_category->slug;
      }
    }

    // 1C. Get the author (if needed)
    $author = '';
    if ( strpos($default_base, '%author%') !== false ) {
      $authordata = get_userdata($post->post_author);
      $author = $authordata->user_nicename;
    }

    // 2. Fix for hierarchical CPT (start)
    $full_slug = get_page_uri($post);
    $post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);

    // 3A. Do the replacement (post tag is removed now to enable support for hierarchical CPT)
    $tags = array('%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%category%', '%author%', $post_type_tag);
    $replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $category, $author, '');
    $default_uri = str_replace($tags, $replacements, "{$default_base}/{$full_slug}");

    // 3B. Replace custom taxonomies
    $terms = get_taxonomies( array('public' => true, '_builtin' => false), 'names', 'and' );
    $taxonomies = $terms;
    if ( $taxonomies ) {
      foreach($taxonomies as $taxonomy) {
        // A. Try to use Yoast SEO Primary Term
        $category = (Permalink_Manager_Helper_Functions::get_primary_term($post->ID, $taxonomy));

        // B. Get the first assigned term to this taxonomy
        if(empty($replacement)) {
          $terms = wp_get_object_terms($post->ID, $taxonomy);
          $replacement = (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) ? $terms[0]->slug : "";
        }

        // Do the replacement
        $default_uri = ($replacement) ? str_replace("%{$taxonomy}%", $replacement, $default_uri) : $default_uri;
      }
    }

    $default_uri = preg_replace('/\s+/', '', $default_uri);
    $default_uri = str_replace('//', '/', $default_uri);
    $default_uri = trim($default_uri, "/");

    return $default_uri;
  }

  /**
   * The homepage should not use URI
   */
  function exclude_homepage($uris) {
    // Find the homepage URI
    $homepage_id = get_option('page_on_front');
    if(isset($uris[$homepage_id])) { unset($uris[$homepage_id]); }

    return $uris;
  }

}

?>
