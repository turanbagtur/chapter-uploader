# Manga Chapter Uploader

[![Version](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://github.com/turanbagtur/chapter-uploader)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL2-green.svg)](LICENSE)

A comprehensive WordPress plugin for uploading manga chapters with multiple sources and automatic homepage updates, specifically designed for MangaReader themes.

MangaReader temaları için özel olarak tasarlanmış, çoklu kaynaklardan manga bölümü yükleme ve otomatik ana sayfa güncellemesi özelliklerine sahip kapsamlı bir WordPress eklentisi.

---

## 🌍 Language / Dil

- [🇺🇸 English](#english)
- [🇹🇷 Türkçe](#türkçe)

---

## English

### 🚀 Key Features

#### 📝 Single Chapter Upload
- **Drag & Drop Interface**: Easy file selection with visual feedback
- **Multi-Image Support**: Upload multiple images simultaneously
- **Auto Chapter Increment**: Automatic +1 button for sequential uploads
- **Chapter Management**: Display current chapter count for each manga
- **Category Auto-Selection**: Automatically assigns categories based on manga series
- **Homepage Integration**: Push chapters to homepage latest updates
- **Scheduled Publishing**: Set future publish dates for chapters

#### 📦 Multiple Chapter Upload (ZIP)
- **NEW in v1.4.0:** Enhanced sequential processing with guaranteed chapter ordering
- **NEW in v1.4.0:** Configurable maximum ZIP file size (100MB - 5GB)
- **Bulk Processing**: Upload multiple chapters via ZIP files
- **Smart Folder Recognition**: Supports various naming conventions:
  - `1`, `2`, `3` (numbers only)
  - `1-Title`, `2-Second Chapter` (number-title format)
  - `chapter 1`, `bölüm 1` (with prefix)
- **Natural Sorting**: Proper numerical ordering (1, 2, 10 instead of 1, 10, 2)
- **FIXED in v1.4.0:** Sequential chapter ordering - no more mixed order uploads (161,163,162 → 160,161,162,163...)
- **Real-time Progress Tracking**: Visual indicators with detailed status updates
- **Comprehensive Error Reporting**: Detailed feedback for each chapter processing

#### 🌐 Website Fetching
- **Multi-Site Support**: Fetch chapters from Blogger and other websites
- **URL Testing**: Built-in URL validation and image detection
- **Auto Chapter Detection**: Automatic chapter number extraction from URLs
- **High-Resolution Optimization**: Automatically converts to highest quality available
- **Lazy Loading Support**: Handles modern image loading techniques

#### 🛡️ Advanced Features
- **NEW in v1.4.0:** Advanced logging system for debugging
- **NEW in v1.4.0:** Enhanced error handling and recovery mechanisms
- **Image Optimization**: Automatic WebP conversion and progressive JPEG
- **Watermark Protection**: Built-in image protection (optional)
- **Cache Management**: Automatic cache clearing for immediate updates
- **FIXED in v1.4.0:** Scheduled post issues - all chapters now publish immediately

#### 📊 Statistics & Management
- **Upload Analytics**: Track total chapters, images, and storage usage
- **Monthly Reports**: Detailed monthly upload statistics
- **Orphaned Media Cleanup**: Automatic cleanup of unused media files
- **NEW in v1.4.0:** Enhanced debugging with comprehensive logging
- **NEW in v1.4.0:** Advanced error tracking and reporting

### 💻 Installation

#### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- ZipArchive PHP extension (for multiple uploads)
- `manga` post type (provided by theme)
- Meta Box plugin (optional, for enhanced compatibility)

#### Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/manga-chapter-uploader/`
3. Activate through WordPress admin panel
4. Configure settings under **Chapter Uploader** menu

### 🔧 Configuration

#### Theme Compatibility
The plugin uses these meta fields for MangaReader theme compatibility:
```php
'ero_seri'        // Manga series ID
'ero_chapter'     // Chapter number  
'ero_chaptertitle' // Chapter title
'_chapter_order'  // Sorting order
'_manga_series_id' // Additional series reference
```

#### Settings Options
- **NEW in v1.4.0:** Configurable ZIP file size limits (100MB - 5GB)
- **Chapter Prefix**: Default prefix for chapter titles (Chapter, Bölüm, etc.)
- **Auto Homepage Push**: Automatically update manga on homepage
- **Image Quality**: JPEG compression settings (85%-100%)
- **Image Optimization**: Enable WebP conversion and optimization
- **Scheduled Publishing**: Enable future publishing capabilities

### 📚 Usage Guide

#### Single Chapter Upload
1. Navigate to **Chapter Uploader** in WordPress admin
2. Select **Single Chapter Upload** tab
3. Enter chapter number or use **Auto +1** button
4. Select manga series (category auto-selected)
5. Drag & drop images or click to select files
6. Configure optional settings (title, scheduled publishing)
7. Click **Upload Chapter**

#### Multiple Chapter Upload
1. Prepare your ZIP file structure:
```
manga_chapters.zip
├── 1/
│   ├── 001.jpg
│   └── 002.jpg
├── 2-New Beginning/
│   ├── page1.png
│   └── page2.png
└── 3/
    ├── image1.webp
    └── image2.webp
```
2. Select **Multiple Chapters (ZIP)** tab
3. Choose ZIP file and manga series
4. Select chapter prefix
5. **NEW in v1.4.0:** Watch real-time sequential processing with guaranteed order
6. Click **Upload ZIP and Process**

#### Website Fetching
1. Select **Fetch from Website** tab
2. Enter the webpage URL containing chapter images
3. Use **Test URL** to verify accessibility
4. Select manga series and configure options
5. Click **Fetch from Website**

### 🛠️ Technical Details

#### Supported File Formats
- **Images**: JPG, JPEG, PNG, GIF, WebP
- **Archives**: ZIP (for multiple chapters)
- **Maximum Sizes**: 10MB per image, **NEW:** Configurable ZIP size up to 5GB

#### Homepage Update System
**ENHANCED in v1.4.0:** When chapters are uploaded, the plugin updates the homepage by:
- Modifying manga post `post_date` and `post_modified` timestamps with current time
- Updating multiple meta fields for better theme compatibility
- Comprehensive cache clearing (WordPress cache, transients, object cache)
- Triggering all relevant theme hooks
- **NEW:** Force homepage appearance system for stubborn themes

#### Security Features
- **ENHANCED in v1.4.0:** Improved file validation and security checks
- CSRF protection with nonces
- File type validation
- Size limit enforcement
- User permission checks
- Secure file naming conventions
- **NEW:** Safe recursive directory operations

### 🚨 Troubleshooting

#### Common Issues

**Chapters not appearing on homepage:**
- **FIXED in v1.4.0:** Enhanced homepage push system with multiple fallback methods
- Verify "Push to Homepage" is enabled
- Clear cache plugins
- Ensure theme has proper manga post type support

**ZIP upload failures:**
- **IMPROVED in v1.4.0:** Better error handling and reporting
- Check ZipArchive PHP extension is installed
- Increase PHP `memory_limit` and `max_execution_time`
- Verify file permissions on uploads directory

**Chapters uploading in wrong order:**
- **FIXED in v1.4.0:** Sequential processing guarantees correct order
- No more mixed ordering issues (like 161,163,162,167,164)
- Sequential processing ensures: 160 → 161 → 162 → 163 → 164...

**Scheduled posts instead of immediate publishing:**
- **FIXED in v1.4.0:** All chapters now publish immediately
- No more "scheduled" post status issues

#### Debug Mode
**ENHANCED in v1.4.0:** Failed uploads automatically display comprehensive debug information with detailed insights for troubleshooting.

### 📈 Performance Optimization

#### **NEW in v1.4.0:** Performance Improvements
- **Sequential Processing:** Eliminates race conditions and ensures perfect order
- **Optimized File Operations:** Safe and efficient file handling
- **Enhanced Caching:** Smart cache management for better performance
- **Reduced Memory Usage:** Optimized for large ZIP files

#### Recommendations
- Increase PHP timeout values for large files
- Use Redis/Memcached for heavy usage
- Implement CDN for faster image delivery
- Regular orphaned media cleanup
- Enable image optimization features

### 📞 Support

- **Issues**: [GitHub Issues](https://github.com/turanbagtur/chapter-uploader/issues)
- **Website**: [mangaruhu.com](https://mangaruhu.com)

---

## Türkçe

### 🚀 Temel Özellikler

#### 📝 Tekli Bölüm Yükleme
- **Sürükle & Bırak Arayüzü**: Görsel geri bildirimle kolay dosya seçimi
- **Çoklu Resim Desteği**: Aynı anda birden fazla resim yükleme
- **Otomatik Bölüm Artırma**: Sıralı yüklemeler için otomatik +1 butonu
- **Bölüm Yönetimi**: Her manga için mevcut bölüm sayısını görüntüleme
- **Otomatik Kategori Seçimi**: Manga serisine göre otomatik kategori atama
- **Ana Sayfa Entegrasyonu**: Bölümleri ana sayfa güncel güncellemelerine itme
- **Programlanmış Yayınlama**: Bölümler için gelecek yayın tarihleri belirleme

#### 📦 Çoklu Bölüm Yükleme (ZIP)
- **YENİ v1.4.0'da:** Garantili bölüm sıralaması ile geliştirilmiş sıralı işleme
- **YENİ v1.4.0'da:** Yapılandırılabilir maksimum ZIP dosya boyutu (100MB - 5GB)
- **Toplu İşleme**: ZIP dosyaları ile çoklu bölüm yükleme
- **Akıllı Klasör Tanıma**: Çeşitli adlandırma kurallarını destekler:
  - `1`, `2`, `3` (sadece numara)
  - `1-Başlık`, `2-İkinci Bölüm` (numara-başlık formatı)
  - `chapter 1`, `bölüm 1` (ön ek ile)
- **Doğal Sıralama**: Doğru sayısal sıralama (1, 2, 10 şeklinde, 1, 10, 2 değil)
- **DÜZELTİLDİ v1.4.0'da:** Sıralı bölüm düzeni - artık karışık sıralama yok (161,163,162 → 160,161,162,163...)
- **Gerçek Zamanlı İlerleme Takibi**: Detaylı durum güncellemeleri ile görsel göstergeler
- **Kapsamlı Hata Raporlama**: Her bölüm işlemi için detaylı geri bildirim

#### 🌐 Web Sitelerinden Çekme
- **Çoklu Site Desteği**: Blogger ve diğer web sitelerinden bölüm çekme
- **URL Testi**: Yerleşik URL doğrulama ve resim algılama
- **Otomatik Bölüm Algılama**: URL'lerden otomatik bölüm numarası çıkarma
- **Yüksek Çözünürlük Optimizasyonu**: Mevcut en yüksek kaliteye otomatik dönüştürme
- **Lazy Loading Desteği**: Modern resim yükleme tekniklerini işleme

#### 🛡️ Gelişmiş Özellikler
- **YENİ v1.4.0'da:** Debug için gelişmiş loglama sistemi
- **YENİ v1.4.0'da:** Gelişmiş hata yönetimi ve kurtarma mekanizmaları
- **Resim Optimizasyonu**: Otomatik WebP dönüştürme ve progressive JPEG
- **Filigran Koruması**: Yerleşik resim koruması (opsiyonel)
- **Önbellek Yönetimi**: Anında güncellemeler için otomatik önbellek temizleme
- **DÜZELTİLDİ v1.4.0'da:** Zamanlanmış post sorunları - tüm bölümler artık anında yayınlanıyor

#### 📊 İstatistikler ve Yönetim
- **Yükleme Analitiği**: Toplam bölüm, resim ve depolama kullanımı takibi
- **Aylık Raporlar**: Detaylı aylık yükleme istatistikleri
- **Sahipsiz Medya Temizliği**: Kullanılmayan medya dosyalarının otomatik temizliği
- **YENİ v1.4.0'da:** Kapsamlı loglama ile geliştirilmiş hata ayıklama
- **YENİ v1.4.0'da:** Gelişmiş hata izleme ve raporlama

### 💻 Kurulum

#### Gereksinimler
- WordPress 5.0 veya üzeri
- PHP 7.4 veya üzeri
- ZipArchive PHP uzantısı (çoklu yüklemeler için)
- `manga` post türü (tema tarafından sağlanmalı)
- Meta Box eklentisi (opsiyonel, gelişmiş uyumluluk için)

#### Manuel Kurulum
1. Eklenti dosyalarını indirin
2. `/wp-content/plugins/manga-chapter-uploader/` dizinine yükleyin
3. WordPress yönetici panelinden etkinleştirin
4. **Chapter Uploader** menüsünden ayarları yapılandırın

### 🔧 Yapılandırma

#### Tema Uyumluluğu
Eklenti MangaReader tema uyumluluğu için şu meta alanlarını kullanır:
```php
'ero_seri'        // Manga serisi ID'si
'ero_chapter'     // Bölüm numarası
'ero_chaptertitle' // Bölüm başlığı
'_chapter_order'  // Sıralama düzeni
'_manga_series_id' // Ek seri referansı
```

#### Ayar Seçenekleri
- **YENİ v1.4.0'da:** Yapılandırılabilir ZIP dosya boyutu limitleri (100MB - 5GB)
- **Bölüm Ön Eki**: Bölüm başlıkları için varsayılan ön ek (Chapter, Bölüm, vb.)
- **Otomatik Ana Sayfa İtme**: Mangayı otomatik olarak ana sayfada güncelle
- **Resim Kalitesi**: JPEG sıkıştırma ayarları (%85-%100)
- **Resim Optimizasyonu**: WebP dönüştürme ve optimizasyonu etkinleştir
- **Programlanmış Yayınlama**: Gelecek yayınlama yeteneklerini etkinleştir

### 📚 Kullanım Kılavuzu

#### Tekli Bölüm Yükleme
1. WordPress yöneticisinde **Chapter Uploader**'a gidin
2. **Single Chapter Upload** sekmesini seçin
3. Bölüm numarasını girin veya **Auto +1** butonunu kullanın
4. Manga serisini seçin (kategori otomatik seçilir)
5. Resimleri sürükleyip bırakın veya dosya seçmek için tıklayın
6. Opsiyonel ayarları yapılandırın (başlık, programlanmış yayınlama)
7. **Upload Chapter**'a tıklayın

#### Çoklu Bölüm Yükleme
1. ZIP dosya yapınızı hazırlayın:
```
manga_chapters.zip
├── 1/
│   ├── 001.jpg
│   └── 002.jpg
├── 2-Yeni Başlangıç/
│   ├── sayfa1.png
│   └── sayfa2.png
└── 3/
    ├── resim1.webp
    └── resim2.webp
```
2. **Multiple Chapters (ZIP)** sekmesini seçin
3. ZIP dosyası ve manga serisini seçin
4. Bölüm ön ekini seçin
5. **YENİ v1.4.0'da:** Garantili sıralama ile gerçek zamanlı sıralı işlemeyi izleyin
6. **Upload ZIP and Process**'e tıklayın

#### Web Sitesinden Çekme
1. **Fetch from Website** sekmesini seçin
2. Bölüm resimlerini içeren web sayfası URL'sini girin
3. Erişilebilirliği doğrulamak için **Test URL** kullanın
4. Manga serisini seçin ve seçenekleri yapılandırın
5. **Fetch from Website**'e tıklayın

### 🛠️ Teknik Detaylar

#### Desteklenen Dosya Formatları
- **Resimler**: JPG, JPEG, PNG, GIF, WebP
- **Arşivler**: ZIP (çoklu bölümler için)
- **Maksimum Boyutlar**: Resim başına 10MB, **YENİ:** 5GB'a kadar yapılandırılabilir ZIP boyutu

#### Ana Sayfa Güncelleme Sistemi
**GELİŞTİRİLDİ v1.4.0'da:** Bölümler yüklendiğinde, eklenti ana sayfayı şu şekilde günceller:
- Manga post'unun `post_date` ve `post_modified` zaman damgalarını güncel zaman ile değiştirir
- Daha iyi tema uyumluluğu için birden fazla meta alanını günceller
- Kapsamlı önbellek temizleme (WordPress önbellek, transients, object cache)
- Tüm ilgili tema hook'larını tetikler
- **YENİ:** İnatçı temalar için zorla ana sayfa görünüm sistemi

#### Güvenlik Özellikleri
- **GELİŞTİRİLDİ v1.4.0'da:** İyileştirilmiş dosya doğrulama ve güvenlik kontrolleri
- Nonce'lar ile CSRF koruması
- Dosya türü doğrulaması
- Boyut sınırı zorlaması
- Kullanıcı izin kontrolleri
- Güvenli dosya adlandırma kuralları
- **YENİ:** Güvenli recursive dizin işlemleri

### 🚨 Sorun Giderme

#### Yaygın Sorunlar

**Bölümler ana sayfada görünmüyor:**
- **DÜZELTİLDİ v1.4.0'da:** Birden fazla fallback yöntemi ile geliştirilmiş ana sayfa itme sistemi
- "Push to Homepage"'in etkin olduğunu doğrulayın
- Önbellek eklentilerini temizleyin
- Temanın uygun manga post türü desteğine sahip olduğundan emin olun

**ZIP yükleme hataları:**
- **GELİŞTİRİLDİ v1.4.0'da:** Daha iyi hata yönetimi ve raporlama
- ZipArchive PHP uzantısının kurulu olduğunu kontrol edin
- PHP `memory_limit` ve `max_execution_time` değerlerini artırın
- Uploads dizininde dosya izinlerini doğrulayın

**Bölümler yanlış sırayla yükleniyor:**
- **DÜZELTİLDİ v1.4.0'da:** Sıralı işleme doğru sırayı garanti eder
- Artık karışık sıralama sorunları yok (161,163,162,167,164 gibi)
- Sıralı işleme: 160 → 161 → 162 → 163 → 164... şeklinde çalışır

**Zamanlanmış postlar yerine anlık yayınlama:**
- **DÜZELTİLDİ v1.4.0'da:** Tüm bölümler artık anında yayınlanıyor
- Artık "zamanlandı" post durumu sorunları yok

#### Debug Modu
**GELİŞTİRİLDİ v1.4.0'da:** Başarısız yüklemeler, sorun giderme için detaylı bilgiler ile kapsamlı debug bilgilerini otomatik olarak görüntüler.

### 📈 Performans Optimizasyonu

#### **YENİ v1.4.0'da:** Performans İyileştirmeleri
- **Sıralı İşleme:** Race condition'ları ortadan kaldırır ve mükemmel sıralama sağlar
- **Optimize Edilmiş Dosya İşlemleri:** Güvenli ve verimli dosya yönetimi
- **Geliştirilmiş Önbelleğleme:** Daha iyi performans için akıllı önbellek yönetimi
- **Azaltılmış Bellek Kullanımı:** Büyük ZIP dosyaları için optimize edildi

#### Öneriler
- Büyük dosyalar için PHP timeout değerlerini artırın
- Yoğun kullanım için Redis/Memcached kullanın
- Daha hızlı resim dağıtımı için CDN uygulayın
- Düzenli sahipsiz medya temizliği yapın
- Resim optimizasyon özelliklerini etkinleştirin

### 📞 Destek

- **Sorunlar**: [GitHub Issues](https://github.com/turanbagtur/chapter-uploader/issues)
- **Web Sitesi**: [mangaruhu.com](https://mangaruhu.com)

---

## 🔄 API Integration / API Entegrasyonu

The plugin provides hooks for developers / Eklenti geliştiriciler için hook'lar sağlar:

```php
// After chapter upload / Bölüm yüklendikten sonra
do_action('mcu_chapter_uploaded', $post_id, $manga_id, $chapter_number);

// Before homepage update / Ana sayfa güncellemesinden önce
do_action('mcu_before_homepage_push', $manga_id, $post_id);

// Image processing / Resim işleme
do_action('mcu_process_image', $image_path, $attachment_id);
```

---

## 🤝 Contributing / Katkıda Bulunma

1. Fork the project / Projeyi fork edin
2. Create your feature branch / Özellik dalınızı oluşturun (`git checkout -b feature/AmazingFeature`)
3. Commit your changes / Değişikliklerinizi commit edin (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch / Dala push edin (`git push origin feature/AmazingFeature`)
5. Open a Pull Request / Pull Request açın

---

## ⭐ Show Your Support / Desteğinizi Gösterin

If this project helped you, please give it a ⭐ on GitHub!

Bu proje size yardımcı olduysa, lütfen GitHub'da ⭐ verin!

---

## 👥 Authors / Yazarlar

- **Solderet** - *Initial work* - [GitHub](https://github.com/turanbagtur)

---

## 🙏 Acknowledgments / Teşekkürler

- MangaReader theme developers for inspiration
- WordPress community for amazing documentation
- Contributors and users for feedback and suggestions

- İlham için MangaReader tema geliştiricilerine
- Harika dokümantasyon için WordPress topluluğuna
- Geri bildirim ve öneriler için katkıda bulunanlara ve kullanıcılara