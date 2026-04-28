<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = get_option( FIBB_COMM_OPTION, [] );
$saved    = isset( $_GET['platforms-saved'] );
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
        <p style="color:#666;font-size:13px;">Utilisez un <strong>Page Access Token long-lived</strong> généré via le Meta Graph API Explorer. Ce token ne expire pas sauf révocation. La page Instagram Business doit être liée à votre page Facebook dans Meta Business Suite.</p>

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
                    <p class="description">Token long-lived. Laissez vide pour conserver le token existant si vous ne souhaitez pas le modifier.</p>
                </td>
            </tr>
        </table>

        <button type="button" class="fibb-test-btn fibb-btn fibb-btn-outline" data-platform="facebook">
            Tester la connexion Facebook/Instagram
        </button>
        <span id="fibb-test-result-facebook" class="fibb-test-result"></span>
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
