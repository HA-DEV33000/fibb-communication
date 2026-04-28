/* global fibbWizardPhpData, jQuery */
(function ($) {
    'use strict';

    // ── État global ────────────────────────────────────────────────
    var state = fibbWizardPhpData.savedState || {};
    var currentStep = 1;

    var PLATFORM_COLORS = {
        facebook:  '#1877f2',
        instagram: '#e1306c',
        linkedin:  '#0a66c2',
    };

    var PHASE_LABELS = fibbWizardPhpData.phases || {};

    // ── Initialisation ─────────────────────────────────────────────
    $(document).ready(function () {
        if ( ! $('#fibb-panel-1').length ) return;

        // Si session en cours, laisser la bannière gérer la reprise
        // Sinon démarrer à l'étape 1
        fibbWizardGoTo(1);
        fibbWizardUpdateSeoBadges();
        fibbWizardUpdateDateRows();

        // Recalcule le score SEO en temps réel quand keywords changent
        $('#wiz-keywords').on('input', debounce(fibbWizardUpdateSeoBadges, 500));

        // Mise à jour du tag Auto/Modifiée sur les overrides de dates
        $(document).on('change', '.fibb-wiz-date-override', function () {
            var $row = $(this).closest('tr');
            var $status = $row.find('.fibb-wiz-date-status');
            if ($(this).val()) {
                $status.html('<span class="fibb-wiz-date-edit-tag">Modifiée</span>');
            } else {
                $status.html('<span class="fibb-wiz-date-auto-tag">Auto</span>');
            }
            fibbWizardUpdateTimeline();
        });

        // Toggle sélection de carte au clic sur la carte entière (hors boutons)
        $(document).on('click', '.fibb-wiz-tpl-card', function (e) {
            if ($(e.target).is('button, input, label')) return;
            var $cb = $(this).find('.fibb-wiz-tpl-check');
            $cb.prop('checked', !$cb.prop('checked')).trigger('change');
        });

        $(document).on('change', '.fibb-wiz-tpl-check', function () {
            var $card = $(this).closest('.fibb-wiz-tpl-card');
            $card.toggleClass('is-selected', $(this).prop('checked'));
        });
    });

    // ── Navigation ─────────────────────────────────────────────────
    window.fibbWizardGoTo = function (step) {
        currentStep = step;
        // Cacher tous les panneaux
        $('.fibb-wizard-panel').hide();
        $('#fibb-panel-' + step).show();

        // Mettre à jour la barre de progression
        $('#fibb-wizard-nav .fibb-wizard-step').each(function () {
            var n = parseInt($(this).data('step'));
            $(this).removeClass('active done');
            if (n === step)  $(this).addClass('active');
            if (n < step)    $(this).addClass('done');
        });

        // Scroll en haut du wizard
        $('html, body').animate({ scrollTop: $('.fibb-wizard-wrap').offset().top - 32 }, 200);

        // Actions spéciales à l'arrivée sur certaines étapes
        if (step === 3) fibbWizardUpdateDateRows();
        if (step === 4) fibbWizardRenderReview();
    };

    window.fibbWizardResume = function (step) {
        state = fibbWizardPhpData.savedState || {};
        fibbWizardRestoreInputs();
        fibbWizardGoTo(step);
    };

    window.fibbWizardNext = function (step) {
        if (!fibbWizardValidateStep(step)) return;
        var btn = $('#wiz-btn-' + step + '-next, .fibb-wizard-panel[data-panel="' + step + '"] .button-primary');
        btn.prop('disabled', true).text('Enregistrement…');
        fibbWizardSaveStep(step, function () {
            btn.prop('disabled', false);
            // Restore button text
            if (step === 1) btn.text('Suivant →');
            else btn.text('Suivant →');
            fibbWizardGoTo(step + 1);
        }, function () {
            btn.prop('disabled', false).text('Suivant →');
        });
    };

    // ── Validation ─────────────────────────────────────────────────
    function fibbWizardValidateStep(step) {
        var $err = $('#wiz-error-' + step);
        $err.hide().text('');

        if (step === 1) {
            if (!$('#wiz-edition').val() || parseInt($('#wiz-edition').val()) < 1) {
                $err.text('Veuillez saisir un numéro d\'édition valide.').show();
                return false;
            }
            if (!$('#wiz-date').val()) {
                $err.text('Veuillez saisir la date du festival.').show();
                return false;
            }
            var channels = fibbWizardGetChannels();
            if (channels.length === 0) {
                $err.text('Veuillez sélectionner au moins un canal de diffusion.').show();
                return false;
            }
        }

        if (step === 2) {
            var selected = $('.fibb-wiz-tpl-check:checked').length;
            if (selected === 0) {
                var $err2 = $('#wiz-error-2');
                $err2.text('Veuillez sélectionner au moins un template.').show();
                return false;
            }
        }

        return true;
    }

    // ── AJAX : Sauvegarder une étape ───────────────────────────────
    function fibbWizardSaveStep(step, onSuccess, onError) {
        var data = {};

        if (step === 1) {
            data = {
                edition:               $('#wiz-edition').val(),
                date:                  $('#wiz-date').val(),
                channels:              fibbWizardGetChannels(),
                keywords:              $('#wiz-keywords').val(),
                seo_hashtags_facebook:  $('#wiz-ht-facebook').val(),
                seo_hashtags_instagram: $('#wiz-ht-instagram').val(),
                seo_hashtags_linkedin:  $('#wiz-ht-linkedin').val(),
            };
        } else if (step === 2) {
            var sel = {};
            $('.fibb-wiz-tpl-card').each(function () {
                var slug = $(this).data('slug');
                sel[slug] = $(this).find('.fibb-wiz-tpl-check').prop('checked') ? 1 : 0;
            });
            data = { selection: sel };
        } else if (step === 3) {
            var dates = {};
            $('.fibb-wiz-date-override').each(function () {
                var slug = $(this).data('slug');
                dates[slug] = $(this).val();
            });
            data = { dates: dates };
        }

        $.post(fibbWizardPhpData.ajaxurl, {
            action:      'fibb_wizard_save_step',
            _ajax_nonce: fibbWizardPhpData.nonce,
            step:        step,
            data:        JSON.stringify(data),
        }, function (resp) {
            if (resp.success) {
                state = resp.data.state || state;
                if (onSuccess) onSuccess(resp.data);
            } else {
                fibbWizardToast('Erreur : ' + (resp.data || 'inconnue'), 'error');
                if (onError) onError();
            }
        }).fail(function () {
            fibbWizardToast('Erreur réseau', 'error');
            if (onError) onError();
        });
    }

    // ── Réinitialisation ───────────────────────────────────────────
    window.fibbWizardReset = function () {
        $.post(fibbWizardPhpData.ajaxurl, {
            action:      'fibb_wizard_save_step',
            _ajax_nonce: fibbWizardPhpData.nonce,
            step:        0,
            data:        '{}',
        });
        state = {};
        // Réinitialiser les inputs
        $('#wiz-edition').val(fibbWizardPhpData.edition);
        $('#wiz-date').val(fibbWizardPhpData.festivalDate);
        $('input[name="channels[]"]').prop('checked', true);
        $('#wiz-keywords').val('');
        $('.fibb-wiz-tpl-check').prop('checked', true);
        $('.fibb-wiz-tpl-card').addClass('is-selected');
        $('.fibb-wiz-date-override').val('');
        $('.fibb-wiz-date-status').html('<span class="fibb-wiz-date-auto-tag">Auto</span>');
        $('#fibb-wizard-resume-banner').hide();
        fibbWizardGoTo(1);
    };

    function fibbWizardRestoreInputs() {
        if (!state || !state.config) return;
        var cfg = state.config;

        if (cfg.edition)  $('#wiz-edition').val(cfg.edition);
        if (cfg.date)     $('#wiz-date').val(cfg.date);
        if (cfg.channels) {
            $('input[name="channels[]"]').prop('checked', false);
            cfg.channels.forEach(function (ch) {
                $('input[name="channels[]"][value="' + ch + '"]').prop('checked', true);
            });
        }
        if (cfg.keywords) $('#wiz-keywords').val(cfg.keywords.join(', '));
        if (cfg.seo_hashtags) {
            if (cfg.seo_hashtags.facebook)  $('#wiz-ht-facebook').val(cfg.seo_hashtags.facebook);
            if (cfg.seo_hashtags.instagram) $('#wiz-ht-instagram').val(cfg.seo_hashtags.instagram);
            if (cfg.seo_hashtags.linkedin)  $('#wiz-ht-linkedin').val(cfg.seo_hashtags.linkedin);
        }

        if (state.selection) {
            Object.keys(state.selection).forEach(function (slug) {
                var $card = $('.fibb-wiz-tpl-card[data-slug="' + slug + '"]');
                var checked = !!state.selection[slug];
                $card.find('.fibb-wiz-tpl-check').prop('checked', checked);
                $card.toggleClass('is-selected', checked);
            });
        }
    }

    // ── Filtrage des templates par canal ───────────────────────────
    function fibbWizardFilterByChannels() {
        var channels = fibbWizardGetChannels();
        $('.fibb-wiz-tpl-card').each(function () {
            var platform = $(this).data('platform');
            if (channels.length === 0 || channels.indexOf(platform) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
                $(this).find('.fibb-wiz-tpl-check').prop('checked', false);
                $(this).removeClass('is-selected');
            }
        });
    }

    $('input[name="channels[]"]').on('change', fibbWizardFilterByChannels);

    // ── Sélection globale par phase ────────────────────────────────
    window.fibbWizardSelectAllPhase = function (phase, checked) {
        var cards = $('.fibb-wiz-tpl-card[data-phase="' + phase + '"]:visible');
        cards.each(function () {
            $(this).find('.fibb-wiz-tpl-check').prop('checked', checked);
            $(this).toggleClass('is-selected', checked);
        });
    };

    // ── Badges SEO en temps réel (étape 2) ─────────────────────────
    function fibbWizardUpdateSeoBadges() {
        var keywords = fibbWizardGetKeywords();
        var seoHt = {
            facebook:  $('#wiz-ht-facebook').val(),
            instagram: $('#wiz-ht-instagram').val(),
            linkedin:  $('#wiz-ht-linkedin').val(),
        };
        var festDate = $('#wiz-date').val() || fibbWizardPhpData.festivalDate;
        var edition  = $('#wiz-edition').val() || fibbWizardPhpData.edition;

        $('.fibb-wiz-tpl-card').each(function () {
            var $card    = $(this);
            var slug     = $card.data('slug');
            var platform = $card.data('platform');
            var offset   = parseInt($card.data('offset')) || 0;
            var rawContent = $card.data('content') || '';

            // Résolution basique des tokens côté JS
            var hashtags  = seoHt[platform] || '';
            var content   = rawContent
                .replace(/\{\{edition\}\}/g, edition)
                .replace(/\{\{festival_date\}\}/g, festDate)
                .replace(/\{\{hashtags\}\}/g, hashtags);

            var score = fibbWizardCalcSeoScore(content, platform, keywords, hashtags);
            var color = score >= 70 ? '#27ae60' : (score >= 40 ? '#f39c12' : '#e74c3c');

            var $badge = $card.find('.fibb-wiz-seo-badge[data-slug="' + slug + '"]');
            $badge.html('<span style="background:' + color + ';color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;font-weight:700;">' + score + '</span>');
        });
    }

    // Score SEO simplifié côté JS (miroir de FIBB_Comm_SEO::score en PHP)
    function fibbWizardCalcSeoScore(content, platform, keywords, hashtags) {
        var score = 0;
        var combined = content + ' ' + hashtags;

        // +25 — mot-clé présent
        for (var i = 0; i < keywords.length; i++) {
            if (keywords[i] && content.toLowerCase().indexOf(keywords[i].toLowerCase()) !== -1) {
                score += 25;
                break;
            }
        }

        // +25 — longueur optimale
        var len = content.length;
        var inRange = false;
        if (platform === 'instagram') inRange = len >= 100 && len <= 300;
        else if (platform === 'linkedin') inRange = len >= 300 && len <= 700;
        else inRange = len >= 100 && len <= 500;
        if (inRange) score += 25;

        // +20 — au moins 2 hashtags
        var tagMatches = combined.match(/#\w+/g) || [];
        if (tagMatches.length >= 2) score += 20;

        // +15 — emoji
        if (/[\u{1F000}-\u{1FFFF}]/u.test(content)) score += 15;

        // +15 — pas de répétition > 3×
        var overRepeat = false;
        for (var j = 0; j < keywords.length; j++) {
            if (!keywords[j]) continue;
            var re = new RegExp(keywords[j].replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            var matches = content.match(re) || [];
            if (matches.length > 3) { overRepeat = true; break; }
        }
        if (!overRepeat) score += 15;

        return Math.min(100, score);
    }

    // ── Modal aperçu template ──────────────────────────────────────
    window.fibbWizardOpenPreview = function (slug) {
        $('#fibb-wizard-overlay').show();
        $('#fibb-wizard-modal').show();
        $('#fibb-wiz-modal-title').text('Aperçu : ' + slug);
        $('#fibb-wiz-modal-body').html('<div class="fibb-wiz-spinner">Chargement…</div>');

        $.post(fibbWizardPhpData.ajaxurl, {
            action:      'fibb_wizard_preview_template',
            _ajax_nonce: fibbWizardPhpData.nonce,
            slug:        slug,
        }, function (resp) {
            if (!resp.success) {
                $('#fibb-wiz-modal-body').html('<p>Erreur : ' + (resp.data || 'inconnue') + '</p>');
                return;
            }
            var d = resp.data;
            var icons = fibbWizardPhpData.platformIcons || {};
            var html = '<div>';
            html += '<div style="margin-bottom:12px">';
            html += '<span class="fibb-platform-badge fibb-platform-' + d.platform + '">' + (icons[d.platform] || '') + ' ' + d.platform + '</span> ';
            html += '<span class="fibb-phase-badge fibb-phase-' + d.phase + '">' + (PHASE_LABELS[d.phase] || d.phase) + '</span>';
            html += '</div>';

            html += '<div style="background:#f9f9f9;padding:12px;border-radius:4px;white-space:pre-wrap;font-size:13px;margin-bottom:12px;">' + escHtml(d.content) + '</div>';

            html += '<p><strong>Date planifiée :</strong> ' + escHtml(d.date) + '</p>';

            // Score SEO
            html += '<div class="fibb-wiz-seo-detail">';
            html += '<strong>Score SEO : </strong>';
            html += '<span style="color:' + d.seo_color + ';font-weight:700;">' + d.seo_score + '/100 — ' + d.seo_label + '</span>';
            html += '<ul style="margin:8px 0 0 0">';
            if (d.kw_present && d.kw_present.length) {
                html += '<li style="color:#27ae60">✔ Mots-clés présents : ' + escHtml(d.kw_present.join(', ')) + '</li>';
            }
            if (d.kw_missing && d.kw_missing.length) {
                html += '<li style="color:#e74c3c">✘ Mots-clés absents : ' + escHtml(d.kw_missing.join(', ')) + '</li>';
            }
            html += '</ul></div>';

            if (d.image_required) {
                html += '<div style="background:#fff3e0;border:1px solid #f39c12;border-radius:4px;padding:8px 12px;margin-top:10px;">⚠️ Ce post Instagram nécessite une image.</div>';
            }

            html += '</div>';
            $('#fibb-wiz-modal-body').html(html);
        });
    };

    window.fibbWizardCloseModal = function () {
        $('#fibb-wizard-overlay').hide();
        $('#fibb-wizard-modal').hide();
    };

    // ── Étape 3 : table des dates ──────────────────────────────────
    function fibbWizardUpdateDateRows() {
        var festDate = $('#wiz-date').val() || fibbWizardPhpData.festivalDate;
        if (!festDate) return;

        var festTs  = new Date(festDate).getTime();
        var selection = state.selection || {};

        $('#fibb-wiz-date-tbody tr.fibb-wiz-date-row').each(function () {
            var $row    = $(this);
            var slug    = $row.data('slug');
            var offset  = parseInt($row.data('offset')) || 0;
            var selVal  = selection.hasOwnProperty(slug) ? selection[slug] : true;

            if (!selVal) {
                $row.hide();
                return;
            }
            $row.show();

            var autoTs = festTs + offset * 86400000;
            var autoDate = new Date(autoTs);
            var formatted = padZ(autoDate.getUTCDate()) + '/' + padZ(autoDate.getUTCMonth() + 1) + '/' + autoDate.getUTCFullYear() + ' 09:00';
            $row.find('.fibb-wiz-auto-date').text(formatted);
        });

        fibbWizardUpdateTimeline();
    }

    // ── Timeline visuelle (étape 3) ────────────────────────────────
    function fibbWizardUpdateTimeline() {
        var $timeline = $('#fibb-wiz-timeline');
        if (!$timeline.length) return;

        // Enlever les anciens points
        $timeline.find('.fibb-wiz-tl-dot').remove();

        var festDate = $('#wiz-date').val() || fibbWizardPhpData.festivalDate;
        if (!festDate) return;

        var festTs  = new Date(festDate).getTime();
        var minTs   = festTs - 90 * 86400000;
        var maxTs   = festTs + 14 * 86400000;
        var span    = maxTs - minTs;
        var selection = state.selection || {};

        $('#fibb-wiz-date-tbody tr.fibb-wiz-date-row:visible').each(function () {
            var $row    = $(this);
            var slug    = $row.data('slug');
            var platform = $row.data('platform');
            var offset  = parseInt($row.data('offset')) || 0;
            var override = $row.find('.fibb-wiz-date-override').val();
            var ts;

            if (override) {
                ts = new Date(override).getTime();
            } else {
                ts = festTs + offset * 86400000;
            }

            var pct = Math.max(0, Math.min(100, ((ts - minTs) / span) * 100));
            var color = PLATFORM_COLORS[platform] || '#888';

            $timeline.append(
                $('<div class="fibb-wiz-tl-dot"></div>').css({
                    left: pct + '%',
                    background: color,
                    title: slug,
                })
            );
        });
    }

    // ── Étape 4 : Révision ─────────────────────────────────────────
    window.fibbWizardRenderReview = function () {
        var festDate  = state.config ? state.config.date  : (fibbWizardPhpData.festivalDate || '');
        var edition   = state.config ? state.config.edition : (fibbWizardPhpData.edition || '');
        var keywords  = state.config ? (state.config.keywords || []) : (fibbWizardPhpData.keywords || []);
        var seoHt     = state.config ? (state.config.seo_hashtags || {}) : (fibbWizardPhpData.seoHashtags || {});
        var selection = state.selection || {};
        var dateOvr   = state.dates || {};
        var icons     = fibbWizardPhpData.platformIcons || {};

        var posts = [];
        var byPhase   = {};
        var byChannel = {};

        $('.fibb-wiz-tpl-card').each(function () {
            var slug     = $(this).data('slug');
            var platform = $(this).data('platform');
            var phase    = $(this).data('phase');
            var offset   = parseInt($(this).data('offset')) || 0;
            var rawContent = $(this).data('content') || '';
            var selVal   = selection.hasOwnProperty(slug) ? selection[slug] : $(this).hasClass('is-selected');

            if (!selVal) return;

            var hashtags = (seoHt[platform] || '');
            var content  = rawContent
                .replace(/\{\{edition\}\}/g, edition)
                .replace(/\{\{festival_date\}\}/g, festDate)
                .replace(/\{\{hashtags\}\}/g, hashtags);

            var schedTs;
            if (dateOvr[slug]) {
                schedTs = new Date(dateOvr[slug]).getTime();
            } else {
                schedTs = festDate ? (new Date(festDate).getTime() + offset * 86400000) : 0;
            }

            var schedStr = schedTs ? (function () {
                var d = new Date(schedTs);
                return padZ(d.getUTCDate()) + '/' + padZ(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear() + ' 09:00';
            })() : '—';

            var score = fibbWizardCalcSeoScore(content, platform, keywords, hashtags);

            byPhase[phase]       = (byPhase[phase] || 0) + 1;
            byChannel[platform]  = (byChannel[platform] || 0) + 1;

            // Image manquante pour instagram
            var needsImage = platform === 'instagram';

            posts.push({ slug, platform, phase, content, schedStr, schedTs, score, needsImage });
        });

        // Tri par date
        posts.sort(function (a, b) { return a.schedTs - b.schedTs; });

        var total   = posts.length;
        var avgSeo  = total ? Math.round(posts.reduce(function (s, p) { return s + p.score; }, 0) / total) : 0;
        var lowSeo  = posts.filter(function (p) { return p.score < 70; }).length;

        // Rendu des stats
        var statsHtml = '';
        statsHtml += '<div class="fibb-wiz-stat-box"><div class="fibb-wiz-stat-num">' + total + '</div><div class="fibb-wiz-stat-label">Posts total</div></div>';
        Object.keys(byPhase).forEach(function (ph) {
            statsHtml += '<div class="fibb-wiz-stat-box"><div class="fibb-wiz-stat-num">' + byPhase[ph] + '</div><div class="fibb-wiz-stat-label">' + escHtml(PHASE_LABELS[ph] || ph) + '</div></div>';
        });
        Object.keys(byChannel).forEach(function (ch) {
            statsHtml += '<div class="fibb-wiz-stat-box"><div class="fibb-wiz-stat-num">' + byChannel[ch] + '</div><div class="fibb-wiz-stat-label">' + (icons[ch] || '') + ' ' + ch + '</div></div>';
        });
        var seoColor = avgSeo >= 70 ? '#27ae60' : (avgSeo >= 40 ? '#f39c12' : '#e74c3c');
        statsHtml += '<div class="fibb-wiz-stat-box"><div class="fibb-wiz-stat-num" style="color:' + seoColor + '">' + avgSeo + '</div><div class="fibb-wiz-stat-label">Score SEO moyen</div></div>';
        if (lowSeo > 0) {
            statsHtml += '<div class="fibb-wiz-stat-box" style="border-color:#f39c12"><div class="fibb-wiz-stat-num" style="color:#f39c12">' + lowSeo + '</div><div class="fibb-wiz-stat-label">⚠ À optimiser</div></div>';
        }
        $('#fibb-wiz-stats').html(statsHtml);

        // Avertissements
        var igNoImg = posts.filter(function (p) { return p.needsImage; });
        var warnings = '';
        if (igNoImg.length) {
            warnings += '<div class="fibb-wiz-warn-item">⚠️ <strong>' + igNoImg.length + ' post(s) Instagram</strong> nécessitent une image. Éditez-les dans l\'onglet Nouveau Post après création.</div>';
        }
        $('#fibb-wiz-warnings').html(warnings);

        // Rendu du tableau
        var rows = '';
        posts.forEach(function (p) {
            var scoreColor = p.score >= 70 ? '#27ae60' : (p.score >= 40 ? '#f39c12' : '#e74c3c');
            var preview    = p.content.substring(0, 70) + (p.content.length > 70 ? '…' : '');
            var warning    = p.score < 40 ? ' <span style="color:#e74c3c" title="Contenu à optimiser">⚠</span>' : '';

            rows += '<tr>';
            rows += '<td><span class="fibb-phase-badge fibb-phase-' + p.phase + '">' + escHtml(PHASE_LABELS[p.phase] || p.phase) + '</span></td>';
            rows += '<td><span class="fibb-platform-badge fibb-platform-' + p.platform + '">' + (icons[p.platform] || '') + ' ' + p.platform + '</span></td>';
            rows += '<td>' + escHtml(p.schedStr) + '</td>';
            rows += '<td><small>' + escHtml(preview) + '</small></td>';
            rows += '<td><span style="font-weight:700;color:' + scoreColor + '">' + p.score + '</span>' + warning + '</td>';
            rows += '<td class="fibb-wiz-yoast-cell" data-slug="' + escHtml(p.slug) + '">—</td>';
            rows += '</tr>';
        });
        $('#fibb-wiz-review-tbody').html(rows);
    };

    // ── Étape 5 : Activation ───────────────────────────────────────
    window.fibbWizardActivate = function (force) {
        // Reset affichage
        $('#fibb-wiz-activation-loading').show();
        $('#fibb-wiz-activation-success').hide();
        $('#fibb-wiz-activation-error').hide();

        // Animer la progress bar
        var $fill = $('#fibb-wiz-progress-fill');
        $fill.css('width', '0%').stop(true).animate({ width: '85%' }, 2000);

        $.post(fibbWizardPhpData.ajaxurl, {
            action:      'fibb_wizard_activate',
            _ajax_nonce: fibbWizardPhpData.nonce,
            force:       force ? 1 : 0,
        }, function (resp) {
            if (!resp.success) {
                $fill.stop(true).css('width', '0%');
                $('#fibb-wiz-activation-loading').hide();
                $('#fibb-wiz-activation-error').show();
                $('#fibb-wiz-error-list').html('<li>' + escHtml(resp.data || 'Erreur inconnue') + '</li>');
                return;
            }

            var d = resp.data;

            // Avertissement doublons
            if (d.duplicate_warning) {
                $fill.stop(true).css('width', '0%');
                $('#fibb-wiz-activation-loading').hide();
                var msg = d.count + ' post(s) similaires existent déjà en base. Créer quand même ?';
                if (confirm(msg)) {
                    fibbWizardActivate(true);
                } else {
                    $('#fibb-panel-4').show();
                    $('#fibb-panel-5').hide();
                    currentStep = 4;
                }
                return;
            }

            $fill.stop(true).animate({ width: '100%' }, 300, function () {
                $('#fibb-wiz-activation-loading').hide();
                if (d.errors && d.errors.length) {
                    $('#fibb-wiz-activation-error').show();
                    var errorHtml = d.errors.map(function (e) { return '<li>' + escHtml(e) + '</li>'; }).join('');
                    if (d.created > 0) {
                        errorHtml = '<li style="color:#27ae60">✔ ' + d.created + ' post(s) créés avec succès</li>' + errorHtml;
                    }
                    $('#fibb-wiz-error-list').html(errorHtml);
                } else {
                    $('#fibb-wiz-success-count').text(d.created);
                    $('#fibb-wiz-activation-success').show();
                }
                state = {};
            });
        }).fail(function () {
            $fill.stop(true).css('width', '0%');
            $('#fibb-wiz-activation-loading').hide();
            $('#fibb-wiz-activation-error').show();
            $('#fibb-wiz-error-list').html('<li>Erreur réseau. Veuillez réessayer.</li>');
        });
    };

    // ── Utilitaires ─────────────────────────────────────────────────
    function fibbWizardGetChannels() {
        var channels = [];
        $('input[name="channels[]"]:checked').each(function () {
            channels.push($(this).val());
        });
        return channels;
    }

    function fibbWizardGetKeywords() {
        var raw = $('#wiz-keywords').val() || '';
        return raw.split(',').map(function (k) { return k.trim(); }).filter(function (k) { return k !== ''; });
    }

    function padZ(n) { return n < 10 ? '0' + n : '' + n; }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function debounce(fn, delay) {
        var timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, delay);
        };
    }

    function fibbWizardToast(msg, type) {
        var cls = type === 'error' ? 'fibb-toast-error' : 'fibb-toast-success';
        var $t = $('<div class="fibb-kanban-toast ' + cls + '">' + escHtml(msg) + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.addClass('visible'); }, 10);
        setTimeout(function () { $t.removeClass('visible'); setTimeout(function () { $t.remove(); }, 400); }, 3000);
    }

    // Export pour usage PHP inline
    window.fibbWizardUpdateSeoBadges  = fibbWizardUpdateSeoBadges;
    window.fibbWizardUpdateDateRows   = fibbWizardUpdateDateRows;

})(jQuery);
