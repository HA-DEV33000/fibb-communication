<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Admin {

    const PAGE_SLUG = 'fibb-communication';

    public function register_menu(): void {
        add_menu_page(
            'FIBB Communication',
            'FIBB Communication',
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ],
            'dashicons-share',
            30
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) return;

        wp_enqueue_style(
            'fibb-comm-admin',
            FIBB_COMM_PLUGIN_URL . 'assets/fibb-comm-admin.css',
            [],
            FIBB_COMM_VERSION
        );
        wp_enqueue_media();
        wp_enqueue_script(
            'fibb-comm-admin',
            FIBB_COMM_PLUGIN_URL . 'assets/fibb-comm-admin.js',
            [ 'jquery' ],
            FIBB_COMM_VERSION,
            true
        );
        wp_localize_script( 'fibb-comm-admin', 'fibbCommAjax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fibb_comm_ajax' ),
        ] );

        // JS wizard — chargé uniquement sur l'onglet wizard
        if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'wizard' ) {
            wp_enqueue_script(
                'fibb-comm-wizard',
                FIBB_COMM_PLUGIN_URL . 'assets/fibb-comm-wizard.js',
                [ 'jquery' ],
                FIBB_COMM_VERSION,
                true
            );
        }

        // JS Instagram Auto + Calendrier (panneau latéral partagé)
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'calendar';
        if ( in_array( $current_tab, [ 'instagram-auto', 'calendar' ], true ) ) {
            wp_enqueue_script(
                'fibb-comm-instagram',
                FIBB_COMM_PLUGIN_URL . 'assets/fibb-comm-instagram.js',
                [ 'jquery' ],
                FIBB_COMM_VERSION,
                true
            );
        }
    }

    public function handle_actions(): void {
        if ( ! isset( $_POST['fibb_comm_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $action = sanitize_key( $_POST['fibb_comm_action'] );

        switch ( $action ) {
            case 'save_platforms':
                $this->action_save_platforms();
                break;
            case 'save_settings':
                $this->action_save_settings();
                break;
            case 'new_post':
                $this->action_new_post();
                break;
            case 'delete_post':
                $this->action_delete_post();
                break;
            case 'import_plan':
                $this->action_import_plan();
                break;
            case 'update_post':
                $this->action_update_post();
                break;
            case 'retry_post':
                $this->action_retry_post();
                break;
            case 'clear_failed':
                $this->action_clear_failed();
                break;
        }
    }

    /* ── AJAX ──────────────────────────────────────────────────── */

    public function ajax_test_connection(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $platform = sanitize_key( $_POST['platform'] ?? '' );

        if ( 'facebook' === $platform || 'instagram' === $platform ) {
            $api    = new FIBB_Comm_Meta_API();
            $result = $api->test_connection();
        } elseif ( 'linkedin' === $platform ) {
            $api    = new FIBB_Comm_LinkedIn_API();
            $result = $api->test_connection();
        } else {
            wp_send_json_error( [ 'error' => 'Plateforme inconnue.' ] );
            return;
        }

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'error' => $result['error'] ] );
        }
    }

    public function ajax_delete_post_ajax(): void {
        $post_id = absint( $_POST['post_id'] ?? 0 );
        check_ajax_referer( 'fibb_comm_delete_ajax_' . $post_id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }
        if ( ! $post_id ) {
            wp_send_json_error( [ 'error' => 'ID invalide.' ] );
            return;
        }
        $post = FIBB_Comm_DB::get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( [ 'error' => 'Post introuvable.' ] );
            return;
        }
        FIBB_Comm_DB::delete_post( $post_id );
        wp_send_json_success( [ 'post_id' => $post_id ] );
    }

    public function ajax_move_post(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $column  = sanitize_key( $_POST['column'] ?? '' );
        $view    = sanitize_key( $_POST['view'] ?? 'phase' );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'error' => 'ID invalide.' ] );
            return;
        }

        if ( 'phase' === $view ) {
            $allowed_phases = [ '', 'launch', 'pre_event', 'during', 'post_event', 'auto' ];
            if ( ! in_array( $column, $allowed_phases, true ) ) {
                wp_send_json_error( [ 'error' => 'Phase invalide.' ] );
                return;
            }
            FIBB_Comm_DB::update_post( $post_id, [ 'phase' => $column ?: null ] );
        } else {
            $allowed_statuses = [ 'draft', 'scheduled' ];
            if ( ! in_array( $column, $allowed_statuses, true ) ) {
                wp_send_json_error( [ 'error' => 'Statut invalide.' ] );
                return;
            }
            $data = [ 'status' => $column ];
            if ( 'scheduled' === $column ) {
                $data['error_message'] = null;
            }
            FIBB_Comm_DB::update_post( $post_id, $data );
        }

        wp_send_json_success( [ 'post_id' => $post_id, 'column' => $column ] );
    }

    public function ajax_newsletter_preview(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }
        $bridge = new FIBB_Comm_Newsletter_Bridge();
        $posts  = $bridge->get_wp_posts();
        if ( empty( $posts ) ) {
            wp_send_json_error( [ 'error' => 'Aucun article trouvé sur le site.' ] );
            return;
        }
        wp_send_json_success( [ 'html' => $bridge->build_html( $posts ) ] );
    }

    public function ajax_newsletter_get_lists(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }
        $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        if ( ! $api_key ) {
            wp_send_json_error( [ 'error' => 'Clé API manquante.' ] );
            return;
        }
        $bridge = new FIBB_Comm_Newsletter_Bridge();
        $lists  = $bridge->get_brevo_lists( $api_key );
        if ( null === $lists ) {
            wp_send_json_error( [ 'error' => 'Impossible de charger les listes Brevo. Vérifiez la clé API.' ] );
            return;
        }
        wp_send_json_success( [ 'lists' => $lists ] );
    }

    public function handle_newsletter_save(): void {
        check_admin_referer( 'fibb_comm_newsletter_save', 'fibb_nl_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission refusée.' );
        $bridge = new FIBB_Comm_Newsletter_Bridge();
        $bridge->save_options( wp_unslash( $_POST ) );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'newsletter', 'nl-saved' => '1' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    public function handle_newsletter_send(): void {
        check_admin_referer( 'fibb_comm_newsletter_send', 'fibb_nl_send_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission refusée.' );
        $bridge = new FIBB_Comm_Newsletter_Bridge();
        $result = $bridge->send();
        $param  = $result['success']
            ? [ 'tab' => 'newsletter', 'nl-sent' => $result['campaign_id'] ]
            : [ 'tab' => 'newsletter', 'nl-error' => urlencode( $result['error'] ) ];
        wp_safe_redirect( add_query_arg( $param, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    public function ajax_get_template(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        $slug = sanitize_key( $_POST['slug'] ?? '' );
        $tpl  = FIBB_Comm_Templates::get_by_slug( $slug );
        if ( ! $tpl ) {
            wp_send_json_error( [ 'error' => 'Modèle introuvable.' ] );
            return;
        }
        // Résoudre les tokens simples pour l'aperçu.
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $edition  = $settings['festival_edition'] ?? '6';
        $tpl['content'] = str_replace( '{{edition}}', $edition, $tpl['content'] );
        wp_send_json_success( $tpl );
    }

    /* ── ACTION HANDLERS ───────────────────────────────────────── */

    private function action_save_platforms(): void {
        check_admin_referer( 'fibb_comm_platforms', 'fibb_platforms_nonce' );

        $settings = get_option( FIBB_COMM_OPTION, [] );

        $settings['meta_page_id']    = sanitize_text_field( wp_unslash( $_POST['meta_page_id'] ?? '' ) );
        $settings['meta_ig_user_id'] = sanitize_text_field( wp_unslash( $_POST['meta_ig_user_id'] ?? '' ) );

        // Ne pas écraser le token si le champ est vide.
        $new_token = sanitize_text_field( wp_unslash( $_POST['meta_page_token'] ?? '' ) );
        if ( $new_token ) $settings['meta_page_token'] = $new_token;

        $settings['linkedin_org_id'] = sanitize_text_field( wp_unslash( $_POST['linkedin_org_id'] ?? '' ) );
        $new_li_token = sanitize_text_field( wp_unslash( $_POST['linkedin_token'] ?? '' ) );
        if ( $new_li_token ) $settings['linkedin_token'] = $new_li_token;

        $expiry_date = sanitize_text_field( wp_unslash( $_POST['linkedin_token_expiry_date'] ?? '' ) );
        if ( $expiry_date ) {
            $settings['linkedin_token_expiry'] = strtotime( $expiry_date );
        }

        update_option( FIBB_COMM_OPTION, $settings );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'platforms', 'platforms-saved' => '1' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_save_settings(): void {
        check_admin_referer( 'fibb_comm_settings', 'fibb_settings_nonce' );

        $settings = get_option( FIBB_COMM_OPTION, [] );

        $settings['festival_edition']   = absint( $_POST['festival_edition'] ?? 6 );
        $settings['festival_date']      = sanitize_text_field( wp_unslash( $_POST['festival_date'] ?? '' ) );
        $settings['festival_timezone']  = sanitize_text_field( wp_unslash( $_POST['festival_timezone'] ?? 'Europe/Paris' ) );
        $settings['hashtags_facebook']  = sanitize_text_field( wp_unslash( $_POST['hashtags_facebook'] ?? '' ) );
        $settings['hashtags_instagram'] = sanitize_text_field( wp_unslash( $_POST['hashtags_instagram'] ?? '' ) );
        $settings['hashtags_linkedin']  = sanitize_text_field( wp_unslash( $_POST['hashtags_linkedin'] ?? '' ) );
        $settings['auto_ig_enabled']    = ! empty( $_POST['auto_ig_enabled'] ) ? 1 : 0;
        $settings['auto_ig_delay']      = absint( $_POST['auto_ig_delay'] ?? 30 );
        $settings['auto_ig_min_width']  = absint( $_POST['auto_ig_min_width'] ?? 1080 );
        $settings['auto_ig_caption']    = sanitize_text_field( wp_unslash( $_POST['auto_ig_caption'] ?? '' ) );
        $settings['auto_ig_categories'] = sanitize_text_field( wp_unslash( $_POST['auto_ig_categories'] ?? '' ) );
        $settings['auto_ig_interval']   = max( 1, absint( $_POST['auto_ig_interval'] ?? 60 ) );
        $settings['log_retention']      = absint( $_POST['log_retention'] ?? 90 );

        update_option( FIBB_COMM_OPTION, $settings );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'settings', 'settings-saved' => '1' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_new_post(): void {
        check_admin_referer( 'fibb_comm_new_post', 'fibb_new_post_nonce' );

        $platforms = array_map( 'sanitize_key', (array) ( $_POST['platforms'] ?? [] ) );
        $allowed   = [ 'facebook', 'instagram', 'linkedin' ];
        $platforms = array_intersect( $platforms, $allowed );

        if ( empty( $platforms ) ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'new-post', 'post-error' => 'Sélectionnez au moins une plateforme.' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
            exit;
        }

        $content      = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $image_url    = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );
        $link_url     = esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ) );
        $phase        = sanitize_key( $_POST['phase'] ?? '' );
        $scheduled_at = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );
        $publish_now  = ! empty( $_POST['publish_now'] );

        // Convertir datetime-local en UTC.
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $tz_str   = $settings['festival_timezone'] ?? 'Europe/Paris';
        try {
            $tz       = new DateTimeZone( $tz_str );
            $dt_local = new DateTime( $scheduled_at, $tz );
            $dt_local->setTimezone( new DateTimeZone( 'UTC' ) );
            $scheduled_utc = $dt_local->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            $scheduled_utc = current_time( 'mysql', true );
        }

        $meta_api     = new FIBB_Comm_Meta_API();
        $linkedin_api = new FIBB_Comm_LinkedIn_API();

        foreach ( $platforms as $platform ) {
            if ( 'instagram' === $platform && ! $image_url ) {
                continue; // Instagram sans image : ignoré silencieusement.
            }

            $row = [
                'platform'     => $platform,
                'content'      => $content,
                'image_url'    => $image_url ?: null,
                'link_url'     => $link_url ?: null,
                'phase'        => $phase ?: null,
                'scheduled_at' => $scheduled_utc,
                'status'       => 'scheduled',
            ];

            if ( $publish_now ) {
                $id = (int) FIBB_Comm_DB::insert_post( $row );
                switch ( $platform ) {
                    case 'facebook':
                        $result = $meta_api->publish_facebook( $row );
                        break;
                    case 'instagram':
                        $result = $meta_api->publish_instagram( $row );
                        break;
                    case 'linkedin':
                        $result = $linkedin_api->publish( $row );
                        break;
                    default:
                        $result = [ 'success' => false, 'error' => 'Plateforme inconnue.' ];
                }
                if ( $id ) {
                    if ( $result['success'] ) {
                        FIBB_Comm_DB::mark_published( $id, $result['post_id'] ?? '' );
                    } else {
                        FIBB_Comm_DB::mark_failed( $id, $result['error'] ?? 'Erreur' );
                    }
                }
            } else {
                FIBB_Comm_DB::insert_post( $row );
            }
        }

        wp_safe_redirect( add_query_arg( [ 'tab' => 'new-post', 'post-saved' => '1' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_delete_post(): void {
        $id = absint( $_POST['post_id'] ?? 0 );
        check_admin_referer( 'fibb_comm_delete_' . $id, 'fibb_delete_nonce' );
        FIBB_Comm_DB::delete_post( $id );
        wp_safe_redirect( add_query_arg( 'tab', 'calendar', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_import_plan(): void {
        check_admin_referer( 'fibb_comm_import_plan', 'fibb_import_plan_nonce' );
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $date     = $settings['festival_date'] ?? '';
        if ( ! $date ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'settings', 'post-error' => 'Configurez la date du festival d\'abord.' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
            exit;
        }
        $count = FIBB_Comm_Templates::import_all( $date );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'calendar', 'imported' => $count ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_update_post(): void {
        check_admin_referer( 'fibb_comm_new_post', 'fibb_new_post_nonce' );

        $id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $id ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'calendar' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
            exit;
        }

        $content      = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $image_url    = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );
        $link_url     = esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ) );
        $phase        = sanitize_key( $_POST['phase'] ?? '' );
        $scheduled_at = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );
        $publish_now  = ! empty( $_POST['publish_now'] );

        $settings = get_option( FIBB_COMM_OPTION, [] );
        $tz_str   = $settings['festival_timezone'] ?? 'Europe/Paris';
        try {
            $tz       = new DateTimeZone( $tz_str );
            $dt_local = new DateTime( $scheduled_at, $tz );
            $dt_local->setTimezone( new DateTimeZone( 'UTC' ) );
            $scheduled_utc = $dt_local->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            $scheduled_utc = current_time( 'mysql', true );
        }

        FIBB_Comm_DB::update_post( $id, [
            'content'       => $content,
            'image_url'     => $image_url ?: null,
            'link_url'      => $link_url ?: null,
            'phase'         => $phase ?: null,
            'scheduled_at'  => $scheduled_utc,
            'status'        => 'scheduled',
            'error_message' => null,
        ] );

        if ( $publish_now ) {
            $post         = FIBB_Comm_DB::get_post( $id );
            $meta_api     = new FIBB_Comm_Meta_API();
            $linkedin_api = new FIBB_Comm_LinkedIn_API();

            switch ( $post['platform'] ) {
                case 'facebook':  $result = $meta_api->publish_facebook( $post ); break;
                case 'instagram': $result = $meta_api->publish_instagram( $post ); break;
                case 'linkedin':  $result = $linkedin_api->publish( $post ); break;
                default:          $result = [ 'success' => false, 'error' => 'Plateforme inconnue.' ];
            }

            if ( $result['success'] ) {
                FIBB_Comm_DB::mark_published( $id, $result['post_id'] ?? '' );
            } else {
                FIBB_Comm_DB::mark_failed( $id, $result['error'] ?? 'Erreur' );
            }
        }

        wp_safe_redirect( add_query_arg( [ 'tab' => 'new-post', 'post-saved' => '1', 'edit' => $id ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_retry_post(): void {
        $id = absint( $_POST['post_id'] ?? 0 );
        check_admin_referer( 'fibb_comm_retry_' . $id, 'fibb_retry_nonce' );

        $post = FIBB_Comm_DB::get_post( $id );
        if ( ! $post ) {
            wp_safe_redirect( add_query_arg( 'tab', 'calendar', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
            exit;
        }

        // Tenter la publication immédiatement.
        $meta_api     = new FIBB_Comm_Meta_API();
        $linkedin_api = new FIBB_Comm_LinkedIn_API();

        switch ( $post['platform'] ) {
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
                $result = [ 'success' => false, 'error' => 'Plateforme inconnue.' ];
        }

        if ( $result['success'] ) {
            FIBB_Comm_DB::mark_published( $id, $result['post_id'] ?? '' );
        } else {
            // Remettre en scheduled pour la prochaine passe du cron si échec.
            FIBB_Comm_DB::update_post( $id, [
                'status'        => 'scheduled',
                'scheduled_at'  => current_time( 'mysql', true ),
                'error_message' => null,
            ] );
        }

        wp_safe_redirect( add_query_arg( 'tab', 'calendar', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    private function action_clear_failed(): void {
        check_admin_referer( 'fibb_comm_clear_failed', 'fibb_clear_failed_nonce' );
        FIBB_Comm_DB::clear_failed();
        wp_safe_redirect( add_query_arg( [ 'tab' => 'settings', 'settings-saved' => '1' ], admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    /* ── WIZARD AJAX ──────────────────────────────────────────── */

    public function ajax_wizard_get_state(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Accès refusé' );
        wp_send_json_success( [ 'state' => FIBB_Comm_Wizard::get_state() ] );
    }

    public function ajax_wizard_save_step(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Accès refusé' );

        $step = absint( $_POST['step'] ?? 0 );
        $raw  = json_decode( wp_unslash( $_POST['data'] ?? '{}' ), true );
        if ( ! is_array( $raw ) ) $raw = [];

        // step=0 → clear
        if ( $step === 0 ) {
            FIBB_Comm_Wizard::clear_state();
            wp_send_json_success( [ 'cleared' => true ] );
        }

        $state = FIBB_Comm_Wizard::get_state() ?? [ 'created_at' => time() ];

        switch ( $step ) {
            case 1:
                $result        = FIBB_Comm_Wizard::process_step1( $raw, $state );
                $state['config']    = array_intersect_key( $result, array_flip( [ 'edition', 'date', 'channels', 'keywords', 'seo_hashtags' ] ) );
                $state['selection'] = $result['selection'];
                break;
            case 2:
                $state = FIBB_Comm_Wizard::process_step2( $raw, $state );
                break;
            case 3:
                $state = FIBB_Comm_Wizard::process_step3( $raw, $state );
                break;
        }

        $state['step'] = max( $step, (int) ( $state['step'] ?? 1 ) );
        if ( ! isset( $state['created_at'] ) ) $state['created_at'] = time();
        FIBB_Comm_Wizard::save_state( $state );

        wp_send_json_success( [ 'step' => $step, 'state' => $state ] );
    }

    public function ajax_wizard_preview_template(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Accès refusé' );

        $slug  = sanitize_key( $_POST['slug'] ?? '' );
        $tpl   = FIBB_Comm_Templates::get_by_slug( $slug );
        if ( ! $tpl ) wp_send_json_error( 'Template introuvable' );

        $state    = FIBB_Comm_Wizard::get_state();
        $config   = $state['config'] ?? [];
        $settings = get_option( FIBB_COMM_OPTION, [] );
        $edition  = (string) ( $config['edition'] ?? $settings['festival_edition'] ?? '' );
        $date_ymd = $config['date'] ?? $settings['festival_date'] ?? '';
        $keywords = $config['keywords'] ?? [];
        $ht_key   = "seo_hashtags_{$tpl['platform']}";
        $hashtags = $config['seo_hashtags'][ $tpl['platform'] ] ?? FIBB_Comm_Templates::hashtags_for( $tpl['platform'], $settings );

        $content = FIBB_Comm_Wizard::resolve_tokens( $tpl['content'], $edition, $date_ymd, $tpl['platform'], $settings );
        $seo     = FIBB_Comm_SEO::score( $content, $tpl['platform'], $keywords, $hashtags );
        $kw_info = FIBB_Comm_SEO::keywords_found( $content, $keywords );

        $overrides    = $state['dates'] ?? [];
        $scheduled_at = FIBB_Comm_Wizard::compute_scheduled_at( $slug, $date_ymd, $overrides );
        $date_display = $scheduled_at ? gmdate( 'd/m/Y', strtotime( $scheduled_at ) ) : '—';

        wp_send_json_success( [
            'slug'      => $slug,
            'platform'  => $tpl['platform'],
            'phase'     => $tpl['phase'],
            'content'   => $content,
            'date'      => $date_display,
            'seo_score' => $seo,
            'seo_color' => FIBB_Comm_SEO::score_color( $seo ),
            'seo_label' => FIBB_Comm_SEO::score_label( $seo ),
            'kw_present'=> $kw_info['present'],
            'kw_missing'=> $kw_info['missing'],
            'image_required' => $tpl['platform'] === 'instagram' && empty( $tpl['image_url'] ),
        ] );
    }

    public function ajax_wizard_seo_check(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Accès refusé' );

        $link_url = esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ) );
        if ( $link_url === '' ) wp_send_json_success( [ 'seo' => null ] );

        $seo = FIBB_Comm_SEO::get_wp_post_seo( $link_url );
        wp_send_json_success( [ 'seo' => $seo ] );
    }

    public function ajax_wizard_activate(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Accès refusé' );

        $state = FIBB_Comm_Wizard::get_state();
        if ( ! $state ) {
            wp_send_json_error( 'Session wizard introuvable ou expirée. Veuillez recommencer.' );
        }

        // Vérification doublons
        $duplicates = FIBB_Comm_Wizard::check_duplicates( $state['selection'] ?? [] );
        $force      = (bool) ( $_POST['force'] ?? false );
        if ( ! empty( $duplicates ) && ! $force ) {
            wp_send_json_success( [
                'duplicate_warning' => true,
                'duplicates'        => $duplicates,
                'count'             => count( $duplicates ),
            ] );
        }

        $result = FIBB_Comm_Wizard::activate( $state );
        wp_send_json_success( $result );
    }

    /* ── INSTAGRAM QUEUE AJAX ─────────────────────────────────── */

    public function ajax_ig_queue_add(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $image_url = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );
        if ( ! $image_url ) {
            wp_send_json_error( [ 'error' => 'URL image manquante.' ] );
            return;
        }

        $caption    = sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) );
        $auto_ig    = new FIBB_Comm_Auto_Instagram();
        $resolved   = $auto_ig->build_caption(
            sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) )
        );
        $final_caption = $caption ?: $resolved;

        $id = $auto_ig->add_to_queue( $image_url, $final_caption );
        if ( ! $id ) {
            wp_send_json_error( [ 'error' => 'Impossible d\'ajouter à la file.' ] );
            return;
        }

        wp_send_json_success( [ 'id' => $id, 'message' => 'Photo ajoutée à la file Instagram.' ] );
    }

    public function ajax_ig_queue_remove(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'error' => 'ID invalide.' ] );
            return;
        }

        $post = FIBB_Comm_DB::get_post( $id );
        if ( ! $post || $post['status'] !== 'ig_queued' ) {
            wp_send_json_error( [ 'error' => 'Post introuvable ou déjà traité.' ] );
            return;
        }

        FIBB_Comm_DB::delete_post( $id );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_ig_preview_caption(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $auto_ig = new FIBB_Comm_Auto_Instagram();
        $caption = $auto_ig->build_caption(
            sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) )
        );

        $template = sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) );
        if ( $template ) {
            $settings   = get_option( FIBB_COMM_OPTION, [] );
            $edition    = $settings['festival_edition'] ?? '';
            $hashtags   = $settings['hashtags_instagram'] ?? '#bridge #bordeaux';
            $fest_date  = isset( $settings['festival_date'] ) ? gmdate( 'd/m/Y', strtotime( $settings['festival_date'] ) ) : '';
            $caption = str_replace(
                [ '{{title}}', '{{url}}', '{{category}}', '{{date}}', '{{edition}}', '{{hashtags}}', '{{festival_date}}' ],
                [ sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ), gmdate( 'd/m/Y' ), $edition, $hashtags, $fest_date ],
                $template
            );
        }

        wp_send_json_success( [ 'caption' => $caption ] );
    }

    public function ajax_ig_bulk_schedule(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $images        = array_map( 'esc_url_raw', (array) ( $_POST['images'] ?? [] ) );
        $caption       = sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) );
        $start_local   = sanitize_text_field( wp_unslash( $_POST['start_datetime'] ?? '' ) );
        $interval_min  = max( 1, absint( $_POST['interval_minutes'] ?? 60 ) );

        $images = array_filter( $images );
        if ( empty( $images ) ) {
            wp_send_json_error( [ 'error' => 'Aucune image sélectionnée.' ] );
            return;
        }
        if ( ! $start_local ) {
            wp_send_json_error( [ 'error' => 'Date de début manquante.' ] );
            return;
        }

        $settings = get_option( FIBB_COMM_OPTION, [] );
        $tz_str   = $settings['festival_timezone'] ?? 'Europe/Paris';
        try {
            $tz           = new DateTimeZone( $tz_str );
            $dt           = new DateTime( $start_local, $tz );
            $dt->setTimezone( new DateTimeZone( 'UTC' ) );
            $start_utc_ts = $dt->getTimestamp();
        } catch ( Exception $e ) {
            $start_utc_ts = time();
        }

        $meta_api = new FIBB_Comm_Meta_API();
        $created  = 0;
        $errors   = [];

        foreach ( array_values( $images ) as $i => $image_url ) {
            $ratio_check = $meta_api->check_instagram_ratio( $image_url );
            if ( ! $ratio_check['valid'] ) {
                $errors[] = 'Image #' . ( $i + 1 ) . ' : ' . $ratio_check['error'];
                continue;
            }

            $scheduled_utc = gmdate( 'Y-m-d H:i:s', $start_utc_ts + $i * $interval_min * 60 );
            $id = FIBB_Comm_DB::insert_post( [
                'platform'     => 'instagram',
                'content'      => $caption,
                'image_url'    => $image_url,
                'phase'        => 'auto',
                'scheduled_at' => $scheduled_utc,
                'status'       => 'ig_queued',
            ] );

            if ( $id ) {
                $created++;
            } else {
                $errors[] = 'Image #' . ( $i + 1 ) . ' : erreur d\'insertion en base.';
            }
        }

        wp_send_json_success( [ 'created' => $created, 'errors' => $errors ] );
    }

    public function ajax_ig_publish_now(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'error' => 'ID invalide.' ] );
            return;
        }

        $post = FIBB_Comm_DB::get_post( $id );
        if ( ! $post ) {
            wp_send_json_error( [ 'error' => 'Post introuvable.' ] );
            return;
        }

        $meta_api = new FIBB_Comm_Meta_API();
        $result   = $meta_api->publish_instagram( $post );

        if ( $result['success'] ) {
            FIBB_Comm_DB::mark_published( $id, $result['post_id'] ?? '' );
            wp_send_json_success( [ 'post_id' => $id ] );
        } else {
            FIBB_Comm_DB::mark_failed( $id, $result['error'] ?? 'Erreur inconnue' );
            wp_send_json_error( [ 'error' => $result['error'] ?? 'Erreur inconnue' ] );
        }
    }

    public function ajax_ig_get_post(): void {
        check_ajax_referer( 'fibb_comm_ajax', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Permission refusée.' ] );
        }

        $id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'error' => 'ID invalide.' ] );
            return;
        }

        $post = FIBB_Comm_DB::get_post( $id );
        if ( ! $post ) {
            wp_send_json_error( [ 'error' => 'Post introuvable.' ] );
            return;
        }

        $settings = get_option( FIBB_COMM_OPTION, [] );
        $tz_str   = $settings['festival_timezone'] ?? 'Europe/Paris';
        try {
            $tz = new DateTimeZone( $tz_str );
            $dt = new DateTime( $post['scheduled_at'], new DateTimeZone( 'UTC' ) );
            $dt->setTimezone( $tz );
            $date_local = $dt->format( 'd/m/Y H:i' );
        } catch ( Exception $e ) {
            $date_local = substr( $post['scheduled_at'], 0, 16 );
        }

        wp_send_json_success( [
            'id'           => $post['id'],
            'image_url'    => $post['image_url'] ?? '',
            'content'      => $post['content'],
            'scheduled_at' => $date_local,
            'status'       => $post['status'],
            'edit_url'     => admin_url( 'admin.php?page=fibb-communication&tab=new-post&edit=' . $id ),
            'can_delete'   => in_array( $post['status'], [ 'ig_queued', 'scheduled', 'draft', 'failed' ], true ),
            'delete_nonce' => wp_create_nonce( 'fibb_comm_delete_' . $id ),
        ] );
    }

    /* ── RENDER ────────────────────────────────────────────────── */

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'calendar';
        $base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;

        $tabs = [
            'calendar'       => '📅 Calendrier',
            'plan'           => '🗂 Plan',
            'wizard'         => '🧙 Assistant Plan',
            'instagram-auto' => '📸 Instagram Auto',
            'new-post'       => '✏️ Nouveau Post',
            'newsletter'     => '📧 Newsletter',
            'platforms'      => '⚙️ Plateformes',
            'settings'       => '🛠 Paramètres',
        ];
        ?>
        <div class="wrap">
            <div class="fibb-header">
                <img src="https://festival-international-bridge-bordeaux.com/wp-content/uploads/2022/02/FIBB_logo-1000x300-1.png"
                     alt="FIBB Logo">
                <h1>FIBB Communication Suite</h1>
            </div>

            <?php if ( $imported ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ <?php echo esc_html( $imported ); ?> posts importés depuis le plan de communication.</p>
                </div>
            <?php endif; ?>

            <div class="fibb-tabs">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( $base_url . '&tab=' . $key ); ?>"
                       class="<?php echo $tab === $key ? 'active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Panneau latéral Instagram (partagé entre onglets) -->
            <div id="fibb-ig-panel-overlay" class="fibb-side-panel-overlay"></div>
            <div id="fibb-ig-panel" class="fibb-side-panel" role="dialog" aria-label="Détails du post Instagram">
                <div class="fibb-side-panel-header">
                    <span class="fibb-badge fibb-badge-instagram">Instagram</span>
                    <button type="button" class="fibb-side-panel-close" id="fibb-ig-panel-close">✕</button>
                </div>
                <img id="fibb-panel-img" src="" alt="" class="fibb-side-panel-img">
                <div class="fibb-side-panel-body">
                    <p id="fibb-panel-caption" class="fibb-side-panel-caption"></p>
                    <p id="fibb-panel-date" class="fibb-side-panel-date"></p>
                    <span id="fibb-panel-status" class="fibb-side-panel-status"></span>
                </div>
                <div class="fibb-side-panel-actions">
                    <a id="fibb-panel-edit-btn" href="#" class="button">✏️ Modifier</a>
                    <button type="button" id="fibb-panel-delete-btn" class="button">🗑 Supprimer</button>
                    <button type="button" id="fibb-panel-publish-btn" class="button button-primary">▶ Publier</button>
                </div>
                <div id="fibb-panel-msg" style="display:none;margin:0 16px 16px;padding:8px;font-size:13px;border-radius:4px;"></div>
            </div>

            <?php
            switch ( $tab ) {
                case 'wizard':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-wizard.php';
                    break;
                case 'instagram-auto':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-instagram.php';
                    break;
                case 'plan':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-plan.php';
                    break;
                case 'new-post':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-new-post.php';
                    break;
                case 'newsletter':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-newsletter.php';
                    break;
                case 'platforms':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-platforms.php';
                    break;
                case 'settings':
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-settings.php';
                    break;
                default:
                    include FIBB_COMM_PLUGIN_DIR . 'admin/tab-calendar.php';
            }
            ?>
        </div>
        <?php
    }
}
