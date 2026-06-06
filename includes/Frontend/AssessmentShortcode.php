<?php
namespace WpEsg\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AssessmentShortcode {

    public function __construct() {
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
        $no_auth_mode = get_option( 'wp_esg_no_auth_mode', '1' );

        if ( ! isset( $_GET['esg_action'] ) && ! isset( $_POST['action'] ) && ! isset( $_SESSION['esg_verified_code'] ) && ! is_user_logged_in() ) {
            return $this->render_landing_button( $no_auth_mode );
        }

        if ( '1' !== $no_auth_mode ) {
            if ( ! is_user_logged_in() ) {
                // FIX #2: usare meta refresh invece di wp_redirect() dentro uno shortcode
                $login_url = wp_login_url( get_permalink() );
                return '<meta http-equiv="refresh" content="0;url=' . esc_url( $login_url ) . '">';
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

            if ( ! isset( $_SESSION['esg_verified_code'] ) && ! is_user_logged_in() ) {
                return $this->render_access_code_form();
            }
        }

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

        $current_step = isset($_GET['esg_step']) ? sanitize_text_field($_GET['esg_step']) : 'history';
        
        switch ( $current_step ) {
            case 'history':
                return $this->render_history_dashboard($no_auth_mode); 
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
                return $this->render_history_dashboard($no_auth_mode);
        }
    }

    // ==========================================
    // HELPER: carica JSON framework con fallback lingua
    // ==========================================
    private function load_framework_json( string $relative_path ): array {
        $locale    = determine_locale();
        $lang_code = substr( $locale, 0, 2 );

        $lang_path = WP_ESG_PATH . "languages/{$lang_code}/{$relative_path}";
        $base_path = WP_ESG_PATH . "frameworks/{$relative_path}";

        if ( file_exists( $lang_path ) ) {
            $raw = file_get_contents( $lang_path );
        } elseif ( file_exists( $base_path ) ) {
            $raw = file_get_contents( $base_path );
        } else {
            return array();
        }

        return json_decode( $raw, true ) ?: array();
    }

    // ==========================================
    // HELPER: redirect sicuro dentro shortcode (meta refresh)
    // ==========================================
    private function safe_redirect( string $url ): string {
        return '<meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '">
                <p style="text-align:center;color:#646970;font-family:sans-serif;margin-top:30px;">
                    ' . esc_html__( 'Redirecting…', 'wp-esg' ) . '
                    <a href="' . esc_url( $url ) . '">' . esc_html__( 'Click here if not redirected.', 'wp-esg' ) . '</a>
                </p>';
    }

    // ==========================================
    // HELPER: renderizza un singolo campo in base al tipo
    // ==========================================
    private function render_field( string $field_id, array $field_def ): string {
        $label   = esc_html( $field_def['label'] ?? $field_def['text'] ?? $field_id );
        $help    = esc_html( $field_def['help'] ?? '' );
        $control = $field_def['control'] ?? '';
        $type    = $field_def['type'] ?? 'string';
        $required = ! empty( $field_def['required'] ) ? 'required' : '';
        $name    = esc_attr( $field_id );

        $html  = '<div style="margin-bottom:22px; border-bottom:1px solid #f0f0f1; padding-bottom:16px;">';
        $html .= '<label style="display:block; margin-bottom:6px; font-weight:bold;">' . $label . '</label>';

        if ( $help ) {
            $html .= '<span style="display:block; font-size:13px; color:#50575e; background:#f6f7f7; padding:8px 10px; border-left:3px solid #2271b1; margin-bottom:10px; font-style:italic;">ℹ️ ' . $help . '</span>';
        }

        if ( $type === 'bool' ) {
            $html .= '<label style="margin-right:15px;"><input type="radio" name="' . $name . '" value="yes" ' . $required . '> ' . esc_html__( 'Sì', 'wp-esg' ) . '</label>';
            $html .= '<label><input type="radio" name="' . $name . '" value="no"> ' . esc_html__( 'No', 'wp-esg' ) . '</label>';
        } elseif ( $control === 'textarea' ) {
            $html .= '<textarea name="' . $name . '" rows="3" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px; box-sizing:border-box;" ' . $required . '></textarea>';
        } elseif ( $type === 'enum' && ! empty( $field_def['options'] ) ) {
            $html .= '<select name="' . $name . '" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px;" ' . $required . '>';
            $html .= '<option value="">' . esc_html__( '— Seleziona —', 'wp-esg' ) . '</option>';
            foreach ( (array) $field_def['options'] as $opt ) {
                $html .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
            }
            $html .= '</select>';
        } elseif ( $type === 'int' || $type === 'float' ) {
            $step = ( $type === 'float' ) ? '0.01' : '1';
            $html .= '<input type="number" name="' . $name . '" step="' . $step . '" min="0" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px; box-sizing:border-box;" ' . $required . '>';
        } elseif ( $type === 'array' || $control === 'checkbox' ) {
            // array/checkbox: multi checkbox se options presenti, altrimenti textarea
            if ( ! empty( $field_def['options'] ) ) {
                foreach ( (array) $field_def['options'] as $opt ) {
                    $html .= '<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr( $opt ) . '"> ' . esc_html( $opt ) . '</label>';
                }
            } else {
                $html .= '<textarea name="' . $name . '" rows="2" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px; box-sizing:border-box;"></textarea>';
            }
        } else {
            // default: text input
            $html .= '<input type="text" name="' . $name . '" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px; box-sizing:border-box;" ' . $required . '>';
        }

        $html .= '</div>';
        return $html;
    }

    // ==========================================
    // RENDER LANDING / ACCESS CODE
    // ==========================================
    private function render_landing_button( $no_auth_mode ) {
        $target_url = ( '1' === $no_auth_mode ) ? add_query_arg( 'esg_action', 'enter_code', get_permalink() ) : get_permalink();
        $btn_text   = ( '1' !== $no_auth_mode ) ? __( 'Log In via Network Account &rarr;', 'wp-esg' ) : __( 'Begin ESG Assessment &rarr;', 'wp-esg' );
        
        ob_start();
        ?>
        <div class="esg-landing-cta" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto; font-family: sans-serif;">
            <h2 style="color: #1d2327; margin-bottom: 15px;"><?php esc_html_e( 'Corporate ESG Assessment Platform', 'wp-esg' ); ?></h2>
            <p style="color: #646970; font-size: 15px; line-height: 1.6; margin-bottom: 30px;">
                <?php esc_html_e( 'Start your corporate sustainability disclosure reporting. The engine automatically provisions custom environmental, social, and governance metrics tailored to your operational profiles.', 'wp-esg' ); ?>
            </p>
            <a href="<?php echo esc_url( $target_url ); ?>" class="button button-primary" style="display: inline-block; padding: 14px 35px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(34,113,177,0.2);">
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

    // ==========================================
    // HELPERS SESSIONE / TAX ID
    // ==========================================
    private function resolve_active_tax_id($no_auth_mode) {
        if ( '1' !== $no_auth_mode && class_exists('WpEsg\Storage\UserCompanyLinker') ) {
            return \WpEsg\Storage\UserCompanyLinker::getCompanyTaxIdByUserId( get_current_user_id() );
        }
        return $_SESSION['esg_company_tax_id'] ?? '';
    }

    // ==========================================
    // HISTORY DASHBOARD
    // ==========================================
    private function render_history_dashboard($no_auth_mode) {
        global $wpdb;
        $table  = $wpdb->prefix . 'esg_assessments';
        $tax_id = $this->resolve_active_tax_id($no_auth_mode);

        if ( ! empty($tax_id) ) {
            $records = $wpdb->get_results( $wpdb->prepare( "SELECT id, company_tax_id, balance_year, workflow_status FROM $table WHERE company_tax_id = %s ORDER BY balance_year DESC", $tax_id ) );
        } else {
            $records = $wpdb->get_results( "SELECT id, company_tax_id, balance_year, workflow_status FROM $table ORDER BY balance_year DESC" );
        }
        
        ob_start();
        ?>
        <div class="esg-history-box" style="max-width: 700px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="text-align:center; margin-bottom:30px; padding-bottom:25px; border-bottom:1px solid #f0f0f1;">
                <a href="<?php echo esc_url(add_query_arg('esg_step', 'screening', get_permalink())); ?>" class="button" style="background:#46b450; color:#fff; text-decoration:none; padding:12px 28px; font-weight:bold; border-radius:4px; font-size:15px; display:inline-block;"><?php esc_html_e( '+ New Campaign Assessment', 'wp-esg' ); ?></a>
            </div>

            <h2 style="margin:0 0 20px; color:#1d2327; font-size:18px;"><?php esc_html_e( 'ESG Assessment Campaign Archive', 'wp-esg' ); ?></h2>

            <?php if ( empty( $records ) ) : ?>
                <div style="text-align:center; padding:30px; background:#f6f7f7; border-radius:4px; border:1px dashed #c3c4c7; color:#646970;">
                    <?php esc_html_e( 'No previous assessments found. Click the button above to create your first ESG assessment.', 'wp-esg' ); ?>
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
                                        <a href="<?php echo esc_url( add_query_arg( array( 'esg_step' => 'view_archive', 'report_id' => $row->id ), get_permalink() ) ); ?>" style="color:#2271b1; text-decoration:none; font-weight:bold;"><button class="button button-small"><?php esc_html_e( 'View Report »', 'wp-esg' ); ?></button></a>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'esg_step' => 'hub', 'resume_id' => $row->id ), get_permalink() ) ); ?>" style="color:#bc0b0b; text-decoration:none; font-weight:bold;"><button class="button button-small button-primary"><?php esc_html_e( 'Resume »', 'wp-esg' ); ?></button></a>
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

