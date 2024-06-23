jQuery(document).ready(function($) {
    const container = $('#folder-tree');
    const url = container.data('url');

    function cleanFilePath(filePath) {
        return filePath.replace(/^\/public\.php\/webdav\//, '');
    }

    function generateViewLink(viewToken) {
        return `${nextcloudAjax.ajaxurl}?action=view_nextcloud_file&token=${viewToken}`;
    }

    async function getFolderStructure(path = '') {
        console.log('Getting folder structure for path:', path);
        try {
            const response = await $.ajax({
                url: nextcloudAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_nextcloud_folder',
                    url: url,
                    path: cleanFilePath(path),
                }
            });

            console.log('AJAX response:', response);

            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            console.error('Error in getFolderStructure:', error);
            throw error;
        }
    }

    function createTreeView(container, items, path = '') {
        const ul = $('<ul>').addClass('nextcloud-folder-list');
        container.append(ul);

        items.forEach(item => {
            // Skip the item if it's the same as the current path (except for root)
            if (item.path === path && path !== '/public.php/webdav/') {
                return;
            }

            const li = $('<li>').addClass('nextcloud-folder-item');
            ul.append(li);
            const folder = $('<div>').addClass('nextcloud-folder-handle');
            const icon = $('<span>').addClass('nextcloud-folder-icon');
            const content = $('<span>').addClass('nextcloud-folder-content').text(item.name);
            folder.append(icon, content);

            if (item.isFolder) {
                icon.addClass('folder-icon');
                li.addClass('folder');
                const folderContent = $('<div>').addClass('nextcloud-subfolder-content').hide();
                li.append(folder, folderContent);

                if (item.root===true) {
                    li.addClass('root-folder');
                    return
                }else{
                    folderContent.hide();
                }

                folder.on('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).parent('.nextcloud-folder-handle');
                    $(this).toggleClass('open');

                    if (folderContent.is(':empty')) {
                        try {
                            const subItems = await getFolderStructure(item.path);
                            createTreeView(folderContent, subItems, item.path);
                        } catch (error) {
                            console.error('Error loading subfolder:', error);
                            folderContent.html('<p>Error loading folder contents</p>');
                        }
                    }
                    folderContent.slideToggle(200);
                });
            } else {
                icon.addClass('file-icon');
                const fileLink = $('<a>')
                    .append(icon, content)
                    .attr('href', generateViewLink(item.viewToken))
                    .attr('target', '_blank');
                li.append(fileLink);
                li.addClass('file');
            }
        });
    }

    async function initTreeView() {
        try {
            console.log('Initializing tree view');
            const rootItems = await getFolderStructure('');
            console.log('Root items:', rootItems);
            container.empty();
            createTreeView(container, rootItems, '');
        } catch (error) {
            console.error('Error initializing tree view:', error);
            container.html('<p>Error loading folder structure</p>');
        }
    }

    initTreeView();
});
