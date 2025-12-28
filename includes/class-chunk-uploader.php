<?php
/**
 * MCU Chunk Uploader Class
 * Büyük dosyalar için parçalı yükleme sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCU_ChunkUploader {
    
    private $chunk_size = 2097152; // 2MB
    private $temp_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->temp_dir = $upload_dir['basedir'] . '/mcu-chunks';
        $this->chunk_size = get_option('mcu_chunk_size', $this->chunk_size);
        
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
            file_put_contents($this->temp_dir . '/.htaccess', 'Deny from all');
        }
        
        add_action('wp_ajax_mcu_chunk_upload', array($this, 'handle_chunk_upload'));
        add_action('wp_ajax_mcu_chunk_complete', array($this, 'handle_chunk_complete'));
        add_action('wp_ajax_mcu_chunk_cancel', array($this, 'handle_chunk_cancel'));
    }
    
    public function get_chunk_size() {
        return $this->chunk_size;
    }
    
    public function handle_chunk_upload() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'manga-chapter-uploader')));
        }
        
        $upload_id = isset($_POST['upload_id']) ? sanitize_text_field($_POST['upload_id']) : '';
        $chunk_index = isset($_POST['chunk_index']) ? intval($_POST['chunk_index']) : 0;
        $total_chunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 0;
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        
        if (empty($upload_id) || !isset($_FILES['chunk'])) {
            wp_send_json_error(array('message' => __('Invalid chunk data', 'manga-chapter-uploader')));
        }
        
        $upload_dir = $this->temp_dir . '/' . $upload_id;
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        
        $chunk_file = $upload_dir . '/chunk_' . str_pad($chunk_index, 6, '0', STR_PAD_LEFT);
        
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_file)) {
            MCU_Logger::error('Chunk upload failed', array('upload_id' => $upload_id, 'chunk' => $chunk_index));
            wp_send_json_error(array('message' => __('Failed to save chunk', 'manga-chapter-uploader')));
        }
        
        $meta_file = $upload_dir . '/meta.json';
        $meta = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : array(
            'filename' => $filename,
            'total_chunks' => $total_chunks,
            'uploaded_chunks' => array(),
            'created_at' => time(),
            'user_id' => get_current_user_id()
        );
        
        $meta['uploaded_chunks'][] = $chunk_index;
        $meta['last_activity'] = time();
        file_put_contents($meta_file, json_encode($meta));
        
        wp_send_json_success(array(
            'chunk_index' => $chunk_index,
            'uploaded' => count($meta['uploaded_chunks']),
            'total' => $total_chunks,
            'progress' => round((count($meta['uploaded_chunks']) / $total_chunks) * 100, 2)
        ));
    }
    
    public function handle_chunk_complete() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'manga-chapter-uploader')));
        }
        
        $upload_id = isset($_POST['upload_id']) ? sanitize_text_field($_POST['upload_id']) : '';
        $upload_dir = $this->temp_dir . '/' . $upload_id;
        $meta_file = $upload_dir . '/meta.json';
        
        if (!file_exists($meta_file)) {
            wp_send_json_error(array('message' => __('Upload session not found', 'manga-chapter-uploader')));
        }
        
        $meta = json_decode(file_get_contents($meta_file), true);
        
        if (count($meta['uploaded_chunks']) !== $meta['total_chunks']) {
            wp_send_json_error(array('message' => __('Not all chunks uploaded', 'manga-chapter-uploader')));
        }
        
        $final_file = $upload_dir . '/' . $meta['filename'];
        $output = fopen($final_file, 'wb');
        
        if (!$output) {
            wp_send_json_error(array('message' => __('Could not create output file', 'manga-chapter-uploader')));
        }
        
        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            $chunk_file = $upload_dir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            if (!file_exists($chunk_file)) {
                fclose($output);
                wp_send_json_error(array('message' => sprintf(__('Missing chunk %d', 'manga-chapter-uploader'), $i)));
            }
            
            $chunk_data = file_get_contents($chunk_file);
            fwrite($output, $chunk_data);
            unlink($chunk_file);
        }
        
        fclose($output);
        unlink($meta_file);
        
        MCU_Logger::info('Chunk upload completed', array(
            'upload_id' => $upload_id,
            'filename' => $meta['filename'],
            'size' => filesize($final_file)
        ));
        
        wp_send_json_success(array(
            'file_path' => $final_file,
            'filename' => $meta['filename'],
            'upload_id' => $upload_id
        ));
    }
    
    public function handle_chunk_cancel() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        $upload_id = isset($_POST['upload_id']) ? sanitize_text_field($_POST['upload_id']) : '';
        $upload_dir = $this->temp_dir . '/' . $upload_id;
        
        if (is_dir($upload_dir)) {
            $this->cleanup_upload_dir($upload_dir);
        }
        
        wp_send_json_success(array('message' => __('Upload cancelled', 'manga-chapter-uploader')));
    }
    
    public function cleanup_upload_dir($dir) {
        if (!is_dir($dir)) return;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        
        rmdir($dir);
    }
    
    public function cleanup_old_uploads($hours = 24) {
        if (!is_dir($this->temp_dir)) return 0;
        
        $cleaned = 0;
        $threshold = time() - ($hours * 3600);
        $dirs = glob($this->temp_dir . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $meta_file = $dir . '/meta.json';
            if (file_exists($meta_file)) {
                $meta = json_decode(file_get_contents($meta_file), true);
                if (isset($meta['last_activity']) && $meta['last_activity'] < $threshold) {
                    $this->cleanup_upload_dir($dir);
                    $cleaned++;
                }
            } elseif (filemtime($dir) < $threshold) {
                $this->cleanup_upload_dir($dir);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}
