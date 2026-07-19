# python/cf/similarity.py
import numpy as np
import pandas as pd
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.preprocessing import MinMaxScaler


def build_item_user_matrix(df: pd.DataFrame) -> pd.DataFrame:
    return df.pivot_table(index='id_product', columns='id_user',
                          values='qty', fill_value=0, aggfunc='sum')


def compute_cosine_similarity(matrix: pd.DataFrame) -> pd.DataFrame:
    scaler     = MinMaxScaler()
    normalized = scaler.fit_transform(matrix)
    sim_values = cosine_similarity(normalized)
    sim_df     = pd.DataFrame(sim_values, index=matrix.index, columns=matrix.index)
    arr = sim_df.to_numpy(copy=True)
    np.fill_diagonal(arr, 0)
    sim_df[:] = arr
    return sim_df


def compute_co_occurrence(df: pd.DataFrame) -> pd.DataFrame:
    binary = df.pivot_table(index='id_user', columns='id_product',
                            values='qty', fill_value=0, aggfunc='sum')
    binary    = (binary > 0).astype(int)
    co_matrix = binary.T.dot(binary)
    arr = co_matrix.to_numpy(copy=True)
    np.fill_diagonal(arr, 0)
    co_matrix[:] = arr
    return co_matrix


def run_similarity_analysis() -> tuple:
    from cf.data_loader import load_transaction_data
    df     = load_transaction_data()
    matrix = build_item_user_matrix(df)
    sim_df = compute_cosine_similarity(matrix)
    co_df  = compute_co_occurrence(df)
    return sim_df, co_df
