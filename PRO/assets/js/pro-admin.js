/**
 * Pro Admin JavaScript
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since 1.0.0
 */

jQuery(document).ready(function ($) {
    'use strict';

    // PRO features initialization
    console.log('Post Export Import with Media Pro - Version ' + peiwm_pro_ajax.version);

    // Add PRO indicator to free features
    $('.peiwm-feature-free').each(function() {
        $(this).append('<span class="peiwm-pro-available" title="Enhanced in PRO version">⚡ PRO Enhanced</span>');
    });
});
