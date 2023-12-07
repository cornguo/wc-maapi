<?php
/**
* Plugin Name: WooCommerce Market America API Integration
* Plugin URI:
* Description: Integrates Market America API into WooCommerce
* Version: 0.1
* Author: CornGuo
* Author URI: https://github.com/cornguo
* Developer: CornGuo
* Developer URI: https://github.com/cornguo
* Text Domain: wc-maapi
* Domain Path: /languages
*/

require 'class-market-america-api.php';

// custom post type
function wc_maapi_register_maapi_order() {
    register_post_type('maapi_order', [
        'labels'       => [
            'name'          => __('MA Orders', 'textdomain'),
            'singular_name' => __('MA Order', 'textdomain'),
        ],
        'public'       => false,
        'internal'     => true,
        'has_archive'  => false,
        'show_ui'      => true,
        'supports'     => ['title'],
        // disables post create/delete functions
        'capabilities' => [
            'edit_post'              => true,
            'read_post'              => true,
            'delete_post'            => false, // ***
            'edit_posts'             => true,
            'edit_others_posts'      => true,
            'publish_posts'          => false,
            'read_private_posts'     => true,
            'read'                   => true,
            'delete_posts'           => false, // ***
            'delete_private_posts'   => false,
            'delete_published_posts' => false, // ***
            'delete_others_posts'    => false, // ***
            'edit_private_posts'     => true,
            'edit_published_posts'   => true,
            'create_posts'           => false,
        ],
    ]);
}
add_action('init', 'wc_maapi_register_maapi_order');


function maapi_order_data_meta_box($maapiOrder) {
    $orderId = get_post_meta($maapiOrder->ID, 'ma_order_id', true);
    $maConfig = wc_maapi_get_ma_config($orderId);
    $maDataPurchase = wc_maapi_get_ma_data($orderId, 'purchase');
    $maDataRefund = wc_maapi_get_ma_data($orderId, 'refund');

    echo '<h4>AFFLIATE INFO</h4>';
    echo '<blockquote>';
    echo 'RID: ' . $maConfig['rid'] . '<br />';
    echo 'Click ID: ' . $maConfig['clickId'];
    echo '</blockquote>';

    if (null !== $maDataPurchase) {
        echo '<h4>PURCHASE</h4>';
        echo '<blockquote>';
        echo 'Date: ' . $maDataPurchase['date'] . '<br />';
        echo 'Order ID: ' . $maDataPurchase['orderId'] . '<br />';
        echo 'Order Amount: ' . $maDataPurchase['orderAmount'];
        echo '</blockquote>';
    }

    if (null !== $maDataRefund) {
        echo '<h4>REFUND</h4>';
        echo '<blockquote>';
        echo 'Date: ' . $maDataRefund['date'] . '<br />';
        echo 'Order ID: ' . $maDataRefund['orderId'] . '<br />';
        echo 'Order Amount: ' . $maDataRefund['orderAmount'];
        echo '</blockquote>';
    }
}

function maapi_api_result_meta_box($maapiOrder) {
    $results = json_decode($maapiOrder->post_content, true);
    if (null === $results) {
        return;
    }
    $date = new DateTime('2000-01-01', wp_timezone());
    foreach ($results as $result) {
        $date->setTimestamp($result['timestamp']);
        $timeStr = $date->format('Y-m-d H:i:s (P)');
        echo '<h4>' . $result['type'] . ' @ ' . $timeStr . '</h4>';
        echo '<blockquote>';
        if (isset($result['error'])) {
            echo 'Error [' . $result['error'] . ']: ' . $result['message'];
            if (isset($result['errors'])) {
                echo '<ul>';
                foreach ($result['errors'] as $error) {
                    echo '<li>' . $error['publicMessage'] . '</li>';
                }
                echo '</ul>';
            }
        } else {
            if (isset($result['response']) && isset($result['response']['Conversion'])) {
                $response = $result['response']['Conversion'];
                echo 'Date: ' . $response['session_datetime'] . '<br />';
                echo 'ID: ' . $response['id'] . '<br />';
                echo 'RID: ' . $response['affiliate_info1'] . '<br />';
                echo 'Click ID: ' . $response['ad_id'] . '<br />';
                echo 'Order ID: ' . $response['advertiser_info'] . '<br />';
                echo 'Sale amount: ' . $response['sale_amount'] . '<br />';
                echo 'Payout: ' . $response['payout'] . '<br />';
                echo 'Is adjustment: ' . $response['is_adjustment'] . '<br />';
                echo 'Status: ' . $response['status'] . '<br />';
                echo 'Event ID: ' . $response['tune_event_id'];
            } else {
                echo '<pre>';
                print_r($result);
                echo '</pre>';
            }
        }
        echo '</blockquote>';
    }
}

