<?php

/**
* Additional functions used in classes and another subclasses
*/
class Permalink_Manager_URI_Functions_Post extends Permalink_Manager_Class {

	public function __construct() {
		add_action( 'admin_init', array($this, 'admin_init'), 999, 3);

		add_filter( '_get_page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_type_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'attachment_link', array($this, 'custom_post_permalinks'), 999, 2);

		add_filter( 'permalink-manager-uris', array($this, 'exclude_homepage'), 999, 2);

		/**
		 * URI Editor
		 */
		add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 999, 5 );
		add_action( 'save_post', array($this, 'update_post_uri'), 999, 1);
		add_action( 'edit_attachment', array($this, 'update_post_uri'), 999, 1 );
		add_action( 'wp_insert_post', array($this, 'new_post_uri'), 999, 1 );
		add_action( 'wp_trash_post', array($this, 'remove_post_uri'), 10, 1 );

		add_action( 'quick_edit_custom_box', array($this, 'quick_edit_column_form'), 999, 3);
	}

	/**
	 * Init
	 */
	function admin_init() {
		$post_types = get_post_types(array('public' => true, '_builtin' => false), 'names', 'and');

		// Add "URI Editor" to "Quick Edit" for all post_types
		foreach($post_types as $post_type) {
			add_filter( "manage_{$post_type}_columns" , array($this, 'quick_edit_column') );
			add_filter( "manage_{$post_type}_custom_column" , array($this, 'quick_edit_column_content'), 10, 2 );
		}
		add_filter( "manage_posts_columns" , array($this, 'quick_edit_column') );
		add_filter( "manage_pages_columns" , array($this, 'quick_edit_column') );
		add_filter( "manage_posts_custom_column" , array($this, 'quick_edit_column_content'), 10, 2 );
		add_filter( "manage_pages_custom_column" , array($this, 'quick_edit_column_content'), 10, 2 );

		add_filter( "default_hidden_columns" , array($this, 'quick_edit_hide_column'), 10, 2 );
	}

	/**
	* Change permalinks for posts, pages & custom post types
	*/
	function custom_post_permalinks($permalink, $post) {
		global $wp_rewrite, $permalink_manager_uris, $permalink_manager_options;

		$post = (is_integer($post)) ? get_post($post) : $post;
		$post_type = $post->post_type;

		// 1A. Do not change permalink of frontpage
		if(get_option('page_on_front') == $post->ID) {
			return $permalink;
		}
		// 1B. Do not change permalink for drafts and future posts (+ remove trailing slash from them)
		else if(in_array($post->post_status, array('draft', 'pending', 'auto-draft', 'future'))) {
			return trim($permalink, "/");
		}

		// 2. Filter only the posts with custom permalink assigned
		if(isset($permalink_manager_uris[$post->ID])) {
			// Apend the language code as a non-editable prefix (can be used also for another prefixes)
			$prefix = apply_filters('permalink-manager-post-permalink-prefix', '', $post);

			$permalink = get_option('home');

			// Encode URI?
			if(!empty($permalink_manager_options['general']['decode_uris'])) {
				$permalink .= urldecode("/{$prefix}{$permalink_manager_uris[$post->ID]}");
			} else {
				$permalink .= Permalink_Manager_Helper_Functions::encode_uri("/{$prefix}{$permalink_manager_uris[$post->ID]}");
			}
		} else if(!empty($permalink_manager_options['general']['decode_uris'])) {
			$permalink = urldecode($permalink);
		}

		// 3. Additional filter
		$permalink = apply_filters('permalink_manager_filter_final_post_permalink', user_trailingslashit($permalink), $post);

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
	public static function get_post_uri($post_id, $native_uri = false) {
		global $permalink_manager_uris;

		// Check if input is post object
		$post_id = (isset($post_id->ID)) ? $post_id->ID : $post_id;

		$final_uri = (!empty($permalink_manager_uris[$post_id])) ? $permalink_manager_uris[$post_id] : self::get_default_post_uri($post_id, $native_uri);
		return $final_uri;
	}

	/**
	* Get the default (not overwritten by the user) or native URI (unfiltered)
	*/
	public static function get_default_post_uri($post, $native_uri = false) {
		global $permalink_manager_options, $permalink_manager_uris, $permalink_manager_permastructs;

		// Load all bases & post
		$post = is_object($post) ? $post : get_post($post);

		// Check if post ID is defined
		if(empty($post->ID)) { return ''; }
		$post_id = $post->ID;
		$post_type = $post->post_type;
		$post_name = (empty($post->post_name)) ? sanitize_title($post->post_title) : $post->post_name;

		// Get the permastruct
		$default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type);
		if($native_uri) {
			$permastruct = $default_permastruct;
		} else {
			$permastruct = (isset($permalink_manager_permastructs['post_types'][$post_type])) ? $permalink_manager_permastructs['post_types'][$post_type] : $default_permastruct;
		}

		$default_base = (!empty($permastruct)) ? trim($permastruct, '/') : "";

		// Check if the slug should not be appended
		$dont_append_slug = (!$native_uri && !empty($permalink_manager_options['general']['disable_slug_appendix']) && in_array($post_type, $permalink_manager_options['general']['disable_slug_appendix'])) ? true : false;

		// 1A. Get the date
		$date = explode(" ", date('Y m d H i s', strtotime($post->post_date)));

		// 1B. Get the author (if needed)
		$author = '';
		if ( strpos($default_base, '%author%') !== false ) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		// 2A. Fix for hierarchical CPT (start)
		$full_slug = get_page_uri($post);
		$full_slug = (empty($full_slug)) ? $post_name : $full_slug;

		if(!$native_uri) {
			// 2C. Allow filter the default slug
			$full_slug = ($native_uri) ? $full_slug : Permalink_Manager_Helper_Functions::force_custom_slugs($full_slug, $post);
			$full_slug = apply_filters('permalink_manager_filter_default_post_slug', $full_slug, $post, $post_name);
		}
		$post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);

