<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_SEO {

    /**
     * Score SEO 0-100 pour un post.
     * Critères : présence mots-clés (+25), longueur optimale (+25),
     * hashtags (+20), emoji (+15), pas de répétition excessive (+15).
     */
    public static function score( string $content, string $platform, array $keywords, string $hashtags ): int {
        $score   = 0;
        $content = trim( $content );

        // +25 — au moins 1 mot-clé cible présent (insensible à la casse)
        foreach ( $keywords as $kw ) {
            if ( $kw !== '' && mb_stripos( $content, $kw ) !== false ) {
                $score += 25;
                break;
            }
        }

        // +25 — longueur optimale par plateforme
        $len = mb_strlen( $content );
        $in_range = false;
        switch ( $platform ) {
            case 'instagram': $in_range = $len >= 100 && $len <= 300; break;
            case 'linkedin':  $in_range = $len >= 300 && $len <= 700; break;
            case 'facebook':  $in_range = $len >= 100 && $len <= 500; break;
            default:          $in_range = $len >= 50;
        }
        if ( $in_range ) $score += 25;

        // +20 — au moins 2 hashtags dans le contenu ou dans la zone hashtags
        $combined   = $content . ' ' . $hashtags;
        $tag_count  = preg_match_all( '/#\w+/u', $combined );
        if ( $tag_count >= 2 ) $score += 20;

        // +15 — présence d'un emoji (unicode range)
        if ( preg_match( '/[\x{1F000}-\x{1FFFF}]/u', $content ) ) $score += 15;

        // +15 — pas de répétition de mot-clé > 3×
        $over_repeated = false;
        foreach ( $keywords as $kw ) {
            if ( $kw === '' ) continue;
            $occurrences = mb_substr_count( mb_strtolower( $content ), mb_strtolower( $kw ) );
            if ( $occurrences > 3 ) {
                $over_repeated = true;
                break;
            }
        }
        if ( ! $over_repeated ) $score += 15;

        return min( 100, $score );
    }

    /**
     * Retourne le score SEO Yoast ou RankMath pour une URL WP interne.
     * Retourne null si URL externe, plugin absent, ou score non disponible.
     */
    public static function get_wp_post_seo( string $url ): ?array {
        if ( $url === '' ) return null;

        $post_id = url_to_postid( $url );
        if ( ! $post_id ) return null;

        // Yoast SEO
        $yoast_kw    = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
        $yoast_score = (int) get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );
        if ( $yoast_kw || $yoast_score > 0 ) {
            return [
                'focus_keyword' => (string) $yoast_kw,
                'score'         => $yoast_score,
                'source'        => 'yoast',
            ];
        }

        // RankMath
        $rm_kw    = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
        $rm_score = (int) get_post_meta( $post_id, 'rank_math_seo_score', true );
        if ( $rm_kw || $rm_score > 0 ) {
            return [
                'focus_keyword' => (string) $rm_kw,
                'score'         => $rm_score,
                'source'        => 'rankmath',
            ];
        }

        return null;
    }

    /**
     * Vérifie quels mots-clés cibles sont présents / absents dans le contenu.
     */
    public static function keywords_found( string $content, array $keywords ): array {
        $present = [];
        $missing = [];
        foreach ( $keywords as $kw ) {
            $kw = trim( $kw );
            if ( $kw === '' ) continue;
            if ( mb_stripos( $content, $kw ) !== false ) {
                $present[] = $kw;
            } else {
                $missing[] = $kw;
            }
        }
        return [ 'present' => $present, 'missing' => $missing ];
    }

    /**
     * Retourne la couleur CSS selon le score SEO.
     */
    public static function score_color( int $score ): string {
        if ( $score >= 70 ) return '#27ae60';
        if ( $score >= 40 ) return '#f39c12';
        return '#e74c3c';
    }

    /**
     * Retourne le libellé textuel du score.
     */
    public static function score_label( int $score ): string {
        if ( $score >= 70 ) return 'Bon';
        if ( $score >= 40 ) return 'Moyen';
        return 'Faible';
    }
}
