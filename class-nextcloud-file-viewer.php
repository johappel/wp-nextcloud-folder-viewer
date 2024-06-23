<?php

class NextcloudFileViewer
{
    private $base_url;
    private $token;

    public function __construct($url, $share_token)
    {
        $parsed_url = parse_url($url);
        $this->base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/public.php/webdav/';
        $this->token = base64_encode($share_token . ':');
    }

    public function viewFile($file_path)
    {
        $file_url = $this->base_url . ltrim($file_path, '/');
        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Basic ' . $this->token,
            ),
        );

        $response = wp_remote_get($file_url, $args);

        if (is_wp_error($response)) {
            wp_die('Error fetching file: ' . $response->get_error_message());
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content = wp_remote_retrieve_body($response);

        $this->outputContent($content_type, $content, basename($file_path));
    }

    private function outputContent($content_type, $content, $filename)
    {
        $viewable_types = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',
            'application/pdf',
            'video/mp4', 'video/webm', 'audio/mpeg', 'audio/ogg',
            'text/plain', 'text/markdown',
            'text/html'
        );

        if (in_array($content_type, $viewable_types)) {
            header("Content-Type: $content_type");
            if ($content_type === 'application/pdf') {
                header("Content-Disposition: inline; filename=\"$filename\"");
            }
            echo $content;
        } else {
            header("Content-Type: $content_type");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            echo $content;
        }
        exit;
    }
}
