<?php

/**
 * Additional Screen Options that helps to manage the permalinks in the table
 */

class Permalink_Manager_Screen_Options extends Permalink_Manager_Class {
    
    public function __construct() {
        
		$admin_page = PERMALINK_MANAGER_MENU_PAGE;
        add_action( "load-{$admin_page}", array($this, "save_screen_options") );
        add_filter( "screen_settings", array($this, "add_screen_options") );
        
    }
    
    /**
     * Add scren options
     */
    public function add_screen_options() {
        
        $button = get_submit_button( __( 'Apply', 'permalink-manager' ), 'primary', 'screen-options-apply', false );
        $return = "<fieldset>";
        
        foreach(parent::fields_arrays('screen_options') as $field_name => $field_args) {
            
            $return .= Permalink_Manager_Helper_Functions::generate_option_field($field_name, $field_args, 'screen-options');
            
        }
        
        $return .= "</fieldset><br class='clear'>{$button}";

        return $return;
        
    }
    
    /**
     * Save fields
     */
    public function save_screen_options() {
   
        if(isset($_POST['screen-options-apply'])) update_option('permalink-manager', $_POST['permalink-manager']['screen-options']);
        
    }
       
}