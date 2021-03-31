<?php

use WilokeListgoFunctionality\AlterTable\AlterTableGeoPosition;
use WilokeListgoFunctionality\AlterTable\AlterTableBusinessHours;
use WilokeListgoFunctionality\Framework\Helpers\GetSettings;

function wilokeExportListgoBusinessHours()
{
	$paged = 1;
	$postsPerPage = 100;

	if (isset($_POST['action']) && $_POST['action'] == 'export_listgo_business_hours') {
		$paged = isset($_POST['paged']) ? $_POST['paged'] : 1;
		$postsPerPage = isset($_POST['posts_per_page']) ? $_POST['posts_per_page'] : 100;

		$query = new WP_Query([
			'post_type'      => 'listing',
			'posts_per_page' => $postsPerPage,
			'paged'          => $paged,
			'post_status'    => 'publish'
		]);


		$aData = [];
		global $wpdb, $wiloke;

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();

				$postID = $query->post->ID;
				$aBusinessHours = GetSettings::getBusinessHours($postID);

				if (!empty($aBusinessHours)) {
					$toggle = get_post_meta($postID, 'wiloke_toggle_business_hours', true);
					$toggle = $toggle ? $toggle : 'enable';

					$aData[$query->post->post_name] = [
						'hours'      => $aBusinessHours,
						'toggle'     => $toggle,
						'post_title' => $query->post->post_title
					];
				}
			}
		}
	}
	?>
    <div class="ui segment">
        <form action="<?php echo admin_url('admin.php?page=export-listgo-business-hours'); ?>" method="POST"
              class="form ui">
            <div class="field">
                <label for="terms-options-data">Business Hour Data</label>
                <textarea cols="30" rows="10"><?php echo base64_encode(json_encode($aData)); ?></textarea>
            </div>
            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="posts_per_page"
                       value="<?php echo esc_attr($postsPerPage); ?>">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="paged" value="<?php echo esc_attr($paged); ?>">
            </div>
            <input type="hidden" name="action" value="export_listgo_business_hours">
            <input type="hidden" name="page" value="export-listgo-business-hours">
            <button class="ui button green" type="submit">Export</button>
        </form>
    </div>
	<?php
}

function wilokeExportListgoDataArea()
{
	$aTaxonomies = ['listing_location', 'listing_cat', 'listing_tag'];
	$aTaxonomiesOptions = [];
	if (isset($_POST['run_export_terms_options']) && !empty($_POST['run_export_terms_options'])) {
		foreach ($aTaxonomies as $taxonomy) {
			$aTerms = get_terms([
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 1500
			]);

			if (empty($aTerms) || is_wp_error($aTerms)) {
				continue;
			}

			$aTaxonomiesOptions[$taxonomy] = [];
			foreach ($aTerms as $oTerm) {
				$options = get_option('_wiloke_cat_settings_' . $oTerm->term_id);
				$aTermOptions = json_decode($options, true);
				if (isset($aTermOptions['featured_image']) && !empty($aTermOptions['featured_image'])) {
					$aTermOptions['featured_image_url'] = wp_get_attachment_image_url($aTermOptions['featured_image'],
						'full');
				}
				$aTermOptions['name'] = $oTerm->name;
				$aTaxonomiesOptions[$taxonomy][$oTerm->slug] = $aTermOptions;
			}
		}
	}
	?>
    <div class="ui segment">
        <form action="<?php echo admin_url('admin.php?page=export-listgo-data'); ?>" method="POST" class="form ui">
            <div class="field">
                <label for="terms-options-data">Terms Options Data</label>
                <textarea name="terms_options_data" id="terms-options-data" cols="30"
                          rows="10"><?php echo base64_encode(json_encode($aTaxonomiesOptions)); ?></textarea>
            </div>

            <input type="hidden" name="run_export_terms_options" value="1">
            <div class="field">
                <button class="ui button green">Export Terms Options</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeExportListgoListingCustomFields()
{
	$aFields = [];
	if (isset($_POST['run_export_custom_fields']) && !empty($_POST['run_export_custom_fields'])) {
		$query = new WP_Query(
			[
				'post_type'      => 'listing',
				'posts_per_page' => absint($_POST['listings_per_export']),
				'paged'          => abs($_POST['listing_page'])
			]
		);

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$data = get_post_meta($query->post->ID, 'wiloke_listgo_my_custom_fields', true);
				if (empty($data)) {
					$aFields[$query->post->post_name] = '';
				} else {
					$aFields[$query->post->post_name] = $data;
				}
			}
		}
	}

	?>
    <div class="ui segment">
        <h1 class="ui heading dividing">Export Listing Custom Fields</h1>

        <form action="<?php echo admin_url('admin.php?page=export-listgo-custom-fields'); ?>" method="POST"
              class="form ui">
			<?php if (!empty($aFields)) : ?>
                <div class="field">
                    <label for="terms-options-data">Copy This Data and Parse To Wiloke Listgo Import -> Import Custom
                        Fields</label>
                    <textarea cols="30" rows="10"><?php echo base64_encode(serialize($aFields)); ?></textarea>
                </div>
			<?php endif; ?>

            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="listings_per_export" value="100">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="listing_page" value="1">
            </div>

            <input type="hidden" name="run_export_custom_fields" value="1">
            <div class="field">
                <button class="ui button green">Export Custom Fields</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeListGoGetGeocode($postID)
{
	global $wpdb;
	$tableName = $wpdb->prefix . AlterTableGeoPosition::$tblName;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $tableName WHERE postID=%d",
			$postID
		),
		ARRAY_A
	);
}

