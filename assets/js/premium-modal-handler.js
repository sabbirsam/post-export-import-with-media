/**
 * Premium Upgrade Modal Handler
 * Centralized click delegation for Pro-locked elements and badges across all admin pages.
 *
 * @package Post_Export_Import_With_Media
 */
jQuery(document).ready(function ($) {
    'use strict';

    $(document).on('click', '.peiwm-open-premium-modal, .peiwm-locked-section', function (e) {
        // Do not intercept active controls inside locked sections unless explicitly tagged as a modal trigger
        if ($(e.target).is('input, select, textarea, button:not(.peiwm-open-premium-modal), label, a:not(.peiwm-open-premium-modal)')) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var $modal = $('#peiwm-premium-modal');
        if ($modal.length) {
            $modal.show().addClass('peiwm-show');

            // Close button listener
            $modal.find('.peiwm-premium-close, .peiwm-modal-close').off('click.peiwm-premium').on('click.peiwm-premium', function (ev) {
                ev.preventDefault();
                $modal.removeClass('peiwm-show').hide();
            });

            // Overlay backdrop click listener
            $modal.off('click.peiwm-premium-bg').on('click.peiwm-premium-bg', function (ev) {
                if (ev.target === this) {
                    $modal.removeClass('peiwm-show').hide();
                }
            });

            // Escape key listener
            $(document).off('keydown.peiwm-premium-modal').on('keydown.peiwm-premium-modal', function (ev) {
                if (ev.key === 'Escape') {
                    $modal.removeClass('peiwm-show').hide();
                    $(document).off('keydown.peiwm-premium-modal');
                }
            });
        }
    });
});
