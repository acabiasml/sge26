<?php

namespace App\Support;

class ScheduleTeacherColor
{
    private const PALETTE = [
        ['#4e713e', '#eaf3e5'],
        ['#4a86a0', '#e7f2f6'],
        ['#db6b30', '#fff0e7'],
        ['#7f56d9', '#f1ecff'],
        ['#c7537e', '#fdeaf2'],
        ['#2a9d8f', '#e6f6f4'],
        ['#94623f', '#f5ece5'],
        ['#6172a6', '#edf0fa'],
        ['#9b6a20', '#fbf0d9'],
        ['#6f5f58', '#f1ede9'],
    ];

    /**
     * @return array{border: string, background: string}
     */
    public static function for(?int $teacherId, ?string $teacherName = null): array
    {
        if ($teacherId === null && blank($teacherName)) {
            return ['border' => '#9a8f86', 'background' => '#f4f1ed'];
        }

        $seed = $teacherId ?? abs(crc32((string) $teacherName));
        $colors = self::PALETTE[$seed % count(self::PALETTE)];

        return ['border' => $colors[0], 'background' => $colors[1]];
    }
}
