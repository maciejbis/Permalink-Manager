<?php

/**
 * Plugin Name:       Permalink Manager
 * Plugin URI:        http://maciejbis.net/
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.3.3
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
define( 'PERMALINK_MANAGER_VERSION', '0.3.3' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://maciejbis.net' );
define( 'PERMALINK_MANAGER_MENU_PAGE', 'tools_page_permalink-manager' );
define( 'PERMALINK_MANAGER_OPTIONS_PAGE', PERMALINK_MANAGER_PLUGIN_NAME . '.php' );

class Permalink_Manager_Class {

	protected $permalink_manager, $admin_page, $permalink_manager_options_page, $permalink_manager_options;

	public function __construct() {

    $this->permalink_manager_options = get_option('permalink-manager');

		if( is_admin() ) {
			add_action( 'plugins_loaded', array($this, 'localize_me') );
			add_action( 'init', array($this, 'flush_rewrite_rules') );
			add_action( 'admin_init', array($this, 'bulk_actions') );
			add_action( 'admin_menu', array($this, 'add_menu_page') );
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugins_page_links') );

			add_filter( 'page_rewrite_rules', array($this, 'custom_page_rewrite_rules'), 999, 1);
			add_filter( 'post_rewrite_rules', array($this, 'custom_post_rewrite_rules'), 999, 1);
			add_filter( 'rewrite_rules_array', array($this, 'custom_cpt_rewrite_rules'), 999, 1);
		}

		// Public functions
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

		$Permalink_Manager_Slug_Editor = new Permalink_Manager_Slug_Editor();
    $Permalink_Manager_Slug_Editor->set_screen_option_fields($this->fields_arrays('screen_options'));
		$Permalink_Manager_Slug_Editor->prepare_items($wpdb->posts);

		?>

		<form id="permalinks-table" method="post">
			<input type="hidden" name="tab" value="slug_editor" />
			<?php echo $Permalink_Manager_Slug_Editor->display(); ?>
		</form>
	<?php
	}

	/**
	 * Mass replace options page.
	 */
	function find_and_replace_html() {
		$button = get_submit_button( __( 'Find & Replace', 'permalink-manager' ), 'primary', 'find-replace-button', false );

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

		//echo '<pre>';
		//print_r($wp_rewrite);
		//echo '</pre>';

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
				'name'				=>	__('Slug Editor', 'permalink-manager'),
				'function'		=>	'slug_editor_html',
				'description'	=>	__('You can disable/enable selected post types from the table below using <strong>"Screen Options"</strong> (click on the upper-right button to show it) section above.', 'permalink-manager')
			),
			'find_and_replace' => array(
				'name'				=>	__('Find and replace', 'permalink-manager'),
				'function'		=>	'find_and_replace_html',
				'warning'			=>	(__('<strong>You are doing it at your own risk!</strong>', 'permalink-manager') . '<br />' . __('A backup of MySQL database before using this tool is highly recommended. The search & replace operation might be not revertible!', 'permalink-manager'))
			),
			'regenerate_slugs' => array(
				'name'				=>	__('Regenerate slugs', 'permalink-manager'),
				'function'		=>	'regenerate_slugs_html',
				'warning'			=>	(__('<strong>You are doing it at your own risk!</strong>', 'permalink-manager') . '<br />' . __('A backup of MySQL database before using this tool is highly recommended. The regenerate process of slugs might be not revertible!', 'permalink-manager'))
			),
			'base_editor' => array(
				'name'				=>	__('Permalinks Base Editor', 'permalink-manager'),
				'function'		=>	'base_editor_html',
				'warning'			=>	array(
														sprintf(__('<strong>This is an experimental feature!</strong> Please report all the bugs & issues <a href="%s">here</a>.', 'permalink-manager'), 'https://wordpress.org/support/plugin/permalink-manager'),
														__('Custom Post Types should have their own, unique front, eg. <em>products/%product%!</em>', 'permalink-manager'),
														__('After you update & save the settings below, you need to flush the rewrite rules!', 'permalink-manager'),
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
			'old_string' => array(
        'label' => __( 'Find ...', 'permalink-manager' ),
        'type' => 'text',
      ),
			'new_string' => array(
        'label' => __( 'Replace with ...', 'permalink-manager' ),
        'type' => 'text',
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

		$updated_slugs_count = 0;
		$updated_array = array();
		$alert_type = $alert_content = $errors = $main_content = '';

		if (isset($_POST['update_all_slugs'])) {

			$slugs = isset($_POST['slug']) ? $_POST['slug'] : array();

			// Double check if the slugs and ids are stored in arrays
			if (!is_array($slugs)) $slugs = explode(',', $slugs);

			if (!empty($slugs)) {
				foreach($slugs as $id => $new_slug) {
					$this_post = get_post($id);

					// Check if slug was changed
					if($this_post->post_name != $new_slug) {
						// Update slugs
						Permalink_Manager_Helper_Functions::update_slug_by_id($new_slug, $id);

						$updated_array[] = array('post_title' => get_the_title($id), 'old_slug' => $this_post->post_name, 'new_slug' => $new_slug);
						$updated_slugs_count++;
					}

					// Reset slug
					$slug = '';
				}
			}

		} else if (isset($_POST['find-replace-button'])) {

			$var['old_string'] = esc_sql($_POST['permalink-manager']['find-replace']['old_string']);
			$var['new_string'] = esc_sql($_POST['permalink-manager']['find-replace']['new_string']);
			$post_types_array = ($_POST['permalink-manager']['find-replace']['post_types']);
			$post_statuses_array = ($_POST['permalink-manager']['find-replace']['post_statuses']);
			$var['post_types'] = implode("', '", $post_types_array);
			$var['post_statuses'] = implode("', '", $post_statuses_array);

			// Check if any of variables is not empty
			$find_and_replace_fields = $this->fields_arrays('find_and_replace');
			foreach($var as $key => $val) {
				if(empty($val)) $errors .= '<p>' . sprintf( __( '<strong>"%1s"</strong> field is empty!', 'permalink-manager' ), $find_and_replace_fields[$key]['label'] ) . '</p>';
			}

			// Save the rows before they are updated to an array
			$posts_to_update = $wpdb->get_results("SELECT post_title, post_name, ID FROM {$wpdb->posts} WHERE post_status IN ('{$var['post_statuses']}') AND post_name LIKE '%{$var['old_string']}%' AND post_type IN ('{$var['post_types']}')", ARRAY_A);

			// Now if the array is not empty use IDs from each subarray as a key
			if($posts_to_update && empty($errors)) {
				foreach ($posts_to_update as $row) {
					// Get new slug
					$old_slug = $row['post_name'];
					$new_slug = str_replace($var['old_string'], $var['new_string'], $old_slug);

					// Update slugs
					Permalink_Manager_Helper_Functions::update_slug_by_id($new_slug, $row['ID']);

					$updated_array[] = array('post_title' => $row['post_title'], 'old_slug' => $old_slug, 'new_slug' => $new_slug);
					$updated_slugs_count++;

					// Reset slug
					$slug = '';
				}
			} else {
				$alert_type = 'error';
				$alert_content = $errors;
			}

		} else if (isset($_POST['regenerate-button'])) {

			// Setup needed variables
			$post_types_array = ($_POST['permalink-manager']['regenerate_slugs']['post_types']);
			$post_statuses_array = ($_POST['permalink-manager']['regenerate_slugs']['post_statuses']);

			// Reset query
			$reset_query = new WP_Query( array( 'post_type' => $post_types_array, 'post_status' => $post_statuses_array, 'posts_per_page' => -1 ) );

			// The Loop
			if ( $reset_query->have_posts() ) {
				while ( $reset_query->have_posts() ) {
					$reset_query->the_post();
					$this_post = get_post(get_the_ID());

					$correct_slug = sanitize_title(get_the_title());
					$old_slug = $this_post->post_name;
					$new_slug = wp_unique_post_slug($correct_slug, get_the_ID(), get_post_status(get_the_ID()), get_post_type(get_the_ID()), null);

					if($old_slug != $new_slug) {
						$updated_slugs_count++;

						Permalink_Manager_Helper_Functions::update_slug_by_id($new_slug, get_the_ID());
						$updated_array[] = array('post_title' => get_the_title(), 'old_slug' => $old_slug, 'new_slug' => $new_slug);
					}
				}
			}

			// Restore original Post Data
			wp_reset_postdata();

		// Save Permalink Structures/Permalinks Bases
		} else if (isset($_POST['save_permalink_structures'])) {
			Permalink_Manager_Helper_Functions::save_option('base-editor', $_POST['permalink-manager']['base-editor']);

			$alert_type = 'updated';
			$alert_content = sprintf( __( '<a href="%s">Click here</a> to flush the rewrite rules (it is required to make the new permalinks working).', 'permalink-manager' ), admin_url('admin.php?page=' . PERMALINK_MANAGER_PLUGIN_NAME . '.php&flush_rewrite_rules=true&tab=base_editor'));
			Permalink_Manager_Helper_Functions::display_alert($alert_content, $alert_type, true);
			return;
		// Flush rewrite rules
		} else if (isset($_POST['flush_rewrite_rules'])) {
			$this->flush_rewrite_rules();
			return;
		}

		/**
		 * Display results
		 */
		if((isset($_POST['permalink-manager']) || isset($_POST['update_all_slugs'])) && !(isset($_POST['screen-options-apply']))) {
			// Display errors or success message

			// Check how many rows/slugs were affected
			if($updated_slugs_count > 0) {
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
			if ( $updated_slugs_count > 0 && $updated_array ) {
				add_filter('permalink-manager-after-tabs', function( $arg ) use ( $alert_content, $alert_type, $errors, $updated_array, $main_content ) {

					$header_footer = '<tr>';
						$header_footer .= '<th class="column-primary">' . __('Title', 'permalink-manager') . '</th>';
						$header_footer .= '<th>' . __('Old slug', 'permalink-manager') . '</th>';
						$header_footer .= '<th>' . __('New slug', 'permalink-manager') . '</th>';
					$header_footer .= '</tr>';

					$updated_slugs_count = 0;
					foreach($updated_array as $row) {
						// Odd/even class
						$updated_slugs_count++;
						$alternate_class = ($updated_slugs_count % 2 == 1) ? ' class="alternate"' : '';

						$main_content .= "<tr{$alternate_class}>";
							$main_content .= '<td class="row-title column-primary" data-colname="' . __('Title', 'permalink-manager') . '">' . $row['post_title'] . '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'permalink-manager') . '</span></button></td>';
							$main_content .= '<td data-colname="' . __('Old slug', 'permalink-manager') . '">' . $row['old_slug'] . '</td>';
							$main_content .= '<td data-colname="' . __('New slug', 'permalink-manager') . '">' . $row['new_slug'] . '</td>';
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
		$post = (is_integer($post)) ? get_post($post) : $post;
		$post_type = $post->post_type;
		$permastruct = isset($this->permalink_manager_options['base-editor'][$post_type]) ? $this->permalink_manager_options['base-editor'][$post_type] : '';

		// Ignore empty permastructures (do not change them)
		if(empty($permastruct) || $post->post_status != 'publish') return $permalink;

		// Get options
		if($permastruct) {
			$permalink = home_url() . "/" . trim($permastruct, '/');
		}

		/**
		 * Replace Structure Tags
		 */

		// Get the date
		$date = explode(" ",date('Y m d H i s', strtotime($post->post_date)));

		// Get the category (if needed)
		$category = '';
		if ( strpos($permalink, '%category%') !== false ) {
			$cats = get_the_category($post->ID);
			if ( $cats ) {
				usort($cats, '_usort_terms_by_ID'); // order by ID
				$category_object = apply_filters( 'post_link_category', $cats[0], $cats, $post );
				$category_object = get_term( $category_object, 'category' );
				$category = $category_object->slug;
				if ( $parent = $category_object->parent )
					$category = get_category_parents($parent, false, '/', true) . $category;
			}
			// show default category in permalinks, without having to assign it explicitly
			if ( empty($category) ) {
				$default_category = get_term( get_option( 'default_category' ), 'category' );
				$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
			}
		}

		// Get the author (if needed)
		$author = '';
		if ( strpos($permalink, '%author%') !== false ) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		// Fix for hierarchical CPT (start)
		$full_slug = get_page_uri($post);
		$post_type_tag = Permalink_Manager_Helper_Functions::get_post_tag($post_type);

		// Do the replacement (post tag is removed now to enable support for hierarchical CPT)
		$tags = array('%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%category%', '%author%', $post_type_tag);
		$replacements = array($date[0], $date[1], $date[2], $date[3], $date[4], $date[5], $post->ID, $category, $author, '');

		return str_replace($tags, $replacements, "{$permalink}{$full_slug}");
	}

	/**
	 * Add rewrite rules
	 */
	function custom_cpt_rewrite_rules($rules) {

		global $wp_rewrite;

		$new_rules = array();
		$permastructures = $this->permalink_manager_options['base-editor'];

		// Rewrite rules for Posts & Pages are defined in different filters
		unset($permastructures['post'], $permastructures['page']);

		foreach($permastructures as $post_type => $permastruct) {
			// Ignore empty permastructures (do not add them)
			if(empty($permastruct)) continue;

			$new_rule = $wp_rewrite->generate_rewrite_rules($wp_rewrite->root . $permastruct, EP_PERMALINK);
			$rules = array_merge($new_rule, $rules);
		}
		return $rules;
	}

	/**
	 * Post Rewrite Rules
	 */
	function custom_post_rewrite_rules($rules) {
		global $wp_rewrite;
		if(isset($this->permalink_manager_options['base-editor']['post'])) {
			$rules = $wp_rewrite->generate_rewrite_rules($wp_rewrite->root . $this->permalink_manager_options['base-editor']['post'], EP_PERMALINK);
		}
		return $rules;
	}

	/**
	 * Page Rewrite Rules
	 */
	function custom_page_rewrite_rules($rules) {
		global $wp_rewrite;
		if(isset($this->permalink_manager_options['base-editor']['page'])) {
			$rules = $wp_rewrite->generate_rewrite_rules($wp_rewrite->root . $this->permalink_manager_options['base-editor']['page'], EP_PERMALINK);
		}
		return $rules;
	}

	/**
	 * Flush rewrite rules
	 */
	function flush_rewrite_rules() {
		if(isset($_REQUEST['flush_rewrite_rules'])) {
			flush_rewrite_rules();

			$alert_type = 'updated';
			$alert_content = __( 'The rewrite rules are flushed!', 'permalink-manager' );
			return Permalink_Manager_Helper_Functions::display_alert($alert_content, $alert_type, true);
		}
	}

}

/**
 * Begins execution of the plugin.
 */
function run_permalink_manager() {

	// Load plugin files.
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-slug-editor.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-base-editor.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-screen-options.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-helper-functions.php';

	$Permalink_Manager_Class = new Permalink_Manager_Class();
	$Permalink_Manager_Screen_Options = new Permalink_Manager_Screen_Options();

}

run_permalink_manager();
