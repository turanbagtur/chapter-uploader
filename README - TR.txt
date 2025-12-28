# Manga Chapter Uploader

**Version:** 1.4.0  
**WordPress Uyumluluğu:** 5.0+  
**PHP Gereksinimleri:** 7.4+  
**Yazar:** Solderet  
**Lisans:** GPL2  

## Açıklama

Manga Chapter Uploader, MangaReader teması ile uyumlu olarak tasarlanmış gelişmiş bir manga bölüm yükleme eklentisidir. Tekli bölüm yüklemesi, çoklu ZIP dosyası işleme ve web sitelerinden otomatik bölüm çekme gibi güçlü özellikler sunar.

## 🚀 Temel Özellikler

### 📝 Tekli Bölüm Yükleme
- Sürükle-bırak ile kolay dosya seçimi
- Çoklu resim yükleme desteği
- Otomatik bölüm numarası artırma (+1)
- Mevcut bölüm sayısını görüntüleme
- Kategori seçimi
- Ana sayfaya itme seçeneği
- Programlanmış yayınlama desteği

### 📦 Çoklu Bölüm Yükleme (ZIP)
- **YENİ v1.4.0'da:** Garantili bölüm sıralaması ile geliştirilmiş sıralı işleme
- **YENİ v1.4.0'da:** Yapılandırılabilir maksimum ZIP dosya boyutu (100MB - 5GB)
- ZIP dosyası ile toplu bölüm yükleme
- Otomatik klasör tanıma ve sıralama
- Desteklenen klasör formatları:
  - `1`, `2`, `3` (sadece numara)
  - `1-Başlık`, `2-İkinci Bölüm` (numara-başlık)
  - `bölüm 1`, `chapter 1` (prefix ile)
- Doğal sayı sıralama (1, 2, 10 şeklinde)
- **DÜZELTİLDİ v1.4.0'da:** Sıralı bölüm düzeni (artık 161,163,162 gibi karışık sıralama yok)
- Gerçek zamanlı ilerleme takibi
- Kapsamlı hata raporlama sistemi

### 🌐 Web Sitelerinden Çekme
- Blogger ve diğer web sitelerinden otomatik bölüm çekme
- URL test özelliği
- Otomatik bölüm numarası tespiti
- Yüksek çözünürlük resim optimizasyonu
- Lazy-loading desteği

### 📊 İstatistikler ve Yönetim
- Toplam bölüm sayısı
- Aylık yükleme istatistikleri
- Sahipsiz medya temizleme
- **YENİ v1.4.0'da:** Detaylı loglama ile geliştirilmiş hata ayıklama
- **YENİ v1.4.0'da:** Gelişmiş hata izleme ve raporlama

## 💻 Kurulum

1. **Manuel Kurulum:**
   - Plugin dosyalarını `/wp-content/plugins/manga-chapter-uploader/` klasörüne yükleyin
   - WordPress admin panelinde Eklentiler > Yüklü Eklentiler'e gidin
   - "Manga Chapter Uploader"ı etkinleştirin

2. **Gereksinimler:**
   - WordPress 5.0 veya üzeri
   - PHP 7.4 veya üzeri
   - ZipArchive PHP uzantısı (çoklu yükleme için)
   - `manga` post type'ı (tema tarafından sağlanmalı)

## 🔧 Yapılandırma

### Tema Uyumluluğu
Plugin, aşağıdaki meta alanlarını kullanır:
- `ero_seri` - Manga serisi ID
- `ero_chapter` - Bölüm numarası
- `ero_chaptertitle` - Bölüm başlığı

### Ayarlar Sayfası
**Chapter Uploader > Settings** menüsünden:
- **YENİ v1.4.0'da:** Yapılandırılabilir ZIP dosya boyutu limitleri
- Dil seçimi (Türkçe/İngilizce)
- Varsayılan bölüm prefix'i
- Otomatik ana sayfa itme
- Resim kalitesi ayarları
- Performans optimizasyon seçenekleri

## 📚 Kullanım Kılavuzu

### Tekli Bölüm Yükleme
1. **Chapter Uploader** menüsüne gidin
2. **Single Chapter Upload** sekmesini seçin
3. Bölüm numarasını girin veya **Auto +1** butonunu kullanın
4. Manga serisini seçin
5. Resimleri sürükle-bırak alanına atın
6. **Upload Chapter** butonuna tıklayın

