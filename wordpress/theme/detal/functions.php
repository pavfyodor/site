<?php
if (!defined('ABSPATH')) { exit; }
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
    register_nav_menus(['primary' => 'Главное меню']);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('detal', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
});
// SKU search also retains WooCommerce's product visibility and stock queries.
add_filter('posts_search', function ($search, $query) {
    global $wpdb;
    if (!is_admin() && $query->is_search() && 'product' === $query->get('post_type') && $search) {
        $term = '%' . $wpdb->esc_like($query->get('s')) . '%';
        $sku = $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS sku_meta WHERE sku_meta.post_id = {$wpdb->posts}.ID AND sku_meta.meta_key = '_sku' AND sku_meta.meta_value LIKE %s)", $term);
        $search = ' AND ((' . preg_replace('/^\s*AND\s*/i', '', $search) . ') OR ' . $sku . ')';
        if (!is_user_logged_in()) { $search .= " AND ({$wpdb->posts}.post_password = '')"; }
    }
    return $search;
}, 10, 2);

function detal_icon($name) {
    $paths = [
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h10"/>',
        'user'=>'<circle cx="12" cy="7" r="3.5"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/>',
        'orders'=>'<rect x="5" y="7" width="14" height="14" rx="2"/><path d="M3 3h18v4H3zM9 12h6"/>',
        'cart'=>'<path d="M2 3h3l3 12h11l3-9H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'home'=>'<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3Z"/>',
    ];
    return '<svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
}
function detal_cart_link() {
    $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    return '<a class="detal-cart-link" href="' . esc_url(wc_get_cart_url()) . '">' . detal_icon('cart') . '<span>Корзина</span><b class="cart-count" aria-label="Товаров в корзине: ' . esc_attr($count) . '">' . esc_html($count) . '</b></a>';
}
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $fragments['a.detal-cart-link'] = detal_cart_link();
    return $fragments;
});
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if ($product && $product->get_sku()) { echo '<p class="product-sku">Артикул: ' . esc_html($product->get_sku()) . '</p>'; }
}, 6);
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('detal-ui', get_template_directory_uri() . '/assets/ui.js', [], wp_get_theme()->get('Version'), true);
});

/** Decorative category artwork never replaces individual product photographs. */
function detal_category_art_key($category) {
    $label = $category->name . ' ' . rawurldecode($category->slug);
    $rules = [
        'filters' => '/фильтр|filter/ui',
        'oils' => '/масл|жидкост|oil|fluid|lubric/ui',
        'brakes' => '/тормоз|brak/ui',
        'suspension' => '/подвес|рулев|амортиз|suspension|steering/ui',
        'electrics' => '/электр|зажиган|аккумулятор|electric|ignition|batter/ui',
        'accessories' => '/аксессуар|инструмент|accessor|tool/ui',
    ];
    foreach ($rules as $asset => $pattern) {
        if (preg_match($pattern, $label)) { return $asset; }
    }
    return '';
}
function detal_category_artwork($category) {
    $asset = detal_category_art_key($category);
    echo '<span class="detal-category-scene" aria-hidden="true"><span class="detal-category-podium"></span>';
    if ($asset) {
        echo '<img class="detal-category-art" src="' . esc_url(get_template_directory_uri() . '/assets/art/' . $asset . '.webp') . '" alt="" width="1254" height="1254" loading="lazy" decoding="async">';
    } else {
        woocommerce_subcategory_thumbnail($category);
    }
    echo '</span>';
}
remove_action('woocommerce_before_subcategory_title', 'woocommerce_subcategory_thumbnail', 10);
add_action('woocommerce_before_subcategory_title', 'detal_category_artwork', 10);
add_action('woocommerce_after_subcategory_title', function ($category) {
    $captions = [
        'filters' => 'Масляные, воздушные и салонные',
        'oils' => 'Для двигателя и заботы об авто',
        'brakes' => 'Диски, колодки и комплектующие',
        'suspension' => 'Амортизаторы, рычаги и опоры',
        'electrics' => 'Аккумуляторы, свечи и лампы',
        'accessories' => 'Инструменты и полезные мелочи',
    ];
    $caption = $captions[detal_category_art_key($category)] ?? 'Все товары этого раздела';
    echo '<span class="detal-category-subtitle">' . esc_html($caption) . '</span><span class="detal-category-cta">Смотреть товары ' . detal_icon('arrow') . '</span>';
});

/** Explicit admin action: creates only missing categories, never sample products. */
function detal_default_catalog_categories() {
    return [
        'Фильтры' => 'filters',
        'Масла и жидкости' => 'oils-fluids',
        'Тормозная система' => 'brakes',
        'Подвеска и рулевое' => 'suspension-steering',
        'Электрика' => 'electrics',
        'Аксессуары' => 'accessories',
    ];
}
add_action('admin_notices', function () {
    global $pagenow;
    if ($pagenow !== 'themes.php' || !current_user_can('manage_woocommerce') || !taxonomy_exists('product_cat')) { return; }
    $missing = false;
    foreach (detal_default_catalog_categories() as $name => $slug) {
        if (!term_exists($name, 'product_cat') && !term_exists($slug, 'product_cat')) { $missing = true; break; }
    }
    if ($missing) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=detal_create_catalog_categories'), 'detal_create_catalog_categories');
        echo '<div class="notice notice-info"><p><strong>Деталь: разделы каталога</strong></p><p>Добавьте шесть разделов с объёмными иллюстрациями. Будут созданы только недостающие категории, без тестовых товаров.</p><p><a class="button button-primary" href="' . esc_url($url) . '">Добавить разделы каталога</a></p></div>';
    } elseif (isset($_GET['detal_categories_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Разделы каталога готовы. Откройте главную страницу сайта.</p></div>';
    }
});
add_action('admin_post_detal_create_catalog_categories', function () {
    if (!current_user_can('manage_woocommerce') || !taxonomy_exists('product_cat')) { wp_die('Недостаточно прав.', '', ['response' => 403]); }
    check_admin_referer('detal_create_catalog_categories');
    foreach (detal_default_catalog_categories() as $name => $slug) {
        if (!term_exists($name, 'product_cat') && !term_exists($slug, 'product_cat')) {
            $result = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
            if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        }
    }
    wp_safe_redirect(admin_url('themes.php?detal_categories_created=1'));
    exit;
});
