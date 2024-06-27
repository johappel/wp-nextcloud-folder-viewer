<?php
/*
Plugin Name: Nextcloud Shares Viewer
Description: Zeigt Freigaben von Nextcloud als Ordnerstruktur über oEmbed an oder via Shortcode [nextcloud url="https://example.com/nextcloud/f/123456"].
Version: 1.0.1
Author: Joachim Happel
*/
define('WP_HTTP_BLOCK_EXTERNAL', false);

if (!defined('ABSPATH')) exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
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
        add_action('init', array($this,'handle_custom_oembed_endpoint'));
        wp_embed_register_handler('nextcloud', '#https?://[^/]+/s/[a-zA-Z0-9]+#i', array($this, 'nextcloud_embed_handler'));
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

    protected function do_propfind_request($url, $path) {
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
            return false;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 207) {
                return false;
            }
            $body = wp_remote_retrieve_body($response);

            $xml_start = strpos($body, '<?xml');
            if ($xml_start !== false) {
                $body = substr($body, $xml_start);
            }

            $xml = simplexml_load_string($body);

            if ($xml === false) {
                return false;
            } else {
                return $xml;
            }
        }
    }

    public function check_is_nextcloud_folder($url) {

        $xml = $this->do_propfind_request($url, '');
        if ($xml === false) {
            return false;
        } else {
            $xml->registerXPathNamespace('d', 'DAV:');
            $n = 0;
            foreach ($xml->xpath('//d:response') as $item) {
                $n ++;
            }
            if($n > 1){
                return true;
            }
            return false;
        }
    }

    public function get_nextcloud_folder() {
        $url = $this->decrypt($_POST['url']);
        if (!$url) {
            wp_send_json_error('Missing Encrypted URL');
            $url= $_POST['url'];
        }
        $path = isset($_POST['path']) ? $_POST['path'] : '';
        $parsed_url = parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/public.php/webdav/';


        $xml = $this->do_propfind_request($url, $path);
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
            wp_send_json_success($items);
            wp_die();
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
    public function custom_oembed_handler($provider, $url, $args) {

        if (preg_match('#https?://https?://[^/]+/s/[a-zA-Z0-9]#i', $url)) {
           # return plugins_url('oembed-endpoint.php', __FILE__);
        }
        return $provider;
    }
    function handle_custom_oembed_endpoint() {
        if (isset($_GET['url']) && preg_match('#https?://[^/]+/s/[a-zA-Z0-9]+#i', $_GET['url'])) {
            $url = $_GET['url'];

            if($this->check_is_nextcloud_folder($url)){
                $shortcode = '[nextcloud url="' . $url . '"]';
                $html = '<div style="width: 100%; background-color: #fff; border: 1px solid #ccc;font-size: 20px;padding: 5px;font-family:sans-serif;">'.
                    '<label for="blocks-shortcode-input" style="padding: 20px; width: 100%, font-size: 24px;">'.
                    '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="28" height="28" aria-hidden="true" focusable="false">'.
                    '<path d="M16 4.2v1.5h2.5v12.5H16v1.5h4V4.2h-4zM4.2 19.8h4v-1.5H5.8V5.8h2.5V4.2h-4l-.1 15.6zm5.1-3.1l1.4.6 4-10-1.4-.6-4 10z"></path></svg>'.
                    '<span style="margin-left: 20px">Zeigt Nextcloud Ordner im Frontend an</span>'.
                    '</label>'.
                    '<textarea placeholder="Schreibe hier den Shortcode…" rows="1" '.
                        'style="border-top: 1px solid #ccc;overflow: hidden;font-size: 16px; padding:7px 11px ;overflow: hidden; overflow-wrap: break-word; resize: horizontal; height: 38px; width:94%">'.
                        $shortcode.
                    '</textarea></div>';
            }else{
                $shortcode = '[nextcloud url="' . $url . '"]';
                $html = do_shortcode($shortcode);

            }


            $provider = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
            header('Content-Type: application/json');
            $oembed_data = array(
                'version' => '1.0',
                'type' => 'video',
                'title' => 'Shared Nextcloud Folder',
                'html' => $html,
                'width' => 600,
                'provider_name' => 'Nextcloud',
                'provider_url' => "$provider",
                'height' => 400,
            );

           echo json_encode($oembed_data);
           exit;
        }
    }

    public function nextcloud_embed_handler($matches, $attr, $url, $rawattr) {
        // Shortcode basierend auf der URL
        $shortcode = sprintf('[nextcloud url="%s"]', esc_url($url));
        return do_shortcode($shortcode);
    }




}

// Initialize the plugin
add_action('plugins_loaded', array('NextcloudFolderViewer', 'get_instance'));

// Composer install on activation
// Überprüfen Sie einfach, ob die erforderlichen Dateien vorhanden sind
register_activation_hook(__FILE__, 'check_required_files');
function check_required_files() {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        do_action('composer_installation_notice');

    }
}
add_action('admin_notices', 'composer_installation_notice');
function composer_installation_notice() {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            // Versuchen, Composer auszuführen
            exec('composer install --no-dev');
        } catch (Exception $e) {
            // Fehlermeldung an den Admin ausgeben
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Plugin deaktiviert!</strong><p>';
            echo 'Bitte führen Sie auf der Console den Befehl "composer install" im Plugin-Verzeichnis aus, um alle erforderlichen Abhängigkeiten zu installieren.';
            echo '</p></div>';
            deactivate_plugins(plugin_basename(__FILE__));

        }
    }
}
