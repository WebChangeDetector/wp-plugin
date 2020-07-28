<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       wp-mike.com
 * @since      1.0.0
 *
 * @package    WebChangeDetector
 * @subpackage WebChangeDetector/admin/partials
 */


function webchangedetector_init()
{
    $postdata = $_POST;
    $get = $_GET;

    $wcd = new WebChangeDetector_Admin();

    // Actions without API Token needed
    if (isset($postdata['wcd_action'])) {
        switch ($postdata['wcd_action']) {
            case 'reset_api_token':
                $wcd->delete_website();
                delete_option(WP_OPTION_KEY_API_TOKEN);
                break;

            case 'save_api_token':

                $website = $wcd->create_group();

                if (empty($website)) {
                    echo '<div class="error notice"><p>The API Token is invalid. Please try again.</p></div>';
                    return false;
                }

                update_option(WP_OPTION_KEY_API_TOKEN, $postdata['api_token']);
                $wcd->sync_posts();

                break;
        }
    }

    $api_token = get_option(WP_OPTION_KEY_API_TOKEN);

    // Change api token option name from V1.0.7
    if (! $api_token) {
        $api_token = get_option('webchangedetector_api_key');
        if (! $api_token) {
            delete_option('webchangedetector_api_key');
            add_option(WP_OPTION_KEY_API_TOKEN, $api_token, '', false);
        }
    }

    // The account doesn't have an api_token
    if (! $api_token) {
        echo $wcd->get_no_account_page();
        return false;
    }

    $account_details = $wcd->account_details();

    // Check if account is activated and if the api key is authorized
    if ($account_details === 'activate account' || $account_details === 'unauthorized') {
        $wcd->show_activate_account($account_details);
        return false;
    }

    $website_details = $wcd->get_website_details();

    $group_id = ! empty($website_details['manual_detection_group_id']) ? $website_details['manual_detection_group_id'] : null;
    $monitoring_group_id = ! empty($website_details['auto_detection_group_id']) ? $website_details['auto_detection_group_id'] : null;

    $monitoring_group_settings = null;

    if ($monitoring_group_id) {
        $wcd->get_monitoring_settings($monitoring_group_id);
    }

    // Perform actions
    if (isset($postdata['wcd_action'])) {
        switch ($postdata['wcd_action']) {
            case 'take_screenshots':
                $results = $wcd->take_screenshot($group_id, $postdata['sc_type']);

                if ($results[0] === 'error') {
                    echo '<div class="error notice"><p>' . $results[1] . '</p></div>';
                }

                if ($results[0] === 'success') {
                    echo '<div class="updated notice"><p>' . $results[1] . '</p></div>';
                }
                break;

            case 'update_monitoring_settings':
                $wcd->update_monitoring_settings($postdata, $monitoring_group_id);
                break;

            case 'post_urls':
                // Get active posts from post data
                $active_posts = array();
                $count_selected = 0;
                foreach ($postdata as $key => $post_id) {
                    if (strpos($key, 'url_id') === 0) {
                        $active_posts[] = array(
                            'url_id' => $post_id,
                            'url' => get_permalink($postdata['post_id-'. $post_id]),
                            //'active' => 1,
                            'desktop' => $postdata['desktop-' . $post_id],
                            'mobile' => $postdata['mobile-' . $post_id]
                        );
                        if ($postdata['desktop-' . $post_id]) {
                            $count_selected++;
                        }

                        if ($postdata['mobile-' . $post_id]) {
                            $count_selected++;
                        }
                    }
                }

                // Check if there is a limit for selecting URLs
                if ($website_details['enable_limits'] &&
                    $website_details['url_limit_manual_detection'] < $count_selected &&
                    $website_details['manual_detection_group_id'] == $postdata['group_id']) {
                    echo '<div class="error notice"><p>The limit for selecting URLs is ' .
                        $website_details['url_limit_manual_detection'] . '.
                        You selected ' . $count_selected . ' URLs. The settings were not saved.</p></div>';
                } elseif ($website_details['enable_limits'] &&
                    isset($monitoring_group_settings) &&
                    $website_details['sc_limit'] < $count_selected * (24 / $monitoring_group_settings['interval_in_h']) * 30 &&
                    $website_details['auto_detection_group_id'] == $postdata['group_id']) {
                    echo '<div class="error notice"><p>The limit for auto change detection is ' .
                        $website_details['sc_limit'] . '. per month.
                            You selected ' . $count_selected * (24 / $monitoring_group_settings['interval_in_h']) * 30 . ' change detections. The settings were not saved.</p></div>';
                } else {
                    // Update API URLs
                    $wcd->update_urls($postdata['group_id'], $active_posts);
                    echo '<div class="updated notice"><p>Settings saved.</p></div>';
                }
                break;
        }

        // Get updated account and website data
        $account_details = $wcd->account_details();
        $website_details = $wcd->get_website_details();
    }

    // Start view
    echo '<div class="wrap">';
    echo '<div class="webchangedetector">';
    echo '<h1>WebChangeDetector</h1>';

    $wcd->tabs();

    echo '<div style="margin-top: 30px;"></div>';
    if (isset($get['tab'])) {
        $tab = $get['tab'];
    } else {
        $tab = 'dashboard';
    }

    // Account credits
    $comp_usage = $account_details['usage'];
    $limit = $account_details['sc_limit'];
    $available_compares = $account_details['available_compares'];

    if ($website_details['enable_limits']) {
        $account_details['usage'] = $comp_usage; // used in dashboard
        $account_details['plan']['sc_limit'] = $limit; // used in dashboard
    }

    // Renew date
    $renew_date = strtotime($account_details['renewal_at']);

    switch ($tab) {

        case'dashboard':
            $wcd->get_dashboard_view($account_details, $group_id, $monitoring_group_id);
            break;

        /********************
         * Change Detections
         ********************/

        case 'change-detections':
            echo '<h2>Latest Change Detections</h2>';

            $limit_days = null;
            if (isset($postdata['limit_days'])) {
                $limit_days = $postdata['limit_days'];
            }
            $group_type = null;
            if (isset($postdata['group_type'])) {
                $group_type = $postdata['group_type'];
            }

            $difference_only = null;
            if (isset($postdata['difference_only'])) {
                $difference_only = $postdata['difference_only'];
            }

            $compares = $wcd->get_compares([$group_id, $monitoring_group_id], $limit_days, $group_type, $difference_only);
            ?>
            <div class="action-container">
                <form method="post">
                    <select name="limit_days">
                        <option value="" <?= $limit_days == null ? 'selected' : '' ?>> Show all</option>
                        <option value="3" <?= $limit_days == 3 ? 'selected' : '' ?>>Last 3 days</option>
                        <option value="7" <?= $limit_days == 7 ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="14" <?= $limit_days == 14 ? 'selected' : '' ?>>Last 14 days</option>
                        <option value="30"<?= $limit_days == 30 ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="60"<?= $limit_days == 60 ? 'selected' : '' ?>>Last 60 days</option>
                    </select>

                    <select name="group_type" >
                        <option value="" <?= ! $group_type ? 'selected' : '' ?>>All Change Detections</option>
                        <option value="update" <?= $group_type == 'update' ? 'selected' : '' ?>>Only Update Change Detections</option>
                        <option value="auto" <?= $group_type == 'auto' ? 'selected' : '' ?>>Only Auto Change Detections</option>
                    </select>

                    <select name="difference_only" class="js-dropdown">
                        <option value="1" <?= $difference_only ? 'selected' : '' ?>>With difference</option>
                        <option value="0" <?= ! $difference_only ? 'selected' : '' ?>>All detections</option>
                    </select>

                    <input class="button" type="submit" value="Filter">
                </form>
                <?php

                $wcd->compare_view($compares);
                ?>
            </div>
            <div class="sidebar">
                <div class="account-box">
                    <?php include 'templates/account.php'; ?>
                </div>
                <div class="help-box">
                    <?php include 'templates/help-change-detection.php'; ?>
                </div>
            </div>
            <?php
            break;

        /********************
         * Update Change Detections
         ********************/

        case 'update-settings':
            if ($website_details['enable_limits'] && ! $website_details['allow_manual_detection']) {
                echo 'Settings for Update Change detections are disabled by your API Token.';
                break;
            }

            // Get amount selected Screenshots
            $groups_and_urls = $wcd->get_urls_of_group($group_id);
            ?>

            <h2>Select Update Change Detection URLs</h2>
            <div class="action-container">
                <h2>Do the magic</h2>
                <p>Currently selected:
                    <strong>
                        <?= $groups_and_urls['amount_selected_urls'] ?>
                        Change Detections
                    </strong>
                </p>
                <?php
                $wcd->get_url_settings($groups_and_urls);
                if ($website_details['enable_limits']) {
                    ?>
                    <p><strong>Creating Update Change Detections is disabled.</strong></p>
                    <?php
                } else {
                    ?>
                    <form action="<?= admin_url() ?>/admin.php?page=webchangedetector&tab=update-settings" method="post" style="float:left; margin-right: 10px;">
                    <input type="hidden" value="take_screenshots" name="wcd_action">
                    <input type="hidden" name="sc_type" value="pre">
                    <input type="submit" value="Create Reference Screenshots" <?php echo $available_compares > 0 ? '' : 'disabled'; ?> class="button" id="pre-button">
                    </form>

                    <form action="<?= admin_url() ?>/admin.php?page=webchangedetector&tab=update-settings" method="post" style="float:left;">
                    <input type="hidden" value="take_screenshots" name="wcd_action">
                    <input type="hidden" name="sc_type" value="post">
                    <input type="submit" value="Create Change Detections" <?php echo $available_compares > 0 ? '' : 'disabled'; ?> class="button" id="post-button">
                    </form>
                <?php
                } ?>
                </div>

                <div class="sidebar">
                    <div class="account-box">
                        <?php include 'templates/account.php'; ?>
                    </div>
                    <div class="help-box">
                        <?php include 'templates/help-update.php'; ?>
                    </div>
                </div>
                <div class="clear"></div>
            <?php
            break;

        /************************
         * Auto Change Detections
         * **********************/

        case 'auto-settings':
            if ($website_details['enable_limits'] && ! $website_details['allow_auto_detection']) {
                echo 'Settings for Update Change detections are disabled by your API Token.';
                break;
            }

            $groups_and_urls = $wcd->get_urls_of_group($monitoring_group_id);

            ?>
            <h2>Select Auto Change Detection URLs</h2>
            <div class="action-container">
                <?php $wcd->get_url_settings($groups_and_urls, true); ?>
                <h2>Settings for Auto Change Detection</h2>
                <p>
                    Currently selected:
                    <strong>
                        <?= $groups_and_urls['amount_selected_urls'] ?>
                        Change Detections
                    </strong>
                    <br>
                    The current settings require
                    <strong><?php
                    if (! empty($groups_and_urls['interval_in_h'])) {
                        echo $groups_and_urls['amount_selected_urls'] * (24 / $groups_and_urls['interval_in_h']) * 30;
                    }
                    ?></strong>
                    change detections per month.<br>
                </p>

                <form action="<?= admin_url() ?>/admin.php?page=webchangedetector&tab=auto-settings" method="post" onsubmit="return mmValidateForm()">
                <p>
                    <input type="hidden" name="wcd_action" value="update_monitoring_settings">
                    <input type="hidden" name="monitoring" value="1">
                    <input type="hidden" name="group_name" value="<?= $groups_and_urls['name'] ?>">

                <label for="enabled">Enabled</label>
                <select name="enabled" id="auto-enabled">
                    <option value="1" <?= isset($groups_and_urls['enabled']) && $groups_and_urls['enabled'] == '1' ? 'selected' : ''; ?>>
                        Yes
                    </option>
                    <option value="0" <?= isset($groups_and_urls['enabled']) && $groups_and_urls['enabled'] == '0' ? 'selected' : ''; ?>>
                        No
                    </option>
                </select>
                </p>
                <p>
                    <label for="hour_of_day" class="auto-setting">Hour of the day</label>
                    <select name="hour_of_day" class="auto-setting">
                        <?php
                        for ($i = 0; $i < 24; $i++) {
                            if (isset($groups_and_urls['hour_of_day']) && $groups_and_urls['hour_of_day'] == $i) {
                                $selected = 'selected';
                            } else {
                                $selected = '';
                            }
                            echo '<option value="' . $i . '" ' . $selected . '>' . $i . ':00</option>';
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label for="interval_in_h" class="auto-setting">Interval in hours</label>
                    <select name="interval_in_h" class="auto-setting">
                        <option value="1" <?= isset($groups_and_urls['interval_in_h']) && $groups_and_urls['interval_in_h'] == '1' ? 'selected' : ''; ?>>
                            Every 1 hour (720 Change Detections / URL / month)
                        </option>
                        <option value="3" <?= isset($groups_and_urls['interval_in_h']) && $groups_and_urls['interval_in_h'] == '3' ? 'selected' : ''; ?>>
                            Every 3 hours (240 Change Detections / URL / month)
                        </option>
                        <option value="6" <?= isset($groups_and_urls['interval_in_h']) && $groups_and_urls['interval_in_h'] == '6' ? 'selected' : ''; ?>>
                            Every 6 hours (120 Change Detections / URL / month)
                        </option>
                        <option value="12" <?= isset($groups_and_urls['interval_in_h']) && $groups_and_urls['interval_in_h'] == '12' ? 'selected' : ''; ?>>
                            Every 12 hours (60 Change Detections / URL / month)
                        </option>
                        <option value="24" <?= isset($groups_and_urls['interval_in_h']) && $groups_and_urls['interval_in_h'] == '24' ? 'selected' : ''; ?>>
                            Every 24 hours (30 Change Detections / URL / month)
                        </option>
                    </select>
                </p>
                <p>
                    <label for="alert_emails" class="auto-setting">
                        Alert email addresses
                    </label>
                    <input type="text" name="alert_emails" id="alert_emails" style="width: 500px;" class="auto-setting"
                           value="<?= isset($groups_and_urls['alert_emails']) ? implode(',', $groups_and_urls['alert_emails']) : '' ?>">
                    <br>
                    <label for="alert_emails" class="auto-setting">
                    (Separate more email addresses with ",")
                    </label>
                </p>
                    <input type="submit" class="button" value="Save" >
                </form>
            </div>
            <div class="sidebar">
                <div class="account-box">
                    <?php include 'templates/account.php'; ?>
                </div>
                <div class="help-box">
                    <?php include 'templates/help-auto.php'; ?>
                </div>
            </div>

            <?php
            break;

        /********************
         * Logs
         ********************/

        case 'logs':
            // Show queued urls
            $queues = $wcd->get_queue();
            $type_nice_name = array(
                'pre' => 'Reference Screenshot',
                'post' => 'Compare Screenshot',
                'auto' => 'Auto Detection',
                'compare' => 'Change Detection',
            );
            ?>
            <div class="action-container">
            <?php
                if (! empty($queues) && is_iterable($queues)) {
                    echo '<table class="queue">';
                    echo '<tr><th></th><th width="100%">Page & URL</th><th>Type</th><th>Status</th><th>Added</th><th>Last changed</th></tr>';
                    foreach ($queues as $queue) {
                        // should not be returned by the API anyway, but if the URL does not contain the current domain name, it's not the data to look at here
                        if (! str_contains($queue['url']['url'], $_SERVER['SERVER_NAME'])) {
                            continue;
                        }
                        $group_type = $queue['monitoring'] ? 'Auto Change Detection' : 'Update Change Detection';
                        echo '<tr class="queue-status-' . $queue['status'] . '">';
                        echo '<td>' . $wcd->get_device_icon($queue['device']) . '</td>';
                        echo '<td>
                                    <span class="html-title queue"> ' . $queue['url']['html_title'] . '</span><br>
                                    <span class="url queue">URL: '.$queue['url']['url'] . '</span><br>
                                    ' . $group_type . '
                              </td>';


                        echo '<td>' . $type_nice_name[$queue['sc_type']] . '</td>';
                        echo '<td>' . ucfirst($queue['status']) . '</td>';
                        echo '<td>' .  date('d/m/Y H:i:s', strtotime($queue['created_at'])) . '</td>';
                        echo '<td>' .  date('d/m/Y H:i:s', strtotime($queue['updated_at'])) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo 'Nothing to show yet.';
                }
                ?>
            </div>
            <div class="sidebar">
                <div class="account-box">
                    <?php include 'templates/account.php'; ?>
                </div>
                <div class="help-box">
                    <?php include 'templates/help-logs.php'; ?>
                </div>
            </div>
            <?php
            break;
        /********************
         * Settings
         ********************/

        case 'settings':

            if (! $api_token) {
                echo '<div class="error notice">
                <p>Please enter a valid API Token.</p>
            </div>';
            } elseif (! $website_details['enable_limits']) {
                echo '<h2>Your credits</h2>';
                echo 'Your current plan: <strong>' . $account_details['plan']['name'] . '</strong><br>';
                echo 'Next renew: ' . date('d/m/Y', $renew_date);
                echo '<p>Change detections in this period: ' . $limit . '<br>';
                echo 'Used change detections: ' . $comp_usage . '<br>';
                echo 'Available change detections in this period: ' . $available_compares . '</p>';

                echo $wcd->get_upgrade_options($account_details['plan_id']);
            }
            echo $wcd->get_api_token_form($api_token);
            break;

        /*****************
         * Show compare
         ****************/
        case 'show-compare':
            echo '<h1>The Change Detection Images</h1>';

            /* Why do we need an extra css file from the api?
             * function change_detection_css()
            {
                wp_enqueue_style('change-detection', mm_get_api_url() . '/css/change-detection.css');
            }
            add_action('admin_enqueue_scripts', 'change_detection_css');*/

            $public_link = mm_get_app_url() . 'show-change-detection/?token=' . $_GET['token'];
            echo '<p>Public link: <a href="' . $public_link . '" target="_blank">' . $public_link . '</a></p>';

            $back_button = '<a href="' . $_SERVER['HTTP_REFERER'] . '" class="button" style="margin: 10px 0;">Back</a><br>';
            echo $back_button;
            echo $wcd->get_comparison_partial($_GET['token']);
            echo '<div class="clear"></div>';
            echo $back_button;

    }
    echo '</div>'; // closing from div webchangedetector
    echo '</div>'; // closing wrap
}
