# -*- coding: utf-8 -*-
"""
Customer360 — Universal Customer Segmentation Pipeline (Colab Edition)

A Google Colab notebook for Ghanaian SME customer segmentation.
Works on ANY transaction CSV — fashion, restaurants, electronics, etc.

Structure:
  SECTION A (lines ~1–950):  Core reusable functions (importable by FastAPI)
  SECTION B (lines ~950+):   Colab notebook wrapper cells

Pipeline: CSV → Schema Detection → Clean → RFM → Scale → PCA → Optimal K
          → Clustering (KMeans/GMM/Hierarchical) → Segment Labels → SHAP
          → Validation → Charts → PDF Report
"""

# ══════════════════════════════════════════════════════════════════
# SECTION A: CORE FUNCTIONS (reusable by web app)
# ══════════════════════════════════════════════════════════════════

import os
import warnings
import numpy as np
import pandas as pd
from collections import Counter
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Any

from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans, AgglomerativeClustering
from sklearn.mixture import GaussianMixture
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    silhouette_score, silhouette_samples,
    davies_bouldin_score, calinski_harabasz_score,
    adjusted_rand_score,
)
from scipy import stats

warnings.filterwarnings('ignore')

# ── RFM Segment Definitions ──────────────────────────────────────
RFM_SEGMENT_DEFINITIONS = {
    'Champions':           'Best customers: bought recently, buy often, spend most',
    'Loyal Customers':     'High frequency and monetary, good recency',
    'Potential Loyalists': 'Recent customers with average frequency, potential for growth',
    'New Customers':       'Bought recently but low frequency and spend',
    'Promising':           'Recent shoppers but low spend, need nurturing',
    'Need Attention':      'Above average RFM but not recent, may be slipping',
    'About to Sleep':      'Below average recency and frequency, at risk',
    'At Risk':             'Spent big money but long time ago, need reactivation',
    'Cannot Lose Them':    'Used to be top customers, haven\'t returned recently',
    'Hibernating':         'Low spend and long time since last purchase',
    'Lost Customers':      'Lowest RFM scores, likely churned',
}


# ─────────────────────────────────────────────────────────────────
# 1. detect_schema
# ─────────────────────────────────────────────────────────────────
def detect_schema(df: pd.DataFrame) -> Dict[str, Any]:
    """
    Auto-detect column roles from any CSV using case-insensitive keyword matching.

    Returns dict with keys: customer, date, revenue, price, qty, product,
    category, payment, shipping, currency_col, discount, order, status,
    color, size, promo, currency (str), business (str).
    """
    cols_lower = [c.lower().strip() for c in df.columns]
    orig = list(df.columns)

    def find(keywords, dtype='any', exclude_keywords=None):
        """Return original-case column matching any keyword."""
        for kw in keywords:
            for i, c in enumerate(cols_lower):
                if kw in c:
                    if exclude_keywords and any(ex in c for ex in exclude_keywords):
                        continue
                    col = orig[i]
                    if dtype == 'numeric' and not pd.api.types.is_numeric_dtype(df[col]):
                        try:
                            pd.to_numeric(df[col].dropna().head(20))
                        except (ValueError, TypeError):
                            continue
                    elif dtype == 'datetime':
                        try:
                            pd.to_datetime(df[col].dropna().iloc[:5])
                        except Exception:
                            continue
                    return col
        return None

    # Customer ID — exclude "customer country"
    customer = (
        find(['customer_id', 'cust_id', 'client_id', 'buyer_id', 'member_id']) or
        find(['customer', 'client', 'buyer', 'member'], exclude_keywords=['country', 'nation', 'city', 'email', 'name', 'phone'])
    )

    # Date
    date = find(['purchase_date', 'order_date', 'transaction_date', 'invoice_date', 'date'], dtype='datetime')

    # Revenue / Amount — prefer "total" columns
    revenue = (
        find(['total_line', 'total line amount', 'line_amount'], dtype='numeric') or
        find(['total_amount', 'total amount', 'order_total'], dtype='numeric') or
        find(['revenue', 'amount', 'total', 'sales', 'gmv', 'spend'], dtype='numeric')
    )

    # Unit price — only if not already taken by revenue
    price = find(['unit_price', 'unit price', 'item_price', 'selling_price'], dtype='numeric')
    if price == revenue:
        price = find(['price', 'cost', 'rate'], dtype='numeric')
    if price == revenue:
        price = None

    qty = find(['quantity', 'qty', 'units'], dtype='numeric')
    product = find(['product_name', 'product name', 'item_name', 'product', 'item', 'description'])
    category = find(['categories', 'category', 'product_type', 'department', 'type'])
    payment = find(['payment_method', 'payment method', 'pay_method', 'payment'])
    shipping = find(['shipping_method', 'shipping method', 'ship_method', 'shipping', 'delivery'])
    currency_col = find(['currency', 'curr'])
    discount = find(['discount_amount', 'discount amount', 'discount'], dtype='numeric')
    order = find(['order_id', 'order id', 'order', 'invoice', 'transaction_id'])
    status = find(['order_status', 'order status', 'status'])
    promo = find(['promo_code', 'promo code', 'promo', 'coupon'])
    color = find(['color', 'colour'])
    size = find(['size'])

    # If revenue missing but qty * price exist, flag for computation
    compute_revenue = False
    if not revenue and qty and price:
        compute_revenue = True

    # Auto-detect currency
    currency = 'GHS'  # default for Ghanaian SMEs
    if currency_col and currency_col in df.columns:
        most_common = df[currency_col].dropna().astype(str).str.strip().str.upper()
        if len(most_common) > 0:
            currency = most_common.mode().iloc[0] if len(most_common.mode()) > 0 else 'GHS'
    else:
        all_text = ' '.join(orig).lower()
        for kw, cur in [('ghs','GHS'),('cedi','GHS'),('ngn','NGN'),('naira','NGN'),
                         ('kes','KES'),('zar','ZAR'),('gbp','GBP'),('£','GBP'),
                         ('eur','EUR'),('€','EUR'),('usd','USD'),('$','USD')]:
            if kw in all_text:
                currency = cur
                break

    # Detect business type
    biz_type = 'Retail'
    if category and category in df.columns:
        cats = ' '.join(df[category].dropna().astype(str).str.lower().unique()[:50])
        biz_map = [
            (['shirt','dress','trouser','jean','jacket','shoe','bag','fashion','cloth','apparel','wear'], 'Fashion Retail'),
            (['phone','laptop','tablet','electronic','gadget','tech','computer'], 'Electronics Retail'),
            (['food','grocery','beverage','snack','drink','fruit','vegetable','meat'], 'Food & Grocery'),
            (['beauty','cosmetic','skincare','makeup','hair','fragrance'], 'Beauty & Personal Care'),
            (['furniture','home','kitchen','decor','bedding'], 'Home & Furniture'),
            (['restaurant','meal','menu','dine','chef','cuisine'], 'Restaurant'),
            (['book','stationery','school','education'], 'Education & Books'),
            (['sport','fitness','gym','exercise','outdoor'], 'Sports & Fitness'),
        ]
        for keywords, btype in biz_map:
            if any(k in cats for k in keywords):
                biz_type = btype
                break

    return {
        'customer': customer, 'date': date, 'revenue': revenue,
        'price': price, 'qty': qty, 'product': product,
        'category': category, 'payment': payment, 'shipping': shipping,
        'currency_col': currency_col, 'discount': discount, 'order': order,
        'status': status, 'promo': promo, 'color': color, 'size': size,
        'currency': currency, 'business': biz_type,
        'compute_revenue': compute_revenue,
    }


