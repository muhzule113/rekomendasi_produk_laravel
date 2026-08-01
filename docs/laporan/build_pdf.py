"""Convert laporan Markdown -> HTML -> PDF (Edge headless)."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

import markdown

ROOT = Path(__file__).resolve().parent
MD_PATH = ROOT / "Laporan_Pengujian_Sistem_Rekomendasi.md"
HTML_PATH = ROOT / "Laporan_Pengujian_Sistem_Rekomendasi.html"
PDF_PATH = ROOT / "Laporan_Pengujian_Sistem_Rekomendasi.pdf"

EDGE_CANDIDATES = [
    Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
    Path(r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"),
]

CSS = """
@page { size: A4; margin: 18mm 16mm 20mm 16mm; }
* { box-sizing: border-box; }
body {
  font-family: "Times New Roman", Times, Georgia, serif;
  font-size: 11.5pt;
  line-height: 1.55;
  color: #111;
  max-width: 210mm;
  margin: 0 auto;
  padding: 12mm 10mm;
  background: #fff;
}
h1 {
  font-size: 16pt;
  text-align: center;
  margin: 0 0 0.4em;
  line-height: 1.35;
  page-break-after: avoid;
}
h2 {
  font-size: 13.5pt;
  margin-top: 1.6em;
  border-bottom: 1.5px solid #222;
  padding-bottom: 0.25em;
  page-break-after: avoid;
}
h3 {
  font-size: 12pt;
  margin-top: 1.2em;
  page-break-after: avoid;
}
h4 { font-size: 11.5pt; margin-top: 1em; page-break-after: avoid; }
p { text-align: justify; margin: 0.55em 0; }
ul, ol { margin: 0.4em 0 0.4em 1.3em; }
li { margin: 0.2em 0; }
blockquote {
  margin: 0.8em 0;
  padding: 0.5em 0.9em;
  border-left: 3px solid #555;
  background: #f6f6f6;
  font-size: 10.5pt;
}
code {
  font-family: Consolas, "Courier New", monospace;
  font-size: 9.5pt;
  background: #f2f2f2;
  padding: 0.05em 0.3em;
  border-radius: 3px;
}
pre {
  font-family: Consolas, "Courier New", monospace;
  font-size: 9pt;
  background: #f4f4f4;
  border: 1px solid #ddd;
  padding: 0.75em 0.9em;
  overflow-x: auto;
  white-space: pre-wrap;
  page-break-inside: avoid;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin: 0.8em 0 1em;
  font-size: 10pt;
  page-break-inside: avoid;
}
th, td {
  border: 1px solid #333;
  padding: 0.35em 0.5em;
  vertical-align: top;
}
th { background: #efefef; text-align: left; }
hr { border: none; border-top: 1px solid #999; margin: 1.4em 0; }
a { color: #111; text-decoration: none; }
.cover {
  text-align: center;
  margin: 2em 0 2.5em;
  page-break-after: always;
}
.cover .meta {
  margin-top: 2em;
  font-size: 11pt;
  line-height: 1.8;
}
.toc ul { list-style: none; margin-left: 0; padding-left: 0; }
.toc li { margin: 0.35em 0; }
.footer-note {
  margin-top: 2em;
  font-size: 9.5pt;
  color: #444;
  font-style: italic;
}
"""


def find_browser() -> Path:
    for path in EDGE_CANDIDATES:
        if path.exists():
            return path
    raise FileNotFoundError("Browser Edge/Chrome tidak ditemukan untuk print-to-PDF.")


def md_to_html(md_text: str) -> str:
    body = markdown.markdown(
        md_text,
        extensions=["tables", "fenced_code", "sane_lists", "toc"],
        output_format="html5",
    )
    return f"""<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Pengujian Sistem Rekomendasi — Toko Sinar Manis</title>
<style>{CSS}</style>
</head>
<body>
{body}
</body>
</html>
"""


def html_to_pdf(browser: Path, html_path: Path, pdf_path: Path) -> None:
    html_uri = html_path.resolve().as_uri()
    cmd = [
        str(browser),
        "--headless=new",
        "--disable-gpu",
        "--no-pdf-header-footer",
        f"--print-to-pdf={pdf_path.resolve()}",
        html_uri,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0 or not pdf_path.exists():
        raise RuntimeError(
            "Gagal membuat PDF.\n"
            f"stdout: {result.stdout}\nstderr: {result.stderr}"
        )


def main() -> int:
    md_text = MD_PATH.read_text(encoding="utf-8")
    HTML_PATH.write_text(md_to_html(md_text), encoding="utf-8")
    print(f"[OK] HTML: {HTML_PATH}")

    browser = find_browser()
    print(f"[OK] Browser: {browser}")
    html_to_pdf(browser, HTML_PATH, PDF_PATH)
    size_kb = PDF_PATH.stat().st_size / 1024
    print(f"[OK] PDF : {PDF_PATH} ({size_kb:.1f} KB)")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"[ERROR] {exc}", file=sys.stderr)
        raise SystemExit(1)
