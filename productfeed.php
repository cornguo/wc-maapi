<?php
//define('SHORTINIT', true);
error_reporting(0);
define('WP_CACHE', false);
require('../../../wp-load.php');

$args = ['status' => ['published']];
$products = wc_get_products($args);

header('content-type: text/xml');
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<Products>';
foreach ($products as $product) {
    if ('' === $product->get_sku()) {
        continue;
    }
    $category = strip_tags(wc_get_product_category_list($product->get_id()));
    echo '<Product>';
    echo '<SKU>' . $product->get_sku() . '</SKU>';
    echo '<Name>' . $product->get_name() . '</Name>';
    echo '<Description><![CDATA[' . $product->get_description() . ']]></Description>';
    echo '<URL>' . $product->get_permalink() . '</URL>';
    echo '<Price>' . $product->get_price() . '</Price>';
    echo '<LargeImage><![CDATA[' . get_the_post_thumbnail_url($product->get_id(), 'large') . ']]></LargeImage>';
    echo '<Category>' . $category . '</Category>';
    echo '</Product>';
}
echo '</Products>';
exit;
