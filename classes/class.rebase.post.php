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
		if (!empty($_POST['page']) && is_numeric($_POST['page'])) {
			$paged = intval($_POST['page']);
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
	
	static function adminStyles() {
		// Admin styles for this form
		$form_id = '#wp-rebase-data-'.self::$_id;
		$styles = <<<EOD
		$form_id {
			width: 400px;
		}
		$form_id fieldset {
			border: thin solid #333333;
			padding: 0 5px;
			width: 100%;
			margin-bottom: 5px;
			border-radius: 3px;
		}
		$form_id legend {
			margin: 0px 5px;
			font-weight: bold;
		}
		$form_id li {
			width: 185px;
			float: left;
			margin-right: 5px;
		}
		$form_id .progress-indicator {
			display: none;
		}
		$form_id .progress-bar-wrapper {
			width: 100%;
			padding: 2px;
			background-color: #999;
			border-radius: 3px;
		}
		$form_id .progress-bar {
			height: 20px;
			width: 0px;
			border-radius: 2px;
			background-color: lime;
		}
EOD;
		echo $styles;
	}
	
	static  function adminScripts() {
		// Admin scripts for this form
		$form_selector = '#wp-rebase-data-'.self::$_id;
		?>
		jQuery(document).ready(function($) {
			var $form = $(<?php echo json_encode($form_selector); ?>),
				$nonceField = $(<?php echo json_encode($form_selector . ' input[name="key"]'); ?>),
				data = {};
			$form
				.on("submit", function(e) {
					e.preventDefault();
					e.stopPropagation();
					data.hook = <?php echo json_encode(self::$_hook); ?>;
					data.post_stati = [];
					data.post_types = [];
					data.page = 1;
					data.key = $form.find("input[name=\"key\"]").val();
					$form
						.find("#wp-rebase-data-post-types")
							.find("input[type=\"checkbox\"]:checked").each(function() {
								data.post_types.push($(this).val());
							}).end()
						.end().find("#wp-rebase-data-post-stati")
							.find("input[type=\"checkbox\"]:checked").each(function() {
								data.post_stati.push($(this).val());
							}).end()
						.end().find("button, input").attr("disabled", "disabled")
						.end().find(".progress-indicator").show()
							.find(".current-task").html(<?php echo json_encode(__('Saving Records', 'sm-wp-rebase-data')); ?>);
					doResave(data);
				})
			$("body").on("wpRebaseAjaxError", function(xhr) {	
				$form
					.find("button, input")
						.removeAttr("disabled");
				alert(<?php echo json_encode(__('Could not save. Confirm your settings and try again.')); ?>);
				
			}).on("wpRebaseAjaxSuccess", function(e, response) {
				var completePercent;
				response = jQuery.parseJSON(response);
				console.log("LOGGING OUT DATA AFTER SUCCESS");
				console.log(data);
				data.page += 1;
				if (typeof response.key !== "undefined") {
					data.key = response.key
					$nonceField.val(response.key);
				}
				hasComplete = (response.total_records <= response.records_complete);
				if (response.total_records == 0) {
					completePercent = "100%";
				}
				else {
					completePercent = Math.round((response.records_complete / response.total_records) * 100) + "%";
				}
				
				$form
					.find(".progress-indicator")
						.find(".progress-bar")
							.width(completePercent)
							.end()
						.find(".numeric-indicator")
							.html(response.records_complete + "/" + response.total_records)
							.end()
						.find(".ajax-errors")
							.append(response.warnings);
							
				if (!hasComplete) {
					// We need to do this again. Loop it with a timeout so we can redraw the screen.
					doResave(data);
				}
				else {
					$form
						.find("button, input")
							.removeAttr("disabled")
						.end().find(".progress-indicator .current-task")
							.html(<?php echo json_encode(__('Saving Records Complete', 'sm-wp-rebase-data'))?>);
				}
			});
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
		<?php if (!empty($allowed_post_types)) { ?>
		<fieldset id="wp-rebase-data-post-types">
			<legend><?php echo esc_html(__('Post Types', 'sm-wp-rebase-data')); ?></legend>
			<ul class="checkbox-list">
				<?php foreach ($allowed_post_types as $type) { ?>
				<li>
					<input type="checkbox" name="post-types[]" id="post-type-<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($type); ?>" />
					<label for="post-type-<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></label>
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
					<input type="checkbox" name="post-status[]" id="post-status-<?php echo esc_attr($status); ?>" value="<?php echo esc_attr($status); ?>" />
					<label for="post-status-<?php echo $status; ?>"><?php echo esc_html($status); ?></label>
				</li>
				<?php } ?>
			</ul>
		</fieldset>
		<button class="button button-primary" id="btn-run-resave"><?php echo esc_html(__('Resave Selected Posts', 'sm-wp-rebase-data')); ?></button>
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