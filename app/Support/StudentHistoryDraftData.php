<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JsonException;

final class StudentHistoryDraftData
{
    public static function decode(string $snapshot): array
    {
        try {
            $entries = json_decode($snapshot, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::invalid();
        }

        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > 12000) {
            throw self::invalid();
        }

        $data = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! array_is_list($entry) || count($entry) !== 2
                || ! is_string($entry[0]) || ! is_string($entry[1])) {
                throw self::invalid();
            }

            [$name, $value] = $entry;
            if (in_array($name, ['school_id', 'technical_course_id'], true)) {
                if ($value !== '' && ! ctype_digit($value)) {
                    throw self::invalid();
                }
                $data[$name] = $value;
            } elseif ($name === 'removed_year_ids[]') {
                if (! ctype_digit($value)) {
                    throw self::invalid();
                }
                $data['removed_year_ids'][] = $value;
            } elseif (preg_match('/^years\[(\d{1,3})\]\[enabled_formations\]\[\]$/D', $name, $match)) {
                $data['years'][(int) $match[1]]['enabled_formations'][] = $value;
            } elseif (preg_match('/^years\[(\d{1,3})\]\[formation_workload_hours\]\[([^\[\]]{1,255})\]$/uD', $name, $match)) {
                $data['years'][(int) $match[1]]['formation_workload_hours'][$match[2]] = $value;
            } elseif (preg_match('/^years\[(\d{1,3})\]\[([a-z_]+)\]$/D', $name, $match)
                && ! in_array($match[2], ['enabled_formations', 'formation_workload_hours'], true)) {
                Arr::set($data, 'years.'.(int) $match[1].'.'.$match[2], $value);
            } elseif (preg_match('/^year_components\[(\d{1,3})\]\[(\d{1,4})\]\[(?:record\]\[)?([a-z_]+)\]$/D', $name, $match)
                && $match[3] !== 'record') {
                $field = str_contains($name, '[record][') ? 'record.'.$match[3] : $match[3];
                Arr::set($data, 'year_components.'.(int) $match[1].'.'.(int) $match[2].'.'.$field, $value);
            } else {
                throw self::invalid();
            }
        }

        if (! array_is_list($data['years'] ?? []) || empty($data['years'])) {
            throw self::invalid();
        }
        foreach ($data['year_components'] ?? [] as $year => $rows) {
            if (! isset($data['years'][$year])) {
                throw self::invalid();
            }
            $data['year_components'][$year] = array_values($rows);
        }

        return $data;
    }

    private static function invalid(): ValidationException
    {
        return ValidationException::withMessages(['snapshot' => __('Não foi possível interpretar o rascunho do histórico.')]);
    }
}
