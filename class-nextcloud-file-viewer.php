<?php

use Parsedown;
/**
 * Class NextcloudFileViewer
 * Fetch the file content from the Nextcloud server
 */
class NextcloudFileViewer
{
    private $base_url;
    private $token;

    /**
     * @param $url
     * @param $share_token
     */
    public function __construct($url, $share_token)
    {
        $parsed_url = parse_url($url);
        $this->base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/public.php/webdav/';
        $this->token = base64_encode($share_token . ':');
    }
    /**
     * Fetch the file content from the Nextcloud server
     *
     * @param string $file_path
     * @return void
     */
    public function viewFile($file_path)
    {
        $file_url = $this->base_url . ltrim($file_path, '/');

        // set the headers for the request
        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Basic ' . $this->token,
            ),
        );
        // Fetch the file content from the Nextcloud server
        $response = wp_remote_get($file_url, $args);

        if (is_wp_error($response)) {
            wp_die('Error fetching file: ' . $response->get_error_message());
        }

        // Get the content type of the file type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        //maybe the content type has a charset, so we need to remove it
        $ct = explode(';', $content_type);
        $content_type = $ct[0];
        // Get the content of the file
        $content = wp_remote_retrieve_body($response);

        // Check if the file is a markdown or text file
        if($content_type === 'text/markdown' || $content_type === 'text/plain'){
            $Parsedown = new Parsedown();
            $content = $Parsedown->text($content);
            return $content;
        }else{
            // Output the file content to the browser
            $this->outputContent($content_type, $content, basename($file_path));
        }



    }

    /**
     * @param $content_type
     * @param $content
     * @param $filename
     * @return void
     */
    private function outputContent($content_type, $content, $filename)
    {

        $viewable_types = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',
            'application/pdf',
            'video/mp4', 'video/webm', 'audio/mpeg', 'audio/ogg',
            'text/plain', 'text/markdown',
            'text/html'
        );

        header_remove() ;

        header('Content-Type: '.$content_type.';charset=utf-8');
        header("Content-Disposition: inline; filename=\"$filename\"");
        echo $content;
        exit();
    }


    /**
     * Check if the file is a shared file and not a folder
     * @return false|string
     */
    public function isSharedFile()
    {
        $args = array(
            'method' => 'PROPFIND',
            'headers' => array(
                'Authorization' => 'Basic ' . $this->token,
                'Depth' => '1',
            ),
        );

        $response = wp_remote_request($this->base_url, $args);
        error_log('body: ' . json_encode($response));

        if (is_wp_error($response)) {
            return false;
        }


        $body = wp_remote_retrieve_body($response);


        $xml = simplexml_load_string($body);

        if ($xml === false) {
            return false;
        }

        // Register the namespace
        $xml->registerXPathNamespace('d', 'DAV:');
        $resourcetype = $xml->xpath('//d:getcontenttype');
        // Check if the file is a folder; if it has more then one href, return false
        $files = $xml->xpath('//d:href');
        $is_folder = count($files) > 1 ?true: false;
        if($is_folder){
            return false;
        }
        if(is_array($resourcetype)){
            $resourcetype= (string)$resourcetype[0];
        }
        // Return the content type of the file
        return $resourcetype;
    }
}
