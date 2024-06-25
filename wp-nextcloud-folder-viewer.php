<?php
/*
Plugin Name: Nextcloud Folder Viewer
Description: Zeigt Freigaben von Nextcloud als Ordnerstruktur an und ermöglicht das Anzeigen von Dateien via Shortcode [nextcloud url="https://example.com/nextcloud/f/123456"] oder das Einbetten von Dateien mit [nextcloud_folder url="https://example.com/nextcloud/s/123456" title="readme.md" show="false"].
Version: 0.1.0
Author: Joachim Happel
*/

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'class-nextcloud-file-viewer.php';

class NextcloudFolderViewer {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new NextcloudFolderViewer();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('nextcloud', array($this, 'nextcloud_folder_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_get_nextcloud_folder', array($this, 'get_nextcloud_folder'));
        add_action('wp_ajax_nopriv_get_nextcloud_folder', array($this, 'get_nextcloud_folder'));
        add_action('wp_ajax_view_nextcloud_file', array($this, 'view_nextcloud_file'));
        add_action('wp_ajax_nopriv_view_nextcloud_file', array($this, 'view_nextcloud_file'));
        add_action('init', array($this, 'stream_nextcloud_file'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style('nextcloud-folder-viewer-style', plugin_dir_url(__FILE__) . 'css/nextcloud-folder-viewer.css', array(), '1.0');
        wp_enqueue_script('nextcloud-folder-viewer', plugin_dir_url(__FILE__) . 'js/nextcloud-folder-viewer.js', array('jquery'), '1.0', true);
        wp_localize_script('nextcloud-folder-viewer', 'nextcloudAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    public function nextcloud_folder_shortcode($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'title' => '',
            'show' => 'true',
        ), $atts, 'nextcloud');

        if (empty($atts['url'])) {
            return 'Error: Nextcloud URL is required.';
        }

        $url = $atts['url'];
        $share_token = basename(parse_url($url, PHP_URL_PATH));

        $viewer = new NextcloudFileViewer($url, $share_token);

        if ($content_type = $viewer->isSharedFile()) {
            return $this->handle_shared_file($content_type, $viewer, $url, $atts);
        } else {
            return $this->handle_shared_folder($url);
        }
    }

    private function handle_shared_file($content_type, $viewer, $url, $atts) {
        $label = $atts['title'] != '' ? $atts['title'] : '';
        if ($atts['show'] == 'true') {
            return $this->nextcloud_file_embed($content_type, $viewer, $url, $label);
        } else {
            return "<a class='button button-primary' href='{$url}/download' target='_blank'>{$label}</a>";
        }
    }

    private function handle_shared_folder($url) {

        $unique_id = 'nextcloud-folder-' . wp_generate_password(8, false);
        $crypt_url = $this->encrypt($url);
        $output = '<div id="' . $unique_id . '" class="nextcloud-folder-tree" data-url="' . $crypt_url . '"></div>';
        wp_enqueue_script('nextcloud-folder-viewer');
        return $output;

    }

    public function stream_nextcloud_file() {
        if (isset($_GET['action']) && $_GET['action'] == 'stream_nextcloud_file' && isset($_GET['token']) && $_GET['token'] != '') {
            $this->view_nextcloud_file($_GET['token']);
            die();
        }
    }

    public function view_nextcloud_file($token) {
        if (!isset($_GET['token']) && !$token) {
            return;
        }

        $data = $this->get_file_view_data($_GET['token']);

        if (!$data) {
            wp_die('Invalid or expired token');
        }

        $url = $data['url'];
        $path = $data['path'];
        $parsed_url = parse_url($url);
        $share_token = basename($parsed_url['path']);

        $viewer = new NextcloudFileViewer($url, $share_token);
        $viewer->viewFile($path);
    }

    private function generate_file_view_token($url, $path='') {
        $token = wp_generate_password(32, false);
        set_transient('nextcloud_view_' . $token, array('url' => $url, 'path' => $path), 60 * MINUTE_IN_SECONDS);
        return $token;
    }

    private function encrypt($url) {
        $token = wp_generate_password(32, false);
        $share_token = basename(parse_url($url, PHP_URL_PATH));
        $host = parse_url($url, PHP_URL_HOST);
        $crypt_url = str_replace($host, $share_token.'.org', $url);
        $crypt_url = str_replace($share_token, $token, $crypt_url);
        set_transient('nextcloud_crypt_' . $token, $url, 60 * MINUTE_IN_SECONDS);
        return $crypt_url;
    }

    private function decrypt($url) {
        $token = basename(parse_url($url, PHP_URL_PATH));
        return get_transient('nextcloud_crypt_' . $token);
    }

    private function get_file_view_data($token) {
        return get_transient('nextcloud_view_' . $token);
    }

    public function get_nextcloud_folder() {
        $url = $this->decrypt($_POST['url']);
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
                        $view_token = $this->generate_file_view_token($url, $item_path);
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

    private function nextcloud_file_embed($content_type, $viewer, $shared_url, $file_name="") {


        $crypt_url = $this->encrypt($shared_url);
        $unique_id = 'nextcloud-folder-' . wp_generate_password(8, false);

        $prefix = '<div id="' . $unique_id . '" data-url="'.$crypt_url.'" class="nextcloud-folder-tree">';
        $suffix = '</div>';
        if($file_name !== ''){
            $prefix .= '<div class="nextcloud-file-embed"><span class="file-name">'.$file_name.'</span>';
            $suffix = '<div class="nextcloud-download-link"><a href="'.$shared_url.'/download">Download</a></div>'.$suffix;
        }

        $view_token = $this->generate_file_view_token($shared_url);
        $url = home_url()."?action=stream_nextcloud_file&token={$view_token}";


        $img = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',

        );
        $pdf = array(
            'application/pdf',
        );
        $video = array(
            'video/mp4', 'video/webm',
        );
        $audio = array(
            'audio/mpeg', 'audio/ogg',
        );
        $text = array(
            'text/plain', 'text/markdown',
        );
        $texthtml = array(
            'text/html'
        );

        $html_file_embed = '';
        if (in_array($content_type, $img)) {
            $html_file_embed = '<img src="' . $url . '" alt="Image" />';
        } elseif (in_array($content_type, $pdf)) {
            $html_file_embed =  '<embed class="embed-nextcloud-pdf" src="' . $url . '" type="application/pdf" width="100%" height="770"/>';
        } elseif (in_array($content_type, $video)) {
            $html_file_embed =  '<video class="embed-nextcloud-av" width="100%" height="auto" controls="" data-origheight="auto">
            <source src="' . $url . '" type="' . $content_type . '">
            Your browser does not support the video tag.
            </video>';
        } elseif (in_array($content_type, $audio)) {
            $html_file_embed =  '<div class="embed-nextcloud-av"><audio controls>
            <source src="' . $url . '" type="' . $content_type . '">
            Your browser does not support the audio element.
            </audio></div>';
        } elseif (in_array($content_type, $text)) {
            $html_file_embed =  '<div class="embed-markdown">'.$viewer->viewFile('').'</div>';
        } elseif (in_array($content_type, $texthtml)) {
            $html_file_embed =  '<iframe src="' . $url . '" width="100%" height="600px"></iframe>';
        }
        if($html_file_embed !== ''){
            return $prefix.$html_file_embed.$suffix;
        }
        return '';
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('NextcloudFolderViewer', 'get_instance'));

// Composer install on activation
register_activation_hook(__FILE__, 'parsedown_composer_activation');
function parsedown_composer_activation() {
    // ACHTUNG: Dies könnte Sicherheitsrisiken bergen und auf vielen Hosts nicht funktionieren
    // besser ist es, die Abhängigkeiten lokal zu installieren und die Dateien hochzuladen
    // composer install --optimize-autoloader --no-dev
    exec('composer install --no-dev');
}
