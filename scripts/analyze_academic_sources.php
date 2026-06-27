<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$base = realpath(__DIR__.'/../storage/app/private');

if ($base === false) {
    fwrite(STDERR, "Diretório storage/app/private não encontrado.\n");
    exit(1);
}

$files = [
    'MATRIZ CURRICULAR FUND 2021 LICEU.xlsx',
    'MATRIZ CURRICULAR MEDIO 2021 LICEU.xlsx',
    '2022 MATRIZES.docx',
    '2024 MATRIZES.docx',
    '2025 MATRIZES.docx',
    'MATRIZ 2026 FINAL.pdf',
    'MATRIZ CURRICULAR 2019 - LICEU 2019.pdf',
    'MATRIZ CURRICULAR 2020 - LICEU.pdf',
];

foreach ($files as $file) {
    $path = $base.DIRECTORY_SEPARATOR.$file;
    echo "\n===== {$file} =====\n";

    if (! is_file($path)) {
        echo "Arquivo não encontrado.\n";
        continue;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    try {
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            summarizeSpreadsheet($path);
        } elseif ($extension === 'docx') {
            summarizeDocx($path);
        } elseif ($extension === 'pdf') {
            summarizePdf($path);
        }
    } catch (Throwable $exception) {
        echo "Falha ao ler: {$exception->getMessage()}\n";
    }
}

function summarizeSpreadsheet(string $path): void
{
    $spreadsheet = IOFactory::load($path);

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        echo "-- Aba: {$sheet->getTitle()}\n";
        $highestRow = min($sheet->getHighestDataRow(), 45);
        $highestColumn = $sheet->getHighestDataColumn();

        for ($row = 1; $row <= $highestRow; $row++) {
            $values = [];

            foreach ($sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, true, true)[$row] as $value) {
                $text = trim((string) $value);

                if ($text !== '') {
                    $values[] = preg_replace('/\s+/', ' ', $text);
                }
            }

            if ($values !== []) {
                echo implode(' | ', $values)."\n";
            }
        }
    }
}

function summarizeDocx(string $path): void
{
    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        echo "Não foi possível abrir o DOCX.\n";
        return;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        echo "Documento sem word/document.xml.\n";
        return;
    }

    $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));
    $lines = relevantLines($text);

    foreach (array_slice($lines, 0, 120) as $line) {
        echo $line."\n";
    }
}

function summarizePdf(string $path): void
{
    $content = file_get_contents($path);

    if ($content === false) {
        echo "Não foi possível abrir o PDF.\n";
        return;
    }

    preg_match_all('/\(([^()]{3,120})\)/', $content, $matches);
    $text = implode("\n", array_map(static fn ($value) => str_replace(['\\(', '\\)'], ['(', ')'], $value), $matches[1] ?? []));
    $lines = relevantLines($text);

    if ($lines === []) {
        echo "Extração simples não encontrou texto legível neste PDF.\n";
        return;
    }

    foreach (array_slice($lines, 0, 120) as $line) {
        echo $line."\n";
    }
}

/**
 * @return array<int, string>
 */
function relevantLines(string $text): array
{
    $lines = preg_split('/\R+/', html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8')) ?: [];
    $result = [];

    foreach ($lines as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));

        if ($line === '' || strlen($line) < 3) {
            continue;
        }

        if (preg_match('/(matriz|curricular|componente|lingua|língua|matem|hist|geog|cien|ciên|fisic|físic|quim|quím|bio|soci|filo|arte|relig|projeto|vida|itiner|m[oó]veis|marcen|carga|hor[áa]ria|ano|m[oó]dulo|ensino|fundamental|m[ée]dio|t[ée]cnico)/iu', $line)) {
            $result[] = $line;
        }
    }

    return array_values(array_unique($result));
}