function wc_maapi_add_meta_box() {
    add_meta_box('maapi_order_data_meta_box', 'MA Order Data', 'maapi_order_data_meta_box', 'maapi_order');
    add_meta_box('maapi_api_result_meta_box', 'MA API Result', 'maapi_api_result_meta_box', 'maapi_order');
}
add_action('add_meta_boxes_maapi_order', 'wc_maapi_add_meta_box');

function wc_maapi_disable_autosave() {
    if ('maapi_order' == get_post_type()) {
        wp_dequeue_script('autosave');
    }
}
add_action('admin_enqueue_scripts', 'wc_maapi_disable_autosave');

function wc_maapi_post_submitbox_start($post) {
    if ('maapi_order' === $post->post_type) {
        echo '<div id="send-conversion-action" style="float:right;">'
            . '&nbsp;'
            . '<input type="submit" name="send_conversion" class="button button-large" value="purchase"></input>'
            . '&nbsp;'
            . '<input type="submit" name="send_conversion" class="button button-large" value="refund"></input>'
            . '</div>';
    }
}
add_action('post_submitbox_start', 'wc_maapi_post_submitbox_start');

function wc_maapi_manage_add_columns($columns) {
    return array_merge($columns, ['ma_sent_purchase' => 'Purchase', 'ma_sent_refund' => 'Refund']);
}
add_filter('manage_maapi_order_posts_columns', 'wc_maapi_manage_add_columns');

function wc_maapi_manage_display_columns($column_key, $post_id) {
    if (in_array($column_key, ['ma_sent_purchase', 'ma_sent_refund'])) {
        $time = get_post_meta($post_id, $column_key, true);
        if ($time) {
            $date = new DateTime('2000-01-01', wp_timezone());
            $date->setTimestamp($time);
            $timeStr = $date->format('Y-m-d H:i:s (P)');
            echo 'Sent at [' . $timeStr . ']';
        }
    }
}
add_action('manage_maapi_order_posts_custom_column', 'wc_maapi_manage_display_columns', 10, 2);


// settings
function wc_maapi_register_settings() {
    register_setting('wc_maapi_options', 'wc_maapi_options');
    add_settings_section('api_settings', 'API Settings', '__return_false', 'wc_maapi_');

    $fields = [
        'offer_id'        => 'Offer ID',
        'advertiser_id'   => 'Advertiser ID',
        'commission_rate' => 'Commission Rate',
        'notice_text'     => 'Notice Text',
    ];

    foreach ($fields as $field => $fieldName) {
        add_settings_field('wc_maapi_' . $field, $fieldName, 'wc_maapi_field', 'wc_maapi_', 'api_settings', $field);
    }
}
add_action('admin_init', 'wc_maapi_register_settings');

function wc_maapi_field($field) {
    $options = get_option('wc_maapi_options');
    if ('notice_text' === $field) {
        echo '<textarea id="wc_maapi_' . $field . '"'
            . ' name="wc_maapi_options[' . $field . ']"'
            . ' type="text" rows="6" cols="60">'
            . $options[$field]
            . '</textarea>';
    } else {
        echo '<input id="wc_maapi_' . $field . '"'
            . ' name="wc_maapi_options[' . $field . ']"'
            . ' type="text"'
            . ' value="' . esc_attr( $options[$field] ) . '" />';
    }
}

