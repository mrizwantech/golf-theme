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

$required = array(
    '@media (max-width: 700px)',
    '.wp-block-woocommerce-checkout .wc-block-components-order-summary-item',
    'grid-template-columns: 48px minmax(0, 1fr)',
    'overflow-wrap: anywhere',
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
