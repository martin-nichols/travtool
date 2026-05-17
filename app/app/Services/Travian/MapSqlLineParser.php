<?php

namespace App\Services\Travian;

use RuntimeException;

class MapSqlLineParser
{
    public function parse(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        if (! preg_match('/^INSERT INTO\s+`[^`]+`\s+VALUES\s*\((.*)\);\s*$/i', $line, $matches)) {
            throw new RuntimeException('Unsupported map.sql line format.');
        }

        $tokens = $this->splitTuple($matches[1]);

        if (count($tokens) !== 16) {
            throw new RuntimeException(sprintf('Expected 16 values, got %d.', count($tokens)));
        }

        return [
            'map_tile_id' => $this->toInt($tokens[0]),
            'x' => $this->toInt($tokens[1]),
            'y' => $this->toInt($tokens[2]),
            'tribe_id' => $this->toInt($tokens[3]),
            'external_village_id' => $this->toInt($tokens[4]),
            'village_name_raw' => $this->toString($tokens[5]) ?? '',
            'external_player_id' => $this->toInt($tokens[6]),
            'player_name_raw' => $this->toString($tokens[7]) ?? '',
            'external_alliance_id' => $this->toNullableInt($tokens[8]) ?? 0,
            'alliance_tag_raw' => $this->toString($tokens[9]),
            'population' => $this->toInt($tokens[10]),
            'region_id' => $this->toNullableInt($tokens[11]),
            'is_capital' => $this->toNullableBool($tokens[12]),
            'is_city' => $this->toNullableBool($tokens[13]),
            'has_harbor' => $this->toNullableBool($tokens[14]),
            'victory_points' => $this->toNullableInt($tokens[15]),
        ];
    }

    /**
     * @return list<string>
     */
    private function splitTuple(string $tuple): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $length = strlen($tuple);

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                $current .= $char;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ',') {
                $tokens[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $tokens[] = trim($current);

        return $tokens;
    }

    private function toNullableBool(string $value): ?bool
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            'TRUE' => true,
            'FALSE' => false,
            'NULL' => null,
            default => throw new RuntimeException(sprintf('Invalid boolean token [%s].', $value)),
        };
    }

    private function toInt(string $value): int
    {
        $normalized = trim($value);

        if (! preg_match('/^-?\d+$/', $normalized)) {
            throw new RuntimeException(sprintf('Invalid integer token [%s].', $value));
        }

        return (int) $normalized;
    }

    private function toNullableInt(string $value): ?int
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === 'NULL') {
            return null;
        }

        return $this->toInt($value);
    }

    private function toString(string $value): ?string
    {
        $value = trim($value);
        $upper = strtoupper($value);

        if ($upper === 'NULL') {
            return null;
        }

        if ($value === '""' || $value === "''") {
            return '';
        }

        $first = $value[0] ?? null;
        $last = $value[strlen($value) - 1] ?? null;

        if (($first === '\'' || $first === '"') && $last === $first) {
            $inner = substr($value, 1, -1);
            $inner = str_replace(['\\\\', '\\\'', '\\"'], ['\\', '\'', '"'], $inner);

            return $inner;
        }

        return $value;
    }
}
