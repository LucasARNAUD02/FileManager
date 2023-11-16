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
    const regexFichiers = /[\/:*?"<>|]/;

    let validField = true;
    let invalidInput;

    $('#rename_f_name, #rename_name').on('keyup', function(e){

        let val = $(this).val();

        if(regexFichiers.test(val)){

            if(e.keyCode !== 8){
                if($('#regex_error').length === 0){
                    $(this).after(`<p class="text-danger mt-2 mb-0" id="regex_error">Les caractères suivants sont interdits : /\:*?"<>|</p>`);
                }
                $(this).addClass("is-invalid");
                $(this).removeClass("is-valid");
                shakeInputAnimation(this.closest('.modal'));
            }

            invalidInput = this;

            validField = false;

        } else {
            validField = true;
            $(this).removeClass("is-invalid");
            $(this).addClass("is-valid");
            $('#regex_error').remove();
        }

        $(this).closest('form').submit(function(e){

            if(!validField){
                shakeInputAnimation(invalidInput.closest('.modal'));
                e.preventDefault();
            }
        });
    });

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

    $(document).on('click', '.lazy, .pdf i, .video i, .audio i', function () {
        $(this).closest('.file-wrapper').find('.js-open-modal').click();
    });

    function shakeInputAnimation(element){
        element.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-10px)' },
            { transform: 'translateX(10px)' },
            { transform: 'translateX(-10px)' },
            { transform: 'translateX(10px)' },
            { transform: 'translateX(0)' }
        ], {
            duration: 500,
            easing: 'ease'
        });
    }

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
        selector: '.pdf, .img, .video, .audio', callback: callback, items: getContextMenuOptions('preview')
    });

    if (permissionGererCloudCommun) {

        $.contextMenu({
            selector: '.dir', callback: callback, items: {
                "edit": {name: 'Renommer', icon: "far fa-edit"},
                "delete": {name: 'Supprimer', icon: "far fa-trash-alt"}
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
        const target = previewModalButton.data('bs-target');
        const modal = new bootstrap.Modal($(target));
        const modalPdfBody = $(target).find('.pdf-body');
        const modalVideoBody = $(target).find('.video-body');
        const modalAudioBody = $(target).find('.audio-body');
        const modalImgBody = $(target).find('.img-body');
        const loader = `<div class="spinner-border text-white mt-5 p-5" role="status"><span class="visually-hidden">Chargement...</span></div>`;

        switch (target) {
            case "#js-display-image":
                const img = $('<img>', {
                    'src': href,
                    'id': 'preview_img',
                    'class': 'img-fluid'
                });
                modalImgBody.html(img);
                modalImgBody.append(loader);
                modal.show();
                img.on('load', function () {
                    modalImgBody.find('.spinner-border').remove();
                });
                break;

            case "#js-display-pdf":

                const indexConf = href.indexOf('?conf');
                const confParameters = href.substring(indexConf);

                const newHref = '/bibliotheque/file/' + previewModalButton.data('filename') + confParameters;

                modalPdfBody.html(`<object type="application/pdf" data="${newHref}" width="100%" height="600"></object>`);
                modalPdfBody.find('object').parent().prepend(loader);
                modal.show();
                modalPdfBody.find('object').on('load', function () {
                    modalPdfBody.find('.spinner-border').remove();
                });
                break;

            case "#js-display-video":
                const video = $(`
                <video width="100%" controls="controls">
                    <source src="${href}"/>
                    <object data="${href}" width="100%">
                        <embed src="${href}" width="100%">
                            <p class="text-muted">Votre navigateur ne supporte pas la lecture de vidéos.</p>
                        </embed>
                    </object>
                </video>`);
                modalVideoBody.html(video);
                modal.show();
                break;
            case "#js-display-audio":
                const audio = $(`
                <audio controls>
                  <source src="${href}">
                Votre navigateur ne supporte pas la lecture de fichier audio.
                </audio>`);
                modalAudioBody.html(audio);
                modal.show();
                break;
        }

        $('.btn-close-modal').on('click', function (e) {
            modalVideoBody.add(modalPdfBody).add(modalImgBody).add(modalAudioBody).empty();
            $(".modal-backdrop").remove()
            document.body.style.overflow = 'auto';
        });
    }


    function addParameterToURL(_url, param) {
        _url += (_url.split('?')[1] ? '&' : '?') + param;
        return _url;
    }

    function initTree(treedata) {

        $tree.jstree({
            'core': {
                'data': treedata,
                "check_callback": true,
                "themes": {"name": "simple-cb",},
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

    }).on('fileuploadadd', function (e, data) {

        let fileName = data.files[0].name.split('\\').pop();
        let fileExtension = fileName.split('.').pop();
        let fileNameWithoutExtension = fileName.substring(0, fileName.lastIndexOf('.'));

        let newFilename = fileNameWithoutExtension.replace(regexFichiers, '');

        data.files[0].uploadName = newFilename + '.' + fileExtension;

    }).on('fileuploaddone', function (e, data) {

        const addedFiles = data.result.files;

        $.each(addedFiles, function (index, file) {

            const fileName = file.name;

            if (file.url) {

                $.ajax({
                    dataType: "json", url: url, type: 'GET'
                }).done(function (data) {

                    formMultipleDelete.html(data.data);
                    window.parent.postMessage({ type: 'fileUpload', data: 'Nouveaux fichiers ajoutés.' }, '*');

                    lazy();

                    if (tree) {
                        $tree.data('jstree', false).empty();
                        initTree(data.treeData);
                    }

                    selectAll.prop('checked', false);
                    jsDeleteMultipleModal.addClass('link-disabled');

                    displayToast("success", `Le fichier ${fileName} a été ajouté.`, 3000, {transition: "pinItUp"});

                }).fail(function () {
                    displayToast("error", "Une erreur est survenue, essayez de recharger la page (CTRL + SHIFT + R).", 3000, {transition: "pinItUp"});
                });

            } else if (file.error) {
                displayToast("error", `Une erreur est survenue lors de l'ajout du fichier suivant : ${fileName}.`, 3000, {transition: "pinItUp"});
            }
        });

    }).on('fileuploadfail', function (e, data) {

        $.each(data.files, function (index, file) {

            let message = `Le fichier ${file.name} n'a pas pu être ajouté.`;

            if (file.size > 8388608) {
                message = `Le fichier ${file.name} est trop volumineux pour être ajouté, sa taille ne doit pas dépasser 8 mo.`;
            }

            displayToast("error", message, 3000, {transition: "pinItUp"});
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
            effect: 'fadeIn',
            effectTime: 125,
            afterLoad: function (element) {
                $(element).removeClass("lazy");
            },
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
                            <td class="text-center text-muted" colspan="7">Aucun résultat.. 👻</td>
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

        if (permissionGererCloudCommun) {
            options = {
                "edit": {name: 'Renommer', icon: 'far fa-edit'},
                "delete": {name: 'Supprimer', icon: "far fa-trash-alt"}
            };
        }

        switch (type) {
            case 'file':
                options = {
                    "download": {name: 'Télécharger', icon: "fas fa-download"},
                    ...options
                };
                break;
            case 'preview':
                options = {
                    "preview": {name: 'Prévisualiser', icon: "fas fa-eye"},
                    "download": {name: 'Télécharger', icon: "fas fa-download"},
                    ...options
                };
                break;
        }

        return options;
    }

});