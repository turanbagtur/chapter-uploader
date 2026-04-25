# Changelog

All notable changes to the Manga Chapter Uploader plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.6.0] - 2026-04-25 - Performance & Stability Update 🚀

This major update drastically improves bulk upload performance, resolves critical timeout issues with large ZIP files, and fixes chronologic sorting issues for bulk chapters.

### 🚀 Performance Optimizations
* **Fast Sideload Mode:** The chapter upload process was completely rewritten to bypass the WordPress Media Library. Images are now directly saved to `wp-content/uploads/manga_chapters/`.
* **Zero Database Overhead:** Removed the redundant database INSERT queries for each individual image, resulting in near-instantaneous chapter processing.

### 📦 Stability & Fixes
* **Chunk Uploading System:** Integrated a chunked upload system (2MB chunks) to safely process massive ZIP files (up to 5GB) without hitting server `upload_max_filesize` or connection timeout limits.
* **Extraction Timeout Fix:** Disabled PHP execution time limits (`@set_time_limit(0)`) and increased memory limits during the ZIP extraction phase to prevent silent failures on massive archives. Added robust fallback logic to handle chunk-assembled files.
* **Timestamp Overflow Fix:** Corrected a critical timestamp miscalculation that occurred with high chapter numbers (e.g., 300+). The system now uses a batch-relative index rather than an absolute chapter number, accurately sorting up to 1440 chapters sequentially in a single batch without jumping into future dates.

### 🧹 Improvements
* **Garbage Collection Hooks:** Added `before_delete_post` and `wp_trash_post` hooks. Deleting a chapter from the WordPress admin panel now automatically cleans up its associated isolated image folder from the server, preventing orphaned files.
* **UI Updates:** Added a prominent "Support the Developer" (Ko-fi) button to the plugin admin header.

## [1.5.0] - 2026-04-21 - Bug Fix & Code Quality Release 🛠️

This release focuses on resolving logic errors, code quality improvements, and stability fixes identified through code review. No new user-facing features are introduced; all changes improve reliability and correctness.

### 🐛 Bug Fixes

#### `manga-uploader-extensions.php`
- **FIXED:** Variable shadowing in `fetch_from_webtoons()` — the `$url` function parameter was being silently overwritten inside the `foreach` loop processing image URLs, causing the wrong URL to be passed to `extract_chapter_from_webtoons_url()`. Renamed loop variable to `$img_url`.
- **FIXED:** Dead code / incorrect ternary in `update_manga_timestamp()` — both branches of `($key === 'ts_edit_post_push_cb') ? $current_time : $current_time` returned the same value. Timestamp meta keys now correctly receive a Unix timestamp (`$timestamp`) while date-string meta keys receive `$current_time`.
- **FIXED:** Duplicate `mcu_publish_scheduled_chapter` hook registration — the hook was registered both in `MangaChapterUploader::__construct()` (main plugin) and at the bottom of the extensions file, causing the scheduled chapter publisher to fire twice per event. The redundant registration in extensions has been removed.
- **FIXED:** `MCU_ScheduledPublisher::publish_scheduled_chapter()` was instantiating `new MangaChapterUploader()` to call `push_series_to_latest_update()`. Creating a new instance re-registers all AJAX and WordPress hooks, leading to duplicate callbacks. Now uses the existing global `$manga_chapter_uploader` instance instead.
- **FIXED:** Typo in `fetch_from_mangadex()` regex pattern — `mangadx` corrected to `mangadex`. The previous pattern never matched any real MangaDex image URLs.

#### `includes/class-chunk-uploader.php`
- **FIXED:** Division by zero risk in `handle_chunk_upload()` — progress percentage calculation `count(...) / $total_chunks` would produce a fatal `Division by zero` error if `$total_chunks` was `0`. Added a guard: returns `0` when `$total_chunks` is not positive.

#### `manga-chapter-uploader.php`
- **FIXED:** Unnecessary `global $mcu_logger` declaration — `MCU_Logger` is a fully static class and requires no instance. The unused global variable declaration has been removed to avoid confusion.
- **FIXED:** Redundant `sprintf()` wrapping a translation call with no format placeholders in `admin_notices()`. If a translator's string contained `%` characters this could trigger a PHP warning. Changed to a direct `__()` call.

### 🔧 Technical Improvements
- **UPDATED:** Plugin header `Tested up to` bumped from `6.4` to `6.7`
- **CLEANED:** Removed dead switch case `'mangadx.org'` from `MCU_AdvancedFetcher::fetch_from_url()` (was unreachable after the typo fix)

### ⬆️ Upgrade Notes
- **Automatic:** No manual intervention required
- **Database:** No schema changes
- **Settings:** All existing settings are fully preserved
- **Compatibility:** Full backward compatibility maintained

---

