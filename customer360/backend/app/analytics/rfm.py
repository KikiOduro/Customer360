"""
RFM (Recency, Frequency, Monetary) computation module.
Calculates customer-level RFM metrics from transaction data.
"""
import pandas as pd
import numpy as np
from datetime import datetime
from typing import Tuple, Dict, Any, Optional
from sklearn.preprocessing import StandardScaler, MinMaxScaler
import logging

logger = logging.getLogger(__name__)


def compute_rfm(
    df: pd.DataFrame,
    reference_date: Optional[datetime] = None,
    customer_col: str = 'customer_id',
    date_col: str = 'invoice_date',
    amount_col: str = 'amount',
    invoice_col: str = 'invoice_id'
) -> pd.DataFrame:
    """
    Compute RFM metrics for each customer.
    
    RFM Definition:
    - Recency: Days since last purchase (lower is better)
    - Frequency: Number of transactions/purchases
    - Monetary: Total amount spent
    
    Args:
        df: Transaction DataFrame with customer, date, amount columns
        reference_date: Date to calculate recency from (default: max date + 1)
        customer_col: Name of customer ID column
        date_col: Name of date column
        amount_col: Name of amount column
        invoice_col: Name of invoice/transaction ID column
        
    Returns:
        DataFrame with customer_id, recency, frequency, monetary columns
    """
    if df.empty:
        raise ValueError("Cannot compute RFM on empty DataFrame")
    
    # Ensure date column is datetime
    df = df.copy()
    if not pd.api.types.is_datetime64_any_dtype(df[date_col]):
        df[date_col] = pd.to_datetime(df[date_col])
    
    # Set reference date (default: day after last transaction)
    if reference_date is None:
        reference_date = df[date_col].max() + pd.Timedelta(days=1)
    elif isinstance(reference_date, str):
        reference_date = pd.to_datetime(reference_date)
    
    # Compute RFM metrics per customer
    rfm = df.groupby(customer_col).agg({
        date_col: lambda x: (reference_date - x.max()).days,  # Recency
        invoice_col: 'nunique' if invoice_col in df.columns else 'count',  # Frequency
        amount_col: 'sum'  # Monetary
    }).reset_index()
    
    # Rename columns
    rfm.columns = ['customer_id', 'recency', 'frequency', 'monetary']
    
    # Handle edge cases
    rfm['recency'] = rfm['recency'].clip(lower=0)  # No negative recency
    rfm['frequency'] = rfm['frequency'].clip(lower=1)  # At least 1 transaction
    rfm['monetary'] = rfm['monetary'].clip(lower=0)  # No negative monetary
    
    logger.info(f"Computed RFM for {len(rfm)} customers")
    
    return rfm


def compute_rfm_scores(
    rfm: pd.DataFrame,
    n_bins: int = 5
) -> pd.DataFrame:
    """
    Compute RFM scores (1-5) using quintile-based binning.
    
    Note: For recency, lower values get higher scores (more recent = better).
    For frequency and monetary, higher values get higher scores.
    
    Args:
        rfm: DataFrame with recency, frequency, monetary columns
        n_bins: Number of bins for scoring (default 5)
        
    Returns:
        DataFrame with additional R_score, F_score, M_score columns
    """
    rfm = rfm.copy()
    
    # Recency: Lower is better, so we reverse the labels
    try:
        rfm['R_score'] = pd.qcut(
            rfm['recency'], 
            q=n_bins, 
            labels=list(range(n_bins, 0, -1)),
            duplicates='drop'
        ).astype(int)
    except ValueError:
        # Handle case with too few unique values
        rfm['R_score'] = pd.cut(
            rfm['recency'],
            bins=n_bins,
            labels=list(range(n_bins, 0, -1)),
            duplicates='drop'
        ).astype(int)
    
    # Frequency: Higher is better
    try:
        rfm['F_score'] = pd.qcut(
            rfm['frequency'],
            q=n_bins,
            labels=list(range(1, n_bins + 1)),
            duplicates='drop'
        ).astype(int)
    except ValueError:
        rfm['F_score'] = pd.cut(
            rfm['frequency'],
            bins=n_bins,
            labels=list(range(1, n_bins + 1)),
            duplicates='drop'
        ).astype(int)
    
    # Monetary: Higher is better
    try:
        rfm['M_score'] = pd.qcut(
            rfm['monetary'],
            q=n_bins,
            labels=list(range(1, n_bins + 1)),
            duplicates='drop'
        ).astype(int)
    except ValueError:
        rfm['M_score'] = pd.cut(
            rfm['monetary'],
            bins=n_bins,
            labels=list(range(1, n_bins + 1)),
            duplicates='drop'
        ).astype(int)
    
    # Combined RFM score (concatenated string)
    rfm['RFM_score'] = (
        rfm['R_score'].astype(str) + 
        rfm['F_score'].astype(str) + 
        rfm['M_score'].astype(str)
    )
    
    # Total RFM score (sum)
    rfm['RFM_total'] = rfm['R_score'] + rfm['F_score'] + rfm['M_score']
    
    return rfm


