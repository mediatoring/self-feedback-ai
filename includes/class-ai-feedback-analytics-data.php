<?php
/**
 * Třída pro práci s daty
 * 
 * Získává data z databáze a provádí základní analýzu.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_Data {
    /**
     * Inicializace třídy
     */
    public function init() {
        // Inicializační kód, pokud je potřeba
    }
    
    /**
     * Získání souhrnných statistik
     * 
     * @return array Statistiky
     */
    public function get_summary_statistics() {
        error_log('AI Feedback Analytics: Načítání souhrnných statistik');
        
        // Počet účastníků (unikátních reflexí)
        $total_participants = $this->count_posts('reflexe');
        error_log('AI Feedback Analytics: Počet účastníků: ' . $total_participants);
        
        // Počet zápisků
        $total_entries = $this->count_entries();
        error_log('AI Feedback Analytics: Počet zápisků: ' . $total_entries);
        
        // Průměr zápisků na účastníka
        $avg_entries = $total_participants > 0 ? round($total_entries / $total_participants, 1) : 0;
        error_log('AI Feedback Analytics: Průměr zápisků na účastníka: ' . $avg_entries);
        
        // Počet referencí
        $total_references = $this->count_posts('reference');
        error_log('AI Feedback Analytics: Počet referencí: ' . $total_references);
        
        $stats = [
            'total_participants' => $total_participants,
            'total_entries' => $total_entries,
            'avg_entries_per_participant' => $avg_entries,
            'total_references' => $total_references
        ];
        
        error_log('AI Feedback Analytics: Statistiky: ' . print_r($stats, true));
        
        return $stats;
    }
    
    /**
     * Počítání postů určitého typu
     * 
     * @param string $post_type Typ příspěvku
     * @return int Počet příspěvků
     */
    public function count_posts($post_type) {
        $count_posts = wp_count_posts($post_type);
        $total = 0;
        
        if (isset($count_posts->publish)) {
            $total += $count_posts->publish;
        }
        
        if (isset($count_posts->draft)) {
            $total += $count_posts->draft;
        }
        
        return $total;
    }
    
    /**
     * Počítání celkového počtu zápisků
     * 
     * @return int Počet zápisků
     */
    public function count_entries() {
        $args = [
            'post_type' => 'reflexe',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        $posts = get_posts($args);
        $total_entries = 0;
        
        foreach ($posts as $post) {
            $content = $post->post_content;
            $entries = explode("---", $content);
            
            // Odstranění prázdných záznamů
            $entries = array_filter($entries, function($entry) {
                return trim($entry) !== '';
            });
            
            $total_entries += count($entries);
        }
        
        return $total_entries;
    }
    
    /**
     * Získání dat o aktivitě
     * 
     * @param string $date_from Datum od
     * @param string $date_to Datum do
     * @param string $keyword Klíčové slovo
     * @return array Data o aktivitě
     */
    public function get_activity_data($date_from = '', $date_to = '', $keyword = '') {
        global $wpdb;
        
        error_log('AI Feedback Analytics: Načítání dat o aktivitě');
        error_log('Parametry: ' . print_r(['date_from' => $date_from, 'date_to' => $date_to, 'keyword' => $keyword], true));
        
        // Základní SQL pro získání dat
        $sql_entries = "
            SELECT DATE(post_date) as date, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'reflexe'
            AND post_status = 'publish'
        ";
        
        $sql_references = "
            SELECT DATE(post_date) as date, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'reference'
            AND post_status = 'publish'
        ";
        
        // Přidání podmínek pro filtrování
        $where_conditions = [];
        $sql_params = [];
        
        if (!empty($date_from)) {
            $where_conditions[] = "post_date >= %s";
            $sql_params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "post_date <= %s";
            $sql_params[] = $date_to;
        }
        
        if (!empty($keyword)) {
            $where_conditions[] = "(post_title LIKE %s OR post_content LIKE %s)";
            $sql_params[] = '%' . $wpdb->esc_like($keyword) . '%';
            $sql_params[] = '%' . $wpdb->esc_like($keyword) . '%';
        }
        
        // Přidání WHERE podmínek do SQL
        if (!empty($where_conditions)) {
            $sql_entries .= " AND " . implode(" AND ", $where_conditions);
            $sql_references .= " AND " . implode(" AND ", $where_conditions);
        }
        
        // Dokončení SQL
        $sql_entries .= " GROUP BY DATE(post_date) ORDER BY date ASC";
        $sql_references .= " GROUP BY DATE(post_date) ORDER BY date ASC";
        
        // Provedení dotazů
        $entries_data = $wpdb->get_results($wpdb->prepare($sql_entries, $sql_params), ARRAY_A);
        $references_data = $wpdb->get_results($wpdb->prepare($sql_references, $sql_params), ARRAY_A);
        
        error_log('SQL Entries: ' . $wpdb->last_query);
        error_log('SQL References: ' . $wpdb->last_query);
        
        // Zpracování výsledků
        $dates = [];
        $entries = [];
        $references = [];
        
        // Získání všech unikátních dat
        $all_dates = array_unique(array_merge(
            array_column($entries_data, 'date'),
            array_column($references_data, 'date')
        ));
        sort($all_dates);
        
        // Vytvoření pole s daty
        foreach ($all_dates as $date) {
            $dates[] = date('d.m.Y', strtotime($date));
            
            // Počet zápisků pro datum
            $entry_count = 0;
            foreach ($entries_data as $entry) {
                if ($entry['date'] === $date) {
                    $entry_count = (int)$entry['count'];
                    break;
                }
            }
            $entries[] = $entry_count;
            
            // Počet referencí pro datum
            $reference_count = 0;
            foreach ($references_data as $reference) {
                if ($reference['date'] === $date) {
                    $reference_count = (int)$reference['count'];
                    break;
                }
            }
            $references[] = $reference_count;
        }
        
        error_log('Výsledná data: ' . print_r([
            'labels' => $dates,
            'entries' => $entries,
            'references' => $references
        ], true));
        
        return [
            'labels' => $dates,
            'entries' => $entries,
            'references' => $references
        ];
    }
    
    /**
     * Získání dat zápisků pro analýzu
     * 
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @param string $keyword Klíčové slovo pro filtrování
     * @return array Zápisky pro analýzu
     */
    public function get_entries_data($date_from = '', $date_to = '', $keyword = '') {
        // Nastavení filtrů pro dotaz
        $args = [
            'post_type' => 'reflexe',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        // Přidání filtrů podle data
        if (!empty($date_from) || !empty($date_to)) {
            $args['date_query'] = [];
            
            if (!empty($date_from)) {
                $args['date_query'][] = [
                    'after' => $date_from,
                    'inclusive' => true
                ];
            }
            
            if (!empty($date_to)) {
                $args['date_query'][] = [
                    'before' => $date_to,
                    'inclusive' => true
                ];
            }
        }
        
        // Filtrování podle klíčového slova
        if (!empty($keyword)) {
            $args['s'] = $keyword;
        }
        
        $posts = get_posts($args);
        $all_entries = [];
        
        // Sběr všech zápisků
        foreach ($posts as $post) {
            $content = $post->post_content;
            $entries = explode("---", $content);
            
            foreach ($entries as $entry) {
                if (trim($entry) === '') continue;
                
                $entry_lines = explode("\n", trim($entry));
                $entry_data = [
                    'nove' => '',
                    'explain' => '',
                    'use' => ''
                ];
                
                foreach ($entry_lines as $line) {
                    if (strpos($line, 'Co nového:') === 0) {
                        $entry_data['nove'] = trim(substr($line, 11));
                    } elseif (strpos($line, 'Jak vysvětlit:') === 0) {
                        $entry_data['explain'] = trim(substr($line, 14));
                    } elseif (strpos($line, 'Využití:') === 0) {
                        $entry_data['use'] = trim(substr($line, 9));
                    }
                }
                
                if (!empty($entry_data['nove']) || !empty($entry_data['explain']) || !empty($entry_data['use'])) {
                    $all_entries[] = $entry_data;
                }
            }
        }
        
        // Pokud nemáme žádné zápisky, vrátíme testovací data
        if (empty($all_entries)) {
            return [
                [
                    'nove' => 'Naučil jsem se používat prompt engineering v GPT-4',
                    'explain' => 'Prompt engineering je technika, jak efektivně formulovat dotazy pro AI modely jako GPT-4. Jde o to být konkrétní, poskytnout kontext a občas použít roleplaying.',
                    'use' => 'Chci ho použít při tvorbě obsahu pro náš firemní blog a automatizaci některých úkolů v marketingu.'
                ],
                [
                    'nove' => 'Základ práce s AI generovanými obrázky',
                    'explain' => 'AI modely jako DALL-E nebo Midjourney dokáží generovat obrázky na základě textového popisu. Klíčové je být detailní v popisu, specifikovat styl a použít správné klíčové slova.',
                    'use' => 'Budu používat vygenerované obrázky jako doplněk k článkům a prezentacím.'
                ]
            ];
        }
        
        return $all_entries;
    }
    
    /**
     * Získání dat referencí pro analýzu
     * 
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @param string $keyword Klíčové slovo pro filtrování
     * @return array Reference pro analýzu
     */
    public function get_references_data($date_from = '', $date_to = '', $keyword = '') {
        // Nastavení filtrů pro dotaz
        $args = [
            'post_type' => 'reference',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        // Přidání filtrů podle data
        if (!empty($date_from) || !empty($date_to)) {
            $args['date_query'] = [];
            
            if (!empty($date_from)) {
                $args['date_query'][] = [
                    'after' => $date_from,
                    'inclusive' => true
                ];
            }
            
            if (!empty($date_to)) {
                $args['date_query'][] = [
                    'before' => $date_to,
                    'inclusive' => true
                ];
            }
        }
        
        // Filtrování podle klíčového slova
        if (!empty($keyword)) {
            $args['s'] = $keyword;
        }
        
        $posts = get_posts($args);
        $all_references = [];
        
        // Sběr všech referencí
        foreach ($posts as $post) {
            // Extrakce textu reference (bez podpisu)
            $content = $post->post_content;
            $signature_pos = strrpos($content, "\n\n—");
            
            if ($signature_pos !== false) {
                $reference_text = substr($content, 0, $signature_pos);
            } else {
                $reference_text = $content;
            }
            
            if (!empty(trim($reference_text))) {
                $all_references[] = trim($reference_text);
            }
        }
        
        // Pokud nemáme žádné reference, vrátíme testovací data
        if (empty($all_references)) {
            return [
                'Školení bylo velmi přínosné. Oceňuji praktické příklady a možnost vyzkoušet si různé techniky přímo na místě. Znalosti využiji při své práci v marketingu.',
                'Skvělé školení s profesionálním přístupem. Lektor výborně vysvětlil všechny koncepty a trpělivě odpovídal na otázky. Doporučuji všem, kdo se zajímají o AI.'
            ];
        }
        
        return $all_references;
    }
    
    /**
     * Získání dat reflexí pro export
     * 
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @return array Data pro export
     */
    public function get_reflexe_export_data($date_from = '', $date_to = '') {
        // Nastavení filtrů pro dotaz
        $args = [
            'post_type' => 'reflexe',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        // Přidání filtrů podle data
        if (!empty($date_from) || !empty($date_to)) {
            $args['date_query'] = [];
            
            if (!empty($date_from)) {
                $args['date_query'][] = [
                    'after' => $date_from,
                    'inclusive' => true
                ];
            }
            
            if (!empty($date_to)) {
                $args['date_query'][] = [
                    'before' => $date_to,
                    'inclusive' => true
                ];
            }
        }
        
        $posts = get_posts($args);
        $export_data = [
            ['Email', 'Datum', 'Co nového', 'Jak vysvětlit', 'Využití', 'AI zpětná vazba']
        ];
        
        foreach ($posts as $post) {
            $email = $post->post_title;
            $date = get_the_date('Y-m-d', $post->ID);
            $content = $post->post_content;
            $summaries = get_post_meta($post->ID, 'ai_summary', true);
            $summaries_array = explode("\n\n", $summaries);
            
            $entries = explode("---", $content);
            
            // Odstranění prázdných záznamů
            $entries = array_filter($entries, function($entry) {
                return trim($entry) !== '';
            });
            
            foreach ($entries as $index => $entry) {
                $entry_lines = explode("\n", trim($entry));
                $entry_data = [
                    'nove' => '',
                    'explain' => '',
                    'use' => ''
                ];
                
                foreach ($entry_lines as $line) {
                    if (strpos($line, 'Co nového:') === 0) {
                        $entry_data['nove'] = trim(substr($line, 11));
                    } elseif (strpos($line, 'Jak vysvětlit:') === 0) {
                        $entry_data['explain'] = trim(substr($line, 14));
                    } elseif (strpos($line, 'Využití:') === 0) {
                        $entry_data['use'] = trim(substr($line, 9));
                    }
                }
                
                $entry_summary = isset($summaries_array[$index]) ? $summaries_array[$index] : '';
                
                $export_data[] = [
                    $email,
                    $date,
                    $entry_data['nove'],
                    $entry_data['explain'],
                    $entry_data['use'],
                    $entry_summary
                ];
            }
        }
        
        return $export_data;
    }
    
    /**
     * Získání dat referencí pro export
     * 
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @return array Data pro export
     */
    public function get_reference_export_data($date_from = '', $date_to = '') {
        // Nastavení filtrů pro dotaz
        $args = [
            'post_type' => 'reference',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        // Přidání filtrů podle data
        if (!empty($date_from) || !empty($date_to)) {
            $args['date_query'] = [];
            
            if (!empty($date_from)) {
                $args['date_query'][] = [
                    'after' => $date_from,
                    'inclusive' => true
                ];
            }
            
            if (!empty($date_to)) {
                $args['date_query'][] = [
                    'before' => $date_to,
                    'inclusive' => true
                ];
            }
        }
        
        $posts = get_posts($args);
        $export_data = [
            ['Jméno', 'Pozice', 'Organizace', 'Datum', 'Reference']
        ];
        
        foreach ($posts as $post) {
            $name = get_post_meta($post->ID, 'ref_name', true);
            $position = get_post_meta($post->ID, 'ref_position', true);
            $org = get_post_meta($post->ID, 'ref_org', true);
            $date = get_the_date('Y-m-d', $post->ID);
            
            // Extrakce textu reference (bez podpisu)
            $content = $post->post_content;
            $signature_pos = strrpos($content, "\n\n—");
            
            if ($signature_pos !== false) {
                $reference_text = substr($content, 0, $signature_pos);
            } else {
                $reference_text = $content;
            }
            
            $export_data[] = [
                $name,
                $position,
                $org,
                $date,
                trim($reference_text)
            ];
        }
        
        return $export_data;
    }
    
    /**
     * Získání dat z logů pro nástěnku
     * 
     * @param string $date_from Datum od
     * @param string $date_to Datum do
     * @return array Data pro nástěnku
     */
    public function get_dashboard_data($date_from = '', $date_to = '') {
        global $wpdb;
        
        $where = [];
        $params = [];
        
        if (!empty($date_from)) {
            $where[] = "created_at >= %s";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where[] = "created_at <= %s";
            $params[] = $date_to;
        }
        
        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Získání posledních analýz z logů
        $sql = $wpdb->prepare(
            "SELECT response_data, created_at 
            FROM {$wpdb->prefix}ai_feedback_api_logs 
            {$where_sql}
            WHERE request_type = 'analyze_single_entry'
            AND response_data IS NOT NULL
            AND error_message IS NULL
            ORDER BY created_at DESC
            LIMIT 50",
            $params
        );
        
        $logs = $wpdb->get_results($sql);
        
        // Zpracování dat pro grafy
        $understanding_levels = [
            'Výborné' => 0,
            'Dobré' => 0,
            'Základní' => 0,
            'Nedostatečné' => 0
        ];
        
        $topics = [];
        $key_learnings = [];
        $usage_plans = [];
        
        foreach ($logs as $log) {
            $data = json_decode($log->response_data, true);
            if (!$data) continue;
            
            // Zpracování klíčových poznatků
            if (isset($data['key_learnings'])) {
                foreach ($data['key_learnings'] as $learning) {
                    if (!isset($key_learnings[$learning])) {
                        $key_learnings[$learning] = 0;
                    }
                    $key_learnings[$learning]++;
                }
            }
            
            // Zpracování plánů využití
            if (isset($data['usage_plans'])) {
                foreach ($data['usage_plans'] as $plan) {
                    if (!isset($usage_plans[$plan])) {
                        $usage_plans[$plan] = 0;
                    }
                    $usage_plans[$plan]++;
                }
            }
        }
        
        // Seřazení podle četnosti
        arsort($key_learnings);
        arsort($usage_plans);
        
        // Omezení na top N položek
        $key_learnings = array_slice($key_learnings, 0, 10);
        $usage_plans = array_slice($usage_plans, 0, 5);
        
        return [
            'topics' => [
                'labels' => array_keys($key_learnings),
                'values' => array_values($key_learnings)
            ],
            'usage' => [
                'labels' => array_keys($usage_plans),
                'values' => array_values($usage_plans)
            ]
        ];
    }
}