    // ==========================================
    // ARCHIVE DETAIL
    // ==========================================
    private function render_archive_detail() {
        global $wpdb;
        $report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
        $table     = $wpdb->prefix . 'esg_assessments';

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
            </div>

            <div style="background:#f6f7f7; padding:15px; border-radius:4px; margin-bottom:25px; font-size:14px; line-height:1.5;">
                <strong>Company Tax Identification:</strong> <code><?php echo esc_html($report->company_tax_id); ?></code><br>
                <strong>ATECO Industrial Segment:</strong> <code><?php echo esc_html($report->business_code); ?></code><br>
                <strong>Calculated Framework Scope:</strong> <?php echo esc_html($report->company_size); ?>
            </div>

            <div style="margin-bottom:25px;">
                <h4 style="color:#2271b1;">1. OpenESEA Core Responses (Audited Matrix)</h4>
                <pre style="background:#f8f9fa; padding:10px; border-left:3px solid #2271b1; overflow-x:auto;"><?php print_r($payload['openesea_framework'] ?? []); ?></pre>
            </div>
            <div style="margin-bottom:25px;">
                <h4 style="color:#2c3338;">2. Network PGS Evaluation</h4>
                <pre style="background:#f8f9fa; padding:10px; border-left:3px solid #2c3338; overflow-x:auto;"><?php print_r($payload['pgs_framework'] ?? []); ?></pre>
            </div>
            <div style="margin-bottom:25px;">
                <h4 style="color:#46b450;">3. Vertical Product Self-Certifications</h4>
                <pre style="background:#f8f9fa; padding:10px; border-left:3px solid #46b450; overflow-x:auto;"><?php print_r($payload['products_framework'] ?? []); ?></pre>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // SCREENING FORM
    // ==========================================
    private function render_screening_form() {
        ob_start();
        ?>
        <div class="esg-screening-box" style="max-width: 550px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'history', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; <?php esc_html_e( 'Back to Archive', 'wp-esg' ); ?></a></div>
            <div style="color:#2271b1; font-weight:bold; font-size:12px; margin-bottom:10px; text-transform:uppercase;"><?php esc_html_e( 'Step 1 of 4: Setup', 'wp-esg' ); ?></div>
            <h2><?php esc_html_e( 'Pre-Assessment Screening', 'wp-esg' ); ?></h2>
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
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Legal Entity Type:', 'wp-esg' ); ?></label>
                    <input type="text" name="legal_entity_code" required placeholder="e.g., APS, COOP, SRL" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Total Employee Count (FTE):', 'wp-esg' ); ?></label>
                    <input type="number" name="employees_count" required placeholder="e.g., 12" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Reporting Accounting Year:', 'wp-esg' ); ?></label>
                    <input type="number" name="balance_year" value="2026" required style="width:100%; padding:8px;">
                </p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Generate Questionnaires Index →', 'wp-esg' ); ?>" style="padding:10px 20px;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // PROCESS SCREENING
    // FIX #1: aggiunto legal_entity_code nel $db_record (era NOT NULL senza default → insert falliva)
    // FIX #2: sostituito wp_redirect() + exit con safe_redirect() (meta refresh)
    // ==========================================
    private function process_screening_and_route() {
        $country            = sanitize_text_field($_POST['company_country']);
        $tax_id             = sanitize_text_field($_POST['company_tax_id']);
        $ateco              = sanitize_text_field($_POST['business_code']);
        $legal_entity_code  = sanitize_text_field($_POST['legal_entity_code'] ?? '');
        $employees          = (int)$_POST['employees_count'];
        $year               = (int)$_POST['balance_year'];

        $_SESSION['esg_company_tax_id'] = $tax_id;
        $_SESSION['esg_balance_year']   = $year;

        global $wpdb;
        $table = $wpdb->prefix . 'esg_assessments';

        $db_record = array(
            'company_tax_id'    => $tax_id,
            'business_code'     => $ateco,
            'legal_entity_code' => $legal_entity_code,  // FIX #1: campo NOT NULL ora incluso
            'company_size'      => 'Standard',
            'balance_year'      => $year,
            'country_code'      => $country,
            'workflow_status'   => 'Draft',
            'raw_answers'       => json_encode(array('company_metadata' => array(
                'company_country'   => $country,
                'company_tax_id'    => $tax_id,
                'business_code'     => $ateco,
                'legal_entity_code' => $legal_entity_code,
                'employees_count'   => $employees,
                'balance_year'      => $year,
            )))
        );

        $existing_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year) );
        if ( $existing_id ) {
            $wpdb->update( $table, $db_record, array( 'id' => $existing_id ) );
        } else {
            $wpdb->insert( $table, $db_record );
        }

        // FIX #2: meta refresh invece di wp_redirect() che fallisce se headers già inviati
        return $this->safe_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
    }

