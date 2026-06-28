Ignore all of these patterns:
Squiz.PHP.DiscouragedFunctions — set_time_limit(), ini_set() — all instances across all files
WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler — set_error_handler() in class-main.php
WordPress.PHP.DevelopmentFunctions.error_log_error_log — error_log() in post/page handler
WordPress.DB.SlowDBQuery — meta_query, meta_key slow query warnings — unavoidable for your use case
WordPress.DB.DirectDatabaseQuery — the direct DB + no caching warnings in post-handler — acceptable for import context


Don't ignore these even though they say WARNING:

WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — the $_POST['post_data'], $_FILES unsanitized inputs — these are WARNINGs but security-related, reviewers do flag these
WordPress.WP.AlternativeFunctions.file_system_operations_* — fopen, fread, rmdir, readfile, is_writable — WARNINGs but WP.org expects WP_Filesystem usage


🔴 Must Fix Now (Hard Blockers)
severity 7+ / forbidden functions
FileIssueLineclass-media-handler.phpmove_uploaded_file() forbidden329class-themes-plugins-handler.phpmove_uploaded_file() forbidden536class-themes-plugins-handler.phpmove_uploaded_file() forbidden709class-themes-plugins-handler.phpWriting to WP_PLUGIN_DIR via copy() — use uploads dir instead771class-heartbeat-handler.phpmysqli_close() direct DB — forbidden78class-main.phpregister_setting() missing sanitize_callback409class-generic-recommendations.phpVariable $info['name'] passed to __() — must be string literal253class-generic-recommendations.phpVariable $info['description'] passed to __() — must be string literal254.gitignoreHidden file not permitted — delete it—changes.md, doc.md, mail-security-fix.md, mail.md, phpcs-issues.mdUnexpected markdown files — delete all—readme.txtShort description over 150 chars—

🟡 Should Fix Before Submission (Reviewers Flag These)
Security + WP standards violations
FileIssueLineclass-ajax-handler.phpis_writable() → WP_Filesystem97class-ajax-handler.phpreadfile() → WP_Filesystem453class-ajax-handler.phpfopen() → WP_Filesystem499class-ajax-handler.phpfread() → WP_Filesystem502class-ajax-handler.phpfclose() → WP_Filesystem505class-ajax-handler.phpreadfile() → WP_Filesystem509class-media-handler.phpis_writable() → WP_Filesystem310class-media-handler.phprmdir() → WP_Filesystem714class-media-handler.phpsuppress_filters => true prohibited130class-themes-plugins-handler.phprmdir() → WP_Filesystem676class-batch-processor.phpsuppress_filters => true prohibited502class-post-handler.php$_POST['post_data'] unsanitized310class-post-handler.php$_POST['image_data'] unsanitized555class-page-handler.php$_POST['page_data'] unsanitized229class-page-handler.php$_POST['image_data'] unsanitized789class-widgets-menus-handler.php$_POST['widgets_data'] unsanitized249class-media-handler.php$_FILES['media_file']['tmp_name'] unsanitized291

🟠 Fix Before Next Release (i18n / Standards)
Easy fixes, just tedious — do in one pass
FileIssueLinesclass-admin-download-buttons.phpdate() → gmdate()349, 400class-admin-menu.phpdate() → gmdate()644, 650class-themes-plugins-handler.phpdate() → gmdate()391, 440class-post-handler.phpparse_url() → wp_parse_url()1393class-page-handler.phpparse_url() → wp_parse_url()1056class-widgets-menus-handler.phpparse_url() → wp_parse_url()872class-generic-recommendations.phpMissing text domain on __(), _x(), esc_attr_e(), esc_html_e()variousAll filesMissing /* translators: */ comments on esc_html__() with %s/%dvariousMultiple filesUnordered %d, %d → %1$d, %2$d placeholdersvarious

Here is the Issues list from there follow fixing all the must fixed only 

Important JSON file which need to fixed

