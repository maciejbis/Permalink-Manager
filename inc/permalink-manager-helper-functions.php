<?php

/**
 * Additional functions used in classes and another subclasses
 */

class Permalink_Manager_Helper_Functions extends Permalink_Manager_Class {
    
    public function __construct() { 
		parent::construct();
	}
    	
	/**
	 * Display error/info message
	 */
	function display_alert($content, $type) {
		return sprintf( "<div class='{$type} is-dismissible notice'><p> %s </p></div>", $content );
	}
	
	/**
	 * Display the permalink in a better way
	 */
	function get_correct_permalink($id, $correct_slug, $highlight = false) {
		$output = get_permalink($id);
		
		// Get last part of URI
		$old_slug = end((explode('/', get_page_uri($id)))); 
		$correct_slug = ($highlight) ? "<code>{$correct_slug}</code>" : $correct_slug;
		$output = Permalink_Manager_Helper_Functions::str_lreplace($old_slug, $correct_slug, $output);
		
		return $output;
	}
	
	/**
	 * Get post_types array
	 */
	function get_post_types_array() {
        $post_types = get_post_types( array('public' => true), 'objects' ); 

        $post_types_array = array();
        foreach ( $post_types as $post_type ) {
			$post_types_array[$post_type->name] = $post_type->labels->name;
        }

        return $post_types_array;   
    }
	
	/**
	 * Replace last occurence
	 */
	function str_lreplace($search, $replace, $subject) {
    	$pos = strrpos($subject, $search);
    	return ($pos !== false) ? substr_replace($subject, $replace, $pos, strlen($search)) : $subject;
	}
	
	/** 
     * Generate the fields
     */
    public function generate_option_field($name, $args, $group) {
        
		// Load values from options if needed
		//$saved_values = (in_array($group, array('screen-options', 'find-replace'))) ? get_option('permalink-manager') : '';    
		$saved_values = get_option('permalink-manager');    
		
		// Reset $fields variable
		$fields = '';
            
        switch($args['type']) {
			case 'checkbox' :
				$fields .= '<div class="checkboxes">';
				foreach($args['choices'] as $value => $label) {
					$all_checked = (isset($saved_values[$name])) ? $saved_values[$name] : $args['default'];
					$checked = in_array($value, $all_checked) ? "checked='checked'" : ""; 
					$fields .= "<label for='permalink-manager[{$group}][{$name}][]'><input type='checkbox' value='{$value}' name='permalink-manager[{$group}][{$name}][]' {$checked} /> {$label}</label>";
				}
				$fields .= '</div>';
				break;
			case 'number' :
            	$value = (isset($saved_values[$name])) ? $saved_values[$name] : $args['default'];
            	$fields .= "<input type='number' value='{$value}' name='permalink-manager[{$group}][{$name}]' />";
				break;
        	default :
            	$value = (isset($saved_values[$name])) ? $saved_values[$name] : $args['default'];
            	$fields .= "<input type='text' value='{$value}' name='permalink-manager[{$group}][{$name}]' />";
		}
        
		// Get all variables into one final variable
		if($screenoptions) {
			$output = "<legend>{$args['label']}</legend>";
			$output .= "<div class='metabox-prefs'><div class='{$name}-container'>{$fields}</div></div>";
		} else {
			$output = "<tr><th><label for='permalink-manager[{$name}]'>{$args['label']}</label></th>";
			$output .= "<td>{$fields}</td>";
		}
		
        return $output;
        
    }
	
}