# ─────────────────────────────────────────────────────────────────
# 2. clean_data
# ─────────────────────────────────────────────────────────────────
def clean_data(df: pd.DataFrame, schema: Dict) -> Tuple[pd.DataFrame, Dict]:
    """
    Clean and validate transaction data.

    Returns (cleaned_df, cleaning_summary_dict).
    """
    df = df.copy()
    summary = {'original_rows': len(df), 'steps': []}

    # Remove exact duplicates
    before = len(df)
    df = df.drop_duplicates()
    n_dup = before - len(df)
    summary['duplicates_removed'] = n_dup
    summary['steps'].append(f'Removed {n_dup} duplicate rows')

    # Compute revenue if missing
    if schema.get('compute_revenue') and schema['qty'] and schema['price']:
        df[schema['qty']] = pd.to_numeric(df[schema['qty']], errors='coerce')
        df[schema['price']] = pd.to_numeric(df[schema['price']], errors='coerce')
        rev_col = 'Computed_Revenue'
        df[rev_col] = df[schema['qty']] * df[schema['price']]
        schema['revenue'] = rev_col
        summary['steps'].append(f'Computed revenue = qty × unit_price')
    elif not schema['revenue'] and schema['qty'] and schema['price']:
        df[schema['qty']] = pd.to_numeric(df[schema['qty']], errors='coerce')
        df[schema['price']] = pd.to_numeric(df[schema['price']], errors='coerce')
        rev_col = 'Computed_Revenue'
        df[rev_col] = df[schema['qty']] * df[schema['price']]
        schema['revenue'] = rev_col
        summary['steps'].append(f'Computed revenue = qty × unit_price')

    # Parse dates
    date_col = schema['date']
    if date_col and date_col in df.columns:
        df[date_col] = pd.to_datetime(df[date_col], errors='coerce', utc=True)
        before = len(df)
        df = df.dropna(subset=[date_col])
        n_bad = before - len(df)
        summary['steps'].append(f'Removed {n_bad} rows with unparseable dates')

    # Drop rows with missing/invalid revenue
    rev_col = schema['revenue']
    if rev_col and rev_col in df.columns:
        df[rev_col] = pd.to_numeric(df[rev_col], errors='coerce')
        before = len(df)
        df = df.dropna(subset=[rev_col])
        df = df[df[rev_col] > 0]
        n_bad = before - len(df)
        summary['steps'].append(f'Removed {n_bad} rows with missing/zero/negative revenue')

    # Drop invalid qty / price
    for key in ['qty', 'price']:
        col = schema.get(key)
        if col and col in df.columns:
            df[col] = pd.to_numeric(df[col], errors='coerce')
            before = len(df)
            df = df.dropna(subset=[col])
            df = df[df[col] >= 0]
            n_bad = before - len(df)
            if n_bad > 0:
                summary['steps'].append(f'Removed {n_bad} rows with invalid {col}')

    # Filter cancelled / refunded orders
    status_col = schema.get('status')
    if status_col and status_col in df.columns:
        valid = ['confirmed','delivered','shipped','completed','processing',
                 'fulfilled','paid','closed','success']
        mask = df[status_col].astype(str).str.lower().str.strip().isin(valid)
        if mask.sum() > 0 and mask.sum() < len(df):
            before = len(df)
            df = df[mask]
            summary['steps'].append(f'Filtered to valid statuses: kept {len(df)} of {before}')

    # Fill missing categoricals
    cat_defaults = {
        schema.get('payment'): 'Unknown', schema.get('shipping'): 'Unknown',
        schema.get('promo'): 'None', schema.get('color'): 'Unknown',
        schema.get('size'): 'Unknown', schema.get('category'): 'Unknown',
    }
    for col, default in cat_defaults.items():
        if col and col in df.columns:
            df[col] = df[col].fillna(default)

    # Handle discount NaN
    disc_col = schema.get('discount')
    if disc_col and disc_col in df.columns:
        df[disc_col] = pd.to_numeric(df[disc_col], errors='coerce').fillna(0)

    # Generate synthetic customer IDs if missing
    if not schema['customer'] or schema['customer'] not in df.columns:
        df['Synthetic_CustomerID'] = [f'CUST_{i:05d}' for i in range(len(df))]
        schema['customer'] = 'Synthetic_CustomerID'
        summary['steps'].append('WARNING: No customer ID found — generated synthetic IDs (each row = 1 customer)')

    summary['final_rows'] = len(df)
    summary['retention_pct'] = round(len(df) / summary['original_rows'] * 100, 1)
    return df, summary


