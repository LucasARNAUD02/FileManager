$(function () {

    // enlever les form group dans les formulaires car ca pete les modal

    $('form[name=rename_f], form[name=rename], form[name=delete_f]').find('div[class=form-group]').removeAttr('class');

    var $renameModal = $('#js-confirm-rename');
    var $deleteModal = $('#js-confirm-delete');
    var $displayImageModal = $('#js-display-image');
    var $displayPdfModal = $('#js-display-pdf');

    var callback = function (key, opt) {
        switch (key) {
            case 'edit':
                var $renameModalButton = opt.$trigger.find(".js-rename-modal")
                renameFile($renameModalButton)
                $renameModal.modal("show");
                break;
            case 'delete':
                var $deleteModalButton = opt.$trigger.find(".js-delete-modal")
                deleteFile($deleteModalButton)
                $deleteModal.modal("show");
                break;
            case 'download':
                var $downloadButton = opt.$trigger.find(".js-download")
                downloadFile($downloadButton)
                break;
            case 'preview-image':
                var $previewModalButton = opt.$trigger.find(".js-open-modal");
                previewFile($previewModalButton)
                $displayImageModal.modal("show");
                break;

            case 'preview-pdf':
                var $previewModalButton = opt.$trigger.find(".js-open-modal");
                previewFile($previewModalButton)
                $displayPdfModal.modal("show");
                break;
        }
    };

    $.contextMenu({
        selector: '.file',
        callback: callback,
        items: {
            "delete": {name: deleteMessage, icon: "far fa-trash-alt"},
            "edit": {name: renameMessage, icon: "far fa-edit"},
            "download": {name: downloadMessage, icon: "fas fa-download"},
        }
    });

    $.contextMenu({
        selector: '.pdf',
        callback: callback,
        items: {
            "delete": {name: deleteMessage, icon: "far fa-trash-alt"},
            "edit": {name: renameMessage, icon: "far fa-edit"},
            "download": {name: downloadMessage, icon: "fas fa-download"},
            "preview-pdf": {name: previewMessage, icon: "fas fa-eye"},
        }
    });

    $.contextMenu({
        selector: '.img',
        callback: callback,
        items: {
            "delete": {name: deleteMessage, icon: "far fa-trash-alt"},
            "edit": {name: renameMessage, icon: "far fa-edit"},
            "download": {name: downloadMessage, icon: "fas fa-download"},
            "preview-image": {name: previewMessage, icon: "fas fa-eye"},
        }
    });

    $.contextMenu({
        selector: '.dir',
        callback: callback,
        items: {
            "delete": {name: deleteMessage, icon: "far fa-trash-alt"},
            "edit": {name: renameMessage, icon: "far fa-edit"},
        }
    });

    function renameFile($renameModalButton) {
        $('#rename_f_name').val($renameModalButton.data('name'));
        $('#rename_f_extension').val($renameModalButton.data('extension'));
        $renameModal.find('form').attr('action', $renameModalButton.data('href'))
    }

    function deleteFile($deleteModalButton) {
        $('#js-confirm-delete').find('form').attr('action', $deleteModalButton.data('href'));
    }

    function previewFile($previewModalButton) {

        var href = addParameterToURL($previewModalButton.data('href'), 'time=' + new Date().getTime());

        let target = $previewModalButton.data('bs-target');

        if (target === "#js-display-image") {

            $(target).find('img').attr('src', href);

        } else {

            $(target).find('.modal-body').html(
                `
                 <object type=""
                            data="${href}"
                            width="750"
                            height="600">
                    </object>
                `
            );
        }
    }

    function addParameterToURL(_url, param) {
        _url += (_url.split('?')[1] ? '&' : '?') + param;
        return _url;
    }

    function downloadFile($downloadButton) {
        $downloadButton[0].click();
    }

    function initTree(treedata) {

        $('#tree').jstree({
            'core': {
                'data': treedata,
                "check_callback": true
            }
        }).bind("changed.jstree", function (e, data) {
            if (data.node) {
                document.location = data.node.a_attr.href;
            }
        });
    }

    if (tree === true) {

        // sticky kit
        $("#tree-block").stick_in_parent();

        initTree(treedata);
    }
    $(document)
        // checkbox select all
        .on('click', '#select-all', function () {
            var checkboxes = $('#form-multiple-delete').find(':checkbox')
            if ($(this).is(':checked')) {
                checkboxes.prop('checked', true);
            } else {
                checkboxes.prop('checked', false);
            }
        })
        // delete modal buttons
        .on('click', '.js-delete-modal', function () {
                deleteFile($(this));
            }
        )
        // preview modal buttons
        .on('click', '.js-open-modal', function () {
                previewFile($(this));
            }
        )
        // rename modal buttons
        .on('click', '.js-rename-modal', function () {
                renameFile($(this));
            }
        )
        // multiple delete modal button
        .on('click', '#js-delete-multiple-modal', function () {
            var $multipleDelete = $('#form-multiple-delete').serialize();
            if ($multipleDelete) {
                var href = urldelete + '&' + $multipleDelete;
                $('#js-confirm-delete').find('form').attr('action', href);
            }
        })
        // checkbox
        .on('click', '#form-multiple-delete :checkbox', function () {
            var $jsDeleteMultipleModal = $('#js-delete-multiple-modal');
            if ($("input[type=checkbox][class=form-check-input]").is(':checked')) {
                $jsDeleteMultipleModal.removeClass('link-disabled');
            } else {
                $jsDeleteMultipleModal.addClass('link-disabled');
            }
        });

    // preselected
    $renameModal.on('shown.bs.modal', function () {
        $('#form_name').select().mouseup(function () {
            $('#form_name').unbind("mouseup");
            return false;
        });
    });
    $('#addFolder').on('shown.bs.modal', function () {
        $('#rename_name').select().mouseup(function () {
            $('#rename_name').unbind("mouseup");
            return false;
        });
    });

    // file upload
    $('#fileupload').fileupload({

        dataType: 'json',
        processQueue: false,
        dropZone: $('#dropzone')

    }).on('fileuploaddone', function (e, data) {

        $.each(data.result.files, function (index, file) {

            const fileName = file.name;

            if (file.url) {
                displayToast("success", fileName + ' ' + successMessage, 3000);
                // Ajax update view
                $.ajax({
                    dataType: "json",
                    url: url,
                    type: 'GET'
                }).done(function (data) {
                    // update file list
                    $('#form-multiple-delete').html(data.data);

                    lazy();

                    if (tree === true) {
                        $('#tree').data('jstree', false).empty();
                        initTree(data.treeData);
                    }

                    $('#select-all').prop('checked', false);
                    $('#js-delete-multiple-modal').addClass('link-disabled');

                }).fail(function (jqXHR, textStatus, errorThrown) {
                    displayToast("error", "Une erreur est survenue, essayez de recharger la page (CTRL + SHIFT + R).", 3000);
                });

            } else if (file.error) {
                displayToast("error", `${fileName} ${file.error}`);
            }
        });

        $('.dropdown-menu').removeClass('show');

    }).on('fileuploadfail', function (e, data) {

        $.each(data.files, function (index, file) {

            let message = `Le fichier ${file.name} n'a pas pu être ajouté.`;

            if (file.size > 8000000) {
                message = `Le fichier ${file.name} est trop volumineux pour être ajouté, sa taille ne doit pas dépasser 8 mo.`;
            }

            displayToast("error", message, 3000);
        });


    }).on('fileuploadprogressall', function (e, data) {

        if (e.isDefaultPrevented()) {
            return false;
        }
        var progress = Math.floor((data.loaded / data.total) * 100);

        $('.progress-bar')
            .removeClass("notransition")
            .attr('aria-valuenow', progress)
            .css('width', progress + '%');

    }).on('fileuploadstop', function (e) {

        if (e.isDefaultPrevented()) {
            return false;
        }

        $('.progress-bar')
            .addClass("notransition")
            .attr('aria-valuenow', 0)
            .css('width', 0 + '%');
    });

    function lazy() {
        $('.lazy').Lazy({});
    }

    lazy();

    $('#search').on("input", function () {

        var value = removeAccent($(this).val().toLowerCase().trim());

        $('#form-multiple-delete .file-wrapper').filter(function () {
            $(this).toggle(removeAccent($(this).text().toLowerCase()).indexOf(value) > -1);
        });
    });

});