def normalize_rfm(
    rfm: pd.DataFrame,
    method: str = 'standard',
    features: list = ['recency', 'frequency', 'monetary']
) -> Tuple[pd.DataFrame, Any]:
    """
    Normalize RFM features for clustering.
    
    Args:
        rfm: DataFrame with RFM columns
        method: 'standard' (z-score) or 'minmax' (0-1 scaling)
        features: List of columns to normalize
        
    Returns:
        Tuple of (normalized DataFrame, fitted scaler)
    """
    rfm = rfm.copy()
    
    if method == 'standard':
        scaler = StandardScaler()
    elif method == 'minmax':
        scaler = MinMaxScaler()
    else:
        raise ValueError(f"Unknown normalization method: {method}")
    
    # Apply log transformation to reduce skewness (common for monetary data)
    for col in ['monetary', 'frequency']:
        if col in features:
            rfm[f'{col}_log'] = np.log1p(rfm[col])
    
    # Use log-transformed versions for clustering
    features_for_scaling = []
    for f in features:
        if f in ['monetary', 'frequency']:
            features_for_scaling.append(f'{f}_log')
        else:
            features_for_scaling.append(f)
    
    # Fit and transform
    rfm_normalized = rfm.copy()
    normalized_cols = [f'{f}_normalized' for f in features]
    
    rfm_normalized[normalized_cols] = scaler.fit_transform(
        rfm[features_for_scaling]
    )
    
    return rfm_normalized, scaler


def get_rfm_statistics(rfm: pd.DataFrame) -> Dict[str, Any]:
    """
    Calculate descriptive statistics for RFM features.
    
    Args:
        rfm: DataFrame with RFM columns
        
    Returns:
        Dictionary with statistics for each RFM metric
    """
    stats = {}
    
    for col in ['recency', 'frequency', 'monetary']:
        if col in rfm.columns:
            series = rfm[col]
            stats[col] = {
                'mean': float(series.mean()),
                'median': float(series.median()),
                'std': float(series.std()),
                'min': float(series.min()),
                'max': float(series.max()),
                'q25': float(series.quantile(0.25)),
                'q75': float(series.quantile(0.75)),
                'skewness': float(series.skew()),
                'kurtosis': float(series.kurtosis())
            }
    
    # Correlation between RFM metrics
    rfm_cols = [c for c in ['recency', 'frequency', 'monetary'] if c in rfm.columns]
    corr_matrix = rfm[rfm_cols].corr().to_dict()
    stats['correlations'] = corr_matrix
    
    return stats


def get_rfm_distributions(rfm: pd.DataFrame, n_bins: int = 20) -> Dict[str, Dict]:
    """
    Calculate histogram data for RFM distributions.
    
    Args:
        rfm: DataFrame with RFM columns
        n_bins: Number of histogram bins
        
    Returns:
        Dictionary with histogram data for each metric
    """
    distributions = {}
    
    for col in ['recency', 'frequency', 'monetary']:
        if col in rfm.columns:
            hist, bin_edges = np.histogram(rfm[col].dropna(), bins=n_bins)
            distributions[col] = {
                'counts': hist.tolist(),
                'bin_edges': bin_edges.tolist(),
                'bin_labels': [
                    f"{bin_edges[i]:.1f}-{bin_edges[i+1]:.1f}" 
                    for i in range(len(bin_edges)-1)
                ]
            }
    
    return distributions
