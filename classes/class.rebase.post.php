<?php
/**
 * Class: WPRD_Rebase_Postdata (abstract)
 */

class WPRD_Rebase_Postdata {
	private static $_hook = 'postdata';
	private static $_id = 'postdata';
	
	static function apply() {
		// Execute at wp_rebase_load_libraries
		$hook = self::$_hook;
		add_filter('wp_rebase_admin_tabs', 'WPRD_Rebase_Postdata::adminTab');
		add_action("wp_rebase_admin_styles_{$hook}", 'WPRD_Rebase_Postdata::adminStyles');
		add_action("wp_rebase_admin_scripts_{$hook}", 'WPRD_Rebase_Postdata::adminScripts');
		add_action("wp_rebase_admin_screen_{$hook}", 'WPRD_Rebase_Postdata::adminScreen');
		add_action("wp_rebaase_ajax_{$hook}", 'WPRD_Rebase_Postdata::handleAjax');
	}
	
	static function handleAjax() {
		// Execute on appropriate ajax call
	}
	
	static function adminScreen() {
		// Execute on wp_rebase_admin_screen_default
		global $wpdb;
		$post_types = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts}");
		$allowed_post_types = array_diff($post_types, apply_filters("wp_rebase_data_{self::$_hook}_excluded_types", array('revision')));
		$post_stati = $wpdb->get_col("SELECT DISTINCT post_status FROM {$wpdb->posts}");
		$allowed_post_stati = array_diff($post_stati, apply_filters("wp_rebase_data_{self::$_hook}_excluded_stati", array('auto-draft', 'inherit')));
		?>
		<form id="wp-rebase-data-form" action="#" method="POST">
		<?php if (!empty($allowed_post_types)) { ?>
		<fieldset id="wp-rebase-data-post-types">
			<legend><?php echo esc_html(__('Post Types', 'sm-wp-rebase-data')); ?></legend>
			<ul class="checkbox-list">
				<?php foreach ($allowed_post_types as $type) { ?>
				<li>
					<input type="checkbox" name="post-types[]" id="post-type-<?php echo $type; ?>" value="<?php echo $type; ?>" />
					<label for="post-type-<?php echo $type; ?>"><?php echo $type; ?></label>
				</li>
				<?php } ?>
			</ul>
		</fieldset>
		<?php } ?>
		<?php if (!empty($allowed_post_stati)) { ?>
		<fieldset id="wp-rebase-data-post-stati">
			<legend><?php echo esc_html(__('Post Stati', 'sm-wp-rebase-data')); ?></legend>
			<ul class="checkbox-list">
				<?php foreach ($allowed_post_stati as $status) { ?>
				<li>
					<input type="checkbox" name="post-status[]" id="post-status-<?php echo $status; ?>" value="<?php echo $status; ?>" />
					<label for="post-status-<?php echo $status; ?>"><?php echo $status; ?></label>
				</li>
				<?php } ?>
			</ul>
		</fieldset>
		<button class="button button-primary" id="btn-run-resave"><?php echo esc_html(__('Resave Selected Posts')); ?></button>
		<div class="progress-indicator">
			<div class="progress-bar-wrapper" style="float:left;clear:both">
				<div class="progress-bar"></div>
			</div>
			<div class="current-task" style="float:left;"></div>
			<div class="numeric-indicator" style="float:right;"></div>
			<div class="ajax-errors"></div>
		</div>
		</form>
		<?php }
	}
	
	static function adminTab($tabs = array()) {
		// Add appropriate tab for display.
		$tabs[self::$_hook] = array(
			'title' => __('Post Data', 'sm-wp-rebase-data'),
			'id' => self::$_id,
		);
		return $tabs;
	}

}
add_action('wp_rebase_load_libraries', 'WPRD_Rebase_Postdata::apply');