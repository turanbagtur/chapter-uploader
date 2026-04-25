<?php
/**
 * MCU Batch Processor Class
 * Arka planda toplu işlem yapma sistemi (WP Cron bazlı)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCU_BatchProcessor {
    
    private $batch_size = 5;
    private $queue_option = 'mcu_batch_queue';
    
    public function __construct() {
        $this->batch_size = get_option('mcu_batch_size', 5);
        
        // Önce cron interval'ı kaydet
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        add_action('mcu_process_batch', array($this, 'process_next_batch'));
        add_action('wp_ajax_mcu_add_to_queue', array($this, 'ajax_add_to_queue'));
        add_action('wp_ajax_mcu_get_queue_status', array($this, 'ajax_get_queue_status'));
        add_action('wp_ajax_mcu_clear_queue', array($this, 'ajax_clear_queue'));
        
        // Cron zamanlamasını init'te yap (filtre hazır olduktan sonra)
        add_action('init', array($this, 'schedule_batch_processing'));
    }
    
    public function add_cron_interval($schedules) {
        $schedules['mcu_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'manga-chapter-uploader')
        );
        return $schedules;
    }
    
    public function schedule_batch_processing() {
        if (!wp_next_scheduled('mcu_process_batch')) {
            wp_schedule_event(time(), 'mcu_five_minutes', 'mcu_process_batch');
        }
    }
    
    public static function register_cron_interval($schedules) {
        $schedules['mcu_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'manga-chapter-uploader')
        );
        return $schedules;
    }
    
    public function add_to_queue($task) {
        $queue = get_option($this->queue_option, array());
        
        $task_id = uniqid('mcu_task_');
        $task['id'] = $task_id;
        $task['status'] = 'pending';
        $task['created_at'] = time();
        $task['attempts'] = 0;
        
        $queue[$task_id] = $task;
        update_option($this->queue_option, $queue);
        
        MCU_Logger::info('Task added to queue', array('task_id' => $task_id, 'type' => $task['type']));
        
        // Hemen işlemeye başla
        if (!wp_next_scheduled('mcu_process_batch')) {
            wp_schedule_single_event(time() + 10, 'mcu_process_batch');
        }
        
        return $task_id;
    }
    
    public function process_next_batch() {
        $queue = get_option($this->queue_option, array());
        
        if (empty($queue)) {
            return;
        }
        
        $processed = 0;
        
        foreach ($queue as $task_id => $task) {
            if ($processed >= $this->batch_size) {
                break;
            }
            
            if ($task['status'] !== 'pending') {
                continue;
            }
            
            // Task'ı işleniyor olarak işaretle
            $queue[$task_id]['status'] = 'processing';
            $queue[$task_id]['started_at'] = time();
            update_option($this->queue_option, $queue);
            
            // Task'ı işle
            $result = $this->process_task($task);
            
            if ($result['success']) {
                $queue[$task_id]['status'] = 'completed';
                $queue[$task_id]['completed_at'] = time();
                $queue[$task_id]['result'] = $result;
                
                MCU_Logger::info('Batch task completed', array('task_id' => $task_id));
            } else {
                $queue[$task_id]['attempts']++;
                
                if ($queue[$task_id]['attempts'] >= 3) {
                    $queue[$task_id]['status'] = 'failed';
                    $queue[$task_id]['error'] = $result['message'];
                    MCU_Logger::error('Batch task failed', array('task_id' => $task_id, 'error' => $result['message']));
                } else {
                    $queue[$task_id]['status'] = 'pending';
                    MCU_Logger::warning('Batch task retry', array('task_id' => $task_id, 'attempt' => $queue[$task_id]['attempts']));
                }
            }
            
            update_option($this->queue_option, $queue);
            $processed++;
        }
        
        // Tamamlanmış ve başarısız görevleri 24 saat sonra temizle
        $this->cleanup_old_tasks();
        
        // Hala bekleyen görev varsa tekrar zamanla
        $pending = array_filter($queue, function($t) { return $t['status'] === 'pending'; });
        if (!empty($pending) && !wp_next_scheduled('mcu_process_batch')) {
            wp_schedule_single_event(time() + 60, 'mcu_process_batch');
        }
    }
    
    private function process_task($task) {
        switch ($task['type']) {
            case 'fetch_url':
                return $this->process_fetch_url($task);
            
            case 'process_images':
                return $this->process_images($task);
            
            case 'optimize_chapter':
                return $this->optimize_chapter($task);
            
            default:
                return array('success' => false, 'message' => 'Unknown task type');
        }
    }
    
    private function process_fetch_url($task) {
        if (!class_exists('MCU_AdvancedFetcher')) {
            return array('success' => false, 'message' => 'Fetcher class not found');
        }
        
        $fetcher = new MCU_AdvancedFetcher();
        $result = $fetcher->fetch_from_url($task['url'], $task['manga_id'], $task['chapter_number']);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Resimleri indir ve chapter oluştur
        return $this->create_chapter_from_fetched($task, $result['images']);
    }
    
    private function create_chapter_from_fetched($task, $image_urls) {
        if (!function_exists('download_url')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        
        $uploaded_images = array();
        
        foreach ($image_urls as $img_url) {
            $temp_file = download_url($img_url, 300);
            
            if (is_wp_error($temp_file)) {
                continue;
            }
            
            $file_array = array(
                'name' => basename(parse_url($img_url, PHP_URL_PATH)) ?: 'image_' . uniqid() . '.jpg',
                'tmp_name' => $temp_file
            );
            
            $attachment_id = media_handle_sideload($file_array, 0);
            
            if (!is_wp_error($attachment_id)) {
                $uploaded_images[] = wp_get_attachment_image($attachment_id, 'full');
            }
            
            @unlink($temp_file);
        }
        
        if (empty($uploaded_images)) {
            return array('success' => false, 'message' => 'No images could be downloaded');
        }
        
        // Chapter oluştur
        $manga_title = get_the_title($task['manga_id']);
        $post_title = sprintf('%s Chapter %s', $manga_title, $task['chapter_number']);
        
        $post_id = wp_insert_post(array(
            'post_title' => $post_title,
            'post_content' => implode("\n", $uploaded_images),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $task['user_id'] ?? 1
        ));
        
        if (is_wp_error($post_id)) {
            return array('success' => false, 'message' => $post_id->get_error_message());
        }
        
        // Meta kaydet
        update_post_meta($post_id, 'ero_chapter', $task['chapter_number']);
        update_post_meta($post_id, 'ero_seri', $task['manga_id']);
        
        return array(
            'success' => true,
            'message' => 'Chapter created',
            'post_id' => $post_id,
            'images_count' => count($uploaded_images)
        );
    }
    
    private function process_images($task) {
        if (!isset($task['attachment_ids']) || empty($task['attachment_ids'])) {
            return array('success' => false, 'message' => 'No attachments specified');
        }
        
        global $mcu_image_processor;
        if (!$mcu_image_processor) {
            return array('success' => false, 'message' => 'Image processor not available');
        }
        
        $processed = 0;
        foreach ($task['attachment_ids'] as $attachment_id) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                $mcu_image_processor->process_image($file, $attachment_id);
                $processed++;
            }
        }
        
        return array('success' => true, 'message' => sprintf('%d images processed', $processed));
    }
    
    private function optimize_chapter($task) {
        $post_id = $task['post_id'];
        $post = get_post($post_id);
        
        if (!$post) {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        // Post içeriğindeki resimleri bul
        preg_match_all('/wp-image-(\d+)/', $post->post_content, $matches);
        
        if (empty($matches[1])) {
            return array('success' => true, 'message' => 'No images to optimize');
        }
        
        // Her resmi optimize et
        global $mcu_image_processor;
        $optimized = 0;
        
        foreach ($matches[1] as $attachment_id) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file) && $mcu_image_processor) {
                if ($mcu_image_processor->process_image($file, $attachment_id)) {
                    $optimized++;
                }
            }
        }
        
        return array('success' => true, 'message' => sprintf('%d images optimized', $optimized));
    }
    
    private function cleanup_old_tasks() {
        $queue = get_option($this->queue_option, array());
        $threshold = time() - (24 * 3600); // 24 saat
        
        foreach ($queue as $task_id => $task) {
            if (in_array($task['status'], array('completed', 'failed'))) {
                $completed_at = $task['completed_at'] ?? $task['created_at'];
                if ($completed_at < $threshold) {
                    unset($queue[$task_id]);
                }
            }
        }
        
        update_option($this->queue_option, $queue);
    }
    
    public function ajax_add_to_queue() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'manga-chapter-uploader')));
        }
        
        $task = array(
            'type' => isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : '',
            'user_id' => get_current_user_id()
        );
        
        // Task tipine göre ek parametreler
        if ($task['type'] === 'fetch_url') {
            $task['url'] = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
            $task['manga_id'] = isset($_POST['manga_id']) ? intval($_POST['manga_id']) : 0;
            $task['chapter_number'] = isset($_POST['chapter_number']) ? floatval($_POST['chapter_number']) : 0;
        }
        
        $task_id = $this->add_to_queue($task);
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => __('Task added to queue', 'manga-chapter-uploader')
        ));
    }
    
    public function ajax_get_queue_status() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        $queue = get_option($this->queue_option, array());
        
        $stats = array(
            'total' => count($queue),
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        );
        
        foreach ($queue as $task) {
            if (isset($stats[$task['status']])) {
                $stats[$task['status']]++;
            }
        }
        
        wp_send_json_success(array(
            'stats' => $stats,
            'tasks' => array_values($queue)
        ));
    }
    
    public function ajax_clear_queue() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'manga-chapter-uploader')));
        }
        
        update_option($this->queue_option, array());
        
        wp_send_json_success(array('message' => __('Queue cleared', 'manga-chapter-uploader')));
    }
    
    public function get_queue() {
        return get_option($this->queue_option, array());
    }
    
    public function get_task($task_id) {
        $queue = $this->get_queue();
        return isset($queue[$task_id]) ? $queue[$task_id] : null;
    }
}

// Eski static metod kaldırıldı - artık constructor'da yapılıyor
