jQuery(document).ready(function ($) {
    'use strict';

    console.log('Themes & Plugins JS loaded successfully');

    // Modal Utility Functions
    function showModal(type, title, message) {
        $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
        $(document).off('keydown.peiwm-modal');

        let modalId = '#peiwm-modal-overlay';

        switch (type) {
            case 'success':
                modalId = '#peiwm-success-modal';
                break;
            case 'error':
                modalId = '#peiwm-error-modal';
                break;
            case 'confirm':
                modalId = '#peiwm-modal-overlay';
                break;
        }

        const modal = $(modalId);
        
        if (type === 'success') {
            modal.find('#peiwm-success-message').html(message);
        } else if (type === 'error') {
            modal.find('#peiwm-error-message').html(message);
        } else {
            modal.find('#peiwm-modal-title').text(title);
            modal.find('#peiwm-modal-message').html(message);
        }
        
        modal.addClass('peiwm-show').show();

        $(document).on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
    }

    function showSuccess(message) {
        showModal('success', 'Success!', message);
    }

    function showError(message) {
        showModal('error', 'Error', message);
    }

    function showConfirm(title, message, callback) {
        const modal = $('#peiwm-modal-overlay');
        modal.find('#peiwm-modal-title').text(title);
        modal.find('#peiwm-modal-message').html(message);
        modal.addClass('peiwm-show').show();

        $(document).on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
        
        $('#peiwm-modal-confirm').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
            if (callback) callback();
        });
        
        $('#peiwm-modal-cancel').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        });
    }

    function addLog(message, logContainer = null, className = '') {
        if (!logContainer) {
            logContainer = $('.peiwm-log:visible').first();
        }

        const time = new Date().toLocaleTimeString();
        const classAttr = className ? ' class="peiwm-log-entry ' + className + '"' : ' class="peiwm-log-entry"';
        logContainer.append('<div' + classAttr + '>[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    
    // ── FAQ Section ──────────────────────────────────────────────────────────────
    function initFAQ() {
        var activeTab = 'all';
        var searchVal = '';
        var $list  = $('#peiwm-faq-list');
        var $count = $('#peiwm-faq-count');

        if (!$list.length) return;

        var peiwmFAQData = [

            // ── POSTS ──────────────────────────────────────────────────────────
            {
                cat: "posts", badge: "",
                q: "How do I export only specific posts?",
                a: "<p>Enable <strong>Export individually (select specific posts)</strong> under Post Export. A searchable list of all your posts will appear — check the ones you want and click <em>Export Posts</em>. Only the selected posts will be included in the JSON file.</p>"
            },
            {
                cat: "posts", badge: "",
                q: "Can I export posts within a date range?",
                a: "<p>Yes. Enable <strong>Export by date range</strong> and enter a <em>From</em> and/or <em>To</em> date, then click <em>Apply Filter</em>. The post list will refresh to show only posts within that range, which you can then export selectively or all at once.</p>"
            },
            {
                cat: "posts", badge: "pro",
                q: "How are ACF custom fields exported with posts?",
                a: "<p>Enable <strong>Export custom ACF meta fields</strong> before exporting. This includes all Advanced Custom Fields data — field keys and values — inside the exported JSON. On import, ACF fields are restored automatically if the same field keys exist on the destination site.</p>"
            },
            {
                cat: "posts", badge: "pro",
                q: "How do I export WPML multilingual post data?",
                a: "<p>Enable <strong>Export WPML multilingual language data</strong> before exporting. This saves the post's language code and translation group ID. On import, enable <strong>Enable WPML multilingual language support</strong> so the plugin re-links translations correctly via WPML or Polylang.</p>"
            },
            {
                cat: "posts", badge: "",
                q: "What does 'Check media library for post images' do on import?",
                a: "<p>Before downloading any image, the importer searches your existing media library by filename. If a match is found it reuses the existing attachment — avoiding duplicate uploads and saving storage space. Uncheck this only if you want to re-download all images regardless.</p>"
            },
            {
                cat: "posts", badge: "",
                q: "What does 'Download missing images from original URLs' do?",
                a: "<p>When enabled, any image not found in the media library is fetched from its original URL stored in the export file. This automatically rebuilds your media library from the source site. Disable it if the source site is offline or you plan to add images manually later.</p>"
            },
            {
                cat: "posts", badge: "pro",
                q: "How does WPML language support work on import?",
                a: "<p>Enable <strong>Enable WPML multilingual language support</strong> in the import options. The importer will assign each post its original language and re-create translation group links. Requires WPML or Polylang to be active on the destination site.</p>"
            },
            {
                cat: "posts", badge: "pro",
                q: "Can I import only selected posts from a file?",
                a: "<p>Yes. Enable <strong>Import individually (select specific posts)</strong> after choosing your JSON file. A list of all posts in the file will load — check only the ones you want to import. You can also override the import status (publish / draft / private) per post using the ⚙ settings icon.</p>"
            },
            {
                cat: "posts", badge: "pro",
                q: "How does Smart Author Mapping work?",
                a: `<p>Smart Author Mapping offers two fallback options when a post's original author does not exist on the destination site:</p>
            <ul>
            <li>
                <strong>Assign to current admin user</strong> — all unmatched posts are assigned to the currently logged-in admin.
            </li>
            <li>
                <strong>Automatically create the missing user</strong> — a new WordPress account is created using the author data from the export file. You can:
                <ul>
                <li>Set a <strong>default password</strong> for all imported users, or leave it blank to auto-generate a secure password per user.</li>
                <li>Enable a <strong>welcome email</strong> so newly created users receive their login credentials via email.</li>
                </ul>
            </li>
            </ul>
            <p>If a user with the same login already exists, that account is reused — no duplicate will be created.</p>`
            },
            {
                cat: "posts", badge: "",
                q: "⚠️ What happens when I delete all posts?",
                a: "<p><strong style='color:#dc2626;'>Warning: This will permanently delete ALL posts from your website. This action cannot be undone.</strong></p><p>Clicking <em>Delete All Posts</em> removes every post, draft, and published item from your database. Media files and their library records are <em>not</em> deleted — only post content and metadata. Make sure you have a full backup before proceeding.</p>"
            },

            // ── MEDIA ──────────────────────────────────────────────────────────
            {
                cat: "media", badge: "",
                q: "What do the Media Statistics numbers mean?",
                a: "<p>The stats panel shows four key numbers:</p><ul><li><strong>Unique Files</strong> — the number of original media attachments registered in your media library, plus their total size.</li><li><strong>Total Files</strong> — all physical files on disk including every generated thumbnail (medium, large, custom sizes). This number is always higher than Unique Files.</li><li><strong>Available Files</strong> — how many originals actually exist on disk. A warning appears if any are missing.</li><li><strong>Largest File</strong> — the single biggest original file by size.</li></ul>"
            },
            {
                cat: "media", badge: "",
                q: "What are the file type badges (WEBP, JPEG, PNG…) in Media Statistics?",
                a: "<p>The <strong>File Types</strong> section groups your media library by MIME type and shows a count for each. Only the top 5 types are shown by default — click <em>+N more types</em> to expand the full list. This helps you understand what formats make up your library before exporting.</p>"
            },
            {
                cat: "media", badge: "",
                q: "I see '2 missing from disk' in Available Files — what should I do?",
                a: "<p>This means the database has attachment records but the physical files are gone. Two tools are available:</p><ul><li><strong>Fix Paths</strong> — corrects misconfigured file paths (e.g. <code>202311</code> stored instead of <code>2023/11</code>). Try this first.</li><li><strong>Clean Missing Files</strong> — permanently removes the orphaned database records. Use this after Fix Paths if files are truly gone and you want a clean library.</li></ul><p>Entries showing <em>Unknown</em> filename or path are severely corrupted and cannot be fixed — only cleaned up.</p>"
            },
            {
                cat: "media", badge: "",
                q: "What does 'Export all image sizes' do during Media Export?",
                a: "<p>When <strong>Export all image sizes (thumbnails, medium, large)</strong> is checked, every size variation WordPress generated for each image is included in the ZIP — this can make the file significantly larger.</p><p>When <strong>unchecked</strong> (default), only the original uploaded files are exported. WordPress will automatically regenerate thumbnails on the destination site after import, so unchecked is recommended for most migrations.</p>"
            },
            {
                cat: "media", badge: "",
                q: "How do I import media, and can I import multiple ZIPs?",
                a: "<p>Go to <strong>Import Media</strong>, click <em>Select ZIP File</em> and choose the ZIP exported by this plugin. You can select <strong>multiple ZIP files at once</strong> — they will be processed one by one in sequence. Each file is uploaded, extracted, and registered in your media library automatically. Maximum file and batch size can be change from Batch settings page. </p>"
            },

            // ── PAGES ──────────────────────────────────────────────────────────
            {
                cat: "pages", badge: "",
                q: "Can I export only specific pages instead of all?",
                a: "<p>Yes. Enable <strong>Export individually (select specific pages)</strong> under Page Export. A list of all pages will appear — select the ones you need and export. This is useful when migrating a subset of a large site.</p>"
            },
            {
                cat: "pages", badge: "",
                q: "Will page templates from my custom theme be preserved during import?",
                a: `<p>Yes. When a page has a custom template assigned — including templates from your custom theme — that template data is included in the export file. On import, the template is automatically re-applied to the page, so your layout and design are restored exactly as they were.</p>
            <p>Just make sure the same template file exists in your theme on the destination site, otherwise WordPress will fall back to the default template.</p>`
            },
            {
                cat: "pages", badge: "pro",
                q: "How are ACF fields exported with pages?",
                a: "<p>Enable <strong>Export custom ACF meta fields</strong> before exporting. All ACF field data attached to pages will be included in the JSON with their field keys, ready to be restored on import.</p>"
            },
            {
                cat: "pages", badge: "pro",
                q: "How does WPML export work for pages?",
                a: "<p>Enable <strong>Export WPML multilingual language data</strong>. If WPML or Polylang is not active a notice is shown. When active, each page's language code and translation group ID are saved so translations can be re-linked on the destination site.</p>"
            },
            {
                cat: "pages", badge: "",
                q: "How do I import pages from a JSON file?",
                a: "<p>Under <strong>Import Pages</strong>, click <em>Select JSON File</em> and choose the file exported by this plugin. Options available during import:</p><ul><li><strong>Check media library for page images</strong> — searches existing media before downloading. Uncheck for faster import if you will add images manually.</li><li><strong>Download missing images from original URLs</strong> — fetches images from their source URLs if not found in the library.</li></ul>"
            },

            // ── SETTINGS & WIDGETS ─────────────────────────────────────────────
            {
                cat: "settings", badge: "",
                q: "Which WordPress settings can be exported?",
                a: "<p>You can export configuration from any combination of these groups: <strong>General, Writing, Reading, Discussion, Media, Permalinks,</strong> and <strong>Privacy</strong>. Select the groups you need before exporting — the result is a single JSON file you can import on another site to instantly replicate your settings.</p>"
            },
            {
                cat: "settings", badge: "",
                q: "How do I export and import Widgets and Navigation Menus?",
                a: "<p>Under <strong>Widgets &amp; Navigation Menus</strong> you have three export options:</p><ul><li><strong>Export Widgets Only</strong></li><li><strong>Export Menus Only</strong></li><li><strong>Export Both</strong></li></ul><p>The result is a JSON file. To import, click <em>Select JSON File</em> under <em>Import Widgets &amp; Menus</em> and upload the file. Existing widgets/menus with matching names will be updated; new ones will be created.</p>"
            },

            // ── THEMES & PLUGINS ───────────────────────────────────────────────
            {
                cat: "themes", badge: "",
                q: "What theme export options are available?",
                a: "<p>Three options are available when exporting themes:</p><ul><li><strong>Active Theme Only</strong> — fastest option; exports only the currently active theme.</li><li><strong>All Installed Themes</strong> — exports every theme in your <code>wp-content/themes</code> folder.</li><li><strong>Selected Themes</strong> — choose specific themes to include.</li></ul><p>The result is a ZIP file you can import on another site.</p>"
            },
            {
                cat: "themes", badge: "",
                q: "How do I import a theme from a ZIP file?",
                a: "<p>Under <strong>Import Themes</strong>, select the ZIP file exported by this plugin. You can optionally tick <em>Activate imported theme</em> to set the first theme in the ZIP as the active theme immediately after import completes.</p>"
            },
            {
                cat: "themes", badge: "",
                q: "What plugin export options are available?",
                a: "<p>Three options are available:</p><ul><li><strong>Active Plugins Only</strong> — exports only currently active plugins.</li><li><strong>All Installed Plugins</strong> — exports every plugin in <code>wp-content/plugins</code>.</li><li><strong>Selected Plugins</strong> — pick specific plugins to export.</li></ul><p>The result is a ZIP. Note: plugin <em>settings</em> stored in the database are not included — use the Settings Export for that.</p>"
            },
            {
                cat: "themes", badge: "",
                q: "How do I import plugins from a ZIP file?",
                a: "<p>Under <strong>Import Plugins</strong>, click <em>Select ZIP File</em> and choose the ZIP exported by this plugin. During import you can choose to replace existing plugins, skip already-present ones, and auto-activate plugins after import completes.</p>"
            },

            // ── CPT & ACF ──────────────────────────────────────────────────────
            {
                cat: "cpt", badge: "",
                q: "How do I export Custom Post Type (CPT) posts?",
                a: "<p>Under <strong>Export CPT Posts</strong>, select the post type from the dropdown (e.g. <em>Funeral Guides</em>, <em>Products</em>). Options include:</p><ul><li><strong>Export all ACF meta fields</strong> — includes all ACF data with field keys.</li><li><strong>Export individually</strong> — select specific posts from that CPT to export.</li></ul><p>You can also click <em>Export All (N post types)</em> to export every registered CPT in one go.</p>"
            },
            {
                cat: "cpt", badge: "",
                q: "How do I import CPT posts from a JSON file?",
                a: "<p>Under <strong>Import CPT Posts</strong>, click <em>Select JSON File(s)</em> — multiple files can be selected. Import options include:</p><ul><li><strong>Check media library</strong> — avoids duplicate image uploads.</li><li><strong>Download missing images</strong> — fetches images from original URLs.</li><li><strong>Import individually</strong> — choose specific posts from the file to import.</li></ul>"
            },

            // ── USERS ──────────────────────────────────────────────────────────
            {
                cat: "users", badge: "",
                q: "What user data is included in an export?",
                a: "<p><strong>Always included:</strong> login, email, display name, registration date, nicename, URL, status, locale, and roles.</p><p><strong>Optional fields you can enable:</strong></p><ul><li><strong>Password (hashed)</strong> — lets users log in immediately after import without a password reset.</li><li><strong>User Meta &amp; Capabilities</strong> — custom capabilities and plugin-stored role data.</li><li><strong>WooCommerce Data</strong> — billing/shipping addresses and last active date.</li><li><strong>ACF User Fields</strong> — all ACF fields attached to user profiles.</li><li><strong>CPT Authorship</strong> — records which custom post types this user authored, for remapping on import.</li></ul>"
            },
            {
                cat: "users", badge: "",
                q: "What happens to existing users during import?",
                a: "<p>Existing users matched by <strong>login or email</strong> are automatically skipped — they will not be overwritten or duplicated. Only genuinely new users are created. A full summary is shown after import listing created, skipped, and any errors.</p>"
            },
            {
                cat: "users", badge: "",
                q: "What are the import options for users?",
                a: "<p>Three options are available during user import:</p><ul><li><strong>Set a default password</strong> — assigns a specific password to all imported users (useful when password hashes were not exported).</li><li><strong>Try to preserve original user IDs</strong> — works only if the ID is not already taken; conflicts are logged in the summary.</li><li><strong>Send welcome email</strong> — emails login credentials to each new user. Silently skipped and noted in the summary if your server email is not configured.</li></ul>"
            },

            // ── BATCH & SCHEDULED ──────────────────────────────────────────────
            {
                cat: "batch", badge: "",
                q: "Why should I enable Batch Processing?",
                a: "<p>Post import is resource-intensive — each post requires image detection, formatting, tags, categories, and metadata processing. Without batching, large imports can time out on shared hosting. Batch Processing breaks the work into smaller chunks with configurable delays, preventing server overload and making 100K+ post migrations practical.</p>"
            },
            {
                cat: "batch", badge: "",
                q: "Which server preset should I choose?",
                a: "<p>Match the preset to your hosting environment:</p><ul><li><strong>Micro / Minimal</strong> — tiny shared hosting, prevents any timeout.</li><li><strong>Low / Light</strong> — budget VPS or throttled shared hosting.</li><li><strong>Standard (Recommended)</strong> — mid-tier VPS, good speed and safe load.</li><li><strong>Balanced / Performance</strong> — managed VPS, cloud, or dedicated server.</li><li><strong>Turbo / Max</strong> — powerful dedicated or enterprise bare-metal servers.</li></ul><p>After selecting a preset, click <em>Apply preset</em> to auto-fill the batch fields, then save.</p>"
            },
            {
                cat: "batch", badge: "",
                q: "What does 'Concurrent Requests' control and why does it matter?",
                a: "<p><strong>Concurrent Requests</strong> is the most impactful speed setting — it controls how many posts are processed simultaneously.</p><ul><li><strong>Shared hosting:</strong> 5–10</li><li><strong>VPS:</strong> 20–50</li><li><strong>Dedicated server:</strong> 100–200</li></ul><p>With 100 concurrent requests, 100K posts can complete in ~20 minutes versus 50+ hours sequentially. Setting it too high on weak hosting will cause timeouts — start low and increase gradually.</p>"
            },
            {
                cat: "batch", badge: "",
                q: "How does Scheduled Exports work?",
                a: "<p>Enable <strong>Scheduled Exports</strong> and configure:</p><ul><li><strong>Frequency</strong> — Daily, Weekly, or Monthly.</li><li><strong>What to export</strong> — Posts, Pages, Media, and/or Settings.</li><li><strong>Email notifications</strong> — receive an email when each export completes.</li><li><strong>Backup rotation</strong> — automatically delete old backups to manage storage.</li></ul><p>Backups are saved to your WordPress uploads directory. You can download or delete individual backup files from the <em>Existing Backups</em> list at any time. Google Drive storage is coming soon.</p>"
            },

            // ── SYSTEM & EMAIL ─────────────────────────────────────────────────
            {
                cat: "system", badge: "",
                q: "What does the System Test check?",
                a: "<p>The System Test reads your server's PHP configuration and reports:</p><ul><li>PHP and WordPress versions</li><li>Upload limits (<code>upload_max_filesize</code>, <code>post_max_size</code>)</li><li>Execution and input time limits</li><li>Memory limit and current usage</li><li>Whether ZipArchive is available (required for media export/import)</li><li>Whether the uploads directory is writable</li></ul><p>A <strong>Recommendations</strong> section highlights any values that are too low and may cause timeouts or upload failures.</p>"
            },
            {
                cat: "system", badge: "",
                q: "My Max Execution Time is low — will that cause problems?",
                a: "<p>Yes, for large operations. The plugin recommends at least <strong>300 seconds</strong> for <code>max_execution_time</code> and <code>max_input_time</code>. If these are lower, large media uploads or post imports may time out mid-process. Ask your hosting provider to increase these values in <code>php.ini</code>, or use <strong>Batch Processing</strong> to break work into smaller chunks that each complete within the time limit.</p>"
            },
            {
                cat: "system", badge: "",
                q: "How do I customise the welcome and notification email templates?",
                a: "<p>Under <strong>Email Template Settings</strong> you can configure:</p><ul><li>Brand name, primary and secondary header colours, header and body text colours.</li><li>Whether to show plugin branding in the footer.</li><li>A custom footer with HTML support and template tags such as <code>{site_name}</code>, <code>{user_name}</code>, <code>{password}</code>, <code>{login_url}</code>, and more.</li></ul><p>Use <em>Send Test Email</em> to preview your template before it goes live. These settings apply to both user welcome emails and scheduled export notifications.</p>"
            },
            {
                cat: "system", badge: "",
                q: "What are the Recommended Plugins shown in the plugin?",
                a: "<p>The <strong>Recommended Plugins</strong> section showcases hand-picked free and freemium plugins that complement this plugin's workflow — covering areas like SEO, performance, security, and forms. These are independent recommendations; installing them is entirely optional.</p>"
            }

        ];

        function render() {
            var s = searchVal.toLowerCase();
            var filtered = peiwmFAQData.filter(function (f) {
                return (activeTab === 'all' || f.cat === activeTab) &&
                    (!s || f.q.toLowerCase().indexOf(s) !== -1 || f.a.toLowerCase().indexOf(s) !== -1);
            });

            $count.text(filtered.length + ' question' + (filtered.length !== 1 ? 's' : ''));

            if (!filtered.length) {
                $list.html(
                    '<div class="peiwm-faq-empty">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                            '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>' +
                        '</svg>' +
                        'No questions found — try a different search.' +
                    '</div>'
                );
                return;
            }

            $list.html(filtered.map(function (f, i) {
                var badge = f.badge === 'pro'
                    ? '<span class="peiwm-faq-badge peiwm-faq-badge--pro">PRO</span>'
                    : '';
                return (
                    '<div class="peiwm-faq-item" id="pfaq-' + i + '">' +
                        '<button class="peiwm-faq-question" aria-expanded="false" aria-controls="pfaq-ans-' + i + '">' +
                            '<span class="peiwm-faq-icon" aria-hidden="true">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                    '<circle cx="12" cy="12" r="10"/>' +
                                    '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>' +
                                    '<circle cx="12" cy="17" r=".5" fill="currentColor"/>' +
                                '</svg>' +
                            '</span>' +
                            '<span class="peiwm-faq-question-text">' + f.q + '</span>' +
                            badge +
                            '<svg class="peiwm-faq-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                                '<polyline points="6 9 12 15 18 9"/>' +
                            '</svg>' +
                        '</button>' +
                        '<div class="peiwm-faq-answer" id="pfaq-ans-' + i + '" role="region">' +
                            '<div class="peiwm-faq-answer-inner">' + f.a + '</div>' +
                        '</div>' +
                    '</div>'
                );
            }).join(''));
        }

        $list.on('click', '.peiwm-faq-question', function () {
            var $item   = $(this).closest('.peiwm-faq-item');
            var wasOpen = $item.hasClass('is-open');

            $list.find('.peiwm-faq-item.is-open')
                .removeClass('is-open')
                .find('.peiwm-faq-question').attr('aria-expanded', 'false');

            if (!wasOpen) {
                $item.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $('.peiwm-faq-tabs').on('click', '.peiwm-faq-tab', function () {
            $('.peiwm-faq-tab').removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');
            activeTab = $(this).data('cat');
            render();
        });

        $('#peiwm-faq-search').on('input', function () {
            searchVal = $(this).val();
            render();
        });

        render();
    }

    // Call it
    initFAQ();

    // Theme export type change
    $('input[name="theme_export_type"]').on('change', function () {
        const exportType = $(this).val();
        const selectionGrid = $('#peiwm-theme-selection');
        
        if (exportType === 'selected') {
            loadThemesList();
            selectionGrid.show();
        } else {
            selectionGrid.hide();
        }
    });

    // Plugin export type change
    $('input[name="plugin_export_type"]').on('change', function () {
        const exportType = $(this).val();
        const selectionGrid = $('#peiwm-plugin-selection');
        
        if (exportType === 'selected') {
            loadPluginsList();
            selectionGrid.show();
        } else {
            selectionGrid.hide();
        }
    });

    // Load themes list
    function loadThemesList() {
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_get_themes_list',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    displayThemesList(response.data.themes);
                } else {
                    showError('Failed to load themes list: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                showError('Failed to load themes list: ' + error);
            }
        });
    }

    // Load plugins list
    function loadPluginsList() {
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_get_plugins_list',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    displayPluginsList(response.data.plugins);
                } else {
                    showError('Failed to load plugins list: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                showError('Failed to load plugins list: ' + error);
            }
        });
    }

    // Display themes list
    function displayThemesList(themes) {
        const container = $('#peiwm-theme-selection');
        let html = '<div class="peiwm-checkbox-grid">';
        
        themes.forEach(function (theme) {
            const activeLabel = theme.is_active ? ' <span class="peiwm-active-label">(Active)</span>' : '';
            html += `
                <label class="peiwm-checkbox-label">
                    <input type="checkbox" name="selected_themes[]" value="${theme.slug}" ${theme.is_active ? 'checked' : ''}>
                    <span class="peiwm-checkbox-text">
                        ${theme.name}${activeLabel}
                        <small class="peiwm-checkbox-description">Version: ${theme.version}</small>
                    </span>
                </label>
            `;
        });
        
        html += '</div>';
        container.html(html);
    }

    // Display plugins list
    function displayPluginsList(plugins) {
        const container = $('#peiwm-plugin-selection');
        let html = '<div class="peiwm-checkbox-grid">';
        
        plugins.forEach(function (plugin) {
            const activeLabel = plugin.is_active ? ' <span class="peiwm-active-label">(Active)</span>' : '';
            html += `
                <label class="peiwm-checkbox-label">
                    <input type="checkbox" name="selected_plugins[]" value="${plugin.file}" ${plugin.is_active ? 'checked' : ''}>
                    <span class="peiwm-checkbox-text">
                        ${plugin.name}${activeLabel}
                        <small class="peiwm-checkbox-description">Version: ${plugin.version}</small>
                    </span>
                </label>
            `;
        });
        
        html += '</div>';
        container.html(html);
    }

    // Export Themes
    $('#peiwm-export-themes').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const exportType = $('input[name="theme_export_type"]:checked').val();
        const selectedThemes = [];
        
        if (exportType === 'selected') {
            $('input[name="selected_themes[]"]:checked').each(function() {
                selectedThemes.push($(this).val());
            });
            
            if (selectedThemes.length === 0) {
                showError('Please select at least one theme to export.');
                return;
            }
        }
        
        const progress = $('#peiwm-themes-export-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Creating themes backup...');
        
        addLog('Starting themes export...', log);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_themes',
                nonce: peiwm_ajax.nonce,
                export_type: exportType,
                selected_themes: selectedThemes
            },
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Export complete!');
                    addLog('✓ ' + response.data.message, log, 'peiwm-log-success');
                    
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showSuccess(response.data.message + ' File size: ' + formatFileSize(response.data.file_size));
                } else {
                    progressText.text('Export failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log, 'peiwm-log-error');
                    showError('Export failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Export failed: ' + error);
                addLog('✗ Error: ' + error, log, 'peiwm-log-error');
                showError('Export failed: ' + error);
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Export Plugins
    $('#peiwm-export-plugins').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const exportType = $('input[name="plugin_export_type"]:checked').val();
        const selectedPlugins = [];
        
        if (exportType === 'selected') {
            $('input[name="selected_plugins[]"]:checked').each(function() {
                selectedPlugins.push($(this).val());
            });
            
            if (selectedPlugins.length === 0) {
                showError('Please select at least one plugin to export.');
                return;
            }
        }
        
        const progress = $('#peiwm-plugins-export-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Creating plugins backup...');
        
        addLog('Starting plugins export...', log);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_plugins',
                nonce: peiwm_ajax.nonce,
                export_type: exportType,
                selected_plugins: selectedPlugins
            },
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Export complete!');
                    addLog('✓ ' + response.data.message, log, 'peiwm-log-success');
                    
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showSuccess(response.data.message + ' File size: ' + formatFileSize(response.data.file_size));
                } else {
                    progressText.text('Export failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log, 'peiwm-log-error');
                    showError('Export failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Export failed: ' + error);
                addLog('✗ Error: ' + error, log, 'peiwm-log-error');
                showError('Export failed: ' + error);
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Select themes file
    $('#peiwm-select-themes-file').on('click', function () {
        $('#peiwm-themes-file').click();
    });

    $('#peiwm-themes-file').on('change', function () {
        const file = this.files[0];
        if (file) {
            if (file.type !== 'application/zip' && !file.name.toLowerCase().endsWith('.zip')) {
                showError('Please select a ZIP file.');
                return;
            }
            
            $('#peiwm-select-themes-file').text(file.name);
            $('#peiwm-import-themes').show();
            $('#peiwm-themes-import-options').show();
        }
    });

    // Select plugins file
    $('#peiwm-select-plugins-file').on('click', function () {
        $('#peiwm-plugins-file').click();
    });

    $('#peiwm-plugins-file').on('change', function () {
        const file = this.files[0];
        if (file) {
            if (file.type !== 'application/zip' && !file.name.toLowerCase().endsWith('.zip')) {
                showError('Please select a ZIP file.');
                return;
            }
            
            $('#peiwm-select-plugins-file').text(file.name);
            $('#peiwm-import-plugins').show();
            $('#peiwm-plugins-import-options').show();
        }
    });

    // Import Themes
    $('#peiwm-import-themes').on('click', function () {
        const fileInput = $('#peiwm-themes-file')[0];
        if (!fileInput.files.length) {
            showError('Please select a file to import.');
            return;
        }

        const file = fileInput.files[0];
        const replaceExisting = $('#peiwm-replace-existing-themes').is(':checked');
        const skipExisting    = $('#peiwm-skip-existing-themes').is(':checked');
        const activateTheme   = $('#peiwm-activate-imported-theme').is(':checked');
        
        const progress = $('#peiwm-themes-import-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Starting themes import...');
        
        addLog('Starting themes import...', log);
        
        const formData = new FormData();
        formData.append('action', 'peiwm_import_themes');
        formData.append('nonce', peiwm_ajax.nonce);
        formData.append('themes_file', file);
        formData.append('replace_existing', replaceExisting ? '1' : '0');
        formData.append('skip_existing', skipExisting ? '1' : '0');
        formData.append('activate_theme', activateTheme ? '1' : '0');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Import complete!');
                    addLog('✓ ' + response.data.message, log, 'peiwm-log-success');
                    
                    // Show detailed results
                    if (response.data.imported_themes && response.data.imported_themes.length > 0) {
                        addLog('📥 Imported Themes:', log, 'peiwm-log-success');
                        response.data.imported_themes.forEach(function(theme) {
                            addLog('  ✓ ' + theme, log, 'peiwm-log-success');
                        });
                    }
                    
                    if (response.data.skipped_themes && response.data.skipped_themes.length > 0) {
                        addLog('⚠ Skipped Themes (already exist):', log, 'peiwm-log-warning');
                        response.data.skipped_themes.forEach(function(theme) {
                            addLog('  ⚠ ' + theme, log, 'peiwm-log-warning');
                        });
                    }
                    
                    if (response.data.failed_themes && response.data.failed_themes.length > 0) {
                        addLog('❌ Failed Themes:', log, 'peiwm-log-error');
                        response.data.failed_themes.forEach(function(theme) {
                            addLog('  ❌ ' + theme, log, 'peiwm-log-error');
                        });
                    }
                    
                    if (response.data.activated_theme) {
                        addLog('🎨 Activated Theme: ' + response.data.activated_theme, log, 'peiwm-log-info');
                    }
                    
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log, 'peiwm-log-error');
                    showError('Import failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Import failed: ' + error);
                addLog('✗ Error: ' + error, log, 'peiwm-log-error');
                showError('Import failed: ' + error);
            }
        });
    });

    // Import Plugins
    $('#peiwm-import-plugins').on('click', function () {
        const fileInput = $('#peiwm-plugins-file')[0];
        if (!fileInput.files.length) {
            showError('Please select a file to import.');
            return;
        }

        const file = fileInput.files[0];
        const replaceExisting = $('#peiwm-replace-existing-plugins').is(':checked');
        const skipExisting    = $('#peiwm-skip-existing-plugins').is(':checked');
        const activatePlugins = $('#peiwm-activate-imported-plugins').is(':checked');
        
        const progress = $('#peiwm-plugins-import-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Starting plugins import...');
        
        addLog('Starting plugins import...', log);
        
        const formData = new FormData();
        formData.append('action', 'peiwm_import_plugins');
        formData.append('nonce', peiwm_ajax.nonce);
        formData.append('plugins_file', file);
        formData.append('replace_existing', replaceExisting ? '1' : '0');
        formData.append('skip_existing', skipExisting ? '1' : '0');
        formData.append('activate_plugins', activatePlugins ? '1' : '0');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Import complete!');
                    addLog('✓ ' + response.data.message, log, 'peiwm-log-success');
                    
                    // Show detailed results
                    if (response.data.imported_plugins && response.data.imported_plugins.length > 0) {
                        addLog('📥 Imported Plugins:', log, 'peiwm-log-success');
                        response.data.imported_plugins.forEach(function(plugin) {
                            addLog('  ✓ ' + plugin, log, 'peiwm-log-success');
                        });
                    }
                    
                    if (response.data.skipped_plugins && response.data.skipped_plugins.length > 0) {
                        addLog('⚠ Skipped Plugins (already exist):', log, 'peiwm-log-warning');
                        response.data.skipped_plugins.forEach(function(plugin) {
                            addLog('  ⚠ ' + plugin, log, 'peiwm-log-warning');
                        });
                    }
                    
                    if (response.data.failed_plugins && response.data.failed_plugins.length > 0) {
                        addLog('❌ Failed Plugins:', log, 'peiwm-log-error');
                        response.data.failed_plugins.forEach(function(plugin) {
                            addLog('  ❌ ' + plugin, log, 'peiwm-log-error');
                        });
                    }
                    
                    if (response.data.activated_plugins && response.data.activated_plugins.length > 0) {
                        addLog('🔌 Activated Plugins:', log, 'peiwm-log-info');
                        response.data.activated_plugins.forEach(function(plugin) {
                            addLog('  🔌 ' + plugin, log, 'peiwm-log-info');
                        });
                    }
                    
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log, 'peiwm-log-error');
                    showError('Import failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Import failed: ' + error);
                addLog('✗ Error: ' + error, log, 'peiwm-log-error');
                showError('Import failed: ' + error);
            }
        });
    });

    // Format file size
    function formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    // Close modal handlers
    $('.peiwm-modal-close, .peiwm-modal-overlay').on('click', function (e) {
        if (e.target === this) {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        }
    });
});
