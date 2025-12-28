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
    
    $('#single-chapter-btn').click();

    // Manga seçimi değiştiğinde
    $('#single-manga-series, #multiple-manga-series, #blogger-manga-series').change(function() {
        var selectedOption = $(this).find('option:selected');
        var chapterCount = selectedOption.data('chapters') || 0;
        var categoryId = selectedOption.data('category') || 0;
        var formType = $(this).attr('id').split('-')[0];
        
        if (chapterCount > 0 && formType === 'single') {
            $('#manga-info').show();
            $('#current-chapter-count').text(chapterCount);
        } else if (formType === 'single') {
            $('#manga-info').hide();
        }
        
        if (categoryId > 0) {
            $('#' + formType + '-chapter-category').val(categoryId);
        }
    });

    // ZIP dosya boyutu kontrolü
    $('#multiple-zip-file').on('change', function() {
        var files = this.files;
        $('#zip-size-info').remove();
        
        if (files.length > 0) {
            var file = files[0];
            var maxZipSize = mangaUploaderAjax.max_zip_size || 524288000;
            var sizeInfo = '<div id="zip-size-info" style="margin-top: 10px;">';
            sizeInfo += '<p><strong>ZIP:</strong> ' + file.name + ' (' + formatBytes(file.size) + ')</p>';
            if (file.size > maxZipSize) {
                sizeInfo += '<p style="color: #d63638;">⚠ Dosya çok büyük! Max: ' + formatBytes(maxZipSize) + '</p>';
            } else {
                sizeInfo += '<p style="color: #46b450;">✓ Boyut uygun</p>';
            }
            sizeInfo += '</div>';
            $(this).parent().append(sizeInfo);
        }
    });

    // Programlanmış yayınlama
    $('#schedule-publish').change(function() {
        if ($(this).is(':checked')) {
            $('#schedule-fields').show();
        } else {
            $('#schedule-fields').hide();
        }
    });

    // Auto increment
    $('#auto-increment-btn').click(function() {
        var mangaId = $('#single-manga-series').val();
        var button = $(this);
        if (!mangaId) { alert('Önce manga serisi seçin'); return; }
        button.prop('disabled', true).text('...');
        $.post(mangaUploaderAjax.ajaxurl, {
            action: 'auto_increment_chapter',
            manga_id: mangaId,
            nonce: mangaUploaderAjax.nonce
        }, function(response) {
            if (response.success) {
                $('#single-chapter-number').val(response.data.next_chapter);
            }
            button.prop('disabled', false).text('Auto +1');
        });
    });

    // Sürükle-bırak
    var singleDragDropArea = $('#single-chapter-drag-drop');
    var singleFileInput = $('#single-chapter-images');
    var singleSelectedFilesInfo = $('#single-selected-files-info');

    singleDragDropArea.on('click', function(e) {
        if (e.target.id !== singleFileInput.attr('id')) {
            singleFileInput.trigger('click');
        }
    });

    singleFileInput.on('change', function() {
        updateSelectedFilesInfo(this.files);
    });

    singleDragDropArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    }).on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
    }).on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            singleFileInput[0].files = files;
            updateSelectedFilesInfo(files);
        }
    });

    function updateSelectedFilesInfo(files) {
        if (files.length > 0) {
            var totalSize = Array.from(files).reduce(function(t, f) { return t + f.size; }, 0);
            singleSelectedFilesInfo.html('<p><strong>' + files.length + ' resim</strong> (' + formatBytes(totalSize) + ')</p>');
        } else {
            singleSelectedFilesInfo.html('');
        }
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // URL Test
    $('#test-blogger-url').click(function() {
        var url = $('#blogger-url').val();
        var button = $(this);
        if (!url) { $('#url-test-result').html('<span style="color:red">URL girin</span>'); return; }
        button.prop('disabled', true).text('Test...');
        $.post(mangaUploaderAjax.ajaxurl, {
            action: 'test_blogger_url',
            blogger_url: url,
            nonce: mangaUploaderAjax.nonce
        }, function(response) {
            if (response.success) {
                $('#url-test-result').html('<span style="color:green">✓ ' + response.data.images_found + ' resim bulundu</span>');
            } else {
                $('#url-test-result').html('<span style="color:orange">⚠ ' + response.data.message + '</span>');
            }
            button.prop('disabled', false).text('URL Test');
        });
    });

    // Form validasyonları
    function validateSingleForm() {
        if (!$('#single-chapter-number').val()) { alert('Bölüm numarası girin'); return false; }
        if (!$('#single-manga-series').val()) { alert('Manga seçin'); return false; }
        if (!$('#single-chapter-images')[0].files.length) { alert('Resim seçin'); return false; }
        return true;
    }

    function validateMultipleForm() {
        if (!$('#multiple-manga-series').val()) { alert('Manga seçin'); return false; }
        var zipFile = $('#multiple-zip-file')[0].files[0];
        if (!zipFile) { alert('ZIP dosyası seçin'); return false; }
        var maxZipSize = mangaUploaderAjax.max_zip_size || 524288000;
        if (zipFile.size > maxZipSize) {
            alert('ZIP dosyası çok büyük! Max: ' + formatBytes(maxZipSize));
            return false;
        }
        return true;
    }

    // TEKLİ BÖLÜM YÜKLEME
    $('#single-upload-form').submit(function(e) {
        e.preventDefault();
        if (!validateSingleForm()) return false;
        
        var formData = new FormData(this);
        formData.append('action', 'upload_single_chapter');
        formData.append('nonce', mangaUploaderAjax.nonce);
        
        $('#upload-progress').show();
        $('#upload-results').html('');
        $('#submit_single_chapter').prop('disabled', true).val('Yükleniyor...');

        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 300000,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100);
                        $('.progress-fill').css('width', pct + '%');
                        $('#progress-text').text(pct + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#upload-progress').hide();
                if (response.success) {
                    $('#upload-results').html('<div class="upload-result upload-success">✓ ' + response.data.message + ' <a href="' + response.data.post_link + '" target="_blank">Görüntüle</a></div>');
                    $('#single-upload-form')[0].reset();
                    singleSelectedFilesInfo.html('');
                } else {
                    $('#upload-results').html('<div class="upload-result upload-error">✗ ' + (response.data.message || 'Hata') + '</div>');
                }
                $('.progress-fill').css('width', '0%');
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                $('#upload-results').html('<div class="upload-result upload-error">Hata: ' + (status === 'timeout' ? 'Zaman aşımı' : error) + '</div>');
            },
            complete: function() {
                $('#submit_single_chapter').prop('disabled', false).val('Bölümü Yükle');
            }
        });
    });

    // ÇOKLU BÖLÜM YÜKLEME - AŞAMALI İŞLEME (Timeout önleme)
    $('#multiple-upload-form').submit(function(e) {
        e.preventDefault();
        if (!validateMultipleForm()) return false;
        
        var form = this;
        var mangaId = $('#multiple-manga-series').val();
        var chapterCategory = $('#multiple-chapter-category').val() || '';
        var chapterPrefix = $('#multiple-chapter-prefix').val() || 'chapter';
        var pushToLatest = $('input[name="push_to_latest"]:checked').val() === '1' ? '1' : '0';
        
        $('#upload-progress').show();
        $('#upload-results').html('<div class="upload-result">ZIP yükleniyor ve çıkarılıyor...</div>');
        $('#submit_multiple_chapters').prop('disabled', true);
        
        // AŞAMA 1: ZIP'i yükle ve hazırla
        var formData = new FormData(form);
        formData.append('action', 'mcu_prepare_zip');
        formData.append('nonce', mangaUploaderAjax.nonce);
        
        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 300000,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100);
                        $('.progress-fill').css('width', pct + '%');
                        $('#progress-text').text('Yükleme: ' + pct + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (!response.success) {
                    $('#upload-progress').hide();
                    $('#upload-results').html('<div class="upload-result upload-error">Hata: ' + (response.data ? response.data.message : 'Bilinmeyen hata') + '</div>');
                    $('#submit_multiple_chapters').prop('disabled', false);
                    return;
                }
                
                // AŞAMA 2: SIRALI bölüm işleme (doğru sıralama için) - İYİLEŞTİRİLMİŞ
                var sessionId = response.data.session_id;
                var chapters = response.data.chapters;
                var total = chapters.length;
                var completed = 0;
                var results = [];
                var successCount = 0;
                var currentIndex = 0;
                
                // Bölümleri sayısal sıraya koy (160, 161, 162 gibi)
                chapters.sort(function(a, b) {
                    return parseFloat(a.number) - parseFloat(b.number);
                });
                
                $('#upload-results').html('<div class="upload-result">' + total + ' bölüm bulundu. Sıralı işleniyor (doğru sıralama): ' +
                    chapters.map(function(c) { return c.number; }).join(', ') + '</div>');
                
                function updateProgress() {
                    var pct = Math.round((completed / total) * 100);
                    $('.progress-fill').css('width', pct + '%');
                    var currentChapter = currentIndex < total ? chapters[currentIndex].number : 'N/A';
                    $('#progress-text').text('İşleniyor: ' + completed + '/' + total + ' (' + pct + '%) - Şu an: Bölüm ' + currentChapter);
                }
                
                function finalize() {
                    $.post(mangaUploaderAjax.ajaxurl, {
                        action: 'mcu_finalize_upload',
                        nonce: mangaUploaderAjax.nonce,
                        session_id: sessionId,
                        manga_id: mangaId,
                        push_to_latest: pushToLatest
                    }, function() {
                        $('#upload-progress').hide();
                        // Sonuçları sayısal sıraya koy
                        results.sort(function(a, b) {
                            return parseFloat(a.number) - parseFloat(b.number);
                        });
                        var html = '<div class="upload-result upload-success"><strong>Tamamlandı:</strong> ' + successCount + '/' + total + ' bölüm yüklendi.</div>';
                        results.forEach(function(r) {
                            if (r.success) {
                                html += '<div class="upload-result upload-success-item">✓ Bölüm ' + r.number + ' (' + r.folder + ') <a href="' + r.link + '" target="_blank">Görüntüle</a></div>';
                            } else {
                                html += '<div class="upload-result upload-error-item">✗ Bölüm ' + r.number + ' (' + r.folder + '): ' + r.message + '</div>';
                            }
                        });
                        $('#upload-results').html(html);
                        $('#submit_multiple_chapters').prop('disabled', false);
                        $('.progress-fill').css('width', '0%');
                        $('#progress-text').text('');
                        $('#zip-size-info').remove();
                    });
                }
                
                // SIRALI TEKLİ İŞLEME: Sıralama garantisi için
                function processNextChapter() {
                    // Tamamlandı mı kontrol et
                    if (currentIndex >= total) {
                        finalize();
                        return;
                    }
                    
                    var chapter = chapters[currentIndex];
                    var chapterNum = chapter.number;
                    var chapterFolder = chapter.folder;
                    currentIndex++;
                    
                    $('#upload-results').append('<div id="chapter-' + chapterNum + '" class="chapter-processing">⚳ Bölüm ' + chapterNum + ' (' + chapterFolder + ') işleniyor... [' + currentIndex + '/' + total + ']</div>');
                    
                    $.post(mangaUploaderAjax.ajaxurl, {
                        action: 'mcu_process_chapter',
                        nonce: mangaUploaderAjax.nonce,
                        session_id: sessionId,
                        folder: chapterFolder,
                        manga_id: mangaId,
                        chapter_category: chapterCategory,
                        chapter_prefix: chapterPrefix
                    }).done(function(res) {
                        var isSuccess = res && res.success;
                        if (isSuccess) successCount++;
                        results.push({
                            folder: chapterFolder,
                            number: chapterNum,
                            success: isSuccess,
                            message: res && res.data ? res.data.message : 'Hata',
                            link: res && res.data ? res.data.post_link : ''
                        });
                        
                        $('#chapter-' + chapterNum).removeClass('chapter-processing')
                            .addClass(isSuccess ? 'chapter-success' : 'chapter-error')
                            .html((isSuccess ? '✓' : '✗') + ' Bölüm ' + chapterNum + ' (' + chapterFolder + ') - ' +
                                (isSuccess ? 'Başarılı!' : (res && res.data ? res.data.message : 'Hata')));
                    }).fail(function() {
                        results.push({
                            folder: chapterFolder,
                            number: chapterNum,
                            success: false,
                            message: 'Bağlantı hatası'
                        });
                        $('#chapter-' + chapterNum).removeClass('chapter-processing').addClass('chapter-error')
                            .html('✗ Bölüm ' + chapterNum + ' (' + chapterFolder + ') - Bağlantı hatası');
                    }).always(function() {
                        completed++;
                        updateProgress();
                        
                        // Hızlı sıralı işleme - minimum gecikme
                        setTimeout(processNextChapter, 10);
                    });
                }
                
                // İlk bölümü başlat
                updateProgress();
                processNextChapter();
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                var msg = status === 'timeout' ? 'ZIP yükleme zaman aşımına uğradı' : error;
                $('#upload-results').html('<div class="upload-result upload-error">Hata: ' + msg + '</div>');
                $('#submit_multiple_chapters').prop('disabled', false);
            }
        });
    });

    // BLOGGER/WEB SİTESİNDEN ÇEKME
    $('#blogger-fetch-form-content').submit(function(e) {
        e.preventDefault();
        
        var bloggerUrl = $('#blogger-url').val();
        var mangaSeries = $('#blogger-manga-series').val();
        var chapterCategory = $('#blogger-chapter-category').val() || '';
        var pushToLatest = $('input[name="push_to_latest"]:checked').val() === '1' ? '1' : '0';
        
        if (!bloggerUrl) { alert('URL girin'); return false; }
        if (!mangaSeries) { alert('Manga seçin'); return false; }

        var formData = new FormData(this);
        formData.append('action', 'handle_blogger_fetch');
        formData.append('nonce', mangaUploaderAjax.nonce);
        formData.append('chapter_category', chapterCategory);
        formData.append('push_to_latest', pushToLatest);
        
        $('#upload-progress').show();
        $('#upload-results').html('');
        $('#submit_blogger_fetch').prop('disabled', true).val('İndiriliyor...');

        $.ajax({
            url: mangaUploaderAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 300000,
            success: function(response) {
                $('#upload-progress').hide();
                if (response.success) {
                    $('#upload-results').html('<div class="upload-result upload-success">✓ ' + response.data.message + ' <a href="' + response.data.post_link + '" target="_blank">Görüntüle</a></div>');
                } else {
                    $('#upload-results').html('<div class="upload-result upload-error">✗ ' + (response.data.message || 'Hata') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#upload-progress').hide();
                $('#upload-results').html('<div class="upload-result upload-error">Hata: ' + (status === 'timeout' ? 'Zaman aşımı' : error) + '</div>');
            },
            complete: function() {
                $('#submit_blogger_fetch').prop('disabled', false).val("Web'den Çek");
                $('.progress-fill').css('width', '0%');
            }
        });
    });

    // İstatistik sayfası
    $('#cleanup-orphaned').click(function() {
        if (!confirm('Sahipsiz medyaları silmek istediğinize emin misiniz?')) return;
        var button = $(this);
        button.prop('disabled', true).text('Temizleniyor...');
        $.post(mangaUploaderAjax.ajaxurl, {
            action: 'cleanup_orphaned_media',
            nonce: mangaUploaderAjax.nonce
        }, function(response) {
            alert(response.success ? response.data.message : 'Hata: ' + response.data.message);
            button.prop('disabled', false).text('Sahipsiz Medyaları Temizle');
        });
    });

    $('#refresh-stats').click(function() {
        location.reload();
    });
});