### Çoklu Bölüm Yükleme
1. **Multiple Chapters (ZIP)** sekmesini seçin
2. ZIP dosyanızı hazırlayın:
   ```
   manga_chapters.zip
   ├── 1/
   │   ├── 001.jpg
   │   └── 002.jpg
   ├── 2-Yeni Başlangıç/
   │   ├── 001.jpg
   │   └── 002.jpg
   └── 3/
       ├── page1.png
       └── page2.png
   ```
3. ZIP dosyasını seçin ve manga serisini belirtin
4. **YENİ v1.4.0'da:** Garantili sıralama ile gerçek zamanlı sıralı işlemeyi izleyin
5. **Upload ZIP and Process** butonuna tıklayın

### Web Sitesinden Çekme
1. **Fetch from Website** sekmesini seçin
2. Bölümün bulunduğu URL'yi girin
3. **Test URL** ile URL'yi kontrol edin
4. Manga serisini seçin
5. **Fetch from Website** butonuna tıklayın

## 🛠️ Teknik Detaylar

### Desteklenen Dosya Formatları
- JPG, JPEG, PNG, GIF, WebP
- Maksimum dosya boyutu: 10MB (tekli)
- **YENİ v1.4.0'da:** Yapılandırılabilir maksimum ZIP boyutu: 100MB - 5GB

### Ana Sayfa Güncelleme Sistemi
**GELİŞTİRİLDİ v1.4.0'da:** Plugin, bölüm yüklendiğinde manga serisini ana sayfaya itmek için:
- Manga post'unun `post_date` ve `post_modified` tarihlerini güncel zaman ile günceller
- Daha iyi tema uyumluluğu için birden fazla meta alanını günceller
- Kapsamlı cache temizleme (WordPress cache, transients, object cache)
- Tüm ilgili tema hook'larını tetikler
- **YENİ:** İnatçı temalar için zorla ana sayfa görünüm sistemi

### Meta Box Uyumluluğu
Plugin hem Meta Box hem de standart WordPress meta alanlarını destekler:
```php
// Meta Box varsa
rwmb_set_meta($post_id, 'ero_seri', $manga_id);

// Standart WordPress
update_post_meta($post_id, 'ero_seri', $manga_id);
```

### **YENİ v1.4.0'da:** Gelişmiş Loglama Sistemi
- Tüm işlemler için kapsamlı debug loglama
- Hata izleme ve raporlama
- Performans monitörü
- Detaylı yükleme istatistikleri

## 🚨 Sorun Giderme

### Yaygın Sorunlar

**Bölümler ana sayfada görünmüyor:**
- **DÜZELTİLDİ v1.4.0'da:** Birden fazla fallback yöntemi ile geliştirilmiş ana sayfa itme sistemi
- "Push to Homepage" seçeneğinin işaretli olduğundan emin olun
- Cache eklentilerinizi temizleyin
- Tema'nın manga post type desteği olduğunu kontrol edin

**ZIP yükleme çalışmıyor:**
- **GELİŞTİRİLDİ v1.4.0'da:** Daha iyi hata yönetimi ve raporlama
- Sunucuda ZipArchive uzantısının yüklü olduğunu kontrol edin
- PHP memory_limit ve max_execution_time değerlerini artırın
- Dosya izinlerini kontrol edin (wp-content/uploads yazılabilir olmalı)

**Bölümler yanlış sırayla yükleniyor:**
- **DÜZELTİLDİ v1.4.0'da:** Sıralı işleme doğru sırayı garanti eder
- Artık karışık sıralama sorunları yok (161,163,162,167,164 gibi)
- Sıralı işleme: 160 → 161 → 162 → 163 → 164... şeklinde çalışır

**Zamanlanmış postlar yerine anlık yayınlama:**
- **DÜZELTİLDİ v1.4.0'da:** Tüm bölümler artık anında yayınlanıyor
- Artık "zamanlandı" post durumu sorunları yok

**Blogger çekme başarısız:**
- URL'nin doğru olduğundan emin olun
- Site'nin resim koruması olmadığını kontrol edin
- Firewall veya bot koruması olup olmadığını kontrol edin

### Debug Modu
**GELİŞTİRİLDİ v1.4.0'da:** Upload işlemi sırasında hata oluşursa, kapsamlı debug bilgileri otomatik olarak görüntülenir. Yeni loglama sistemi sorun giderme için detaylı bilgiler sağlar.

## 📈 Performans Optimizasyonu

