<?php
/**
 * Třída pro administrační rozhraní
 * 
 * Zajišťuje vykreslení stránek a interakci v administraci.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_Admin {
    /**
     * Instance třídy data
     * 
     * @var AI_Feedback_Analytics_Data
     */
    private $data;
    
    /**
     * Instance třídy API
     * 
     * @var AI_Feedback_Analytics_API
     */
    private $api;
    
    /**
     * Instance třídy export
     * 
     * @var AI_Feedback_Analytics_Export
     */
    private $export;

    /**
     * Instance třídy
     * 
     * @var AI_Feedback_Analytics_Admin
     */
    private static $instance = null;

    /**
     * Získání instance třídy
     * 
     * @return AI_Feedback_Analytics_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializace třídy
     * 
     * @param AI_Feedback_Analytics_Data $data Instance třídy data
     * @param AI_Feedback_Analytics_API $api Instance třídy API
     * @param AI_Feedback_Analytics_Export $export Instance třídy export
     */
    public function init($data, $api, $export) {
        $this->data = $data;
        $this->api = $api;
        $this->export = $export;
        
        // Registrace admin menu
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('load-toplevel_page_ai-feedback-analytics', [$this, 'add_dashboard_metaboxes']);
        
        // Přidání metaboxu pro AI analýzu
        add_action('add_meta_boxes', [$this, 'add_analysis_metabox']);
    }
    
    /**
     * Registrace položky v admin menu
     */
    public function register_admin_menu() {
        // Hlavní menu je již vytvořeno v hlavním souboru pluginu
        // Nepřidáváme podmenu, protože hlavní menu již používá stejný slug
    }
    
    /**
     * Načtení stylů a skriptů
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_ai-feedback-analytics') {
            return;
        }

        // Registrace a načtení Chart.js
        wp_register_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
            [],
            '3.7.1',
            true
        );

        // Registrace vlastních stylů a skriptů
        wp_enqueue_script('chartjs');
        wp_enqueue_script(
            'ai-feedback-analytics',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/analytics.js',
            ['jquery', 'chartjs', 'wp-util'],
            '1.0.0',
            true
        );
        wp_enqueue_style(
            'ai-feedback-analytics-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/analytics.css',
            [],
            '1.0.0'
        );

        // Přidání proměnných pro JavaScript
        wp_localize_script('ai-feedback-analytics', 'aiFeedbackAnalytics', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_feedback_analytics_nonce')
        ]);
    }
    
    /**
     * Přidání metaboxů do dashboardu
     */
    public function add_dashboard_metaboxes() {
        // Souhrnné statistiky
        add_meta_box(
            'ai_feedback_summary',
            'Souhrnné statistiky',
            [$this, 'render_summary_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'high'
        );

        // Analýza témat
        add_meta_box(
            'ai_feedback_topics',
            'Analýza témat',
            [$this, 'render_topics_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'default'
        );

        // Analýza porozumění
        add_meta_box(
            'ai_feedback_understanding',
            'Analýza porozumění',
            [$this, 'render_understanding_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'default'
        );

        // Analýza sentimentu
        add_meta_box(
            'ai_feedback_sentiment',
            'Analýza sentimentu',
            [$this, 'render_sentiment_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'side',
            'default'
        );

        // Přidání podpory pro přetahování metaboxů
        wp_enqueue_script('dashboard');
        wp_enqueue_script('postbox');
        add_action('admin_footer', function() {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    // Inicializace metaboxů
                    postboxes.add_postbox_toggles('toplevel_page_ai-feedback-analytics');
                    
                    // Automatické načtení dat při načtení stránky
                    function loadInitialData() {
                        const dateFrom = $('#date_from').val();
                        const dateTo = $('#date_to').val();
                        const keyword = $('#keyword').val();
                        
                        // Načtení souhrnných statistik
                        $.ajax({
                            url: aiFeedbackAnalytics.ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'analyze_feedback',
                                nonce: aiFeedbackAnalytics.nonce,
                                analysis_type: 'activity_overview',
                                date_from: dateFrom,
                                date_to: dateTo,
                                keyword: keyword
                            },
                            success: function(response) {
                                if (response.success) {
                                    renderActivityChart(response.data);
                                }
                            }
                        });
                        
                        // Načtení analýzy témat
                        $.ajax({
                            url: aiFeedbackAnalytics.ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'analyze_feedback',
                                nonce: aiFeedbackAnalytics.nonce,
                                analysis_type: 'topics',
                                date_from: dateFrom,
                                date_to: dateTo,
                                keyword: keyword
                            },
                            success: function(response) {
                                if (response.success) {
                                    renderTopicsCharts(response.data);
                                }
                            }
                        });
                        
                        // Načtení analýzy porozumění
                        $.ajax({
                            url: aiFeedbackAnalytics.ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'analyze_feedback',
                                nonce: aiFeedbackAnalytics.nonce,
                                analysis_type: 'understanding',
                                date_from: dateFrom,
                                date_to: dateTo,
                                keyword: keyword
                            },
                            success: function(response) {
                                if (response.success) {
                                    renderUnderstandingCharts(response.data);
                                }
                            }
                        });
                        
                        // Načtení analýzy sentimentu
                        $.ajax({
                            url: aiFeedbackAnalytics.ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'analyze_feedback',
                                nonce: aiFeedbackAnalytics.nonce,
                                analysis_type: 'sentiment',
                                date_from: dateFrom,
                                date_to: dateTo,
                                keyword: keyword
                            },
                            success: function(response) {
                                if (response.success) {
                                    renderSentimentChart(response.data);
                                }
                            }
                        });
                    }
                    
                    // Načtení dat při prvním zobrazení stránky
                    loadInitialData();
                });
            </script>
            <?php
        });
    }
    
    /**
     * Přidání metaboxu pro AI analýzu
     */
    public function add_analysis_metabox() {
        add_meta_box(
            'ai_analysis_metabox',
            'AI Analýza reflexe',
            [$this, 'render_analysis_metabox'],
            'reflexe',
            'normal',
            'high'
        );
    }
    
    /**
     * Vykreslení metaboxu s AI analýzou
     */
    public function render_analysis_metabox($post) {
        $analysis_json = get_post_meta($post->ID, 'ai_analysis', true);
        
        if (empty($analysis_json)) {
            echo '<p>Reflexe zatím nebyla analyzována pomocí AI.</p>';
            return;
        }
        
        $analysis = json_decode($analysis_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<p>Chyba při načítání analýzy.</p>';
            return;
        }
        
        ?>
        <div class="ai-analysis-container">
            <div class="ai-analysis-section">
                <h3>Shrnutí</h3>
                <p><?php echo esc_html($analysis['summary']); ?></p>
            </div>
            
            <div class="ai-analysis-section">
                <h3>Co jsem se naučil/a</h3>
                <ul>
                    <?php foreach ($analysis['key_learnings'] as $learning): ?>
                        <li><?php echo esc_html($learning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="ai-analysis-section">
                <h3>Jak to rozumím</h3>
                <ul>
                    <?php foreach ($analysis['understanding'] as $understanding): ?>
                        <li><?php echo esc_html($understanding); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="ai-analysis-section">
                <h3>Jak to využiji</h3>
                <ul>
                    <?php foreach ($analysis['usage_plans'] as $plan): ?>
                        <li><?php echo esc_html($plan); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <style>
            .ai-analysis-container {
                background: #fff;
                padding: 15px;
                border-radius: 4px;
            }
            
            .ai-analysis-section {
                margin-bottom: 20px;
            }
            
            .ai-analysis-section:last-child {
                margin-bottom: 0;
            }
            
            .ai-analysis-section h3 {
                margin-top: 0;
                color: #2271b1;
                border-bottom: 1px solid #eee;
                padding-bottom: 8px;
            }
            
            .ai-analysis-section ul {
                margin: 0;
                padding-left: 20px;
            }
            
            .ai-analysis-section li {
                margin-bottom: 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Vykreslení hlavní stránky analytiky
     */
    public function render_analytics_page() {
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.'));
        }
        ?>
        <div class="wrap">
            <h1>AI Feedback Analytics</h1>
            
            <!-- Filtr -->
            <form method="get" id="ai-feedback-filter-form">
                <input type="hidden" name="page" value="ai-feedback-analytics">
                
                <div class="ai-feedback-filters">
                    <div class="filter-group">
                        <label for="date_from">Od:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Do:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="keyword">Klíčové slovo:</label>
                        <input type="text" id="keyword" name="keyword" value="<?php echo esc_attr($_GET['keyword'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary">Filtrovat</button>
                        <button type="button" id="reset-filters" class="button">Reset filtrů</button>
                    </div>
                </div>
            </form>
            
            <!-- Re-analýza -->
            <div class="ai-feedback-reanalyze">
                <form method="post" action="">
                    <?php wp_nonce_field('ai_feedback_reanalyze', 'reanalyze_nonce'); ?>
                    <button type="submit" name="reanalyze_all" class="button button-primary">Spustit re-analýzu</button>
                </form>
            </div>
            
            <?php
            // Zpracování re-analýzy
            if (isset($_POST['reanalyze_all']) && check_admin_referer('ai_feedback_reanalyze', 'reanalyze_nonce')) {
                $result = $this->api->reanalyze_all_posts();
                if ($result['success']) {
                    echo '<div class="notice notice-success"><p>' . sprintf(
                        'Re-analýza dokončena. Zpracováno %d příspěvků, %d chyb.',
                        $result['total_posts'],
                        $result['errors']
                    ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Chyba při re-analýze: ' . 
                        implode(', ', $result['error_messages']) . '</p></div>';
                }
            }
            ?>
            
            <!-- Přehled analýz -->
            <div class="ai-feedback-analysis-grid">
                <?php
                global $wpdb;
                
                // Debug výpis
                $debug_sql = "SELECT request_type, COUNT(*) as count FROM {$wpdb->prefix}ai_feedback_api_logs GROUP BY request_type";
                $debug_results = $wpdb->get_results($debug_sql);
                echo '<div class="notice notice-info is-dismissible"><p>Dostupné typy požadavků:</p><pre>';
                foreach ($debug_results as $row) {
                    echo esc_html($row->request_type . ': ' . $row->count) . "\n";
                }
                echo '</pre></div>';
                
                // Získání posledních analýz
                $items_per_page = 10;
                $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($current_page - 1) * $items_per_page;
                
                // Počet všech záznamů pro stránkování
                $total_items = $wpdb->get_var("
                    SELECT COUNT(*)
                    FROM {$wpdb->prefix}ai_feedback_api_logs
                    WHERE response_data IS NOT NULL
                    AND error_message IS NULL
                    AND (
                        request_type LIKE 'analyze_entries_%'
                        OR request_type = 'analyze_references'
                        OR request_type = 'analyze_single_entry'
                    )
                ");
                
                // Získání dat pro aktuální stránku
                $sql = $wpdb->prepare("
                    SELECT response_data, created_at, request_type 
                    FROM {$wpdb->prefix}ai_feedback_api_logs 
                    WHERE response_data IS NOT NULL
                    AND error_message IS NULL
                    AND (
                        request_type LIKE 'analyze_entries_%'
                        OR request_type = 'analyze_references'
                        OR request_type = 'analyze_single_entry'
                    )
                    ORDER BY created_at DESC
                    LIMIT %d OFFSET %d",
                    $items_per_page,
                    $offset
                );
                
                $logs = $wpdb->get_results($sql);
                
                // Seskupení analýz podle typu
                $grouped_logs = [];
                foreach ($logs as $log) {
                    $type = $log->request_type;
                    if (!isset($grouped_logs[$type])) {
                        $grouped_logs[$type] = [];
                    }
                    $grouped_logs[$type][] = $log;
                }
                
                if (empty($logs)) {
                    echo '<div class="notice notice-info"><p>Zatím nejsou k dispozici žádné analýzy.</p></div>';
                } else {
                    // Zobrazení filtrů a řazení
                    ?>
                    <div class="ai-feedback-filters">
                        <select id="type-filter" class="filter-select">
                            <option value="">Všechny typy</option>
                            <option value="analyze_entries_topics">Analýza témat</option>
                            <option value="analyze_entries_understanding">Analýza porozumění</option>
                            <option value="analyze_references">Analýza referencí</option>
                            <option value="analyze_single_entry">Jednotlivé analýzy</option>
                        </select>
                        
                        <select id="sort-order" class="filter-select">
                            <option value="newest">Nejnovější první</option>
                            <option value="oldest">Nejstarší první</option>
                        </select>
                    </div>
                    
                    <?php
                    // Zobrazení analýz podle typu
                    foreach ($grouped_logs as $type => $type_logs) {
                        $type_label = [
                            'analyze_entries_topics' => 'Analýza témat',
                            'analyze_entries_understanding' => 'Analýza porozumění',
                            'analyze_references' => 'Analýza referencí',
                            'analyze_single_entry' => 'Jednotlivá analýza'
                        ][$type] ?? $type;
                        
                        ?>
                        <div class="ai-feedback-type-section" data-type="<?php echo esc_attr($type); ?>">
                            <h2 class="type-header"><?php echo esc_html($type_label); ?></h2>
                            <div class="ai-feedback-grid">
                                <?php foreach ($type_logs as $log): 
                                    $data = json_decode($log->response_data, true);
                                    if (!$data) continue;
                                ?>
                                <div class="ai-feedback-card">
                                    <div class="card-header">
                                        <div class="card-meta">
                                            <span class="card-date"><?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($type === 'analyze_references'): ?>
                                        <?php if (isset($data['sentiment'])): ?>
                                        <div class="card-section sentiment-section">
                                            <h4>Sentiment</h4>
                                            <div class="sentiment-bars">
                                                <?php
                                                $total = array_sum($data['sentiment']);
                                                foreach ($data['sentiment'] as $sent_type => $count):
                                                    $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                                                    $color = [
                                                        'positive' => '#4CAF50',
                                                        'neutral' => '#FFC107',
                                                        'negative' => '#F44336'
                                                    ][$sent_type] ?? '#999';
                                                ?>
                                                <div class="sentiment-bar-container">
                                                    <div class="sentiment-label">
                                                        <?php echo esc_html(ucfirst($sent_type)); ?>
                                                        <span class="sentiment-count"><?php echo $count; ?></span>
                                                    </div>
                                                    <div class="sentiment-bar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>"></div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($data['appreciated_aspects'])): ?>
                                        <div class="card-section">
                                            <h4>Oceňované aspekty</h4>
                                            <div class="aspects-cloud">
                                                <?php 
                                                foreach ($data['appreciated_aspects'] as $aspect => $count): 
                                                    $size = 14 + min($count * 2, 10); // Velikost fontu 14-24px
                                                ?>
                                                <span class="aspect-tag" style="font-size: <?php echo $size; ?>px">
                                                    <?php echo esc_html($aspect); ?>
                                                    <span class="aspect-count"><?php echo $count; ?></span>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (isset($data['summary'])): ?>
                                        <div class="card-section">
                                            <h4>Shrnutí</h4>
                                            <p class="summary-text"><?php echo esc_html($data['summary']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($data['key_learnings']) && !empty($data['key_learnings'])): ?>
                                        <div class="card-section">
                                            <h4>Klíčové poznatky</h4>
                                            <ul class="key-points">
                                                <?php foreach ($data['key_learnings'] as $learning): ?>
                                                    <li><?php echo esc_html($learning); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($data['main_topics'])): ?>
                                        <div class="card-section">
                                            <h4>Hlavní témata</h4>
                                            <div class="topics-cloud">
                                                <?php 
                                                foreach ($data['main_topics'] as $topic => $count): 
                                                    $size = 14 + min($count * 2, 10);
                                                ?>
                                                <span class="topic-tag" style="font-size: <?php echo $size; ?>px">
                                                    <?php echo esc_html($topic); ?>
                                                    <span class="topic-count"><?php echo $count; ?></span>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php
                    }
                    
                    // Stránkování
                    $total_pages = ceil($total_items / $items_per_page);
                    if ($total_pages > 1) {
                        ?>
                        <div class="ai-feedback-pagination">
                            <?php
                            for ($i = 1; $i <= $total_pages; $i++) {
                                $class = $i === $current_page ? 'current' : '';
                                echo sprintf(
                                    '<a href="%s" class="page-number %s">%d</a>',
                                    esc_url(add_query_arg('paged', $i)),
                                    $class,
                                    $i
                                );
                            }
                            ?>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>

        <style>
            .ai-feedback-filters {
                margin: 20px 0;
                display: flex;
                gap: 15px;
            }
            
            .filter-select {
                padding: 8px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            
            .ai-feedback-type-section {
                margin-bottom: 30px;
            }
            
            .type-header {
                color: #1d2327;
                border-bottom: 2px solid #2271b1;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            
            .ai-feedback-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .ai-feedback-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .card-header {
                background: #f8f9fa;
                padding: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .card-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .card-date {
                color: #666;
                font-size: 0.9em;
            }
            
            .card-section {
                padding: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .card-section:last-child {
                border-bottom: none;
            }
            
            .card-section h4 {
                margin: 0 0 10px 0;
                color: #1d2327;
            }
            
            .summary-text {
                margin: 0;
                line-height: 1.5;
                color: #666;
            }
            
            .key-points {
                margin: 0;
                padding-left: 20px;
                color: #666;
            }
            
            .key-points li {
                margin-bottom: 5px;
                line-height: 1.4;
            }
            
            .sentiment-bars {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .sentiment-bar-container {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .sentiment-label {
                display: flex;
                justify-content: space-between;
                font-size: 0.9em;
                color: #666;
            }
            
            .sentiment-bar {
                height: 8px;
                border-radius: 4px;
                transition: width 0.3s ease;
            }
            
            .aspects-cloud, .topics-cloud {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .aspect-tag, .topic-tag {
                background: #f0f0f0;
                padding: 4px 8px;
                border-radius: 4px;
                color: #666;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            .aspect-count, .topic-count {
                background: #fff;
                color: #666;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 0.8em;
            }
            
            .ai-feedback-pagination {
                display: flex;
                justify-content: center;
                gap: 5px;
                margin-top: 20px;
            }
            
            .page-number {
                display: inline-block;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                text-decoration: none;
                color: #2271b1;
            }
            
            .page-number.current {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
            }
            
            .page-number:hover:not(.current) {
                background: #f0f0f0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Filtrování podle typu
            $('#type-filter').on('change', function() {
                var selectedType = $(this).val();
                if (selectedType) {
                    $('.ai-feedback-type-section').hide();
                    $('.ai-feedback-type-section[data-type="' + selectedType + '"]').show();
                } else {
                    $('.ai-feedback-type-section').show();
                }
            });
            
            // Řazení
            $('#sort-order').on('change', function() {
                var order = $(this).val();
                $('.ai-feedback-grid').each(function() {
                    var cards = $(this).children('.ai-feedback-card').get();
                    cards.sort(function(a, b) {
                        var dateA = new Date($(a).find('.card-date').text().split(' ')[0].split('.').reverse().join('-'));
                        var dateB = new Date($(b).find('.card-date').text().split(' ')[0].split('.').reverse().join('-'));
                        return order === 'newest' ? dateB - dateA : dateA - dateB;
                    });
                    $(this).append(cards);
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Vykreslení metaboxu se souhrnnými statistikami
     */
    public function render_summary_metabox() {
        $stats = $this->data->get_summary_statistics();
        ?>
        <div class="ai-feedback-summary">
            <div class="ai-feedback-stat-grid">
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_participants'] ?? 0); ?></span>
                    <span class="ai-feedback-stat-label">Celkem účastníků</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_entries'] ?? 0); ?></span>
                    <span class="ai-feedback-stat-label">Celkem zápisků</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['avg_entries_per_participant'] ?? 0); ?></span>
                    <span class="ai-feedback-stat-label">Průměr zápisků na účastníka</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_references'] ?? 0); ?></span>
                    <span class="ai-feedback-stat-label">Celkem referencí</span>
                </div>
            </div>

            <div class="ai-feedback-chart-container">
                <div class="ai-feedback-chart-header">
                    <h3>Přehled aktivity v čase</h3>
                    <button type="button" class="button button-secondary ai-analyze-button" data-analysis="activity_overview">
                        <span class="dashicons dashicons-update"></span> Analyzovat
                    </button>
                </div>
                <canvas id="activityChart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Vykreslení metaboxu s analýzou klíčových témat
     */
    public function render_topics_metabox() {
        ?>
        <div class="ai-feedback-topics">
            <div class="ai-feedback-chart-header">
                <p>Analýza poznámek účastníků vzdělávacího programu.</p>
                <button type="button" class="button button-secondary ai-analyze-button" data-analysis="topics">
                    <span class="dashicons dashicons-update"></span> Analyzovat
                </button>
            </div>
            
            <div id="topics-summary"></div>
            
            <div class="ai-feedback-chart-container">
                <h3>Četnost témat</h3>
                <canvas id="topicsChart"></canvas>
            </div>
            
            <div id="key-learnings-list"></div>
        </div>
        <?php
    }
    
    /**
     * Vykreslení metaboxu s analýzou porozumění
     */
    public function render_understanding_metabox() {
        ?>
        <div class="ai-feedback-understanding">
            <div class="ai-feedback-chart-header">
                <p>Analýza míry porozumění a aplikace znalostí účastníky.</p>
                <button type="button" class="button button-secondary ai-analyze-button" data-analysis="understanding">
                    <span class="dashicons dashicons-update"></span> Analyzovat
                </button>
            </div>
            
            <div class="ai-feedback-charts-grid">
                <div class="chart-container">
                    <h3>Úroveň porozumění</h3>
                    <canvas id="understandingChart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3>Kvalita aplikace znalostí</h3>
                    <canvas id="applicationChart"></canvas>
                </div>
            </div>
            
            <div class="ai-feedback-suggestions">
                <h3>Doporučení pro zlepšení</h3>
                <div id="improvementSuggestions">
                    <p>Klikněte na tlačítko "Analyzovat" pro zobrazení doporučení.</p>
                </div>
            </div>
        </div>
        
        <style>
            .ai-feedback-charts-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .chart-container {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .chart-container h3 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #2c3338;
                font-size: 16px;
            }
            
            .key-learning-item, .improvement-suggestion-item {
                margin-bottom: 10px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
                color: #2c3338;
            }
            
            .analysis-summary {
                background: #f8f9ff;
                padding: 20px;
                margin: 20px 0;
                border-radius: 8px;
                border-left: 4px solid #6c5ce7;
            }
            
            .analysis-summary h3 {
                margin-top: 0;
                color: #2c3338;
                font-size: 16px;
            }
            
            .analysis-summary p {
                margin: 10px 0 0;
                color: #4a5568;
                line-height: 1.6;
            }
            
            .key-learnings-list, .improvement-suggestions-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .key-learnings-list li, .improvement-suggestions-list li {
                position: relative;
                padding-left: 24px;
                margin-bottom: 12px;
            }
            
            .key-learnings-list li:before, .improvement-suggestions-list li:before {
                content: "•";
                color: #6c5ce7;
                font-size: 20px;
                position: absolute;
                left: 8px;
                top: -2px;
            }
        </style>
        <?php
    }
    
    /**
     * Vykreslení metaboxu s analýzou sentimentu
     */
    public function render_sentiment_metabox() {
        ?>
        <div class="ai-feedback-sentiment">
            <div class="ai-feedback-chart-header">
                <p>Analýza sentimentu a zpětné vazby z referencí.</p>
                <button type="button" class="button button-secondary ai-analyze-button" data-analysis="sentiment">
                    <span class="dashicons dashicons-update"></span> Analyzovat
                </button>
            </div>
            
            <div class="chart-container">
                <canvas id="sentimentChart"></canvas>
            </div>
            
            <div class="ai-feedback-aspects">
                <h3>Oceňované aspekty</h3>
                <div id="aspectsContainer">
                    <p>Klikněte na tlačítko "Analyzovat" pro zobrazení oceňovaných aspektů.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Vykreslení metaboxu pro export dat
     */
    public function render_export_metabox() {
        ?>
        <div class="ai-feedback-export">
            <p>Exportujte data pro další analýzu nebo archivaci.</p>
            
            <h4>Export reflexí</h4>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="ai_feedback_export_data">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ai_feedback_analytics_nonce'); ?>">
                <input type="hidden" name="export_type" value="reflexe">
                
                <div class="ai-feedback-export-filters">
                    <label for="export_date_from">Od:</label>
                    <input type="date" id="export_date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                    
                    <label for="export_date_to">Do:</label>
                    <input type="date" id="export_date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                </div>
                
                <div class="ai-feedback-export-buttons">
                    <button type="submit" name="format" value="csv" class="button">CSV export</button>
                    <button type="submit" name="format" value="excel" class="button">Excel export</button>
                </div>
            </form>
            
            <h4>Export referencí</h4>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="ai_feedback_export_data">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ai_feedback_analytics_nonce'); ?>">
                <input type="hidden" name="export_type" value="reference">
                
                <div class="ai-feedback-export-filters">
                    <label for="export_ref_date_from">Od:</label>
                    <input type="date" id="export_ref_date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                    
                    <label for="export_ref_date_to">Do:</label>
                    <input type="date" id="export_ref_date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                </div>
                
                <div class="ai-feedback-export-buttons">
                    <button type="submit" name="format" value="csv" class="button">CSV export</button>
                    <button type="submit" name="format" value="excel" class="button">Excel export</button>
                </div>
            </form>
            
            <h4>Generování PDF reportu</h4>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="ai-feedback-report-form">
                <input type="hidden" name="action" value="ai_feedback_generate_report">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ai_feedback_analytics_nonce'); ?>">
                
                <div class="ai-feedback-export-filters">
                    <label for="report_date_from">Od:</label>
                    <input type="date" id="report_date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                    
                    <label for="report_date_to">Do:</label>
                    <input type="date" id="report_date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                    
                    <label for="report_title">Název reportu:</label>
                    <input type="text" id="report_title" name="report_title" placeholder="Zadejte název reportu">
                </div>
                
                <div class="ai-feedback-export-buttons">
                    <button type="submit" class="button">Generovat PDF</button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Vykreslení stránky s logy
     */
    public function render_logs_page() {
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.'));
        }
        
        // Získání logů
        $logs = $this->api->get_api_logs();
        $total_tokens = $this->api->get_total_tokens_used();
        
        ?>
        <div class="wrap">
            <h1>Logy API požadavků</h1>
            
            <div class="ai-feedback-logs-summary">
                <h2>Souhrnné statistiky</h2>
                <p>Celkový počet použitých tokenů: <strong><?php echo number_format($total_tokens); ?></strong></p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Typ požadavku</th>
                        <th>Datum</th>
                        <th>Počet tokenů</th>
                        <th>Doba zpracování</th>
                        <th>Stav</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7">Žádné logy nebyly nalezeny.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['id'] ?? ''); ?></td>
                                <td><?php echo esc_html($log['request_type'] ?? ''); ?></td>
                                <td><?php echo esc_html($log['created_at'] ?? ''); ?></td>
                                <td><?php echo esc_html($log['tokens_used'] ? number_format($log['tokens_used']) : '0'); ?></td>
                                <td><?php echo esc_html($log['execution_time'] ? number_format($log['execution_time'], 2) : '0.00'); ?>s</td>
                                <td>
                                    <?php if (empty($log['error_message'])): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span> Úspěšné
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span> Chyba
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small view-log-details" data-log-id="<?php echo esc_attr($log['id'] ?? ''); ?>">
                                        Zobrazit detaily
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Modal pro zobrazení detailů logu -->
            <div id="log-details-modal" class="ai-feedback-modal" style="display: none;">
                <div class="ai-feedback-modal-content">
                    <span class="ai-feedback-modal-close">&times;</span>
                    <h2>Detaily logu</h2>
                    <div class="ai-feedback-modal-body">
                        <h3>Požadavek</h3>
                        <pre class="ai-feedback-log-data"></pre>
                        
                        <h3>Odpověď</h3>
                        <pre class="ai-feedback-log-response"></pre>
                        
                        <h3>Chyba</h3>
                        <pre class="ai-feedback-log-error"></pre>
                    </div>
                </div>
            </div>
            
            <style>
                .ai-feedback-logs-summary {
                    background: #fff;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                
                .ai-feedback-modal {
                    display: none;
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                }
                
                .ai-feedback-modal-content {
                    background-color: #fff;
                    margin: 5% auto;
                    padding: 20px;
                    width: 80%;
                    max-width: 800px;
                    border-radius: 4px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                
                .ai-feedback-modal-close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                
                .ai-feedback-modal-close:hover {
                    color: #000;
                }
                
                .ai-feedback-modal-body {
                    margin-top: 20px;
                }
                
                .ai-feedback-modal-body pre {
                    background: #f5f5f5;
                    padding: 10px;
                    border-radius: 4px;
                    overflow-x: auto;
                }
            </style>
            
            <script>
                jQuery(document).ready(function($) {
                    $('.view-log-details').on('click', function() {
                        var logId = $(this).data('log-id');
                        
                        // Zobrazení modálního okna
                        $('#log-details-modal').show();
                        
                        // Načtení detailů logu
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_feedback_get_log_details',
                                nonce: '<?php echo wp_create_nonce('ai_feedback_analytics_nonce'); ?>',
                                log_id: logId
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('.ai-feedback-log-data').text(JSON.stringify(response.data.request_data || {}, null, 2));
                                    $('.ai-feedback-log-response').text(JSON.stringify(response.data.response_data || {}, null, 2));
                                    $('.ai-feedback-log-error').text(response.data.error_message || 'Žádná chyba');
                                } else {
                                    alert('Chyba při načítání detailů: ' + (response.data?.message || 'Neznámá chyba'));
                                }
                            },
                            error: function() {
                                alert('Chyba při komunikaci se serverem');
                            }
                        });
                    });
                    
                    $('.ai-feedback-modal-close').on('click', function() {
                        $('#log-details-modal').hide();
                    });
                    
                    $(window).on('click', function(event) {
                        if ($(event.target).is('#log-details-modal')) {
                            $('#log-details-modal').hide();
                        }
                    });
                });
            </script>
        </div>
        <?php
    }
}