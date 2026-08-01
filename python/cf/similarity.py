# python/cf/similarity.py
import numpy as np
import pandas as pd


def build_item_user_matrix(df: pd.DataFrame) -> pd.DataFrame:
    """Bangun matriks item×user biner (1 = pernah membeli)."""
    matrix = df.pivot_table(
        index='id_product',
        columns='id_user',
        values='qty',
        fill_value=0,
        aggfunc='sum',
    )
    return (matrix > 0).astype(int)


def compute_cosine_similarity(
    matrix: pd.DataFrame,
    min_co_occurrence: int = 2,
) -> tuple[pd.DataFrame, pd.DataFrame]:
    """
    Cosine similarity biner tanpa normalisasi maksimum.

    cosine(A,B) = |A∩B| / sqrt(|A| * |B|)

    Returns
    -------
    sim_df : DataFrame skor cosine
    co_df  : DataFrame co-occurrence (intersection buyers)
    """
    if matrix.empty:
        empty = pd.DataFrame()
        return empty, empty

    binary = matrix.to_numpy(dtype=np.float64)
    intersection = binary @ binary.T
    sizes = binary.sum(axis=1, keepdims=True)
    denom = np.sqrt(sizes * sizes.T)

    with np.errstate(divide='ignore', invalid='ignore'):
        sim = np.divide(
            intersection,
            denom,
            out=np.zeros_like(intersection),
            where=denom > 0,
        )

    np.fill_diagonal(sim, 0)
    np.fill_diagonal(intersection, 0)

    # Terapkan minimum co-occurrence
    mask = intersection >= float(min_co_occurrence)
    sim = np.where(mask, sim, 0.0)
    co = np.where(mask, intersection, 0.0)

    # Jangan simpan skor nol
    sim = np.where(sim > 0, sim, 0.0)

    index = matrix.index
    sim_df = pd.DataFrame(sim, index=index, columns=index)
    co_df = pd.DataFrame(co, index=index, columns=index)
    return sim_df, co_df


def cosine_from_binary_vectors(vector_a, vector_b) -> float:
    """Hitung cosine dari dua vektor biner 1D (untuk uji matematis)."""
    a = np.asarray(vector_a, dtype=np.float64)
    b = np.asarray(vector_b, dtype=np.float64)
    denom = np.sqrt(np.dot(a, a) * np.dot(b, b))
    if denom <= 0:
        return 0.0
    return float(np.dot(a, b) / denom)


def compute_co_occurrence(df: pd.DataFrame) -> pd.DataFrame:
    binary = df.pivot_table(
        index='id_user',
        columns='id_product',
        values='qty',
        fill_value=0,
        aggfunc='sum',
    )
    binary = (binary > 0).astype(int)
    co_matrix = binary.T.dot(binary)
    arr = co_matrix.to_numpy(copy=True)
    np.fill_diagonal(arr, 0)
    co_matrix[:] = arr
    return co_matrix


def extract_unique_pairs(
    sim_df: pd.DataFrame,
    co_df: pd.DataFrame,
) -> list[tuple[int, int, float, int]]:
    """Ambil semua pasangan unik (a < b) dengan skor > 0."""
    if sim_df.empty:
        return []

    products = [int(p) for p in sim_df.index.tolist()]
    pairs = []
    n = len(products)
    for i in range(n):
        for j in range(i + 1, n):
            a, b = products[i], products[j]
            score = float(sim_df.loc[a, b])
            if score <= 0:
                continue
            co = int(co_df.loc[a, b]) if a in co_df.index and b in co_df.columns else 0
            pairs.append((a, b, score, co))
    return pairs


def run_similarity_analysis(min_co_occurrence: int | None = None) -> tuple:
    from cf.data_loader import load_transaction_data
    from config import CF_CONFIG

    if min_co_occurrence is None:
        min_co_occurrence = CF_CONFIG['min_co_occurrence']

    df = load_transaction_data()
    if df.empty:
        return pd.DataFrame(), pd.DataFrame(), []

    matrix = build_item_user_matrix(df)
    sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=min_co_occurrence)
    pairs = extract_unique_pairs(sim_df, co_df)
    return sim_df, co_df, pairs
