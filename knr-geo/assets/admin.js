jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    $('#big-geo-preview-llms').on('click', function() {
        $.post(bigGeo.ajaxUrl, { action: 'big_geo_regenerate_llms', nonce: bigGeo.nonce }, function(res) {
            $('#big-geo-llms-preview').val(res.data.content);
        });
    });
    $('#big-geo-run-audit').on('click', function() {
        var btn = $(this);
        btn.text('Running...');
        $.post(bigGeo.ajaxUrl, { action: 'big_geo_run_audit', nonce: bigGeo.nonce }, function(res) {
            var html = '<table class="big-geo-audit-table"><thead><tr><th>Bot</th><th>Label</th><th>Status</th></tr></thead><tbody>';
            $.each(res.data.bots, function(i, bot) {
                var statusClass = bot.status === 'allowed' ? 'big-geo-status-allowed' : 'big-geo-status-blocked';
                html += '<tr><td>' + bot.bot + '</td><td>' + bot.label + '</td><td class="' + statusClass + '">' + bot.status.toUpperCase() + '</td></tr>';
            });
            html += '</tbody></table>';
            if (!res.data.all_allowed) {
                html += '<p><button type="button" class="button button-primary" id="big-geo-apply-fix">Auto-Fix (Apply Allow Rules)</button></p>';
            }
            $('#big-geo-audit-results').html(html);
            btn.text('Run Audit Now');
        });
    });
    $(document).on('click', '#big-geo-apply-fix', function() {
        $.post(bigGeo.ajaxUrl, { action: 'big_geo_fix_robots', nonce: bigGeo.nonce }, function(res) {
            alert(res.data.message);
            $('#big-geo-run-audit').click();
        });
    });
});
