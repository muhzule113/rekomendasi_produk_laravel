# -*- coding: utf-8 -*-
"""Convert Panduan_Isi_Tabel_Evaluasi_Offline.md -> PDF."""
from __future__ import annotations

import re
from pathlib import Path

import markdown
from xhtml2pdf import pisa

MD_PATH = Path(__file__).resolve().parents[1] / (
    "docs/laporan/Panduan_Isi_Tabel_Evaluasi_Offline.md"
)
PDF_PATH = MD_PATH.with_suffix(".pdf")


def simplify_math(text: str) -> str:
    """Turn simple LaTeX blocks into readable HTML for PDF."""

    def block_repl(match: re.Match[str]) -> str:
        inner = match.group(1)
        inner = (
            inner.replace(r"\mathrm{", "")
            .replace("}", "")
            .replace(r"\times", "×")
            .replace(r"\ge", "≥")
            .replace(r"\sqrt", "√")
            .replace(r"\begin{cases}", "")
            .replace(r"\end{cases}", "")
            .replace(r"\\", " | ")
            .replace("&", " ")
            .replace("\n", " ")
        )
        inner = re.sub(r"\s+", " ", inner).strip()
        return f'<div class="formula">{inner}</div>'

    text = re.sub(r"\\\[(.*?)\\\]", block_repl, text, flags=re.S)
    text = re.sub(r"\\\((.*?)\\\)", r"<code>\1</code>", text)
    text = (
        text.replace(r"\mathrm{", "")
        .replace(r"\times", "×")
        .replace(r"\ge", "≥")
        .replace(r"\sqrt", "√")
    )
    return text


def main() -> None:
    text = MD_PATH.read_text(encoding="utf-8")
    text = simplify_math(text)
    body = markdown.markdown(
        text,
        extensions=["tables", "fenced_code", "nl2br", "sane_lists"],
    )
    html = f"""<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<style>
@page {{ size: A4; margin: 1.8cm; }}
body {{
  font-family: Helvetica, Arial, sans-serif;
  font-size: 10pt;
  line-height: 1.45;
  color: #111;
}}
h1 {{ font-size: 16pt; margin-top: 0; }}
h2 {{
  font-size: 12.5pt;
  margin-top: 16pt;
  border-bottom: 1px solid #bbb;
  padding-bottom: 3pt;
}}
h3 {{ font-size: 11pt; margin-top: 12pt; }}
table {{
  border-collapse: collapse;
  width: 100%;
  margin: 8pt 0 12pt 0;
  font-size: 8.5pt;
}}
th, td {{
  border: 1px solid #444;
  padding: 3pt 5pt;
  vertical-align: top;
}}
th {{ background: #eeeeee; }}
code {{
  font-family: Courier, monospace;
  font-size: 8pt;
  background: #f3f3f3;
}}
pre {{
  font-family: Courier, monospace;
  font-size: 8pt;
  background: #f5f5f5;
  padding: 7pt;
  border: 1px solid #ddd;
  white-space: pre-wrap;
}}
.formula {{
  font-family: Courier, monospace;
  background: #f7f7f7;
  padding: 6pt 8pt;
  margin: 8pt 0;
  border-left: 3px solid #666;
}}
ul, ol {{ margin: 6pt 0 6pt 16pt; }}
li {{ margin-bottom: 2pt; }}
hr {{ border: none; border-top: 1px solid #ccc; margin: 14pt 0; }}
</style>
</head>
<body>
{body}
</body>
</html>
"""
    with PDF_PATH.open("wb") as out:
        result = pisa.CreatePDF(html.encode("utf-8"), dest=out, encoding="utf-8")
    if result.err:
        raise SystemExit(f"PDF generation failed with {result.err} error(s)")
    print(f"Wrote {PDF_PATH} ({PDF_PATH.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
