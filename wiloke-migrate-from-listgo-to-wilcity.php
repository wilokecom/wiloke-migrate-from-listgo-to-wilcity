<?php
/*
 * Plugin Name: Wiloke - Migrate From ListGo To Wilcity
 * Plugin URI: https://wiloke.net
 * Author: Wiloke
 * Author URI: https://wiloke.net
 * Description: It helps to migrate from ListGo to Wilcity
 */

use WilokeListingTools\MetaBoxes\Listing as ListingMetaBox;

function wilcityImportVerifyNonce()
{
	$status = check_ajax_referer('wilcity_nonce_action', 'nonce', false);
	if (!$status) {
		wp_send_json_success(['msg' => 'Invalid Nonce']);
	}
}

function wilcityImportListgoTermsOptions()
{
	wilcityImportVerifyNonce();

	$aParseData = json_decode(base64_decode($_POST['data']), true);

	if (!function_exists('wilcityMigrationInsertImage')) {
		wp_send_json_error(['msg' => 'Please activate Wilcity Bulk Import plugin']);
	}

	foreach ($aParseData as $taxonomy => $aData) {
		if (empty($aData)) {
			continue;
		}

		foreach ($aData as $taxSlug => $aInfo) {
			$oTerm = get_term_by('slug', $taxSlug, $taxonomy);
			if (empty($oTerm) || is_wp_error($oTerm)) {
				$oTerm = get_term_by('name', $aInfo['name'], $taxonomy);
				if (empty($oTerm) || is_wp_error($oTerm)) {
					continue;
				}
			}

			if (isset($aInfo['featured_image_url']) && !empty($aInfo['featured_image_url'])) {
				$aImage = wilcityMigrationInsertImage($aInfo['featured_image_url']);

				if (!empty($aImage)) {
					update_term_meta($oTerm->term_id, 'wilcity_featured_image_id', $aImage['id']);
					update_term_meta($oTerm->term_id, 'wilcity_featured_image', $aImage['url']);
				}
			}

			if (isset($aInfo['map_marker_image']) && !empty($aInfo['map_marker_image'])) {
				$aImage = wilcityMigrationInsertImage($aInfo['map_marker_image']);
				if (!empty($aImage)) {
					update_term_meta($oTerm->term_id, 'wilcity_icon_img_id', $aImage['id']);
					update_term_meta($oTerm->term_id, 'wilcity_icon_img', $aImage['url']);
				}
			}
		}
	}

	wp_send_json_success(['msg' => 'The data have been imported']);
}

function wilcityImportListgoCustomFields()
{
	wilcityImportVerifyNonce();

	if (current_user_can('administrator')) {
		if (!empty($_POST['data'])) {
			$aParseData = unserialize(base64_decode($_POST['data']));

			if (!empty($aParseData)) {
				foreach ($aParseData as $slug => $aCustomFields) {
					$postID = wilokeImportFindPostIDBySlug($slug);
					if (empty($postID)) {
						continue;
					}
					$postID = abs($postID);
					foreach ($aCustomFields as $fieldKey => $data) {
						\WilokeListingTools\Framework\Helpers\SetSettings::setPostMeta($postID, 'custom_' . $fieldKey,
							$data);
					}
				}
			}
		}
		wp_send_json_success(['msg' => 'Congratulations! The custom fields have been imported successfully']);
	} else {
		wp_send_json_error(['msg' => 'You do not have permission to access this page.']);
	}
}

function wilokeImportFindPostIDBySlug($slug, $postType = ''): int
{
	global $wpdb;
	$sql = $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE post_name=%s",
		$slug
	);

	if (!empty($postType)) {
		$sql = $wpdb->prepare($sql . " AND post_type=%s", $postType);
	}

	return (int)$wpdb->get_var("$sql ORDER BY $wpdb->posts.ID DESC");
}

function wilokeImportFindPostIDByTitle($title, $postType = ''): int
{
	global $wpdb;
	$sql = $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE post_title=%s",
		$title
	);

	if (!empty($postType)) {
		$sql = $wpdb->prepare($sql . " AND post_type=%s", $postType);
	}

	return (int)$wpdb->get_var("$sql ORDER BY $wpdb->posts.ID DESC");
}

function wilcityImportEventFields()
{
	wilcityImportVerifyNonce();
	if (current_user_can('administrator')) {
		if (!empty($_POST['data'])) {
			$aParseData = unserialize(base64_decode($_POST['data']));

			if (!empty($aParseData)) {
				foreach ($aParseData as $slug => $aEventData) {
					$postID = wilokeImportFindPostIDBySlug($slug);
					if (empty($postID)) {
						continue;
					}
					$postID = abs($postID);

					if (isset($aEventData['parent'])) {
						$parentId = wilokeImportFindPostIDBySlug($aEventData['parent']);
						if (!empty($parentId)) {
							wp_update_post([
								'ID'          => $postID,
								'post_parent' => $parentId
							]);
						}
					}
				}
			}
		}
		wp_send_json_success(['msg' => 'Congratulations! The custom fields have been imported successfully']);
	} else {
		wp_send_json_error(['msg' => 'You do not have permission to access this page.']);
	}
}

