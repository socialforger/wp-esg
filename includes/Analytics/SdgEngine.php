<?php

namespace WpEsg\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class SdgEngine
{
    private string $mappingPath;

    public function __construct()
    {
        $this->mappingPath =
            WP_ESG_PATH .
            'frameworks/sdg/sdg_mapping.json';
    }

    /**
     * Associa gli SDG agli indicatori presenti.
     */
    public function computeUnlockedGoals(
        array $indicatorsMap
    ): array {

        if (!file_exists($this->mappingPath)) {
            return [];
        }

        $mapping = json_decode(
            file_get_contents(
                $this->mappingPath
            ),
            true
        );

        $sdgs =
            $mapping['mappings'] ?? [];

        $results = [];

        foreach ($sdgs as $sdgCode => $sdgData) {

            $linkedIndicators =
                $sdgData['indicators'] ?? [];

            $score = 0;
            $count = 0;

            foreach (
                $linkedIndicators
                as $indicatorId
            ) {

                if (
                    isset(
                        $indicatorsMap[
                            $indicatorId
                        ]
                    )
                ) {

                    $score +=
                        (float)
                        $indicatorsMap[
                            $indicatorId
                        ];

                    $count++;
                }
            }

            $average =
                $count > 0
                    ? $score / $count
                    : 0;

            $results[$sdgCode] = [

                'goal_code' => $sdgCode,

                'goal_name' =>
                    $sdgData['name'] ?? '',

                'score' =>
                    round(
                        $average,
                        2
                    ),

                'indicator_count' =>
                    $count,

                'audited_at' =>
                    gmdate(
                        'Y-m-d H:i:s'
                    )
            ];
        }

        return $results;
    }
}