## [1.4.1] - 2025-12-28 - Critical Image Processing Fix 🔧

### 🛠️ Critical Fixes
- **FIXED:** Image mixing between chapters in batch uploads - eliminated cross-chapter image contamination
- **FIXED:** `wp_handle_sideload()` file deletion causing image confusion between different chapters
- **IMPLEMENTED:** Each chapter now uses isolated temporary directory (`temp_chapter_{number}_{uniqid}`)
- **ENHANCED:** File isolation system with unique image copies per chapter to prevent WordPress file conflicts
- **ADDED:** Advanced logging for image processing debugging and troubleshooting

### 🚀 Technical Improvements
- **NEW:** Isolated file processing system - each chapter gets its own temporary workspace
- **ENHANCED:** Unique filename generation: `{folder}_ch{number}_img{index}_{original}` format
- **IMPROVED:** Safe file copying before WordPress media processing to prevent file deletion conflicts
- **OPTIMIZED:** Temporary directory cleanup with per-chapter isolation and automatic cleanup
- **STRENGTHENED:** File existence and readability checks before processing

### 🔍 What This Fixes
- ✅ **Chapter Image Separation:** Chapter 1 images no longer appear in Chapter 2 or vice versa
- ✅ **Proper Image Segregation:** Complete isolation of images in multi-chapter ZIP uploads
- ✅ **File Path Conflicts:** No more file path conflicts between different chapters
- ✅ **WordPress Integration:** Eliminated `wp_handle_sideload()` file deletion issues that caused image mixing
- ✅ **Batch Upload Reliability:** Multi-chapter uploads now maintain perfect image integrity per chapter

### 🎯 Impact
This hotfix resolves the most critical user-reported issue where images from different chapters would mix together during batch uploads. Now each chapter maintains its own isolated set of images with zero cross-contamination.

---

## [1.4.0] - 2025-12-28 - Major Stability and Performance Update 🚀

This release represents a complete overhaul of the plugin's core systems, focusing on reliability, performance, and user experience. All reported critical issues have been resolved.

### 🛠️ Critical Fixes

#### Chapter Ordering System
- **FIXED**: Sequential chapter ordering - eliminated mixed order uploads (161,163,162,167,164 → 160,161,162,163,164...)
- **FIXED**: Race conditions in parallel processing causing wrong sequence
- **IMPLEMENTED**: Mathematical post date calculation for guaranteed ordering
- **REPLACED**: Parallel processing with fast sequential processing for 100% reliability

#### Publishing System  
- **FIXED**: Scheduled post issues - all chapters now publish immediately instead of being marked as "scheduled"
- **IMPLEMENTED**: Force immediate publishing logic with current timestamp validation
- **ENHANCED**: Post status management to prevent draft/scheduled states

#### File Operations
- **FIXED**: PHP file operation errors (`unlink()`, `filesize()` warnings eliminated)
- **IMPLEMENTED**: Safe file operations with existence checks
- **REPLACED**: Problematic `RecursiveDirectoryIterator` with secure `scandir()` approach
- **ENHANCED**: File path validation and error handling

#### WordPress Integration
- **FIXED**: WordPress cron errors with custom schedule definitions
- **ADDED**: Custom cron schedules (`mcu_five_minutes`, `mcu_ten_minutes`, `mcu_daily`)
- **ENHANCED**: Hook management and deactivation cleanup
- **IMPLEMENTED**: Proper cron event scheduling and management

### 🚀 Performance Improvements

#### Processing System
- **NEW**: Sequential processing system with 10ms intervals for optimal speed
- **OPTIMIZED**: File operations for better memory usage and performance
- **ENHANCED**: Error recovery mechanisms and timeout handling
- **IMPLEMENTED**: Smart progress tracking with real-time updates

#### Cache Management
- **ENHANCED**: Comprehensive cache clearing system
- **IMPLEMENTED**: Multi-level cache invalidation (WordPress, transients, object cache)
- **OPTIMIZED**: Cache management for immediate homepage updates
- **ADDED**: Selective cache clearing to prevent performance issues

### 🎛️ New Features

#### Upload System
- **NEW**: Configurable ZIP file size limits (100MB to 5GB)
- **NEW**: Real-time progress tracking with detailed status information
- **NEW**: Enhanced error reporting with actionable feedback
- **NEW**: Batch processing status indicators

#### Settings & Configuration
- **NEW**: Advanced settings page with ZIP size configuration
- **NEW**: Performance optimization options
- **NEW**: Enhanced debugging controls
- **EXPANDED**: Theme compatibility options

#### Homepage Integration
- **NEW**: Force homepage appearance system for stubborn themes
- **NEW**: Multiple meta field support for broader theme compatibility
- **ENHANCED**: Automatic timestamp management for latest updates
- **IMPLEMENTED**: Fallback methods for reliable homepage push