function wilokeExportListgoEventFields()
{
	$aFields = [];
	if (isset($_POST['run_export_event_fields']) && !empty($_POST['run_export_event_fields'])) {
		$query = new WP_Query(
			[
				'post_type'      => 'event',
				'posts_per_page' => absint($_POST['listings_per_export']),
				'paged'          => abs($_POST['listing_page'])
			]
		);

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$aData = get_post_meta($query->post->ID, 'event_settings', true);

				if (isset($aData['belongs_to'])) {
					$aFields[$query->post->post_name]['parent'] = get_post_field('post_name', $aData['belongs_to']);
				}
			}
		}
	}

	?>
    <div class="ui segment">
        <h1 class="ui heading dividing">Export Event Data</h1>

        <form action="<?php echo admin_url('admin.php?page=export-listgo-event-fields'); ?>" method="POST"
              class="form ui">
			<?php if (!empty($aFields)) : ?>
                <div class="field">
                    <label for="event-data">Copy This Data and Parse To Wiloke Listgo Import -> Import Custom
                        Fields</label>
                    <textarea id="event-data" cols="30"
                              rows="10"><?php echo base64_encode(serialize($aFields)); ?></textarea>
                </div>
			<?php endif; ?>

            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="listings_per_export" value="100">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="listing_page" value="1">
            </div>

            <input type="hidden" name="run_export_event_fields" value="1">
            <div class="field">
                <button class="ui button green">Export Custom Fields</button>
            </div>
        </form>
    </div>
	<?php
}

function wilokeExportListgoAuthor()
{
	$aFields = [];
	if (isset($_POST['run_export_post_authors']) && !empty($_POST['run_export_post_authors'])) {
		$query = new WP_Query(
			[
				'post_type'      => ['event', 'listing'],
				'posts_per_page' => absint($_POST['listings_per_export']),
				'paged'          => abs($_POST['listing_page']),
				'post_status'    => 'publish'
			]
		);

		$paged = isset($_POST['listing_page']) ? $_POST['listing_page'] : 1;
		$postsPerPage = isset($_POST['listings_per_export']) ? $_POST['listings_per_export'] : 100;

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				if (!empty($query->post->post_name)) {
					$authorId = get_post_field('post_author', $query->post->ID);
					$oAuthor = new WP_User($authorId);
					$aFields[] = [
						'author_slug' => $oAuthor->user_login,
						'post_title'  => $query->post->post_title,
						'post_slug'   => $query->post->post_name
					];
				}
			}
		}
	}

	?>
    <div class="ui segment">
        <h1 class="ui heading dividing">Export Post Authors</h1>

        <form action="<?php echo admin_url('admin.php?page=export-listgo-author'); ?>" method="POST"
              class="form ui">
			<?php if (!empty($aFields)) : ?>
                <div class="field">
                    <label >
                        Copy This Data and Parse To Wiloke Import Listgo Post Authors -> Import Data Field
                    </label>
                    <textarea cols="30" rows="10"><?php echo base64_encode(serialize($aFields)); ?></textarea>
                </div>
			<?php endif; ?>

            <div class="field">
                <label for="listings-per-export">Maximum Listings / Export</label>
                <input id="listings-per-export" type="text" name="listings_per_export" value="<?php echo isset($_POST['listings_per_export']) ?
	                $_POST['listings_per_export'] : 100; ?>">
            </div>
            <div class="field">
                <label for="terms-options-data">Current Page</label>
                <p>Assume we wish to export all listings from 1 - 30 (inclusive). You should enter Maximum Listings = 30
                    and Current Page. Current page = 2 means it will export start on 31.</p>
                <input id="listings-page" type="text" name="listing_page" value="<?php echo $paged; ?>">
            </div>

            <input type="hidden" name="run_export_post_authors" value="1">
            <div class="field">
                <button class="ui button green">Export Post Authors</button>
            </div>
        </form>
    </div>
	<?php
}


function wilokeExportListgoData()
{
	add_menu_page('Export Listgo Term Data', 'Export Listgo Term Data', 'manage_options', 'export-listgo-data',
		'wilokeExportListgoDataArea');
	add_menu_page('Export Listgo Business Hour', 'Export Listgo Business Hour', 'manage_options',
		'export-listgo-business-hours', 'wilokeExportListgoBusinessHours');
	add_menu_page('Export Listgo Listings', 'Export Listgo Listings', 'manage_options', 'export-listgo-custom-fields',
		'wilokeExportListgoListingCustomFields');
	add_menu_page('Export Listgo Events', 'Export Listgo Events', 'manage_options', 'export-listgo-event-fields',
		'wilokeExportListgoEventFields');
	add_menu_page('Export ListGo Author', 'Export Listgo Author', 'manage_options', 'export-listgo-author',
		'wilokeExportListgoAuthor');
}

function wilcityExportListgoScripts()
{
	wp_enqueue_style('semantic', plugin_dir_url(__FILE__) . './../vendor/semantic/semantic.css');
}

add_action('admin_menu', 'wilokeExportListgoData');
add_action('admin_enqueue_scripts', 'wilcityExportListgoScripts');
