$(function () {

    const treeDiv = $('#tree_div');
    const arboText = $('#arbo-text');
    const selectAll = $('#select-all');
    const arbo = $('#arbo');
    const renameModal = $('#js-confirm-rename');
    const deleteModal = $('#js-confirm-delete');
    const $tree = $('#tree');
    const formMultipleDelete = $('#form-multiple-delete');
    const jsDeleteMultipleModal = $('#js-delete-multiple-modal');
    const fileUpload = $('#fileupload');
    const search = $('#search');

    $.cookie('last_route', urlLastRoute, {path: '/'});

    $("form").filter('[name=rename_f], [name=rename], [name=delete_f]').find('.form-group').removeClass();

    arbo.click(function () {

        if (!treeDiv.is(':visible')) {

            treeDiv.removeClass('d-none').hide().show(200, function () {
                $.cookie('tree_visible', true);
                arboText.text("Masquer");
            });

        } else {

            treeDiv.hide(200, function () {
                $.cookie('tree_visible', false);
                arboText.text("Afficher");
            });
        }
    });

    $(document).on('click', '.lazy, .pdf i, .video i', function () {
        $(this).closest('.file-wrapper').find('.js-open-modal').click();
    });

    $(window).resize(function () {

        let treeVisible = treeDiv.is(':visible');
        let windowWidth = $(window).width();

        if (treeVisible && windowWidth <= 1186) {

            treeDiv.hide(200, function () {
                arboText.text("Afficher");
            });

        } else if (!treeVisible && windowWidth > 1186 && $.cookie('tree_visible') !== "false") {

            treeDiv.removeClass('d-none').hide().show(200, function () {
                arboText.text("Masquer");
            });

        }
    });

    let callback = function (key, opt) {

        const trigger = opt.$trigger;

        switch (key) {
            case 'edit':
                let renameModalButton = trigger.find(".js-rename-modal")
                renameFile(renameModalButton)
                renameModal.modal("show");
                break;
            case 'delete':
                let deleteModalButton = trigger.find(".js-delete-modal")
                deleteFile(deleteModalButton)
                deleteModal.modal("show");
                break;
            case 'download':
                trigger.find(".js-download")[0].click();
                break;
            case 'preview':
                let previewModalButton = trigger.find(".js-open-modal");
                previewFile(previewModalButton);
                break;
        }
    };

    $.contextMenu({
        selector: '.file', callback: callback, items: getContextMenuOptions('file')
    });

    $.contextMenu({
        selector: '.pdf, .img, .video', callback: callback, items: getContextMenuOptions('preview')
    });

    if (isAdministratif) {

        $.contextMenu({
            selector: '.dir', callback: callback, items: {
                "edit": {name: renameMessage, icon: "far fa-edit"},
                "delete": {name: deleteMessage, icon: "far fa-trash-alt"}
            }
        });
    }

    function renameFile(renameModalButton) {
        $('#rename_f_name').val(renameModalButton.data('name'));
        $('#rename_f_extension').val(renameModalButton.data('extension'));
        renameModal.find('form').attr('action', renameModalButton.data('href'));
    }

    function deleteFile(deleteModalButton) {
        deleteModal.find('form').attr('action', deleteModalButton.data('href'));
    }

    function previewFile(previewModalButton) {

        const href = addParameterToURL(previewModalButton.data('href'), 'time=' + new Date().getTime());
        const target = previewModalButton.data('target');
        const modal = new bootstrap.Modal($(target));
        const modalBody = $(target).find('.modal-body');

        switch (target) {

            case "#js-display-image":

                modalBody.html(`<img src="${href}" id="preview_img" class="img-fluid img-thumbnail">`);

                modalBody.find('img').on('load', function () {
                    modal.show();
                });

                break;

            case "#js-display-pdf":

                modalBody.html(`
                 <object type=""
                            data="${href}"
                            width="100%"
                            height="600">
                    </object>
                `);

                modal.show();
                break;

            case "#js-display-video":

                modalBody.html(`
                 <video width="100%" height="600" controls="controls">
                  <source src="${href}"/>                  
                    <object data="${href}" width="100%" height="600">
                        <embed src="${href}" width="100%" height="600">
                            <p class="text-muted">Votre navigateur ne supporte pas la lecture de vid??os.</p>
                        </embed>
                    </object>
                </video> 
                `);

                modal.show();
                break;
        }

        $(target).on('hidden.bs.modal', function () {
            modalBody.empty();
        });

    }

    function addParameterToURL(_url, param) {
        _url += (_url.split('?')[1] ? '&' : '?') + param;
        return _url;
    }

    function initTree(treedata) {

        $tree.jstree({
            'core': {
                'data': treedata, "check_callback": true
            }
        }).bind("changed.jstree", function (e, data) {
            if (data.node) {
                document.location = data.node.a_attr.href;
            }
        });
    }

    if (tree) {

        // sticky kit
        $("#tree-block").stick_in_parent();

        initTree(treedata);
    }

    $(document)

        .on('click', '#select-all', function () {

            let checkboxes = formMultipleDelete.find(':checkbox:enabled');
            checkboxes.prop('checked', $(this).is(':checked'));
        })

        .on('click', '.js-delete-modal', function () {
            deleteFile($(this));
        })

        .on('click', '.js-open-modal', function () {
            previewFile($(this));
        })

        .on('click', '.js-rename-modal', function () {
            renameFile($(this));
        })

        .on('click', '#js-delete-multiple-modal', function () {

            let multipleDelete = formMultipleDelete.serialize();

            if (multipleDelete) {
                deleteModal.find('form').attr('action', urldelete + '&' + multipleDelete);
            }
        })

        .on('click', '#form-multiple-delete :checkbox', function () {

            if ($('#form-multiple-delete tbody :checkbox:checked:enabled').length > 0) {
                jsDeleteMultipleModal.removeClass('link-disabled');
            } else {
                jsDeleteMultipleModal.addClass('link-disabled');
            }

        });

    renameModal.on('shown.bs.modal', function () {
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

    fileUpload.fileupload({

        dataType: 'json',
        processQueue: false,
        dropZone: $('#dropzone'),
        pasteZone: $('#dropzone'),

    }).on('fileuploaddone', function (e, data) {

        const addedFiles = data.result.files;

        $.each(addedFiles, function (index, file) {

            const fileName = file.name;

            if (file.url) {

                displayToast("success", `Le fichier ${fileName} a ??t?? ajout??.`, 3000);

                $.ajax({
                    dataType: "json", url: url, type: 'GET'
                }).done(function (data) {

                    formMultipleDelete.html(data.data);

                    lazy();

                    if (tree) {
                        $tree.data('jstree', false).empty();
                        initTree(data.treeData);
                    }

                    selectAll.prop('checked', false);
                    jsDeleteMultipleModal.addClass('link-disabled');

                }).fail(function () {
                    displayToast("error", "Une erreur est survenue, essayez de recharger la page (CTRL + SHIFT + R).", 3000);
                });

            } else if (file.error) {
                displayToast("error", `Une erreur est survenue lors de l'ajout du fichier suivant : ${fileName}.`, 3000);
            }
        });

    }).on('fileuploadfail', function (e, data) {

        $.each(data.files, function (index, file) {

            let message = `Le fichier ${file.name} n'a pas pu ??tre ajout??.`;

            if (file.size > 8388608) {
                message = `Le fichier ${file.name} est trop volumineux pour ??tre ajout??, sa taille ne doit pas d??passer 8 mo.`;
            }

            displayToast("error", message, 3000);
        });

    }).on('fileuploadprogressall', function (e, data) {

        if (e.isDefaultPrevented()) {
            return false;
        }

        const progress = Math.floor((data.loaded / data.total) * 100);

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
        $('.lazy').Lazy({
            effect: 'fadeIn', effectTime: 125,
        });
    }

    lazy();

    search.on("input", function (event) {

        const value = removeAccent(event.target.value.toLowerCase().trim());
        const trNoResult = $('#tr-no-result');

        $('.searchable').filter(function () {
            $(this).closest('tr').toggle(removeAccent($(this).text().toLowerCase()).indexOf(value) > -1);
        });

        if ($('.file-wrapper:visible').length === 0) {

            selectAll.prop('disabled', true);

            if (trNoResult.length === 0) {

                $('#form-multiple-delete tbody').append(`
                        <tr id="tr-no-result">
                            <td class="text-center text-muted" colspan="7">Aucun r??sultat.. ????</td>
                        </tr>`);

                $('#tr-no-result').closest('.table').toggleClass('table-striped');
            }

        } else {

            trNoResult.closest('table').toggleClass('table-striped');
            trNoResult.remove();
            selectAll.prop('disabled', false);
        }

    });

    function getContextMenuOptions(type) {

        let options;

        if (isAdministratif) {
            options = {
                "edit": {name: renameMessage, icon: "far fa-edit"},
                "delete": {name: deleteMessage, icon: "far fa-trash-alt"}
            };
        }

        switch (type) {
            case 'file':
                options = {
                    "download": {name: downloadMessage, icon: "fas fa-download"},
                    ...options
                };
                break;
            case 'preview':
                options = {
                    "preview": {name: previewMessage, icon: "fas fa-eye"},
                    "download": {name: downloadMessage, icon: "fas fa-download"},
                    ...options
                };
                break;
        }

        return options;
    }

});