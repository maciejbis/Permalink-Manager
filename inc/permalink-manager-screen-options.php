<?php

/**
 * Additional Screen Options that helps to manage the permalinks in the table
 */

class Permalink_Manager_Screen_Options extends Permalink_Manager_Class {
    
    protected $parent_admin_page;
    
    public function __construct($parent_admin_page) {
        
        $this->parent_admin_page = $parent_admin_page;
        
        add_action( "load-{$this->parent_admin_page}", array($this, "save_screen_options") );
        add_filter( "screen_settings", array($this, "add_screen_options") );
        
    }
    
    /**
     * Add scren options
     */
    public function add_screen_options($status, $args) {
        
        $button = get_submit_button( __( 'Apply', 'permalink-manager' ), 'primary', 'screen-options-apply', false );
        $return .= "<fieldset>";
        
        foreach(parent::screen_options_fields() as $field_name => $field_args) {
            
            $return .= $this->generate_screen_option_field($field_name, $field_args);
            
        }
        
        $return .= "</fieldset><br class='clear'>{$button}";

        return $return;
        
    }
    
    /**
     * Save fields
     */
    public function save_screen_options() {
        
        if(isset($_POST['permalink-manager'])) update_option('permalink-manager', $_POST['permalink-manager']);
        
    }
    
    /** 
     * Generate the output for "Screen Options" section
     */
    public function generate_screen_option_field($name, $args) {
        
        $saved_options = get_option('permalink-manager');
        
        $output = "<legend>{$args['label']}</legend>";
        $output .= "<div class='metabox-prefs'><div class='{$name}-container'>";
            
        if($args['type'] == 'checkbox') {
            foreach($args['choices'] as $value => $label) {
                $all_checked = ($saved_options[$name]) ? $saved_options[$name] : $args['default'];
                $checked = in_array($value, $all_checked) ? "checked='checked'" : ""; 
                $output .= "<label for='{$label}'><input type='checkbox' value='{$value}' name='permalink-manager[{$name}][]' {$checked} /> {$label}</label>";
            }
        } elseif($args['type'] == 'number') {
            $value = ($saved_options[$name]) ? $saved_options[$name] : $args['default'];
            $output .= "<label for='{$label}'><input type='number' value='{$value}' name='permalink-manager[{$name}]' /> {$label}</label>";
        } else {
            $value = ($saved_options[$name]) ? $saved_options[$name] : $args['default'];
            $output .= "<label for='{$label}'><input type='text' value='{$value}' name='permalink-manager[{$name}]' /> {$label}</label>";
        }
                    
        $output .= "</div></div>";
        
        return $output;
        
    }
       
}