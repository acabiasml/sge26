#!/usr/bin/env python3
"""Extract legacy 2026 class diary PDFs into an auditable JSON manifest."""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import unicodedata
from pathlib import Path


SCHOOLS = {
    "Liceu Pedagógico São Francisco de Assis": "beaba",
    "LAR SÃO DOMINGOS SÁVIO": "lar",
    "Escola Laura Vicuna": "laura",
}

ROMAN_PERIODS = {"I": 1, "II": 2, "III": 3, "IV": 4}
VALUE = r"(?:-|\d+(?:[.,]\d+)?)"
PROGRESS_RE = re.compile(
    rf"^\s*(\d+)\s+(.+?)\s+({VALUE})\s+({VALUE})\s+({VALUE})\s+({VALUE})"
    rf"\s+({VALUE})\s+({VALUE})\s+({VALUE})\s+({VALUE})\s+({VALUE})\s+({VALUE})\s+\S+\s*$"
)
HEADER_RE = re.compile(
    r"Educação Básica,\s*2026\.\s*(.+?),\s*(ENSINO\s+(?:FUNDAMENTAL|MEDIO))\.\s*(.+?),\s*\d+h\s*-\s*Área:",
    re.IGNORECASE,
)
CONTENT_RE = re.compile(r"^\s*(\d{2}-\d{2}-2026)\s+(\d+)\s+(.+?)\s*$")
FREQUENCY_RE = re.compile(r"FREQUÊNCIA\s+(I|II|III|IV)\s+BIMESTRE", re.IGNORECASE)


def normalized(value: str) -> str:
    value = unicodedata.normalize("NFKD", value)
    value = "".join(char for char in value if not unicodedata.combining(char))
    return re.sub(r"[^a-z0-9]+", " ", value.lower()).strip()


def pdf_text(path: Path, pdftotext: str) -> str:
    result = subprocess.run(
        [pdftotext, "-layout", "-enc", "UTF-8", str(path), "-"],
        check=True,
        capture_output=True,
    )
    return result.stdout.decode("utf-8")


def score(value: str) -> float | None:
    if value == "-":
        return None
    return float(value.replace(",", "."))


def parse_progress(lines: list[str]) -> list[dict]:
    results: list[dict] = []
    in_progress = False
    for line in lines:
        if "FICHA DE REGISTRO DE PROGRESSÃO" in line:
            in_progress = True
            continue
        if in_progress and ("FREQUÊNCIA " in line or "Ficha gerada" in line):
            if results:
                break
        if not in_progress:
            continue
        match = PROGRESS_RE.match(line)
        if not match:
            continue
        notes = [score(value) for value in match.groups()[2:6]]
        results.append({
            "legacy_student_id": int(match.group(1)),
            "student_name": match.group(2).strip(),
            "grades": {str(index): value for index, value in enumerate(notes, 1) if value is not None},
        })
    return results


def positions(line: str, pattern: str) -> list[int]:
    return [match.start() for match in re.finditer(pattern, line)]