function wc_maapi_admin_settings() {
    ?>
    <h2>Market America Integration Settings</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields('wc_maapi_options');
        do_settings_sections('wc_maapi_'); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
    <?php
}

function wc_maapi_add_admin_settings() {
    add_submenu_page(
        'edit.php?post_type=maapi_order',
        __('Market America Integration Settings', 'textdomain'),
        __('MA Settings', 'textdomain'),
        'manage_options',
        'maapi-settings',
        'wc_maapi_admin_settings'
    );
}
add_action('admin_menu', 'wc_maapi_add_admin_settings');


// get rid & clickId on page view
function wc_maapi_init() {
    if (is_user_logged_in() || is_admin()) {
        return;
    }
    if (isset(WC()->session)) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
            wc_maapi_get_rid_click_id();
        }
    }
}
add_action('woocommerce_init', 'wc_maapi_init');

function wc_maapi_set_cookie_js($key, $val, $time=7200) {
    echo '<script type="text/javascript">'
        . 'document.cookie="' . $key . '=' . $val . '; max-age=' . $time . '; path=/";'
        . '</script>' . PHP_EOL;
}

function wc_maapi_get_rid_click_id() {
    if (is_admin()) {
        return;
    }

    if (isset(WC()->session) && WC()->session->has_session()) {
        if (isset($_GET['RID'])) {
            WC()->session->ma_rid = $_GET['RID'];
            add_action('wp_footer', function () {
                wc_maapi_set_cookie_js('ma_rid', $_GET['RID']);
            });
        }
        if (isset($_GET['Click_ID'])) {
            WC()->session->ma_click_id = $_GET['Click_ID'];
            add_action('wp_footer', function () {
                wc_maapi_set_cookie_js('ma_click_id', $_GET['Click_ID']);
            });
        }
    }
}
add_action('init', 'wc_maapi_get_rid_click_id');

function wc_maapi_thankyou() {
    add_action('wp_footer', function() {
        wc_maapi_set_cookie_js('ma_rid', '', -999);
        wc_maapi_set_cookie_js('ma_click_id', '', -999);
    });
}
add_action('woocommerce_thankyou', 'wc_maapi_thankyou');

// hook into woocommerce functions
function wc_maapi_checkout_update_order_meta($orderId) {
    if (null !== WC()->session->ma_rid && null !== WC()->session->ma_click_id) {
        update_post_meta($orderId, 'ma_rid', WC()->session->ma_rid);
        update_post_meta($orderId, 'ma_click_id', WC()->session->ma_click_id);
        // clear session data
        WC()->session->ma_rid = null;
        WC()->session->ma_click_id = null;
    } elseif (isset($_COOKIE['ma_rid']) && isset($_COOKIE['ma_click_id'])) {
        update_post_meta($orderId, 'ma_rid', $_COOKIE['ma_rid']);
        update_post_meta($orderId, 'ma_click_id', $_COOKIE['ma_click_id']);
    }
}
add_action('woocommerce_checkout_update_order_meta', 'wc_maapi_checkout_update_order_meta');

function wc_maapi_order_status_processing($orderId) {
    $order = wc_get_order($orderId);
    $amount = $order->get_total() - $order->get_total_tax() - $order->get_total_shipping();

    $maData = [
        'date'        => $order->date_modified->date('Y-m-d'),
        'orderId'     => $orderId,
        'orderAmount' => $amount,
    ];

    // check if maapi_order entry exists before create
    if (null === wc_maapi_get_maapi_order($orderId)) {
        $maapiOrderArr = [
            'post_type'      => 'maapi_order',
            'post_title'     => 'MA Order #' . $orderId,
            'post_status'    => 'publish',
            'post_content'   => '[]',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ];

        // check if rid & clickId is set
        $maConfig = wc_maapi_get_ma_config($orderId);
        if (!empty($maConfig['rid']) && !empty($maConfig['clickId'])) {
            // create maapi_order item
            $maapiOrderId = wp_insert_post($maapiOrderArr);
            // set metadata for the item
            update_post_meta($maapiOrderId, 'ma_order_id', $orderId);
            update_post_meta($maapiOrderId, 'ma_data_purchase', wp_slash(json_encode($maData, JSON_UNESCAPED_UNICODE)));
        }
    }
    wc_maapi_send_conversion($orderId, 'purchase');
}
add_action('woocommerce_order_status_processing', 'wc_maapi_order_status_processing');

