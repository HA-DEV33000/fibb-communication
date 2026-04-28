<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Auto_Instagram {

    private function settings(): array {
        return get_option( FIBB_COMM_OPTION, [] );
    }

    public function is_enabled(): bool {
        return ! empty( $this->settings()['auto_ig_enabled'] );
    }

    private function delay_minutes(): int {
        return max( 0, (int) ( $this->settings()['auto_ig_delay'] ?? 30 ) );
    }

    private function min_width(): int {
        return max( 0, (int) ( $this->settings()['auto_ig_min_width'] ?? 1080 ) );
    }

    private function default_caption(): string {
        $edition  = $this->settings()['festival_edition'] ?? '';
        $caption  = $this->settings()['auto_ig_caption'] ?? "📸 FIBB {{edition}} — #bridge #bordeaux #festival";
        $hashtags = $this->settings()['ig_hashtags'] ?? '#bridge #bordeaux';
        $caption  = str_replace( '{{edition}}', $edition, $caption );
        $caption  = str_replace( '{{hashtags}}', $hashtags, $caption );
        return $caption;
    }

    private function trigger_categories(): array {
        $raw = $this->settings()['auto_ig_categories'] ?? '';
        if ( ! $raw ) return [];
        return array_map( 'trim', explode( ',', $raw ) );
    }

    public function passes_size_check( int $attachment_id ): bool {
        $min = $this->min_width();
        if ( $min <= 0 ) return true;
        $meta = wp_get_attachment_metadata( $attachment_id );
        return isset( $meta['width'] ) && $meta['width'] >= $min;
    }

    public function on_media_upload( int $attachment_id ): void {
        if ( ! $this->is_enabled() ) return;
        if ( ! wp_attachment_is_image( $attachment_id ) ) return;
        if ( ! $this->passes_size_check( $attachment_id ) ) return;

        $image_url = wp_get_attachment_url( $attachment_id );
        if ( ! $image_url ) return;

        $caption = $this->default_caption();
        $this->schedule_instagram_post( $image_url, $caption, $this->delay_minutes() );
    }

    public function on_post_published( int $post_id ): void {
        if ( ! $this->is_enabled() ) return;

        $post = get_post( $post_id );
        if ( ! $post || 'post' !== $post->post_type ) return;

        // Vérifier la catégorie déclencheuse si configurée.
        $trigger_cats = $this->trigger_categories();
        if ( $trigger_cats ) {
            $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
            if ( ! array_intersect( $trigger_cats, $post_cats ) ) return;
        }

        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumbnail_id ) return;
        if ( ! $this->passes_size_check( $thumbnail_id ) ) return;

        $image_url = wp_get_attachment_url( $thumbnail_id );
        if ( ! $image_url ) return;

        $caption = $post->post_title . "\n\n" . $this->default_caption();
        $this->schedule_instagram_post( $image_url, $caption, $this->delay_minutes() );
    }

    public function schedule_instagram_post( string $image_url, string $caption, int $delay_minutes ): void {
        $scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay_minutes * 60 );
        FIBB_Comm_DB::insert_post( [
            'platform'     => 'instagram',
            'content'      => $caption,
            'image_url'    => $image_url,
            'phase'        => 'auto',
            'scheduled_at' => $scheduled_at,
            'status'       => 'scheduled',
        ] );
    }

    public function build_caption( string $title = '' ): string {
        $caption = $this->default_caption();
        if ( $title ) {
            $caption = $title . "\n\n" . $caption;
        }
        return $caption;
    }
}
