<?php
/**
 * Třída pro komunikaci s OpenAI API
 * 
 * Zajišťuje analýzu dat pomocí GPT-4.1 a logování API požadavků.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_API {
    /**
     * Instance třídy (Singleton pattern)
     * 
     * @var AI_Feedback_Analytics_API
     */
    private static $instance = null;
    
    private $api_key;
    private $model;
    private $endpoint = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Instance třídy data
     * 
     * @var AI_Feedback_Analytics_Data
     */
    private $data;
    
    /**
     * Log tabulka v databázi
     * 
     * @var string
     */
    private $log_table;
    
    /**
     * Konstruktor třídy
     */
    public function __construct() {
        global $wpdb;
        $this->api_key = get_option('ai_plugin_apikey');
        $this->model = get_option('ai_plugin_model', 'gpt-4');
        $this->log_table = $wpdb->prefix . 'ai_feedback_api_logs';
    }

    /**
     * Získání instance třídy (Singleton pattern)
     * 
     * @return AI_Feedback_Analytics_API
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
     */
    public function init($data) {
        global $wpdb;
        
        $this->data = $data;
        $this->log_table = $wpdb->prefix . 'ai_feedback_api_logs';
        
        // Vytvoření log tabulky, pokud neexistuje
        $this->create_log_table();
        
        // Kontrola API klíče
        if (empty($this->api_key)) {
            error_log('AI Feedback Analytics: Chybí API klíč');
        }
    }
    
    /**
     * Ověření platnosti API klíče pomocí jednoduchého dotazu
     * 
     * @return bool True, pokud je API klíč platný, jinak false
     */
    public function is_api_key_valid() {
        if (empty($this->api_key)) {
            return false;
        }
        
        // Kontrola, zda je model nastaven
        if (empty($this->model)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => 'Není nastaven model pro OpenAI API',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Příprava velmi jednoduchého dotazu pro ověření klíče
        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Respond with only the text "VALID".'],
                ['role' => 'user', 'content' => 'API key test']
            ],
            'max_tokens' => 10,
            'temperature' => 0.1
        ];
        
        // Volání OpenAI API
        $start_time = microtime(true);
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 10
        ]);
        $execution_time = microtime(true) - $start_time;
        
        // Kontrola odpovědi
        if (is_wp_error($response)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => 'Chyba při komunikaci s OpenAI API: ' . $response->get_error_message(),
                'execution_time' => $execution_time
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Kontrola stavového kódu
        if ($status_code == 401) {
            // Neplatný API klíč
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => 'Neplatný API klíč (401 Unauthorized)',
                'execution_time' => $execution_time
            ];
            $this->log_api_request($log_data);
            return false;
        } elseif ($status_code == 429) {
            // Překročení limitu požadavků
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => 'Překročen limit požadavků (429 Too Many Requests)',
                'execution_time' => $execution_time
            ];
            $this->log_api_request($log_data);
            return false;
        } elseif ($status_code < 200 || $status_code >= 300) {
            // Jiná chyba
            $body = wp_remote_retrieve_body($response);
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => "Chyba API: HTTP kód $status_code. Odpověď: $body",
                'execution_time' => $execution_time
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Kontrola obsahu odpovědi
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body) || !isset($body['choices'][0]['message']['content'])) {
            $log_data = [
                'request_type' => 'api_key_validation',
                'error_message' => 'Neplatná odpověď od API: ' . wp_remote_retrieve_body($response),
                'execution_time' => $execution_time
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Logování úspěšného požadavku
        $log_data = [
            'request_type' => 'api_key_validation',
            'response_data' => wp_remote_retrieve_body($response),
            'tokens_used' => $body['usage']['total_tokens'] ?? 0,
            'execution_time' => $execution_time
        ];
        $this->log_api_request($log_data);
        
        // Pokud prošlo až sem, API klíč je pravděpodobně platný
        return true;
    }
    
    /**
     * Vytvoření log tabulky
     */
    private function create_log_table() {
        global $wpdb;
        
        // Kontrola, zda tabulka již existuje
        $table_name = $wpdb->prefix . 'ai_feedback_api_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                request_type varchar(50) NOT NULL,
                request_data longtext NOT NULL,
                response_data longtext,
                tokens_used int(11),
                error_message text,
                execution_time float,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Kontrola, zda byla tabulka vytvořena
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('Chyba při vytváření tabulky ' . $table_name);
                error_log('SQL: ' . $sql);
                error_log('Poslední DB chyba: ' . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Analýza témat pomocí GPT-4.1
     * 
     * @param array $entries Zápisky k analýze
     * @return array|bool Data pro grafy nebo false při chybě
     */
    public function analyze_topics($entries) {
        // Kontrola, zda máme zápisky k analýze
        if (empty($entries)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_topics',
                'error_message' => 'Prázdná data pro analýzu',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Kontrola API klíče
        if (empty($this->api_key)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_topics',
                'error_message' => 'Chybí API klíč',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Kontrola platnosti API klíče
        if (!$this->is_api_key_valid()) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_topics',
                'error_message' => 'Neplatný API klíč',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Příprava dat pro GPT
        $entries_json = json_encode($entries, JSON_UNESCAPED_UNICODE);
        
        // Vytvoření promptu
        $system_prompt = "Jsi asistent, který analyzuje poznámky účastníků vzdělávacího programu. Tvým úkolem je identifikovat klíčová slova, kategorie témat a plány využití. Analýza musí být strukturovaná do následujících sekcí:
1. Klíčová slova (10 nejčastějších)
2. Témata (6 nejčastějších)
3. Plány využití (5 nejčastějších)

Formát odpovědi:
```json
{
  \"keywords\": {\"slovo1\": počet, \"slovo2\": počet, ...},
  \"topics\": {\"téma1\": počet, \"téma2\": počet, ...},
  \"usage_plans\": {\"plán1\": počet, \"plán2\": počet, ...}
}
```

Poskytni co nejlepší analýzu na základě dostupných dat. Všechny sekce jsou povinné.";

        // Uživatelská zpráva s daty
        $user_prompt = "Zde jsou poznámky účastníků k analýze (ve formátu JSON): $entries_json";
        
        // Příprava request body
        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];
        
        // Začátek měření času
        $start_time = microtime(true);
        
        // Příprava log dat pro request
        $log_data = [
            'request_type' => 'analyze_topics',
            'request_data' => json_encode([
                'entries_count' => count($entries),
                'system_prompt' => $system_prompt,
                'request_body' => $request_body
            ])
        ];
        
        // Volání OpenAI API
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60
        ]);
        
        // Konec měření času
        $execution_time = microtime(true) - $start_time;
        
        // Podrobné logování požadavku a odpovědi
        error_log('API REQUEST TO: ' . $this->endpoint);
        error_log('API REQUEST BODY: ' . json_encode($request_body));
        
        // Zpracování odpovědi a logování
        if (is_wp_error($response)) {
            // Detailní logování chyby
            error_log('API ERROR: ' . $response->get_error_message());
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        // Logování HTTP status kódu
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('API RESPONSE CODE: ' . $status_code);
        
        // Podrobné logování odpovědi body
        $response_body = wp_remote_retrieve_body($response);
        error_log('API RESPONSE BODY: ' . $response_body);
        
        $body = json_decode($response_body, true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            // Logování chyby
            $log_data['error_message'] = 'Prázdná odpověď od API';
            $log_data['response_data'] = json_encode($body);
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        // Extrakce a logování tokenů
        $tokens_used = $body['usage']['total_tokens'] ?? 0;
        
        // Dekódování JSON odpovědi
        $analysis_json = $body['choices'][0]['message']['content'];
        $analysis_data = json_decode($analysis_json, true);
        
        if (empty($analysis_data) || !is_array($analysis_data)) {
            // Logování chyby
            $log_data['error_message'] = 'Neplatný JSON v odpovědi';
            $log_data['response_data'] = $analysis_json;
            $log_data['tokens_used'] = $tokens_used;
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        // Logování úspěšného požadavku
        $log_data['response_data'] = $analysis_json;
        $log_data['tokens_used'] = $tokens_used;
        $log_data['execution_time'] = $execution_time;
        $this->log_api_request($log_data);
        
        // Extrakce dat z analýzy
        $keywords = $analysis_data['keywords'] ?? [];
        $topics = $analysis_data['topics'] ?? [];
        $usage = $analysis_data['usage_plans'] ?? [];
        
        // Příprava dat pro grafy
        $keyword_labels = array_keys($keywords);
        $keyword_values = array_values($keywords);
        
        $topic_labels = array_keys($topics);
        $topic_values = array_values($topics);
        
        $usage_labels = array_keys($usage);
        $usage_values = array_values($usage);
        
        return [
            'keywords' => [
                'labels' => $keyword_labels,
                'values' => $keyword_values
            ],
            'topics' => [
                'labels' => $topic_labels,
                'values' => $topic_values
            ],
            'usage' => [
                'labels' => $usage_labels,
                'values' => $usage_values
            ]
        ];
    }
    
    /**
     * Analýza porozumění pomocí GPT-4.1
     * 
     * @param array $entries Zápisky k analýze
     * @return array|bool Data pro grafy nebo false při chybě
     */
    public function analyze_understanding($entries) {
        if (empty($entries)) {
            error_log('Prázdná data pro analýzu porozumění');
            return false;
        }

        // Analýza pomocí GPT
        $analysis = $this->analyze_entries_with_gpt($entries, 'understanding');
        if (!$analysis) {
            error_log('GPT analýza selhala');
            return false;
        }

        try {
            $analysis_data = $analysis['data'];

            // Kontrola očekávané struktury
            if (!isset($analysis_data['understanding_levels']) || 
                !isset($analysis_data['application_quality']) || 
                !isset($analysis_data['improvement_suggestions'])) {
                error_log('Neplatný formát odpovědi z GPT - chybí povinné klíče');
                return false;
            }

            // Příprava dat pro grafy
            $understanding_data = [
                'labels' => array_keys($analysis_data['understanding_levels']),
                'values' => array_values($analysis_data['understanding_levels'])
            ];

            $application_data = [
                'labels' => array_keys($analysis_data['application_quality']),
                'values' => array_values($analysis_data['application_quality'])
            ];

            return [
                'understanding' => $understanding_data,
                'application' => $application_data,
                'suggestions' => $analysis_data['improvement_suggestions'],
                'tokens_used' => $analysis['tokens_used'],
                'execution_time' => $analysis['execution_time']
            ];

        } catch (Exception $e) {
            error_log('Chyba při zpracování analýzy: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Analýza sentimentu referencí pomocí GPT-4.1
     * 
     * @param array $references Reference k analýze
     * @return array|bool Data pro grafy nebo false při chybě
     */
    public function analyze_sentiment($references) {
        // Kontrola, zda máme reference k analýze
        if (empty($references)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_sentiment',
                'error_message' => 'Prázdná data pro analýzu',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Kontrola platnosti API klíče
        if (empty($this->api_key)) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_sentiment',
                'error_message' => 'Chybí API klíč',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        if (!$this->is_api_key_valid()) {
            // Logování chyby
            $log_data = [
                'request_type' => 'analyze_sentiment',
                'error_message' => 'Neplatný API klíč',
                'execution_time' => 0
            ];
            $this->log_api_request($log_data);
            return false;
        }
        
        // Analýza dat pomocí GPT-4.1
        $analysis_result = $this->analyze_references_with_gpt($references);
        
        // Pokud analýza selhala, vrátíme false
        if (empty($analysis_result)) {
            return false;
        }
        
        // Extrakce dat z analýzy
        $sentiment = $analysis_result['data']['sentiment'] ?? ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        $aspects = $analysis_result['data']['appreciated_aspects'] ?? [];
        
        // Příprava dat pro grafy
        $sentiment_values = [
            $sentiment['positive'] ?? 0,
            $sentiment['neutral'] ?? 0,
            $sentiment['negative'] ?? 0
        ];
        
        // Konverze aspektů na formát pro zobrazení
        $aspects_formatted = [];
        foreach ($aspects as $aspect => $count) {
            $aspects_formatted[] = [
                'name' => $aspect,
                'count' => $count
            ];
        }
        
        // Seřazení aspektů podle počtu
        usort($aspects_formatted, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return [
            'sentiment' => [
                'labels' => ['Pozitivní', 'Neutrální', 'Negativní'],
                'values' => $sentiment_values
            ],
            'aspects' => $aspects_formatted
        ];
    }
    
    /**
     * Analýza zápisků pomocí GPT-4.1
     * 
     * @param array $entries Zápisky k analýze
     * @param string $analysis_type Typ analýzy ('topics' nebo 'understanding')
     * @return array|bool Výsledek analýzy nebo false při chybě
     */
    private function analyze_entries_with_gpt($entries, $analysis_type) {
        if (empty($this->api_key)) {
            error_log('GPT API klíč není nastaven');
            return false;
        }

        // Pokud nejsou žádné záznamy, vrátíme prázdný výsledek
        if (empty($entries) || !is_array($entries)) {
            error_log('Žádné záznamy pro analýzu');
            return false;
        }

        // Příprava dat pro GPT
        $entries_json = json_encode($entries, JSON_UNESCAPED_UNICODE);

        // Vytvoření promptu podle typu analýzy
        if ($analysis_type === 'understanding') {
            $system_prompt = "Jsi asistent, který analyzuje poznámky účastníků vzdělávacího programu. Tvým úkolem je analyzovat úroveň porozumění a kvalitu plánované aplikace poznatků. Analýza musí obsahovat:

1. Úrovně porozumění (kolik účastníků dosáhlo jaké úrovně)
2. Kvalita plánované aplikace (jak dobře účastníci plánují využít poznatky)
3. Návrhy na zlepšení (pokud jsou relevantní)

Formát odpovědi:
```json
{
  \"understanding_levels\": {
    \"Výborné\": počet,
    \"Dobré\": počet,
    \"Základní\": počet,
    \"Nedostatečné\": počet
  },
  \"application_quality\": {
    \"Konkrétní plán\": počet,
    \"Obecný záměr\": počet,
    \"Bez plánu\": počet
  },
  \"improvement_suggestions\": [
    \"návrh 1\",
    \"návrh 2\"
  ]
}
```";
        } else {
            $system_prompt = "Jsi asistent, který analyzuje poznámky účastníků vzdělávacího programu. Tvým úkolem je vytvořit ucelené shrnutí témat a poznatků. Shrnutí musí obsahovat:

1. Celkové shrnutí (2-3 věty)
2. Hlavní témata (3-5 nejčastějších)
3. Klíčové poznatky (3-5 nejdůležitějších)

Formát odpovědi:
```json
{
  \"summary\": \"text shrnutí\",
  \"main_topics\": {
    \"téma 1\": počet_výskytů,
    \"téma 2\": počet_výskytů
  },
  \"key_learnings\": [
    \"poznatek 1\",
    \"poznatek 2\"
  ]
}
```";
        }

        // Uživatelská zpráva s daty
        $user_prompt = "Zde jsou poznámky účastníků ze školení (ve formátu JSON): $entries_json";
        
        // Příprava request body
        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];
        
        // Začátek měření času
        $start_time = microtime(true);
        
        // Příprava log dat pro request
        $log_data = [
            'request_type' => 'analyze_entries_' . $analysis_type,
            'request_data' => json_encode([
                'entries_count' => count($entries),
                'system_prompt' => $system_prompt,
                'request_body' => $request_body
            ])
        ];
        
        // Volání OpenAI API
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60
        ]);
        
        // Konec měření času
        $execution_time = microtime(true) - $start_time;
        
        // Podrobné logování požadavku a odpovědi
        error_log('API REQUEST TO: ' . $this->endpoint);
        error_log('API REQUEST BODY: ' . json_encode($request_body));
        
        // Zpracování odpovědi a logování
        if (is_wp_error($response)) {
            error_log('API ERROR: ' . $response->get_error_message());
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('API RESPONSE CODE: ' . $status_code);
        
        if ($status_code !== 200) {
            error_log('API vrátila chybový kód: ' . $status_code);
            $log_data['error_message'] = 'API vrátila chybový kód: ' . $status_code;
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        error_log('API RESPONSE BODY: ' . $response_body);
        
        $body = json_decode($response_body, true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            $log_data['error_message'] = 'Prázdná odpověď od API';
            $log_data['response_data'] = json_encode($body);
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $tokens_used = $body['usage']['total_tokens'] ?? 0;
        $analysis_json = $body['choices'][0]['message']['content'];
        
        try {
            $analysis_data = json_decode($analysis_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Chyba při dekódování JSON odpovědi: ' . json_last_error_msg());
            }
            
            // Kontrola očekávané struktury podle typu analýzy
            if ($analysis_type === 'understanding') {
                if (!isset($analysis_data['understanding_levels']) || 
                    !isset($analysis_data['application_quality']) || 
                    !isset($analysis_data['improvement_suggestions'])) {
                    throw new Exception('Neplatný formát odpovědi z GPT - chybí povinné klíče pro understanding analýzu');
                }
            } else {
                if (!isset($analysis_data['summary']) || 
                    !isset($analysis_data['main_topics']) || 
                    !isset($analysis_data['key_learnings'])) {
                    throw new Exception('Neplatný formát odpovědi z GPT - chybí povinné klíče pro topics analýzu');
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $log_data['error_message'] = $e->getMessage();
            $log_data['response_data'] = $analysis_json;
            $log_data['tokens_used'] = $tokens_used;
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $log_data['response_data'] = $analysis_json;
        $log_data['tokens_used'] = $tokens_used;
        $log_data['execution_time'] = $execution_time;
        $this->log_api_request($log_data);
        
        return [
            'data' => $analysis_data,
            'tokens_used' => $tokens_used,
            'execution_time' => $execution_time
        ];
    }
    
    /**
     * Analýza referencí pomocí GPT-4.1
     * 
     * @param array $references Reference k analýze
     * @return array|bool Výsledek analýzy nebo false při chybě
     */
    public function analyze_references_with_gpt($references) {
        if (empty($this->api_key)) {
            error_log('GPT API klíč není nastaven');
            return false;
        }

        // Pokud nejsou žádné záznamy, vrátíme prázdný výsledek
        if (empty($references) || !is_array($references)) {
            error_log('Žádné záznamy pro analýzu referencí');
            return false;
        }

        // Příprava dat pro GPT
        $entries_json = json_encode($references, JSON_UNESCAPED_UNICODE);

        // Systémová instrukce pro GPT
        $system_prompt = "Jsi asistent, který analyzuje poznámky účastníků vzdělávacího programu. Tvým úkolem je vytvořit ucelené shrnutí toho, co si účastníci odnesli jako nejcennější. Shrnutí musí být strukturované do následujících sekcí:
1. Celkové shrnutí (2-3 věty)
2. Hlavní přínosy (3-5 bodů)
3. Zamýšlené aplikace (jak chtějí účastníci nabyté znalosti využít)
4. Návrhy na zlepšení (pokud byly zmíněny)

Formát odpovědi:
```json
{
  \"summary\": \"text celkového shrnutí\",
  \"benefits\": [\"přínos 1\", \"přínos 2\", ...],
  \"applications\": [\"aplikace 1\", \"aplikace 2\", ...],
  \"improvements\": [\"návrh 1\", \"návrh 2\", ...]
}
```

Poskytni co nejlepší analýzu na základě dostupných dat. Všechny sekce jsou povinné kromě 'improvements', kterou vynech, pokud žádné návrhy na zlepšení nebyly zmíněny.";

        // Uživatelská zpráva s daty
        $user_prompt = "Zde jsou poznámky účastníků o tom, co si odnesli jako nejcennější ze školení (ve formátu JSON): $entries_json";

        // Data pro API
        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];

        // Logování požadavku a měření času
        $log_data = [
            'endpoint' => $this->endpoint,
            'model' => $this->model,
            'request_type' => 'analyze_references',
            'input_tokens' => $this->estimate_tokens($system_prompt . $user_prompt)
        ];
        
        $start_time = microtime(true);
        
        // Volání OpenAI API
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60
        ]);
        
        // Konec měření času
        $execution_time = microtime(true) - $start_time;
        
        // Podrobné logování požadavku a odpovědi
        error_log('API REQUEST TO: ' . $this->endpoint);
        error_log('API REQUEST BODY: ' . json_encode($request_body));
        
        // Zpracování odpovědi a logování
        if (is_wp_error($response)) {
            // Detailní logování chyby
            error_log('API ERROR: ' . $response->get_error_message());
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        // Logování HTTP status kódu
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('API RESPONSE CODE: ' . $status_code);
        
        // Podrobné logování odpovědi body
        $response_body = wp_remote_retrieve_body($response);
        error_log('API RESPONSE BODY: ' . $response_body);
        
        $body = json_decode($response_body, true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            // Logování chyby
            $log_data['error_message'] = 'Prázdná odpověď od GPT API';
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }

        // Extrakce odpovědi
        $content = $body['choices'][0]['message']['content'];
        $analysis_data = json_decode($content, true);

        // Logování úspěšného požadavku
        $log_data['execution_time'] = $execution_time;
        $log_data['output_tokens'] = $this->estimate_tokens($content);
        $log_data['total_tokens'] = $log_data['input_tokens'] + $log_data['output_tokens'];
        $this->log_api_request($log_data);

        return $analysis_data;
    }
    
    /**
     * Logování API požadavku
     * 
     * @param array $log_data Data pro logování
     * @return int ID logu
     */
    private function log_api_request($log_data) {
        global $wpdb;
        
        $default_data = [
            'request_type' => '',
            'request_data' => '',
            'response_data' => '',
            'tokens_used' => 0,
            'error_message' => '',
            'execution_time' => 0
        ];
        
        $log_data = wp_parse_args($log_data, $default_data);
        
        // Serializace dat
        $log_data['request_data'] = is_array($log_data['request_data']) ? 
            json_encode($log_data['request_data']) : 
            $log_data['request_data'];
            
        $log_data['response_data'] = is_array($log_data['response_data']) ? 
            json_encode($log_data['response_data']) : 
            $log_data['response_data'];
        
        // Vložení logu do databáze
        $wpdb->insert(
            $this->log_table,
            $log_data,
            ['%s', '%s', '%s', '%d', '%s', '%f']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Získání logů API požadavků
     * 
     * @param int $limit Limit počtu záznamů
     * @param int $offset Offset pro stránkování
     * @return array Logy API požadavků
     */
    public function get_api_logs($limit = 50, $offset = 0) {
        global $wpdb;
        
        error_log('AI Feedback Analytics: Načítání logů z tabulky ' . $this->log_table);
        
        // Kontrola, zda tabulka existuje
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->log_table}'") != $this->log_table) {
            error_log('AI Feedback Analytics: Tabulka ' . $this->log_table . ' neexistuje');
            return [];
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->log_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        
        error_log('AI Feedback Analytics: SQL dotaz: ' . $query);
        
        $logs = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('AI Feedback Analytics: SQL chyba: ' . $wpdb->last_error);
        }
        
        return $logs ?: [];
    }
    
    /**
     * Získání součtu všech použitých tokenů
     * 
     * @return int Celkový počet použitých tokenů
     */
    public function get_total_tokens_used() {
        global $wpdb;
        
        error_log('AI Feedback Analytics: Počítání tokenů z tabulky ' . $this->log_table);
        
        // Kontrola, zda tabulka existuje
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->log_table}'") != $this->log_table) {
            error_log('AI Feedback Analytics: Tabulka ' . $this->log_table . ' neexistuje');
            return 0;
        }
        
        $query = "SELECT SUM(tokens_used) FROM {$this->log_table} WHERE tokens_used IS NOT NULL";
        error_log('AI Feedback Analytics: SQL dotaz: ' . $query);
        
        $result = $wpdb->get_var($query);
        
        if ($wpdb->last_error) {
            error_log('AI Feedback Analytics: SQL chyba: ' . $wpdb->last_error);
        }
        
        return (int) ($result ?: 0);
    }
    
    /**
     * Přidání stránky s přehledem API logů
     */
    public function add_logs_page() {
        add_submenu_page(
            'ai-feedback-analytics', // Rodičovská stránka
            'API Logy', // Titulek stránky
            'API Logy', // Text menu
            'manage_options', // Oprávnění
            'ai-feedback-api-logs', // Slug
            [$this, 'render_logs_page'] // Callback funkce
        );
    }
    
    /**
     * Vykreslení stránky s přehledem API logů
     */
    public function render_logs_page() {
        // Získání parametrů stránkování
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Získání logů
        $logs = $this->get_api_logs($per_page, $offset);
        
        // Získání celkového počtu záznamů pro stránkování
        global $wpdb;
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
        $total_pages = ceil($total_logs / $per_page);
        
        // Získání celkového počtu tokenů
        $total_tokens = $this->get_total_tokens_used();
        
        // Výpočet odhadovaných nákladů (přibližný odhad, ceny se mohou lišit)
        $estimated_cost = ($total_tokens / 1000) * 0.03; // Přibližná cena $0.03 za 1000 tokenů
        
        ?>
        <div class="wrap">
            <h1>AI Feedback Analytics - API Logy</h1>
            
            <div class="ai-logs-summary">
                <div class="ai-logs-summary-box">
                    <h2>Souhrnné statistiky</h2>
                    <p><strong>Celkem použito tokenů:</strong> <?php echo number_format($total_tokens, 0, ',', ' '); ?></p>
                    <p><strong>Odhadované náklady:</strong> $<?php echo number_format($estimated_cost, 2, '.', ' '); ?></p>
                    <p><strong>Celkem API požadavků:</strong> <?php echo number_format($total_logs, 0, ',', ' '); ?></p>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Typ požadavku</th>
                        <th>Tokeny</th>
                        <th>Čas [s]</th>
                        <th>Chyba</th>
                        <th>Datum a čas</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7">Žádné logy nenalezeny.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['id']); ?></td>
                                <td><?php echo esc_html($log['request_type']); ?></td>
                                <td><?php echo $log['tokens_used'] ? number_format($log['tokens_used'], 0, ',', ' ') : 'N/A'; ?></td>
                               <td><?php echo $log['execution_time'] ? number_format($log['execution_time'], 2) : 'N/A'; ?></td>
                               <td><?php echo $log['error_message'] ? '<span class="error">' . esc_html(substr($log['error_message'], 0, 50)) . '...</span>' : '—'; ?></td>
                               <td><?php echo esc_html($log['created_at']); ?></td>
                               <td>
                                   <a href="#" class="button view-details" data-id="<?php echo esc_attr($log['id']); ?>">Detail</a>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   <?php endif; ?>
               </tbody>
           </table>
           
           <?php if ($total_pages > 1): ?>
               <div class="tablenav">
                   <div class="tablenav-pages">
                       <span class="displaying-num"><?php echo number_format($total_logs, 0, ',', ' '); ?> položek</span>
                       <span class="pagination-links">
                           <?php
                           // Předchozí stránka
                           if ($page > 1) {
                               echo '<a class="prev-page button" href="?page=ai-feedback-api-logs&paged=' . ($page - 1) . '"><span class="screen-reader-text">Předchozí stránka</span><span aria-hidden="true">‹</span></a>';
                           } else {
                               echo '<span class="prev-page button disabled" aria-hidden="true">‹</span>';
                           }
                           
                           // Aktuální stránka
                           echo '<span class="paging-input">' . $page . ' z <span class="total-pages">' . $total_pages . '</span></span>';
                           
                           // Další stránka
                           if ($page < $total_pages) {
                               echo '<a class="next-page button" href="?page=ai-feedback-api-logs&paged=' . ($page + 1) . '"><span class="screen-reader-text">Další stránka</span><span aria-hidden="true">›</span></a>';
                           } else {
                               echo '<span class="next-page button disabled" aria-hidden="true">›</span>';
                           }
                           ?>
                       </span>
                   </div>
               </div>
           <?php endif; ?>
           
           <!-- Modal pro zobrazení detailů -->
           <div id="log-details-modal" class="ai-logs-modal" style="display: none;">
               <div class="ai-logs-modal-content">
                   <span class="ai-logs-modal-close">&times;</span>
                   <h2>Detaily API požadavku</h2>
                   <div id="log-details-content"></div>
               </div>
           </div>
           
           <style>
               .ai-logs-summary {
                   margin-bottom: 20px;
               }
               .ai-logs-summary-box {
                   background: white;
                   padding: 15px;
                   border-radius: 5px;
                   box-shadow: 0 1px 3px rgba(0,0,0,0.1);
               }
               .ai-logs-summary-box h2 {
                   margin-top: 0;
               }
               span.error {
                   color: #dc3545;
               }
               
               /* Modal styly */
               .ai-logs-modal {
                   position: fixed;
                   top: 0;
                   left: 0;
                   width: 100%;
                   height: 100%;
                   background: rgba(0,0,0,0.5);
                   z-index: 9999;
                   display: flex;
                   justify-content: center;
                   align-items: center;
               }
               .ai-logs-modal-content {
                   background: white;
                   padding: 20px;
                   border-radius: 5px;
                   width: 80%;
                   max-width: 800px;
                   max-height: 80vh;
                   overflow-y: auto;
                   position: relative;
               }
               .ai-logs-modal-close {
                   position: absolute;
                   right: 10px;
                   top: 10px;
                   font-size: 24px;
                   cursor: pointer;
               }
               pre {
                   background: #f8f9fa;
                   padding: 10px;
                   border-radius: 5px;
                   overflow-x: auto;
                   max-height: 300px;
               }
           </style>
           
           <script>
               jQuery(document).ready(function($) {
                   // Zobrazení detailů logu
                   $('.view-details').on('click', function(e) {
                       e.preventDefault();
                       const logId = $(this).data('id');
                       
                       // AJAX požadavek pro získání detailů logu
                       $.ajax({
                           url: ajaxurl,
                           type: 'POST',
                           data: {
                               action: 'ai_feedback_get_log_details',
                               nonce: '<?php echo wp_create_nonce('ai_feedback_log_details_nonce'); ?>',
                               log_id: logId
                           },
                           success: function(response) {
                               if (response.success) {
                                   // Formátování JSON pro zobrazení
                                   let requestData = '';
                                   try {
                                       const parsed = JSON.parse(response.data.request_data);
                                       requestData = JSON.stringify(parsed, null, 2);
                                   } catch (e) {
                                       requestData = response.data.request_data;
                                   }
                                   
                                   let responseData = '';
                                   if (response.data.response_data) {
                                       try {
                                           const parsed = JSON.parse(response.data.response_data);
                                           responseData = JSON.stringify(parsed, null, 2);
                                       } catch (e) {
                                           responseData = response.data.response_data;
                                       }
                                   }
                                   
                                   // Zobrazení detailů
                                   const content = `
                                       <h3>Základní informace</h3>
                                       <p><strong>ID:</strong> ${response.data.id}</p>
                                       <p><strong>Typ požadavku:</strong> ${response.data.request_type}</p>
                                       <p><strong>Datum a čas:</strong> ${response.data.created_at}</p>
                                       <p><strong>Použité tokeny:</strong> ${response.data.tokens_used || 'N/A'}</p>
                                       <p><strong>Čas zpracování:</strong> ${response.data.execution_time ? response.data.execution_time.toFixed(2) + ' s' : 'N/A'}</p>
                                       
                                       ${response.data.error_message ? `<h3>Chybová zpráva</h3><p class="error">${response.data.error_message}</p>` : ''}
                                       
                                       <h3>Data požadavku</h3>
                                       <pre>${requestData}</pre>
                                       
                                       ${responseData ? `<h3>Data odpovědi</h3><pre>${responseData}</pre>` : ''}
                                   `;
                                   
                                   $('#log-details-content').html(content);
                                   $('#log-details-modal').show();
                               } else {
                                   alert('Nepodařilo se načíst detaily: ' + response.data);
                               }
                           },
                           error: function() {
                               alert('Chyba při komunikaci se serverem.');
                           }
                       });
                   });
                   
                   // Zavření modalu
                   $('.ai-logs-modal-close').on('click', function() {
                       $('#log-details-modal').hide();
                   });
                   
                   // Zavření modalu při kliknutí mimo obsah
                   $(window).on('click', function(e) {
                       if ($(e.target).is('.ai-logs-modal')) {
                           $('#log-details-modal').hide();
                       }
                   });
               });
           </script>
       </div>
       <?php
   }

    /**
     * Znovu analýza všech příspěvků
     * 
     * @return array Výsledky analýzy
     */
    public function reanalyze_all_posts() {
        // Kontrola API klíče
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error_messages' => ['Chybí API klíč. Nastavte jej v nastavení pluginu.'],
                'total_posts' => 0,
                'errors' => 0
            ];
        }

        // Získání všech příspěvků
        $args = [
            'post_type' => ['reflexe', 'reference'],
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        $posts = get_posts($args);
        $results = [
            'success' => true,
            'total_posts' => count($posts),
            'analyzed' => 0,
            'errors' => 0,
            'error_messages' => []
        ];
        
        // Logování začátku analýzy
        error_log(sprintf('Začíná re-analýza %d příspěvků', $results['total_posts']));
        
        foreach ($posts as $post) {
            try {
                if ($post->post_type === 'reflexe') {
                    // Analýza reflexí
                    $entries = explode("---", $post->post_content);
                    $entries = array_filter($entries, function($entry) {
                        return trim($entry) !== '';
                    });
                    
                    if (!empty($entries)) {
                        foreach ($entries as $entry) {
                            // Přímé volání API podobně jako ai_generate_entry_summary
                            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $this->api_key,
                                    'Content-Type' => 'application/json'
                                ],
                                'body' => json_encode([
                                    'model' => $this->model,
                                    'messages' => [
                                        ['role' => 'system', 'content' => 'Poskytni strukturovanou zpětnou vazbu na tento zápisek ze školení. Zaměř se na tři oblasti: 1) jak konkrétní je plán účastníka, 2) zda rozumí tomu, co říká, 3) co mu v přemýšlení případně uniká. Tvým cílem je pomoci účastníkovi lépe reflektovat nabyté znalosti a jejich praktické využití.'],
                                        ['role' => 'user', 'content' => $entry]
                                    ],
                                    'max_tokens' => 250
                                ])
                            ]);
                            
                            if (is_wp_error($response)) {
                                throw new Exception('Chyba při komunikaci s OpenAI API: ' . $response->get_error_message());
                            }
                            
                            $body = json_decode(wp_remote_retrieve_body($response), true);
                            if (empty($body['choices'][0]['message']['content'])) {
                                throw new Exception('Prázdná odpověď od OpenAI API');
                            }
                        }
                        $results['analyzed']++;
                        
                        // Logování průběhu
                        error_log(sprintf('Zpracován příspěvek %d (reflexe)', $post->ID));
                    }
                } else {
                    // Analýza referencí - přímé volání API podobně jako ai_generate_reference
                    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->api_key,
                            'Content-Type' => 'application/json'
                        ],
                        'body' => json_encode([
                            'model' => $this->model,
                            'messages' => [
                                ['role' => 'system', 'content' => 'Vytvoř lidsky znějící, stručnou, autentickou referenci na školení.'],
                                ['role' => 'user', 'content' => $post->post_content]
                            ],
                            'max_tokens' => 500
                        ])
                    ]);
                    
                    if (is_wp_error($response)) {
                        throw new Exception('Chyba při komunikaci s OpenAI API: ' . $response->get_error_message());
                    }
                    
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (empty($body['choices'][0]['message']['content'])) {
                        throw new Exception('Prázdná odpověď od OpenAI API');
                    }
                    
                    $results['analyzed']++;
                    
                    // Logování průběhu
                    error_log(sprintf('Zpracován příspěvek %d (reference)', $post->ID));
                }
            } catch (Exception $e) {
                $results['errors']++;
                $error_message = sprintf(
                    'Chyba při analýze příspěvku %d: %s',
                    $post->ID,
                    $e->getMessage()
                );
                $results['error_messages'][] = $error_message;
                
                // Logování chyby
                error_log($error_message);
            }
            
            // Krátká pauza mezi požadavky
            usleep(500000); // 0.5 sekundy
        }
        
        // Logování výsledků
        error_log(sprintf(
            'Re-analýza dokončena. Zpracováno %d z %d příspěvků, %d chyb.',
            $results['analyzed'],
            $results['total_posts'],
            $results['errors']
        ));
        
        return $results;
    }

    /**
     * Odhad počtu tokenů v textu
     * 
     * Jednoduchá implementace - přibližně 4 znaky na token
     * 
     * @param string $text Text k analýze
     * @return int Odhadovaný počet tokenů
     */
    private function estimate_tokens($text) {
        if (empty($text)) {
            return 0;
        }
        // Přibližný odhad - 4 znaky na token
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Analýza jednotlivé reflexe a uložení výsledků
     * 
     * @param int $post_id ID příspěvku
     * @param string $content Obsah reflexe
     * @return array|bool Výsledek analýzy nebo false při chybě
     */
    public function analyze_single_entry($post_id, $content) {
        if (empty($this->api_key)) {
            error_log('GPT API klíč není nastaven');
            return false;
        }

        // Příprava dat pro GPT
        $entries = explode("---", $content);
        $entries = array_filter($entries, function($entry) {
            return trim($entry) !== '';
        });

        if (empty($entries)) {
            error_log('Žádné zápisky pro analýzu');
            return false;
        }

        // Systémová instrukce pro GPT
        $system_prompt = "Jsi asistent, který analyzuje poznámky účastníků vzdělávacího programu. Tvým úkolem je vytvořit ucelené shrnutí toho, co si účastník odnesl. Shrnutí musí obsahovat:

1. Celkové shrnutí (2-3 věty)
2. Klíčové poznatky (3-5 bodů)
3. Porozumění (jak účastník chápe koncepty)
4. Plány využití (jak chce účastník znalosti aplikovat)

Formát odpovědi:
```json
{
  \"summary\": \"text celkového shrnutí\",
  \"key_learnings\": [\"poznatek 1\", \"poznatek 2\", ...],
  \"understanding\": [\"vysvětlení 1\", \"vysvětlení 2\", ...],
  \"usage_plans\": [\"plán 1\", \"plán 2\", ...]
}
```";

        // Uživatelská zpráva s daty
        $user_prompt = "Zde jsou poznámky účastníka ze školení:\n\n" . implode("\n\n", $entries);

        // Data pro API
        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];

        // Logování požadavku a měření času
        $log_data = [
            'request_type' => 'analyze_single_entry',
            'request_data' => json_encode([
                'post_id' => $post_id,
                'entries_count' => count($entries)
            ])
        ];
        
        $start_time = microtime(true);
        
        // Volání OpenAI API
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60
        ]);
        
        $execution_time = microtime(true) - $start_time;
        
        if (is_wp_error($response)) {
            error_log('API ERROR: ' . $response->get_error_message());
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('API vrátila chybový kód: ' . $status_code);
            $log_data['error_message'] = 'API vrátila chybový kód: ' . $status_code;
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            $log_data['error_message'] = 'Prázdná odpověď od API';
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }

        $analysis_json = $body['choices'][0]['message']['content'];
        $analysis_data = json_decode($analysis_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $log_data['error_message'] = 'Chyba při dekódování JSON odpovědi: ' . json_last_error_msg();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            return false;
        }

        // Uložení analýzy k příspěvku
        update_post_meta($post_id, 'ai_analysis', $analysis_json);
        
        // Logování úspěšného požadavku
        $log_data['response_data'] = $analysis_json;
        $log_data['tokens_used'] = $body['usage']['total_tokens'] ?? 0;
        $log_data['execution_time'] = $execution_time;
        $this->log_api_request($log_data);

        return $analysis_data;
    }
}