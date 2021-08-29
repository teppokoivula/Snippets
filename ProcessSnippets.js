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
	var $csrf = $(this).parents('form:first').find('._post_token:first');
	var args = {
	    id: $(this).attr('value'),
	    enabled: $(this).is(':checked') ? 1 : 0
	};
	args[$csrf.attr('name')] = $csrf.attr('value');
        $label.find('span').hide();
        $.post(moduleConfig.processPage + 'toggle', args, function(data) {
            if (data) {
                $label.find('span.enabled').fadeIn('fast');
                $link.removeClass('snippets__link--disabled').addClass('snippets__link--enabled');
                return;
            }
            $label.find('span.disabled').fadeIn('fast');
            $link.removeClass('snippets__link--enabed').addClass('snippets__link--disabled');
        });
    });

    // make snippets table sortable
    $('.snippets tbody').sortable({
        axis: 'y',
        handle: '.snippets__sort',
        update: function(event, ui) {
            var $item = ui.item.find('.snippets__item:first');
            var $root = ui.item.parent();
	    var $csrf = ui.item.parents('form:first').find('._post_token:first');
            var sort = $.map($('.snippets__item'), function(item) {
                return parseInt($(item).attr('data-id'));
            });
            if (sort.length) {
		$item.addClass('snippets__item--sort');
		var args = {
		    sort: sort
		};
		args[$csrf.attr('name')] = $csrf.attr('value');
                $.post(moduleConfig.processPage + 'sort', args, function(data) {
                    data = JSON.parse(data);
                    if (data.error) {
                        alert(data.message);
                        return;
                    }
                    $item.removeClass('snippets__item--sort');
                });
            }
        }
    });
});
