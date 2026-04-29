/* FIBB Communication Suite — Admin JS */
(function () {
    'use strict';

    // ── Character counters ──────────────────────────────────────
    var limits = { facebook: 63206, instagram: 2200, linkedin: 3000 };

    function updateCounter(textarea) {
        var counter = document.getElementById('fibb-char-count');
        if (!counter) return;
        var len = textarea.value.length;
        var limit = 63206; // default to Facebook max (most permissive shown)

        // Check which platforms are selected.
        var checked = document.querySelectorAll('.fibb-platform-cb:checked');
        if (checked.length) {
            var mins = Array.from(checked).map(function (cb) {
                return limits[cb.value] || 63206;
            });
            limit = Math.min.apply(null, mins);
        }

        counter.textContent = len + ' / ' + limit;
        counter.className = 'fibb-char-counter' + (len > limit ? ' warn' : '');
    }

    var content = document.getElementById('fibb-post-content');
    if (content) {
        content.addEventListener('input', function () { updateCounter(this); });
        document.querySelectorAll('.fibb-platform-cb').forEach(function (cb) {
            cb.addEventListener('change', function () { updateCounter(content); });
        });
    }

    // ── Media library picker ────────────────────────────────────
    var mediaBtn = document.getElementById('fibb-media-btn');
    var imageInput = document.getElementById('fibb-image-url');
    var imagePreview = document.getElementById('fibb-image-preview');

    if (mediaBtn && window.wp && window.wp.media) {
        var frame;
        mediaBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!frame) {
                frame = wp.media({
                    title: 'Choisir une image',
                    button: { text: 'Utiliser cette image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    if (imageInput) imageInput.value = att.url;
                    if (imagePreview) {
                        imagePreview.src = att.url;
                        imagePreview.style.display = 'block';
                    }
                });
            }
            frame.open();
        });
    }

    // ── Test connexion (AJAX) ───────────────────────────────────
    document.querySelectorAll('.fibb-test-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var platform = btn.dataset.platform;
            var result = document.getElementById('fibb-test-result-' + platform);
            if (!result) return;

            result.textContent = 'Test en cours…';
            result.className = 'fibb-test-result';

            var data = new FormData();
            data.append('action', 'fibb_comm_test_connection');
            data.append('platform', platform);
            data.append('_ajax_nonce', fibbCommAjax.nonce);

            fetch(fibbCommAjax.ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (resp.success) {
                        result.textContent = '✓ ' + resp.data.message;
                        result.className = 'fibb-test-result fibb-test-ok';
                    } else {
                        result.textContent = '✗ ' + (resp.data && resp.data.error ? resp.data.error : 'Erreur inconnue');
                        result.className = 'fibb-test-result fibb-test-err';
                    }
                })
                .catch(function () {
                    result.textContent = '✗ Erreur réseau';
                    result.className = 'fibb-test-result fibb-test-err';
                });
        });
    });

    // ── Load template into form ─────────────────────────────────
    var templateSelect = document.getElementById('fibb-load-template');
    if (templateSelect) {
        templateSelect.addEventListener('change', function () {
            var slug = this.value;
            if (!slug) return;

            var data = new FormData();
            data.append('action', 'fibb_comm_get_template');
            data.append('slug', slug);
            data.append('_ajax_nonce', fibbCommAjax.nonce);

            fetch(fibbCommAjax.ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp.success) return;
                    var tpl = resp.data;
                    if (content && tpl.content) content.value = tpl.content;
                    if (imageInput && tpl.image_url) imageInput.value = tpl.image_url;

                    // Pre-select platform checkbox.
                    if (tpl.platform) {
                        document.querySelectorAll('.fibb-platform-cb').forEach(function (cb) {
                            cb.checked = (cb.value === tpl.platform);
                        });
                    }
                    // Set phase.
                    var phaseSelect = document.getElementById('fibb-phase');
                    if (phaseSelect && tpl.phase) phaseSelect.value = tpl.phase;

                    if (content) updateCounter(content);
                    templateSelect.value = '';
                });
        });
    }


    // ── Kanban drag & drop ─────────────────────────────────────
    var kanban = document.getElementById('fibb-kanban');
    if (kanban) {
        var draggedCard = null;
        var draggedId   = null;

        kanban.addEventListener('dragstart', function (e) {
            if (e.target.closest('.fibb-kanban-delete-btn')) {
                e.preventDefault();
                return;
            }
            var card = e.target.closest('.fibb-kanban-card[draggable="true"]');
            if (!card) return;
            draggedCard = card;
            draggedId   = card.dataset.id;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedId);
            setTimeout(function () { card.classList.add('is-dragging'); }, 0);
        });

        kanban.addEventListener('dragend', function () {
            if (draggedCard) draggedCard.classList.remove('is-dragging');
            kanban.querySelectorAll('.fibb-kanban-col').forEach(function (c) {
                c.classList.remove('drag-over');
            });
            draggedCard = null;
            draggedId   = null;
        });

        kanban.addEventListener('dragover', function (e) {
            var col = e.target.closest('.fibb-kanban-col:not(.fibb-kanban-readonly)');
            if (!col) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            kanban.querySelectorAll('.fibb-kanban-col.drag-over').forEach(function (c) {
                if (c !== col) c.classList.remove('drag-over');
            });
            col.classList.add('drag-over');
        });

        kanban.addEventListener('dragleave', function (e) {
            var col = e.target.closest('.fibb-kanban-col');
            if (col && !col.contains(e.relatedTarget)) {
                col.classList.remove('drag-over');
            }
        });

        kanban.addEventListener('drop', function (e) {
            e.preventDefault();
            var col = e.target.closest('.fibb-kanban-col:not(.fibb-kanban-readonly)');
            if (!col || !draggedCard || !draggedId) return;

            col.classList.remove('drag-over');
            var targetColumn = col.dataset.column;

            // Optimistic UI : déplacer la carte dans la nouvelle colonne
            var dropZone = col.querySelector('.fibb-kanban-drop-zone');
            var colBody  = col.querySelector('.fibb-kanban-col-body');
            if (colBody) {
                colBody.insertBefore(draggedCard, dropZone);
                fibbUpdateColCounts();
            }

            // AJAX save
            var formData = new FormData();
            formData.append('action', 'fibb_comm_move_post');
            formData.append('post_id', draggedId);
            formData.append('column', targetColumn);
            formData.append('view', kanban.dataset.view);
            formData.append('_ajax_nonce', kanban.dataset.nonce);

            fetch(kanban.dataset.ajaxurl, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (resp.success) {
                        fibbShowToast('Post déplacé ✓', 'ok');
                    } else {
                        fibbShowToast('Erreur : ' + (resp.data && resp.data.error ? resp.data.error : 'inconnue'), 'err');
                    }
                })
                .catch(function () {
                    fibbShowToast('Erreur réseau', 'err');
                });
        });

        function fibbUpdateColCounts() {
            kanban.querySelectorAll('.fibb-kanban-col').forEach(function (col) {
                var n   = col.querySelectorAll('.fibb-kanban-card').length;
                var cnt = col.querySelector('.fibb-kanban-col-count');
                if (cnt) cnt.textContent = n;
            });
        }

        // Suppression depuis le Kanban
        kanban.addEventListener('click', function (e) {
            var btn = e.target.closest('.fibb-kanban-delete-btn');
            if (!btn) return;
            e.stopPropagation();

            var isPublished = btn.dataset.status === 'published';
            var msg = isPublished
                ? 'Ce post a déjà été publié sur la plateforme.\nSupprimer l\'entrée du plan quand même ?'
                : 'Supprimer ce post définitivement ?';
            if (!confirm(msg)) return;

            var postId = btn.dataset.id;
            var nonce  = btn.dataset.nonce;
            var card   = btn.closest('.fibb-kanban-card');

            // Désactiver le bouton pendant la requête
            btn.disabled = true;
            btn.textContent = '…';

            var formData = new FormData();
            formData.append('action', 'fibb_comm_delete_post_ajax');
            formData.append('post_id', postId);
            formData.append('nonce', nonce);

            fetch(kanban.dataset.ajaxurl, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (resp.success) {
                        // Animer la disparition de la carte
                        card.style.transition = 'opacity 0.25s, transform 0.25s';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(0.9)';
                        setTimeout(function () {
                            card.remove();
                            fibbUpdateColCounts();
                        }, 260);
                        fibbShowToast('Post supprimé ✓', 'ok');
                    } else {
                        btn.disabled    = false;
                        btn.textContent = '🗑';
                        fibbShowToast('Erreur : ' + (resp.data && resp.data.error ? resp.data.error : 'inconnue'), 'err');
                    }
                })
                .catch(function () {
                    btn.disabled    = false;
                    btn.textContent = '🗑';
                    fibbShowToast('Erreur réseau', 'err');
                });
        });

        function fibbShowToast(msg, type) {
            var toast = document.getElementById('fibb-kanban-toast');
            if (!toast) return;
            toast.textContent = msg;
            toast.className = 'fibb-kanban-toast fibb-toast-' + type;
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2500);
        }
    }

    // ── Filtres plateforme ──────────────────────────────────────
    var filterCbs = document.querySelectorAll('.fibb-filter-cb');
    if (filterCbs.length) {
        function applyFilters() {
            var active = Array.from(filterCbs)
                .filter(function (cb) { return cb.checked; })
                .map(function (cb) { return cb.value; });

            document.querySelectorAll('.fibb-filterable').forEach(function (el) {
                el.style.display = active.indexOf(el.dataset.platform) !== -1 ? '' : 'none';
            });
        }
        filterCbs.forEach(function (cb) {
            cb.addEventListener('change', applyFilters);
        });
    }

    // ── Popup de prévisualisation ───────────────────────────────
    var statusLabels = {
        scheduled: 'Planifié', published: 'Publié', failed: 'Échoué', draft: 'Brouillon'
    };
    var statusColors = {
        scheduled: '#f39c12', published: '#27ae60', failed: '#e74c3c', draft: '#95a5a6'
    };
    var platformColors = {
        facebook: '#1877f2', instagram: '#e1306c', linkedin: '#0a66c2'
    };
    var platformLabels = {
        facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn'
    };

    window.fibbOpenPopup = function (el) {
        var overlay = document.getElementById('fibb-popup-overlay');
        var popup   = document.getElementById('fibb-popup');
        if (!overlay || !popup) return;

        var platform = el.dataset.platform;
        var status   = el.dataset.status;
        var content  = el.dataset.content;
        var date     = el.dataset.date;
        var image    = el.dataset.image;
        var editUrl  = el.dataset.editUrl;
        var canDel   = el.dataset.canDelete === '1';
        var canRetry = el.dataset.canRetry === '1';
        var postId   = el.dataset.id;

        // Badges
        var badgesEl = document.getElementById('fibb-popup-badges');
        var pColor = platformColors[platform] || '#555';
        var sColor = statusColors[status] || '#888';
        badgesEl.innerHTML =
            '<span style="background:' + pColor + ';color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">' +
            (platformLabels[platform] || platform) + '</span>' +
            '<span style="background:' + sColor + ';color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">' +
            (statusLabels[status] || status) + '</span>';

        // Date
        document.getElementById('fibb-popup-date').textContent = '🗓 ' + date;

        // Contenu
        document.getElementById('fibb-popup-content').textContent = content;

        // Image
        var imgEl = document.getElementById('fibb-popup-image');
        if (image) {
            imgEl.src = image;
            imgEl.style.display = 'block';
        } else {
            imgEl.src = '';
            imgEl.style.display = 'none';
        }

        // Actions
        var actionsEl = document.getElementById('fibb-popup-actions');
        var html = '';
        if (editUrl) {
            html += '<a href="' + editUrl + '" class="fibb-btn fibb-btn-outline" style="font-size:12px;padding:6px 14px;">✏️ Éditer</a>';
        }
        if (canRetry) {
            html += '<button onclick="fibbRetryFromPopup()" class="fibb-btn fibb-btn-secondary" style="background:#f39c12;font-size:12px;padding:6px 14px;">🔄 Republier</button>';
        }
        if (canDel) {
            html += '<button onclick="fibbDeleteFromPopup()" class="fibb-btn fibb-btn-secondary" style="background:#e74c3c;font-size:12px;padding:6px 14px;">🗑 Supprimer</button>';
        }
        actionsEl.innerHTML = html;

        // Préparer les formulaires cachés
        var delId    = document.getElementById('fibb-popup-delete-id');
        var delNonce = document.getElementById('fibb-popup-delete-nonce');
        var retId    = document.getElementById('fibb-popup-retry-id');
        var retNonce = document.getElementById('fibb-popup-retry-nonce');
        if (delId) delId.value = postId;
        if (delNonce) delNonce.value = el.dataset.deleteNonce || '';
        if (retId) retId.value = postId;
        if (retNonce) retNonce.value = el.dataset.retryNonce || '';

        overlay.style.display = 'block';
        popup.style.display   = 'block';
        popup.focus();
    };

    window.fibbClosePopup = function () {
        var overlay = document.getElementById('fibb-popup-overlay');
        var popup   = document.getElementById('fibb-popup');
        if (overlay) overlay.style.display = 'none';
        if (popup)   popup.style.display   = 'none';
    };

    window.fibbDeleteFromPopup = function () {
        if (!confirm('Supprimer ce post ?')) return;
        document.getElementById('fibb-popup-delete-form').submit();
    };

    window.fibbRetryFromPopup = function () {
        if (!confirm('Republier ce post maintenant ?')) return;
        document.getElementById('fibb-popup-retry-form').submit();
    };

    // Fermer popup sur touche Échap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.fibbClosePopup();
    });

    // ── Republier tous les posts échoués ───────────────────────
    var retryAllBtn = document.getElementById('fibb-retry-all-btn');
    if (retryAllBtn) {
        retryAllBtn.addEventListener('click', function () {
            if (!confirm('Republier tous les posts échoués maintenant ?')) return;

            retryAllBtn.disabled    = true;
            retryAllBtn.textContent = '⏳ Republication en cours…';

            var resultDiv = document.getElementById('fibb-retry-all-result');

            var data = new FormData();
            data.append('action', 'fibb_comm_retry_all_failed');
            data.append('_ajax_nonce', fibbCommAjax.nonce);

            fetch(fibbCommAjax.ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    retryAllBtn.disabled    = false;
                    retryAllBtn.textContent = '🔄 Republier tous les échecs';
                    if (resp.success) {
                        resultDiv.style.display    = 'block';
                        resultDiv.style.background = resp.data.errors === 0 ? '#d4edda' : '#fff3cd';
                        resultDiv.style.color      = resp.data.errors === 0 ? '#155724' : '#856404';
                        resultDiv.textContent       = '✓ ' + resp.data.message + ' Rechargez la page pour voir le résultat.';
                        if (resp.data.ok > 0) {
                            retryAllBtn.closest('.notice').style.display = 'none';
                        }
                    } else {
                        resultDiv.style.display    = 'block';
                        resultDiv.style.background = '#f8d7da';
                        resultDiv.style.color      = '#721c24';
                        resultDiv.textContent       = '✗ ' + (resp.data && resp.data.error ? resp.data.error : 'Erreur inconnue');
                    }
                })
                .catch(function () {
                    retryAllBtn.disabled    = false;
                    retryAllBtn.textContent = '🔄 Republier tous les échecs';
                    resultDiv.style.display    = 'block';
                    resultDiv.style.background = '#f8d7da';
                    resultDiv.style.color      = '#721c24';
                    resultDiv.textContent       = '✗ Erreur réseau.';
                });
        });
    }

    // ── Génération token Meta permanent ────────────────────────
    var refreshBtn = document.getElementById('fibb-refresh-meta-token-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            var input  = document.getElementById('fibb-user-token-input');
            var result = document.getElementById('fibb-refresh-meta-result');
            var token  = input ? input.value.trim() : '';

            if (!token) {
                result.textContent = '⚠ Collez votre User Access Token dans le champ ci-dessus.';
                result.style.color = '#e65800';
                return;
            }

            refreshBtn.disabled    = true;
            refreshBtn.textContent = 'Traitement…';
            result.textContent     = 'Échange du token en cours avec Facebook…';
            result.style.color     = '#444';

            var data = new FormData();
            data.append('action', 'fibb_comm_refresh_meta_token');
            data.append('user_token', token);
            data.append('_ajax_nonce', fibbCommAjax.nonce);

            fetch(fibbCommAjax.ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    refreshBtn.disabled    = false;
                    refreshBtn.textContent = 'Générer token permanent';
                    if (resp.success) {
                        result.style.color = '#0a7227';
                        result.textContent = '✓ ' + resp.data.message + ' Rechargez la page pour voir le statut mis à jour.';
                        if (input) input.value = '';
                    } else {
                        result.style.color = '#b32d2e';
                        result.textContent = '✗ ' + (resp.data && resp.data.error ? resp.data.error : 'Erreur inconnue');
                    }
                })
                .catch(function () {
                    refreshBtn.disabled    = false;
                    refreshBtn.textContent = 'Générer token permanent';
                    result.style.color     = '#b32d2e';
                    result.textContent     = '✗ Erreur réseau. Vérifiez votre connexion.';
                });
        });
    }

})();
