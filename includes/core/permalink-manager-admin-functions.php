<?php

/**
* Additional back-end functions related to Wordpress Dashboard UI
*/
class Permalink_Manager_Admin_Functions extends Permalink_Manager_Class {

	public $menu_name, $sections, $active_section, $active_subsection;
	public $plugin_slug = PERMALINK_MANAGER_PLUGIN_SLUG;
	public $plugin_basename = PERMALINK_MANAGER_BASENAME;

	public function __construct() {
		add_action( 'admin_menu', array($this, 'add_menu_page') );
		add_action( 'admin_init', array($this, 'init') );

		add_action( 'admin_notices', array($this, 'display_plugin_notices'));
		add_action( 'admin_notices', array($this, 'display_global_notices'));
		add_action( 'wp_ajax_dismissed_notice_handler', array($this, 'hide_global_notice') );
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
	}

	/**
	* Get current section (only in plugin sections)
	*/
	public function get_current_section() {
		global $active_section, $active_subsection, $current_admin_tax;

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

		// Check if current admin page is related to taxonomies
		if(substr($this->active_subsection, 0, 4) == 'tax_') {
			$current_admin_tax = substr($this->active_subsection, 4, strlen($this->active_subsection));
		} else {
			$current_admin_tax = false;
		}

		// Set globals
		$active_section = $this->active_section;
		$active_subsection = $this->active_subsection;
	}

	/**
	* Add menu page.
	*/
	public function add_menu_page() {
		$this->menu_name = add_management_page( __('Permalink Manager', 'permalink-manager'), __('Permalink Manager', 'permalink-manager'), 'manage_options', $this->plugin_slug, array($this, 'display_section') );

		add_action( 'admin_init', array($this, 'enqueue_styles' ) );
		add_action( 'admin_init', array($this, 'enqueue_scripts' ) );
	}

	/**
	* Register the CSS file for the dashboard.
	*/
	public function enqueue_styles() {
		wp_enqueue_style( 'permalink-manager-plugins', PERMALINK_MANAGER_URL . '/out/permalink-manager-plugins.css', array(), PERMALINK_MANAGER_VERSION, 'all' );
		wp_enqueue_style( 'permalink-manager', PERMALINK_MANAGER_URL . '/out/permalink-manager-admin.css', array('permalink-manager-plugins'), PERMALINK_MANAGER_VERSION, 'all' );
	}

