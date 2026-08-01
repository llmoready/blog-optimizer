<?php
/**
 * LLMO API Client
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Client Class for WordPress Plugin
 * Uses website-specific API token authentication
 */
class LLMO_API_Client {
    
    /**
     * API token (website-specific)
     */
    private $api_token;
    
    /**
     * Website domain for validation
     */
    private $website_domain;
    
    /**
     * API endpoint
     */
    private $api_endpoint;
    
    /**
     * Constructor
     *
     * @param string $api_token Website-specific API token from LLMO Ready
     * @param string $website_domain Current website domain for validation
     * @param string $api_endpoint API base URL
     */
    public function __construct($api_token, $website_domain = '', $api_endpoint = 'https://api.libers.ai/api') {
        $this->api_token = $api_token;
        $this->website_domain = $website_domain ? $website_domain : get_site_url();
        $this->api_endpoint = rtrim($api_endpoint, '/');
    }
    
    /**
     * Optimize article via WordPress plugin API
     *
     * @param array $article_data Article data (url, title, content, excerpt, author)
     * @return array|WP_Error Response or error
     */
    public function optimize_article($article_data) {
        $response = $this->make_request('/blog-optimizer/articles/optimize', 'POST', $article_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Get article by URL to check optimization status
     *
     * @param string $url Article URL
     * @return array|WP_Error Response or error
     */
    public function get_article_by_url($url) {
        $response = $this->make_request('/blog-optimizer/articles/by-url?url=' . urlencode($url), 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }

    /**
     * Single-shot status check for a queued optimization (no sleep; caller polls via AJAX/cron).
     *
     * @param string $url Article URL.
     * @return array|WP_Error|null Article when ready, WP_Error on failure, null when still pending.
     */
    public function check_optimization_status($url) {
        $result = $this->get_article_by_url($url);
        if (is_wp_error($result)) {
            return null;
        }

        $article = isset($result['article']) ? $result['article'] : null;
        $status = isset($article['status']) ? $article['status'] : '';

        if ($status === 'failed') {
            return new WP_Error('optimization_failed', __('Optimization failed on the server.', 'llmo-ready-blog-optimizer'));
        }

        if ($status === 'analyzed' && is_array($article)) {
            return $article;
        }

        return null;
    }

    /**
     * Sanitize remote API error text before showing it in the admin UI.
     *
     * @param mixed $message Raw remote message.
     * @return string
     */
    private function sanitize_remote_message($message) {
        if (!is_string($message) || $message === '') {
            return __('API request failed', 'llmo-ready-blog-optimizer');
        }

        $message = wp_strip_all_tags($message);
        $message = sanitize_text_field($message);

        if (strlen($message) > 200) {
            $message = substr($message, 0, 200);
        }

        return $message !== '' ? $message : __('API request failed', 'llmo-ready-blog-optimizer');
    }
    
    /**
     * Make API request with token authentication
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|WP_Error Response or error
     */
    private function make_request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->api_endpoint . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 90,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'X-Website-Domain' => $this->website_domain,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );
        
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $raw_message = (is_array($error_data) && isset($error_data['message'])) ? $error_data['message'] : '';
            $error_message = $this->sanitize_remote_message($raw_message);
            
            return new WP_Error('api_error', $error_message, array(
                'status' => $response_code,
            ));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', esc_html__('Invalid JSON response from API', 'llmo-ready-blog-optimizer'));
        }
        
        return $data;
    }
    
    /**
     * Test API connection
     *
     * @return bool True if connected, false otherwise
     */
    public function test_connection() {
        $response = $this->make_request('/blog-optimizer/health', 'GET');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return true;
    }
}
