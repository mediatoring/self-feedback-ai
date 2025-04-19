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

        // Předání dat do JavaScriptu
        wp_localize_script(
            'ai-feedback-analytics',
            'aiFeedbackAnalytics',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_feedback_analytics_nonce'),
            ]
        );
    }
    
    /**
     * Přidání metaboxů do dashboardu
     */
    public function add_dashboard_metaboxes() {
        add_meta_box(
            'ai_feedback_summary',
            'Souhrnné statistiky',
            [$this, 'render_summary_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'high'
        );

        add_meta_box(
            'ai_feedback_topics',
            'Klíčová témata a koncepty',
            [$this, 'render_topics_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'default'
        );

        add_meta_box(
            'ai_feedback_understanding',
            'Analýza porozumění',
            [$this, 'render_understanding_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'normal',
            'default'
        );

        add_meta_box(
            'ai_feedback_sentiment',
            'Analýza sentimentu referencí',
            [$this, 'render_sentiment_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'side',
            'default'
        );

        add_meta_box(
            'ai_feedback_export',
            'Export dat',
            [$this, 'render_export_metabox'],
            'toplevel_page_ai-feedback-analytics',
            'side',
            'default'
        );
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
        <div class="wrap ai-feedback-analytics">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ai-feedback-filter">
                <form method="get" id="ai-feedback-filter-form">
                    <input type="hidden" name="page" value="ai-feedback-analytics">
                    
                    <label for="date_from">Od:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                    
                    <label for="date_to">Do:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                    
                    <label for="keyword">Klíčové slovo:</label>
                    <input type="text" id="keyword" name="keyword" value="<?php echo isset($_GET['keyword']) ? esc_attr($_GET['keyword']) : ''; ?>">
                    
                    <?php submit_button('Filtrovat', 'secondary', 'filter', false); ?>
                    <button type="button" id="reset-filters" class="button button-secondary">Resetovat filtry</button>
                    <button type="button" id="reanalyze-all" class="button button-primary">Spustit re-analýzu</button>
                </form>
            </div>

            <div class="ai-feedback-notification"></div>
            <div id="reanalyze-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%"></div>
                </div>
                <div class="progress-status">Zahajuji analýzu...</div>
            </div>

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div id="postbox-container-1" class="postbox-container">
                        <?php do_meta_boxes('toplevel_page_ai-feedback-analytics', 'normal', null); ?>
                    </div>
                    <div id="postbox-container-2" class="postbox-container">
                        <?php do_meta_boxes('toplevel_page_ai-feedback-analytics', 'side', null); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Processing Overlay -->
        <div id="ai-analytics-overlay" style="display: none;">
            <div class="ai-analytics-content">
                <div class="ai-spinner"></div>
                <h3>GPT-4.1 analýza v průběhu</h3>
                <p>Čekejte prosím, analyzuji data pomocí umělé inteligence...</p>
            </div>
        </div>
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
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_participants']); ?></span>
                    <span class="ai-feedback-stat-label">Celkem účastníků</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_entries']); ?></span>
                    <span class="ai-feedback-stat-label">Celkem zápisků</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['avg_entries_per_participant']); ?></span>
                    <span class="ai-feedback-stat-label">Průměr zápisků na účastníka</span>
                </div>
                <div class="ai-feedback-stat-box">
                    <span class="ai-feedback-stat-value"><?php echo esc_html($stats['total_references']); ?></span>
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
            
            <div id="analysis-results">
                <p class="ai-loading-placeholder">Klikněte na tlačítko "Analyzovat" pro zobrazení výsledků analýzy.</p>
            </div>
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
                    <span class="dashicons dashicons-update"></span> Analyzovat porozumění
                </button>
            </div>
            
            <div class="ai-feedback-understanding-grid">
                <div class="ai-feedback-understanding-chart">
                    <h4>Úroveň porozumění</h4>
                    <canvas id="understandingChart"></canvas>
                    <div id="understanding-loading" class="ai-loading-placeholder">
                        <p>Klikněte na tlačítko "Analyzovat porozumění" pro zobrazení grafu úrovně porozumění.</p>
                    </div>
                </div>
                
                <div class="ai-feedback-application-chart">
                    <h4>Kvalita praktických aplikací</h4>
                    <canvas id="applicationChart"></canvas>
                    <div id="application-loading" class="ai-loading-placeholder">
                        <p>Klikněte na tlačítko "Analyzovat porozumění" pro zobrazení grafu kvality praktických aplikací.</p>
                    </div>
                </div>
            </div>
            
            <div class="ai-feedback-improvement-suggestions">
                <h4>Doporučení pro zlepšení</h4>
                <div id="improvementSuggestions">
                    <p class="ai-loading-placeholder">Klikněte na tlačítko "Analyzovat porozumění" pro zobrazení doporučení pro zlepšení.</p>
                </div>
            </div>
        </div>
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
                    <span class="dashicons dashicons-update"></span> Analyzovat sentiment
                </button>
            </div>
            
            <div class="ai-feedback-chart-container">
                <canvas id="sentimentChart"></canvas>
                <div id="sentiment-loading" class="ai-loading-placeholder">
                    <p>Klikněte na tlačítko "Analyzovat sentiment" pro zobrazení grafu sentimentu.</p>
                </div>
            </div>
            
            <div class="ai-feedback-aspects">
                <h4>Nejčastěji oceňované aspekty</h4>
                <div id="aspectsContainer">
                    <p class="ai-loading-placeholder">Klikněte na tlačítko "Analyzovat sentiment" pro zobrazení oceňovaných aspektů.</p>
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
                
                <div class="ai-feedback-reanalyze">
                    <button type="button" class="button button-primary" id="reanalyze-all">
                        <span class="dashicons dashicons-update"></span> Znovu analyzovat všechny příspěvky
                    </button>
                    <div id="reanalyze-progress" style="display: none;">
                        <p>Probíhá analýza... <span id="reanalyze-status"></span></p>
                        <div class="progress-bar">
                            <div class="progress-bar-fill"></div>
                        </div>
                    </div>
                </div>
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
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html($log->request_type); ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td><?php echo esc_html($log->tokens_used); ?></td>
                            <td><?php echo esc_html(number_format($log->execution_time, 2)); ?>s</td>
                            <td>
                                <?php if (empty($log->error_message)): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Úspěšné
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span> Chyba
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small view-log-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                    Zobrazit detaily
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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
                        
                        <?php if (!empty($log->error_message)): ?>
                            <h3>Chyba</h3>
                            <pre class="ai-feedback-log-error"></pre>
                        <?php endif; ?>
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
                
                .ai-feedback-reanalyze {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background-color: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin-top: 10px;
                }
                
                .progress-bar-fill {
                    height: 100%;
                    background-color: #0073aa;
                    width: 0%;
                    transition: width 0.3s ease;
                }
            </style>
            
            <script>
                // Define ajaxurl if it's not already defined
                if (typeof ajaxurl === 'undefined') {
                    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                }
                
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
                                    $('.ai-feedback-log-data').text(JSON.stringify(response.data.request_data, null, 2));
                                    $('.ai-feedback-log-response').text(JSON.stringify(response.data.response_data, null, 2));
                                    if (response.data.error_message) {
                                        $('.ai-feedback-log-error').text(response.data.error_message);
                                    }
                                }
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
                    
                    $('#reanalyze-all').on('click', function() {
                        var $button = $(this);
                        var $progress = $('#reanalyze-progress');
                        var $status = $('#reanalyze-status');
                        var $progressBar = $('.progress-bar-fill');
                        
                        $button.prop('disabled', true);
                        $progress.show();
                        $status.text('Připravuji analýzu...');
                        $progressBar.css('width', '0%');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'reanalyze_all_posts',
                                nonce: '<?php echo wp_create_nonce('ai_feedback_analytics_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.text('Analýza dokončena!');
                                    $progressBar.css('width', '100%');
                                    
                                    if (response.data.errors > 0) {
                                        alert('Analýza dokončena s chybami:\n\n' + response.data.error_messages.join('\n'));
                                    } else {
                                        alert('Analýza úspěšně dokončena!');
                                    }
                                    
                                    // Obnovení stránky po 2 sekundách
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    $status.text('Chyba při analýze!');
                                    alert('Chyba při analýze: ' + response.data.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX error:', xhr.responseText);
                                $status.text('Chyba při komunikaci se serverem!');
                                alert('Chyba při komunikaci se serverem: ' + error);
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
}