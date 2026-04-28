<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_DB {

    public static function install() {
        global $wpdb;
        $table   = $wpdb->prefix . 'fibb_comm_posts';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform         VARCHAR(20)  NOT NULL,
            content          TEXT         NOT NULL,
            image_url        VARCHAR(500) DEFAULT NULL,
            link_url         VARCHAR(500) DEFAULT NULL,
            phase            VARCHAR(30)  DEFAULT NULL,
            template_slug    VARCHAR(80)  DEFAULT NULL,
            scheduled_at     DATETIME     NOT NULL,
            status           VARCHAR(20)  NOT NULL DEFAULT 'scheduled',
            platform_post_id VARCHAR(200) DEFAULT NULL,
            error_message    TEXT         DEFAULT NULL,
            published_at     DATETIME     DEFAULT NULL,
            created_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_scheduled (status, scheduled_at),
            KEY idx_platform (platform),
            KEY idx_phase (phase)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fibb_comm_posts" );
    }

    /* ── WRITE ─────────────────────────────────────────────────── */

    public static function insert_post( array $data ) {
        global $wpdb;
        $defaults = [
            'platform'      => '',
            'content'       => '',
            'image_url'     => null,
            'link_url'      => null,
            'phase'         => null,
            'template_slug' => null,
            'scheduled_at'  => current_time( 'mysql', true ),
            'status'        => 'scheduled',
            'created_by'    => get_current_user_id() ?: null,
        ];
        $row = array_merge( $defaults, $data );
        $ok  = $wpdb->insert( $wpdb->prefix . 'fibb_comm_posts', $row );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update_post( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'fibb_comm_posts',
            $data,
            [ 'id' => $id ]
        );
    }

    public static function delete_post( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'fibb_comm_posts',
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    public static function mark_published( int $id, string $platform_post_id ): void {
        self::update_post( $id, [
            'status'           => 'published',
            'platform_post_id' => $platform_post_id,
            'published_at'     => current_time( 'mysql', true ),
        ] );
    }

    public static function mark_failed( int $id, string $error ): void {
        self::update_post( $id, [
            'status'        => 'failed',
            'error_message' => $error,
        ] );
    }

    /* ── READ ──────────────────────────────────────────────────── */

    public static function get_post( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fibb_comm_posts WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_posts_by_status( string $status ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fibb_comm_posts
                 WHERE status = %s
                 ORDER BY scheduled_at ASC
                 LIMIT 10",
                $status
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_due_posts(): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fibb_comm_posts
                 WHERE status = 'scheduled' AND scheduled_at <= %s
                 ORDER BY scheduled_at ASC
                 LIMIT 10",
                current_time( 'mysql', true )
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_posts_for_calendar( string $from, string $to ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fibb_comm_posts
                 WHERE scheduled_at BETWEEN %s AND %s
                 ORDER BY scheduled_at ASC",
                $from,
                $to
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_all_posts( array $filters = [] ): array {
        global $wpdb;
        $where  = '1=1';
        $params = [];

        if ( ! empty( $filters['platform'] ) ) {
            $where   .= ' AND platform = %s';
            $params[] = $filters['platform'];
        }
        if ( ! empty( $filters['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if ( ! empty( $filters['phase'] ) ) {
            $where   .= ' AND phase = %s';
            $params[] = $filters['phase'];
        }

        $sql = "SELECT * FROM {$wpdb->prefix}fibb_comm_posts WHERE {$where} ORDER BY scheduled_at DESC LIMIT 200";
        if ( $params ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare( $sql, ...$params );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    public static function get_recent_errors( int $limit = 10 ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fibb_comm_posts
                 WHERE status = 'failed'
                 ORDER BY updated_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function clear_failed(): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'fibb_comm_posts', [ 'status' => 'failed' ] );
    }

    public static function purge_old_logs( int $days ): void {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}fibb_comm_posts
                 WHERE status IN ('published','failed') AND updated_at < %s",
                $cutoff
            )
        );
    }
}
