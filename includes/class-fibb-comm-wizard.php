<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Wizard {

    const TRANSIENT_TTL = 2 * HOUR_IN_SECONDS;

    // ── Transient ──────────────────────────────────────────────────

    private static function transient_key(): string {
        return 'fibb_wizard_' . get_current_user_id();
    }

    public static function get_state(): ?array {
        $state = get_transient( self::transient_key() );
        return is_array( $state ) ? $state : null;
    }

    public static function save_state( array $state ): void {
        set_transient( self::transient_key(), $state, self::TRANSIENT_TTL );
    }

    public static function clear_state(): void {
        delete_transient( self::transient_key() );
    }

    // ── Traitement des étapes ──────────────────────────────────────

    /**
     * Valide et enrichit la config de l'étape 1.
     * Pré-remplit la sélection des templates selon les canaux choisis.
     */
    public static function process_step1( array $raw, array $existing_state = [] ): array {
        $edition  = absint( $raw['edition'] ?? 1 );
        $date     = sanitize_text_field( $raw['date'] ?? '' );
        $channels = array_map( 'sanitize_key', (array) ( $raw['channels'] ?? [] ) );
        $channels = array_values( array_intersect( $channels, [ 'facebook', 'instagram', 'linkedin' ] ) );

        // Mots-clés : string CSV → tableau nettoyé
        $kw_raw  = sanitize_text_field( $raw['keywords'] ?? '' );
        $keywords = array_values( array_filter( array_map( 'trim', explode( ',', $kw_raw ) ) ) );

        $settings      = get_option( FIBB_COMM_OPTION, [] );
        $seo_hashtags  = [
            'facebook'  => sanitize_text_field( $raw['seo_hashtags_facebook']  ?? ( $settings['hashtags_facebook']  ?? '#FIBB #bridge #Bordeaux' ) ),
            'instagram' => sanitize_text_field( $raw['seo_hashtags_instagram'] ?? ( $settings['hashtags_instagram'] ?? '#FIBB #bridge #Bordeaux' ) ),
            'linkedin'  => sanitize_text_field( $raw['seo_hashtags_linkedin']  ?? ( $settings['hashtags_linkedin']  ?? '#bridge #bordeaux #fibb' ) ),
        ];

        // Pré-remplir la sélection : true si platform ∈ channels
        $all_templates = FIBB_Comm_Templates::get_all();
        $prev_selection = $existing_state['selection'] ?? [];
        $selection = [];
        foreach ( $all_templates as $slug => $tpl ) {
            // Conserver la sélection précédente si déjà définie, sinon auto
            if ( isset( $prev_selection[ $slug ] ) ) {
                $selection[ $slug ] = (bool) $prev_selection[ $slug ];
            } else {
                $selection[ $slug ] = in_array( $tpl['platform'], $channels, true );
            }
        }

        return [
            'edition'      => $edition,
            'date'         => $date,
            'channels'     => $channels,
            'keywords'     => $keywords,
            'seo_hashtags' => $seo_hashtags,
            'selection'    => $selection,
        ];
    }

    /**
     * Met à jour la sélection des templates (étape 2).
     * $raw['selection'] = ['slug1' => true, 'slug2' => false, ...]
     */
    public static function process_step2( array $raw, array $state ): array {
        $new_selection = (array) ( $raw['selection'] ?? [] );
        $all_slugs     = array_keys( FIBB_Comm_Templates::get_all() );
        $selection     = $state['selection'] ?? [];
        foreach ( $all_slugs as $slug ) {
            // Si le slug est fourni dans raw, utiliser sa valeur ; sinon garder l'existant
            if ( array_key_exists( $slug, $new_selection ) ) {
                $selection[ $slug ] = (bool) $new_selection[ $slug ];
            }
        }
        $state['selection'] = $selection;
        return $state;
    }

    /**
     * Met à jour les overrides de dates (étape 3).
     * $raw['dates'] = ['slug' => 'YYYY-MM-DDTHH:MM', ...]
     */
    public static function process_step3( array $raw, array $state ): array {
        $date_overrides = (array) ( $raw['dates'] ?? [] );
        $settings       = get_option( FIBB_COMM_OPTION, [] );
        $tz_id          = $settings['festival_timezone'] ?? 'Europe/Paris';
        $tz             = new DateTimeZone( $tz_id );
        $utc            = new DateTimeZone( 'UTC' );

        $state['dates'] = $state['dates'] ?? [];
        foreach ( $date_overrides as $slug => $local_dt ) {
            $slug     = sanitize_key( $slug );
            $local_dt = sanitize_text_field( $local_dt );
            if ( $local_dt === '' ) {
                unset( $state['dates'][ $slug ] ); // retour à la date auto
                continue;
            }
            // Convertir datetime-local (local) → UTC
            try {
                $dt = new DateTime( $local_dt, $tz );
                $dt->setTimezone( $utc );
                $state['dates'][ $slug ] = $dt->format( 'Y-m-d H:i:s' );
            } catch ( Exception $e ) {
                // Date invalide → ignorer
            }
        }
        return $state;
    }

    // ── Activation ─────────────────────────────────────────────────

    /**
     * Crée tous les posts sélectionnés.
     * Retourne ['created' => int, 'errors' => array].
     */
    public static function activate( array $state ): array {
        $config    = $state['config'] ?? [];
        $selection = $state['selection'] ?? [];
        $date_ovr  = $state['dates'] ?? [];

        $festival_date = $config['date'] ?? '';
        $edition       = (string) ( $config['edition'] ?? '' );
        $settings      = get_option( FIBB_COMM_OPTION, [] );

        $created = 0;
        $errors  = [];

        foreach ( $selection as $slug => $selected ) {
            if ( ! $selected ) continue;

            $tpl = FIBB_Comm_Templates::get_by_slug( $slug );
            if ( ! $tpl ) {
                $errors[] = "Template introuvable : {$slug}";
                continue;
            }

            $hashtags     = FIBB_Comm_Templates::hashtags_for( $tpl['platform'], $settings );
            $scheduled_at = self::compute_scheduled_at( $slug, $festival_date, $date_ovr );
            $content      = self::resolve_tokens( $tpl['content'], $edition, $festival_date, $tpl['platform'], $settings );

            $post_id = FIBB_Comm_DB::insert_post( [
                'platform'      => $tpl['platform'],
                'content'       => $content,
                'image_url'     => $tpl['image_url'] ?? null,
                'phase'         => $tpl['phase'],
                'template_slug' => $slug,
                'scheduled_at'  => $scheduled_at,
                'status'        => 'draft',
                'created_by'    => get_current_user_id(),
            ] );

            if ( $post_id ) {
                $created++;
            } else {
                $errors[] = "Échec d'insertion pour : {$slug}";
            }
        }

        self::clear_state();
        return [ 'created' => $created, 'errors' => $errors ];
    }

    // ── Utilitaires ────────────────────────────────────────────────

    /**
     * Calcule la date planifiée pour un slug.
     * Utilise l'override si défini, sinon festival_date + offset du template.
     */
    public static function compute_scheduled_at( string $slug, string $festival_date_ymd, array $overrides ): string {
        if ( isset( $overrides[ $slug ] ) ) {
            return $overrides[ $slug ];
        }
        $tpl    = FIBB_Comm_Templates::get_by_slug( $slug );
        $offset = (int) ( $tpl['offset'] ?? 0 );
        $ts     = strtotime( $festival_date_ymd ) + $offset * DAY_IN_SECONDS;
        return gmdate( 'Y-m-d', $ts ) . ' 09:00:00';
    }

    /**
     * Remplace les tokens {{edition}}, {{festival_date}}, {{hashtags}} dans le contenu.
     */
    public static function resolve_tokens( string $content, string $edition, string $festival_date_ymd, string $platform, array $settings ): string {
        $hashtags = FIBB_Comm_Templates::hashtags_for( $platform, $settings );
        $date_fr  = $festival_date_ymd !== '' ? gmdate( 'd/m/Y', strtotime( $festival_date_ymd ) ) : '';
        return str_replace(
            [ '{{edition}}', '{{festival_date}}', '{{hashtags}}' ],
            [ $edition, $date_fr, $hashtags ],
            $content
        );
    }

    /**
     * Construit le résumé pour l'étape 4.
     */
    public static function build_summary( array $state ): array {
        $config    = $state['config'] ?? [];
        $selection = $state['selection'] ?? [];
        $date_ovr  = $state['dates'] ?? [];
        $edition   = (string) ( $config['edition'] ?? '' );
        $fest_date = $config['date'] ?? '';
        $keywords  = $config['keywords'] ?? [];
        $settings  = get_option( FIBB_COMM_OPTION, [] );

        $total      = 0;
        $by_phase   = [];
        $by_channel = [];
        $posts      = [];

        foreach ( $selection as $slug => $selected ) {
            if ( ! $selected ) continue;
            $tpl = FIBB_Comm_Templates::get_by_slug( $slug );
            if ( ! $tpl ) continue;

            $total++;
            $phase    = $tpl['phase'];
            $platform = $tpl['platform'];
            $by_phase[ $phase ]     = ( $by_phase[ $phase ] ?? 0 ) + 1;
            $by_channel[ $platform ] = ( $by_channel[ $platform ] ?? 0 ) + 1;

            $content      = self::resolve_tokens( $tpl['content'], $edition, $fest_date, $platform, $settings );
            $scheduled_at = self::compute_scheduled_at( $slug, $fest_date, $date_ovr );
            $hashtags     = FIBB_Comm_Templates::hashtags_for( $platform, $settings );
            $seo_score    = FIBB_Comm_SEO::score( $content, $platform, $keywords, $hashtags );

            $posts[] = [
                'slug'          => $slug,
                'phase'         => $phase,
                'platform'      => $platform,
                'content'       => $content,
                'image_url'     => $tpl['image_url'] ?? '',
                'scheduled_at'  => $scheduled_at,
                'seo_score'     => $seo_score,
                'date_override' => isset( $date_ovr[ $slug ] ),
            ];
        }

        // Tri par date planifiée
        usort( $posts, fn( $a, $b ) => strcmp( $a['scheduled_at'], $b['scheduled_at'] ) );

        $avg_seo = $total > 0
            ? (int) round( array_sum( array_column( $posts, 'seo_score' ) ) / $total )
            : 0;

        return [
            'total'      => $total,
            'by_phase'   => $by_phase,
            'by_channel' => $by_channel,
            'avg_seo'    => $avg_seo,
            'posts'      => $posts,
        ];
    }

    /**
     * Vérifie si des doublons existent déjà en base pour les slugs sélectionnés.
     */
    public static function check_duplicates( array $selection ): array {
        global $wpdb;
        $selected_slugs = array_keys( array_filter( $selection ) );
        if ( empty( $selected_slugs ) ) return [];

        $placeholders = implode( ',', array_fill( 0, count( $selected_slugs ), '%s' ) );
        $table        = $wpdb->prefix . 'fibb_comm_posts';
        $results      = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT template_slug FROM {$table} WHERE template_slug IN ({$placeholders})",
                ...$selected_slugs
            )
        );
        return array_unique( $results );
    }
}
