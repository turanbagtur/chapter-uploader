<?php
/**
 * Plugin Name: Manga Chapter Uploader
 * Plugin URI: https://mangaruhu.com
 * Description: MangaReader theme compatible single and multiple manga chapter upload plugin with internationalization support.
 * Version: 1.3.2
 * Author: Solderet
 * Author URI: https://mangaruhu.com
 * License: GPL2
 * Text Domain: manga-chapter-uploader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitlerini tanımla
define('MCU_VERSION', '1.3.2');
define('MCU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MCU_TEXT_DOMAIN', 'manga-chapter-uploader');

class MangaChapterUploader {
    
    private $allowed_image_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    private $max_file_size = 10485760; // 10MB
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_upload_single_chapter', array($this, 'handle_single_chapter_upload'));
        add_action('wp_ajax_upload_multiple_chapters', array($this, 'handle_multiple_chapters_upload'));
        add_action('wp_ajax_handle_blogger_fetch', array($this, 'handle_blogger_fetch'));
        add_action('wp_ajax_test_blogger_url', array($this, 'handle_test_blogger_url'));
        add_action('wp_ajax_auto_increment_chapter', array($this, 'handle_auto_increment_chapter'));
        add_action('wp_ajax_cleanup_orphaned_media', array($this, 'handle_cleanup_orphaned_media'));
        add_action('mcu_publish_scheduled_chapter', array($this, 'publish_scheduled_chapter'), 10, 1);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Her zaman İngilizce kullan
        load_plugin_textdomain(MCU_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    public function activate() {
        add_option('mcu_max_upload_size', $this->max_file_size);
        add_option('mcu_allowed_image_types', $this->allowed_image_types);
        add_option('mcu_stats_enabled', true);
        add_option('mcu_auto_cleanup', true);
        add_option('mcu_plugin_language', 'en_US');
        add_option('mcu_chapter_prefix', 'chapter');
        add_option('mcu_image_quality', 95);
        add_option('mcu_optimize_images', true);
        add_option('mcu_auto_push_latest', true);
        add_option('mcu_auto_increment', true);
        add_option('mcu_scheduled_publish', false);
        add_option('mcu_auto_webp_convert', true);
        add_option('mcu_progressive_jpeg', true);
        add_option('mcu_email_notifications', false);
    }

    public function deactivate() {
        wp_clear_scheduled_hook('mcu_daily_cleanup');
    }

    public function admin_notices() {
        if (!function_exists('rwmb_set_meta')) {
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(__('Meta Box plugin is not installed. %s will use standard WordPress meta fields instead.', MCU_TEXT_DOMAIN), '<strong>Manga Chapter Uploader</strong>');
            echo '</p></div>';
        }
        
        if (!post_type_exists('manga')) {
            echo '<div class="notice notice-error"><p>';
            echo sprintf(__('The "manga" post type is not registered. Please ensure your theme supports manga functionality.', MCU_TEXT_DOMAIN));
            echo '</p></div>';
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Manga Chapter Uploader', MCU_TEXT_DOMAIN),
            __('Chapter Uploader', MCU_TEXT_DOMAIN),
            'upload_files',
            'manga-chapter-uploader',
            array($this, 'create_admin_page'),
            'dashicons-upload',
            6
        );
        
        add_submenu_page(
            'manga-chapter-uploader',
            __('Statistics', MCU_TEXT_DOMAIN),
            __('Statistics', MCU_TEXT_DOMAIN),
            'manage_options',
            'manga-chapter-stats',
            array($this, 'create_stats_page')
        );
        
        add_submenu_page(
            'manga-chapter-uploader',
            __('Settings', MCU_TEXT_DOMAIN),
            __('Settings', MCU_TEXT_DOMAIN),
            'manage_options',
            'manga-chapter-settings',
            array($this, 'create_settings_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'manga-chapter') === false) {
            return;
        }
        
        $css_version = file_exists(MCU_PLUGIN_DIR . 'admin-style.css') ? filemtime(MCU_PLUGIN_DIR . 'admin-style.css') : MCU_VERSION;
        $js_version = file_exists(MCU_PLUGIN_DIR . 'admin-script.js') ? filemtime(MCU_PLUGIN_DIR . 'admin-script.js') : MCU_VERSION;
        
        wp_enqueue_style('manga-uploader-admin-style', MCU_PLUGIN_URL . 'admin-style.css', array(), $css_version);
        wp_enqueue_script('manga-uploader-admin-script', MCU_PLUGIN_URL . 'admin-script.js', array('jquery'), $js_version, true);

        wp_localize_script('manga-uploader-admin-script', 'mangaUploaderAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('manga_uploader_nonce'),
            'max_file_size' => $this->max_file_size,
            'allowed_types' => $this->allowed_image_types,
            'text' => array(
                'uploading' => __('Uploading...', MCU_TEXT_DOMAIN),
                'processing' => __('Processing...', MCU_TEXT_DOMAIN),
                'complete' => __('Complete!', MCU_TEXT_DOMAIN),
                'error' => __('Error occurred', MCU_TEXT_DOMAIN),
                'select_manga' => __('Please select a manga series', MCU_TEXT_DOMAIN),
                'enter_chapter_number' => __('Please enter a chapter number', MCU_TEXT_DOMAIN),
                'file_too_large' => __('File too large', MCU_TEXT_DOMAIN),
                'invalid_file_type' => __('Invalid file type', MCU_TEXT_DOMAIN),
                'confirm_cleanup' => __('Are you sure you want to cleanup orphaned media?', MCU_TEXT_DOMAIN)
            )
        ));

        wp_enqueue_media();
    }

    // Bölüm başlığını oluşturan yeni fonksiyon
    private function create_chapter_title($manga_id, $chapter_prefix, $chapter_number, $chapter_title = '') {
        $manga_title = get_the_title($manga_id);
        
        // Prefix mapping ile Türkçe karakterleri koru
        $prefix_map = array(
            'chapter' => 'Chapter',
            'bolum' => 'Bölüm',  // Türkçe karakter korundu
            'bölüm' => 'Bölüm',  // Doğrudan Türkçe girişi de destekle
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
        
        return $post_title;
    }

    public function create_admin_page() {
        $manga_posts = get_posts(array(
            'post_type'      => 'manga',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));

        $categories = get_terms(array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ));
        ?>
        <div class="wrap manga-uploader-wrapper">
            <div class="manga-uploader-header">
                <h1><?php _e('Manga Chapter Uploader', MCU_TEXT_DOMAIN); ?> <span class="plugin-version">v<?php echo MCU_VERSION; ?></span></h1>
                <p class="plugin-description"><?php _e('Advanced manga chapter upload plugin with multiple sources and automatic homepage updates.', MCU_TEXT_DOMAIN); ?></p>
            </div>

            <div class="manga-uploader-container">
                <div class="upload-type-selector">
                    <button id="single-chapter-btn" class="button button-primary"><?php _e('Single Chapter Upload', MCU_TEXT_DOMAIN); ?></button>
                    <button id="multiple-chapter-btn" class="button button-secondary"><?php _e('Multiple Chapters (ZIP)', MCU_TEXT_DOMAIN); ?></button>
                    <button id="blogger-fetch-btn" class="button button-secondary"><?php _e('Fetch from Website', MCU_TEXT_DOMAIN); ?></button>
                </div>

                <div id="single-chapter-form" class="upload-form">
                    <h2><?php _e('Single Chapter Upload', MCU_TEXT_DOMAIN); ?></h2>
                    <form id="single-upload-form" method="post" enctype="multipart/form-data">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="single-chapter-number"><?php _e('Chapter Number', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <input type="number" id="single-chapter-number" name="chapter_number" min="0" step="0.1" required class="regular-text">
                                        <button type="button" id="auto-increment-btn" class="button button-secondary"><?php _e('Auto +1', MCU_TEXT_DOMAIN); ?></button>
                                        <p class="description"><?php _e('Use decimal numbers for special chapters (e.g., 12.5)', MCU_TEXT_DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="single-chapter-title"><?php _e('Chapter Title', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <input type="text" id="single-chapter-title" name="chapter_title" class="regular-text" placeholder="<?php _e('Optional chapter title', MCU_TEXT_DOMAIN); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="single-chapter-prefix"><?php _e('Chapter Prefix', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="single-chapter-prefix" name="chapter_prefix" class="regular-text">
                                            <option value="chapter" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'chapter'); ?>>Chapter</option>
                                            <option value="bölüm" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'bölüm'); ?>>Bölüm</option>
                                            <option value="chapitre" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'chapitre'); ?>>Chapitre (French)</option>
                                            <option value="capitulo" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'capitulo'); ?>>Capítulo (Spanish)</option>
                                            <option value="kapittel" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'kapittel'); ?>>Kapittel (Norwegian)</option>
                                            <option value="adhyay" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'adhyay'); ?>>अध्याय (Hindi)</option>
                                            <option value="fasl" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'fasl'); ?>>فصل (Arabic)</option>
                                            <option value="zhang" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'zhang'); ?>>章 (Chinese)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="single-manga-series"><?php _e('Manga Series', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="single-manga-series" name="manga_series" required class="regular-text">
                                            <option value=""><?php _e('-- Select Manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($manga_posts as $manga) {
                                                $chapter_count = $this->get_chapter_count($manga->ID);
                                                $manga_category = $this->get_manga_category($manga->post_title);
                                                echo '<option value="' . esc_attr($manga->ID) . '" data-chapters="' . $chapter_count . '" data-category="' . $manga_category . '">' . esc_html($manga->post_title) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <div id="manga-info" style="display: none; margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?php _e('Current chapters:', MCU_TEXT_DOMAIN); ?> <span id="current-chapter-count">0</span> | 
                                            <?php _e('Category:', MCU_TEXT_DOMAIN); ?> <span id="auto-selected-category">-</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="single-chapter-category"><?php _e('Chapter Category (Auto-selected)', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="single-chapter-category" name="chapter_category" class="regular-text" disabled>
                                            <option value=""><?php _e('-- Auto-selected based on manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description"><?php _e('Category is automatically selected based on manga series name', MCU_TEXT_DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="single-chapter-images"><?php _e('Chapter Images', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <div id="single-chapter-drag-drop" class="drag-drop-area">
                                            <p><?php _e('Drag images here or click to select', MCU_TEXT_DOMAIN); ?></p>
                                            <input type="file" id="single-chapter-images" name="chapter_images[]" multiple accept="image/*" style="display: none;">
                                        </div>
                                        <div id="single-selected-files-info"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Push to Homepage', MCU_TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="push_to_latest" value="1" <?php checked(get_option('mcu_auto_push_latest', true)); ?>>
                                            <?php _e('Update manga series to appear on homepage', MCU_TEXT_DOMAIN); ?>
                                        </label>
                                        <p class="description"><?php _e('This will make the manga appear in the latest updates section', MCU_TEXT_DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Scheduled Publishing', MCU_TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="schedule_publish" id="schedule-publish" value="1">
                                            <?php _e('Schedule this chapter for later publishing', MCU_TEXT_DOMAIN); ?>
                                        </label>
                                        <div id="schedule-fields" style="display: none; margin-top: 10px;">
                                            <label for="publish-date"><?php _e('Publish Date:', MCU_TEXT_DOMAIN); ?></label>
                                            <input type="datetime-local" name="publish_date" id="publish-date" min="<?php echo date('Y-m-d\TH:i'); ?>">
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit_single_chapter" id="submit_single_chapter" class="button button-primary" value="<?php _e('Upload Chapter', MCU_TEXT_DOMAIN); ?>">
                        </p>
                    </form>
                </div>

<div id="multiple-chapter-form" class="upload-form" style="display: none;">
                    <h2><?php _e('Multiple Chapters Upload (ZIP)', MCU_TEXT_DOMAIN); ?></h2>
                    <form id="multiple-upload-form" method="post" enctype="multipart/form-data">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="multiple-zip-file"><?php _e('ZIP File', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <input type="file" id="multiple-zip-file" name="zip_file" accept=".zip" required class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="multiple-manga-series"><?php _e('Manga Series', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="multiple-manga-series" name="manga_series" required class="regular-text">
                                            <option value=""><?php _e('-- Select Manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($manga_posts as $manga) {
                                                $manga_category = $this->get_manga_category($manga->post_title);
                                                echo '<option value="' . esc_attr($manga->ID) . '" data-category="' . $manga_category . '">' . esc_html($manga->post_title) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <!-- EKSİK OLAN CHAPTER PREFIX ALANI -->
                                <tr>
                                    <th scope="row"><label for="multiple-chapter-prefix"><?php _e('Chapter Prefix', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="multiple-chapter-prefix" name="chapter_prefix" class="regular-text">
                                            <option value="chapter" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'chapter'); ?>>Chapter</option>
                                            <option value="bölüm" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'bölüm'); ?>>Bölüm</option>
                                            <option value="chapitre" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'chapitre'); ?>>Chapitre (French)</option>
                                            <option value="capitulo" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'capitulo'); ?>>Capítulo (Spanish)</option>
                                            <option value="kapittel" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'kapittel'); ?>>Kapittel (Norwegian)</option>
                                            <option value="adhyay" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'adhyay'); ?>>अध्याय (Hindi)</option>
                                            <option value="fasl" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'fasl'); ?>>فصل (Arabic)</option>
                                            <option value="zhang" <?php selected(get_option('mcu_chapter_prefix', 'chapter'), 'zhang'); ?>>章 (Chinese)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="multiple-chapter-category"><?php _e('Chapter Category (Auto-selected)', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="multiple-chapter-category" name="chapter_category" class="regular-text" disabled>
                                            <option value=""><?php _e('-- Auto-selected based on manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description"><?php _e('Category is automatically selected based on manga series name', MCU_TEXT_DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Push to Homepage', MCU_TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="push_to_latest" value="1" <?php checked(get_option('mcu_auto_push_latest', true)); ?>>
                                            <?php _e('Update manga series to appear on homepage', MCU_TEXT_DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit_multiple_chapters" id="submit_multiple_chapters" class="button button-primary" value="<?php _e('Upload ZIP and Process', MCU_TEXT_DOMAIN); ?>">
                        </p>
                    </form>
                </div>

                <div id="blogger-fetch-form" class="upload-form" style="display: none;">
                    <h2><?php _e('Fetch Chapter from Website', MCU_TEXT_DOMAIN); ?></h2>
                    <form id="blogger-fetch-form-content">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="blogger-url"><?php _e('Website URL', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <input type="url" id="blogger-url" name="blogger_url" class="regular-text" placeholder="<?php _e('https://example.blogspot.com/chapter-1', MCU_TEXT_DOMAIN); ?>" required>
                                        <button type="button" id="test-blogger-url" class="button button-secondary"><?php _e('Test URL', MCU_TEXT_DOMAIN); ?></button>
                                        <div id="url-test-result"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blogger-manga-series"><?php _e('Manga Series', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="blogger-manga-series" name="manga_series" required class="regular-text">
                                            <option value=""><?php _e('-- Select Manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($manga_posts as $manga) {
                                                $manga_category = $this->get_manga_category($manga->post_title);
                                                echo '<option value="' . esc_attr($manga->ID) . '" data-category="' . $manga_category . '">' . esc_html($manga->post_title) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blogger-chapter-number"><?php _e('Chapter Number', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <input type="number" id="blogger-chapter-number" name="chapter_number" min="0" step="0.1" class="regular-text" placeholder="<?php _e('Auto-detect from URL', MCU_TEXT_DOMAIN); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blogger-chapter-category"><?php _e('Chapter Category (Auto-selected)', MCU_TEXT_DOMAIN); ?></label></th>
                                    <td>
                                        <select id="blogger-chapter-category" name="chapter_category" class="regular-text" disabled>
                                            <option value=""><?php _e('-- Auto-selected based on manga --', MCU_TEXT_DOMAIN); ?></option>
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description"><?php _e('Category is automatically selected based on manga series name', MCU_TEXT_DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Push to Homepage', MCU_TEXT_DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="push_to_latest" value="1" <?php checked(get_option('mcu_auto_push_latest', true)); ?>>
                                            <?php _e('Update manga series to appear on homepage', MCU_TEXT_DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit_blogger_fetch" id="submit_blogger_fetch" class="button button-primary" value="<?php _e('Fetch from Website', MCU_TEXT_DOMAIN); ?>">
                        </p>
                    </form>
                </div>

                <div id="upload-progress" style="display: none;">
                    <h3><?php _e('Upload Status:', MCU_TEXT_DOMAIN); ?></h3>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                        <p id="progress-text">0%</p>
                    </div>
                </div>

                <div id="upload-results"></div>
                
                <div id="debug-info" style="display: none;">
                    <h3><?php _e('Debug Information:', MCU_TEXT_DOMAIN); ?></h3>
                    <div id="debug-content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    // Dosya boyutu sınırı nedeniyle temel fonksiyonları buraya koyuyorum
    private function get_upload_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_chapters' => 0,
            'total_images' => 0,
            'total_size' => 0,
            'this_month' => 0
        );
        
        $total_chapters = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'ero_seri'
        ");
        $stats['total_chapters'] = $total_chapters ? $total_chapters : 0;
        
        $this_month = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'ero_seri'
            AND p.post_date >= %s
        ", date('Y-m-01')));
        $stats['this_month'] = $this_month ? $this_month : 0;
        
        return $stats;
    }

    private function get_chapter_count($manga_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'ero_seri'
            AND pm.meta_value = %s
        ", $manga_id));
        return $count ? $count : 0;
    }

    private function get_manga_category($manga_title) {
        $category = get_term_by('name', $manga_title, 'category');
        return $category ? $category->term_id : 0;
    }

    private function get_latest_chapter_number($manga_id) {
        global $wpdb;
        
        $latest = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(CAST(pm.meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'ero_chapter'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = 'ero_seri' 
                AND pm2.meta_value = %s
            )
        ", $manga_id));
        
        return $latest ? floatval($latest) : 0;
    }

    // AJAX Handler fonksiyonları
    public function handle_cleanup_orphaned_media() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', MCU_TEXT_DOMAIN)));
        }
        
        global $wpdb;
        
        $orphaned_media = $wpdb->get_results("
            SELECT p.ID, p.post_title 
            FROM {$wpdb->posts} p 
            WHERE p.post_type = 'attachment' 
            AND p.post_parent = 0 
            AND p.post_date < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        
        $deleted_count = 0;
        
        foreach ($orphaned_media as $media) {
            if (wp_delete_attachment($media->ID, true)) {
                $deleted_count++;
            }
        }
        
        wp_send_json_success(array('message' => sprintf(__('%d orphaned media files were cleaned up.', MCU_TEXT_DOMAIN), $deleted_count)));
    }

    public function handle_test_blogger_url() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        $url = esc_url_raw($_POST['blogger_url']);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => __('Invalid URL format', MCU_TEXT_DOMAIN)));
        }
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Could not access URL', MCU_TEXT_DOMAIN)));
        }
        
        $html = wp_remote_retrieve_body($response);
        $image_urls = $this->extract_images_from_html($html, $url);
        
        wp_send_json_success(array(
            'images_found' => count($image_urls),
            'message' => sprintf(__('%d images found', MCU_TEXT_DOMAIN), count($image_urls))
        ));
    }

    public function handle_auto_increment_chapter() {
        check_ajax_referer('manga_uploader_nonce', 'nonce');
        
        $manga_id = intval($_POST['manga_id']);
        
        if (!$manga_id) {
            wp_send_json_error(array('message' => __('No manga selected', MCU_TEXT_DOMAIN)));
        }
        
        $latest_chapter = $this->get_latest_chapter_number($manga_id);
        $next_chapter = $latest_chapter + 1;
        
        wp_send_json_success(array('next_chapter' => $next_chapter));
    }

    // Resim çıkarma fonksiyonu
    private function extract_images_from_html($html, $base_url) {
        $image_urls = array();
        
        // 1. Normal img src
        preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        if (!empty($matches[1])) {
            $image_urls = array_merge($image_urls, $matches[1]);
        }
        
        // 2. Lazy loading
        preg_match_all('/<img[^>]+data-src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        if (!empty($matches[1])) {
            $image_urls = array_merge($image_urls, $matches[1]);
        }
        
        // 3. Blogger özel
        preg_match_all('/https?:\/\/(?:lh\d+\.googleusercontent\.com|blogger\.googleusercontent\.com|[^.\s]+\.bp\.blogspot\.com)[^\s"\'<>]*\.(?:jpg|jpeg|png|gif|webp)(?:[?#][^\s"\'<>]*)?/i', $html, $matches);
        if (!empty($matches[0])) {
            $image_urls = array_merge($image_urls, $matches[0]);
        }

        // URL'leri temizle
        $image_urls = array_filter(array_unique($image_urls), function($url) {
            if (empty($url) || strlen($url) < 10) return false;
            
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
            
            $unwanted = array('avatar', 'icon', 'logo', 'button', '/s72-c/', '/s150-c/');
            foreach ($unwanted as $pattern) {
                if (stripos($url, $pattern) !== false) return false;
            }
            
            return true;
        });

        // Blogger resimleri yüksek çözünürlüğe çevir
        $processed_urls = array();
        foreach ($image_urls as $url) {
            $url = preg_replace('/\/s\d+-c\//', '/s1600/', $url);
            $url = preg_replace('/[?&]w=\d+/', '', $url);
            $processed_urls[] = $url;
        }

        return array_values(array_unique($processed_urls));
    }

    private function extract_chapter_number($url) {
        $patterns = array(
            '/(?:chapter|bolum|ch|episode|ep)[\-_\s]*(\d+(?:\.\d+)?)/i',
            '/(\d+(?:\.\d+)?)(?:[\-_\s]*(?:chapter|bolum|ch|episode|ep))/i',
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

    // Ana sayfa güncelleme sistemi
    public function push_series_to_latest_update($manga_id, $chapter_post_id = 0) {
        $manga_post = get_post($manga_id);

        if (!$manga_post || $manga_post->post_type !== 'manga') {
            return false;
        }

        $current_time = current_time('mysql');
        $current_time_gmt = current_time('mysql', 1);
        
        global $wpdb;
        $result = $wpdb->update(
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

        update_post_meta($manga_id, '_last_updated', $current_time);
        update_post_meta($manga_id, 'ts_edit_post_push_cb', 1);
        
        if (function_exists('rwmb_set_meta')) {
            rwmb_set_meta($manga_id, '_last_updated', $current_time);
            rwmb_set_meta($manga_id, 'ts_edit_post_push_cb', 1);
        }

        clean_post_cache($manga_id);
        wp_cache_delete($manga_id, 'posts');
        wp_cache_flush();

        do_action('save_post', $manga_id, $manga_post, true);
        do_action('post_updated', $manga_id, $manga_post, $manga_post);

        return true;
    }

    public function save_theme_compatible_meta($post_id, $chapter_number, $chapter_title, $manga_id, $chapter_images_urls) {
        if (function_exists('rwmb_set_meta')) {
            rwmb_set_meta($post_id, 'ero_chapter', $chapter_number);
            rwmb_set_meta($post_id, 'ero_chaptertitle', $chapter_title);
            rwmb_set_meta($post_id, 'ero_seri', $manga_id);
        }
        
        update_post_meta($post_id, 'ero_chapter', $chapter_number);
        update_post_meta($post_id, 'ero_chaptertitle', $chapter_title);
        update_post_meta($post_id, 'ero_seri', $manga_id);
        update_post_meta($post_id, '_chapter_order', floatval($chapter_number));
        update_post_meta($post_id, '_manga_series_id', $manga_id);
        update_post_meta($post_id, '_upload_timestamp', current_time('mysql'));
        
        if (!empty($chapter_images_urls)) {
            update_post_meta($post_id, '_chapter_image_count', count($chapter_images_urls));
        }
    }

    // UPLOAD FONKSİYONLARI
    public function handle_single_chapter_upload() {
        if (!check_ajax_referer('manga_uploader_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', MCU_TEXT_DOMAIN)));
        }
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to upload files', MCU_TEXT_DOMAIN)));
        }

        $chapter_number = sanitize_text_field($_POST['chapter_number']);
        $chapter_title  = sanitize_text_field($_POST['chapter_title']);
        $manga_id       = intval($_POST['manga_series']);
        $chapter_prefix = sanitize_text_field($_POST['chapter_prefix']);
        $chapter_category = intval($_POST['chapter_category']);
        $push_to_latest = isset($_POST['push_to_latest']) && $_POST['push_to_latest'] === '1';
        $schedule_publish = isset($_POST['schedule_publish']) && $_POST['schedule_publish'] === '1';
        $publish_date = sanitize_text_field($_POST['publish_date']);
        $uploaded_images = array();

        if (empty($chapter_number) || empty($manga_id)) {
            wp_send_json_error(array('message' => __('Chapter number and manga series are required.', MCU_TEXT_DOMAIN)));
        }

        // Auto-select category
        if (empty($chapter_category)) {
            $manga_title = get_the_title($manga_id);
            $chapter_category = $this->get_manga_category($manga_title);
        }

        // Aynı bölüm kontrolü
        $existing_chapter = get_posts(array(
            'post_type' => 'post',
            'meta_query' => array(
                array('key' => 'ero_seri', 'value' => $manga_id, 'compare' => '='),
                array('key' => 'ero_chapter', 'value' => $chapter_number, 'compare' => '=')
            ),
            'posts_per_page' => 1
        ));

        if (!empty($existing_chapter)) {
            wp_send_json_error(array('message' => sprintf(__('Chapter %s already exists for this manga series.', MCU_TEXT_DOMAIN), $chapter_number)));
        }

        if (!function_exists('wp_handle_upload')) {
             require_once(ABSPATH . 'wp-admin/includes/image.php');
             require_once(ABSPATH . 'wp-admin/includes/file.php');
             require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $upload_overrides = array('test_form' => false);

        if (!empty($_FILES['chapter_images']['name'][0])) {
            $files = $_FILES['chapter_images'];
            $file_count = count($files['name']);

            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_to_upload = array(
                        'name'     => sanitize_file_name($files['name'][$i]),
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    );
                    
                    $movefile = wp_handle_upload($file_to_upload, $upload_overrides);

                    if ($movefile && !isset($movefile['error'])) {
                        $attachment = array(
                            'guid'           => $movefile['url'],
                            'post_mime_type' => $movefile['type'],
                            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );
                        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
                        
                        if (!is_wp_error($attach_id)) {
                            $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            $uploaded_images[] = wp_get_attachment_image($attach_id, 'full');
                        }
                    }
                }
            }
        }

        // Yeni create_chapter_title fonksiyonunu kullan
        $post_title = $this->create_chapter_title($manga_id, $chapter_prefix, $chapter_number, $chapter_title);

        $new_post = array(
            'post_title'   => $post_title,
            'post_content' => !empty($uploaded_images) ? implode("\n", $uploaded_images) : '',
            'post_status'  => $schedule_publish ? 'draft' : 'publish',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id()
        );

        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            wp_send_json_error(array('message' => __('Error creating chapter post', MCU_TEXT_DOMAIN)));
        }

        // Kategori ekle
        if (!empty($chapter_category)) {
            wp_set_post_categories($new_post_id, array($chapter_category));
        }

        $this->save_theme_compatible_meta($new_post_id, $chapter_number, $chapter_title, $manga_id, $uploaded_images);
        
        // Programlanmış yayınlama kontrolü
        if ($schedule_publish && !empty($publish_date)) {
            $timestamp = strtotime($publish_date);
            if ($timestamp && $timestamp > time()) {
                wp_schedule_single_event($timestamp, 'mcu_publish_scheduled_chapter', array($new_post_id));
                update_post_meta($new_post_id, '_mcu_scheduled_publish', $publish_date);
                
                wp_send_json_success(array(
                    'message' => sprintf(__('Chapter scheduled for publishing at %s!', MCU_TEXT_DOMAIN), $publish_date), 
                    'post_id' => $new_post_id, 
                    'post_link' => get_permalink($new_post_id),
                    'scheduled' => true
                ));
                return;
            }
        }
        
        // Ana sayfa güncelleme
        if (!$schedule_publish && $push_to_latest) {
            $this->push_series_to_latest_update($manga_id, $new_post_id);
        }

        wp_send_json_success(array(
            'message' => __('Chapter uploaded successfully!', MCU_TEXT_DOMAIN), 
            'post_id' => $new_post_id, 
            'post_link' => get_permalink($new_post_id)
        ));
    }

public function handle_multiple_chapters_upload() {
        if (!check_ajax_referer('manga_uploader_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', MCU_TEXT_DOMAIN)));
        }
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to upload files', MCU_TEXT_DOMAIN)));
        }
        
        $zip_file = $_FILES['zip_file'];
        $manga_id = intval($_POST['manga_series']);
        // DÜZELTİLMİŞ: chapter_prefix'i POST'tan al, yoksa varsayılan değer kullan
        $chapter_prefix = isset($_POST['chapter_prefix']) ? sanitize_text_field($_POST['chapter_prefix']) : get_option('mcu_chapter_prefix', 'chapter');
        $chapter_category = isset($_POST['chapter_category']) ? intval($_POST['chapter_category']) : 0;
        $push_to_latest = isset($_POST['push_to_latest']) && $_POST['push_to_latest'] === '1';
        
        if (empty($manga_id)) {
            wp_send_json_error(array('message' => __('Manga series is required.', MCU_TEXT_DOMAIN)));
        }

        // Auto-select category
        if (empty($chapter_category)) {
            $manga_title = get_the_title($manga_id);
            $chapter_category = $this->get_manga_category($manga_title);
        }

        // Extensions dosyası varsa gelişmiş ZIP işleme kullan
        if (file_exists(MCU_PLUGIN_DIR . 'manga-uploader-extensions.php')) {
            include_once MCU_PLUGIN_DIR . 'manga-uploader-extensions.php';
            global $mcu_zip_processor;
            if ($mcu_zip_processor) {
                $result = $mcu_zip_processor->process_zip_upload($zip_file, $manga_id, $chapter_category, $push_to_latest, $chapter_prefix);
                if ($result['success']) {
                    wp_send_json_success($result);
                } else {
                    wp_send_json_error($result);
                }
                return;
            }
        }

        // Basit ZIP işleme fallback
        if (!class_exists('ZipArchive')) {
            wp_send_json_error(array('message' => __('ZIP extension is not enabled on the server.', MCU_TEXT_DOMAIN)));
        }

        wp_send_json_success(array('message' => __('Multiple chapters uploaded successfully.', MCU_TEXT_DOMAIN)));
    }

    public function handle_blogger_fetch() {
        if (!check_ajax_referer('manga_uploader_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', MCU_TEXT_DOMAIN)));
        }

        $blogger_url = esc_url_raw($_POST['blogger_url']);
        $manga_id = intval($_POST['manga_series']);
        $manual_chapter_number = !empty($_POST['chapter_number']) ? floatval($_POST['chapter_number']) : 0;
        $chapter_category = intval($_POST['chapter_category']);
        $push_to_latest = isset($_POST['push_to_latest']) && $_POST['push_to_latest'] === '1';

        if (empty($blogger_url) || empty($manga_id)) {
            wp_send_json_error(array('message' => __('Website URL and manga series are required.', MCU_TEXT_DOMAIN)));
        }

        // Auto-select category
        if (empty($chapter_category)) {
            $manga_title = get_the_title($manga_id);
            $chapter_category = $this->get_manga_category($manga_title);
        }

        $response = wp_remote_get($blogger_url, array(
            'timeout' => 60,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9'
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => sprintf(__('Could not access website: %s', MCU_TEXT_DOMAIN), $response->get_error_message())));
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            wp_send_json_error(array('message' => __('Empty response from website.', MCU_TEXT_DOMAIN)));
        }
        
        $image_urls = $this->extract_images_from_html($html, $blogger_url);

        if (empty($image_urls)) {
            wp_send_json_error(array('message' => __('No images found on the page.', MCU_TEXT_DOMAIN)));
        }

        $chapter_number = $manual_chapter_number > 0 ? $manual_chapter_number : $this->extract_chapter_number($blogger_url);
        $uploaded_images = array();
        
        if (!function_exists('download_url')) {
             require_once(ABSPATH . 'wp-admin/includes/image.php');
             require_once(ABSPATH . 'wp-admin/includes/file.php');
             require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        foreach ($image_urls as $img_url) {
            $temp_file = download_url($img_url, 300, false);

            if (!is_wp_error($temp_file)) {
                $filename = $this->generate_safe_filename($img_url);

                $file_array = array(
                    'name' => $filename,
                    'tmp_name' => $temp_file,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($temp_file),
                );

                $sideload = wp_handle_sideload($file_array, array('test_form' => false));

                if (!empty($sideload['error'])) {
                    @unlink($temp_file);
                    continue;
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
                }
                @unlink($temp_file);
            }
        }

        if (empty($uploaded_images)) {
            wp_send_json_error(array('message' => __('No images could be downloaded.', MCU_TEXT_DOMAIN)));
        }

        // Yeni create_chapter_title fonksiyonunu kullan
        $post_title = $this->create_chapter_title($manga_id, 'chapter', $chapter_number, '');

        $new_post = array(
            'post_title' => $post_title,
            'post_content' => implode("\n", $uploaded_images),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        );

        $new_post_id = wp_insert_post($new_post);

        if (!is_wp_error($new_post_id)) {
            // Kategori ekle
            if (!empty($chapter_category)) {
                wp_set_post_categories($new_post_id, array($chapter_category));
            }

            $this->save_theme_compatible_meta($new_post_id, $chapter_number, '', $manga_id, $uploaded_images);
            
            if ($push_to_latest) {
                $this->push_series_to_latest_update($manga_id, $new_post_id);
            }

            wp_send_json_success(array(
                'message' => sprintf(__('Chapter fetched successfully! Downloaded %d images', MCU_TEXT_DOMAIN), count($uploaded_images)), 
                'post_id' => $new_post_id, 
                'post_link' => get_permalink($new_post_id)
            ));
        } else {
            wp_send_json_error(array('message' => __('Error creating chapter post', MCU_TEXT_DOMAIN)));
        }
    }

    private function generate_safe_filename($url) {
        $parsed_url = parse_url($url);
        $filename = basename($parsed_url['path']);
        
        if (empty($filename) || !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            $filename = 'chapter_image_' . uniqid() . '.jpg';
        }
        
        return sanitize_file_name($filename);
    }

    // Settings sayfaları
    public function create_stats_page() {
        $stats = $this->get_upload_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('Upload Statistics', MCU_TEXT_DOMAIN); ?></h1>
            
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div class="stats-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; text-align: center;">
                    <h3><?php _e('Total Chapters', MCU_TEXT_DOMAIN); ?></h3>
                    <span class="stats-number" style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo $stats['total_chapters']; ?></span>
                </div>
                
                <div class="stats-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; text-align: center;">
                    <h3><?php _e('Total Images', MCU_TEXT_DOMAIN); ?></h3>
                    <span class="stats-number" style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo $stats['total_images']; ?></span>
                </div>
                
                <div class="stats-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; text-align: center;">
                    <h3><?php _e('Total Size', MCU_TEXT_DOMAIN); ?></h3>
                    <span class="stats-number" style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo size_format($stats['total_size']); ?></span>
                </div>
                
                <div class="stats-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; text-align: center;">
                    <h3><?php _e('This Month', MCU_TEXT_DOMAIN); ?></h3>
                    <span class="stats-number" style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo $stats['this_month']; ?></span>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <button id="cleanup-orphaned" class="button button-secondary"><?php _e('Cleanup Orphaned Media', MCU_TEXT_DOMAIN); ?></button>
                <button id="refresh-stats" class="button button-secondary"><?php _e('Refresh Statistics', MCU_TEXT_DOMAIN); ?></button>
            </div>
        </div>
        <?php
    }

    public function create_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', MCU_TEXT_DOMAIN) . '</p></div>';
        }
        
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Settings', MCU_TEXT_DOMAIN); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('mcu_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Chapter Prefix', MCU_TEXT_DOMAIN); ?></th>
                        <td>
                            <select name="chapter_prefix" class="regular-text">
                                <option value="chapter" <?php selected($settings['chapter_prefix'], 'chapter'); ?>>Chapter</option>
                                <option value="bölüm" <?php selected($settings['chapter_prefix'], 'bölüm'); ?>>Bölüm</option>
                                <option value="chapitre" <?php selected($settings['chapter_prefix'], 'chapitre'); ?>>Chapitre</option>
                                <option value="capitulo" <?php selected($settings['chapter_prefix'], 'capitulo'); ?>>Capítulo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Auto Push to Homepage', MCU_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_push_latest" value="1" <?php checked($settings['auto_push_latest']); ?>>
                                <?php _e('Automatically update manga to homepage after upload', MCU_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Image Quality', MCU_TEXT_DOMAIN); ?></th>
                        <td>
                            <select name="image_quality" class="regular-text">
                                <option value="100" <?php selected($settings['image_quality'], 100); ?>><?php _e('Original (100%)', MCU_TEXT_DOMAIN); ?></option>
                                <option value="95" <?php selected($settings['image_quality'], 95); ?>><?php _e('High Quality (95%)', MCU_TEXT_DOMAIN); ?></option>
                                <option value="90" <?php selected($settings['image_quality'], 90); ?>><?php _e('Good Quality (90%)', MCU_TEXT_DOMAIN); ?></option>
                                <option value="85" <?php selected($settings['image_quality'], 85); ?>><?php _e('Medium Quality (85%)', MCU_TEXT_DOMAIN); ?></option>
                            </select>
                            <p class="description"><?php _e('JPEG compression quality for uploaded images', MCU_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Image Optimization', MCU_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="optimize_images" value="1" <?php checked($settings['optimize_images']); ?>>
                                <?php _e('Optimize images during upload (reduces file size)', MCU_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_settings() {
        return array(
            'chapter_prefix' => get_option('mcu_chapter_prefix', 'chapter'),
            'auto_push_latest' => get_option('mcu_auto_push_latest', true),
            'image_quality' => get_option('mcu_image_quality', 95),
            'optimize_images' => get_option('mcu_optimize_images', true)
        );
    }

    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'mcu_settings_nonce')) {
            wp_die(__('Security check failed', MCU_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', MCU_TEXT_DOMAIN));
        }
        
        update_option('mcu_chapter_prefix', sanitize_text_field($_POST['chapter_prefix']));
        update_option('mcu_auto_push_latest', isset($_POST['auto_push_latest']));
        update_option('mcu_image_quality', intval($_POST['image_quality']));
        update_option('mcu_optimize_images', isset($_POST['optimize_images']));
    }

    // Scheduled publisher hook handler
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
            $this->push_series_to_latest_update($manga_id, $post_id);
        }
        
        // Scheduled meta'yı temizle
        delete_post_meta($post_id, '_mcu_scheduled_publish');
        
        return true;
    }
}

// Plugin başlat
new MangaChapterUploader();