	/**
	* Register the JavaScript file for the dashboard.
	*/
	public function enqueue_scripts() {
		wp_enqueue_script( 'permalink-manager-plugins', PERMALINK_MANAGER_URL . '/out/permalink-manager-plugins.js', array( 'jquery', ), PERMALINK_MANAGER_VERSION, false );
		wp_enqueue_script( 'permalink-manager', PERMALINK_MANAGER_URL . '/out/permalink-manager-admin.js', array( 'jquery', 'permalink-manager-plugins' ), PERMALINK_MANAGER_VERSION, false );

		wp_localize_script( 'permalink-manager', 'permalink_manager', array('url' => PERMALINK_MANAGER_URL) );
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
		$links[] = sprintf('<a href="%s">%s</a>', $this->get_admin_url(), __( 'URI Editor', 'permalink-manager' ));
		if(!defined('PERMALINK_MANAGER_PRO')) {
			$links[] = sprintf('<a href="%s" target="_blank">%s</a>', PERMALINK_MANAGER_WEBSITE, __( 'Buy Permalink Manager Pro', 'permalink-manager' ));
		}
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

		$field_type = (isset($args['type'])) ? $args['type'] : 'text';
		$default = (isset($args['default'])) ? $args['default'] : '';
		$label = (isset($args['label'])) ? $args['label'] : '';
		$rows = (isset($args['rows'])) ? "rows=\"{$rows}\"" : "rows=\"5\"";
		$container_class = (isset($args['container_class'])) ? " class=\"{$args['container_class']} field-container\"" : " class=\"field-container\"";
		$description = (isset($args['before_description'])) ? $args['before_description'] : "";
		$description .= (isset($args['description'])) ? "<p class=\"field-description description\">{$args['description']}</p>" : "";
		$description .= (isset($args['after_description'])) ? $args['after_description'] : "";
		$description .= (isset($args['pro'])) ? sprintf("<p class=\"field-description description alert info\">%s</p>", (Permalink_Manager_Admin_Functions::pro_text(true))) : "";
		$append_content = (isset($args['append_content'])) ? "{$args['append_content']}" : "";

		// Input attributes
		$input_atts = (isset($args['input_class'])) ? "class='{$args['input_class']}'" : '';
		$input_atts .= (isset($args['readonly'])) ? " readonly='readonly'" : '';
		$input_atts .= (isset($args['disabled'])) ? " disabled='disabled'" : '';
		$input_atts .= (isset($args['placeholder'])) ? " placeholder='{$args['placeholder']}'" : '';
		$input_atts .= (isset($args['extra_atts'])) ? " {$args['extra_atts']}" : '';

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

		switch($field_type) {
			case 'checkbox' :
				$fields .= '<div class="checkboxes">';
				foreach($args['choices'] as $choice_value => $choice) {
					$label = (is_array($choice)) ? $choice['label'] : $choice;
					$atts = (is_array($value) && in_array($choice_value, $value)) ? "checked='checked'" : "";
					$atts .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

					$fields .= "<label for='{$input_name}[]'><input type='checkbox' {$input_atts} value='{$choice_value}' name='{$input_name}[]' {$atts} /> {$label}</label>";
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

			case 'single_checkbox' :
				$fields .= '<div class="single_checkbox">';
				$checked = ($value == 1) ? "checked='checked'" : "";
				$checkbox_label = (isset($args['checkbox_label'])) ? $args['checkbox_label'] : '';

				$fields .= "<input type='hidden' {$input_atts} value='0' name='{$input_name}' checked=\"checked\" />";
				$fields .= "<label for='{$input_name}'><input type='checkbox' {$input_atts} value='1' name='{$input_name}' {$checked} /> {$checkbox_label}</label>";
				$fields .= '</div>';
			break;

			case 'radio' :
				$fields .= '<div class="radios">';
				foreach($args['choices'] as $choice_value => $choice) {
					$label = (is_array($choice)) ? $choice['label'] : $choice;
					$atts = ($choice_value == $value) ? "checked='checked'" : "";
					$atts .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

					$fields .= "<label for='{$input_name}[]'><input type='radio' {$input_atts} value='{$choice_value}' name='{$input_name}[]' {$atts} /> {$label}</label>";
				}
				$fields .= '</div>';
			break;

			case 'select' :
				$fields .= '<span class="select">';
				$fields .= "<select name='{$input_name}' {$input_atts}>";
				foreach($args['choices'] as $choice_value => $choice) {
					$label = (is_array($choice)) ? $choice['label'] : $choice;
					$atts = ($choice_value == $value) ? "selected='selected'" : "";
					$atts .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

					$fields .= "<option value='{$choice_value}' {$atts}>{$label}</option>";
				}
				$fields .= '</select>';
				$fields .= '</span>';
				break;

			case 'number' :
				$fields .= "<input type='number' {$input_atts} value='{$value}' name='{$input_name}' />";
				break;

			case 'hidden' :
				$fields .= "<input type='hidden' {$input_atts} value='{$value}' name='{$input_name}' />";
				break;

			case 'textarea' :
				$fields .= "<textarea {$input_atts} name='{$input_name}' {$rows}>{$value}</textarea>";
				break;

			case 'pre' :
				$fields .= "<pre {$input_atts}>{$value}</pre>";
				break;

			case 'info' :
				$fields .= "<div {$input_atts}>{$value}</div>";
				break;

			case 'clearfix' :
				return "<div class=\"clearfix\"></div>";

			case 'permastruct' :
				$siteurl = get_option('home');
				$fields .= "<div class=\"permastruct-container\"><span><code>{$siteurl}/</code></span><span><input type='text' {$input_atts} value='{$value}' name='{$input_name}'/></span></div>";
				break;

			default :
				$fields .= "<input type='text' {$input_atts} value='{$value}' name='{$input_name}'/>";
		}

		// Get the final HTML output
		if(isset($args['container']) && $args['container'] == 'tools') {
			$html = "<div{$container_class}>";
			$html .= "<h4>{$label}</h4>";
			$html .= "<div class='{$input_name}-container'>{$fields}</div>";
			$html .= $description;
			$html .= $append_content;
			$html .= "</div>";
		} else if(isset($args['container']) && $args['container'] == 'row') {
			$html = "<tr data-field=\"{$input_name}\" {$container_class}><th><label for='{$input_name}'>{$args['label']}</label></th>";
			$html .= "<td><fieldset>{$fields}{$description}</fieldset></td></tr>";
			$html .= ($append_content) ? "<tr class=\"appended-row\"><td colspan=\"2\">{$append_content}</td></tr>" : "";
		} else if(isset($args['container']) && $args['container'] == 'screen-options') {
			$html = "<fieldset data-field=\"{$input_name}\" {$container_class}><legend>{$args['label']}</legend>";
			$html .= "<div class=\"field-content\">{$fields}{$description}</div>";
			$html .= ($append_content) ? "<div class=\"appended-row\">{$append_content}</div>" : "";
			$html .= "</fieldset>";
		} else {
			$html = $fields . $append_content;
		}

		return apply_filters('permalink-manager-field-output', $html);
	}

	/**
	* Display hidden field to indicate posts or taxonomies admin sections
	*/
	static public function section_type_field($type = 'post') {
		return self::generate_option_field('content_type', array('value' => $type, 'type' => 'hidden'));
	}

	/**
	* Display the form
	*/
	static public function get_the_form($fields = array(), $container = '', $button = array(), $sidebar = '', $nonce = array(), $wrap = false) {
		// 1. Check if the content will be displayed in columns and button details
		switch($container) {
			case 'columns-3' :
				$wrapper_class = 'columns-container';
				$form_column_class = 'column column-2_3';
				$sidebar_class = 'column column-1_3';
				break;

			// there will be more cases in future ...
			default :
				$form_column_class = 'form';
				$sidebar_class = 'sidebar';
				$wrapper_class = $form_column_class = '';
		}

		// 2. Process the array with button and nonce field settings
		$button_text = (!empty($button['text'])) ? $button['text'] : '';
		$button_class = (!empty($button['class'])) ? $button['class'] : '';
		$button_attributes = (!empty($button['attributes'])) ? $button['attributes'] : '';
		$nonce_action = (!empty($nonce['action'])) ? $nonce['action'] : '';
		$nonce_name = (!empty($nonce['name'])) ? $nonce['name'] : '';

		// 2. Now get the HTML output (start section row container)
		$html = ($wrapper_class) ? "<div class=\"{$wrapper_class}\">" : '';

		// 3. Display some notes
		if($sidebar_class && $sidebar) {
			$html .= "<div class=\"{$sidebar_class}\">";
			$html .= "<div class=\"section-notes\">";
			$html .= $sidebar;
			$html .= "</div>";
			$html .= "</div>";
		}

		// 4. Start fields' section
		$html .= ($form_column_class) ? "<div class=\"{$form_column_class}\">" : "";
		$html .= "<form method=\"POST\">";
		$html .= ($wrap) ? "<table class=\"form-table\">" : "";

		// Loop through all fields assigned to this section
		foreach($fields as $field_name => $field) {
			$field_name = (!empty($field['name'])) ? $field['name'] : $field_name;

			// A. Display table row
			if(isset($field['container']) && $field['container'] == 'row') {
				$row_output = "";

				// Loop through all fields assigned to this section
				if(isset($field['fields'])) {
					foreach($field['fields'] as $section_field_id => $section_field) {
						$section_field_name = (!empty($section_field['name'])) ? $section_field['name'] : "{$field_name}[$section_field_id]";
						$section_field['container'] = 'row';

						$row_output .= self::generate_option_field($section_field_name, $section_field);
					}
				} else {
					$row_output .= self::generate_option_field($field_name, $field);
				}

				if(isset($field['section_name'])) {
					$html .= "<h3>{$field['section_name']}</h3>";
					$html .= (isset($field['append_content'])) ? $field['append_content'] : "";
					$html .= (isset($field['description'])) ? "<p class=\"description\">{$field['description']}</p>" : "";
					$html .= "<table class=\"form-table\" data-field=\"{$field_name}\">{$row_output}</table>";
				} else {
					$html .= $row_output;
				}
			}
			// B. Display single field
			else {
				$html .= self::generate_option_field($field_name, $field);
			}
		}

		$html .= ($wrap) ? "</table>" : "";

		// End the fields' section + add button & nonce fields
		$html .= ($nonce_action && $nonce_name) ? wp_nonce_field($nonce_action, $nonce_name, true, true) : "";
		$html .= ($button_text) ? get_submit_button($button_text, $button_class, '', false, $button_attributes) : "";
		$html .= '</form>';
		$html .= ($form_column_class) ? "</div>" : "";

		// 5. End the section row container
		$html .= ($wrapper_class) ? "</div>" : "";

		return $html;
	}

	/**
	* Display the plugin sections.
	*/
	public function display_section() {
		global $wpdb, $permalink_manager_before_sections_html, $permalink_manager_after_sections_html;

		$html = "<div id=\"permalink-manager\" class=\"wrap\">";

		$donate_link = sprintf("<a href=\"%s\" target=\"_blank\" class=\"page-title-action\">%s</a>", PERMALINK_MANAGER_DONATE, __("Donate", "permalink-manager"));
		$html .= sprintf("<h2 id=\"plugin-name-heading\">%s <a href=\"http://maciejbis.net\" class=\"author-link\" target=\"_blank\">%s</a> %s</h2>", PERMALINK_MANAGER_PLUGIN_NAME, __("by Maciej Bis", "permalink-manager"), $donate_link);

		// Display the tab navigation
		$html .= "<div id=\"permalink-manager-tab-nav\" class=\"nav-tab-wrapper\">";
		foreach($this->sections as $section_name => $section_properties) {
			$active_class = ($this->active_section === $section_name) ? 'nav-tab-active nav-tab' : 'nav-tab';
			$section_url = $this->get_admin_url("&section={$section_name}");

			$html .= "<a href=\"{$section_url}\" class=\"{$active_class} section_{$section_name}\">{$section_properties['name']}</a>";
		}
		$html .= "</div>";

		// Now display the active section
		$html .= "<div id=\"permalink-manager-sections\">";
		$active_section_array = (isset($this->sections[$this->active_section])) ? $this->sections[$this->active_section] : "";

		// Display addidional navigation for subsections
		if(isset($this->sections[$this->active_section]['subsections'])) {
			$html .= "<ul class=\"subsubsub\">";
			foreach ($this->sections[$this->active_section]['subsections'] as $subsection_name => $subsection) {
				$active_class = ($this->active_subsection === $subsection_name) ? 'current' : '';
				$subsection_url = $this->get_admin_url("&section={$this->active_section}&subsection={$subsection_name}");

				$html .= "<li><a href=\"{$subsection_url}\" class=\"{$active_class}\">{$subsection['name']}</a></li>";
			}
			$html .= "</ul>";
		}

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
		// C. Display the raw HTMl output of subsection
		else if(isset($active_section_array['subsections'][$this->active_subsection]['html'])) {
			$section_content = (isset($active_section_array['subsections'][$this->active_subsection]['html'])) ? $active_section_array['subsections'][$this->active_subsection]['html'] : "";
		}
		// D. Try to display the raw HTMl output of section
		else {
			$section_content = (isset($active_section_array['html'])) ? $active_section_array['html'] : "";
		}

		$html .= "<div class=\"single-section\" data-section=\"{$this->active_section}\" id=\"{$this->active_section}\">{$section_content}</div>";
		$html .= "</div>";

		// Display alerts and another content if needed and close .wrap container
		$html .= $permalink_manager_after_sections_html;
		$html .= "</div>";

		echo $html;
	}

	/**
	* Display error/info message
	*/
	public static function get_alert_message($alert_content = "", $alert_type = "", $dismissable = true, $id = false) {
		// Ignore empty messages (just in case)
		if(empty($alert_content) || empty($alert_type)) {
			return "";
		}

		$class = ($dismissable) ? "is-dismissible" : "";
		$alert_id = ($id) ? " data-alert_id=\"{$id}\"" : "";

		$html = sprintf( "<div class=\"{$alert_type} permalink-manager-notice notice {$class}\"{$alert_id}> %s</div>", wpautop($alert_content) );

		return $html;
	}

	static function pro_text($text_only = false) {
		$text = sprintf(__('This functionality is available only in <a href="%s" target="_blank">Permalink Manager Pro</a>.', 'permalink-manager'), PERMALINK_MANAGER_WEBSITE);

		return ($text_only) ? $text : sprintf("<div class=\"alert info\"> %s</div>", wpautop($text, 'alert', false));
	}

	/**
	* Help tooltip
	*/
	static function help_tooltip($text = '') {
		$html = " <a href=\"#\" title=\"{$text}\" class=\"help_tooltip\"><span class=\"dashicons dashicons-editor-help\"></span></a>";
		return $html;
	}

	/**
	* Display the table with updated slugs after one of the actions is triggered
	*/
	static function display_updated_slugs($updated_array, $uri_type = 'post') {
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

			// Taxonomy
			if(!empty($row['tax'])) {
				$term_link = get_term_link(intval($row['ID']), $row['tax']);
				$permalink = (is_wp_error($term_link)) ? "-" : $term_link;
			} else {
				$permalink = get_permalink($row['ID']);
			}

			$main_content .= "<tr{$alternate_class}>";
			$main_content .= '<td class="row-title column-primary" data-colname="' . __('Title', 'permalink-manager') . '">' . $row['item_title'] . "<a target=\"_blank\" href=\"{$permalink}\"><span class=\"small\">{$permalink}</span></a>" . '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'permalink-manager') . '</span></button></td>';
			$main_content .= '<td data-colname="' . __('Old URI', 'permalink-manager') . '">' . urldecode($row['old_uri']) . '</td>';
			$main_content .= '<td data-colname="' . __('New URI', 'permalink-manager') . '">' . urldecode($row['new_uri']) . '</td>';
			$main_content .= (isset($row['old_slug'])) ? '<td data-colname="' . __('Old Slug', 'permalink-manager') . '">' . urldecode($row['old_slug']) . '</td>' : "";
			$main_content .= (isset($row['new_slug'])) ? '<td data-colname="' . __('New Slug', 'permalink-manager') . '">' . urldecode($row['new_slug']) . '</td>' : "";
			$main_content .= '</tr>';
		}

		// Merge header, footer and content
		$html = '<h3 id="updated-list">' . __('List of updated items', 'permalink-manager') . '</h3>';
		$html .= '<table class="widefat wp-list-table updated-slugs-table">';
		$html .= "<thead>{$header_footer}</thead><tbody>{$main_content}</tbody><tfoot>{$header_footer}</tfoot>";
		$html .= '</table>';

		return $html;
	}

