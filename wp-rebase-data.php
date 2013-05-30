<?php
/**
 * Plugin Name: WP Rebase Data
 * Plugin URI: http://github.com/ssmathias/wp-rebase-data
 * Description: Allows a site admin to trigger "save" actions on a variety of data programmatically.
 * Author: Steven Mathias
 * Version: 2.0.1
 * Author URI: http://github.com/ssmathias/
 **/

define('WP_REBASE_PLUGIN_DIR', trailingslashit(dirname(__file__)));

class WP_Rebase_Data {
	private static $_current;
	private static $_tabs;
	
	public static function admin_init() {
		if (!class_exists('WPRD_Rebase_Posts')) {
			include WP_REBASE_PLUGIN_DIR.'classes/class.rebase.post.php';
		}
		do_action('wp_rebase_load_libraries');
		
		$default_tab = apply_filters('wp_rebase_data_default_tab', '');
		
		self::$_tabs = apply_filters('wp_rebase_admin_tabs', array());
		self::$_current = isset($_REQUEST['wp_rebase_screen']) ? $_REQUEST['wp_rebase_screen'] : $default_tab;
		if (!isset(self::$_tabs[self::$_current])) {
			reset(self::$_tabs);
			self::$_current = key(self::$_tabs);
		}
	}

	public static function admin_enqueue_scripts($hook) {
		if ($hook == 'tools_page_wp-rebase-data') {
			wp_enqueue_script('wp-rebase-data-js', admin_url('admin-ajax.php?action=wp_rebase_data_js'), array('jquery'));
			do_action('wp_rebase_admin_scripts_'.self::$_current);
			wp_enqueue_style('wp-rebase-data-css', admin_url('admin-ajax.php?action=wp_rebase_data_css'));
			do_action('wp_rebase_admin_styles_'.self::$_current);
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
		<div id="wp-rebase-data-admin">
		<h1><?php echo esc_html(__('Rebase Data', 'sm-wp-rebase-data')); ?></h1>
		<ul class="tab-list" id="wp-rebase-tabs">
		<?php
		foreach (self::$_tabs as $hook=>$tabdata) {
			$classes = 'tab';
			if (self::$_current == $hook) {
			?>
			<li class="<?php echo esc_attr($classes); ?> current-tab">
				<span><?php echo esc_html($tabdata['title']); ?></span>
			</li>
			<?php
			}
			else {
			?>
			<li class="<?php echo esc_attr($classes); ?>">
				<a href="<?php echo esc_url(add_query_arg('wp_rebase_screen', $tabdata['id'], $_SERVER['REQUEST_URI'])); ?>"><?php echo esc_html($tabdata['title']); ?></a>
			</li>
			<?php 
			}
		} ?>
		</ul>
		<div id="wp-rebase-data-tab-contents">
			<?php do_action('wp_rebase_admin_screen_'.self::$_current); ?>
		</div>
		</div>
		<?php
	}
	
	public static function admin_js() {
		header('Content-Type: text/javascript');
		if ($_GET['hook']) {
			do_action('wp_rebase_admin_print_scripts_'.$_GET['hook']);
			exit();
		}
		?>
		function doResave($form) {
			var $ = jQuery;
			if (console) {
				console.log($form.serialize());
			}
			if ($form.find("[name=\"action\"]").length === 0) {
				$form.append("<input type=\"hidden\" name=\"action\" value=\"wp_rebase_data\" />");
			}
			$.ajax({
				"type": "POST",
				"async": true,
				"url": ajaxurl,
				"data": $form.serialize(),
				"success": function(response) {
					$("body").trigger("wpRebaseAjaxSuccess", response);
				},
				"error": function(xhr) {
					$("body").trigger("wpRebaseAjaxError", xhr);
				}
			});
		}
		<?php
		do_action('wp_rebase_admin_scripts');
		exit();
	}
	
	public static function admin_css() {
		header('Content-Type: text/css');
		if ($_GET['hook']) {
			do_action('wp_rebase_admin_print_styles_'.$_GET['hook']);
			exit();
		}
		?>
		#wp-rebase-data-admin {
			width: 95%;
			min-width: 800px;
		}
		.tab-list {
				padding: 10px 5px 0 5px;
				border-radius: 5px 5px 0px 0px;
				background-color: #21759b;
				background-image: linear-gradient(to bottom,#2a95c5,#21759b);
				overflow:hidden;
				margin-bottom: 0px;
		}
		.tab-list .tab {
			border-radius: 5px 5px 0 0;
			float:left;
			padding:5px;
			margin:0 4px;
			height:20px;
			background-color: #CCCCFF;
			background-image: linear-gradient(to top, #CCCCCC, #999999);
		}
		.tab-list .tab a,
		.tab-list .tab span {
			color: #000033;
			text-decoration: none;
			font-weight: bold;
		}
		.tab-list .tab.current-tab a,
		.tab-list .tab.current-tab span {
			color: #000033;
		}
		.tab-list .tab:hover {
			background-color: #EEEEFF;
			background-image: linear-gradient(to top, #EEEEEE, #AAAAAA);
		}
		.tab-list .tab.current-tab {
			background-color: #FFFFFF;
			background-image: linear-gradient(to top, #FFFFFF, #CCCCCC);
		}
		#wp-rebase-data-tab-contents {
			border: 1px solid #21759b;
			border-top:none;
			box-sizing:border-box;
			border-radius: 0px 0px 5px 5px;
			padding: 10px;
		}
		<?php
		do_action('wp_rebase_admin_print_styles');
		exit();
	}

	public static function wp_ajax_wp_rebase_data() {
		global $post;
		if (isset($_POST['hook']) && !empty($_POST['hook'])) {
			do_action('wp_rebase_ajax_'.$_POST['hook']);
		}
		exit(-1);
	}

}
add_action('admin_init', 'WP_Rebase_Data::admin_init', 1);
add_action('admin_enqueue_scripts', 'WP_Rebase_Data::admin_enqueue_scripts');
add_action('admin_menu', 'WP_Rebase_Data::admin_menu');
add_action('wp_ajax_wp_rebase_data_js', 'WP_Rebase_Data::admin_js');
add_action('wp_ajax_wp_rebase_data_css', 'WP_Rebase_Data::admin_css');
add_action('wp_ajax_wp_rebase_data', 'WP_Rebase_Data::wp_ajax_wp_rebase_data');
