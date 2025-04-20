<?php
/**
 * Hlavní třída analytického modulu
 * 
 * Řídí inicializaci, zpracování a propojení ostatních tříd modulu.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_Core {
    /**
     * Instance třídy (Singleton pattern)
     * 
     * @var AI_Feedback_Analytics_Core
     */
    private static $instance = null;
    
    /**
     * Instance třídy data
     * 
     * @var AI_Feedback_Analytics_Data
     */
    private $data;
    
    /**
     * Instance třídy admin
     * 
     * @var AI_Feedback_Analytics_Admin
     */
    private $admin;
    
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
     * Konstruktor třídy
     */
    public function __construct() {
        // Vytvoření instancí v pořadí podle závislostí
        $this->data = new AI_Feedback_Analytics_Data();
        $this->api = new AI_Feedback_Analytics_API();
        $this->export = new AI_Feedback_Analytics_Export();
        $this->admin = AI_Feedback_Analytics_Admin::get_instance();
    }
    
    /**
     * Získání instance třídy (Singleton pattern)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializace modulu
     */
    public function init() {
        // Inicializace jednotlivých modulů
        $this->data->init();
        $this->api->init($this->data);
        $this->export->init();
        $this->admin->init($this->data, $this->api, $this->export);

        // Registrace AJAX handlerů
        add_action('wp_ajax_analyze_feedback', array($this, 'handle_analyze_feedback'));
        add_action('wp_ajax_export_analytics', array($this, 'handle_export_analytics'));
        add_action('wp_ajax_reanalyze_all_posts', array($this, 'handle_reanalyze_all_posts'));
        add_action('wp_ajax_ai_feedback_get_log_details', array($this, 'handle_get_log_details'));
    }
    
    /**
     * Handler pro AJAX požadavek na analýzu zpětné vazby
     */
    public function handle_analyze_feedback() {
        try {
            error_log('AI Feedback Analytics: Začátek AJAX požadavku na analýzu');
            error_log('POST data: ' . print_r($_POST, true));
            
            // Kontrola nonce
            if (!isset($_POST['nonce'])) {
                error_log('AI Feedback Analytics: Chybí nonce');
                wp_send_json_error('Chybí bezpečnostní token.');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
                error_log('AI Feedback Analytics: Neplatný nonce');
                wp_send_json_error('Neplatný bezpečnostní token.');
                return;
            }
            
            // Kontrola oprávnění
            if (!current_user_can('manage_options')) {
                error_log('AI Feedback Analytics: Nedostatečná oprávnění');
                wp_send_json_error('Nemáte dostatečná oprávnění.');
                return;
            }

            $analysis_type = isset($_POST['analysis_type']) ? sanitize_text_field($_POST['analysis_type']) : '';
            $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
            $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

            error_log('AI Feedback Analytics: Typ analýzy: ' . $analysis_type);
            
            // Zpracování podle typu analýzy
            switch ($analysis_type) {
                case 'activity_overview':
                    error_log('AI Feedback Analytics: Získávání dat o aktivitě');
                    $data = $this->data->get_activity_data($date_from, $date_to, $keyword);
                    break;
                    
                case 'topics':
                    error_log('AI Feedback Analytics: Získávání dat zápisků pro analýzu témat');
                    $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                    
                    if (empty($entries)) {
                        error_log('AI Feedback Analytics: Žádná data pro analýzu');
                        wp_send_json_error('Nebyla nalezena žádná data pro analýzu.');
                        return;
                    }
                    
                    error_log('AI Feedback Analytics: Počet zápisků pro analýzu témat: ' . count($entries));
                    
                    try {
                        $result = $this->api->analyze_entries_with_gpt($entries, 'topics');
                        error_log('AI Feedback Analytics: Výsledek analýzy témat: ' . print_r($result, true));
                        
                        if ($result === false) {
                            error_log('AI Feedback Analytics: Analýza témat selhala');
                            throw new Exception('Analýza témat selhala');
                        }
                        
                        $data = $result['data'];
                    } catch (Exception $e) {
                        error_log('AI Feedback Analytics: Chyba při analýze témat: ' . $e->getMessage());
                        wp_send_json_error('Chyba při analýze témat: ' . $e->getMessage());
                        return;
                    }
                    break;
                    
                case 'understanding':
                    error_log('AI Feedback Analytics: Získávání dat zápisků');
                    $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                    
                    if (empty($entries)) {
                        error_log('AI Feedback Analytics: Žádná data pro analýzu');
                        wp_send_json_error('Nebyla nalezena žádná data pro analýzu.');
                        return;
                    }
                    
                    error_log('AI Feedback Analytics: Počet zápisků pro analýzu: ' . count($entries));
                    error_log('AI Feedback Analytics: Volání GPT analýzy');
                    
                    try {
                        $result = $this->api->analyze_entries_with_gpt($entries, $analysis_type);
                        error_log('AI Feedback Analytics: Výsledek GPT analýzy: ' . print_r($result, true));
                        
                        if ($result === false) {
                            error_log('AI Feedback Analytics: GPT analýza selhala');
                            throw new Exception('Analýza selhala');
                        }
                        
                        $data = $result['data'];
                    } catch (Exception $e) {
                        error_log('AI Feedback Analytics: Chyba při GPT analýze: ' . $e->getMessage());
                        error_log('AI Feedback Analytics: Stack trace: ' . $e->getTraceAsString());
                        
                        global $wpdb;
                        $last_log = $wpdb->get_row(
                            "SELECT * FROM {$wpdb->prefix}ai_feedback_api_logs 
                            WHERE error_message != '' 
                            ORDER BY id DESC 
                            LIMIT 1",
                            ARRAY_A
                        );
                        
                        $error_message = 'Chyba při analýze.';
                        if (!empty($last_log) && !empty($last_log['error_message'])) {
                            $error_message .= ' ' . $last_log['error_message'];
                        }
                        
                        wp_send_json_error($error_message);
                        return;
                    }
                    break;
                    
                case 'sentiment':
                    error_log('AI Feedback Analytics: Získávání dat referencí');
                    $references = $this->data->get_references_data($date_from, $date_to, $keyword);
                    
                    if (empty($references)) {
                        error_log('AI Feedback Analytics: Žádné reference pro analýzu');
                        wp_send_json_error('Nebyla nalezena žádná data pro analýzu sentimentu.');
                        return;
                    }
                    
                    error_log('AI Feedback Analytics: Počet referencí pro analýzu: ' . count($references));
                    error_log('AI Feedback Analytics: Volání GPT analýzy sentimentu');
                    
                    try {
                        $result = $this->api->analyze_references_with_gpt($references);
                        error_log('AI Feedback Analytics: Výsledek GPT analýzy sentimentu: ' . print_r($result, true));
                        
                        if ($result === false) {
                            error_log('AI Feedback Analytics: GPT analýza sentimentu selhala');
                            throw new Exception('Analýza sentimentu selhala');
                        }
                        
                        $data = $result;
                    } catch (Exception $e) {
                        error_log('AI Feedback Analytics: Chyba při GPT analýze sentimentu: ' . $e->getMessage());
                        error_log('AI Feedback Analytics: Stack trace: ' . $e->getTraceAsString());
                        
                        global $wpdb;
                        $last_log = $wpdb->get_row(
                            "SELECT * FROM {$wpdb->prefix}ai_feedback_api_logs 
                            WHERE error_message != '' 
                            ORDER BY id DESC 
                            LIMIT 1",
                            ARRAY_A
                        );
                        
                        $error_message = 'Chyba při analýze sentimentu.';
                        if (!empty($last_log) && !empty($last_log['error_message'])) {
                            $error_message .= ' ' . $last_log['error_message'];
                        }
                        
                        wp_send_json_error($error_message);
                        return;
                    }
                    break;
                    
                default:
                    error_log('AI Feedback Analytics: Neznámý typ analýzy: ' . $analysis_type);
                    wp_send_json_error('Neznámý typ analýzy.');
                    return;
            }

            error_log('AI Feedback Analytics: Odesílání úspěšné odpovědi');
            error_log('AI Feedback Analytics: Data: ' . print_r($data, true));
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('AI Feedback Analytics: Neočekávaná chyba: ' . $e->getMessage());
            error_log('AI Feedback Analytics: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Došlo k neočekávané chybě: ' . $e->getMessage());
        }
    }
    
    /**
     * Handler pro AJAX požadavek na export analytiky
     * 
     * Zpracovává AJAX požadavek z JS kódu
     */
    public function handle_export_analytics() {
        $this->handle_export_request();
    }
    
    /**
     * Zpracování AJAX požadavku na export
     */
    public function handle_export_request() {
        // Kontrola nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            wp_die('Neplatný bezpečnostní token.');
        }

        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte dostatečná oprávnění.');
        }

        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Export podle typu
        switch ($export_type) {
            case 'reflexe':
                $data = $this->data->get_reflexe_export_data($date_from, $date_to);
                $filename = 'reflexe-export-' . date('Y-m-d');
                break;
            case 'reference':
                $data = $this->data->get_reference_export_data($date_from, $date_to);
                $filename = 'reference-export-' . date('Y-m-d');
                break;
            default:
                wp_die('Neznámý typ exportu.');
        }

        // Export podle formátu
        switch ($format) {
            case 'csv':
                $this->export->export_as_csv($data, $filename);
                break;
            case 'excel':
                $this->export->export_as_excel($data, $filename);
                break;
            default:
                wp_die('Neznámý formát exportu.');
        }
    }
    
    /**
     * Zpracování AJAX požadavku na generování reportu
     */
    public function handle_report_request() {
        // Kontrola nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            wp_die('Neplatný bezpečnostní token.');
        }

        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte dostatečná oprávnění.');
        }

        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $report_title = isset($_POST['report_title']) ? sanitize_text_field($_POST['report_title']) : 'Analytický report';

        // Generování reportu
        $this->export->generate_pdf_report($this->data, $date_from, $date_to, $report_title);
    }
    
    /**
     * Handler pro znovu analýzu všech příspěvků
     */
    public function handle_reanalyze_all() {
        check_ajax_referer('ai_feedback_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nemáte dostatečná oprávnění pro tuto akci.']);
        }
        
        try {
            $results = $this->api->reanalyze_all_posts();
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handler pro získání detailů logu
     */
    public function handle_get_log_details() {
        // Kontrola nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token.']);
            return;
        }

        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nemáte dostatečná oprávnění.']);
            return;
        }

        // Kontrola ID logu
        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) {
            wp_send_json_error(['message' => 'Chybí ID logu.']);
            return;
        }

        try {
            global $wpdb;
            $log = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_feedback_api_logs WHERE id = %d",
                    $log_id
                ),
                ARRAY_A
            );

            if (!$log) {
                wp_send_json_error(['message' => 'Log nebyl nalezen.']);
                return;
            }

            wp_send_json_success($log);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Chyba při načítání detailů logu: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handler pro AJAX požadavek na re-analýzu všech příspěvků
     */
    public function handle_reanalyze_all_posts() {
        try {
            // Detailní logování požadavku
            error_log('REANALYZE REQUEST START: ' . print_r($_POST, true));
            error_log('REANALYZE SERVER: ' . print_r($_SERVER, true));
            
            // Kontrola nonce
            if (!isset($_POST['nonce'])) {
                error_log('REANALYZE: Missing nonce');
                wp_send_json_error(['message' => 'Chybí bezpečnostní token.']);
                return;
            }

            if (!wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
                error_log('REANALYZE: Invalid nonce: ' . $_POST['nonce']);
                wp_send_json_error(['message' => 'Neplatný bezpečnostní token.']);
                return;
            }

            // Kontrola oprávnění
            if (!current_user_can('manage_options')) {
                error_log('REANALYZE: Insufficient permissions');
                wp_send_json_error(['message' => 'Nemáte dostatečná oprávnění pro tuto akci.']);
                return;
            }

            // Kontrola instance API
            if (!isset($this->api) || !is_object($this->api)) {
                error_log('REANALYZE: API instance not available');
                wp_send_json_error(['message' => 'Interní chyba: API instance není dostupná.']);
                return;
            }
            
            // Kontrola API klíče
            if (empty(get_option('ai_plugin_apikey'))) {
                error_log('REANALYZE: Missing API key');
                wp_send_json_error(['message' => 'Chybí API klíč. Nastavte jej v nastavení pluginu.']);
                return;
            }
            
            // Spuštění re-analýzy
            $result = $this->api->reanalyze_all_posts();
            error_log('REANALYZE RESULT: ' . print_r($result, true));
            
            if (!is_array($result)) {
                wp_send_json_error(['message' => 'Neočekávaný formát odpovědi od API.']);
                return;
            }
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success([
                    'message' => sprintf(
                        'Re-analýza dokončena. Zpracováno %d příspěvků, %d chyb.',
                        $result['total_posts'],
                        $result['errors']
                    ),
                    'data' => $result
                ]);
            } else {
                $error_message = isset($result['error_messages']) ? implode(', ', $result['error_messages']) : 'Neznámá chyba';
                wp_send_json_error([
                    'message' => 'Chyba při re-analýze: ' . $error_message
                ]);
            }
        } catch (Exception $e) {
            error_log('REANALYZE ERROR: ' . $e->getMessage());
            error_log('REANALYZE STACK TRACE: ' . $e->getTraceAsString());
            wp_send_json_error([
                'message' => 'Došlo k neočekávané chybě: ' . $e->getMessage()
            ]);
        }
    }
}