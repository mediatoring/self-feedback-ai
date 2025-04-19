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
     * 
     * Zpracovává AJAX požadavek z JS kódu
     */
    public function handle_analyze_feedback() {
        // Debugging - zapíše data požadavku do log souboru
        error_log('DEBUG AJAX REQUEST: ' . print_r($_POST, true));
        error_log('DEBUG SERVER: ' . print_r($_SERVER, true));
        
        // Kontrola nonce
        if (!isset($_POST['nonce'])) {
            error_log('NONCE MISSING');
            wp_send_json_error('Chybí bezpečnostní token (nonce).');
            return;
        }
        
        // Kontrola nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            error_log('INVALID NONCE: ' . $_POST['nonce']);
            wp_send_json_error('Neplatný bezpečnostní token.');
            return;
        }
        
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            error_log('INSUFFICIENT PERMISSIONS');
            wp_send_json_error('Nemáte dostatečná oprávnění.');
            return;
        }
        
        $this->handle_analyze_request();
    }
    
    /**
     * Zpracování AJAX požadavku na analýzu
     */
    private function handle_analyze_request() {
        // Detailní logování všech vstupních parametrů
        error_log('ANALYZE REQUEST PARAMETERS: ' . print_r($_POST, true));
        error_log('SERVER REQUEST: ' . print_r($_SERVER, true));
        
        // Kontrola nonce - upravená pro lepší diagnostiku
        if (!isset($_POST['nonce'])) {
            error_log('NONCE MISSING');
            wp_send_json_error('Chybí bezpečnostní token (nonce).');
            return;
        }
        
        // Kontrola nonce
        $nonce_verification = wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce');
        if ($nonce_verification === false) {
            error_log('NONCE INVALID: ' . $_POST['nonce']);
            wp_send_json_error('Neplatný bezpečnostní token. Zkuste obnovit stránku.');
            return;
        } else {
            error_log('NONCE VERIFIED: ' . $nonce_verification);
        }

        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nemáte dostatečná oprávnění.');
            return;
        }

        $analysis_type = isset($_POST['analysis_type']) ? sanitize_text_field($_POST['analysis_type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

        // Kontrola API klíče před analýzou
        if (empty(get_option('ai_plugin_apikey'))) {
            wp_send_json_error('Chybí API klíč. Nastavte jej v nastavení pluginu.');
            return;
        }

        // Zpracování podle typu analýzy
        switch ($analysis_type) {
            case 'activity_overview':
                $data = $this->data->get_activity_data($date_from, $date_to, $keyword);
                break;
            case 'topics':
                $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                
                if (empty($entries)) {
                    wp_send_json_error('Nebyla nalezena žádná data pro analýzu.');
                    return;
                }
                
                $result = $this->api->analyze_entries_with_gpt($entries, 'topics');
                
                if ($result === false) {
                    global $wpdb;
                    // Zjistím poslední chybu z logů
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
                
                $data = $result['data'];
                break;
            case 'understanding':
                $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                
                if (empty($entries)) {
                    wp_send_json_error('Nebyla nalezena žádná data pro analýzu porozumění.');
                    return;
                }
                
                $result = $this->api->analyze_entries_with_gpt($entries, 'understanding');
                
                if ($result === false) {
                    global $wpdb;
                    // Zjistím poslední chybu z logů
                    $last_log = $wpdb->get_row(
                        "SELECT * FROM {$wpdb->prefix}ai_feedback_api_logs 
                        WHERE error_message != '' 
                        ORDER BY id DESC 
                        LIMIT 1",
                        ARRAY_A
                    );
                    
                    $error_message = 'Chyba při analýze porozumění.';
                    
                    if (!empty($last_log) && !empty($last_log['error_message'])) {
                        $error_message .= ' ' . $last_log['error_message'];
                    }
                    
                    wp_send_json_error($error_message);
                    return;
                }
                
                $data = $result['data'];
                break;
            case 'sentiment':
                $references = $this->data->get_references_data($date_from, $date_to, $keyword);
                
                if (empty($references)) {
                    wp_send_json_error('Nebyla nalezena žádná data pro analýzu sentimentu.');
                    return;
                }
                
                $result = $this->api->analyze_references_with_gpt($references);
                
                if ($result === false) {
                    global $wpdb;
                    // Zjistím poslední chybu z logů
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
                
                $data = $result;
                break;
            default:
                wp_send_json_error('Neznámý typ analýzy.');
                return;
        }

        wp_send_json_success($data);
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
        check_ajax_referer('ai_feedback_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nemáte dostatečná oprávnění pro tuto akci.']);
        }
        
        $log_id = intval($_POST['log_id']);
        if (!$log_id) {
            wp_send_json_error(['message' => 'Neplatné ID logu.']);
        }
        
        try {
            $log = $this->api->get_log_details($log_id);
            wp_send_json_success($log);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handler pro AJAX požadavek na re-analýzu všech příspěvků
     */
    public function handle_reanalyze_all_posts() {
        // Debugging - zapíše data požadavku do log souboru
        error_log('DEBUG REANALYZE AJAX REQUEST: ' . print_r($_POST, true));
        
        // Kontrola nonce
        if (!isset($_POST['nonce'])) {
            error_log('REANALYZE NONCE MISSING');
            wp_send_json_error('Chybí bezpečnostní token (nonce).');
            return;
        }
        
        // Kontrola nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            error_log('REANALYZE INVALID NONCE: ' . $_POST['nonce']);
            wp_send_json_error('Neplatný bezpečnostní token. Zkuste obnovit stránku.');
            return;
        }
        
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            error_log('REANALYZE INSUFFICIENT PERMISSIONS');
            wp_send_json_error('Nemáte dostatečná oprávnění.');
            return;
        }
        
        // Kontrola API klíče
        if (empty(get_option('ai_plugin_apikey'))) {
            error_log('REANALYZE MISSING API KEY');
            wp_send_json_error('Chybí API klíč. Nastavte jej v nastavení pluginu.');
            return;
        }
        
        try {
            // Spuštění re-analýzy
            $results = $this->api->reanalyze_all_posts();
            
            // Kontrola výsledků
            if ($results['errors'] > 0) {
                error_log('REANALYZE ERRORS: ' . print_r($results['error_messages'], true));
                wp_send_json_error('Během re-analýzy došlo k chybám: ' . implode(', ', $results['error_messages']));
                return;
            }
            
            // Úspěšné dokončení
            error_log('REANALYZE SUCCESS: ' . print_r($results, true));
            wp_send_json_success([
                'message' => sprintf(
                    'Re-analýza dokončena. Zpracováno %d z %d příspěvků.',
                    $results['analyzed'],
                    $results['total']
                )
            ]);
        } catch (Exception $e) {
            error_log('REANALYZE EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Chyba při re-analýze: ' . $e->getMessage());
        }
    }
}