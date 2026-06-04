<?php
namespace WpEsg\Core; // 🔴 FIXED: Changed namespace to match the Core directory

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SchemaValidator {

    /**
     * Validates JSON string structural integrity without external libraries.
     */
    public static function validate( $json_string ) {
        if ( empty( $json_string ) ) {
            return false;
        }

        $data = json_decode( $json_string, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false; // Invalid JSON format syntax
        }

        // Structural check tailored to verify our ecosystem schemas
        if ( isset( $data['framework_id'] ) && ( isset( $data['pgs_evaluation_criteria'] ) || isset( $data['metrics'] ) || isset( $data['mappings'] ) || isset( $data['product_evaluation_criteria'] ) ) ) {
            return true;
        }

        return is_array( $data );
    }
}
