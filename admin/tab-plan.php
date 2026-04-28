<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$view_mode = ( isset( $_GET['plan_view'] ) && 'status' === $_GET['plan_view'] ) ? 'status' : 'phase';
$base_url  = admin_url( 'admin.php?page=fibb-communication&tab=plan' );
$new_url   = admin_url( 'admin.php?page=fibb-communication&tab=new-post' );
$settings  = get_option( FIBB_COMM_OPTION, [] );
$edition   = $settings['festival_edition'] ?? '6';

$all_posts = FIBB_Comm_DB::get_all_posts();

// Stats par plateforme
$plat_stats = [];
foreach ( $all_posts as $p ) {
    $pl = $p['platform'];
    $st = $p['status'];
    if ( ! isset( $plat_stats[ $pl ] ) ) {
        $plat_stats[ $pl ] = [ 'draft' => 0, 'scheduled' => 0, 'published' => 0, 'failed' => 0, 'total' => 0 ];
    }
    if ( isset( $plat_stats[ $pl ][ $st ] ) ) $plat_stats[ $pl ][ $st ]++;
    $plat_stats[ $pl ]['total']++;
}

// Définition des colonnes selon la vue
if ( 'phase' === $view_mode ) {
    $columns = [
        ''           => [ 'label' => '📋 Sans phase',     'drop' => true,  'color' => '#95a5a6' ],
        'launch'     => [ 'label' => '🚀 Lancement',      'drop' => true,  'color' => '#3498db' ],
        'pre_event'  => [ 'label' => '📅 Pré-événement',  'drop' => true,  'color' => '#9b59b6' ],
        'during'     => [ 'label' => '🎪 Pendant',        'drop' => true,  'color' => '#e67e22' ],
        'post_event' => [ 'label' => '🏆 Post-événement', 'drop' => true,  'color' => '#27ae60' ],
    ];
    $grouped = [];
    foreach ( $all_posts as $p ) {
        $grouped[ $p['phase'] ?? '' ][] = $p;
    }
} else {
    $columns = [
        'draft'     => [ 'label' => '📝 Brouillon', 'drop' => true,  'color' => '#95a5a6' ],
        'scheduled' => [ 'label' => '⏰ Planifié',   'drop' => true,  'color' => '#f39c12' ],
        'failed'    => [ 'label' => '❌ Échoué',     'drop' => false, 'color' => '#e74c3c' ],
        'published' => [ 'label' => '✅ Publié',     'drop' => false, 'color' => '#27ae60' ],
    ];
    $grouped = [];
    foreach ( $all_posts as $p ) {
        $grouped[ $p['status'] ][] = $p;
    }
}

$status_labels = [ 'draft' => 'Brouillon', 'scheduled' => 'Planifié', 'published' => 'Publié', 'failed' => 'Échoué' ];
$phase_labels  = [ '' => '—', 'launch' => 'Lancement', 'pre_event' => 'Pré-événement', 'during' => 'Pendant', 'post_event' => 'Post-événement', 'auto' => 'Auto' ];
$platform_colors = [ 'facebook' => '#1877f2', 'instagram' => '#e1306c', 'linkedin' => '#0a66c2' ];
?>

