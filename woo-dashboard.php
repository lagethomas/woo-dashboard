<?php
/*
  Plugin Name: Dashboard Loja Virtual - WooCommerce
  Description: Dashboard moderno com gráfico de vendas reais e últimas vendas para WooCommerce.
  Version: 1.0.1
  TAG: true
  Author: Thomas Marcelino
  Author URI: https://wpmasters.com.br
  License: GPL2
  Text Domain: lojavirtual-dashboard
*/

if (!defined('ABSPATH')) {
    exit;
}

// Constante única e com time() para evitar cache de CSS/JS durante o desenvolvimento.
define('LV_ASSETS_VERSION', time());

define('LV_DIR', plugin_dir_path(__FILE__));
define('LV_URL', plugin_dir_url(__FILE__));
define('LV_SLUG_DASH', 'minha-loja-dashboard');
define('LV_SLUG_CFG', 'minha-loja-configuracoes');

/**
 * Retorna a versão do plugin a partir do cabeçalho do arquivo principal.
 *
 * Esta função garante que o número da versão seja mantido em um único local (o cabeçalho).
 *
 * @return string Versão do plugin.
 */
function lv_get_plugin_version() {
    // É necessário incluir 'plugin.php' para usar get_plugin_data
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    // Usamos o nome real do arquivo principal para obter os dados.
    $plugin_data = get_plugin_data( LV_DIR . 'woo-dashboard.php' );
    return ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.0';
}


/**
 * Retorna as opções padrão do plugin.
 * @return array
 */
function lv_default_options()
{
    return array(
        'title'   => 'Dashboard da Minha Loja',
        'font'    => 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
        'scale'   => '100%',
        'dark_mode' => 0,
        'chart_default_view' => 'value',
        'monthly_goal' => 10000,
        'colors'  => array('primary' => '#3b82f6', 'secondary' => '#f3f4f6', 'text' => '#1f2937', 'link' => '#6b7280', 'title' => '#111827', 'button' => '#3b82f6', 'button_text' => '#ffffff', 'border' => '#e5e7eb', 'bars' => '#3b82f6', 'icons' => '#9ca3af'),
        'cards' => array(
            'monthly_goal' => 1, 'total_sales'  => 1, 'total_orders' => 1, 'avg_ticket'   => 1,
            'new_customers'=> 1, 'pending'      => 1, 'on_hold'      => 1, 'low_stock'    => 1,
            'out_stock'    => 1, 'last_order'   => 1, 'customers'    => 1, 'chart'        => 1,
            'last_sales'   => 1, 'top_products' => 1, 'payment_methods' => 1, 'top_customers' => 1,
        ),
        'order' => array(
            'monthly_goal' => 1, 'total_sales'  => 2, 'total_orders' => 3, 'avg_ticket'   => 4,
            'new_customers'=> 5, 'pending'      => 6, 'on_hold'      => 7, 'low_stock'    => 8,
            'last_order'   => 9, 'customers'    => 10,
        ),
        'months'  => 12,
        'statuses_for_metrics' => array('wc-processing', 'wc-completed'),
    );
}

/**
 * Obtém as opções salvas, mesclando com as padrões.
 *
 * @return array Opções mescladas.
 */
function lv_get_options()
{
    $o = get_option('lv_dash_settings');
    if (!is_array($o)) {
        $o = array();
    }
    $def = lv_default_options();
    $o['colors'] = isset($o['colors']) && is_array($o['colors']) ? array_merge($def['colors'], $o['colors']) : $def['colors'];
    $o['cards']  = isset($o['cards'])  && is_array($o['cards'])  ? array_merge($def['cards'],  $o['cards'])  : $def['cards'];
    $o['order']  = isset($o['order'])  && is_array($o['order'])  ? array_merge($def['order'],  $o['order'])  : $def['order'];
    return array_merge($def, $o);
}

/* ===== Includes ===== */
$cfg_file = LV_DIR . 'includes/lojavirtual-configuracoes.php';
if (file_exists($cfg_file)) {
    require_once $cfg_file;
}

/* ===== Hooks de Ação e Filtros (Menus, Assets, etc.) ===== */

/**
 * Adiciona a página principal do dashboard e o submenu de configurações.
 *
 * @action admin_menu
 */
