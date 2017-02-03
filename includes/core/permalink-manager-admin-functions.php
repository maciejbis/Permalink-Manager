<?php

/**
 * Additional back-end functions related to Wordpress Dashboard UI
 */
class Permalink_Manager_Admin_Functions extends Permalink_Manager_Class {

    public $menu_name, $sections, $active_section, $active_subsection;
    public $plugin_slug = PERMALINK_MANAGER_PLUGIN_SLUG;
    public $plugin_basename = PERMALINK_MANAGER_BASENAME;

    public function __construct() {
      add_action( "admin_menu", array($this, "add_menu_page") );
      add_action( 'admin_init', array($this, 'init') );

      // Allow to edit URIs in posts, pages & CPT elements
      add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 10, 4 );
    }

    /**
     * Hooks that should be triggered with "admin_init"
     */
    public function init() {
      // Additional link in "Plugins" page
      add_filter( "plugin_action_links_{$this->plugin_basename}", array($this, "plugins_page_links") );

      // Detect current section
      $this->sections = apply_filters('permalink-manager-sections', array());
      $this->get_current_section();

      // Save settings
      $this->save_settings();
    }

    /**
     * Get current section (only in plugin sections)
     */
    public function get_current_section() {
      global $active_section, $active_subsection;

      // 1. Get current section
      if(isset($_GET['page']) && $_GET['page'] == $this->plugin_slug) {
        if(isset($_POST['section'])) {
          $this->active_section = $_POST['section'];
        } else if(isset($_GET['section'])) {
          $this->active_section = $_GET['section'];
        } else {
          $sections_names = array_keys($this->sections);
          $this->active_section = $sections_names[0];
        }
      }

       // 2. Get current subsection
      if($this->active_section && isset($this->sections[$this->active_section]['subsections'])) {
        if(isset($_POST['subsection'])) {
          $this->active_subsection = $_POST['subsection'];
        } else if(isset($_GET['subsection'])) {
          $this->active_subsection = $_GET['subsection'];
        } else {
          $subsections_names = array_keys($this->sections[$this->active_section]['subsections']);
          $this->active_subsection = $subsections_names[0];
        }
      }

      // Set globals
      $active_section = $this->active_section;
      $active_subsection = $this->active_subsection;
    }

    /**
     * Save settings
     */
    public function save_settings() {
      global $permalink_manager_options, $permalink_manager_permastructs;

      // Check if the form was submitted
      if(empty($_POST)) { return; }

      $post_fields = $_POST;
      $new_options = array();
      $helper_functions = new Permalink_Manager_Helper_Functions();

      // 1. Sanitize the field values
      foreach($post_fields as $option_name => $option_value) {
        $new_options[$option_name] = $helper_functions->multidimensional_array_map('sanitize_text_field', $option_value);
      }

      // 2A. Save options/settings
      if(isset($_POST['permalink-manager-options']) && wp_verify_nonce($_POST['permalink-manager-options'], 'save_settings')) {
        // Override the global with settings
        $permalink_manager_options = $new_options = array_filter($new_options);

        // Save the settings in database
        update_option('permalink-manager', $new_options);
      }
      // 2B. Save permastructs
      else if(isset($_POST['permalink-manager-permastructs']) && wp_verify_nonce($_POST['permalink-manager-permastructs'], 'save_settings')) {
        // Trim the trailing slashes & remove empty permastructures
        $new_options = $helper_functions->multidimensional_array_map('untrailingslashit', $new_options['permastructures']);
        $new_options = array_filter($new_options);

        // Override the global with settings
        $permalink_manager_permastructs = $new_options;

        // Save the settings in database
        update_option('permalink-manager-permastructs', $new_options);
      }
    }

    /**
     * Add menu page.
     */
    public function add_menu_page() {
      $this->menu_name = add_management_page( __('Permalink Manager', 'permalink-manager'), __('Permalink Manager', 'permalink-manager'), 'manage_options', $this->plugin_slug, array($this, 'display_section') );

      // Make sure thata the CSS and JS files are loaded only on plugin admin page.
      add_action( 'admin_print_styles-' . $this->menu_name, array($this, 'enqueue_styles' ) );
      add_action( 'admin_print_scripts-' . $this->menu_name, array($this, 'enqueue_scripts' ) );
    }

    /**
     * Register the CSS file for the dashboard.
     */
    public function enqueue_styles() {
      wp_enqueue_style( $this->plugin_slug, PERMALINK_MANAGER_URL . '/out/permalink-manager-admin.css', array(), PERMALINK_MANAGER_VERSION, 'all' );
    }

    /**
     * Register the JavaScript file for the dashboard.
     */
    public function enqueue_scripts() {
      wp_enqueue_script( $this->plugin_slug, PERMALINK_MANAGER_URL . '/out/permalink-manager-admin.js', array( 'jquery' ), PERMALINK_MANAGER_VERSION, false );
    }

    /**
     * Get admin url for the plugin
     */
    function get_admin_url($append = '') {
      return menu_page_url( "{$this->plugin_slug}", false ) . $append;
    }

    /**
     * Additional links on "Plugins" page
     */
    public function plugins_page_links($links) {
      $links[] = '<a href="' . $this->get_admin_url()  .'">' . __( 'Go To Permalink Manager', 'permalink-manager' ) . '</a>';
      return $links;
    }

    /**
     * Generate the fields
     */
    static public function generate_option_field($input_name, $args) {
      global $permalink_manager_options;

      // Reset $fields variables
      $fields = $section_name = $field_name = '';

      // Allow to filter the $args
      $args = apply_filters('permalink-manager-field-args', $args, $input_name);

      $default = (isset($args['default'])) ? $args['default'] : '';
      $label = (isset($args['label'])) ? $args['label'] : '';
      $placeholder = (isset($args['placeholder'])) ? "placeholder=\"{$args['placeholder']}\"" : '';
      $readonly = (isset($args['readonly'])) ? "readonly=\"readonly\"" : '';
      $rows = (isset($args['rows'])) ? "rows=\"{$rows}\"" : "rows=\"5\"";
      $input_class = (isset($args['input_class'])) ? "class=\"{$args['input_class']}\"" : '';
      $container_class = (isset($args['container_class'])) ? " class=\"{$args['container_class']} field-container\"" : " class=\"field-container\"";
      $description = (isset($args['description'])) ? "<p class=\"field-description description\">{$args['description']}</p>" : "";
      $append_content = (isset($args['append_content'])) ? "{$args['append_content']}" : "";

      // Get the field value (if it is not set in $args)
      if(isset($args['value']) && empty($args['value']) == false) {
        $value = $args['value'];
      } else {
        // Extract the section and field name from $input_name
        preg_match("/(.*)\[(.*)\]/", $input_name, $field_section_and_name);

        if($field_section_and_name) {
          $section_name = $field_section_and_name[1];
          $field_name = $field_section_and_name[2];
          $value = (isset($permalink_manager_options[$section_name][$field_name])) ? $permalink_manager_options[$section_name][$field_name] : $default;
        } else {
          $value = (isset($permalink_manager_options[$input_name])) ? $permalink_manager_options[$input_name] : $default;
        }
      }

      switch($args['type']) {
        case 'checkbox' :
          $fields .= '<div class="checkboxes">';
          foreach($args['choices'] as $choice_value => $checkbox_label) {
            $checked = (is_array($value) && in_array($choice_value, $value)) ? "checked='checked'" : "";
            $fields .= "<label for='{$input_name}[]'><input type='checkbox' {$input_class} value='{$choice_value}' name='{$input_name}[]' {$checked} /> {$checkbox_label}</label>";
          }
          $fields .= '</div>';

          // Add helper checkboxes for bulk actions
          if(isset($args['select_all']) || isset($args['unselect_all'])) {
            $select_all_label = (!empty($args['select_all'])) ? $args['select_all'] : __('Select all', 'permalink-manager');
            $unselect_all_label = (!empty($args['unselect_all'])) ? $args['unselect_all'] : __('Unselect all', 'permalink-manager');

            $fields .= "<p class=\"checkbox_actions extra-links\">";
            $fields .= (isset($args['select_all'])) ? "<a href=\"#\" class=\"select_all\">{$select_all_label}</a>&nbsp;" : "";
            $fields .= (isset($args['unselect_all'])) ? "<a href=\"#\" class=\"unselect_all\">{$unselect_all_label}</a>" : "";
            $fields .= "</p>";
          }
          break;

        case 'radio' :
          $fields .= '<div class="radios">';
          foreach($args['choices'] as $choice_value => $checkbox_label) {
            $checked = ($choice_value == $value) ? "checked='checked'" : "";
            $fields .= "<label for='{$input_name}[]'><input type='radio' {$input_class} value='{$choice_value}' name='{$input_name}[]' {$checked} /> {$checkbox_label}</label>";
          }
          $fields .= '</div>';
          break;

        case 'select' :
          $fields .= '<div class="select">';
          $fields .= "<select name='{$input_name}' {$input_class}>";
          foreach($args['choices'] as $choice_value => $checkbox_label) {
            $selected = ($choice_value == $value) ? "selected='selected'" : "";
            $fields .= "<option value='{$choice_value}' {$selected} />{$checkbox_label}</option>";
          }
          $fields .= '</select>';
          $fields .= '</div>';
          break;

        case 'number' :
          $fields .= "<input type='number' {$input_class} value='{$value}' name='{$input_name}' />";
          break;

        case 'textarea' :
          $fields .= "<textarea {$input_class} name='{$input_name}' {$placeholder} {$readonly} {$rows}>{$value}</textarea>";
          break;

        case 'pre' :
          $fields .= "<pre {$input_class}>{$value}</pre>";
          break;

        case 'clearfix' :
          return "<div class=\"clearfix\"></div>";

        default :
          $fields .= "<input type='text' {$input_class} value='{$value}' name='{$input_name}' {$placeholder} {$readonly}/>";
      }

      // Get the final HTML output
      if(isset($args['container']) && $args['container'] == 'tools') {
        $output = "<div{$container_class}>";
        $output .= "<h4>{$label}</h4>";
        $output .= "<div class='{$input_name}-container'>{$fields}</div>";
        $output .= $description;
        $output .= $append_content;
        $output .= "</div>";
      } else if(isset($args['container']) && $args['container'] == 'row') {
        $output = "<tr><th><label for='{$input_name}'>{$args['label']}</label></th>";
        $output .= "<td>{$fields}{$description}</td></tr>";
        $output .= ($append_content) ? "<tr class=\"appended-row\"><td colspan=\"2\">{$append_content}</td></tr>" : "";
      } else {
        $output = $fields . $append_content;
      }

      return apply_filters('permalink-manager-field-output', $output);
    }

    /**
  	 * Allow to edit URIs from "Edit Post" admin pages
  	 */
  	function edit_uri_box($html, $id, $new_title, $new_slug) {
  		global $post, $permalink_manager_uris;

  		// Do not change anything if post is not saved yet
  		if(empty($post->post_name)) return $html;
  		$default_uri = trim(str_replace(home_url("/"), "", get_permalink($id)), "/");
  		$uri = (!empty($permalink_manager_uris[$id])) ? $permalink_manager_uris[$id] : $default_uri;

  		$html = preg_replace("/(<strong>(.*)<\/strong>)(.*)/is", "$1 ", $html);
  		$html .= home_url("/") . " <span id=\"editable-post-name\"><input type='text' value='{$uri}' name='custom_uri'/></span>";
  		return $html;
  	}

    /**
     * Display the form
     */
    static public function get_the_form($fields = array(), $container = '', $button = array(), $sidebar = '', $nonce = array()) {
      // 1. Check if the content will be displayed in columns and button details
      switch($container) {
        case 'columns-3' :
          $wrapper_class = 'columns-container';
          $form_column_class = 'column column-2_3';
          $sidebar_class = 'column column-1_3';
          break;
        // there will be more cases in future ...
        default :
          $wrapper_class = $form_column_class = $sidebar_class = '';
      }

      // 2. Process the array with button and nonce field settings
      $button_text = (!empty($button['text'])) ? $button['text'] : '';
      $button_class = (!empty($button['class'])) ? $button['class'] : '';
      $nonce_action = (!empty($nonce['action'])) ? $nonce['action'] : '';
      $nonce_name = (!empty($nonce['name'])) ? $nonce['name'] : '';

      // 2. Now get the HTML output (start section row container)
      $output = ($wrapper_class) ? "<div class=\"{$wrapper_class}\">" : '';

      // 3. Start fields' section
      $output .= ($form_column_class) ? "<div class=\"{$form_column_class}\">" : "";
      $output .= "<form method=\"POST\">";

      // Loop through all fields assigned to this section
      foreach($fields as $field_name => $field) {
        $field['container'] = 'tools';

        // A. Detect fields group
        if(isset($field['group'])) {
          $output .= "<div class=\"columns-container\">";
          // Loop through all fields assigned to this section
          foreach($field['group'] as $groupped_field_name => $groupped_field) {
            $groupped_field['container'] = 'tools';
            $output .= self::generate_option_field($groupped_field_name, $groupped_field);
          }
          $output .= "</div>";
        }
        // B. Detect section
        else if(isset($field['section_name'])) {
          $output .= "<h4>{$field['section_name']}</h4>";
          $output .= (isset($section['description'])) ? "<p class=\"description\">{$field['description']}</p>" : "";
          $output .= "<table class=\"form-table\">";

          // Loop through all fields assigned to this section
          foreach($field['fields'] as $section_field_id => $section_field) {
            $section_field_name = "{$field_name}[$section_field_id]";
            $section_field['container'] = 'row';

            $output .= self::generate_option_field($section_field_name, $section_field);
          }

          $output .= "</table>";
        }
        // C. Display single field
        else {
          $output .= self::generate_option_field($field_name, $field);
        }
      }

      // End the fields' section + add button & nonce fields
      $output .= ($nonce_action && $nonce_name) ? wp_nonce_field($nonce_action, $nonce_name, true, true) : "";
      $output .= ($button_text) ? get_submit_button($button_text, $button_class, '', false) : "";
      $output .= '</form>';
      $output .= ($form_column_class) ? "</div>" : "";

      // 4. Display some notes
      if($sidebar_class && $sidebar) {
        $output .= "<div class=\"{$sidebar_class}\">";
        $output .= "<div class=\"section-notes\">";
        $output .= $sidebar;
        $output .= "</div>";
        $output .= "</div>";
      }

      // 5. End the section row container
      $output .= ($wrapper_class) ? "</div>" : "";

      return $output;
    }

    /**
   	 * Display the plugin sections.
   	 */
    public function display_section() {
      global $wpdb, $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

   		$output = "<div id=\"permalink-manager\" class=\"wrap\">";

      // Display alerts and another content if needed and the plugin header
      $output .= $permalink_manager_before_sections_html;
      $output .= "<h2 id=\"plugin-name-heading\">" . PERMALINK_MANAGER_PLUGIN_NAME . " <a href=\"" . PERMALINK_MANAGER_WEBSITE ."\" target=\"_blank\">" . __('by Maciej Bis', 'permalink-manager') . "</a></h2>";

      // Display the tab navigation
      $output .= "<div id=\"permalink-manager-tab-nav\" class=\"nav-tab-wrapper\">";
      foreach($this->sections as $section_name => $section_properties) {
        $active_class = ($this->active_section === $section_name) ? 'nav-tab-active nav-tab' : 'nav-tab';
        $section_url = $this->get_admin_url("&section={$section_name}");

        $output .= "<a href=\"{$section_url}\" class=\"{$active_class}\">{$section_properties['name']}</a>";
      }
   		$output .= "</div>";

      // Display addidional navigation for subsections
      if(isset($this->sections[$this->active_section]['subsections'])) {
        $output .= "<ul class=\"subsubsub\">";
        foreach ($this->sections[$this->active_section]['subsections'] as $subsection_name => $subsection) {
          $active_class = ($this->active_subsection === $subsection_name) ? 'current' : '';
          $subsection_url = $this->get_admin_url("&section={$this->active_section}&subsection={$subsection_name}");

          $output .= "<li><a href=\"{$subsection_url}\" class=\"{$active_class}\">{$subsection['name']}</a></li>";
        }
        $output .= "</ul>";
      }

      // Now display the active section
      $output .= "<div id=\"permalink-manager-sections\">";
      $active_section_array = $this->sections[$this->active_section];

      // A. Execute the function assigned to the subsection
      if(isset($active_section_array['subsections'][$this->active_subsection]['function'])) {
        $class_name = $active_section_array['subsections'][$this->active_subsection]['function']['class'];
        $section_object = new $class_name();

        $section_content = call_user_func(array($section_object, $active_section_array['subsections'][$this->active_subsection]['function']['method']));
      }
      // B. Execute the function assigned to the section
      else if(isset($active_section_array['function'])) {
        $class_name = $active_section_array['function']['class'];
        $section_object = new $class_name();

        $section_content = call_user_func(array($section_object, $active_section_array['function']['method']));
      }
      // C. Display the raw HTMl output
      else {
        $section_content = (isset($active_section_array['html'])) ? $active_section_array['html'] : "";
      }

      $output .= "<div data-section=\"{$this->active_section}\" id=\"{$this->active_section}\">{$section_content}</div>";
      $output .= "</div>";

      // Display alerts and another content if needed and close .wrap container
      $output .= $permalink_manager_after_sections_html;
      $output .= "</div>";

      echo $output;
   	}

    /**
     * Display error/info message
     */
    static function get_alert_message($alert_content, $alert_type, $dismissable = true) {
      $class = ($dismissable) ? "is-dismissible" : "";
      $output = sprintf( "<div class=\"{$alert_type} notice {$class}\"> %s</div>", wpautop($alert_content) );

      return $output;
    }

    /**
     * Display the table with updated slugs after one of the actions is triggered
     */
    static function display_updated_slugs($updated_array) {
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
        $permalink = home_url("{$row['new_uri']}");

        $main_content .= "<tr{$alternate_class}>";
          $main_content .= '<td class="row-title column-primary" data-colname="' . __('Title', 'permalink-manager') . '">' . $row['post_title'] . "<a target=\"_blank\" href=\"{$permalink}\"><span class=\"small\">{$permalink}</span></a>" . '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'permalink-manager') . '</span></button></td>';
          $main_content .= '<td data-colname="' . __('Old URI', 'permalink-manager') . '">' . $row['old_uri'] . '</td>';
          $main_content .= '<td data-colname="' . __('New URI', 'permalink-manager') . '">' . $row['new_uri'] . '</td>';
          $main_content .= (isset($row['old_slug'])) ? '<td data-colname="' . __('Old Slug', 'permalink-manager') . '">' . $row['old_slug'] . '</td>' : "";
          $main_content .= (isset($row['new_slug'])) ? '<td data-colname="' . __('New Slug', 'permalink-manager') . '">' . $row['new_slug'] . '</td>' : "";
        $main_content .= '</tr>';
      }

      // Merge header, footer and content
      $output = '<h3 id="updated-list">' . __('List of updated items', 'permalink-manager') . '</h3>';
      $output .= '<table class="widefat wp-list-table updated-slugs-table">';
        $output .= "<thead>{$header_footer}</thead><tbody>{$main_content}</tbody><tfoot>{$header_footer}</tfoot>";
      $output .= '</table>';

      return $output ;
    }

}
