<?php
/*
Plugin Name: Nextcloud Folder Viewer
Description: Zeigt Ordnerstrukturen von Nextcloud an
Version: 1.0
Author: Joachim Happel
*/

if (!defined('ABSPATH')) exit;

add_shortcode('nextcloud_folder', 'nextcloud_folder_shortcode');

function nextcloud_folder_enqueue_scripts() {
    wp_enqueue_style('nextcloud-folder-viewer-style', plugin_dir_url(__FILE__) . 'css/nextcloud-folder-viewer.css', array(), '1.0');
    wp_enqueue_script('nextcloud-folder-viewer', plugin_dir_url(__FILE__) . 'js/nextcloud-folder-viewer.js', array('jquery'), '1.0', true);
    wp_localize_script('nextcloud-folder-viewer', 'nextcloudAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'nextcloud_folder_enqueue_scripts');

function nextcloud_folder_shortcode($atts) {
    $atts = shortcode_atts(array(
        'url' => '',
    ), $atts, 'nextcloud_folder');

    return '<div id="folder-tree" data-url="' . esc_attr($atts['url']) . '"></div>';
}

add_action('wp_ajax_get_nextcloud_folder', 'get_nextcloud_folder');
add_action('wp_ajax_nopriv_get_nextcloud_folder', 'get_nextcloud_folder');

function get_nextcloud_folder() {
    $url = $_POST['url'];
    $path = isset($_POST['path']) ? $_POST['path'] : '';

    $parsed_url = parse_url($url);
    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/public.php/webdav/';
    $share_token = basename($parsed_url['path']);

    $request_url = $base_url . $path;

    $args = array(
        'method' => 'PROPFIND',
        'headers' => array(
            'Authorization' => 'Bearer ' . $share_token,
            'Depth' => '1'
        )
    );

    $response = wp_remote_request($request_url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    } else {
        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);

        $items = array();
        foreach ($xml->response as $item) {
            if ((string)$item->href !== $path) {
                $href = (string)$item->href;
                $name = urldecode(basename($href));
                $is_folder = !empty($item->propstat->prop->resourcetype->collection);

                $items[] = array(
                    'name' => $name,
                    'isFolder' => $is_folder,
                    'path' => $href
                );
            }
        }

        wp_send_json_success($items);
    }

    wp_die();
}