		// 3A. Do the replacement (post tag is removed now to enable support for hierarchical CPT)
		$tags = array('%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%author%', $post_type_tag, '%postname%');

		if($dont_append_slug) {
			$replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $author, $full_slug, $full_slug);
			$default_uri = str_replace($tags, $replacements, "{$default_base}");
		} else {
			$replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $author, '', '');
			$default_uri = str_replace($tags, $replacements, "{$default_base}/{$full_slug}");
		}

		// 3B. Replace taxonomies
		$taxonomies = get_taxonomies();

		if($taxonomies) {
			foreach($taxonomies as $taxonomy) {
				// 1. Reset $replacement
				$replacement = $terms = $replacement_term = "";

				// 2. Try to use Yoast SEO Primary Term
				$replacement_term = Permalink_Manager_Helper_Functions::get_primary_term($post->ID, $taxonomy, false);

				// 3. Get the first assigned term to this taxonomy
				if(empty($replacement_term)) {
					$terms = wp_get_object_terms($post->ID, $taxonomy);
					$replacement_term = (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) ? $terms[0] : "";
				}

				if(!empty($replacement_term->term_id) && strpos($default_uri, "%{$taxonomy}_custom_uri%") !== false && !empty($permalink_manager_uris["tax-{$replacement_term->term_id}"])) {
					$replacement = $permalink_manager_uris["tax-{$replacement_term->term_id}"];
				}
				// 4B. Hierarhcical taxonomy base
				else if(!empty($replacement_term->term_id) && strpos($default_uri, "%{$taxonomy}_flat%") === false && is_taxonomy_hierarchical($taxonomy)) {
					$ancestors = get_ancestors( $replacement_term->term_id, $taxonomy, 'taxonomy' );
					$hierarchical_slugs = array();

					foreach((array) $ancestors as $ancestor) {
						$ancestor_term = get_term($ancestor, $taxonomy);
						$hierarchical_slugs[] = ($native_uri) ? $replacement_term->slug : Permalink_Manager_Helper_Functions::force_custom_slugs($ancestor_term->slug, $ancestor_term);
					}
					$hierarchical_slugs = array_reverse($hierarchical_slugs);
					$replacement = implode('/', $hierarchical_slugs);

					// Append the term slug now
					$last_term_slug = ($native_uri) ? $replacement_term->slug : Permalink_Manager_Helper_Functions::force_custom_slugs($replacement_term->slug, $replacement_term);
					$replacement = "{$replacement}/{$last_term_slug}";
				}
				// 4C. Flat taxonomy base
				else if(!empty($replacement_term->slug)) {
					$replacement = ($native_uri) ? $replacement_term->slug : Permalink_Manager_Helper_Functions::force_custom_slugs($replacement_term->slug, $replacement_term);
				}

				// 4. Do the replacement
				$default_uri = (!empty($replacement)) ? str_replace(array("%{$taxonomy}%", "%{$taxonomy}_flat%", "%{$taxonomy}_custom_uri%"), $replacement, $default_uri) : $default_uri;
			}
		}

		// Clear the URI
		$default_uri = preg_replace("/%(.+?)%/", "", $default_uri);
		$default_uri = preg_replace('/\s+/', '', $default_uri);
		$default_uri = str_replace('//', '/', $default_uri);
		$default_uri = trim($default_uri, "/");

		return apply_filters('permalink_manager_filter_default_post_uri', $default_uri, $post->post_name, $post, $post_name, $native_uri);
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

	/**
	* Find & replace (bulk action)
	*/
	public static function find_and_replace() {
		global $wpdb, $permalink_manager_uris;

		// Check if post types & statuses are not empty
		if(empty($_POST['post_types']) || empty($_POST['post_statuses'])) { return false; }

		// Get homepage URL and ensure that it ends with slash
		$home_url = trim(get_option('home'), "/") . "/";

		// Reset variables
		$updated_slugs_count = 0;
		$updated_array = array();
		$alert_type = $alert_content = $errors = '';

		// Process the variables from $_POST object
		$old_string = str_replace($home_url, '', esc_sql($_POST['old_string']));
		$new_string = str_replace($home_url, '', esc_sql($_POST['new_string']));

		$post_types_array = ($_POST['post_types']);
		$post_statuses_array = ($_POST['post_statuses']);
		$post_types = implode("', '", $post_types_array);
		$post_statuses = implode("', '", $post_statuses_array);
		$mode = isset($_POST['mode']) ? $_POST['mode'] : 'custom_uris';

		// Filter the posts by IDs
		$where = '';
		if(!empty($_POST['ids'])) {
			// Remove whitespaces and prepare array with IDs and/or ranges
			$ids = esc_sql(preg_replace('/\s*/m', '', $_POST['ids']));
			preg_match_all("/([\d]+(?:-?[\d]+)?)/x", $ids, $groups);

			// Prepare the extra ID filters
			$where .= "AND (";
			foreach($groups[0] as $group) {
				$where .= ($group == reset($groups[0])) ? "" : " OR ";
				// A. Single number
				if(is_numeric($group)) {
					$where .= "(ID = {$group})";
				}
				// B. Range
				else if(substr_count($group, '-')) {
					$range_edges = explode("-", $group);
					$where .= "(ID BETWEEN {$range_edges[0]} AND {$range_edges[1]})";
				}
			}
			$where .= ")";
		}

		// Get the rows before they are altered
		$posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}') {$where}", ARRAY_A);

		// Now if the array is not empty use IDs from each subarray as a key
		if($posts_to_update && empty($errors)) {
			foreach ($posts_to_update as $row) {
				// Get default & native URL
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);
				$old_post_name = $row['post_name'];
				$old_uri = (isset($permalink_manager_uris[$row['ID']])) ? $permalink_manager_uris[$row['ID']] : $default_uri;

				// Do replacement on slugs (non-REGEX)
				if(preg_match("/^\/.+\/[a-z]*$/i", $old_string)) {
					// Use $_POST['old_string'] directly here & fix double slashes problem
					$pattern = "~" . stripslashes(trim(sanitize_text_field($_POST['old_string']), "/")) . "~";

					$new_post_name = ($mode == 'slugs') ? preg_replace($pattern, $new_string, $old_post_name) : $old_post_name;
					$new_uri = ($mode != 'slugs') ? preg_replace($pattern, $new_string, $old_uri) : $old_uri;
				} else {
					$new_post_name = ($mode == 'slugs') ? str_replace($old_string, $new_string, $old_post_name) : $old_post_name; // Post name is changed only in first mode
					$new_uri = ($mode != 'slugs') ? str_replace($old_string, $new_string, $old_uri) : $old_uri;
				}

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

				// Check if native slug should be changed
				if(($mode == 'slugs') && ($old_post_name != $new_post_name)) {
					self::update_slug_by_id($new_post_name, $row['ID']);
				}

				if(($old_uri != $new_uri) || ($old_post_name != $new_post_name) && !(empty($new_uri))) {
					$permalink_manager_uris[$row['ID']] = $new_uri;
					$updated_array[] = array('item_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
					$updated_slugs_count++;
				}

				do_action('permalink-manager-updated-post-uri', $row['ID'], $new_uri, $old_uri, $native_uri, $default_uri);
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
	static function regenerate_all_permalinks() {
		global $wpdb, $permalink_manager_uris;

		// Check if post types & statuses are not empty
		if(empty($_POST['post_types']) || empty($_POST['post_statuses'])) { return false; }

		// Process the variables from $_POST object
		$updated_slugs_count = 0;
		$updated_array = array();
		$alert_type = $alert_content = $errors = '';

		$post_types_array = ($_POST['post_types']) ? ($_POST['post_types']) : '';
		$post_statuses_array = ($_POST['post_statuses']) ? $_POST['post_statuses'] : '';
		$post_types = implode("', '", $post_types_array);
		$post_statuses = implode("', '", $post_statuses_array);
		$mode = isset($_POST['mode']) ? $_POST['mode'] : 'custom_uris';

		// Filter the posts by IDs
		$where = '';
		if(!empty($_POST['ids'])) {
			// Remove whitespaces and prepare array with IDs and/or ranges
			$ids = esc_sql(preg_replace('/\s*/m', '', $_POST['ids']));
			preg_match_all("/([\d]+(?:-?[\d]+)?)/x", $ids, $groups);

			// Prepare the extra ID filters
			$where .= "AND (";
			foreach($groups[0] as $group) {
				$where .= ($group == reset($groups[0])) ? "" : " OR ";
				// A. Single number
				if(is_numeric($group)) {
					$where .= "(ID = {$group})";
				}
				// B. Range
				else if(substr_count($group, '-')) {
					$range_edges = explode("-", $group);
					$where .= "(ID BETWEEN {$range_edges[0]} AND {$range_edges[1]})";
				}
			}
			$where .= ")";
		}

		// Get the rows before they are altered
		$posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}') {$where}", ARRAY_A);

		// Now if the array is not empty use IDs from each subarray as a key
		if($posts_to_update && empty($errors)) {
			foreach ($posts_to_update as $row) {
				// Prevent server timeout
				set_time_limit(0);

				// Get default & native URL
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);
				$old_post_name = $row['post_name'];
				$old_uri = isset($permalink_manager_uris[$row['ID']]) ? trim($permalink_manager_uris[$row['ID']], "/") : $native_uri;
				$correct_slug = sanitize_title($row['post_title']);

				// Process URI & slug
				$new_slug = wp_unique_post_slug($correct_slug, $row['ID'], get_post_status($row['ID']), get_post_type($row['ID']), null);
				$new_post_name = ($mode == 'slugs') ? $new_slug : $old_post_name; // Post name is changed only in first mode

				// Prepare the new URI
				if($mode == 'slugs') {
					$new_uri = $old_uri;
				} else if($mode == 'native') {
					$new_uri = $native_uri;
				} else {
					$new_uri = $default_uri;
				}

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

				// Check if native slug should be changed
				if(($mode == 'slugs') && ($old_post_name != $new_post_name)) {
					self::update_slug_by_id($new_post_name, $row['ID']);
				}

				if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
					$permalink_manager_uris[$row['ID']] = $new_uri;
					$updated_array[] = array('item_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
					$updated_slugs_count++;
				}

				do_action('permalink-manager-updated-post-uri', $row['ID'], $new_uri, $old_uri, $native_uri, $default_uri);
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
	static public function update_all_permalinks() {
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
				// Prevent server timeout
				set_time_limit(0);

				// Prepare variables
				$this_post = get_post($id);
				$updated = '';

				// Get default & native URL
				$native_uri = self::get_default_post_uri($id, true);
				$default_uri = self::get_default_post_uri($id);

				$old_uri = isset($old_uris[$id]) ? trim($old_uris[$id], "/") : $native_uri;

				// Process new values - empty entries will be treated as default values
				$new_uri = preg_replace('/\s+/', '', $new_uri);
				$new_uri = (!empty($new_uri)) ? trim($new_uri, "/") : $default_uri;
				$new_slug = (strpos($new_uri, '/') !== false) ? substr($new_uri, strrpos($new_uri, '/') + 1) : $new_uri;

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri}\n");

				if($new_uri != $old_uri) {
					$old_uris[$id] = $new_uri;
					$updated_array[] = array('item_title' => get_the_title($id), 'ID' => $id, 'old_uri' => $old_uri, 'new_uri' => $new_uri);
					$updated_slugs_count++;
				}

				do_action('permalink-manager-updated-post-uri', $id, $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving & append the global
			$old_uris = $permalink_manager_uris = array_filter($old_uris);
			update_option('permalink-manager-uris', $old_uris);

			//print_r($permalink_manager_uris);

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
		}

		return ($output) ? $output : "";
	}

	/**
	* Allow to edit URIs from "Edit Post" admin pages
	*/
	function edit_uri_box($html, $id, $new_title, $new_slug, $post) {
		global $permalink_manager_uris, $permalink_manager_options;

		// Detect auto drafts
		$autosave = (!empty($new_title) && empty($new_slug)) ? true : false;

		// Do not do anything if new slug is empty or page is front-page
		if(get_option('page_on_front') == $id) { return $html; }

		$html = preg_replace("/(<strong>(.*)<\/strong>)(.*)/is", "$1 ", $html);
		$default_uri = self::get_default_post_uri($id);
		$native_uri = self::get_default_post_uri($id, true);

		// Make sure that home URL ends with slash
		$home_url = trim(get_option('home'), "/") . "/";
		$prefix = apply_filters('permalink-manager-post-permalink-prefix', '', $post, true);

		// Do not change anything if post is not saved yet (display sample permalink instead)
		if($autosave || empty($post->post_status)) {
			$sample_permalink_url = $default_uri;
		} else {
			//$uri = $sample_permalink_url = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $default_uri;
			$uri = $sample_permalink_url = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $native_uri;
		}

		// Decode URI & allow to filter it
		$sample_permalink_url = apply_filters('permalink_manager_filter_post_sample_permalink', urldecode($sample_permalink_url), $post);

		// Prepare the sample & default permalink
		$sample_permalink = sprintf("{$home_url}{$prefix}<span class=\"editable\">%s</span>", str_replace("//", "/", $sample_permalink_url));

		// Allow to filter the sample permalink URL

		// Append new HTML output
		$html .= sprintf("<span class=\"sample-permalink-span\"><a href=\"%s\">%s</a></span>&nbsp;", strip_tags($sample_permalink), $sample_permalink);

		if(!empty($uri)) {
			$html .= Permalink_Manager_Admin_Functions::display_uri_box($post, $default_uri, $uri, $native_uri, "{$home_url}{$prefix}");
		}

		// Append hidden field with native slug
		$html .= (!empty($post->post_name)) ? "<input id=\"new-post-slug\" value=\"{$post->post_name}\" autocomplete=\"off\" type=\"hidden\">" : "";

		return $html;
	}

	/**
	 * "Quick Edit" form
	 */
	function quick_edit_column($columns) {
		return array_merge($columns, array('permalink-manager-col' => __( 'Current URI', 'permalink-manager')));
	}

	function quick_edit_column_content($column_name, $post_id) {
		if($column_name != "permalink-manager-col") { return; }

		$html = self::get_post_uri($post_id);
		echo $html;
	}

	function quick_edit_hide_column($hidden, $screen) {
		$hidden[] = 'permalink-manager-col';
		return $hidden;
	}

	function quick_edit_column_form($column_name, $post_type, $taxonomy = '') {
		if(!$taxonomy && $column_name == 'permalink-manager-col') {
			echo Permalink_Manager_Admin_Functions::quick_edit_column_form();
		}
	}

	/**
	* Update URI when new post is added
	*/
	function new_post_uri($post_id) {
		global $permalink_manager_uris, $permalink_manager_options, $permalink_manager_before_sections_html;

		// Do not do anything if post is autosaved
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

		// Do not do anything on in "Bulk Edit"
		if(!empty($_REQUEST['bulk_edit'])) { return $post_id; }

		// Hotfix
		if(isset($_POST['custom_uri']) || isset($_POST['permalink-manager-quick-edit'])) { return; }

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		// Hotfix for menu items
		if($post->post_type == 'nav_menu_item') { return; }

		$native_uri = self::get_default_post_uri($post_id, true);
		$new_uri = self::get_default_post_uri($post_id);;
		$permalink_manager_uris[$post->ID] = $new_uri;

		update_option('permalink-manager-uris', $permalink_manager_uris);

		do_action('permalink-manager-new-post-uri', $post_id, $new_uri, $native_uri);
	}

	/**
	* Update URI from "Edit Post" admin page
	*/
	function update_post_uri($post_id) {
		global $permalink_manager_uris, $permalink_manager_options, $permalink_manager_before_sections_html;

		// Do not do anything if post is autosaved
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

		// Do not do anything on in "Bulk Edit"
		if(!empty($_REQUEST['bulk_edit'])) { return $post_id; }

		// Do not do anything if the field with URI is not present
		if(!isset($_POST['custom_uri'])) { return $post_id; }

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		// Hotfix for menu items
		if($post->post_type == 'nav_menu_item') { return; }

		// Ignore auto-drafts & removed posts
		if(in_array($post->post_status, array('auto-draft', 'trash'))) { return; }

		// Get auto-update URI setting (if empty use global setting)
		$auto_update_uri_current = (!empty($_POST["auto_update_uri"])) ? intval($_POST["auto_update_uri"]) : 0;
		$auto_update_uri = (!empty($_POST["auto_update_uri"])) ? $auto_update_uri_current : $permalink_manager_options["general"]["auto_update_uris"];

		$default_uri = self::get_default_post_uri($post_id);
		$native_uri = self::get_default_post_uri($post_id, true);
		$old_uri = (isset($permalink_manager_uris[$post->ID])) ? $permalink_manager_uris[$post->ID] : $native_uri;

		// Use default URI if URI is cleared by user OR URI should be automatically updated
		$new_uri = (($_POST['custom_uri'] == '') || $auto_update_uri == 1) ? $default_uri : Permalink_Manager_Helper_Functions::sanitize_title($_POST['custom_uri']);

		// Save or remove "Auto-update URI" settings
		if(!empty($auto_update_uri_current)) {
			update_post_meta($post_id, "auto_update_uri", $auto_update_uri_current);
		} elseif(isset($_POST['auto_update_uri'])) {
			delete_post_meta($post_id, "auto_update_uri");
		}

		// Save only changed URIs
		if($new_uri != $old_uri) {
			$permalink_manager_uris[$post->ID] = $new_uri;
			update_option('permalink-manager-uris', $permalink_manager_uris);
		}

		do_action('permalink-manager-updated-post-uri', $post_id, $new_uri, $old_uri, $native_uri, $default_uri);
	}

	/**
	* Remove URI from options array after post is moved to the trash
	*/
	function remove_post_uri($post_id) {
		global $permalink_manager_uris;

		// Check if the custom permalink is assigned to this post
		if(isset($permalink_manager_uris[$post_id])) {
			unset($permalink_manager_uris[$post_id]);
		}

		update_option('permalink-manager-uris', $permalink_manager_uris);
	}

}

?>
