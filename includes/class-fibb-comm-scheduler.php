<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Scheduler {

    const CRON_HOOK    = 'fibb_comm_dispatch_scheduled';
    const IG_CRON_HOOK = 'fibb_ig_queue_dispatch';

    public static function register_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'every_15_minutes', self::CRON_HOOK );
        }
        if ( ! wp_next_scheduled( self::IG_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'every_15_minutes', self::IG_CRON_HOOK );
        }
    }

    public static function unregister_cron(): void {
        foreach ( [ self::CRON_HOOK, self::IG_CRON_HOOK ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) {
                wp_unschedule_event( $ts, $hook );
            }
        }
    }

    private static function resolve_tokens( array $post ): array {
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $platform = $post['platform'];

        $hashtags_key = 'hashtags_' . $platform;
        $hashtags     = $settings[ $hashtags_key ] ?? '#FIBB #bridge #Bordeaux';
        $edition      = $settings['festival_edition'] ?? '6';
        $festival_date = isset( $settings['festival_date'] ) ? gmdate( 'd/m/Y', strtotime( $settings['festival_date'] ) ) : '';

        $replacements = [
            '{{hashtags}}'      => $hashtags,
            '{{edition}}'       => $edition,
            '{{festival_date}}' => $festival_date,
        ];

        $post['content'] = str_replace( array_keys( $replacements ), array_values( $replacements ), $post['content'] );

        return $post;
    }

    public static function dispatch(): void {
        $posts = FIBB_Comm_DB::get_due_posts();
        if ( empty( $posts ) ) {
            return;
        }

        $meta_api     = new FIBB_Comm_Meta_API();
        $linkedin_api = new FIBB_Comm_LinkedIn_API();

        foreach ( $posts as $post ) {
            $id       = (int) $post['id'];
            $platform = $post['platform'];
            $post     = self::resolve_tokens( $post );

            switch ( $platform ) {
                case 'facebook':
                    $result = $meta_api->publish_facebook( $post );
                    break;
                case 'instagram':
                    $result = $meta_api->publish_instagram( $post );
                    break;
                case 'linkedin':
                    $result = $linkedin_api->publish( $post );
                    break;
                default:
                    FIBB_Comm_DB::mark_failed( $id, "Plateforme inconnue : {$platform}" );
                    continue 2;
            }

            if ( $result['success'] ) {
                FIBB_Comm_DB::mark_published( $id, $result['post_id'] ?? '' );
            } else {
                FIBB_Comm_DB::mark_failed( $id, $result['error'] ?? 'Erreur inconnue' );
            }
        }

        // Purge des anciens logs selon la rétention configurée.
        $settings  = get_option( FIBB_COMM_OPTION, [] );
        $retention = isset( $settings['log_retention'] ) ? (int) $settings['log_retention'] : 90;
        if ( $retention > 0 ) {
            FIBB_Comm_DB::purge_old_logs( $retention );
        }
    }

    public static function dispatch_instagram_queue(): void {
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $interval = max( 1, (int) ( $settings['auto_ig_interval'] ?? 60 ) );

        $last = FIBB_Comm_DB::get_last_published_instagram_time();
        if ( $last && ( time() - strtotime( $last ) ) < $interval * 60 ) {
            return;
        }

        $next = FIBB_Comm_DB::get_next_ig_queued();
        if ( ! $next ) {
            return;
        }

        $meta_api = new FIBB_Comm_Meta_API();
        $result   = $meta_api->publish_instagram( $next );

        if ( $result['success'] ) {
            FIBB_Comm_DB::mark_published( (int) $next['id'], $result['post_id'] ?? '' );
        } else {
            FIBB_Comm_DB::mark_failed( (int) $next['id'], $result['error'] ?? 'Erreur inconnue' );
        }
    }
}
