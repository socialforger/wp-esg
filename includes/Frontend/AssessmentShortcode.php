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

        // STATO 0: Landing CTA Button (Mostrato solo se scollegati e senza codici attivi)
        if ( ! isset( $_GET['esg_action'] ) && ! isset( $_POST['action'] ) && ! isset( $_SESSION['esg_verified_code'] ) && ! is_user_logged_in() ) {
            return $this->render_landing_button( $engine_mode );
        }

        // ROTTA DI INTERFACCIA: MODALITÀ USERS DB (MULTITENANT CONNESSO)
        if ( 'multitenant_db' === $engine_mode ) {
            if ( ! is_user_logged_in() ) {
                wp_redirect( wp_login_url( get_permalink() ) );
                exit;
            }
        } 
        // ROTTA DI INTERFACCIA: MODALITÀ STANDALONE (CODICE MASTER ESG2026)
        else {
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

        // ==========================================
        // GESTORE INTERCETTAZIONE FORM E SALVATAGGI
        // ==========================================

        // Screening Iniziale Anagrafico
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_screening' ) {
            return $this->process_screening_and_route();
        }

        // STEP 1: OPENESEA (Sottoposto a Giudizio)
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_openesea' ) {
            return $this->process_openesea_and_route();
        }

        // STEP 2: PGS
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_pgs' ) {
            return $this->process_pgs_and_route();
        }

        // STEP 3: PRODOTTI (Chiusura Campagna)
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'submit_products' ) {
            return $this->process_products_and_route();
        }

        // ==========================================
        // ROUTING SCHERMATE DYNAMIC STEP (GET)
        // ==========================================
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

    // ==========================================
    // PARTE 1: DASHBOARD STORICO ARCHIVIO
    // ==========================================
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

    // ==========================================
    // PARTE 2: READ-ONLY ARCHIVE AUDIT VIEW
    // ==========================================
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
                <h4 style="color:#46b450; margin-bottom:10px;">3. Vertical Product Self-Certifications</h4>
                <p style="font-size:14px; margin-bottom:5px; font-weight:bold;">Q: Are your primary commercial goods produced utilizing certified eco-compatible materials?</p>
                <p style="font-size:14px; color:#1d2327; background:#fafafa; padding:8px; border-left:3px solid #46b450; text-transform:uppercase;">
                    <strong>Answer:</strong> <?php echo esc_html($payload['products_framework']['products_q1'] ?? 'Not Answered'); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // FASE 1: PRE-QUESTIONARIO SCREENING ANAGRAFICO
    // ==========================================
    private function render_screening_form() {
        ob_start();
        ?>
        <div class="esg-screening-box" style="max-width: 550px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'history', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Archive</a></div>
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
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Total Employee Count (FTE):', 'wp-esg' ); ?></label>
                    <input type="number" name="employees_count" required placeholder="e.g., 12" style="width:100%; padding:8px;">
                </p>
                <p>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;"><?php esc_html_e( 'Reporting Accounting Year:', 'wp-esg' ); ?></label>
                    <input type="number" name="balance_year" value="2026" required style="width:100%; padding:8px;">
                </p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Generate Questionnaires Index &rarr;', 'wp-esg' ); ?>" style="margin-top:10px; padding:10px 20px; background:#2271b1; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function process_screening_and_route() {
        $country   = sanitize_text_field($_POST['company_country']);
        $tax_id    = sanitize_text_field($_POST['company_tax_id']);
        $ateco     = sanitize_text_field($_POST['business_code']);
        $employees = (int)$_POST['employees_count'];
        $year      = (int)$_POST['balance_year'];

        $_SESSION['esg_company_tax_id'] = $tax_id;
        $_SESSION['esg_balance_year']   = $year;

        $context = array('company_size_scope' => 'Standard', 'qualitative_module' => 'none');
        if ( class_exists( 'WpEsg\Storage\UserCompanyLinker' ) ) {
            $context = \WpEsg\Storage\UserCompanyLinker::resolveContext($country, $ateco, $employees, $year);
        }
        $_SESSION['esg_qualitative_module'] = $context['qualitative_module'];

        global $wpdb;
        $table = $wpdb->prefix . 'esg_assessments';

        $db_record = array(
            'company_tax_id'  => $tax_id,
            'business_code'   => $ateco,
            'company_size'    => $context['company_size_scope'],
            'balance_year'    => $year,
            'country_code'    => $country,
            'workflow_status' => 'Draft',
            'raw_answers'     => json_encode(array('company_metadata' => $_POST))
        );

        $existing_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year) );
        if ( $existing_id ) {
            $wpdb->update( $table, $db_record, array( 'id' => $existing_id ) );
        } else {
            $wpdb->insert( $table, $db_record );
        }

        wp_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
        exit;
    }

    // ==========================================
    // 📋 HUB CENTRALE: INDICE COMPILATIVO DEI CASSETTI
    // ==========================================
    private function render_assessment_hub() {
        global $wpdb;
        
        if ( isset($_GET['resume_id']) ) {
            $resume_id = (int)$_GET['resume_id'];
            $res_row = $wpdb->get_row($wpdb->prepare("SELECT company_tax_id, balance_year FROM {$wpdb->prefix}esg_assessments WHERE id = %d", $resume_id));
            if($res_row) {
                $_SESSION['esg_company_tax_id'] = $res_row->company_tax_id;
                $_SESSION['esg_balance_year']   = $res_row->balance_year;
                $_SESSION['esg_qualitative_module'] = 'none';
            }
        }

        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        $table = $wpdb->prefix . 'esg_assessments';
        $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
        $payload = $current_raw ? json_decode($current_raw, true) : array();

        $has_openesea = isset($payload['openesea_framework']);
        $has_pgs      = isset($payload['pgs_framework']);
        $has_products = isset($payload['products_framework']);

        ob_start();
        ?>
        <div class="esg-hub-box" style="max-width: 650px; margin: 30px auto; padding: 30px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="margin-bottom:15px;"><a href="<?php echo esc_url(add_query_arg('esg_step', 'history', get_permalink())); ?>" style="color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Archive</a></div>
            <h2 style="margin-top:0; color:#1d2327; border-bottom: 1px solid #f0f0f1; padding-bottom:15px;"><?php esc_html_e( 'Corporate ESG Disclosure Index', 'wp-esg' ); ?></h2>
            <p style="color:#646970; font-size:14px; margin-bottom:25px;">
                <?php printf( esc_html__( 'Reporting Year: %d | Business Registry VAT ID: %s', 'wp-esg' ), (int)$year, esc_html($tax_id) ); ?>
            </p>

            <div style="margin-bottom:30px;">
                <!-- SEZIONE 1: OPENESEA -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div>
                        <strong style="display:block; font-size:15px; color:#2271b1;">1. OpenESEA Core Framework</strong>
                        <span style="font-size:12px; color:#646970; font-style:italic;"><?php esc_html_e( 'Mandatory - Subject to official Auditing & Review', 'wp-esg' ); ?></span>
                    </div>
                    <div>
                        <?php if($has_openesea): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px;">🔒 Locked & Submitted</span>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'openesea', get_permalink())); ?>" class="button" style="background:#2271b1; color:#fff; text-decoration:none; padding:6px 12px; font-size:13px; font-weight:bold; border-radius:3px;"><?php esc_html_e( 'Compile Section &rarr;', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SEZIONE 2: PGS -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div>
                        <strong style="display:block; font-size:15px; color:#2c3338;">2. Network PGS Evaluation</strong>
                        <span style="font-size:12px; color:#646970; font-style:italic;"><?php esc_html_e( 'Social & Governance parameters - Platform Score', 'wp-esg' ); ?></span>
                    </div>
                    <div>
                        <?php if($has_pgs): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px; margin-right:10px;">✓ Completed</span>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'pgs', get_permalink())); ?>" style="font-size:12px; color:#2271b1; text-decoration:none;"><?php esc_html_e( 'Edit responses', 'wp-esg' ); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'pgs', get_permalink())); ?>" class="button" style="background:#2c3338; color:#fff; text-decoration:none; padding:6px 12px; font-size:13px; font-weight:bold; border-radius:3px;"><?php esc_html_e( 'Compile Section &rarr;', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SEZIONE 3: PRODOTTI -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:12px;">
                    <div>
                        <strong style="display:block; font-size:15px; color:#46b450;">3. Vertical Product Self-Certifications</strong>
                        <span style="font-size:12px; color:#646970; font-style:italic;"><?php esc_html_e( 'Product environmental footprints declarations', 'wp-esg' ); ?></span>
                    </div>
                    <div>
                        <?php if($has_products): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:5px 10px; font-size:12px; font-weight:bold; border-radius:3px; margin-right:10px;">✓ Completed</span>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'products', get_permalink())); ?>" style="font-size:12px; color:#2271b1; text-decoration:none;"><?php esc_html_e( 'Edit responses', 'wp-esg' ); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('esg_step', 'products', get_permalink())); ?>" class="button" style="background:#46b450; color:#fff; text-decoration:none; padding:6px 12px; font-size:13px; font-weight:bold; border-radius:3px;"><?php esc_html_e( 'Compile Section &rarr;', 'wp-esg' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="text-align:center; border-top:1px solid #f0f0f1; padding-top:20px;">
                <?php if($has_openesea && $has_pgs && $has_products): ?>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="submit_products">
                        <input type="submit" class="button" value="🔒 Finalize & Close Assessment Campaign" style="background:#46b450; color:#fff; font-size:15px; padding:12px 25px; font-weight:bold; border:none; border-radius:4px; cursor:pointer;">
                    </form>
                <?php else: ?>
                    <p style="font-size:13px; color:#dc3232; font-weight:bold; margin:0;"><?php esc_html_e( '⚠️ You must fill all core matrices modules to seal the structural database record packet.', 'wp-esg' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================
    // STEP 1: MODULE FORM CORE OPENESEA (CON HELP CARICATO DA JSON LINGUA)
    // ==========================================
    private function render_openesea_form() {
        $module = $_SESSION['esg_qualitative_module'] ?? 'none';
        
        // 🌐 ACCESSO DINAMICO E PARACADUTE AIUTI LOCALIZZATI
        $locale = determine_locale(); 
        $lang_code = substr($locale, 0, 2); 
        
        $help_file_path = WP_ESG_PATH . "languages/{$lang_code}/frameworks/openesea/questions-help.json";
        
        // Paracadute di sicurezza: se la lingua del sito non è ancora mappata, carica la cartella standard italiana
        if ( ! file_exists($help_file_path) ) {
            $help_file_path = WP_ESG_PATH . "languages/it/frameworks/openesea/questions-help.json";
        }

        $openesea_help_text = '';
        if ( file_exists($help_file_path) ) {
            $help_data = json_decode(file_get_contents($help_file_path), true);
            $openesea_help_text = $help_data['openesea_q1'] ?? ''; 
        }

        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="color:#2271b1; font-weight:bold; font-size:12px; margin-bottom:10px; text-transform:uppercase;"><?php esc_html_e( 'Step 2 of 4: Compliance Validation', 'wp-esg' ); ?></div>
            <h2 style="color:#2271b1; margin-top:0; border-bottom:2px solid #2271b1; padding-bottom:10px;"><?php esc_html_e( 'OpenESEA Core Framework', 'wp-esg' ); ?></h2>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_openesea">
                <p>
                    <label style="display:block; margin-bottom:8px; font-weight:bold; line-height:1.4;">
                        <?php esc_html_e( 'Does your enterprise actively monitor circular economy protocols or resource recycling targets contextualized to the industrial vertical sector:', 'wp-esg' ); ?> <code><?php echo esc_html($module); ?></code>?
                    </label>
                    
                    <!-- Box Descrizione Aiuto Localizzato -->
                    <?php if ( ! empty($openesea_help_text) ) : ?>
                        <span class="esg-question-help" style="display:block; font-size:13px; color:#50575e; background:#f6f7f7; padding:12px; border-left:4px solid #2271b1; margin-bottom:15px; font-style:italic; line-height:1.4; border-radius: 0 4px 4px 0;">
                            ℹ️ <?php echo esc_html($openesea_help_text); ?>
                        </span>
                    <?php endif; ?>

                    <input type="radio" name="openesea_q1" value="yes" required> <?php esc_html_e( 'Yes', 'wp-esg' ); ?> &nbsp;&nbsp;
                    <input type="radio" name="openesea_q1" value="no"> <?php esc_html_e( 'No', 'wp-esg' ); ?>
                </p>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Save & Back to Index &rarr;', 'wp-esg' ); ?>" style="margin-top:15px; padding:10px 20px; background:#2271b1; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function process_openesea_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table = $wpdb->prefix . 'esg_assessments';
            $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            $payload = $current_raw ? json_decode($current_raw, true) : array();
            
            $payload['openesea_framework'] = array_map('sanitize_text_field', $_POST);
            unset($payload['openesea_framework']['action']);

            $wpdb->update(
                $table,
                array( 'workflow_status' => 'Submitted', 'raw_answers' => json_encode($payload) ),
                array('company_tax_id' => $tax_id, 'balance_year' => $year)
            );
        }
        wp_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
        exit;
    }

    // ==========================================
    // STEP 2: MODULE FORM NETWORK PGS
    // ==========================================
    private function render_pgs_form() {
        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="color:#2c3338; font-weight:bold; font-size:12px; margin-bottom:10px; text-transform:uppercase;"><?php esc_html_e( 'Step 3 of 4: Platform Evaluation', 'wp-esg' ); ?></div>
            <h2 style="color:#2c3338; margin-top:0; border-bottom:2px solid #2c3338; padding-bottom:10px;"><?php esc_html_e( 'Network PGS Evaluation (Social & Governance)', 'wp-esg' ); ?></h2>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_pgs">
                <p>
                    <label style="display:block; margin-bottom:8px; font-weight:bold; line-height:1.4;">
                        <?php esc_html_e( 'Does your enterprise run local community support guidelines or corporate code-of-conduct transparency policies?', 'wp-esg' ); ?>
                    </label>
                    <input type="radio" name="pgs_q1" value="yes" required> <?php esc_html_e( 'Yes', 'wp-esg' ); ?> &nbsp;&nbsp;
                    <input type="radio" name="pgs_q1" value="no"> <?php esc_html_e( 'No', 'wp-esg' ); ?>
                </p>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Save & Back to Index &rarr;', 'wp-esg' ); ?>" style="margin-top:15px; padding:10px 20px; background:#2c3338; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function process_pgs_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table = $wpdb->prefix . 'esg_assessments';
            $current_raw = $wpdb->get_var($wpdb->prepare("SELECT raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            $payload = $current_raw ? json_decode($current_raw, true) : array();
            
            $payload['pgs_framework'] = array_map('sanitize_text_field', $_POST);
            unset($payload['pgs_framework']['action']);

            $wpdb->update($table, array( 'raw_answers' => json_encode($payload) ), array('company_tax_id' => $tax_id, 'balance_year' => $year));
        }
        wp_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
        exit;
    }

    // ==========================================
    // STEP 3: MODULE FORM CATALOGO PRODOTTI
    // ==========================================
    private function render_products_form() {
        ob_start();
        ?>
        <div class="esg-questions-box" style="max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #ccd0d4; background: #fff; border-radius: 6px; font-family: sans-serif;">
            <div style="color:#46b450; font-weight:bold; font-size:12px; margin-bottom:10px; text-transform:uppercase;"><?php esc_html_e( 'Step 4 of 4: Finalization', 'wp-esg' ); ?></div>
            <h2 style="color:#46b450; margin-top:0; border-bottom:2px solid #46b450; padding-bottom:10px;"><?php esc_html_e( 'Vertical Product Self-Certifications', 'wp-esg' ); ?></h2>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="submit_products">
                <p>
                    <label style="display:block; margin-bottom:8px; font-weight:bold; line-height:1.4;">
                        <?php esc_html_e( 'Are your primary commercial goods produced utilizing certified eco-compatible materials?', 'wp-esg' ); ?>
                    </label>
                    <input type="radio" name="products_q1" value="yes" required> <?php esc_html_e( 'Yes', 'wp-esg' ); ?> &nbsp;&nbsp;
                    <input type="radio" name="products_q1" value="no"> <?php esc_html_e( 'No', 'wp-esg' ); ?>
                </p>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Save & Back to Index &rarr;', 'wp-esg' ); ?>" style="margin-top:15px; padding:10px 20px; background:#46b450; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function process_products_and_route() {
        global $wpdb;
        $tax_id = $_SESSION['esg_company_tax_id'] ?? '';
        $year   = $_SESSION['esg_balance_year'] ?? 0;

        if ( ! empty($tax_id) && $year > 0 ) {
            $table = $wpdb->prefix . 'esg_assessments';
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, raw_answers FROM $table WHERE company_tax_id = %s AND balance_year = %d", $tax_id, $year));
            
            if ($row) {
                $payload = json_decode($row->raw_answers, true) ?: array();
                
                // Se la richiesta arriva dal form, salviamo le risposte nel cassetto prodotti
                if (isset($_POST['products_q1'])) {
                    $payload['products_framework'] = array_map('sanitize_text_field', $_POST);
                    unset($payload['products_framework']['action']);
                }

                $wpdb->update($table, array( 'raw_answers' => json_encode($payload) ), array('id' => $row->id));

                // ⚡ SEGNALE CHIUSURA INTERNA: Se attivato dal bottone di sblocco definitivo dell'Hub
                if ( ! isset($_POST['products_q1']) ) {
                    if ( class_exists('WpEsg\Core\WorkflowManager') ) {
                        $wm = new \WpEsg\Core\WorkflowManager();
                        $wm->submitToReview((int)$row->id); // Cambia lo stato in Pending Review e sigilla le traduzioni!
                    } else {
                        $wpdb->update($table, array( 'workflow_status' => 'Pending Review' ), array('id' => $row->id));
                    }
                }
            }
        }

        // Se l'invio è interno al form singolo, riporta all'hub per premere l'invio finale
        if ( isset($_POST['products_q1']) ) {
            wp_redirect( add_query_arg( 'esg_step', 'hub', get_permalink() ) );
            exit;
        }

        // Se la sottomissione è globale dell'intera campagna, azzera le sessioni volatili e torna all'archivio storico
        unset($_SESSION['esg_company_tax_id'], $_SESSION['esg_balance_year'], $_SESSION['esg_qualitative_module']);
        wp_redirect( add_query_arg( 'esg_step', 'history', get_permalink() ) );
        exit;
    }
}
