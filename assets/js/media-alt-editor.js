/**
 * Media Title & ALT Editor - Base (Free Version Demo)
 */
jQuery(document).ready(function ($) {
    'use strict';

    // Click anywhere on locked section triggers upgrade modal
    $('.peiwm-locked-section').on('click', function (e) {
        e.preventDefault();
        if (typeof showPremiumModal === 'function') {
            showPremiumModal();
        } else if ($('#peiwm-premium-modal').length) {
            $('#peiwm-premium-modal').fadeIn(200);
        }
    });
});