add_action('admin_menu', function () {
    add_menu_page(__('Woo Dashboard', 'lojavirtual-dashboard'), __('Woo Dashboard', 'lojavirtual-dashboard'), 'manage_options', LV_SLUG_DASH, 'lv_dashboard_screen', 'dashicons-chart-area', 3);
    if (function_exists('lv_settings_screen')) {
        add_submenu_page(LV_SLUG_DASH, __('Configurações', 'lojavirtual-dashboard'), __('Configurações', 'lojavirtual-dashboard'), 'manage_options', LV_SLUG_CFG, 'lv_settings_screen');
    }
});

/**
 * Enqueue de scripts e estilos para as páginas do plugin.
 *
 * @param string $hook O slug da página de administração atual.
 * @action admin_enqueue_scripts
 */
add_action('admin_enqueue_scripts', function ($hook) {
    $allowed = array('toplevel_page_' . LV_SLUG_DASH, LV_SLUG_DASH . '_page_' . LV_SLUG_CFG, 'woocommerce_page_minha-loja-dashboard');
    if (!in_array($hook, $allowed, true)) {
        return;
    }

    $css_path = 'assets/css/lojavirtual.css';
    if (file_exists(LV_DIR . $css_path)) {
        // Usando a constante LV_ASSETS_VERSION para evitar cache
        wp_enqueue_style('lv-dashboard-css', LV_URL . $css_path, array(), LV_ASSETS_VERSION);
    }
    
    $o = lv_get_options();
    $font_map = array('Montserrat' => 'Montserrat:wght@400;600;700', 'Poppins' => 'Poppins:wght@400;600;700', 'Raleway' => 'Raleway:wght@400;600;700', 'Inter' => 'Inter:wght@400;500;600;700', 'Roboto' => 'Roboto:wght@400;700', 'Open Sans' => 'Open+Sans:wght@400;700', 'Nunito' => 'Nunito:wght@400;700', 'Lato' => 'Lato:wght@400;700', 'Plus Jakarta Sans' => 'Plus+Jakarta+Sans:wght@400;700');
    foreach ($font_map as $label => $gf) {
        if (strpos($o['font'], $label) !== false) {
            wp_enqueue_style('lv-google-font', 'https://fonts.googleapis.com/css2?family=' . $gf . '&display=swap', array(), null);
            break;
        }
    }
    // Chart.js é necessário para os gráficos e é usado de um CDN
    if (!empty($o['cards']['chart']) || !empty($o['cards']['payment_methods'])) {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    }
});

