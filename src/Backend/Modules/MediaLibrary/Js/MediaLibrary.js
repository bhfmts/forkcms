/**
 * Interaction for the media module
 * global: jsBackend
 */
jsBackend.mediaLibrary =
{
    init: function()
    {
        // load the tree
        jsBackend.mediaLibrary.tree.init();

        // add some extra controls
        jsBackend.mediaLibrary.controls.init();

        // adds functionalities for library
        jsBackend.mediaLibrary.library.init();
    }
};

/**
 * Add some extra controls
 * global: jsBackend
 */
jsBackend.mediaLibrary.controls =
{
    init: function()
    {
        // save and edit
        $('#saveAndEdit').on('click', function()
        {
            $('form').append('<input type="hidden" name="after_save" value="EditMediaItem" />').submit();
        });
    }
};

/**
 * All methods related to the library overview
 * global: jsBackend
 */
jsBackend.mediaLibrary.library =
{
    init: function()
    {
        // start or not
        if ($('#library').length == 0) {
            return false;
        }

        // init edit folder dialog
        jsBackend.mediaLibrary.library.addEditFolderDialog();

        // init mass action hidden input fields
        jsBackend.mediaLibrary.library.dataGrids();
    },

    /**
     * Add edit folder dialog
     */
    addEditFolderDialog : function()
    {
        var $editMediaFolderDialog = $('#editMediaFolderDialog');
        var $editMediaFolderSubmit = $('#editMediaFolderSubmit');

        // stop here
        if ($editMediaFolderDialog.length == 0) {
            return false;
        }

        $editMediaFolderSubmit.on('click', function(){
            // Update folder using ajax
            $.ajax({
                data: {
                    fork: { action: 'EditMediaFolder' },
                    folder_id: $('#mediaFolderId').val(),
                    name: $('#mediaFolderName').val()
                },
                success: function(json, textStatus) {
                    if (json.code != 200) {
                        // show error if needed
                        if (jsBackend.debug) {
                            alert(textStatus);
                        }

                        // show message
                        jsBackend.messages.error('success', textStatus);
                    } else {
                        // show message
                        jsBackend.messages.add('success', json.message);

                        // close dialog
                        $('#editFolderDialog').modal('close');

                        // reload document
                        window.location.reload('true');
                    }
                }
            });
        });
    },

    /**
     * Move audio to another folder or connect audio to a gallery
     */
    dataGrids : function()
    {
        if (window.location.hash == '') {
            // select first tab
            $('#library .nav-tabs a:first').tab('show');
        }

        // init data
        var dataGallery = {form_id : 'mediaAudio', value_id : 'galleryAudioId', name : 'gallery_id'};
        var dataFolder = {form_id : 'mediaAudio', value_id : 'folderAudioId', name : 'folder_id'};

        // add gallery_id to form
        $('#galleryAudioId').on('focus change', dataGallery, jsBackend.mediaLibrary.library.updateForm);

        // add folder_id to form
        $('#folderAudioId').on('focus change', dataFolder, jsBackend.mediaLibrary.library.updateForm);
    },

    /**
     * Adds an extra field to the form so we know which gallery_id or folder_id is selected
     */
    updateForm : function(e)
    {
        // get value
        var value = $('#' + e.data.value_id).val();

        // add field to form
        if ($('#' + e.data.form_id).find('#' + e.data.name).length == 0) {
            $('#' + e.data.form_id).append('<input id="' + e.data.name + '" name="' + e.data.name + '" type="hidden" value="' + value + '" />');
        // update existing field
        } else {
            $('#' + e.data.form_id).find('#' + e.data.name).val(value);
        }
    }
};

/**
 * All methods related to the tree
 * global: jsBackend
 * global: utils
 */
