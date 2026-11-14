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
        self.assertEqual(3.0, result['students'][0]['reported_total_absences'])
        self.assertEqual({'1': 1.0, '2': None, '3': 2.0, '4': None}, result['students'][0]['reported_absences'])

    def test_wrapped_attendance_row_does_not_use_a_presence_mark_as_student_name(self):
        text = self.document(2025, 'I') + '''
FREQUÊNCIA II BIMESTRE
ID  Estudante                               04  05 TF
                                            05  05
    ESTUDANTE COM NOME QUEBRA DE LINHA
53                                          F   *  1
CONTEÚDOS II BIMESTRE
04-05-2025 1 Aula do segundo bimestre.
05-05-2025 1 Aula do segundo bimestre.
'''
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual([], result['issues'])
        self.assertEqual('absent', result['sessions'][2]['attendance']['ESTUDANTE COM NOME QUEBRA DE LINHA'])

    def test_a_grade_only_document_does_not_imply_attendance(self):
        text = self.document(2024, 'I').split('FREQUÊNCIA')[0]
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual(1, len(result['students']))
        self.assertEqual([], result['sessions'])

    def test_hash_marks_are_explicit_exclusions_and_do_not_override_a_reenrollment(self):
        text = self.document(2025, 'I').replace('53  ESTUDANTE COM NOME QUEBRA DE LINHA        *   F  1',
            '53  ESTUDANTE COM NOME QUEBRA DE LINHA        #   #  0\n53  ESTUDANTE COM NOME QUEBRA DE LINHA        *   #  0')
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        name = result['students'][0]['student_name']
        self.assertEqual('present', result['sessions'][0]['attendance'][name])
        self.assertEqual([], result['sessions'][0]['not_enrolled'])
        self.assertEqual([name], result['sessions'][1]['not_enrolled'])
        self.assertEqual([], result['issues'])

    def test_conflicting_duplicate_marks_are_reported(self):
        text = self.document(2025, 'I').replace('Observações:',
            '53  ESTUDANTE COM NOME QUEBRA DE LINHA        F   F  2\nObservações:')
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual('conflicting_frequency_rows', result['issues'][0]['type'])

    def test_verified_roster_resolves_different_wrapped_fragments_without_accepting_another_name(self):
        text = self.document(2025, 'I').replace('53  ESTUDANTE COM NOME QUEBRA DE LINHA        *   F  1',
            '53  ESTUDANTE        *   F  1')
        text += '''
FREQUÊNCIA II BIMESTRE
ID  Estudante                               04  05 TF
                                            05  05
53  QUEBRA                                  *   F  1
CONTEÚDOS II BIMESTRE
04-05-2025 1 Continuação.
05-05-2025 1 Continuação.
'''
        names = {53: 'ESTUDANTE COM NOME QUEBRA DE LINHA'}
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext', names)
        self.assertEqual([], result['issues'])
        self.assertEqual(names[53], result['students'][0]['student_name'])
        with patch.object(extract, 'pdf_text', return_value=text.replace('53  QUEBRA', '53  OUTRA PESSOA')):
            with self.assertRaisesRegex(ValueError, 'incompatível'):
                extract.parse_document(Path('example.pdf'), 'pdftotext', names)

    def test_progress_table_continues_after_print_footer_on_the_next_page(self):
        text = self.document(2025, 'I').replace('FREQUÊNCIA I BIMESTRE',
            'Ficha gerada para impressão\n\fFICHA DE REGISTRO DE PROGRESSÃO\n54 OUTRO ESTUDANTE 6 7 8 9 7.5 - - - - 0 aprovado\nFREQUÊNCIA I BIMESTRE')
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual([53, 54], [student['legacy_student_id'] for student in result['students']])

    def test_progress_uses_printed_period_order_instead_of_column_position(self):
        text = self.document(2025, 'I').replace('FICHA DE REGISTRO DE PROGRESSÃO',
            'FICHA DE REGISTRO DE PROGRESSÃO\nI Bimestre III Bimestre IV Bimestre II Bimestre I Bimestre III Bimestre IV Bimestre II Bimestre')
        with patch.object(extract, 'pdf_text', return_value=text):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        student = result['students'][0]
        self.assertEqual({'1': 0.0, '3': 8.0, '2': 9.0}, student['grades'])
        self.assertEqual({'1': 1.0, '3': None, '4': 2.0, '2': None}, student['reported_absences'])
        self.assertEqual([1, 3, 4, 2], student['grade_column_order'])

    def test_progress_rejects_ambiguous_period_order(self):
        text = self.document(2025, 'I').replace('FICHA DE REGISTRO DE PROGRESSÃO',
            'FICHA DE REGISTRO DE PROGRESSÃO\nI Bimestre I Bimestre III Bimestre IV Bimestre')
        with patch.object(extract, 'pdf_text', return_value=text):
            with self.assertRaisesRegex(ValueError, 'ambígua'):
                extract.parse_document(Path('example.pdf'), 'pdftotext')

    def test_2026_roman_period_keeps_its_original_year(self):
        with patch.object(extract, 'pdf_text', return_value=self.document(2026, 'I')):
            result = extract.parse_document(Path('example.pdf'), 'pdftotext')
        self.assertEqual(2026, result['reference_year'])
        self.assertTrue(all(row['date'].startswith('2026-') for row in result['sessions']))
        self.assertEqual(1, result['sessions'][0]['period'])


if __name__ == '__main__':
    unittest.main()
