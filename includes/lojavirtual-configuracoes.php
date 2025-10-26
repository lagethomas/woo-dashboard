<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('lv_settings_screen')) {

    /**
     * Função para carregar os assets da página de configurações.
     *
     * @param string $hook O slug da página de administração atual.
     * @action admin_enqueue_scripts
     */
    add_action('admin_enqueue_scripts', function ($hook) {
        // Hook correto para submenus é {parent_slug}_page_{submenu_slug}
        if ($hook !== LV_SLUG_DASH . '_page_' . LV_SLUG_CFG) {
             return;
        }
        
        $css_file = 'assets/css/lojavirtual-settings.css';
        if (file_exists(LV_DIR . $css_file)) {
            // Usando LV_ASSETS_VERSION
            wp_enqueue_style('lv-settings-css', LV_URL . $css_file, array(), LV_ASSETS_VERSION);
        }
    });

    /**
     * Registra as configurações do plugin no WordPress Settings API.
     *
     * @action admin_init
     */
    add_action('admin_init', function () {
        register_setting('lv_dash_group', 'lv_dash_settings', array(
            'type' => 'array',
            'sanitize_callback' => function ($in) {
                // Verificação de segurança (Nonce)
                if (!isset($_POST['lv_settings_nonce_field']) || !wp_verify_nonce($_POST['lv_settings_nonce_field'], 'lv_settings_nonce')) {
                    add_settings_error('lv_dash_settings', 'lv_nonce_error', __('Erro de segurança. As alterações não foram salvas.', 'lojavirtual-dashboard'), 'error');
                    return get_option('lv_dash_settings');
                }
                
                $def = lv_default_options();
                $out = array();
                // Sanitização de campos
                $out['title'] = isset($in['title']) ? sanitize_text_field($in['title']) : $def['title'];
                
                $fonts = array('Montserrat, system-ui, Arial, sans-serif', 'Poppins, system-ui, Arial, sans-serif', 'Raleway, system-ui, Arial, sans-serif', 'Inter, system-ui, Arial, sans-serif', 'Roboto, system-ui, Arial, sans-serif', 'Open Sans, system-ui, Arial, sans-serif', 'Nunito, system-ui, Arial, sans-serif', 'Lato, system-ui, Arial, sans-serif', 'Plus Jakarta Sans, system-ui, Arial, sans-serif', 'system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif');
                $out['font'] = in_array($in['font'] ?? '', $fonts, true) ? $in['font'] : $def['font'];
                
                $scale = isset($in['scale']) ? preg_replace('/[^0-9%]/', '', $in['scale']) : $def['scale'];
                if (!preg_match('/^\d{2,3}%$/', $scale)) {
                    $scale = $def['scale'];
                }
                $out['scale'] = $scale;
                
                $out['dark_mode'] = !empty($in['dark_mode']) ? 1 : 0;
                $out['chart_default_view'] = in_array($in['chart_default_view'] ?? '', ['value', 'orders']) ? $in['chart_default_view'] : $def['chart_default_view'];
                $out['monthly_goal'] = isset($in['monthly_goal']) ? abs(floatval($in['monthly_goal'])) : $def['monthly_goal'];
                
                // Sanitização de Cores
                $map = array('text','link','title','button','button_text','border','bars','icons','primary','secondary');
                foreach ($map as $k) {
                    if (in_array($k, array('primary','secondary'), true)) {
                        $out['colors'][$k] = $def['colors'][$k];
                        continue;
                    }
                    $val = isset($in['colors'][$k]) ? sanitize_hex_color($in['colors'][$k]) : '';
                    $out['colors'][$k] = $val ?: $def['colors'][$k];
                }
                
                // Sanitização de Cards
                $out['cards'] = array();
                foreach (array_keys($def['cards']) as $k) {
                    $out['cards'][$k] = !empty($in['cards'][$k]) ? 1 : 0;
                }
                
                // Sanitização da Ordem dos Cards
                $out['order'] = array();
                foreach ($def['order'] as $k => $pos) {
                    $num = isset($in['order'][$k]) ? intval($in['order'][$k]) : $pos;
                    $out['order'][$k] = max(1, min(count($def['order']), $num));
                }
                
                // Sanitização de Período
                $m = isset($in['months']) ? intval($in['months']) : $def['months'];
                $out['months'] = max(3, min(24, $m));
                
                // Sanitização de Status de Pedidos
                $allowed = array('wc-pending','wc-processing','wc-on-hold','wc-completed','wc-cancelled','wc-refunded','wc-failed');
                $out['statuses_for_metrics'] = array();
                if (!empty($in['statuses_for_metrics']) && is_array($in['statuses_for_metrics'])) {
                    foreach ($in['statuses_for_metrics'] as $st) {
                        $st = sanitize_text_field($st);
                        if (in_array($st, $allowed, true)) {
                            $out['statuses_for_metrics'][] = $st;
                        }
                    }
                }
                if (empty($out['statuses_for_metrics'])) {
                    $out['statuses_for_metrics'] = $def['statuses_for_metrics'];
                }
                
                // Limpa Transients para forçar a atualização dos dados
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lv\_%' OR option_name LIKE '\_transient\_timeout\_lv\_%'");
                
                return $out;
            }, 'default' => lv_default_options(),
        ));

        // Campos e Seções de Configuração
        add_settings_section('lv_sec_main', __('Configurações Gerais', 'lojavirtual-dashboard'), function () {
            echo '<p style="max-width:720px">' . esc_html__('Ajustes visuais e de conteúdo do painel. As cores afetam bordas, botões, títulos, ícones e barras do gráfico.', 'lojavirtual-dashboard') . '</p>';
        }, 'lv_dash_settings_page');
        add_settings_field('lv_title', __('Título do Dashboard', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            echo '<input type="text" name="lv_dash_settings[title]" value="' . esc_attr($o['title']) . '" class="regular-text" /><p class="description">' . esc_html__('Texto exibido no topo do painel.', 'lojavirtual-dashboard') . '</p>';
        }, 'lv_dash_settings_page', 'lv_sec_main');
        add_settings_field('lv_monthly_goal', __('Meta de Faturamento Mensal', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            echo 'R$ <input type="number" name="lv_dash_settings[monthly_goal]" value="' . esc_attr($o['monthly_goal']) . '" class="regular-text" step="100" min="0" />';
            echo '<p class="description">' . esc_html__('Defina um objetivo de vendas para o mês atual. Deixe em 0 para desativar.', 'lojavirtual-dashboard') . '</p>';
        }, 'lv_dash_settings_page', 'lv_sec_main');
        add_settings_field('lv_dark_mode', __('Aparência', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $checked = !empty($o['dark_mode']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="lv_dash_settings[dark_mode]" value="1" ' . $checked . '> ' . esc_html__('Ativar modo escuro (Dark Mode)', 'lojavirtual-dashboard') . '</label>';
        }, 'lv_dash_settings_page', 'lv_sec_main');
        add_settings_field('lv_font_scale', __('Fonte e Tamanho base', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $fonts = array('Montserrat, system-ui, Arial, sans-serif' => 'Montserrat', 'Poppins, system-ui, Arial, sans-serif' => 'Poppins', 'Raleway, system-ui, Arial, sans-serif' => 'Raleway', 'Inter, system-ui, Arial, sans-serif' => 'Inter', 'Roboto, system-ui, Arial, sans-serif' => 'Roboto', 'Open Sans, system-ui, Arial, sans-serif' => 'Open Sans', 'Nunito, system-ui, Arial, sans-serif' => 'Nunito', 'Lato, system-ui, Arial, sans-serif' => 'Lato', 'Plus Jakarta Sans, system-ui, Arial, sans-serif' => 'Plus Jakarta Sans', 'system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif' => 'System UI');
            echo '<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><label>' . esc_html__('Fonte', 'lojavirtual-dashboard') . ':&nbsp;<select name="lv_dash_settings[font]">';
            foreach ($fonts as $k => $label) {
                echo '<option value="' . esc_attr($k) . '" ' . selected($o['font'], $k, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label><label>' . esc_html__('Tamanho base', 'lojavirtual-dashboard') . ':&nbsp;<select name="lv_dash_settings[scale]">';
            foreach (array('90%','100%','110%','120%','130%') as $opt) {
                echo '<option value="' . esc_attr($opt) . '" ' . selected($o['scale'], $opt, false) . '>' . esc_html($opt) . '</option>';
            }
            echo '</select></label></div>';
        }, 'lv_dash_settings_page', 'lv_sec_main');
        add_settings_field('lv_colors', __('Cores', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $c=$o['colors'];
            $row = function ($key, $label) use ($c) {
                echo '<div style="display:flex;align-items:center;gap:8px;margin:6px 0;"><label style="min-width:180px">' . esc_html($label) . ':</label><input type="color" name="lv_dash_settings[colors][' . esc_attr($key) . ']" value="' . esc_attr($c[$key]) . '" /></div>';
            };
            $row('text', __('Cor de texto', 'lojavirtual-dashboard'));
            $row('link', __('Cor de link', 'lojavirtual-dashboard'));
            $row('title', __('Cor do título', 'lojavirtual-dashboard'));
            $row('button', __('Cor do botão', 'lojavirtual-dashboard'));
            $row('button_text', __('Cor do texto do botão', 'lojavirtual-dashboard'));
            $row('border', __('Cor da borda', 'lojavirtual-dashboard'));
            $row('bars', __('Cor das barras do gráfico', 'lojavirtual-dashboard'));
            $row('icons', __('Cor dos ícones', 'lojavirtual-dashboard'));
        }, 'lv_dash_settings_page', 'lv_sec_main');

        add_settings_section('lv_sec_content', __('Conteúdo do Dashboard', 'lojavirtual-dashboard'), function () {
            echo '<p style="max-width:720px">' . esc_html__('Controle quais elementos são exibidos e a ordem dos cards de métricas.', 'lojavirtual-dashboard') . '</p>';
        }, 'lv_dash_settings_page');
        add_settings_field('lv_cards', __('Cards e Boxes Exibidos', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $c=$o['cards'];
            $items = array( 'monthly_goal' => 'Meta Mensal', 'total_sales' => 'Vendas Totais', 'total_orders' => 'Total de Pedidos', 'avg_ticket' => 'Ticket Médio', 'new_customers' => 'Novos Clientes', 'pending' => 'Pendentes', 'on_hold' => 'Em Espera', 'low_stock' => 'Estoque Baixo', 'out_stock' => 'Sem Estoque', 'last_order' => 'Última Venda', 'customers' => 'Total de Clientes', 'chart' => 'Gráfico Principal (Vendas)', 'last_sales' => 'Últimas Vendas (Tabela)', 'payment_methods' => 'Gráfico de Pagamentos', 'top_products' => 'Produtos Mais Vendidos', 'top_customers' => 'Clientes Mais Valiosos' );
            echo '<fieldset class="lv-cards-fieldset">';
            foreach ($items as $k => $label) {
                $checked = !empty($c[$k]) ? 'checked' : '';
                echo '<label><input type="checkbox" name="lv_dash_settings[cards][' . esc_attr($k) . ']" value="1" ' . $checked . '> ' . esc_html__($label, 'lojavirtual-dashboard') . '</label>';
            }
            echo '</fieldset>';
        }, 'lv_dash_settings_page', 'lv_sec_content');
        add_settings_field('lv_order', __('Ordem dos Cards de Métricas', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $labels = array( 'monthly_goal' => 'Meta Mensal', 'total_sales' => 'Vendas Totais', 'total_orders' => 'Total de Pedidos', 'avg_ticket' => 'Ticket Médio', 'new_customers' => 'Novos Clientes', 'pending' => 'Pendentes', 'on_hold' => 'Em Espera', 'low_stock' => 'Estoque Baixo', 'out_stock' => 'Sem Estoque', 'last_order' => 'Última Venda', 'customers' => 'Total de Clientes' );
            $max_order = count($labels);
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px 24px;max-width:900px">';
            foreach ($labels as $key => $label) {
                $val = isset($o['order'][$key]) ? intval($o['order'][$key]) : 1;
                echo '<label style="display:flex;align-items:center;gap:8px"><span style="min-width:180px">' . esc_html__($label, 'lojavirtual-dashboard') . ':</span><select name="lv_dash_settings[order][' . esc_attr($key) . ']">';
                for ($i = 1; $i <= $max_order; $i++) {
                    echo '<option value="' . $i . '" ' . selected($val, $i, false) . '>' . $i . '</option>';
                }
                echo '</select></label>';
            }
            echo '</div>';
        }, 'lv_dash_settings_page', 'lv_sec_content');
        add_settings_field('lv_period_status', __('Configurações de Métricas', 'lojavirtual-dashboard'), function () {
            $o = lv_get_options();
            $sel = $o['statuses_for_metrics'];
            echo '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;"><label>' . esc_html__('Meses no gráfico', 'lojavirtual-dashboard') . ':&nbsp;<input type="number" min="3" max="24" name="lv_dash_settings[months]" value="' . esc_attr(intval($o['months'])) . '" class="small-text" /></label><label>' . esc_html__('Visão padrão do gráfico', 'lojavirtual-dashboard') . ':&nbsp;<select name="lv_dash_settings[chart_default_view]"><option value="value" ' . selected($o['chart_default_view'], 'value', false) . '>' . esc_html__('Valor (R$)', 'lojavirtual-dashboard') . '</option><option value="orders" ' . selected($o['chart_default_view'], 'orders', false) . '>' . esc_html__('Pedidos', 'lojavirtual-dashboard') . '</option></select></label></div><hr style="margin: 16px 0; border: 0; border-top: 1px solid #ddd;"><div><div style="margin-bottom:6px;font-weight:600">' . esc_html__('Status considerados nas métricas', 'lojavirtual-dashboard') . '</div>';
            $all = array('wc-pending' => 'Pendente', 'wc-processing' => 'Processando', 'wc-on-hold' => 'Aguardando', 'wc-completed' => 'Concluído', 'wc-cancelled' => 'Cancelado', 'wc-refunded' => 'Reembolsado', 'wc-failed' => 'Falhou');
            foreach ($all as $key => $label) {
                $checked = in_array($key, $sel, true) ? 'checked' : '';
                echo '<label style="display:inline-block;margin:4px 12px 4px 0;"><input type="checkbox" name="lv_dash_settings[statuses_for_metrics][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html__($label, 'lojavirtual-dashboard') . '</label>';
            }
            echo '</div>';
        }, 'lv_dash_settings_page', 'lv_sec_content');
    });

    /**
     * Renderiza a tela de configurações do plugin.
     *
     * @uses lv_get_plugin_version()
     */
    function lv_settings_screen()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sem permissão para acessar esta página.', 'lojavirtual-dashboard'));
        }
        $plugin_version = lv_get_plugin_version(); // Obtém a versão do arquivo principal
        // Badge de versão usando a versão dinâmica
        echo '<div class="wrap"><h1 style="margin-bottom:12px;">' . esc_html__('Configurações do Dashboard', 'lojavirtual-dashboard') . ' <span class="lv-version-badge">v' . esc_html($plugin_version) . '</span></h1>';
        settings_errors('lv_dash_settings');
        echo '<form method="post" action="options.php">';
        settings_fields('lv_dash_group');
        wp_nonce_field('lv_settings_nonce', 'lv_settings_nonce_field');
        do_settings_sections('lv_dash_settings_page');
        echo '<div style="display:flex;gap:12px;align-items:center;margin-top:24px">';
        submit_button(__('Salvar alterações', 'lojavirtual-dashboard'), 'primary', 'submit', false);
        $reset_url = wp_nonce_url(admin_url('admin.php?page=' . LV_SLUG_CFG . '&lvreset=1'), 'lv_reset_nonce', '_wpnonce');
        echo '<a href="' . esc_url($reset_url) . '" class="button button-secondary">' . esc_html__('Restaurar padrão', 'lojavirtual-dashboard') . '</a>';
        echo '</div></form>';
        echo '<div class="lv-footer-brand" style="margin:16px 0 0;text-align:right;color:#6b7a8c;">' . esc_html__('Desenvolvido por', 'lojavirtual-dashboard') . ' <a href="https://wpmasters.com.br" target="_blank" rel="noopener"><strong style="color:#5b6470;">Thomas Marcelino</strong></a></div></div>';

        // Lógica de Reset
        if (!empty($_GET['lvreset']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'lv_reset_nonce')) {
            update_option('lv_dash_settings', lv_default_options());
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lv\_%' OR option_name LIKE '\_transient\_timeout\_lv\_%'");
            wp_safe_redirect(remove_query_arg(array('lvreset', '_wpnonce')));
            exit;
        }
    }
}
?>