### 🔧 Technical Improvements

#### Code Architecture
- **REFACTORED**: Core upload processing logic for better maintainability
- **ENHANCED**: Error handling with comprehensive logging system
- **IMPLEMENTED**: Advanced debugging with detailed operation tracking
- **OPTIMIZED**: Memory management for large file processing

#### Database Operations
- **ENHANCED**: Post meta management with multiple key support
- **IMPLEMENTED**: Safe database queries with proper escaping
- **OPTIMIZED**: Batch operations for better performance
- **ADDED**: Transaction-like operations for data consistency

#### Security Enhancements
- **ENHANCED**: File validation with stricter security checks
- **IMPLEMENTED**: Safe recursive directory operations
- **STRENGTHENED**: Input validation and sanitization
- **ADDED**: Additional permission checks and nonce validation

### 🐛 Bug Fixes

#### Image Processing
- **FIXED**: Duplicate image issues where all chapters showed same images
- **RESOLVED**: Image path resolution problems in ZIP processing
- **ENHANCED**: Image file validation and processing
- **OPTIMIZED**: Memory usage during image operations

#### User Interface
- **FIXED**: Progress bar accuracy and real-time updates
- **ENHANCED**: Error message clarity and actionability
- **IMPROVED**: Form validation and user feedback
- **OPTIMIZED**: Interface responsiveness during operations

#### Theme Compatibility
- **ENHANCED**: Meta Box plugin integration
- **EXPANDED**: Support for multiple manga reader themes
- **IMPROVED**: Custom post type handling
- **STRENGTHENED**: Hook compatibility and integration

---

## [1.3.2] - 2024-XX-XX

### Added
- Chapter prefix selection in multiple upload form
- Auto-category selection for all upload methods
- Enhanced form validation system

### Fixed
- Form validation issues in multiple upload
- Category assignment problems
- User feedback inconsistencies

### Improved
- Error handling and debugging capabilities
- Homepage update reliability
- User experience across all upload methods

---

## [1.3.1] - 2024-XX-XX

### Added
- Complete multiple chapter upload system
- Blogger fetching with category support
- Enhanced statistics and reporting

### Improved
- Language switching system
- Homepage update mechanism
- Error reporting and debugging

### Changed
- Upload processing architecture
- File handling system
- User interface layout

---

## [1.2.0] - 2024-XX-XX

### Added
- Advanced ZIP processing with natural sorting
- Website fetching capabilities from multiple sources
- Scheduled publishing system
- Image optimization features (WebP conversion, progressive JPEG)
- Comprehensive statistics dashboard

### Enhanced
- Drag & drop interface with visual feedback
- Error reporting with detailed information
- Performance optimization for large files

### Fixed
- File sorting issues in ZIP processing
- Memory management problems
- Upload timeout issues

---

## [1.1.0] - 2024-XX-XX

### Added
- Basic multiple chapter upload functionality
- Statistics and management tools
- Auto-increment chapter numbering
- Orphaned media cleanup

### Improved
- Single chapter upload reliability
- User interface responsiveness
- Error handling and recovery

### Changed
- Database schema optimizations
- File processing algorithms

---

## [1.0.0] - 2024-XX-XX

### Added
- Initial release with single chapter upload
- Drag & drop interface for image selection
- MangaReader theme compatibility
- Basic homepage integration
- WordPress admin integration
- Multi-language support (Turkish/English)

### Features
- Single image and multi-image upload support
- Automatic chapter numbering
- Category auto-selection based on manga series
- Basic error handling and validation
- Theme-compatible meta field management

---

## Upgrade Notes

### From 1.4.x to 1.5.0
- **Automatic**: No manual intervention required
- **Database**: No schema changes; all existing data preserved
- **Settings**: Existing settings are fully preserved
- **Compatibility**: Full backward compatibility maintained

### From 1.3.x to 1.4.0
- **Automatic**: No manual intervention required
- **Database**: Plugin automatically handles any necessary updates
- **Settings**: Existing settings are preserved and enhanced
- **Performance**: Significant performance improvements will be immediately noticeable
- **Compatibility**: Full backward compatibility maintained

### Recommended Actions After Update
1. Clear any caching plugins
2. Test upload functionality with a small ZIP file
3. Verify homepage push functionality
4. Check debug logs for any residual issues
5. Review new settings in **Chapter Uploader > Settings**

---

## Support

For issues, questions, or feature requests:
- **GitHub Issues**: [Report bugs or request features](https://github.com/turanbagtur/chapter-uploader/issues)
- **Website**: [Visit our website](https://mangaruhu.com)

---

## Contributors

- **Solderet** - Lead Developer and Maintainer
- Community contributors and testers

---

*This changelog follows [semantic versioning](https://semver.org/) principles and maintains backward compatibility across minor version updates.*