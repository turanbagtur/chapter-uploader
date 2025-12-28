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
        // Uzun işlemler için zaman limitini artır
        @set_time_limit(600); // 10 dakika
        @ini_set('max_execution_time', 600);
        
        // ZIP class kontrolü
        if (!class_exists('ZipArchive')) {
            return array('success' => false, 'message' => 'ZIP extension is not enabled on the server.');
        }

        // Dosya yükleme hatalarını kontrol et
        if ($zip_file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => sprintf('ZIP file upload error (PHP Code: %s)', $zip_file['error']));
        }

        // GÜNCELLENMİŞ: Ayarlardan ZIP boyut limitini al
        $max_zip_size = get_option('mcu_max_zip_size', 524288000); // 500MB varsayılan
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

        // WordPress dosya işleme fonksiyonlarını dahil et (doğru sırada)
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $results = array();
        $successful_uploads = 0;
        $manga_title = get_the_title($manga_id);

        // Çıkarılan klasörleri tara - DİKKATLE FOLDER PATH KONTROLÜ
        $chapter_folders = $this->get_chapter_folders($extract_path);

        // DEBUG: Toplam klasör sayısı
        MCU_Logger::info('ZIP processing started', array(
            'extract_path' => $extract_path,
            'total_folders' => count($chapter_folders),
            'folder_list' => $chapter_folders
        ));

        foreach ($chapter_folders as $folder_name) {
            // DOĞRU PATH OLUŞTURMA - DIRECTORY_SEPARATOR kullan
            $folder_path = $extract_path . DIRECTORY_SEPARATOR . $folder_name;
            
            // DEBUG: Her klasör işlenirken logla
            MCU_Logger::debug('Processing chapter folder', array(
                'folder_name' => $folder_name,
                'folder_path' => $folder_path,
                'folder_exists' => is_dir($folder_path)
            ));
            
            // Klasör adından bölüm numarasını çıkar
            $chapter_number = $this->extract_chapter_number_from_folder($folder_name);
            
            if ($chapter_number === false) {
                MCU_Logger::warning('Invalid chapter folder name', array('folder' => $folder_name));
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('Invalid chapter folder name: %s. Must start with a number.', $folder_name),
                    'folder' => $folder_name
                );
                continue;
            }

            // Aynı bölümün zaten var olup olmadığını kontrol et
            if ($this->chapter_exists($manga_id, $chapter_number)) {
                MCU_Logger::info('Chapter already exists, skipping', array('chapter' => $chapter_number));
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('Chapter %s already exists, skipping.', $chapter_number),
                    'folder' => $folder_name
                );
                continue;
            }

            // KRITIK: Klasördeki resimleri topla - FOLDER PATH DOĞRULAĞI
            $images = $this->get_images_from_folder($folder_path);
            
            if (empty($images)) {
                MCU_Logger::warning('No images found in folder', array(
                    'folder' => $folder_name,
                    'path' => $folder_path
                ));
                $results[] = array(
                    'status' => 'error',
                    'message' => sprintf('No images found in folder: %s', $folder_name),
                    'folder' => $folder_name
                );
                continue;
            }

            // GÖRSEL TEKRARLANMA SORUNUNUN ÇÖZÜMÜ: Her bölüm için benzersiz resim listesi
            MCU_Logger::info('Creating chapter from images', array(
                'folder' => $folder_name,
                'chapter_number' => $chapter_number,
                'image_count' => count($images),
                'sample_images' => array_slice(array_map('basename', $images), 0, 3)
            ));

            // Resimleri yükle ve bölüm oluştur
            $chapter_result = $this->create_chapter_from_images($manga_id, $chapter_number, $folder_name, $images, $chapter_category, $chapter_prefix);
            
            if ($chapter_result['success']) {
                MCU_Logger::info('Chapter created successfully', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'post_link' => $chapter_result['post_link']
                ));
                $results[] = array(
                    'status' => 'success',
                    'message' => sprintf('Chapter %s uploaded successfully.', $chapter_number),
                    'post_link' => $chapter_result['post_link'],
                    'folder' => $folder_name
                );
                $successful_uploads++;
            } else {
                MCU_Logger::error('Chapter creation failed', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'error' => $chapter_result['message']
                ));
                $results[] = array(
                    'status' => 'error',
                    'message' => $chapter_result['message'],
                    'folder' => $folder_name
                );
            }
            
            // BELLEK TEMİZLEME: Her bölümden sonra cache temizle
            if (function_exists('wp_cache_flush')) wp_cache_flush();
        }

        // ANA SAYFA GÜNCELLEMESİNİ SADECE FİNALİZE'DA YAP (Sıralama karışmasın diye)
        MCU_Logger::info('Batch upload completed, homepage update will be done in finalize', array(
            'manga_id' => $manga_id,
            'successful_uploads' => $successful_uploads
        ));

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
        $self = $this; // PHP closure için $this referansı
        usort($chapter_folders, function($a, $b) use ($self) {
            // Klasör adlarından sayıları çıkar
            $num_a = $self->extract_chapter_number_from_folder($a);
            $num_b = $self->extract_chapter_number_from_folder($b);
            
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
        
        // DEBUG: Folder path'i loglayalım
        MCU_Logger::debug('Getting images from folder', array('path' => $folder_path, 'exists' => is_dir($folder_path)));
        
        if (!is_dir($folder_path)) {
            MCU_Logger::warning('Folder not found', array('path' => $folder_path));
            return $images;
        }
        
        // Realpath ile gerçek path'i al
        $real_folder_path = realpath($folder_path);
        if (!$real_folder_path) {
            MCU_Logger::error('Invalid folder path', array('path' => $folder_path));
            return $images;
        }
        
        $files = scandir($real_folder_path);
        if ($files === false) {
            MCU_Logger::error('Cannot read folder', array('path' => $real_folder_path));
            return $images;
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $real_folder_path . DIRECTORY_SEPARATOR . $file;
            if (is_file($file_path)) {
                $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($file_extension, $allowed_extensions)) {
                    $images[] = $file_path;
                }
            }
        }
        
        // Dosyaları doğal sıralama ile sırala (1.jpg, 2.jpg, 10.jpg)
        natsort($images);
        $final_images = array_values($images);
        
        // DEBUG: Bulunan resimleri logla
        MCU_Logger::debug('Images found in folder', array(
            'folder' => basename($real_folder_path),
            'image_count' => count($final_images),
            'first_image' => !empty($final_images) ? basename($final_images[0]) : 'none'
        ));
        
        return $final_images;
    }
    
    private function create_chapter_from_images($manga_id, $chapter_number, $folder_name, $images, $chapter_category, $chapter_prefix = 'chapter') {
        $uploaded_images = array();
        $upload_overrides = array('test_form' => false);
        
        // DEBUG: İşlenen klasör ve resim bilgileri
        MCU_Logger::debug('Creating chapter from images', array(
            'folder' => $folder_name,
            'chapter' => $chapter_number,
            'image_count' => count($images),
            'first_image_path' => !empty($images) ? $images[0] : 'none'
        ));
        
        // PERFORMANS: Thumbnail oluşturmayı geçici olarak devre dışı bırak
        add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
        
        // Her resim yolunu debug et
        $image_index = 0;
        foreach ($images as $image_path) {
            $image_index++;
            
            // DEBUG: İşlenen resim yolunu logla
            MCU_Logger::debug('Processing image', array(
                'folder' => $folder_name,
                'chapter' => $chapter_number,
                'image_index' => $image_index,
                'image_path' => $image_path,
                'file_exists' => file_exists($image_path),
                'is_readable' => is_readable($image_path)
            ));
            
            // Dosya var mı kontrol et - GÜVENLİ YÖNTEM
            if (!file_exists($image_path) || !is_readable($image_path)) {
                MCU_Logger::warning('Image file not accessible', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'image_path' => $image_path
                ));
                continue;
            }
            
            // Dosya boyutunu güvenli şekilde al
            $file_size = @filesize($image_path);
            if ($file_size === false || $file_size === 0) {
                MCU_Logger::warning('Image file size invalid', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'image_path' => $image_path,
                    'file_size' => $file_size
                ));
                continue;
            }
            
            // Benzersiz dosya adı oluştur (çakışma önleme)
            $original_filename = basename($image_path);
            $unique_filename = $folder_name . '_' . $chapter_number . '_' . $image_index . '_' . $original_filename;
            
            $file_array = array(
                'name' => $unique_filename,
                'tmp_name' => $image_path,
                'error' => UPLOAD_ERR_OK,
                'size' => $file_size
            );

            $file_info = wp_check_filetype($file_array['name']);
            $file_array['type'] = $file_info['type'];

            $sideload = wp_handle_sideload($file_array, $upload_overrides);

            if (!empty($sideload['error'])) {
                MCU_Logger::error('Image sideload failed', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'image_path' => $image_path,
                    'error' => $sideload['error']
                ));
                continue;
            }

            $attachment_id = wp_insert_attachment(array(
                'guid' => $sideload['url'],
                'post_mime_type' => $sideload['type'],
                'post_title' => $folder_name . ' Chapter ' . $chapter_number . ' Image ' . $image_index,
                'post_content' => '',
                'post_status' => 'inherit'
            ), $sideload['file']);

            if (!is_wp_error($attachment_id)) {
                // PERFORMANS: Minimal metadata
                wp_update_attachment_metadata($attachment_id, array(
                    'file' => _wp_relative_upload_path($sideload['file'])
                ));
                $uploaded_images[] = wp_get_attachment_image($attachment_id, 'full');
                
                MCU_Logger::debug('Image uploaded successfully', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'attachment_id' => $attachment_id,
                    'image_index' => $image_index
                ));
            } else {
                MCU_Logger::error('Failed to create attachment', array(
                    'folder' => $folder_name,
                    'chapter' => $chapter_number,
                    'error' => $attachment_id->get_error_message()
                ));
            }
        }
        
        // Thumbnail filtresini geri al
        remove_filter('intermediate_image_sizes_advanced', '__return_empty_array');

        MCU_Logger::info('Chapter images processed', array(
            'folder' => $folder_name,
            'chapter' => $chapter_number,
            'total_images' => count($images),
            'uploaded_images' => count($uploaded_images)
        ));

        if (empty($uploaded_images)) {
            return array('success' => false, 'message' => 'No images could be processed for ' . $folder_name);
        }

        // Bölüm başlığını oluştur
        $chapter_title = '';
        if (strpos($folder_name, '-') !== false) {
            $title_parts = explode('-', $folder_name, 2);
            if (count($title_parts) > 1) {
                $chapter_title = trim($title_parts[1]);
            }
        }

        // Başlık oluşturma - Gelen chapter_prefix'i kullan
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

        // SIRALI POST TARİHİ HESAPLAMA - DOĞRU LOJİK DÜZELTME
        $base_time = current_time('timestamp');
        $chapter_num = floatval($chapter_number);
        
        // DOĞRU SIRALAMA: Yüksek bölüm = YENİ tarih (WordPress sıralaması için)
        $seconds_per_chapter = 5; // Her bölüm 5 saniye aralık
        $seconds_offset = ($chapter_num - 1) * $seconds_per_chapter;
        $chapter_timestamp = $base_time - 1800 + $seconds_offset; // 30dk öncesinden başla
        
        // GÜVENLİK: Gelecek tarihe çıkmasın (1dk önce max)
        if ($chapter_timestamp > $base_time - 60) {
            $chapter_timestamp = $base_time - 60;
        }
        
        $chapter_date = date('Y-m-d H:i:s', $chapter_timestamp);
        $chapter_date_gmt = gmdate('Y-m-d H:i:s', $chapter_timestamp);

        // Bölüm post'unu oluştur - DİREKT YAYINLAMA
        $new_post = array(
            'post_title' => $post_title,
            'post_content' => implode("\n", $uploaded_images),
            'post_status' => 'publish', // Direkt yayınla
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'post_date' => $chapter_date,
            'post_date_gmt' => $chapter_date_gmt,
            'post_modified' => $chapter_date,
            'post_modified_gmt' => $chapter_date_gmt
        );

        $new_post_id = wp_insert_post($new_post);
        
        // EK GÜVENCELİK: Post durumunu zorla publish yap
        if (!is_wp_error($new_post_id)) {
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                array(
                    'post_status' => 'publish', // Zorla yayınla
                    'post_date' => $chapter_date,
                    'post_date_gmt' => $chapter_date_gmt,
                    'post_modified' => $chapter_date,
                    'post_modified_gmt' => $chapter_date_gmt
                ),
                array('ID' => $new_post_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            MCU_Logger::debug('Chapter published immediately', array(
                'chapter' => $chapter_number,
                'post_id' => $new_post_id,
                'status' => 'publish',
                'timestamp' => $chapter_timestamp,
                'date' => $chapter_date
            ));
        }

        if (is_wp_error($new_post_id)) {
            return array('success' => false, 'message' => 'Error creating chapter post');
        }

        // Kategori ekle
        if (!empty($chapter_category)) {
            wp_set_post_categories($new_post_id, array($chapter_category));
        }

        // Meta verileri kaydet - global instance kullan
        global $manga_chapter_uploader;
        if ($manga_chapter_uploader && method_exists($manga_chapter_uploader, 'save_theme_compatible_meta')) {
            $manga_chapter_uploader->save_theme_compatible_meta($new_post_id, $chapter_number, $chapter_title, $manga_id, $uploaded_images);
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
        
        // GÜVENLİK: Path validation
        $upload_dir = wp_upload_dir();
        $base_temp_dir = $upload_dir['basedir'];
        
        // Sadece temp dizinleri altındaki dosyaları sil
        if (strpos(realpath($dir_path), realpath($base_temp_dir)) !== 0) {
            return;
        }
        
        // PERFORMANS KORUYUCU: Hızlı ve güvenli cleanup
        $this->safe_recursive_delete($dir_path);
    }
    
    // Yeni güvenli recursive delete fonksiyonu
    private function safe_recursive_delete($dir_path) {
        if (!is_dir($dir_path)) {
            return;
        }
        
        try {
            // Önce basit scandir ile kontrol et (daha hızlı)
            $items = @scandir($dir_path);
            if ($items === false) {
                // Dizin okunamıyor, basit silme dene
                @rmdir($dir_path);
                return;
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $item_path = $dir_path . DIRECTORY_SEPARATOR . $item;
                
                if (is_dir($item_path)) {
                    // Recursive olarak alt dizinleri sil
                    $this->safe_recursive_delete($item_path);
                } else {
                    // Dosyayı sil
                    @unlink($item_path);
                }
            }
            
            // Son olarak ana dizini sil
            @rmdir($dir_path);
            
        } catch (Exception $e) {
            // Bu normal bir durum (dizin zaten silinmiş), DEBUG level'da logla
            MCU_Logger::debug('Cleanup notice: ' . $e->getMessage(), array('dir' => basename($dir_path)));
            
            // Fallback: zorla silme dene
            @rmdir($dir_path);
        }
    }

    private function update_manga_timestamp($manga_id) {
        $manga_post = get_post($manga_id);
        if (!$manga_post || $manga_post->post_type !== 'manga') {
            return false;
        }

        $current_time = current_time('mysql');
        $current_time_gmt = current_time('mysql', 1);
        
        global $wpdb;
        
        // Post tarihlerini güncelle
        $wpdb->update(
            $wpdb->posts,
            array(
                'post_modified' => $current_time,
                'post_modified_gmt' => $current_time_gmt,
                'post_date' => $current_time,
                'post_date_gmt' => $current_time_gmt
            ),
            array('ID' => $manga_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Tüm olası meta key'leri güncelle
        $meta_keys = array('_last_updated', 'ts_edit_post_push_cb', '_latest_update', 'latest_update', '_manga_latest_update');
        foreach ($meta_keys as $key) {
            $value = ($key === 'ts_edit_post_push_cb') ? $current_time : $current_time;
            update_post_meta($manga_id, $key, $value);
            if (function_exists('rwmb_set_meta')) {
                rwmb_set_meta($manga_id, $key, $value);
            }
        }

        // Cache ve transient temizle
        clean_post_cache($manga_id);
        wp_cache_delete($manga_id, 'posts');
        wp_cache_delete($manga_id, 'post_meta');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        
        // Transient temizle
        delete_transient('manga_latest_updates');
        delete_transient('homepage_manga_list');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_manga%'");
        
        // Hook'ları tetikle
        do_action('save_post', $manga_id, $manga_post, true);
        do_action('save_post_manga', $manga_id, $manga_post, true);
        
        return true;
    }

    // AŞAMALI İŞLEME - Aşama 1: ZIP'i hazırla ve bölüm listesini döndür
    public function prepare_zip_upload($zip_file) {
        if (!class_exists('ZipArchive')) {
            return array('success' => false, 'message' => 'ZIP extension is not enabled.');
        }
        if ($zip_file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'ZIP upload error.');
        }
        $upload_dir = wp_upload_dir();
        $session_id = 'mcu_' . uniqid();
        $temp_dir = $upload_dir['basedir'] . '/temp_' . $session_id;
        if (!wp_mkdir_p($temp_dir)) {
            return array('success' => false, 'message' => 'Could not create temp directory.');
        }
        $zip_path = $temp_dir . '/chapters.zip';
        if (!move_uploaded_file($zip_file['tmp_name'], $zip_path)) {
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'Could not move ZIP file.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== TRUE) {
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'Could not open ZIP file.');
        }
        $extract_path = $temp_dir . '/extracted';
        if (!$zip->extractTo($extract_path)) {
            $zip->close();
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'Could not extract ZIP.');
        }
        $zip->close();
        @unlink($zip_path);
        $chapter_folders = $this->get_chapter_folders($extract_path);
        
        // SIRALI BÖLÜM İŞLEME İÇİN SIRALAMA
        $chapters = array();
        foreach ($chapter_folders as $folder) {
            $num = $this->extract_chapter_number_from_folder($folder);
            if ($num !== false) {
                $chapters[] = array('folder' => $folder, 'number' => $num);
            }
        }
        
        // Bölümleri sayısal olarak sırala (160, 161, 162, ... şeklinde)
        usort($chapters, function($a, $b) {
            return floatval($a['number']) <=> floatval($b['number']);
        });
        
        if (empty($chapters)) {
            $this->cleanup_temp_dir($temp_dir);
            return array('success' => false, 'message' => 'No valid chapter folders found.');
        }
        set_transient('mcu_session_' . $session_id, array('temp_dir' => $temp_dir, 'extract_path' => $extract_path), 3600);
        return array('success' => true, 'session_id' => $session_id, 'chapters' => $chapters, 'total' => count($chapters));
    }
    
    public function process_single_chapter_from_session($session_id, $folder_name, $manga_id, $chapter_category, $chapter_prefix) {
        $session = get_transient('mcu_session_' . $session_id);
        if (!$session) return array('success' => false, 'message' => 'Session expired.');
        $folder_path = $session['extract_path'] . '/' . $folder_name;
        if (!is_dir($folder_path)) return array('success' => false, 'message' => 'Folder not found.');
        $chapter_number = $this->extract_chapter_number_from_folder($folder_name);
        if ($chapter_number === false) return array('success' => false, 'message' => 'Invalid chapter number.');
        if ($this->chapter_exists($manga_id, $chapter_number)) return array('success' => false, 'message' => 'Chapter exists.', 'skipped' => true);
        $images = $this->get_images_from_folder($folder_path);
        if (empty($images)) return array('success' => false, 'message' => 'No images found.');
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        return $this->create_chapter_from_images($manga_id, $chapter_number, $folder_name, $images, $chapter_category, $chapter_prefix);
    }
    
    public function finalize_session($session_id, $manga_id, $push_to_latest) {
        $session = get_transient('mcu_session_' . $session_id);
        if ($session && isset($session['temp_dir'])) $this->cleanup_temp_dir($session['temp_dir']);
        delete_transient('mcu_session_' . $session_id);
        
        // ZORUNLU ANA SAYFA GÜNCELLEMESİ - GÜNCEL TARİH İLE ZORLA PUSH
        if ($manga_id && $push_to_latest) {
            MCU_Logger::info('Finalizing session with CURRENT timestamp homepage update', array(
                'session_id' => $session_id,
                'manga_id' => $manga_id,
                'push_to_latest' => $push_to_latest
            ));
            
            // DİREKT GÜNCEL TARİH İLE MANGA'YI GÜNCELLE (bölüm tarihlerini değil!)
            global $manga_chapter_uploader;
            if ($manga_chapter_uploader && method_exists($manga_chapter_uploader, 'push_series_to_latest_update')) {
                $push_result = $manga_chapter_uploader->push_series_to_latest_update($manga_id);
                MCU_Logger::info('Finalize homepage push with CURRENT TIME result', array('result' => $push_result, 'manga_id' => $manga_id));
            } else {
                // Fallback: Güncel timestamp ile güncelle
                $current_time = current_time('mysql');
                $current_timestamp = current_time('timestamp');
                global $wpdb;
                $wpdb->update($wpdb->posts,
                    array('post_modified' => $current_time, 'post_date' => $current_time),
                    array('ID' => $manga_id), array('%s', '%s'), array('%d'));
                update_post_meta($manga_id, '_last_updated', $current_timestamp);
                update_post_meta($manga_id, 'ts_edit_post_push_cb', $current_time);
                MCU_Logger::info('Used fallback push with CURRENT TIME', array('manga_id' => $manga_id));
            }
        }
        
        return array('success' => true);
    }
    
    // Yeni fonksiyon: Manga'yı EN YÜKSEK NUMARALI bölümün tarihi ile güncelle
    private function update_manga_with_latest_chapter_date($manga_id) {
        global $wpdb;
        
        // Bu manga'nın EN YÜKSEK NUMARALI bölümünü bul ANCAK GÜNCEL TARİH İLE GÜNCELLE
        $latest_chapter = $wpdb->get_row($wpdb->prepare("
            SELECT p.ID, p.post_date, p.post_date_gmt, pm1.meta_value as chapter_number,
                   pm2.meta_value as chapter_num_for_sort
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'ero_seri'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'ero_chapter'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %s
            ORDER BY CAST(pm2.meta_value AS DECIMAL(10,2)) DESC, p.ID DESC
            LIMIT 1
        ", $manga_id));
        
        if ($latest_chapter) {
            // GÜNCEL TARİH KULLAN - geçmişte kalmayalım
            $current_time = current_time('mysql');
            $current_time_gmt = current_time('mysql', 1);
            $current_timestamp = current_time('timestamp');
            
            // Manga'nın tarihini GÜNCEL tarih ile güncelle (eski bölüm tarihiyle değil)
            $wpdb->update(
                $wpdb->posts,
                array(
                    'post_modified' => $current_time,
                    'post_modified_gmt' => $current_time_gmt,
                    'post_date' => $current_time,
                    'post_date_gmt' => $current_time_gmt
                ),
                array('ID' => $manga_id, 'post_type' => 'manga'),
                array('%s', '%s', '%s', '%s'),
                array('%d', '%s')
            );
            
            // Meta verileri de GÜNCEL timestamp ile güncelle
            $meta_updates = array(
                '_last_updated' => $current_timestamp,
                'ts_edit_post_push_cb' => $current_time,
                '_latest_update' => $current_timestamp,
                'latest_update' => $current_timestamp,
                '_manga_latest_update' => $current_timestamp,
                'manga_latest_update' => $current_timestamp,
                '_homepage_update_timestamp' => $current_timestamp
            );
            
            foreach ($meta_updates as $key => $value) {
                update_post_meta($manga_id, $key, $value);
            }
            
            // Cache temizleme - ana sayfa sıralaması için kritik
            clean_post_cache($manga_id);
            wp_cache_delete($manga_id, 'posts');
            wp_cache_delete($manga_id, 'post_meta');
            
            // Transient'ları temizle - ana sayfa listesi için
            delete_transient('manga_latest_updates');
            delete_transient('homepage_manga_list');
            delete_transient('latest_manga');
            
            MCU_Logger::info('Manga updated with current timestamp', array(
                'manga_id' => $manga_id,
                'latest_chapter_id' => $latest_chapter->ID,
                'highest_chapter_number' => $latest_chapter->chapter_num_for_sort,
                'updated_with_current_time' => $current_time,
                'timestamp' => $current_timestamp
            ));
        }
    }
    
    // Yeni fonksiyon: Zorla ana sayfada görünme
    private function force_homepage_appearance($manga_id) {
        global $wpdb;
        
        $current_time = current_time('mysql');
        $current_time_gmt = current_time('mysql', 1);
        $timestamp = current_time('timestamp');
        
        // Post'u zorla güncelle
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->posts}
            SET post_modified = %s, post_modified_gmt = %s, post_date = %s, post_date_gmt = %s
            WHERE ID = %d AND post_type = 'manga'
        ", $current_time, $current_time_gmt, $current_time, $current_time_gmt, $manga_id));
        
        // Özel homepage flag'ları
        update_option('_force_homepage_manga_' . $manga_id, $timestamp);
        update_post_meta($manga_id, '_force_latest_update', $timestamp);
        update_post_meta($manga_id, '_homepage_priority', 1);
        
        // Transient'ları temizle
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%manga%' OR option_name LIKE '%_transient_%latest%' OR option_name LIKE '%_transient_%homepage%'");
        
        MCU_Logger::info('Forced homepage appearance', array('manga_id' => $manga_id, 'timestamp' => $timestamp));
    }
}

// Resim İşleme ve Optimizasyon Sınıfı
class MCU_ImageProcessor {
    public function __construct() {}
    
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
    
    private function fetch_from_mangadx($url, $manga_id, $chapter_number) {
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
        
        // Zamanlı event oluştur
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