/* ===== Funções Auxiliares com Cache (Transients API) e PHPDoc ===== */
/** @return bool */
function lv_wc_active() { return function_exists('wc_get_orders') && class_exists('WC_Product'); }
/** @param float $v @return string */
function lv_price_html($v) { return function_exists('wc_price') ? wc_price($v) : 'R$ ' . number_format_i18n($v, 2); }
/** @param string $status @return int */
function lv_count_orders_by_status($status) { $transient_key = 'lv_count_' . sanitize_key($status); if (false === ($count = get_transient($transient_key))) { if (!lv_wc_active()) return 0; $ids = wc_get_orders(array('limit' => -1, 'status' => array($status), 'return' => 'ids')); $count = is_array($ids) ? count($ids) : 0; set_transient($transient_key, $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @return int */
function lv_total_orders() { if (false === ($count = get_transient('lv_total_orders'))) { if (!lv_wc_active()) return 0; $ids = wc_get_orders(array('limit' => -1, 'return' => 'ids')); $count = is_array($ids) ? count($ids) : 0; set_transient('lv_total_orders', $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @param int $months @param array $statuses @return float */
function lv_total_sales_value($months, $statuses) { $transient_key = 'lv_sales_' . $months . '_months_' . md5(implode(',', $statuses)); if (false === ($sum = get_transient($transient_key))) { if (!lv_wc_active()) return 0.0; $start = date('Y-m-01', strtotime('-' . max(0, $months - 1) . ' months')); $end = date('Y-m-t'); $ids = wc_get_orders(array('limit' => -1, 'status' => $statuses, 'type' => 'shop_order', 'date_created' => $start . '...' . $end, 'return' => 'ids')); $sum = 0.0; foreach ($ids as $id) { $o = wc_get_order($id); if ($o) $sum += (float) $o->get_total(); } set_transient($transient_key, $sum, 15 * MINUTE_IN_SECONDS); } return $sum; }
/** @param int $months @param array $statuses @return float */
function lv_average_ticket_value($months, $statuses) { $transient_key = 'lv_avg_ticket_' . $months . '_months_' . md5(implode(',', $statuses)); if (false === ($avg_ticket = get_transient($transient_key))) { if (!lv_wc_active()) return 0.0; $start = date('Y-m-01', strtotime('-' . max(0, $months - 1) . ' months')); $end = date('Y-m-t'); $orders = wc_get_orders(array('limit' => -1, 'status' => $statuses, 'type' => 'shop_order', 'date_created' => $start . '...' . $end)); $total_sales = 0.0; $order_count = count($orders); if ($order_count > 0) { foreach ($orders as $order) { $total_sales += (float) $order->get_total(); } $avg_ticket = $total_sales / $order_count; } else { $avg_ticket = 0.0; } set_transient($transient_key, $avg_ticket, 15 * MINUTE_IN_SECONDS); } return $avg_ticket; }
/** @return int */
function lv_new_customers_count() { if (false === ($count = get_transient('lv_new_customers_30d'))) { $start_date = date('Y-m-d', strtotime('-30 days')); $user_query = new WP_User_Query(array('role' => 'customer', 'date_query' => array(array('after' => $start_date, 'inclusive' => true)))); $count = $user_query->get_total(); set_transient('lv_new_customers_30d', $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @return int */
function lv_customers_count() { if (false === ($count = get_transient('lv_customers_count'))) { if (!function_exists('count_users')) return 0; $cu = count_users(); $count = isset($cu['avail_roles']['customer']) ? intval($cu['avail_roles']['customer']) : 0; set_transient('lv_customers_count', $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @return int */
function lv_products_low_stock_count() { if (false === ($count = get_transient('lv_low_stock_count'))) { if (!lv_wc_active()) return 0; $global_threshold = absint(get_option('woocommerce_notify_low_stock_amount', 2)); $ids = wc_get_products(array('limit' => -1, 'status' => 'publish', 'type' => array('simple','variable'), 'stock_status' => 'instock', 'return' => 'ids')); $count = 0; foreach ($ids as $pid) { $p = wc_get_product($pid); if (!$p) continue; if ($p->managing_stock()) { $th = (int) $p->get_low_stock_amount(); if ($th <= 0) $th = $global_threshold; $qty = (int) $p->get_stock_quantity(); if ($qty > 0 && $qty <= $th) $count++; } } set_transient('lv_low_stock_count', $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @return int */
function lv_products_out_stock_count() { if (false === ($count = get_transient('lv_out_stock_count'))) { if (!lv_wc_active()) return 0; $ids = wc_get_products(array('limit' => -1,'status' => 'publish','stock_status' => 'outofstock','return' => 'ids')); $count = is_array($ids) ? count($ids) : 0; set_transient('lv_out_stock_count', $count, 15 * MINUTE_IN_SECONDS); } return $count; }
/** @param int $months @param array $statuses @return array */
function lv_monthly_series($months, $statuses) { $transient_key = 'lv_monthly_series_' . $months . '_' . md5(implode(',', $statuses)); if (false === ($data = get_transient($transient_key))) { $labels = array(); $labels_full = array(); $sales_values = array(); $order_counts = array(); if (lv_wc_active()) { for ($i = $months - 1; $i >= 0; $i--) { $ts = strtotime(date('Y-m-01') . " -$i months"); $mStart = date('Y-m-01', $ts); $mEnd = date('Y-m-t', $ts); $m_short = date_i18n('M', $ts); $y_short = date_i18n('y', $ts); $labels[] = sprintf('%s/%s', $m_short, $y_short); $m_full = date_i18n('F', $ts); $y_full = date_i18n('Y', $ts); $labels_full[] = sprintf('%s %s', ucfirst($m_full), $y_full); $ids = wc_get_orders(array('limit' => -1, 'status' => $statuses, 'type' => 'shop_order', 'date_created' => $mStart . '...' . $mEnd, 'return' => 'ids')); $sum = 0.0; foreach ($ids as $id) { $o = wc_get_order($id); if ($o) $sum += (float) $o->get_total(); } $sales_values[] = round($sum, 2); $order_counts[] = count($ids); } } $data = array($labels, $labels_full, $sales_values, $order_counts); set_transient($transient_key, $data, 15 * MINUTE_IN_SECONDS); } return $data; }
/** @param int $limit @param array $statuses @return WC_Order[] */
function lv_last_orders($limit = 10, $statuses = array()) { if (!lv_wc_active()) return array(); $args = array('limit' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order'); if (!empty($statuses)) $args['status'] = $statuses; return wc_get_orders($args); }
/** @return array|null */
function lv_last_order_info() { if (false === ($info = get_transient('lv_last_order_info'))) { if (!lv_wc_active()) return null; $orders = wc_get_orders(array('limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys(wc_get_order_statuses()))); if (empty($orders)) { $info = null; } else { $o = $orders[0]; $info = array('id' => $o->get_id(), 'url' => get_edit_post_link($o->get_id()), 'text_html' => '#' . $o->get_id() . ' – ' . lv_price_html($o->get_total())); } set_transient('lv_last_order_info', $info, 15 * MINUTE_IN_SECONDS); } return $info; }
/** @param array $statuses @return float */
function lv_get_current_month_sales($statuses) { $transient_key = 'lv_current_month_sales_' . md5(implode(',', $statuses)); if (false === ($sum = get_transient($transient_key))) { if (!lv_wc_active()) return 0.0; $start = date('Y-m-01'); $end = date('Y-m-t'); $ids = wc_get_orders(array('limit' => -1, 'status' => $statuses, 'type' => 'shop_order', 'date_created' => $start . '...' . $end, 'return' => 'ids')); $sum = 0.0; foreach ($ids as $id) { $o = wc_get_order($id); if ($o) $sum += (float) $o->get_total(); } set_transient($transient_key, $sum, 15 * MINUTE_IN_SECONDS); } return $sum; }
/** @param int $limit @return array */
function lv_get_top_selling_products($limit = 5) { $transient_key = 'lv_top_products_' . $limit; if (false === ($top_products = get_transient($transient_key))) { if (!lv_wc_active()) return array(); global $wpdb; $start_date = date('Y-m-d', strtotime('-30 days')); $results = $wpdb->get_results($wpdb->prepare("SELECT p.ID as product_id, p.post_title, SUM(woim.meta_value) as total_quantity FROM {$wpdb->prefix}woocommerce_order_items AS woi JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim ON woi.order_item_id = woim.order_item_id JOIN {$wpdb->posts} AS o ON woi.order_id = o.ID JOIN {$wpdb->posts} AS p ON woim.meta_value = p.ID WHERE woi.order_item_type = 'line_item' AND woim.meta_key = '_product_id' AND o.post_type = 'shop_order' AND o.post_status IN ('wc-processing', 'wc-completed') AND o.post_date >= %s GROUP BY p.ID ORDER BY total_quantity DESC LIMIT %d", $start_date, $limit)); $top_products = $results; set_transient($transient_key, $top_products, HOUR_IN_SECONDS); } return $top_products; }
/** @return array */
function lv_get_sales_by_payment_method() { $transient_key = 'lv_sales_by_payment'; if (false === ($payment_sales = get_transient($transient_key))) { if (!lv_wc_active()) return array(); global $wpdb; $start_date = date('Y-m-d', strtotime('-90 days')); $results = $wpdb->get_results($wpdb->prepare("SELECT pm.meta_value as payment_method_title, SUM(pm_total.meta_value) as total_sales FROM {$wpdb->posts} AS p JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id JOIN {$wpdb->postmeta} AS pm_total ON p.ID = pm_total.post_id WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-processing', 'wc-completed') AND p.post_date >= %s AND pm.meta_key = '_payment_method_title' AND pm_total.meta_key = '_order_total' GROUP BY payment_method_title ORDER BY total_sales DESC", $start_date)); $payment_sales = $results; set_transient($transient_key, $payment_sales, HOUR_IN_SECONDS); } return $payment_sales; }
/** @param int $limit @return array */
function lv_get_top_customers($limit = 5) { $transient_key = 'lv_top_customers_' . $limit; if (false === ($top_customers = get_transient($transient_key))) { if (!lv_wc_active()) return array(); global $wpdb; $results = $wpdb->get_results($wpdb->prepare("SELECT meta_value as customer_id, SUM(meta2.meta_value) as total_spent FROM {$wpdb->postmeta} AS meta JOIN {$wpdb->postmeta} AS meta2 ON meta.post_id = meta2.post_id JOIN {$wpdb->posts} AS p ON meta.post_id = p.ID WHERE meta.meta_key = '_customer_user' AND meta2.meta_key = '_order_total' AND p.post_type = 'shop_order' AND p.post_status IN ('wc-processing', 'wc-completed') AND meta.meta_value > 0 GROUP BY customer_id ORDER BY total_spent DESC LIMIT %d", $limit)); $top_customers = array(); foreach($results as $result) { $user = get_user_by('id', $result->customer_id); if ($user) { $top_customers[] = array('name' => $user->display_name, 'total_spent' => $result->total_spent, 'edit_link' => get_edit_user_link($user->ID)); } } set_transient($transient_key, $top_customers, HOUR_IN_SECONDS); } return $top_customers; }

/* ===== Lógica de Exportação CSV ===== */

/**
 * Lida com a exportação de vendas recentes para CSV.
 *
 * @action admin_init
 */
function lv_handle_csv_export() {
    if (isset($_GET['lv_export_csv']) && $_GET['lv_export_csv'] == 'true' && current_user_can('manage_options')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ultimas-vendas-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Pedido ID', 'Cliente', 'Email Cliente', 'Total (R$)', 'Status', 'Data', 'Itens'));
        $o = lv_get_options();
        // Obtém os pedidos (limitado a 100) com base nos status de métricas configurados
        $orders = lv_last_orders(100, $o['statuses_for_metrics']);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $items_list = array();
                foreach ($order->get_items() as $item) {
                    $items_list[] = $item->get_name() . ' (x' . $item->get_quantity() . ')';
                }
                $row = array(
                    $order->get_id(),
                    $order->get_formatted_billing_full_name(),
                    $order->get_billing_email(),
                    $order->get_total(),
                    wc_get_order_status_name($order->get_status()),
                    $order->get_date_created()->date('d/m/Y H:i'),
                    implode('; ', $items_list)
                );
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'lv_handle_csv_export');

/* ===== Tela do Dashboard ===== */

/**
 * Renderiza a tela principal do Dashboard.
 *
 * @uses lv_get_options()
 * @uses lv_get_plugin_version()
 */
function lv_dashboard_screen()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão para acessar esta página.', 'lojavirtual-dashboard'));
    }

    $o = lv_get_options();
    $c = $o['colors'];
    $plugin_version = lv_get_plugin_version(); // Obtém a versão do arquivo principal
    $style_vars = sprintf('--lv-primary:%1$s;--lv-secondary:%2$s;--lv-text:%3$s;--lv-link:%4$s;--lv-title:%5$s;--lv-btn:%6$s;--lv-btn-text:%7$s;--lv-border:%8$s;--lv-bars:%9$s;--lv-icons:%10$s; font-family:%11$s;', esc_attr($c['primary']), esc_attr($c['secondary']), esc_attr($c['text']), esc_attr($c['link']), esc_attr($c['title']), esc_attr($c['button']), esc_attr($c['button_text']), esc_attr($c['border']), esc_attr($c['bars']), esc_attr($c['icons']), esc_attr($o['font']));
    $dark_mode_class = !empty($o['dark_mode']) ? 'lv-dark-mode' : '';

    echo '<div class="wrap ' . esc_attr($dark_mode_class) . '" style="' . $style_vars . '">';
    // Badge de versão usando a versão dinâmica
    echo '<h1 class="lv-title-admin" style="color:var(--lv-title);margin:8px 0 14px;font-weight:700;">' . esc_html($o['title']) . ' <span class="lv-version-badge">v' . esc_html($plugin_version) . '</span></h1>';


    $all_cards = array(
        'monthly_goal' => function () use ($o) {
            $goal = (float)$o['monthly_goal'];
            $current_sales = lv_get_current_month_sales($o['statuses_for_metrics']);
            $progress = $goal > 0 ? min(100, ($current_sales / $goal) * 100) : 0;
            return '<h3><span class="lv-ico dashicons dashicons-flag"></span>META MENSAL</h3><div class="lv-progress-bar-wrapper"><div class="lv-progress-bar" style="width:' . esc_attr($progress) . '%"></div></div><div class="lv-progress-labels"><span>' . lv_price_html($current_sales) . '</span><span>' . lv_price_html($goal) . '</span></div>';
        },
        'total_sales' => function () use ($o) {
            return '<h3><span class="lv-ico dashicons dashicons-chart-bar"></span>VENDAS TOTAIS</h3><p class="metric">' . lv_price_html(lv_total_sales_value($o['months'], $o['statuses_for_metrics'])) . '</p><span class="lv-card-footer">Nos últimos ' . esc_html($o['months']) . ' meses</span>';
        },
        'total_orders' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-cart"></span>TOTAL DE PEDIDOS</h3><p class="metric">' . number_format_i18n(lv_total_orders()) . '</p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=shop_order')) . '">Ver todos</a>';
        },
        'avg_ticket' => function () use ($o) {
            return '<h3><span class="lv-ico dashicons dashicons-money-alt"></span>TICKET MÉDIO</h3><p class="metric">' . lv_price_html(lv_average_ticket_value($o['months'], $o['statuses_for_metrics'])) . '</p><span class="lv-card-footer">Nos últimos ' . esc_html($o['months']) . ' meses</span>';
        },
        'new_customers' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-admin-users"></span>NOVOS CLIENTES</h3><p class="metric">' . number_format_i18n(lv_new_customers_count()) . '</p><span class="lv-card-footer">Nos últimos 30 dias</span>';
        },
        'pending' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-editor-ol"></span>PENDENTES</h3><p class="metric">' . number_format_i18n(lv_count_orders_by_status('wc-pending')) . '</p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_status=wc-pending&post_type=shop_order')) . '">Ver todos</a>';
        },
        'on_hold' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-backup"></span>EM ESPERA</h3><p class="metric">' . number_format_i18n(lv_count_orders_by_status('wc-on-hold')) . '</p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_status=wc-on-hold&post_type=shop_order')) . '">Ver todos</a>';
        },
        'low_stock' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-warning"></span>ESTOQUE BAIXO</h3><p class="metric">' . number_format_i18n(lv_products_low_stock_count()) . '</p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=product&stock_status=instock')) . '">Ver produtos</a>';
        },
        'out_stock' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-no"></span>SEM ESTOQUE</h3><p class="metric">' . number_format_i18n(lv_products_out_stock_count()) . '</p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=product&stock_status=outofstock')) . '">Ver produtos</a>';
        },
        'last_order' => function () {
            $lo = lv_last_order_info();
            return '<h3><span class="lv-ico dashicons dashicons-media-default"></span>ÚLTIMA VENDA</h3><p class="metric">' . ($lo ? wp_kses_post($lo['text_html']) : '—') . '</p>' . ($lo ? '<a class="button button-primary" href="' . esc_url($lo['url']) . '">Ver detalhes</a>' : '');
        },
        'customers' => function () {
            return '<h3><span class="lv-ico dashicons dashicons-groups"></span>TOTAL DE CLIENTES</h3><p class="metric">' . number_format_i18n(lv_customers_count()) . '</p><a class="button button-primary" href="' . esc_url(admin_url('users.php?role=customer')) . '">Ver todos</a>';
        },
    );

    $enabled_cards = array();
    foreach ($o['order'] as $key => $pos) {
        if (!empty($o['cards'][$key]) && isset($all_cards[$key])) {
            $enabled_cards[$key] = (int)$pos;
        }
    }
    asort($enabled_cards, SORT_NUMERIC);

    $html_cards = array();
    foreach (array_keys($enabled_cards) as $k) {
        $html_cards[] = '<div class="dashboard-card">' . call_user_func($all_cards[$k]) . '</div>';
    }
    echo '<div class="dashboard-cards-metrics">' . implode('', $html_cards) . '</div>';
    echo '<div class="dashboard-wrapper">';

    if (!empty($o['cards']['chart'])) {
        list($labels, $labels_full, $sales_values, $order_counts) = lv_monthly_series($o['months'], $o['statuses_for_metrics']);
        $labels_js = wp_json_encode(array_values($labels));
        $labels_full_js = wp_json_encode(array_values($labels_full));
        $sales_values_js = wp_json_encode(array_values($sales_values));
        $order_counts_js = wp_json_encode(array_values($order_counts));
        echo '<div class="dashboard-box grafico"><div class="lv-chart-controls"><h3>Vendas Mensais</h3><div class="lv-chart-toggle"><button class="button active" data-view="value">Valor (R$)</button><button class="button" data-view="orders">Pedidos</button></div></div><div class="chart-holder"><canvas id="lvChartMonthly"></canvas></div></div>';
        ?>
        <script>
        // Script em Vanilla JS para o Gráfico de Vendas
        (function(){
            /**
             * Função para garantir que o script só execute após o DOM estar pronto (Vanilla JS).
             * @param {function} fn - A função a ser executada.
             */
            function ready(fn){
                if(document.readyState !== 'loading'){
                    fn();
                } else {
                    document.addEventListener('DOMContentLoaded', fn);
                }
            }
            ready(function(){
                if (typeof Chart === 'undefined') return;
                var el = document.getElementById('lvChartMonthly');
                if(!el) return;
                var ctx = el.getContext('2d');
                var wrap = document.querySelector('.wrap');
                var css = getComputedStyle(wrap);

                // Cores dinâmicas
                var baseColor = css.getPropertyValue('--lv-bars').trim() || '#3b82f6';
                var textColor = wrap.classList.contains('lv-dark-mode') ? '#9ca3af' : '#6b7280';

                /**
                 * Adiciona transparência a uma cor hexadecimal (Vanilla JS).
                 */
                function withAlpha(hex, a){
                    var c = hex.replace('#','');
                    if (c.length === 3) c = c.split('').map(function(x){return x+x}).join('');
                    var r = parseInt(c.substr(0,2),16), g = parseInt(c.substr(2,2),16), b = parseInt(c.substr(4,2),16);
                    return 'rgba('+r+','+g+','+b+','+a+')';
                }

                var currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
                var numberFormatter = new Intl.NumberFormat('pt-BR');
                var labels = <?php echo $labels_js ?: '[]'; ?>;
                var labelsFull = <?php echo $labels_full_js ?: '[]'; ?>;
                var salesValues = <?php echo $sales_values_js ?: '[]'; ?>;
                var orderCounts = <?php echo $order_counts_js ?: '[]'; ?>;
                var defaultView = '<?php echo esc_js($o['chart_default_view']); ?>';

                var chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Vendas por Mês',
                            data: salesValues,
                            backgroundColor: withAlpha(baseColor, 0.6),
                            borderColor: baseColor,
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function(items){ return labelsFull[items[0].dataIndex] || items[0].label; },
                                    label: function(ctx){ return currencyFormatter.format(ctx.parsed.y); }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: withAlpha(textColor, 0.1) },
                                ticks: {
                                    color: textColor,
                                    callback: function(value){ return currencyFormatter.format(value); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: textColor }
                            }
                        }
                    }
                });

                // Lógica de alternância (toggle) do gráfico (Vanilla JS)
                var controls = document.querySelector('.lv-chart-toggle');
                if (controls) {
                    controls.addEventListener('click', function(e){
                        if (e.target.tagName !== 'BUTTON') return;
                        var view = e.target.dataset.view;
                        controls.querySelectorAll('button').forEach(function(btn){ btn.classList.remove('active'); });
                        e.target.classList.add('active');

                        if (view === 'value') {
                            chart.data.datasets[0].data = salesValues;
                            chart.data.datasets[0].label = 'Valor das Vendas';
                            chart.options.scales.y.ticks.callback = function(value){ return currencyFormatter.format(value); };
                            chart.options.plugins.tooltip.callbacks.label = function(ctx){ return 'Vendas: ' + currencyFormatter.format(ctx.parsed.y); };
                        } else {
                            chart.data.datasets[0].data = orderCounts;
                            chart.data.datasets[0].label = 'Número de Pedidos';
                            chart.options.scales.y.ticks.callback = function(value){
                                if (Number.isInteger(value)) return numberFormatter.format(value);
                                return '';
                            };
                            chart.options.plugins.tooltip.callbacks.label = function(ctx){ return 'Pedidos: ' + numberFormatter.format(ctx.parsed.y); };
                        }
                        chart.update();
                    });
                }

                // Simula o clique no botão padrão
                var defaultButton = controls.querySelector('button[data-view="' + defaultView + '"]');
                if(defaultButton) defaultButton.click();
            });
        })();
        </script>
    <?php }

    if (!empty($o['cards']['payment_methods'])) {
        $payment_data = lv_get_sales_by_payment_method();
        $payment_labels = wp_json_encode(wp_list_pluck($payment_data, 'payment_method_title'));
        $payment_values = wp_json_encode(wp_list_pluck($payment_data, 'total_sales'));
        echo '<div class="dashboard-box"><div class="lv-box-header"><h3 style="display:block;">Vendas por Pagamento (90d)</h3></div><div class="chart-holder-pie"><canvas id="lvPaymentChart"></canvas></div></div>'; ?>
        <script>
        // Script em Vanilla JS para o Gráfico de Pagamentos
        (function(){
            function ready(fn){ if(document.readyState !== 'loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
            ready(function(){
                if (typeof Chart === 'undefined') return;
                var ctx = document.getElementById('lvPaymentChart')?.getContext('2d');
                if (!ctx) return;
                var fixedColors = ['#3b82f6','#10b981','#f97316','#ef4444','#8b5cf6','#14b8a6'];
                var textColor = document.querySelector('.wrap').classList.contains('lv-dark-mode') ? '#9ca3af' : '#6b7280';

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo $payment_labels; ?>,
                        datasets: [{
                            label: 'Total de Vendas',
                            data: <?php echo $payment_values; ?>,
                            backgroundColor: fixedColors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    color: textColor
                                }
                            }
                        }
                    }
                });
            });
        })();
        </script>
    <?php }

    if (!empty($o['cards']['last_sales'])) {
        $export_url = admin_url('admin.php?page=' . LV_SLUG_DASH . '&lv_export_csv=true');
        echo '<div class="dashboard-box"><div class="lv-box-header"><h3 style="display:block;">Últimas Vendas</h3><a href="' . esc_url($export_url) . '" class="button button-secondary">Exportar CSV</a></div><div class="lv-box-content"><table class="widefat fixed striped"><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th><th>Data</th></tr></thead><tbody>';
        $orders = lv_last_orders(30);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $id = $order->get_id();
                $name = trim($order->get_formatted_billing_full_name());
                if ($name === '') {
                    $name = $order->get_billing_email();
                }
                $d = $order->get_date_created();
                echo '<tr><td><a href="' . esc_url(get_edit_post_link($id)) . '">#' . intval($id) . '</a></td><td>' . esc_html($name ?: '—') . '</td><td>' . lv_price_html($order->get_total()) . '</td><td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td><td>' . esc_html($d ? $d->date_i18n('d/m/Y H:i') : '-') . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="5">Sem registros</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    if (!empty($o['cards']['top_products'])) {
        $top_products = lv_get_top_selling_products();
        echo '<div class="dashboard-box"><div class="lv-box-header"><h3 style="display:block;">Produtos Mais Vendidos (30d)</h3></div><div class="lv-box-content"><ul class="lv-list">';
        if (!empty($top_products)) {
            foreach ($top_products as $product) {
                echo '<li><a href="' . esc_url(get_edit_post_link($product->product_id)) . '">' . esc_html($product->post_title) . '</a><span>' . esc_html($product->total_quantity) . ' vendidos</span></li>';
            }
        } else {
            echo '<li>Sem dados suficientes.</li>';
        }
        echo '</ul></div></div>';
    }

    if (!empty($o['cards']['top_customers'])) {
        $top_customers = lv_get_top_customers();
        echo '<div class="dashboard-box"><div class="lv-box-header"><h3 style="display:block;">Clientes Mais Valiosos</h3></div><div class="lv-box-content"><ul class="lv-list">';
        if (!empty($top_customers)) {
            foreach ($top_customers as $customer) {
                echo '<li><a href="' . esc_url($customer['edit_link']) . '">' . esc_html($customer['name']) . '</a><span>' . lv_price_html($customer['total_spent']) . '</span></li>';
            }
        } else {
            echo '<li>Sem dados suficientes.</li>';
        }
        echo '</ul></div></div>';
    }

    echo '</div>'; // .dashboard-wrapper

    // --- Propaganda (Call to Action) Adicionada ---
    $promo_text = sprintf(
        __('Quer mais gráficos e funcionalidades? Conheça a versão completa do Dashboard em <a href="%s" target="_blank" rel="noopener" style="color:var(--lv-link);font-weight:600;">%s</a>', 'lojavirtual-dashboard'),
        'https://wpmasters.com.br/produto/woocommerce-dashboard-pro-painel-de-controle-e-metricas-em-tempo-real/',
        'wpmasters.com.br'
    );
    echo '<div style="margin:20px 0;padding:15px;background-color:var(--lv-secondary);border:1px solid var(--lv-border);border-radius:8px;text-align:center;font-size:14px;color:var(--lv-text);">';
    echo wp_kses_post($promo_text);
    echo '</div>';

    echo '<div class="lv-footer-brand" style="margin:12px 0 0;text-align:right;color:#6b7280;">Desenvolvido por <a href="https://wpmasters.com.br" target="_blank" rel="noopener"><strong style="color:#4b5563;">Thomas Marcelino</strong></a></div>';
    echo '</div>'; // .wrap

    if (!empty($o['dark_mode'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('lv-body-dark'); });</script>";
    }
}
?>