{
    "plugin": "Post Export Import with Media",
    "generated_at": "2026-06-27",
    "categories": {
        "must_fix_now": {
            "label": "Hard Blockers — Fix Before Submission",
            "issues": [
                {
                    "file": "includes/class-media-handler.php",
                    "line": 329,
                    "code": "Generic.PHP.ForbiddenFunctions.Found",
                    "message": "move_uploaded_file() is forbidden — replace with wp_handle_upload()",
                    "severity": 7
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "line": 536,
                    "code": "Generic.PHP.ForbiddenFunctions.Found",
                    "message": "move_uploaded_file() is forbidden — replace with wp_handle_upload()",
                    "severity": 7
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "line": 709,
                    "code": "Generic.PHP.ForbiddenFunctions.Found",
                    "message": "move_uploaded_file() is forbidden — replace with wp_handle_upload()",
                    "severity": 7
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "line": 771,
                    "code": "PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite",
                    "message": "Writing to WP_PLUGIN_DIR via copy() is forbidden — plugin folders are deleted on upgrade. Use wp_upload_dir() instead",
                    "severity": 7
                },
                {
                    "file": "includes/class-heartbeat-handler.php",
                    "line": 78,
                    "code": "WordPress.DB.RestrictedFunctions.mysql_mysqli_close",
                    "message": "mysqli_close() direct DB access is forbidden — remove or replace with $wpdb approach",
                    "severity": 7
                },
                {
                    "file": "includes/class-main.php",
                    "line": 409,
                    "code": "PluginCheck.CodeAnalysis.SettingSanitization.register_settingMissing",
                    "message": "register_setting() missing sanitize_callback parameter",
                    "severity": 7
                },
                {
                    "file": "includes/class-generic-recommendations.php",
                    "line": 253,
                    "code": "WordPress.WP.I18n.NonSingularStringLiteralText",
                    "message": "Variable $info['name'] passed to __() — must be a static string literal",
                    "severity": 7
                },
                {
                    "file": "includes/class-generic-recommendations.php",
                    "line": 254,
                    "code": "WordPress.WP.I18n.NonSingularStringLiteralText",
                    "message": "Variable $info['description'] passed to __() — must be a static string literal",
                    "severity": 7
                },
                {
                    "file": ".gitignore",
                    "line": 0,
                    "code": "hidden_files",
                    "message": "Hidden files are not permitted in plugin root — delete .gitignore before submission",
                    "severity": 8
                },
                {
                    "file": "changes.md",
                    "line": 0,
                    "code": "unexpected_markdown_file",
                    "message": "Unexpected markdown file in plugin root — delete before submission",
                    "severity": 9
                },
                {
                    "file": "doc.md",
                    "line": 0,
                    "code": "unexpected_markdown_file",
                    "message": "Unexpected markdown file in plugin root — delete before submission",
                    "severity": 9
                },
                {
                    "file": "mail-security-fix.md",
                    "line": 0,
                    "code": "unexpected_markdown_file",
                    "message": "Unexpected markdown file in plugin root — delete before submission",
                    "severity": 9
                },
                {
                    "file": "mail.md",
                    "line": 0,
                    "code": "unexpected_markdown_file",
                    "message": "Unexpected markdown file in plugin root — delete before submission",
                    "severity": 9
                },
                {
                    "file": "phpcs-issues.md",
                    "line": 0,
                    "code": "unexpected_markdown_file",
                    "message": "Unexpected markdown file in plugin root — delete before submission",
                    "severity": 9
                },
                {
                    "file": "readme.txt",
                    "line": 0,
                    "code": "readme_parser_warnings_trimmed_short_description",
                    "message": "Short description exceeds 150 characters and will be truncated on WP.org — shorten it",
                    "severity": 6
                }
            ]
        },
        "should_fix_before_submission": {
            "label": "Security + WP Standards — Reviewers Flag These",
            "issues": [
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 97,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_is_writable",
                    "message": "is_writable() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 453,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_readfile",
                    "message": "readfile() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 499,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fopen",
                    "message": "fopen() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 502,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fread",
                    "message": "fread() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 505,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fclose",
                    "message": "fclose() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "line": 509,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_readfile",
                    "message": "readfile() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "line": 310,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_is_writable",
                    "message": "is_writable() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "line": 714,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_rmdir",
                    "message": "rmdir() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "line": 130,
                    "code": "WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters",
                    "message": "suppress_filters => true is prohibited — remove it"
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "line": 676,
                    "code": "WordPress.WP.AlternativeFunctions.file_system_operations_rmdir",
                    "message": "rmdir() — use WP_Filesystem instead"
                },
                {
                    "file": "includes/class-batch-processor.php",
                    "line": 502,
                    "code": "WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters",
                    "message": "suppress_filters => true is prohibited — remove it"
                },
                {
                    "file": "includes/class-post-handler.php",
                    "line": 310,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_POST['post_data'] is not sanitized — add wp_unslash() and sanitization or phpcs:ignore with justification comment"
                },
                {
                    "file": "includes/class-post-handler.php",
                    "line": 555,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_POST['image_data'] is not sanitized"
                },
                {
                    "file": "includes/class-page-handler.php",
                    "line": 229,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_POST['page_data'] is not sanitized"
                },
                {
                    "file": "includes/class-page-handler.php",
                    "line": 789,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_POST['image_data'] is not sanitized"
                },
                {
                    "file": "includes/class-widgets-menus-handler.php",
                    "line": 249,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_POST['widgets_data'] is not sanitized"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "line": 291,
                    "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                    "message": "$_FILES['media_file']['tmp_name'] is not sanitized"
                }
            ]
        },
        "fix_before_next_release": {
            "label": "i18n + Standards — Easy but Tedious, Do in One Pass",
            "issues": [
                {
                    "file": "includes/class-admin-download-buttons.php",
                    "lines": [349, 400],
                    "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                    "message": "date() → gmdate()"
                },
                {
                    "file": "includes/class-admin-menu.php",
                    "lines": [644, 650],
                    "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                    "message": "date() → gmdate()"
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "lines": [391, 440],
                    "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                    "message": "date() → gmdate()"
                },
                {
                    "file": "includes/class-post-handler.php",
                    "line": 1393,
                    "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                    "message": "parse_url() → wp_parse_url()"
                },
                {
                    "file": "includes/class-page-handler.php",
                    "line": 1056,
                    "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                    "message": "parse_url() → wp_parse_url()"
                },
                {
                    "file": "includes/class-widgets-menus-handler.php",
                    "line": 872,
                    "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                    "message": "parse_url() → wp_parse_url()"
                },
                {
                    "file": "includes/class-generic-recommendations.php",
                    "lines": [333, 358, 360, 430, 431, 434, 479, 481, 486, 499, 501, 506, 516, 520, 528, 529, 542, 561, 563, 565],
                    "code": "WordPress.WP.I18n.MissingArgDomain",
                    "message": "Missing text domain parameter in __(), _x(), esc_attr_e(), esc_html_e() calls — add 'post-export-import-with-media'"
                },
                {
                    "file": "includes/class-admin-download-buttons.php",
                    "lines": [274, 320],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-admin-menu.php",
                    "lines": [710, 840, 1352, 1502],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-ajax-handler.php",
                    "lines": [310, 401],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "lines": [340, 410, 484, 562, 568],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-media-handler.php",
                    "line": 484,
                    "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                    "message": "Unordered placeholders — change '%s, %s' to '%1$s, %2$s' in 'Source file not found: %s (looking in: %s)'"
                },
                {
                    "file": "includes/class-page-handler.php",
                    "lines": [345, 472, 478],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-post-handler.php",
                    "lines": [429, 660, 666],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-settings-handler.php",
                    "line": 356,
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-settings-handler.php",
                    "line": 356,
                    "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                    "message": "Unordered placeholders — change '%d, %d, %d' to '%1$d, %2$d, %3$d' in 'Settings import completed: %d imported, %d skipped, %d failed'"
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "lines": [178, 221, 608, 614, 801, 807],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-themes-plugins-handler.php",
                    "lines": [608, 801],
                    "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                    "message": "Unordered placeholders — change '%d, %d, %d' to '%1$d, %2$d, %3$d'"
                },
                {
                    "file": "includes/class-widgets-menus-handler.php",
                    "lines": [368, 555, 652],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above esc_html__() with placeholders"
                },
                {
                    "file": "includes/class-widgets-menus-handler.php",
                    "lines": [368, 555, 652],
                    "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                    "message": "Unordered placeholders — change '%d, %d' / '%d, %d, %d' to ordered versions"
                },
                {
                    "file": "includes/class-generic-recommendations.php",
                    "lines": [333, 358, 431, 479, 499, 520, 529],
                    "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                    "message": "Missing /* translators: */ comment above __() / _x() with placeholders"
                }
            ]
        }
    }
}