def parse_frequency_sections(lines: list[str], student_names: dict[int, str]) -> tuple[dict[str, dict], list[dict]]:
    sessions: dict[str, dict] = {}
    issues: list[dict] = []
    index = 0
    while index < len(lines):
        heading = FREQUENCY_RE.search(lines[index])
        if not heading:
            index += 1
            continue
        period = ROMAN_PERIODS[heading.group(1).upper()]
        section_end = index + 1
        while section_end < len(lines):
            if "CONTEÚDOS " in lines[section_end] or (section_end > index + 2 and FREQUENCY_RE.search(lines[section_end])):
                break
            section_end += 1
        section = lines[index:section_end]
        header_index = next((i for i, line in enumerate(section) if "Estudante" in line), None)
        if header_index is None or header_index + 1 >= len(section):
            issues.append({"type": "frequency_header_missing", "period": period})
            index = section_end
            continue
        day_line = section[header_index]
        month_line = section[header_index + 1]
        start = day_line.index("Estudante") + len("Estudante")
        tf_position = day_line.rfind("TF") if "TF" in day_line else len(day_line)
        days = [(match.group(), match.start()) for match in re.finditer(r"\b\d{2}\b", day_line[start:tf_position])]
        days = [(day, position + start) for day, position in days]
        months = re.findall(r"\b\d{2}\b", month_line[start:])
        if len(days) != len(months):
            issues.append({"type": "frequency_date_count", "period": period, "days": len(days), "months": len(months)})
            index = section_end
            continue
        dates = [f"2026-{month}-{day}" for (day, _), month in zip(days, months)]
        for date in dates:
            sessions.setdefault(date, {"date": date, "period": period, "attendance": {}})
        for line in section[header_index + 2:]:
            id_match = re.match(r"^\s*(\d+)\s+", line)
            marks = [(match.group(), match.start()) for match in re.finditer(r"(?<!\S)[*F#](?!\S)", line)]
            if not id_match or not marks:
                continue
            if len(marks) != len(dates):
                issues.append({"type": "frequency_mark_count", "period": period, "student_id": int(id_match.group(1)), "dates": len(dates), "marks": len(marks)})
                continue
            student_id = int(id_match.group(1))
            student_name = line[id_match.end():marks[0][1]].strip() or student_names.get(student_id, "")
            if not student_name:
                issues.append({"type": "frequency_student_name_missing", "period": period, "student_id": student_id})
                continue
            for date, (mark, _) in zip(dates, marks):
                if mark != "#":
                    sessions[date]["attendance"][student_name] = "absent" if mark == "F" else "present"
        index = section_end
    return sessions, issues


def parse_contents(lines: list[str], sessions: dict[str, dict]) -> list[dict]:
    contents: list[dict] = []
    for line in lines:
        match = CONTENT_RE.match(line)
        if not match:
            continue
        day, month, year = match.group(1).split("-")
        date = f"{year}-{month}-{day}"
        content = match.group(3).strip()
        row = {"date": date, "lesson_count": int(match.group(2)), "content": content}
        contents.append(row)
        session = sessions.setdefault(date, {"date": date, "period": None, "attendance": {}})
        session["lesson_count"] = row["lesson_count"]
        session["content"] = content
    return contents


def parse_document(path: Path, pdftotext: str) -> dict:
    text = pdf_text(path, pdftotext)
    lines = text.splitlines()
    school_name = next((school for school in SCHOOLS if school in text[:3000]), None)
    header = HEADER_RE.search(text)
    if not school_name or not header:
        raise ValueError("Cabeçalho de escola/turma/componente não reconhecido")
    students = parse_progress(lines)
    sessions, issues = parse_frequency_sections(
        lines,
        {student["legacy_student_id"]: student["student_name"] for student in students},
    )
    contents = parse_contents(lines, sessions)
    return {
        "file": path.name,
        "source": SCHOOLS[school_name],
        "school": school_name,
        "class_name": header.group(1).strip(),
        "education_level": re.sub(r"\s+", " ", header.group(2)).upper(),
        "component_name": header.group(3).strip(),
        "normalized_class": normalized(header.group(1)),
        "normalized_component": normalized(header.group(3)),
        "students": students,
        "sessions": sorted(sessions.values(), key=lambda item: item["date"]),
        "content_rows": len(contents),
        "issues": issues,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--pdftotext", default="pdftotext")
    args = parser.parse_args()
    documents = []
    failures = []
    for path in sorted(args.input.glob("*.pdf")):
        try:
            documents.append(parse_document(path, args.pdftotext))
        except Exception as error:  # preserve every source failure in the audit file
            failures.append({"file": path.name, "error": str(error)})
    manifest = {
        "format": "legacy-diary-pdf-manifest-v1",
        "documents": documents,
        "failures": failures,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({
        "documents": len(documents),
        "failures": len(failures),
        "sessions": sum(len(document["sessions"]) for document in documents),
        "grades": sum(sum(len(student["grades"]) for student in document["students"]) for document in documents),
        "issues": sum(len(document["issues"]) for document in documents),
    }, ensure_ascii=False))
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