# ─────────────────────────────────────────────────────────────────
# 3. compute_rfm
# ─────────────────────────────────────────────────────────────────
def compute_rfm(df: pd.DataFrame, schema: Dict) -> pd.DataFrame:
    """
    Aggregate transactions to customer level.
    Returns rfm_df with columns [CustomerID, Recency, Frequency, Monetary].
    """
    customer_col = schema['customer']
    date_col = schema['date']
    rev_col = schema['revenue']
    order_col = schema.get('order')

    if not all([customer_col, date_col, rev_col]):
        raise ValueError("RFM requires customer, date, and revenue columns.")

    reference_date = df[date_col].max() + pd.Timedelta(days=1)

    agg_dict = {date_col: lambda x: (reference_date - x.max()).days}

    # Frequency: count unique orders if order column exists, else count rows
    if order_col and order_col in df.columns:
        agg_dict[order_col] = 'nunique'
    else:
        agg_dict[rev_col] = ['count', 'sum']

    if order_col and order_col in df.columns:
        rfm = df.groupby(customer_col).agg({
            date_col: lambda x: (reference_date - x.max()).days,
            order_col: 'nunique',
            rev_col: 'sum',
        }).reset_index()
        rfm.columns = ['CustomerID', 'Recency', 'Frequency', 'Monetary']
    else:
        rfm = df.groupby(customer_col).agg({
            date_col: lambda x: (reference_date - x.max()).days,
            rev_col: ['count', 'sum'],
        }).reset_index()
        rfm.columns = ['CustomerID', 'Recency', 'Frequency', 'Monetary']

    # Clean: clip negatives, drop zero/negative monetary
    rfm['Recency'] = rfm['Recency'].clip(lower=0)
    rfm['Frequency'] = rfm['Frequency'].clip(lower=1)
    rfm = rfm[rfm['Monetary'] > 0].reset_index(drop=True)

    return rfm


# ─────────────────────────────────────────────────────────────────
# 4. scale_rfm
# ─────────────────────────────────────────────────────────────────
def scale_rfm(rfm_df: pd.DataFrame) -> Tuple[np.ndarray, StandardScaler, List[str]]:
    """
    Log-transform F and M (and R if heavily skewed), then StandardScaler.
    Returns (scaled_data, scaler, feature_labels).
    """
    feat_labels = ['Recency', 'Frequency', 'Monetary']
    work = rfm_df[feat_labels].copy()

    # Always log-transform Frequency and Monetary
    work['Frequency'] = np.log1p(work['Frequency'])
    work['Monetary'] = np.log1p(work['Monetary'])

    # Log-transform Recency only if heavily right-skewed
    if work['Recency'].skew() > 2:
        work['Recency'] = np.log1p(work['Recency'])

    scaler = StandardScaler()
    scaled_data = scaler.fit_transform(work.values)

    return scaled_data, scaler, feat_labels


# ─────────────────────────────────────────────────────────────────
# 5. find_optimal_k
# ─────────────────────────────────────────────────────────────────
def find_optimal_k(
    scaled_data: np.ndarray,
    k_range: range = None,
) -> Tuple[int, pd.DataFrame]:
    """
    Evaluate K=2..10, majority vote across 3 metrics (Silhouette, CH, DB).
    Returns (optimal_k, metrics_df).
    """
    n_samples = len(scaled_data)
    if k_range is None:
        k_range = range(2, min(11, n_samples))

    results = {'k': [], 'Inertia': [], 'Silhouette': [],
               'Calinski-Harabasz': [], 'Davies-Bouldin': []}

    for k in k_range:
        km = KMeans(n_clusters=k, random_state=42, n_init=10, max_iter=300)
        labs = km.fit_predict(scaled_data)
        results['k'].append(k)
        results['Inertia'].append(round(km.inertia_, 1))
        results['Silhouette'].append(round(silhouette_score(scaled_data, labs), 4))
        results['Calinski-Harabasz'].append(round(calinski_harabasz_score(scaled_data, labs), 1))
        results['Davies-Bouldin'].append(round(davies_bouldin_score(scaled_data, labs), 4))

    metrics_df = pd.DataFrame(results)
    ks = list(k_range)

    best_sil = ks[np.argmax(results['Silhouette'])]
    best_ch  = ks[np.argmax(results['Calinski-Harabasz'])]
    best_db  = ks[np.argmin(results['Davies-Bouldin'])]

    # Majority vote across 3 metrics
    votes = [best_sil, best_ch, best_db]
    vote_counts = Counter(votes)
    winner, count = vote_counts.most_common(1)[0]
    if count >= 2:
        optimal_k = winner
    else:
        optimal_k = best_sil  # tiebreaker

    metrics_df.attrs['best_sil_k'] = best_sil
    metrics_df.attrs['best_ch_k'] = best_ch
    metrics_df.attrs['best_db_k'] = best_db
    metrics_df.attrs['votes'] = votes

    return optimal_k, metrics_df