    // ==========================================
    // ASSESSMENT HUB
    // ==========================================
    private function render_assessment_hub() {
        global $wpdb;
        
        if ( isset($_GET['resume_id']) ) {
            $resume_id = (int)$_GET['resume_id'];
            $res_row   = $wpdb->get_row($wpdb->prepare("SELECT company_tax_id, balance_year FROM {$wpdb->prefix}esg_assessments WHERE id = %d", $resume_id));
            if ($res_row) {
                $_SESSION['esg_company_tax_id'] = $res_row->company_tax_id;
                $_SESSION['esg_balance_year']   = $res_row->balance_year;
            }
        }

        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        $table       = $wpdb->prefix . 'esg_assessments';
        $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
        $payload     = $current_raw ? json_decode($current_raw, true) : array();

        $has_openesea = isset($payload['openesea_framework']);
        $has_pgs      = isset($payload['pgs_framework']);
        $has_products = isset($payload['products_framework']);

        ob_start();
        ?>
        <div class="esg-hub-box" style="max-width: 650px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'history', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; <?php esc_html_e( 'Back to Archive', 'wp-esg' ); ?></a></div>
            <h2 style="margin-top:0; color:#1d2327; border-bottom: 1px solid #f0f0f1; padding-bottom:15px;"><?php esc_html_e( 'Corporate ESG Disclosure Index', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:14px; margin-bottom:25px;">
                <strong>VAT ID:</strong> <?php echo esc_html($tax_id); ?> | <strong>Year:</strong> <?php echo (int)$year; ?>
            </p>

            <?php if ( empty($tax_id) || $year === 0 ) : ?>
                <div style="background:#fef3cd; border:1px solid #ffc107; padding:15px; border-radius:4px; color:#664d03; margin-bottom:20px;">
                    ⚠️ <?php esc_html_e( 'Session data missing. Please restart from the screening form.', 'wp-esg' ); ?>
                    <br><a href="<?php echo esc_url(add_query_arg('esg_step', 'screening', get_permalink())); ?>"><?php esc_html_e( 'Go to Screening →', 'wp-esg' ); ?></a>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:30px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div><strong style="display:block; font-size:15px; color:#2271b1;">1. OpenESEA Core Framework</strong></div>
                    <div>
                        <?php if($has_openesea): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px; margin-right:8px;">✓ Completato</span>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'openesea', get_permalink())); ?>" style="font-size:12px;"><?php esc_html_e( 'Edit', 'wp-esg' ); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'openesea', get_permalink())); ?>" class="button button-primary"><?php esc_html_e( 'Compile Section →', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div><strong style="display:block; font-size:15px; color:#2c3338;">2. Network PGS Evaluation</strong></div>
                    <div>
                        <?php if($has_pgs): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px; margin-right:10px;">✓ Completato</span>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'pgs', get_permalink())); ?>" style="font-size:12px;"><?php esc_html_e( 'Edit', 'wp-esg' ); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'pgs', get_permalink())); ?>" class="button"><?php esc_html_e( 'Compile Section →', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div><strong style="display:block; font-size:15px; color:#46b450;">3. Vertical Product Self-Certifications</strong></div>
                    <div>
                        <?php if($has_products): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px; margin-right:10px;">✓ Completato</span>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'products', get_permalink())); ?>" style="font-size:12px;"><?php esc_html_e( 'Edit', 'wp-esg' ); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'products', get_permalink())); ?>" class="button"><?php esc_html_e( 'Compile Section →', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="text-align:center; border-top:1px solid #f0f0f1; padding-top:20px;">
                <?php if($has_openesea && $has_pgs && $has_products): ?>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="submit_products">
                        <input type="submit" class="button button-primary" value="🔒 Finalize & Close Campaign" style="font-size:15px; padding:10px 25px;">
                    </form>
                <?php else: ?>
                    <p style="font-size:13px; color:#dc3232; font-weight:bold;"><?php esc_html_e( '⚠️ Complete all modules to unlock final locking block execution.', 'wp-esg' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // OPENESEA FORM — FIX #3: legge il JSON reale invece di array hardcoded
    // ==========================================
    private function render_openesea_form() {
        $data      = $this->load_framework_json( 'frameworks/openesea/questions.json' );
        $questions = $data['questions'] ?? array();

        if ( empty( $questions ) ) {
            return '<p style="color:#dc3232; text-align:center; font-family:sans-serif;">'
                . esc_html__( 'Errore: impossibile caricare il questionario OpenESEA (questions.json non trovato).', 'wp-esg' )
                . '</p>';
        }

        // Raggruppa per blocco tematico
        $blocks = array();
        foreach ( $questions as $q_id => $q_def ) {
            $block_key = $q_def['block'] ?? 'other';
            $blocks[ $block_key ][ $q_id ] = $q_def;
        }

        $block_labels = array(
            'general_data'                    => __( 'Dati Generali', 'wp-esg' ),
            'economy_profit_policy'           => __( 'Economia e Politica di Profitto', 'wp-esg' ),
            'quality_of_work'                 => __( 'Qualità del Lavoro', 'wp-esg' ),
            'equity_democracy'                => __( 'Equità e Democrazia', 'wp-esg' ),
            'social_commitment_cooperation'   => __( 'Impegno Sociale e Cooperazione', 'wp-esg' ),
            'environmental_sustainability'    => __( 'Sostenibilità Ambientale', 'wp-esg' ),
        );

        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 650px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'hub', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Index</a></div>
            <h2 style="color:#2271b1; margin-top:0; border-bottom:2px solid #2271b1; padding-bottom:10px;"><?php esc_html_e( 'OpenESEA Core Framework', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:13px; margin-bottom:20px;"><?php printf( esc_html__( '%d domande suddivise per area tematica.', 'wp-esg' ), count($questions) ); ?></p>
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_openesea">

                <?php foreach ( $blocks as $block_key => $block_questions ) : ?>
                    <h3 style="margin-top:25px; margin-bottom:12px; padding:8px 12px; background:#f0f6fc; border-left:4px solid #2271b1; color:#1d2327; font-size:14px;">
                        <?php echo esc_html( $block_labels[ $block_key ] ?? ucwords( str_replace('_', ' ', $block_key) ) ); ?>
                    </h3>
                    <?php foreach ( $block_questions as $q_id => $q_def ) : ?>
                        <?php echo $this->render_field( $q_id, array(
                            'label'    => $q_def['text'] ?? $q_id,
                            'help'     => $q_def['help'] ?? '',
                            'type'     => $q_def['type'] ?? 'string',
                            'control'  => $q_def['control'] ?? '',
                            'required' => $q_def['required'] ?? false,
                            'options'  => $q_def['options'] ?? array(),
                        ) ); ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <div style="text-align:right; margin-top:20px;">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Salva risposte →', 'wp-esg' ); ?>" style="padding:10px 25px;">
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // PGS FORM — FIX #3: legge schema.json PGS reale
    // ==========================================
    private function render_pgs_form() {
        $data     = $this->load_framework_json( 'frameworks/pgs/schema.json' );
        $criteria = $data['pgs_evaluation_criteria'] ?? array();

        if ( empty( $criteria ) ) {
            return '<p style="color:#dc3232; text-align:center; font-family:sans-serif;">'
                . esc_html__( 'Errore: impossibile caricare il questionario PGS (schema.json non trovato).', 'wp-esg' )
                . '</p>';
        }

        // Raggruppa per blocco
        $blocks = array();
        foreach ( $criteria as $c_id => $c_def ) {
            $block_key = $c_def['block'] ?? 'other';
            $blocks[ $block_key ][ $c_id ] = $c_def;
        }

        $block_labels = array(
            'general_information'   => __( 'Informazioni Generali', 'wp-esg' ),
            'company_structure'     => __( 'Struttura Aziendale', 'wp-esg' ),
            'agronomic_practices'   => __( 'Pratiche Agronomiche', 'wp-esg' ),
            'production_methods'    => __( 'Metodi di Produzione', 'wp-esg' ),
            'catalog_economics'     => __( 'Catalogo ed Economia', 'wp-esg' ),
            'solidarity_engagement' => __( 'Impegno Solidale', 'wp-esg' ),
            'commitments_signoff'   => __( 'Impegni e Sottoscrizione', 'wp-esg' ),
        );

        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 650px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'hub', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Index</a></div>
            <h2 style="color:#2c3338; margin-top:0; border-bottom:2px solid #2c3338; padding-bottom:10px;"><?php esc_html_e( 'Network PGS Evaluation', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:13px; margin-bottom:20px;"><?php printf( esc_html__( '%d criteri di valutazione PGS.', 'wp-esg' ), count($criteria) ); ?></p>
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_pgs">

                <?php foreach ( $blocks as $block_key => $block_criteria ) : ?>
                    <h3 style="margin-top:25px; margin-bottom:12px; padding:8px 12px; background:#f6f7f7; border-left:4px solid #2c3338; color:#1d2327; font-size:14px;">
                        <?php echo esc_html( $block_labels[ $block_key ] ?? ucwords( str_replace('_', ' ', $block_key) ) ); ?>
                    </h3>
                    <?php foreach ( $block_criteria as $c_id => $c_def ) : ?>
                        <?php echo $this->render_field( $c_id, $c_def ); ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <div style="text-align:right; margin-top:20px;">
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Salva risposte →', 'wp-esg' ); ?>" style="background:#2c3338; color:#fff; border:none; padding:10px 25px; border-radius:4px; cursor:pointer;">
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // PRODUCTS FORM — FIX #3: legge bread.json reale
    // ==========================================
    private function render_products_form() {
        // Carica la lista dei prodotti disponibili
        $data     = $this->load_framework_json( 'frameworks/products/food/bread.json' );
        $criteria = $data['product_evaluation_criteria'] ?? array();

        if ( empty( $criteria ) ) {
            return '<p style="color:#dc3232; text-align:center; font-family:sans-serif;">'
                . esc_html__( 'Errore: impossibile caricare la scheda prodotto (bread.json non trovato o vuoto).', 'wp-esg' )
                . '</p>';
        }

        $product_name = $data['product_id'] ?? 'food/bread';

        // Raggruppa per blocco
        $blocks = array();
        foreach ( $criteria as $c_id => $c_def ) {
            $block_key = $c_def['block'] ?? 'other';
            $blocks[ $block_key ][ $c_id ] = $c_def;
        }

        $block_labels = array(
            'product_specifications' => __( 'Specifiche Prodotto', 'wp-esg' ),
            'flours_ingredients'     => __( 'Farine e Ingredienti', 'wp-esg' ),
            'water_leavening'        => __( 'Acqua e Lievitazione', 'wp-esg' ),
            'additives_processing'   => __( 'Additivi e Trasformazione', 'wp-esg' ),
            'baking_packaging'       => __( 'Cottura e Confezionamento', 'wp-esg' ),
            'source_pricing'         => __( 'Approvvigionamento e Prezzi', 'wp-esg' ),
        );

        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 650px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'hub', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Index</a></div>
            <h2 style="color:#46b450; margin-top:0; border-bottom:2px solid #46b450; padding-bottom:10px;"><?php esc_html_e( 'Vertical Product Self-Certifications', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:13px; margin-bottom:5px;"><?php esc_html_e( 'Scheda prodotto:', 'wp-esg' ); ?> <strong><?php echo esc_html( $product_name ); ?></strong></p>
            <p style="color:#646970; font-size:13px; margin-bottom:20px;"><?php printf( esc_html__( '%d criteri di auto-certificazione.', 'wp-esg' ), count($criteria) ); ?></p>
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_products">
                <input type="hidden" name="products_q1" value="1"><!-- sentinel per process_products_and_route() -->

                <?php foreach ( $blocks as $block_key => $block_criteria ) : ?>
                    <h3 style="margin-top:25px; margin-bottom:12px; padding:8px 12px; background:#f0faf0; border-left:4px solid #46b450; color:#1d2327; font-size:14px;">
                        <?php echo esc_html( $block_labels[ $block_key ] ?? ucwords( str_replace('_', ' ', $block_key) ) ); ?>
                    </h3>
                    <?php foreach ( $block_criteria as $c_id => $c_def ) : ?>
                        <?php echo $this->render_field( $c_id, $c_def ); ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <div style="text-align:right; margin-top:20px;">
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Salva risposte →', 'wp-esg' ); ?>" style="background:#46b450; color:#fff; border:none; padding:10px 25px; border-radius:4px; cursor:pointer;">
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // PROCESS OPENESEA
    // FIX #2: safe_redirect invece di wp_redirect
    // ==========================================
    private function process_openesea_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table       = $wpdb->prefix . 'esg_assessments';
            $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            $payload     = $current_raw ? json_decode($current_raw, true) : array();
            
            $openesea_data = array();
            foreach ( $_POST as $key => $value ) {
                if ( $key === 'action' ) continue;
                if ( is_array($value) ) {
                    $openesea_data[ sanitize_key($key) ] = array_map('sanitize_text_field', $value);
                } else {
                    $openesea_data[ sanitize_key($key) ] = sanitize_text_field($value);
                }
            }
            $payload['openesea_framework'] = $openesea_data;

            $wpdb->update(
                $table,
                array( 'workflow_status' => 'Submitted', 'raw_answers' => json_encode($payload) ),
                array( 'company_tax_id' => $tax_id, 'balance_year' => $year )
            );
        }

        return $this->safe_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
    }

    // ==========================================
    // PROCESS PGS
    // FIX #2: safe_redirect invece di wp_redirect
    // ==========================================
    private function process_pgs_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table       = $wpdb->prefix . 'esg_assessments';
            $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            $payload     = $current_raw ? json_decode($current_raw, true) : array();
            
            $pgs_data = array();
            foreach ( $_POST as $key => $value ) {
                if ( $key === 'action' ) continue;
                if ( is_array($value) ) {
                    $pgs_data[ sanitize_key($key) ] = array_map('sanitize_text_field', $value);
                } else {
                    $pgs_data[ sanitize_key($key) ] = sanitize_text_field($value);
                }
            }
            $payload['pgs_framework'] = $pgs_data;

            $wpdb->update(
                $table,
                array( 'raw_answers' => json_encode($payload) ),
                array( 'company_tax_id' => $tax_id, 'balance_year' => $year )
            );
        }

        return $this->safe_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
    }

    // ==========================================
    // PROCESS PRODUCTS
    // FIX #2: safe_redirect invece di wp_redirect
    // ==========================================
    private function process_products_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table = $wpdb->prefix . 'esg_assessments';
            $row   = $wpdb->get_row($wpdb->prepare("SELECT id, raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            
            if ($row) {
                $payload = json_decode($row->raw_answers, true) ?: array();
                
                if ( isset($_POST['products_q1']) ) {
                    $products_data = array();
                    foreach ( $_POST as $key => $value ) {
                        if ( $key === 'action' || $key === 'products_q1' ) continue;
                        if ( is_array($value) ) {
                            $products_data[ sanitize_key($key) ] = array_map('sanitize_text_field', $value);
                        } else {
                            $products_data[ sanitize_key($key) ] = sanitize_text_field($value);
                        }
                    }
                    $payload['products_framework'] = $products_data;
                    $wpdb->update($table, array( 'raw_answers' => json_encode($payload) ), array('id' => $row->id));

                    return $this->safe_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
                }

                // Submit finale (hub "Finalize")
                if ( class_exists('WpEsg\Core\WorkflowManager') ) {
                    $wm = new \WpEsg\Core\WorkflowManager();
                    $wm->submitToReview( (int)$row->id );
                } else {
                    $wpdb->update($table, array( 'workflow_status' => 'Pending Review' ), array('id' => $row->id));
                }
            }
        }

        unset($_SESSION['esg_company_tax_id'], $_SESSION['esg_balance_year'], $_SESSION['esg_qualitative_module']);

        return $this->safe_redirect( add_query_arg( 'esg_step', 'history', get_permalink() ) );
    }
}
