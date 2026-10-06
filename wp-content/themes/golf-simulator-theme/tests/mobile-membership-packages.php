<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__ . '/');
$posts = array();
$meta = array();
$titles = array();

function add_action($hook, $callback) {}
function golf_simulator_theme_get_default_membership_packages() {
    return array(
        'PAR' => array('title' => 'PAR', 'price' => '250', 'discount_price' => '199', 'billing' => '/Month', 'featured' => false, 'features' => array('24/7 Access'), 'thumbnail_id' => 10),
        'BIRDIE' => array('title' => 'BIRDIE', 'price' => '350', 'discount_price' => '', 'billing' => '/Month', 'featured' => true, 'features' => array('Bring 1 Guest Free')),
        'ALBATROSS' => array('title' => 'EAGLE', 'price' => '450', 'discount_price' => '399', 'billing' => '/Month', 'featured' => false, 'features' => array('Bring 2 Guests Free'), 'thumbnail_id' => 30),
    );
}
function get_posts($args) { global $posts; return $posts; }
function get_post_meta($id, $key, $single) { global $meta; return $meta[$id][$key] ?? ''; }
function get_the_title($id) { global $titles; return $titles[$id] ?? ''; }
function absint($value) { return abs((int) $value); }
function sanitize_title($value) { return strtolower($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_get_attachment_image_url($id, $size) { return $id === 99 ? false : 'https://example.test/' . $id . '.png'; }
function esc_url_raw($value) { return $value; }
function rest_ensure_response($value) { return $value; }
function check_packages($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

require dirname(__DIR__) . '/inc/mobile-membership-api.php';
$defaults = golf_simulator_theme_mobile_membership_packages();
check_packages(count($defaults) === 3, 'All tiers must remain in the response.');
check_packages($defaults[0]['thumbnail_url'] === 'https://example.test/10.png', 'Use default tier image when no post thumbnail exists.');
check_packages($defaults[1]['thumbnail_url'] === null, 'Unset image must remain optional.');
check_packages($defaults[1]['discount_price'] === null, 'Empty discount must not become zero.');
check_packages($defaults[2]['slug'] === 'albatross' && $defaults[2]['title'] === 'EAGLE', 'Checkout slug must remain distinct from renamed display title.');

$posts = array((object) array('ID' => 1), (object) array('ID' => 2), (object) array('ID' => 3));
$meta = array(
    1 => array('_membership_default_key' => 'PAR', '_membership_thumbnail_id' => 11),
    2 => array('_membership_default_key' => 'BIRDIE', '_membership_thumbnail_id' => 99),
    3 => array('_membership_thumbnail_id' => 33),
);
$titles = array(1 => 'Custom PAR display name', 2 => 'BIRDIE', 3 => 'EAGLE');
$overrides = golf_simulator_theme_mobile_membership_packages();
check_packages($overrides[0]['thumbnail_url'] === 'https://example.test/11.png', 'Package post thumbnail must override default-tier image.');
check_packages($overrides[1]['thumbnail_url'] === null, 'Missing attachment must not expose false as a URL.');
check_packages($overrides[2]['thumbnail_url'] === 'https://example.test/33.png', 'Legacy EAGLE title must match ALBATROSS tier.');
foreach ($overrides as $index => $package) {
    unset($package['thumbnail_url'], $defaults[$index]['thumbnail_url']);
    check_packages($package === $defaults[$index], 'Thumbnail changes must not change package pricing, features, or checkout slugs.');
}
echo "Membership packages passed: thumbnails, tier fallback, legacy EAGLE mapping, and unchanged package fields.\n";
