define(['jquery'], function($) {
    const $document = $(document);

    $document.on('click', '.translated-link-open', function(event){
        event.preventDefault();
        $document.find('.translated-link-close').trigger('click');
        let $this = $(this),
            url   = $this.attr('href'),
            uid   = $this.attr('data-uid');

        $this.parents('tr').find('.loading-animation').show();

        $.ajax({
            url: url
        }).done(function (data) {
            let $content = $(data);
            $this.parents('tr').after('<tr id="translation-' + uid+ '"><td colspan="5">' + $content.find('.return').html() + '</td></tr>');
            $this.parents('tr').find('.loading-animation').hide();
            $this.hide();
            $this.parent().find('.translated-link-close').show()
        })
    });
    $document.on('click', '.translated-link-close', function(event){
        event.preventDefault();
        let $this = $(this),
            url   = $this.attr('href'),
            uid   = $this.attr('data-uid');

        $this.hide();
        $this.parent().find('.translated-link-open').show();
        $('#translation-' + uid).remove();
    });

    $document.on('submit', '.translation-form', function (event) {
        event.preventDefault();
        let $form = $(this),
            data  = $form.serializeArray(),
            url   = $form.attr('action'),
            uid   = $form.attr('data-uid');

        $document.find('#entry-' + uid).find('.loading-animation').show();

        $.post({
            url: url,
            data: data,
        }).done(function (response) {
            $document.find('#translation-' + uid).find('td').html($(response).find('.return').html());
            $document.find('#entry-' + uid).find('.loading-animation').hide();
        })
    });
});
