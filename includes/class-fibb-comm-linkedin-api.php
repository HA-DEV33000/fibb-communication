<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_LinkedIn_API {

    private string $api_url = 'https://api.linkedin.com/v2';

    private function settings(): array {
        return get_option( FIBB_COMM_OPTION, [] );
    }

    private function token(): string {
        return $this->settings()['linkedin_token'] ?? '';
    }

    private function org_id(): string {
        return $this->settings()['linkedin_org_id'] ?? '';
    }

    private function token_expiry(): int {
        return (int) ( $this->settings()['linkedin_token_expiry'] ?? 0 );
    }

    public function days_until_expiry(): int {
        $expiry = $this->token_expiry();
        if ( ! $expiry ) return -1;
        return (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS );
    }

    /* ── HTTP helper ───────────────────────────────────────────── */

    private function request( string $method, string $url, array $payload = [] ): array {
        $token = $this->token();
        $ch    = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0',
            ],
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

        $data = $body ? json_decode( $body, true ) : [];
        if ( $code >= 400 ) {
            $msg = $data['message'] ?? $body;
            return [ 'success' => false, 'error' => "HTTP {$code} : {$msg}" ];
        }

        return [ 'success' => true, 'data' => $data, 'http_code' => $code ];
    }

    /* ── Publish ───────────────────────────────────────────────── */

    public function publish( array $post ): array {
        $token  = $this->token();
        $org_id = $this->org_id();

        if ( ! $token || ! $org_id ) {
            return [ 'success' => false, 'error' => 'Token ou Organization ID LinkedIn manquant.' ];
        }

        $payload = [
            'author'         => "urn:li:organization:{$org_id}",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $post['content'],
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        // Ajouter un lien si présent.
        if ( ! empty( $post['link_url'] ) ) {
            $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
            $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                [
                    'status'      => 'READY',
                    'originalUrl' => $post['link_url'],
                ],
            ];
        }

        $result = $this->request( 'POST', "{$this->api_url}/ugcPosts", $payload );

        if ( $result['success'] ) {
            $post_id = $result['data']['id'] ?? '';
            return [ 'success' => true, 'post_id' => $post_id ];
        }
        return $result;
    }

    /* ── Test connexion ────────────────────────────────────────── */

    public function test_connection(): array {
        $token  = $this->token();
        $org_id = $this->org_id();

        if ( ! $token || ! $org_id ) {
            return [ 'success' => false, 'error' => 'Token ou Organization ID manquant.' ];
        }

        $result = $this->request( 'GET', "{$this->api_url}/organizations/{$org_id}" );

        if ( $result['success'] ) {
            $name = $result['data']['localizedName'] ?? "(nom inconnu)";
            $days = $this->days_until_expiry();
            $msg  = "Connecté à : {$name}";
            if ( $days >= 0 ) {
                $msg .= " — token expire dans {$days} jour(s)";
            }
            return [ 'success' => true, 'message' => $msg ];
        }
        return $result;
    }
}
