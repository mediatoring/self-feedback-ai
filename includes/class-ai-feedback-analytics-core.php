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
    private function __construct() {
        // Vytvoření instancí ostatních tříd
        $this->data = new AI_Feedback_Analytics_Data();
        $this->admin = new AI_Feedback_Analytics_Admin();
        $this->api = new AI_Feedback_Analytics_API();
        $this->export = new AI_Feedback_Analytics_Export();
    }
    
    /**
     * Získání instance třídy (Singleton pattern)
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializace modulu
     */
    public function init() {
        // Inicializace ostatních tříd
        $this->data->init();
        $this->admin->init($this->data, $this->api, $this->export);
        $this->api->init($this->data);
        $this->export->init($this->data);
        
        // Registrace AJAX handlerů
        add_action('wp_ajax_ai_feedback_analyze', [$this, 'handle_analyze_request']);
        add_action('wp_ajax_ai_feedback_export_data', [$this, 'handle_export_request']);
        add_action('wp_ajax_ai_feedback_generate_report', [$this, 'handle_report_request']);
    }
    
    /**
     * Zpracování AJAX požadavku na analýzu
     */
    public function handle_analyze_request() {
        // Kontrola nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_feedback_analytics_nonce')) {
            wp_send_json_error('Neplatný bezpečnostní token.');
        }

        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nemáte dostatečná oprávnění.');
        }

        $analysis_type = isset($_POST['analysis_type']) ? sanitize_text_field($_POST['analysis_type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

        // Zpracování podle typu analýzy
        switch ($analysis_type) {
            case 'activity_overview':
                $data = $this->data->get_activity_data($date_from, $date_to, $keyword);
                break;
            case 'topics':
                $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                $data = $this->api->analyze_topics($entries);
                break;
            case 'understanding':
                $entries = $this->data->get_entries_data($date_from, $date_to, $keyword);
                $data = $this->api->analyze_understanding($entries);
                break;
            case 'sentiment':
                $references = $this->data->get_references_data($date_from, $date_to, $keyword);
                $data = $this->api->analyze_sentiment($references);
                break;
            default:
                wp_send_json_error('Neznámý typ analýzy.');
                return;
        }

        wp_send_json_success($data);
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
}