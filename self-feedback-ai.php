<?php
/*
Plugin Name: AI Feedback & Reference Plugin
Description: Zápisník reflexí a referencí s využitím GPT OpenAI API. Nástroj pro osobní reflexi účastníků školení.
Version: 6.5
Author: Michal Kubíček
*/

defined('ABSPATH') || exit;

// Inicializace session na začátku
add_action('plugins_loaded', function() {
    if (!session_id()) {
        session_start();
    }
});

// Načtení potřebných tříd
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-data.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-export.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-core.php';

// Načtení CLI třídy pokud je dostupné WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-ai-feedback-analytics-cli.php';
}

// Inicializace analytického modulu
$analytics_core = AI_Feedback_Analytics_Core::get_instance();
$analytics_core->init();

// Inicializace tříd
$data = new AI_Feedback_Analytics_Data();
$api = new AI_Feedback_Analytics_API();
$export = new AI_Feedback_Analytics_Export();
$admin = AI_Feedback_Analytics_Admin::get_instance();
$admin->init($data, $api, $export);

// Hook pro automatickou analýzu při vytvoření/aktualizaci reflexe
add_action('save_post_reflexe', function($post_id, $post, $update) {
    // Pokud se jedná o autosave, přeskočíme
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Pokud nemáme oprávnění k editaci
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Pokud je post ve stavu 'auto-draft' nebo 'trash', přeskočíme
    if (in_array($post->post_status, ['auto-draft', 'trash'])) {
        return;
    }

    // Získání instance API třídy
    $api = new AI_Feedback_Analytics_API();
    
    // Analýza reflexe
    $analysis = $api->analyze_single_entry($post_id, $post->post_content);
    
    if ($analysis) {
        // Přidání notifikace do admin rozhraní
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Reflexe byla úspěšně analyzována pomocí AI.</p>';
            echo '</div>';
        });
    }
}, 10, 3);

// 1️⃣ CPT
add_action('init', function() {
    register_post_type('reflexe', [
        'labels' => ['name' => 'Reflexe'],
        'public' => true,
        'supports' => ['title', 'editor', 'custom-fields']
    ]);
    register_post_type('reference', [
        'labels' => ['name' => 'Reference'],
        'public' => true,
        'supports' => ['title', 'editor', 'custom-fields', 'thumbnail']
    ]);
});

// 2️⃣ Admin nastavení
add_action('admin_menu', function() {
    // Přidání hlavního menu
    add_menu_page(
        'AI Feedback & Reference',
        'AI Feedback',
        'manage_options',
        'ai-feedback-analytics',
        [AI_Feedback_Analytics_Admin::get_instance(), 'render_analytics_page'],
        'dashicons-feedback',
        30
    );

    // Přidání podmenu pro nastavení
    add_submenu_page(
        'ai-feedback-analytics',
        'Nastavení AI pluginu',
        'Nastavení',
        'manage_options',
        'ai-plugin-settings',
        'ai_plugin_settings_page'
    );
    
    // Přidání podmenu pro logy
    add_submenu_page(
        'ai-feedback-analytics',
        'Logy API požadavků',
        'Logy',
        'manage_options',
        'ai-feedback-logs',
        [AI_Feedback_Analytics_Admin::get_instance(), 'render_logs_page']
    );
});

add_action('admin_init', function() {
    register_setting('ai_plugin_options', 'ai_plugin_apikey');
    register_setting('ai_plugin_options', 'ai_plugin_model');
});

// Definice dostupných modelů jako konstanty
define('AI_FEEDBACK_AVAILABLE_MODELS', [
    'gpt-4o' => 'GPT-4o - Rychlý, inteligentní, flexibilní model',
    'gpt-4.1-mini' => 'GPT-4.1 mini - Vyvážený pro inteligenci, rychlost a cenu',
    'gpt-4.1-nano' => 'GPT-4.1 nano - Nejrychlejší a nejekonomičtější model',
    'o4-mini' => 'o4-mini - Rychlejší, dostupnější model pro uvažování',
    'o3' => 'o3 - Nejmocnější model pro uvažování',
    'o3-mini' => 'o3-mini - Menší alternativa k o3',
    'o1' => 'o1 - Předchozí plný model pro uvažování',
    'o1-mini' => 'o1-mini - Menší alternativa k o1',
    'o1-pro' => 'o1-pro - Verze o1 s větším výpočetním výkonem'
]);

// Výchozí model jako konstanta
define('AI_FEEDBACK_DEFAULT_MODEL', 'gpt-4o');

function ai_plugin_settings_page() {
    $available_models = AI_FEEDBACK_AVAILABLE_MODELS;
    ?>
    <div class="wrap">
        <h1>Nastavení AI pluginu</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ai_plugin_options');
            do_settings_sections('ai_plugin_settings');
            ?>
            <h2>API Klíč OpenAI</h2>
            <p>Zadejte API klíč z OpenAI pro přístup k GPT modelům</p>
            <input type="text" name="ai_plugin_apikey" value="<?php echo esc_attr(get_option('ai_plugin_apikey')); ?>" style="width: 400px;">

            <h2>Výběr GPT modelu</h2>
            <p>Vyberte model, který bude použit pro generování zpětné vazby a referencí</p>
            <select name="ai_plugin_model" style="width: 400px;">
                <?php
                $current_model = get_option('ai_plugin_model', AI_FEEDBACK_DEFAULT_MODEL);
                foreach ($available_models as $value => $label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($value),
                        selected($current_model, $value, false),
                        esc_html($label)
                    );
                }
                ?>
            </select>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 3️⃣ Shortcode formulář
