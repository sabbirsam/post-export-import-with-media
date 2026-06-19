jQuery(document).ready(function($) {
    'use strict';

    loadRecommendations();

    function loadRecommendations() {
        if (typeof peiwmRecommendations === 'undefined') {
            showError('Configuration not found. Please refresh the page.');
            return;
        }

        $.ajax({
            url: peiwmRecommendations.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fetch_recommendations',
                nonce: peiwmRecommendations.nonce
            },
            beforeSend: showLoading,
            success: function(response) {
                if (response.success) {
                    renderRecommendations(response.data);
                } else {
                    showError(response.data.message || 'Failed to load recommendations');
                }
            },
            error: function() {
                showError('Network error occurred. Please try again.');
            }
        });
    }

    function showLoading() {
        $('#peiwm-recommendations-container').html(`
            <div class="peiwm-loading">
                <div class="peiwm-loading-spinner"></div>
                <h3>Loading recommendations...</h3>
            </div>
        `);
    }

    function showError(message) {
        $('#peiwm-recommendations-container').html(`
            <div class="peiwm-error">
                <h3>Error Loading Recommendations</h3>
                <p>${escapeHtml(message)}</p>
                <button class="peiwm-btn peiwm-btn-primary" onclick="location.reload()">
                    Try Again
                </button>
            </div>
        `);
    }

    function renderRecommendations(data) {
        let html = '';

        if (data.plugin_cards_html) {
            html += data.plugin_cards_html;
        } else {
            html += '<div class="peiwm-error"><h3>No recommendations available</h3></div>';
        }

        $('#peiwm-recommendations-container').html(html);

        // Initialize thickbox
        if (typeof tb_init === 'function') {
            tb_init('a.thickbox');
        }
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});