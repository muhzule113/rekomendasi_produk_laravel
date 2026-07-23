# python/cf/similarity.py
import numpy as np
import pandas as pd


def build_item_user_matrix(df: pd.DataFrame) -> pd.DataFrame:
    matrix = df.pivot_table(index='id_product', columns='id_user',
                            values='qty', fill_value=0, aggfunc='sum')
    return (matrix > 0).astype(int)


def compute_dice_similarity(matrix: pd.DataFrame) -> pd.DataFrame:
    """Dice |A∩B|*2 / (|A|+|B|), lalu normalisasi ke max=1."""
    binary = matrix.to_numpy(dtype=np.float64)
    intersection = binary @ binary.T
    sizes = binary.sum(axis=1, keepdims=True)
    denom = sizes + sizes.T
    with np.errstate(divide='ignore', invalid='ignore'):
        sim = np.divide(2 * intersection, denom, out=np.zeros_like(intersection), where=denom > 0)
    np.fill_diagonal(sim, 0)
    max_raw = sim.max()
    if max_raw > 0:
        sim = sim / max_raw
    return pd.DataFrame(sim, index=matrix.index, columns=matrix.index)


def compute_co_occurrence(df: pd.DataFrame) -> pd.DataFrame:
    binary = df.pivot_table(index='id_user', columns='id_product',
                            values='qty', fill_value=0, aggfunc='sum')
    binary = (binary > 0).astype(int)
    co_matrix = binary.T.dot(binary)
    arr = co_matrix.to_numpy(copy=True)
    np.fill_diagonal(arr, 0)
    co_matrix[:] = arr
    return co_matrix


def run_similarity_analysis() -> tuple:
    from cf.data_loader import load_transaction_data
    df = load_transaction_data()
    matrix = build_item_user_matrix(df)
    sim_df = compute_dice_similarity(matrix)
    co_df = compute_co_occurrence(df)
    return sim_df, co_df