add_action('wp_ajax_wilcity_import_listgo_custom_fields', 'wilcityImportListgoCustomFields');

add_action('wp_ajax_wilcity_import_listgo_terms_options', 'wilcityImportListgoTermsOptions');
add_action('wp_ajax_wilcity_import_listgo_event_fields', 'wilcityImportEventFields');

function wilokeImportListgoDataArea()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-listgo-terms-options"
              action="<?php echo admin_url('admin.php?page=export-listgo-data'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_terms_options">
            <div class="field">
                <label for="terms-options-data">Terms Options Data</label>
                <textarea name="terms_options_data" id="terms-options-data" class="data" cols="30" rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_export_terms_options" value="1">
            <div class="field">
                <button class="ui button green">Import Terms Options</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoCustomFields()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-listgo-custom-fields"
              action="<?php echo admin_url('admin.php?page=import-listgo-custom-fields'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_custom_fields">
            <div class="field">
                <label for="listgo_custom_field_data">Paste Listgo Custom Fields Data To The Field below</label>
                <textarea name="listgo_custom_field_data" id="listgo_custom_field_data" class="data" cols="30"
                          rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_import_custom_fields" value="1">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoPostAuthors()
{
	if (isset($_POST['action']) && $_POST['action'] == 'run_import_listgo_post_authors') {
		$aData = unserialize(base64_decode($_POST['listgo_post_authors_data']));

		$aErrors = [];
		foreach ($aData as $aInfo) {
			$postId = wilokeImportFindPostIDBySlug($aInfo['post_slug']);
			if (empty($postId)) {
				$postId = wilokeImportFindPostIDByTitle($aInfo['post_title']);
			}

			if (empty($postId)) {
				$aErrors[] = sprintf('We could not found post %s', $aInfo['post_title']);
				continue;
			}

			$oAuthor = get_user_by('login', $aInfo['author_slug']);
			if (empty($oAuthor) || is_wp_error($oAuthor)) {
				$aErrors[] = sprintf('We could not found author %s', $aInfo['author_slug']);
				continue;
			}

			wp_update_post([
				'ID'          => $postId,
				'post_author' => $oAuthor->ID
			]);
		}

		if (count($aErrors) == count($aData)) {
			?>
            <div class="ui segment red">We could not migrate the post authors</div>
			<?php
		} else {
			if (!empty($aErrors)) {
				?>
                <div class="ui segment red">
                    The data have been migrated except the following:
					<?php
					foreach ($aErrors as $err) {
						echo $err . '<br />';
					}
					?>
                </div>
				<?php
			} else {
				?>
                <div class="ui segment green">
                    All data have been migrated
                </div>
				<?php
			}
		}
	}
	?>
    <div class="ui segment">
        <form action="<?php echo admin_url('admin.php?page=import-listgo-post-authors'); ?>"
              method="POST"
              class="form ui">
            <div class="field">
                <label for="listgo_post_authors_data">Listgo Post Authors Data</label>
                <textarea name="listgo_post_authors_data"
                          id="listgo_post_authors_data"
                          class="data"
                          cols="30"
                          rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="action" value="run_import_listgo_post_authors">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoEventsFields()
{
	?>
    <div class="ui segment">
        <form id="wilcity-import-event-fields"
              action="<?php echo admin_url('admin.php?page=import-listgo-event-fields'); ?>" method="POST"
              class="form ui wilcity-import-listgo" data-ajax="wilcity_import_listgo_event_fields">
            <div class="field">
                <label for="listgo_custom_field_data">Paste Events Data To The Field below</label>
                <textarea name="listgo_custom_field_data" id="listgo_custom_field_data" class="data" cols="30"
                          rows="10"></textarea>
            </div>
			<?php echo wp_nonce_field('wilcity_nonce_action', 'wilcity_nonce_fields'); ?>
            <input type="hidden" name="run_import_custom_fields" value="1">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportBusinessHours($aFullBusinessHours, $postId, $aRawData)
{
	$aBusinessHours = [];
//	$aSunday = array_pop($aFullBusinessHours);
//	$aFullBusinessHours = array_merge([$aSunday], $aFullBusinessHours);
	global $wpdb;
	$wpdb->show_errors();

	$aDayOfWeeks = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

	foreach ($aFullBusinessHours as $order => $aItem) {
		$aDay = [];
		if (isset($aItem['open_time'])) {
			if ($aItem['closed']) {
				$aDay['isOpen'] = 'no';
				$aDay['operating_times']['firstOpenHour'] = '';
				$aDay['operating_times']['firstCloseHour'] = '';
			} else {
				$aDay['isOpen'] = 'yes';
				$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aItem['open_time']);
				$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem['close_time']);

				if (!empty($aItem['extra_open_time']) && !empty($aItem['extra_close_time'])) {
					$aDay['operating_times']['secondOpenHour'] = wilokeBuildBH($aItem['extra_open_time']);
					$aDay['operating_times']['secondCloseHour'] = wilokeBuildBH($aItem['extra_close_time']);
				}
			}
		}

		$aBusinessHours['businessHours'][$aDayOfWeeks[$order]] = $aDay;
	}

	$aBusinessHours['hourMode'] = 'open_for_selected_hours';
	$aBusinessHours['timeFormat'] = 'inherit';
	$status = ListingMetaBox::saveBusinessHours($postId, $aBusinessHours);
}

