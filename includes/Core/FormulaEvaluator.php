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
        }

        $indicators = $context['indicators'] ?? [];

        foreach ($indicators as $key => $value) {

            $expression = str_replace(
                "indicators['{$key}']",
                (string)(float)$value,
                $expression
            );
        }

        if (!$this->isSafeExpression($expression)) {
            return 0.0;
        }

        try {

            return (float) eval(
                "return ({$expression});"
            );

        } catch (\Throwable $e) {

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
