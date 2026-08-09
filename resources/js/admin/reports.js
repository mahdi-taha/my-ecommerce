document.querySelectorAll('.report-entity-select').forEach(element => {
    const select = $(element);

    select.select2({
        width: '100%',
        placeholder: element.dataset.placeholder,
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: element.dataset.lookupUrl,
            dataType: 'json',
            delay: 200,
            data: params => ({ q: params.term || '' }),
            processResults: response => response,
        },
    });
});
