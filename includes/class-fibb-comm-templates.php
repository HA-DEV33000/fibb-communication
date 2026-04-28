<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FIBB_Comm_Templates {

    private static function raw(): array {
        return [
            /* ─── PHASE LAUNCH ─── */
            'launch_announce_fb' => [
                'phase'    => 'launch',
                'platform' => 'facebook',
                'offset'   => -90,
                'content'  => "🃏 La {{edition}}e édition du Festival International de Bridge de Bordeaux est officielle !\n\nInscriptions ouvertes — rejoignez des joueurs passionnés du monde entier à Bordeaux.\n\n🗓 {{festival_date}}\n\n{{hashtags}}",
            ],
            'launch_announce_ig' => [
                'phase'    => 'launch',
                'platform' => 'instagram',
                'offset'   => -90,
                'content'  => "La {{edition}}e édition du FIBB est lancée ! 🃏\nInscriptions ouvertes dès maintenant.\n{{hashtags}}",
                'image_url' => '',
            ],
            'launch_announce_li' => [
                'phase'    => 'launch',
                'platform' => 'linkedin',
                'offset'   => -90,
                'content'  => "Nous sommes fiers d'annoncer la {{edition}}e édition du Festival International de Bridge de Bordeaux.\n\nUn événement incontournable qui réunit chaque année des joueurs de haut niveau venus du monde entier.\n\n📅 {{festival_date}} — Bordeaux\n\n#bridge #bordeaux #sport #fibb",
            ],
            'launch_register_fb' => [
                'phase'    => 'launch',
                'platform' => 'facebook',
                'offset'   => -60,
                'content'  => "⏳ Plus que 60 jours pour vous inscrire au FIBB {{edition}} !\n\nNe manquez pas votre place — les inscriptions ferment bientôt.\n\n👉 {{hashtags}}",
            ],
            'launch_early_bird_ig' => [
                'phase'    => 'launch',
                'platform' => 'instagram',
                'offset'   => -60,
                'content'  => "60 jours avant le FIBB {{edition}} 🃏\nInscris-toi maintenant !\n{{hashtags}}",
                'image_url' => '',
            ],

            /* ─── PHASE PRE_EVENT ─── */
            'pre_countdown_30_fb' => [
                'phase'    => 'pre_event',
                'platform' => 'facebook',
                'offset'   => -30,
                'content'  => "🗓 Plus que 30 jours avant le FIBB {{edition}} !\n\nÊtes-vous prêt(e) ? Préparez vos stratégies — le niveau sera au rendez-vous.\n\n{{hashtags}}",
            ],
            'pre_countdown_30_ig' => [
                'phase'    => 'pre_event',
                'platform' => 'instagram',
                'offset'   => -30,
                'content'  => "J-30 avant le FIBB {{edition}} 🔥\nPrêt(e) pour le plus grand festival de bridge du Sud-Ouest ?\n{{hashtags}}",
                'image_url' => '',
            ],
            'pre_champion_highlight_fb' => [
                'phase'    => 'pre_event',
                'platform' => 'facebook',
                'offset'   => -21,
                'content'  => "🏆 Spotlight — Découvrez les champions qui seront au FIBB {{edition}} !\n\nDes joueurs de renommée internationale seront présents cette année.\n\n{{hashtags}}",
            ],
            'pre_champion_highlight_ig' => [
                'phase'    => 'pre_event',
                'platform' => 'instagram',
                'offset'   => -21,
                'content'  => "Les champions du FIBB {{edition}} arrivent 🃏✨\n{{hashtags}}",
                'image_url' => '',
            ],
            'pre_practical_info_fb' => [
                'phase'    => 'pre_event',
                'platform' => 'facebook',
                'offset'   => -14,
                'content'  => "📍 Informations pratiques pour le FIBB {{edition}} :\n\n📍 Lieu : Bordeaux (détails sur le site)\n🚗 Parking disponible sur place\n📅 {{festival_date}}\n📋 Programme complet sur notre site\n\n{{hashtags}}",
            ],
            'pre_practical_info_li' => [
                'phase'    => 'pre_event',
                'platform' => 'linkedin',
                'offset'   => -14,
                'content'  => "Le FIBB {{edition}} approche ! Rejoignez-nous pour plusieurs jours de compétition de haut niveau à Bordeaux.\n\nPartenaires, sponsors et professionnels du sport, c'est l'occasion idéale de visibilité.\n\n#bridge #bordeaux #sponsoring #sport",
            ],
            'pre_countdown_7_fb' => [
                'phase'    => 'pre_event',
                'platform' => 'facebook',
                'offset'   => -7,
                'content'  => "⏰ J-7 avant le FIBB {{edition}} !\n\nDernier appel pour les inscriptions tardives. On vous attend nombreux à Bordeaux !\n\n{{hashtags}}",
            ],
            'pre_countdown_7_ig' => [
                'phase'    => 'pre_event',
                'platform' => 'instagram',
                'offset'   => -7,
                'content'  => "J-7 ⏰ FIBB {{edition}}\nLa semaine prochaine, c'est le grand départ ! 🃏\n{{hashtags}}",
                'image_url' => '',
            ],
            'pre_eve_fb' => [
                'phase'    => 'pre_event',
                'platform' => 'facebook',
                'offset'   => -1,
                'content'  => "🌟 Demain, le FIBB {{edition}} commence !\n\nNous avons hâte de vous retrouver tous à Bordeaux. Que la meilleure équipe gagne ! 🃏\n\n{{hashtags}}",
            ],

            /* ─── PHASE DURING ─── */
            'during_day1_open_fb' => [
                'phase'    => 'during',
                'platform' => 'facebook',
                'offset'   => 0,
                'content'  => "🎉 C'est parti ! Le FIBB {{edition}} est officiellement lancé !\n\nDes joueurs de toute l'Europe sont réunis à Bordeaux pour cette {{edition}}e édition mémorable.\n\n{{hashtags}}",
            ],
            'during_day1_open_ig' => [
                'phase'    => 'during',
                'platform' => 'instagram',
                'offset'   => 0,
                'content'  => "Le FIBB {{edition}} est lancé ! 🎉🃏\nQue la compétition commence !\n{{hashtags}}",
                'image_url' => '',
            ],
            'during_day1_results_fb' => [
                'phase'    => 'during',
                'platform' => 'facebook',
                'offset'   => 0,
                'content'  => "📊 Fin de la première journée du FIBB {{edition}} !\n\nRetrouvez les résultats et les tables de leaders sur notre site.\n\n{{hashtags}}",
            ],
            'during_day2_midpoint_fb' => [
                'phase'    => 'during',
                'platform' => 'facebook',
                'offset'   => 1,
                'content'  => "📈 Mi-parcours au FIBB {{edition}} !\n\nLes tables de leaders se dessinent. Les équipes donnent tout pour une place en finale.\n\n{{hashtags}}",
            ],
            'during_day2_atmosphere_ig' => [
                'phase'    => 'during',
                'platform' => 'instagram',
                'offset'   => 1,
                'content'  => "L'ambiance est incroyable au FIBB {{edition}} ! 🃏🔥\n{{hashtags}}",
                'image_url' => '',
            ],
            'during_day2_linkedin_update' => [
                'phase'    => 'during',
                'platform' => 'linkedin',
                'offset'   => 1,
                'content'  => "Le FIBB {{edition}} bat son plein à Bordeaux ! La compétition est serrée et le niveau de jeu est exceptionnel cette année. Merci à tous nos partenaires pour leur soutien.\n\n#bridge #bordeaux #fibb",
            ],
            'during_day3_finale_fb' => [
                'phase'    => 'during',
                'platform' => 'facebook',
                'offset'   => 2,
                'content'  => "🏆 La grande finale du FIBB {{edition}} se déroule cet après-midi !\n\nSuivez l'événement en direct — les champions vont se révéler !\n\n{{hashtags}}",
            ],
            'during_day3_finale_ig' => [
                'phase'    => 'during',
                'platform' => 'instagram',
                'offset'   => 2,
                'content'  => "La finale du FIBB {{edition}} aujourd'hui ! 🏆🃏\nQui sera champion cette année ?\n{{hashtags}}",
                'image_url' => '',
            ],

            /* ─── PHASE POST_EVENT ─── */
            'post_results_fb' => [
                'phase'    => 'post_event',
                'platform' => 'facebook',
                'offset'   => 3,
                'content'  => "🏆 Palmarès du FIBB {{edition}} !\n\nFélicitations à tous les lauréats de cette {{edition}}e édition. Retrouvez le palmarès complet sur notre site.\n\n{{hashtags}}",
            ],
            'post_results_ig' => [
                'phase'    => 'post_event',
                'platform' => 'instagram',
                'offset'   => 3,
                'content'  => "Palmarès FIBB {{edition}} ! 🏆🃏\nFélicitations aux champions !\n{{hashtags}}",
                'image_url' => '',
            ],
            'post_results_li' => [
                'phase'    => 'post_event',
                'platform' => 'linkedin',
                'offset'   => 4,
                'content'  => "Le FIBB {{edition}} s'est achevé avec succès ! Un grand merci à tous nos partenaires, participants, bénévoles et à la ville de Bordeaux pour avoir rendu cet événement exceptionnel possible.\n\nRendez-vous pour la prochaine édition !\n\n#bridge #bordeaux #sport #fibb",
            ],
            'post_gallery_fb' => [
                'phase'    => 'post_event',
                'platform' => 'facebook',
                'offset'   => 5,
                'content'  => "📸 L'album photo du FIBB {{edition}} est en ligne !\n\nRevivez les meilleurs moments de cette {{edition}}e édition — retrouvez toutes les photos sur notre site.\n\n{{hashtags}}",
            ],
            'post_thankyou_fb' => [
                'phase'    => 'post_event',
                'platform' => 'facebook',
                'offset'   => 7,
                'content'  => "❤️ Un immense merci à tous !\n\nMerci aux participants, bénévoles, sponsors et à toute l'équipe organisatrice du FIBB {{edition}}. Votre engagement rend ce festival unique.\n\n{{hashtags}}",
            ],
            'post_next_edition_fb' => [
                'phase'    => 'post_event',
                'platform' => 'facebook',
                'offset'   => 14,
                'content'  => "🗓 Rendez-vous pour la prochaine édition du FIBB !\n\nOn repart sur une nouvelle aventure — restez connectés pour les premières annonces.\n\n{{hashtags}}",
            ],
            'post_next_edition_ig' => [
                'phase'    => 'post_event',
                'platform' => 'instagram',
                'offset'   => 14,
                'content'  => "À bientôt pour la prochaine édition du FIBB ! 🃏✨\n{{hashtags}}",
                'image_url' => '',
            ],
        ];
    }

    public static function get_all(): array {
        return self::raw();
    }

    public static function get_by_phase( string $phase ): array {
        return array_filter( self::raw(), fn( $t ) => $t['phase'] === $phase );
    }

    public static function get_by_slug( string $slug ): ?array {
        return self::raw()[ $slug ] ?? null;
    }

    public static function import_to_db( string $slug, string $festival_date_ymd ): int {
        $tpl = self::get_by_slug( $slug );
        if ( ! $tpl ) return 0;

        $settings = get_option( FIBB_COMM_OPTION, [] );
        $edition  = $settings['festival_edition'] ?? '';
        $hashtags = self::hashtags_for( $tpl['platform'], $settings );

        $offset       = (int) ( $tpl['offset'] ?? 0 );
        $scheduled_ts = strtotime( $festival_date_ymd ) + $offset * DAY_IN_SECONDS;
        $scheduled_at = gmdate( 'Y-m-d', $scheduled_ts ) . ' 09:00:00';

        $content = str_replace(
            [ '{{edition}}', '{{festival_date}}', '{{hashtags}}' ],
            [ $edition, gmdate( 'd/m/Y', strtotime( $festival_date_ymd ) ), $hashtags ],
            $tpl['content']
        );

        return (int) FIBB_Comm_DB::insert_post( [
            'platform'      => $tpl['platform'],
            'content'       => $content,
            'image_url'     => $tpl['image_url'] ?? null,
            'phase'         => $tpl['phase'],
            'template_slug' => $slug,
            'scheduled_at'  => $scheduled_at,
            'status'        => 'draft',
        ] );
    }

    public static function import_all( string $festival_date_ymd ): int {
        $count = 0;
        foreach ( array_keys( self::raw() ) as $slug ) {
            if ( self::import_to_db( $slug, $festival_date_ymd ) ) {
                $count++;
            }
        }
        return $count;
    }

    public static function hashtags_for( string $platform, array $settings ): string {
        $key = "hashtags_{$platform}";
        return $settings[ $key ] ?? '#FIBB #bridge #Bordeaux';
    }
}
