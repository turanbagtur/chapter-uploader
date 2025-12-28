# Manga Chapter Uploader

**Version:** 1.4.0  
**WordPress Compatibility:** 5.0+  
**PHP Requirements:** 7.4+  
**Author:** Solderet  
**License:** GPL2  

## Description

Manga Chapter Uploader is an advanced manga chapter upload plugin designed to be compatible with MangaReader themes. It offers powerful features including single chapter uploads, bulk ZIP file processing, and automatic chapter fetching from websites.

## 🚀 Key Features

### 📝 Single Chapter Upload
- Easy drag-and-drop file selection
- Multiple image upload support
- Automatic chapter number increment (+1)
- Display current chapter count
- Category selection
- Push to homepage option
- Scheduled publishing support

### 📦 Multiple Chapter Upload (ZIP)
- **NEW in v1.4.0:** Enhanced sequential processing with guaranteed chapter ordering
- **NEW in v1.4.0:** Configurable maximum ZIP file size (100MB - 5GB)
- Bulk chapter upload via ZIP files
- Automatic folder recognition and sorting
- Supported folder formats:
  - `1`, `2`, `3` (numbers only)
  - `1-Title`, `2-Second Chapter` (number-title)
  - `chapter 1`, `bolum 1` (with prefix)
- Natural number sorting (1, 2, 10 order)
- **FIXED in v1.4.0:** Sequential chapter ordering (no more mixed order like 161,163,162)
- Real-time progress tracking
- Comprehensive error reporting system

### 🌐 Website Fetching
- Automatic chapter fetching from Blogger and other websites
- URL testing feature
- Automatic chapter number detection
- High-resolution image optimization
- Lazy-loading support

### 📊 Statistics and Management
- Total chapter count
- Monthly upload statistics
- Orphaned media cleanup
- **NEW in v1.4.0:** Enhanced debugging with detailed logging
- **NEW in v1.4.0:** Advanced error tracking and reporting

## 💻 Installation

1. **Manual Installation:**
   - Upload plugin files to `/wp-content/plugins/manga-chapter-uploader/` folder
   - Go to WordPress admin panel > Plugins > Installed Plugins
   - Activate "Manga Chapter Uploader"

2. **Requirements:**
   - WordPress 5.0 or higher
   - PHP 7.4 or higher
   - ZipArchive PHP extension (for multiple uploads)
   - `manga` post type (must be provided by theme)

## 🔧 Configuration

### Theme Compatibility
The plugin uses the following meta fields:
- `ero_seri` - Manga series ID
- `ero_chapter` - Chapter number
- `ero_chaptertitle` - Chapter title

### Settings Page
From **Chapter Uploader > Settings** menu:
- **NEW in v1.4.0:** Configurable ZIP file size limits
- Language selection (Turkish/English)
- Default chapter prefix
- Automatic homepage push
- Image quality settings
- Performance optimization options

## 📚 Usage Guide

### Single Chapter Upload
1. Go to **Chapter Uploader** menu
2. Select **Single Chapter Upload** tab
3. Enter chapter number or use **Auto +1** button
4. Select manga series
5. Drop images into drag-and-drop area
6. Click **Upload Chapter** button

### Multiple Chapter Upload
1. Select **Multiple Chapters (ZIP)** tab
2. Prepare your ZIP file:
   ```
   manga_chapters.zip
   ├── 1/
   │   ├── 001.jpg
   │   └── 002.jpg
   ├── 2-New Beginning/
   │   ├── 001.jpg
   │   └── 002.jpg
   └── 3/
       ├── page1.png
       └── page2.png
   ```
3. Select ZIP file and specify manga series
4. **NEW in v1.4.0:** Watch real-time sequential processing with guaranteed order
5. Click **Upload ZIP and Process** button

### Website Fetching
1. Select **Fetch from Website** tab
2. Enter the URL where the chapter is located
3. Test URL with **Test URL** button
4. Select manga series
5. Click **Fetch from Website** button

## 🛠️ Technical Details

### Supported File Formats
- JPG, JPEG, PNG, GIF, WebP
- Maximum file size: 10MB (single)
- **NEW in v1.4.0:** Configurable maximum ZIP size: 100MB - 5GB

### Homepage Update System
**ENHANCED in v1.4.0:** When a chapter is uploaded, the plugin pushes the manga series to the homepage by:
- Updating manga post's `post_date` and `post_modified` timestamps with current time
- Updating multiple meta fields for better theme compatibility
- Comprehensive cache clearing (WordPress cache, transients, object cache)
- Triggering all relevant theme hooks
- **NEW:** Force homepage appearance system for stubborn themes

