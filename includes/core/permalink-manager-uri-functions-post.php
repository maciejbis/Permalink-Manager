<?php

/**
* Additional functions used in classes and another subclasses
*/
class Permalink_Manager_URI_Functions_Post extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 999, 4 );
		add_filter( '_get_page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_type_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'permalink-manager-uris', array($this, 'exclude_homepage'), 999, 2);
		add_action( 'save_post', array($this, 'update_post_uri'), 10, 3 );
		add_action( 'wp_trash_post', array($this, 'remove_post_uri'), 10, 3 );
	}

	/**
	* Change permalinks for posts, pages & custom post types
	*/
	function custom_post_permalinks($permalink, $post) {
		global $wp_rewrite, $permalink_manager_uris, $sitepress_settings;

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

		// 2. Apend the language code as a non-editable prefix
		if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 1) {
			$post_lang_details = apply_filters('wpml_post_language_details', NULL, $post->ID);
			$language_code = (!empty($post_lang_details['language_code'])) ? "{$post_lang_details['language_code']}/" : '';

			// Hide language code if "Use directory for default language" option is enabled
			$default_language = Permalink_Manager_Helper_Functions::get_language();
			if(isset($sitepress_settings['urls']['directory_for_default_language']) && ($sitepress_settings['urls']['directory_for_default_language'] == 0) && ($default_language == $post_lang_details['language_code'])) {
				$language_code = "";
			}
		} else {
			$language_code = "";
		}

		// 3. Filter only the posts with custom permalink assigned
		if(isset($permalink_manager_uris[$post->ID])) { $permalink = get_option('home') . "/{$language_code}{$permalink_manager_uris[$post->ID]}"; }

		// 4. Additional filter
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
		global $permalink_manager_options, $permalink_manager_uris, $permalink_manager_permastructs, $sitepress_settings;

		// Load all bases & post
		$post = is_object($post) ? $post : get_post($post);

		// Check if post ID is defined
		if(empty($post->ID)) { return ''; }
		$post_id = $post->ID;
		$post_type = $post->post_type;
		$post_name = $post->post_name;

		// Get the permastruct
		$default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type);
		if($native_uri) {
			$permastruct = $default_permastruct;
		} else {
			$permastruct = (!empty($permalink_manager_permastructs['post_types'][$post_type])) ? $permalink_manager_permastructs['post_types'][$post_type] : $default_permastruct;
		}

		$default_base = (!empty($permastruct)) ? trim($permastruct, '/') : "";

		// 1A. Get the date
		$date = explode(" ", date('Y m d H i s', strtotime($post->post_date)));

		// 1B. Get the author (if needed)
		$author = '';
		if ( strpos($default_base, '%author%') !== false ) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		// 2. Fix for hierarchical CPT (start)
		$full_slug = ($native_uri == false) ? apply_filters('permalink_manager_filter_default_post_slug', get_page_uri($post), $post) : get_page_uri($post);
		$post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);

		// 3A. Do the replacement (post tag is removed now to enable support for hierarchical CPT)
		$tags = array('%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%author%', $post_type_tag);
		$replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $author, '');
		$default_uri = str_replace($tags, $replacements, "{$default_base}/{$full_slug}");

		// 3B. Replace taxonomies
		$taxonomies = get_taxonomies();

		if($taxonomies) {
			foreach($taxonomies as $taxonomy) {
				// A. Try to use Yoast SEO Primary Term
				$replacement = Permalink_Manager_Helper_Functions::get_primary_term($post->ID, $taxonomy);

				// B. Get the first assigned term to this taxonomy
				if(empty($replacement)) {
					$terms = wp_get_object_terms($post->ID, $taxonomy);
					$replacement = (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) ? $terms[0]->slug : "";
				}

				// Do the replacement
				$default_uri = (!empty($replacement)) ? str_replace("%{$taxonomy}%", $replacement, $default_uri) : $default_uri;
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

		// Prepare default variables from $_POST object
		$old_string = str_replace($home_url, '', esc_sql($_POST['old_string']));
		$new_string = str_replace($home_url, '', esc_sql($_POST['new_string']));
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
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);
				$old_uri = (isset($permalink_manager_uris[$row['ID']])) ? $permalink_manager_uris[$row['ID']] : $default_uri;
				$old_slug = (strpos($old_uri, '/') !== false) ? substr($old_uri, strrpos($old_uri, '/') + 1) : $old_uri;

				// Do replacement on slugs (non-REGEX)
				if(preg_match("/^\/.+\/[a-z]*$/i", $old_string)) {
					// Use $_POST['old_string'] directly here & fix double slashes problem
					$pattern = "~" . stripslashes(trim($_POST['old_string'], "/")) . "~";

					$new_post_name = (in_array($mode, array('post_names'))) ? preg_replace($pattern, $new_string, $old_post_name) : $old_post_name;
					$new_uri = preg_replace($pattern, $new_string, $old_uri);
				}
				else {
					$new_post_name = (in_array($mode, array('post_names'))) ? str_replace($old_string, $new_string, $old_post_name) : $old_post_name; // Post name is changed only in first mode
					$new_uri = str_replace($old_string, $new_string, $old_uri);
				}

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n");

				// Check if native slug should be changed
				if(in_array($mode, array('post_names')) && ($old_post_name != $new_post_name)) {
					self::update_slug_by_id($new_post_name, $row['ID']);
				}

				if(($old_uri != $new_uri) || ($old_post_name != $new_post_name) && !(empty($new_uri))) {
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
	static function regenerate_all_permalinks() {
		global $wpdb, $permalink_manager_uris;

		// Check if post types & statuses are not empty
		if(empty($_POST['post_types']) || empty($_POST['post_statuses'])) { return false; }

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
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);
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
					self::update_slug_by_id($new_post_name, $row['ID']);
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
					$updated_array[] = array('post_title' => get_the_title($id), 'ID' => $id, 'old_uri' => $old_uri, 'new_uri' => $new_uri);
					$updated_slugs_count++;
				}

				// Do not store native URIs
				if($new_uri == $native_uri) {
					unset($old_uris[$id]);
				}

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
	function edit_uri_box($html, $id, $new_title, $new_slug) {
		global $post, $permalink_manager_uris, $sitepress_settings;

		// Try to alter the slug in auto-drafts ...
		$new_slug = (empty($new_slug) && !empty($new_title)) ? sanitize_title($new_title) : $new_slug;
		// ... and saved drafts
		$new_slug = (empty($new_slug) && !empty($post->post_title)) ? sanitize_title($post->post_title) : $new_slug;

		// Do not do anything if new slug is empty or page is front-page
		if(empty($new_slug) || get_option('page_on_front') == $id) { return $html; }

		$html = preg_replace("/(<strong>(.*)<\/strong>)(.*)/is", "$1 ", $html);
		$default_uri = self::get_default_post_uri($id);
		$default_uri .= (empty($post->post_name)) ? apply_filters("permalink_manager_filter_default_post_draft_slug", "/{$new_slug}", $id) : "";
		// Make sure that home URL ends with slash
		$home_url = trim(get_option('home'), "/") . "/";

		// WPML - apend the language code as a non-editable prefix
		if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 1) {
			$post_lang_details = apply_filters('wpml_post_language_details', NULL, $id);
			$language_code = (!empty($post_lang_details['language_code'])) ? $post_lang_details['language_code'] : '';

			// Last instance - use language paramater from &_GET array
			$language_code = (empty($language_code) && !empty($_GET['lang'])) ? $_GET['lang'] : $language_code;

			// Hide language code if "Use directory for default language" option is enabled
			$default_language = Permalink_Manager_Helper_Functions::get_language();
			if(isset($sitepress_settings['urls']['directory_for_default_language']) && ($sitepress_settings['urls']['directory_for_default_language'] == 0) && ($default_language == $language_code)) {
				$language_code = '';
			}
		} else {
			$language_code = "";
		}

		// Append slash to the end of language code if it is not empty
		$language_code .= ($language_code) ? "/" : "";

		// Do not change anything if post is not saved yet (display sample permalink instead)
		if(empty($post->post_status) || $post->post_status == 'auto-draft') {
			$sample_permalink = $home_url . str_replace("//", "/", trim("{$language_code}{$default_uri}", "/"));

			$html .= "<span><a href=\"{$sample_permalink}\">{$sample_permalink}</a></span>";
		} else {
			$uri = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $default_uri;
			$html .= "{$home_url}{$language_code} <span id=\"editable-post-name\"><input type='text' value='{$uri}' name='custom_uri'/></span>";
		}

		return $html;
	}

	/**
	* Update URI from "Edit Post" admin page
	*/
	function update_post_uri($post_id, $post, $update) {
		global $permalink_manager_uris;

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		// Ignore auto-drafts & removed posts
		if(in_array($post->post_status, array('auto-draft', 'trash')) || !isset($_POST['custom_uri'])) { return; }

		$default_uri = self::get_default_post_uri($post_id);
		$native_uri = self::get_default_post_uri($post_id, true);
		$old_uri = (isset($permalink_manager_uris[$post->ID])) ? $permalink_manager_uris[$post->ID] : $native_uri;

		// Use default URI if URI is cleared by user
		$new_uri = (!empty($_POST['custom_uri'])) ? trim($_POST['custom_uri'], "/") : $default_uri;

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
