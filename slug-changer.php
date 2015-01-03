<?php

/*ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);*/

/**
 * Plugin Name:       Slug Changer
 * Plugin URI:        http://maciejbis.net/slug-changer
 * Description:       A simple tool that allows to mass update of slugs that are used to build permalinks for Posts, Pages and Custom Post Types.
 * Version:           0.1.0
 * Author:            Maciej Bis
 * Author URI:        http://maciejbis.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       slug-changer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define the directories used to load plugin files.
define( 'SLUG_CHANGER_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'SLUG_CHANGER_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'SLUG_CHANGER_PLUGIN_NAME', 'slug-changer' );

class Slug_Changer_Class {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $slug_changer    The ID of this plugin.
	 */
	private $slug_changer;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @var      string    $slug_changer       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct() {

		$this->slug_changer = SLUG_CHANGER_PLUGIN_NAME;
		$this->version = '1.0.0';
		
		add_action( 'admin_menu', array($this, 'add_menu_page' ));

	}
	
	/**
	 * Add menu page and load CSS & JS.
	 *
	 * @since    1.0.0
	 */
	public function add_menu_page() {
		$page_hook_suffix = add_menu_page( 'Slug Changer', 'Slug Changer', 'manage_options', SLUG_CHANGER_PLUGIN_NAME . '.php', array($this, 'list_slugs_admin_page'), 'dashicons-update' );
		
		// Make sure thata the CSS and JS files are loaded only on plugin admin page.
		add_action( 'admin_print_scripts-' . $page_hook_suffix, array($this, 'enqueue_styles' ) );
		add_action( 'admin_print_scripts-' . $page_hook_suffix, array($this, 'enqueue_scripts' ) );
	}
	
	/**
     * Display the table with slugs.
     *
     * @since    1.0.0
     */
	public function slug_table_html() {
		global $wpdb;
		$SlugsListTable = new SlugsListTable();
		$SlugsListTable->prepare_items($wpdb->posts);
		?>
	
		<form id="slugs-table" method="post">
			<input type="hidden" name="page" value="<?php echo $_POST['page']; ?>" />
			<?php echo $SlugsListTable->display(); ?>
		</form>
	
	<?php
	}
	
	/**
     * Display the options tab.
     *
     * @since    1.0.0
     */
	public function slug_options_html() { ?>
	
		Options.
	
	<?php
	}
	
	/**
     * Display the plugin dashboard.
     *
     * @since    1.0.0
     */
	public function list_slugs_admin_page() {
		global $wpdb;
		
		// Check which tab is active now.
		$active_tab = ( isset( $_GET[ 'tab' ] ) ) ? $_GET[ 'tab' ] : 'slugs';
		
		// Tabs array with assigned functions used to display HTML content.
		$tabs = array(
			'slugs'		=>	array( 'name' => __('Slugs', 'slug-changer'), 'function' => 'slug_table_html' ),
			'options'	=>	array( 'name' => __('Options', 'slug-changer'), 'function' => 'slug_options_html' )
		);
		
		?>
			<div class="wrap">
			
				<div id="icon-themes" class="icon32"></div>
				<h2>Slug Changer</h2>
				
				<h2 class="nav-tab-wrapper">
					<?php 
					foreach($tabs as $tab_id => $tab_properties) {
						$active_class = ($active_tab === $tab_id) ? 'nav-tab-active nav-tab' : 'nav-tab';
						echo '<a href="' . admin_url('admin.php?page=' . SLUG_CHANGER_PLUGIN_NAME . '.php&tab=' . $tab_id) . '" class="' . $active_class . '">' . $tab_properties['name'] . '</a>';
					} ?>
				</h2>
				
				<?php
				foreach($tabs as $tab_id => $tab_properties) { 
					$active_show = ($active_tab === $tab_id) ? 'show' : 'hide';
					
					echo '<div id="' . $tab_id . '" class="' . $active_show . '">';
						$this->$tab_properties['function']();
					echo '</div>';
				} ?>
				
			</div>
		<?php
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->slug_changer, SLUG_CHANGER_URL . '/css/slug-changer-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->slug_changer, SLUG_CHANGER_URL . '/js/slug-changer-admin.js', array( 'jquery' ), $this->version, false );

	}

}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_slug_changer() {

	// Load plugin files.
	require_once SLUG_CHANGER_DIR . '/slug-changer-wp-table.php';
	
	$plugin = new Slug_Changer_Class();

}

run_slug_changer();
