<?php
/**
 * Class: WPRD_Rebase (abstract)
 */

class WPRD_Rebase {
	private static $_hook = 'default';
	private static $_id = 'default';
	
	static function apply() {
		// Execute at wp_rebase_load_libraries
		add_filter('wp_rebase_admin_tabs', 'WPRD_Rebase::adminTab'));
		add_action("wp_rebase_admin_styles_{self::$_hook}", 'WPRD_Rebase::adminStyles');
		add_action("wp_rebase_admin_scripts_{self::$_hook}", 'WPRD_Rebase::adminScripts');
		add_action("wp_rebase_admin_screen_{self::$_hook}", 'WPRD_Rebase::adminScreen');
		add_action("wp_rebaase_ajax_$hook", 'WPRD_Rebase::handleAjax');
	}
	
	static function handleAjax() {
		// Execute on appropriate ajax call
	}
	
	static function adminScreen() {
		// Execute on wp_rebase_$hook_screen
	}
	
	static function adminTab($tabs = array()) {
		// Add appropriate tab for display.
		return $tabs;
	}

}