# ─────────────────────────────────────────────────────────────────
# 6. run_all_clustering
# ─────────────────────────────────────────────────────────────────
def run_all_clustering(
    scaled_data: np.ndarray,
    optimal_k: int,
) -> Tuple[np.ndarray, str, Dict]:
    """
    Run K-Means, GMM, Hierarchical. Compare by Silhouette.
    Returns (best_labels, best_algo_name, comparison_dict).
    """
    comparison = {}

    # K-Means
    km = KMeans(n_clusters=optimal_k, random_state=42, n_init=10, max_iter=300)
    km_labels = km.fit_predict(scaled_data)
    comparison['K-Means'] = {
        'labels': km_labels,
        'silhouette': round(silhouette_score(scaled_data, km_labels), 4),
        'davies_bouldin': round(davies_bouldin_score(scaled_data, km_labels), 4),
        'calinski': round(calinski_harabasz_score(scaled_data, km_labels), 1),
        'model': km,
    }

    # GMM
    gmm = GaussianMixture(n_components=optimal_k, covariance_type='full',
                           random_state=42, n_init=3)
    gmm.fit(scaled_data)
    gmm_labels = gmm.predict(scaled_data)
    comparison['GMM'] = {
        'labels': gmm_labels,
        'silhouette': round(silhouette_score(scaled_data, gmm_labels), 4),
        'davies_bouldin': round(davies_bouldin_score(scaled_data, gmm_labels), 4),
        'calinski': round(calinski_harabasz_score(scaled_data, gmm_labels), 1),
        'bic': round(gmm.bic(scaled_data), 1),
        'aic': round(gmm.aic(scaled_data), 1),
        'model': gmm,
    }

    # Hierarchical
    hc = AgglomerativeClustering(n_clusters=optimal_k, linkage='ward')
    hc_labels = hc.fit_predict(scaled_data)
    comparison['Hierarchical'] = {
        'labels': hc_labels,
        'silhouette': round(silhouette_score(scaled_data, hc_labels), 4),
        'davies_bouldin': round(davies_bouldin_score(scaled_data, hc_labels), 4),
        'calinski': round(calinski_harabasz_score(scaled_data, hc_labels), 1),
        'model': hc,
    }

    # Best by silhouette
    best_algo = max(comparison, key=lambda a: comparison[a]['silhouette'])
    best_labels = comparison[best_algo]['labels']

    return best_labels, best_algo, comparison


# ─────────────────────────────────────────────────────────────────
# 7. label_segments
# ─────────────────────────────────────────────────────────────────
def label_segments(
    rfm_df: pd.DataFrame,
    cluster_labels: np.ndarray,
) -> Tuple[Dict[int, str], pd.DataFrame]:
    """
    Assign RFM-based segment names using percentile thresholds.
    Returns (segment_map {cluster_id: name}, labelled_rfm_df).
    """
    df = rfm_df.copy()
    df['Cluster'] = cluster_labels

    # Cluster-level RFM means
    cluster_rfm = df.groupby('Cluster')[['Recency','Frequency','Monetary']].mean()

    def _score(row):
        """Score cluster RFM profile into segment name."""
        r_pct = (df['Recency'].max() - row['Recency']) / (df['Recency'].max() - df['Recency'].min() + 1e-10)
        f_pct = (row['Frequency'] - df['Frequency'].min()) / (df['Frequency'].max() - df['Frequency'].min() + 1e-10)
        m_pct = (row['Monetary'] - df['Monetary'].min()) / (df['Monetary'].max() - df['Monetary'].min() + 1e-10)

        r = min(5, max(1, int(r_pct * 5) + 1))
        f = min(5, max(1, int(f_pct * 5) + 1))
        m = min(5, max(1, int(m_pct * 5) + 1))

        if r >= 4 and f >= 4 and m >= 4:    return 'Champions'
        if r >= 3 and f >= 4:                return 'Loyal Customers'
        if r >= 4 and f <= 2:                return 'New Customers'
        if r >= 3 and f >= 2 and m >= 3:     return 'Potential Loyalists'
        if r >= 4 and m <= 2:                return 'Promising'
        if r <= 2 and f >= 3 and m >= 3:     return 'At Risk'
        if r <= 2 and f >= 4 and m >= 4:     return 'Cannot Lose Them'
        if r <= 2 and f <= 2 and m >= 3:     return 'Hibernating'
        if r <= 2 and f <= 2 and m <= 2:     return 'Lost Customers'
        if r <= 3 and f >= 2:                return 'Need Attention'
        return 'About to Sleep'

    segment_map = {}
    for cid in cluster_rfm.index:
        segment_map[cid] = _score(cluster_rfm.loc[cid])

    # Handle duplicate names: append cluster number if needed
    seen = {}
    for cid, name in segment_map.items():
        if name in seen:
            segment_map[cid] = f"{name} ({cid})"
        seen[name] = cid

    df['Segment'] = df['Cluster'].map(segment_map)
    return segment_map, df


# ─────────────────────────────────────────────────────────────────
# 8. explain_with_shap
# ─────────────────────────────────────────────────────────────────
def explain_with_shap(
    scaled_data: np.ndarray,
    cluster_labels: np.ndarray,
    feature_labels: List[str],
) -> Dict[str, Any]:
    """
    Train Random Forest surrogate, apply SHAP TreeExplainer.
    Returns dict with shap_values, importances, surrogate_accuracy.
    """
    import shap

    rf = RandomForestClassifier(n_estimators=100, random_state=42, max_depth=5)
    rf.fit(scaled_data, cluster_labels)
    accuracy = rf.score(scaled_data, cluster_labels)

    explainer = shap.TreeExplainer(rf)
    shap_values = explainer.shap_values(scaled_data)

    # Feature importance from SHAP
    if isinstance(shap_values, list):
        mean_abs = np.mean([np.abs(sv).mean(axis=0) for sv in shap_values], axis=0)
    else:
        mean_abs = np.abs(shap_values).mean(axis=0)

    importances = dict(zip(feature_labels, mean_abs / (mean_abs.sum() + 1e-10)))

    # Centroid-based importance
    n_clusters = len(np.unique(cluster_labels))
    centroids = np.array([scaled_data[cluster_labels == k].mean(axis=0) for k in range(n_clusters)])
    centroid_range = np.ptp(centroids, axis=0)
    centroid_imp = centroid_range / (centroid_range.sum() + 1e-10)

    return {
        'shap_values': shap_values,
        'importances': importances,
        'centroid_importances': dict(zip(feature_labels, centroid_imp)),
        'surrogate_accuracy': round(accuracy, 4),
        'model': rf,
        'explainer': explainer,
    }


