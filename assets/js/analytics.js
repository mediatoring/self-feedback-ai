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
    
    // Tlačítka pro analýzu dat
    $('.ai-analyze-button').on('click', function() {
        const analysisType = $(this).data('analysis');
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        const keyword = $('#keyword').val();
        
        console.log('Requesting analysis:', analysisType); // Debug výpis
        
        // Zobrazení overlay
        $('#ai-analytics-overlay').show();
        
        // AJAX požadavek na analýzu
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ai_feedback_analyze',
                nonce: aiFeedbackAnalytics.nonce,
                analysis_type: analysisType,
                date_from: dateFrom,
                date_to: dateTo,
                keyword: keyword
            },
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
                    // Zobrazení chybové zprávy
                    showNotification('Chyba při analýze: ' + (response.data || 'Neznámá chyba'), 'error');
                }
            },
            error: function(xhr, status, error) {
                // Skrytí overlay
                $('#ai-analytics-overlay').hide();
                
                // Výpis detailů chyby do konzole
                console.error('AJAX error:', status, error);
                console.log(xhr.responseText);
                
                // Zobrazení chybové zprávy
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
});