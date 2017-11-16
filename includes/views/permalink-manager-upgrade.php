<?php

/**
* Display the page where the slugs could be regenerated or replaced
*/
class Permalink_Manager_Upgrade extends Permalink_Manager_Class {

	public function __construct() {
		if(!defined('PERMALINK_MANAGER_PRO')) {
			add_filter( 'permalink-manager-sections', array($this, 'add_upgrade_section'), 1 );
		}
	}

	public function add_upgrade_section($admin_sections) {
		$admin_sections['upgrade'] = array(
			'name'				=>	__('Extra features', 'permalink-manager'),
			'function'    => array('class' => 'Permalink_Manager_Upgrade', 'method' => 'output')
		);

		return $admin_sections;
	}

	public function output() {
		$output = sprintf("<h3>%s</h3>", __("Permalink Manager Pro extra features", "permalink-manager"));

		$output .=	sprintf("<p class=\"lead\">%s</p>", __('Take full control of your permalinks! Permalink Manager Pro contains a bunch of useful extra functionalities!', 'permalink-manager'));
		$output .=	sprintf("<p class=\"\">%s</p>", __('Not certain if Permalink Manager Pro will fix your permalink problem?<br />Contact us at <a href="mailto:contact@permalinkmanager.pro">contact@permalinkmanager.pro</a>!', 'permalink-manager'));

		$output .= "<div class=\"columns-container\">";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Full Taxonomy Support", "permalink-manager"));
		$output .= wpautop(__("With Permalink Manager Pro you can easily alter the default taxonomies’ permastructures & edit the full permalink in all the categories, tags and custom taxonomies terms!", "permalink-manager"));
		$output .= wpautop(__("You can also bulk edit the taxonomies permalinks (eg. reset the native terms slugs) with included tools - “Find & replace” or “Regnerate/reset”", "permalink-manager"));
		$output .= "</div>";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Full WooCommerce Support", "permalink-manager"));
		$output .= wpautop(__("Adjust your shop, product category, tags or single product permalinks and set your e-commerce URLs any way you want!", "permalink-manager"));
		$output .= wpautop(__("Remove <em>product-category</em>, <em>product-tag</em> and <em>product</em> or replace them with another permalink tags. Furthermore, the plugin allows to set completely custom permalinks for each product &#038; product taxonomies individually.", "permalink-manager"));
		$output .= "</div>";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Custom fields inside permalinks", "permalink-manager"));
		$output .= wpautop(__("Automatically embed your custom fields values inside the permalinks, by adding the custom field tags to the permastructures.", "permalink-manager"));
		$output .= wpautop(__("This functionality is compatible with meta keys set with Advanced Custom Fields plugin.", "permalink-manager"));
		$output .= "</div>";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Extra Redirects", "permalink-manager"));
		$output .= wpautop(__("Set-up extra redirects and/or aliases for each post or term. Permalink Manager would also automatically create redirects for previously used custom permalinks.", "permalink-manager"));
		$output .= "</div>";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Import permalinks from \"Custom Permalinks\"", "permalink-manager"));
		$output .= wpautop(__("Additionally, Permalink Manager Pro allows to import the custom URIs defined previously with \"Custom Permalinks\" plugin. ", "permalink-manager"));
		$output .= "</div>";
		$output .= "<div class=\"column-1_3\">";
		$output .= sprintf("<h5>%s</h5>", __("Remove \"stop words\" from permalinks", "permalink-manager"));
		$output .= wpautop(__("Set your own list of stop words or use a predefined one available in 21 languages. If enabled, all the words will be automatically removed from the default permalinks.", "permalink-manager"));
		$output .= "</div>";
		$output .= "</div>";

		$output .= sprintf("<p><a class=\"button button-default margin-top\" href=\"%s\" target=\"_blank\">%s</a>&nbsp;&nbsp;<a class=\"button button-primary margin-top\" href=\"%s\" target=\"_blank\">%s</a></p>", PERMALINK_MANAGER_WEBSITE, __("More info about Permalink Manager Pro"), "https://gumroad.com/l/permalink-manager", __("Buy Permalink Manager Pro"));

		return $output;
	}

}
