
/**
 * Big GEO Admin JavaScript - v1.0.0
 * Handles all AJAX interactions for the Big GEO plugin
 */
(function ($) {
    'use strict';

    var BigGEO = {

        init: function () {
            this.bindAudit();
            this.bindRobotsFix();
            this.bindRobotsWrite();
            this.bindLLMSGenerate();
            this.bindLLMSFullGenerate();
            this.bindLLMSFullPreview();
        },

        showNotice: function ($container, type, message) {
            var $notice = $container.find('.big-geo-notice');
            if (!$notice.length) {
                $notice = $('<div class="big-geo-notice"></div>').appendTo($container);
            }
            $notice.removeClass('notice-success notice-error notice-warning')
                   .addClass('notice-' + type)
                   .html(message).show();
        },

        setLoading: function ($btn, loading) {
            var $spinner = $btn.next('.big-geo-spinner');
            if (loading) {
                $btn.prop('disabled', true);
                if (!$spinner.length) {
                    $('<span class="big-geo-spinner"></span>').insertAfter($btn);
                    $spinner = $btn.next('.big-geo-spinner');
                }
                $spinner.show();
            } else {
                $btn.prop('disabled', false);
                $spinner.hide();
            }
        },

        doAjax: function (action, extraData, successCb, errorCb) {
            var data = $.extend({ action: action, nonce: bigGeo.nonce }, extraData);
            return $.ajax({
                url: bigGeo.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (res) {
                    if (res.success) {
                        if (typeof successCb === 'function') successCb(res.data);
                    } else {
                        var msg = (res.data && res.data.message) ? res.data.message : 'An error occurred.';
                        if (typeof errorCb === 'function') errorCb(msg);
                    }
                },
                error: function (xhr, status, error) {
                    if (typeof errorCb === 'function') errorCb('Request failed: ' + error);
                }
            });
        },

        buildAuditTable: function (data) {
            if (data.html) { return data.html; }
            var bots = data.bots || [];
            if (!bots.length) { return '<p>No bots data returned.</p>'; }
            var html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>AI Crawler</th><th>Status</th><th>Rule Found</th><th>Details</th>';
            html += '</tr></thead><tbody>';
            $.each(bots, function (idx, bot) {
                var status = (bot.status || '').toLowerCase();
                var rowClass = 'bot-row-' + (status === 'allowed' ? 'allowed' : (status === 'blocked' ? 'blocked' : 'missing'));
                var icon = status === 'allowed'
                    ? '<span class="dashicons dashicons-yes-alt"></span>'
                    : (status === 'blocked' ? '<span class="dashicons dashicons-dismiss"></span>'
                    : '<span class="dashicons dashicons-warning"></span>');
                html += '<tr class="' + rowClass + '">';
                html += '<td><strong>' + $('<div>').text(bot.name || bot.agent || '').html() + '</strong></td>';
                html += '<td>' + icon + ' <span class="status-badge ' + status + '">' + $('<div>').text(bot.status || 'Unknown').html() + '</span></td>';
                html += '<td>' + $('<div>').text(bot.rule || '-').html() + '</td>';
                html += '<td>' + $('<div>').text(bot.details || bot.note || '').html() + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            return html;
        },

        bindAudit: function () {
            $(document).on('click', '#big-geo-run-audit', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_run_audit', {},
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        var tableHtml = BigGEO.buildAuditTable(data);
                        var $results = $('#big-geo-audit-results');
                        if ($results.length) { $results.html(tableHtml); }
                        var summary = data.all_allowed
                            ? 'All AI crawlers are allowed! Your site is AI-discoverable.'
                            : 'Some AI crawlers may be blocked. Review and apply the fix.';
                        BigGEO.showNotice($section, data.all_allowed ? 'success' : 'warning', summary);
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        },

        bindRobotsFix: function () {
            $(document).on('click', '#big-geo-apply-fix', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                if (!confirm('This will modify the virtual robots.txt via WordPress filter. Continue?')) return;
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_fix_robots', {},
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        BigGEO.showNotice($section, 'success', data.message || 'AI bot rules applied to robots.txt.');
                        setTimeout(function () { $('#big-geo-run-audit').trigger('click'); }, 800);
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        },

        bindRobotsWrite: function () {
            $(document).on('click', '#big-geo-write-robots', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                var content = $('#big-geo-robots-content').val() || '';
                if (!confirm('This will write a physical robots.txt to your site root. Continue?')) return;
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_write_robots', { robots_content: content },
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        BigGEO.showNotice($section, 'success', data.message || 'robots.txt written successfully.');
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        },

        bindLLMSGenerate: function () {
            $(document).on('click', '#big-geo-generate-llms', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_generate_llms', {},
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        BigGEO.showNotice($section, 'success', data.message || 'llms.txt generated successfully.');
                        if (data.preview) { $('#big-geo-llms-preview').text(data.preview).show(); }
                        if (data.file_url) { $('#big-geo-llms-link').attr('href', data.file_url).show(); }
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        },

        bindLLMSFullGenerate: function () {
            $(document).on('click', '#big-geo-generate-llms-full', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_generate_llms_full', {},
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        BigGEO.showNotice($section, 'success', data.message || 'llms-full.txt generated successfully.');
                        if (data.preview) { $('#big-geo-llms-full-preview').text(data.preview).show(); }
                        if (data.file_url) { $('#big-geo-llms-full-link').attr('href', data.file_url).show(); }
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        },

        bindLLMSFullPreview: function () {
            $(document).on('click', '#big-geo-preview-llms-full', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $section = $btn.closest('.big-geo-section, .card, .inside, .postbox').first();
                if (!$section.length) $section = $btn.parent();
                BigGEO.setLoading($btn, true);
                BigGEO.doAjax('big_geo_preview_llms_full', {},
                    function (data) {
                        BigGEO.setLoading($btn, false);
                        var $preview = $('#big-geo-llms-full-preview');
                        if (data.content) {
                            if (!$preview.length) {
                                $('<div id="big-geo-llms-full-preview" class="big-geo-preview-box"></div>').insertAfter($btn.parent());
                            }
                            $('#big-geo-llms-full-preview').text(data.content).show();
                        }
                    },
                    function (msg) { BigGEO.setLoading($btn, false); BigGEO.showNotice($section, 'error', msg); }
                );
            });
        }
    };

    $(document).ready(function () { BigGEO.init(); });

}(jQuery));
