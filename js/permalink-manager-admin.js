jQuery(document).ready(function() {

	/*
	 * Tab navigation
	 */
	/*jQuery('#permalink-manager-tabs-nav a').on('click', function(){

		// Disable current active tab in navigation
		jQuery('#permalink-manager-tabs-nav .nav-tab-active').removeClass('nav-tab-active');
		jQuery('#permalink-manager-tabs .show').removeClass('show');

		// Get current tab name
		var tab_to_open = jQuery(this).data("tab");

		// Add "active" class to the clicked tab
		jQuery(this).addClass('nav-tab-active');
		jQuery('#permalink-manager-tabs div[data-tab="'+tab_to_open+'"]').addClass('show');

		// Disable native click event
		return false;
	});*/

	/*
	 * "Select all" checkbox
	 */
	jQuery('input[value="all"]').on('change', function() {
		// Uncheck "Select all"
		jQuery(this).prop('checked', false);

		jQuery(this).parents('.checkboxes').find('input[type="checkbox"]').not(this).each(function() {
			jQuery(this).prop('checked', true);
		});
	});

});
