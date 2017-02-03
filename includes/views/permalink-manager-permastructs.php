<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class Permalink_Manager_Permastructs extends Permalink_Manager_Class {

  public function __construct() {
    add_filter( 'permalink-manager-sections', array($this, 'add_admin_section'), 1 );
  }

  public function add_admin_section($admin_sections) {

    $admin_sections['permastructs'] = array(
      'name'				=>	__('Permastructures', 'permalink-manager'),
      'function'    => array('class' => 'Permalink_Manager_Permastructs', 'method' => 'output')
    );

    return $admin_sections;
  }

  /**
   * Get the array with settings and render the HTML output
   */
  public function output() {
    global $permalink_manager_permastructs;

    $all_post_types = Permalink_Manager_Helper_Functions::get_post_types_array('full');
    $fields = array();

    // 2. Get all post types
    foreach($all_post_types as $post_type) {

      $default_permastruct = Permalink_Manager_Helper_Functions::get_default_permastruct($post_type['name'], true);
      $current_permastruct = isset($permalink_manager_permastructs[$post_type['name']]) ? $permalink_manager_permastructs[$post_type['name']] : '';

      $fields["permastructures[{$post_type['name']}]"] = array(
        'label' => $post_type['label'],
        'container' => 'row',
        'input_class' => 'widefat',
        'value' => $current_permastruct,
        'placeholder' => $default_permastruct,
        'type' => 'text'
      );
    }

    // 2. Display some notes
    $notes[] = sprintf( __('All available <a href="%s" target="_blank">structure tags</a> allowed are listed below. Please note that some of them can be used only for particular post types.', 'permalink-manager'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags") . "<br />" . Permalink_Manager_Helper_Functions::get_all_structure_tags() . "\n";
    $notes[] = __('Each Custom Post Type should have unique permastructure.', 'permalink-manager');
    $notes[] = __('Please note that the following settings will be applied only to new posts.<br />If you would like to use the current permastructures settings, you will need to regenerate the posts\' URIs in <strong>"Tools -> Regnerate/Reset"</strong> section.', 'permalink-manager');
    $notes[] = __('To use the native permastruct please keep the field empty.', 'permalink-manager');
    $sidebar = '<h3>' . __('Usage Instructions', 'permalink-manager') . '</h3>';
    $sidebar .= "<ol><li>" . implode('</li><li>', $notes) . "</li></ol>";

    $output = Permalink_Manager_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __( 'Save permastructures', 'permalink-manager' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'save_settings', 'name' => 'permalink-manager-permastructs'));
    return $output;
  }

}
