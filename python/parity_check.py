# -*- coding: utf-8 -*-
"""
Parity PHP vs Python pada snapshot DB yang sama.

Membandingkan:
1. jumlah pasangan similarity
2. selisih skor maksimum (lulus jika <= 1e-6)
3. Top-5 rekomendasi (agregasi murni, tanpa filter stok) untuk beberapa user

Cara jalan (PowerShell):
  cd python
  # opsional jika .env tidak terbaca otomatis:
  # $env:DB_PASSWORD="root"
  py parity_check.py
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PYTHON_DIR = Path(__file__).resolve().parent
PHP_DUMP = PYTHON_DIR / "tools" / "dump_php_cf.php"
OUT_JSON = ROOT / "docs" / "laporan" / "parity_result.json"
TOLERANCE = 1e-6


def resolve_php() -> str:
    env = os.environ.get("PHP_BIN")
    if env:
        return env
    for name in ("php", "php.bat", "php.exe"):
        found = shutil.which(name)
        if found:
            return found
    herd = Path.home() / ".config" / "herd" / "bin" / "php.bat"
    if herd.is_file():
        return str(herd)
    laragon = Path(r"C:\laragon\bin\php")
    if laragon.is_dir():
        matches = sorted(laragon.glob("php-*/php.exe"), reverse=True)
        if matches:
            return str(matches[0])
    raise FileNotFoundError(
        "PHP tidak ditemukan di PATH. Set PHP_BIN ke path php.exe / php.bat."
    )


def load_dotenv(path: Path) -> None:
    if not path.is_file():
        return
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        key = key.strip()
        val = val.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = val


load_dotenv(ROOT / ".env")
sys.path.insert(0, str(PYTHON_DIR))

from cf.data_loader import load_transaction_data  # noqa: E402
from cf.evaluator import recommend_top_k  # noqa: E402
from cf.similarity import (  # noqa: E402
    build_item_user_matrix,
    compute_cosine_similarity,
    extract_unique_pairs,
)
from config import CF_CONFIG  # noqa: E402


def dump_php() -> dict:
    php_bin = resolve_php()
    if not PHP_DUMP.is_file():
        raise FileNotFoundError(f"Tidak ketemu {PHP_DUMP}")
    # Windows Herd/Laragon sering pakai php.bat → butuh shell
    use_shell = os.name == "nt" and php_bin.lower().endswith((".bat", ".cmd"))
    cmd = f'"{php_bin}" "{PHP_DUMP}"' if use_shell else [php_bin, str(PHP_DUMP)]
    proc = subprocess.run(
        cmd,
        cwd=str(ROOT),
        capture_output=True,
        text=True,
        encoding="utf-8",
        shell=use_shell,
    )
    if proc.returncode != 0:
        raise RuntimeError(
            "Gagal dump CF PHP.\n"
            f"stdout: {proc.stdout[:500]}\n"
            f"stderr: {proc.stderr[:800]}"
        )
    text = proc.stdout.strip()
    if not text:
        raise RuntimeError("Dump PHP kosong.")
    # Ambil JSON terakhir jika ada noise
    start = text.find("{")
    end = text.rfind("}")
    if start < 0 or end < 0:
        raise RuntimeError(f"Output PHP bukan JSON: {text[:300]}")
    return json.loads(text[start : end + 1])


def compute_python() -> dict:
    min_co = CF_CONFIG["min_co_occurrence"]
    df = load_transaction_data()
    if df.empty:
        return {
            "engine": "python",
            "min_co_occurrence": min_co,
            "pair_count": 0,
            "pairs": {},
            "stats": {"error": "Tidak ada data interaksi valid"},
        }

    matrix = build_item_user_matrix(df)
    sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=min_co)
    raw_pairs = extract_unique_pairs(sim_df, co_df)

    pairs = {}
    for a, b, score, co in raw_pairs:
        key = f"{a}-{b}"
        pairs[key] = {
            "product_a": a,
            "product_b": b,
            "score": float(score),
            "co_occurrence": int(co),
        }

    return {
        "engine": "python",
        "min_co_occurrence": min_co,
        "pair_count": len(pairs),
        "pairs": pairs,
        "stats": {
            "total_products_in_matrix": int(matrix.shape[0]),
            "total_users": int(matrix.shape[1]),
        },
    }


def pairs_to_sim_map(pairs: dict) -> dict[tuple[int, int], float]:
    sim_map: dict[tuple[int, int], float] = {}
    for item in pairs.values():
        a, b, score = item["product_a"], item["product_b"], float(item["score"])
        sim_map[(a, b)] = score
        sim_map[(b, a)] = score
    return sim_map


def compare_scores(php_pairs: dict, py_pairs: dict) -> dict:
    keys_php = set(php_pairs)
    keys_py = set(py_pairs)
    only_php = sorted(keys_php - keys_py)
    only_py = sorted(keys_py - keys_php)
    common = keys_php & keys_py

    max_delta = 0.0
    worst_key = None
    deltas = []
    for key in common:
        d = abs(float(php_pairs[key]["score"]) - float(py_pairs[key]["score"]))
        deltas.append(d)
        if d > max_delta:
            max_delta = d
            worst_key = key

    return {
        "common_pairs": len(common),
        "only_php": len(only_php),
        "only_py": len(only_py),
        "only_php_sample": only_php[:5],
        "only_py_sample": only_py[:5],
        "max_abs_delta": max_delta,
        "worst_pair": worst_key,
        "mean_abs_delta": (sum(deltas) / len(deltas)) if deltas else 0.0,
        "score_pass": max_delta <= TOLERANCE and not only_php and not only_py,
    }


def compare_topk(sample_users: list, php_pairs: dict, py_pairs: dict, k: int = 5) -> list:
    php_map = pairs_to_sim_map(php_pairs)
    py_map = pairs_to_sim_map(py_pairs)
    rows = []
    for user in sample_users:
        uid = user["id_user"]
        bought = [int(x) for x in user["bought"]]
        top_php = recommend_top_k(bought, php_map, k)
        top_py = recommend_top_k(bought, py_map, k)
        rows.append({
            "id_user": uid,
            "bought_count": len(bought),
            "top_php": top_php,
            "top_py": top_py,
            "same": top_php == top_py,
        })
    return rows


def print_table(php: dict, py: dict, score_cmp: dict, topk_rows: list) -> None:
    status_pairs = "Lulus" if php["pair_count"] == py["pair_count"] else "Beda"
    status_score = (
        "Lulus"
        if score_cmp["score_pass"]
        else ("Lulus skor" if score_cmp["max_abs_delta"] <= TOLERANCE else "Gagal")
    )
    if score_cmp["only_php"] or score_cmp["only_py"]:
        status_score = "Gagal (pasangan tidak sama)"

    print()
    print("=" * 72)
    print("HASIL PARITY PHP <-> PYTHON (full snapshot)")
    print("=" * 72)
    print(f"min_co_occurrence PHP={php.get('min_co_occurrence')}  Python={py.get('min_co_occurrence')}")
    print(f"toleransi skor     {TOLERANCE}")
    print()
    print(f"{'Item':<28} {'PHP':<18} {'Python':<18} {'Selisih/Ket':<16} {'Status'}")
    print("-" * 72)
    print(
        f"{'Jumlah pasangan':<28} {php['pair_count']:<18} {py['pair_count']:<18} "
        f"{abs(php['pair_count'] - py['pair_count']):<16} {status_pairs}"
    )
    print(
        f"{'Skor sampel (max |d|)':<28} {'-':<18} {'-':<18} "
        f"{score_cmp['max_abs_delta']:<16.6g} {status_score}"
    )
    if score_cmp["worst_pair"]:
        print(f"  pasangan terburuk: {score_cmp['worst_pair']}")
    print(
        f"{'Pasangan hanya di PHP':<28} {score_cmp['only_php']:<18} {'-':<18} {'-':<16} "
        f"{'OK' if score_cmp['only_php'] == 0 else 'Cek'}"
    )
    print(
        f"{'Pasangan hanya di Python':<28} {'-':<18} {score_cmp['only_py']:<18} {'-':<16} "
        f"{'OK' if score_cmp['only_py'] == 0 else 'Cek'}"
    )

    for row in topk_rows:
        label = f"Top-5 user {row['id_user']}"
        php_s = ",".join(map(str, row["top_php"])) or "(kosong)"
        py_s = ",".join(map(str, row["top_py"])) or "(kosong)"
        st = "Sama" if row["same"] else "Beda"
        print(f"{label:<28} {php_s:<18} {py_s:<18} {'-':<16} {st}")

    overall = (
        status_pairs == "Lulus"
        and score_cmp["score_pass"]
        and all(r["same"] for r in topk_rows)
    )
    print("-" * 72)
    print("KESIMPULAN:", "LULUS" if overall else "BELUM LULUS")
    print("=" * 72)
    print()
    print("Catatan: Top-K di sini dari agregasi skor murni (tanpa filter stok UI).")
    print(f"Detail JSON: {OUT_JSON}")


def main() -> int:
    print("[1/3] Dump CF PHP (RecommenderService)...")
    php = dump_php()
    print(f"      pasangan PHP: {php['pair_count']}")

    print("[2/3] Hitung CF Python (cf.similarity)...")
    py = compute_python()
    print(f"      pasangan Python: {py['pair_count']}")

    print("[3/3] Bandingkan skor & Top-5...")
    score_cmp = compare_scores(php["pairs"], py["pairs"])
    topk_rows = compare_topk(php.get("sample_users", []), php["pairs"], py["pairs"], k=5)

    payload = {
        "tolerance": TOLERANCE,
        "php": {
            "pair_count": php["pair_count"],
            "min_co_occurrence": php.get("min_co_occurrence"),
            "stats": php.get("stats"),
        },
        "python": {
            "pair_count": py["pair_count"],
            "min_co_occurrence": py.get("min_co_occurrence"),
            "stats": py.get("stats"),
        },
        "score_comparison": score_cmp,
        "topk_comparison": topk_rows,
        "overall_pass": (
            php["pair_count"] == py["pair_count"]
            and score_cmp["score_pass"]
            and all(r["same"] for r in topk_rows)
        ),
    }

    OUT_JSON.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSON.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    print_table(php, py, score_cmp, topk_rows)
    return 0 if payload["overall_pass"] else 1


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:  # pragma: no cover
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(2)
