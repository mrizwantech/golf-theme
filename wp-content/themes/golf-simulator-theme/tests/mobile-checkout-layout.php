<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

$css = file_get_contents(__DIR__ . '/../style.css');
$start = strpos($css, '/* On phones, show the line total once');
if ($start === false) {
    throw new RuntimeException('Missing mobile checkout layout rules.');
}
$mobile = substr($css, $start);

$app_menu_rule = '.ttn-app-checkout .header-menu-toggle';
$app_menu_position = strpos($css, $app_menu_rule);
$mobile_rules_position = strpos($css, '/* On phones, show the line total once');
if ($app_menu_position === false || $app_menu_position > $mobile_rules_position
    || !preg_match('/\.ttn-app-checkout \.header-menu-toggle\s*\{\s*display:\s*none\s*!important;\s*\}/', $css)) {
    throw new RuntimeException('Hide the menu on app checkout at every viewport size.');
}

$theme_functions = file_get_contents(__DIR__ . '/../functions.php');
if (strpos($theme_functions, "isset(\$_GET['ttn_app_checkout'])") === false
    || strpos($theme_functions, "add_query_arg('ttn_app_checkout', '1', \$url)") === false) {
    throw new RuntimeException('Identify app checkout from its explicit URL marker, not viewport size.');
}

$required = array(
    '@media (max-width: 700px)',
    '.wp-block-woocommerce-checkout .wc-block-components-order-summary-item',
    'grid-template-columns: 48px minmax(0, 1fr)',
    'max-width: 100%',
    'overflow-wrap: anywhere',
    'justify-self: start',
    'font-size: 1rem',
);
foreach ($required as $rule) {
    if (strpos($mobile, $rule) === false) {
        throw new RuntimeException('Missing checkout layout contract: ' . $rule);
    }
}

if (!preg_match('/\.wp-block-woocommerce-checkout \.wc-block-components-order-summary-item__description \.wc-block-cart-item__prices\s*\{\s*display: none;\s*\}/', $mobile)) {
    throw new RuntimeException('Hide only the duplicate unit-price row within mobile checkout.');
}
if (preg_match('/(?:overflow(?:-x)?:\s*hidden|\.wc-block-components-order-summary-item__total-price\s*\{[^}]*display:\s*none)/s', $mobile)) {
    throw new RuntimeException('Checkout must reflow, not clip content or hide the line total.');
}

echo "Mobile checkout layout contracts passed.\n";
