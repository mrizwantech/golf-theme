<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__ . '/');

$user_id = 12;
$is_admin = true;
$notices = array();
$hooks = array();
$meta = array(91 => array('_ttn_competition_product_for' => 42), 42 => array('_ttn_wc_product_id' => 91));

function is_user_logged_in() { return get_current_user_id() > 0; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function is_admin() { return $GLOBALS['is_admin']; }
function absint($value) { return abs((int) $value); }
function current_time($format) { return '2026-10-07 21:30:00'; }
function __($message, $domain = '') { return $message; }
function get_the_title($id) { return 'Test tournament'; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function wc_add_notice($message, $type) { $GLOBALS['notices'][] = $message; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { add_action($hook, $callback, $priority, $args); }
function WC() { return $GLOBALS['woocommerce']; }
function wc_load_cart() {}
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? false; }
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_Error {
    public $code;
    public function __construct($code, $message) { $this->code = $code; }
}

class Registration_Test_DB {
    public $prefix = 'wp_';
    public $rows = array();
    public function prepare($query, ...$args) { return $args[0]; }
    public function get_row($id) { return $this->rows[$id] ?? null; }
}

class WC_Product_Simple {
    public $id = 91;
    public $price = '10.00';
    public $status = 'private';
    public $visibility = 'hidden';
    public $regular_price = '10.00';
    public $virtual = true;
    public $individual = true;
    public function get_id() { return $this->id; }
    public function get_meta($key) { return get_post_meta($this->id, $key, true); }
    public function set_name($name) {}
    public function set_status($status) { $this->status = $status; }
    public function set_catalog_visibility($visibility) { $this->visibility = $visibility; }
    public function set_virtual($value) { $this->virtual = $value; }
    public function set_sold_individually($value) { $this->individual = $value; }
    public function set_price($price) { $this->price = $price; }
    public function set_regular_price($price) { $this->regular_price = $price; }
    public function update_meta_data($key, $value) { update_post_meta($this->id, $key, $value); }
    public function save() { $GLOBALS['products'][$this->id] = $this; return $this->id; }
}

class Registration_Test_Session {
    public $data = array();
    public $saved = array();
    public $cookie_set = false;
    public function get($key, $default = null) { return $this->data[$key] ?? $default; }
    public function set($key, $value) { $this->data[$key] = $value; }
    public function set_customer_session_cookie($value) { $this->cookie_set = $value; }
    public function save_data() { $this->saved = $this->data; }
}

class Registration_Test_Cart {
    public $items = array();
    public $fail_add = false;
    public $total = 0;
    public $get_cart_calls = 0;
    public function get_cart_contents() { return $this->items; }
    public function get_cart() { $this->get_cart_calls++; return $this->items; }
    public function empty_cart($clear_persistent = true) {
        $this->items = array();
        WC()->session->data = array();
    }
    public function add_to_cart($product_id, $quantity, $variation_id, $variations, $data) {
        if ($this->fail_add) {
            return false;
        }
        $product = wc_get_product($product_id);
        if (!TTN_Competitions_Registrations::allow_cart_product(false, $product)) {
            return false;
        }
        $this->items['registration'] = array_merge($data, array(
            'product_id' => $product_id, 'quantity' => $quantity, 'data' => clone $product,
        ));
        return 'registration';
    }
    public function calculate_totals() {
        TTN_Competitions_Registrations::set_cart_item_price($this);
        $this->total = 0;
        foreach ($this->items as $item) {
            $this->total += (float) $item['data']->price * $item['quantity'];
        }
    }
}

class WC_Cart_Session {
    private $cart;
    public function __construct($cart) { $this->cart = $cart; }
    public function set_session() {
        $items = $this->cart->get_cart_contents();
        foreach ($items as &$item) {
            unset($item['data']);
        }
        unset($item);
        WC()->session->set('cart', $items);
        WC()->session->set('cart_totals', array('total' => $this->cart->total));
    }
}

require dirname(__DIR__) . '/includes/class-competition-registrations.php';

$wpdb = new Registration_Test_DB();
$registration = (object) array(
    'id' => 7, 'competition_id' => 42, 'user_id' => 12,
    'status' => 'pending_payment', 'fee' => '75.00',
    'expires_at' => '2026-10-07 22:00:00', 'order_id' => null,
);
$wpdb->rows[7] = $registration;
$product = new WC_Product_Simple();
$products = array(91 => $product);
$woocommerce = (object) array('cart' => new Registration_Test_Cart(), 'session' => new Registration_Test_Session());
$item = array(
    'product_id' => 91, 'quantity' => 1,
    TTN_Competitions_Registrations::CART_KEY => array('registration_id' => 7, 'competition_id' => 42, 'fee' => 1),
);

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

TTN_Competitions_Registrations::register_hooks();
check(isset($hooks['woocommerce_cart_item_is_purchasable']), 'Session restoration filter is registered');

// WooCommerce checks purchasability before the item enters the in-memory cart.
WC()->session->set('cart', array('registration' => $item));
check(TTN_Competitions_Registrations::allow_cart_product(false, $product), 'Saved registration is purchasable before cart restoration');
check(WC()->cart->get_cart_calls === 0, 'Purchasability does not recursively load the cart');
WC()->session->set('cart', array());
check(TTN_Competitions_Registrations::allow_session_cart_item(false, 'registration', $item, $product), 'Persistent-cart item can be restored without an in-memory cart');
check(!TTN_Competitions_Registrations::allow_cart_product(true, $product), 'Unscoped competition product purchase stays blocked');
check(!TTN_Competitions_Registrations::validate_cart_add(true, 91, 1), 'Direct competition add-to-cart stays blocked');

foreach (array(
    'Other account' => array('user_id', 99),
    'Expired hold' => array('expires_at', '2026-10-07 21:00:00'),
    'Cancelled registration' => array('status', 'cancelled'),
    'Wrong competition' => array('competition_id', 99),
) as $name => $change) {
    $field = $change[0];
    $old = $registration->$field;
    $registration->$field = $change[1];
    check(!TTN_Competitions_Registrations::allow_session_cart_item(true, 'registration', $item, $product), $name . ' cannot restore payment');
    $registration->$field = $old;
}
$invalid_item = $item;
$invalid_item['quantity'] = 2;
check(!TTN_Competitions_Registrations::allow_session_cart_item(true, 'registration', $invalid_item, $product), 'Multiple-player quantity is rejected');
$user_id = 0;
check(!TTN_Competitions_Registrations::allow_session_cart_item(true, 'registration', $item, $product), 'Guest cannot restore registration');
$user_id = 12;
$ordinary_product = new WC_Product_Simple();
$ordinary_product->id = 200;
check(TTN_Competitions_Registrations::allow_cart_product(true, $ordinary_product), 'Ordinary booking/store products are unaffected');
check(!TTN_Competitions_Registrations::allow_session_cart_item(false, 'booking', array(), $ordinary_product), 'Ordinary products preserve WooCommerce validation');

$prepare = new ReflectionMethod(TTN_Competitions_Registrations::class, 'prepare_payment_cart');
$prepare->setAccessible(true);
WC()->cart->items['old-booking'] = array('product_id' => 200, 'quantity' => 1);
check($prepare->invoke(null, 7, 42, 75.0) === true, 'Registration payment cart is prepared');
check(count(WC()->cart->items) === 1 && isset(WC()->cart->items['registration']), 'Previous booking cart is replaced, not charged');
check(WC()->cart->total === 75.0, 'Admin-post cart total uses saved registration fee, not old product price');
check($product->status === 'publish' && $product->visibility === 'hidden', 'Existing private product is repaired to hidden/published booking convention');
check(WC()->session->cookie_set && isset(WC()->session->saved['cart']['registration']), 'Cart and session cookie are saved before redirect');
check(!isset(WC()->session->saved['cart']['registration']['data']), 'Session cart contains serializable item data, not the product object');

$saved_cart = WC()->session->saved['cart'];
WC()->cart = new Registration_Test_Cart();
WC()->session = new Registration_Test_Session();
WC()->session->data['cart'] = $saved_cart;
check(TTN_Competitions_Registrations::allow_cart_product(false, $product), 'Registration survives a second request with an initially empty cart');
foreach ($saved_cart as $key => $values) {
    if (TTN_Competitions_Registrations::allow_session_cart_item(false, $key, $values, $product)) {
        WC()->cart->items[$key] = array_merge($values, array('data' => clone $product));
    }
}
WC()->cart->calculate_totals();
check(count(WC()->cart->items) === 1 && WC()->cart->total === 75.0, 'Restored checkout retains one player and exact saved fee');

WC()->cart->fail_add = true;
check(is_wp_error($prepare->invoke(null, 7, 42, 75.0)), 'Failed cart addition returns an explicit checkout error');
WC()->session->set('cart', array());
check(!TTN_Competitions_Registrations::allow_cart_product(true, $product), 'Temporary product-add permission is reset after failure');

echo 'All registration cart regression checks passed.' . PHP_EOL;
