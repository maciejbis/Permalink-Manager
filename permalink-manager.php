<?php

/**
 * Plugin Name:       Permalink Manager
 * Plugin URI:        http://maciejbis.net/
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.5.0
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
define( 'PERMALINK_MANAGER_VERSION', '0.5.0' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_BASENAME', plugin_basename(__FILE__) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://maciejbis.net' );

class Permalink_Manager_Class {

	public $permalink_manager, $permalink_manager_options_page, $permalink_manager_options;
	public $sections, $functions, $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

	/**
	 * Get options from DB, load subclasses & hooks
	 */
	public function __construct() {
		$this->include_subclassess();
		$this->run_subclasses();
		$this->register_init_hooks();
	}

	/**
	 * Include back-end classess and set their instances
	 */
	function include_subclassess() {
		// Load back-end functions
		foreach(array('helper-functions', 'post-uri-functions', 'admin-functions', 'uri-actions') as $class) {
			require_once PERMALINK_MANAGER_DIR . "/includes/core/permalink-manager-{$class}.php";
		}

		// WP_List_Table needed for post types & taxnomies editors
		if( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		// Load section classes
		foreach(array('uri-editor', 'tools', 'permastructs', 'settings', 'advanced') as $class) {
			require_once PERMALINK_MANAGER_DIR . "/includes/views/permalink-manager-{$class}.php";
		}
	}

	/**
	 * Load front-end classes and set their instances.
	 */
	function run_subclasses() {
		$this->functions['helper_functions'] = new Permalink_Manager_Helper_Functions();
		$this->functions['admin_functions'] = new Permalink_Manager_Admin_Functions();
		$this->functions['post_uri_functions'] = new Permalink_Manager_Post_URI_Functions();
		$this->functions['uri_actions'] = new Permalink_Manager_Uri_Actions();
		$this->sections['uri_editor'] = new Permalink_Manager_Uri_Editor();
		$this->sections['tools'] = new Permalink_Manager_Tools();
		$this->sections['permastructs'] = new Permalink_Manager_Permastructs();
		$this->sections['settings'] = new Permalink_Manager_Settings();
		$this->sections['advanced'] = new Permalink_Manager_Advanced();
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

		$this->permalink_manager_options = $permalink_manager_options = apply_filters('permalink-manager-options', get_option('permalink-manager'));
		$this->permalink_manager_uris = $permalink_manager_uris = apply_filters('permalink-manager-uris', get_option('permalink-manager-uris'));
		$this->permalink_manager_permastructs = $permalink_manager_permastructs = apply_filters('permalink-manager-permastructs', get_option('permalink-manager-permastructs'));

		// 2. Globals used to display additional content (eg. alerts)
		global $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		$this->permalink_manager_before_sections_html = $permalink_manager_before_sections_html = apply_filters('permalink-manager-before-sections', '');
		$this->permalink_manager_after_sections_html = $permalink_manager_after_sections_html = apply_filters('permalink-manager-after-sections', '');
	}

	/**
	 * Used to optimize SQL queries amount instead of rewrite rules - the essential part of this plugin
	 */
	function detect_post($query) {
		global $wpdb, $permalink_manager_uris;

		// Used in debug mode
		$old_query = $query;

		$protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
		$url = str_replace(home_url(), "{$protocol}{$_SERVER['HTTP_HOST']}", "{$protocol}{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"); // Fix for Wordpress installed in subdirectories

		// Remove .html suffix from URL and query (URLs ended with .html work as aliases)
		$url = str_replace(".html", "", $url);
		if(isset($query['name'])) { $query['name'] = str_replace('.html', '', $query['name']); }
		if(isset($query['pagename'])) { $query['pagename'] = str_replace('.html', '', $query['pagename']); }

		// Check if it is correct URL
		if (filter_var($url, FILTER_VALIDATE_URL)) {
			// Separate endpoints (if set) - support for comment pages will be added later
			preg_match("/(.*)\/(page|feed|embed|attachment|track)\/(.*)/", $url, $url_with_endpoints);
			if(isset($url_with_endpoints[3]) && !(empty($url_with_endpoints[3]))) {
				$url = $url_with_endpoints[1];
				$endpoint = str_replace(array('page', 'trackback'), array('paged', 'tb'), $url_with_endpoints[2]);
				$endpoint_value = $url_with_endpoints[3];
			}

			// Parse URL
			$url_parts = parse_url($url);
			$uri = (isset($url_parts['path'])) ? trim($url_parts['path'], "/") : "";
			if(empty($uri)) return $query;

			// Check if current URL is assigned to any post
			if(!(is_array($permalink_manager_uris))) return $query;
			$post_id = array_search($uri,  $permalink_manager_uris);

			// Check again in case someone added .html suffix to particular post (with .html suffix)
			$post_id = (empty($post_id)) ? array_search("{$uri}.html",  $permalink_manager_uris) : $post_id;

			if(isset($post_id) && is_numeric($post_id)) {
				// Check if it is revision (hotfix) and use original post ID instead of revision ID
				$is_revision = wp_is_post_revision($post_id);

				// Debug for @andresgl
				if(isset($_REQUEST['debug_detect_post'])) {
					$post_to_load = (is_object(get_post($post_id))) ? get_post($post_id) : 'no object!';
					$revision_post_to_load = (is_object(get_post($is_revision))) ? get_post($is_revision) : 'no object!';

					$debug_array = array(
						'original_post_id' => $post_id,
						'original_post' => $post_to_load,
						'revision_post_id' => $is_revision,
						'revision_post' => $revision_post_to_load,
					);

					wp_die("<pre>" . print_r($debug_array, true) . "</pre>");
				}

				$post_id = ($is_revision) ? $is_revision : $post_id;

				$post_to_load = get_post($post_id);
				$original_page_uri = $post_to_load->post_name;
				$post_type = $post_to_load->post_type;
				unset($query['attachment']);
				unset($query['error']);

				// Fix for hierarchical CPT & pages
				if( isset($post_to_load->ancestors) && !(empty($post_to_load->ancestors))) {
					foreach ( $post_to_load->ancestors as $parent ) {
						$parent = get_post( $parent );
						if ( $parent && $parent->post_name ) {
							$original_page_uri = $parent->post_name . '/' . $original_page_uri;
						}
					}
				}

				// Fix for not-pages
				if($post_to_load->post_type != 'post') {
					unset($query['year']);
					unset($query['monthnum']);
					unset($query['day']);
				}

				// Alter query parameters
				if($post_to_load->post_type == 'page') {
					unset($query['name']);
					$query['pagename'] = $original_page_uri;
				} else if($post_to_load->post_type == 'post') {
					$query['name'] = $original_page_uri;
				} else {
					$query['name'] = $original_page_uri;
					$query['post_type'] = $post_type;
					$query[$post_type] = $original_page_uri;
				}

				// Make the redirects more clever - see redirect_to_new_uri() method
				$query['do_not_redirect'] = 1;

				// Add endpoint
				if(isset($endpoint_value) && !(empty($endpoint_value))) {
					$query[$endpoint] = $endpoint_value;
				}
			}
		}

		// Debug mode
		if(isset($_REQUEST['debug_url'])) {
			$debug_info['old_query_vars'] = $old_query;
			$debug_info['new_query_vars'] = $query;
			//$debug_info['request'] = "{$protocol}{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
			$debug_info['request'] = $url;

			$debug_txt = json_encode($debug_info);
			$debug_txt = "<textarea style=\"width:100%;height:300px\">{$debug_txt}</textarea>";
			wp_die($debug_txt);
		}

		return $query;
	}

	function redirect_to_new_uri() {
		global $wp, $wp_query, $permalink_manager_uris, $permalink_manager_options;

		// Affect only posts with custom URI and old URIs
		if(is_singular() && isset($permalink_manager_uris[$wp_query->queried_object_id]) && empty($wp_query->query['do_not_redirect'])) {
			$current_url = home_url(add_query_arg(array(),$wp->request));
			$native_uri = Permalink_Manager_Post_URI_Functions::get_default_post_uri($row['ID'], true);
			
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

}

/**
 * Begins execution of the plugin.
 */
function run_permalink_manager() {
	$Permalink_Manager_Class = new Permalink_Manager_Class();
}

run_permalink_manager();