# ─────────────────────────────────────────────────────────────────
# 9. validate_clusters
# ─────────────────────────────────────────────────────────────────
def validate_clusters(
    scaled_data: np.ndarray,
    cluster_labels: np.ndarray,
    rfm_df: pd.DataFrame,
    optimal_k: int,
    n_stability_runs: int = 10,
) -> Dict[str, Any]:
    """
    ANOVA per RFM feature + ARI stability over n runs.
    Returns validation_results dict.
    """
    results = {}

    # ── ANOVA ──
    rfm_with_labels = rfm_df.copy()
    rfm_with_labels['Cluster'] = cluster_labels
    anova = {}
    for feat in ['Recency', 'Frequency', 'Monetary']:
        groups = [rfm_with_labels[rfm_with_labels['Cluster'] == c][feat].dropna().values
                  for c in sorted(rfm_with_labels['Cluster'].unique())]
        groups = [g for g in groups if len(g) > 1]
        if len(groups) >= 2:
            f_stat, p_val = stats.f_oneway(*groups)
            anova[feat] = {
                'F': round(float(f_stat), 2),
                'p': float(p_val),
                'significant': p_val < 0.05,
            }
    results['anova'] = anova

    # ── ARI Stability ──
    reference = KMeans(n_clusters=optimal_k, random_state=42, n_init=10).fit_predict(scaled_data)
    ari_scores = []
    for seed in range(1, n_stability_runs + 1):
        labels_i = KMeans(n_clusters=optimal_k, random_state=seed * 7, n_init=10).fit_predict(scaled_data)
        ari = adjusted_rand_score(reference, labels_i)
        ari_scores.append(round(ari, 4))

    avg_ari = float(np.mean(ari_scores))
    std_ari = float(np.std(ari_scores))
    if avg_ari > 0.9:     stability = 'Excellent'
    elif avg_ari > 0.7:   stability = 'Good'
    elif avg_ari > 0.5:   stability = 'Fair'
    else:                 stability = 'Poor'

    results['ari_scores'] = ari_scores
    results['avg_ari'] = round(avg_ari, 4)
    results['std_ari'] = round(std_ari, 4)
    results['stability'] = stability

    return results


