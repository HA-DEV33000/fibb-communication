<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings      = get_option( FIBB_COMM_OPTION, [] );
$all_templates = FIBB_Comm_Templates::get_all();
$state         = FIBB_Comm_Wizard::get_state();

// Données pré-remplies
$edition  = esc_attr( $state['config']['edition']  ?? $settings['festival_edition'] ?? '' );
$fest_date = esc_attr( $state['config']['date']    ?? $settings['festival_date']    ?? '' );
$channels  = $state['config']['channels']          ?? [ 'facebook', 'instagram', 'linkedin' ];
$keywords  = $state['config']['keywords']          ?? [];
$kw_string = esc_attr( implode( ', ', $keywords ) );
$seo_ht    = $state['config']['seo_hashtags']      ?? [];

$seo_ht_fb = esc_attr( $seo_ht['facebook']  ?? $settings['hashtags_facebook']  ?? '#FIBB #bridge #Bordeaux' );
$seo_ht_ig = esc_attr( $seo_ht['instagram'] ?? $settings['hashtags_instagram'] ?? '#FIBB #bridge #Bordeaux' );
$seo_ht_li = esc_attr( $seo_ht['linkedin']  ?? $settings['hashtags_linkedin']  ?? '#bridge #bordeaux #fibb' );

$has_meta_creds    = ! empty( $settings['meta_page_token'] ) && ! empty( $settings['meta_page_id'] );
$has_ig_creds      = ! empty( $settings['meta_ig_user_id'] );
$has_linkedin_creds = ! empty( $settings['linkedin_token'] );

$phases = [
    'launch'     => 'Lancement',
    'pre_event'  => 'Avant l\'événement',
    'during'     => 'Pendant l\'événement',
    'post_event' => 'Après l\'événement',
];

// Grouper les templates par phase
$by_phase = [];
foreach ( $all_templates as $slug => $tpl ) {
    $by_phase[ $tpl['phase'] ][ $slug ] = $tpl;
}

$platform_icons = [
    'facebook'  => '🔵',
    'instagram' => '📸',
    'linkedin'  => '🔷',
];

$current_step = $state ? (int) ( $state['step'] ?? 1 ) : 1;
$resume_age   = '';
if ( $state && isset( $state['created_at'] ) ) {
    $diff = time() - $state['created_at'];
    if ( $diff < 3600 ) {
        $resume_age = round( $diff / 60 ) . ' min';
    } else {
        $resume_age = round( $diff / 3600, 1 ) . 'h';
    }
}
?>

