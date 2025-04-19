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
        // Počet účastníků (unikátních reflexí)
        $total_participants = $this->count_posts('reflexe');
        
        // Počet zápisků
        $total_entries = $this->count_entries();
        
        // Průměr zápisků na účastníka
        $avg_entries = $total_participants > 0 ? round($total_entries / $total_participants, 1) : 0;
        
        // Počet referencí
        $total_references = $this->count_posts('reference');
        
        return [
            'total_participants' => $total_participants,
            'total_entries' => $total_entries,
            'avg_entries_per_participant' => $avg_entries,
            'total_references' => $total_references
        ];
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
     * Získání dat o aktivitě pro graf
     * 
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @param string $keyword Klíčové slovo pro filtrování
     * @return array Data pro graf aktivity
     */
    public function get_activity_data($date_from = '', $date_to = '', $keyword = '') {
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
        $entries_by_date = [];
        
        // Data aktivit podle data
        foreach ($posts as $post) {
            $post_date = get_the_date('Y-m-d', $post->ID);
            
            // Počítání zápisků v každém postu
            $content = $post->post_content;
            $entries = explode("---", $content);
            $entries = array_filter($entries, function($entry) {
                return trim($entry) !== '';
            });
            
            if (!isset($entries_by_date[$post_date])) {
                $entries_by_date[$post_date] = 0;
            }
            
            $entries_by_date[$post_date] += count($entries);
        }
        
        // Získání dat pro reference
        $ref_args = [
            'post_type' => 'reference',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        // Přidání filtrů podle data
        if (!empty($date_from) || !empty($date_to)) {
            $ref_args['date_query'] = [];
            
            if (!empty($date_from)) {
                $ref_args['date_query'][] = [
                    'after' => $date_from,
                    'inclusive' => true
                ];
            }
            
            if (!empty($date_to)) {
                $ref_args['date_query'][] = [
                    'before' => $date_to,
                    'inclusive' => true
                ];
            }
        }
        
        $references = get_posts($ref_args);
        $refs_by_date = [];
        
        foreach ($references as $ref) {
            $ref_date = get_the_date('Y-m-d', $ref->ID);
            
            if (!isset($refs_by_date[$ref_date])) {
                $refs_by_date[$ref_date] = 0;
            }
            
            $refs_by_date[$ref_date]++;
        }
        
        // Vytvoření kompletního seznamu dat
        $all_dates = array_unique(array_merge(array_keys($entries_by_date), array_keys($refs_by_date)));
        sort($all_dates);
        
        $entries_data = [];
        $refs_data = [];
        
        foreach ($all_dates as $date) {
            $entries_data[] = $entries_by_date[$date] ?? 0;
            $refs_data[] = $refs_by_date[$date] ?? 0;
        }
        
        // Pokud nemáme žádná data, vrátíme testovací data
        if (empty($all_dates)) {
            return [
                'labels' => ['2023-01-01', '2023-01-02', '2023-01-03', '2023-01-04', '2023-01-05'],
                'entries' => [5, 7, 3, 8, 6],
                'references' => [2, 0, 1, 3, 2]
            ];
        }
        
        return [
            'labels' => $all_dates,
            'entries' => $entries_data,
            'references' => $refs_data
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
}