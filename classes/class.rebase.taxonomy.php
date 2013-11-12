<?php
/**
 * Class: WPRD_Rebase_Taxdata
 */

class WPRD_Rebase_Taxdata {
	private static $_hook = 'taxdata';
	private static $_id = 'taxdata';
	
	static function apply() {
		// Execute at wp_rebase_load_libraries
		$hook = self::$_hook;

		add_filter('wp_rebase_admin_tabs', 'WPRD_Rebase_Taxdata::adminTab');
		add_action("wp_rebase_admin_print_styles_{$hook}", 'WPRD_Rebase_Taxdata::printAdminStyles');
		add_action("wp_rebase_admin_scripts_{$hook}", 'WPRD_Rebase_Taxdata::enqueueAdminScripts');
		add_action("wp_rebase_admin_styles_{$hook}", 'WPRD_Rebase_Taxdata::enqueueAdminStyles');
		add_action("wp_rebase_admin_print_scripts_{$hook}", 'WPRD_Rebase_Taxdata::printAdminScripts');
		add_action("wp_rebase_admin_screen_{$hook}", 'WPRD_Rebase_Taxdata::adminScreen');
		add_action("wp_rebase_ajax_{$hook}", 'WPRD_Rebase_Taxdata::handleAjax');
	}
	
	static function handleAjax() {
		global $post;
		// Execute on appropriate ajax call
		if (!check_admin_referer('wp_rebase_data_taxdata', 'key')) {
			header('HTTP/1.0 403 Forbidden');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'I\'m sorry, Dave. I can\'t do that.',
			));
			print $response;
			exit();
		}
		$new_key = wp_create_nonce('wp_rebase_data_taxdata');
		if (!current_user_can('manage_options')) {
			header('HTTP/1.0 403 Forbidden');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'You do not have the necessary permissions to complete this request.',
			));
			print $response;
			exit();
		}
		if (empty($_POST['taxonomies'])) {
			header('HTTP/1.0 401 Invalid Request');
			$response = json_encode(array(
				'status' => 'error',
				'message' => 'At least one taxonomy must be selected',
				'key' => $new_key,
			));
			print $response;
			exit();
		}
		$taxonomies = $_POST['taxonomies'];
		if (!is_array($taxonomies)) {
			$taxonomies = explode(',', $taxonomies);
			// TODO Use array_map here
			foreach ($taxonomies as $i=>$taxonomy) {
				$taxonomies[$i] = trim($taxonomy);
			}
		}
		// May eventually allow posts per page to be configured from front-end.
		$terms_per_page = 25;
		
		$paged = 1;
		if (!empty($_POST['paged']) && is_numeric($_POST['paged'])) {
			$paged = intval($_POST['paged']);
		}
		$warnings = array();
		
		$total_done = $terms_per_page * ($paged - 1);
		
		// Doing this twice is unfortunate, however we don't want to load all the term objects to get the count.
		$count = get_terms($taxonomies, array('get' => 'all', 'fields' => 'count'));
		if (is_wp_error($count)) {
			$warnings[] = 'Could not calculate total terms to be updated.';
			$count = 0;
		}
		$terms = get_terms($taxonomies, array(
			'get' => 'all',
			'number' => $terms_per_page,
			'offset' => $total_done,
		));
		
		if (is_wp_error($terms)) {
			$warnings[] = 'Error retrieving terms. Could not continue';
			header('HTTP/1.0 201 Done With Errors');
		}
		else if (!empty($terms)) {
			foreach ($terms as $term) {
				$result = wp_update_term($term->term_id, $term->taxonomy);
				if (empty($result) || is_wp_error($result)) {
					$warnings[] = 'Error updating "' . $term->name . '" in taxonomy "' . $term->taxonomy . '"';
				}
				++$total_done;
			}
		}
		
		if (!empty($warnings)) {
			header('HTTP/1.0 201 Done With Errors');
		}
		else {
			header('HTTP/1.0 200 Success');
		}
		$response = json_encode(array(
			'status' => 'success',
			'total_records' => intval($count),
			'records_complete' => $total_done,
			'warnings' => $warnings,
			'key' => $new_key,
		));
		print $response;
		exit();
	}
	
	static function enqueueAdminStyles() {
		wp_enqueue_style('wp-rebase-taxdata-css', admin_url('admin-ajax.php?action=wp_rebase_data_css&hook='.self::$_hook));
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
		wp_enqueue_script('wp-rebase-taxdata', admin_url('admin-ajax.php?action=wp_rebase_data_js&hook='.self::$_hook), array('jquery', 'jquery-ui-core', 'jquery-ui-widget'));
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
					$form.progressIndicator("activate", "Saving Taxonomies");
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
		$taxonomies = apply_filters("wp_rebase_data_{self::$_hook}_taxonomies", get_taxonomies(array(), 'objects'));
		?>
		<form id="wp-rebase-data-taxdata" action="#" method="POST">
		<?php wp_nonce_field('wp_rebase_data_taxdata', 'key'); ?>
		<input type="hidden" name="hook" value="<?php echo esc_attr(self::$_hook); ?>" />
		<input type="hidden" name="paged" value="1" />
		<?php if (!empty($taxonomies)) { ?>
		<fieldset id="wp-rebase-data-post-types" class="multi-check">
			<legend><?php echo esc_html(__('Taxonomies', 'sm-wp-rebase-data')); ?></legend>
			<div class="checkbox-list-wrapper">
				<ul class="checkbox-list">
				<?php foreach ($taxonomies as $tax=>$obj) {
					$label = $obj->labels->name;
					?>
					<li>
						<input type="checkbox" name="taxonomies[]" id="taxonomy-<?php echo esc_attr($tax); ?>" value="<?php echo esc_attr($tax); ?>" />
						<label for="taxonomy-<?php echo esc_attr($tax); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($tax); ?>)</label>
					</li>
				<?php } ?>
				</ul>
			</div>
		</fieldset>
		<div class="form-controls">
			<button class="button button-primary" id="btn-run-resave"><?php echo esc_html(__('Resave Selected Taxonomies', 'sm-wp-rebase-data')); ?></button>
		</div>
		</form>
		<?php }
	}
	
	static function adminTab($tabs = array()) {
		// Add appropriate tab for display.
		if (current_user_can('manage_options')) {
			$tabs[self::$_hook] = array(
				'title' => __('Taxonomy Data', 'sm-wp-rebase-data'),
				'id' => self::$_id,
			);
		}
		return $tabs;
	}

}
add_action('wp_rebase_load_libraries', 'WPRD_Rebase_Taxdata::apply');