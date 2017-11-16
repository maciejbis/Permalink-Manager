<?php
/**
* Additional hooks for "Permalink Manager Pro"
*/
class Permalink_Manager_Actions extends Permalink_Manager_Class {

	public function __construct() {
		add_action( 'admin_init', array($this, 'trigger_action'), 999 );
		add_action( 'admin_init', array($this, 'extra_actions') );
	}

	/**
	* Actions
	*/
	public function trigger_action() {
		global $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		// 1. Check if the form was submitted
		if(empty($_POST)) { return; }

		$actions_map = array(
			'uri_editor' => array('function' => 'update_all_permalinks', 'display_uri_table' => true),
			'regenerate' => array('function' => 'regenerate_all_permalinks', 'display_uri_table' => true),
			'find_and_replace' => array('function' => 'find_and_replace', 'display_uri_table' => true),
			'permalink_manager_options' => array('function' => 'save_settings'),
			'permalink_manager_permastructs' => array('function' => 'save_permastructures'),
			'flush_sitemaps' => array('function' => 'save_permastructures'),
			'import' => array('function' => 'import_custom_permalinks_uris'),
		);
		// Clear URIs & reset settings & permastructs also should be added here.

		// 2. Find the action
		foreach($actions_map as $action => $map) {
			if(isset($_POST[$action]) && wp_verify_nonce($_POST[$action], 'permalink-manager')) {
				$output = call_user_func(array($this, $map['function']));

				// Get list of updated URIs
				if(!empty($map['display_uri_table'])) {
					$updated_slugs_count = (isset($output['updated_count']) && $output['updated_count'] > 0) ? $output['updated_count'] : false;
					$updated_slugs_array = ($updated_slugs_count) ? $output['updated'] : '';
				}

				// Trigger only one function
				break;
			}
		}

		// 3. Display the slugs table (and append the globals)
		if(isset($updated_slugs_count)) {
			if($updated_slugs_count > 0) {
				$updated_title = __('List of updated items', 'bis');
				$alert_content = sprintf( _n( '<strong>%d</strong> slug was updated!', '<strong>%d</strong> slugs were updated!', $updated_slugs_count, 'permalink-manager' ), $updated_slugs_count ) . ' ';
				$alert_content .= sprintf( __( '<a %s>Click here</a> to go to the list of updated slugs', 'permalink-manager' ), "href=\"#updated-list\" title=\"{$updated_title}\" class=\"thickbox\"");

				$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message($alert_content, 'updated');
				$permalink_manager_after_sections_html .= Permalink_Manager_Admin_Functions::display_updated_slugs($updated_slugs_array);
			} else {
				$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( '<strong>No slugs</strong> were updated!', 'permalink-manager' ), 'error');
			}
		}
	}

	/**
	* Save settings
	*/
	public static function save_settings($field = false, $value = false) {
		global $permalink_manager_options, $permalink_manager_before_sections_html;

		// Info: The settings array is used also by "Screen Options"
		$new_options = $permalink_manager_options;
		//$new_options = array();

		// Save only selected field/sections
		if($field && $value) {
			$new_options[$field] = $value;
		} else {
			$post_fields = $_POST;

			foreach($post_fields as $option_name => $option_value) {
				$new_options[$option_name] = $option_value;
			}
		}

		// Sanitize & override the global with new settings
		$new_options = Permalink_Manager_Helper_Functions::sanitize_array($new_options);
		$permalink_manager_options = $new_options = array_filter($new_options);

		// Save the settings in database
		update_option('permalink-manager', $new_options);

		// Display the message
		$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'The settings are saved!', 'permalink-manager' ), 'updated');
	}

	/**
	 * Additional actions
	 */
	public static function extra_actions() {
		if(isset($_GET['flush_sitemaps'])) {
			self::flush_sitemaps();
		} else if(isset($_GET['clear-permalink-manager-uris'])) {
			self::clear_all_uris();
		} else if(!empty($_REQUEST['remove-uri'])) {
			$uri_key = sanitize_text_field($_REQUEST['remove-uri']);
			self::force_clear_single_element_uris_and_redirects($uri_key);
		} else if(!empty($_POST['screen-options-apply'])) {
			self::save_screen_options();
		}
	}

	/**
	 * Save "Screen Options"
	 */
	public static function save_screen_options() {
		check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

		// The values will be sanitized inside the function
		self::save_settings('screen-options', $_POST['screen-options']);
	}

	/**
	* Save permastructures
	*/
	public static function save_permastructures() {
		global $permalink_manager_permastructs;

		$post_fields = $_POST;
		$new_options = array();

		foreach($post_fields as $option_name => $option_value) {
			$new_options[$option_name] = $option_value;
		}

		// Trim the trailing slashes & remove empty permastructures
		$new_options = Permalink_Manager_Helper_Functions::multidimensional_array_map('untrailingslashit', $new_options);
		foreach($new_options as $group_name => $group) {
			if(is_array($group)) {
				foreach($group as $element => $permastruct) {
					// Trim slashes
					$permastruct = trim($permastruct, "/");

					// Do not store default permastructures
					// $default_permastruct = ($group_name == 'post_types') ? Permalink_Manager_Helper_Functions::get_default_permastruct($element, true) : "";
					// if($permastruct == $default_permastruct) { unset($group[$element]); }
				}
				// Do not store empty permastructures
				// $new_options[$group_name] = array_filter($group);
			} else {
				unset($new_options[$group_name]);
			}
		}

		// Override the global with settings
		// $permalink_manager_permastructs = $new_options = $new_options;
		$permalink_manager_permastructs = $new_options;

		// Save the settings in database
		update_option('permalink-manager-permastructs', $new_options);
	}

	/**
	 * Clear URIs
	 */
	public static function clear_all_uris() {
		global $permalink_manager_uris, $permalink_manager_redirects, $wpdb, $permalink_manager_before_sections_html;

		// Check if array with custom URIs exists
		if(empty($permalink_manager_uris)) { return; }

		// Count removed URIs & redirects
		$removed_uris = 0;
		$removed_redirects = 0;

		foreach($permalink_manager_uris as $element_id => $uri) {
			$count = self::clear_single_element_uris_and_redirects($element_id, true);

			$removed_uris = (!empty($count[0])) ? $count[0] + $removed_uris : $removed_uris;
			$removed_redirects = (!empty($count[1])) ? $count[1] + $removed_redirects : $removed_redirects;
		}

		// Save cleared URIs & Redirects
		if($removed_uris > 0 || $removed_redirects > 0) {
			update_option('permalink-manager-uris', array_filter($permalink_manager_uris));
			update_option('permalink-manager-redirects', array_filter($permalink_manager_redirects));

			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(sprintf(__( '%d Custom URIs and %d Custom Redirects were removed!', 'permalink-manager' ), $removed_uris, $removed_redirects), 'updated');
		} else {
			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'No Custom URIs or Custom Redirects were removed!', 'permalink-manager' ), 'error');
		}
	}

	/**
	 * Check if the post/term uses the same URI for both permalink & custom redirects
	 */
	public static function clear_single_element_duplicated_redirect($element_id, $count_removed = false) {
		global $permalink_manager_uris, $permalink_manager_redirects;

		if(!empty($permalink_manager_uris[$element_id]) && !empty($permalink_manager_redirects[$element_id])) {
			$custom_uri = $permalink_manager_uris[$element_id];

			if(in_array($custom_uri, $permalink_manager_redirects[$element_id])) {
				$duplicated_redirect_id = array_search($custom_uri, $permalink_manager_redirects[$element_id]);
				unset($permalink_manager_redirects[$element_id][$duplicated_redirect_id]);
			}
		}

		// Check if function should only return the counts or update
		if($count_removed) {
			return (isset($duplicated_redirect_id)) ? 1 : 0;
		} else if(isset($duplicated_redirect_id)) {
			update_option('permalink-manager-redirects', array_filter($permalink_manager_redirects));
			return true;
		}
	}

	/**
	 * Remove unused custom URI & redirects for deleted post or term
	 */
 	public static function clear_single_element_uris_and_redirects($element_id, $count_removed = false) {
		global $wpdb, $permalink_manager_uris, $permalink_manager_redirects;

		// Count removed URIs & redirects
		$removed_uris = 0;
		$removed_redirects = 0;

		// 1. Check if element exists
		if(is_numeric($element_id)) {
			$post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->prefix}posts WHERE ID = {$element_id} AND post_status NOT IN ('auto-draft', 'trash') AND post_type != 'nav_menu_item'");

			// Remove custom URIs for removed, auto-draft posts or disabled post types
			$remove = (!empty($post_type)) ? Permalink_Manager_Helper_Functions::is_disabled($post_type, 'post_type') : true;
		} else if(strpos($element_id, 'tax-') !== false) {
			$term_id = preg_replace("/[^0-9]/", "", $element_id);
			$taxonomy = $wpdb->get_var($wpdb->prepare("SELECT t.taxonomy FROM $wpdb->term_taxonomy AS t WHERE t.term_id = %s LIMIT 1", $term_id));

			// Remove custom URIs for removed terms or disabled taxonomies
			$remove = (!empty($taxonomy)) ? Permalink_Manager_Helper_Functions::is_disabled($taxonomy) : true;
		}

		// 2A. Remove ALL unused custom permalinks & redirects
		if(!empty($remove)) {
			// Remove URI
			if(!empty($permalink_manager_uris[$element_id])) {
				$removed_uris = 1;
				unset($permalink_manager_uris[$element_id]);
			}

			// Remove all custom redirects
			if(!empty($permalink_manager_redirects[$element_id]) && is_array($permalink_manager_redirects[$element_id])) {
				$removed_redirects = count($permalink_manager_redirects[$element_id]);
				unset($permalink_manager_redirects[$element_id]);;
			}
		}
		// 2B. Check if the post/term uses the same URI for both permalink & custom redirects
		else {
			$removed_redirect = self::clear_single_element_duplicated_redirect($element_id, true);
			$removed_redirects = (!empty($removed_redirect)) ? 1 : 0;
		}

		// Check if function should only return the counts or update
		if($count_removed) {
			return array($removed_uris, $removed_redirects);
		} else if(!empty($removed_uris) || !empty($removed_redirects)) {
			update_option('permalink-manager-uris', array_filter($permalink_manager_uris));
			update_option('permalink-manager-redirects', array_filter($permalink_manager_redirects));
			return true;
		}
 	}

	/**
	 * Remove custom URI & redirects for any requested post or term
	 */
	public static function force_clear_single_element_uris_and_redirects($uri_key) {
		global $permalink_manager_uris, $permalink_manager_redirects, $permalink_manager_before_sections_html;

		// Check if custom URI is set
		if(isset($permalink_manager_uris[$uri_key])) {
			$uri = $permalink_manager_uris[$uri_key];

			unset($permalink_manager_uris[$uri_key]);
			update_option('permalink-manager-uris', $permalink_manager_uris);

			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(sprintf(__( 'URI "%s" was removed successfully!', 'permalink-manager' ), $uri), 'updated');
			$updated = true;
		}

		// Check if custom redirects are set
		if(isset($permalink_manager_redirects[$uri_key])) {
			unset($permalink_manager_redirects[$uri_key]);
			update_option('permalink-manager-redirects', $permalink_manager_redirects);

			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'Broken redirects were removed successfully!', 'permalink-manager' ), 'updated');
			$updated = true;
		}

		if(empty($updated)) {
			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'URI and/or custom redirects does not exist or were already removed!', 'permalink-manager' ), 'error');
		}
	}

	/**
	* "Find and replace" in "Tools"
	*/
	function find_and_replace() {
		// Check if posts or terms should be updated
		if(!empty($_POST['content_type']) && $_POST['content_type'] == 'taxonomies') {
			return Permalink_Manager_URI_Functions_Tax::find_and_replace();
		} else {
			return Permalink_Manager_URI_Functions_Post::find_and_replace();
		}
	}

	/**
	* Regenerate all permalinks in "Tools"
	*/
	function regenerate_all_permalinks() {
		// Check if posts or terms should be updated
		if(!empty($_POST['content_type']) && $_POST['content_type'] == 'taxonomies') {
			return Permalink_Manager_URI_Functions_Tax::regenerate_all_permalinks();
		} else {
			return Permalink_Manager_URI_Functions_Post::regenerate_all_permalinks();
		}
	}

	/**
	* Update all permalinks in "Permalink Editor"
	*/
	function update_all_permalinks() {
		// Check if posts or terms should be updated
		if(!empty($_POST['content_type']) && $_POST['content_type'] == 'taxonomies') {
			return Permalink_Manager_URI_Functions_Tax::update_all_permalinks();
		} else {
			return Permalink_Manager_URI_Functions_Post::update_all_permalinks();
		}
	}

	/**
	 * Clear sitemaps cache
	 */
	function flush_sitemaps($types = array()) {
		global $permalink_manager_before_sections_html;

		if(class_exists('WPSEO_Sitemaps_Cache')) {
			$sitemaps = WPSEO_Sitemaps_Cache::clear($types);

			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'Sitemaps were updated!', 'permalink-manager' ), 'updated');
		}
	}

	/**
	 * Import old URIs from "Custom Permalinks" (Pro)
	 */
	function import_custom_permalinks_uris() {
		Permalink_Manager_Third_Parties::import_custom_permalinks_uris();
	}

}

?>