function wc_maapi_order_status_refunded($orderId) {
    $order = wc_get_order($orderId);
    $amount = $order->get_total() - $order->get_total_tax() - $order->get_total_shipping();

    $maData = [
        'date'        => $order->date_modified->date('Y-m-d'),
        'orderId'     => $orderId,
        'orderAmount' => -1 * $amount,
    ];

    $maapiOrder = wc_maapi_get_maapi_order($orderId);

    if (null !== $maapiOrder) {
        update_post_meta($maapiOrder->ID, 'ma_data_refund', wp_slash(json_encode($maData, JSON_UNESCAPED_UNICODE)));
    }
    wc_maapi_send_conversion($orderId, 'refund');
}
add_action('woocommerce_order_status_refunded', 'wc_maapi_order_status_refunded');


// calling market america api
function wc_maapi_send_conversion($orderId, $convType = '') {
    // prevent recursives
    remove_action('edit_post_maapi_order', 'wc_maapi_edit_post_maapi_order');
    $sentMetaKey = 'ma_sent_' . $convType;
    $maapiOrder = wc_maapi_get_maapi_order($orderId);
    // check if conversion had been sent
    $sentStatus = intval(get_post_meta($maapiOrder->ID, $sentMetaKey, true));

    if ($sentStatus > 0) {
        return null;
    }

    $ret = wc_maapi_call_maapi($orderId, $convType);
    if ($ret) {
        $content = json_decode($maapiOrder->post_content, true);
        if (!is_array($content)) {
            $content = [];
        }
        $content[] = $ret;
        wp_update_post([
            'ID'           => $maapiOrder->ID,
            'post_content' => wp_slash(json_encode($content, JSON_UNESCAPED_UNICODE)),
        ]);
        // set conversion sent
        if (!isset($ret['error'])) {
            update_post_meta($maapiOrder->ID, $sentMetaKey, time());
        }
    }
    return $ret;
}

function wc_maapi_call_maapi($orderId, $convType = '') {
    $now = time();
    $maConfig = wc_maapi_get_ma_config($orderId);
    $maData = wc_maapi_get_ma_data($orderId, $convType);
    if (!in_array($convType, ['purchase', 'refund'])) {
        return [
            'timestamp' => $now,
            'type'      => $convType,
            'error'     => 'wrong_conv_type',
        ];
    }
    if (null === $maConfig['rid'] || null === $maConfig['clickId']) {
        return [
            'timestamp' => $now,
            'type'      => $convType,
            'error'     => 'no_rid_clickid',
        ];
    }
    if (null !== $maData) {
        try {
            $result = '';
            $maapi = new MarketAmericaApi($maConfig);
            if ('purchase' === $convType) {
                $result = $maapi->trackConversion($maData);
            }
            if ('refund' === $convType) {
                $result = $maapi->trackRefund($maData);
            }
            $result = json_decode($result, true);
            $result['timestamp'] = $now;
            if (-1 === $result['response']['status']) {
                return [
                    'timestamp' => $now,
                    'type'      => $convType,
                    'error'     => 'api_error',
                    'message'   => $result['response']['errorMessage'],
                    'errors'    => $result['response']['errors'],
                ];
            } else {
                return [
                    'timestamp' => $now,
                    'type'      => $convType,
                    'response'  => $result['response']['data'],
                ];
            }
            return $result;
        } catch (Exception $e) {
            return [
                'timestamp' => $now,
                'type'      => $convType,
                'error'     => 'api_param_error',
                'message'   => $e->getMessage(),
            ];
        }
    }
    return [
        'timestamp' => $now,
        'type'      => $convType,
        'error'     => 'no_order_or_refund_data',
    ];
}