jsBackend.mediaLibrary.tree =
{
    pageID: null,
    // init, something like a constructor
    init: function()
    {
        if ($('#tree div').length === 0) {
            return false;
        }

        // add "treeHidden"-class on leafs that are hidden, only for browsers that don't support opacity
        if (!jQuery.support.opacity) {
            $('#tree ul li[rel="hidden"]').addClass('treeHidden');
        }

        // set the item selected
        if (jsBackend.data.get('MediaLibrary.openedFolderId')) {
            $('#folder-' + jsBackend.data.get('MediaLibrary.openedFolderId')).addClass('selected');
            jsBackend.mediaLibrary.tree.pageID = jsBackend.data.get('MediaLibrary.openedFolderId');
        };

        var openedIds = [];
        if (typeof jsBackend.mediaLibrary.tree.pageID != 'undefined') {
            // get parents
            var parents = $('#folder-'+ jsBackend.mediaLibrary.tree.pageID).parents('li');

            // init var
            openedIds = ['folder-'+ jsBackend.mediaLibrary.tree.pageID];

            // add parents
            for(var i = 0; i < parents.length; i++) {
                openedIds.push($(parents[i]).prop('id'));
            }
        }

        // add home if needed
        if (!utils.array.inArray('folder-1', openedIds)) {
            openedIds.push('folder-1');
        }

        var options = {
            ui: { theme_name: 'fork' },
            opened: openedIds,
            rules: {
                multiple: false,
                multitree: 'all',
                drag_copy: false
            },
            lang: { loading: utils.string.ucfirst(jsBackend.locale.lbl('Loading')) },
            callback: {
                beforemove: jsBackend.mediaLibrary.tree.beforeMove,
                onselect: jsBackend.mediaLibrary.tree.onSelect,
                onmove: jsBackend.mediaLibrary.tree.onMove
            },
            plugins: {
                cookie: { prefix: 'jstree_', types: { selected: false }, options: { path: '/' } }
            }
        };

        // create tree
        $('#tree div').tree(options);

        // layout fix for the tree
        $('.tree li.open').each(function() {
            // if the so-called open-element doesn't have any childs we should replace the open-class.
            if ($(this).find('ul').length === 0) {
                $(this).removeClass('open').addClass('leaf');
            }
        });
    },

    // before an item will be moved we have to do some checks
    beforeMove: function(node, refNode, type, tree)
    {
        // get pageID that has to be moved
        var currentPageID = $(node).prop('id').replace('folder-', '');
        if (typeof refNode == 'undefined') {
            parentPageID = 0;
        } else {
            parentPageID = $(refNode).prop('id').replace('folder-', '');
        }

        // init var
        var result = false;

        // make the call
        $.ajax({
            async: false, // important that this isn't asynchronous
            data: {
                fork: { action: 'GetMediaFolderInfo' },
                id: currentPageID
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                if (jsBackend.debug) {
                    alert(textStatus);
                }
                result = false;
            },
            success: function(json, textStatus) {
                if (json.code != 200) {
                    if (jsBackend.debug) {
                        alert(textStatus);
                    }
                    result = false;
                } else {
                    if (json.data.allow_move == 'Y') {
                        result = true;
                    }
                }
            }
        });

        // return
        return result;
    },

    // when an item is selected
    onSelect: function(node, tree)
    {
        // get current and new URL
        var currentPageURL = window.location.pathname + window.location.search;
        var newPageURL = $(node).find('a').prop('href');

        // only redirect if destination isn't the current one.
        if (typeof newPageURL != 'undefined' && newPageURL != currentPageURL) {
            window.location = newPageURL;
        }
    },

    // when an item is moved
    onMove: function(node, refNode, type, tree, rollback)
    {
        // get the tree
        tree = tree.container.data('tree');

        // get pageID that has to be moved
        var currentPageID = $(node).prop('id').replace('folder-', '');

        // get pageID wheron the page has been dropped
        var droppedOnPageID;
        if (typeof refNode == 'undefined') {
            droppedOnPageID = 0;
        } else {
            droppedOnPageID = $(refNode).prop('id').replace('folder-', '');
        }

        // make the call
        $.ajax({
            data: {
                fork: { action: 'MoveMediaFolder' },
                id: currentPageID,
                dropped_on: droppedOnPageID,
                type: type,
                tree: tree
            },
            success: function(json, textStatus) {
                if (json.code != 200) {
                    if (jsBackend.debug) {
                        alert(textStatus);
                    }

                    // show message
                    jsBackend.messages.add('danger', jsBackend.locale.err('CantBeMoved'));

                    // rollback
                    $.tree.rollback(rollback);
                } else {
                    // show message
                    jsBackend.messages.add('success', json.message);
                }
            }
        });
    }
};

/** global: jsBackend */
$(jsBackend.mediaLibrary.init);
