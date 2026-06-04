<?php
namespace WpEsg\Input\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Input\BaseInputAdapter;

/**
 * Class GravityFormsAdapter
 * Integrates directly with native Gravity Forms post-submission processing streams.
 *
 * @package WpEsg\Input\Adapters
 */
class GravityFormsAdapter extends BaseInputAdapter {

    public function __construct() {
        // Hook into Gravity Forms definitive form processing loop
        add_action('gform_after_submission', [$this, 'interceptGravitySubmission'], 10, 2);
    }

    /**
     * Intercepts entry indices data configurations mapping fields onto clean session storage parameters rows.
     */
    public function interceptGravitySubmission(array $entry, array $form): void {
        $rawAnswers = [];
        
        // Dynamic iteration extraction resolving native numeric field tracking keys
        foreach ($form['fields'] as $field) {
            $id = $field->id;
            $rawAnswers['field_' . $id] = sanitize_text_field(rgar($entry, (string)$id));
        }

        // Standard operational field mappings translation based on fixed system identifiers
        $normalized = [
            'company_tax_id' => sanitize_text_field(rgar($entry, '1')), // Mapping configuration target for VAT ids
            'business_code'  => sanitize_text_field(rgar($entry, '2')), // Mapping configuration target for ATECO inputs
            'country_code'   => sanitize_text_field(rgar($entry, '3'))  // Mapping configuration target for jurisdiction strings
        ];

        $dbRecord = [
            'company_tax_id'  => $normalized['company_tax_id'],
            'business_code'   => $normalized['business_code'],
            'company_size'    => 'Short',
            'balance_year'    => (int)gmdate('Y'),
            'country_code'    => !empty($normalized['country_code']) ? $normalized['country_code'] : 'IT',
            'workflow_status' => 'Draft',
            'raw_answers'     => json_encode($rawAnswers)
        ];

        $this->persistAssessmentAnswers($dbRecord);
    }

    protected function normalizeFormFields(array $rawPayload): array {
        return $rawPayload; 
    }
}