<div class="fibb-wizard-wrap">

    <!-- Barre de progression -->
    <div class="fibb-wizard-steps" id="fibb-wizard-nav">
        <?php
        $step_labels = [
            1 => '1 · Configuration',
            2 => '2 · Templates',
            3 => '3 · Dates',
            4 => '4 · Révision',
            5 => '5 · Activation',
        ];
        foreach ( $step_labels as $n => $label ) :
            $cls = $n === 1 ? 'active' : '';
        ?>
            <div class="fibb-wizard-step <?php echo $cls; ?>" data-step="<?php echo $n; ?>">
                <?php echo esc_html( $label ); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bannière de reprise -->
    <?php if ( $state && $current_step >= 2 ) : ?>
    <div class="fibb-wizard-resume" id="fibb-wizard-resume-banner">
        <span>📋 Session en cours — commencée il y a ~<?php echo esc_html( $resume_age ); ?></span>
        <button type="button" class="button" onclick="fibbWizardResume(<?php echo $current_step; ?>)">
            Reprendre à l'étape <?php echo $current_step; ?>
        </button>
        <button type="button" class="button" onclick="fibbWizardReset()">Recommencer</button>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ÉTAPE 1 — Configuration + SEO                  -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="fibb-wizard-panel" id="fibb-panel-1" data-panel="1">
        <h2>Configuration du festival</h2>

        <div class="fibb-wizard-row">
            <div class="fibb-wizard-col-main">
                <table class="form-table">
                    <tr>
                        <th><label for="wiz-edition">Édition n°</label></th>
                        <td><input type="number" id="wiz-edition" min="1" max="99" value="<?php echo $edition; ?>" class="small-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="wiz-date">Date du festival</label></th>
                        <td><input type="date" id="wiz-date" value="<?php echo $fest_date; ?>" required></td>
                    </tr>
                    <tr>
                        <th>Canaux de diffusion</th>
                        <td>
                            <label class="fibb-wiz-chan-label">
                                <input type="checkbox" name="channels[]" value="facebook" <?php checked( in_array( 'facebook', $channels ) ); ?>>
                                🔵 Facebook
                                <span class="fibb-wiz-cred <?php echo $has_meta_creds ? 'fibb-wiz-cred-ok' : 'fibb-wiz-cred-warn'; ?>">
                                    <?php echo $has_meta_creds ? '✔ Connecté' : '⚠ Clés API manquantes'; ?>
                                </span>
                            </label><br>
                            <label class="fibb-wiz-chan-label">
                                <input type="checkbox" name="channels[]" value="instagram" <?php checked( in_array( 'instagram', $channels ) ); ?>>
                                📸 Instagram
                                <span class="fibb-wiz-cred <?php echo $has_ig_creds ? 'fibb-wiz-cred-ok' : 'fibb-wiz-cred-warn'; ?>">
                                    <?php echo $has_ig_creds ? '✔ Connecté' : '⚠ Clés API manquantes'; ?>
                                </span>
                            </label><br>
                            <label class="fibb-wiz-chan-label">
                                <input type="checkbox" name="channels[]" value="linkedin" <?php checked( in_array( 'linkedin', $channels ) ); ?>>
                                🔷 LinkedIn
                                <span class="fibb-wiz-cred <?php echo $has_linkedin_creds ? 'fibb-wiz-cred-ok' : 'fibb-wiz-cred-warn'; ?>">
                                    <?php echo $has_linkedin_creds ? '✔ Connecté' : '⚠ Clés API manquantes'; ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                </table>

                <hr>
                <h3>SEO &amp; Mots-clés</h3>
                <p class="description">Ces mots-clés seront utilisés pour scorer vos posts à l'étape 4.</p>

                <table class="form-table">
                    <tr>
                        <th><label for="wiz-keywords">Mots-clés cibles</label></th>
                        <td>
                            <input type="text" id="wiz-keywords" value="<?php echo $kw_string; ?>" class="regular-text"
                                   placeholder="bridge, Bordeaux, FIBB, tournoi">
                            <p class="description">Séparés par des virgules</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Hashtags SEO</th>
                        <td>
                            <label>🔵 Facebook</label><br>
                            <input type="text" id="wiz-ht-facebook" value="<?php echo $seo_ht_fb; ?>" class="regular-text"><br><br>
                            <label>📸 Instagram</label><br>
                            <input type="text" id="wiz-ht-instagram" value="<?php echo $seo_ht_ig; ?>" class="regular-text"><br><br>
                            <label>🔷 LinkedIn</label><br>
                            <input type="text" id="wiz-ht-linkedin" value="<?php echo $seo_ht_li; ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="fibb-wizard-footer">
            <span></span>
            <button type="button" class="button button-primary" id="wiz-btn-1-next" onclick="fibbWizardNext(1)">
                Suivant →
            </button>
        </div>
        <div id="wiz-error-1" class="fibb-wiz-error" style="display:none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ÉTAPE 2 — Sélection des templates              -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="fibb-wizard-panel" id="fibb-panel-2" data-panel="2" style="display:none">
        <h2>Sélection des templates</h2>
        <p class="description" id="wiz-tpl-notice">
            Cochez les posts à inclure dans votre plan de communication.
        </p>

        <?php foreach ( $phases as $phase_key => $phase_label ) : ?>
            <?php if ( empty( $by_phase[ $phase_key ] ) ) continue; ?>
            <div class="fibb-wiz-phase-section" data-phase="<?php echo esc_attr( $phase_key ); ?>">
                <div class="fibb-wiz-phase-header">
                    <h3><?php echo esc_html( $phase_label ); ?></h3>
                    <div>
                        <button type="button" class="button button-small" onclick="fibbWizardSelectAllPhase('<?php echo esc_attr( $phase_key ); ?>', true)">Tout sélectionner</button>
                        <button type="button" class="button button-small" onclick="fibbWizardSelectAllPhase('<?php echo esc_attr( $phase_key ); ?>', false)">Tout désélectionner</button>
                    </div>
                </div>
                <div class="fibb-wiz-tpl-grid">
                    <?php foreach ( $by_phase[ $phase_key ] as $slug => $tpl ) :
                        $platform     = $tpl['platform'];
                        $icon         = $platform_icons[ $platform ] ?? '📄';
                        $saved_sel    = $state['selection'][ $slug ] ?? in_array( $platform, $channels );
                        $preview_text = mb_substr( $tpl['content'], 0, 120 ) . ( mb_strlen( $tpl['content'] ) > 120 ? '…' : '' );
                    ?>
                        <div class="fibb-wiz-tpl-card <?php echo $saved_sel ? 'is-selected' : ''; ?>"
                             data-slug="<?php echo esc_attr( $slug ); ?>"
                             data-platform="<?php echo esc_attr( $platform ); ?>"
                             data-phase="<?php echo esc_attr( $phase_key ); ?>"
                             data-offset="<?php echo (int) $tpl['offset']; ?>"
                             data-content="<?php echo esc_attr( $tpl['content'] ); ?>">
                            <div class="fibb-wiz-tpl-card-head">
                                <span class="fibb-platform-badge fibb-platform-<?php echo esc_attr( $platform ); ?>">
                                    <?php echo $icon; ?> <?php echo esc_html( ucfirst( $platform ) ); ?>
                                </span>
                                <span class="fibb-wiz-seo-badge" data-slug="<?php echo esc_attr( $slug ); ?>"></span>
                            </div>
                            <p class="fibb-wiz-tpl-preview"><?php echo esc_html( $preview_text ); ?></p>
                            <div class="fibb-wiz-tpl-card-foot">
                                <label>
                                    <input type="checkbox" class="fibb-wiz-tpl-check"
                                           name="selection[<?php echo esc_attr( $slug ); ?>]"
                                           value="1"
                                           <?php checked( $saved_sel ); ?>>
                                    Inclure
                                </label>
                                <button type="button" class="button button-small"
                                        onclick="fibbWizardOpenPreview('<?php echo esc_attr( $slug ); ?>')">
                                    Aperçu
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Note newsletter -->
        <div class="fibb-wiz-newsletter-note" id="fibb-wiz-nl-note" style="display:none">
            <p>📧 <strong>Newsletter :</strong> Aucun template disponible — les envois newsletter peuvent être créés manuellement dans l'onglet Newsletter.</p>
        </div>

        <div class="fibb-wizard-footer">
            <button type="button" class="button" onclick="fibbWizardGoTo(1)">← Étape précédente</button>
            <button type="button" class="button button-primary" onclick="fibbWizardNext(2)">Suivant →</button>
        </div>
        <div id="wiz-error-2" class="fibb-wiz-error" style="display:none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ÉTAPE 3 — Personnalisation des dates           -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="fibb-wizard-panel" id="fibb-panel-3" data-panel="3" style="display:none">
        <h2>Personnalisation des dates</h2>
        <p class="description">Les dates sont calculées automatiquement à partir de la date du festival. Vous pouvez les ajuster individuellement.</p>

        <!-- Timeline visuelle -->
        <div class="fibb-wiz-timeline-wrap">
            <div class="fibb-wiz-timeline" id="fibb-wiz-timeline">
                <div class="fibb-wiz-tl-label fibb-wiz-tl-start">J-90</div>
                <div class="fibb-wiz-tl-label fibb-wiz-tl-mid">Jour J</div>
                <div class="fibb-wiz-tl-label fibb-wiz-tl-end">J+14</div>
            </div>
        </div>

        <table class="fibb-wiz-date-table wp-list-table widefat fixed striped" id="fibb-wiz-date-table">
            <thead>
                <tr>
                    <th style="width:100px">Phase</th>
                    <th style="width:90px">Canal</th>
                    <th style="width:180px">Slug</th>
                    <th style="width:160px">Date auto</th>
                    <th>Override (optionnel)</th>
                    <th style="width:80px">Statut</th>
                </tr>
            </thead>
            <tbody id="fibb-wiz-date-tbody">
                <?php foreach ( $all_templates as $slug => $tpl ) :
                    $saved_override = $state['dates'][ $slug ] ?? '';
                    $local_override = '';
                    if ( $saved_override ) {
                        // Convertir UTC → local pour l'affichage dans datetime-local
                        $tz_id = $settings['festival_timezone'] ?? 'Europe/Paris';
                        try {
                            $dt = new DateTime( $saved_override, new DateTimeZone( 'UTC' ) );
                            $dt->setTimezone( new DateTimeZone( $tz_id ) );
                            $local_override = $dt->format( 'Y-m-d\TH:i' );
                        } catch ( Exception $e ) {}
                    }
                ?>
                    <tr class="fibb-wiz-date-row"
                        data-slug="<?php echo esc_attr( $slug ); ?>"
                        data-phase="<?php echo esc_attr( $tpl['phase'] ); ?>"
                        data-platform="<?php echo esc_attr( $tpl['platform'] ); ?>"
                        data-offset="<?php echo (int) $tpl['offset']; ?>">
                        <td>
                            <span class="fibb-phase-badge fibb-phase-<?php echo esc_attr( $tpl['phase'] ); ?>">
                                <?php echo esc_html( $phases[ $tpl['phase'] ] ?? $tpl['phase'] ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="fibb-platform-badge fibb-platform-<?php echo esc_attr( $tpl['platform'] ); ?>">
                                <?php echo $platform_icons[ $tpl['platform'] ] ?? ''; ?> <?php echo esc_html( ucfirst( $tpl['platform'] ) ); ?>
                            </span>
                        </td>
                        <td><small><?php echo esc_html( $slug ); ?></small></td>
                        <td class="fibb-wiz-auto-date">—</td>
                        <td>
                            <input type="datetime-local"
                                   class="fibb-wiz-date-override"
                                   data-slug="<?php echo esc_attr( $slug ); ?>"
                                   value="<?php echo esc_attr( $local_override ); ?>">
                        </td>
                        <td class="fibb-wiz-date-status">
                            <?php if ( $local_override ) : ?>
                                <span class="fibb-wiz-date-edit-tag">Modifiée</span>
                            <?php else : ?>
                                <span class="fibb-wiz-date-auto-tag">Auto</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="fibb-wizard-footer">
            <button type="button" class="button" onclick="fibbWizardGoTo(2)">← Étape précédente</button>
            <button type="button" class="button button-primary" onclick="fibbWizardNext(3)">Suivant →</button>
        </div>
        <div id="wiz-error-3" class="fibb-wiz-error" style="display:none;"></div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ÉTAPE 4 — Révision + SEO                       -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="fibb-wizard-panel" id="fibb-panel-4" data-panel="4" style="display:none">
        <h2>Révision du plan</h2>

        <!-- Stats résumé -->
        <div class="fibb-wiz-summary-stats" id="fibb-wiz-stats">
            <!-- Rempli par JS -->
        </div>

        <!-- Avertissements -->
        <div id="fibb-wiz-warnings" class="fibb-wiz-warnings"></div>

        <!-- Table de révision -->
        <table class="wp-list-table widefat fixed striped" id="fibb-wiz-review-table">
            <thead>
                <tr>
                    <th style="width:130px">Phase</th>
                    <th style="width:90px">Canal</th>
                    <th style="width:150px">Date planifiée</th>
                    <th>Aperçu contenu</th>
                    <th style="width:80px">Score SEO</th>
                    <th style="width:100px">Yoast/RM</th>
                </tr>
            </thead>
            <tbody id="fibb-wiz-review-tbody">
                <!-- Rempli par JS fibbWizardRenderReview() -->
            </tbody>
        </table>

        <div class="fibb-wizard-footer">
            <div>
                <button type="button" class="button" onclick="fibbWizardGoTo(2)">← Modifier les templates</button>
                <button type="button" class="button" onclick="fibbWizardGoTo(3)" style="margin-left:8px">← Modifier les dates</button>
                <button type="button" class="button" onclick="fibbWizardReset()" style="margin-left:8px; color:#c0392b">Tout recommencer</button>
            </div>
            <button type="button" class="button button-primary" id="wiz-btn-activate" onclick="fibbWizardGoTo(5); fibbWizardActivate()">
                🚀 Lancer la création →
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ÉTAPE 5 — Activation                           -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="fibb-wizard-panel" id="fibb-panel-5" data-panel="5" style="display:none">
        <h2>Activation du plan</h2>

        <div id="fibb-wiz-activation-loading">
            <p>Création des posts en cours…</p>
            <div class="fibb-wiz-progress-bar">
                <div class="fibb-wiz-progress-fill" id="fibb-wiz-progress-fill"></div>
            </div>
        </div>

        <div id="fibb-wiz-activation-success" style="display:none">
            <div class="fibb-wiz-success-banner">
                <span class="fibb-wiz-checkmark">✅</span>
                <strong id="fibb-wiz-success-count"></strong> posts créés avec succès !
            </div>
            <div style="margin-top:20px; display:flex; gap:12px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=plan' ) ); ?>"
                   class="button button-primary">Voir le tableau de bord →</a>
                <button type="button" class="button" onclick="fibbWizardReset()">Nouvel assistant</button>
            </div>
        </div>

        <div id="fibb-wiz-activation-error" style="display:none">
            <div class="fibb-wiz-error-banner">
                ❌ Des erreurs sont survenues lors de la création :
            </div>
            <ul id="fibb-wiz-error-list"></ul>
            <div style="margin-top:16px; display:flex; gap:12px;">
                <button type="button" class="button button-primary" onclick="fibbWizardActivate()">Réessayer</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=plan' ) ); ?>"
                   class="button">Voir le plan quand même</a>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- Modal preview template                         -->
    <!-- ═══════════════════════════════════════════════ -->
    <div id="fibb-wizard-overlay" onclick="fibbWizardCloseModal()" style="display:none"></div>
    <div id="fibb-wizard-modal" class="fibb-wizard-modal" style="display:none">
        <div class="fibb-wizard-modal-head">
            <h3 id="fibb-wiz-modal-title">Aperçu du template</h3>
            <button type="button" class="fibb-wiz-modal-close" onclick="fibbWizardCloseModal()">✕</button>
        </div>
        <div id="fibb-wiz-modal-body">
            <div class="fibb-wiz-spinner">Chargement…</div>
        </div>
    </div>

</div><!-- .fibb-wizard-wrap -->

<!-- Données PHP → JS -->
<script>
var fibbWizardPhpData = <?php echo wp_json_encode( [
    'ajaxurl'      => admin_url( 'admin-ajax.php' ),
    'nonce'        => wp_create_nonce( 'fibb_comm_ajax' ),
    'festivalDate' => $fest_date,
    'edition'      => $edition,
    'keywords'     => $keywords,
    'seoHashtags'  => [
        'facebook'  => $seo_ht_fb,
        'instagram' => $seo_ht_ig,
        'linkedin'  => $seo_ht_li,
    ],
    'channels'     => $channels,
    'savedState'   => $state,
    'planUrl'      => admin_url( 'admin.php?page=fibb-communication&tab=plan' ),
    'phases'       => $phases,
    'platformIcons' => $platform_icons,
] ); ?>;
</script>