<!-- Stats plateformes -->
<div class="fibb-plan-header">
    <div class="fibb-plan-stats">
        <?php foreach ( [ 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn' ] as $plat => $lbl ) :
            $s = $plat_stats[ $plat ] ?? [ 'total' => 0, 'scheduled' => 0, 'published' => 0, 'draft' => 0, 'failed' => 0 ];
        ?>
        <div class="fibb-plan-stat-card" style="border-top:3px solid <?php echo esc_attr( $platform_colors[ $plat ] ); ?>">
            <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( $lbl ); ?></span>
            <div class="fibb-plan-stat-total"><?php echo (int) $s['total']; ?></div>
            <div class="fibb-plan-stat-breakdown">
                <span style="color:#95a5a6;"><?php echo (int) $s['draft']; ?> br.</span>
                <span style="color:#f39c12;"><?php echo (int) $s['scheduled']; ?> plan.</span>
                <span style="color:#27ae60;"><?php echo (int) $s['published']; ?> pub.</span>
                <?php if ( $s['failed'] ) : ?>
                <span style="color:#e74c3c;"><?php echo (int) $s['failed']; ?> éch.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <a href="<?php echo esc_url( $new_url ); ?>" class="fibb-btn fibb-btn-primary">+ Nouveau post</a>
</div>

<!-- Bascule de vue -->
<div class="fibb-plan-view-toggle">
    <a href="<?php echo esc_url( $base_url . '&plan_view=phase' ); ?>"
       class="fibb-toggle-btn<?php echo 'phase' === $view_mode ? ' active' : ''; ?>">📋 Par phase</a>
    <a href="<?php echo esc_url( $base_url . '&plan_view=status' ); ?>"
       class="fibb-toggle-btn<?php echo 'status' === $view_mode ? ' active' : ''; ?>">📊 Par statut</a>
    <span class="fibb-kanban-hint">⟵ Glissez-déposez pour déplacer un post entre colonnes</span>
</div>

<!-- Kanban -->
<div class="fibb-kanban"
     id="fibb-kanban"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_ajax' ) ); ?>"
     data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
     data-view="<?php echo esc_attr( $view_mode ); ?>">

    <?php foreach ( $columns as $col_key => $col ) :
        $cards     = $grouped[ $col_key ] ?? [];
        $col_count = count( $cards );
        $is_drop   = $col['drop'];
    ?>
    <div class="fibb-kanban-col<?php echo $is_drop ? '' : ' fibb-kanban-readonly'; ?>"
         style="--col-color:<?php echo esc_attr( $col['color'] ); ?>"
         data-column="<?php echo esc_attr( $col_key ); ?>">

        <div class="fibb-kanban-col-header">
            <span class="fibb-kanban-col-title"><?php echo esc_html( $col['label'] ); ?></span>
            <span class="fibb-kanban-col-count"><?php echo $col_count; ?></span>
        </div>

        <div class="fibb-kanban-col-body">
            <?php foreach ( $cards as $p ) :
                $plat    = $p['platform'];
                $excerpt = mb_substr( $p['content'], 0, 90 );
                if ( mb_strlen( $p['content'] ) > 90 ) $excerpt .= '…';
                $edit_url = admin_url( 'admin.php?page=fibb-communication&tab=new-post&edit=' . (int) $p['id'] );
                $time     = substr( $p['scheduled_at'], 0, 16 );
            ?>
            <div class="fibb-kanban-card"
                 draggable="<?php echo $is_drop ? 'true' : 'false'; ?>"
                 data-id="<?php echo (int) $p['id']; ?>">
                <div class="fibb-kanban-card-header">
                    <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( ucfirst( $plat ) ); ?></span>
                    <?php if ( 'phase' === $view_mode ) : ?>
                    <span class="fibb-status fibb-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $status_labels[ $p['status'] ] ?? $p['status'] ); ?></span>
                    <?php else : ?>
                    <span class="fibb-kanban-phase-tag"><?php echo esc_html( $phase_labels[ $p['phase'] ?? '' ] ?? '—' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="fibb-kanban-card-time">🕐 <?php echo esc_html( $time ); ?></div>
                <div class="fibb-kanban-card-content"><?php echo esc_html( $excerpt ); ?></div>
                <?php if ( ! empty( $p['image_url'] ) ) : ?>
                <div class="fibb-kanban-card-img-badge">📸 Image</div>
                <?php endif; ?>
                <div class="fibb-kanban-card-footer">
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="fibb-kanban-edit-link">✏️ Éditer</a>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <?php if ( 'failed' === $p['status'] ) : ?>
                        <span class="fibb-kanban-error-hint" title="<?php echo esc_attr( $p['error_message'] ?? '' ); ?>">⚠ Échec</span>
                        <?php endif; ?>
                        <button type="button"
                                class="fibb-kanban-delete-btn<?php echo 'published' === $p['status'] ? ' fibb-kanban-delete-published' : ''; ?>"
                                draggable="false"
                                data-id="<?php echo (int) $p['id']; ?>"
                                data-status="<?php echo esc_attr( $p['status'] ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_delete_ajax_' . (int) $p['id'] ) ); ?>"
                                title="Supprimer ce post"
                                onmousedown="event.stopPropagation()">🗑</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ( $is_drop ) : ?>
            <div class="fibb-kanban-drop-zone">Déposer ici</div>
            <?php endif; ?>
        </div>

        <?php if ( $is_drop ) : ?>
        <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'new-post', 'preset_phase' => $col_key ], admin_url( 'admin.php?page=fibb-communication' ) ) ); ?>"
           class="fibb-kanban-add-card">＋ Ajouter</a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Toast feedback -->
<div id="fibb-kanban-toast" class="fibb-kanban-toast" style="display:none;"></div>

<!-- Templates -->
<div class="fibb-plan-templates" style="margin-top:40px;">
    <div class="fibb-plan-tpl-toggle" onclick="document.getElementById('fibb-tpl-grid').classList.toggle('open')">
        <h3>📚 Bibliothèque de modèles (<?php echo count( FIBB_Comm_Templates::get_all() ); ?> templates)</h3>
        <span class="fibb-tpl-arrow">▼</span>
    </div>

    <div id="fibb-tpl-grid" class="fibb-plan-tpl-grid">
        <?php
        $tpl_phases = [
            'launch'     => '🚀 Lancement',
            'pre_event'  => '📅 Pré-événement',
            'during'     => '🎪 Pendant',
            'post_event' => '🏆 Post-événement',
        ];
        foreach ( $tpl_phases as $phase_key => $phase_label ) :
            $phase_tpls = FIBB_Comm_Templates::get_by_phase( $phase_key );
            if ( empty( $phase_tpls ) ) continue;
        ?>
        <div class="fibb-plan-tpl-section">
            <h4><?php echo esc_html( $phase_label ); ?></h4>
            <div class="fibb-plan-tpl-cards">
                <?php foreach ( $phase_tpls as $slug => $tpl ) :
                    $plat    = $tpl['platform'];
                    $preview = str_replace( '{{edition}}', esc_html( $edition ), $tpl['content'] );
                    $preview = mb_substr( $preview, 0, 130 ) . ( mb_strlen( $preview ) > 130 ? '…' : '' );
                    $use_url = admin_url( 'admin.php?page=fibb-communication&tab=new-post&load_template=' . urlencode( $slug ) );
                ?>
                <div class="fibb-plan-tpl-card">
                    <div class="fibb-plan-tpl-card-header">
                        <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( ucfirst( $plat ) ); ?></span>
                        <code class="fibb-plan-tpl-slug"><?php echo esc_html( $slug ); ?></code>
                    </div>
                    <p class="fibb-plan-tpl-preview"><?php echo esc_html( $preview ); ?></p>
                    <a href="<?php echo esc_url( $use_url ); ?>" class="button button-small">Utiliser →</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
