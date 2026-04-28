<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = get_option( FIBB_COMM_OPTION, [] );
$saved    = isset( $_GET['settings-saved'] );
?>
<?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>Paramètres enregistrés.</p></div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field( 'fibb_comm_settings', 'fibb_settings_nonce' ); ?>
    <input type="hidden" name="fibb_comm_action" value="save_settings">

    <!-- FESTIVAL -->
    <div class="fibb-settings-section">
        <h3>🎪 Paramètres du Festival</h3>
        <table class="form-table">
            <tr>
                <th><label for="festival_edition">Numéro d'édition</label></th>
                <td>
                    <input type="number" id="festival_edition" name="festival_edition" min="1"
                           value="<?php echo esc_attr( $settings['festival_edition'] ?? '6' ); ?>"
                           class="small-text">
                    <p class="description">Utilisé comme <code>{{edition}}</code> dans tous les modèles de posts.</p>
                </td>
            </tr>
            <tr>
                <th><label for="festival_date">Date de début du festival</label></th>
                <td>
                    <input type="date" id="festival_date" name="festival_date"
                           value="<?php echo esc_attr( $settings['festival_date'] ?? '' ); ?>"
                           class="regular-text">
                    <p class="description">Toutes les dates des modèles sont calculées par rapport à cette date.</p>
                </td>
            </tr>
            <tr>
                <th><label for="festival_timezone">Fuseau horaire</label></th>
                <td>
                    <select id="festival_timezone" name="festival_timezone">
                        <?php
                        $current_tz = $settings['festival_timezone'] ?? 'Europe/Paris';
                        $timezones  = [ 'Europe/Paris', 'Europe/London', 'Europe/Brussels', 'America/New_York', 'UTC' ];
                        foreach ( $timezones as $tz ) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr( $tz ),
                                selected( $current_tz, $tz, false ),
                                esc_html( $tz )
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
    </div>

    <!-- HASHTAGS PAR DEFAUT -->
    <div class="fibb-settings-section">
        <h3>#️⃣ Hashtags par défaut</h3>
        <p style="color:#666;font-size:13px;">Utilisé comme <code>{{hashtags}}</code> dans les modèles.</p>
        <table class="form-table">
            <tr>
                <th><label for="hashtags_facebook">Facebook</label></th>
                <td>
                    <input type="text" id="hashtags_facebook" name="hashtags_facebook"
                           value="<?php echo esc_attr( $settings['hashtags_facebook'] ?? '#FIBB #bridge #Bordeaux #festival' ); ?>"
                           class="large-text">
                </td>
            </tr>
            <tr>
                <th><label for="hashtags_instagram">Instagram</label></th>
                <td>
                    <input type="text" id="hashtags_instagram" name="hashtags_instagram"
                           value="<?php echo esc_attr( $settings['hashtags_instagram'] ?? '#FIBB #bridge #Bordeaux #festival #bridgelife #cardgames' ); ?>"
                           class="large-text">
                </td>
            </tr>
            <tr>
                <th><label for="hashtags_linkedin">LinkedIn</label></th>
                <td>
                    <input type="text" id="hashtags_linkedin" name="hashtags_linkedin"
                           value="<?php echo esc_attr( $settings['hashtags_linkedin'] ?? '#bridge #bordeaux #sport #fibb' ); ?>"
                           class="large-text">
                </td>
            </tr>
        </table>
    </div>

    <!-- AUTO INSTAGRAM -->
    <div class="fibb-settings-section">
        <h3>📸 Publication automatique Instagram</h3>
        <p style="color:#666;font-size:13px;">Publier automatiquement sur Instagram quand une photo est uploadée ou un article publié.</p>
        <table class="form-table">
            <tr>
                <th>Activation</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_ig_enabled" value="1"
                               <?php checked( ! empty( $settings['auto_ig_enabled'] ) ); ?>>
                        Activer la publication automatique des photos
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="auto_ig_delay">Délai avant publication</label></th>
                <td>
                    <input type="number" id="auto_ig_delay" name="auto_ig_delay" min="0" max="1440"
                           value="<?php echo esc_attr( $settings['auto_ig_delay'] ?? '30' ); ?>"
                           class="small-text"> minutes
                    <p class="description">Délai après l'upload ou la publication avant de poster sur Instagram (0 = immédiat).</p>
                </td>
            </tr>
            <tr>
                <th><label for="auto_ig_min_width">Largeur minimale</label></th>
                <td>
                    <input type="number" id="auto_ig_min_width" name="auto_ig_min_width" min="0"
                           value="<?php echo esc_attr( $settings['auto_ig_min_width'] ?? '1080' ); ?>"
                           class="small-text"> px
                    <p class="description">Les images plus petites ne sont pas publiées automatiquement (recommandé : 1080px).</p>
                </td>
            </tr>
            <tr>
                <th><label for="auto_ig_caption">Légende automatique</label></th>
                <td>
                    <input type="text" id="auto_ig_caption" name="auto_ig_caption"
                           value="<?php echo esc_attr( $settings['auto_ig_caption'] ?? '📸 FIBB {{edition}} — {{hashtags}}' ); ?>"
                           class="large-text">
                    <p class="description">Tokens disponibles : <code>{{edition}}</code>, <code>{{hashtags}}</code>. Pour les articles, le titre est ajouté automatiquement.</p>
                </td>
            </tr>
            <tr>
                <th><label for="auto_ig_categories">Catégories déclencheuses</label></th>
                <td>
                    <input type="text" id="auto_ig_categories" name="auto_ig_categories"
                           value="<?php echo esc_attr( $settings['auto_ig_categories'] ?? '' ); ?>"
                           class="large-text" placeholder="Photos, Galerie, Actualités">
                    <p class="description">Séparées par des virgules. Laisser vide = déclencher sur tous les articles avec image à la une.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- MAINTENANCE -->
    <div class="fibb-settings-section">
        <h3>🗑 Maintenance des logs</h3>
        <table class="form-table">
            <tr>
                <th><label for="log_retention">Rétention des logs</label></th>
                <td>
                    <input type="number" id="log_retention" name="log_retention" min="7" max="365"
                           value="<?php echo esc_attr( $settings['log_retention'] ?? '90' ); ?>"
                           class="small-text"> jours
                    <p class="description">Les posts publiés et échoués plus anciens que cette durée sont supprimés automatiquement.</p>
                </td>
            </tr>
        </table>

        <p style="margin-top:12px;">
            <button type="button" class="button button-secondary"
                    onclick="document.getElementById('fibb-clear-failed-form').submit();"
                    >Supprimer tous les posts échoués</button>
        </p>
    </div>

    <!-- ERREURS RECENTES -->
    <div class="fibb-settings-section">
        <h3>❌ Dernières erreurs</h3>
        <?php $errors = FIBB_Comm_DB::get_recent_errors( 10 ); ?>
        <?php if ( empty( $errors ) ) : ?>
            <p style="color:#27ae60;">✓ Aucune erreur récente.</p>
        <?php else : ?>
            <table class="fibb-log-table widefat">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plateforme</th>
                        <th>Contenu (début)</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $errors as $err ) : ?>
                        <tr>
                            <td><?php echo esc_html( $err['updated_at'] ); ?></td>
                            <td><span class="fibb-badge fibb-badge-<?php echo esc_attr( $err['platform'] ); ?>"><?php echo esc_html( $err['platform'] ); ?></span></td>
                            <td><?php echo esc_html( mb_substr( $err['content'], 0, 80 ) . '…' ); ?></td>
                            <td style="color:#c0392b;"><?php echo esc_html( $err['error_message'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p class="submit">
        <input type="submit" class="button button-primary" value="Enregistrer les paramètres">
    </p>
</form>

<!-- Formulaire séparé pour "Supprimer les posts échoués" (hors du form principal) -->
<form id="fibb-clear-failed-form" method="post" action=""
      onsubmit="return confirm('Supprimer tous les posts avec statut « échoué » ?');">
    <?php wp_nonce_field( 'fibb_comm_clear_failed', 'fibb_clear_failed_nonce' ); ?>
    <input type="hidden" name="fibb_comm_action" value="clear_failed">
</form>

<p style="color:#aaa;font-size:11px;margin-top:24px;">FIBB Communication Suite v<?php echo esc_html( FIBB_COMM_VERSION ); ?></p>
