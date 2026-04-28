<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$bridge  = new FIBB_Comm_Newsletter_Bridge();
$nl_opts = $bridge->get_options();
$api_key = $nl_opts['api_key']      ?? '';
$list_id = (int) ( $nl_opts['list_id'] ?? 0 );

$last_sent  = $bridge->get_last_send();
$next_cron  = $bridge->get_next_send();
$wp_posts   = $bridge->get_wp_posts();
$history    = get_option( 'fibb_nl_send_history', [] );

// Récupère les stats de la liste en cache (1h) pour éviter les appels API inutiles
$list_stats = null;
if ( $api_key && $list_id ) {
    $cache_key  = 'fibb_nl_list_stats_' . $list_id;
    $list_stats = get_transient( $cache_key );
    if ( false === $list_stats ) {
        $list_stats = $bridge->get_list_stats( $api_key, $list_id );
        if ( $list_stats ) set_transient( $cache_key, $list_stats, HOUR_IN_SECONDS );
    }
}

$subscriber_count = $list_stats['totalSubscribers'] ?? null;
$list_name        = $list_stats['name']             ?? ( $list_id ? 'Liste #' . $list_id : '—' );

$base_url = admin_url( 'admin.php?page=fibb-communication&tab=newsletter' );
?>

<?php
// Notices de retour
if ( isset( $_GET['nl-saved'] ) ) :
?>
<div class="notice notice-success is-dismissible"><p>✅ Paramètres newsletter enregistrés.</p></div>
<?php elseif ( isset( $_GET['nl-sent'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>✅ Newsletter envoyée ! ID campagne Brevo : <strong><?php echo esc_html( $_GET['nl-sent'] ); ?></strong></p></div>
<?php elseif ( isset( $_GET['nl-error'] ) ) : ?>
<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_html( urldecode( $_GET['nl-error'] ) ); ?></p></div>
<?php endif; ?>

<!-- ── Status cards ─────────────────────────────────────── -->
<div class="fibb-nl-status-bar">

    <div class="fibb-nl-stat-card">
        <div class="fibb-nl-stat-icon">📨</div>
        <div class="fibb-nl-stat-body">
            <span class="fibb-nl-stat-label">Dernier envoi</span>
            <span class="fibb-nl-stat-value">
                <?php if ( $last_sent ) :
                    echo esc_html( date_i18n( 'd/m/Y', strtotime( $last_sent['date'] ) ) );
                else : ?>—<?php endif; ?>
            </span>
            <?php if ( $last_sent ) : ?>
            <span class="fibb-nl-stat-sub">ID <?php echo esc_html( $last_sent['campaign_id'] ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="fibb-nl-stat-card">
        <div class="fibb-nl-stat-icon">⏰</div>
        <div class="fibb-nl-stat-body">
            <span class="fibb-nl-stat-label">Prochain envoi automatique</span>
            <span class="fibb-nl-stat-value">
                <?php if ( $next_cron ) :
                    echo esc_html( date_i18n( 'd/m/Y', $next_cron ) );
                else : ?>Non planifié<?php endif; ?>
            </span>
            <?php if ( $next_cron ) : ?>
            <span class="fibb-nl-stat-sub">dans <?php echo esc_html( human_time_diff( time(), $next_cron ) ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="fibb-nl-stat-card">
        <div class="fibb-nl-stat-icon">👥</div>
        <div class="fibb-nl-stat-body">
            <span class="fibb-nl-stat-label">Abonnés</span>
            <span class="fibb-nl-stat-value">
                <?php echo null !== $subscriber_count ? number_format( (int) $subscriber_count, 0, ',', ' ' ) : '—'; ?>
            </span>
            <span class="fibb-nl-stat-sub"><?php echo esc_html( $list_name ); ?></span>
        </div>
    </div>

    <div class="fibb-nl-stat-card">
        <div class="fibb-nl-stat-icon">📰</div>
        <div class="fibb-nl-stat-body">
            <span class="fibb-nl-stat-label">Articles disponibles</span>
            <span class="fibb-nl-stat-value"><?php echo count( $wp_posts ); ?></span>
            <span class="fibb-nl-stat-sub">pour le prochain envoi</span>
        </div>
    </div>

</div>

<!-- ── Colonnes ─────────────────────────────────────────── -->
<div class="fibb-nl-layout">

    <!-- Colonne gauche : Configuration -->
    <div class="fibb-nl-config">
        <h3 class="fibb-nl-section-title">⚙️ Configuration Brevo</h3>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'fibb_comm_newsletter_save', 'fibb_nl_nonce' ); ?>
            <input type="hidden" name="action" value="fibb_comm_newsletter_save">

            <div class="fibb-nl-field">
                <label>Clé API Brevo</label>
                <input type="text" name="api_key"
                       value="<?php echo esc_attr( $api_key ); ?>"
                       class="widefat" style="font-family:monospace;font-size:12px;"
                       placeholder="xkeysib-…">
            </div>

            <div class="fibb-nl-field">
                <label>ID de la liste Brevo</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" name="list_id"
                           value="<?php echo esc_attr( $list_id ); ?>"
                           class="small-text" min="1">
                    <button type="button" id="fibb-nl-load-lists" class="button">
                        🔍 Voir mes listes
                    </button>
                </div>
                <div id="fibb-nl-lists-result" style="margin-top:8px;"></div>
            </div>

            <div class="fibb-nl-field">
                <label>Nom expéditeur</label>
                <input type="text" name="sender_name"
                       value="<?php echo esc_attr( $nl_opts['sender_name'] ?? 'FIBB' ); ?>"
                       class="regular-text">
            </div>

            <div class="fibb-nl-field">
                <label>Email expéditeur</label>
                <input type="email" name="sender_email"
                       value="<?php echo esc_attr( $nl_opts['sender_email'] ?? 'info@fibb.fr' ); ?>"
                       class="regular-text">
            </div>

            <div class="fibb-nl-field">
                <label>Email de réponse</label>
                <input type="email" name="reply_to"
                       value="<?php echo esc_attr( $nl_opts['reply_to'] ?? 'info@fibb.fr' ); ?>"
                       class="regular-text">
            </div>

            <div class="fibb-nl-field">
                <label>Jour d'envoi mensuel automatique</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" name="send_day"
                           value="<?php echo esc_attr( $nl_opts['send_day'] ?? 1 ); ?>"
                           class="small-text" min="1" max="28">
                    <span style="color:#666;">du mois</span>
                </div>
            </div>

            <button type="submit" class="fibb-btn fibb-btn-primary" style="margin-top:8px;">
                💾 Enregistrer
            </button>
        </form>
    </div>

    <!-- Colonne droite : Actions + Historique -->
    <div class="fibb-nl-sidebar">

        <!-- Aperçu -->
        <div class="fibb-nl-action-card">
            <h3 class="fibb-nl-section-title">👁 Aperçu</h3>
            <p style="font-size:12px;color:#666;margin:0 0 12px 0;">
                Visualise la newsletter avec les <?php echo count( $wp_posts ); ?> derniers articles.
            </p>
            <button type="button" id="fibb-nl-preview-btn" class="button button-secondary" style="width:100%;">
                Prévisualiser →
            </button>
        </div>

        <!-- Envoi manuel -->
        <div class="fibb-nl-action-card fibb-nl-action-danger">
            <h3 class="fibb-nl-section-title">🚀 Envoi manuel</h3>
            <p style="font-size:12px;color:#666;margin:0 0 12px 0;">
                Envoie immédiatement la newsletter à toute la liste
                <?php if ( $subscriber_count ) : ?>
                (<?php echo (int) $subscriber_count; ?> abonnés)
                <?php endif; ?>.
            </p>
            <?php if ( ! $api_key || ! $list_id ) : ?>
            <p style="font-size:12px;color:#e74c3c;margin:0;">
                ⚠ Configurez la clé API et l'ID de liste avant d'envoyer.
            </p>
            <?php else : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'fibb_comm_newsletter_send', 'fibb_nl_send_nonce' ); ?>
                <input type="hidden" name="action" value="fibb_comm_newsletter_send">
                <button type="submit"
                        class="fibb-btn fibb-btn-primary"
                        style="width:100%;background:#c8102e;border-color:#a00;"
                        onclick="return confirm('Envoyer la newsletter maintenant à toute la liste ?')">
                    📨 Envoyer maintenant
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Historique -->
        <?php if ( $last_sent || ! empty( $history ) ) : ?>
        <div class="fibb-nl-action-card">
            <h3 class="fibb-nl-section-title">📋 Historique des envois</h3>
            <table class="fibb-nl-history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>ID campagne</th>
                        <th>Articles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $all_sends = $last_sent ? array_merge( [ $last_sent ], $history ) : $history;
                    foreach ( array_slice( $all_sends, 0, 8 ) as $send ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $send['date'] ) ) ); ?></td>
                        <td><code><?php echo esc_html( $send['campaign_id'] ); ?></code></td>
                        <td style="text-align:center;"><?php echo (int) $send['posts_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal aperçu newsletter -->
<div id="fibb-nl-preview-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:99998;" onclick="document.getElementById('fibb-nl-preview-overlay').style.display='none';"></div>
<div id="fibb-nl-preview-modal" style="display:none;position:fixed;top:40px;left:50%;transform:translateX(-50%);width:680px;max-width:95vw;height:80vh;background:#fff;border-radius:8px;overflow:hidden;z-index:99999;box-shadow:0 20px 60px rgba(0,0,0,.4);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#1a1a2e;color:#fff;">
        <span style="font-weight:700;font-size:14px;">📧 Aperçu Newsletter</span>
        <button onclick="document.getElementById('fibb-nl-preview-overlay').style.display='none';document.getElementById('fibb-nl-preview-modal').style.display='none';"
                style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;">✕</button>
    </div>
    <iframe id="fibb-nl-preview-frame" style="width:100%;height:calc(100% - 44px);border:none;"></iframe>
</div>

<script>
(function () {
    // Aperçu
    document.getElementById('fibb-nl-preview-btn').addEventListener('click', function () {
        this.textContent = 'Chargement…';
        this.disabled = true;
        var self = this;
        var fd = new FormData();
        fd.append('action', 'fibb_comm_newsletter_preview');
        fd.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'fibb_comm_ajax' ) ); ?>);
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                self.textContent = 'Prévisualiser →';
                self.disabled = false;
                if (!resp.success) { alert('Erreur : ' + (resp.data && resp.data.error ? resp.data.error : 'inconnue')); return; }
                var frame = document.getElementById('fibb-nl-preview-frame');
                frame.srcdoc = resp.data.html;
                document.getElementById('fibb-nl-preview-overlay').style.display = 'block';
                document.getElementById('fibb-nl-preview-modal').style.display = 'block';
            })
            .catch(function () {
                self.textContent = 'Prévisualiser →';
                self.disabled = false;
                alert('Erreur réseau');
            });
    });

    // Chargement des listes Brevo
    document.getElementById('fibb-nl-load-lists').addEventListener('click', function () {
        var apiKey = document.querySelector('input[name="api_key"]').value.trim();
        if (!apiKey) { alert('Entrez d\'abord la clé API Brevo.'); return; }
        var result = document.getElementById('fibb-nl-lists-result');
        result.innerHTML = '<span style="color:#666;font-size:12px;">Chargement…</span>';
        var fd = new FormData();
        fd.append('action', 'fibb_comm_newsletter_lists');
        fd.append('api_key', apiKey);
        fd.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'fibb_comm_ajax' ) ); ?>);
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success || !resp.data.lists) {
                    result.innerHTML = '<span style="color:#e74c3c;font-size:12px;">❌ Impossible de charger les listes.</span>';
                    return;
                }
                var html = '<table style="font-size:12px;border-collapse:collapse;width:100%;margin-top:4px;">';
                html += '<tr><th style="text-align:left;padding:3px 8px;background:#f1f1f1;">ID</th><th style="text-align:left;padding:3px 8px;background:#f1f1f1;">Nom</th><th style="padding:3px 8px;background:#f1f1f1;">Abonnés</th></tr>';
                resp.data.lists.forEach(function (l) {
                    html += '<tr style="cursor:pointer;" onclick="document.querySelector(\'input[name=list_id]\').value=' + l.id + ';this.style.background=\'#e8f4fd\';">'
                         + '<td style="padding:4px 8px;border-top:1px solid #eee;"><strong>' + l.id + '</strong></td>'
                         + '<td style="padding:4px 8px;border-top:1px solid #eee;">' + l.name + '</td>'
                         + '<td style="padding:4px 8px;border-top:1px solid #eee;text-align:center;">' + (l.totalSubscribers || 0) + '</td>'
                         + '</tr>';
                });
                html += '</table><p style="font-size:11px;color:#888;margin:4px 0 0 0;">Cliquez sur une ligne pour sélectionner la liste.</p>';
                result.innerHTML = html;
            })
            .catch(function () {
                result.innerHTML = '<span style="color:#e74c3c;font-size:12px;">❌ Erreur réseau.</span>';
            });
    });
})();
</script>
