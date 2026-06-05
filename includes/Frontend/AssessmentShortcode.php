private function process_screening_and_render_questions() {
        $country   = sanitize_text_field($_POST['company_country']);
        $tax_id    = sanitize_text_field($_POST['company_tax_id']);
        $ateco     = sanitize_text_field($_POST['business_code']);
        $employees = (int)$_POST['employees_count'];
        $year      = (int)$_POST['balance_year'];

        $context = array('company_size_scope' => 'Standard', 'qualitative_module' => 'none');
        if ( class_exists( 'WpEsg\Storage\UserCompanyLinker' ) ) {
            $context = \WpEsg\Storage\UserCompanyLinker::resolveContext($country, $ateco, $employees, $year);
        }

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'esg_assessments', array(
            'company_tax_id'  => $tax_id,
            'business_code'   => $ateco,
            'company_size'    => $context['company_size_scope'],
            'balance_year'    => $year,
            'country_code'    => $country,
            'workflow_status' => 'Draft',
            'raw_answers'     => json_encode($_POST)
        ));

        ob_start();
        ?>
        <div class="esg-dynamic-questions" style="max-width: 650px; margin: 30px auto; padding:25px; border:1px solid #ccd0d4; background:#fff; border-radius:6px; font-family: sans-serif;">
            <h3><?php esc_html_e( 'Questionnaire Generated Successfully', 'wp-esg' ); ?></h3>
            <p style="background:#e7f5fe; padding:12px; border-left:4px solid #2271b1; font-size:14px;">
                <strong><?php esc_html_e( 'Calculated Scope Size:', 'wp-esg' ); ?></strong> <?php echo esc_html($context['company_size_scope']); ?> | <strong><?php esc_html_e( 'Active Modular Matrix:', 'wp-esg' ); ?></strong> <code><?php echo esc_html($context['qualitative_module']); ?></code>
            </p>
            <form method="post" action="">
                <h4><?php esc_html_e( 'Target Dynamic ESG Metrics', 'wp-esg' ); ?></h4>
                <p>
                    <label style="display:block; margin-bottom:8px; line-height:1.4;">
                        <?php esc_html_e( 'Does your enterprise actively monitor circular economy protocols or resource recycling targets contextualized to the industrial vertical sector:', 'wp-esg' ); ?> <strong><?php echo esc_html($context['qualitative_module']); ?></strong>?
                    </label>
                    <input type="radio" name="q_1" value="yes"> <?php esc_html_e( 'Yes', 'wp-esg' ); ?> &nbsp;&nbsp;
                    <input type="radio" name="q_1" value="no"> <?php esc_html_e( 'No', 'wp-esg' ); ?>
                </p>
                <!-- FIXED ACCORDINGLY -->
                <input type="submit" value="<?php esc_attr_e( 'Save & Submit Responses', 'wp-esg' ); ?>" class="button button-primary" style="padding:10px 20px; background:#46b450; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
