<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings  = get_option( FIBB_COMM_OPTION, [] );
$posted    = isset( $_GET['post-saved'] );
$error     = isset( $_GET['post-error'] ) ? sanitize_text_field( wp_unslash( $_GET['post-error'] ) ) : '';
$edit_id   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$edit_post = $edit_id ? FIBB_Comm_DB::get_post( $edit_id ) : null;

$templates_by_phase = [
    'launch'     => 'Lancement',
    'pre_event'  => 'Pré-événement',
    'during'     => 'Pendant le festival',
    'post_event' => 'Post-événement',
];
?>

<?php if ( $posted ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo $edit_id ? 'Post mis à jour avec succès.' : 'Post planifié avec succès.'; ?></p></div>
<?php endif; ?>
<?php if ( $error ) : ?>
    <div class="notice notice-error is-dismissible"><p>Erreur : <?php echo esc_html( $error ); ?></p></div>
<?php endif; ?>
<?php if ( $edit_post ) : ?>
    <div class="notice notice-warning inline" style="margin-bottom:16px;">
        <p>✏️ <strong>Mode édition</strong> — Post #<?php echo $edit_id; ?> (<?php echo esc_html( $edit_post['platform'] ); ?> — <?php echo esc_html( $edit_post['status'] ); ?>)</p>
    </div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field( 'fibb_comm_new_post', 'fibb_new_post_nonce' ); ?>
    <input type="hidden" name="fibb_comm_action" value="<?php echo $edit_post ? 'update_post' : 'new_post'; ?>">
    <?php if ( $edit_post ) : ?>
    <input type="hidden" name="post_id" value="<?php echo $edit_id; ?>">
    <?php endif; ?>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- COLONNE PRINCIPALE -->
        <div style="flex:2;min-width:340px;">

            <!-- Plateformes -->
            <div class="fibb-settings-section">
                <h3>Plateformes</h3>
                <div class="fibb-platform-checks">
                    <?php foreach ( [ 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn' ] as $plat => $label ) :
                        $checked = $edit_post ? ( $edit_post['platform'] === $plat ) : ( $plat === 'facebook' );
                        $disabled = $edit_post ? 'disabled' : '';
                    ?>
                    <label>
                        <input type="checkbox" class="fibb-platform-cb" name="platforms[]"
                               value="<?php echo esc_attr( $plat ); ?>"
                               <?php checked( $checked ); ?> <?php echo $disabled; ?>>
                        <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( $label ); ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php if ( $edit_post ) : ?>
                        <input type="hidden" name="platforms[]" value="<?php echo esc_attr( $edit_post['platform'] ); ?>">
                        <p style="font-size:11px;color:#aaa;margin:4px 0 0;">La plateforme ne peut pas être changée en mode édition.</p>
                    <?php endif; ?>
                </div>
                <p style="font-size:12px;color:#888;margin-top:8px;">Instagram nécessite une image. Un post séparé sera créé par plateforme cochée.</p>
            </div>

            <!-- Contenu -->
            <div class="fibb-settings-section">
                <h3>Contenu <span id="fibb-char-count" class="fibb-char-counter">0</span></h3>
                <textarea id="fibb-post-content" name="content" rows="7"
                          style="width:100%;font-size:14px;line-height:1.5;"
                          placeholder="Rédigez votre post ici…" required><?php echo esc_textarea( $edit_post['content'] ?? '' ); ?></textarea>
            </div>

            <!-- Image -->
            <div class="fibb-settings-section">
                <h3>Image <small style="color:#999;font-weight:400;">(obligatoire pour Instagram)</small></h3>
                <input type="url" id="fibb-image-url" name="image_url"
                       style="width:100%;margin-bottom:8px;"
                       value="<?php echo esc_attr( $edit_post['image_url'] ?? '' ); ?>"
                       placeholder="https://…">
                <button type="button" id="fibb-media-btn" class="button">
                    Choisir depuis la médiathèque
                </button>
                <br><br>
                <img id="fibb-image-preview" src="" alt="" style="max-width:200px;display:none;border:1px solid #ddd;border-radius:4px;">
            </div>

            <!-- Lien -->
            <div class="fibb-settings-section">
                <h3>Lien <small style="color:#999;font-weight:400;">(optionnel — Facebook &amp; LinkedIn)</small></h3>
                <input type="url" name="link_url" style="width:100%;"
                       value="<?php echo esc_attr( $edit_post['link_url'] ?? '' ); ?>"
                       placeholder="https://festival-international-bridge-bordeaux.com/…">
            </div>
        </div>

        <!-- COLONNE LATÉRALE -->
        <div style="flex:1;min-width:220px;">

            <!-- Planification -->
            <div class="fibb-settings-section">
                <h3>Planification</h3>
                <label style="display:block;font-weight:600;margin-bottom:6px;">Date et heure</label>
                <?php
                $default_dt = $edit_post
                    ? gmdate( 'Y-m-d\TH:i', strtotime( $edit_post['scheduled_at'] ) )
                    : gmdate( 'Y-m-d\TH:i', strtotime( '+1 hour' ) );
                ?>
                <input type="datetime-local" name="scheduled_at" value="<?php echo esc_attr( $default_dt ); ?>" style="width:100%;" required>
                <p style="font-size:12px;color:#888;margin-top:4px;">Heure locale (<?php echo esc_html( $settings['festival_timezone'] ?? 'Europe/Paris' ); ?>)</p>
            </div>

            <!-- Phase -->
            <div class="fibb-settings-section">
                <h3>Phase</h3>
                <select id="fibb-phase" name="phase" style="width:100%;">
                    <?php
                    $current_phase = $edit_post['phase'] ?? '';
                    $phases = [ '' => '— Sans phase —', 'launch' => 'Lancement', 'pre_event' => 'Pré-événement', 'during' => 'Pendant le festival', 'post_event' => 'Post-événement', 'auto' => 'Auto (Instagram)' ];
                    foreach ( $phases as $val => $lbl ) :
                    ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_phase, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Modèles -->
            <div class="fibb-settings-section">
                <h3>Charger un modèle</h3>
                <select id="fibb-load-template" style="width:100%;">
                    <option value="">— Choisir un modèle —</option>
                    <?php foreach ( $templates_by_phase as $phase_key => $phase_label ) : ?>
                        <optgroup label="<?php echo esc_attr( $phase_label ); ?>">
                        <?php
                        $phase_tpls = FIBB_Comm_Templates::get_by_phase( $phase_key );
                        foreach ( $phase_tpls as $slug => $tpl ) :
                            $icon = [ 'facebook' => '🔵', 'instagram' => '📸', 'linkedin' => '🔷' ][ $tpl['platform'] ] ?? '•';
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>">
                                <?php echo esc_html( $icon . ' ' . $slug ); ?>
                            </option>
                        <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:11px;color:#aaa;margin-top:6px;">Remplace le contenu du formulaire avec le modèle sélectionné.</p>
            </div>

            <!-- Boutons -->
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button type="submit" name="publish_now" value="1"
                        class="fibb-btn fibb-btn-secondary" style="text-align:center;padding:10px;">
                    ⚡ Publier maintenant
                </button>
                <button type="submit"
                        class="fibb-btn fibb-btn-primary" style="text-align:center;padding:10px;">
                    📅 Planifier
                </button>
            </div>
        </div>

    </div>
</form>
