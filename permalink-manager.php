<?php

/*ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);*/

/**
 * Plugin Name:       Permalink Manager
 * Plugin URI:        http://maciejbis.net/
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.1.0
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
define( 'PERMALINK_MANAGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'PERMALINK_MANAGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PERMALINK_MANAGER_PLUGIN_NAME', 'permalink-manager' );
define( 'PERMALINK_MANAGER_VERSION', '0.1.0' );

class Permalink_Manager_Class {

	protected $permalink_manager, $admin_page, $permalink_manager_options;

	public function __construct() {

		$this->permalink_manager = PERMALINK_MANAGER_PLUGIN_NAME;
        $this->permalink_manager_options = get_option('permalink-manager');
        
		add_action( 'admin_init', array($this, 'update_all_slugs') );
		add_action( 'admin_menu', array($this, 'add_menu_page') );

	}
	
	/**
	 * Add menu page and load CSS & JS.
	 */
	public function add_menu_page() {
		$this->admin_page = add_options_page( __('Permalink Manager', 'permalink-manager'), __('Permalink Manager', 'permalink-manager'), 'manage_options', PERMALINK_MANAGER_PLUGIN_NAME . '.php', array($this, 'list_slugs_admin_page') );
		
        // Add screen options and process them
        $Permalink_Manager_Screen_Options = new Permalink_Manager_Screen_Options($this->admin_page);
        
		// Make sure thata the CSS and JS files are loaded only on plugin admin page.
		add_action( 'admin_print_scripts-' . $this->admin_page, array($this, 'enqueue_styles' ) );
		add_action( 'admin_print_scripts-' . $this->admin_page, array($this, 'enqueue_scripts' ) );
	}
	
	/**
     * Display the table with slugs.
     */
	public function permalinks_table_html() {
		global $wpdb;
		$Permalink_Manager_Table = new Permalink_Manager_Table();
        $Permalink_Manager_Table->set_screen_option_fields($this->screen_options_fields());
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
	public function slug_mass_replace_html() { ?>
	
		<p>Mass replace.</p>
	
	<?php
	}
	
	/**
     * Display the plugin dashboard.
     *
     */
	public function list_slugs_admin_page() {
		global $wpdb;
		
		// Check which tab is active now.
		$active_tab = ( isset( $_GET[ 'tab' ] ) ) ? $_GET[ 'tab' ] : 'permalinks';
		
		// Tabs array with assigned functions used to display HTML content.
		$tabs = array(
			'permalinks' => array(
				'name'			=>	__('Permalinks', 'permalink-manager'), 
				'function'		=>	'permalinks_table_html',
				'description'	=> __('You can disable/enable particular post types from the table below using <strong>"Screen Options"</strong> (click on the upper-right button to show it) section above.', 'permalink-manager')
			),
			'mass_replace' => array(
				'name'			=>	__('Mass slug replace', 'permalink-manager'),
				'function'		=>	'slug_mass_replace_html',
				'description'	=> __('<strong>Please backup you MySQL database before using this tool!</strong>', 'permalink-manager')
			),
		);
		
		?>
			<div id="permalinks-table-wrap" class="wrap">
			
				<div id="icon-themes" class="icon32"></div>
				<h2><?php _e('Permalink Manager', 'permalink-manager'); ?></h2>
				
				<h2 class="nav-tab-wrapper">
					<?php 
					foreach($tabs as $tab_id => $tab_properties) {
						$active_class = ($active_tab === $tab_id) ? 'nav-tab-active nav-tab' : 'nav-tab';
						echo '<a href="' . admin_url('admin.php?page=' . PERMALINK_MANAGER_PLUGIN_NAME . '.php&tab=' . $tab_id) . '" class="' . $active_class . '">' . $tab_properties['name'] . '</a>';
					} ?>
				</h2>
				
				<?php
				foreach($tabs as $tab_id => $tab_properties) { 
					$active_show = ($active_tab === $tab_id) ? 'show' : 'hide';
					
					echo '<div id="' . $tab_id . '" class="' . $active_show . '">';
						echo ($tab_properties['description']) ? "<p>{$tab_properties['description']}</p>" : "";
						$this->$tab_properties['function']();
					echo '</div>';
				} ?>
				
			</div>
		<?php
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->permalink_manager, PERMALINK_MANAGER_URL . '/css/permalink-manager-admin.css', array(), PERMALINK_MANAGER_VERSION, 'all' );

	}

	/**
	 * Register the JavaScript for the dashboard.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->permalink_manager, PERMALINK_MANAGER_URL . '/js/permalink-manager-admin.js', array( 'jquery' ), PERMALINK_MANAGER_VERSION, false );

	}
    
    /**
     * Get post_types array
     */
    public function get_post_types_array() {
        
        $post_types = get_post_types( array('public' => true), 'objects' ); 
        
        $post_types_array = array();
        foreach ( $post_types as $post_type ) {
          $post_types_array[$post_type->name] = $post_type->labels->name;
        }
        
        return $post_types_array;
        
    }
    
    /**
     * Fields for "Screen Options"
     */
    public function screen_options_fields() {
        
        return array(
            'post_types' => array(
                'label' => __( 'Post Types', 'permalink-manager' ),
                'type' => 'checkbox', 
                'choices' => $this->get_post_types_array(),
                'default' => array('post', 'page')
            ),
            'per_page' => array(
                'label' => __( 'Per page', 'permalink-manager' ),
                'type' => 'number',
                'default' => 10
            )
        );
        
    }
	
	/**
     * Check if the provided slug is unique and then update it with SQL query.
     */
	function update_slug_by_id($posts_table, $slug, $id) {
		global $wpdb;
		
		// Update slug and make it unique
		$slug = (empty($slug)) ? sanitize_title(get_the_title($id)) : $slug; 
		$new_slug = wp_unique_post_slug($slug, $id, get_post_status($id), get_post_type($id), null);
		$wpdb->query("UPDATE $posts_table SET post_name = '$new_slug' WHERE ID = '$id'");
		
		return $new_slug;
	}

    /**
     * Bulk actions functions
     */
    function update_all_slugs() {
        global $wpdb;
		$posts_table = $wpdb->posts;

        if (isset($_POST['update_all_slugs'])) {
        	
			$slugs = isset($_POST['slug']) ? $_POST['slug'] : array();
			
			// Double check if the slugs and ids are stored in arrays
			if (!is_array($slugs)) $slugs = explode(',', $slugs);
			
			if (!empty($slugs)) {
				foreach($slugs as $id => $slug) {
					// Update slugs
					$this->update_slug_by_id($posts_table, $slug, $id);
					// Reset slug
					$slug = '';
				}
			}
			
			// A dirty trick to make the permalinks in the table refreshed
			wp_safe_redirect( $_SERVER['REQUEST_URI'] );
			exit;
			
		}
		
    }
	
}

/**
 * Begins execution of the plugin.
 */
function run_permalink_manager() {

	// Load plugin files.
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-wp-table.php';
	require_once PERMALINK_MANAGER_DIR . '/inc/permalink-manager-screen-options.php';
    
	$Permalink_Manager_Class = new Permalink_Manager_Class();
	$Permalink_Manager_Class->get_post_types_array();

}

run_permalink_manager();
