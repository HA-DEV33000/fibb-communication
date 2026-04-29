<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings  = get_option( FIBB_COMM_OPTION, [] );
$interval  = (int) ( $settings['auto_ig_interval'] ?? 60 );
$queue     = FIBB_Comm_DB::get_all_ig_queued();
$history   = FIBB_Comm_DB::get_ig_published( 30 );
$failed    = FIBB_Comm_DB::get_ig_failed();
$today_nb  = FIBB_Comm_DB::get_ig_published_today();
$last_pub  = FIBB_Comm_DB::get_last_published_instagram_time();

// Calcul "prochain post"
if ( $last_pub ) {
    $next_ts     = strtotime( $last_pub ) + $interval * 60;
    $diff        = $next_ts - time();
    $next_label  = $diff > 0
        ? sprintf( 'Dans %dh %02dmin', floor( $diff / 3600 ), floor( ( $diff % 3600 ) / 60 ) )
        : 'Dès la prochaine passe cron';
} else {
    $next_label = count( $queue ) ? 'Dès la prochaine passe cron' : '—';
}

$base_url = admin_url( 'admin.php?page=fibb-communication&tab=instagram-auto' );
?>

<!-- STATS BAR -->
<div class="fibb-ig-stats">
    <div class="fibb-ig-stat">
        <span class="fibb-ig-stat-number"><?php echo esc_html( count( $queue ) ); ?></span>
        <span class="fibb-ig-stat-label">En file</span>
    </div>
    <div class="fibb-ig-stat">
        <span class="fibb-ig-stat-number"><?php echo esc_html( $today_nb ); ?></span>
        <span class="fibb-ig-stat-label">Publiés aujourd'hui</span>
    </div>
    <div class="fibb-ig-stat">
        <span class="fibb-ig-stat-number"><?php echo esc_html( $next_label ); ?></span>
        <span class="fibb-ig-stat-label">Prochain post</span>
    </div>
    <div class="fibb-ig-stat <?php echo count( $failed ) ? 'fibb-ig-stat-warn' : ''; ?>">
        <span class="fibb-ig-stat-number"><?php echo esc_html( count( $failed ) ); ?></span>
        <span class="fibb-ig-stat-label">Échecs</span>
    </div>
</div>

<div class="fibb-ig-interval-notice">
    Intervalle : 1 post toutes les <strong><?php echo esc_html( $interval ); ?> min</strong>
    &nbsp;·&nbsp;
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=settings' ) ); ?>">Modifier dans Paramètres</a>
</div>

<!-- SECTION 0 : PROGRAMMER EN MASSE -->
<div class="fibb-settings-section fibb-bulk-schedule-section">
    <h3>📅 Programmer en masse</h3>
    <div class="fibb-bulk-layout">

        <!-- Colonne gauche : formulaire -->
        <div class="fibb-bulk-form">
            <div class="fibb-bulk-form-row">
                <label>Images</label>
                <div>
                    <button type="button" class="button" id="fibb-bulk-media-btn">🖼 Choisir les images</button>
                    <span id="fibb-bulk-count-label" style="margin-left:8px;color:#888;">0 image sélectionnée</span>
                </div>
            </div>
            <div id="fibb-bulk-thumbs" class="fibb-bulk-thumbs"></div>
            <div class="fibb-bulk-form-row">
                <label for="fibb-bulk-start">Date de début</label>
                <input type="datetime-local" id="fibb-bulk-start" class="regular-text">
            </div>
            <div class="fibb-bulk-form-row">
                <label for="fibb-bulk-interval">Intervalle (min)</label>
                <div>
                    <input type="number" id="fibb-bulk-interval" min="1" value="<?php echo esc_attr( $interval ); ?>" class="small-text" style="width:80px;">
                    <p class="description" style="margin:2px 0 0;">Minutes entre chaque publication.</p>
                </div>
            </div>
            <div class="fibb-bulk-form-row">
                <label for="fibb-bulk-caption">Légende</label>
                <div>
                    <textarea id="fibb-bulk-caption" rows="3" class="large-text"><?php echo esc_textarea( $settings['auto_ig_caption'] ?? '' ); ?></textarea>
                    <p class="description">Tokens : <code>{{edition}}</code> <code>{{hashtags}}</code> <code>{{festival_date}}</code></p>
                </div>
            </div>
            <div class="fibb-bulk-form-row">
                <label></label>
                <div>
                    <button type="button" class="button button-primary" id="fibb-bulk-submit" disabled>
                        📅 Programmer <span id="fibb-bulk-count">0</span> posts
                    </button>
                    <div id="fibb-bulk-msg" style="display:none;margin-top:8px;"></div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : aperçu calendrier -->
        <div class="fibb-bulk-preview">
            <h4 style="margin-top:0;margin-bottom:12px;">Aperçu du calendrier</h4>
            <div id="fibb-bulk-preview-grid" class="fibb-bulk-preview-grid">
                <p style="color:#aaa;font-size:12px;text-align:center;padding:24px 0;">
                    Sélectionnez des images et une date pour voir l'aperçu.
                </p>
            </div>
        </div>

    </div>
