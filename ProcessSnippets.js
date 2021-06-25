$(function() {

    // get config settings from Process module
    var moduleConfig = config.ProcessSnippets;

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
    $('.snippets').on('click', '.snippets__toggle', function() {
        var $label = $(this).next('label');
        var $link = $(this).parents('tr:first').find('.snippets__link');
        $label.find('span').hide();
        $.post(moduleConfig.processPage + 'toggle', { id: $(this).attr('value'), enabled: $(this).is(':checked') ? 1 : 0 }, function(data) {
            if (data) {
                $label.find('span.enabled').fadeIn('fast');
                $link.removeClass('snippets__link--disabled').addClass('snippets__link--enabled');
                return;
            }
            $label.find('span.disabled').fadeIn('fast');
            $link.removeClass('snippets__link--enabed').addClass('snippets__link--disabled');
        });
    });

});
