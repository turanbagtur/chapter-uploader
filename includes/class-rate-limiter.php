<?php
/**
 * MCU Rate Limiter Class
 * Spam ve abuse önleme için rate limiting sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCU_RateLimiter {
    
    private $limits = array(
        'fetch_url' => array('requests' => 10, 'period' => 60),
        'upload_single' => array('requests' => 30, 'period' => 60),
        'upload_zip' => array('requests' => 5, 'period' => 300),
        'test_url' => array('requests' => 20, 'period' => 60)
    );
    
    private $transient_prefix = 'mcu_rate_';
    
    public function __construct() {
        $custom_limits = get_option('mcu_rate_limits', array());
        if (!empty($custom_limits)) {
            $this->limits = array_merge($this->limits, $custom_limits);
        }
    }
    
    public function check_limit($action, $user_id = 0) {
        if (!isset($this->limits[$action])) {
            return true;
        }
        
        $limit = $this->limits[$action];
        $key = $this->get_rate_key($action, $user_id);
        $data = get_transient($key);
        
        if ($data === false) {
            set_transient($key, array(
                'count' => 1,
                'first_request' => time()
            ), $limit['period']);
            return true;
        }
        
        if ((time() - $data['first_request']) > $limit['period']) {
            set_transient($key, array(
                'count' => 1,
                'first_request' => time()
            ), $limit['period']);
            return true;
        }
        
        if ($data['count'] >= $limit['requests']) {
            $remaining_time = $limit['period'] - (time() - $data['first_request']);
            
            if (class_exists('MCU_Logger')) {
                MCU_Logger::warning('Rate limit exceeded', array(
                    'action' => $action,
                    'user_id' => $user_id,
                    'ip' => $this->get_client_ip()
                ));
            }
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(__('Too many requests. Please wait %d seconds.', 'manga-chapter-uploader'), $remaining_time),
                array('retry_after' => $remaining_time)
            );
        }
        
        $data['count']++;
        set_transient($key, $data, $limit['period']);
        
        return true;
    }
    
    public function get_remaining($action, $user_id = 0) {
        if (!isset($this->limits[$action])) {
            return -1;
        }
        
        $key = $this->get_rate_key($action, $user_id);
        $data = get_transient($key);
        
        if ($data === false) {
            return $this->limits[$action]['requests'];
        }
        
        return max(0, $this->limits[$action]['requests'] - $data['count']);
    }
    
    public function reset_limit($action, $user_id = 0) {
        $key = $this->get_rate_key($action, $user_id);
        delete_transient($key);
    }
    
    private function get_rate_key($action, $user_id) {
        $identifier = $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($this->get_client_ip());
        return $this->transient_prefix . $action . '_' . $identifier;
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    public function is_url_blocked($url) {
        $blocked_domains = get_option('mcu_blocked_domains', array());
        $parsed = parse_url($url);
        
        if (!$parsed || empty($parsed['host'])) {
            return true;
        }
        
        $host = strtolower($parsed['host']);
        
        foreach ($blocked_domains as $blocked) {
            if (strpos($host, $blocked) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
