<?php
/**
 * WP CLI příkazy pro AI Feedback Analytics
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_CLI {
    
    /**
     * Instance API třídy
     * 
     * @var AI_Feedback_Analytics_API
     */
    private $api;
    
    /**
     * Konstruktor
     */
    public function __construct($api) {
        $this->api = $api;
    }
    
    /**
     * Re-analýza všech příspěvků
     * 
     * ## OPTIONS
     * 
     * [--type=<type>]
     * : Typ příspěvků k analýze (reflexe|reference|all)
     * default: all
     * 
     * [--batch-size=<size>]
     * : Počet příspěvků zpracovaných v jedné dávce
     * default: 10
     * 
     * ## EXAMPLES
     * 
     *     wp ai-feedback reanalyze
     *     wp ai-feedback reanalyze --type=reflexe
     *     wp ai-feedback reanalyze --batch-size=20
     * 
     * @when after_wp_load
     */
    public function reanalyze($args, $assoc_args) {
        // Výchozí hodnoty
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'all';
        $batch_size = isset($assoc_args['batch-size']) ? (int)$assoc_args['batch-size'] : 10;
        
        WP_CLI::log('Začínám re-analýzu příspěvků...');
        
        // Kontrola API klíče
        if (empty($this->api->get_api_key())) {
            WP_CLI::error('Chybí API klíč. Nastavte jej v nastavení pluginu.');
            return;
        }
        
        // Získání příspěvků
        $post_types = $type === 'all' ? ['reflexe', 'reference'] : [$type];
        
        $args = [
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ];
        
        $posts = get_posts($args);
        $total_posts = count($posts);
        
        if ($total_posts === 0) {
            WP_CLI::warning('Nebyly nalezeny žádné příspěvky k analýze.');
            return;
        }
        
        WP_CLI::log(sprintf('Nalezeno %d příspěvků k analýze.', $total_posts));
        
        // Progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Probíhá analýza', $total_posts);
        
        $analyzed = 0;
        $errors = 0;
        $error_messages = [];
        
        // Zpracování po dávkách
        $batches = array_chunk($posts, $batch_size);
        
        foreach ($batches as $batch) {
            foreach ($batch as $post) {
                try {
                    if ($post->post_type === 'reflexe') {
                        // Analýza reflexí
                        $entries = explode("---", $post->post_content);
                        $entries = array_filter($entries, function($entry) {
                            return trim($entry) !== '';
                        });
                        
                        if (!empty($entries)) {
                            $this->api->analyze_entries_with_gpt($entries, 'understanding');
                            $this->api->analyze_entries_with_gpt($entries, 'topics');
                            $analyzed++;
                        }
                    } else {
                        // Analýza referencí
                        $this->api->analyze_references_with_gpt([$post]);
                        $analyzed++;
                    }
                    
                    $progress->tick();
                    
                } catch (Exception $e) {
                    $errors++;
                    $error_message = sprintf(
                        'Chyba při analýze příspěvku %d: %s',
                        $post->ID,
                        $e->getMessage()
                    );
                    $error_messages[] = $error_message;
                    WP_CLI::warning($error_message);
                }
            }
            
            // Pauza mezi dávkami
            if (count($batches) > 1) {
                sleep(2);
            }
        }
        
        $progress->finish();
        
        // Výsledky
        WP_CLI::success(sprintf(
            'Re-analýza dokončena. Zpracováno %d z %d příspěvků, %d chyb.',
            $analyzed,
            $total_posts,
            $errors
        ));
        
        if (!empty($error_messages)) {
            WP_CLI::log('Chyby během zpracování:');
            foreach ($error_messages as $message) {
                WP_CLI::log('- ' . $message);
            }
        }
    }
}

// Registrace WP CLI příkazů
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ai-feedback', new AI_Feedback_Analytics_CLI(AI_Feedback_Analytics_API::get_instance()));
} 