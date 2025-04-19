<?php
/**
 * AI Feedback Analytics
 * 
 * Analytický modul pro AI Feedback & Reference Plugin.
 * Poskytuje pokročilou analýzu dat z reflexí a referencí pomocí GPT-4.1.
 */

defined('ABSPATH') || exit;

// Načtení tříd
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-data.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-export.php';

/**
 * Inicializace modulu pro analytikou
 */
function ai_feedback_analytics_init() {
    // Vytvoření instance a zahájení činnosti modulu
    $analytics = AI_Feedback_Analytics_Core::get_instance();
    $analytics->init();
}
add_action('plugins_loaded', 'ai_feedback_analytics_init');