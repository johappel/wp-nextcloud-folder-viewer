<?php
/*
Plugin Name: Nextcloud Folder Viewer
Description: Zeigt Ordnerstrukturen von Nextcloud an
Version: 0.1
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
add_action('wp_ajax_view_nextcloud_file', 'view_nextcloud_file');
add_action('wp_ajax_nopriv_view_nextcloud_file', 'view_nextcloud_file');

function view_nextcloud_file() {
    if (!isset($_GET['token'])) {
        wp_die('Missing token');
    }

    $data = get_file_view_data($_GET['token']);
    if (!$data) {
        wp_die('Invalid or expired token');
    }

    require_once plugin_dir_path(__FILE__) . 'class-nextcloud-file-viewer.php';

    $url = $data['url'];
    $path = $data['path'];
    $parsed_url = parse_url($url);
    $share_token = basename($parsed_url['path']);

    $viewer = new NextcloudFileViewer($url, $share_token);
    $viewer->viewFile($path);
}
function generate_file_view_token($url, $path) {
    $token = wp_generate_password(32, false);
    set_transient('nextcloud_view_' . $token, array('url' => $url, 'path' => $path), 5 * MINUTE_IN_SECONDS);
    return $token;
}

function get_file_view_data($token) {
    return get_transient('nextcloud_view_' . $token);
}

function get_nextcloud_folder(){

    $url = $_POST['url'];
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    error_log('get_nextcloud_folder URL: ' . $url. ' Path: ' . $path);
    if (!$url) {
        wp_send_json_error('Missing URL');
    }
    $parsed_url = parse_url($url);
    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/public.php/webdav/';
    $share_token = basename($parsed_url['path']);
    $token = base64_encode($share_token . ':');

    $request_url = $base_url . ltrim($path, '/');

    $args = array(
        'method' => 'PROPFIND',
        'headers' => array(
            'Authorization' => 'Basic ' . $token,
            'Depth' => '1',
            'Content-Type' => 'text/xml'
        ),
    );
    $response = wp_remote_request($request_url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 207) {
            error_log('Error response: ' . $response_code);
            wp_send_json_error('Error: ' . $response_code);
        }
        $body = wp_remote_retrieve_body($response);

        $xml_start = strpos($body, '<?xml');
        if ($xml_start !== false) {
            $body = substr($body, $xml_start);
        }

        $xml = simplexml_load_string($body);

        if ($xml === false) {
            wp_send_json_error('Failed to parse XML response');
        } else {
            $items = array();
            //Register namespace 'DAV:' for XPath queries and connect with the prefix 'd'
            $xml->registerXPathNamespace('d', 'DAV:');
            // "//d:response" means: find all 'response' elements on any depth
            foreach ($xml->xpath('//d:response') as $item) {
                $href = (string)$item->xpath('d:href')[0];
                // File Path
                $item_path = urldecode(str_replace($base_url, '', $href));
                $item_path = str_replace('/public.php/webdav/', '', $item_path);
                error_log('Item path: ' . $item_path);
                error_log('Current path: ' . $path);
                // File Name
                $name = basename(rtrim($item_path, '/'));
                //folder if d:resourcetype is a d:collection
                $is_folder = count($item->xpath('d:propstat/d:prop/d:resourcetype/d:collection')) > 0;

                if (!$is_folder) {
                    // If it's a file, generate a view token for it an add it to the items array
                    $view_token = generate_file_view_token($url, $item_path);
                    $items[] = array(
                        'name' => $name,
                        'isFolder' => $is_folder,
                        'path' => $item_path,
                        'viewToken' => $view_token,
                        'root' => false
                    );
                } else {
                    $items[] = array(
                        'name' => $name,
                        'isFolder' => $is_folder,
                        'path' => $item_path,
                        'root' => false
                    );
                }



            }
            // If we are at the root, add "Ordnerfreigabe" folder
            if ($path === '') {
                array_unshift($items, array(
                    'name' => 'Ordnerfreigabe',
                    'root' => true, // Add a root property to the item to identify it as the root folder
                    'isFolder' => true,
                    'path' => '/public.php/webdav/'
                ));
            }
            error_log('Items: ' . print_r($items, true));
            wp_send_json_success($items);
            wp_die();
        }
    }
}
