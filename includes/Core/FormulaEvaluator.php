<?php

namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

class FormulaEvaluator
{
    public function evaluate(
        string $formula,
        array $context = []
    ): float {

        $expression = $formula;

        $metrics = $context['metrics'] ?? [];

        foreach ($metrics as $key => $value) {

            $expression = str_replace(
                "metrics['{$key}']",
                (string)(float)$value,
                $expression
            );

            $expression = str_replace(
                "metrics.{$key}",
                (string)(float)$value,
                $expression
            );
        }

        $indicators = $context['indicators'] ?? [];

        foreach ($indicators as $key => $value) {

            $expression = str_replace(
                "indicators['{$key}']",
                (string)(float)$value,
                $expression
            );

            $expression = str_replace(
                "indicators.{$key}",
                (string)(float)$value,
                $expression
            );
        }

        /*
         * Variabili residue → 0
         */

        $expression = preg_replace(
            "/metrics\\[['\"]([^'\"]+)['\"]\\]/",
            '0',
            $expression
        );

        $expression = preg_replace(
            "/indicators\\[['\"]([^'\"]+)['\"]\\]/",
            '0',
            $expression
        );

        $expression = preg_replace(
            "/metrics\\.[a-zA-Z0-9_]+/",
            '0',
            $expression
        );

        $expression = preg_replace(
            "/indicators\\.[a-zA-Z0-9_]+/",
            '0',
            $expression
        );

        if (!$this->isSafeExpression($expression)) {

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    '[WP-ESG] Invalid formula: ' .
                    $formula .
                    ' => ' .
                    $expression
                );
            }

            return 0.0;
        }

        try {

            return (float) eval(
                "return ({$expression});"
            );

        } catch (\Throwable $e) {

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    '[WP-ESG] Formula error: ' .
                    $formula .
                    ' => ' .
                    $e->getMessage()
                );
            }

            return 0.0;
        }
    }

    private function isSafeExpression(
        string $expression
    ): bool {

        return preg_match(
            '/^[0-9+\-*\/().<>=!?:&|,% ]+$/',
            $expression
        ) === 1;
    }
}
