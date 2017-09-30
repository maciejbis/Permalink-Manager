<?php

/**
* Plugin Name:       Permalink Manager Lite
* Plugin URI:        https://permalinkmanager.pro?utm_source=plugin
* Description:       Most advanced Permalink utility for Wordpress. It allows to bulk edit the permalinks & permastructures and regenerate/reset all the URIs in your Wordpress instance.
* Version:           2.0.3
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
define( 'PERMALINK_MANAGER_VERSION', '2.0.3' );
define( 'PERMALINK_MANAGER_FILE', __FILE__ );
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
				'core-functions' => 'Permalink_Manager_Core_Functions',
				'pro-functions' => 'Permalink_Manager_Pro_Functions'
			),
			'views' => array(
				'uri-editor' => 'Permalink_Manager_Uri_Editor',
				'tools' => 'Permalink_Manager_Tools',
				'permastructs' => 'Permalink_Manager_Permastructs',
				'settings' => 'Permalink_Manager_Settings',
				'debug' => 'Permalink_Manager_Debug',
				'pro-addons' => 'Permalink_Manager_Pro_Addons',
				'upgrade' => 'Permalink_Manager_Upgrade',
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

		// Load globals & options
		add_action( 'plugins_loaded', array($this, 'get_options_and_globals'), 9 );

		// Legacy support
		add_action( 'init', array($this, 'legacy_support'), 2 );

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
		global $permalink_manager_options, $permalink_manager_uris, $permalink_manager_permastructs, $permalink_manager_redirects;

		$this->permalink_manager_options = $permalink_manager_options = apply_filters('permalink-manager-options', get_option('permalink-manager', array()));
		$this->permalink_manager_uris = $permalink_manager_uris = apply_filters('permalink-manager-uris', get_option('permalink-manager-uris', array()));
		$this->permalink_manager_permastructs = $permalink_manager_permastructs = apply_filters('permalink-manager-permastructs', get_option('permalink-manager-permastructs', array()));
		$this->permalink_manager_redirects = $permalink_manager_redirects = apply_filters('permalink-manager-redirects', get_option('permalink-manager-redirects', array()));

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
				'post_statuses' => array('publish'),
				'group' => false,
			),
			'general' => array(
				'force_custom_slugs' => 0,
				'auto_update_uris' => 0,
				'case_insensitive_permalinks' => 0,
				'decode_uris' => 0,
				'yoast_primary_term' => 1,
				'redirect' => '302',
				'yoast_attachment_redirect' => 1,
				'canonical_redirect' => 1,
				'trailing_slashes' => 0,
				'setup_redirects' => 0,
				'auto_remove_duplicates' => 0,
				'disable_slug_appendix' => array()
			),
			'licence' => array()
		));

		// Apply the default settings (if empty values) in all settings sections
		foreach($default_settings as $group_name => $fields) {
			foreach($fields as $field_name => $field) {
				if(!isset($settings[$group_name][$field_name])) {
					$settings[$group_name][$field_name] = $field;
				}
			}
		}

		return $settings;
	}

	/**
	* Set the initial/default admin notices
	*/
	public function default_alerts($alerts) {
		$default_alerts = apply_filters('permalink-manager-default-alerts', array(
			'september' => array(
				'txt' => sprintf(
					__("Get access to extra features: full taxonomy and WooCommerce support, possibility to use custom fields inside the permalinks and more!<br /><strong>Buy Permalink Manager Pro <a href=\"%s\" target=\"_blank\">here</a> and save 20&#37; using \"SUMMER\" coupon code!</strong>", "permalink-manager"),
					PERMALINK_MANAGER_WEBSITE
				),
				'type' => 'notice-info',
				'show' => 'pro_hide',
				'plugin_only' => true,
				'until' => '2017-09-10'
			)
		));

		// Apply the default settings (if empty values) in all settings sections
		return $alerts + $default_alerts;
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

		// Adjust options structure
		if(!empty($permalink_manager_options['miscellaneous'])) {
			// Get the options direclty from database
			$permalink_manager_unfiltered_options = get_option('permalink-manager', array('general' => array(), 'miscellaneous' => array(), 'licence'));

			// Combine general & general
			$permalink_manager_unfiltered_options['general'] = array_merge($permalink_manager_unfiltered_options['general'], $permalink_manager_unfiltered_options['miscellaneous']);

			// Move licence key to different section
			$permalink_manager_unfiltered_options['licence']['licence_key'] = (!empty($permalink_manager_unfiltered_options['miscellaneous']['license_key'])) ? $permalink_manager_unfiltered_options['miscellaneous']['license_key'] : "";

			// Remove redundant keys
			unset($permalink_manager_unfiltered_options['general']['license_key']);
			unset($permalink_manager_unfiltered_options['miscellaneous']);
			unset($permalink_manager_unfiltered_options['permalink_manager_options']);
			unset($permalink_manager_unfiltered_options['_wp_http_referer']);

			// Save the settings in database
			update_option('permalink-manager', $permalink_manager_unfiltered_options);
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
