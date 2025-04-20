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
        
        console.log('Requesting analysis:', analysisType);
        
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
        
        // AJAX požadavek na analýzu
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                console.log('AJAX response:', response);
                
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
                    
                    showNotification('Analýza dokončena.', 'success');
                } else {
                    let errorMessage = response.data || 'Chyba při analýze';
                    
                    if (errorMessage.includes('API klíč')) {
                        errorMessage += '. Zkontrolujte nastavení API klíče v nastavení pluginu.';
                    } else if (errorMessage.includes('Neplatný API klíč')) {
                        errorMessage += '. Zkontrolujte, zda je API klíč platný a má dostatečný kredit.';
                    }
                    
                    showNotification(errorMessage, 'error');
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
        // Vytvoření notifikace, pokud neexistuje
        let notification = $('.ai-feedback-notification');
        if (notification.length === 0) {
            notification = $('<div class="ai-feedback-notification"></div>');
            $('body').append(notification);
        }
        
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
        if (!data || !data.labels || data.labels.length === 0) {
            showNotification('Žádná data k zobrazení.', 'error');
            return;
        }
        
        const ctx = document.getElementById('activityChart');
        if (!ctx) {
            console.error('Canvas element not found');
            return;
        }
        
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
        if (!data || !data.response_data) {
            showNotification('Nepodařilo se získat data pro analýzu témat.', 'error');
            return;
        }
        
        try {
            const responseData = JSON.parse(data.response_data);
            
            // Zobrazení souhrnu
            if (responseData.summary) {
                $('#topics-summary').html(`
                    <div class="analysis-summary">
                        <h3>Souhrn analýzy</h3>
                        <p>${responseData.summary}</p>
                    </div>
                `);
            }
            
            // Zpracování hlavních témat pro graf
            if (responseData.main_topics && responseData.main_topics.length > 0) {
                const labels = responseData.main_topics.map(topic => topic.name);
                const values = responseData.main_topics.map(topic => topic.count);
                
                const ctx = document.getElementById('topicsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Četnost témat',
                            data: values,
                            backgroundColor: 'rgba(108, 92, 231, 0.6)',
                            borderColor: 'rgba(108, 92, 231, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            // Zobrazení klíčových poznatků
            if (responseData.key_learnings && responseData.key_learnings.length > 0) {
                const learningsList = responseData.key_learnings.map(learning => 
                    `<li>${learning}</li>`
                ).join('');
                
                $('#key-learnings-list').html(`
                    <div class="analysis-summary">
                        <h3>Klíčové poznatky</h3>
                        <ul class="key-learnings-list">
                            ${learningsList}
                        </ul>
                    </div>
                `);
            }
            
        } catch (error) {
            console.error('Chyba při zpracování dat z API:', error);
            showNotification('Chyba při zpracování dat z API.', 'error');
        }
    }
    
    // Funkce pro vykreslení grafů porozumění
    function renderUnderstandingCharts(data) {
        if (!data || !data.response_data) {
            showNotification('Nepodařilo se získat data pro analýzu porozumění.', 'error');
            return;
        }
        
        try {
            const responseData = JSON.parse(data.response_data);
            
            // Graf úrovně porozumění
            if (responseData.understanding_levels) {
                const understandingCtx = document.getElementById('understandingChart').getContext('2d');
                new Chart(understandingCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(responseData.understanding_levels),
                        datasets: [{
                            data: Object.values(responseData.understanding_levels),
                            backgroundColor: [
                                'rgba(108, 92, 231, 0.6)',
                                'rgba(46, 213, 115, 0.6)',
                                'rgba(255, 71, 87, 0.6)'
                            ],
                            borderColor: [
                                'rgba(108, 92, 231, 1)',
                                'rgba(46, 213, 115, 1)',
                                'rgba(255, 71, 87, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Graf kvality aplikace
            if (responseData.application_quality) {
                const applicationCtx = document.getElementById('applicationChart').getContext('2d');
                new Chart(applicationCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(responseData.application_quality),
                        datasets: [{
                            label: 'Kvalita aplikace znalostí',
                            data: Object.values(responseData.application_quality),
                            backgroundColor: 'rgba(46, 213, 115, 0.6)',
                            borderColor: 'rgba(46, 213, 115, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            // Zobrazení doporučení pro zlepšení
            if (responseData.improvement_suggestions && responseData.improvement_suggestions.length > 0) {
                const suggestionsList = responseData.improvement_suggestions.map(suggestion => 
                    `<li>${suggestion}</li>`
                ).join('');
                
                $('#improvementSuggestions').html(`
                    <ul class="improvement-suggestions-list">
                        ${suggestionsList}
                    </ul>
                `);
            }
            
        } catch (error) {
            console.error('Chyba při zpracování dat z API:', error);
            showNotification('Chyba při zpracování dat z API.', 'error');
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
    
    // Automatické načtení dat při načtení stránky
    function loadInitialData() {
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        const keyword = $('#keyword').val();
        
        // Načtení souhrnných statistik
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'analyze_feedback',
                nonce: aiFeedbackAnalytics.nonce,
                analysis_type: 'activity_overview',
                date_from: dateFrom,
                date_to: dateTo,
                keyword: keyword
            },
            success: function(response) {
                if (response.success) {
                    renderActivityChart(response.data);
                }
            }
        });
        
        // Načtení analýzy témat
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'analyze_feedback',
                nonce: aiFeedbackAnalytics.nonce,
                analysis_type: 'topics',
                date_from: dateFrom,
                date_to: dateTo,
                keyword: keyword
            },
            success: function(response) {
                if (response.success) {
                    renderTopicsCharts(response.data);
                }
            }
        });
        
        // Načtení analýzy porozumění
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'analyze_feedback',
                nonce: aiFeedbackAnalytics.nonce,
                analysis_type: 'understanding',
                date_from: dateFrom,
                date_to: dateTo,
                keyword: keyword
            },
            success: function(response) {
                if (response.success) {
                    renderUnderstandingCharts(response.data);
                }
            }
        });
        
        // Načtení analýzy sentimentu
        $.ajax({
            url: aiFeedbackAnalytics.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'analyze_feedback',
                nonce: aiFeedbackAnalytics.nonce,
                analysis_type: 'sentiment',
                date_from: dateFrom,
                date_to: dateTo,
                keyword: keyword
            },
            success: function(response) {
                if (response.success) {
                    renderSentimentChart(response.data);
                }
            }
        });
    }
    
    // Načtení dat při prvním zobrazení stránky
    loadInitialData();
});