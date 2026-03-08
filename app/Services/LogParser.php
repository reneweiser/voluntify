<?php

namespace App\Services;

class LogParser
{
    private const LOG_ENTRY_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s\w+\.(ERROR|WARNING|INFO|DEBUG|NOTICE|CRITICAL|ALERT|EMERGENCY):\s(.*)$/';

    /**
     * @param  list<string>  $lines
     * @return list<array{level: string, timestamp: string, message: string, trace: string}>
     */
    public function parse(array $lines): array
    {
        $entries = [];
        $current = null;
        $orphanLines = [];

        foreach ($lines as $line) {
            if (preg_match(self::LOG_ENTRY_PATTERN, $line, $matches)) {
                if ($current !== null) {
                    $current['trace'] = rtrim($current['trace']);
                    $entries[] = $current;
                } elseif (count($orphanLines) > 0) {
                    $entries[] = [
                        'level' => 'DEBUG',
                        'timestamp' => '',
                        'message' => array_shift($orphanLines),
                        'trace' => rtrim(implode("\n", $orphanLines)),
                    ];
                    $orphanLines = [];
                }

                $current = [
                    'level' => $matches[2],
                    'timestamp' => $matches[1],
                    'message' => $matches[3],
                    'trace' => '',
                ];
            } elseif ($current !== null) {
                $current['trace'] .= ($current['trace'] !== '' ? "\n" : '').$line;
            } else {
                if ($line !== '') {
                    $orphanLines[] = $line;
                }
            }
        }

        if ($current !== null) {
            $current['trace'] = rtrim($current['trace']);
            $entries[] = $current;
        } elseif (count($orphanLines) > 0) {
            $entries[] = [
                'level' => 'DEBUG',
                'timestamp' => '',
                'message' => array_shift($orphanLines),
                'trace' => rtrim(implode("\n", $orphanLines)),
            ];
        }

        return $entries;
    }
}