// meta handling functions
function wc_maapi_get_ma_config($orderId) {
    $order = wc_get_order($orderId);
    $options = get_option('wc_maapi_options');
    $rid = $order->get_meta('ma_rid');
    $clickId = $order->get_meta('ma_click_id');
    if (null !== $rid && null !== $clickId) {
        return [
            'offerId'        => $options['offer_id'],
            'advertiserId'   => $options['advertiser_id'],
            'commissionRate' => $options['commission_rate'],
            'rid'            => $rid,
            'clickId'        => $clickId,
        ];
    }

    return null;
}

function wc_maapi_get_maapi_order($orderId) {
    $args = [
        'post_type'   => 'maapi_order',
        'post_status' => 'publish',
        'meta_key'    => 'ma_order_id',
        'meta_value'  => $orderId,
    ];
    $maapiOrders = get_posts($args);

    if (count($maapiOrders) > 0) {
        return $maapiOrders[0];
    }

    return null;
}

function wc_maapi_get_ma_data($orderId, $convType = '') {
    // check if maapi_order exists
    $maapiOrder = wc_maapi_get_maapi_order($orderId);
    if (null !== $maapiOrder) {
        if ('purchase' === $convType) {
            $ret = get_post_meta($maapiOrder->ID, 'ma_data_purchase', true);
        }
        if ('refund' === $convType) {
            $ret = get_post_meta($maapiOrder->ID, 'ma_data_refund', true);
        }
        if (null !== $ret) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    return null;
}

function wc_maapi_edit_post_maapi_order($maapiOrderId) {
    $maapiOrder = get_post($maapiOrderId);

    $orderId = get_post_meta($maapiOrder->ID, 'ma_order_id', true);
    $maConfig = wc_maapi_get_ma_config($orderId);
    $maDataPurchase = wc_maapi_get_ma_data($orderId, 'purchase');
    $maDataRefund = wc_maapi_get_ma_data($orderId, 'refund');

    if (isset($_POST['send_conversion'])) {
        if ('purchase' === $_POST['send_conversion']) {
            wc_maapi_send_conversion($orderId, 'purchase');
        }

        if ('refund' === $_POST['send_conversion']) {
            wc_maapi_send_conversion($orderId, 'refund');
        }
/*
    echo '<pre>';
    echo 'CONFIG' . PHP_EOL;
    print_r($maConfig);
    echo 'PURCHASE' . PHP_EOL;
        $maData = wc_maapi_get_ma_data($orderId, 'purchase');
        print_r($maData);
//        $ret = wc_maapi_send_conversion($orderId, 'purchase');
    echo 'REFUND' . PHP_EOL;
        $maData = wc_maapi_get_ma_data($orderId, 'refund');
        print_r($maData);
//        $ret = wc_maapi_send_conversion($orderId, 'refund');
    echo '</pre>';
*/
    }
}
add_action('edit_post_maapi_order', 'wc_maapi_edit_post_maapi_order');


function wc_maapi_register_productfeed() {
    add_rewrite_rule(
        '^wc-maapi\/productfeed\.xml$',
        'wp-content/plugins/wc-maapi/productfeed.php',
        'top'
    );
}
add_action('init', 'wc_maapi_register_productfeed');


function wc_maapi_print_warning() {
    $options = get_option('wc_maapi_options');
    $text = str_replace(PHP_EOL, '<br />', strip_tags($options['notice_text']));
    echo '<div style="border:1px solid #333; background-color:#FFC; margin:0.5em 0; padding: 0.5em;">';
    echo $text;
    echo '</div>';
}
add_action('woocommerce_before_checkout_form', 'wc_maapi_print_warning');
add_action('woocommerce_thankyou', 'wc_maapi_print_warning');
