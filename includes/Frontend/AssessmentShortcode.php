<?php
namespace WpEsg\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AssessmentShortcode {

    public function __construct() {
        if ( ! session_id() ) {
            add_action( 'init', function() {
                if ( ! session_id() ) { session_start(); }
            }, 1 );
        }
        add_shortcode( 'wp_esg_dynamic_assessment', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode( $atts ) {
        $engine_mode = get_option( 'wp_esg_engine_mode', 'standalone' );

        // STATE 0: The Unified Landing Page CTA Button
        if ( ! isset( $_GET['esg_action'] ) && ! isset( $_POST['action'] ) && ! isset( $_SESSION['esg_verified_code'] ) && ! is_user_logged_in() ) {
            return $this->render_landing_button( $engine_mode );
        }

        // INTERFACE ROUTING: USERS DB MODE (MULTITENANT)
        if ( 'multitenant_db' === $engine_mode ) {
            if ( ! is_user_logged_in() ) {
                wp_redirect( wp_login_url( get_permalink() ) );
                exit;
            }
        } 
        
        // INTERFACE ROUTING: STANDALONE MODE (ACCESS CODE PROTECTED)
        else {
            if ( isset( $_POST['esg_submit_code'] ) ) {
                $inserted_code = sanitize_text_field( $_POST['access_code'] );
                
                // 🔒 MASTER CODE CHECK: Matching against the default installation key 'ESG2026'
                if ( strtoupper( trim( $inserted_code ) ) === 'ESG2026' ) {
                    $_SESSION['esg_verified_code'] = true;
                } else {
                    echo '<div style="max-width:480px; margin:auto;"><p style="color:#dc3232; font-weight:bold; text-align:center; margin-bottom:15px; padding:10px; background:#fbeaeaea; border-radius:4px;">' . esc_html__( '❌ Invalid access code. Please use the default key: ESG2026', 'wp-esg' ) . '</p></div>';
                }
            }

            if ( ! isset( $_SESSION['esg_verified_code'] ) ) {
                return $this->render_access_code_form();
            }
        }

        // SCREENING & QUESTIONNAIRE BLOCK
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_screening' ) {
            return $this->process_screening_and_render_questions();
        }

        return $this->render_screening_form();
    }

    private function render_landing_button( $engine_mode ) {
        $target_url = ( 'standalone' === $engine_mode ) ? add_query_arg( 'esg_action', 'enter_code', get_permalink() ) : get_permalink();
        $btn_text   = ( 'multitenant_db' === $engine_mode ) ? __( 'Log In via Network Account &rarr;', 'wp-esg' ) : __( 'Begin ESG Assessment &rarr;', 'wp-esg' );
        
        ob_start();
        ?>
        <div class="esg-landing-cta" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto; font-family: sans-serif;">
            <h2 style="color: #1d2327; margin-bottom: 15px;"><?php esc_html_e( 'Corporate ESG Assessment Platform', 'wp-esg' ); ?></h2>
            <p style="color: #646970; font-size: 15px; line-height: 1.6; margin-bottom: 30px;">
                <?php esc_html_e( 'Start your corporate sustainability disclosure reporting. The engine automatically provisions custom environmental, social, and governance metrics tailored to your operational profiles.', 'wp-esg' ); ?>
            </p>
            <a href="<?php echo esc_url( $target_url ); ?>" class="button button-primary" style="display: inline-block; padding: 14px 35px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(34,113,177,0.2); transition: background 0.2s;">
                <?php echo wp_kses_post( $btn_text ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_access_code_form() {
        ob_start();
        ?>
        <div class="esg-access-box" style="max-width: 480px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: sans-serif;">
            <h3 style="margin-top:0; color:#1d2327;"><?php esc_html_e( 'Verify Invitation Code', 'wp-esg' ); ?></h3>
            <p style="color:#646970; font-size:14px; line-height:1.5;">
                <?php esc_html_e( 'Please enter the unique access key provided by your organization manager to unlock your reporting environment.', 'wp-esg' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( remove_query_arg('esg_action') ); ?>" style="margin-top:20px;">
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-weight:bold; margin-bottom:8px;"><?php esc_html_e( 'ESG Access Key:', 'wp-esg' ); ?></label>
                    <input type="text" name="access_code" placeholder="e.g., ESG2026" required style="width:100%; padding:10px; border:1px solid #8c8f94; border-radius:4px; box-sizing:border-box;">
                    <span style="display:block; margin-top:6px; color:#646970; font-size:12px; font-style:italic;">
                        <?php esc_html_e( 'Tip: Use the default installation key "ESG2026" to open the sandbox.', 'wp-esg' ); ?>
                    </span>
                </div>
                <input type="submit" name="esg_submit_code" class="button button-primary" value="<?php esc_attr_e( 'Validate & Authorize', 'wp-esg' ); ?>" style="width:100%; padding:12px; background:#2271b1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_screening_form() {
        ob_start();
        ?>
        <div class="esg-screening-box" style="max-width: 550px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <h2><?php esc_html_e( 'Pre-Assessment Screening', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:14px;"><?php esc_html_e( 'Provide preliminary baseline criteria to calibrate your contextual disclosure frameworks.', 'wp-esg' ); ?></p>
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_screening">
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Country Jurisdiction:', 'wp-esg' ); ?></label>
                    <select name="company_country" required style="width:100%; padding:8px;"><option value="IT"><?php esc_html_e( 'Italy', 'wp-esg' ); ?></option></select>
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Tax Identifier / VAT ID:', 'wp-esg' ); ?></label>
                    <input type="text" name="company_tax_id" required placeholder="e.g., 12345678901" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Economic Activity Code (ATECO):', 'wp-esg' ); ?></label>
                    <input type="text" name="business_code" required placeholder="e.g., A.01.13.11" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Total Employee Count (FTE):', 'wp-esg' ); ?></label>
                    <input type="number" name="employees_count" required placeholder="e.g., 12" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Reporting Accounting Year:', 'wp-esg' ); ?></label>
                    <input type="number" name="balance_year" value="2026" required style="width:100%; padding:8px;">
                </p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Generate Dynamic Questionnaire &rarr;', 'wp-esg' ); ?>" style="margin-top:10px; padding:10px 20px; background:#2271b1; color:#fff; border:
