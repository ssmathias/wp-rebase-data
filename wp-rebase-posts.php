<?php
/**
 * Plugin Name: WP Rebase Data
 * Plugin URI: http://github.com/ssmathias/wp-rebase-data
 * Description: Allows a site admin to trigger "save" actions on a variety of data programmatically.
 * Author: Steven Mathias
 * Version: 0.1
 * Author URI: http://github.com/ssmathias/
 **/

define('WP_REBASE_PLUGIN_DIR', trailingslashit(dirname(__file__)));

class WP_Rebase_Data {
	public static $current;
	
	public static function admin_init() {
		self::$current = isset($_GET['wp_rebase_screen']) ? $_GET['wp_rebase_screen'] : 'postdata';
		if (!class_exists('WPRD_Rebase_Posts')) {
			include WP_REBASE_PLUGIN_DIR.'classes/class.rebase.post.php';
		}
		do_action('wp_rebase_load_libraries');
	}

	public static function admin_enqueue_scripts($hook) {
		if ($hook == 'tools_page_wp-rebase-data') {
			wp_enqueue_script('wp-rebase-data-js', admin_url('admin-ajax.php').'?action=wp_rebase_data_js', array('jquery'));
			wp_enqueue_style('wp-rebase-data-css', admin_url('admin-ajax.php').'?action=wp_rebase_data_css');
		}
	}

	public static function admin_menu() {
		add_submenu_page(
			'tools.php',
			__('Rebase Data', 'sm-wp-rebase-data'),
			__('Rebase Data', 'sm-wp-rebase-data'),
			'manage_options',
			'wp-rebase-data',
			'WP_Rebase_Data::admin_page'
		);
	}
	
	public static function admin_page() {
		if (!current_user_can('manage_options')) {
			echo __('You do not have sufficient privileges to access this page.', 'sm-wp-rebase-data');
			return;
		}
		screen_icon();
		?>
		<h1><?php echo esc_html(__('Rebase Data', 'sm-wp-rebase-data')); ?></h1>
		<?php
		$tabs = apply_filters('wp_rebase_admin_tabs', array());
		$current = isset($_GET['wp_rebase_screen']) ? $_GET['wp_rebase_screen'] : 'default';
		if (empty($current) || !isset($tabs[$current])) {
			$current = array_keys($tabs);
			$current = $current[0];
		}
		?>
		<ul class="tab-list" id="wp-rebase-tabs">
		<?php
		foreach ($tabs as $hook=>$tabdata) {
			$classes = 'tab';
			if ($current == $hook) { $classes .= ' current-tab'; };
		?>
			<li class="<?php echo esc_attr($classes); ?>"><a href="#<?php echo esc_attr($tabdata['id']); ?>"><?php echo esc_html($tabdata['title']); ?></a></li>
		<?php } ?>
		</ul>
		
		<?php
		do_action("wp_rebase_admin_screen_$current");
	}
	
	public static function admin_js() {
		header('Content-Type: text/javascript');
		$current = self::$current;
		?>
		function doResaveAction(action) {
			jQuery.ajax({
				"method": "POST",
				"async": true,
				"url": ajaxurl,
				"data": {
					"action": "wp_rebase_data",
					"resave_action": action,
					
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
		do_action('wp_rebase_admin_scripts');
		do_action("wp_rebase_admin_scripts_{$current}");
		exit();
	}
	
	public static function admin_css() {
		header('Content-Type: text/css');
		do_action('wp_rebase_admin_scripts');
		do_action("wp_rebase_admin_scripts_{$current}");
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
		do_action('wp_rebase_admin_scripts');
		do_action("wp_rebase_admin_scripts_{$current}");
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
add_action('admin_init', 'WP_Rebase_Data::admin_init', 1);
add_action('admin_enqueue_scripts', 'WP_Rebase_Data::admin_enqueue_scripts');
add_action('admin_menu', 'WP_Rebase_Data::admin_menu');
add_action('wp_ajax_wp_rebase_data_js', 'WP_Rebase_Data::admin_js');
add_action('wp_ajax_wp_rebase_data_css', 'WP_Rebase_Data::admin_css');
add_action('wp_ajax_wp_rebase_data', 'WP_Rebase_Data::wp_ajax_wp_rebase_data');
