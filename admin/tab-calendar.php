<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Params ──────────────────────────────────────────────────────
$valid_views = [ 'year', 'month', 'week' ];
$view_param  = ( isset( $_GET['view'] ) && in_array( $_GET['view'], $valid_views, true ) ) ? sanitize_key( $_GET['view'] ) : 'month';
$month_param = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : gmdate( 'Y-m' );
if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_param ) ) {
    $month_param = gmdate( 'Y-m' );
}
$year  = (int) substr( $month_param, 0, 4 );
$month = (int) substr( $month_param, 5, 2 );
$today = gmdate( 'Y-m-d' );

$settings      = get_option( FIBB_COMM_OPTION, [] );
$festival_date = $settings['festival_date'] ?? '';
$edition       = $settings['festival_edition'] ?? '6';

$base_url       = admin_url( 'admin.php?page=fibb-communication&tab=calendar' );
$settings_url   = admin_url( 'admin.php?page=fibb-communication&tab=settings' );
$platform_icons = [ 'facebook' => '🔵', 'instagram' => '📸', 'linkedin' => '🔷' ];

$fr_months = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
?>

<?php
// Calcul du lundi de la semaine courante pour le lien vue semaine
$week_monday_default = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
$week_url_param      = isset( $_GET['week'] ) ? sanitize_text_field( wp_unslash( $_GET['week'] ) ) : $week_monday_default;
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week_url_param ) ) {
    $week_url_param = $week_monday_default;
}
?>
<!-- Sélecteur de vue -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <a href="<?php echo esc_url( $base_url . '&view=year&month=' . $month_param ); ?>"
       class="button<?php echo 'year' === $view_param ? ' button-primary' : ''; ?>">📆 Année</a>
    <a href="<?php echo esc_url( $base_url . '&view=month&month=' . $month_param ); ?>"
       class="button<?php echo 'month' === $view_param ? ' button-primary' : ''; ?>">📅 Mois</a>
    <a href="<?php echo esc_url( $base_url . '&view=week&week=' . $week_url_param ); ?>"
       class="button<?php echo 'week' === $view_param ? ' button-primary' : ''; ?>">📋 Semaine</a>
</div>

<!-- Bouton import plan festival -->
<?php if ( $festival_date ) : ?>
<div class="fibb-import-btn-wrap">
    <div>
        <strong>Plan FIBB <?php echo esc_html( $edition ); ?>e édition</strong>
        <p>Importer les <?php echo count( FIBB_Comm_Templates::get_all() ); ?> modèles aux dates calculées depuis le <?php echo esc_html( gmdate( 'd/m/Y', strtotime( $festival_date ) ) ); ?>.</p>
    </div>
    <form method="post" action="" style="margin:0;">
        <?php wp_nonce_field( 'fibb_comm_import_plan', 'fibb_import_plan_nonce' ); ?>
        <input type="hidden" name="fibb_comm_action" value="import_plan">
        <button type="submit" class="fibb-btn fibb-btn-primary"
                onclick="return confirm('Importer le plan de communication pour la <?php echo esc_js( $edition ); ?>e édition ?\n\nCela créera <?php echo esc_js( count( FIBB_Comm_Templates::get_all() ) ); ?> posts en statut brouillon.');">
            📅 Charger le plan <?php echo esc_html( $edition ); ?>e édition
        </button>
    </form>
</div>
<?php else : ?>
<div class="notice notice-warning inline">
    <p>⚠️ Configurez la date du festival dans <a href="<?php echo esc_url( $settings_url ); ?>">Paramètres</a> pour activer le bouton d'import du plan.</p>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════════
