<?php
/**
 * MCU Logger Class
 * Gelişmiş hata loglama ve debug sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCU_Logger {
    
    private static $log_file = null;
    private static $log_enabled = null;
    
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/mcu-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
            
            self::$log_file = $log_dir . '/mcu-' . date('Y-m-d') . '.log';
        }
        
        return self::$log_file;
    }
    
    private static function is_enabled() {
        if (self::$log_enabled === null) {
            self::$log_enabled = get_option('mcu_logging_enabled', true);
        }
        return self::$log_enabled;
    }
    
    public static function log($level, $message, $context = array()) {
        if (!self::is_enabled()) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI',
            'context' => $context,
            'memory' => size_format(memory_get_usage(true))
        );
        
        $log_line = sprintf(
            "[%s] [%s] [User:%d] [IP:%s] %s %s\n",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['user_id'],
            $log_entry['ip'],
            $log_entry['message'],
            !empty($context) ? '| ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        
        error_log($log_line, 3, self::get_log_file());
        
        if (in_array($level, array(self::LEVEL_ERROR, self::LEVEL_CRITICAL)) && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[MCU] ' . $log_line);
        }
        
        self::save_to_db($log_entry);
    }
    
    private static function save_to_db($entry) {
        $logs = get_option('mcu_recent_logs', array());
        array_unshift($logs, $entry);
        
        if (count($logs) > 500) {
            $logs = array_slice($logs, 0, 500);
        }
        
        update_option('mcu_recent_logs', $logs, false);
    }
    
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    public static function info($message, $context = array()) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    public static function warning($message, $context = array()) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    public static function critical($message, $context = array()) {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    public static function log_upload($type, $manga_id, $chapter, $success, $details = array()) {
        $level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;
        $message = $success 
            ? sprintf('Upload OK: %s ch.%s manga#%d', $type, $chapter, $manga_id)
            : sprintf('Upload FAIL: %s ch.%s manga#%d', $type, $chapter, $manga_id);
        
        self::log($level, $message, array_merge(array(
            'type' => $type,
            'manga_id' => $manga_id,
            'chapter' => $chapter
        ), $details));
    }
    
    public static function clear_logs($days_old = 30) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/mcu-logs';
        
        if (!is_dir($log_dir)) return 0;
        
        $deleted = 0;
        $files = glob($log_dir . '/mcu-*.log');
        $threshold = strtotime("-{$days_old} days");
        
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                if (unlink($file)) $deleted++;
            }
        }
        
        if ($days_old <= 7) {
            update_option('mcu_recent_logs', array());
        }
        
        return $deleted;
    }
    
    public static function get_recent_logs($count = 100, $level = null) {
        $logs = get_option('mcu_recent_logs', array());
        
        if ($level !== null) {
            $logs = array_filter($logs, function($log) use ($level) {
                return $log['level'] === $level;
            });
        }
        
        return array_slice($logs, 0, $count);
    }
    
    public static function get_log_stats() {
        $logs = get_option('mcu_recent_logs', array());
        $stats = array(
            'total' => count($logs),
            'errors' => 0,
            'warnings' => 0,
            'info' => 0
        );
        
        foreach ($logs as $log) {
            if ($log['level'] === self::LEVEL_ERROR || $log['level'] === self::LEVEL_CRITICAL) {
                $stats['errors']++;
            } elseif ($log['level'] === self::LEVEL_WARNING) {
                $stats['warnings']++;
            } else {
                $stats['info']++;
            }
        }
        
        return $stats;
    }
}
