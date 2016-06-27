<?php

/**
 * Plugin Name:       Permalink Manager
 * Plugin URI:        http://maciejbis.net/
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.4
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
define( 'PERMALINK_MANAGER_PLUGIN_NAME', 'permalink-manager' );
define( 'PERMALINK_MANAGER_VERSION', '0.4' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://maciejbis.net' );
define( 'PERMALINK_MANAGER_MENU_PAGE', 'tools_page_permalink-manager' );
define( 'PERMALINK_MANAGER_OPTIONS_PAGE', PERMALINK_MANAGER_PLUGIN_NAME . '.php' );

class Permalink_Manager_Class {

	protected $permalink_manager, $admin_page, $permalink_manager_options_page, $permalink_manager_options;

	public function __construct() {

    $this->permalink_manager_options = get_option('permalink-manager');
    $this->permalink_manager_uris = get_option('permalink-manager-uris');
    $this->permalink_manager_permastructs = get_option('permalink-manager-permastructs');

		if( is_admin() ) {
			add_action( 'plugins_loaded', array($this, 'localize_me') );
			add_action( 'init', array($this, 'upgrade_plugin'), 99999 );
			add_action( 'wp_loaded', array($this, 'bulk_actions'), 1 );
			add_action( 'admin_menu', array($this, 'add_menu_page') );
			add_action( 'save_post', array($this, 'update_single_uri'), 10, 3 );
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugins_page_links') );
			add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 10, 4 );
		}

		add_action( 'wp_loaded', array($this, 'permalink_filters'), 9);

	}

	function permalink_filters() {
		// Public functions
		add_filter( 'request', array($this, 'detect_post') );
		add_filter( '_get_page_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'page_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'post_link', array($this, 'custom_permalinks'), 999, 2);
		add_filter( 'post_type_link', array($this, 'custom_permalinks'), 999, 2);
	}

	/**
	 * Localize this plugin
	 */
	function localize_me() {
		load_plugin_textdomain( 'permalink-manager', false, PERMALINK_MANAGER_DIR );
	}

	/**
	 * Add menu page and load CSS & JS.
	 */
	function add_menu_page() {
		add_management_page( __('Permalink Manager', 'permalink-manager'), __('Permalink Manager', 'permalink-manager'), 'manage_options', PERMALINK_MANAGER_OPTIONS_PAGE, array($this, 'list_slugs_admin_page') );

		// Make sure thata the CSS and JS files are loaded only on plugin admin page.
		add_action( 'admin_print_scripts-' . PERMALINK_MANAGER_MENU_PAGE, array($this, 'enqueue_styles' ) );
		add_action( 'admin_print_scripts-' . PERMALINK_MANAGER_MENU_PAGE, array($this, 'enqueue_scripts' ) );
	}

	/**
	 * Display the table with slugs.
	 */
	function slug_editor_html() {
		global $wpdb;

		$Permalink_Manager_Editor = new Permalink_Manager_Editor();
    $Permalink_Manager_Editor->set_screen_option_fields($this->fields_arrays('screen_options'));
		$Permalink_Manager_Editor->prepare_items($wpdb->posts);

		?>

		<form id="permalinks-table" method="post">
			<input type="hidden" name="tab" value="slug_editor" />
			<?php echo $Permalink_Manager_Editor->display(); ?>
		</form>
	<?php
	}

	/**
	 * Mass replace options page.
	 */
	function find_and_replace_html() {
		$button = get_submit_button( __( 'Find & Replace Slugs', 'permalink-manager' ), 'primary', 'find-replace-button', false );

		$return = "<form id=\"permalinks-table-find-replace\" method=\"post\">";
			$return .= "<input type=\"hidden\" name=\"tab\" value=\"find_and_replace\" />";
      $return .= "<table class=\"form-table\">";

      foreach($this->fields_arrays('find_and_replace') as $field_name => $field_args) {
        $return .= Permalink_Manager_Helper_Functions::generate_option_field($field_name, $field_args, 'find-replace');
      }

      $return .= "</table>{$button}";
		$return .= "</form>";

		echo $return;
	}

	/**
	 * Reset slugs page.
	 */
	function regenerate_slugs_html() {
		$button = get_submit_button( __( 'Regenerate', 'permalink-manager' ), 'primary', 'regenerate-button', false );

        $return = "<form id=\"permalinks-table-regenerate\" method=\"post\">";
        $return .= "<input type=\"hidden\" name=\"tab\" value=\"regenerate_slugs\" />";
        $return .= "<table class=\"form-table\">";

        foreach($this->fields_arrays('regenerate_slugs') as $field_name => $field_args) {
          $return .= Permalink_Manager_Helper_Functions::generate_option_field($field_name, $field_args, 'regenerate_slugs');
        }

        $return .= "</table>{$button}";
		$return .= "</form>";

		echo $return;
	}

	/**
	 * Permalink Base Editor
	 */
	function base_editor_html() {
		global $wpdb, $wp_rewrite;

		$Permalink_Manager_Base_Editor = new Permalink_Manager_Base_Editor();
    $Permalink_Manager_Base_Editor->set_screen_option_fields($this->fields_arrays('screen_options'));
		$Permalink_Manager_Base_Editor->prepare_items($wpdb->posts);
		?>

		<form id="permalinks-base-table" method="post">
			<input type="hidden" name="tab" value="base_editor" />
			<?php echo $Permalink_Manager_Base_Editor->display(); ?>
		</form>
	<?php
	}

	/**
	 * Display the plugin dashboard.
	 */
	function list_slugs_admin_page() {
		global $wpdb;

		// Check which tab is active now.
		if(isset($_POST['tab'])) {
			$active_tab = $_POST['tab'];
		} else if(isset($_GET['tab'])) {
			$active_tab = $_GET['tab'];
		} else {
			$active_tab = 'slug_editor';
		}

		// Tabs array with assigned functions used to display HTML content.
		$tabs = array(
			'slug_editor' => array(
				'name'				=>	__('Permalink editor', 'permalink-manager'),
				'function'		=>	'slug_editor_html',
				'description'	=>	__('You can disable/enable selected post types from the table below using <strong>"Screen Options"</strong> (click on the upper-right button to show it) section above.', 'permalink-manager'),
			),
			'find_and_replace' => array(
				'name'				=>	__('Find and replace', 'permalink-manager'),
				'function'		=>	'find_and_replace_html'
			),
			'regenerate_slugs' => array(
				'name'				=>	__('Regenerate/Reset', 'permalink-manager'),
				'function'		=>	'regenerate_slugs_html'
			),
			'base_editor' => array(
				'name'				=>	__('Base editor', 'permalink-manager'),
				'function'		=>	'base_editor_html',
				'warning'			=>	array(
														sprintf(__('<strong>This is an experimental feature!</strong> Please report all the bugs & issues <a href="%s">here</a>.', 'permalink-manager'), 'https://wordpress.org/support/plugin/permalink-manager'),
														__('Each Custom Post Type should have their own, unique front (eg. <em>products</em> for Products)', 'permalink-manager'),
														__('Please note that the following settings will be applied only to new posts.<br />If you want to apply them to exisiting posts, you will need to regenerate the URIs in <strong>"Regnerate/Reset"</strong> section (with <strong>"Slugs & bases"</strong> option selected).', 'permalink-manager'),
													),
				'description'	=>	(sprintf( __('All the <a href="%s" target="_blank">Structure Tags</a> allowed are listed below. Please note that some of them can be used only for particular Post Types.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags") . "<br />" . Permalink_Manager_Helper_Functions::get_all_structure_tags())
			),
		);

		?>
			<div id="permalinks-table-wrap" class="wrap">

				<?php
				// Display alerts and another content if needed
				echo apply_filters('permalink-manager-before-tabs','');
				?>

				<div id="icon-themes" class="icon32"></div>
				<h2 id="plugin-name-heading"><?php _e('Permalink Manager', 'permalink-manager'); ?> <a href="<?php echo PERMALINK_MANAGER_WEBSITE; ?>" target="_blank"><?php _e('by Maciej Bis', 'permalink-manager'); ?></a></h2>

				<h2 id="permalink-manager-tabs-nav" class="nav-tab-wrapper">
					<?php
					foreach($tabs as $tab_id => $tab_properties) {
						$active_class = ($active_tab === $tab_id) ? 'nav-tab-active nav-tab' : 'nav-tab';
						echo '<a data-tab="' . $tab_id . '" href="' . admin_url('admin.php?page=' . PERMALINK_MANAGER_PLUGIN_NAME . '.php&tab=' . $tab_id) . '" class="' . $active_class . '">' . $tab_properties['name'] . '</a>';
					} ?>
				</h2>

				<div id="permalink-manager-tabs">
					<?php
					foreach($tabs as $tab_id => $tab_properties) {
						$active_show = ($active_tab === $tab_id) ? 'show' : '';

						// Prepare warning & description texts
						$warning = (isset($tab_properties['warning'])) ? $tab_properties['warning'] : '';
						$description = (isset($tab_properties['description'])) ? $tab_properties['description'] : '';

						if(is_array($warning)) {
							$warning = "<ol>"; // Overwrite the variable
							foreach($tab_properties['warning'] as $point) { $warning .= "<li>{$point}</li>"; }
							$warning .= "</ol>";
						}

						if(is_array($description)) {
							$description = "<ol>"; // Overwrite the variable
							foreach($tab_properties['description'] as $point) { $description .= "<li>{$point}</li>"; }
							$description .= "</ol>";
						}

						echo '<div data-tab="' . $tab_id . '" id="' . $tab_id . '" class="' . $active_show . '">';
							echo ($warning) ? "<div class=\"warning alert\">" . wpautop($warning) . "</div>" : "";
							echo (isset($tab_properties['description'])) ? "<div class=\"info alert\">" . wpautop($description) . "</div>" : "";
							$function_name = $tab_properties['function'];
							$this->$function_name();
						echo '</div>';
					} ?>
				</div>

				<?php
				// Display alerts and another content if needed
				echo apply_filters('permalink-manager-after-tabs','');
				?>

			</div>
		<?php
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 */
	function enqueue_styles() {
		wp_enqueue_style( PERMALINK_MANAGER_PLUGIN_NAME, PERMALINK_MANAGER_URL . '/css/permalink-manager-admin.css', array(), PERMALINK_MANAGER_VERSION, 'all' );
	}

	/**
	 * Register the JavaScript for the dashboard.
	 */
	function enqueue_scripts() {
		wp_enqueue_script( PERMALINK_MANAGER_PLUGIN_NAME, PERMALINK_MANAGER_URL . '/js/permalink-manager-admin.js', array( 'jquery' ), PERMALINK_MANAGER_VERSION, false );
	}

	/**
	 * Additional links on "Plugins" page
	 */
	function plugins_page_links( $links ) {
		$links[] = '<a href="' . esc_url( get_admin_url(null, "tools.php?page=" . PERMALINK_MANAGER_OPTIONS_PAGE) ) .'">' . __( 'Go To Permalink Manager', 'permalink-manager' ) . '</a>';
		return $links;
	}

  /**
   * Fields for "Screen Options"
   */
  function fields_arrays($array) {

		// All registered post types array
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array();

		// Fields for "Screen Options"
    $screen_options = array(
      'post_types' => array(
        'label' => __( 'Post Types', 'permalink-manager' ),
        'type' => 'checkbox',
        'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types),
        'default' => array('post', 'page')
      ),
			'post_statuses' => array(
				'label' => __( 'Post Statuses', 'permalink-manager' ),
				'type' => 'checkbox',
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Statuses', 'permalink-manager') . '</strong>'), $all_post_statuses_array),
				'default' => array('publish')
      ),
			'per_page' => array(
        'label' => __( 'Per page', 'permalink-manager' ),
        'type' => 'number',
        'default' => 10
      )
    );

		// Fields for "Find and replace"
		$find_and_replace = array(
			'clearfix1' => array(
				'type' => 'clearfix'
			),
			'old_string' => array(
        'label' => __( 'Find ...', 'permalink-manager' ),
        'type' => 'text',
				'container_class' => 'half'
      ),
			'new_string' => array(
        'label' => __( 'Replace with ...', 'permalink-manager' ),
        'type' => 'text',
				'container_class' => 'half half2'
      ),
			'clearfix2' => array(
				'type' => 'clearfix'
			),
			'variant' => array(
				'label' => __( 'Select which elements should be affected', 'permalink-manager' ),
				'type' => 'radio',
				'choices' => array('both' => '<strong>' . __('Plugin Slugs & Bases (Full URIs)', 'permalink-manager') . '</strong>', 'slugs' => '<strong>' . __('Only Plugin Slugs', 'permalink-manager') . '</strong>', 'post_names' => '<strong>' . __('Plugin Slugs & Wordpress Native Slugs (Post Names)', 'permalink-manager') . '</strong>'),
				'default' => array('slugs'),
				'desc' => __('First two options will affect settings used only by this plugin.<br />A MySQL backup is recommended before using third option - it overwrites the value of <strong>post_name</strong> field (part of <strong>$post</strong> object used by Wordpress core).', 'permalink-manager')
      ),
			'post_types' => array(
				'label' => __( 'Post Types that should be affected', 'permalink-manager' ),
				'type' => 'checkbox',
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types),
				'default' => array('post', 'page')
      ),
			'post_statuses' => array(
				'label' => __( 'Post Statuses that should be affected', 'permalink-manager' ),
				'type' => 'checkbox',
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Statuses', 'permalink-manager') . '</strong>'), $all_post_statuses_array),
				'default' => array('publish')
      )
    );

		// Fields for "Regenerate slugs"
		$regenerate_slugs = array(
			'variant' => array(
				'label' => __( 'Select which elements should be affected', 'permalink-manager' ),
				'type' => 'radio',
				'choices' => array('both' => '<strong>' . __('Plugin Slugs & Bases (Full URIs)', 'permalink-manager') . '</strong>', 'slugs' => '<strong>' . __('Only Plugin Slugs', 'permalink-manager') . '</strong>', 'post_names' => '<strong>' . __('Plugin Slugs & Wordpress Native Slugs (Post Names)', 'permalink-manager') . '</strong>'),
				'default' => array('slugs'),
				'desc' => __('First two options will affect settings used only by this plugin.<br />A MySQL backup is recommended before using third option - it overwrites the value of <strong>post_name</strong> field (part of <strong>$post</strong> object used by Wordpress core).', 'permalink-manager')
      ),
			'post_types' => array(
				'label' => __( 'Post Types that should be affected', 'permalink-manager' ),
				'type' => 'checkbox',
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types),
				'default' => array('post', 'page')
      ),
			'post_statuses' => array(
				'label' => __( 'Post Statuses that should be affected', 'permalink-manager' ),
				'type' => 'checkbox',
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Statuses', 'permalink-manager') . '</strong>'), $all_post_statuses_array),
				'default' => array('publish')
      )
    );

		return isset($array) ? ${$array} : array();

  }

	/**
	 * Bulk actions functions
	 */
	function bulk_actions() {
		global $wpdb;

		// Trigger a selected function
		if (isset($_POST['update_all_slugs'])) {
			$output = Permalink_Manager_Actions::update_all_permalinks();
		} else if (isset($_POST['find-replace-button'])) {
			$output = Permalink_Manager_Actions::find_replace($this->fields_arrays('find_and_replace'));
		} else if (isset($_POST['regenerate-button'])) {
			$output = Permalink_Manager_Actions::regenerate_all_permalinks();
		// Save Permalink Structures/Permalinks Bases
		} else if (isset($_POST['save_permastructs'])) {
			$output = Permalink_Manager_Actions::update_permastructs();
		}

		// Load variables
		$updated_array = isset($output['updated']) ? $output['updated'] : array();
		$updated_slugs_count = isset($output['updated_count']) ? $output['updated_count'] : 0;
		$alert_content = isset($output['alert_content']) ? $output['alert_content'] : "";
		$alert_type = isset($output['alert_type']) ? $output['alert_type'] : "";

		/**
		 * Display results
		 */
		if((isset($_POST['permalink-manager']) || isset($_POST['update_all_slugs'])) && !(isset($_POST['screen-options-apply']))) {
			// Display errors or success message

			// Check how many rows/slugs were affected
			if (isset($_POST['save_permastructs'])) {
				$alert_type = 'updated';
				$alert_content = __( 'Permastructures were updated!', 'permalink-manager' ) . ' ';
			} else if($updated_slugs_count > 0) {
				$alert_type = 'updated';
				$alert_content = sprintf( _n( '<strong>%d</strong> slug were updated!', '<strong>%d</strong> slugs were updated!', $updated_slugs_count, 'permalink-manager' ), $updated_slugs_count ) . ' ';
				$alert_content .= sprintf( __( '<a href="%s">Click here</a> to go to the list of updated slugs', 'permalink-manager' ), '#updated-list');
			} else {
				$alert_type = 'error';
				$alert_content = ($alert_content) ? $alert_content : __( '<strong>No slugs</strong> were updated!', 'permalink-manager' );
			}

			Permalink_Manager_Helper_Functions::display_alert($alert_content, $alert_type, true);

			// Display summary after update
			// Display only if there are any slugs updated
			if ( $updated_slugs_count > 0 && is_array($updated_array) ) {
				add_filter('permalink-manager-after-tabs', function( $arg ) use ( $alert_content, $alert_type, $updated_array ) {

					// Check if slugs should be displayed
					$first_slug = reset($updated_array);

					$header_footer = '<tr>';
						$header_footer .= '<th class="column-primary">' . __('Title', 'permalink-manager') . '</th>';
						$header_footer .= '<th>' . __('Old URI', 'permalink-manager') . '</th>';
						$header_footer .= '<th>' . __('New URI', 'permalink-manager') . '</th>';
						$header_footer .= (isset($first_slug['old_slug'])) ? '<th>' . __('Old Slug', 'permalink-manager') . '</th>' : "";
						$header_footer .= (isset($first_slug['new_slug'])) ? '<th>' . __('New Slug', 'permalink-manager') . '</th>' : "";
					$header_footer .= '</tr>';

					$updated_slugs_count = 0;
					$main_content = "";
					foreach($updated_array as $row) {
						// Odd/even class
						$updated_slugs_count++;
						$alternate_class = ($updated_slugs_count % 2 == 1) ? ' class="alternate"' : '';
						//$permalink = Permalink_Manager_Helper_Functions::get_correct_permalink($row[ 'ID' ]);
						$permalink = home_url("{$row['new_uri']}");

						$main_content .= "<tr{$alternate_class}>";
							$main_content .= '<td class="row-title column-primary" data-colname="' . __('Title', 'permalink-manager') . '">' . $row['post_title'] . "<a target=\"_blank\" href=\"{$permalink}\"><small>{$permalink}</small></a>" . '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'permalink-manager') . '</span></button></td>';
							$main_content .= '<td data-colname="' . __('Old URI', 'permalink-manager') . '">' . $row['old_uri'] . '</td>';
							$main_content .= '<td data-colname="' . __('New URI', 'permalink-manager') . '">' . $row['new_uri'] . '</td>';
							$main_content .= (isset($row['old_slug'])) ? '<td data-colname="' . __('Old Slug', 'permalink-manager') . '">' . $row['old_slug'] . '</td>' : "";
							$main_content .= (isset($row['new_slug'])) ? '<td data-colname="' . __('New Slug', 'permalink-manager') . '">' . $row['new_slug'] . '</td>' : "";
						$main_content .= '</tr>';
					}

					// Merge header, footer and content
					$output = '<h3 id="updated-list">' . __('List of updated posts', 'permalink-manager') . '</h3>';
					$output .= '<table class="widefat wp-list-table">';
						$output .= "<thead>{$header_footer}</thead><tbody>{$main_content}</tbody><tfoot>{$header_footer}</tfoot>";
					$output .= '</table>';

					return $output ;

				});
			}
		}
  }

	/**
	 * Change permalinks for posts, pages & custom post types
	 */
	function custom_permalinks($permalink, $post) {
		global $wp_rewrite, $permalink_manager;

		$post = (is_integer($post)) ? get_post($post) : $post;
		$post_type = $post->post_type;

		// Do not change permalink of frontpage
		if(get_option('page_on_front') == $post->ID) { return $permalink; }

		$uris = $this->permalink_manager_uris;
		if(isset($uris[$post->ID])) $permalink = home_url('/') . $uris[$post->ID];

		return $permalink;
	}

	/**
	 * Used to optimize SQL queries amount instead of rewrite rules
	 */
	function detect_post($query) {

		// GET URL
		// Fix for Wordpress installed in subdirectories (protocol does not matter here)
		$url = str_replace(home_url(), "http://" . $_SERVER['HTTP_HOST'], "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");

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
			$uri = trim($url_parts['path'], "/");
			if(empty($uri)) return $query;

			// Check if current URL is assigned to any post
			$uris = $this->permalink_manager_uris;
			if(!(is_array($uris))) return $query;
			$post_id = array_search($uri,  $uris);

			if(isset($post_id) && is_numeric($post_id)) {
				$post_to_load = get_post($post_id);
				$original_page_uri = get_page_uri($post_to_load->ID);
				unset($query['attachment']);
				unset($query['error']);

				if($post_to_load->post_type == 'page') {
					$query['pagename'] = $original_page_uri;
				} elseif($post_to_load->post_type == 'post') {
					$query['name'] = $original_page_uri;
				} else {
					$query['post_type'] = $post_to_load->post_type;
					$query['name'] = $original_page_uri;
					$query[$post_to_load->post_type] = $original_page_uri;
				}

				// Add endpoint
				if(isset($endpoint_value)) {
					$query[$endpoint] = $endpoint_value;
				}
			}

		}
		return $query;
	}

	/**
	 * Allow to edit URIs from "Edit Post" admin pages
	 */
	function edit_uri_box($html, $id, $new_title, $new_slug) {

		global $post;

		// Do not change anything if post is not saved yet
		if(empty($post->post_name)) return $html;

		$uris = $this->permalink_manager_uris;
		$default_uri = trim(str_replace(home_url("/"), "", get_permalink($id)), "/");
		$uri = (isset($uri[$id])) ? $uri[$id] : $default_uri;

		$html = preg_replace("/(<strong>(.*)<\/strong>)(.*)/is", "$1 ", $html);
		$html .= home_url("/") . " <span id=\"editable-post-name\"><input type='text' value='{$uri}' name='custom_uri'/></span>";
		return $html;
	}

	/**
	 * Update URI from "Edit Post" admin page
	 */
	function update_single_uri($post_id, $post, $update) {

		// Ignore trashed items
		if($post->post_status == 'trash') return;

		$uris = $this->permalink_manager_uris;
		$old_default_uri = trim(str_replace(home_url("/"), "", get_permalink($post_id)), "/");
		$new_default_uri = Permalink_Manager_Helper_Functions::get_uri($post, true);
		$new_uri = '';

		// Check if user changed URI (available after post is saved)
		if(isset($_POST['custom_uri'])) {
			$new_uri = trim($_POST['custom_uri'], "/");
		}

		// A little hack
		$new_uri = ($new_uri) ? $new_uri : $new_default_uri;

		// Do not store default values
		if(isset($uris[$post_id]) && ($new_uri == $old_default_uri)) {
			unset($uris[$post_id]);
		} else if ($new_uri != $old_default_uri) {
			$uris[$post_id] = $new_uri;
		}
		update_option('permalink-manager-uris', $uris);
	}

	/**
	 * Convert old plugin structure to the new solution (this function will be removed in 1.0 version)
	 */
	function upgrade_plugin() {

		global $wpdb;

		/*
		 * Separate slugs from rest of plugin options
		 */
		 $options = $this->permalink_manager_options;
		//if !empty($this->permalink_manager_options['base-editor'])
		if (isset($options['base-editor']) && is_array($options['base-editor'])) {
			$old_permastructs = $options['base-editor'];
			$new_permastructs = $uris = array();

			// At first save permastructs to new separate option field
			foreach($old_permastructs as $post_type => $permastruct) {
				$new_permastructs[$post_type] = trim(str_replace(Permalink_Manager_Helper_Functions::get_post_tag($post_type), '', $permastruct), "/");
			}
			unset($options['base-editor']);

			// Grab posts from database
			$sql_query = "SELECT * FROM {$wpdb->posts} WHERE post_status IN ('publish') LIMIT 99999";
			$posts = $wpdb->get_results($sql_query);

			foreach($posts as $post) {
				$uri = Permalink_Manager_Helper_Functions::get_uri($post, true);

				// Do not save default permastructures
				$default_permastruct = trim( Permalink_Manager_Helper_Functions::get_default_permastruct($post_type), "/" );
				if ($permastruct != $default_permastruct) $uris[$post->ID] = trim($uri, "/");
			}

			// Save new option fields
			update_option('permalink-manager-uris', $uris);
			update_option('permalink-manager', $options);
			update_option('permalink-manager-permastructs', $new_permastructs);

			// Reset rewrite rules
			flush_rewrite_rules();
		}
	}
}

/**
 * Begins execution of the plugin.
 */
function run_permalink_manager() {

	// Load plugin files.
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-editor.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-base-editor.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-screen-options.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-helper-functions.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-actions.php';

	$Permalink_Manager_Class = new Permalink_Manager_Class();
	$Permalink_Manager_Screen_Options = new Permalink_Manager_Screen_Options();

}

run_permalink_manager();
