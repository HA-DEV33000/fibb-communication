<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = get_option( FIBB_COMM_OPTION, [] );
$saved    = isset( $_GET['platforms-saved'] );

$meta_expiry     = (int) ( $settings['meta_token_expiry'] ?? 0 );
$meta_token_type = $settings['meta_token_type'] ?? '';
$meta_days_left  = $meta_expiry ? (int) round( ( $meta_expiry - time() ) / DAY_IN_SECONDS ) : null;
?>
<?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>Paramètres des plateformes enregistrés.</p></div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field( 'fibb_comm_platforms', 'fibb_platforms_nonce' ); ?>
    <input type="hidden" name="fibb_comm_action" value="save_platforms">

    <!-- META (Facebook + Instagram) -->
    <div class="fibb-settings-section">
        <h3>🔵 Meta — Facebook &amp; Instagram</h3>
        <p style="color:#666;font-size:13px;">Utilisez un <strong>Page Access Token permanent</strong> généré via le bouton ci-dessous. Ce token ne expire pas sauf révocation. La page Instagram Business doit être liée à votre page Facebook dans Meta Business Suite.</p>

        <?php if ( null !== $meta_days_left && $meta_days_left <= 0 ) : ?>
            <div class="notice notice-error inline" style="margin:0 0 12px;">
                <p>🔴 Votre token Meta a <strong>expiré</strong>. Utilisez le bouton <em>Obtenir un token permanent</em> ci-dessous.</p>
            </div>
        <?php elseif ( null !== $meta_days_left && $meta_days_left <= 7 ) : ?>
            <div class="notice notice-warning inline" style="margin:0 0 12px;">
                <p>⚠️ Votre token Meta expire dans <strong><?php echo esc_html( $meta_days_left ); ?> jour(s)</strong>. Renouvelez-le dès que possible.</p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="meta_page_id">Facebook Page ID</label></th>
                <td>
                    <input type="text" id="meta_page_id" name="meta_page_id"
                           value="<?php echo esc_attr( $settings['meta_page_id'] ?? '' ); ?>"
                           class="regular-text" placeholder="Ex : 123456789012345">
                    <p class="description">L'identifiant numérique de votre page Facebook.</p>
                </td>
            </tr>
            <tr>
                <th><label for="meta_ig_user_id">Instagram Business Account ID</label></th>
                <td>
                    <input type="text" id="meta_ig_user_id" name="meta_ig_user_id"
                           value="<?php echo esc_attr( $settings['meta_ig_user_id'] ?? '' ); ?>"
                           class="regular-text" placeholder="Ex : 987654321098765">
                    <p class="description">L'ID de votre compte professionnel Instagram (IG User ID).</p>
                </td>
            </tr>
            <tr>
                <th><label for="meta_page_token">Page Access Token</label></th>
                <td>
                    <input type="password" id="meta_page_token" name="meta_page_token"
                           value="<?php echo esc_attr( $settings['meta_page_token'] ?? '' ); ?>"
                           class="large-text" autocomplete="off">
                    <p class="description">
                        Token permanent. Laissez vide pour conserver le token existant.
                        <?php if ( $meta_token_type ) : ?>
                            <br>Type actuel : <strong><?php echo esc_html( $meta_token_type === 'page' ? 'Page Access Token (permanent ✓)' : ( $meta_token_type === 'long_lived' ? 'Long-lived User Token (~60 jours)' : 'Manuel' ) ); ?></strong>
                            <?php if ( $meta_expiry && $meta_days_left > 0 ) : ?>
                                — expire le <strong><?php echo esc_html( gmdate( 'd/m/Y', $meta_expiry ) ); ?></strong>
                            <?php elseif ( $meta_token_type === 'page' ) : ?>
                                — <strong>ne expire pas</strong>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="meta_app_id">App ID Facebook</label></th>
                <td>
                    <input type="text" id="meta_app_id" name="meta_app_id"
                           value="<?php echo esc_attr( $settings['meta_app_id'] ?? '' ); ?>"
                           class="regular-text" placeholder="Ex : 123456789012">
                    <p class="description">ID de votre application Facebook (<a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers</a>). Requis pour générer un token permanent.</p>
                </td>
            </tr>
            <tr>
                <th><label for="meta_app_secret">App Secret</label></th>
                <td>
                    <input type="password" id="meta_app_secret" name="meta_app_secret"
                           value="<?php echo esc_attr( $settings['meta_app_secret'] ?? '' ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description">Secret de l'application. Laissez vide pour conserver le secret existant.</p>
                </td>
            </tr>
        </table>

        <button type="button" class="fibb-test-btn fibb-btn fibb-btn-outline" data-platform="facebook">
            Tester la connexion Facebook/Instagram
        </button>
        <span id="fibb-test-result-facebook" class="fibb-test-result"></span>

        <!-- Génération token permanent -->
        <div style="margin-top:16px;padding:16px;background:#f0f6fc;border:1px solid #c5d8f0;border-radius:4px;">
            <h4 style="margin:0 0 8px;">🔄 Obtenir un token permanent (Page Access Token)</h4>
            <p style="color:#444;font-size:13px;margin:0 0 12px;">
                Si votre token expire régulièrement, suivez ces étapes pour obtenir un token permanent :<br>
                1. Allez dans <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a><br>
                2. Sélectionnez votre App, cochez les permissions <code>pages_manage_posts</code> et <code>instagram_basic</code><br>
                3. Générez un <strong>User Access Token</strong> (pas un Page Token)<br>
                4. Collez-le ci-dessous et cliquez <em>Générer token permanent</em> — le Page Token non-expirant sera sauvegardé automatiquement
            </p>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="fibb-user-token-input"
                       placeholder="Coller le User Access Token ici…"
                       style="flex:1;min-width:280px;font-family:monospace;font-size:12px;"
                       autocomplete="off">
                <button type="button" id="fibb-refresh-meta-token-btn" class="button button-primary">
                    Générer token permanent
                </button>
            </div>
            <div id="fibb-refresh-meta-result" style="margin-top:8px;font-size:13px;min-height:20px;"></div>
        </div>
    </div>

    <!-- LINKEDIN -->
    <div class="fibb-settings-section">
        <h3>🔷 LinkedIn</h3>
        <p style="color:#666;font-size:13px;">Le token LinkedIn expire après <strong>60 jours</strong>. Une alerte s'affiche à J-7. Générez un nouveau token via OAuth 2.0 dans l'application LinkedIn Developer.</p>

        <?php
        $linkedin_api  = new FIBB_Comm_LinkedIn_API();
        $days_left     = $linkedin_api->days_until_expiry();
        $expiry_stored = $settings['linkedin_token_expiry'] ?? 0;
        ?>

        <?php if ( $expiry_stored && $days_left >= 0 && $days_left <= 7 ) : ?>
            <div class="notice notice-warning inline">
                <p>⚠️ Votre token LinkedIn expire dans <strong><?php echo esc_html( $days_left ); ?> jour(s)</strong>. Renouvelez-le dès que possible.</p>
            </div>
        <?php elseif ( $expiry_stored && $days_left < 0 ) : ?>
            <div class="notice notice-error inline">
                <p>🔴 Votre token LinkedIn a <strong>expiré</strong>. Les publications LinkedIn échoueront jusqu'au renouvellement.</p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="linkedin_org_id">Organization ID</label></th>
                <td>
                    <input type="text" id="linkedin_org_id" name="linkedin_org_id"
                           value="<?php echo esc_attr( $settings['linkedin_org_id'] ?? '' ); ?>"
                           class="regular-text" placeholder="Ex : 12345678">
                    <p class="description">L'identifiant numérique de votre page entreprise LinkedIn.</p>
                </td>
            </tr>
            <tr>
                <th><label for="linkedin_token">Access Token</label></th>
                <td>
                    <input type="password" id="linkedin_token" name="linkedin_token"
                           value="<?php echo esc_attr( $settings['linkedin_token'] ?? '' ); ?>"
                           class="large-text" autocomplete="off">
                    <p class="description">Token OAuth 2.0. Laissez vide pour conserver le token existant.</p>
                </td>
            </tr>
            <tr>
                <th><label for="linkedin_token_expiry">Date d'expiration du token</label></th>
                <td>
                    <input type="date" id="linkedin_token_expiry_date" name="linkedin_token_expiry_date"
                           value="<?php echo $expiry_stored ? esc_attr( gmdate( 'Y-m-d', $expiry_stored ) ) : ''; ?>"
                           class="regular-text">
                    <?php if ( $expiry_stored && $days_left >= 0 ) : ?>
                        <span class="fibb-token-alert <?php echo $days_left <= 7 ? 'critical' : ''; ?>">
                            Expire dans <?php echo esc_html( $days_left ); ?> jour(s)
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <button type="button" class="fibb-test-btn fibb-btn fibb-btn-outline" data-platform="linkedin">
            Tester la connexion LinkedIn
        </button>
        <span id="fibb-test-result-linkedin" class="fibb-test-result"></span>
    </div>

    <p class="submit">
        <input type="submit" class="button button-primary" value="Enregistrer les plateformes">
    </p>
</form>
