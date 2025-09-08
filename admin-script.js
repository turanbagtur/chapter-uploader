jQuery(document).ready(function($) {
    // Form seçimi butonları
    $('#single-chapter-btn').click(function() {
        $('.upload-type-selector button').removeClass('button-primary').addClass('button-secondary');
        $(this).addClass('button-primary');
        $('.upload-form').hide();
        $('#single-chapter-form').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
    });
    
    $('#multiple-chapter-btn').click(function() {
        $('.upload-type-selector button').removeClass('button-primary').addClass('button-secondary');
        $(this).addClass('button-primary');
        $('.upload-form').hide();
        $('#multiple-chapter-form').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
    });

    $('#blogger-fetch-btn').click(function() {
        $('.upload-type-selector button').removeClass('button-primary').addClass('button-secondary');
        $(this).addClass('button-primary');
        $('.upload-form').hide();
        $('#blogger-fetch-form').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
    });
    
    // Sayfa yüklendiğinde varsayılan olarak tekli formu göster
    $('#single-chapter-btn').click();

    // Manga seçimi değiştiğinde bölüm sayısını ve kategoriyi göster - TÜM FORMLAR İÇİN
    $('#single-manga-series, #multiple-manga-series, #blogger-manga-series').change(function() {
        var selectedOption = $(this).find('option:selected');
        var chapterCount = selectedOption.data('chapters') || 0;
        var categoryId = selectedOption.data('category') || 0;
        var formType = $(this).attr('id').split('-')[0]; // single, multiple, blogger
        
        if (chapterCount > 0 && formType === 'single') {
            $('#manga-info').show();
            $('#current-chapter-count').text(chapterCount);
            $('#auto-selected-category').text('Auto-selected');
        } else if (formType === 'single') {
            $('#manga-info').hide();
        }
        
        // Kategori otomatik seçimi - tüm formlar için
        if (categoryId > 0) {
            $('#' + formType + '-chapter-category').val(categoryId).prop('disabled', false).prop('disabled', true);
            
            if (formType === 'single') {
                var categoryName = $('#' + formType + '-chapter-category option[value="' + categoryId + '"]').text();
                $('#auto-selected-category').text(categoryName || 'Auto-selected');
            }
        } else {
            $('#' + formType + '-chapter-category').val('').prop('disabled', true);
            
            if (formType === 'single') {
                $('#auto-selected-category').text('Not found');
            }
        }
    });

    // YENİ EKLENEN: ZIP dosya boyutu kontrolü
    $('#multiple-zip-file').on('change', function() {
        var files = this.files;
        var zipSizeInfo = $('#zip-size-info');
        
        // Mevcut bilgi div'ini kaldır
        zipSizeInfo.remove();
        
        if (files.length > 0) {
            var file = files[0];
            var maxZipSize = mangaUploaderAjax.max_zip_size || 524288000; // 500MB varsayılan
            var fileSize = file.size;
            
            // Dosya boyutu bilgisini göster
            var sizeInfo = '<div id="zip-size-info" style="margin-top: 10px;">';
            sizeInfo += '<p><strong>Seçilen ZIP:</strong> ' + file.name + '</p>';
            sizeInfo += '<p><strong>Dosya Boyutu:</strong> ' + formatBytes(fileSize) + '</p>';
            
            if (fileSize > maxZipSize) {
                sizeInfo += '<p style="color: #d63638;"><strong>⚠ Uyarı:</strong> Dosya çok büyük! Maksimum boyut: ' + formatBytes(maxZipSize) + '</p>';
            } else {
                sizeInfo += '<p style="color: #46b450;"><strong>✓ OK:</strong> Dosya boyutu uygun</p>';
            }
            
            sizeInfo += '</div>';
            $(this).parent().append(sizeInfo);
        }
    });

    // Programlanmış yayınlama toggle
    $('#schedule-publish').change(function() {
        if ($(this).is(':checked')) {
            $('#schedule-fields').show();
            $('#publish-date').attr('required', true);
        } else {
            $('#schedule-fields').hide();
            $('#publish-date').removeAttr('required');
        }
    });

    // Auto increment butonu
    $('#auto-increment-btn').click(function() {
        var mangaId = $('#single-manga-series').val();
        var button = $(this);
        
        if (!mangaId) {
            alert('Önce manga serisi seçin');
            return;
        }
        
        button.prop('disabled', true).text('Yükleniyor...');
        
        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'auto_increment_chapter',
                manga_id: mangaId,
                nonce: mangaUploaderAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#single-chapter-number').val(response.data.next_chapter);
                } else {
                    alert('Hata: ' + response.data.message);
                }
            },
            error: function() {
                alert('Bağlantı hatası oluştu');
            },
            complete: function() {
                button.prop('disabled', false).text('Auto +1');
            }
        });
    });

    // Tekli bölüm yükleme - Sürükle-bırak ve dosya seçme alanı
    var singleDragDropArea = $('#single-chapter-drag-drop');
    var singleFileInput = $('#single-chapter-images');
    var singleSelectedFilesInfo = $('#single-selected-files-info');

    // Sürükle-bırak alanına tıklanınca gizli dosya seçme penceresini aç
    singleDragDropArea.on('click', function(e) {
        if (e.target.id !== singleFileInput.attr('id')) {
            singleFileInput.trigger('click');
        }
    });

    // Dosya inputunda değişiklik olduğunda (dosya seçildiğinde veya sürükle-bırak ile geldiğinde)
    singleFileInput.on('change', function() {
        var files = this.files;
        updateSelectedFilesInfo(files);
    });

    // Sürükle-bırak olayları
    singleDragDropArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });

    singleDragDropArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });

    singleDragDropArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');

        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            singleFileInput[0].files = files;
            updateSelectedFilesInfo(files);
        } else {
            singleSelectedFilesInfo.html('');
        }
    });

    // Seçilen/sürüklenen dosyaların bilgisini güncelleyen yardımcı fonksiyon
    function updateSelectedFilesInfo(files) {
        if (files.length > 0) {
            var fileNames = Array.from(files).map(file => file.name).join(', ');
            var totalSize = Array.from(files).reduce((total, file) => total + file.size, 0);
            
            singleSelectedFilesInfo.html(
                '<p><strong>Seçilen Dosyalar:</strong> ' + files.length + ' resim</p>' +
                '<p><strong>Toplam Boyut:</strong> ' + formatBytes(totalSize) + '</p>' +
                '<div class="file-list" style="max-height: 100px; overflow-y: auto; font-size: 12px; color: #666;">' +
                fileNames + '</div>'
            );
        } else {
            singleSelectedFilesInfo.html('');
        }
    }

    // Dosya boyutunu formatlamak için yardımcı fonksiyon
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // Blogger URL test fonksiyonu
    $('#test-blogger-url').click(function() {
        var url = $('#blogger-url').val();
        var button = $(this);
        var resultDiv = $('#url-test-result');
        
        if (!url) {
            resultDiv.html('<div style="color: red; margin-top: 5px;">Lütfen bir URL girin</div>');
            return;
        }
        
        button.prop('disabled', true).text('Test Ediliyor...');
        resultDiv.html('<div style="color: blue; margin-top: 5px;">URL test ediliyor...</div>');
        
        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_blogger_url',
                blogger_url: url,
                nonce: mangaUploaderAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div style="color: green; margin-top: 5px;">✓ URL erişilebilir (' + response.data.images_found + ' resim bulundu)</div>');
                } else {
                    resultDiv.html('<div style="color: orange; margin-top: 5px;">⚠ ' + response.data.message + '</div>');
                }
            },
            error: function() {
                resultDiv.html('<div style="color: red; margin-top: 5px;">✗ URL test edilemedi</div>');
            },
            complete: function() {
                button.prop('disabled', false).text('URL\'yi Test Et');
            }
        });
    });

    // Form validasyon fonksiyonları
    function validateSingleForm() {
        var chapterNumber = $('#single-chapter-number').val();
        var mangaSeries = $('#single-manga-series').val();
        var files = $('#single-chapter-images')[0].files;
        
        if (!chapterNumber) {
            alert(mangaUploaderAjax.text.enter_chapter_number);
            return false;
        }
        
        if (!mangaSeries) {
            alert(mangaUploaderAjax.text.select_manga);
            return false;
        }
        
        if (!files || files.length === 0) {
            alert('Lütfen en az bir resim seçin');
            return false;
        }
        
        // Dosya boyutu ve türü kontrolü
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            
            if (file.size > mangaUploaderAjax.max_file_size) {
                alert(mangaUploaderAjax.text.file_too_large + ': ' + file.name);
                return false;
            }
            
            var fileExtension = file.name.split('.').pop().toLowerCase();
            if (mangaUploaderAjax.allowed_types.indexOf(fileExtension) === -1) {
                alert(mangaUploaderAjax.text.invalid_file_type + ': ' + file.name);
                return false;
            }
        }
        
        return true;
    }

    // Çoklu bölüm form validasyonu - GÜNCELLENMİŞ
    function validateMultipleForm() {
        var mangaSeries = $('#multiple-manga-series').val();
        var zipFile = $('#multiple-zip-file')[0].files[0];
        
        if (!mangaSeries) {
            alert(mangaUploaderAjax.text.select_manga);
            return false;
        }
        
        if (!zipFile) {
            alert('Lütfen bir ZIP dosyası seçin');
            return false;
        }
        
        // YENİ EKLENEN: ZIP dosya boyutu kontrolü
        var maxZipSize = mangaUploaderAjax.max_zip_size || 524288000; // 500MB varsayılan
        if (zipFile.size > maxZipSize) {
            alert(mangaUploaderAjax.text.zip_too_large + ': ' + formatBytes(zipFile.size) + ' (Maksimum: ' + formatBytes(maxZipSize) + ')');
            return false;
        }
        
        return true;
    }

    // Tekli bölüm yükleme AJAX
    $('#single-upload-form').submit(function(e) {
        e.preventDefault();
        
        if (!validateSingleForm()) {
            return false;
        }
        
        var formData = new FormData(this);
        formData.append('action', 'upload_single_chapter');
        formData.append('nonce', mangaUploaderAjax.nonce); 
        
        $('#upload-progress').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
        $('#submit_single_chapter').prop('disabled', true).val('Yükleniyor...');

        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 300000, // 5 dakika timeout
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $('.progress-fill').css('width', percentComplete + '%');
                        $('#progress-text').text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#upload-progress').hide();
                
                if (response.success) {
                    $('#upload-results').html('<div class="upload-result upload-success"><strong>Başarılı:</strong> ' + response.data.message + ' <a href="' + response.data.post_link + '" target="_blank">Bölümü Görüntüle</a></div>');
                    
                    // Formu sıfırla ama önemli alanları koru
                    var currentMangaSeries = $('#single-manga-series').val();
                    var currentChapterCategory = $('#single-chapter-category').val();
                    var currentChapterPrefix = $('#single-chapter-prefix').val();
                    
                    $('#single-upload-form')[0].reset();
                    $('#single-manga-series').val(currentMangaSeries).trigger('change');
                    $('#single-chapter-category').val(currentChapterCategory);
                    $('#single-chapter-prefix').val(currentChapterPrefix);
                    singleSelectedFilesInfo.html('');
                    singleFileInput[0].value = '';
                    
                    // Progress bar sıfırla
                    $('.progress-fill').css('width', '0%');
                    $('#progress-text').text('0%');
                } else {
                    $('#upload-results').html('<div class="upload-result upload-error"><strong>Hata:</strong> ' + (response.data.message || 'Bilinmeyen bir hata oluştu.') + '</div>');
                    
                    if (response.data.debug) {
                        $('#debug-content').html('<pre>' + JSON.stringify(response.data.debug, null, 2) + '</pre>');
                        $('#debug-info').show();
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                
                var errorMsg = 'Yükleme sırasında bir hata oluştu: ';
                if (status === 'timeout') {
                    errorMsg += 'İşlem zaman aşımına uğradı. Lütfen tekrar deneyin.';
                } else if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMsg += response.data ? response.data.message : error;
                    } catch(e) {
                        errorMsg += error;
                    }
                } else {
                    errorMsg += error;
                }
                
                $('#upload-results').html('<div class="upload-result upload-error"><strong>' + errorMsg + '</strong></div>');
                console.error("AJAX error:", status, error, xhr.responseText);
            },
            complete: function() {
                $('#submit_single_chapter').prop('disabled', false).val('Bölümü Yükle');
                $('.progress-fill').css('width', '0%');
                $('#progress-text').text('0%');
            }
        });
    });

    // Çoklu bölüm yükleme AJAX - GÜNCELLENMİŞ
    $('#multiple-upload-form').submit(function(e) {
        e.preventDefault();
        
        if (!validateMultipleForm()) {
            return false;
        }
        
        var formData = new FormData(this);
        formData.append('action', 'upload_multiple_chapters');
        formData.append('nonce', mangaUploaderAjax.nonce);
        
        $('#upload-progress').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
        $('#submit_multiple_chapters').prop('disabled', true);

        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 900000, // 15 dakika timeout (büyük ZIP dosyaları için)
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $('.progress-fill').css('width', percentComplete + '%');
                        $('#progress-text').text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#upload-progress').hide();
                if (response.success) {
                    var resultsHtml = '<div class="upload-result upload-success"><strong>Başarılı:</strong> ' + response.data.message + '</div>';
                    if (response.data.results && Array.isArray(response.data.results)) {
                        response.data.results.forEach(function(res) {
                            if (res.status === 'success') {
                                resultsHtml += '<div class="upload-result upload-success-item">✓ ' + res.message + ' <a href="' + res.post_link + '" target="_blank">Görüntüle</a></div>';
                            } else {
                                resultsHtml += '<div class="upload-result upload-error-item">✗ ' + res.message + ' (Klasör: ' + (res.folder || '') + ')</div>';
                            }
                        });
                    }
                    $('#upload-results').html(resultsHtml);
                   
                    // Formu sıfırla ama önemli alanları koru
                    var currentMangaSeries = $('#multiple-manga-series').val();
                    var currentChapterCategory = $('#multiple-chapter-category').val();
                    var currentChapterPrefix = $('#multiple-chapter-prefix').val();
                    
                    $('#multiple-upload-form')[0].reset();
                    $('#multiple-manga-series').val(currentMangaSeries).trigger('change');
                    $('#multiple-chapter-category').val(currentChapterCategory);
                    $('#multiple-chapter-prefix').val(currentChapterPrefix);
                    
                    // ZIP boyut bilgisini temizle
                    $('#zip-size-info').remove();

                } else {
                    $('#upload-results').html('<div class="upload-result upload-error"><strong>Hata:</strong> ' + (response.data.message || 'Bilinmeyen bir hata oluştu.') + '</div>');
                     if (response.data.debug) {
                        $('#debug-content').html('<pre>' + JSON.stringify(response.data.debug, null, 2) + '</pre>');
                        $('#debug-info').show();
                    }
                }
                $('.progress-fill').css('width', '0%');
                $('#progress-text').text('0%');
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                
                var errorMsg = 'Yükleme sırasında bir hata oluştu: ';
                if (status === 'timeout') {
                    errorMsg += 'İşlem zaman aşımına uğradı. Büyük ZIP dosyaları için sunucu ayarlarını kontrol edin.';
                } else {
                    errorMsg += error;
                }
                
                $('#upload-results').html('<div class="upload-result upload-error"><strong>' + errorMsg + '</strong></div>');
                console.error("AJAX error:", status, error, xhr.responseText);
                $('.progress-fill').css('width', '0%');
                $('#progress-text').text('0%');
            },
            complete: function() {
                $('#submit_multiple_chapters').prop('disabled', false);
            }
        });
    });

    // Blogger Bölüm Çekme AJAX
    $('#blogger-fetch-form-content').submit(function(e) {
        e.preventDefault();

        var bloggerUrl = $('#blogger-url').val();
        var mangaSeries = $('#blogger-manga-series').val();
        
        if (!bloggerUrl) {
            alert('Lütfen Blogger URL\'si girin');
            return false;
        }
        
        if (!mangaSeries) {
            alert(mangaUploaderAjax.text.select_manga);
            return false;
        }

        var formData = new FormData(this);
        formData.append('action', 'handle_blogger_fetch');
        formData.append('nonce', mangaUploaderAjax.nonce);
        
        $('#upload-progress').show();
        $('#upload-results').html('');
        $('#debug-info').hide();
        $('#submit_blogger_fetch').prop('disabled', true).val('İndiriliyor...');

        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 300000, // 5 dakika timeout
            success: function(response) {
                $('#upload-progress').hide();
                
                if (response.success) {
                    var resultsHtml = '<div class="upload-result upload-success"><strong>Başarılı:</strong> ' + response.data.message + ' <a href="' + response.data.post_link + '" target="_blank">Bölümü Görüntüle</a></div>';
                     if (response.data.results && Array.isArray(response.data.results)) {
                        response.data.results.forEach(function(res) {
                            if (res.status === 'error') {
                                resultsHtml += '<div class="upload-result upload-error-item">✗ ' + res.message + '</div>';
                            }
                        });
                    }
                    $('#upload-results').html(resultsHtml);
                    
                    // Formu sıfırla ama önemli alanları koru
                    var currentMangaSeries = $('#blogger-manga-series').val();
                    var currentChapterCategory = $('#blogger-chapter-category').val();
                    
                    $('#blogger-fetch-form-content')[0].reset();
                    $('#blogger-manga-series').val(currentMangaSeries);
                    $('#blogger-chapter-category').val(currentChapterCategory);
                } else {
                    $('#upload-results').html('<div class="upload-result upload-error"><strong>Hata:</strong> ' + (response.data.message || 'Bilinmeyen bir hata oluştu.') + '</div>');
                    if (response.data.debug) {
                        $('#debug-content').html('<pre>' + JSON.stringify(response.data.debug, null, 2) + '</pre>');
                        $('#debug-info').show();
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                
                var errorMsg = 'Çekme işlemi sırasında bir hata oluştu: ';
                if (status === 'timeout') {
                    errorMsg += 'İşlem zaman aşımına uğradı. Lütfen tekrar deneyin.';
                } else {
                    errorMsg += error;
                }
                
                $('#upload-results').html('<div class="upload-result upload-error"><strong>' + errorMsg + '</strong></div>');
                console.error("AJAX error:", status, error, xhr.responseText);
            },
            complete: function() {
                $('#submit_blogger_fetch').prop('disabled', false).val('Website\'den Çek');
                $('.progress-fill').css('width', '0%');
                $('#progress-text').text('0%');
            }
        });
    });

    // İstatistik sayfası için işlevler
    if ($('#cleanup-orphaned').length) {
        $('#cleanup-orphaned').click(function() {
            if (confirm(mangaUploaderAjax.text.confirm_cleanup)) {
                var button = $(this);
                button.prop('disabled', true).text('Temizleniyor...');
                
                $.ajax({
                    url: mangaUploaderAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cleanup_orphaned_media',
                        nonce: mangaUploaderAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Başarıyla temizlendi: ' + response.data.message);
                        } else {
                            alert('Hata: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Temizleme işlemi sırasında bir hata oluştu');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Sahipsiz Medyaları Temizle');
                    }
                });
            }
        });
    }

    if ($('#refresh-stats').length) {
        $('#refresh-stats').click(function() {
            location.reload();
        });
    }
});