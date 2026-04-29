<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Meta_API {

    private string $graph_url = 'https://graph.facebook.com/v19.0';

    private function settings(): array {
        return get_option( FIBB_COMM_OPTION, [] );
    }

    private function page_token(): string {
        return $this->settings()['meta_page_token'] ?? '';
    }

    private function page_id(): string {
        return $this->settings()['meta_page_id'] ?? '';
    }

    private function ig_user_id(): string {
        return $this->settings()['meta_ig_user_id'] ?? '';
    }

    /* ── HTTP helper ───────────────────────────────────────────── */

    private function request( string $method, string $url, array $payload = [] ): array {
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
        ] );

        if ( 'POST' === $method ) {
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );
        }

        $body = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $err  = curl_error( $ch );
        curl_close( $ch );

        if ( $err ) {
            return [ 'success' => false, 'error' => "cURL : {$err}" ];
        }

        $data = json_decode( $body, true );
        if ( $code >= 400 ) {
            $msg = $data['error']['message'] ?? $body;
            return [ 'success' => false, 'error' => "HTTP {$code} : {$msg}" ];
        }

        return [ 'success' => true, 'data' => $data ];
    }

    /* ── Facebook ──────────────────────────────────────────────── */

    public function publish_facebook( array $post ): array {
        $token   = $this->page_token();
        $page_id = $this->page_id();

        if ( ! $token || ! $page_id ) {
            return [ 'success' => false, 'error' => 'Token ou Page ID Facebook manquant.' ];
        }

        $payload = [ 'message' => $post['content'], 'access_token' => $token ];
        if ( ! empty( $post['link_url'] ) ) {
            $payload['link'] = $post['link_url'];
        }

        $url    = "{$this->graph_url}/{$page_id}/feed";
        $result = $this->request( 'POST', $url, $payload );

        if ( $result['success'] ) {
            return [ 'success' => true, 'post_id' => $result['data']['id'] ?? '' ];
        }
        return $result;
    }

    /* ── Instagram ─────────────────────────────────────────────── */

    /**
     * Instagram accepte les ratios entre 4:5 (0.8) et 1.91:1 (1.91).
     * Toute image hors de cette plage déclenche HTTP 400 "aspect ratio not supported".
     */
    public function check_instagram_ratio( string $image_url ): array {
        $size = @getimagesize( $image_url );
        if ( ! $size || $size[1] === 0 ) {
            return [ 'valid' => true ];
        }

        $ratio = $size[0] / $size[1];
        if ( $ratio < 0.8 || $ratio > 1.91 ) {
            $label = round( $ratio, 2 );
            return [
                'valid' => false,
                'error' => "Format d'image non supporté par Instagram ({$size[0]}×{$size[1]}, ratio {$label}:1). "
                         . "Recadrez l'image entre 4:5 (portrait) et 1.91:1 (paysage) avant de publier.",
            ];
        }

        return [ 'valid' => true ];
    }

    public function publish_instagram( array $post ): array {
        $token     = $this->page_token();
        $ig_user   = $this->ig_user_id();
        $image_url = $post['image_url'] ?? '';

        if ( ! $token || ! $ig_user ) {
            return [ 'success' => false, 'error' => 'Token ou IG User ID manquant.' ];
        }
        if ( ! $image_url ) {
            return [ 'success' => false, 'error' => "Instagram nécessite une image (image_url vide)." ];
        }

        $ratio_check = $this->check_instagram_ratio( $image_url );
        if ( ! $ratio_check['valid'] ) {
            return [ 'success' => false, 'error' => $ratio_check['error'] ];
        }

        // Étape 1 : créer le container média.
        $container = $this->request( 'POST', "{$this->graph_url}/{$ig_user}/media", [
            'image_url'    => $image_url,
            'caption'      => $post['content'],
            'access_token' => $token,
        ] );

        if ( ! $container['success'] ) {
            return $container;
        }

        $container_id = $container['data']['id'] ?? '';
        if ( ! $container_id ) {
            return [ 'success' => false, 'error' => 'Container ID Instagram manquant dans la réponse.' ];
        }

        // Étape 2 : publier.
        $publish = $this->request( 'POST', "{$this->graph_url}/{$ig_user}/media_publish", [
            'creation_id'  => $container_id,
            'access_token' => $token,
        ] );

        if ( $publish['success'] ) {
            return [ 'success' => true, 'post_id' => $publish['data']['id'] ?? '' ];
        }
        return $publish;
    }

    /* ── Test connexion ────────────────────────────────────────── */

    public function test_connection(): array {
        $token   = $this->page_token();
        $page_id = $this->page_id();

        if ( ! $token || ! $page_id ) {
            return [ 'success' => false, 'error' => 'Token ou Page ID manquant.' ];
        }

        $url    = "{$this->graph_url}/{$page_id}?fields=name,fan_count&access_token={$token}";
        $result = $this->request( 'GET', $url );

        if ( $result['success'] ) {
            $name = $result['data']['name'] ?? '(nom inconnu)';
            $fans = $result['data']['fan_count'] ?? 0;
            return [ 'success' => true, 'message' => "Connecté à : {$name} ({$fans} abonnés)" ];
        }
        return $result;
    }
}