</div>

<!-- SECTION 1 : AJOUTER À LA FILE -->
<div class="fibb-settings-section">
    <h3>➕ Ajouter à la file</h3>
    <div class="fibb-ig-add-cols">

        <!-- Colonne gauche : médiathèque WordPress -->
        <div class="fibb-ig-add-col">
            <h4>Depuis la médiathèque</h4>
            <div id="fibb-ig-preview-wrap" style="display:none;margin-bottom:10px;">
                <img id="fibb-ig-preview-img" src="" alt="" style="max-width:200px;max-height:200px;border-radius:6px;border:2px solid #ddd;">
            </div>
            <input type="hidden" id="fibb-ig-image-url" value="">
            <button type="button" class="button" id="fibb-ig-media-btn">🖼 Choisir une image</button>
            <br><br>
            <label for="fibb-ig-caption"><strong>Légende</strong></label>
            <textarea id="fibb-ig-caption" rows="4" class="large-text"
                      placeholder="Légende. Tokens : {{edition}}, {{hashtags}}, {{festival_date}}, {{date}}"></textarea>
            <p class="description">
                Tokens disponibles :
                <code>{{edition}}</code> <code>{{hashtags}}</code> <code>{{festival_date}}</code>
                <code>{{title}}</code> <code>{{url}}</code> <code>{{category}}</code> <code>{{date}}</code>
            </p>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                <button type="button" class="button button-secondary" id="fibb-ig-preview-caption-btn">
                    👁 Aperçu légende
                </button>
                <button type="button" class="button button-primary" id="fibb-ig-add-btn">
                    ➕ Ajouter à la file
                </button>
            </div>
            <div id="fibb-ig-caption-preview" style="display:none;margin-top:10px;background:#f6f7f7;border-left:4px solid #0073aa;padding:10px 14px;font-size:13px;white-space:pre-wrap;"></div>
            <div id="fibb-ig-add-msg" style="display:none;margin-top:8px;"></div>
        </div>

        <!-- Colonne droite : upload direct -->
        <div class="fibb-ig-add-col">
            <h4>Upload direct</h4>
            <label for="fibb-ig-upload-file"><strong>Fichier image (JPG/PNG)</strong></label><br>
            <input type="file" id="fibb-ig-upload-file" accept="image/jpeg,image/png" style="margin:6px 0 10px;">
            <div id="fibb-ig-upload-preview-wrap" style="display:none;margin-bottom:10px;">
                <img id="fibb-ig-upload-preview-img" src="" alt="" style="max-width:200px;max-height:200px;border-radius:6px;border:2px solid #ddd;">
            </div>
            <label for="fibb-ig-upload-caption"><strong>Légende</strong></label>
            <textarea id="fibb-ig-upload-caption" rows="4" class="large-text"
                      placeholder="Même tokens supportés…"></textarea>
            <br>
            <button type="button" class="button button-primary" id="fibb-ig-upload-btn" style="margin-top:8px;">
                ⬆️ Uploader &amp; ajouter à la file
            </button>
            <div id="fibb-ig-upload-msg" style="display:none;margin-top:8px;"></div>
            <!-- Upload via l'API WordPress (ajax wp-admin) -->
            <?php wp_nonce_field( 'media-form' ); ?>
        </div>

    </div>
</div>

