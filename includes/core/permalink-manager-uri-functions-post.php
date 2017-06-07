<?php

/**
* Additional functions used in classes and another subclasses
*/
class Permalink_Manager_URI_Functions_Post extends Permalink_Manager_Class {

	public function __construct() {
		add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 999, 5 );

		add_filter( '_get_page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'page_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_link', array($this, 'custom_post_permalinks'), 999, 2);
		add_filter( 'post_type_link', array($this, 'custom_post_permalinks'), 999, 2);

		add_filter( 'permalink-manager-uris', array($this, 'exclude_homepage'), 999, 2);
		add_action( 'save_post', array($this, 'update_post_uri'), 999, 3 );
		add_action( 'wp_trash_post', array($this, 'remove_post_uri'), 10, 3 );

		add_filter( 'permalink_manager_filter_default_post_slug', array($this, 'force_custom_slugs'), 5, 3 );
	}

	/**
	* Change permalinks for posts, pages & custom post types
	*/
	function custom_post_permalinks($permalink, $post) {
		global $wp_rewrite, $permalink_manager_uris;

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

		// 2. Apend the language code as a non-editable prefix (can be used also for another prefixes)
		$prefix = apply_filters('permalink-manager-post-permalink-prefix', '', $post);

		// 3. Filter only the posts with custom permalink assigned
		if(isset($permalink_manager_uris[$post->ID])) { $permalink = get_option('home') . "/{$prefix}{$permalink_manager_uris[$post->ID]}"; }

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
		$full_slug = get_page_uri($post);
		$full_slug = (empty($full_slug)) ? $post_name : $full_slug;
		$full_slug = ($native_uri == false) ? apply_filters('permalink_manager_filter_default_post_slug', $full_slug, $post, $post_name) : $full_slug;
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
				$new_uri = ($mode == 'slugs') ? $old_uri : $default_uri;

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
			$sample_permalink = $default_uri;
		} else {
			//$uri = $sample_permalink = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $default_uri;
			$uri = $sample_permalink = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $native_uri;
		}

		// Prepare the sample & default permalink
		$sample_permalink = sprintf("{$home_url}{$prefix}<span class=\"editable\">%s</span>", str_replace("//", "/", trim($sample_permalink, "/")));

		// Append new HTML output
		$html .= sprintf("<span class=\"sample-permalink-span\"><a href=\"%s\">%s</a></span>&nbsp;", strip_tags($sample_permalink), $sample_permalink);

