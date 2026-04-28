<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Newsletter_Bridge {

    const NL_OPTION    = 'fibb_newsletter_settings';
    const NL_LAST_SENT = 'fibb_nl_last_sent';

    /* ── Compatibilité standalone plugin ──────────────────────── */

    public function is_newsletter_active(): bool {
        return defined( 'FIBB_NL_CRON_HOOK' );
    }

    /* ── Paramètres ───────────────────────────────────────────── */

    public function get_options(): array {
        return get_option( self::NL_OPTION, [] );
    }

    public function save_options( array $raw ): void {
        $opts = [
            'api_key'      => sanitize_text_field( $raw['api_key']      ?? '' ),
            'list_id'      => absint( $raw['list_id']                   ?? 0 ),
            'sender_name'  => sanitize_text_field( $raw['sender_name']  ?? 'FIBB' ),
            'sender_email' => sanitize_email( $raw['sender_email']      ?? '' ),
            'reply_to'     => sanitize_email( $raw['reply_to']          ?? '' ),
            'send_day'     => max( 1, min( 28, absint( $raw['send_day'] ?? 1 ) ) ),
        ];
        update_option( self::NL_OPTION, $opts );
    }

    /* ── Statut ───────────────────────────────────────────────── */

    public function get_last_send(): ?array {
        $data = get_option( self::NL_LAST_SENT );
        return $data ?: null;
    }

    public function get_next_send(): ?int {
        if ( ! $this->is_newsletter_active() ) return null;
        $ts = wp_next_scheduled( FIBB_NL_CRON_HOOK );
        return $ts ?: null;
    }

    /* ── Calendrier ───────────────────────────────────────────── */

    public function get_newsletter_events_for_calendar(): array {
        $events = [];

        $last = $this->get_last_send();
        if ( $last && ! empty( $last['date'] ) ) {
            $events[] = [
                'type'  => 'email',
                'date'  => gmdate( 'Y-m-d', strtotime( $last['date'] ) ),
                'label' => 'Newsletter envoyée',
                'color' => '#c8102e',
            ];
        }

        $next = $this->get_next_send();
        if ( $next ) {
            $events[] = [
                'type'  => 'email',
                'date'  => gmdate( 'Y-m-d', $next ),
                'label' => 'Newsletter planifiée',
                'color' => '#c8102e',
            ];
        }

        return $events;
    }

    /* ── Articles WordPress ───────────────────────────────────── */

    public function get_wp_posts(): array {
        return get_posts( [
            'numberposts'      => 10,
            'post_status'      => 'publish',
            'post_type'        => 'post',
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ] );
    }

    /* ── Construction HTML ────────────────────────────────────── */

    public function build_html( array $posts ): string {
        $month_fr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $mois = $month_fr[ (int) gmdate( 'n' ) ] . ' ' . gmdate( 'Y' );

        $articles_html = '';
        foreach ( $posts as $i => $post ) {
            $url       = get_permalink( $post->ID );
            $title     = esc_html( get_the_title( $post->ID ) );
            $excerpt   = esc_html( wp_trim_words( get_the_excerpt( $post->ID ) ?: $post->post_content, 18, '…' ) );
            $date      = date_i18n( 'j F Y', strtotime( $post->post_date ) );
            $thumb_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
            $cat_obj   = get_the_category( $post->ID );
            $cat       = ! empty( $cat_obj ) ? esc_html( $cat_obj[0]->name ) : 'Actualité';
            $separator = ( $i < count( $posts ) - 1 )
                ? '<tr><td style="padding:0 40px;"><hr style="border:none;border-top:1px solid #eeeeee;margin:0;"></td></tr>'
                : '';

            $img_block = $thumb_url
                ? '<img src="' . esc_url( $thumb_url ) . '" alt="' . $title . '" width="120" style="display:block;border-radius:6px;height:80px;object-fit:cover;max-width:120px;">'
                : '<div style="width:120px;height:80px;background:#f0f0f0;border-radius:6px;"></div>';

            $articles_html .= '
            <tr>
              <td class="content-padding" style="padding:20px 40px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td width="130" valign="top" style="padding-right:16px;">' . $img_block . '</td>
                    <td valign="top">
                      <p style="font-size:10px;color:#c8102e;margin:0 0 5px 0;text-transform:uppercase;font-weight:700;letter-spacing:1px;">' . $cat . ' · ' . $date . '</p>
                      <h3 style="font-size:16px;color:#1a1a2e;margin:0 0 8px 0;line-height:1.4;font-weight:700;">' . $title . '</h3>
                      <p style="font-size:13px;color:#666;margin:0 0 10px 0;line-height:1.5;">' . $excerpt . '</p>
                      <a href="' . esc_url( $url ) . '" style="font-size:12px;color:#c8102e;font-weight:700;text-decoration:none;">Lire la suite →</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            ' . $separator;
        }

        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;}
@media only screen and (max-width:600px){
  .email-container{width:100%!important;border-radius:0!important;}
  .content-padding{padding:16px 16px!important;}
  .hero-padding{padding:24px 16px!important;}
  .btn-full{width:100%!important;box-sizing:border-box!important;display:block!important;text-align:center!important;}
}
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
  <tr><td align="center" style="padding:0 15px;">
    <table class="email-container" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

      <tr>
        <td style="background:#ffffff;padding:20px 40px;text-align:center;border-bottom:3px solid #c8102e;">
          <img src="https://festival-international-bridge-bordeaux.com/wp-content/uploads/2022/02/FIBB_logo-1000x300-1.png" alt="FIBB" width="260" style="max-width:100%;height:auto;display:block;margin:0 auto;">
        </td>
      </tr>

      <tr>
        <td class="hero-padding" style="background:#c8102e;padding:24px 40px;text-align:center;">
          <p style="color:#ffcccc;font-size:11px;margin:0 0 6px 0;text-transform:uppercase;letter-spacing:3px;font-weight:700;">Newsletter mensuelle</p>
          <h1 style="color:#ffffff;font-size:22px;margin:0;font-weight:700;">Les actualités du FIBB — ' . $mois . '</h1>
        </td>
      </tr>

      <tr>
        <td class="content-padding" style="padding:24px 40px 16px 40px;">
          <p style="font-size:14px;color:#555;line-height:1.7;margin:0;">
            Bonjour,<br>Retrouvez les 10 dernières actualités du <strong style="color:#c8102e;">Festival International de Bridge de Bordeaux</strong>.
          </p>
        </td>
      </tr>

      <tr><td style="padding:0 40px;"><hr style="border:none;border-top:1px solid #eeeeee;margin:0;"></td></tr>

      ' . $articles_html . '

      <tr>
        <td style="padding:20px 40px 32px 40px;text-align:center;">
          <a href="https://festival-international-bridge-bordeaux.com/actualites" class="btn-full" style="display:inline-block;background:#c8102e;color:#ffffff;text-decoration:none;padding:13px 32px;border-radius:4px;font-size:14px;font-weight:700;">Toutes les actualités →</a>
        </td>
      </tr>

      <tr>
        <td style="background:#1a1a2e;padding:24px 30px;text-align:center;">
          <p style="color:#aaa;font-size:12px;margin:0 0 4px 0;">© ' . gmdate( 'Y' ) . ' FIBB — Festival International de Bridge de Bordeaux</p>
          <p style="color:#aaa;font-size:12px;margin:0 0 4px 0;">
            <a href="https://festival-international-bridge-bordeaux.com" style="color:#aaa;">Site web</a>
          </p>
          <p style="color:#888;font-size:11px;margin:0;">
            <a href="{{unsubscribe}}" style="color:#888;text-decoration:underline;">Se désinscrire</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    /* ── Envoi Brevo ──────────────────────────────────────────── */

    public function send(): array {
        $opts         = $this->get_options();
        $api_key      = $opts['api_key']      ?? '';
        $list_id      = (int) ( $opts['list_id']  ?? 0 );
        $sender_name  = $opts['sender_name']  ?? 'FIBB';
        $sender_email = $opts['sender_email'] ?? 'info@fibb.fr';
        $reply_to     = $opts['reply_to']     ?? 'info@fibb.fr';

        if ( empty( $api_key ) || empty( $list_id ) ) {
            return [ 'success' => false, 'error' => 'Clé API ou ID de liste manquant dans les paramètres.' ];
        }

        $posts = $this->get_wp_posts();
        if ( empty( $posts ) ) {
            return [ 'success' => false, 'error' => 'Aucun article trouvé sur le site.' ];
        }

        $month_fr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $mois    = $month_fr[ (int) gmdate( 'n' ) ] . ' ' . gmdate( 'Y' );
        $subject = 'Les actualités du FIBB — ' . $mois;
        $html    = $this->build_html( $posts );

        // 1. Créer la campagne
        $response = wp_remote_post( 'https://api.brevo.com/v3/emailCampaigns', [
            'timeout' => 20,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'api-key'      => $api_key,
            ],
            'body' => wp_json_encode( [
                'name'        => 'Newsletter FIBB ' . $mois . ' [auto]',
                'subject'     => $subject,
                'type'        => 'classic',
                'htmlContent' => $html,
                'sender'      => [ 'name' => $sender_name, 'email' => $sender_email ],
                'replyTo'     => $reply_to,
                'recipients'  => [ 'listIds' => [ $list_id ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 201 !== $code || empty( $body['id'] ) ) {
            return [ 'success' => false, 'error' => $body['message'] ?? 'Erreur création campagne (HTTP ' . $code . ')' ];
        }

        $campaign_id = $body['id'];

        // 2. Envoyer immédiatement
        $response2 = wp_remote_post( 'https://api.brevo.com/v3/emailCampaigns/' . $campaign_id . '/sendNow', [
            'timeout' => 20,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'api-key'      => $api_key,
            ],
            'body' => '{}',
        ] );

        $code2 = (int) wp_remote_retrieve_response_code( $response2 );

        if ( 204 !== $code2 ) {
            $body2 = json_decode( wp_remote_retrieve_body( $response2 ), true );
            return [
                'success' => false,
                'error'   => 'Campagne créée (ID ' . $campaign_id . ') mais envoi échoué : ' . ( $body2['message'] ?? 'HTTP ' . $code2 ),
            ];
        }

        // Log
        $last = $this->get_last_send();
        $history = get_option( 'fibb_nl_send_history', [] );
        if ( $last ) array_unshift( $history, $last );
        update_option( 'fibb_nl_send_history', array_slice( $history, 0, 10 ) );

        $entry = [
            'date'        => current_time( 'mysql' ),
            'campaign_id' => $campaign_id,
            'posts_count' => count( $posts ),
        ];
        update_option( self::NL_LAST_SENT, $entry );

        return [ 'success' => true, 'campaign_id' => $campaign_id ];
    }

    /* ── Listes Brevo ─────────────────────────────────────────── */

    public function get_brevo_lists( string $api_key ): ?array {
        $response = wp_remote_get( 'https://api.brevo.com/v3/contacts/lists?limit=50&offset=0', [
            'timeout' => 15,
            'headers' => [
                'accept'  => 'application/json',
                'api-key' => $api_key,
            ],
        ] );

        if ( is_wp_error( $response ) ) return null;
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) return null;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['lists'] ?? null;
    }

    /* ── Statistiques liste Brevo ─────────────────────────────── */

    public function get_list_stats( string $api_key, int $list_id ): ?array {
        if ( ! $api_key || ! $list_id ) return null;
        $response = wp_remote_get( 'https://api.brevo.com/v3/contacts/lists/' . $list_id, [
            'timeout' => 10,
            'headers' => [
                'accept'  => 'application/json',
                'api-key' => $api_key,
            ],
        ] );
        if ( is_wp_error( $response ) ) return null;
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) return null;
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