add_shortcode('self_feedback_form', function() {
    if (!session_id()) session_start();
    $post_id = $_SESSION['reflexe_id'] ?? null;
    $post = $post_id ? get_post($post_id) : null;
    $summary = $post_id ? get_post_meta($post_id, 'ai_summary', true) : '';
    $ref_preview = $_SESSION['last_generated_reference'] ?? '';

    ob_start();
    ?>
    <div>
        <div id="tabs" class="sf-tabs">
            <span class="sf-tab active" data-tab="zapisnik">Zápisník</span>
            <span class="sf-tab" data-tab="reference">Reference</span>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div id="zapisnik" class="sf-section active">
                <?php if (!$post_id): ?>
                    <!-- Formulář pro začátek zápisníku -->
                    <div class="sf-entry-form sf-start-form">
                        <h3>Začněte svůj zápisník ze školení</h3>
                        <p>Váš zápisník vám pomůže zachytit klíčové poznatky ze školení a získat personalizovanou AI zpětnou vazbu. Na konci vám bude zaslán e-mailem kompletní přehled vašeho učení.</p>
                        <input type="email" name="sf_email" placeholder="Váš e-mail pro zaslání zápisků" required>
                        <button type="submit" name="sf_create" class="sf-button sf-button-primary">Vytvořit zápisník</button>
                    </div>
                
                    <!-- Formulář pro přidání nového zápisku -->
                    <div class="sf-entry-form sf-new-entry">
                        <h3>Váš zápisník ze školení</h3>
                        <p>Každý zápisek by se měl týkat jednoho tématu nebo poznatku. Po uložení zápisku dostanete okamžitou AI zpětnou vazbu, která vám pomůže lépe pochopit a využít nové znalosti.</p>
                        <div class="sf-input-group">
                            <label for="sf-nove">Co nového jste se naučili?</label>
                            <p class="sf-help-text">Popište konkrétní znalost, dovednost nebo koncept, který pro vás byl nový nebo zajímavý.</p>
                            <textarea id="sf-nove" name="sf_nove" placeholder="Například: Naučil/a jsem se, jak efektivně používat metodu XYZ pro..."></textarea>
                        </div>
                        <div class="sf-input-group">
                            <label for="sf-explain">Jak byste to vysvětlili kolegovi?</label>
                            <p class="sf-help-text">Vysvětlení vlastními slovy vám pomůže lépe pochopit téma a upevnit znalosti.</p>
                            <textarea id="sf-explain" name="sf_explain" placeholder="Například: Představ si, že... Funguje to tak, že..."></textarea>
                        </div>
                        <div class="sf-input-group">
                            <label for="sf-use">Kde a jak to využijete v praxi?</label>
                            <p class="sf-help-text">Popište konkrétní situaci nebo projekt, kde novou znalost využijete.</p>
                            <textarea id="sf-use" name="sf_use" placeholder="Například: Tuto metodu použiji příští týden při..."></textarea>
                        </div>
                        <div class="sf-button-container">
                            <button type="submit" name="sf_add_entry" class="sf-button sf-button-primary">
                                <span class="button-icon">+</span> Uložit zápisek a získat AI zpětnou vazbu
                            </button>
                            <button type="submit" name="sf_send" class="sf-button sf-button-secondary">
                                <span class="button-icon">✉</span> Dokončit a poslat na e-mail
                            </button>
                        </div>
                    </div>
                    
                    <!-- Historie zápisků -->
                    <div class="sf-entries-history">
                        <h3>Vaše zápisky s AI zpětnou vazbou</h3>
                        <p>Zde vidíte vaše zápisky spolu s personalizovanou AI analýzou vašeho učení.</p>
                        <?php 
                        // Získání obsahu a rozdělení na jednotlivé zápisky
                        $content = $post->post_content;
                        $entries = explode("---", $content);
                        $summaries = explode("\n\n", $summary);
                        
                        // Odstranění prázdných záznamů
                        $entries = array_filter($entries, function($entry) {
                            return trim($entry) !== '';
                        });
                        
                        // Zobrazení zápisků od nejnovějšího
                        $entries = array_reverse($entries);
                        $summaries = array_reverse($summaries);
                        
                        if (empty($entries)): 
                        ?>
                            <div class="sf-no-entries">
                                <p>Zatím nemáte žádné zápisky. Začněte vytvořením prvního zápisku pomocí formuláře výše.</p>
                            </div>
                        <?php
                        else:
                            foreach ($entries as $index => $entry): 
                                $entry_summary = isset($summaries[$index]) ? $summaries[$index] : '';
                                $entry_lines = explode("\n", trim($entry));
                                $entry_data = [
                                    'nove' => '',
                                    'explain' => '',
                                    'use' => ''
                                ];
                                
                                $current_section = '';
                                
                                foreach ($entry_lines as $line) {
                                    $line = trim($line);
                                    if (empty($line)) continue;
                                    
                                    // Kontrola starého formátu
                                    if (strpos($line, 'Co nového:') === 0) {
                                        $entry_data['nove'] = ltrim(trim(substr($line, 11)), ': ');
                                    } elseif (strpos($line, 'Jak vysvětlit:') === 0) {
                                        $entry_data['explain'] = ltrim(trim(substr($line, 14)), ': ');
                                    } elseif (strpos($line, 'Využití:') === 0) {
                                        $entry_data['use'] = ltrim(trim(substr($line, 9)), ': ');
                                    }
                                    // Kontrola nového formátu
                                    elseif ($line === '#nove') {
                                        $current_section = 'nove';
                                    } elseif ($line === '#explain') {
                                        $current_section = 'explain';
                                    } elseif ($line === '#use') {
                                        $current_section = 'use';
                                    }
                                    // Přidání obsahu do aktuální sekce
                                    elseif ($current_section) {
                                        $line = ltrim($line, ': '); // Odstranění případné úvodní dvojtečky
                                        if (empty($entry_data[$current_section])) {
                                            $entry_data[$current_section] = $line;
                                        } else {
                                            $entry_data[$current_section] .= "\n" . $line;
                                        }
                                    }
                                }
                        ?>
                            <div class="sf-entry-box">
                                <div class="sf-entry-content">
                                    <?php if (!empty($entry_data['nove'])): ?>
                                        <?php 
                                        // Parsování JSON zpětné vazby
                                        $ai_feedback = null;
                                        if (!empty($entry_summary)) {
                                            try {
                                                $ai_feedback = json_decode($entry_summary, true);
                                            } catch (Exception $e) {
                                                error_log('Chyba při parsování JSON zpětné vazby: ' . $e->getMessage());
                                            }
                                        }
                                        ?>
                                        <?php if ($ai_feedback && !empty($ai_feedback['tema'])): ?>
                                            <div class="sf-entry-tema">
                                                <?php echo esc_html($ai_feedback['tema']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Nová znalost</span>
                                            <p><?php echo nl2br(esc_html($entry_data['nove'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($entry_data['explain'])): ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Vlastní vysvětlení</span>
                                            <p><?php echo nl2br(esc_html($entry_data['explain'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($entry_data['use'])): ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Praktické využití</span>
                                            <p><?php echo nl2br(esc_html($entry_data['use'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($entry_summary)): ?>
                                    <div class="sf-entry-summary">
                                        <span class="sf-summary-label">AI zpětná vazba</span>
                                        <div class="sf-summary-content">
                                            <?php
                                            if ($ai_feedback) {
                                                // Formátování strukturované zpětné vazby
                                                if (!empty($ai_feedback['shrnuti'])) {
                                                    echo nl2br(esc_html($ai_feedback['shrnuti'])) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['konkretnost'])) {
                                                    echo "Konkrétnost plánu: " . esc_html($ai_feedback['konkretnost']['hodnoceni']) . "\n";
                                                    echo esc_html($ai_feedback['konkretnost']['komentar']) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['porozumeni'])) {
                                                    echo "Úroveň porozumění: " . esc_html($ai_feedback['porozumeni']['hodnoceni']) . "\n";
                                                    echo esc_html($ai_feedback['porozumeni']['komentar']) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['doporuceni'])) {
                                                    echo "Doporučení pro zlepšení:\n";
                                                    foreach ($ai_feedback['doporuceni'] as $doporuceni) {
                                                        echo "• " . esc_html($doporuceni) . "\n";
                                                    }
                                                }
                                            } else {
                                                // Pokud není JSON formát, zobrazíme původní text
                                                echo nl2br(esc_html($entry_summary));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <style>
                        /* Základní styly pro formulář */
                        .sf-help-text {
                            color: #666;
                            font-size: 0.9em;
                            margin: 2px 0 8px;
                            font-style: italic;
                        }
                        
                        .sf-input-group {
                            margin-bottom: 20px;
                        }
                        
                        .sf-input-group label {
                            font-weight: 600;
                            display: block;
                            margin-bottom: 5px;
                            color: #2c3338;
                        }
                        
                        .sf-input-group textarea {
                            width: 100%;
                            min-height: 100px;
                            padding: 12px;
                            border: 1px solid #ddd;
                            border-radius: 6px;
                            font-size: 14px;
                            line-height: 1.6;
                            transition: all 0.3s ease;
                        }
                        
                        .sf-input-group textarea:focus {
                            border-color: #6c5ce7;
                            box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.1);
                            outline: none;
                        }
                        
                        /* Styly pro historii zápisků */
                        .sf-entries-history {
                            margin-top: 40px;
                        }
                        
                        .sf-entry-box {
                            background: white;
                            border-radius: 12px;
                            margin-bottom: 25px;
                            overflow: hidden;
                            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                            border: 1px solid #eee;
                        }
                        
                        .sf-entry-content {
                            padding: 20px;
                        }
                        
                        .sf-entry-item {
                            margin-bottom: 20px;
                            padding-bottom: 20px;
                            border-bottom: 1px solid #f0f0f0;
                        }
                        
                        .sf-entry-item:last-child {
                            margin-bottom: 0;
                            padding-bottom: 0;
                            border-bottom: none;
                        }
                        
                        .sf-entry-label {
                            display: inline-block;
                            font-size: 13px;
                            font-weight: 600;
                            color: #6c5ce7;
                            background: rgba(108, 92, 231, 0.1);
                            padding: 4px 12px;
                            border-radius: 20px;
                            margin-bottom: 8px;
                        }
                        
                        .sf-entry-item p {
                            margin: 0;
                            color: #2c3338;
                            line-height: 1.6;
                            font-size: 15px;
                        }
                        
                        .sf-entry-summary {
                            background: #f8f9ff;
                            padding: 20px;
                            border-top: 1px solid #eef0ff;
                        }
                        
                        .sf-summary-label {
                            display: inline-block;
                            font-size: 13px;
                            font-weight: 600;
                            color: #6c5ce7;
                            margin-bottom: 12px;
                            background: white;
                            padding: 4px 12px;
                            border-radius: 20px;
                            box-shadow: 0 2px 4px rgba(108, 92, 231, 0.1);
                        }
                        
                        .sf-summary-content {
                            color: #2c3338;
                            line-height: 1.6;
                            font-size: 15px;
                            white-space: pre-line;
                        }
                        
                        .sf-no-entries {
                            background: #f9f9f9;
                            padding: 30px;
                            border-radius: 12px;
                            text-align: center;
                            color: #666;
                            border: 2px dashed #eee;
                        }
                        
                        /* Styly pro tlačítka */
                        .sf-button-container {
                            display: flex;
                            gap: 12px;
                            margin-top: 25px;
                        }
                        
                        .sf-button {
                            padding: 12px 24px;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            font-size: 14px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            transition: all 0.2s ease;
                        }
                        
                        .sf-button-primary {
                            background: #6c5ce7;
                            color: white;
                        }
                        
                        .sf-button-primary:hover {
                            background: #5649c0;
                            transform: translateY(-1px);
                        }
                        
                        .sf-button-secondary {
                            background: #f8f9fa;
                            color: #2c3338;
                            border: 1px solid #ddd;
                        }
                        
                        .sf-button-secondary:hover {
                            background: #f0f0f0;
                            transform: translateY(-1px);
                        }
                        
                        .button-icon {
                            font-size: 18px;
                            line-height: 1;
                        }
                    </style>
                <?php else: ?>
                    <!-- Formulář pro přidání nového zápisku -->
                    <div class="sf-entry-form sf-new-entry">
                        <h3>Zápisník ze školení</h3>
                        <p>Zaznamenávejte si své myšlenky a reflexe ze školení. Jeden zápisek = jedno téma.</p>
                        <div class="sf-input-group">
                            <label for="sf-nove">Co nového jste se naučili?</label>
                            <textarea id="sf-nove" name="sf_nove" placeholder="Popište nové znalosti nebo dovednosti, které jste získali"></textarea>
                        </div>
                        <div class="sf-input-group">
                            <label for="sf-explain">Jak byste to vysvětlili kolegovi?</label>
                            <textarea id="sf-explain" name="sf_explain" placeholder="Vysvětlete koncept vlastními slovy, jako byste ho představovali kolegovi"></textarea>
                        </div>
                        <div class="sf-input-group">
                            <label for="sf-use">Kde to můžete využít zítra?</label>
                            <textarea id="sf-use" name="sf_use" placeholder="Popište konkrétní případ, kdy tuto znalost využijete v praxi"></textarea>
                        </div>
                        <div class="sf-button-container">
                            <button type="submit" name="sf_add_entry" class="sf-button sf-button-primary">
                                <span class="button-icon">+</span> Nový zápisek
                            </button>
                            <button type="submit" name="sf_send" class="sf-button sf-button-secondary">
                                <span class="button-icon">✉</span> Hotovo, poslat na e-mail
                            </button>
                        </div>
                    </div>
                    
                    <!-- Historie zápisků -->
                    <div class="sf-entries-history">
                        <h3>Vaše zápisky s AI zpětnou vazbou</h3>
                        <p>Zde vidíte vaše zápisky spolu s personalizovanou AI analýzou vašeho učení.</p>
                        <?php 
                        // Získání obsahu a rozdělení na jednotlivé zápisky
                        $content = $post->post_content;
                        $entries = explode("---", $content);
                        $summaries = explode("\n\n", $summary);
                        
                        // Odstranění prázdných záznamů
                        $entries = array_filter($entries, function($entry) {
                            return trim($entry) !== '';
                        });
                        
                        // Zobrazení zápisků od nejnovějšího
                        $entries = array_reverse($entries);
                        $summaries = array_reverse($summaries);
                        
                        if (empty($entries)): 
                        ?>
                            <div class="sf-no-entries">
                                <p>Zatím nemáte žádné zápisky. Vytvořte svůj první zápisek pomocí formuláře výše.</p>
                            </div>
                        <?php
                        else:
                            foreach ($entries as $index => $entry): 
                                $entry_summary = isset($summaries[$index]) ? $summaries[$index] : '';
                                $entry_lines = explode("\n", trim($entry));
                                $entry_data = [
                                    'nove' => '',
                                    'explain' => '',
                                    'use' => ''
                                ];
                                
                                $current_section = '';
                                
                                foreach ($entry_lines as $line) {
                                    $line = trim($line);
                                    if (empty($line)) continue;
                                    
                                    // Kontrola starého formátu
                                    if (strpos($line, 'Co nového:') === 0) {
                                        $entry_data['nove'] = ltrim(trim(substr($line, 11)), ': ');
                                    } elseif (strpos($line, 'Jak vysvětlit:') === 0) {
                                        $entry_data['explain'] = ltrim(trim(substr($line, 14)), ': ');
                                    } elseif (strpos($line, 'Využití:') === 0) {
                                        $entry_data['use'] = ltrim(trim(substr($line, 9)), ': ');
                                    }
                                    // Kontrola nového formátu
                                    elseif ($line === '#nove') {
                                        $current_section = 'nove';
                                    } elseif ($line === '#explain') {
                                        $current_section = 'explain';
                                    } elseif ($line === '#use') {
                                        $current_section = 'use';
                                    }
                                    // Přidání obsahu do aktuální sekce
                                    elseif ($current_section) {
                                        $line = ltrim($line, ': '); // Odstranění případné úvodní dvojtečky
                                        if (empty($entry_data[$current_section])) {
                                            $entry_data[$current_section] = $line;
                                        } else {
                                            $entry_data[$current_section] .= "\n" . $line;
                                        }
                                    }
                                }
                        ?>
                            <div class="sf-entry-box">
                                <div class="sf-entry-content">
                                    <?php if (!empty($entry_data['nove'])): ?>
                                        <?php 
                                        // Parsování JSON zpětné vazby
                                        $ai_feedback = null;
                                        if (!empty($entry_summary)) {
                                            try {
                                                $ai_feedback = json_decode($entry_summary, true);
                                            } catch (Exception $e) {
                                                error_log('Chyba při parsování JSON zpětné vazby: ' . $e->getMessage());
                                            }
                                        }
                                        ?>
                                        <?php if ($ai_feedback && !empty($ai_feedback['tema'])): ?>
                                            <div class="sf-entry-tema">
                                                <?php echo esc_html($ai_feedback['tema']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Nová znalost</span>
                                            <p><?php echo nl2br(esc_html($entry_data['nove'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($entry_data['explain'])): ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Vlastní vysvětlení</span>
                                            <p><?php echo nl2br(esc_html($entry_data['explain'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($entry_data['use'])): ?>
                                        <div class="sf-entry-item">
                                            <span class="sf-entry-label">Praktické využití</span>
                                            <p><?php echo nl2br(esc_html($entry_data['use'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($entry_summary)): ?>
                                    <div class="sf-entry-summary">
                                        <span class="sf-summary-label">AI zpětná vazba</span>
                                        <div class="sf-summary-content">
                                            <?php
                                            if ($ai_feedback) {
                                                // Formátování strukturované zpětné vazby
                                                if (!empty($ai_feedback['shrnuti'])) {
                                                    echo nl2br(esc_html($ai_feedback['shrnuti'])) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['konkretnost'])) {
                                                    echo "Konkrétnost plánu: " . esc_html($ai_feedback['konkretnost']['hodnoceni']) . "\n";
                                                    echo esc_html($ai_feedback['konkretnost']['komentar']) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['porozumeni'])) {
                                                    echo "Úroveň porozumění: " . esc_html($ai_feedback['porozumeni']['hodnoceni']) . "\n";
                                                    echo esc_html($ai_feedback['porozumeni']['komentar']) . "\n\n";
                                                }
                                                
                                                if (!empty($ai_feedback['doporuceni'])) {
                                                    echo "Doporučení pro zlepšení:\n";
                                                    foreach ($ai_feedback['doporuceni'] as $doporuceni) {
                                                        echo "• " . esc_html($doporuceni) . "\n";
                                                    }
                                                }
                                            } else {
                                                // Pokud není JSON formát, zobrazíme původní text
                                                echo nl2br(esc_html($entry_summary));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="reference" class="sf-section">
                <div class="sf-entry-form">
                    <h3>Vytvořit referenci</h3>
                    <p>Sdílejte své zkušenosti ze školení formou reference.</p>
                    
                    <div class="sf-reference-mode">
                        <label><input type="radio" name="ref_mode" value="manual" checked> Napsat sám</label>
                        <label><input type="radio" name="ref_mode" value="ai"> S pomocí AI</label>
                    </div>
                    
                    <div id="ref_manual">
                        <textarea name="ref_manual_text" placeholder="Vaše vlastní reference..."><?php echo esc_textarea($ref_preview); ?></textarea>
                    </div>
                    
                    <div id="ref_ai" style="display:none">
                        <div class="sf-input-group">
                            <label for="ref_q1">Jak školení probíhalo?</label>
                            <input type="text" id="ref_q1" name="ref_q1" placeholder="Popište průběh školení">
                        </div>
                        <div class="sf-input-group">
                            <label for="ref_q2">Co jste ocenil/a nejvíc?</label>
                            <input type="text" id="ref_q2" name="ref_q2" placeholder="Co bylo pro vás nejpřínosnější">
                        </div>
                        <div class="sf-input-group">
                            <label for="ref_q3">Jak to využijete?</label>
                            <input type="text" id="ref_q3" name="ref_q3" placeholder="Jak nabyté znalosti aplikujete v praxi">
                        </div>
                        <div class="sf-input-group">
                            <label for="ref_q4">Komu byste doporučil/a?</label>
                            <input type="text" id="ref_q4" name="ref_q4" placeholder="Pro koho je školení vhodné">
                        </div>
                    </div>
                    
                    <div class="sf-reference-info">
                        <h4>Osobní údaje</h4>
                        <div class="sf-input-group">
                            <label for="ref_name">Vaše jméno</label>
                            <input type="text" id="ref_name" name="ref_name" placeholder="Zadejte své jméno">
                        </div>
                        <div class="sf-input-group">
                            <label for="ref_position">Vaše funkce</label>
                            <input type="text" id="ref_position" name="ref_position" placeholder="Zadejte svou pracovní pozici">
                        </div>
                        <div class="sf-input-group">
                            <label for="ref_org">Firma / instituce</label>
                            <input type="text" id="ref_org" name="ref_org" placeholder="Zadejte název své firmy nebo instituce">
                        </div>
                        
                        <h4>Fotografie</h4>
                        <div class="sf-file-upload">
                            <label for="ref_photo">Vaše profilová fotografie (nepovinné)</label>
                            <input type="file" id="ref_photo" name="ref_photo" accept="image/*" capture="user">
                        </div>
                    </div>
                    
                    <button type="submit" name="sf_submit_ref" id="ref_button" class="sf-button sf-button-primary">Vytvořit referenci</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Overlay pro zobrazení průběhu zpracování AI -->
    <div id="ai-processing-overlay" style="display: none;">
        <div class="ai-processing-content">
            <div class="ai-spinner"></div>
            <h3>Zpracování AI...</h3>
            <p>Čekejte prosím, generuji zpětnou vazbu na základě vašeho zápisku.</p>
        </div>
    </div>

    <style>
        /* Základní styly */
        .sf-tabs {
            margin-bottom: 0;
        }
        .sf-tab { 
            display: inline-block; 
            padding: 12px 24px; 
            margin-right: 10px; 
            background: #eee; 
            cursor: pointer; 
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            transition: all 0.2s ease;
        }
        .sf-tab:hover {
            background: #d1c7f9;
        }
        .sf-tab.active { 
            background: #6c5ce7; 
            color: #fff; 
        }
        .sf-section { 
            display: none; 
            margin-top: 0;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .sf-section.active { 
            display: block; 
        }
        
        /* Vstupní pole */
        .sf-input-group {
            margin-bottom: 15px;
        }
        .sf-input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        textarea, input[type="email"], input[type="text"] { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 8px; 
            border-radius: 6px; 
            border: 1px solid #ddd;
            font-size: 14px;
            transition: border 0.3s, box-shadow 0.3s;
        }
        textarea:focus, input:focus {
            border-color: #6c5ce7;
            box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.2);
            outline: none;
        }
        textarea {
            min-height: 100px;
        }
        
        /* Tlačítka */
        .sf-button {
            padding: 12px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sf-button .button-icon {
            margin-right: 8px;
        }
        .sf-button-primary {
            background: #6c5ce7;
            color: white;
        }
        .sf-button-primary:hover {
            background: #5649c0;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .sf-button-secondary {
            background: #74b9ff;
            color: white;
        }
        .sf-button-secondary:hover {
            background: #5da9f0;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .sf-button-container {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Zápisky a historie */
        .sf-entry-form {
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .sf-entry-form h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        .sf-entry-form p {
            color: #666;
            margin-bottom: 20px;
        }
        .sf-entries-history h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .sf-entries-history > p {
            color: #666;
            margin-bottom: 20px;
        }
        .sf-entry-box {
            background: white;
            border-radius: 8px;
            margin-bottom: 25px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .sf-entry-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        }
        .sf-entry-item {
            margin-bottom: 15px;
        }
        .sf-entry-item h4 {
            color: #333;
            margin: 0 0 5px 0;
            font-size: 15px;
        }
        .sf-entry-item p {
            margin: 0;
            color: #444;
        }
        .sf-entry-summary {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            background-color: #f8f9ff;
            padding: 15px;
            border-radius: 6px;
        }
        .sf-entry-summary h4 {
            color: #6c5ce7;
            margin: 0 0 10px 0;
        }
        .sf-summary-content {
            color: #444;
        }
        .sf-no-entries {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            color: #666;
        }
        
        /* Reference */
        .sf-reference-mode {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }
        .sf-reference-mode label {
            display: flex;
            align-items: center;
            font-weight: bold;
            cursor: pointer;
        }
        .sf-reference-mode input[type="radio"] {
            margin-right: 8px;
        }
        .sf-reference-info h4 {
            margin: 25px 0 15px;
            color: #333;
            font-size: 16px;
        }
        .sf-file-upload {
            margin-top: 10px;
        }
        .sf-file-upload label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="file"] {
            padding: 10px 0;
        }

        /* AI Processing overlay */
        #ai-processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .ai-processing-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        }
        .ai-spinner {
            border: 5px solid rgba(108, 92, 231, 0.1);
            border-radius: 50%;
            border-top: 5px solid #6c5ce7;
            width: 50px;
            height: 50px;
            margin: 0 auto 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sf-entry-tema {
            font-size: 1.2em;
            font-weight: 600;
            color: #6c5ce7;
            margin-bottom: 15px;
            padding: 8px 15px;
            background: rgba(108, 92, 231, 0.1);
            border-radius: 20px;
            display: inline-block;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Kontrola URL hash při načtení stránky
            if (window.location.hash === '#reference') {
                document.querySelector('[data-tab="reference"]').click();
            }

            // Přidání hash do URL při kliknutí na záložku
            document.querySelectorAll('.sf-tab').forEach(tab => {
                tab.onclick = () => {
                    document.querySelectorAll('.sf-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.sf-section').forEach(s => s.classList.remove('active'));
                    tab.classList.add('active');
                    document.getElementById(tab.dataset.tab).classList.add('active');
                    
                    // Aktualizace URL hash
                    window.location.hash = tab.dataset.tab;
                };
            });

            // Změna textu tlačítka podle režimu reference
            document.querySelectorAll('input[name="ref_mode"]').forEach(r => {
                r.onchange = () => {
                    document.getElementById('ref_manual').style.display = r.value === 'manual' ? 'block' : 'none';
                    document.getElementById('ref_ai').style.display = r.value === 'ai' ? 'block' : 'none';
                    
                    // Změna textu tlačítka podle režimu
                    const button = document.getElementById('ref_button');
                    if (r.value === 'ai') {
                        button.textContent = 'Poslat do AI';
                    } else {
                        button.textContent = 'Odeslat referenci';
                    }
                };
            });

            // Zobrazení overlay při odesílání formuláře
            document.querySelector('button[name="sf_add_entry"]').addEventListener('click', function() {
                // Kontrola, zda jsou vyplněná všechna pole
                const nove = document.getElementById('sf-nove').value.trim();
                const explain = document.getElementById('sf-explain').value.trim();
                const use = document.getElementById('sf-use').value.trim();
                
                if (nove && explain && use) {
                    document.getElementById('ai-processing-overlay').style.display = 'flex';
                }
            });

            // Zobrazení overlay při odesílání reference s AI
            document.querySelector('button[name="sf_submit_ref"]').addEventListener('click', function() {
                const mode = document.querySelector('input[name="ref_mode"]:checked').value;
                
                if (mode === 'ai') {
                    const q1 = document.getElementById('ref_q1').value.trim();
                    const q2 = document.getElementById('ref_q2').value.trim();
                    const q3 = document.getElementById('ref_q3').value.trim();
                    const q4 = document.getElementById('ref_q4').value.trim();
                    
                    if (q1 && q2 && q3 && q4) {
                        document.getElementById('ai-processing-overlay').style.display = 'flex';
                    }
                }
            });

            // Nastavení výchozího textu tlačítka při načtení
            const mode = document.querySelector('input[name="ref_mode"]:checked').value;
            const button = document.getElementById('ref_button');
            if (mode === 'ai') {
                button.textContent = 'Poslat do AI';
            } else {
                button.textContent = 'Odeslat referenci';
            }

            if ("<?php echo $ref_preview ? 'true' : ''; ?>") {
                document.querySelector('[data-tab="reference"]').click();
            }
        });

        // Pro historii prohlížeče
        window.addEventListener('hashchange', function() {
            if (window.location.hash === '#reference') {
                document.querySelector('[data-tab="reference"]').click();
            } else if (window.location.hash === '#zapisnik' || window.location.hash === '') {
                document.querySelector('[data-tab="zapisnik"]').click();
            }
        });
    </script>
    <?php
    unset($_SESSION['last_generated_reference']);
    return ob_get_clean();
});

// 4️⃣ Zpracování dat
add_action('init', function() {
    if (isset($_POST['sf_create'])) {
        $email = sanitize_email($_POST['sf_email']);
        $post = get_page_by_title($email, OBJECT, 'reflexe');
        $post_id = $post ? $post->ID : wp_insert_post([
            'post_type' => 'reflexe', 'post_title' => $email, 'post_status' => 'draft'
        ]);
        $_SESSION['reflexe_id'] = $post_id;
    }

    if (isset($_POST['sf_add_entry'], $_SESSION['reflexe_id'])) {
        $id = $_SESSION['reflexe_id'];
        $content = get_post_field('post_content', $id);
        
        // Odstranění případných dvojteček ze vstupních dat
        $nove = sanitize_textarea_field($_POST['sf_nove']);
        $explain = sanitize_textarea_field($_POST['sf_explain']);
        $use = sanitize_textarea_field($_POST['sf_use']);
        
        // Odstranění úvodní dvojtečky, pokud existuje
        $nove = ltrim($nove, ': ');
        $explain = ltrim($explain, ': ');
        $use = ltrim($use, ': ');
        
        // Vytvoření nového zápisku - bez dvojteček v textu
        $zapis = "\n\n---\n\n#nove\n" . $nove;
        $zapis .= "\n#explain\n" . $explain;
        $zapis .= "\n#use\n" . $use;
        
        // Přidáme současný zápisek do obsahu
        $new_content = $content . $zapis;
        wp_update_post(['ID' => $id, 'post_content' => $new_content]);
        
        // Extrahování jednotlivých zápisků
        $entries = explode("---", $new_content);
        $summaries = [];
        
        // Procházíme každý zápisek a generujeme shrnutí
        foreach ($entries as $entry) {
            if (trim($entry) !== '') {
                $summary = ai_generate_entry_summary(trim($entry));
                if (!empty($summary)) {
                    $summaries[] = $summary;
                }
            }
        }
        
        // Uložíme všechna shrnutí
        update_post_meta($id, 'ai_summary', implode("\n\n", $summaries));
    }

    if (isset($_POST['sf_send'], $_SESSION['reflexe_id'])) {
        $id = $_SESSION['reflexe_id'];
        $email = get_the_title($id);
        $body = get_post_field('post_content', $id);
        $shr = get_post_meta($id, 'ai_summary', true);
        
        $subject = 'Vaše reflexe ze školení - ' . get_bloginfo('name');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Vytvoření HTML e-mailu
        $emailHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Vaše reflexe ze školení</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; }
                .header { background-color: #6c5ce7; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .entry { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .entry-item { margin-bottom: 10px; }
                .entry-item h3 { margin: 0 0 5px 0; color: #444; }
                .entry-item p { margin: 0; }
                .summary { background: #f0f0ff; padding: 15px; margin-top: 15px; border-radius: 5px; }
                .summary h3 { color: #6c5ce7; margin-top: 0; }
                .footer { text-align: center; padding: 15px; color: #777; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Vaše reflexe ze školení</h1>
                <p>' . get_bloginfo('name') . '</p>
            </div>
            <div class="content">';
        
        // Zpracování zápisků pro e-mail
        $entries = explode("---", $body);
        $summaries = explode("\n\n", $shr);
        
        // Odstranění prázdných záznamů
        $entries = array_filter($entries, function($entry) {
            return trim($entry) !== '';
        });
        
        // Zobrazení zápisků od nejnovějšího
        $entries = array_reverse($entries);
        $summaries = array_reverse($summaries);
        
        foreach ($entries as $index => $entry) {
            $entry_summary = isset($summaries[$index]) ? $summaries[$index] : '';
            $entry_lines = explode("\n", trim($entry));
            $entry_data = [];
            $current_section = '';
            
            foreach ($entry_lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Kontrola starého formátu
                if (strpos($line, 'Co nového:') === 0) {
                    $entry_data['nove'] = ltrim(trim(substr($line, 11)), ': ');
                } elseif (strpos($line, 'Jak vysvětlit:') === 0) {
                    $entry_data['explain'] = ltrim(trim(substr($line, 14)), ': ');
                } elseif (strpos($line, 'Využití:') === 0) {
                    $entry_data['use'] = ltrim(trim(substr($line, 9)), ': ');
                }
                // Kontrola nového formátu
                elseif ($line === '#nove') {
                    $current_section = 'nove';
                } elseif ($line === '#explain') {
                    $current_section = 'explain';
                } elseif ($line === '#use') {
                    $current_section = 'use';
                }
                // Přidání obsahu do aktuální sekce
                elseif ($current_section) {
                    $line = ltrim($line, ': '); // Odstranění případné úvodní dvojtečky
                    if (empty($entry_data[$current_section])) {
                        $entry_data[$current_section] = $line;
                    } else {
                        $entry_data[$current_section] .= "\n" . $line;
                    }
                }
            }
            
            $emailHtml .= '<div class="entry">';
            
            if (!empty($entry_data['nove'])) {
                $emailHtml .= '<div class="entry-item">
                    <h3>Co jsem se naučil/a:</h3>
                    <p>' . esc_html($entry_data['nove']) . '</p>
                </div>';
            }
            
            if (!empty($entry_data['explain'])) {
                $emailHtml .= '<div class="entry-item">
                    <h3>Jak tomu rozumím:</h3>
                    <p>' . esc_html($entry_data['explain']) . '</p>
                </div>';
            }
            
            if (!empty($entry_data['use'])) {
                $emailHtml .= '<div class="entry-item">
                    <h3>Jak to využiji:</h3>
                    <p>' . esc_html($entry_data['use']) . '</p>
                </div>';
            }
            
            if (!empty($entry_summary)) {
                $emailHtml .= '<div class="summary">
                    <h3>AI zpětná vazba:</h3>
                    <p>' . nl2br(esc_html($entry_summary)) . '</p>
                </div>';
            }
            
            $emailHtml .= '</div>';
        }
        
        $emailHtml .= '
            </div>
            <div class="footer">
                <p>Toto je automaticky generovaný e-mail. Neodpovídejte na něj.</p>
                <p>&copy; ' . date('Y') . ' ' . get_bloginfo('name') . '</p>
            </div>
        </body>
        </html>';
        
        // Odeslání e-mailu v HTML formátu
        wp_mail($email, $subject, $emailHtml, $headers);
    }

    if (isset($_POST['sf_submit_ref'])) {
        $manual = sanitize_textarea_field($_POST['ref_manual_text']);
        $name = sanitize_text_field($_POST['ref_name']);
        $position = sanitize_text_field($_POST['ref_position']);
        $org = sanitize_text_field($_POST['ref_org']);
        $context = $_SESSION['reflexe_id'] ? get_post_field('post_content', $_SESSION['reflexe_id']) : '';
        
        $is_ai_mode = isset($_POST['ref_mode']) && $_POST['ref_mode'] === 'ai';
        
        if (!$is_ai_mode && $manual) {
            $content = $manual;
            $post_content = $manual . "\n\n— $name, $position, $org";
        } else {
            $q1 = sanitize_text_field($_POST['ref_q1']);
            $q2 = sanitize_text_field($_POST['ref_q2']);
            $q3 = sanitize_text_field($_POST['ref_q3']);
            $q4 = sanitize_text_field($_POST['ref_q4']);
            $content = ai_generate_reference($q1, $q2, $q3, $q4, $context);
            $post_content = $content . "\n\n— $name, $position, $org";
        }
        
        $post_title = 'Reference - ' . $name;
        if (!empty($org)) {
            $post_title .= ' (' . $org . ')';
        }
        
        $post_id = wp_insert_post([
            'post_type' => 'reference',
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'draft'
        ]);
        
        // Uložení metadat uživatele
        update_post_meta($post_id, 'ref_name', $name);
        update_post_meta($post_id, 'ref_position', $position);
        update_post_meta($post_id, 'ref_org', $org);
        
        $_SESSION['last_generated_reference'] = $content;

        if (!empty($_FILES['ref_photo']['tmp_name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_id = media_handle_upload('ref_photo', $post_id);
            if (!is_wp_error($attach_id)) {
                set_post_thumbnail($post_id, $attach_id);
            }
        }
    }
});

// 5️⃣ GPT API
function ai_generate_summary($text) {
    $api_key = get_option('ai_plugin_apikey');
    $model = get_option('ai_plugin_model', AI_FEEDBACK_DEFAULT_MODEL);
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Shrň zápisky do hlavních bodů a navrhni další krok.'],
                ['role' => 'user', 'content' => $text]
            ],
            'max_tokens' => 1500
        ])
    ]);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? '';
}

// Funkce pro shrnutí jednoho zápisku
function ai_generate_entry_summary($text) {
    $api_key = get_option('ai_plugin_apikey');
    $model = get_option('ai_plugin_model', AI_FEEDBACK_DEFAULT_MODEL);
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Poskytni strukturovanou zpětnou vazbu na tento zápisek ze školení. Zaměř se na tři oblasti a vrať odpověď v následujícím JSON formátu:

{
    "tema": "Název tématu zápisku (např. \'Prompting\' pro zápisky o promptech a instrukcích pro AI, \'Vektorizace\' pro zápisky o převodu na vektory, atd.). Téma by mělo být jedno až dvouslovné a vystihovat hlavní koncept zápisku.",
    "konkretnost": {
        "hodnoceni": "vysoká/střední/nízká",
        "komentar": "Vysvětlení hodnocení konkrétnosti plánu"
    },
    "porozumeni": {
        "hodnoceni": "výborné/dobré/částečné/nedostatečné",
        "komentar": "Vysvětlení úrovně porozumění"
    },
    "doporuceni": [
        "První doporučení pro zlepšení",
        "Druhé doporučení pro zlepšení"
    ],
    "shrnuti": "Celkové shrnutí zpětné vazby"
}'],
                ['role' => 'user', 'content' => $text]
            ],
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object']
        ])
    ]);
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $content = $body['choices'][0]['message']['content'] ?? '';
    
    try {
        $feedback = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Formátování odpovědi do čitelného formátu
            $output = "";
            
            // Shrnutí
            if (!empty($feedback['shrnuti'])) {
                $output .= $feedback['shrnuti'] . "\n\n";
            }
            
            // Konkrétnost plánu
            if (!empty($feedback['konkretnost'])) {
                $output .= "Konkrétnost plánu: " . $feedback['konkretnost']['hodnoceni'] . "\n";
                $output .= $feedback['konkretnost']['komentar'] . "\n\n";
            }
            
            // Úroveň porozumění
            if (!empty($feedback['porozumeni'])) {
                $output .= "Úroveň porozumění: " . $feedback['porozumeni']['hodnoceni'] . "\n";
                $output .= $feedback['porozumeni']['komentar'] . "\n\n";
            }
            
            // Doporučení
            if (!empty($feedback['doporuceni'])) {
                $output .= "Doporučení pro zlepšení:\n";
                foreach ($feedback['doporuceni'] as $doporuceni) {
                    $output .= "• " . $doporuceni . "\n";
                }
            }
            
            return $output;
        }
    } catch (Exception $e) {
        error_log('Chyba při parsování JSON odpovědi: ' . $e->getMessage());
    }
    
    // Pokud se nepodaří zparsovat JSON, vrátíme původní text
    return $content;
}

function ai_generate_reference($a, $b, $c, $d, $context) {
    $api_key = get_option('ai_plugin_apikey');
    $model = get_option('ai_plugin_model', AI_FEEDBACK_DEFAULT_MODEL);
    $prompt = "Na základě odpovědí účastníka vytvoř citovatelnou referenci:\n\n1. Průběh: $a\n2. Ocenění: $b\n3. Využití: $c\n4. Doporučení: $d\n\nZápisky:\n$context";
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Vytvoř lidsky znějící, autentickou referenci na školení.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1500
        ])
    ]);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? '';
}

// Registrace aktivačního hooku
register_activation_hook(__FILE__, 'ai_feedback_activate');

function ai_feedback_activate() {
    global $wpdb;
    
    // Vytvoření tabulky pro logy
    $table_name = $wpdb->prefix . 'ai_feedback_api_logs';
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
        error_log('AI Feedback Analytics: Chyba při vytváření tabulky ' . $table_name);
        error_log('SQL: ' . $sql);
        error_log('Poslední DB chyba: ' . $wpdb->last_error);
    } else {
        error_log('AI Feedback Analytics: Tabulka ' . $table_name . ' byla úspěšně vytvořena');
    }
}