	/**
	 * Quick Edit Box
	 */
	public static function quick_edit_column_form($is_taxonomy = false) {
		$html = Permalink_Manager_Admin_Functions::generate_option_field('permalink-manager-quick-edit', array('value' => true, 'type' => 'hidden'));
		$html .= "<fieldset class=\"inline-edit-permalink\">";
		$html .= sprintf("<legend class=\"inline-edit-legend\">%s</legend>", __("Permalink Manager", "permalink-manager"));

		$html .= "<div class=\"inline-edit-col\">";
		$html .= sprintf("<label class=\"inline-edit-group\"><span class=\"title\">%s</span><span class=\"input-text-wrap\">%s</span></label>",
			__("Current URI", "permalink-manager"),
			Permalink_Manager_Admin_Functions::generate_option_field("custom_uri", array("input_class" => "custom_uri", "value" => ''))
		);
		$html .= "</div>";

		$html .= "</fieldset>";

		return $html;
	}

	/**
	 * Display "Permalink Manager" box
	 */
	public static function display_uri_box($element, $default_uri, $uri, $native_uri, $home_with_prefix) {
		global $permalink_manager_options;

		if(!empty($element->ID)) {
			$id = $element->ID;

			// Auto-update settings
			$auto_update_val = get_post_meta($id, "auto_update_uri", true);
			$auto_update_def_val = $permalink_manager_options["general"]["auto_update_uris"];
			$auto_update_def_label = ($auto_update_def_val) ? __("Yes", "permalink-manager") : __("No", "permalink-manager");
			$auto_update_choices = array(
				0 => array("label" => sprintf(__("Use global settings [%s]", "permalink-manager"), $auto_update_def_label), "atts" => "data-auto-update=\"{$auto_update_def_val}\""),
				-1 => array("label" => __("No", "permalink-manager"), "atts" => "data-auto-update=\"0\""),
				1 => array("label" => __("Yes", "permalink-manager"), "atts" => "data-auto-update=\"1\"")
			);
		} else {
			$id = $element->term_id;
		}

		// 1. Button
		$html = sprintf("<span><button type=\"button\" class=\"button button-small hide-if-no-js\" id=\"permalink-manager-toggle\">%s</button></span>", __("Permalink Manager", "permalink-manager"));

		$html .= "<div id=\"permalink-manager\" class=\"postbox permalink-manager-edit-uri-box\" style=\"display: none;\">";

		// 2. The heading
		$html .= "<a class=\"close-button\"><span class=\"screen-reader-text\">" . __("Close: ", "permalink-manager") . __("Permalink Manager", "permalink-manager") . "</span><span class=\"close-icon\" aria-hidden=\"false\"></span></a>";
		$html .= sprintf("<h2><span>%s</span></h2>", __("Permalink Manager", "permalink-manager"));

		// 3. The fields container [start]
		$html .= "<div class=\"inside\">";

		// 4. Custom URI
		$html .= sprintf("<div><label for=\"custom_uri\" class=\"strong\">%s %s</label><span>%s</span></div>",
			__("Current URI", "permalink-manager"),
			($element->ID) ? Permalink_Manager_Admin_Functions::help_tooltip(__("The custom URI can be edited only if 'Auto-update the URI' feature is not enabled.", "permalink-manager")) : "",
			Permalink_Manager_Admin_Functions::generate_option_field("custom_uri", array("extra_atts" => "data-default=\"{$default_uri}\"", "input_class" => "widefat custom_uri", "value" => urldecode($uri)))
		);

		// 5. Custom URI
		if(!empty($auto_update_choices)) {
			$html .= sprintf("<div><label for=\"auto_auri\" class=\"strong\">%s %s</label><span>%s</span></div>",
				__("Auto-update the URI", "permalink-manager"),
				Permalink_Manager_Admin_Functions::help_tooltip(__("If enabled, the 'Current URI' field will be automatically changed to 'Default URI' (displayed below) after the post is saved or updated.", "permalink-manager")),
				Permalink_Manager_Admin_Functions::generate_option_field("auto_update_uri", array("type" => "select", "input_class" => "widefat auto_update", "value" => $auto_update_val, "choices" => $auto_update_choices))
			);
		}

		// 6. Default URI
		$html .= sprintf(
			"<div class=\"default-permalink-row columns-container\"><span class=\"column-3_4\"><strong>%s:</strong> %s</span><span class=\"column-1_4\"><a href=\"#\" class=\"restore-default\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span></div>",
			__("Default URI", "permalink-manager"), urldecode(esc_html($default_uri)),
			__("Restore to Default URI", "permalink-manager")
		);

		// 7. Native URI info
		if(!empty($permalink_manager_options['general']['redirect']) && ((!empty($element->post_status) && in_array($element->post_status, array('auto-draft', 'trash', 'draft'))) == false)) {
			$html .= sprintf(
				"<div class=\"default-permalink-row columns-container\"><span><strong>%s</strong> <a href=\"%s\">%s</a></span></div>",
				__("Automatic redirect for native URI enabled:", "permalink-manager"),
				"{$home_with_prefix}{$native_uri}",
				urldecode($native_uri)
			);
		}

		// 8. Custom redirects
		$html .= ($element->ID) ? self::display_redirect_panel($id) : self::display_redirect_panel("tax-{$id}");

		$html .= "</div>";
		$html .= "</div>";

		return $html;
	}

