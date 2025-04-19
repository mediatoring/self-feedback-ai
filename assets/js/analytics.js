/**
 * AI Feedback Analytics - JavaScript
 * 
 * Skript pro interakci s analytickým rozhraním AI Feedback pluginu
 */
jQuery(document).ready(function($) {
    // Přepínání tabů
    $('.ai-feedback-tab-link').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Aktivace tabu
        $('.ai-feedback-tab-link').removeClass('active');
        $(this).addClass('active');
        
        // Aktivace obsahu tabu
        $('.ai-feedback-tab-content').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');
    });
    
    // Resetování filtrů
    $('#reset-filters').on('click', function() {
        $('#date_from, #date_to, #keyword').val('');
        $('#ai-feedback-filter-form').submit();
    });
    
    // Tlačítko pro re-analýzu
    $('#reanalyze-all').on('click', function() {
        const button = $(this);
        const progressBar = $('#reanalyze-progress');
        const progressFill = $('.progress-bar-fill');
        const progressStatus = $('.progress-status');
        
        // Zablokování tlačítka
        button.prop('disabled', true);
        
        // Zobrazení progress baru
        progressBar.show();
        progressStatus.text('Zahajuji analýzu...');
        progressFill.css('width', '0%');
        
        // Data pro AJAX požadavek
        const ajaxData = {
            action: 'reanalyze_all_posts',
            nonce: aiFeedbackAnalytics.nonce
        };
        
        console.log('Sending reanalyze request:', ajaxData);
        console.log('AJAX URL:', aiFeedbackAnalytics.ajaxUrl);
        
        // AJAX požadavek na re-analýzu
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                console.log('Reanalyze response:', response);
                
                if (response.success) {
                    // Aktualizace progress baru
                    progressFill.css('width', '100%');
                    progressStatus.text('Analýza dokončena!');
                    
                    // Zobrazení notifikace o úspěchu
                    showNotification(response.data.message || 'Re-analýza byla úspěšně dokončena.', 'success');
                    
                    // Obnovení stránky po 2 sekundách
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    // Zobrazení chybové zprávy
                    progressStatus.text('Chyba při analýze');
                    showNotification(response.data || 'Chyba při re-analýze.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Reanalyze AJAX error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                progressStatus.text('Chyba při komunikaci se serverem');
                
                let errorMessage = 'Chyba při komunikaci se serverem: ' + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
                
                showNotification(errorMessage, 'error');
            },
            complete: function() {
                // Odblokování tlačítka
                button.prop('disabled', false);
            }
        });
    });
    
    // Tlačítka pro analýzu dat
    $('.ai-analyze-button').on('click', function() {
        const analysisType = $(this).data('analysis');
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        const keyword = $('#keyword').val();
        
        console.log('Requesting analysis:', analysisType); // Debug výpis
        
        // Zobrazení overlay
        $('#ai-analytics-overlay').show();
        
        // Data pro AJAX požadavek
        const ajaxData = {
            action: 'analyze_feedback',
            nonce: aiFeedbackAnalytics.nonce,
            analysis_type: analysisType,
            date_from: dateFrom,
            date_to: dateTo,
            keyword: keyword
        };
        
        // Loguji přesně, co posíláme na server
        console.log('AJAX request data:', ajaxData);
        console.log('AJAX URL:', aiFeedbackAnalytics.ajaxUrl);
        console.log('Nonce:', aiFeedbackAnalytics.nonce);
        
        // AJAX požadavek na analýzu
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                console.log('AJAX response:', response); // Debug výpis
                
                // Skrytí overlay
                $('#ai-analytics-overlay').hide();
                
                if (response.success) {
                    // Zpracování odpovědi podle typu analýzy
                    switch (analysisType) {
                        case 'activity_overview':
                            renderActivityChart(response.data);
                            break;
                        case 'topics':
                            renderTopicsCharts(response.data);
                            break;
                        case 'understanding':
                            renderUnderstandingCharts(response.data);
                            break;
                        case 'sentiment':
                            renderSentimentChart(response.data);
                            break;
                    }
                    
                    // Zobrazení notifikace o úspěchu
                    showNotification('Analýza dokončena.', 'success');
                } else {
                    // Zobrazení detailní chybové zprávy
                    let errorMessage = 'Chyba při analýze';
                    
                    if (response.data) {
                        errorMessage = response.data;
                    }
                    
                    // Podrobnější instrukce pro uživatele
                    if (errorMessage.includes('API klíč')) {
                        errorMessage += '. Zkontrolujte nastavení API klíče v nastavení pluginu.';
                    } else if (errorMessage.includes('Neplatný API klíč')) {
                        errorMessage += '. Zkontrolujte, zda je API klíč platný a má dostatečný kredit.';
                    }
                    
                    showNotification(errorMessage, 'error');
                    console.error('Chyba analýzy:', errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $('#ai-analytics-overlay').hide();
                showNotification('Chyba při komunikaci se serverem: ' + error, 'error');
            }
        });
    });
    
    // Odeslání formuláře pro PDF report
    $('#ai-feedback-report-form').on('submit', function(e) {
        if (!$('#report_title').val()) {
            e.preventDefault();
            showNotification('Zadejte název reportu.', 'error');
        }
    });
    
    // Funkce pro zobrazení notifikace
    function showNotification(message, type) {
        const notification = $('.ai-feedback-notification');
        notification.text(message);
        notification.removeClass('success error');
        notification.addClass(type);
        notification.fadeIn(300);
        
        setTimeout(function() {
            notification.fadeOut(300);
        }, 3000);
    }
    
    // Funkce pro vykreslení grafu aktivity
    function renderActivityChart(data) {
        if (!data.labels || data.labels.length === 0) {
            showNotification('Žádná data k zobrazení.', 'error');
            return;
        }
        
        const ctx = document.getElementById('activityChart').getContext('2d');
        
        // Zničení existujícího grafu, pokud existuje
        if (window.activityChart) {
            window.activityChart.destroy();
        }
        
        // Vytvoření nového grafu
        window.activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Zápisky',
                        data: data.entries,
                        borderColor: '#6c5ce7',
                        backgroundColor: 'rgba(108, 92, 231, 0.1)',
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Reference',
                        data: data.references,
                        borderColor: '#74b9ff',
                        backgroundColor: 'rgba(116, 185, 255, 0.1)',
                        tension: 0.4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
    }
    
    // Funkce pro vykreslení grafů témat
    function renderTopicsCharts(data) {
        // Kontrola, zda máme data
        if (!data.keywords.labels || data.keywords.labels.length === 0) {
            showNotification('Žádná data pro analýzu témat.', 'error');
            return;
        }
        
        // Skrytí placeholderů
        $('#keywords-loading, #topics-loading, #usage-loading').hide();
        
        // Graf klíčových slov
        const keywordsCtx = document.getElementById('keywordsChart').getContext('2d');
        if (window.keywordsChart) {
            window.keywordsChart.destroy();
        }
        window.keywordsChart = new Chart(keywordsCtx, {
            type: 'bar',
            data: {
                labels: data.keywords.labels,
                datasets: [{
                    label: 'Četnost výskytu',
                    data: data.keywords.values,
                    backgroundColor: 'rgba(108, 92, 231, 0.8)'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Graf kategorií témat
        const topicsCtx = document.getElementById('topicsChart').getContext('2d');
        if (window.topicsChart) {
            window.topicsChart.destroy();
        }
        window.topicsChart = new Chart(topicsCtx, {
            type: 'doughnut',
            data: {
                labels: data.topics.labels,
                datasets: [{
                    data: data.topics.values,
                    backgroundColor: [
                        'rgba(108, 92, 231, 0.8)',
                        'rgba(116, 185, 255, 0.8)',
                        'rgba(0, 184, 148, 0.8)',
                        'rgba(253, 203, 110, 0.8)',
                        'rgba(232, 67, 147, 0.8)',
                        'rgba(153, 128, 250, 0.8)'
                    ],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Graf plánů využití
        const usageCtx = document.getElementById('usageChart').getContext('2d');
        if (window.usageChart) {
            window.usageChart.destroy();
        }
        window.usageChart = new Chart(usageCtx, {
            type: 'polarArea',
            data: {
                labels: data.usage.labels,
                datasets: [{
                    data: data.usage.values,
                    backgroundColor: [
                        'rgba(108, 92, 231, 0.8)',
                        'rgba(116, 185, 255, 0.8)',
                        'rgba(0, 184, 148, 0.8)',
                        'rgba(253, 203, 110, 0.8)',
                        'rgba(232, 67, 147, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
    
    // Funkce pro vykreslení grafů porozumění
    function renderUnderstandingCharts(data) {
        // Kontrola, zda máme data
        if (!data.understanding.labels || data.understanding.labels.length === 0) {
            showNotification('Žádná data pro analýzu porozumění.', 'error');
            return;
        }
        
        // Skrytí placeholderů
        $('#understanding-loading, #application-loading').hide();
        
        // Graf úrovní porozumění
        const understandingCtx = document.getElementById('understandingChart').getContext('2d');
        if (window.understandingChart) {
            window.understandingChart.destroy();
        }
        window.understandingChart = new Chart(understandingCtx, {
            type: 'pie',
            data: {
                labels: data.understanding.labels,
                datasets: [{
                    data: data.understanding.values,
                    backgroundColor: [
                        'rgba(0, 184, 148, 0.8)',
                        'rgba(116, 185, 255, 0.8)',
                        'rgba(253, 203, 110, 0.8)',
                        'rgba(232, 67, 147, 0.8)'
                    ],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true
            }
        });
        
        // Graf kvality praktických aplikací
        const applicationCtx = document.getElementById('applicationChart').getContext('2d');
        if (window.applicationChart) {
            window.applicationChart.destroy();
        }
        window.applicationChart = new Chart(applicationCtx, {
            type: 'pie',
            data: {
                labels: data.application.labels,
                datasets: [{
                    data: data.application.values,
                    backgroundColor: [
                        'rgba(0, 184, 148, 0.8)',
                        'rgba(116, 185, 255, 0.8)',
                        'rgba(253, 203, 110, 0.8)',
                        'rgba(232, 67, 147, 0.8)'
                    ],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true
            }
        });
        
        // Zobrazení doporučení pro zlepšení
        const suggestions = data.suggestions;
        if (suggestions && suggestions.length > 0) {
            let suggestionsHtml = '<ul>';
            suggestions.forEach(function(suggestion) {
                suggestionsHtml += '<li>' + suggestion + '</li>';
            });
            suggestionsHtml += '</ul>';
            
            $('#improvementSuggestions').html(suggestionsHtml);
        } else {
            $('#improvementSuggestions').html('<p>Nejsou k dispozici žádná doporučení.</p>');
        }
    }
    
    // Funkce pro vykreslení grafu sentimentu
    function renderSentimentChart(data) {
        // Kontrola, zda máme data
        if (!data.sentiment.labels || data.sentiment.labels.length === 0) {
            showNotification('Žádná data pro analýzu sentimentu.', 'error');
            return;
        }
        
        // Skrytí placeholderů
        $('#sentiment-loading').hide();
        
        // Graf sentimentu
        const sentimentCtx = document.getElementById('sentimentChart').getContext('2d');
        if (window.sentimentChart) {
            window.sentimentChart.destroy();
        }
        window.sentimentChart = new Chart(sentimentCtx, {
            type: 'pie',
            data: {
                labels: data.sentiment.labels,
                datasets: [{
                    data: data.sentiment.values,
                    backgroundColor: [
                        'rgba(0, 184, 148, 0.8)',
                        'rgba(116, 185, 255, 0.8)',
                        'rgba(232, 67, 147, 0.8)'
                    ],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true
            }
        });
        
        // Zobrazení oceňovaných aspektů
        const aspects = data.aspects;
        if (aspects && aspects.length > 0) {
            let aspectsHtml = '';
            
            aspects.forEach(function(aspect) {
                const percentage = Math.round((aspect.count / aspects.reduce((sum, a) => sum + a.count, 0)) * 100);
                
                aspectsHtml += `
                    <div class="ai-feedback-aspect">
                        <div class="ai-feedback-aspect-label">${aspect.name}</div>
                        <div class="ai-feedback-aspect-bar-container">
                            <div class="ai-feedback-aspect-bar" style="width: ${percentage}%"></div>
                            <span class="ai-feedback-aspect-count">${aspect.count} (${percentage}%)</span>
                        </div>
                    </div>
                `;
            });
            
            $('#aspectsContainer').html(aspectsHtml);
        } else {
            $('#aspectsContainer').html('<p>Nejsou k dispozici žádné oceňované aspekty.</p>');
        }
    }
    
    function displayAnalysisResults(data) {
        const resultsContainer = document.getElementById('analysis-results');
        if (!resultsContainer) return;
        
        // Vytvoření HTML struktury pro zobrazení výsledků
        let html = `
            <div class="analysis-summary">
                <h3>Celkové shrnutí</h3>
                <p>${data.summary}</p>
            </div>
            
            <div class="analysis-section">
                <h3>Klíčové poznatky</h3>
                <ul>
                    ${data.key_learnings.map(learning => `<li>${learning}</li>`).join('')}
                </ul>
            </div>
            
            <div class="analysis-section">
                <h3>Porozumění</h3>
                <ul>
                    ${data.understanding.map(explanation => `<li>${explanation}</li>`).join('')}
                </ul>
            </div>
            
            <div class="analysis-section">
                <h3>Plány využití</h3>
                <ul>
                    ${data.usage_plans.map(plan => `<li>${plan}</li>`).join('')}
                </ul>
            </div>
        `;
        
        resultsContainer.innerHTML = html;
    }
});