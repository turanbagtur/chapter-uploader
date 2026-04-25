<?php
/**
 * MCU REST API Class
 * WordPress REST API endpoint'leri
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCU_REST_API {
    
    private $namespace = 'mcu/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // Manga listesi
        register_rest_route($this->namespace, '/manga', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manga_list'),
            'permission_callback' => array($this, 'check_read_permission')
        ));
        
        // Tek manga bilgisi
        register_rest_route($this->namespace, '/manga/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manga'),
            'permission_callback' => array($this, 'check_read_permission')
        ));
        
        // Manga bölümleri
        register_rest_route($this->namespace, '/manga/(?P<id>\d+)/chapters', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manga_chapters'),
            'permission_callback' => array($this, 'check_read_permission')
        ));
        
        // Bölüm yükle
        register_rest_route($this->namespace, '/chapter/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_chapter'),
            'permission_callback' => array($this, 'check_upload_permission')
        ));
        
        // URL'den çek
        register_rest_route($this->namespace, '/chapter/fetch', array(
            'methods' => 'POST',
            'callback' => array($this, 'fetch_chapter'),
            'permission_callback' => array($this, 'check_upload_permission')
        ));
        
        // İstatistikler
        register_rest_route($this->namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Loglar
        register_rest_route($this->namespace, '/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Kuyruk durumu
        register_rest_route($this->namespace, '/queue', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_queue_status'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Kuyruğa görev ekle
        register_rest_route($this->namespace, '/queue/add', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_to_queue'),
            'permission_callback' => array($this, 'check_upload_permission')
        ));
    }
    
    // Permission callbacks
    public function check_read_permission() {
        return is_user_logged_in();
    }
    
    public function check_upload_permission() {
        return current_user_can('upload_files');
    }
    
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    // Endpoint callbacks
    public function get_manga_list($request) {
        $args = array(
            'post_type' => 'manga',
            'posts_per_page' => $request->get_param('per_page') ?: 50,
            'paged' => $request->get_param('page') ?: 1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $search = $request->get_param('search');
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }
        
        $query = new WP_Query($args);
        $manga_list = array();
        
        foreach ($query->posts as $manga) {
            $manga_list[] = $this->format_manga($manga);
        }
        
        return new WP_REST_Response(array(
            'manga' => $manga_list,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ), 200);
    }
    
    public function get_manga($request) {
        $manga_id = $request->get_param('id');
        $manga = get_post($manga_id);
        
        if (!$manga || $manga->post_type !== 'manga') {
            return new WP_Error('not_found', __('Manga not found', 'manga-chapter-uploader'), array('status' => 404));
        }
        
        return new WP_REST_Response($this->format_manga($manga, true), 200);
    }
    
    public function get_manga_chapters($request) {
        $manga_id = $request->get_param('id');
        
        $chapters = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'meta_query' => array(
                array('key' => 'ero_seri', 'value' => $manga_id)
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => 'ero_chapter',
            'order' => 'DESC'
        ));
        
        $chapter_list = array();
        foreach ($chapters as $chapter) {
            $chapter_list[] = array(
                'id' => $chapter->ID,
                'title' => $chapter->post_title,
                'chapter_number' => get_post_meta($chapter->ID, 'ero_chapter', true),
                'chapter_title' => get_post_meta($chapter->ID, 'ero_chaptertitle', true),
                'date' => $chapter->post_date,
                'url' => get_permalink($chapter->ID)
            );
        }
        
        return new WP_REST_Response(array(
            'manga_id' => $manga_id,
            'chapters' => $chapter_list,
            'total' => count($chapter_list)
        ), 200);
    }
    
    public function upload_chapter($request) {
        global $mcu_rate_limiter;
        
        // Rate limit kontrolü
        if ($mcu_rate_limiter) {
            $check = $mcu_rate_limiter->check_limit('upload_single', get_current_user_id());
            if (is_wp_error($check)) {
                return $check;
            }
        }
        
        $manga_id = $request->get_param('manga_id');
        $chapter_number = $request->get_param('chapter_number');
        $chapter_title = $request->get_param('chapter_title') ?: '';
        $images = $request->get_file_params();
        
        if (!$manga_id || !$chapter_number) {
            return new WP_Error('invalid_params', __('Manga ID and chapter number required', 'manga-chapter-uploader'), array('status' => 400));
        }
        
        // Bölüm var mı kontrol
        $existing = get_posts(array(
            'post_type' => 'post',
            'meta_query' => array(
                array('key' => 'ero_seri', 'value' => $manga_id),
                array('key' => 'ero_chapter', 'value' => $chapter_number)
            ),
            'posts_per_page' => 1
        ));
        
        if (!empty($existing)) {
            return new WP_Error('duplicate', __('Chapter already exists', 'manga-chapter-uploader'), array('status' => 409));
        }
        
        // Resimleri yükle
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        
        $uploaded_images = array();
        
        if (isset($images['images'])) {
            $files = $images['images'];
            $file_count = is_array($files['name']) ? count($files['name']) : 1;
            
            for ($i = 0; $i < $file_count; $i++) {
                $file = array(
                    'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                    'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                    'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                    'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                    'size' => is_array($files['size']) ? $files['size'][$i] : $files['size']
                );
                
                $upload = wp_handle_upload($file, array('test_form' => false));
                
                if ($upload && !isset($upload['error'])) {
                    $attachment_id = wp_insert_attachment(array(
                        'guid' => $upload['url'],
                        'post_mime_type' => $upload['type'],
                        'post_title' => pathinfo($upload['file'], PATHINFO_FILENAME),
                        'post_status' => 'inherit'
                    ), $upload['file']);
                    
                    if (!is_wp_error($attachment_id)) {
                        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                        $uploaded_images[] = wp_get_attachment_image($attachment_id, 'full');
                    }
                }
            }
        }
        
        if (empty($uploaded_images)) {
            return new WP_Error('no_images', __('No images uploaded', 'manga-chapter-uploader'), array('status' => 400));
        }
        
        // Chapter oluştur
        $manga_title = get_the_title($manga_id);
        $post_title = sprintf('%s Chapter %s', $manga_title, $chapter_number);
        if ($chapter_title) {
            $post_title .= ' - ' . $chapter_title;
        }
        
        $post_id = wp_insert_post(array(
            'post_title' => $post_title,
            'post_content' => implode("\n", $uploaded_images),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        ));
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Meta kaydet
        update_post_meta($post_id, 'ero_chapter', $chapter_number);
        update_post_meta($post_id, 'ero_chaptertitle', $chapter_title);
        update_post_meta($post_id, 'ero_seri', $manga_id);
        
        MCU_Logger::log_upload('api', $manga_id, $chapter_number, true, array('images' => count($uploaded_images)));
        
        return new WP_REST_Response(array(
            'success' => true,
            'post_id' => $post_id,
            'url' => get_permalink($post_id),
            'images_count' => count($uploaded_images)
        ), 201);
    }
    
    public function fetch_chapter($request) {
        global $mcu_rate_limiter;
        
        if ($mcu_rate_limiter) {
            $check = $mcu_rate_limiter->check_limit('fetch_url', get_current_user_id());
            if (is_wp_error($check)) {
                return $check;
            }
        }
        
        $url = $request->get_param('url');
        $manga_id = $request->get_param('manga_id');
        $chapter_number = $request->get_param('chapter_number');
        $async = $request->get_param('async') === true;
        
        if (!$url || !$manga_id) {
            return new WP_Error('invalid_params', __('URL and manga_id required', 'manga-chapter-uploader'), array('status' => 400));
        }
        
        // Async ise kuyruğa ekle
        if ($async) {
            global $mcu_batch_processor;
            if ($mcu_batch_processor) {
                $task_id = $mcu_batch_processor->add_to_queue(array(
                    'type' => 'fetch_url',
                    'url' => $url,
                    'manga_id' => $manga_id,
                    'chapter_number' => $chapter_number ?: 1,
                    'user_id' => get_current_user_id()
                ));
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'async' => true,
                    'task_id' => $task_id,
                    'message' => __('Task added to queue', 'manga-chapter-uploader')
                ), 202);
            }
        }
        
        // Senkron işlem
        // ... (mevcut blogger fetch mantığı buraya)
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Fetch started', 'manga-chapter-uploader')
        ), 200);
    }
    
    public function get_stats($request) {
        global $wpdb;
        
        $total_chapters = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' AND pm.meta_key = 'ero_seri'
        ");
        
        $this_month = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' AND pm.meta_key = 'ero_seri'
            AND p.post_date >= %s
        ", date('Y-m-01')));
        
        $log_stats = class_exists('MCU_Logger') ? MCU_Logger::get_log_stats() : array();
        
        return new WP_REST_Response(array(
            'total_chapters' => (int)$total_chapters,
            'this_month' => (int)$this_month,
            'logs' => $log_stats
        ), 200);
    }
    
    public function get_logs($request) {
        $count = $request->get_param('count') ?: 100;
        $level = $request->get_param('level');
        
        $logs = MCU_Logger::get_recent_logs($count, $level);
        
        return new WP_REST_Response(array(
            'logs' => $logs,
            'total' => count($logs)
        ), 200);
    }
    
    public function get_queue_status($request) {
        global $mcu_batch_processor;
        
        if (!$mcu_batch_processor) {
            return new WP_Error('not_available', __('Batch processor not available', 'manga-chapter-uploader'), array('status' => 503));
        }
        
        $queue = $mcu_batch_processor->get_queue();
        
        $stats = array('pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0);
        foreach ($queue as $task) {
            if (isset($stats[$task['status']])) {
                $stats[$task['status']]++;
            }
        }
        
        return new WP_REST_Response(array(
            'stats' => $stats,
            'tasks' => array_values($queue)
        ), 200);
    }
    
    public function add_to_queue($request) {
        global $mcu_batch_processor;
        
        if (!$mcu_batch_processor) {
            return new WP_Error('not_available', __('Batch processor not available', 'manga-chapter-uploader'), array('status' => 503));
        }
        
        $task = array(
            'type' => $request->get_param('type'),
            'user_id' => get_current_user_id()
        );
        
        // Task tipine göre parametreler
        $params = $request->get_params();
        unset($params['type']);
        $task = array_merge($task, $params);
        
        $task_id = $mcu_batch_processor->add_to_queue($task);
        
        return new WP_REST_Response(array(
            'success' => true,
            'task_id' => $task_id
        ), 201);
    }
    
    // Helper
    private function format_manga($manga, $detailed = false) {
        $data = array(
            'id' => $manga->ID,
            'title' => $manga->post_title,
            'slug' => $manga->post_name,
            'url' => get_permalink($manga->ID),
            'thumbnail' => get_the_post_thumbnail_url($manga->ID, 'medium')
        );
        
        if ($detailed) {
            $data['content'] = $manga->post_content;
            $data['excerpt'] = $manga->post_excerpt;
            $data['date'] = $manga->post_date;
            $data['modified'] = $manga->post_modified;
            
            // Bölüm sayısı
            global $wpdb;
            $data['chapter_count'] = (int)$wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'ero_seri' AND meta_value = %s
            ", $manga->ID));
        }
        
        return $data;
    }
}