	/**
	 * Display the redirect panel
	 */
	public static function display_redirect_panel($element_id) {
		global $permalink_manager_options, $permalink_manager_redirects;

		// Heading
		$html = sprintf(
			"<div class=\"permalink-manager redirects-row redirects-panel columns-container\"><div class=\"heading\"><span class=\"dashicons dashicons-redo\"></span> <a href=\"#\" id=\"toggle-redirect-panel\">%s</a></span></div>",
			__("Add Extra Redirects", "permalink-manager")
		);

		$html .= "<div id=\"redirect-panel-inside\">";

		// Table
		if(class_exists('Permalink_Manager_Pro_Addons')) {
			$html .= Permalink_Manager_Pro_Addons::display_redirect_form($element_id);
		} else {
			$html .= self::pro_text(true);
		}

		$html .= "</div></div>";

		return $html;
	}

	/**
	 * Display global notices (throughout wp-admin dashboard)
	 */
	function display_global_notices() {
		global $permalink_manager_alerts, $active_section;

		$html = "";
		if(!empty($permalink_manager_alerts) && is_array($permalink_manager_alerts)) {
			foreach($permalink_manager_alerts as $alert_id => $alert) {
				if(!empty($alert['show'])) {
					// Hide notice in Permalink Manager Pro
					if(defined('PERMALINK_MANAGER_PRO') && $alert['show'] == 'pro_hide') { continue; }

					// Display the notice only on the plugin pages
					if(empty($active_section) && !empty($alert['plugin_only'])) { continue; }

					// Check if the notice did not expire
					if(isset($alert['until']) && (time() > strtotime($alert['until']))) { continue; }

					$html .= self::get_alert_message($alert['txt'], $alert['type'], true, $alert_id);
				}
			}
		}

		echo $html;
	}

	/**
	 * Hide global notices (AJAX)
	 */
	function hide_global_notice() {
		global $permalink_manager_alerts;

		// Get the ID of the alert
		$alert_id = (!empty($_REQUEST['alert_id'])) ? sanitize_title($_REQUEST['alert_id']) : "";
		if(!empty($permalink_manager_alerts[$alert_id])) {
			$permalink_manager_alerts[$alert_id]['show'] = 0;
		}

		update_option( 'permalink-manager-alerts', $permalink_manager_alerts);
	}

	/**
	 * Display notices generated by Permalink Manager tools
	 */
	function display_plugin_notices() {
		global $permalink_manager_before_sections_html;

		echo $permalink_manager_before_sections_html;
	}

}
