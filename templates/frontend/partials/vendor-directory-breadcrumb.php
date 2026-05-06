<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Skip if SEO plugin emits its own breadcrumb (Yoast/Rank Math/etc.)
$probes = [ 'WPSEO_VERSION', 'RANK_MATH_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION', 'THE_SEO_FRAMEWORK_VERSION', 'SMARTCRAWL_VERSION' ];
foreach ($probes as $constant) {
    if (defined($constant)) {
        return;
    }
}
// Class probes — some plugins load class before constant (Yoast Free, SmartCrawl)
$class_probes = [
	'WPSEO_Frontend',
	'RankMath',
	'AIOSEO\\Plugin\\AIOSEO',
	'Smartcrawl_Init',
];
foreach ($class_probes as $cls) {
	if (class_exists($cls)) {
		return;
	}
}
if (apply_filters('mhm_rentiva_vendor_directory_seo_disable', false)) {
    return;
}
?>
<nav class="mhm-vendor-directory-breadcrumb" aria-label="<?php echo esc_attr__('Breadcrumb', 'mhm-rentiva'); ?>">
    <ol>
        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Home', 'mhm-rentiva'); ?></a></li>
        <li aria-current="page"><?php echo esc_html__('Vendors', 'mhm-rentiva'); ?></li>
    </ol>
</nav>