# ─────────────────────────────────────────────────────────────────
# 10. generate_all_charts
# ─────────────────────────────────────────────────────────────────
def generate_all_charts(
    rfm_df: pd.DataFrame,
    scaled_data: np.ndarray,
    cluster_labels: np.ndarray,
    segment_map: Dict[int, str],
    shap_results: Dict,
    feature_labels: List[str],
    validation_results: Dict,
    optimal_k: int,
    metrics_df: pd.DataFrame,
    algo_comparison: Dict,
    output_dir: str,
    currency: str = 'GHS',
) -> List[str]:
    """
    Generate all visualisations and save PNGs. Returns list of chart paths.
    Does NOT call plt.show() — that's the Colab wrapper's job.
    """
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt
    import seaborn as sns
    sns.set_style('whitegrid')

    charts_dir = os.path.join(output_dir, 'charts')
    os.makedirs(charts_dir, exist_ok=True)
    paths = []

    PALETTE = ['#2ECC71','#3498DB','#9B59B6','#F39C12','#E74C3C',
               '#1ABC9C','#2C3E50','#F1C40F','#E67E22','#95A5A6']
    segments = list(dict.fromkeys(segment_map.values()))
    seg_color = {s: PALETTE[i % len(PALETTE)] for i, s in enumerate(segments)}

    labelled = rfm_df.copy()
    labelled['Cluster'] = cluster_labels
    labelled['Segment'] = labelled['Cluster'].map(segment_map)

    # ── 1. RFM Distributions ──
    fig, axes = plt.subplots(1, 3, figsize=(15, 5))
    for i, feat in enumerate(feature_labels):
        ax = axes[i]
        ax.hist(rfm_df[feat], bins=30, color='#3498DB', edgecolor='white', alpha=0.8)
        ax.set_title(f'{feat}\nSkew={rfm_df[feat].skew():.2f}', fontsize=12, fontweight='bold')
        ax.axvline(rfm_df[feat].mean(), color='red', linestyle='--', alpha=0.7, label=f'Mean: {rfm_df[feat].mean():.1f}')
        ax.axvline(rfm_df[feat].median(), color='green', linestyle=':', alpha=0.7, label=f'Median: {rfm_df[feat].median():.1f}')
        ax.legend(fontsize=9); ax.set_xlabel(feat)
    plt.suptitle('RFM Distributions (Before Scaling)', fontsize=14, fontweight='bold', y=1.02)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'rfm_distributions.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 2. PCA Scree + Biplot ──
    pca_full = PCA()
    pca_full.fit(scaled_data)
    exp_var = pca_full.explained_variance_ratio_
    cum_var = np.cumsum(exp_var)
    fig, axes = plt.subplots(1, 2, figsize=(14, 5))
    axes[0].bar(range(1, len(exp_var)+1), exp_var, color='#3498DB', edgecolor='white', alpha=0.8)
    axes[0].plot(range(1, len(exp_var)+1), exp_var, 'o-', color='#2C3E50', linewidth=2)
    axes[0].set_title('Scree Plot'); axes[0].set_xlabel('PC'); axes[0].set_ylabel('Explained Variance')
    axes[1].plot(range(1, len(cum_var)+1), cum_var, 's-', color='#E74C3C', linewidth=2.5, markersize=8)
    axes[1].axhline(y=0.85, color='#2ECC71', linestyle='--', linewidth=2, label='85% Threshold')
    axes[1].fill_between(range(1, len(cum_var)+1), cum_var, alpha=0.1, color='#E74C3C')
    axes[1].set_title('Cumulative Explained Variance'); axes[1].legend(fontsize=11)
    plt.suptitle('PCA Analysis', fontsize=15, fontweight='bold', y=1.02)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'pca_scree.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # Biplot
    pca2 = PCA(n_components=min(2, scaled_data.shape[1]))
    pca_2d = pca2.fit_transform(scaled_data)
    fig, ax = plt.subplots(figsize=(10, 8))
    ax.scatter(pca_2d[:, 0], pca_2d[:, 1], alpha=0.3, s=15, color='#95A5A6')
    loadings = pca2.components_.T
    for i, feat in enumerate(feature_labels):
        ax.annotate('', xy=(loadings[i,0]*5, loadings[i,1]*5), xytext=(0,0),
                    arrowprops=dict(arrowstyle='->', color='#E74C3C', lw=2))
        ax.text(loadings[i,0]*5.3, loadings[i,1]*5.3, feat, fontsize=10, fontweight='bold', color='#C0392B')
    ax.set_xlabel(f'PC1 ({pca2.explained_variance_ratio_[0]:.1%})')
    ax.set_ylabel(f'PC2 ({pca2.explained_variance_ratio_[1]:.1%})')
    ax.set_title('PCA Biplot — Feature Loadings', fontsize=14, fontweight='bold')
    ax.axhline(0, color='grey', lw=0.5); ax.axvline(0, color='grey', lw=0.5)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'pca_biplot.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 3. Optimal K Metrics ──
    k_vals = metrics_df['k'].tolist()
    fig, axes = plt.subplots(2, 2, figsize=(14, 10))
    metric_info = [('Inertia','#3498DB','lower'),('Silhouette','#2ECC71','higher'),
                   ('Calinski-Harabasz','#F39C12','higher'),('Davies-Bouldin','#E74C3C','lower')]
    for ax, (metric, color, direction) in zip(axes.flat, metric_info):
        vals = metrics_df[metric].tolist()
        ax.plot(k_vals, vals, 'o-', color=color, linewidth=2.5, markersize=8)
        ax.fill_between(k_vals, vals, alpha=0.1, color=color)
        ax.axvline(x=optimal_k, color='grey', linestyle=':', linewidth=2, alpha=0.7)
        best_idx = np.argmax(vals) if direction == 'higher' else np.argmin(vals)
        ax.scatter([k_vals[best_idx]], [vals[best_idx]], s=200, color=color,
                   zorder=5, edgecolors='black', linewidths=2)
        ax.set_title(f'{metric} ({direction} = better)', fontsize=12, fontweight='bold')
        ax.set_xlabel('k'); ax.set_ylabel(metric)
    plt.suptitle(f'Optimal K Selection — Chosen k={optimal_k}', fontsize=16, fontweight='bold', y=1.02)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'optimal_k_4metrics.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # Silhouette plot for chosen K
    fig, ax = plt.subplots(figsize=(10, 6))
    sil_vals = silhouette_samples(scaled_data, cluster_labels)
    y_lower = 10
    colors_sil = plt.cm.Set2(np.linspace(0, 1, optimal_k))
    for i in range(optimal_k):
        clust = np.sort(sil_vals[cluster_labels == i])
        y_upper = y_lower + len(clust)
        ax.fill_betweenx(np.arange(y_lower, y_upper), 0, clust, alpha=0.7, color=colors_sil[i])
        ax.text(-0.05, y_lower + 0.5*len(clust), f'Cluster {i}', fontsize=11, fontweight='bold')
        y_lower = y_upper + 10
    avg_sil = silhouette_score(scaled_data, cluster_labels)
    ax.axvline(x=avg_sil, color='red', linestyle='--', lw=2, label=f'Avg: {avg_sil:.3f}')
    ax.set_title(f'Silhouette Plot (k={optimal_k})', fontsize=15, fontweight='bold')
    ax.set_xlabel('Silhouette Coefficient'); ax.legend(fontsize=12)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'silhouette_plot.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 4. PCA Cluster Scatter ──
    colors_cluster = plt.cm.Set2(np.linspace(0, 1, optimal_k))
    fig, ax = plt.subplots(figsize=(12, 8))
    for i in range(optimal_k):
        mask = cluster_labels == i
        lbl = segment_map.get(i, f'Cluster {i}')
        ax.scatter(pca_2d[mask, 0], pca_2d[mask, 1], c=[colors_cluster[i]],
                   label=f'{lbl} (n={mask.sum()})', alpha=0.5, s=30, edgecolors='white', linewidth=0.3)
    ax.set_xlabel(f'PC1 ({pca2.explained_variance_ratio_[0]:.1%})'); ax.set_ylabel(f'PC2 ({pca2.explained_variance_ratio_[1]:.1%})')
    ax.set_title(f'Clusters in PCA Space (k={optimal_k})', fontsize=15, fontweight='bold')
    ax.legend(fontsize=10); plt.tight_layout()
    p = os.path.join(charts_dir, 'pca_clusters_2d.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 5. Radar Chart ──
    radar_data = labelled.groupby('Segment')[['Recency','Frequency','Monetary']].mean()
    radar_norm = (radar_data - radar_data.min()) / (radar_data.max() - radar_data.min() + 1e-10)
    n_feats = len(feature_labels)
    angles = np.linspace(0, 2*np.pi, n_feats, endpoint=False).tolist() + [0]
    fig, ax = plt.subplots(figsize=(9, 9), subplot_kw=dict(polar=True))
    for seg in segments:
        if seg not in radar_norm.index: continue
        vals = radar_norm.loc[seg].values.tolist() + [radar_norm.loc[seg].values[0]]
        ax.fill(angles, vals, alpha=0.15, color=seg_color[seg])
        ax.plot(angles, vals, 'o-', linewidth=2, markersize=6, label=seg, color=seg_color[seg])
    ax.set_xticks(angles[:-1]); ax.set_xticklabels(feature_labels, fontsize=11, fontweight='bold')
    ax.set_title('Segment Profiles (Radar Chart)', fontsize=15, fontweight='bold', pad=30)
    ax.legend(loc='upper right', bbox_to_anchor=(1.3, 1.1), fontsize=10); plt.tight_layout()
    p = os.path.join(charts_dir, 'radar_chart.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 6. Violin Plots ──
    fig, axes = plt.subplots(1, 3, figsize=(15, 5))
    for idx, (feat, title) in enumerate([('Recency','Recency (days)'),('Frequency','Frequency'),('Monetary',f'Monetary ({currency})')]):
        ax = axes[idx]
        data_list = [labelled[labelled['Segment']==s][feat].values for s in segments if len(labelled[labelled['Segment']==s]) > 0]
        valid_segs = [s for s in segments if len(labelled[labelled['Segment']==s]) > 0]
        if data_list:
            vp = ax.violinplot(data_list, positions=range(len(valid_segs)), showmeans=True, showmedians=True)
            for j, body in enumerate(vp['bodies']):
                body.set_facecolor(seg_color.get(valid_segs[j], PALETTE[0])); body.set_alpha(0.6)
            vp['cmeans'].set_color('red'); vp['cmedians'].set_color('black')
            ax.set_xticks(range(len(valid_segs))); ax.set_xticklabels(valid_segs, rotation=30, ha='right', fontsize=9)
        ax.set_title(title, fontsize=13, fontweight='bold')
    plt.suptitle('RFM Distribution by Segment', fontsize=15, fontweight='bold', y=1.02)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'violin_plots.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 7. Segment Distribution (Bar + Pie) ──
    seg_counts = labelled['Segment'].value_counts().reindex([s for s in segments if s in labelled['Segment'].values])
    valid_segs = seg_counts.index.tolist()
    valid_colors = [seg_color.get(s, PALETTE[0]) for s in valid_segs]
    fig, axes = plt.subplots(1, 2, figsize=(14, 6))
    bars = axes[0].bar(valid_segs, seg_counts.values, color=valid_colors, edgecolor='white', linewidth=2)
    axes[0].set_title('Customer Count by Segment', fontsize=14, fontweight='bold')
    axes[0].tick_params(axis='x', rotation=30)
    for bar, val in zip(bars, seg_counts.values):
        axes[0].text(bar.get_x()+bar.get_width()/2, bar.get_height()+2, str(int(val)), ha='center', fontsize=12, fontweight='bold')
    axes[1].pie(seg_counts.values, labels=valid_segs, colors=valid_colors, autopct='%1.1f%%', startangle=90, textprops={'fontsize':10})
    axes[1].set_title('Segment Distribution', fontsize=14, fontweight='bold')
    plt.tight_layout()
    p = os.path.join(charts_dir, 'segment_distribution.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 8. Revenue Analysis ──
    total_revenue = labelled['Monetary'].sum()
    seg_rev = labelled.groupby('Segment')['Monetary'].agg(['sum','mean']).reindex(valid_segs)
    fig, axes = plt.subplots(1, 2, figsize=(16, 6))
    bars = axes[0].bar(valid_segs, seg_rev['sum'], color=valid_colors, edgecolor='white')
    axes[0].set_title(f'Total Revenue by Segment ({currency})', fontsize=13, fontweight='bold')
    axes[0].tick_params(axis='x', rotation=30)
    for bar, val in zip(bars, seg_rev['sum']):
        axes[0].text(bar.get_x()+bar.get_width()/2, bar.get_height()+total_revenue*0.01, f'{val:,.0f}', ha='center', fontsize=9, fontweight='bold')
    bars2 = axes[1].bar(valid_segs, seg_rev['mean'], color=valid_colors, edgecolor='white')
    axes[1].set_title(f'Avg Customer Value ({currency})', fontsize=13, fontweight='bold')
    axes[1].tick_params(axis='x', rotation=30)
    plt.suptitle('Revenue Analysis by Segment', fontsize=16, fontweight='bold', y=1.01)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'revenue_analysis.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 9. ANOVA Bar Chart ──
    anova = validation_results.get('anova', {})
    if anova:
        fig, ax = plt.subplots(figsize=(10, 5))
        a_labels = list(anova.keys()); a_f = [v['F'] for v in anova.values()]
        a_colors = ['#2ECC71' if v['significant'] else '#E74C3C' for v in anova.values()]
        bars = ax.barh(a_labels, a_f, color=a_colors, edgecolor='white')
        ax.set_title('ANOVA F-Statistics (Green = Significant)', fontsize=13, fontweight='bold')
        ax.set_xlabel('F-statistic')
        for bar, val, res in zip(bars, a_f, anova.values()):
            ax.text(bar.get_width()+0.5, bar.get_y()+bar.get_height()/2, f'F={val:.1f}, p={res["p"]:.2e}', va='center', fontsize=9)
        plt.tight_layout()
        p = os.path.join(charts_dir, 'anova_validation.png')
        plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 10. ARI Stability ──
    ari_scores = validation_results.get('ari_scores', [])
    if ari_scores:
        avg_ari = validation_results['avg_ari']; std_ari = validation_results['std_ari']
        fig, ax = plt.subplots(figsize=(10, 5))
        bars = ax.bar(range(1, len(ari_scores)+1), ari_scores, color='#3498DB', edgecolor='white', alpha=0.8)
        ax.axhline(y=avg_ari, color='#E74C3C', linestyle='--', linewidth=2, label=f'Mean ARI: {avg_ari:.4f}')
        ax.fill_between(range(0, len(ari_scores)+2), avg_ari-std_ari, avg_ari+std_ari, alpha=0.1, color='#E74C3C')
        for bar, val in zip(bars, ari_scores):
            ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.005, f'{val:.3f}', ha='center', fontsize=9)
        ax.set_xlabel('Run Number'); ax.set_ylabel('Adjusted Rand Index')
        ax.set_title(f'Cluster Stability — {validation_results["stability"]}', fontsize=14, fontweight='bold')
        ax.legend(fontsize=11); ax.set_ylim(0, 1.05); plt.tight_layout()
        p = os.path.join(charts_dir, 'stability_analysis.png')
        plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 11. SHAP Charts ──
    try:
        import shap
        shap_values = shap_results['shap_values']
        plt.figure(figsize=(10, 6))
        shap.summary_plot(shap_values, scaled_data, feature_names=feature_labels,
                          class_names=[segment_map.get(i, f'C{i}') for i in range(optimal_k)], show=False)
        plt.title('SHAP Feature Importance (All Segments)', fontsize=14, fontweight='bold')
        plt.tight_layout()
        p = os.path.join(charts_dir, 'shap_summary.png')
        plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

        plt.figure(figsize=(10, 5))
        shap.summary_plot(shap_values, scaled_data, feature_names=feature_labels, plot_type='bar',
                          class_names=[segment_map.get(i, f'C{i}') for i in range(optimal_k)], show=False)
        plt.title('Mean |SHAP| Value per Feature', fontsize=14, fontweight='bold')
        plt.tight_layout()
        p = os.path.join(charts_dir, 'shap_bar.png')
        plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)
    except Exception:
        pass

    # ── 12. Feature Importance + Centroid Profiles ──
    importances = shap_results.get('importances', {})
    centroid_imp = shap_results.get('centroid_importances', {})
    if importances:
        combined = {f: (importances.get(f,0) + centroid_imp.get(f,0))/2 for f in feature_labels}
        sorted_feats = sorted(combined, key=combined.get, reverse=True)
        sorted_vals = [combined[f] for f in sorted_feats]
        centroids = np.array([scaled_data[cluster_labels == k].mean(axis=0) for k in range(optimal_k)])

        fig, axes = plt.subplots(1, 2, figsize=(16, 5))
        bars = axes[0].barh(sorted_feats[::-1], sorted_vals[::-1],
                            color=plt.cm.viridis(np.linspace(0.3, 0.9, len(sorted_feats)))[::-1], edgecolor='white')
        axes[0].set_title('RFM Feature Importance', fontsize=14, fontweight='bold')
        for bar, val in zip(bars, sorted_vals[::-1]):
            axes[0].text(bar.get_width()+0.005, bar.get_y()+bar.get_height()/2, f'{val:.1%}', va='center', fontsize=10, fontweight='bold')

        x = np.arange(len(feature_labels)); w = 0.8/optimal_k
        for k in range(optimal_k):
            axes[1].bar(x+k*w, centroids[k], w, label=segment_map.get(k, f'C{k}'),
                        color=plt.cm.Set2(k/optimal_k), edgecolor='white')
        axes[1].set_title('Cluster Centroid Profiles', fontsize=14, fontweight='bold')
        axes[1].set_xticks(x+w*(optimal_k-1)/2)
        axes[1].set_xticklabels(feature_labels, rotation=30, ha='right', fontsize=9); axes[1].legend(fontsize=8)
        plt.tight_layout()
        p = os.path.join(charts_dir, 'feature_importance.png')
        plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    # ── 13. Revenue Pareto ──
    seg_totals = labelled.groupby('Segment')['Monetary'].sum().sort_values(ascending=False)
    seg_names = seg_totals.index.tolist()
    seg_revs = seg_totals.values.tolist()
    cum_pcts = [sum(seg_revs[:i+1])/total_revenue*100 for i in range(len(seg_revs))]
    pareto_colors = [seg_color.get(s, '#3498DB') for s in seg_names]
    fig, ax1 = plt.subplots(figsize=(12, 6))
    bars = ax1.bar(seg_names, seg_revs, color=pareto_colors, edgecolor='white', linewidth=2)
    ax1.set_ylabel(f'Revenue ({currency})'); ax1.tick_params(axis='x', rotation=30)
    for bar, val in zip(bars, seg_revs):
        ax1.text(bar.get_x()+bar.get_width()/2, bar.get_height()+total_revenue*0.002, f'{currency} {val:,.0f}', ha='center', fontsize=9, fontweight='bold')
    ax2 = ax1.twinx()
    ax2.plot(seg_names, cum_pcts, 'o-', color='#E74C3C', linewidth=2.5, markersize=8)
    ax2.axhline(y=80, color='grey', linestyle='--', alpha=0.5, label='80% line')
    ax2.set_ylabel('Cumulative %', color='#E74C3C')
    for x_val, y_val in zip(seg_names, cum_pcts):
        ax2.annotate(f'{y_val:.0f}%', (x_val, y_val), textcoords="offset points", xytext=(0,10), ha='center', fontsize=10, fontweight='bold', color='#E74C3C')
    ax2.legend(fontsize=10)
    plt.title('Revenue Pareto Analysis', fontsize=15, fontweight='bold', pad=15)
    plt.tight_layout()
    p = os.path.join(charts_dir, 'revenue_pareto.png')
    plt.savefig(p, dpi=150, bbox_inches='tight'); plt.close(); paths.append(p)

    return paths
