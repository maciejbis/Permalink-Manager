<?php

/**
* Plugin Name:       Permalink Manager
* Plugin URI:        https://permalinkmanager.pro?utm_source=plugin
* Description:       Most advanced Permalink utility for Wordpress. It allows to bulk edit the permalinks & permastructures and regenerate/reset all the URIs in your Wordpress instance.
* Version:           1.1.0
* Author:            Maciej Bis
* Author URI:        http://maciejbis.net/
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       permalink-manager
* Domain Path:       /languages
*/

// If this file is called directly or plugin is already defined, abort.
if (!defined('WPINC')) {
	die;
}

// Define the directories used to load plugin files.
define( 'PERMALINK_MANAGER_PLUGIN_NAME', 'Permalink Manager' );
define( 'PERMALINK_MANAGER_PLUGIN_SLUG', 'permalink-manager' );
define( 'PERMALINK_MANAGER_VERSION', '1.1.0' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_BASENAME', plugin_basename(__FILE__) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://permalinkmanager.pro?utm_source=plugin' );
define( 'PERMALINK_MANAGER_DONATE', 'https://www.paypal.me/Bismit' );

class Permalink_Manager_Class {

	public $permalink_manager, $permalink_manager_options_page, $permalink_manager_options;
	public $sections, $functions, $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

	/**
	* Get options from DB, load subclasses & hooks
	*/
	public function __construct() {
		$this->include_subclassess();
		$this->register_init_hooks();
	}