		if(!empty($uri)) {
			// Auto-update settings
			$auto_update_val = get_post_meta($id, "auto_update_uri", true);
			$auto_update_def_val = $permalink_manager_options["general"]["auto_update_uris"];
			$auto_update_def_label = ($auto_update_def_val) ? __("Yes", "permalink-manager") : __("No", "permalink-manager");
			$auto_update_choices = array(
				0 => array("label" => sprintf(__("Use global settings [%s]", "permalink-manager"), $auto_update_def_label), "atts" => "data-auto-update=\"{$auto_update_def_val}\""),
				-1 => array("label" => __("No", "permalink-manager"), "atts" => "data-auto-update=\"0\""),
				1 => array("label" => __("Yes", "permalink-manager"), "atts" => "data-auto-update=\"1\"")
			);

			// Add the "edit buttons"
			$html .= sprintf("<span><button type=\"button\" class=\"button button-small hide-if-no-js\" id=\"permalink-manager-toggle\">%s</button></span>", __("Permalink Manager", "permalink-manager"));
			$html .= "<div id=\"permalink-manager\" class=\"postbox permalink-manager-edit-uri-box\" style=\"display: none;\">";

			// The heading
			$html .= "<a class=\"close-button\"><span class=\"screen-reader-text\">" . __("Close: ", "permalink-manager") . __("Permalink Manager", "permalink-manager") . "</span><span class=\"close-icon\" aria-hidden=\"false\"></span></a>";
			$html .= sprintf("<h2><span>%s</span></h2>", __("Permalink Manager", "permalink-manager"));

			// The fields
			$html .= "<div class=\"inside\">";
			$html .= sprintf("<p><label for=\"custom_uri\" class=\"strong\">%s %s</label><span>%s</span></p>",
			 	__("Current URI", "permalink-manager"),
				Permalink_Manager_Admin_Functions::help_tooltip(__("The custom URI can be edited only if 'Auto-update the URI' feature is not enabled.", "permalink-manager")),
				Permalink_Manager_Admin_Functions::generate_option_field("custom_uri", array("extra_atts" => "data-default-uri=\"{$default_uri}\"", "input_class" => "widefat custom_uri", "value" => $uri))
			);
			$html .= sprintf("<p><label for=\"auto_auri\" class=\"strong\">%s %s</label><span>%s</span></p>",
				__("Auto-update the URI", "permalink-manager"),
				Permalink_Manager_Admin_Functions::help_tooltip(__("If enabled, the 'Current URI' field will be automatically changed to 'Default URI' (displayed below) after the post is saved or updated.", "permalink-manager")),
				Permalink_Manager_Admin_Functions::generate_option_field("auto_update_uri", array("type" => "select", "input_class" => "widefat auto_update", "value" => $auto_update_val, "choices" => $auto_update_choices))
			);
			$html .= sprintf(
				"<p class=\"default-permalink-row columns-container\"><span class=\"column-3_4\"><strong>%s:</strong> %s</span><span class=\"column-1_4\"><a href=\"#\" id=\"restore-default-uri\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span></p>",
				__("Default URI", "permalink-manager"), esc_html($default_uri),
				__("Restore to Default URI", "permalink-manager")
			);
			$html .= "</div>";

			$html .= "</div>";
		}

		return $html;
	}

	/**
	* Update URI from "Edit Post" admin page
	*/
	function update_post_uri($post_id, $post, $update) {
		global $permalink_manager_uris, $permalink_manager_options, $permalink_manager_before_sections_html;

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		// Ignore auto-drafts & removed posts
		//if(in_array($post->post_status, array('auto-draft', 'trash')) || empty($post->post_name) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
		if(in_array($post->post_status, array('auto-draft', 'trash')) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }

		// Get auto-update URI setting (if empty use global setting)
		$auto_update_uri_current = (!empty($_POST["auto_update_uri"])) ? intval($_POST["auto_update_uri"]) : 0;
		$auto_update_uri = (!empty($_POST["auto_update_uri"])) ? $auto_update_uri_current : $permalink_manager_options["general"]["auto_update_uris"];

		$default_uri = self::get_default_post_uri($post_id);
		$native_uri = self::get_default_post_uri($post_id, true);
		$old_uri = (isset($permalink_manager_uris[$post->ID])) ? $permalink_manager_uris[$post->ID] : $native_uri;

		// Use default URI if URI is cleared by user OR URI should be automatically updated
		$new_uri = (empty($_POST['custom_uri']) || $auto_update_uri == 1) ? $default_uri : trim($_POST['custom_uri'], "/");

		// Do not store default values
		if(isset($permalink_manager_uris[$post->ID]) && ($new_uri == $native_uri)) {
			unset($permalink_manager_uris[$post->ID]);
		}
		// Save only changed URIs
		else if (($new_uri != $native_uri) && ($new_uri != $old_uri)) {
			$permalink_manager_uris[$post->ID] = $new_uri;
		}

		// Save or remove "Auto-update URI" settings
		if(!empty($auto_update_uri_current)) {
			update_post_meta($post_id, "auto_update_uri", $auto_update_uri_current);
		} else {
			delete_post_meta($post_id, "auto_update_uri");
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

	/**
	 * Force custom slugs
	 */
	public function force_custom_slugs($slug, $object, $name) {
		global $permalink_manager_options;

		if(!empty($permalink_manager_options['general']['force_custom_slugs'])) {
			$old_slug = basename($slug);
			$new_slug = sanitize_title($object->post_title);

			$slug = ($old_slug != $new_slug) ? str_replace($old_slug, $new_slug, $slug) : $slug;
		}

		return $slug;
	}

}

?>
