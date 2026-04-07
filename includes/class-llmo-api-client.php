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
 * API Client Class
 */
class LLMO_API_Client {
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * API endpoint
     */
    private $api_endpoint;
    
    /**
     * Constructor
     */
    public function __construct($api_key, $api_endpoint = 'https://llmoready.com/api') {
        $this->api_key = $api_key;
        $this->api_endpoint = rtrim($api_endpoint, '/');
    }
    
    /**
     * Optimize article
     */
    public function optimize_article($article_data) {
        $response = $this->make_request('/blog-articles/optimize', 'POST', $article_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Detect articles from website
     */
    public function detect_articles($website_url) {
        $response = $this->make_request('/blog-articles/detect', 'POST', array(
            'website_url' => $website_url
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Get optimization status
     */
    public function get_optimization_status($article_id) {
        $response = $this->make_request('/blog-articles/' . $article_id, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->api_endpoint . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
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
            $error_message = isset($error_data['message']) ? $error_data['message'] : __('API request failed', 'llmo-blog-optimizer');
            
            return new WP_Error('api_error', $error_message, array(
                'status' => $response_code,
                'response' => $error_data
            ));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON response from API', 'llmo-blog-optimizer'));
        }
        
        return $data;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->make_request('/health', 'GET');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return true;
    }
}
