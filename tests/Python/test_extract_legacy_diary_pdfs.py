import importlib.util
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('extract_diaries', Path(__file__).parents[2] / 'scripts/extract_legacy_diary_pdfs.py')
extract = importlib.util.module_from_spec(spec)
spec.loader.exec_module(extract)


class ExtractLegacyDiariesTest(unittest.TestCase):
    def document(self, year, period):
        return f'''Escola Laura Vicuna
EDUCAÇÃO BÁSICA, {year}. 9° ANO, ENSINO FUNDAMENTAL. ARTE, 40h - Área: Linguagens.
FICHA DE REGISTRO DE PROGRESSÃO
   ESTUDANTE COM NOME
53              0.00 8.00 - 9.00 8.50 1 - 2 - 3 aprovado
   QUEBRA DE LINHA
FREQUÊNCIA {period} BIMESTRE
ID  Estudante                               04  05 TF
                                            02  02
53  ESTUDANTE COM NOME QUEBRA DE LINHA        *   F  1
Observações:
CONTEÚDOS {period} BIMESTRE
04-02-{year} 2 Aula com duas horas-aula.
05-02-{year} 1 Aula com uma hora-aula.
'''

    def test_2025_numeric_period_and_wrapped_student_name_preserve_zero(self):
        with patch.object(extract, 'pdf_text', return_value=self.document(2025, '1°')):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual(2025, result['reference_year'])
        self.assertEqual('ESTUDANTE COM NOME QUEBRA DE LINHA', result['students'][0]['student_name'])
        self.assertEqual({'1': 0.0, '2': 8.0, '4': 9.0}, result['students'][0]['grades'])
        self.assertEqual(3, sum(row['lesson_count'] for row in result['sessions']))
        self.assertEqual('absent', result['sessions'][1]['attendance'][result['students'][0]['student_name']])
        self.assertEqual([], result['issues'])

    def test_2026_roman_period_keeps_its_original_year(self):
        with patch.object(extract, 'pdf_text', return_value=self.document(2026, 'I')):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual(2026, result['reference_year'])
        self.assertTrue(all(row['date'].startswith('2026-') for row in result['sessions']))
        self.assertEqual(1, result['sessions'][0]['period'])


if __name__ == '__main__':
    unittest.main()
