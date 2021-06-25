$(function() {

    // translations etc. are defined in process module
    var moduleConfig = config.ProcessSnippets;

    // init WireTabs
    if ($('.WireTab').length) {
        $('.WireTab').each(function() {
            $(this).parent('.Inputfields').addClass('WireTabsParent');
        });
        $('.WireTabsParent').each(function() {
            $(this).WireTabs({
                items: $(this).find('.WireTab')
            });
        });
    }

    // init CodeMirror
    if ($('#textarea-snippet[data-codemirror=1]').length) {
        var snippetCodeMirrorConfig = {
            lineNumbers: true,
            mode: "htmlmixed"
        };
        var $snippet = $('#textarea-snippet');
        if ($snippet.data('codemirror-theme')) {
            snippetCodeMirrorConfig.theme = $snippet.data('codemirror-theme')
        }
        var snippetCodeMirror = CodeMirror.fromTextArea(document.getElementById('textarea-snippet'), snippetCodeMirrorConfig);
    }

    // enable or disable snippets
    $('.snippets').on('click', '.snippet-toggle', function() {
        var id = $(this).attr('value');
        var enabled = $(this).is(':checked') ? 1 : 0;
        var $label = $(this).next('label');
        var $tr = $(this).parents('tr:first')
        var $table = $tr.find('table:first');
        $label.find('span').hide();
        $.post(moduleConfig.processPage+'toggle', { id: id, enabled: enabled }, function(data) {
            $tr.effect("highlight", {}, 500);
            if (data) {
                $label.find('span.enabled').fadeIn('fast');
                $table.removeClass('disabled').addClass('enabled');
            } else {
                $label.find('span.disabled').fadeIn('fast');
                $table.removeClass('enabed').addClass('disabled');
            }
        });
    });

});