// VUE ANNÉE
// ════════════════════════════════════════════════════════════════
if ( 'year' === $view_param ) :

    $prev_year_param = ( $year - 1 ) . '-' . sprintf( '%02d', $month );
    $next_year_param = ( $year + 1 ) . '-' . sprintf( '%02d', $month );

    $year_from  = sprintf( '%04d-01-01 00:00:00', $year );
    $year_to    = sprintf( '%04d-12-31 23:59:59', $year );
    $year_posts = FIBB_Comm_DB::get_posts_for_calendar( $year_from, $year_to );

    $bridge          = new FIBB_Comm_Newsletter_Bridge();
    $newsletter_evts = $bridge->get_newsletter_events_for_calendar();

    $year_by_date = [];
    foreach ( $year_posts as $p ) {
        $date = substr( $p['scheduled_at'], 0, 10 );
        $year_by_date[ $date ][] = [ 'type' => 'social', 'platform' => $p['platform'], 'status' => $p['status'] ];
    }
    foreach ( $newsletter_evts as $evt ) {
        if ( substr( $evt['date'], 0, 4 ) === (string) $year ) {
            $year_by_date[ $evt['date'] ][] = [ 'type' => 'email' ];
        }
    }

    $year_stats = [ 'scheduled' => 0, 'published' => 0, 'failed' => 0, 'draft' => 0 ];
    foreach ( $year_posts as $p ) {
        if ( isset( $year_stats[ $p['status'] ] ) ) $year_stats[ $p['status'] ]++;
    }
?>

<!-- Navigation année -->
<div class="fibb-cal-nav">
    <a href="<?php echo esc_url( $base_url . '&view=year&month=' . $prev_year_param ); ?>" class="button">← <?php echo $year - 1; ?></a>
    <h2><?php echo $year; ?></h2>
    <a href="<?php echo esc_url( $base_url . '&view=year&month=' . $next_year_param ); ?>" class="button"><?php echo $year + 1; ?> →</a>
</div>

<!-- Compteurs année -->
<div class="fibb-stats-bar">
    <span class="fibb-stat fibb-stat-total"><?php echo count( $year_posts ); ?> posts</span>
    <span class="fibb-stat fibb-stat-scheduled"><?php echo $year_stats['scheduled']; ?> planifiés</span>
    <span class="fibb-stat fibb-stat-published"><?php echo $year_stats['published']; ?> publiés</span>
    <span class="fibb-stat fibb-stat-failed"><?php echo $year_stats['failed']; ?> échoués</span>
    <span class="fibb-stat fibb-stat-draft"><?php echo $year_stats['draft']; ?> brouillons</span>
</div>

<!-- Grille 12 mois -->
<div class="fibb-year-grid">
<?php for ( $m = 1; $m <= 12; $m++ ) :
    $m_first     = mktime( 0, 0, 0, $m, 1, $year );
    $m_days      = (int) gmdate( 'j', mktime( 23, 59, 59, $m + 1, 0, $year ) );
    $m_start_dow = (int) gmdate( 'N', $m_first );
    $m_param     = sprintf( '%04d-%02d', $year, $m );
    $month_url   = esc_url( $base_url . '&view=month&month=' . $m_param );
?>
    <div class="fibb-mini-month">
        <a href="<?php echo $month_url; ?>" class="fibb-mini-month-title"><?php echo esc_html( $fr_months[ $m ] ); ?></a>
        <div class="fibb-mini-cal">
            <?php foreach ( [ 'L','M','M','J','V','S','D' ] as $hd ) : ?>
                <span class="fibb-mini-dow"><?php echo $hd; ?></span>
            <?php endforeach; ?>

            <?php for ( $blank = 1; $blank < $m_start_dow; $blank++ ) : ?>
                <span class="fibb-mini-day"></span>
            <?php endfor; ?>

            <?php for ( $day = 1; $day <= $m_days; $day++ ) :
                $d_str  = sprintf( '%04d-%02d-%02d', $year, $m, $day );
                $d_evts = $year_by_date[ $d_str ] ?? [];
                $is_td  = ( $d_str === $today );
            ?>
                <a href="<?php echo esc_url( $base_url . '&view=month&month=' . $m_param . '#day-' . $day ); ?>"
                   class="fibb-mini-day<?php echo $is_td ? ' is-today' : ''; echo ! empty( $d_evts ) ? ' has-events' : ''; ?>"
                   title="<?php echo esc_attr( count( $d_evts ) . ' post(s) le ' . $d_str ); ?>">
                    <span class="fibb-mini-day-num"><?php echo $day; ?></span>
                    <?php if ( ! empty( $d_evts ) ) : ?>
                    <span class="fibb-mini-dots">
                        <?php foreach ( $d_evts as $ev ) :
                            $dc = ( 'email' === $ev['type'] ) ? 'dot-email' : 'dot-' . $ev['platform'];
                        ?>
                            <span class="fibb-dot <?php echo esc_attr( $dc ); ?>"></span>
                        <?php endforeach; ?>
                    </span>
                    <?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
