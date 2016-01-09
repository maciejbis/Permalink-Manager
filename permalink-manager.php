<?php

/**
 * Plugin Name:       Permalink Manager
 * Plugin URI:        http://maciejbis.net/
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.2.0
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
define( 'PERMALINK_MANAGER_VERSION', '0.2.0' );
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_WEBSITE', 'http://maciejbis.net' );
define( 'PERMALINK_MANAGER_MENU_PAGE', 'tools_page_permalink-manager' );
define( 'PERMALINK_MANAGER_OPTIONS_PAGE', PERMALINK_MANAGER_PLUGIN_NAME . '.php' );

class Permalink_Manager_Class {

	protected $permalink_manager, $admin_page, $permalink_manager_options, $permalink_manager_options_page;

	public function __construct() {

        $this->permalink_manager_options = get_option('permalink-manager');
		
		add_action( 'plugins_loaded', array($this, 'localize_me') );
		add_action( 'admin_init', array($this, 'process_forms') );
		add_action( 'admin_menu', array($this, 'add_menu_page') );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugins_page_links') );
		
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
	function permalinks_table_html() {
		global $wpdb;
		
		$Permalink_Manager_Table = new Permalink_Manager_Table();
        $Permalink_Manager_Table->set_screen_option_fields($this->fields_arrays('screen_options'));
		$Permalink_Manager_Table->prepare_items($wpdb->posts);
		
		?>
	
		<form id="permalinks-table" method="post">
			<input type="hidden" name="page" value="<?php echo $_POST['page']; ?>" />
			<?php echo $Permalink_Manager_Table->display(); ?>
		</form>
	<?php
	}
	
	/**
	 * Mass replace options page.
	 */
	function find_and_replace_html() {
		$button = get_submit_button( __( 'Find & Replace', 'permalink-manager' ), 'primary', 'find-replace-button', false );
		
		$return = "<form id=\"permalinks-table-find-replace\" method=\"post\">";
        $return .= "<table class=\"form-table\">";
        
        foreach($this->fields_arrays('find_replace') as $field_name => $field_args) {
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
        $return .= "<table class=\"form-table\">";
        
        foreach($this->fields_arrays('regenerate') as $field_name => $field_args) {
            $return .= Permalink_Manager_Helper_Functions::generate_option_field($field_name, $field_args, 'regenerate');
        }
        
        $return .= "</table>{$button}";
		$return .= "</form>";
		
		echo $return;
	}
	
	/**
	 * Display the plugin dashboard.
	 */
	function list_slugs_admin_page() {
		global $wpdb;
		
		// Check which tab is active now.
		$active_tab = ( isset( $_GET[ 'tab' ] ) ) ? $_GET[ 'tab' ] : 'permalinks';
		
		// Tabs array with assigned functions used to display HTML content.
		$tabs = array(
			'permalinks' => array(
				'name'			=>	__('Permalinks', 'permalink-manager'), 
				'function'		=>	'permalinks_table_html',
				'description'	=> __('You can disable/enable selected post types from the table below using <strong>"Screen Options"</strong> (click on the upper-right button to show it) section above.', 'permalink-manager')
			),
			'find_and_replace' => array(
				'name'			=>	__('Find and replace', 'permalink-manager'),
				'function'		=>	'find_and_replace_html',
				'warning'		=> (__('<strong>You are doing it at your own risk!</strong>', 'permalink-manager') . '<br />' . __('A backup of MySQL database before using this tool is highly recommended. The search & replace operation might be not revertible!', 'permalink-manager'))
			),
			'regenerate_slugs' => array(
				'name'			=>	__('Regenerate slugs', 'permalink-manager'),
				'function'		=>	'regenerate_slugs_html',
				'warning'		=> (__('<strong>You are doing it at your own risk!</strong>', 'permalink-manager') . '<br />' . __('A backup of MySQL database before using this tool is highly recommended. The regenerate process of slugs might be not revertible!', 'permalink-manager'))
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

						echo '<div data-tab="' . $tab_id . '" id="' . $tab_id . '" class="' . $active_show . '">';
							echo (isset($tab_properties['description'])) ? "<div class=\"info alert\"><p>{$tab_properties['description']}</p></div>" : "";
							echo (isset($tab_properties['warning'])) ? "<div class=\"warning alert\"><p>{$tab_properties['warning']}</p></div>" : "";
							$this->$tab_properties['function']();
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
		$all_post_types_array = Permalink_Manager_Helper_Functions::get_post_types_array();
		$all_post_statuses_array = get_post_statuses();
        
		// Fields for "Screen Options"
        $screen_options = array(
            'post_types' => array(
                'label' => __( 'Post Types', 'permalink-manager' ),
                'type' => 'checkbox', 
                'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types_array),
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
		$find_replace = array(
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
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types_array),
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
		$regenerate = array(
			'post_types' => array(
				'label' => __( 'Post Types that should be affected', 'permalink-manager' ),
				'type' => 'checkbox', 
				'choices' => array_merge(array('all' => '<strong>' . __('All Post Types', 'permalink-manager') . '</strong>'), $all_post_types_array),
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
     * Check if the provided slug is unique and then update it with SQL query.
     */
	function update_slug_by_id($slug, $id) {
		global $wpdb;
		
		// Update slug and make it unique
		$slug = (empty($slug)) ? sanitize_title(get_the_title($id)) : $slug; 
		$new_slug = wp_unique_post_slug($slug, $id, get_post_status($id), get_post_type($id), null);
		$wpdb->query("UPDATE $wpdb->posts SET post_name = '$new_slug' WHERE ID = '$id'");
		
		return $new_slug;
	}

	/**
	 * Bulk actions functions
	 */
	function process_forms() {
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
						$this->update_slug_by_id($new_slug, $id);
						
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
			$find_replace_fields = $this->fields_arrays('find_replace');
			foreach($var as $key => $val) {
				if(empty($val)) $errors .= '<p>' . sprintf( __( '<strong>"%1s"</strong> field is empty!', 'permalink-manager' ), $find_replace_fields[$key]['label'] ) . '</p>';
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
					$this->update_slug_by_id($new_slug, $row['ID']);
					
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
			$post_types_array = ($_POST['permalink-manager']['regenerate']['post_types']);
			$post_statuses_array = ($_POST['permalink-manager']['regenerate']['post_statuses']);
			
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
					
						$this->update_slug_by_id($new_slug, get_the_ID());
						$updated_array[] = array('post_title' => get_the_title(), 'old_slug' => $old_slug, 'new_slug' => $new_slug);
					}
				}
			}
			
			// Restore original Post Data
			wp_reset_postdata();
			
		}
		
		/**
		 * Display results
		 */
		if((isset($_POST['permalink-manager']) || isset($_POST['update_all_slugs'])) && !(isset($_POST['screen-options-apply']))) {
			// Display errors or success message
			add_filter('permalink-manager-before-tabs', function( $arg ) use ( $updated_slugs_count, $alert_content, $alert_type ) {

				// Check how many rows/slugs were affected
				if($updated_slugs_count > 0) {
					$alert_type = 'updated';
					$alert_content = sprintf( _n( '<strong>%d</strong> slug were updated!', '<strong>%d</strong> slugs were updated!', $updated_slugs_count, 'permalink-manager' ), $updated_slugs_count ) . ' ';
					$alert_content .= sprintf( __( '<a href="%s">Click here</a> to go to the list of updated slugs', 'permalink-manager' ), '#updated-list');
				} else {
					$alert_type = 'error';
					$alert_content = ($alert_content) ? $alert_content : __( '<strong>No slugs</strong> were updated!', 'permalink-manager' );
				}

				return Permalink_Manager_Helper_Functions::display_alert($alert_content, $alert_type);

			});

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
}

/**
 * Begins execution of the plugin.
 */
function run_permalink_manager() {

	// Load plugin files.
	if( is_admin() ) {
		
		require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-wp-table.php';
		require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-screen-options.php';
		require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-helper-functions.php';
		
		$Permalink_Manager_Class = new Permalink_Manager_Class();
		$Permalink_Manager_Screen_Options = new Permalink_Manager_Screen_Options();
		
	}

}

run_permalink_manager();
