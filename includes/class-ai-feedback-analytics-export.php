<?php
/**
 * Třída pro export dat
 * 
 * Zajišťuje export dat ve formátech CSV, Excel a PDF.
 */

defined('ABSPATH') || exit;

class AI_Feedback_Analytics_Export {
    /**
     * Inicializace třídy
     */
    public function init() {
        // Inicializační kód, pokud je potřeba
    }
    
    /**
     * Export dat jako CSV
     * 
     * @param array $data Data pro export
     * @param string $filename Název souboru
     */
    public function export_as_csv($data, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Přidání BOM pro správné zobrazení českých znaků v Excelu
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export dat jako Excel
     * 
     * @param array $data Data pro export
     * @param string $filename Název souboru
     */
    public function export_as_excel($data, $filename) {
        // Vzhledem k tomu, že nechceme používat externí knihovny,
        // vytvoříme XML soubor ve formátu kompatibilním s Excelem
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        echo '<Worksheet ss:Name="Data">' . "\n";
        echo '<Table>' . "\n";
        
        foreach ($data as $row) {
            echo '<Row>' . "\n";
            foreach ($row as $cell) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>';
        
        exit;
    }
    
    /**
     * Generování PDF reportu
     * 
     * @param AI_Feedback_Analytics_Data $data Instance třídy data
     * @param string $date_from Počáteční datum
     * @param string $date_to Koncové datum
     * @param string $report_title Název reportu
     */
    public function generate_pdf_report($data, $date_from, $date_to, $report_title) {
        // Zde bychom normálně použili knihovnu jako TCPDF nebo FPDF,
        // ale vzhledem k požadavku nepoužívat externí knihovny,
        // místo toho vygenerujeme HTML, které pak může být převedeno na PDF
        // pomocí nástrojů jako wkhtmltopdf na serveru nebo browser print-to-PDF
        
        $stats = $data->get_summary_statistics();
        
        // Nastavení časového období
        $period_text = '';
        if (!empty($date_from) && !empty($date_to)) {
            $period_text = sprintf('za období %s - %s', $date_from, $date_to);
        } elseif (!empty($date_from)) {
            $period_text = sprintf('od %s', $date_from);
        } elseif (!empty($date_to)) {
            $period_text = sprintf('do %s', $date_to);
        } else {
            $period_text = 'za celé období';
        }
        
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($report_title); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                h1 {
                    color: #6c5ce7;
                    border-bottom: 2px solid #6c5ce7;
                    padding-bottom: 10px;
                }
                .report-header {
                    margin-bottom: 30px;
                }
                .report-section {
                    margin-bottom: 40px;
                }
                .report-section h2 {
                    color: #6c5ce7;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 5px;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin-bottom: 30px;
                }
                .stat-box {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    text-align: center;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                    display: block;
                    margin-bottom: 5px;
                    color: #6c5ce7;
                }
                .stat-label {
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 10px;
                    border: 1px solid #ddd;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                }
                .print-button {
                    background-color: #6c5ce7;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-weight: bold;
                    margin-top: 20px;
                }
                @media print {
                    .print-button {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="report-header">
                <h1><?php echo esc_html($report_title); ?></h1>
                <p>Analytický report zpětné vazby účastníků školení <?php echo esc_html($period_text); ?></p>
                <p>Vygenerováno: <?php echo date('d.m.Y H:i'); ?></p>
            </div>
            
            <div class="report-section">
                <h2>Souhrnné statistiky</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="stat-value"><?php echo esc_html($stats['total_participants']); ?></span>
                        <span class="stat-label">Celkem účastníků</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value"><?php echo esc_html($stats['total_entries']); ?></span>
                        <span class="stat-label">Celkem zápisků</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value"><?php echo esc_html($stats['avg_entries_per_participant']); ?></span>
                        <span class="stat-label">Průměr zápisků na účastníka</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value"><?php echo esc_html($stats['total_references']); ?></span>
                        <span class="stat-label">Celkem referencí</span>
                    </div>
                </div>
            </div>
            
            <div class="report-section">
                <h2>Shrnutí analýzy</h2>
                <p>Tato sekce poskytuje shrnutí dat získaných ze zápisků a referencí účastníků školení.</p>
                
                <h3>Analýza témat</h3>
                <p>Pro detailnější analýzu témat a klíčových konceptů použijte interaktivní dashboard v administraci.</p>
                
                <h3>Analýza porozumění</h3>
                <p>Pro detailnější analýzu úrovně porozumění a kvality praktických aplikací použijte interaktivní dashboard v administraci.</p>
                
                <h3>Analýza sentimentu</h3>
                <p>Pro detailnější analýzu sentimentu a oceňovaných aspektů použijte interaktivní dashboard v administraci.</p>
            </div>
            
            <div class="report-section">
                <h2>Přehled posledních referencí</h2>
                <table>
                    <tr>
                        <th>Jméno</th>
                        <th>Pozice</th>
                        <th>Organizace</th>
                        <th>Datum</th>
                    </tr>
                    <?php
                    $refs = get_posts([
                        'post_type' => 'reference',
                        'posts_per_page' => 5,
                        'post_status' => ['publish', 'draft'],
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ]);
                    
                    foreach ($refs as $ref) {
                        $name = get_post_meta($ref->ID, 'ref_name', true);
                        $position = get_post_meta($ref->ID, 'ref_position', true);
                        $org = get_post_meta($ref->ID, 'ref_org', true);
                        $date = get_the_date('d.m.Y', $ref->ID);
                        
                        echo '<tr>';
                        echo '<td>' . esc_html($name) . '</td>';
                        echo '<td>' . esc_html($position) . '</td>';
                        echo '<td>' . esc_html($org) . '</td>';
                        echo '<td>' . esc_html($date) . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </table>
            </div>
            
            <div class="footer">
                <p>© <?php echo date('Y'); ?> AI Feedback & Reference Plugin</p>
            </div>
            
            <button class="print-button" onclick="window.print();">Vytisknout nebo uložit jako PDF</button>
        </body>
        </html>
        <?php
        exit;
    }
}