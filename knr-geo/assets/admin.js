jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    $('#knr-geo-preview-llms').on('click', function() {
        $.post(knrGeo.ajaxUrl, { action: 'knr_geo_regenerate_llms', nonce: knrGeo.nonce }, function(res) {
            $('#knr-geo-llms-preview').val(res.data.content);
        });
    });
    $('#knr-geo-run-audit').on('click', function() {
        var btn = $(this);
        btn.text('Running...');
        $.post(knrGeo.ajaxUrl, { action: 'knr_geo_run_audit', nonce: knrGeo.nonce }, function(res) {
            var html = '<table class="knr-geo-audit-table"><thead><tr><th>Bot</th><th>Label</th><th>Status</th></tr></thead><tbody>';
            $.each(res.data.bots, function(i, bot) {
                var statusClass = bot.status === 'allowed' ? 'knr-geo-status-allowed' : 'knr-geo-status-blocked';
                html += '<tr><td>' + bot.bot + '</td><td>' + bot.label + '</td><td class="' + statusClass + '">' + bot.status.toUpperCase() + '</td></tr>';
            });
            html += '</tbody></table>';
            if (!res.data.all_allowed) {
                html += '<p><button type="button" class="button button-primary" id="knr-geo-apply-fix">Auto-Fix (Apply Allow Rules)</button></p>';
            }
            $('#knr-geo-audit-results').html(html);
            btn.text('Run Audit Now');
        });
    });
    $(document).on('click', '#knr-geo-apply-fix', function() {
        $.post(knrGeo.ajaxUrl, { action: 'knr_geo_fix_robots', nonce: knrGeo.nonce }, function(res) {
            alert(res.data.message);
            $('#knr-geo-run-audit').click();
        });
    });
});
