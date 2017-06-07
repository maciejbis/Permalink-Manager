<?php
/**
* Additional hooks for "Permalink Manager Pro"
*/
class Permalink_Manager_Actions extends Permalink_Manager_Class {

	public function __construct() {
		add_action( 'admin_init', array($this, 'trigger_action'), 999 );
		add_action( 'admin_init', array($this, 'clear_uris') );

		// Screen Options
		add_action( 'admin_init', array($this, 'save_screen_options'), 999 );
	}

	/**
	* Actions
	*/
	public function trigger_action() {
		global $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		// 1. Check if the form was submitted (make exception for clear sitemap cache function)
		if(isset($_REQUEST['flush_sitemaps'])) {
			$this->flush_sitemaps();
			$permalink_manager_before_sections_html .= Permalink_Manager_Admin_Functions::get_alert_message(__( 'Sitemap was updated!', 'permalink-manager' ), 'updated');

			return;
		} else if(empty($_POST)) { return; }

		$actions_map = array(
			'uri_editor' => array('function' => 'update_all_permalinks', 'display_uri_table' => true),
			'regenerate' => array('function' => 'regenerate_all_permalinks', 'display_uri_table' => true),
			'find_and_replace' => array('function' => 'find_and_replace', 'display_uri_table' => true),
			'permalink_manager_options' => array('function' => 'save_settings'),
			'permalink_manager_permastructs' => array('function' => 'save_permastructures'),
			'flush_sitemaps' => array('function' => 'save_permastructures'),
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
				$alert_content .= sprintf( __( '<a %s>Click here</a> to go to the list of updated slugs', 'permalink-manager' ), "href=\"#TB_inline?width=100%&height=600&inlineId=updated-list\" title=\"{$updated_title}\" class=\"thickbox\"");

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
	 * Save "Screen Options"
	 */
	public static function save_screen_options() {
		if(!empty($_POST['screen-options-apply'])) {
			check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

			// The values will be sanitized inside the function
			self::save_settings('screen-options', $_POST['screen-options']);
		}
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
					$default_permastruct = ($group_name == 'post_types') ? Permalink_Manager_Helper_Functions::get_default_permastruct($element, true) : "";
					if($permastruct == $default_permastruct) { unset($group[$element]); }
				}
				// Do not store empty permastructures
				$new_options[$group_name] = array_filter($group);
			} else {
				unset($new_options[$group_name]);
			}
		}

		// Override the global with settings
		$permalink_manager_permastructs = $new_options = array_filter($new_options);

		// Save the settings in database
		update_option('permalink-manager-permastructs', $new_options);
	}

	/**
	* Remove URI from options array after post is moved to the trash
	*/
	function clear_uris($post_id) {
		global $permalink_manager_uris;

		if(isset($_GET['clear-permalink-manager-uris']) && !empty($permalink_manager_uris)) {
			foreach($permalink_manager_uris as $post_id => $uri) {
				// Loop only through post URIs
				if(is_numeric($post_id)) {
					$post_status = get_post_status($post_id);
					if(in_array($post_status, array('auto-draft', 'trash', ''))) {
						unset($permalink_manager_uris[$post_id]);
					}
				}
			}

			update_option('permalink-manager-uris', $permalink_manager_uris);
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
		// Reset sitemap's cache
		if(class_exists('WPSEO_Sitemaps_Cache')) {
			$sitemaps = WPSEO_Sitemaps_Cache::clear($types);
		}
	}

}

?>