	/**
	* Include back-end classess and set their instances
	*/
	function include_subclassess() {
		// WP_List_Table needed for post types & taxnomies editors
		if( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		$classes = array(
			'core' => array(
				'helper-functions' => 'Permalink_Manager_Helper_Functions',
				'uri-functions-post' => 'Permalink_Manager_URI_Functions_Post',
				'uri-functions-tax' => 'Permalink_Manager_URI_Functions_Tax',
				'admin-functions' => 'Permalink_Manager_Admin_Functions',
				'actions' => 'Permalink_Manager_Actions',
				'third-parties' => 'Permalink_Manager_Third_Parties',
				'pro-functions' => 'Permalink_Manager_Pro_Functions'
			),
			'views' => array(
				'uri-editor' => 'Permalink_Manager_Uri_Editor',
				'tools' => 'Permalink_Manager_Tools',
				'permastructs' => 'Permalink_Manager_Permastructs',
				'settings' => 'Permalink_Manager_Settings',
				'debug' => 'Permalink_Manager_Debug',
				'pro-addons' => 'Permalink_Manager_Pro_Addons',
				'uri-editor-tax' => false,
				'uri-editor-post' => false
			)
		);

		// Load classes and set-up their instances
		foreach($classes as $class_type => $classes_array) {
			foreach($classes_array as $class => $class_name) {
				$filename = PERMALINK_MANAGER_DIR . "/includes/{$class_type}/permalink-manager-{$class}.php";

				if(file_exists($filename)) {
					require_once $filename;
					if($class_name) { $this->functions[$class] = new $class_name(); }
				}
			}
		}
	}

	/**
	* Register general hooks
	*/
	public function register_init_hooks() {
		// Localize plugin
		add_action( 'plugins_loaded', array($this, 'localize_me'), 1 );

		// Load options
		add_action( 'init', array($this, 'get_options_and_globals'), 1 );

		// Use the URIs set in this plugin + redirect from old URIs to new URIs + adjust canonical redirect settings
		add_action( 'wp', array($this, 'disable_canonical_redirect'), 0, 1 );
		add_action( 'template_redirect', array($this, 'redirect_to_new_uri'), 999);
		add_filter( 'request', array($this, 'detect_post'), 0, 1 );

		// Legacy support
		add_action( 'init', array($this, 'legacy_support'), 2 );

		// Check for updates
		// add_action( 'init', array($this, 'check_for_updates'), 999 );

		// Default settings & alerts
		add_filter( 'permalink-manager-options', array($this, 'default_settings'), 1 );
		add_filter( 'permalink-manager-alerts', array($this, 'default_alerts'), 1 );
	}

	/**
	* Localize this plugin
	*/
	function localize_me() {
		load_plugin_textdomain( 'permalink-manager', false, PERMALINK_MANAGER_DIR );
	}

	/**
	* Get options values & set global
	*/
	public function get_options_and_globals() {
		// 1. Globals with data stored in DB
		global $permalink_manager_options, $permalink_manager_uris, $permalink_manager_permastructs;

		$this->permalink_manager_options = $permalink_manager_options = apply_filters('permalink-manager-options', get_option('permalink-manager', array()));
		$this->permalink_manager_uris = $permalink_manager_uris = apply_filters('permalink-manager-uris', get_option('permalink-manager-uris', array()));
		$this->permalink_manager_permastructs = $permalink_manager_permastructs = apply_filters('permalink-manager-permastructs', get_option('permalink-manager-permastructs', array()));

		// 2. Globals used to display additional content (eg. alerts)
		global $permalink_manager_alerts, $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		$this->permalink_manager_alerts = $permalink_manager_alerts = apply_filters('permalink-manager-alerts', get_option('permalink-manager-alerts', array()));
		$this->permalink_manager_before_sections_html = $permalink_manager_before_sections_html = apply_filters('permalink-manager-before-sections', '');
		$this->permalink_manager_after_sections_html = $permalink_manager_after_sections_html = apply_filters('permalink-manager-after-sections', '');
	}

	/**
	* Set the initial/default settings (including "Screen Options")
	*/
	public function default_settings($settings) {
		$all_taxonomies = Permalink_Manager_Helper_Functions::get_taxonomies_array();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		$default_settings = apply_filters('permalink-manager-default-options', array(
			'screen-options' => array(
				'per_page' => 20,
				'post_statuses' => array('publish')
			),
			'general' => array(
				'force_custom_slugs' => 0,
				'auto_update_uris' => 0,
			),
			'miscellaneous' => array(
				'yoast_primary_term' => 1,
				'redirect' => "302",
				'canonical_redirect' => 1,
			)
		));

		// Apply the default settings (if empty values) in all settings sections
		return $settings + $default_settings;
	}

	/**
	* Set the initial/default admin notices
	*/
	public function default_alerts($alerts) {
		$default_alerts = apply_filters('permalink-manager-default-alerts', array(
			'pro' => array('txt' => sprintf(__("Need to change the permalinks for categories, tags, custom taxonomies or WooCommerce?<br /><strong>Buy Permalink Manager Pro <a href=\"%s\" target=\"_blank\">here</a> and enjoy the additional features!</strong>", "permalink-manager"), PERMALINK_MANAGER_WEBSITE), 'type' => 'notice-info', 'show' => 1)
		));

		// Apply the default settings (if empty values) in all settings sections
		return $alerts + $default_alerts;
	}

	/**
	* Used to optimize SQL queries amount instead of rewrite rules - the essential part of this plugin
	*/
	function detect_post($query) {
		global $wpdb, $permalink_manager_uris, $wp_filter;

		// Check if any custom URI is used
		if(!(is_array($permalink_manager_uris))) return $query;

		// Used in debug mode
		$old_query = $query;

		/**
		* 1. Prepare URL and check if it is correct
		*/
		$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://';
		$request_url = "{$protocol}{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$home_url = trim(rtrim(get_option('home'), '/'));
		$home_url = ($protocol == 'https://') ? str_replace("http://", "https://", $home_url) : str_replace("https://", "http://", $home_url); // Adjust prefix (it should be the same in both request & home_url)

		if (filter_var($request_url, FILTER_VALIDATE_URL)) {
			/**
			* 1. Process URL & find the URI
			*/
			// Remove .html suffix and domain name from URL and query (URLs ended with .html will work as aliases)
			$request_url = trim(str_replace($home_url, "", $request_url), "/");

			// Remove querystrings from URI
			$request_url = strtok($request_url, '?');

			// Use default REGEX to detect post
			preg_match("/^(.+?)\/?(page|feed|embed|attachment|track)?(?:\/([\d+]))?\/?$/i", $request_url, $regex_parts);
			$uri_parts['lang'] = false;
			$uri_parts['uri'] = (!empty($regex_parts[1])) ? $regex_parts[1] : "";
			$uri_parts['endpoint'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
			$uri_parts['endpoint_value'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";

			// Allow to filter the results by third-parties
			$uri_parts = apply_filters('permalink-manager-detect-uri', $uri_parts, $request_url);

			// Stop the function if $uri_parts is empty
			if(empty($uri_parts)) return $query;

			// Get the URI parts from REGEX parts
			$lang = $uri_parts['lang'];
			$uri = $uri_parts['uri'];
			$endpoint = $uri_parts['endpoint'];
			$endpoint_value = $uri_parts['endpoint_value'];

			// Trim slashes
			$uri = trim($uri, "/");

			// Ignore URLs with no URI grabbed
			if(empty($uri)) return $query;

			/**
			* 2. Check if found URI matches any element from custom uris array
			*/
			$item_id = array_search($uri, $permalink_manager_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$item_id = (empty($item_id)) ? array_search("{$uri}.html",  $permalink_manager_uris) : $item_id;

			// Check again in case someone used post/tax IDs instead of slugs
			$deep_detect_enabled = apply_filters('permalink-manager-deep-uri-detect', false);
			if($deep_detect_enabled && (empty($item_id)) && isset($old_query['page'])) {
				$item_id = array_search("{$uri}/{$endpoint_value}",  $permalink_manager_uris);
				$endpoint_value = $endpoint = "";
			}

			// Clear the original query before it is filtered
			$query = ($item_id) ? array() : $query;

			/**
			* 3A. Custom URI assigned to taxonomy
			*/
			if(strpos($item_id, 'tax-') !== false) {
				// Remove the "tax-" prefix
				$item_id = preg_replace("/[^0-9]/", "", $item_id);

				// Filter detected post ID
				$item_id = apply_filters('permalink-manager-detected-term-id', $item_id, $uri_parts);

				// Get the variables to filter wp_query and double-check if tax exists
				$term = get_term($item_id);
				if(empty($term->taxonomy)) { return $query; }

				// Get some term data
				$query_parameter = ($term->taxonomy == 'category') ? 'category_name' : $term->taxonomy;
				$term_ancestors = get_ancestors($item_id, $term->taxonomy);
				$final_uri = $term->slug;

				// Fix for hierarchical CPT & pages
				if(empty($term_ancestors)) {
					foreach ($term_ancestors as $parent) {
						$parent = get_term($parent, $term->taxonomy);
						if(!empty($parent->slug)) {
							$final_uri = $parent->slug . '/' . $final_uri;
						}
					}
				}

				// Alter query parameters
				$query[$query_parameter] = $final_uri;
			}
			/**
			* 3B. Custom URI assigned to post/page/cpt item
			*/
			else if(isset($item_id) && is_numeric($item_id)) {
				// Fix for revisions
				$is_revision = wp_is_post_revision($item_id);
				$item_id = ($is_revision) ? $is_revision : $item_id;

				// Filter detected post ID
				$item_id = apply_filters('permalink-manager-detected-post-id', $item_id, $uri_parts);

				$post_to_load = get_post($item_id);
				$final_uri = $post_to_load->post_name;
				$post_type = $post_to_load->post_type;

				// Fix for hierarchical CPT & pages
				if(!(empty($post_to_load->ancestors))) {
					foreach ($post_to_load->ancestors as $parent) {
						$parent = get_post( $parent );
						if($parent && $parent->post_name) {
							$final_uri = $parent->post_name . '/' . $final_uri;
						}
					}
				}

				// Alter query parameters
				if($post_to_load->post_type == 'page') {
					$query['pagename'] = $final_uri;
				} else if($post_to_load->post_type == 'post') {
					$query['name'] = $final_uri;
				} else {
					$query['name'] = $final_uri;
					$query['post_type'] = $post_type;
					$query[$post_type] = $final_uri;
				}

				// Make the redirects more clever - see redirect_to_new_uri() method
				$query['do_not_redirect'] = 1;
			}

			/**
			* 2C. Endpoints
			*/
			if($item_id && !(empty($endpoint_value))) {
				$endpoint = ($endpoint) ? str_replace(array('page', 'trackback'), array('paged', 'tb'), $endpoint) : "page";
				$query[$endpoint] = $endpoint_value;
			}

		}

		// Debug mode
		if(isset($_REQUEST['debug_url'])) {
			$debug_info['old_query_vars'] = $old_query;
			$debug_info['new_query_vars'] = $query;

			$debug_txt = json_encode($debug_info);
			$debug_txt = "<textarea style=\"width:100%;height:300px\">{$debug_txt}</textarea>";
			wp_die($debug_txt);
		}

		return $query;
	}

	function redirect_to_new_uri() {
		global $wp, $wp_query, $permalink_manager_uris, $permalink_manager_options;

		// Affect only posts with custom URI and old URIs
		if(is_singular() && isset($permalink_manager_uris[$wp_query->queried_object_id]) && empty($wp_query->query['do_not_redirect']) && empty($wp_query->query['preview'])) {
			// Ignore posts with specific statuses
			if(!(empty($wp_query->queried_object->post_status)) && in_array($wp_query->queried_object->post_status, array('draft', 'pending', 'auto-draft', 'future'))) {
				return '';
			}

			// Get the real URL
			$correct_permalink = get_permalink($wp_query->queried_object_id);
		}

		// Get the redirection mode
		$redirect_mode = $permalink_manager_options['miscellaneous']['redirect'];

		// Ignore default URIs (or do nothing if redirects are disabled)
		if(!empty($correct_permalink) && !empty($redirect_mode)) {
			wp_redirect($correct_permalink, $redirect_mode);
			exit();
		}
	}

	function disable_canonical_redirect() {
		global $permalink_manager_options;
		if(!($permalink_manager_options['miscellaneous']['canonical_redirect'])) {
			remove_action('template_redirect', 'redirect_canonical');
		}
	}

	/**
	* Temporary hook
	*/
	function legacy_support() {
		global $permalink_manager_permastructs, $permalink_manager_options;

		if(isset($permalink_manager_options['base-editor'])) {
			$new_options['post_types'] = $permalink_manager_options['base-editor'];
			update_option('permalink-manager-permastructs', $new_options);
		}
		else if(empty($permalink_manager_permastructs['post_types']) && count($permalink_manager_permastructs) > 0) {
			$new_options['post_types'] = $permalink_manager_permastructs;
			update_option('permalink-manager-permastructs', $new_options);
		}
	}

	/**
	 * Update check
	 */
	public function check_for_updates() {
		global $permalink_manager_options;

		// Load Plugin Update Checker by YahnisElsts
		require_once PERMALINK_MANAGER_DIR . '/includes/ext/plugin-update-checker/plugin-update-checker.php';

		// Get the licence key
		$license_key = (!empty($permalink_manager_options['miscellaneous']['license_key'])) ? $permalink_manager_options['miscellaneous']['license_key'] : "";

		$UpdateChecker = Puc_v4_Factory::buildUpdateChecker(
			"https://updates.permalinkmanager.pro/?action=get_metadata&slug=permalink-manager-pro&license_key={$license_key}",
			__FILE__,
			"permalink-manager-pro"
		);

		$file = PERMALINK_MANAGER_BASENAME;
	}

}

/**
* Begins execution of the plugin.
*/
function run_permalink_manager() {
	$Permalink_Manager_Class = new Permalink_Manager_Class();
}
run_permalink_manager();