### **YENİ v1.4.0'da:** Performans İyileştirmeleri
- **Sıralı İşleme:** Race condition'ları ortadan kaldırır ve mükemmel sıralama sağlar
- **Optimize Edilmiş Dosya İşlemleri:** Güvenli ve verimli dosya yönetimi
- **Geliştirilmiş Cache'leme:** Daha iyi performans için akıllı cache yönetimi
- **Azaltılmış Bellek Kullanımı:** Büyük ZIP dosyaları için optimize edildi

### Öneriler
- Büyük ZIP dosyaları için PHP timeout değerlerini artırın
- Yoğun kullanım için Redis/Memcached kullanın
- CDN kullanarak resim yükleme hızını artırın
- Düzenli olarak sahipsiz medya temizliği yapın

## 🔒 Güvenlik

### Güvenlik Özellikleri
- **GELİŞTİRİLDİ v1.4.0'da:** İyileştirilmiş dosya validasyonu ve güvenlik kontrolleri
- CSRF koruması (nonce)
- Dosya türü validasyonu
- Dosya boyutu kontrolü
- Kullanıcı yetki kontrolü
- Güvenli dosya adlandırma
- **YENİ:** Güvenli recursive dizin işlemleri

## 📞 Destek

Sorunlarınız veya önerileriniz için:
- **Web:** https://mangaruhu.com
- **GitHub:** https://github.com/turanbagtur/chapter-uploader

## 🔄 Güncelleme Notları

### v1.4.0 - Büyük Kararlılık ve Performans Güncellemesi
#### 🛠️ Kritik Düzeltmeler
- **DÜZELTİLDİ:** Sıralı bölüm düzeni - artık karışık sıralama yok (161,163,162 → 160,161,162,163...)
- **DÜZELTİLDİ:** Zamanlanmış post sorunları - tüm bölümler artık anında yayınlanıyor
- **DÜZELTİLDİ:** PHP dosya işlem hataları (unlink, filesize uyarıları ortadan kaldırıldı)
- **DÜZELTİLDİ:** WordPress cron hataları, özel schedule tanımları ile çözüldü
- **DÜZELTİLDİ:** Ana sayfa itme sistemi güvenilirlik için tamamen yeniden yapıldı

#### 🚀 Performans İyileştirmeleri
- **YENİ:** Garantili bölüm sırası için sıralı işleme sistemi
- **YENİ:** Detaylı durum güncellemeleri ile gerçek zamanlı ilerleme takibi
- **YENİ:** Gelişmiş hata yönetimi ve kurtarma mekanizmaları
- **YENİ:** Daha iyi performans için optimize edilmiş dosya işlemleri
- **YENİ:** Debug için gelişmiş loglama sistemi

#### 🎛️ Yeni Özellikler
- **YENİ:** Yapılandırılabilir ZIP dosya boyutu limitleri (100MB - 5GB)
- **YENİ:** Daha fazla özelleştirme seçeneği ile geliştirilmiş ayarlar sayfası
- **YENİ:** Kapsamlı debug bilgisi görüntüleme
- **YENİ:** Daha iyi tema uyumluluğu için çoklu meta alanı desteği
- **YENİ:** Zorla ana sayfa görünüm sistemi

#### 🔧 Teknik İyileştirmeler
- **GELİŞTİRİLDİ:** Güvenli recursive dizin işlemleri
- **GELİŞTİRİLDİİ:** Gelişmiş cache yönetimi
- **GELİŞTİRİLDİ:** Daha iyi WordPress hook entegrasyonu
- **GELİŞTİRİLDİ:** Kapsamlı transient temizleme
- **GELİŞTİRİLDİ:** Geliştirilmiş tema uyumluluğu

### v1.3.1
- Çoklu bölüm yükleme sistemi tamamlandı
- Blogger çekme işlemine kategori desteği eklendi
- Dil değiştirme sistemi iyileştirildi
- Ana sayfa güncelleme sistemi güçlendirildi
- Hata yönetimi iyileştirildi

## 📜 Lisans

Bu eklenti GPL2 lisansı altında dağıtılmaktadır. Ücretsiz olarak kullanabilir, değiştirebilir ve dağıtabilirsiniz.

---

**Not:** Bu eklenti özellikle MangaReader teması için optimize edilmiştir. v1.4.0 sürümü büyük kararlılık iyileştirmeleri içerir ve tüm kullanıcılar için önerilir.