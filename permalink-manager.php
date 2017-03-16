<?php

/**
* Plugin Name:       Permalink Manager
* Plugin URI:        http://maciejbis.net/
* Description:       Most advanced Permalink  utility for Wordpress. It allows to bulk edit the permalinks & permastructures and regenerate/reset all the URIs in your Wordpress instance.
* Version:           1.0.2
* Author:            Maciej Bis
* Author URI:        http://maciejbis.net/
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       permalink-manager
* Domain Path:       /languages
*/
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define the directories used to load plugin files.
define( 'PERMALINK_MANAGER_PLUGIN_NAME', 'Permalink Manager' );
define( 'PERMALINK_MANAGER_PLUGIN_SLUG', 'permalink-manager' );
define( 'PERMALINK_MANAGER_VERSION', '1.0.2' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_BASENAME', plugin_basename(__FILE__) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://permalinkmanager.pro' );

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
				'pro-hooks' => 'Permalink_Manager_Pro_Hooks'
			),
			'views' => array(
				'uri-editor' => 'Permalink_Manager_Uri_Editor',
				'tools' => 'Permalink_Manager_Tools',
				'permastructs' => 'Permalink_Manager_Permastructs',
				'settings' => 'Permalink_Manager_Settings',
				'advanced' => 'Permalink_Manager_Advanced',
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
		global $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		$this->permalink_manager_before_sections_html = $permalink_manager_before_sections_html = apply_filters('permalink-manager-before-sections', '');
		$this->permalink_manager_after_sections_html = $permalink_manager_after_sections_html = apply_filters('permalink-manager-after-sections', '');
	}

	/**
	* Used to optimize SQL queries amount instead of rewrite rules - the essential part of this plugin
	*/
	function detect_post($query) {
		global $wpdb, $permalink_manager_uris, $sitepress_settings;

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
		// Adjust prefix (it should be the same in both request & home_url)
		$home_url = ($protocol == 'https://') ? str_replace("http://", "https://", $home_url) : str_replace("https://", "http://", $home_url);

		if (filter_var($request_url, FILTER_VALIDATE_URL)) {
			/**
			* 2. Process URL
			*/
			// Remove .html suffix and domain name from URL and query (URLs ended with .html will work as aliases)
			$request_url = trim(str_replace($home_url, "", $request_url), "/");

			// Remove querystrings from URI
			$request_url = strtok($request_url, '?');

			// Split the current URL into subparts (check if WPML is active)
			if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 1) {
				preg_match("/^(?:(\w{2})\/)?(.+?)\/?(page|feed|embed|attachment|track)?(\/[\d+])?\/?$/i", $request_url, $url_parts);
				$lang = (!empty($url_parts[1])) ? $url_parts[1] : "";
				$uri = (!empty($url_parts[2])) ? $url_parts[2] : "";
				$endpoint = (!empty($url_parts[3])) ? $url_parts[3] : "";
				$endpoint_value = (!empty($url_parts[4])) ? $url_parts[4] : "";
			} else {
				preg_match("/^(.+?)\/?(page|feed|embed|attachment|track)?(\/[\d+])?\/?$/i", $request_url, $url_parts);
				$lang = false;
				$uri = (!empty($url_parts[1])) ? $url_parts[1] : "";
				$endpoint = (!empty($url_parts[2])) ? $url_parts[2] : "";
				$endpoint_value = (!empty($url_parts[3])) ? $url_parts[3] : "";
			}

			// Trim slashes
			$uri = trim($uri, "/");

			// Ignore URLs with no URI grabbed
			if(empty($uri)) return $query;

			/**
			* 2A. Check if found URI matches any element from custom uris array
			*/
			$item_id = array_search($uri, $permalink_manager_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$item_id = (empty($item_id)) ? array_search("{$uri}.html",  $permalink_manager_uris) : $item_id;

			// Clear the original query before it is filtered
			$query = ($item_id) ? array() : $query;

			/**
			* 2B. Custom URI assigned to taxonomy
			*/
			if(strpos($item_id, 'tax-') !== false) {
				// Remove the "tax-" prefix
				$item_id = preg_replace("/[^0-9]/", "", $item_id);

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
			* 2C. Custom URI assigned to post/page/cpt item
			*/
			else if(isset($item_id) && is_numeric($item_id)) {
				// Fix for revisions
				$is_revision = wp_is_post_revision($item_id);
				$item_id = ($is_revision) ? $is_revision : $item_id;

				// Fix for WPML languages mismatch
				$post_lang_details = apply_filters('wpml_post_language_details', NULL, $item_id);
				$language_code = (!empty($post_lang_details['language_code'])) ? $post_lang_details['language_code'] : '';
				if($lang && $lang != $language_code) {
					$item_id = apply_filters('wpml_object_id', $item_id);
				}

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
			if(!(empty($endpoint_value))) {
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
			$current_url = home_url(add_query_arg(array(),$wp->request));
			$native_uri = Permalink_Manager_URI_Functions_Post::get_default_post_uri($permalink_manager_uris[$wp_query->queried_object_id], true);

			$active_permalink = str_replace($native_uri, $permalink_manager_uris[$wp_query->queried_object_id], $current_url);
		}

		// Get the redirection mode
		$redirect_mode = $permalink_manager_options['miscellaneous']['redirect'];

		// Ignore default URIs (or do nothing if redirects are disabled)
		if(!empty($active_permalink) && !empty($redirect_mode)) {
			wp_redirect($active_permalink, $redirect_mode);
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

}

/**
* Begins execution of the plugin.
*/
function run_permalink_manager() {
	$Permalink_Manager_Class = new Permalink_Manager_Class();
}

run_permalink_manager();
