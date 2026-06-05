<?php

namespace WpEsg\Core;

if (!defined('ABSPATH')) {
    exit;
}

class FormulaEvaluator
{
    /**
     * Valuta una formula ESG.
     *
     * Esempi:
     * answers.q1 + answers.q2
     * (metrics.energy / metrics.total) * 100
     */

    public function evaluate(string $expression, array $context = []): float
    {
        $expression = $this->replaceVariables($expression, $context);

        if (!$this->isSafeExpression($expression)) {
            return 0.0;
        }

        try {
            return (float) eval("return ({$expression});");
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function replaceVariables(string $expression, array $context): string
    {
        preg_match_all(
            '/([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)+)/',
            $expression,
            $matches
        );

        foreach ($matches[0] as $token) {

            $value = $this->resolveToken($token, $context);

            if (!is_numeric($value)) {
                $value = 0;
            }

            $expression = str_replace(
                $token,
                (string)$value,
                $expression
            );
        }

        return $expression;
    }

    private function resolveToken(string $token, array $context)
    {
        $parts = explode('.', $token);

        $value = $context;

        foreach ($parts as $part) {

            if (!is_array($value)) {
                return 0;
            }

            if (!array_key_exists($part, $value)) {
                return 0;
            }

            $value = $value[$part];
        }

        return $value;
    }

    private function isSafeExpression(string $expression): bool
    {
        return preg_match(
            '/^[0-9+\-*\/().<>=!&|,% ]+$/',
            $expression
        ) === 1;
    }
}