Here is the full json file you may check and fixed from here if you find it must need to fixed.

{
    "generated_at": "2026-06-27 14:11:39",
    "plugin": "Post Export Import with Media",
    "results": {
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-admin-download-buttons.php": [
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 274,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 320,
                "column": 21
            },
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 349,
                "column": 50
            },
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 400,
                "column": 52
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-admin-menu.php": [
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 644,
                "column": 43
            },
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 650,
                "column": 43
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 710,
                "column": 53
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 840,
                "column": 53
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 1352,
                "column": 53
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 1502,
                "column": 53
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-ajax-handler.php": [
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: is_writable().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_is_writable",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 97,
                "column": 30
            },
            
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 310,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 401,
                "column": 25
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: readfile().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_readfile",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 453,
                "column": 3
            },
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 486,
                "column": 4
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 487,
                "column": 4
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: fopen().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fopen",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 499,
                "column": 14
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: fread().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fread",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 502,
                "column": 11
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: fclose().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_fclose",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 505,
                "column": 5
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: readfile().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_readfile",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 509,
                "column": 4
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-generic-recommendations.php": [
            {
                "message": "The $text parameter must be a single text string literal. Found: $info['name']",
                "code": "WordPress.WP.I18n.NonSingularStringLiteralText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#basic-strings",
                "severity": 7,
                "type": "ERROR",
                "line": 253,
                "column": 57
            },
            {
                "message": "The $text parameter must be a single text string literal. Found: $info['description']",
                "code": "WordPress.WP.I18n.NonSingularStringLiteralText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#basic-strings",
                "severity": 7,
                "type": "ERROR",
                "line": 254,
                "column": 78
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 333,
                "column": 44
            },
            {
                "message": "A function call to __() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 333,
                "column": 44
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 358,
                "column": 32
            },
            {
                "message": "A function call to __() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 358,
                "column": 32
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 360,
                "column": 13
            },
            {
                "message": "Missing $domain parameter in function call to esc_attr_e().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 430,
                "column": 39
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 431,
                "column": 49
            },
            {
                "message": "A function call to __() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 431,
                "column": 49
            },
            {
                "message": "Missing $domain parameter in function call to esc_attr_e().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 434,
                "column": 39
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 479,
                "column": 48
            },
            {
                "message": "A function call to _x() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 479,
                "column": 48
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 481,
                "column": 29
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 486,
                "column": 29
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 499,
                "column": 44
            },
            {
                "message": "A function call to _x() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 499,
                "column": 44
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 501,
                "column": 25
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 506,
                "column": 25
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 516,
                "column": 25
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 520,
                "column": 37
            },
            {
                "message": "A function call to _x() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 520,
                "column": 37
            },
            {
                "message": "Missing $domain parameter in function call to __().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 528,
                "column": 40
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 529,
                "column": 41
            },
            {
                "message": "A function call to _x() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 529,
                "column": 41
            },
            {
                "message": "Missing $domain parameter in function call to _x().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 542,
                "column": 25
            },
            {
                "message": "Missing $domain parameter in function call to esc_html_e().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 561,
                "column": 13
            },
            {
                "message": "Missing $domain parameter in function call to esc_html_e().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 563,
                "column": 13
            },
            {
                "message": "Missing $domain parameter in function call to esc_html_e().",
                "code": "WordPress.WP.I18n.MissingArgDomain",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/",
                "severity": 5,
                "type": "ERROR",
                "line": 565,
                "column": 13
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-media-handler.php": [
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 121,
                "column": 5
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 122,
                "column": 5
            },
            {
                "message": "Setting `suppress_filters` to `true` is prohibited.",
                "code": "WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 130,
                "column": 17
            },
            {
                "message": "Detected usage of a non-sanitized input variable: $_FILES[&#039;media_file&#039;][&#039;tmp_name&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 291,
                "column": 64
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: is_writable().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_is_writable",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 310,
                "column": 11
            },
            {
                "message": "The use of function move_uploaded_file() is forbidden",
                "code": "Generic.PHP.ForbiddenFunctions.Found",
                "link": null,
                "docs": "",
                "severity": 7,
                "type": "ERROR",
                "line": 329,
                "column": 11
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 340,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 410,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 484,
                "column": 21
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$s, %2$s\", but got \"%s, %s\" in 'Source file not found: %s (looking in: %s)'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 484,
                "column": 33
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 562,
                "column": 17
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 568,
                "column": 21
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: rmdir().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_rmdir",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 714,
                "column": 3
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-page-handler.php": [
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 214,
                "column": 4
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 215,
                "column": 4
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 218,
                "column": 4
            },
            {
                "message": "Detected usage of a non-sanitized input variable: $_POST[&#039;page_data&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 229,
                "column": 64
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 345,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 472,
                "column": 17
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 478,
                "column": 21
            },
            {
                "message": "Detected usage of a non-sanitized input variable: $_POST[&#039;image_data&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 789,
                "column": 66
            },
            {
                "message": "parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead.",
                "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 1056,
                "column": 25
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-post-handler.php": [
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 78,
                "column": 5
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 144,
                "column": 5
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 220,
                "column": 5
            },
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 295,
                "column": 4
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 296,
                "column": 4
            },
            {
                "message": "The use of function ini_set() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 299,
                "column": 4
            },
            {
                "message": "Detected usage of a non-sanitized input variable: $_POST[&#039;post_data&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 310,
                "column": 64
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 429,
                "column": 21
            },
            {
                "message": "Detected usage of a non-sanitized input variable: $_POST[&#039;image_data&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 555,
                "column": 66
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 660,
                "column": 17
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 666,
                "column": 21
            },
            {
                "message": "parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead.",
                "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 1393,
                "column": 25
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-settings-handler.php": [
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 356,
                "column": 17
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d, %3$d\", but got \"%d, %d, %d\" in 'Settings import completed: %d imported, %d skipped, %d failed'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 356,
                "column": 29
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-themes-plugins-handler.php": [
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 178,
                "column": 21
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 221,
                "column": 21
            },
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 391,
                "column": 38
            },
            {
                "message": "date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.",
                "code": "WordPress.DateTime.RestrictedFunctions.date_date",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 440,
                "column": 39
            },
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 525,
                "column": 4
            },
            {
                "message": "The use of function move_uploaded_file() is forbidden",
                "code": "Generic.PHP.ForbiddenFunctions.Found",
                "link": null,
                "docs": "",
                "severity": 7,
                "type": "ERROR",
                "line": 536,
                "column": 10
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 608,
                "column": 13
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d, %3$d\", but got \"%d, %d, %d\" in 'Themes import completed: %d imported, %d skipped, %d failed'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 608,
                "column": 25
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 614,
                "column": 34
            },
            {
                "message": "File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: rmdir().",
                "code": "WordPress.WP.AlternativeFunctions.file_system_operations_rmdir",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 676,
                "column": 3
            },
            {
                "message": "The use of function set_time_limit() is discouraged",
                "code": "Squiz.PHP.DiscouragedFunctions.Discouraged",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 697,
                "column": 4
            },
            {
                "message": "The use of function move_uploaded_file() is forbidden",
                "code": "Generic.PHP.ForbiddenFunctions.Found",
                "link": null,
                "docs": "",
                "severity": 7,
                "type": "ERROR",
                "line": 709,
                "column": 10
            },
            {
                "message": "Plugin folders are deleted when upgraded. Do not save data to the plugin folder using copy(). Detected usage of constant WP_PLUGIN_DIR. Use wp_upload_dir() to get the uploads directory path or save to the database instead.",
                "code": "PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 771,
                "column": 34
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 801,
                "column": 13
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d, %3$d\", but got \"%d, %d, %d\" in 'Plugins import completed: %d imported, %d skipped, %d failed'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 801,
                "column": 25
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 807,
                "column": 34
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-widgets-menus-handler.php": [
            {
                "message": "Detected usage of a non-sanitized input variable: $_POST[&#039;widgets_data&#039;]",
                "code": "WordPress.Security.ValidatedSanitizedInput.InputNotSanitized",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "WARNING",
                "line": 249,
                "column": 71
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 368,
                "column": 21
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d\", but got \"%d, %d\" in 'Import completed: %d widgets and %d menus imported'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 368,
                "column": 33
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 555,
                "column": 13
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d, %3$d\", but got \"%d, %d, %d\" in 'Widgets import completed: %d imported, %d skipped, %d failed'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 555,
                "column": 25
            },
            {
                "message": "A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a \"translators:\" comment on the line above to clarify the meaning of the placeholders.",
                "code": "WordPress.WP.I18n.MissingTranslatorsComment",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions",
                "severity": 5,
                "type": "ERROR",
                "line": 652,
                "column": 13
            },
            {
                "message": "Multiple placeholders in translatable strings should be ordered. Expected \"%1$d, %2$d, %3$d\", but got \"%d, %d, %d\" in 'Navigation menus import completed: %d imported, %d skipped, %d failed'.",
                "code": "WordPress.WP.I18n.UnorderedPlaceholdersText",
                "link": null,
                "docs": "https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables",
                "severity": 5,
                "type": "ERROR",
                "line": 652,
                "column": 25
            },
            {
                "message": "parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead.",
                "code": "WordPress.WP.AlternativeFunctions.parse_url_parse_url",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 872,
                "column": 18
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-heartbeat-handler.php": [
            {
                "message": "Accessing the database directly should be avoided. Please use the $wpdb object and associated functions instead. Found: mysqli_close.",
                "code": "WordPress.DB.RestrictedFunctions.mysql_mysqli_close",
                "link": null,
                "docs": "",
                "severity": 7,
                "type": "ERROR",
                "line": 78,
                "column": 4
            }
        ],
        "C:\\Users\\HP\\Local Sites\\export-import\\app\\public\\wp-content\\plugins\\post-export-import-with-media\\includes\\class-batch-processor.php": [
            {
                "message": "Setting `suppress_filters` to `true` is prohibited.",
                "code": "WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters",
                "link": null,
                "docs": "",
                "severity": 5,
                "type": "ERROR",
                "line": 502,
                "column": 17
            }
        ]
    }
}