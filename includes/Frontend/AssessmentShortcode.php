<?php
namespace WpEsg\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AssessmentShortcode {

    public function __construct() {
        // FIX BUG #2: Avvio sessione protetto. Non scatta su REST API, AJAX e pannelli admin di WP
        add_action( 'init', function() {
            if ( ! session_id() 
                 && ! ( defined('REST_REQUEST') && REST_REQUEST ) 
                 && ! ( defined('DOING_AJAX') && DOING_AJAX ) 
                 && ! is_admin() ) { 
                session_start(); 
            }
        }, 1 );
        
        add_shortcode( 'wp_esg_dynamic_assessment', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode( $atts ) {
        $engine_mode = get_option( 'wp_esg_engine_mode', 'standalone' );

        if ( ! isset( $_GET['esg_action'] ) && ! isset( $_POST['action'] ) && ! isset( $_SESSION['esg_verified_code'] ) && ! is_user_logged_in() ) {
            return $this->render_landing_button( $engine_mode );
        }

        if ( 'multitenant_db' === $engine_mode ) {
            if ( ! is_user_logged_in() ) {
                wp_redirect( wp_login_url( get_permalink() ) );
                exit;
            }
        } else {
            if ( isset( $_POST['esg_submit_code'] ) ) {
                $inserted_code = sanitize_text_field( $_POST['access_code'] );
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

        // Intercettazione e instradamento azioni form
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_screening' ) {
            return $this->process_screening_and_route();
        }

        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_openesea' ) {
            return $this->process_openesea_and_route();
        }

        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_pgs' ) {
            return $this->process_pgs_and_route();
        }

        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_products' ) {
            return $this->process_products_and_route();
        }

        // Routing a stati tramite parametro GET
        $current_step = isset($_GET['esg_step']) ? sanitize_text_field($_GET['esg_step']) : 'history';
        
        switch ( $current_step ) {
            case 'history':
                return $this->render_history_dashboard($engine_mode); 
            case 'screening':
                return $this->render_screening_form();
            case 'hub':
                return $this->render_assessment_hub();
            case 'openesea':
                return $this->render_openesea_form();
            case 'pgs':
                return $this->render_pgs_form();
            case 'products':
                return $this->render_products_form();
            case 'view_archive':
                return $this->render_archive_detail(); 
            default:
                return $this->render_history_dashboard($engine_mode);
        }
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
            <h3><?php esc_html_e( 'Verify Invitation Code', 'wp-esg' ); ?></h3>
            <p style="color:#646970; font-size:14px; line-height:1.5;">
                <?php esc_html_e( 'Please enter the unique access key provided by your organization manager to unlock your reporting environment.', 'wp-esg' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( remove_query_arg('esg_action') ); ?>">
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

    private function resolve_active_tax_id($engine_mode) {
        if ( 'multitenant_db' === $engine_mode && class_exists('WpEsg\Storage\UserCompanyLinker') ) {
            return \WpEsg\Storage\UserCompanyLinker::getCompanyTaxIdByUserId( get_current_user_id() );
        }
        return $_SESSION['esg_company_tax_id'] ?? '';
    }

    private function render_history_dashboard($engine_mode) {
        global $wpdb;
        $table = $wpdb->prefix . 'esg_assessments';
        $tax_id = $this->resolve_active_tax_id($engine_mode);

        if ( ! empty($tax_id) ) {
            $records = $wpdb->get_results( $wpdb->prepare( "SELECT id, company_tax_id, balance_year, workflow_status FROM $table WHERE company_tax_id = %s ORDER BY balance_year DESC", $tax_id ) );
        } else {
            $records = $wpdb->get_results( "SELECT id, company_tax_id, balance_year, workflow_status FROM $table ORDER BY balance_year DESC" );
        }
        
        ob_start();
        ?>
        <div class="esg-history-box" style="max-width: 700px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f0f0f1; padding-bottom:15px;">
                <h2 style="margin:0; color:#1d2327;"><?php esc_html_e( 'Corporate ESG Reporting Archive', 'wp-esg' ); ?></h2>
                <a href="<?php echo esc_url(add_query_arg('esg_step', 'screening', get_permalink())); ?>" class="button" style="background:#46b450; color:#fff; text-decoration:none; padding:10px 18px; font-weight:bold; border-radius:4px; font-size:14px;"><?php esc_html_e( '+ New Campaign Assessment', 'wp-esg' ); ?></a>
            </div>

            <p style="color:#646970; font-size:14px; margin-bottom:20px;">
                <?php esc_html_e( 'Review previously locked accounting disclosure campaigns or launch a new dynamic validation lifecycle profile.', 'wp-esg' ); ?>
            </p>

            <?php if ( empty( $records ) ) : ?>
                <div style="text-align:center; padding:30px; background:#f6f7f7; border-radius:4px; border:1px dashed #c3c4c7; color:#646970;">
                    <?php esc_html_e( 'No historical assessments found. Click the button above to initialize your first framework.', 'wp-esg' ); ?>
                </div>
            <?php else : ?>
                <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:14px;">
                    <thead>
                        <tr style="background:#f6f7f7; text-align:left; border-bottom:2px solid #dcdcde;">
                            <th style="padding:12px;"><?php esc_html_e( 'Target Year', 'wp-esg' ); ?></th>
                            <th style="padding:12px;"><?php esc_html_e( 'Company Registry ID', 'wp-esg' ); ?></th>
                            <th style="padding:12px;"><?php esc_html_e( 'Status', 'wp-esg' ); ?></th>
                            <th style="padding:12px; text-align:right;"><?php esc_html_e( 'Actions', 'wp-esg' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $records as $row ) : 
                            $is_submitted = ( 'Submitted' === $row->workflow_status || 'Pending Review' === $row->workflow_status || 'Completed' === $row->workflow_status );
                            ?>
                            <tr style="border-bottom:1px solid #f0f0f1;">
                                <td style="padding:12px; font-weight:bold; color:#1d2327;">📈 <?php echo esc_html( $row->balance_year ); ?></td>
                                <td style="padding:12px; color:#50575e;"><code><?php echo esc_html( $row->company_tax_id ); ?></code></td>
                                <td style="padding:12px;">
                                    <?php if ( $is_submitted ) : ?>
                                        <span style="background:#d1e7dd; color:#0f5132; padding:4px 8px; font-size:12px; font-weight:bold; border-radius:3px;">🔒 Locked (Submitted)</span>
                                    <?php else : ?>
                                        <span style="background:#fef3cd; color:#664d03; padding:4px 8px; font-size:12px; font-weight:bold; border-radius:3px;">📝 Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px; text-align:right;">
                                    <?php if ( $is_submitted ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'esg_step' => 'view_archive', 'report_id' => $row->id ), get_permalink() ) ); ?>" style="color:#2271b1; text-decoration:none; font-weight:bold;"><?php esc_html_e( 'View Report &raquo;', 'wp-esg' ); ?></a>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'esg_step' => 'hub', 'resume_id' => $row->id ), get_permalink() ) ); ?>" style="color:#bc0b0b; text-decoration:none; font-weight:bold;"><?php esc_html_e( 'Resume &raquo;', 'wp-esg' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_archive_detail() {
        global $wpdb;
        $report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
        $table = $wpdb->prefix . 'esg_assessments';

        $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $report_id ) );
        if ( ! $report ) {
            return '<p style="color:#dc3232; text-align:center;">Report not found.</p>';
        }

        $payload = json_decode( $report->raw_answers, true );
        ob_start();
        ?>
        <div class="esg-archive-detail-box" style="max-width: 650px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:20px; border-bottom:1px solid #f0f0f1; padding-bottom:15px;">
                <a href="<?php echo esc_url( add_query_arg('esg_step', 'history', get_permalink()) ); ?>" style="color:#2271b1; text-decoration:none; font-size:14px;">&larr; Back to Historical Archive</a>
                <h2 style="margin:10px 0 5px 0; color:#1d2327;">Historical Assessment Audit (<?php echo esc_html($report->balance_year); ?>)</h2>
                <span style="background:#d1e7dd; color:#0f5132; padding:4px 8px; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase;">Official Signed Off Document</span>
            </div>

            <div style="background:#f6f7f7; padding:15px; border-radius:4px; margin-bottom:25px; font-size:14px; line-height:1.5;">
                <strong>Company Tax Identification:</strong> <code><?php echo esc_html($report->company_tax_id); ?></code><br>
                <strong>ATECO Industrial Segment:</strong> <code><?php echo esc_html($report->business_code); ?></code><br>
                <strong>Calculated Framework Scope:</strong> <?php echo esc_html($report->company_size); ?>
            </div>

            <div style="margin-bottom:25px; border-bottom:1px solid #f0f0f1; padding-bottom:20px;">
                <h4 style="color:#2271b1; margin-bottom:10px;">1. OpenESEA Core Responses (Audited Matrix)</h4>
                <p style="font-size:14px; margin-bottom:5px; font-weight:bold;">Q: Does your enterprise actively monitor circular economy protocols or resource recycling targets?</p>
                <p style="font-size:14px; color:#1d2327; background:#fafafa; padding:8px; border-left:3px solid #2271b1; text-transform:uppercase;">
                    <strong>Answer:</strong> <?php echo esc_html($payload['openesea_framework']['openesea_q1'] ?? 'Not Answered'); ?>
                </p>
            </div>

            <div style="margin-bottom:25px; border-bottom:1px solid #f0f0f1; padding-bottom:20px;">
                <h4 style="color:#2c3338; margin-bottom:10px;">2. Network PGS Evaluation</h4>
                <p style="font-size:14px; margin-bottom:5px; font-weight:bold;">Q: Does your enterprise run local community support guidelines or corporate code-of-conduct transparency policies?</p>
                <p style="font-size:14px; color:#1d2327; background:#fafafa; padding:8px; border-left:3px solid #2c3338; text-transform:uppercase;">
                    <strong>Answer:</strong> <?php echo esc_html($payload['pgs_framework']['pgs_q1'] ?? 'Not Answered'); ?>
                </p>
            </div>

            <div style="margin-bottom:20px;">
                <h4 style="color:#46b450; margin-bottom