<?php endfor; ?>
</div>

<?php
// ════════════════════════════════════════════════════════════════
// VUE MOIS
// ════════════════════════════════════════════════════════════════
elseif ( 'month' === $view_param ) :

    $first_day = mktime( 0, 0, 0, $month, 1, $year );
    $last_day  = mktime( 23, 59, 59, $month + 1, 0, $year );
    $days_in   = (int) gmdate( 'j', $last_day );
    $start_dow = (int) gmdate( 'N', $first_day );
    $prev_month = gmdate( 'Y-m', mktime( 0, 0, 0, $month - 1, 1, $year ) );
    $next_month = gmdate( 'Y-m', mktime( 0, 0, 0, $month + 1, 1, $year ) );

    $from  = gmdate( 'Y-m-d', $first_day ) . ' 00:00:00';
    $to    = gmdate( 'Y-m-d', $last_day ) . ' 23:59:59';
    $posts = FIBB_Comm_DB::get_posts_for_calendar( $from, $to );

    $bridge          = new FIBB_Comm_Newsletter_Bridge();
    $newsletter_evts = $bridge->get_newsletter_events_for_calendar();

    $events_by_date = [];
    foreach ( $posts as $p ) {
        $date = substr( $p['scheduled_at'], 0, 10 );
        $events_by_date[ $date ][] = [ 'type' => 'social', 'post' => $p ];
    }
    foreach ( $newsletter_evts as $evt ) {
        if ( substr( $evt['date'], 0, 7 ) === $month_param ) {
            $events_by_date[ $evt['date'] ][] = [ 'type' => 'email', 'evt' => $evt ];
        }
    }

    $month_stats = [ 'scheduled' => 0, 'published' => 0, 'failed' => 0, 'draft' => 0 ];
    foreach ( $posts as $p ) {
        if ( isset( $month_stats[ $p['status'] ] ) ) $month_stats[ $p['status'] ]++;
    }
?>

<!-- Navigation mois -->
<div class="fibb-cal-nav">
    <a href="<?php echo esc_url( $base_url . '&view=month&month=' . $prev_month ); ?>" class="button">← <?php echo esc_html( gmdate( 'M Y', mktime( 0, 0, 0, $month - 1, 1, $year ) ) ); ?></a>
    <h2><?php echo esc_html( $fr_months[ $month ] . ' ' . $year ); ?></h2>
    <a href="<?php echo esc_url( $base_url . '&view=month&month=' . $next_month ); ?>" class="button"><?php echo esc_html( gmdate( 'M Y', mktime( 0, 0, 0, $month + 1, 1, $year ) ) ); ?> →</a>
    <a href="<?php echo esc_url( $base_url . '&view=year&month=' . $month_param ); ?>" class="button" style="margin-left:auto;">📆 Vue année</a>
</div>

