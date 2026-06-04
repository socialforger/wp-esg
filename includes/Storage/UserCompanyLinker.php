<?php
namespace WpEsg\Storage;

if (!defined('ABSPATH')) {
    exit;
}

use WpEsg\Storage\Registry\It\Registry as ItRegistry;
use WpEsg\Frameworks\FrameworkRegistry;

/**
 * Class UserCompanyLinker
 * Interlaces basic industrial parameters against geographical registries to compile context metadata boundaries.
 *
 * @package WpEsg\Storage
 */
class UserCompanyLinker {

    /**
     * Resolves the analytical sector category and questionnaire routing criteria for a specific enterprise profile.
     * Consumes country-specific modular packages using clean algorithmic isolation rules.
     *
     * @param string $country           ISO two-letter country code specification string (e.g., "IT").
     * @param string $businessCode      Raw economic classification index input text string (e.g., "01.11.00").
     * @param string $legalEntity       Raw corporate legal structure format text string (e.g., "Soc. Coop.").
     * @param int    $employeeCount     Corporate employee headcount threshold metric.
     * @return array                    Compiled runtime execution boundary state configuration sheet context.
     */
    public static function resolveContext(string $country, string $businessCode, string $legalEntity, int $employeeCount): array {
        $normalizedCountry = strtoupper(trim($country));
        $macroSectorToken  = '';
        $governanceTier    = 'GENERIC';

        // 1. Instanziazione dinamica del modulo geografico di competenza (attualmente circoscritto all'Italia)
        if ($normalizedCountry === 'IT') {
            $itDriver = new ItRegistry();
            
            // Estrae il token merceologico depurato ad albero invertito (es. "AGRICULTURE")
            $macroSectorToken = $itDriver->getSector($businessCode);
            
            // Estrae i parametri normalizzati della forma societaria (es. "COOPERATIVE_SOLIDARY")
            $legalEntityMeta  = $itDriver->getLegalEntityMeta($legalEntity);
            $governanceTier   = $legalEntityMeta['governance_tier'] ?? 'GENERIC';
        } else {
            // Fallback precauzionale per nazioni non ancora censite nell'ecosistema
            $macroSectorToken = 'UNIVERSAL_SERVICES';
        }

        // 2. Applicazione della regola volumetrica dimensionale: <= 10 dipendenti attiva il tracciato Short
        $sizeScope = ($employeeCount <= 10) ? 'Short' : 'Long';

        // 3. Risoluzione della visibility-track agganciando il core di FrameworkRegistry
        $resolvedQualitativeModule = FrameworkRegistry::mapSectorToCategory($macroSectorToken);

        return [
            'company_size_scope' => $sizeScope,
            'qualitative_module' => $resolvedQualitativeModule,
            'governance_profile' => $governanceTier,
            'active_pgs_pact'    => ($resolvedQualitativeModule !== 'none' && $governanceTier !== 'UNKNOWN')
        ];
    }
}
