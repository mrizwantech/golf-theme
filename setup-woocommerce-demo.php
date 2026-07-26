<?php
require 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

activate_plugin('woocommerce/woocommerce.php');
activate_plugin('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php');

if (class_exists('WC_Install')) {
    WC_Install::create_pages();
}

$term = term_exists('bay-rental', 'product_cat');
if (!$term) {
    $term = wp_insert_term('Bay Rental', 'product_cat', array('slug' => 'bay-rental'));
}
$term_id = is_array($term) ? $term['term_id'] : $term;

$bay_names = array(
    'Bay 1' => 'bay-1',
    'Bay 2' => 'bay-2',
    'Bay 3' => 'bay-3',
    'Bay 4' => 'bay-4',
);

$product_ids = array();

foreach ($bay_names as $name => $sku) {
    $existing = wc_get_products(array('sku' => $sku, 'limit' => 1, 'status' => 'any'));
    if (!empty($existing)) {
        $product = $existing[0];
        $product_id = $product->get_id();
        $product->set_regular_price(50);
        $product->set_price(50);
        $product->save();
    } else {
        $product = new WC_Product_Simple();
        $product->set_name($name . ' Rental');
        $product->set_regular_price(50);
        $product->set_price(50);
        $product->set_sku($sku);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_description('Hourly bay rental for ' . $name . ' at Tee Time Nexus.');
        $product->set_short_description('Hourly bay rental at $50.');
        $product_id = $product->save();
    }

    wp_set_object_terms($product_id, (int) $term_id, 'product_cat', false);
    $product_ids[] = $product_id;
}

$page = get_page_by_path('book-a-bay');
$content = '<h2>Reserve a Bay</h2><p>Choose a bay and continue to checkout.</p>[products ids="' . implode(',', $product_ids) . '"]';

if (!$page) {
    $page_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_title' => 'Book a Bay',
        'post_name' => 'book-a-bay',
        'post_status' => 'publish',
        'post_content' => $content,
    ));
} else {
    $page_id = $page->ID;
    wp_update_post(array(
        'ID' => $page_id,
        'post_title' => 'Book a Bay',
        'post_name' => 'book-a-bay',
        'post_status' => 'publish',
        'post_content' => $content,
    ));
}

update_option('woocommerce_currency', 'USD');
update_option('woocommerce_currency_pos', 'left');
update_option('woocommerce_price_num_decimals', 2);

printf("Created %d products and page ID %d\n", count($product_ids), $page_id);