<!-- Compteurs du mois -->
<div class="fibb-stats-bar">
    <span class="fibb-stat fibb-stat-total"><?php echo count( $posts ); ?> posts</span>
    <span class="fibb-stat fibb-stat-scheduled"><?php echo $month_stats['scheduled']; ?> planifiés</span>
    <span class="fibb-stat fibb-stat-published"><?php echo $month_stats['published']; ?> publiés</span>
    <span class="fibb-stat fibb-stat-failed"><?php echo $month_stats['failed']; ?> échoués</span>
    <span class="fibb-stat fibb-stat-draft"><?php echo $month_stats['draft']; ?> brouillons</span>
</div>

<!-- Filtres plateforme -->
<div class="fibb-filters">
    <strong>Filtrer :</strong>
    <?php foreach ( [ 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'email' => 'Email' ] as $plat => $lbl ) : ?>
    <label class="fibb-filter-label">
        <input type="checkbox" class="fibb-filter-cb" value="<?php echo esc_attr( $plat ); ?>" checked>
        <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( $lbl ); ?></span>
    </label>
    <?php endforeach; ?>
</div>

<!-- Calendrier -->
<div class="fibb-calendar-wrap">
    <div class="fibb-calendar-header">
        <?php foreach ( [ 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' ] as $d ) : ?>
            <span><?php echo esc_html( $d ); ?></span>
        <?php endforeach; ?>
    </div>
    <div class="fibb-calendar" id="fibb-month-calendar">
        <?php
        for ( $blank = 1; $blank < $start_dow; $blank++ ) {
            echo '<div class="fibb-day other-month"></div>';
        }
        for ( $day = 1; $day <= $days_in; $day++ ) :
            $date_str   = sprintf( '%04d-%02d-%02d', $year, $month, $day );
            $is_today   = ( $date_str === $today );
            $day_events = $events_by_date[ $date_str ] ?? [];
        ?>
        <div class="fibb-day<?php echo $is_today ? ' today' : ''; ?>" id="day-<?php echo $day; ?>">
            <span class="fibb-day-num"><?php echo esc_html( $day ); ?></span>
            <?php foreach ( $day_events as $ev ) :
                if ( 'email' === $ev['type'] ) : ?>
                <span class="fibb-event fibb-event-email fibb-filterable" data-platform="email"
                      title="<?php echo esc_attr( $ev['evt']['label'] ); ?>">
                    ✉ <?php echo esc_html( $ev['evt']['label'] ); ?>
                </span>
                <?php else :
                    $p     = $ev['post'];
                    $plat  = $p['platform'];
                    $icon  = $platform_icons[ $plat ] ?? '•';
                    $cls   = ( 'draft' === $p['status'] ) ? 'fibb-event-draft' : "fibb-event-{$plat}";
                    $label = $icon . ' ' . mb_substr( $p['content'], 0, 35 ) . ( mb_strlen( $p['content'] ) > 35 ? '…' : '' );
                    $can_delete = in_array( $p['status'], [ 'scheduled', 'draft', 'failed' ], true );
                ?>
                <a href="#" class="fibb-event <?php echo esc_attr( $cls ); ?> fibb-filterable"
                   data-platform="<?php echo esc_attr( $plat ); ?>"
                   data-id="<?php echo (int) $p['id']; ?>"
                   data-content="<?php echo esc_attr( $p['content'] ); ?>"
                   data-status="<?php echo esc_attr( $p['status'] ); ?>"
                   data-date="<?php echo esc_attr( substr( $p['scheduled_at'], 0, 16 ) ); ?>"
                   data-image="<?php echo esc_attr( $p['image_url'] ?? '' ); ?>"
                   data-can-delete="<?php echo $can_delete ? '1' : '0'; ?>"
                   data-can-retry="<?php echo ( 'failed' === $p['status'] ) ? '1' : '0'; ?>"
                   data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_delete_' . (int) $p['id'] ) ); ?>"
                   data-retry-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_retry_' . (int) $p['id'] ) ); ?>"
                   data-edit-url="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=new-post&edit=' . (int) $p['id'] ) ); ?>"
                   onclick="fibbOpenPopup(this);return false;">
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endfor;
        $end_dow = (int) gmdate( 'N', $last_day );
        for ( $blank = $end_dow + 1; $blank <= 7; $blank++ ) {
            echo '<div class="fibb-day other-month"></div>';
        }
        ?>
    </div>
</div>

<!-- Liste des posts du mois -->
<?php if ( ! empty( $posts ) ) : ?>
<h3 style="margin-top:32px;">Posts de <?php echo esc_html( $fr_months[ $month ] . ' ' . $year ); ?></h3>
<table class="wp-list-table widefat fixed striped fibb-log-table">
    <thead>
        <tr>
            <th>Date / Heure</th>
            <th>Plateforme</th>
            <th>Statut</th>
            <th>Phase</th>
            <th>Contenu</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $posts as $p ) : ?>
        <tr>
            <td><?php echo esc_html( substr( $p['scheduled_at'], 0, 16 ) ); ?></td>
            <td><span class="fibb-badge fibb-badge-<?php echo esc_attr( $p['platform'] ); ?>"><?php echo esc_html( $p['platform'] ); ?></span></td>
            <td><span class="fibb-status fibb-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $p['status'] ); ?></span></td>
            <td><?php echo esc_html( $p['phase'] ?? '—' ); ?></td>
            <td><?php echo esc_html( mb_substr( $p['content'], 0, 80 ) . ( mb_strlen( $p['content'] ) > 80 ? '…' : '' ) ); ?></td>
            <td style="white-space:nowrap;">
                <?php if ( 'failed' === $p['status'] ) : ?>
                <form method="post" action="" style="display:inline;">
                    <?php wp_nonce_field( 'fibb_comm_retry_' . $p['id'], 'fibb_retry_nonce' ); ?>
                    <input type="hidden" name="fibb_comm_action" value="retry_post">
                    <input type="hidden" name="post_id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit" class="button button-small" style="color:#fff;background:#f39c12;border-color:#e67e22;">🔄 Republier</button>
                </form>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=new-post&edit=' . (int) $p['id'] ) ); ?>"
                   class="button button-small" style="margin-left:4px;">✏️ Éditer</a>
                <?php endif; ?>
                <?php if ( in_array( $p['status'], [ 'scheduled', 'draft', 'failed' ], true ) ) : ?>
                <form method="post" action="" style="display:inline;margin-left:4px;">
                    <?php wp_nonce_field( 'fibb_comm_delete_' . $p['id'], 'fibb_delete_nonce' ); ?>
                    <input type="hidden" name="fibb_comm_action" value="delete_post">
                    <input type="hidden" name="post_id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit" class="button button-small"
                            onclick="return confirm('Supprimer ce post ?');">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════════
// VUE SEMAINE
// ════════════════════════════════════════════════════════════════
else :

    $week_start  = strtotime( $week_url_param ); // Monday
    $week_end    = strtotime( '+6 days', $week_start ); // Sunday
    $prev_week   = gmdate( 'Y-m-d', strtotime( '-7 days', $week_start ) );
    $next_week   = gmdate( 'Y-m-d', strtotime( '+7 days', $week_start ) );
    $week_from   = gmdate( 'Y-m-d', $week_start ) . ' 00:00:00';
    $week_to     = gmdate( 'Y-m-d', $week_end ) . ' 23:59:59';
    $week_posts  = FIBB_Comm_DB::get_posts_for_calendar( $week_from, $week_to );

    $bridge          = new FIBB_Comm_Newsletter_Bridge();
    $newsletter_evts = $bridge->get_newsletter_events_for_calendar();

    $week_by_date = [];
    foreach ( $week_posts as $p ) {
        $date = substr( $p['scheduled_at'], 0, 10 );
        $week_by_date[ $date ][] = [ 'type' => 'social', 'post' => $p ];
    }
    foreach ( $newsletter_evts as $evt ) {
        $evt_ts = strtotime( $evt['date'] );
        if ( $evt_ts >= $week_start && $evt_ts <= $week_end ) {
            $week_by_date[ $evt['date'] ][] = [ 'type' => 'email', 'evt' => $evt ];
        }
    }

    $week_stats = [ 'scheduled' => 0, 'published' => 0, 'failed' => 0, 'draft' => 0 ];
    foreach ( $week_posts as $p ) {
        if ( isset( $week_stats[ $p['status'] ] ) ) $week_stats[ $p['status'] ]++;
    }

    $fr_days = [ 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche' ];
    $week_month_param = gmdate( 'Y-m', $week_start );
?>

<!-- Navigation semaine -->
<div class="fibb-cal-nav">
    <a href="<?php echo esc_url( $base_url . '&view=week&week=' . $prev_week ); ?>" class="button">← Semaine précédente</a>
    <h2>Semaine du <?php echo esc_html( gmdate( 'd', $week_start ) ); ?> au <?php echo esc_html( gmdate( 'd/m/Y', $week_end ) ); ?></h2>
    <a href="<?php echo esc_url( $base_url . '&view=week&week=' . $next_week ); ?>" class="button">Semaine suivante →</a>
    <a href="<?php echo esc_url( $base_url . '&view=month&month=' . $week_month_param ); ?>" class="button" style="margin-left:auto;">📅 Vue mois</a>
</div>

<!-- Compteurs semaine -->
<div class="fibb-stats-bar">
    <span class="fibb-stat fibb-stat-total"><?php echo count( $week_posts ); ?> posts</span>
    <span class="fibb-stat fibb-stat-scheduled"><?php echo $week_stats['scheduled']; ?> planifiés</span>
    <span class="fibb-stat fibb-stat-published"><?php echo $week_stats['published']; ?> publiés</span>
    <span class="fibb-stat fibb-stat-failed"><?php echo $week_stats['failed']; ?> échoués</span>
    <span class="fibb-stat fibb-stat-draft"><?php echo $week_stats['draft']; ?> brouillons</span>
</div>

<!-- Filtres plateforme -->
<div class="fibb-filters">
    <strong>Filtrer :</strong>
    <?php foreach ( [ 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'email' => 'Email' ] as $plat => $lbl ) : ?>
    <label class="fibb-filter-label">
        <input type="checkbox" class="fibb-filter-cb" value="<?php echo esc_attr( $plat ); ?>" checked>
        <span class="fibb-badge fibb-badge-<?php echo esc_attr( $plat ); ?>"><?php echo esc_html( $lbl ); ?></span>
    </label>
    <?php endforeach; ?>
</div>

<!-- Grille semaine -->
<div class="fibb-week-grid">
    <?php for ( $i = 0; $i <= 6; $i++ ) :
        $day_ts   = strtotime( "+{$i} days", $week_start );
        $day_str  = gmdate( 'Y-m-d', $day_ts );
        $is_today = ( $day_str === $today );
        $day_evts = $week_by_date[ $day_str ] ?? [];
        $dow_num  = (int) gmdate( 'N', $day_ts );
    ?>
    <div class="fibb-week-day<?php echo $is_today ? ' today' : ''; ?>">
        <div class="fibb-week-day-header<?php echo $is_today ? ' today' : ''; ?>">
            <span class="fibb-week-day-name"><?php echo esc_html( $fr_days[ $dow_num ] ); ?></span>
            <span class="fibb-week-day-num"><?php echo esc_html( gmdate( 'd', $day_ts ) ); ?></span>
        </div>
        <div class="fibb-week-day-events">
            <?php if ( empty( $day_evts ) ) : ?>
                <span style="font-size:11px;color:#ccc;display:block;text-align:center;padding:12px 0;">—</span>
            <?php endif; ?>
            <?php foreach ( $day_evts as $ev ) :
                if ( 'email' === $ev['type'] ) : ?>
                <span class="fibb-event fibb-event-email fibb-filterable" data-platform="email"
                      title="<?php echo esc_attr( $ev['evt']['label'] ); ?>">
                    ✉ <?php echo esc_html( $ev['evt']['label'] ); ?>
                </span>
                <?php else :
                    $p     = $ev['post'];
                    $plat  = $p['platform'];
                    $icon  = $platform_icons[ $plat ] ?? '•';
                    $cls   = ( 'draft' === $p['status'] ) ? 'fibb-event-draft' : "fibb-event-{$plat}";
                    $label = $icon . ' ' . mb_substr( $p['content'], 0, 60 ) . ( mb_strlen( $p['content'] ) > 60 ? '…' : '' );
                    $time  = substr( $p['scheduled_at'], 11, 5 );
                    $can_delete = in_array( $p['status'], [ 'scheduled', 'draft', 'failed' ], true );
                ?>
                <a href="#" class="fibb-event fibb-week-event <?php echo esc_attr( $cls ); ?> fibb-filterable"
                   data-platform="<?php echo esc_attr( $plat ); ?>"
                   data-id="<?php echo (int) $p['id']; ?>"
                   data-content="<?php echo esc_attr( $p['content'] ); ?>"
                   data-status="<?php echo esc_attr( $p['status'] ); ?>"
                   data-date="<?php echo esc_attr( substr( $p['scheduled_at'], 0, 16 ) ); ?>"
                   data-image="<?php echo esc_attr( $p['image_url'] ?? '' ); ?>"
                   data-can-delete="<?php echo $can_delete ? '1' : '0'; ?>"
                   data-can-retry="<?php echo ( 'failed' === $p['status'] ) ? '1' : '0'; ?>"
                   data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_delete_' . (int) $p['id'] ) ); ?>"
                   data-retry-nonce="<?php echo esc_attr( wp_create_nonce( 'fibb_comm_retry_' . (int) $p['id'] ) ); ?>"
                   data-edit-url="<?php echo esc_url( admin_url( 'admin.php?page=fibb-communication&tab=new-post&edit=' . (int) $p['id'] ) ); ?>"
                   onclick="fibbOpenPopup(this);return false;">
                    <span class="fibb-week-event-time"><?php echo esc_html( $time ); ?></span>
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endfor; ?>
</div>

<?php endif; // fin vue mois/semaine ?>

<!-- ── Popup de prévisualisation ────────────────────────────── -->
<div id="fibb-popup-overlay" onclick="fibbClosePopup()"></div>
<div id="fibb-popup" role="dialog" aria-modal="true" aria-label="Détails du post">
    <div class="fibb-popup-header">
        <div id="fibb-popup-badges" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;"></div>
        <button class="fibb-popup-close-btn" onclick="fibbClosePopup()" aria-label="Fermer">✕</button>
    </div>
    <div id="fibb-popup-date" class="fibb-popup-date"></div>
    <div id="fibb-popup-content" class="fibb-popup-content"></div>
    <img id="fibb-popup-image" src="" alt="" class="fibb-popup-image">
    <div id="fibb-popup-actions" class="fibb-popup-actions"></div>
</div>

<!-- Formulaire caché pour delete depuis popup -->
<form id="fibb-popup-delete-form" method="post" action="" style="display:none;">
    <input type="hidden" name="fibb_comm_action" value="delete_post">
    <input type="hidden" id="fibb-popup-delete-id" name="post_id" value="">
    <input type="hidden" id="fibb-popup-delete-nonce" name="fibb_delete_nonce" value="">
</form>
<!-- Formulaire caché pour retry depuis popup -->
<form id="fibb-popup-retry-form" method="post" action="" style="display:none;">
    <input type="hidden" name="fibb_comm_action" value="retry_post">
    <input type="hidden" id="fibb-popup-retry-id" name="post_id" value="">
    <input type="hidden" id="fibb-popup-retry-nonce" name="fibb_retry_nonce" value="">
</form>
