<?php
/**
 * Plugin Name: WP Rebase Data
 * Plugin URI: http://github.com/ssmathias/wp-rebase-data
 * Description: Allows a site admin to trigger "save" actions on a variety of data programmatically.
 * Author: Steven Mathias
 * Version: 0.1
 * Author URI: http://github.com/ssmathias/
 **/
 
class WP_Rebase_Data {

	public static function admin_init() {
		global $pagenow;
		if ($pagenow == 'options-general.php' && !empty($_GET['page']) && $_GET['page'] == 'wp-rebase-data') {
			wp_enqueue_script('wp-rebase-data-js', admin_url('admin-ajax.php').'?action=wp_rebase_data_js', array('jquery'));
			wp_enqueue_style('wp-rebase-data-css', admin_url('admin-ajax.php').'?action=wp_rebase_data_css');
		}
	}

	public static function admin_menu() {
		add_options_page(
			__('WP Rebase Data', 'sm-wp-rebase-data'),
			__('WP Rebase Data', 'sm-wp-rebase-data'),
			'manage_options',
			'wp-rebase-data',
			'WP_Rebase_Data::admin_page'
		);
	}
	
	public static function admin_page() {
		if (!current_user_can('manage_options')) {
			echo 'You do not have sufficient privileges to access this page.';
			return;
		}
		screen_icon();
		global $wpdb;
		$post_types = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts}");
		$allowed_post_types = array_diff($post_types, apply_filters('wp_rebase_data_excluded_types', array('revision')));
		$post_stati = $wpdb->get_col("SELECT DISTINCT post_status FROM {$wpdb->posts}");
		$allowed_post_stati = array_diff($post_stati, apply_filters('wp_rebase_data_excluded_stati', array('auto-draft', 'inherit')));
		?>
		<h1><?php echo esc_html(__('WP Rebase Data', 'sm-wp-rebase-data')); ?></h1>
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
		<?php } ?>
		<?php
	}
	
	public static function admin_js() {
		header('Content-Type: text/javascript');
		?>
		var resavePosts = {"jForm": null, "post_types": [], "post_stati": [], "page": 1};
		function doResaveAction() {
			jQuery.ajax({
				"method": "GET",
				"async": true,
				"url": <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
				"data": {
					"action": "wp_rebase_data",
					"post_types": resavePosts.post_types,
					"post_stati": resavePosts.post_stati,
					"paged": resavePosts.page
				},
				"success": function(response) {
					var completePercent = 0;
					response = jQuery.parseJSON(response);
					resavePosts.page += 1;
					hasComplete = (response.total_records == response.records_complete);
					if (response.total_records == 0) {
						completePercent = "100%";
					}
					else {
						completePercent = Math.round((response.records_complete / response.total_records) * 100) + "%";
					}
					resavePosts.jForm
						.find(".progress-indicator")
							.find(".progress-bar")
								.width(completePercent)
								.end()
							.find(".numeric-indicator")
								.html(response.records_complete + "/" + response.total_records)
								.end()
							.find(".ajax-errors")
								.append(response.warnings);
								
					if (response.total_records > response.records_complete) {
						// We need to do this again. Loop it with a timeout so we can redraw the screen.
						doResaveAction();
					}
					else {
						resavePosts.jForm
							.find("button, input")
								.removeAttr("disabled");
					}
				},
				"error": function() {
					resavePosts.jForm
						.find("button, input")
							.removeAttr("disabled");
					alert(<?php echo json_encode(__('Could not save. Confirm your settings and try again.')); ?>);
				}
			});
		}
		jQuery(document).ready(function($) {
			$("#wp-rebase-data-form")
				.on("submit", function(e) {
					e.preventDefault();
					e.stopPropagation();
					return false;
				})
				.find("#btn-run-resave")
					.on("click", function(e) {
						resavePosts.jForm = $("#wp-rebase-data-form");
						resavePosts.post_stati = [];
						resavePosts.post_types = [];
						resavePosts.page = 1;
						e.preventDefault();
						e.stopPropagation();
						resavePosts.jForm
							.find("#wp-rebase-data-post-types .checkbox-list input[type=\"checkbox\"]:checked")
								.each(function() {
									var $checkbox = $(this);
									resavePosts.post_types.push($checkbox.val());
								})
								.end()
							.find("#wp-rebase-data-post-stati .checkbox-list input[type=\"checkbox\"]:checked")
								.each(function() {
									var $checkbox = $(this);
									resavePosts.post_stati.push($checkbox.val());
								})
								.end()
							.find("button, input")
								.attr("disabled", "disabled")
								.end()
							.find(".progress-indicator")
								.show()
								.find(".current-task")
									.html(<?php echo json_encode(__('Saving Records')); ?>);
						doResaveAction();
					});
		});
		<?php
		exit();
	}
	
	public static function admin_css() {
		header('Content-Type: text/css');
		?>
		#wp-rebase-data-form {
			width: 400px;
		}
		#wp-rebase-data-form fieldset {
			border: thin solid #333333;
			padding: 5px;
			width: 100%;
		}
		#wp-rebase-data-form legend {
			margin: 0px 5px;
		}
		#wp-rebase-data-form li {
			width: 185px;
			float: left;
			margin-right: 5px;
		}
		#wp-rebase-data-form .progress-indicator {
			display: none;
		}
		#wp-rebase-data-form .progress-bar-wrapper {
			width: 100%;
			padding: 2px;
			background-color: #999;
			border-radius: 3px;
		}
		#wp-rebase-data-form .progress-bar {
			height: 20px;
			width: 0px;
			border-radius: 2px;
			background-color: lime;
		}
		<?php
		exit();
	}

	public static function wp_ajax_wp_rebase_data() {
		global $post;
		$posts_per_page = 25;
		if (!current_user_can('manage_options')) {
			header('HTTP/1.0 403 Unauthorized');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'You do not have the necessary permissions to complete this request.'
			));
			print $response;
			exit();
		}
		if (empty($_GET['post_types'])) {
			header('HTTP/1.0 501 Invalid Request');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'At least one post type must be selected'
			));
			print $response;
			exit();
		}
		$post_types = $_GET['post_types'];
		if (!is_array($post_types)) {
			$post_types = explode(',', $post_types);
			// TODO Use array_map here
			foreach ($post_types as $i=>$type) {
				$post_types[$i] = trim($type);
			}
		}
		if (empty($_GET['post_stati'])) {
			header('HTTP/1.0 501 Invalid Request');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'At least one post status must be selected'
			));
			print $response;
			exit();
		}
		$post_stati = $_GET['post_stati'];
		if (!is_array($post_stati)) {
			$post_stati = array($post_stati);
			// TODO Use array_map here
			foreach ($post_stati as $i=>$status) {
				$post_stati[$i] = trim($status);
			}
		}
		$paged = 1;
		if (!empty($_GET['paged']) && is_numeric($_GET['paged'])) {
			$paged = intval($_GET['paged']);
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
			'total_records' => $query->found_posts,
			'records_complete' => $total_done,
			'warnings' => $warnings
		));
		print $response;
		exit();
	}

}
add_action('admin_init', 'WP_Rebase_Data::admin_init');
add_action('admin_menu', 'WP_Rebase_Data::admin_menu');
add_action('wp_ajax_wp_rebase_data_js', 'WP_Rebase_Data::admin_js');
add_action('wp_ajax_wp_rebase_data_css', 'WP_Rebase_Data::admin_css');
add_action('wp_ajax_wp_rebase_data', 'WP_Rebase_Data::wp_ajax_wp_rebase_data');
