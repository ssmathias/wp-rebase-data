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
		add_action("wp_rebase_admin_print_styles_{$hook}", 'WPRD_Rebase_Postdata::printAdminStyles');
		add_action("wp_rebase_admin_scripts_{$hook}", 'WPRD_Rebase_Postdata::enqueueAdminScripts');
		add_action("wp_rebase_admin_styles_{$hook}", 'WPRD_Rebase_Postdata::enqueueAdminStyles');
		add_action("wp_rebase_admin_print_scripts_{$hook}", 'WPRD_Rebase_Postdata::printAdminScripts');
		add_action("wp_rebase_admin_screen_{$hook}", 'WPRD_Rebase_Postdata::adminScreen');
		add_action("wp_rebase_ajax_{$hook}", 'WPRD_Rebase_Postdata::handleAjax');
	}
	
	static function handleAjax() {
		global $post;
		// Execute on appropriate ajax call
		if (!check_admin_referer('wp_rebase_data_postdata', 'key')) {
			header('HTTP/1.0 403 Forbidden');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'I\'m sorry, Dave. I can\'t do that.',
			));
			print $response;
			exit();
		}
		$new_key = wp_create_nonce('wp_rebase_data_postdata');
		if (!current_user_can('manage_options')) {
			header('HTTP/1.0 403 Forbidden');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'You do not have the necessary permissions to complete this request.',
			));
			print $response;
			exit();
		}
		if (empty($_POST['post_types'])) {
			header('HTTP/1.0 401 Invalid Request');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'At least one post type must be selected',
				'key' => $new_key,
			));
			print $response;
			exit();
		}
		$post_types = $_POST['post_types'];
		if (!is_array($post_types)) {
			$post_types = explode(',', $post_types);
			// TODO Use array_map here
			foreach ($post_types as $i=>$type) {
				$post_types[$i] = trim($type);
			}
		}
		if (empty($_POST['post_stati'])) {
			header('HTTP/1.0 401 Invalid Request');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'At least one post status must be selected',
				'key' => $new_key,
			));
			print $response;
			exit();
		}
		$post_stati = $_POST['post_stati'];
		if (!is_array($post_stati)) {
			$post_stati = array($post_stati);
			// TODO Use array_map here
			foreach ($post_stati as $i=>$status) {
				$post_stati[$i] = trim($status);
			}
		}
		// May eventually allow posts per page to be configured from front-end.
		$posts_per_page = 25;
		
		$paged = 1;
		if (!empty($_POST['paged']) && is_numeric($_POST['paged'])) {
			$paged = intval($_POST['paged']);
		}
		$warnings = array();
		$query = new WP_Query(array(
			'post_type' => $post_types,
			'post_status' => $post_stati,
			'posts_per_page' => $posts_per_page,
			'paged' => $paged,
			'no_found_rows' => false,
			'suppress_filters' => true,
		));
		$total_done = $posts_per_page * ($paged - 1);
		while ($query->have_posts()) {
			$query->the_post();
			$post_array = array();
			$post_array['ID'] = $post->ID;
			
			$result = wp_update_post($post_array);
			if (is_wp_error($result)) {
				$warnings[] = 'Error saving post #'.$post->ID;
			}
			else if ($post->post_type == 'attachment' && $file = get_attached_file($post->ID, false)) {
				$metadata = wp_generate_attachment_metadata($post->ID, $file);
				if (!empty($metadata)) {
					wp_update_attachment_metadata($post->ID, $metadata);
				}
			}
			++$total_done;
		}
		if (!empty($warnings)) {
			header('HTTP/1.0 201 Done With Errors');
		}
		else {
			header('HTTP/1.0 200 Success');
		}
		$response = json_encode(array(
			'status' => 'success',
			'total_records' => intval($query->found_posts),
			'records_complete' => $total_done,
			'warnings' => $warnings,
			'key' => $new_key,
		));
		print $response;
		exit();
	}
	
	static function enqueueAdminStyles() {
		wp_enqueue_style('wp-rebase-postdata-css', admin_url('admin-ajax.php?action=wp_rebase_data_css&hook='.self::$_hook));
	}
	
	static function printAdminStyles() {
		// Admin styles for this form
		$form_id = '#wp-rebase-data-'.self::$_id;
		$styles = <<<EOD
		$form_id {
			width: 100%;
			overflow: hidden;
		}
		$form_id fieldset {
			margin-right: 8px;
			float: left;
		}
		$form_id legend {
			font-weight: bold;
		}
		$form_id .checkbox-list {
			margin: 0;
			padding: 0;
		}
		$form_id .checkbox-list li {
			margin: 3px 0;
			padding: 0;
		}
		$form_id .checkbox-list-wrapper {
			border: 1px solid #000033;
			padding-left: 3px;
			width: 200px;
			height: 200px;
			overflow-x: hidden;
			overflow-y: scroll;
		}
		
		$form_id .form-controls {
			padding-top: 5px;
			clear: both;
		}
		
		$form_id .selection-link {
			margin-left: 5px;
		}
EOD;
		echo $styles;
	}
	
	static function enqueueAdminScripts() {
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-widget');
		wp_enqueue_script('wp-rebase-postdata', admin_url('admin-ajax.php?action=wp_rebase_data_js&hook='.self::$_hook), array('jquery', 'jquery-ui-core', 'jquery-ui-widget'));
	}
	
	static function printAdminScripts() {
		// Admin scripts for this form
		$form_selector = '#wp-rebase-data-'.self::$_id;
		echo file_get_contents(dirname(__FILE__).'/../progress-indicator/progress-indicator.js');
		?>
		jQuery(document).ready(function($) {
			var $form = $(<?php echo json_encode($form_selector); ?>),
				$nonceField = $(<?php echo json_encode($form_selector . ' input[name="key"]'); ?>),
				data = {};
				
			function onRebaseAjaxSuccess(e, response) {
				var completePercent, $paged = $form.find("input[name=\"paged\"]");
				response = jQuery.parseJSON(response);
				$paged.val(parseInt($paged.val()) + 1);
				if (typeof response.key !== "undefined") {
					$nonceField.val(response.key);
				}
				$form.progressIndicator("setCompletionText", response.records_complete + " / " + response.total_records);
				completePercent = (response.records_complete / response.total_records);
				$form.progressIndicator("updateProgress", completePercent);
				if (response.warnings.length > 0) {
					for (var i in response.warnings) {
						$form.progressIndicator("addWarning", response.warnings[i]);
					}
				}
				if (completePercent < 1) {
					// We need to do this again. Loop it with a timeout so we can redraw the screen.
					doResave($form);
				}
			}
			
			function onRebaseAjaxError(e, xhr) {
				var errorText = "An unknown error has occurred",
					responseText = "";
				try {
					responseText = $.parseJSON(xhr.responseText);
					responseText = responseText.message;
				}
				catch (unused) {
					responseText = xhr.responseText;
				}
				console.log(responseText);
				if (responseText.length > 0) {
					errorText = responseText;
				}
				$form.progressIndicator("setErrorState", errorText);
			}
			$form
				.find(".multi-check").each(function() {
					var $this = $(this),
						$legend = $(this).children("legend:first"),
						selectAllText = <?php echo json_encode(__('All', 'sm-wp-rebase-data')); ?>,
						selectNoneText = <?php echo json_encode(__('None', 'sm-wp-rebase-data')); ?>;
					$legend.append($("<a href=\"#\" class=\"selection-link\">" + selectAllText + "</a>").click(function(e) {
						e.preventDefault();
						e.stopPropagation();
						$(this).closest(".multi-check").find(".checkbox-list-wrapper :checkbox").prop("checked", true);
					})).append($("<a href=\"#\" class=\"selection-link\">" + selectNoneText + "</a>").click(function(e) {
						e.preventDefault();
						e.stopPropagation();
						$(this).closest(".multi-check").find(".checkbox-list-wrapper :checkbox").prop("checked", false);
					}));
				})
				.end()
				.on("submit", function(e) {
					e.preventDefault();
					e.stopPropagation();
					$form.progressIndicator("activate", "Saving Posts");
					doResave($form);
					
					$("body").on("wpRebaseAjaxError", onRebaseAjaxError)
						.on("wpRebaseAjaxSuccess", onRebaseAjaxSuccess);
				})
			
			$form.progressIndicator();
			$form.progressIndicator("onClose", function() {
				$("body").off("wpRebaseAjaxSuccess", onRebaseAjaxSuccess)
					.off("wpRebaseAjaxError", onRebaseAjaxError);
				$form.find("input[name=\"paged\"]").val(1);
			})
		});
		<?php
	}
	
	static function adminScreen() {
		// Execute on wp_rebase_admin_screen_default
		global $wpdb;
		$post_types = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts}");
		$allowed_post_types = array_diff($post_types, apply_filters("wp_rebase_data_{self::$_hook}_excluded_types", array('revision')));
		$post_stati = $wpdb->get_col("SELECT DISTINCT post_status FROM {$wpdb->posts}");
		$allowed_post_stati = array_diff($post_stati, apply_filters("wp_rebase_data_{self::$_hook}_excluded_stati", array('auto-draft')));
		?>
		<form id="wp-rebase-data-postdata" action="#" method="POST">
		<?php wp_nonce_field('wp_rebase_data_postdata', 'key'); ?>
		<input type="hidden" name="hook" value="<?php echo esc_attr(self::$_hook); ?>" />
		<input type="hidden" name="paged" value="1" />
		<?php if (!empty($allowed_post_types)) { ?>
		<fieldset id="wp-rebase-data-post-types" class="multi-check">
			<legend><?php echo esc_html(__('Post Types', 'sm-wp-rebase-data')); ?></legend>
			<div class="checkbox-list-wrapper">
				<ul class="checkbox-list">
				<?php foreach ($allowed_post_types as $type) {
					$label = ucwords($type);
					// Try for a nicer label, where possible.
					if ($obj = get_post_type_object($type)) {
						$label = $obj->labels->name;
					}
					?>
					<li>
						<input type="checkbox" name="post_types[]" id="post-type-<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($type); ?>" />
						<label for="post-type-<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></label>
					</li>
				<?php } ?>
				</ul>
			</div>
		</fieldset>
		<?php } ?>
		<?php if (!empty($allowed_post_stati)) { ?>
		<fieldset id="wp-rebase-data-post-stati" class="multi-check">
			<legend><?php echo esc_html(__('Post Stati', 'sm-wp-rebase-data')); ?></legend>
			<div class="checkbox-list-wrapper">
				<ul class="checkbox-list">
					<?php foreach ($allowed_post_stati as $status) { ?>
					<li>
						<input type="checkbox" name="post_stati[]" id="post-status-<?php echo esc_attr($status); ?>" value="<?php echo esc_attr($status); ?>" />
						<label for="post-status-<?php echo $status; ?>"><?php echo esc_html(ucwords($status)); ?></label>
					</li>
					<?php } ?>
				</ul>
			</div>
		</fieldset>
		<div class="form-controls">
			<button class="button button-primary" id="btn-run-resave"><?php echo esc_html(__('Resave Selected Posts', 'sm-wp-rebase-data')); ?></button>
		</div>
		</form>
		<?php }
	}
	
	static function adminTab($tabs = array()) {
		// Add appropriate tab for display.
		if (current_user_can('manage_options')) {
			$tabs[self::$_hook] = array(
				'title' => __('Post Data', 'sm-wp-rebase-data'),
				'id' => self::$_id,
			);
		}
		return $tabs;
	}

}
add_action('wp_rebase_load_libraries', 'WPRD_Rebase_Postdata::apply');