function wilokeImportListgoBusinessHours()
{
	$pageSlug = 'import-listgo-business-hours';
	if (isset($_POST['action']) && $_POST['action'] == 'import_listgo_business_hours') {
		if (!isset($_POST['listgo_business_hours']) || empty($_POST['listgo_business_hours'])) {
			$msg = "Business Hour is required";
			$status = "error";
		} else {
			$aData = json_decode(base64_decode($_POST['listgo_business_hours']), true);

			foreach ($aData as $slug => $aValue) {
				$postId = (int)wilokeImportFindPostIDBySlug($slug, 'listing');
				if (empty($postId)) {
					$postId = (int)wilokeImportFindPostIDByTitle($aValue['post_title']);
				}

				if (!empty($postId)) {
					if ($aValue['toggle'] == 'disable') {
						\WilokeListingTools\Framework\Helpers\SetSettings::setPostMeta(
							$postId,
							'wilcity_hourMode',
							'no_hours_available'
						);
					} else {
						\WilokeListingTools\Framework\Helpers\SetSettings::setPostMeta(
							$postId,
							'wilcity_hourMode',
							'open_for_selected_hours'
						);

						wilokeImportBusinessHours($aValue['hours'], $postId, $aData);
					}
				}
			}

			$msg = "Business Hours have been imported";
			$status = "success";
		}
	}

	if (isset($status)) {
		?>
        <div class="ui segment <?php echo $status === 'success' ? 'green' : 'red'; ?>">
			<?php echo $msg; ?>
        </div>
		<?php
	}
	?>
    <div class="ui segment">
        <form action="<?php echo admin_url('admin.php?page=' . $pageSlug); ?>" method="POST"
              class="form ui">
            <div class="field">
                <label for="listgo_business_hours">Paste Business Hours To The Field below</label>
                <textarea id="listgo_business_hours"
                          name="listgo_business_hours"
                          cols="30"
                          rows="10"></textarea>
            </div>
            <input type="hidden" name="action" value="import_listgo_business_hours">
            <div class="field">
                <button type="submit" class="ui button green">Import Now</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeImportListgoData()
{
	add_menu_page('Import Listgo Term Data', 'Import Listgo Term Data', 'manage_options', 'import-listgo-data',
		'wilokeImportListgoDataArea');
	add_menu_page('Import Listgo Business Hours', 'Import Listgo Business Hours', 'manage_options',
		'import-listgo-business-hours',
		'wilokeImportListgoBusinessHours');
	add_menu_page('Import Listgo Listings', 'Import Listgo Listings', 'manage_options', 'import-listgo-custom-fields',
		'wilokeImportListgoCustomFields');
	add_menu_page('Import Listgo Events', 'Import Listgo Events', 'manage_options', 'import-listgo-event-fields',
		'wilokeImportListgoEventsFields');
	add_menu_page('Import Listgo Post Authors', 'Import Listgo Post Authors', 'manage_options',
		'import-listgo-post-authors',
		'wilokeImportListgoPostAuthors');
}

function wilcityImportListgoScripts()
{
	wp_enqueue_style('semantic', plugin_dir_url(__FILE__) . 'vendor/semantic/semantic.css');
	wp_enqueue_script('wilcity-import-listgo', plugin_dir_url(__FILE__) . 'source/js/script.js', ['jquery'], 1.0, true);
}

//if (!function_exists('listgoIsSingleListing')) {
add_action('admin_menu', 'wilokeImportListgoData');
add_action('admin_enqueue_scripts', 'wilcityImportListgoScripts');
//} else {
include plugin_dir_path(__FILE__) . 'export/wiloke-listgo-export.php';
//}
