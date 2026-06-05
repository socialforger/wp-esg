<?php
namespace WpEsg\Frameworks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrameworkRegistry {
    
    /**
     * Resolves the absolute server path for any given blueprint schema layer.
     */
    public static function get_schema_path( $layer, $filename ) {
        $allowed_layers = array( 'openesea', 'sdg', 'pgs', 'products' );
        
        if ( ! in_array( $layer, $allowed_layers, true ) ) {
            return false;
        }
        
        $base_path = defined( 'WP_ESG_FRAMEWORKS_PATH' ) ? WP_ESG_FRAMEWORKS_PATH : plugin_dir_path( dirname( __DIR__ ) ) . 'frameworks/';
        
        if ( $layer === 'products' ) {
            return $base_path . 'products/food/' . sanitize_file_name( $filename );
        }
        
        return $base_path . sanitize_file_name( $layer ) . '/' . sanitize_file_name( $filename );
    }

    /**
     * Maps a macro-sector token (from national registries) to a product evaluation category.
     * Returns the matching qualitative module key, or 'none' if no specific module applies.
     *
     * @param string $sectorToken  Uppercase sector token (e.g., 'AGRICULTURE', 'MANUFACTURING').
     * @return string              Product category key or 'none'.
     */
    public static function mapSectorToCategory( string $sectorToken ): string {
        $map = [
            'AGRICULTURE'           => 'food',
            'FOOD_MANUFACTURING'    => 'food',
            'TEXTILE'               => 'textile_clothing',
            'CHEMICAL'              => 'chemical_detergents',
            'HERBALIST'             => 'herbalist',
            'ARTISAN'               => 'artisan',
        ];

        $normalized = strtoupper( trim( $sectorToken ) );
        return $map[ $normalized ] ?? 'none';
    }

    /**
     * Fetches, reads, and decodes a target JSON schema configuration on the fly.
     */
    public static function get_schema_json( $layer, $filename ) {
        $path = self::get_schema_path( $layer, $filename );
        if ( $path && file_exists( $path ) ) {
            $content = file_get_contents( $path );
            return json_decode( $content, true );
        }
        return null;
    }
}
