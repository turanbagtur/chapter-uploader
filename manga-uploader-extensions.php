<?php
/**
 * Manga Chapter Uploader - Extensions & Advanced Features
 * Bu dosya ana eklentiye dahil edilerek gelişmiş özellikleri aktif hale getirir
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Çoklu Bölüm ZIP İşleme Sınıfı
class MCU_ZipProcessor {
    
    public function process_zip_upload($zip_file, $manga_id, $chapter_category = 0, $push_to_latest = false, $chapter_prefix = 'chapter') {
        // ZIP class kontrolü
        if (!class_exists('ZipArchive')) {
            return array('success' => false, 'message' => 'ZIP extension is not enabled on the server.');
        }

        // Dosya yükleme hatalarını kontrol et
        if ($zip_file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => sprintf('ZIP file upload error (PHP Code: %s)', $zip_file['error']));
        }

        // Dosya boyutu kontrolü (100MB limit)
        $max_zip_size = 100 * 1024 * 1024; // 100MB
        if ($zip_file['size'] > $max_zip_size) {
            return array('success' => false, 'message' => sprintf('ZIP file is too large. Maximum size: %s', size_format($max_zip_size)));
        }

        // Geçici dizin oluştur
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/temp_manga_' . uniqid();
        
        if (!wp_mkdir_p($temp_dir)) {
            return array('success' => false, 'message' => 'Could not create temporary extraction directory.');
        }

        // ZIP dosyasını geçici konuma taşı
        $zip_path = $temp_dir . '/manga_chapters.zip';
        if (!move_uploaded_file($zip_file['tmp_name'], $zip_path)) {
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'Could not move ZIP file to temporary directory.');
        }

        // ZIP dosyasını aç
        $zip = new ZipArchive();
        $zip_result = $zip->open($zip_path);
        
        if ($zip_result !== TRUE) {
            $this->cleanup_temp_dir($temp_dir);
            $error_msg = 'Could not open ZIP file. Error code: ' . $zip_result;
            if ($zip_result === ZipArchive::ER_NOZIP) {
                $error_msg .= ' (Not a valid ZIP archive)';
            }
            return array('success' => false, 'message' => $error_msg);
        }

        // ZIP içeriğini çıkar
        $extract_path = $temp_dir . '/extracted';
        if (!$zip->extractTo($extract_path)) {
            $zip->close();
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'Could not extract ZIP contents.');
        }
        $zip->close();

        // WordPress dosya işleme fonksiyonlarını dahil et
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $results = array();
        $successful_uploads = 0;
        $manga_title = get_the_title($manga_id);

        // Çıkarılan klasörleri tara
        $chapter_folders = $this->get_chapter_folders($extract_path);

        foreach ($chapter_folders as $folder_name) {
            $folder_path = $extract_path . '/' . $folder_name;
            
            // Klasör adından bölüm numarasını çıkar
            $chapter_number = $this->extract_chapter_number_from_folder($folder_name);
            
            if ($chapter_number === false) {
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('Invalid chapter folder name: %s. Must start with a number.', $folder_name),
                    'folder' => $folder_name
                );
                continue;
            }

            // Aynı bölümün zaten var olup olmadığını kontrol et
            if ($this->chapter_exists($manga_id, $chapter_number)) {
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('Chapter %s already exists, skipping.', $chapter_number),
                    'folder' => $folder_name
                );
                continue;
            }

            // Klasördeki resimleri topla
            $images = $this->get_images_from_folder($folder_path);
            
            if (empty($images)) {
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('No images found in folder: %s', $folder_name),
                    'folder' => $folder_name
                );
                continue;
            }

            // Resimleri yükle ve bölüm oluştur
            $chapter_result = $this->create_chapter_from_images($manga_id, $chapter_number, $folder_name, $images, $chapter_category, $chapter_prefix);
            
            if ($chapter_result['success']) {
                $results[] = array(
                    'status' => 'success',
                    'message' => sprintf('Chapter %s uploaded successfully.', $chapter_number),
                    'post_link' => $chapter_result['post_link'],
                    'folder' => $folder_name
                );
                $successful_uploads++;
            } else {
                $results[] = array(
                    'status' => 'error',
                    'message' => $chapter_result['message'],
                    'folder' => $folder_name
                );
            }
        }

        // Ana sayfa güncelleme
        if ($successful_uploads > 0 && $push_to_latest) {
            $uploader = new MangaChapterUploader();
            if (method_exists($uploader, 'push_series_to_latest_update')) {
                $uploader->push_series_to_latest_update($manga_id);
            }
        }

        // Geçici dosyaları temizle
        $this->cleanup_temp_dir($temp_dir);

        return array(
            'success' => $successful_uploads > 0,
            'message' => sprintf('Processing completed. %d chapters uploaded.', $successful_uploads),
            'results' => $results
        );
    }
    
    private function get_chapter_folders($extract_path) {
        $chapter_folders = array();
        if (is_dir($extract_path)) {
            $folders = scandir($extract_path);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') continue;
                
                $folder_path = $extract_path . '/' . $folder;
                if (is_dir($folder_path)) {
                    $chapter_folders[] = $folder;
                }
            }
        }
        
        // Klasör adlarını sayısal sıralama ile sırala (1, 2, 3, 10, 11 gibi)
        usort($chapter_folders, function($a, $b) {
            // Klasör adlarından sayıları çıkar
            $num_a = $this->extract_chapter_number_from_folder($a);
            $num_b = $this->extract_chapter_number_from_folder($b);
            
            if ($num_a === false) $num_a = PHP_INT_MAX;
            if ($num_b === false) $num_b = PHP_INT_MAX;
            
            return $num_a <=> $num_b;
        });
        
        return array_values($chapter_folders);
    }
    
    private function extract_chapter_number_from_folder($folder_name) {
        // Klasör adından bölüm numarasını çıkar
        $patterns = array(
            '/^(\d+(?:\.\d+)?)/',  // Başlangıçta sayı
            '/^(?:bolum|bölüm|chapter|ch)[\s\-_]*(\d+(?:\.\d+)?)/i'  // "bölüm 1", "chapter-2" gibi
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $folder_name, $matches)) {
                $number = floatval($matches[1]);
                if ($number > 0 && $number <= 9999) {
                    return $number;
                }
            }
        }
        
        return false;
    }
    
    private function chapter_exists($manga_id, $chapter_number) {
        $existing = get_posts(array(
            'post_type' => 'post',
            'meta_query' => array(
                array('key' => 'ero_seri', 'value' => $manga_id, 'compare' => '='),
                array('key' => 'ero_chapter', 'value' => $chapter_number, 'compare' => '=')
            ),
            'posts_per_page' => 1
        ));
        
        return !empty($existing);
    }
    
    private function get_images_from_folder($folder_path) {
        $images = array();
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (!is_dir($folder_path)) {
            return $images;
        }
        
        $files = scandir($folder_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $folder_path . '/' . $file;
            if (is_file($file_path)) {
                $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($file_extension, $allowed_extensions)) {
                    $images[] = $file_path;
                }
            }
        }
        
        // Dosyaları doğal sıralama ile sırala (1.jpg, 2.jpg, 10.jpg)
        natsort($images);
        return array_values($images);
    }
    
private function create_chapter_from_images($manga_id, $chapter_number, $folder_name, $images, $chapter_category, $chapter_prefix = 'chapter') {
        $uploaded_images = array();
        $upload_overrides = array('test_form' => false);

        foreach ($images as $image_path) {
            $file_array = array(
                'name' => basename($image_path),
                'tmp_name' => $image_path,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($image_path)
            );

            // Dosya türünü manuel belirle
            $file_info = wp_check_filetype($file_array['name']);
            $file_array['type'] = $file_info['type'];

            // wp_handle_sideload kullan (geçici dosya için)
            $sideload = wp_handle_sideload($file_array, $upload_overrides);

            if (!empty($sideload['error'])) {
                continue; // Hata varsa bu dosyayı atla
            }

            $attachment_id = wp_insert_attachment(array(
                'guid' => $sideload['url'],
                'post_mime_type' => $sideload['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($sideload['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            ), $sideload['file']);

            if (!is_wp_error($attachment_id)) {
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $sideload['file']));
                $uploaded_images[] = wp_get_attachment_image($attachment_id, 'full');
                
                // Resim işleme
                global $mcu_image_processor;
                if ($mcu_image_processor) {
                    $mcu_image_processor->process_image($sideload['file'], $attachment_id);
                }
            }
        }

        if (empty($uploaded_images)) {
            return array('success' => false, 'message' => 'No images could be processed');
        }

        // Bölüm başlığını oluştur
        $chapter_title = '';
        if (strpos($folder_name, '-') !== false) {
            $title_parts = explode('-', $folder_name, 2);
            if (count($title_parts) > 1) {
                $chapter_title = trim($title_parts[1]);
            }
        }

        // Başlık oluşturma - DÜZELTİLMİŞ: Gelen chapter_prefix'i kullan
        $manga_title = get_the_title($manga_id);
        $prefix_map = array(
            'chapter' => 'Chapter',
            'bolum' => 'Bölüm',
            'bölüm' => 'Bölüm',
            'chapitre' => 'Chapitre',
            'capitulo' => 'Capítulo',
            'kapittel' => 'Kapittel',
            'adhyay' => 'अध्याय',
            'fasl' => 'فصل',
            'zhang' => '章'
        );
        
        $prefix_text = isset($prefix_map[$chapter_prefix]) ? $prefix_map[$chapter_prefix] : ucfirst($chapter_prefix);
        $post_title = sprintf('%s %s %s', $manga_title, $prefix_text, $chapter_number);
        
        if (!empty($chapter_title)) {
            $post_title .= ' - ' . $chapter_title;
        }

        // Bölüm post'unu oluştur
        $new_post = array(
            'post_title' => $post_title,
            'post_content' => implode("\n", $uploaded_images),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        );

        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            return array('success' => false, 'message' => 'Error creating chapter post');
        }

        // Kategori ekle
        if (!empty($chapter_category)) {
            wp_set_post_categories($new_post_id, array($chapter_category));
        }

        // Meta verileri kaydet - MangaChapterUploader instance'ı oluştur
        $uploader = new MangaChapterUploader();
        if (method_exists($uploader, 'save_theme_compatible_meta')) {
            $uploader->save_theme_compatible_meta($new_post_id, $chapter_number, $chapter_title, $manga_id, $uploaded_images);
        } else {
            // Fallback - manuel meta kaydetme
            update_post_meta($new_post_id, 'ero_chapter', $chapter_number);
            update_post_meta($new_post_id, 'ero_chaptertitle', $chapter_title);
            update_post_meta($new_post_id, 'ero_seri', $manga_id);
            update_post_meta($new_post_id, '_chapter_order', floatval($chapter_number));
            update_post_meta($new_post_id, '_manga_series_id', $manga_id);
            update_post_meta($new_post_id, '_upload_timestamp', current_time('mysql'));
            if (!empty($uploaded_images)) {
                update_post_meta($new_post_id, '_chapter_image_count', count($uploaded_images));
            }
        }

        return array(
            'success' => true,
            'post_link' => get_permalink($new_post_id)
        );
    }
    
    private function cleanup_temp_dir($dir_path) {
        if (!is_dir($dir_path)) {
            return;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir_path);
    }
}

// Resim İşleme ve Optimizasyon Sınıfı
class MCU_ImageProcessor {
    
    public function __construct() {
        // Watermark ayarları kaldırıldı
    }
    
    public function process_image($image_path, $attachment_id = null) {
        if (!file_exists($image_path)) {
            return false;
        }
        
        $processed = false;
        
        // WebP dönüştürme
        if (get_option('mcu_auto_webp_convert', true)) {
            $processed = $this->convert_to_webp($image_path) || $processed;
        }
        
        // Progressive JPEG
        if (get_option('mcu_progressive_jpeg', true)) {
            $processed = $this->make_progressive_jpeg($image_path) || $processed;
        }
        
        return $processed;
    }
    
    private function convert_to_webp($image_path) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $image_info = getimagesize($image_path);
        if (!$image_info) return false;
        
        $mime_type = $image_info['mime'];
        
        // Eğer zaten WebP ise işlem yapma
        if ($mime_type === 'image/webp') {
            return false;
        }
        
        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($image_path);
                break;
            default:
                return false;
        }
        
        if ($image) {
            $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $image_path);
            $quality = get_option('mcu_image_quality', 85);
            $success = imagewebp($image, $webp_path, $quality);
            imagedestroy($image);
            
            if ($success && file_exists($webp_path) && filesize($webp_path) < filesize($image_path)) {
                // WebP daha küçükse orijinali değiştir
                unlink($image_path);
                rename($webp_path, $image_path);
                return true;
            } elseif (file_exists($webp_path)) {
                // WebP büyükse sil
                unlink($webp_path);
            }
        }
        
        return false;
    }
    
    private function make_progressive_jpeg($image_path) {
        $image_info = getimagesize($image_path);
        if (!$image_info || $image_info['mime'] !== 'image/jpeg') {
            return false;
        }
        
        $image = imagecreatefromjpeg($image_path);
        if (!$image) return false;
        
        imageinterlace($image, 1); // Progressive yapar
        $quality = get_option('mcu_image_quality', 95);
        $success = imagejpeg($image, $image_path, $quality);
        imagedestroy($image);
        
        return $success;
    }
}

// Gelişmiş İçerik Çekme Sınıfı
class MCU_AdvancedFetcher {
    
    private $supported_sites = array(
        'mangadex.org',
        'webtoons.com',
        'mangaplus.shueisha.co.jp'
    );
    
    public function fetch_from_url($url, $manga_id, $chapter_number = null) {
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            return array('success' => false, 'message' => 'Invalid URL');
        }
        
        $host = $parsed_url['host'];
        
        // Alt domain'leri temizle
        $host = preg_replace('/^www\./', '', $host);
        
        switch ($host) {
            case 'mangadx.org':
            case 'mangadex.org':
                return $this->fetch_from_mangadex($url, $manga_id, $chapter_number);
            case 'webtoons.com':
                return $this->fetch_from_webtoons($url, $manga_id, $chapter_number);
            default:
                // Generic fetcher'ı kullan
                return $this->fetch_generic($url, $manga_id, $chapter_number);
        }
    }
    
    private function fetch_from_mangadex($url, $manga_id, $chapter_number) {
        // MangaDx basit çekme (API olmadan)
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Could not access MangaDx');
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // MangaDx resim pattern'leri
        $image_urls = array();
        preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']*mangadx[^"\']*\.(?:jpg|jpeg|png|gif|webp))["\'][^>]*>/i', $html, $matches);
        
        if (!empty($matches[1])) {
            $image_urls = $matches[1];
        }
        
        return array(
            'success' => count($image_urls) > 0,
            'images' => $image_urls,
            'chapter' => $chapter_number ?: $this->extract_chapter_number($url)
        );
    }
    
    private function fetch_from_webtoons($url, $manga_id, $chapter_number) {
        // Webtoons için özel parsing
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Could not access Webtoons');
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Webtoons resimleri için özel pattern
        $image_urls = array();
        preg_match_all('/<img[^>]+data-url\s*=\s*["\']([^"\']+)["\'][^>]*class="[^"]*_images[^"]*"/i', $html, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // URL'leri temizle
                $url = str_replace('&amp;', '&', $url);
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $image_urls[] = $url;
                }
            }
        }
        
        // Başlığı çıkar
        preg_match('/<title>([^<]+)<\/title>/i', $html, $title_matches);
        $title = !empty($title_matches[1]) ? trim($title_matches[1]) : '';
        
        return array(
            'success' => count($image_urls) > 0,
            'images' => $image_urls,
            'title' => $title,
            'chapter' => $chapter_number ?: $this->extract_chapter_from_webtoons_url($url)
        );
    }
    
    private function fetch_generic($url, $manga_id, $chapter_number) {
        // Genel site çekme
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9'
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $html = wp_remote_retrieve_body($response);
        $image_urls = $this->extract_images_from_html($html, $url);
        
        return array(
            'success' => count($image_urls) > 0,
            'images' => $image_urls,
            'chapter' => $chapter_number ?: $this->extract_chapter_number($url)
        );
    }
    
    private function extract_chapter_from_webtoons_url($url) {
        if (preg_match('/episode_no=(\d+)/i', $url, $matches)) {
            return intval($matches[1]);
        }
        return 1;
    }
    
    private function extract_images_from_html($html, $base_url) {
        $image_urls = array();
        
        // Normal img src
        preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        if (!empty($matches[1])) {
            $image_urls = array_merge($image_urls, $matches[1]);
        }
        
        // Lazy loading
        preg_match_all('/<img[^>]+data-src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        if (!empty($matches[1])) {
            $image_urls = array_merge($image_urls, $matches[1]);
        }
        
        // Blogger özel
        preg_match_all('/https?:\/\/(?:lh\d+\.googleusercontent\.com|blogger\.googleusercontent\.com|[^.\s]+\.bp\.blogspot\.com)[^\s"\'<>]*\.(?:jpg|jpeg|png|gif|webp)(?:[?#][^\s"\'<>]*)?/i', $html, $matches);
        if (!empty($matches[0])) {
            $image_urls = array_merge($image_urls, $matches[0]);
        }
        
        // URL'leri temizle ve filtrele
        $cleaned_urls = array();
        foreach ($image_urls as $url) {
            $url = trim($url);
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            
            if (filter_var($url, FILTER_VALIDATE_URL) && $this->is_valid_image_url($url)) {
                $cleaned_urls[] = $url;
            }
        }
        
        return array_values(array_unique($cleaned_urls));
    }
    
    private function is_valid_image_url($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $valid_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (in_array($extension, $valid_extensions)) {
            return true;
        }
        
        // Bazı sitelerde extension URL'de olmayabilir
        $unwanted_patterns = array('avatar', 'icon', 'logo', 'banner', 'button', '/s72-c/', '/s150-c/');
        foreach ($unwanted_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function extract_chapter_number($url) {
        $patterns = array(
            '/(?:chapter|ch|episode|ep|bolum|bölüm)[\-_\s]*(\d+(?:\.\d+)?)/i',
            '/(\d+(?:\.\d+)?)(?:[\-_\s]*(?:chapter|ch|episode|ep|bolum|bölüm))/i',
            '/\/(\d+(?:\.\d+)?)(?:[\-_\.\s]|$)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $number = floatval($matches[1]);
                if ($number > 0 && $number <= 9999) {
                    return $number;
                }
            }
        }
        
        return 1;
    }
}

// Programlanmış Yayınlama Sınıfı
class MCU_ScheduledPublisher {
    
    public function schedule_chapter($post_id, $publish_time) {
        $timestamp = strtotime($publish_time);
        if ($timestamp === false || $timestamp <= time()) {
            return false;
        }
        
        // Post'u draft olarak ayarla
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'draft'
        ));
        
        // Zamanlaï event oluştur
        wp_schedule_single_event($timestamp, 'mcu_publish_scheduled_chapter', array($post_id));
        
        // Scheduled meta ekle
        update_post_meta($post_id, '_mcu_scheduled_publish', $publish_time);
        update_post_meta($post_id, '_mcu_original_author', get_current_user_id());
        
        return true;
    }
    
    public function publish_scheduled_chapter($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'draft') {
            return false;
        }
        
        // Post'u yayınla
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1)
        ));
        
        // Ana sayfaya push et
        $manga_id = get_post_meta($post_id, 'ero_seri', true);
        if ($manga_id) {
            // Ana plugin'den push fonksiyonunu çağır
            $uploader = new MangaChapterUploader();
            if (method_exists($uploader, 'push_series_to_latest_update')) {
                $uploader->push_series_to_latest_update($manga_id, $post_id);
            }
        }
        
        // Scheduled meta'yı temizle
        delete_post_meta($post_id, '_mcu_scheduled_publish');
        
        // Email bildirimi (opsiyonel)
        if (get_option('mcu_email_notifications', false)) {
            $this->send_publish_notification($post_id);
        }
        
        return true;
    }
    
    private function send_publish_notification($post_id) {
        $post = get_post($post_id);
        $admin_email = get_option('admin_email');
        $subject = sprintf('Chapter Published: %s', $post->post_title);
        $message = sprintf(
            "Your scheduled chapter has been published:\n\nTitle: %s\nURL: %s\n\nPublished at: %s",
            $post->post_title,
            get_permalink($post_id),
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    public function get_scheduled_chapters() {
        return get_posts(array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'meta_query' => array(
                array(
                    'key' => '_mcu_scheduled_publish',
                    'compare' => 'EXISTS'
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_mcu_scheduled_publish',
            'order' => 'ASC'
        ));
    }
}

// Global olarak erişilebilir instance'lar
global $mcu_image_processor, $mcu_advanced_fetcher, $mcu_scheduled_publisher, $mcu_zip_processor;

// Instance'ları oluştur
$mcu_image_processor = new MCU_ImageProcessor();
$mcu_advanced_fetcher = new MCU_AdvancedFetcher();
$mcu_scheduled_publisher = new MCU_ScheduledPublisher();
$mcu_zip_processor = new MCU_ZipProcessor();

// Ana plugin'e entegrasyon hook'ları
add_action('mcu_process_image', array($mcu_image_processor, 'process_image'), 10, 2);
add_action('mcu_publish_scheduled_chapter', array($mcu_scheduled_publisher, 'publish_scheduled_chapter'), 10, 1);