<!-- SECTION 2 : FILE D'ATTENTE -->
<div class="fibb-settings-section">
    <h3>📋 File d'attente (<?php echo esc_html( count( $queue ) ); ?> post<?php echo count( $queue ) > 1 ? 's' : ''; ?>)</h3>

    <?php if ( empty( $queue ) ) : ?>
        <p style="color:#888;">La file est vide. Ajoutez des photos ci-dessus.</p>
    <?php else : ?>
        <div class="fibb-ig-queue-list" id="fibb-ig-queue-list">
            <?php foreach ( $queue as $item ) :
                $settings_tz = get_option( FIBB_COMM_OPTION, [] )['festival_timezone'] ?? 'Europe/Paris';
                try {
                    $tz = new DateTimeZone( $settings_tz );
                    $dt = new DateTime( $item['scheduled_at'], new DateTimeZone( 'UTC' ) );
                    $dt->setTimezone( $tz );
                    $date_label = $dt->format( 'd/m/Y H:i' );
                } catch ( Exception $e ) {
                    $date_label = substr( $item['scheduled_at'], 0, 16 );
                }
            ?>
                <div class="fibb-ig-queue-card fibb-ig-panel-trigger" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <?php if ( $item['image_url'] ) : ?>
                        <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="" class="fibb-ig-queue-thumb">
                    <?php else : ?>
                        <div class="fibb-ig-queue-thumb fibb-ig-no-thumb">📷</div>
                    <?php endif; ?>
                    <div class="fibb-ig-queue-info">
                        <span class="fibb-ig-queue-caption">
                            <?php echo esc_html( mb_substr( $item['content'], 0, 80 ) ); ?>
                            <?php echo mb_strlen( $item['content'] ) > 80 ? '…' : ''; ?>
                        </span>
                        <span class="fibb-ig-queue-date">📅 <?php echo esc_html( $date_label ); ?></span>
                    </div>
                    <div class="fibb-ig-queue-actions">
                        <button type="button" class="button button-small fibb-ig-publish-now-btn"
                                data-id="<?php echo esc_attr( $item['id'] ); ?>">▶ Publier</button>
                        <button type="button" class="fibb-ig-queue-remove"
                                data-id="<?php echo esc_attr( $item['id'] ); ?>" title="Retirer de la file">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 3 : HISTORIQUE -->
<div class="fibb-settings-section">
    <h3>📜 Historique des publications</h3>

    <?php
    $history_all = array_merge(
        array_map( function( $r ) { return array_merge( $r, ['_display_status' => 'published'] ); }, $history ),
        array_map( function( $r ) { return array_merge( $r, ['_display_status' => 'failed'] ); }, $failed )
    );
    usort( $history_all, function( $a, $b ) {
        return strtotime( $b['updated_at'] ) - strtotime( $a['updated_at'] );
    } );
    ?>

    <?php if ( empty( $history_all ) ) : ?>
        <p style="color:#888;">Aucune publication Instagram pour l'instant.</p>
    <?php else : ?>
        <table class="fibb-log-table widefat">
            <thead>
                <tr>
                    <th style="width:80px;">Image</th>
                    <th>Légende</th>
                    <th>Date</th>
                    <th>ID Instagram</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $history_all as $item ) : ?>
                    <tr>
                        <td>
                            <?php if ( $item['image_url'] ) : ?>
                                <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( mb_substr( $item['content'], 0, 80 ) . ( mb_strlen( $item['content'] ) > 80 ? '…' : '' ) ); ?></td>
                        <td>
                            <?php
                            $date_ts = $item['_display_status'] === 'published' ? $item['published_at'] : $item['updated_at'];
                            echo $date_ts ? esc_html( gmdate( 'd/m/Y H:i', strtotime( $date_ts ) ) ) : '—';
                            ?>
                        </td>
                        <td>
                            <?php if ( $item['platform_post_id'] ) : ?>
                                <code><?php echo esc_html( $item['platform_post_id'] ); ?></code>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $item['_display_status'] === 'published' ) : ?>
                                <span style="color:#27ae60;font-weight:600;">✓ Publié</span>
                            <?php else : ?>
                                <span style="color:#c0392b;font-weight:600;">✕ Échec</span>
                                <?php if ( $item['error_message'] ) : ?>
                                    <br><small style="color:#888;"><?php echo esc_html( mb_substr( $item['error_message'], 0, 60 ) ); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $item['_display_status'] === 'failed' ) : ?>
                                <button type="button" class="button button-secondary button-small fibb-ig-retry-btn"
                                        data-id="<?php echo esc_attr( $item['id'] ); ?>"
                                        data-image="<?php echo esc_attr( $item['image_url'] ); ?>"
                                        data-caption="<?php echo esc_attr( $item['content'] ); ?>">
                                    🔄 Réessayer
                                </button>
                            <?php else : ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Données pour le JS -->
<script>
var fibbIgData = <?php echo wp_json_encode( [
    'ajaxurl'  => admin_url( 'admin-ajax.php' ),
    'nonce'    => wp_create_nonce( 'fibb_comm_ajax' ),
    'upload_url' => admin_url( 'async-upload.php' ),
] ); ?>;
</script>
