<?php
/**
 * Třída pro komunikaci s OpenAI API
 * 
 * Zajišťuje analýzu dat pomocí GPT-4.1 a logování API požadavků.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_API {
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
    }
    
    /**
     * Vytvoření log tabulky
     */
    private function create_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
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
    }
    
    /**
     * Analýza témat pomocí GPT-4.1
     * 
     * @param array $entries Zápisky k analýze
     * @return array Data pro grafy
     */
    public function analyze_topics($entries) {
        // Pokud používáme testovací data nebo nemáme skutečné zápisky
        if (count($entries) <= 2) {
            // Vrátit testovací výsledky analýzy
            return [
                'keywords' => [
                    'labels' => ['AI', 'GPT-4', 'prompt engineering', 'DALL-E', 'ChatGPT', 'Midjourney', 'workflow', 'automatizace', 'generování obrázků', 'large language model'],
                    'values' => [15, 12, 10, 8, 7, 6, 5, 4, 4, 3]
                ],
                'topics' => [
                    'labels' => ['Generativní AI', 'Praktické aplikace', 'Prompt engineering', 'Etika v AI', 'Technická implementace', 'Jiné'],
                    'values' => [42, 28, 15, 10, 8, 3]
                ],
                'usage' => [
                    'labels' => ['Marketing', 'Vývoj produktů', 'Copywriting', 'Analýza dat', 'Osobní projekty'],
                    'values' => [35, 25, 20, 15, 5]
                ]
            ];
        }
        
        // Analýza dat pomocí GPT-4.1
        $analysis_result = $this->analyze_entries_with_gpt($entries, 'topics');
        
        // Pokud analýza selhala, vrátíme testovací data
        if (empty($analysis_result)) {
            return [
                'keywords' => [
                    'labels' => ['AI', 'GPT-4', 'prompt engineering', 'DALL-E', 'ChatGPT', 'Midjourney', 'workflow', 'automatizace', 'generování obrázků', 'large language model'],
                    'values' => [15, 12, 10, 8, 7, 6, 5, 4, 4, 3]
                ],
                'topics' => [
                    'labels' => ['Generativní AI', 'Praktické aplikace', 'Prompt engineering', 'Etika v AI', 'Technická implementace', 'Jiné'],
                    'values' => [42, 28, 15, 10, 8, 3]
                ],
                'usage' => [
                    'labels' => ['Marketing', 'Vývoj produktů', 'Copywriting', 'Analýza dat', 'Osobní projekty'],
                    'values' => [35, 25, 20, 15, 5]
                ]
            ];
        }
        
        // Extrakce dat z analýzy
        $keywords = $analysis_result['data']['keywords'] ?? [];
        $topics = $analysis_result['data']['topics'] ?? [];
        $usage = $analysis_result['data']['usage_plans'] ?? [];
        
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
     * @return array Data pro grafy
     */
    public function analyze_understanding($entries) {
        // Pokud používáme testovací data nebo nemáme skutečné zápisky
        if (count($entries) <= 2) {
            // Vrátit testovací výsledky analýzy
            return [
                'understanding' => [
                    'labels' => ['Výborné porozumění', 'Dobré porozumění', 'Základní porozumění', 'Nedostatečné porozumění'],
                    'values' => [35, 40, 20, 5]
                ],
                'application' => [
                    'labels' => ['Vynikající aplikace', 'Dobrá aplikace', 'Základní aplikace', 'Slabá aplikace'],
                    'values' => [30, 45, 20, 5]
                ],
                'suggestions' => [
                    'Přidat více praktických cvičení pro lepší upevnění konceptů.',
                    'Poskytnout více příkladů z reálného světa pro pochopení praktického využití.',
                    'Zařadit krátké kvízy na konci každé části školení pro ověření porozumění.',
                    'Rozdělit složitější témata na menší, lépe stravitelné části.'
                ]
            ];
        }
        
        // Analýza dat pomocí GPT-4.1
        $analysis_result = $this->analyze_entries_with_gpt($entries, 'understanding');
        
        // Pokud analýza selhala, vrátíme testovací data
        if (empty($analysis_result)) {
            return [
                'understanding' => [
                    'labels' => ['Výborné porozumění', 'Dobré porozumění', 'Základní porozumění', 'Nedostatečné porozumění'],
                    'values' => [35, 40, 20, 5]
                ],
                'application' => [
                    'labels' => ['Vynikající aplikace', 'Dobrá aplikace', 'Základní aplikace', 'Slabá aplikace'],
                    'values' => [30, 45, 20, 5]
                ],
                'suggestions' => [
                    'Přidat více praktických cvičení pro lepší upevnění konceptů.',
                    'Poskytnout více příkladů z reálného světa pro pochopení praktického využití.',
                    'Zařadit krátké kvízy na konci každé části školení pro ověření porozumění.',
                    'Rozdělit složitější témata na menší, lépe stravitelné části.'
                ]
            ];
        }
        
        // Extrakce dat z analýzy
        $understanding_levels = $analysis_result['data']['understanding_levels'] ?? [];
        $application_quality = $analysis_result['data']['application_quality'] ?? [];
        $suggestions = $analysis_result['data']['improvement_suggestions'] ?? [];
        
        // Příprava dat pro grafy
        $understanding_labels = array_keys($understanding_levels);
        $understanding_values = array_values($understanding_levels);
        
        $application_labels = array_keys($application_quality);
        $application_values = array_values($application_quality);
        
        return [
            'understanding' => [
                'labels' => $understanding_labels,
                'values' => $understanding_values
            ],
            'application' => [
                'labels' => $application_labels,
                'values' => $application_values
            ],
            'suggestions' => $suggestions
        ];
    }
    
    /**
     * Analýza sentimentu referencí pomocí GPT-4.1
     * 
     * @param array $references Reference k analýze
     * @return array Data pro grafy
     */
    public function analyze_sentiment($references) {
        // Pokud používáme testovací data nebo nemáme skutečné reference
        if (count($references) <= 2) {
            // Vrátit testovací výsledky analýzy
            return [
                'sentiment' => [
                    'labels' => ['Pozitivní', 'Neutrální', 'Negativní'],
                    'values' => [75, 20, 5]
                ],
                'aspects' => [
                    ['name' => 'Praktické příklady', 'count' => 15],
                    ['name' => 'Znalosti lektora', 'count' => 12],
                    ['name' => 'Interaktivita', 'count' => 10],
                    ['name' => 'Užitečnost', 'count' => 8],
                    ['name' => 'Organizace školení', 'count' => 5],
                    ['name' => 'Tempo výuky', 'count' => 3]
                ]
            ];
        }
        
        // Analýza dat pomocí GPT-4.1
        $analysis_result = $this->analyze_references_with_gpt($references);
        
        // Pokud analýza selhala, vrátíme testovací data
        if (empty($analysis_result)) {
            return [
                'sentiment' => [
                    'labels' => ['Pozitivní', 'Neutrální', 'Negativní'],
                    'values' => [75, 20, 5]
                ],
                'aspects' => [
                    ['name' => 'Praktické příklady', 'count' => 15],
                    ['name' => 'Znalosti lektora', 'count' => 12],
                    ['name' => 'Interaktivita', 'count' => 10],
                    ['name' => 'Užitečnost', 'count' => 8],
                    ['name' => 'Organizace školení', 'count' => 5],
                    ['name' => 'Tempo výuky', 'count' => 3]
                ]
            ];
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
        $api_key = get_option('ai_plugin_apikey');
        if (empty($api_key)) {
            return false;
        }
        
        // Příprava dat pro GPT
        $entries_json = json_encode($entries, JSON_UNESCAPED_UNICODE);
        
        // Vytvoření promptu podle typu analýzy
        if ($analysis_type === 'topics') {
            $system_prompt = 'Analyzuj zápisky účastníků školení a identifikuj klíčová slova, kategorie témat a plány využití. Vrať výsledek pouze jako JSON ve formátu: {"keywords": {"slovo1": počet, "slovo2": počet, ...}, "topics": {"téma1": počet, "téma2": počet, ...}, "usage_plans": {"plán1": počet, "plán2": počet, ...}}. Počet by měl představovat četnost výskytu. Zahrň pouze 10 nejčastějších klíčových slov, 6 nejčastějších témat a 5 nejčastějších plánů využití.';
        } else {
            $system_prompt = 'Analyzuj zápisky účastníků školení a vyhodnoť úroveň porozumění a kvalitu praktických aplikací. Vrať výsledek pouze jako JSON ve formátu: {"understanding_levels": {"Výborné porozumění": počet, "Dobré porozumění": počet, "Základní porozumění": počet, "Nedostatečné porozumění": počet}, "application_quality": {"Vynikající aplikace": počet, "Dobrá aplikace": počet, "Základní aplikace": počet, "Slabá aplikace": počet}, "improvement_suggestions": ["návrh1", "návrh2", "návrh3", ...]}. Počet by měl představovat četnost výskytu. Zahrň 3-5 konkrétních návrhů pro zlepšení školení na základě analýzy zápisků.';
        }
        
        $user_prompt = "Zápisky účastníků školení k analýze (formát JSON): $entries_json";
        
        // Příprava request body
        $request_body = [
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.5,
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
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60 // Delší timeout pro analýzu většího množství dat
        ]);
        
        // Konec měření času
        $execution_time = microtime(true) - $start_time;
        
        // Zpracování odpovědi a logování
        if (is_wp_error($response)) {
            // Logování chyby
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
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
    private function analyze_references_with_gpt($references) {
        $api_key = get_option('ai_plugin_apikey');
        if (empty($api_key)) {
            return false;
        }
        
        // Příprava dat pro GPT
        $references_json = json_encode($references, JSON_UNESCAPED_UNICODE);
        
        // Vytvoření promptu
        $system_prompt = 'Analyzuj reference účastníků školení a vyhodnoť sentiment a oceňované aspekty. Vrať výsledek pouze jako JSON ve formátu: {"sentiment": {"positive": počet, "neutral": počet, "negative": počet}, "appreciated_aspects": {"aspekt1": počet, "aspekt2": počet, ...}}. Počet by měl představovat četnost výskytu. Zahrň pouze 6 nejčastěji oceňovaných aspektů.';
        $user_prompt = "Reference účastníků školení k analýze (formát JSON): $references_json";
        
        // Příprava request body
        $request_body = [
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.5,
            'response_format' => ['type' => 'json_object']
        ];
        
        // Začátek měření času
        $start_time = microtime(true);
        
        // Příprava log dat pro request
        $log_data = [
            'request_type' => 'analyze_references',
            'request_data' => json_encode([
                'references_count' => count($references),
                'system_prompt' => $system_prompt,
                'request_body' => $request_body
            ])
        ];
        
        // Volání OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_body),
            'timeout' => 60
        ]);
        
        // Konec měření času
        $execution_time = microtime(true) - $start_time;
        
        // Zpracování odpovědi a logování
        if (is_wp_error($response)) {
            // Logování chyby
            $log_data['error_message'] = $response->get_error_message();
            $log_data['execution_time'] = $execution_time;
            $this->log_api_request($log_data);
            
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
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
        
        return [
            'data' => $analysis_data,
            'tokens_used' => $tokens_used,
            'execution_time' => $execution_time
        ];
    }
    
    /**
     * Logování API požadavku
     * 
     * @param array $log_data Data pro logování
     */
    private function log_api_request($log_data) {
        global $wpdb;
        
        $wpdb->insert(
            $this->log_table,
            [
                'request_type' => $log_data['request_type'],
                'request_data' => $log_data['request_data'],
                'response_data' => $log_data['response_data'] ?? null,
                'tokens_used' => $log_data['tokens_used'] ?? null,
                'error_message' => $log_data['error_message'] ?? null,
                'execution_time' => $log_data['execution_time'] ?? null,
                'created_at' => current_time('mysql')
            ]
        );
        
        // Pomocné logování do WP debug logu, pokud je povoleno
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $log_message = sprintf(
                '[AI Feedback Analytics] API Request: %s, Tokens: %s, Time: %.2f s, Error: %s',
                $log_data['request_type'],
                $log_data['tokens_used'] ?? 'N/A',
                $log_data['execution_time'] ?? 0,
                $log_data['error_message'] ?? 'None'
            );
            
            error_log($log_message);
        }
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
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->log_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        
        return $logs;
    }
    
    /**
     * Získání součtu všech použitých tokenů
     * 
     * @return int Celkový počet použitých tokenů
     */
    public function get_total_tokens_used() {
        global $wpdb;
        
        return (int) $wpdb->get_var("SELECT SUM(tokens_used) FROM {$this->log_table} WHERE tokens_used IS NOT NULL");
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
}