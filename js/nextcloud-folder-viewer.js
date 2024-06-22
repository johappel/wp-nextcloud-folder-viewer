jQuery(document).ready(function($) {
    const container = $('#folder-tree');
    const url = container.data('url');

    async function getFolderStructure(path = '') {
        const response = await $.ajax({
            url: nextcloudAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_nextcloud_folder',
                url: url,
                path: path
            }
        });

        if (response.success) {
            return response.data;
        } else {
            console.error('Error fetching folder structure:', response.data);
            return [];
        }
    }

    function createTreeView(container, items, path = '') {
        const ul = $('<ul>').addClass('nextcloud-folder-list');
        container.append(ul);

        items.forEach(item => {
            const li = $('<li>').addClass('nextcloud-folder-item');
            ul.append(li);

            const icon = $('<span>').addClass('nextcloud-folder-icon');
            const content = $('<span>').addClass('nextcloud-folder-content').text(item.name);

            if (item.isFolder) {
                icon.addClass('folder-icon');
                const folderContent = $('<div>').addClass('nextcloud-subfolder-content');
                li.append(icon, content, folderContent);

                li.on('click', async (e) => {
                    e.stopPropagation();
                    li.toggleClass('open');
                    if (folderContent.children().length === 0) {
                        const subItems = await getFolderStructure(item.path);
                        createTreeView(folderContent, subItems, item.path);
                    }
                });
            } else {
                icon.addClass('file-icon');
                const fileLink = $('<a>')
                    .append(icon, content)
                    .attr('href', url.replace('/s/', '/s/apps/files/?dir=/') + item.path)
                    .attr('target', '_blank');
                li.append(fileLink);
            }
        });
    }

    async function initTreeView() {
        const rootItems = await getFolderStructure();
        createTreeView(container, rootItems);
    }

    initTreeView();
});