### Meta Box Compatibility
The plugin supports both Meta Box and standard WordPress meta fields:
```php
// If Meta Box exists
rwmb_set_meta($post_id, 'ero_seri', $manga_id);

// Standard WordPress
update_post_meta($post_id, 'ero_seri', $manga_id);
```

### **NEW in v1.4.0:** Advanced Logging System
- Comprehensive debug logging for all operations
- Error tracking and reporting
- Performance monitoring
- Detailed upload statistics

## 🚨 Troubleshooting

### Common Issues

**Chapters not appearing on homepage:**
- **FIXED in v1.4.0:** Enhanced homepage push system with multiple fallback methods
- Ensure "Push to Homepage" option is checked
- Clear cache plugins
- Verify theme has manga post type support

**ZIP upload not working:**
- **IMPROVED in v1.4.0:** Better error handling and reporting
- Check if ZipArchive extension is installed on server
- Increase PHP memory_limit and max_execution_time values
- Check file permissions (wp-content/uploads must be writable)

**Chapters uploading in wrong order:**
- **FIXED in v1.4.0:** Sequential processing guarantees correct order
- No more mixed ordering issues (like 161,163,162,167,164)
- Sequential processing ensures: 160 → 161 → 162 → 163 → 164...

**Scheduled posts instead of immediate publishing:**
- **FIXED in v1.4.0:** All chapters now publish immediately
- No more "scheduled" post status issues

**Blogger fetching fails:**
- Ensure URL is correct
- Check if site has image protection
- Verify no firewall or bot protection

### Debug Mode
**ENHANCED in v1.4.0:** If errors occur during upload, comprehensive debug information is automatically displayed. The new logging system provides detailed insights for troubleshooting.

## 📈 Performance Optimization

### **NEW in v1.4.0:** Performance Improvements
- **Sequential Processing:** Eliminates race conditions and ensures perfect order
- **Optimized File Operations:** Safe and efficient file handling
- **Enhanced Caching:** Smart cache management for better performance
- **Reduced Memory Usage:** Optimized for large ZIP files

### Recommendations
- Increase PHP timeout values for large ZIP files
- Use Redis/Memcached for heavy usage
- Use CDN to speed up image loading
- Regularly perform orphaned media cleanup

## 🔒 Security

### Security Features
- **ENHANCED in v1.4.0:** Improved file validation and security checks
- CSRF protection (nonce)
- File type validation
- File size control
- User permission checks
- Secure file naming
- **NEW:** Safe recursive directory operations

## 📞 Support

For issues or suggestions:
- **Website:** https://mangaruhu.com
- **GitHub:** https://github.com/turanbagtur/chapter-uploader

## 🔄 Update Notes

### v1.4.0 - Major Stability and Performance Update
#### 🛠️ Critical Fixes
- **FIXED:** Sequential chapter ordering - no more mixed order uploads (161,163,162 → 160,161,162,163...)
- **FIXED:** Scheduled post issues - all chapters now publish immediately
- **FIXED:** PHP file operation errors (unlink, filesize warnings eliminated)
- **FIXED:** WordPress cron errors with custom schedule definitions
- **FIXED:** Homepage push system completely rebuilt for reliability

#### 🚀 Performance Improvements
- **NEW:** Sequential processing system for guaranteed chapter order
- **NEW:** Real-time progress tracking with detailed status updates
- **NEW:** Enhanced error handling and recovery mechanisms
- **NEW:** Optimized file operations for better performance
- **NEW:** Advanced logging system for debugging

#### 🎛️ New Features
- **NEW:** Configurable ZIP file size limits (100MB to 5GB)
- **NEW:** Enhanced settings page with more customization options
- **NEW:** Comprehensive debug information display
- **NEW:** Multiple meta field support for better theme compatibility
- **NEW:** Force homepage appearance system

#### 🔧 Technical Improvements
- **IMPROVED:** Safe recursive directory operations
- **IMPROVED:** Enhanced cache management
- **IMPROVED:** Better WordPress hook integration
- **IMPROVED:** Comprehensive transient clearing
- **IMPROVED:** Enhanced theme compatibility

### v1.3.1
- Multiple chapter upload system completed
- Category support added to Blogger fetching
- Language switching system improved
- Homepage update system strengthened
- Error handling improved

## 📜 License

This plugin is distributed under the GPL2 license. You can use, modify, and distribute it for free.

---

**Note:** This plugin is specifically optimized for MangaReader themes. Version 1.4.0 includes major stability improvements and is recommended for all users.