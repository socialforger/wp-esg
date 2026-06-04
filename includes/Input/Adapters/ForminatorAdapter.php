<?php
namespace WpEsg\Input\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Input\BaseInputAdapter;
use WpEsg\Storage\UserCompanyLinker;
use WpEsg\Core\SchemaValidator;

/**
 * Class ForminatorAdapter
 * Binds directly into the Forminator submission lifecycle events to parse, 
 * secure, and map asynchronous web entries.
 *
 * @package WpEsg\Input\Adapters
 */
class ForminatorAdapter extends BaseInputAdapter {

    private SchemaValidator $validator;

    public function __construct() {
        $this->validator = new SchemaValidator();
        
        // Hooks into the native Forminator pre-persistence custom processing action
        add_action('forminator_custom_form_submit_before_set_fields', [$this, 'interceptForminatorSubmission'], 10, 3);
    }

    /**
     * Catches incoming data arrays, extracts system parameters, and fires schema validation guards.
     */
    public function interceptForminatorSubmission(array $entryData, int $formId, array $formFields): void {
        $rawAnswers = [];
        
        // Map Forminator object matrix into a flat associative input array
        foreach ($formFields as $field) {
            $name = $field['name'] ?? '';
            if (!empty($name)) {
                $rawAnswers[$name] = is_array($field['value']) 
                    ? array_map('sanitize_text_field', $field['value']) 
                    : sanitize_text_field($field['value']);
            }
        }

        $normalized = $this->normalizeFormFields($rawAnswers);
        
        $country   = $normalized['country_code'];
        $taxId     = $normalized['company_tax_id'];
        $business  = $normalized['business_code'];
        $employees = (int)($rawAnswers['q0102'] ?? 0); // Core field index mapping employee headcounts

        // Run contextual auto-routing rules to resolve size scopes and module requirements
        $context = UserCompanyLinker::resolveContext($country, $business, $employees);

        // Abort operations if inputs attempt to inject criteria keys into unauthorized categories blueprints
        if ($context['qualitative_module'] !== 'none') {
            $validation = $this->validator->validateMarketCategoryPayload($rawAnswers, $context['qualitative_module']);
            if (!$validation['valid']) {
                wp_die(esc_html__('Ecosystem safety checkpoint failure: Malformed sector data array intercepted.', 'wp-esg'));
            }
        }

        $dbRecord = [
            'company_tax_id'  => $taxId,
            'business_code'   => $business,
            'company_size'    => $context['company_size_scope'],
            'balance_year'    => (int)($rawAnswers['balance_year'] ?? gmdate('Y')),
            'country_code'    => $country,
            'workflow_status' => 'Draft',
            'raw_answers'     => json_encode($rawAnswers)
        ];

        $this->persistAssessmentAnswers($dbRecord);
    }

    protected function normalizeFormFields(array $rawPayload): array {
        return [
            'company_tax_id' => sanitize_text_field($rawPayload['company_tax_id'] ?? ''),
            'business_code'  => sanitize_text_field($rawPayload['business_code'] ?? ''),
            'country_code'   => sanitize_text_field($rawPayload['company_country'] ?? 'IT')
        ];
    }
}
