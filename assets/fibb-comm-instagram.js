/* global fibbIgData, wp */
(function ($) {
    'use strict';

    var ajaxurl = fibbIgData.ajaxurl;
    var nonce   = fibbIgData.nonce;

    /* ── MEDIA PICKER ─────────────────────────────────────────── */
    var mediaFrame;

    $('#fibb-ig-media-btn').on('click', function () {
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = wp.media({
            title:    'Choisir une image pour Instagram',
            button:   { text: 'Sélectionner' },
            multiple: false,
            library:  { type: 'image' },
        });
        mediaFrame.on('select', function () {
            var att = mediaFrame.state().get('selection').first().toJSON();
            $('#fibb-ig-image-url').val(att.url);
            $('#fibb-ig-preview-img').attr('src', att.url);
            $('#fibb-ig-preview-wrap').show();
            $('#fibb-ig-add-msg').hide();
        });
        mediaFrame.open();
    });

    /* ── UPLOAD DIRECT : prévisualisation locale ──────────────── */
    $('#fibb-ig-upload-file').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#fibb-ig-upload-preview-img').attr('src', e.target.result);
            $('#fibb-ig-upload-preview-wrap').show();
        };
        reader.readAsDataURL(file);
    });

    /* ── APERÇU LÉGENDE ───────────────────────────────────────── */
    function previewCaption(captionEl, previewEl) {
        var caption = captionEl.val().trim();
        $.post(ajaxurl, {
            action:    'fibb_ig_preview_caption',
            _ajax_nonce: nonce,
            caption:   caption,
        }, function (res) {
            if (res.success) {
                previewEl.text(res.data.caption).show();
            }
        });
    }

    $('#fibb-ig-preview-caption-btn').on('click', function () {
        previewCaption($('#fibb-ig-caption'), $('#fibb-ig-caption-preview'));
    });

    /* ── AJOUTER À LA FILE (médiathèque) ─────────────────────── */
    $('#fibb-ig-add-btn').on('click', function () {
        var imageUrl = $('#fibb-ig-image-url').val().trim();
        var caption  = $('#fibb-ig-caption').val().trim();
        var $msg     = $('#fibb-ig-add-msg');

        if (!imageUrl) {
            showMsg($msg, 'Choisissez d\'abord une image.', 'error');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Ajout…');

        $.post(ajaxurl, {
            action:    'fibb_ig_queue_add',
            _ajax_nonce: nonce,
            image_url: imageUrl,
            caption:   caption,
        }, function (res) {
            if (res.success) {
                showMsg($msg, '✅ ' + res.data.message, 'success');
                $('#fibb-ig-image-url').val('');
                $('#fibb-ig-preview-wrap').hide();
                $('#fibb-ig-caption').val('');
                $('#fibb-ig-caption-preview').hide();
                appendQueueCard(res.data.id, imageUrl, caption || '(légende à résoudre lors de la publication)');
            } else {
                showMsg($msg, '❌ ' + (res.data && res.data.error ? res.data.error : 'Erreur'), 'error');
            }
        }).always(function () {
            $btn.prop('disabled', false).text('➕ Ajouter à la file');
        });
    });

    /* ── UPLOAD DIRECT ────────────────────────────────────────── */
    $('#fibb-ig-upload-btn').on('click', function () {
        var file    = document.getElementById('fibb-ig-upload-file').files[0];
        var caption = $('#fibb-ig-upload-caption').val().trim();
        var $msg    = $('#fibb-ig-upload-msg');

        if (!file) {
            showMsg($msg, 'Sélectionnez un fichier image.', 'error');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Upload…');

        // 1. Uploader via l'API WP media
        var formData = new FormData();
        formData.append('action', 'upload-attachment');
        formData.append('async-upload', file);
        formData.append('name', file.name);
        formData.append('_wpnonce', fibbIgData.nonce);

        $.ajax({
            url:         fibbIgData.upload_url,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
        }).done(function (res) {
            var url = res.data && res.data.url ? res.data.url : null;
            if (!url) {
                showMsg($msg, '❌ Upload échoué (réponse inattendue).', 'error');
                $btn.prop('disabled', false).text('⬆️ Uploader & ajouter à la file');
                return;
            }
            // 2. Ajouter à la file
            $.post(ajaxurl, {
                action:    'fibb_ig_queue_add',
                _ajax_nonce: nonce,
                image_url: url,
                caption:   caption,
            }, function (res2) {
                if (res2.success) {
                    showMsg($msg, '✅ Uploadé et ajouté à la file !', 'success');
                    $('#fibb-ig-upload-file').val('');
                    $('#fibb-ig-upload-preview-wrap').hide();
                    $('#fibb-ig-upload-caption').val('');
                    appendQueueCard(res2.data.id, url, caption || '(légende auto)');
                } else {
                    showMsg($msg, '❌ ' + (res2.data && res2.data.error ? res2.data.error : 'Erreur'), 'error');
                }
            }).always(function () {
                $btn.prop('disabled', false).text('⬆️ Uploader & ajouter à la file');
            });
        }).fail(function () {
            showMsg($msg, '❌ Échec de l\'upload.', 'error');
            $btn.prop('disabled', false).text('⬆️ Uploader & ajouter à la file');
        });
    });

    /* ── SUPPRIMER DE LA FILE ─────────────────────────────────── */
    $(document).on('click', '.fibb-ig-queue-remove', function () {
        var $card = $(this).closest('.fibb-ig-queue-card');
        var id    = $(this).data('id');

        if (!confirm('Retirer ce post de la file ?')) return;

        $.post(ajaxurl, {
            action:    'fibb_ig_queue_remove',
            _ajax_nonce: nonce,
            id:        id,
        }, function (res) {
            if (res.success) {
                $card.fadeOut(300, function () {
                    $(this).remove();
                    updateQueueCount();
                });
            } else {
                alert(res.data && res.data.error ? res.data.error : 'Erreur');
            }
        });
    });

    /* ── RÉESSAYER UN POST ÉCHOUÉ ─────────────────────────────── */
    $(document).on('click', '.fibb-ig-retry-btn', function () {
        var $btn    = $(this).prop('disabled', true).text('…');
        var id      = $(this).data('id');
        var image   = $(this).data('image');
        var caption = $(this).data('caption');

        // On re-crée un item ig_queued et on supprime le failed
        $.post(ajaxurl, {
            action:    'fibb_ig_queue_add',
            _ajax_nonce: nonce,
            image_url: image,
            caption:   caption,
        }, function (res) {
            if (res.success) {
                // Supprimer l'ancien failed
                $.post(ajaxurl, {
                    action:    'fibb_ig_queue_remove',
                    _ajax_nonce: nonce,
                    id:        id,
                });
                $btn.closest('tr').find('td:last').html('<span style="color:#27ae60;">✅ Remis en file</span>');
            } else {
                $btn.prop('disabled', false).text('🔄 Réessayer');
                alert(res.data && res.data.error ? res.data.error : 'Erreur');
            }
        });
    });

    /* ── BULK SCHEDULE ─────────────────────────────────────────── */

    var bulkImages    = [];
    var bulkMediaFrame;

    $('#fibb-bulk-media-btn').on('click', function () {
        if (!bulkMediaFrame) {
            bulkMediaFrame = wp.media({
                title:    'Choisir les images pour Instagram',
                button:   { text: 'Sélectionner' },
                multiple: true,
                library:  { type: 'image' },
            });
            bulkMediaFrame.on('select', function () {
                bulkImages = bulkMediaFrame.state().get('selection').map(function (att) {
                    var json  = att.toJSON();
                    var thumb = json.sizes && json.sizes.thumbnail ? json.sizes.thumbnail.url : json.url;
                    return { url: json.url, thumb: thumb };
                });
                renderBulkThumbs();
                renderBulkPreview();
                updateBulkCount();
            });
        }
        bulkMediaFrame.open();
    });

    function renderBulkThumbs() {
        var $wrap = $('#fibb-bulk-thumbs');
        $wrap.empty();
        $.each(bulkImages, function (i, img) {
            $wrap.append(
                '<div class="fibb-bulk-thumb-item">'
                + '<img src="' + escHtml(img.thumb) + '" alt="">'
                + '<button type="button" class="fibb-bulk-thumb-remove" data-index="' + i + '">✕</button>'
                + '</div>'
            );
        });
    }

    $(document).on('click', '.fibb-bulk-thumb-remove', function () {
        bulkImages.splice(parseInt($(this).data('index'), 10), 1);
        renderBulkThumbs();
        renderBulkPreview();
        updateBulkCount();
    });

    function renderBulkPreview() {
        var $grid    = $('#fibb-bulk-preview-grid');
        var start    = $('#fibb-bulk-start').val();
        var interval = parseInt($('#fibb-bulk-interval').val(), 10) || 60;

        if (!bulkImages.length || !start) {
            $grid.html('<p style="color:#aaa;font-size:12px;text-align:center;padding:24px 0;">Sélectionnez des images et une date pour voir l\'aperçu.</p>');
            return;
        }

        var startMs = new Date(start).getTime();
        var html    = '';
        $.each(bulkImages, function (i, img) {
            var d       = new Date(startMs + i * interval * 60000);
            var dateStr = pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear();
            var timeStr = pad2(d.getHours()) + ':' + pad2(d.getMinutes());
            html += '<div class="fibb-bulk-preview-card">'
                + '<img src="' + escHtml(img.thumb) + '" alt="">'
                + '<span class="fibb-bulk-preview-date">' + dateStr + '<br>' + timeStr + '</span>'
                + '</div>';
        });
        $grid.html(html);
    }

    function updateBulkCount() {
        var n = bulkImages.length;
        $('#fibb-bulk-count').text(n);
        $('#fibb-bulk-count-label').text(n + ' image' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : ''));
        $('#fibb-bulk-submit').prop('disabled', n === 0);
    }

    $('#fibb-bulk-start, #fibb-bulk-interval').on('change input', renderBulkPreview);

    $('#fibb-bulk-submit').on('click', function () {
        var start    = $('#fibb-bulk-start').val();
        var interval = parseInt($('#fibb-bulk-interval').val(), 10) || 60;
        var caption  = $('#fibb-bulk-caption').val().trim();
        var $msg     = $('#fibb-bulk-msg');

        if (!bulkImages.length) { showMsg($msg, 'Choisissez au moins une image.', 'error'); return; }
        if (!start)             { showMsg($msg, 'Indiquez une date de début.', 'error');    return; }

        var $btn = $(this).prop('disabled', true).text('Création en cours…');

        $.post(ajaxurl, {
            action:           'fibb_ig_bulk_schedule',
            _ajax_nonce:      nonce,
            images:           bulkImages.map(function (i) { return i.url; }),
            caption:          caption,
            start_datetime:   start,
            interval_minutes: interval,
        }, function (res) {
            if (res.success) {
                var msg = '✅ ' + res.data.created + ' post(s) programmé(s).';
                if (res.data.errors && res.data.errors.length) {
                    msg += ' ⚠️ ' + res.data.errors.join(' | ');
                }
                showMsg($msg, msg, 'success');
                bulkImages = [];
                renderBulkThumbs();
                renderBulkPreview();
                updateBulkCount();
                setTimeout(function () { location.reload(); }, 1800);
            } else {
                showMsg($msg, '❌ ' + (res.data && res.data.error ? res.data.error : 'Erreur'), 'error');
                updateBulkCount();
            }
        }).always(function () { $btn.prop('disabled', false).text('📅 Programmer ' + bulkImages.length + ' posts'); });
    });

    /* ── SIDE PANEL ─────────────────────────────────────────────── */

    function openSidePanel(postId) {
        $.post(ajaxurl, {
            action:      'fibb_ig_get_post',
            _ajax_nonce: nonce,
            post_id:     postId,
        }, function (res) {
            if (!res.success) { alert(res.data && res.data.error ? res.data.error : 'Erreur'); return; }
            var d = res.data;
            if (d.image_url) { $('#fibb-panel-img').attr('src', d.image_url).show(); }
            else             { $('#fibb-panel-img').hide(); }
            $('#fibb-panel-caption').text(d.content);
            $('#fibb-panel-date').text('📅 ' + d.scheduled_at);
            $('#fibb-panel-status').text(d.status).attr('class', 'fibb-side-panel-status fibb-status-' + d.status);
            $('#fibb-panel-edit-btn').attr('href', d.edit_url);
            $('#fibb-panel-delete-btn').data('id', postId).data('dnonce', d.delete_nonce).toggle(!!d.can_delete);
            $('#fibb-panel-publish-btn').data('id', postId).toggle(d.status === 'ig_queued' || d.status === 'scheduled');
            $('#fibb-panel-msg').hide();
            $('#fibb-ig-panel, #fibb-ig-panel-overlay').addClass('active');
        });
    }

    function closeSidePanel() {
        $('#fibb-ig-panel, #fibb-ig-panel-overlay').removeClass('active');
    }

    $('#fibb-ig-panel-close, #fibb-ig-panel-overlay').on('click', closeSidePanel);

    // Ouvrir depuis une carte queue
    $(document).on('click', '.fibb-ig-panel-trigger', function (e) {
        if ($(e.target).closest('button, a').length) return;
        openSidePanel($(this).data('id'));
    });

    // Ouvrir depuis le calendrier (miniature IG)
    window.fibbIgOpenPanel = function (el) {
        openSidePanel(parseInt($(el).data('id'), 10));
    };

    // Publier depuis le panneau
    $('#fibb-panel-publish-btn').on('click', function () {
        publishNow($(this).data('id'), $('#fibb-panel-msg'));
    });

    // Supprimer depuis le panneau
    $('#fibb-panel-delete-btn').on('click', function () {
        var id = $(this).data('id');
        if (!confirm('Supprimer ce post ?')) return;
        $.post(ajaxurl, {
            action:      'fibb_ig_queue_remove',
            _ajax_nonce: nonce,
            id:          id,
        }, function (res) {
            if (res.success) {
                closeSidePanel();
                $('[data-id="' + id + '"].fibb-ig-queue-card').fadeOut(300, function () { $(this).remove(); updateQueueCount(); });
                $('[data-id="' + id + '"].fibb-cal-ig-thumb').fadeOut(300, function () { $(this).remove(); });
            } else {
                showMsg($('#fibb-panel-msg'), '❌ ' + (res.data && res.data.error ? res.data.error : 'Erreur'), 'error');
            }
        });
    });

    // Publier depuis les cartes queue (bouton direct)
    $(document).on('click', '.fibb-ig-publish-now-btn', function (e) {
        e.stopPropagation();
        var $btn = $(this).prop('disabled', true).text('…');
        publishNow($(this).data('id'), null, $btn);
    });

    function publishNow(postId, $msgEl, $btn) {
        $.post(ajaxurl, {
            action:      'fibb_ig_publish_now',
            _ajax_nonce: nonce,
            post_id:     postId,
        }, function (res) {
            if (res.success) {
                if ($msgEl) showMsg($msgEl, '✅ Publié avec succès !', 'success');
                $('[data-id="' + postId + '"].fibb-ig-queue-card').fadeOut(400, function () { $(this).remove(); updateQueueCount(); });
                $('[data-id="' + postId + '"].fibb-cal-ig-thumb').css('opacity', '.4');
                if ($msgEl) setTimeout(closeSidePanel, 1500);
            } else {
                var err = res.data && res.data.error ? res.data.error : 'Erreur';
                if ($msgEl) showMsg($msgEl, '❌ ' + err, 'error');
                else alert('❌ ' + err);
            }
        }).always(function () {
            if ($btn) $btn.prop('disabled', false).text('▶ Publier');
        });
    }

    /* ── HELPERS ──────────────────────────────────────────────── */
    function showMsg($el, msg, type) {
        $el.html(msg)
           .css('color', type === 'error' ? '#c0392b' : '#27ae60')
           .show();
    }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function appendQueueCard(id, imageUrl, caption) {
        var $list = $('#fibb-ig-queue-list');
        if (!$list.length) { location.reload(); return; }
        var now = new Date();
        var dateStr = pad2(now.getDate()) + '/' + pad2(now.getMonth() + 1) + '/' + now.getFullYear()
            + ' ' + pad2(now.getHours()) + ':' + pad2(now.getMinutes());
        var html = '<div class="fibb-ig-queue-card fibb-ig-panel-trigger" data-id="' + id + '">'
            + '<img src="' + escHtml(imageUrl) + '" alt="" class="fibb-ig-queue-thumb">'
            + '<div class="fibb-ig-queue-info">'
            + '<span class="fibb-ig-queue-caption">' + escHtml(caption.substring(0, 80)) + '</span>'
            + '<span class="fibb-ig-queue-date">📅 ' + dateStr + '</span>'
            + '</div>'
            + '<div class="fibb-ig-queue-actions">'
            + '<button type="button" class="button button-small fibb-ig-publish-now-btn" data-id="' + id + '">▶ Publier</button>'
            + '<button type="button" class="fibb-ig-queue-remove" data-id="' + id + '" title="Retirer de la file">✕</button>'
            + '</div>'
            + '</div>';
        $list.append(html);
        updateQueueCount();
    }

    function updateQueueCount() {
        var n = $('#fibb-ig-queue-list .fibb-ig-queue-card').length;
        $('h3:contains("File d\'attente")').text('📋 File d\'attente (' + n + ' post' + (n > 1 ? 's' : '') + ')');
    }

}(jQuery));
