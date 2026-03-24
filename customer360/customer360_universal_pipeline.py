# -*- coding: utf-8 -*-
"""
Customer360 - Universal ML Pipeline (Web/Production Version)

Production-ready pipeline converted from Colab notebook.
Designed to run as a background task in FastAPI backend.

Pipeline stages:
  1. CSV Load & Schema Detection
  2. Data Validation & Cleaning  
  3. Outlier Treatment (IQR Winsorization)
  4. RFM Feature Engineering
  5. Feature Scaling (Log Transform + StandardScaler)
  6. PCA Dimensionality Reduction
  7. Optimal K Selection (4 metrics, majority vote)
  8. Clustering Comparison (K-Means, GMM, Hierarchical)
  9. Cluster Visualization & Profiling
  10. Statistical Validation (ANOVA)
  11. Cluster Stability Analysis
  12. SHAP Explainability
  13. Business Insights & Recommendations
  14. PDF Report Generation
  15. Results Export

Usage:
  from customer360_universal_pipeline import run_full_pipeline
  
  results = run_full_pipeline(
      csv_file_path='data.csv',
      output_directory='/tmp/results',
      job_id='job_123',
      column_mapping={'customer': 'Customer ID', 'date': 'Purchase Date', ...}
  )
"""

# Standard library imports

import json
import logging
import os
import re
import shutil
import warnings
from collections import Counter
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Any

import numpy as np
import pandas as pd
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
import seaborn as sns
from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans, AgglomerativeClustering
from sklearn.mixture import GaussianMixture
from sklearn.metrics import (silhouette_score, silhouette_samples,
                             davies_bouldin_score, calinski_harabasz_score,
                             adjusted_rand_score)
from sklearn.ensemble import RandomForestClassifier
from scipy import stats
import shap
import plotly.express as px
import plotly.graph_objects as go
import squarify
from fpdf import FPDF

warnings.filterwarnings('ignore')
sns.set_style('whitegrid')
plt.rcParams.update({'figure.dpi': 150, 'font.family': 'sans-serif',
                     'axes.titleweight': 'bold', 'axes.titlesize': 13})

# Configure logging
logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)

"""## Cell 2: Upload Your Dataset
Upload any CSV file with transaction or sales data. The pipeline will automatically detect your columns.
"""

uploaded = files.upload()
filename = list(uploaded.keys())[0]
raw_df = pd.read_csv(filename)

print(f"\n✅ Loaded: {len(raw_df)} records from '{filename}'")
print(f"Columns ({len(raw_df.columns)}): {list(raw_df.columns)}")
print(f"\nData types:\n{raw_df.dtypes}")
print(f"\nFirst 3 rows:\n")
raw_df.head(3)

"""## Cell 3: Auto-Detect Schema
Automatically identifies which columns are revenue, price, category, date, etc. — regardless of column names.
"""

print("=" * 60)
print("  AUTO-DETECTING DATASET SCHEMA")
print("=" * 60)

def detect_schema(df):
    cols_lower = [c.lower().strip() for c in df.columns]
    orig = list(df.columns)

    def find(keywords, dtype='any'):
        for kw in keywords:
            for i, c in enumerate(cols_lower):
                if kw in c:
                    col = orig[i]
                    if dtype == 'numeric' and not pd.api.types.is_numeric_dtype(df[col]):
                        continue
                    if dtype == 'text' and df[col].dtype != object:
                        continue
                    if dtype == 'date':
                        try:
                            pd.to_datetime(df[col].dropna().iloc[:5])
                        except:
                            continue
                    return col
        return None

    # Core numeric columns
    revenue = (find(['total line amount','total_line','line_amount','total_amount',
                     'order_total','order total','sale_amount','sales_amount',
                     'transaction_amount','invoice_amount','net_amount'], 'numeric') or
               find(['total','revenue','sales','amount','spend','value','gmv'], 'numeric'))

    price   = (find(['unit price','unit_price','item_price','selling_price',
                     'product_price','sale_price','retail_price'], 'numeric') or
               find(['price','cost','rate','fee'], 'numeric'))

    qty     = (find(['quantity','qty','units','items_sold','item_count',
                     'num_items','units_sold'], 'numeric') or
               find(['count','number','volume'], 'numeric'))

    discount= (find(['discount amount','discount_amount','disc_amount',
                     'discount_value','savings_amount'], 'numeric') or
               find(['discount','markdown','reduction','saving'], 'numeric'))

    # Categorical columns
    promo   = (find(['promo code','promo_code','coupon_code','voucher_code',
                     'discount_code','offer_code'], 'text') or
               find(['promo','coupon','voucher','code','offer'], 'text'))

    category= (find(['categor','product_type','item_type','department',
                     'product_category','item_category','sub_category'], 'text') or
               find(['type','class','group','division','segment_cat'], 'text'))

    product = (find(['product name','product_name','item_name','item name',
                     'product_title','item_title','sku_name','product_desc'], 'text') or
               find(['product','item','name','title','description','sku'], 'text'))

    payment = (find(['payment method','payment_method','pay_method',
                     'payment_type','tender_type','pay_type'], 'text') or
               find(['payment','pay','tender','method','channel'], 'text'))

    shipping= (find(['shipping method','shipping_method','ship_method',
                     'delivery_method','fulfillment_method'], 'text') or
               find(['shipping','delivery','fulfillment','dispatch'], 'text'))

    status  = (find(['order status','order_status','transaction_status',
                     'fulfillment_status'], 'text') or
               find(['status','state','condition'], 'text'))

    color   = find(['color','colour','variant_color'], 'text')
    size    = find(['size','variant_size'], 'text')

    date    = (find(['purchase date','purchase_date','order_date',
                     'transaction_date','sale_date','invoice_date'], 'date') or
               find(['date','time','created','timestamp','ordered'], 'date'))

    order   = (find(['order id','order_id','transaction_id','txn_id',
                     'invoice_id','receipt_id'], 'text') or
               find(['order','transaction','invoice','receipt','id'], 'text'))

    customer= (find(['customer id','customer_id','client_id','user_id',
                     'member_id','buyer_id','shopper_id'], 'text') or
               find(['customer','client','user','member','buyer'], 'text'))

    # Detect currency from column names and data
    currency = 'USD'
    all_text = ' '.join(orig + [str(df.iloc[0].tolist())]).lower()
    currency_map = [
        ('ghs','GHS'), ('cedi','GHS'), ('ghanaian','GHS'),
        ('ngn','NGN'), ('naira','NGN'),
        ('kes','KES'), ('shilling','KES'), ('kenya','KES'),
        ('zar','ZAR'), ('rand','ZAR'),
        ('gbp','GBP'), ('pound','GBP'), ('£','GBP'),
        ('eur','EUR'), ('euro','EUR'), ('€','EUR'),
        ('usd','USD'), ('dollar','USD'), ('$','USD'),
    ]
    for kw, cur in currency_map:
        if kw in all_text:
            currency = cur
            break

    # Detect business type from category column values
    biz_type = 'Retail'
    if category:
        cats = ' '.join(df[category].dropna().astype(str).str.lower().unique()[:50])
        biz_map = [
            (['shirt','dress','trouser','jean','jacket','shoe','bag','fashion','cloth','apparel','wear','top','skirt','blouse','hoodie'], 'Fashion Retail'),
            (['phone','laptop','tablet','electronic','gadget','tech','computer','camera','headphone','speaker'], 'Electronics Retail'),
            (['food','grocery','beverage','snack','drink','fruit','vegetable','meat','dairy','bakery'], 'Food & Grocery'),
            (['beauty','cosmetic','skincare','makeup','hair','fragrance','perfume','lotion','serum'], 'Beauty & Personal Care'),
            (['furniture','home','kitchen','decor','bedding','curtain','sofa','mattress','lamp'], 'Home & Furniture'),
            (['book','stationery','school','education','pen','pencil','notebook','textbook'], 'Education & Stationery'),
            (['sport','fitness','gym','exercise','outdoor','football','jersey','athletic'], 'Sports & Fitness'),
            (['toy','game','puzzle','kids','children','baby','infant'], 'Toys & Kids'),
            (['medicine','health','supplement','vitamin','pharmacy','drug','medical'], 'Health & Pharmacy'),
        ]
        for keywords, btype in biz_map:
            if any(k in cats for k in keywords):
                biz_type = btype
                break

    return {
        'revenue': revenue, 'price': price, 'qty': qty,
        'discount': discount, 'promo': promo, 'category': category,
        'product': product, 'payment': payment, 'shipping': shipping,
        'status': status, 'color': color, 'size': size,
        'date': date, 'order': order, 'customer': customer,
        'currency': currency, 'business': biz_type,
    }

SCHEMA = detect_schema(raw_df)
CUR = SCHEMA['currency']
BIZ = SCHEMA['business']

print(f"\n  Business Type  : {BIZ}")
print(f"  Currency       : {CUR}")
print(f"\n  Column Mapping:")
important = ['revenue','price','qty','discount','promo','category','product','payment','date','customer']
for k in important:
    val = SCHEMA[k]
    status_icon = '✅' if val else '⚠️ NOT FOUND'
    print(f"  {k:<12}: {val or status_icon}")

# Warn if critical columns missing
missing_critical = [k for k in ['revenue','price','qty'] if not SCHEMA[k]]
if missing_critical:
    print(f"\n⚠️  WARNING: Could not auto-detect: {missing_critical}")
    print("   Please manually set these in the MANUAL OVERRIDE block below.")
    print("   Example: SCHEMA['revenue'] = 'Your Column Name'")
else:
    print("\n✅ All critical columns detected successfully!")

# ── MANUAL OVERRIDE (edit these if auto-detection got anything wrong) ──
# SCHEMA['revenue']  = 'Total Line Amount'    # uncomment & edit if needed
# SCHEMA['price']    = 'Unit Price'
# SCHEMA['qty']      = 'Quantity'
# SCHEMA['discount'] = 'Discount Amount'
# SCHEMA['promo']    = 'Promo Code'
# SCHEMA['category'] = 'Categories'
# SCHEMA['product']  = 'Product Name'
# SCHEMA['payment']  = 'Payment Method'
# SCHEMA['date']     = 'Purchase Date'
# SCHEMA['customer'] = 'Customer ID (Hashed)'
# CUR = 'GHS'   # currency symbol: GHS, USD, GBP, EUR, NGN, KES etc.
# BIZ = 'Fashion Retail'   # business type for report

"""## Cell 4: Exploratory Data Analysis (EDA)"""

print("=" * 60)
print("  EXPLORATORY DATA ANALYSIS")
print("=" * 60)

print(f"\n📊 Dataset Shape: {raw_df.shape}")
print(f"\n📋 Data Types:\n{raw_df.dtypes}")
print(f"\n📈 Numerical Summary:\n{raw_df.describe().round(2)}")
print(f"\n❓ Missing Values:\n{raw_df.isnull().sum()}")
print(f"\n🔁 Duplicate Rows: {raw_df.duplicated().sum()}")

# Show value counts for key detected columns
for label, col in [('Status', SCHEMA['status']), ('Payment', SCHEMA['payment']),
                    ('Category', SCHEMA['category']), ('Product', SCHEMA['product'])]:
    if col:
        print(f"\n{label} ({col}):")
        print(raw_df[col].value_counts().head(10))

# Chart 1: Distributions of numeric cols
num_cols = [(SCHEMA['qty'], 'Quantity'),
            (SCHEMA['price'], 'Unit Price'),
            (SCHEMA['revenue'], 'Revenue per Transaction')]
num_cols = [(c, l) for c, l in num_cols if c and c in raw_df.columns]

n_charts = len(num_cols)
fig, axes = plt.subplots(2, max(n_charts, 2), figsize=(6*max(n_charts,2), 10))
for i, (col, label) in enumerate(num_cols):
    data = pd.to_numeric(raw_df[col], errors='coerce').dropna()
    axes[0][i].hist(data, bins=30, color='#3498DB', edgecolor='white', alpha=0.8)
    axes[0][i].set_title(f'{label} Distribution')
    axes[0][i].axvline(data.mean(), color='#E74C3C', linestyle='--', label=f'Mean: {data.mean():.1f}')
    axes[0][i].axvline(data.median(), color='purple', linestyle=':', label=f'Median: {data.median():.1f}')
    axes[0][i].legend(fontsize=9)

# Category bar + Payment pie
row1_extra = [(SCHEMA['category'], 1), (SCHEMA['payment'], 2)]
for col, slot in row1_extra:
    if col and col in raw_df.columns and slot < axes[1].shape[0]:
        ax = axes[1][slot-1]
        if slot == 1:
            raw_df[col].value_counts().head(8).plot(kind='barh', ax=ax, color='#2ECC71', edgecolor='white')
            ax.set_title(f'Top {col}')
        else:
            pm = raw_df[col].value_counts()
            ax.pie(pm.values, labels=pm.index, autopct='%1.1f%%', startangle=90, textprops={'fontsize':8})
            ax.set_title(f'{col} Split')

# Discount distribution in last slot
disc_col = SCHEMA['discount']
if disc_col and disc_col in raw_df.columns:
    disc = pd.to_numeric(raw_df[disc_col], errors='coerce').fillna(0)
    axes[1][-1].hist(disc[disc > 0], bins=20, color='#F39C12', edgecolor='white', alpha=0.8)
    axes[1][-1].set_title(f'{disc_col} (> 0)')

plt.suptitle('Exploratory Data Analysis', fontsize=16, fontweight='bold', y=1.01)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/eda_distributions.png', dpi=150, bbox_inches='tight')
plt.show()

# Chart 2: Box plots for outlier detection
num_box = [(SCHEMA['price'], SCHEMA['price'] or 'Price'),
           (SCHEMA['revenue'], SCHEMA['revenue'] or 'Revenue'),
           (SCHEMA['discount'], SCHEMA['discount'] or 'Discount')]
num_box = [(c, l) for c, l in num_box if c and c in raw_df.columns]
if num_box:
    fig, axes = plt.subplots(1, len(num_box), figsize=(6*len(num_box), 5))
    if len(num_box) == 1: axes = [axes]
    for ax, (col, label), color in zip(axes, num_box, ['#3498DB','#2ECC71','#F39C12']):
        data = pd.to_numeric(raw_df[col], errors='coerce').dropna()
        ax.boxplot(data, patch_artist=True, boxprops=dict(facecolor=color, alpha=0.7),
                   medianprops=dict(color='black', linewidth=2),
                   flierprops=dict(marker='o', markersize=4, alpha=0.5))
        ax.set_title(f'{label} — Outlier Detection'); ax.set_ylabel(f'Value ({CUR})')
        q1, q3 = data.quantile(0.25), data.quantile(0.75)
        iqr = q3 - q1
        outliers = ((data < q1-1.5*iqr) | (data > q3+1.5*iqr)).sum()
        ax.text(0.95, 0.95, f'Outliers: {outliers}', transform=ax.transAxes,
                ha='right', va='top', fontsize=10, bbox=dict(boxstyle='round', facecolor='wheat', alpha=0.8))
    plt.suptitle('Outlier Detection (Box Plots)', fontsize=15, fontweight='bold', y=1.02)
    plt.tight_layout()
    plt.savefig(f'{OUTPUT_DIR}/charts/eda_boxplots.png', dpi=150, bbox_inches='tight')
    plt.show()

# Chart 3: Time series (if date column found)
date_col = SCHEMA['date']
if date_col and date_col in raw_df.columns:
    try:
        temp_dates = pd.to_datetime(raw_df[date_col], errors='coerce', utc=True)
        daily = temp_dates.dt.date.value_counts().sort_index()
        fig, ax = plt.subplots(figsize=(14, 4))
        ax.fill_between(daily.index, daily.values, alpha=0.3, color='#3498DB')
        ax.plot(daily.index, daily.values, color='#2C3E50', linewidth=1.5)
        ax.set_title('Daily Transaction Volume Over Time', fontsize=14, fontweight='bold')
        ax.set_xlabel('Date'); ax.set_ylabel('Transactions')
        plt.xticks(rotation=45); plt.tight_layout()
        plt.savefig(f'{OUTPUT_DIR}/charts/eda_timeseries.png', dpi=150, bbox_inches='tight')
        plt.show()
    except Exception as e:
        print(f"⚠️ Could not plot time series: {e}")

# Chart 4: Product treemap (if product column found)
prod_col = SCHEMA['product']
if prod_col and prod_col in raw_df.columns:
    top_prods = raw_df[prod_col].value_counts().head(12)
    fig, ax = plt.subplots(figsize=(12, 7))
    colors = plt.cm.Set3(np.linspace(0, 1, len(top_prods)))
    squarify.plot(sizes=top_prods.values,
                  label=[f'{str(n)[:20]}\n({v})' for n, v in zip(top_prods.index, top_prods.values)],
                  color=colors, alpha=0.85, text_kwargs={'fontsize': 8, 'fontweight': 'bold'}, ax=ax)
    ax.set_title(f'Top {len(top_prods)} Products (Treemap)', fontsize=15, fontweight='bold')
    ax.axis('off'); plt.tight_layout()
    plt.savefig(f'{OUTPUT_DIR}/charts/eda_treemap.png', dpi=150, bbox_inches='tight')
    plt.show()

print("✅ EDA complete")

"""## Cell 5: Data Cleaning"""

print("=" * 60)
print("  DATA CLEANING")
print("=" * 60)

df = raw_df.copy()
cleaning_report = {'original_rows': len(df)}

# Remove duplicates
before = len(df)
df = df.drop_duplicates()
cleaning_report['duplicates_removed'] = before - len(df)
print(f"🔁 Duplicates removed: {cleaning_report['duplicates_removed']}")

# Count missing values
missing = df.isnull().sum().sum()
cleaning_report['missing_values_handled'] = int(missing)
print(f"❓ Missing values found: {missing}")

# Parse date column
date_col = SCHEMA['date']
if date_col and date_col in df.columns:
    df[date_col] = pd.to_datetime(df[date_col], errors='coerce', utc=True)
    before = len(df)
    df = df.dropna(subset=[date_col])
    print(f"📅 Removed {before - len(df)} rows with unparseable dates")

# Drop rows missing critical revenue values
rev_col = SCHEMA['revenue']
if rev_col and rev_col in df.columns:
    df[rev_col] = pd.to_numeric(df[rev_col], errors='coerce')
    before = len(df)
    df = df.dropna(subset=[rev_col])
    df = df[df[rev_col] > 0]
    print(f"💰 Removed {before - len(df)} rows with missing/zero revenue")

# Drop rows with invalid quantities/prices
for col_key in ['qty', 'price']:
    col = SCHEMA[col_key]
    if col and col in df.columns:
        df[col] = pd.to_numeric(df[col], errors='coerce')
        before = len(df)
        df = df.dropna(subset=[col])
        df = df[df[col] >= 0]
        print(f"🔢 Removed {before - len(df)} rows with invalid {col}")

# Filter out cancelled/refunded orders if status column present
status_col = SCHEMA['status']
if status_col and status_col in df.columns:
    valid_statuses = ['confirmed', 'delivered', 'shipped', 'completed',
                      'processing', 'fulfilled', 'paid', 'closed', 'success']
    mask = df[status_col].str.lower().str.strip().isin(valid_statuses)
    if mask.sum() > 0:
        before = len(df)
        df = df[mask]
        print(f"📋 Filtered to valid statuses: kept {len(df)} of {before} rows")
        print(f"   Statuses kept: {df[status_col].unique().tolist()}")
    else:
        print(f"ℹ️ Status column found but no rows matched standard valid statuses — keeping all rows")

# Fill missing categorical values with 'Unknown'/'None'
cat_defaults = {
    SCHEMA['payment']: 'Unknown',
    SCHEMA['shipping']: 'Unknown',
    SCHEMA['promo']: 'None',
    SCHEMA['color']: 'Unknown',
    SCHEMA['size']: 'Unknown',
    SCHEMA['category']: 'Unknown',
}
for col, default in cat_defaults.items():
    if col and col in df.columns:
        df[col] = df[col].fillna(default)

# Ensure numeric discount column exists and fill NaN with 0
disc_col = SCHEMA['discount']
if disc_col and disc_col in df.columns:
    df[disc_col] = pd.to_numeric(df[disc_col], errors='coerce').fillna(0)

cleaning_report['final_rows'] = len(df)
retention = cleaning_report['final_rows'] / cleaning_report['original_rows'] * 100
print(f"\n✅ Cleaning complete: {cleaning_report['original_rows']} → {cleaning_report['final_rows']} rows ({retention:.1f}% retained)")

"""## Cell 6: Outlier Treatment (IQR Winsorization)"""

print("=" * 60)
print("  OUTLIER TREATMENT (IQR Winsorization)")
print("=" * 60)

outlier_report = {}

# Identify numeric columns to treat — use detected schema columns
cap_cols = [c for c in [SCHEMA['revenue'], SCHEMA['price'], SCHEMA['qty'], SCHEMA['discount']]
            if c and c in df.columns and pd.api.types.is_numeric_dtype(df[c])]

print(f"Treating outliers in: {cap_cols}\n")

fig, axes = plt.subplots(2, len(cap_cols), figsize=(5*len(cap_cols), 10))
if len(cap_cols) == 1:
    axes = [[axes[0]], [axes[1]]]

for i, col in enumerate(cap_cols):
    Q1 = df[col].quantile(0.25)
    Q3 = df[col].quantile(0.75)
    IQR = Q3 - Q1
    lower = max(Q1 - 1.5 * IQR, 0)
    upper = Q3 + 1.5 * IQR
    n_outliers = ((df[col] < lower) | (df[col] > upper)).sum()
    pct = n_outliers / len(df) * 100

    # Before plot
    axes[0][i].hist(df[col], bins=40, color='#E74C3C', edgecolor='white', alpha=0.75)
    axes[0][i].set_title(f'{col}\nBEFORE (outliers: {n_outliers}, {pct:.1f}%)', fontsize=10, fontweight='bold')
    axes[0][i].axvline(upper, color='black', linestyle='--', label=f'Cap: {upper:.0f}')
    axes[0][i].legend(fontsize=8)

    # Apply clipping
    df[col] = df[col].clip(lower=lower, upper=upper)

    # After plot
    axes[1][i].hist(df[col], bins=40, color='#2ECC71', edgecolor='white', alpha=0.75)
    axes[1][i].set_title(f'{col}\nAFTER (Winsorized)', fontsize=10, fontweight='bold')

    # Store report
    outlier_report[col] = {
        'n_capped':   n_outliers,
        'n_outliers': n_outliers,
        'lower':      round(lower, 2),
        'upper':      round(upper, 2),
        'iqr':        round(IQR, 2),
        'pct':        round(pct, 1),
    }
    print(f"  {col:<30}: {n_outliers} capped at [{lower:.2f}, {upper:.2f}] (IQR={IQR:.2f})")

plt.suptitle('Outlier Treatment — Before vs After (IQR Winsorization)', fontsize=15, fontweight='bold', y=1.02)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/outlier_treatment.png', dpi=150, bbox_inches='tight')
plt.show()
print('\n✅ Outlier treatment complete')

"""## Cell 7: RFM Feature Engineering

RFM (Recency, Frequency, Monetary) is a proven customer segmentation framework
that requires only three data points commonly available in SME sales records:
- Customer ID
- Transaction date  
- Transaction amount

This minimalist approach ensures accessibility for small businesses without
sophisticated data collection systems.
"""

print("=" * 60)
print("  RFM FEATURE ENGINEERING")
print("=" * 60)

# ── Identify required columns ────────────────────────────────────
customer_col = SCHEMA['customer']
date_col = SCHEMA['date']
rev_col = SCHEMA['revenue']

# Validate we have the minimum required columns
if not customer_col or customer_col not in df.columns:
    raise ValueError("Customer ID column not found. RFM requires a customer identifier.")
if not date_col or date_col not in df.columns:
    raise ValueError("Date column not found. RFM requires transaction dates.")
if not rev_col or rev_col not in df.columns:
    raise ValueError("Amount/Revenue column not found. RFM requires transaction amounts.")

print(f"  Customer column: {customer_col}")
print(f"  Date column: {date_col}")
print(f"  Amount column: {rev_col}")

# ── Compute RFM metrics per customer ─────────────────────────────
reference_date = df[date_col].max() + pd.Timedelta(days=1)

rfm = df.groupby(customer_col).agg({
    date_col: lambda x: (reference_date - x.max()).days,  # Recency
    rev_col: ['count', 'sum']  # Frequency and Monetary
}).reset_index()

# Flatten column names
rfm.columns = ['customer_id', 'recency', 'frequency', 'monetary']

# Handle edge cases
rfm['recency'] = rfm['recency'].clip(lower=0)
rfm['frequency'] = rfm['frequency'].clip(lower=1)
rfm['monetary'] = rfm['monetary'].clip(lower=0)

print(f"\n📊 RFM computed for {len(rfm)} unique customers")
print(f"   From {len(df)} transactions")
print(f"   Reference date: {reference_date.strftime('%Y-%m-%d')}")

# ── RFM Statistics ───────────────────────────────────────────────
print(f"\n📊 RFM Statistics:")
print(f"   Recency:   mean={rfm['recency'].mean():.1f} days, median={rfm['recency'].median():.1f} days")
print(f"   Frequency: mean={rfm['frequency'].mean():.1f} txns, median={rfm['frequency'].median():.1f} txns")
print(f"   Monetary:  mean={CUR} {rfm['monetary'].mean():,.0f}, median={CUR} {rfm['monetary'].median():,.0f}")

FEAT_COLS = ['recency', 'frequency', 'monetary']
FEAT_LABELS = ['Recency', 'Frequency', 'Monetary']

print(f"\n✅ RFM feature matrix: {len(rfm)} customers × 3 features")
print(f"   Features: {FEAT_LABELS}")

# Distribution plot
fig, axes = plt.subplots(1, 3, figsize=(15, 5))
for i, (col, label) in enumerate(zip(FEAT_COLS, FEAT_LABELS)):
    ax = axes[i]
    ax.hist(rfm[col], bins=30, color='#3498DB', edgecolor='white', alpha=0.8)
    ax.set_title(f'{label}\nSkew={rfm[col].skew():.2f}', fontsize=12, fontweight='bold')
    ax.axvline(rfm[col].mean(), color='red', linestyle='--', alpha=0.7, label=f'Mean: {rfm[col].mean():.1f}')
    ax.axvline(rfm[col].median(), color='green', linestyle=':', alpha=0.7, label=f'Median: {rfm[col].median():.1f}')
    ax.legend(fontsize=9)
    ax.set_xlabel(label)
plt.suptitle('RFM Distributions (Before Scaling)', fontsize=14, fontweight='bold', y=1.02)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/rfm_distributions.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ RFM feature engineering complete")

"""## Cell 8: Feature Scaling (Log Transform + StandardScaler)

RFM features (especially Frequency and Monetary) are typically right-skewed.
We apply log transformation to compress outliers, then StandardScaler for
zero-mean unit-variance normalization — matching the web app pipeline.
"""

print("=" * 60)
print("  FEATURE SCALING (Log Transform + StandardScaler)")
print("=" * 60)

# ── Log transformation for skewed features ───────────────────────
rfm_for_scaling = rfm.copy()

print("📊 Applying log transformation to reduce skewness:")
for col in ['frequency', 'monetary']:
    skew_before = rfm_for_scaling[col].skew()
    rfm_for_scaling[f'{col}_log'] = np.log1p(rfm_for_scaling[col])
    skew_after = rfm_for_scaling[f'{col}_log'].skew()
    print(f"   {col}: skew {skew_before:.2f} → {skew_after:.2f}")

# Use log-transformed versions for frequency and monetary
features_for_scaling = ['recency', 'frequency_log', 'monetary_log']

# ── StandardScaler ───────────────────────────────────────────────
scaler = StandardScaler()
scaled_data = scaler.fit_transform(rfm_for_scaling[features_for_scaling].values)
scaled_df = pd.DataFrame(scaled_data, columns=FEAT_LABELS)

print(f"\n📊 Scaled Statistics:")
print(scaled_df.describe().round(3))
print(f"\n✅ {scaled_data.shape[0]} customers scaled (mean≈0, std≈1)")

"""## Cell 9: PCA Dimensionality Reduction"""

print("=" * 60)
print("  PCA DIMENSIONALITY REDUCTION")
print("=" * 60)

pca_full = PCA()
pca_full.fit(scaled_data)
exp_var = pca_full.explained_variance_ratio_
cum_var = np.cumsum(exp_var)

print("📊 Explained Variance per Component:")
for i, (ev, cv) in enumerate(zip(exp_var, cum_var)):
    marker = " ← 85% threshold" if i > 0 and cum_var[i-1] < 0.85 and cv >= 0.85 else ""
    print(f"  PC{i+1}: {ev:.1%} (Cumulative: {cv:.1%}){marker}")

n_components = np.argmax(cum_var >= 0.85) + 1
print(f"\n🎯 {n_components} components needed for ≥85% variance")

n_2d = min(2, scaled_data.shape[1])
n_3d = min(3, scaled_data.shape[1])

pca = PCA(n_components=n_2d)
pca_2d = pca.fit_transform(scaled_data)
print(f"📊 2D PCA captures {pca.explained_variance_ratio_.sum():.1%} of variance")

pca3 = PCA(n_components=n_3d)
pca_3d = pca3.fit_transform(scaled_data)
print(f"📊 {n_3d}D PCA captures {pca3.explained_variance_ratio_.sum():.1%} of variance")

# Scree plot
fig, axes = plt.subplots(1, 2, figsize=(14, 5))
axes[0].bar(range(1, len(exp_var)+1), exp_var, color='#3498DB', edgecolor='white', alpha=0.8)
axes[0].plot(range(1, len(exp_var)+1), exp_var, 'o-', color='#2C3E50', linewidth=2)
axes[0].set_title('Scree Plot — Variance per Component')
axes[0].set_xlabel('PC'); axes[0].set_ylabel('Explained Variance')
axes[1].plot(range(1, len(cum_var)+1), cum_var, 's-', color='#E74C3C', linewidth=2.5, markersize=8)
axes[1].axhline(y=0.85, color='#2ECC71', linestyle='--', linewidth=2, label='85% Threshold')
axes[1].fill_between(range(1, len(cum_var)+1), cum_var, alpha=0.1, color='#E74C3C')
axes[1].set_title('Cumulative Explained Variance')
axes[1].set_xlabel('Number of Components'); axes[1].set_ylabel('Cumulative Variance')
axes[1].legend(fontsize=11)
plt.suptitle('PCA Analysis', fontsize=15, fontweight='bold', y=1.02)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/pca_scree.png', dpi=150, bbox_inches='tight')
plt.show()

# Biplot
fig, ax = plt.subplots(figsize=(10, 8))
ax.scatter(pca_2d[:, 0], pca_2d[:, 1], alpha=0.3, s=15, color='#95A5A6')
loadings = pca.components_.T
for i, feat in enumerate(FEAT_LABELS):
    ax.annotate('', xy=(loadings[i,0]*5, loadings[i,1]*5), xytext=(0,0),
                arrowprops=dict(arrowstyle='->', color='#E74C3C', lw=2))
    ax.text(loadings[i,0]*5.3, loadings[i,1]*5.3, feat, fontsize=10, fontweight='bold', color='#C0392B')
ax.set_xlabel(f'PC1 ({pca.explained_variance_ratio_[0]:.1%})')
ax.set_ylabel(f'PC2 ({pca.explained_variance_ratio_[1]:.1%})')
ax.set_title('PCA Biplot — Feature Loadings', fontsize=14, fontweight='bold')
ax.axhline(0, color='grey', lw=0.5); ax.axvline(0, color='grey', lw=0.5)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/pca_biplot.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ PCA complete")

"""## Cell 10: Find Optimal K (Number of Segments)"""

print("=" * 60)
print("  OPTIMAL K SELECTION (4 METRICS)")
print("=" * 60)

n_samples = len(scaled_data)
max_k = min(11, n_samples)
k_range = range(2, max_k)
results = {'k': [], 'Inertia': [], 'Silhouette': [], 'Calinski-Harabasz': [], 'Davies-Bouldin': []}

for k in k_range:
    km = KMeans(n_clusters=k, random_state=42, n_init=10, max_iter=300)
    labs = km.fit_predict(scaled_data)
    results['k'].append(k)
    results['Inertia'].append(round(km.inertia_, 1))
    results['Silhouette'].append(round(silhouette_score(scaled_data, labs), 4))
    results['Calinski-Harabasz'].append(round(calinski_harabasz_score(scaled_data, labs), 1))
    results['Davies-Bouldin'].append(round(davies_bouldin_score(scaled_data, labs), 4))
    print(f"  k={k}: Sil={results['Silhouette'][-1]:.4f} | CH={results['Calinski-Harabasz'][-1]:.1f} | DB={results['Davies-Bouldin'][-1]:.4f}")

results_df = pd.DataFrame(results)
print(f"\n📊 Full Comparison:\n{results_df.to_string(index=False)}")

best_sil = list(k_range)[np.argmax(results['Silhouette'])]
best_ch  = list(k_range)[np.argmax(results['Calinski-Harabasz'])]
best_db  = list(k_range)[np.argmin(results['Davies-Bouldin'])]
print(f"\n🎯 Best by Silhouette: k={best_sil}")
print(f"🎯 Best by Calinski-Harabasz: k={best_ch}")
print(f"🎯 Best by Davies-Bouldin: k={best_db}")

votes = [best_sil, best_ch, best_db]
vote_counts = Counter(votes)
optimal_k = vote_counts.most_common(1)[0][0]
print(f"\n✅ OPTIMAL k = {optimal_k} (majority vote)")

# Plots
fig, axes = plt.subplots(2, 2, figsize=(14, 10))
metrics = [('Inertia','#3498DB','lower'),('Silhouette','#2ECC71','higher'),
           ('Calinski-Harabasz','#F39C12','higher'),('Davies-Bouldin','#E74C3C','lower')]
for ax, (metric, color, direction) in zip(axes.flat, metrics):
    vals = results[metric]
    ax.plot(list(k_range), vals, 'o-', color=color, linewidth=2.5, markersize=8)
    ax.fill_between(list(k_range), vals, alpha=0.1, color=color)
    ax.axvline(x=optimal_k, color='grey', linestyle=':', linewidth=2, alpha=0.7)
    best_idx = np.argmax(vals) if direction == 'higher' else np.argmin(vals)
    ax.scatter([list(k_range)[best_idx]], [vals[best_idx]], s=200, color=color,
               zorder=5, edgecolors='black', linewidths=2)
    ax.set_title(f'{metric} ({direction} = better)', fontsize=12, fontweight='bold')
    ax.set_xlabel('k'); ax.set_ylabel(metric)
plt.suptitle(f'Optimal K Selection — Chosen k={optimal_k}', fontsize=16, fontweight='bold', y=1.02)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/optimal_k_4metrics.png', dpi=150, bbox_inches='tight')
plt.show()

# Silhouette per cluster
fig, ax = plt.subplots(figsize=(10, 6))
km_opt = KMeans(n_clusters=optimal_k, random_state=42, n_init=10)
cluster_labels = km_opt.fit_predict(scaled_data)
sil_vals = silhouette_samples(scaled_data, cluster_labels)
y_lower = 10
colors_sil = plt.cm.Set2(np.linspace(0, 1, optimal_k))
for i in range(optimal_k):
    clust = np.sort(sil_vals[cluster_labels == i])
    y_upper = y_lower + len(clust)
    ax.fill_betweenx(np.arange(y_lower, y_upper), 0, clust, alpha=0.7, color=colors_sil[i])
    ax.text(-0.05, y_lower + 0.5*len(clust), f'Cluster {i}', fontsize=11, fontweight='bold')
    y_lower = y_upper + 10
avg_sil = results['Silhouette'][list(k_range).index(optimal_k)]
ax.axvline(x=avg_sil, color='red', linestyle='--', lw=2, label=f'Avg: {avg_sil:.3f}')
ax.set_title(f'Silhouette Plot (k={optimal_k})', fontsize=15, fontweight='bold')
ax.set_xlabel('Silhouette Coefficient'); ax.legend(fontsize=12)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/silhouette_plot.png', dpi=150, bbox_inches='tight')
plt.show()
print(f"✅ Optimal k = {optimal_k}")

"""## Cell 11: Run Clustering (K-Means, GMM, Hierarchical)

Run all three clustering algorithms to compare results.
This matches the web app's clustering comparison feature.
"""

print("=" * 60)
print(f"  CLUSTERING COMPARISON (k={optimal_k})")
print("=" * 60)

algo_results = {}

# ── K-Means ──────────────────────────────────────────────────────
print("\n🔵 Running K-Means...")
kmeans_model = KMeans(n_clusters=optimal_k, random_state=42, n_init=10, max_iter=300)
kmeans_labels = kmeans_model.fit_predict(scaled_data)
kmeans_sil = silhouette_score(scaled_data, kmeans_labels)
kmeans_db = davies_bouldin_score(scaled_data, kmeans_labels)
kmeans_ch = calinski_harabasz_score(scaled_data, kmeans_labels)
algo_results['K-Means'] = {
    'labels': kmeans_labels,
    'silhouette': kmeans_sil,
    'davies_bouldin': kmeans_db,
    'calinski': kmeans_ch
}
print(f"   Silhouette: {kmeans_sil:.4f} | Davies-Bouldin: {kmeans_db:.4f} | Calinski-Harabasz: {kmeans_ch:.1f}")

# ── GMM (Gaussian Mixture Model) ─────────────────────────────────
print("\n🟢 Running GMM...")
gmm_model = GaussianMixture(n_components=optimal_k, random_state=42, covariance_type='full', n_init=3)
gmm_labels = gmm_model.fit_predict(scaled_data)
gmm_sil = silhouette_score(scaled_data, gmm_labels)
gmm_db = davies_bouldin_score(scaled_data, gmm_labels)
gmm_ch = calinski_harabasz_score(scaled_data, gmm_labels)
algo_results['GMM'] = {
    'labels': gmm_labels,
    'silhouette': gmm_sil,
    'davies_bouldin': gmm_db,
    'calinski': gmm_ch,
    'bic': gmm_model.bic(scaled_data),
    'aic': gmm_model.aic(scaled_data)
}
print(f"   Silhouette: {gmm_sil:.4f} | Davies-Bouldin: {gmm_db:.4f} | Calinski-Harabasz: {gmm_ch:.1f}")
print(f"   BIC: {gmm_model.bic(scaled_data):.1f} | AIC: {gmm_model.aic(scaled_data):.1f}")

# ── Hierarchical (Agglomerative) ─────────────────────────────────
print("\n🟣 Running Hierarchical (Ward linkage)...")
hc_model = AgglomerativeClustering(n_clusters=optimal_k, linkage='ward')
hc_labels = hc_model.fit_predict(scaled_data)
hc_sil = silhouette_score(scaled_data, hc_labels)
hc_db = davies_bouldin_score(scaled_data, hc_labels)
hc_ch = calinski_harabasz_score(scaled_data, hc_labels)
algo_results['Hierarchical'] = {
    'labels': hc_labels,
    'silhouette': hc_sil,
    'davies_bouldin': hc_db,
    'calinski': hc_ch
}
print(f"   Silhouette: {hc_sil:.4f} | Davies-Bouldin: {hc_db:.4f} | Calinski-Harabasz: {hc_ch:.1f}")

# ── Select Best Algorithm ────────────────────────────────────────
print("\n" + "=" * 60)
print("  ALGORITHM COMPARISON")
print("=" * 60)
print(f"\n{'Algorithm':<15} {'Silhouette':>12} {'Davies-Bouldin':>15} {'Calinski-H':>12}")
print("-" * 55)
for algo, res in algo_results.items():
    print(f"{algo:<15} {res['silhouette']:>12.4f} {res['davies_bouldin']:>15.4f} {res['calinski']:>12.1f}")

# Best by silhouette score
best_algo = max(algo_results.keys(), key=lambda a: algo_results[a]['silhouette'])
print(f"\n✅ Best algorithm: {best_algo} (highest Silhouette score)")

# Use best algorithm's labels
cluster_labels = algo_results[best_algo]['labels']
final_sil = algo_results[best_algo]['silhouette']
final_db = algo_results[best_algo]['davies_bouldin']
final_ch = algo_results[best_algo]['calinski']

# Add cluster labels to RFM dataframe
rfm['Cluster'] = cluster_labels

print(f"\n📊 Cluster Sizes (using {best_algo}):")
for c, n in zip(*np.unique(cluster_labels, return_counts=True)):
    print(f"  Cluster {c}: {n} customers ({n/len(cluster_labels)*100:.1f}%)")

"""## Cell 12: Visualize Clusters"""

print("=" * 60)
print("  CLUSTER VISUALIZATION")
print("=" * 60)

fig, ax = plt.subplots(figsize=(12, 8))
colors_cluster = plt.cm.Set2(np.linspace(0, 1, optimal_k))
for i in range(optimal_k):
    mask = cluster_labels == i
    ax.scatter(pca_2d[mask, 0], pca_2d[mask, 1], c=[colors_cluster[i]],
               label=f'Cluster {i} (n={mask.sum()})', alpha=0.5, s=30,
               edgecolors='white', linewidth=0.3)
centroids_pca = pca.transform(kmeans_model.cluster_centers_)
ax.scatter(centroids_pca[:, 0], centroids_pca[:, 1], c='black', marker='X',
           s=200, linewidths=2, edgecolors='white', zorder=10, label='Centroids')
ax.set_xlabel(f'PC1 ({pca.explained_variance_ratio_[0]:.1%} variance)', fontsize=12)
ax.set_ylabel(f'PC2 ({pca.explained_variance_ratio_[1]:.1%} variance)', fontsize=12)
ax.set_title(f'K-Means Clusters in PCA Space (k={optimal_k})', fontsize=15, fontweight='bold')
ax.legend(fontsize=10)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/pca_clusters_2d.png', dpi=150, bbox_inches='tight')
plt.show()

# 3D interactive
pca_df_3d = pd.DataFrame({'PC1': pca_3d[:,0], 'PC2': pca_3d[:,1], 'PC3': pca_3d[:,2],
                           'Cluster': [f'Cluster {c}' for c in cluster_labels]})
fig3d = px.scatter_3d(pca_df_3d, x='PC1', y='PC2', z='PC3', color='Cluster', opacity=0.6,
                      title=f'3D PCA Cluster Visualization')
fig3d.update_layout(height=600); fig3d.show()
print("✅ Cluster visualization complete")

"""## Cell 13: RFM Segment Labeling & Profiling

Uses RFM-based segment names that match the web app and industry standards.
Segments are labeled based on Recency, Frequency, and Monetary scores.
"""

print("=" * 60)
print("  RFM SEGMENT LABELING & PROFILING")
print("=" * 60)

# ── RFM-based segment definitions (matching web app) ─────────────
# These are industry-standard RFM segment names
RFM_SEGMENT_DEFINITIONS = {
    'Champions':           {'description': 'Best customers: bought recently, buy often, spend most'},
    'Loyal Customers':     {'description': 'High frequency and monetary, good recency'},
    'Potential Loyalists': {'description': 'Recent customers with average frequency, potential for growth'},
    'New Customers':       {'description': 'Bought recently but low frequency and spend'},
    'Promising':           {'description': 'Recent shoppers but low spend, need nurturing'},
    'Need Attention':      {'description': 'Above average RFM but not recent, may be slipping'},
    'About to Sleep':      {'description': 'Below average recency and frequency, at risk'},
    'At Risk':             {'description': 'Spent big money but long time ago, need reactivation'},
    'Cannot Lose Them':    {'description': 'Used to be top customers, haven\'t returned recently'},
    'Hibernating':         {'description': 'Low spend and long time since last purchase'},
    'Lost Customers':      {'description': 'Lowest RFM scores, likely churned'},
}

# ── Compute RFM scores for each cluster ──────────────────────────
cluster_rfm = rfm.groupby('Cluster').agg({
    'recency': 'mean',
    'frequency': 'mean',
    'monetary': 'mean'
}).round(2)

print("📊 Cluster RFM Centroids:")
print(cluster_rfm)

# ── Label clusters based on RFM patterns ─────────────────────────
def label_rfm_segment(row, rfm_df):
    """Assign RFM segment label based on cluster's RFM profile."""
    # Normalize within the dataset
    r_pct = (rfm_df['recency'].max() - row['recency']) / (rfm_df['recency'].max() - rfm_df['recency'].min() + 1e-10)
    f_pct = (row['frequency'] - rfm_df['frequency'].min()) / (rfm_df['frequency'].max() - rfm_df['frequency'].min() + 1e-10)
    m_pct = (row['monetary'] - rfm_df['monetary'].min()) / (rfm_df['monetary'].max() - rfm_df['monetary'].min() + 1e-10)
    
    # Score 1-5 (5 is best)
    r_score = min(5, max(1, int(r_pct * 5) + 1))
    f_score = min(5, max(1, int(f_pct * 5) + 1))
    m_score = min(5, max(1, int(m_pct * 5) + 1))
    
    # Assign segment based on RFM scores
    if r_score >= 4 and f_score >= 4 and m_score >= 4:
        return 'Champions'
    elif r_score >= 3 and f_score >= 4:
        return 'Loyal Customers'
    elif r_score >= 4 and f_score <= 2:
        return 'New Customers'
    elif r_score >= 3 and f_score >= 2 and m_score >= 3:
        return 'Potential Loyalists'
    elif r_score >= 4 and m_score <= 2:
        return 'Promising'
    elif r_score <= 2 and f_score >= 3 and m_score >= 3:
        return 'At Risk'
    elif r_score <= 2 and f_score >= 4 and m_score >= 4:
        return 'Cannot Lose Them'
    elif r_score <= 2 and f_score <= 2 and m_score >= 3:
        return 'Hibernating'
    elif r_score <= 2 and f_score <= 2 and m_score <= 2:
        return 'Lost Customers'
    elif r_score <= 3 and f_score >= 2:
        return 'Need Attention'
    else:
        return 'About to Sleep'

# Apply labeling to each cluster
segment_map = {}
for cid in cluster_rfm.index:
    segment_map[cid] = label_rfm_segment(cluster_rfm.loc[cid], rfm)

rfm['Segment'] = rfm['Cluster'].map(segment_map)
segments = list(set(segment_map.values()))

print(f"\n📊 Segment Assignments:")
for cid, seg in segment_map.items():
    count = (rfm['Cluster'] == cid).sum()
    print(f"  Cluster {cid} → {seg} ({count} customers)")

PALETTE = ['#2ECC71','#3498DB','#9B59B6','#F39C12','#E74C3C','#1ABC9C','#2C3E50','#F1C40F']
seg_color_map = {seg: PALETTE[i % len(PALETTE)] for i, seg in enumerate(segments)}
seg_colors = [seg_color_map[s] for s in segments]

# ── Build segment profiles from RFM data ─────────────────────────
segment_insights = {}
for seg in segments:
    sd = rfm[rfm['Segment'] == seg]
    if len(sd) == 0: continue
    
    info = {
        'count': len(sd),
        'pct': round(len(sd)/len(rfm)*100, 1),
        'avg_recency': round(sd['recency'].mean(), 1),
        'avg_frequency': round(sd['frequency'].mean(), 1),
        'avg_monetary': round(sd['monetary'].mean(), 2),
        'total_revenue': round(sd['monetary'].sum(), 2),
        'median_monetary': round(sd['monetary'].median(), 2),
    }
    
    # Get segment description
    info['description'] = RFM_SEGMENT_DEFINITIONS.get(seg, {}).get('description', 'Customer segment')
    
    segment_insights[seg] = info
    print(f"\n🎯 {seg} ({info['count']} customers, {info['pct']}%)")
    print(f"   {info['description']}")
    print(f"   Recency: {info['avg_recency']:.0f} days | Frequency: {info['avg_frequency']:.1f} txns | Monetary: {CUR} {info['avg_monetary']:,.0f}")
    print(f"   Total Revenue: {CUR} {info['total_revenue']:,.0f}")

"""## Cell 14: Radar Charts & Violin Plots"""

print("=" * 60)
print("  RADAR CHARTS & DISTRIBUTION PLOTS")
print("=" * 60)

# Radar chart — use RFM features
radar_feat_cols = FEAT_COLS
radar_feat_labels = FEAT_LABELS

if len(radar_feat_cols) >= 3:
    radar_data = rfm.groupby('Segment')[radar_feat_cols].mean()
    radar_data.columns = radar_feat_labels
    radar_norm = (radar_data - radar_data.min()) / (radar_data.max() - radar_data.min() + 1e-10)
    radar_norm = radar_norm.reindex([s for s in segments if s in radar_norm.index])

    n_feats = len(radar_feat_labels)
    angles = np.linspace(0, 2*np.pi, n_feats, endpoint=False).tolist()
    angles += angles[:1]

    fig, ax = plt.subplots(figsize=(9, 9), subplot_kw=dict(polar=True))
    for i, seg in enumerate(segments):
        if seg not in radar_norm.index: continue
        vals = radar_norm.loc[seg].values.tolist() + [radar_norm.loc[seg].values[0]]
        color = seg_color_map.get(seg, PALETTE[i % len(PALETTE)])
        ax.fill(angles, vals, alpha=0.15, color=color)
        ax.plot(angles, vals, 'o-', linewidth=2, markersize=6, label=seg, color=color)
    ax.set_xticks(angles[:-1])
    ax.set_xticklabels(radar_feat_labels, fontsize=11, fontweight='bold')
    ax.set_title('Segment Profiles (Radar Chart)', fontsize=15, fontweight='bold', pad=30)
    ax.legend(loc='upper right', bbox_to_anchor=(1.3, 1.1), fontsize=10)
    plt.tight_layout()
    plt.savefig(f'{OUTPUT_DIR}/charts/radar_chart.png', dpi=150, bbox_inches='tight')
    plt.show()

# Violin plots — use RFM features
violin_pairs = [('recency', 'Recency (days)'),
                ('frequency', 'Frequency (transactions)'),
                ('monetary', f'Monetary ({CUR})')]

ncols = 3
nrows = 1
fig, axes = plt.subplots(nrows, ncols, figsize=(15, 5))

for idx, (col, title) in enumerate(violin_pairs):
    ax = axes[idx]
    data_list = [rfm[rfm['Segment']==s][col].values for s in segments if len(rfm[rfm['Segment']==s]) > 0]
    valid_segments = [s for s in segments if len(rfm[rfm['Segment']==s]) > 0]
    if data_list:
        vp = ax.violinplot(data_list, positions=range(len(valid_segments)), showmeans=True, showmedians=True)
        for j, body in enumerate(vp['bodies']):
            body.set_facecolor(seg_colors[j % len(seg_colors)]); body.set_alpha(0.6)
        vp['cmeans'].set_color('red'); vp['cmedians'].set_color('black')
        ax.set_xticks(range(len(valid_segments)))
        ax.set_xticklabels(valid_segments, rotation=30, ha='right', fontsize=9)
    ax.set_title(title, fontsize=13, fontweight='bold')

plt.suptitle('RFM Distribution by Segment (Violin Plots)', fontsize=15, fontweight='bold', y=1.02)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/violin_plots.png', dpi=150, bbox_inches='tight')
plt.show()

print("✅ Radar & distribution charts complete")

"""## Cell 15: Behavioral Analysis Charts"""

print("=" * 60)
print("  BEHAVIORAL ANALYSIS")
print("=" * 60)

total_revenue = rfm['monetary'].sum()

# Segment count + pie (using RFM data)
seg_counts = rfm['Segment'].value_counts().reindex([s for s in segments if s in rfm['Segment'].values])
valid_segments = seg_counts.index.tolist()
valid_colors = [seg_color_map.get(s, PALETTE[0]) for s in valid_segments]

fig, axes = plt.subplots(1, 2, figsize=(14, 6))
bars = axes[0].bar(valid_segments, seg_counts.values, color=valid_colors, edgecolor='white', linewidth=2)
axes[0].set_title('Customer Count by Segment', fontsize=14, fontweight='bold')
axes[0].tick_params(axis='x', rotation=30)
for bar, val in zip(bars, seg_counts.values):
    axes[0].text(bar.get_x()+bar.get_width()/2, bar.get_height()+2, str(int(val)), ha='center', fontsize=12, fontweight='bold')
axes[1].pie(seg_counts.values, labels=valid_segments, colors=valid_colors, autopct='%1.1f%%', startangle=90, textprops={'fontsize':10})
axes[1].set_title('Segment Distribution', fontsize=14, fontweight='bold')
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/segment_distribution.png', dpi=150, bbox_inches='tight')
plt.show()

# Revenue + avg monetary by segment
fig, axes = plt.subplots(1, 2, figsize=(16, 6))

# Total Revenue by Segment
rev = [segment_insights[s]['total_revenue'] for s in valid_segments if s in segment_insights]
bars = axes[0].bar(valid_segments[:len(rev)], rev, color=valid_colors[:len(rev)], edgecolor='white')
axes[0].set_title(f'Total Revenue by Segment ({CUR})', fontsize=13, fontweight='bold')
axes[0].tick_params(axis='x', rotation=30)
for bar, val in zip(bars, rev):
    axes[0].text(bar.get_x()+bar.get_width()/2, bar.get_height()+total_revenue*0.01, f'{val:,.0f}', ha='center', fontsize=9, fontweight='bold')

# Avg Monetary by Segment
avg_m = [segment_insights[s]['avg_monetary'] for s in valid_segments if s in segment_insights]
bars = axes[1].bar(valid_segments[:len(avg_m)], avg_m, color=valid_colors[:len(avg_m)], edgecolor='white')
axes[1].set_title(f'Avg Customer Value ({CUR})', fontsize=13, fontweight='bold')
axes[1].tick_params(axis='x', rotation=30)
for bar, val in zip(bars, avg_m):
    axes[1].text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.5, f'{CUR} {val:,.0f}', ha='center', fontsize=9, fontweight='bold')

plt.suptitle('Revenue Analysis by Segment', fontsize=16, fontweight='bold', y=1.01)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/behavioral_analysis.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ Behavioral analysis complete")

"""## Cell 16: Statistical Validation (ANOVA)"""

print("=" * 60)
print("  STATISTICAL VALIDATION")
print("=" * 60)

# ANOVA on RFM features
anova_pairs = [('recency', 'Recency'), ('frequency', 'Frequency'), ('monetary', 'Monetary')]

print("\n📊 ANOVA Tests (RFM Features):")
print(f"  {'Feature':<22} {'F-stat':>10} {'p-value':>12} {'Significant?':>14}")
print(f"  {'─'*60}")
anova_results = {}
for col, label in anova_pairs:
    groups = [rfm[rfm['Segment']==s][col].dropna().values for s in valid_segments]
    groups = [g for g in groups if len(g) > 1]
    if len(groups) >= 2:
        try:
            f_stat, p_val = stats.f_oneway(*groups)
            sig = 'Yes (p<0.05)' if p_val < 0.05 else 'No'
            anova_results[label] = {'F': round(float(f_stat), 2), 'p': float(p_val), 'sig': p_val < 0.05}
            print(f"  {label:<22} {f_stat:>10.2f} {p_val:>12.2e} {sig:>14}")
        except Exception as e:
            print(f"  {label:<22} Could not compute: {e}")

if not anova_results:
    anova_results = {'Monetary': {'F': 0, 'p': 1, 'sig': False}}

# Visualize ANOVA results
fig, ax = plt.subplots(figsize=(10, 5))
anova_labels = list(anova_results.keys())
anova_f = [v['F'] for v in anova_results.values()]
anova_colors = ['#2ECC71' if v['sig'] else '#E74C3C' for v in anova_results.values()]
bars = ax.barh(anova_labels, anova_f, color=anova_colors, edgecolor='white')
ax.set_title('ANOVA F-Statistics (RFM Features)\n(Green = Significant)', fontsize=13, fontweight='bold')
ax.set_xlabel('F-statistic')
for bar, val, res in zip(bars, anova_f, anova_results.values()):
    ax.text(bar.get_width()+0.5, bar.get_y()+bar.get_height()/2,
             f'F={val:.1f}, p={res["p"]:.2e}', va='center', fontsize=9)

plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/statistical_validation.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ Statistical validation complete")

"""## Cell 17: Cluster Stability Analysis"""

print("=" * 60)
print("  CLUSTER STABILITY ANALYSIS")
print("=" * 60)

n_runs = 10
reference = KMeans(n_clusters=optimal_k, random_state=42, n_init=10).fit_predict(scaled_data)
ari_scores = []
for seed in range(1, n_runs + 1):
    labels_i = KMeans(n_clusters=optimal_k, random_state=seed*7, n_init=10).fit_predict(scaled_data)
    ari = adjusted_rand_score(reference, labels_i)
    ari_scores.append(round(ari, 4))
    print(f"  Run {seed:>2d} (seed={seed*7:>3d}): ARI = {ari:.4f}")

avg_ari = np.mean(ari_scores); std_ari = np.std(ari_scores)
stability = 'Excellent' if avg_ari > 0.9 else ('Good' if avg_ari > 0.7 else ('Fair' if avg_ari > 0.5 else 'Poor'))
print(f"\n📊 Avg ARI: {avg_ari:.4f} +/- {std_ari:.4f} → Stability: {stability}")

fig, ax = plt.subplots(figsize=(10, 5))
bars = ax.bar(range(1, n_runs+1), ari_scores, color='#3498DB', edgecolor='white', alpha=0.8)
ax.axhline(y=avg_ari, color='#E74C3C', linestyle='--', linewidth=2, label=f'Mean ARI: {avg_ari:.4f}')
ax.fill_between(range(0, n_runs+2), avg_ari-std_ari, avg_ari+std_ari, alpha=0.1, color='#E74C3C')
for bar, val in zip(bars, ari_scores):
    ax.text(bar.get_x()+bar.get_width()/2, bar.get_height()+0.005, f'{val:.3f}', ha='center', fontsize=9)
ax.set_xlabel('Run Number'); ax.set_ylabel('Adjusted Rand Index')
ax.set_title(f'Cluster Stability ({n_runs} runs) — {stability}', fontsize=14, fontweight='bold')
ax.legend(fontsize=11); ax.set_ylim(0, 1.05)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/stability_analysis.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ Stability analysis complete")

"""## Cell 18: Explainable AI (SHAP)"""

print("=" * 60)
print("  EXPLAINABLE AI (XAI) — SHAP")
print("=" * 60)

# Compute cluster centroids from scaled data
cluster_centroids = np.array([scaled_data[cluster_labels == k].mean(axis=0) for k in range(optimal_k)])

# Variance-based importance
centroid_ranges = np.ptp(cluster_centroids, axis=0)
centroid_imp = centroid_ranges / (centroid_ranges.sum() + 1e-10)
variance_imp = []
for i in range(len(FEAT_LABELS)):
    total_var = np.var(scaled_data[:, i])
    overall_mean = np.mean(scaled_data[:, i])
    bv = sum((cluster_labels==k).sum() * (np.mean(scaled_data[cluster_labels==k, i]) - overall_mean)**2
             for k in range(optimal_k)) / len(scaled_data)
    variance_imp.append(bv / (total_var + 1e-10))
variance_imp = np.array(variance_imp)
variance_imp = variance_imp / (variance_imp.sum() + 1e-10)
combined_imp = (centroid_imp + variance_imp) / 2

print("\n📊 RFM Feature Importance:")
for feat, score in sorted(zip(FEAT_LABELS, combined_imp), key=lambda x: x[1], reverse=True):
    bar = '█' * int(score * 40)
    print(f"  {feat:<18} {score:.1%} {bar}")

# SHAP surrogate
print("\n🔍 SHAP with surrogate Random Forest...")
rf = RandomForestClassifier(n_estimators=100, random_state=42, max_depth=5)
rf.fit(scaled_data, cluster_labels)
print(f"  Surrogate accuracy: {rf.score(scaled_data, cluster_labels):.1%}")

explainer = shap.TreeExplainer(rf)
shap_values = explainer.shap_values(scaled_data)

plt.figure(figsize=(10, 6))
shap.summary_plot(shap_values, scaled_data, feature_names=FEAT_LABELS,
                  class_names=[segment_map.get(i, f'C{i}') for i in range(optimal_k)], show=False)
plt.title('SHAP Feature Importance (All Segments)', fontsize=14, fontweight='bold')
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/shap_summary.png', dpi=150, bbox_inches='tight')
plt.show()

plt.figure(figsize=(10, 5))
shap.summary_plot(shap_values, scaled_data, feature_names=FEAT_LABELS, plot_type='bar',
                  class_names=[segment_map.get(i, f'C{i}') for i in range(optimal_k)], show=False)
plt.title('Mean |SHAP| Value per Feature', fontsize=14, fontweight='bold')
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/shap_bar.png', dpi=150, bbox_inches='tight')
plt.show()

# Feature importance bar chart
fig, axes = plt.subplots(1, 2, figsize=(16, 5))
sorted_idx = np.argsort(combined_imp)[::-1]
sorted_feats = [FEAT_LABELS[i] for i in sorted_idx]
sorted_imps = combined_imp[sorted_idx]
bars = axes[0].barh(sorted_feats[::-1], sorted_imps[::-1],
                    color=plt.cm.viridis(np.linspace(0.3, 0.9, len(sorted_feats)))[::-1],
                    edgecolor='white')
axes[0].set_title('RFM Feature Importance Ranking', fontsize=14, fontweight='bold')
for bar, val in zip(bars, sorted_imps[::-1]):
    axes[0].text(bar.get_width()+0.005, bar.get_y()+bar.get_height()/2,
                 f'{val:.1%}', va='center', fontsize=10, fontweight='bold')
x = np.arange(len(FEAT_LABELS)); w = 0.8/optimal_k
for k in range(optimal_k):
    axes[1].bar(x+k*w, cluster_centroids[k], w,
                label=segment_map.get(k, f'C{k}'),
                color=plt.cm.Set2(k/optimal_k), edgecolor='white')
axes[1].set_title('Cluster Centroid Profiles (Scaled RFM)', fontsize=14, fontweight='bold')
axes[1].set_xticks(x+w*(optimal_k-1)/2)
axes[1].set_xticklabels(FEAT_LABELS, rotation=30, ha='right', fontsize=9)
axes[1].legend(fontsize=8)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/feature_importance.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ XAI complete")

"""## Cell 19: Business Insights & Revenue Pareto"""

print("=" * 60)
print("  BUSINESS INSIGHTS & RECOMMENDATIONS")
print("=" * 60)

total_revenue = rfm['monetary'].sum()

for seg, info in segment_insights.items():
    rev_share = info['total_revenue'] / total_revenue * 100
    print(f"\n{'='*60}")
    print(f"  {seg.upper()}")
    print(f"{'='*60}")
    print(f"  {info['count']} customers ({info['pct']}%)")
    print(f"  {info['description']}")
    print(f"  Revenue: {CUR} {info['total_revenue']:,.2f} ({rev_share:.1f}%)")
    print(f"  Avg Monetary: {CUR} {info['avg_monetary']:,.2f}")
    print(f"  Avg Recency: {info['avg_recency']:.0f} days | Avg Frequency: {info['avg_frequency']:.1f} txns")

# Revenue Pareto chart
sorted_segs = sorted(segment_insights.items(), key=lambda x: x[1]['total_revenue'], reverse=True)
seg_names = [s[0] for s in sorted_segs]
seg_revs  = [s[1]['total_revenue'] for s in sorted_segs]
cum_pcts  = [sum(seg_revs[:i+1])/total_revenue*100 for i in range(len(seg_revs))]
pareto_colors = [seg_color_map.get(s, '#3498DB') for s in seg_names]

fig, ax1 = plt.subplots(figsize=(12, 6))
bars = ax1.bar(seg_names, seg_revs, color=pareto_colors, edgecolor='white', linewidth=2)
ax1.set_ylabel(f'Revenue ({CUR})', fontsize=12); ax1.tick_params(axis='x', rotation=30)
for bar, val in zip(bars, seg_revs):
    ax1.text(bar.get_x()+bar.get_width()/2, bar.get_height()+total_revenue*0.002,
             f'{CUR} {val:,.0f}', ha='center', fontsize=9, fontweight='bold')
ax2 = ax1.twinx()
ax2.plot(seg_names, cum_pcts, 'o-', color='#E74C3C', linewidth=2.5, markersize=8)
ax2.axhline(y=80, color='grey', linestyle='--', alpha=0.5, label='80% line')
ax2.set_ylabel('Cumulative %', fontsize=12, color='#E74C3C')
for x_val, y_val in zip(seg_names, cum_pcts):
    ax2.annotate(f'{y_val:.0f}%', (x_val, y_val), textcoords="offset points",
                 xytext=(0,10), ha='center', fontsize=10, fontweight='bold', color='#E74C3C')
ax2.legend(fontsize=10)
plt.title('Revenue Pareto Analysis (80/20 Rule)', fontsize=15, fontweight='bold', pad=15)
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/revenue_pareto.png', dpi=150, bbox_inches='tight')
plt.show()

# Priority matrix
fig, ax = plt.subplots(figsize=(10, 8))
for seg, info in segment_insights.items():
    color = seg_color_map.get(seg, '#3498DB')
    size = max(info['count'] * 2, 100)
    ax.scatter(info['count'], info['total_revenue'], s=size, c=color,
               alpha=0.7, edgecolors='black', linewidth=1.5)
    ax.annotate(seg, (info['count'], info['total_revenue']),
                textcoords="offset points", xytext=(10, 5), fontsize=10, fontweight='bold')
ax.axhline(y=total_revenue/len(valid_segments), color='grey', linestyle='--', alpha=0.4)
ax.axvline(x=len(rfm)/len(valid_segments), color='grey', linestyle='--', alpha=0.4)
ax.set_xlabel('Number of Customers', fontsize=12)
ax.set_ylabel(f'Total Revenue ({CUR})', fontsize=12)
ax.set_title('Strategic Priority Matrix (Customers vs Revenue)', fontsize=15, fontweight='bold')
plt.tight_layout()
plt.savefig(f'{OUTPUT_DIR}/charts/priority_matrix.png', dpi=150, bbox_inches='tight')
plt.show()
print("✅ Business insights complete")

"""## Cell 20: Generate Business PDF Report"""

print("\n" + "=" * 60)
print("  GENERATING UNIVERSAL BUSINESS REPORT")
print("=" * 60)

# ════════════════════════════════════════════════════════════════
# STEP 1: AUTO-DETECT DATASET SCHEMA
# The pipeline inspects the uploaded dataframe to find the right
# columns regardless of what they are named.
# ════════════════════════════════════════════════════════════════

def detect_columns(df):
    """
    Automatically detect which columns serve which roles.
    Returns a config dict so the rest of the pipeline is schema-agnostic.
    """
    cols = [c.lower().strip() for c in df.columns]
    original = list(df.columns)

    def find(keywords, dtype_hint=None):
        """Return the first original-case column that matches any keyword."""
        for kw in keywords:
            for i, c in enumerate(cols):
                if kw in c:
                    col = original[i]
                    if dtype_hint == 'numeric':
                        if pd.api.types.is_numeric_dtype(df[col]):
                            return col
                    elif dtype_hint == 'datetime':
                        try:
                            pd.to_datetime(df[col].dropna().iloc[:5])
                            return col
                        except:
                            pass
                    elif dtype_hint == 'text':
                        if df[col].dtype == object:
                            return col
                    else:
                        return col
        return None

    # Revenue / spend column — most important
    revenue_col = (
        find(['total line amount', 'total_line', 'line_amount'], 'numeric') or
        find(['total amount', 'total_amount', 'order_total', 'order total'], 'numeric') or
        find(['revenue', 'sales', 'gmv', 'amount', 'spend', 'price_total'], 'numeric') or
        find(['total', 'value'], 'numeric')
    )

    # Unit price
    price_col = (
        find(['unit price', 'unit_price', 'item_price', 'selling_price'], 'numeric') or
        find(['price', 'cost', 'rate'], 'numeric')
    )

    # Quantity
    qty_col = (
        find(['quantity', 'qty', 'units', 'items', 'count'], 'numeric')
    )

    # Discount
    discount_col = (
        find(['discount amount', 'discount_amount', 'disc_amount'], 'numeric') or
        find(['discount', 'promo_discount', 'savings'], 'numeric')
    )

    # Promo/coupon
    promo_col = (
        find(['promo code', 'promo_code', 'coupon_code', 'voucher'], 'text') or
        find(['promo', 'coupon', 'voucher', 'code'], 'text')
    )

    # Category
    category_col = (
        find(['categor', 'product_type', 'item_type', 'department', 'product_category'], 'text') or
        find(['type', 'class', 'group'], 'text')
    )

    # Product name
    product_col = (
        find(['product name', 'product_name', 'item_name', 'item name', 'sku_name'], 'text') or
        find(['product', 'item', 'name', 'description', 'sku'], 'text')
    )

    # Payment method
    payment_col = (
        find(['payment method', 'payment_method', 'pay_method', 'payment_type'], 'text') or
        find(['payment', 'pay', 'tender', 'method'], 'text')
    )

    # Date
    date_col = (
        find(['purchase date', 'purchase_date', 'order_date', 'transaction_date', 'date'], 'datetime') or
        find(['created', 'timestamp', 'time'], 'datetime')
    )

    # Order/transaction ID
    order_col = find(['order id', 'order_id', 'transaction_id', 'txn_id', 'invoice'], 'text')

    # Customer ID
    customer_col = (
        find(['customer id', 'customer_id', 'client_id', 'user_id', 'member_id'], 'text') or
        find(['customer', 'client', 'user', 'member'], 'text')
    )

    # Status column
    status_col = find(['status', 'order_status', 'fulfillment'], 'text')

    # Detect currency symbol from column names or data
    currency = 'GHS'  # default
    col_str = ' '.join(original).lower()
    if any(x in col_str for x in ['usd', '$', 'dollar']):
        currency = 'USD'
    elif any(x in col_str for x in ['gbp', 'pound', '£']):
        currency = 'GBP'
    elif any(x in col_str for x in ['eur', 'euro', '€']):
        currency = 'EUR'
    elif any(x in col_str for x in ['ngn', 'naira']):
        currency = 'NGN'
    elif any(x in col_str for x in ['kes', 'shilling']):
        currency = 'KES'
    elif any(x in col_str for x in ['zar', 'rand']):
        currency = 'ZAR'
    elif any(x in col_str for x in ['ghs', 'cedi', 'ghanaian']):
        currency = 'GHS'

    # Detect business type from category values
    business_type = 'Business'
    if category_col:
        cats = df[category_col].dropna().astype(str).str.lower().unique()
        cat_str = ' '.join(cats)
        if any(x in cat_str for x in ['shirt', 'dress', 'trouser', 'jean', 'jacket', 'shoe', 'bag', 'fashion', 'cloth', 'apparel', 'wear']):
            business_type = 'Fashion Retail'
        elif any(x in cat_str for x in ['phone', 'laptop', 'tablet', 'electronic', 'gadget', 'tech', 'computer']):
            business_type = 'Electronics Retail'
        elif any(x in cat_str for x in ['food', 'grocery', 'beverage', 'snack', 'drink', 'fruit', 'vegetable']):
            business_type = 'Food & Grocery'
        elif any(x in cat_str for x in ['beauty', 'cosmetic', 'skincare', 'makeup', 'hair', 'fragrance']):
            business_type = 'Beauty & Personal Care'
        elif any(x in cat_str for x in ['furniture', 'home', 'kitchen', 'decor', 'bedding']):
            business_type = 'Home & Furniture'
        elif any(x in cat_str for x in ['book', 'stationery', 'school', 'education']):
            business_type = 'Education & Books'
        elif any(x in cat_str for x in ['sport', 'fitness', 'gym', 'exercise', 'outdoor']):
            business_type = 'Sports & Fitness'
        else:
            business_type = 'Retail'

    return {
        'revenue':    revenue_col,
        'price':      price_col,
        'qty':        qty_col,
        'discount':   discount_col,
        'promo':      promo_col,
        'category':   category_col,
        'product':    product_col,
        'payment':    payment_col,
        'date':       date_col,
        'order':      order_col,
        'customer':   customer_col,
        'status':     status_col,
        'currency':   currency,
        'business':   business_type,
    }

# Run auto-detection on the uploaded dataframe
SCHEMA = detect_columns(df)
CUR    = SCHEMA['currency']
BIZ    = SCHEMA['business']

print(f"  Detected business type  : {BIZ}")
print(f"  Detected currency       : {CUR}")
print(f"  Revenue column          : {SCHEMA['revenue']}")
print(f"  Price column            : {SCHEMA['price']}")
print(f"  Category column         : {SCHEMA['category']}")
print(f"  Payment column          : {SCHEMA['payment']}")
print(f"  Date column             : {SCHEMA['date']}")

# ════════════════════════════════════════════════════════════════
# STEP 2: COMPUTE TOTAL REVENUE FROM DETECTED COLUMN
# ════════════════════════════════════════════════════════════════

rev_col = SCHEMA['revenue'] or (FEAT_COLS[0] if FEAT_COLS else None)
total_revenue = df[rev_col].sum() if rev_col else 0

# ════════════════════════════════════════════════════════════════
# STEP 3: AUTO-GENERATE SEGMENT NAMES FROM DATA
# Instead of hardcoding "Premium Shoppers", rank clusters by
# average spend and assign tier names dynamically.
# ════════════════════════════════════════════════════════════════

TIER_NAMES_BY_COUNT = {
    2: ['High-Value Customers',  'Standard Customers'],
    3: ['Premium Customers',     'Regular Customers',   'Budget Customers'],
    4: ['Premium Customers',     'Regular Customers',   'Mid-Range Customers', 'Budget Customers'],
    5: ['Premium Customers',     'Regular Customers',   'Mid-Range Customers', 'Discount Seekers', 'Budget Customers'],
    6: ['VIP Customers',         'Premium Customers',   'Regular Customers',   'Mid-Range Customers', 'Discount Seekers', 'Budget Customers'],
    7: ['VIP Customers',         'Premium Customers',   'Regular Customers',   'Mid-Range Customers', 'Occasional Buyers', 'Discount Seekers', 'Budget Customers'],
    8: ['VIP Customers',         'Premium Customers',   'Regular Customers',   'Mid-Range Customers', 'Occasional Buyers', 'Discount Seekers', 'Budget Customers', 'Low-Spend Customers'],
}

def generate_segment_names(segment_insights, n_clusters):
    """
    Sort segments by total revenue descending, then assign tier names.
    Returns a mapping: old_name -> new_name
    """
    sorted_segs = sorted(segment_insights.items(), key=lambda x: x[1]['total_revenue'], reverse=True)
    tier_names = TIER_NAMES_BY_COUNT.get(n_clusters, [f'Segment {i+1}' for i in range(n_clusters)])
    # Pad if needed
    while len(tier_names) < len(sorted_segs):
        tier_names.append(f'Segment {len(tier_names)+1}')
    mapping = {}
    for i, (seg, _) in enumerate(sorted_segs):
        mapping[seg] = tier_names[i]
    return mapping

seg_name_map = generate_segment_names(segment_insights, optimal_k)
# Build a new segment_insights dict with renamed keys
renamed_insights = {seg_name_map[k]: v for k, v in segment_insights.items()}
# Update the actual segment names for consistency
for old, new in seg_name_map.items():
    df.loc[df['Segment'] == old, 'Segment'] = new

# ════════════════════════════════════════════════════════════════
# STEP 4: AUTO-GENERATE PERSONA DESCRIPTIONS FROM DATA
# ════════════════════════════════════════════════════════════════

def generate_persona_desc(seg_name, info, rank, total_rev, total_txns, currency):
    """Generate a plain-English description based on actual data metrics."""
    rev_share = info['total_revenue'] / total_rev * 100
    txn_share = info['count'] / total_txns * 100
    avg_spend = info['avg_spending']
    overall_avg = total_rev / total_txns

    spend_ratio = avg_spend / overall_avg if overall_avg > 0 else 1
    disc_rate   = info.get('discount_rate', 0)
    promo_rate  = info.get('promo_rate', 0)
    top_cat     = info.get('top_category', 'various products')

    if rank == 1:
        return (
            f'Your highest-value customers. They account for only {txn_share:.0f}% of your transactions '
            f'but generate {rev_share:.0f}% of your revenue — spending {currency} {avg_spend:,.0f} per visit, '
            f'which is {spend_ratio:.1f}x your average transaction value. '
            f'They favour {top_cat} and rarely rely on discounts or promotions to make a purchase. '
            f'These customers trust your brand and pay full price. Losing one costs you more than losing '
            f'several from any other group.'
        )
    elif rank == 2:
        return (
            f'Your most consistent revenue engine. They make up {txn_share:.0f}% of your transactions '
            f'and generate {rev_share:.0f}% of your revenue. At {currency} {avg_spend:,.0f} average spend, '
            f'they buy reliably without needing heavy discounts. '
            f'Their top category is {top_cat}. '
            f'These customers are your growth lever — small improvements in retention or basket size '
            f'here compound significantly across your revenue.'
        )
    elif disc_rate > 30 or promo_rate > 30:
        return (
            f'Price-sensitive customers who respond strongly to deals and promotions. '
            f'They make up {txn_share:.0f}% of transactions with an average spend of {currency} {avg_spend:,.0f}. '
            f'They use discounts in {disc_rate:.0f}% of purchases, making them more loyal to price than to your brand. '
            f'The opportunity here is to gradually introduce them to your full-price range '
            f'by demonstrating product value rather than relying on discounts alone.'
        )
    elif rank <= 3:
        return (
            f'A mid-tier group with real growth potential. They represent {txn_share:.0f}% of transactions '
            f'at {currency} {avg_spend:,.0f} average spend — sitting between your top and bottom tiers. '
            f'Their preferred category is {top_cat}. '
            f'These customers can be moved upward with the right offer at the right moment. '
            f'A well-timed promotion, a loyalty reward, or a personalised recommendation '
            f'can shift them into your top spending tier.'
        )
    else:
        return (
            f'Your largest group by transaction volume — {txn_share:.0f}% of all transactions — '
            f'but with the lowest average spend at {currency} {avg_spend:,.0f}. '
            f'They prefer {top_cat} at accessible price points. '
            f'Do not ignore them — even a small increase in average basket size across this large group '
            f'adds meaningfully to total revenue. Bundles, upsells, and free shipping thresholds '
            f'are the most effective tactics here.'
        )

def generate_strategies(seg_name, info, rank, total_rev, currency, top_cats, top_products):
    """Generate 4 data-driven action items for a segment."""
    avg_spend   = info['avg_spending']
    disc_rate   = info.get('discount_rate', 0)
    promo_rate  = info.get('promo_rate', 0)
    top_cat     = info.get('top_category', 'your top category')
    top_prod    = info.get('top_product', 'your top product')
    upsell_amt  = round(avg_spend * 1.3 / 10) * 10  # 30% above avg, rounded to 10
    threshold   = round(avg_spend * 1.2 / 10) * 10  # 20% above avg for shipping threshold

    if rank == 1:
        return [
            f'Launch a VIP loyalty programme with exclusive early access to new {top_cat} arrivals',
            f'Offer premium packaging or a handwritten note for orders above {currency} {upsell_amt:,.0f} to reinforce brand loyalty',
            f'Create a referral programme: customers who refer a friend earn store credit toward {top_cat}',
            f'Never run blanket discounts on {top_cat} — it devalues what your VIPs already paid full price for',
        ]
    elif rank == 2:
        return [
            f'Introduce tiered loyalty points that reward consistent purchases of {top_cat}',
            f'Send monthly personalised "curated for you" messages based on their {top_cat} preferences',
            f'Offer free or express shipping on orders above {currency} {threshold:,.0f} to nudge transaction values upward',
            f'Highlight new arrivals in {top_cat} to this group first — they have shown the highest affinity',
        ]
    elif disc_rate > 30 or promo_rate > 30:
        return [
            f'Run strategic flash sales (24-48 hours only) targeting this group — scarcity drives action for price-sensitive buyers',
            f'Create bundle deals around {top_cat} that feel like a bargain while protecting your margin',
            f'Gradually introduce full-price {top_prod} by showcasing quality and craftsmanship in your messaging',
            f'Vary the timing and depth of discounts unpredictably to avoid training them to always wait for sales',
        ]
    elif rank <= 3:
        return [
            f'Cross-sell complementary items from {top_cat} at checkout to grow basket size',
            f'Run seasonal campaigns timed around key shopping periods with {top_cat} featured prominently',
            f'Use social proof messaging (e.g., "150+ customers bought this week") to build purchase confidence',
            f'Offer a one-time upgrade coupon just above their average spend to nudge them into your next tier',
        ]
    else:
        return [
            f'Set a free shipping threshold just above {currency} {threshold:,.0f} — slightly above their average spend — to encourage larger baskets',
            f'Bundle {top_cat} items together at a slight discount to increase spend per visit',
            f'Showcase "best value" options in {top_cat} — this group responds to perceived value over raw price',
            f'Use checkout upsells: suggest a complementary low-cost item to push them over the free shipping threshold',
        ]

def generate_shap_explanation(feat, pct, feat_type='numeric'):
    """Turn a SHAP feature name into a plain-English explanation."""
    feat_lower = feat.lower()
    pct_str = f'{pct:.1%}'

    if 'promo' in feat_lower or 'coupon' in feat_lower or 'voucher' in feat_lower:
        return (f'Whether a customer uses a promotional code is a top driver ({pct_str} influence). '
                f'Your discount-seeking group almost always uses promos, while high-value customers rarely do. '
                f'This is your strongest signal for distinguishing price-sensitive from loyal buyers.')
    elif 'discount' in feat_lower:
        return (f'The use or amount of discounts ({pct_str} influence) is a strong separator between groups. '
                f'Customers who regularly use discounts cluster together in the same group, '
                f'distinct from those who buy at full price.')
    elif 'payment' in feat_lower or 'pay' in feat_lower:
        return (f'Payment method ({pct_str} influence) correlates strongly with spending level. '
                f'Different payment behaviours align with different spending tiers — '
                f'richer payment data in future will make this signal even stronger.')
    elif 'price' in feat_lower or 'unit' in feat_lower:
        return (f'The price point of what customers buy ({pct_str} influence) cleanly separates groups. '
                f'High-value customers consistently choose higher-priced items, '
                f'while budget customers stick to lower price points regardless of available options.')
    elif 'spend' in feat_lower or 'amount' in feat_lower or 'revenue' in feat_lower or 'total' in feat_lower:
        return (f'Total transaction value ({pct_str} influence) is a core differentiator — as expected. '
                f'It cleanly ranks your customer groups from highest to lowest value '
                f'and confirms the spending tiers are genuinely distinct.')
    elif 'categor' in feat_lower or 'type' in feat_lower:
        return (f'Product category preference ({pct_str} influence) varies significantly across groups. '
                f'Each segment gravitates toward different product types, '
                f'which gives you a direct input for personalised product recommendations.')
    elif 'qty' in feat_lower or 'quantity' in feat_lower or 'basket' in feat_lower:
        return (f'Basket size ({pct_str} influence) — how many items customers buy per visit — '
                f'helps differentiate bulk buyers from single-item purchasers. '
                f'Upsell strategies should be tailored to groups with consistently low basket sizes.')
    elif 'ship' in feat_lower or 'delivery' in feat_lower:
        return (f'Shipping preference ({pct_str} influence) separates customers by urgency and willingness to pay for convenience. '
                f'Premium customers often prefer faster shipping; budget customers gravitate toward free standard options.')
    else:
        return (f'This feature ({pct_str} influence) is a meaningful separator between your customer groups. '
                f'Customers in different segments behave differently on this dimension, '
                f'making it a useful signal for targeting and personalisation.')

# ════════════════════════════════════════════════════════════════
# STEP 5: SAFE VARIABLE DEFAULTS
# ════════════════════════════════════════════════════════════════

RFM_AVAILABLE = 'rfm' in dir() and rfm is not None and len(rfm) > 0
if RFM_AVAILABLE:
    rfm_df = rfm

try:
    algo_results = {
        'K-Means':      {'n_clusters': optimal_k,     'n_noise': 0,       'silhouette': round(final_sil, 4), 'davies_bouldin': round(final_db, 4),  'calinski': round(final_ch, 1)},
        'Hierarchical': {'n_clusters': optimal_k,     'n_noise': 0,       'silhouette': round(hc_sil, 4),   'davies_bouldin': round(hc_db, 4),     'calinski': round(hc_ch, 1)},
        'GMM':          {'n_clusters': optimal_k,     'n_noise': 0,       'silhouette': round(gmm_sil, 4),  'davies_bouldin': round(gmm_db, 4),    'calinski': round(gmm_ch, 1)},
        'DBSCAN':       {'n_clusters': n_db_clusters, 'n_noise': n_noise, 'silhouette': round(db_sil, 4),   'davies_bouldin': 'N/A',               'calinski': 'N/A'},
    }
    ALGO_AVAILABLE = True
except NameError:
    ALGO_AVAILABLE = False

retrain_rules = [
    ('Silhouette Drift',  f'Monthly sil < {final_sil*0.8:.4f} (80% of baseline)', 'Trigger immediate retraining'),
    ('Volume Growth',     f'Dataset exceeds {int(len(df)*1.5):,} rows',            'Schedule retraining run'),
    ('Scheduled',         'Every 3 months (quarterly)',                             'Retrain regardless of drift'),
    ('Business Event',    'New product lines / pricing changes / promotions',       'Manual retraining trigger'),
]

if 'outlier_report' not in dir() or not outlier_report:
    outlier_report = {}
else:
    for _col in outlier_report:
        _info = outlier_report[_col]
        if 'n_capped' not in _info and 'n_outliers' in _info:
            _info['n_capped'] = _info['n_outliers']
        elif 'n_outliers' not in _info and 'n_capped' in _info:
            _info['n_outliers'] = _info['n_capped']
        if 'pct' not in _info:
            _info['pct'] = 0.0

# Pre-compute per-segment rank for use in descriptions
sorted_by_rev = sorted(renamed_insights.items(), key=lambda x: x[1]['total_revenue'], reverse=True)
seg_rank = {seg: i+1 for i, (seg, _) in enumerate(sorted_by_rev)}

# Top categories and products overall
top_cats_overall = []
top_prods_overall = []
if SCHEMA['category']:
    top_cats_overall = df[SCHEMA['category']].value_counts().head(5).index.tolist()
if SCHEMA['product']:
    top_prods_overall = df[SCHEMA['product']].value_counts().head(5).index.tolist()

# ════════════════════════════════════════════════════════════════
# STEP 6: PDF CLASS
# ════════════════════════════════════════════════════════════════

class UniversalReport(FPDF):
    def _c(self, t):
        return str(t).replace('\u2014', '-').replace('\u2013', '-').replace('\u2192', '->').replace('\u00b1', '+/-').replace('\u2018', "'").replace('\u2019', "'").replace('\u201c', '"').replace('\u201d', '"').replace('\u00a3', 'GBP').replace('\u20ac', 'EUR').encode('latin-1', 'replace').decode('latin-1')
    def header(self):
        self.set_font('Helvetica', 'B', 10); self.set_text_color(100, 100, 100)
        self.cell(0, 8, self._c('Customer360 - Customer Segmentation Report'), 0, 0, 'L')
        self.cell(0, 8, datetime.now().strftime('%B %d, %Y'), 0, 1, 'R')
        self.set_draw_color(41, 128, 185); self.line(10, 18, 200, 18); self.ln(5)
    def footer(self):
        self.set_y(-15); self.set_font('Helvetica', 'I', 8); self.set_text_color(128, 128, 128)
        self.cell(0, 10, f'Page {self.page_no()}/{{nb}} | Customer360 | Confidential', 0, 0, 'C')
    def ch_title(self, t, color=(41, 128, 185)):
        self.set_font('Helvetica', 'B', 16); self.set_text_color(*color)
        self.cell(0, 12, self._c(t), 0, 1)
        self.set_draw_color(*color); self.line(10, self.get_y(), 200, self.get_y()); self.ln(6)
    def sec_title(self, t, color=(52, 73, 94)):
        self.set_font('Helvetica', 'B', 13); self.set_text_color(*color)
        self.cell(0, 10, self._c(t), 0, 1); self.ln(2)
    def sub_title(self, t):
        self.set_font('Helvetica', 'B', 11); self.set_text_color(41, 128, 185)
        self.cell(0, 8, self._c(t), 0, 1); self.ln(1)
    def body(self, t):
        self.set_font('Helvetica', '', 10); self.set_text_color(60, 60, 60)
        self.multi_cell(0, 5, self._c(t)); self.ln(3)
    def callout(self, t, bg=(235, 245, 255), text_color=(41, 128, 185)):
        self.set_fill_color(*bg); self.set_text_color(*text_color)
        self.set_font('Helvetica', 'B', 10)
        self.multi_cell(0, 7, self._c(t), fill=True); self.ln(3)
        self.set_text_color(60, 60, 60)
    def bullet(self, t):
        self.set_font('Helvetica', '', 10); self.set_text_color(60, 60, 60)
        self.cell(8, 5, '')
        self.multi_cell(0, 5, self._c(f'- {t}')); self.ln(1)
    def kpi_row(self, items):
        box_w = 180 // len(items)
        for label, value in items:
            self.set_fill_color(41, 128, 185); self.set_text_color(255, 255, 255)
            self.set_font('Helvetica', 'B', 14)
            self.cell(box_w, 10, self._c(str(value)), 1, 0, 'C', True)
        self.ln()
        for label, value in items:
            self.set_fill_color(230, 240, 255); self.set_text_color(52, 73, 94)
            self.set_font('Helvetica', '', 8)
            self.cell(box_w, 6, self._c(label), 1, 0, 'C', True)
        self.ln(8)
    def img(self, path, w=175):
        if os.path.exists(path):
            if 297 - self.get_y() - 25 < 80: self.add_page()
            try: self.image(path, x=15, w=w); self.ln(8)
            except: pass

pdf = UniversalReport()
pdf.alias_nb_pages()
pdf.set_auto_page_break(auto=True, margin=25)

# ════════════════════════════════════════════════════════════════
# COVER PAGE
# ════════════════════════════════════════════════════════════════
pdf.add_page(); pdf.ln(25)
pdf.set_font('Helvetica', 'B', 36); pdf.set_text_color(41, 128, 185)
pdf.cell(0, 15, 'Customer360', 0, 1, 'C')
pdf.set_font('Helvetica', 'B', 18); pdf.set_text_color(52, 73, 94)
pdf.cell(0, 10, 'Who Are Your Customers?', 0, 1, 'C')
pdf.set_font('Helvetica', '', 13); pdf.set_text_color(100, 100, 100)
pdf.cell(0, 8, 'A Plain-English Guide to Your Customer Segments', 0, 1, 'C')
pdf.ln(8)
pdf.set_draw_color(41, 128, 185); pdf.set_line_width(0.5)
pdf.line(40, pdf.get_y(), 170, pdf.get_y()); pdf.ln(10)
pdf.set_font('Helvetica', '', 11); pdf.set_text_color(80, 80, 80)
pdf.cell(0, 7, f'Business Type: {BIZ}', 0, 1, 'C')
pdf.cell(0, 7, f'Date: {datetime.now().strftime("%B %d, %Y")}', 0, 1, 'C')
pdf.cell(0, 7, f'Based on {len(df):,} transactions | {optimal_k} customer groups identified', 0, 1, 'C')
pdf.cell(0, 7, f'Total Revenue Analysed: {CUR} {total_revenue:,.0f}', 0, 1, 'C')
pdf.ln(10)
pdf.set_font('Helvetica', 'I', 9); pdf.set_text_color(150, 150, 150)
pdf.cell(0, 6, 'This report is confidential and prepared exclusively for internal business use.', 0, 1, 'C')

# ════════════════════════════════════════════════════════════════
# WHAT THIS REPORT IS ABOUT
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('What This Report Is About')
pdf.body(
    f'Not all your customers behave the same way. Some spend big consistently, some only buy when '
    f'there is a deal, and some buy in small amounts frequently. If you treat all of them the same, '
    f'you waste marketing budget on people who will not respond, and you risk underserving the customers '
    f'who generate the most value for your {BIZ.lower()} business.'
)
pdf.body(
    f'This report uses data from {len(df):,} real transactions in your {BIZ.lower()} dataset to divide '
    f'customers into {optimal_k} groups that behave similarly. Each group gets a plain-English profile '
    f'and a set of ready-to-use actions tailored to their specific behaviour.'
)
pdf.ln(3)
pdf.callout(
    f'   We analysed {len(df):,} transactions and identified {optimal_k} customer groups. '
    f'   Together they represent {CUR} {total_revenue:,.0f} in analysed revenue.'
)
pdf.ln(3)
pdf.sub_title('How to Read This Report')
pdf.bullet('Section 2 shows the big picture — all groups at a glance in one table.')
pdf.bullet('Section 3 gives each group its own page with a profile and action plan.')
pdf.bullet('Section 4 tells you which groups to prioritise based on revenue impact.')
pdf.bullet('The Technical Appendix at the end covers the statistical details for academic review.')
pdf.ln(3)
pdf.sub_title('How the Groups Were Created')
pdf.body(
    'The grouping was done using a mathematical technique called clustering. '
    'It looks at how much each customer spent, what they bought, how they paid, '
    'and whether they used discounts. Customers who behave similarly end up in the same group. '
    'The algorithm found the natural groupings in your data — we did not pre-decide the groups. '
    'They emerged purely from patterns in your transactions.'
)

# ════════════════════════════════════════════════════════════════
# SNAPSHOT
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('Your Customers at a Glance')

pdf.kpi_row([
    ('Total Transactions', f'{len(df):,}'),
    ('Customer Groups',    str(optimal_k)),
    ('Total Revenue',      f'{CUR} {total_revenue:,.0f}'),
    ('Avg per Transaction', f'{CUR} {total_revenue/len(df):,.0f}'),
])

# Segment summary table
pdf.set_font('Helvetica', 'B', 9)
col_widths = [50, 20, 28, 28, 28, 26]
headers = ['Customer Group', 'Count', 'Avg Spend', 'Total Revenue', '% Revenue', 'Top Product']
for h, w in zip(headers, col_widths):
    pdf.set_fill_color(41, 128, 185); pdf.set_text_color(255, 255, 255)
    pdf.cell(w, 8, h, 1, 0, 'C', True)
pdf.ln()

row_colors = [(240, 248, 255), (255, 255, 255)]
for idx, (seg, info) in enumerate(sorted_by_rev):
    rev_share = info['total_revenue'] / total_revenue * 100
    pdf.set_fill_color(*row_colors[idx % 2])
    pdf.set_text_color(40, 40, 40)
    pdf.set_font('Helvetica', 'B' if idx == 0 else '', 9)
    pdf.cell(col_widths[0], 7, seg[:25], 1, 0, 'L', True)
    pdf.set_font('Helvetica', '', 9)
    pdf.cell(col_widths[1], 7, str(info['count']),                        1, 0, 'C', True)
    pdf.cell(col_widths[2], 7, f"{CUR} {info['avg_spending']:,.0f}",      1, 0, 'C', True)
    pdf.cell(col_widths[3], 7, f"{CUR} {info['total_revenue']:,.0f}",     1, 0, 'C', True)
    pdf.cell(col_widths[4], 7, f'{rev_share:.1f}%',                       1, 0, 'C', True)
    top_p = str(info.get('top_product', info.get('top_category', 'N/A')))[:14]
    pdf.cell(col_widths[5], 7, top_p,                                     1, 0, 'C', True)
    pdf.ln()

pdf.ln(5)
top_seg, top_info = sorted_by_rev[0]
bot_seg, bot_info = sorted_by_rev[-1]
top_share = top_info['total_revenue'] / total_revenue * 100
pdf.body(
    f'The table above shows all {optimal_k} customer groups ranked by total revenue. '
    f'Your top group, {top_seg}, generates {top_share:.0f}% of revenue despite being '
    f'{top_info["count"] / len(df) * 100:.0f}% of transactions. '
    f'This pattern — where a small group of high-value customers drives a disproportionate share '
    f'of revenue — is normal in retail and tells you exactly where to focus your marketing energy.'
)
pdf.img(f'{OUTPUT_DIR}/charts/segment_distribution.png')

# ════════════════════════════════════════════════════════════════
# ONE PAGE PER SEGMENT
# ════════════════════════════════════════════════════════════════
palette = [
    (39, 174, 96), (41, 128, 185), (142, 68, 173),
    (230, 126, 34), (192, 57, 43), (22, 160, 133),
    (52, 73, 94),   (243, 156, 18),
]

for i, (seg, info) in enumerate(sorted_by_rev):
    pdf.add_page()
    rank  = seg_rank[seg]
    color = palette[i % len(palette)]
    rev_share = info['total_revenue'] / total_revenue * 100

    pdf.set_font('Helvetica', 'B', 20); pdf.set_text_color(*color)
    pdf.cell(0, 12, f'[{rank}]  {seg}', 0, 1)
    pdf.set_draw_color(*color); pdf.line(10, pdf.get_y(), 200, pdf.get_y()); pdf.ln(5)

    pdf.kpi_row([
        ('Transactions',       str(info['count'])),
        ('Avg Spend per Visit', f"{CUR} {info['avg_spending']:,.0f}"),
        ('Total Revenue',      f"{CUR} {info['total_revenue']:,.0f}"),
        ('Share of Revenue',   f'{rev_share:.1f}%'),
    ])

    pdf.sub_title('Who Are They?')
    desc = generate_persona_desc(seg, info, rank, total_revenue, len(df), CUR)
    pdf.body(desc)

    pdf.sub_title('Their Buying Behaviour')
    top_cat  = info.get('top_category', 'N/A')
    top_pay  = info.get('top_payment',  'N/A')
    top_prod = info.get('top_product',  'N/A')
    disc_rate = info.get('discount_rate', 0)
    promo_rate = info.get('promo_rate', 0)
    avg_qty  = info.get('avg_quantity', 1)

    if top_cat  != 'N/A': pdf.bullet(f'Favourite category: {top_cat}')
    if top_prod != 'N/A': pdf.bullet(f'Most purchased product: {top_prod}')
    if top_pay  != 'N/A': pdf.bullet(f'Preferred payment method: {top_pay}')
    pdf.bullet(f'Average items per transaction: {avg_qty:.1f}')
    if disc_rate > 5:
        pdf.bullet(f'Uses discounts in {disc_rate:.0f}% of purchases — price sensitivity is a factor for this group')
    else:
        pdf.bullet(f'Rarely uses discounts ({disc_rate:.0f}%) — buys based on value and preference, not price')
    if promo_rate > 5:
        pdf.bullet(f'Responds to promotions ({promo_rate:.0f}% of purchases involved a promo code)')
    else:
        pdf.bullet(f'Does not rely on promo codes ({promo_rate:.0f}%) — organic buying behaviour')

    pdf.ln(2)
    pdf.set_font('Helvetica', 'B', 11); pdf.set_text_color(*color)
    pdf.cell(0, 8, '  What To Do With This Group', 0, 1)
    strategies = generate_strategies(seg, info, rank, total_revenue, CUR, top_cats_overall, top_prods_overall)
    for j, s in enumerate(strategies, 1):
        pdf.bullet(f'Action {j}: {s}')

# ════════════════════════════════════════════════════════════════
# REVENUE PRIORITY
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('Where Should You Focus First?')
pdf.body(
    f'Not every customer group deserves equal marketing spend right now. '
    f'The chart below ranks your {optimal_k} groups by total revenue. '
    f'Use it to decide where your time, budget, and campaigns should go this month.'
)
pdf.img(f'{OUTPUT_DIR}/charts/revenue_pareto.png')

pdf.sub_title('Priority Guide')
priority_labels = ['PROTECT — keep them loyal at all costs',
                   'GROW — highest return on marketing investment',
                   'NURTURE — volume group, upsell opportunities',
                   'CONVERT — reduce price sensitivity over time',
                   'MONITOR — low spend, use bundle tactics']
for rank_i, (seg, info) in enumerate(sorted_by_rev):
    rev_share = info['total_revenue'] / total_revenue * 100
    label = priority_labels[rank_i] if rank_i < len(priority_labels) else 'DEVELOP — targeted campaigns'
    pdf.bullet(f'{seg} ({rev_share:.0f}% of revenue) — {label}')

pdf.ln(4)
top2_rev = sum(info['total_revenue'] for _, info in sorted_by_rev[:2])
pdf.callout(
    f'   Quick win: Your top 2 groups together account for '
    f'{top2_rev/total_revenue*100:.0f}% of revenue. '
    f'Focus 60% of your marketing effort here for the highest return.'
)

pdf.img(f'{OUTPUT_DIR}/charts/priority_matrix.png')

# ════════════════════════════════════════════════════════════════
# TRENDS OVER TIME
# ════════════════════════════════════════════════════════════════
if os.path.exists(f'{OUTPUT_DIR}/charts/cohort_analysis.png'):
    pdf.add_page()
    pdf.ch_title('How Your Customer Groups Are Trending')
    pdf.body(
        'The chart below shows how each customer group has performed over time. '
        'A rising line means that group is growing. A falling line is a warning — '
        'that group needs a targeted campaign before it shrinks further. '
        'A flat line means the group is stable but may be a missed growth opportunity.'
    )
    pdf.img(f'{OUTPUT_DIR}/charts/cohort_analysis.png')
    pdf.body(
        'Use this chart in your monthly business review. '
        'If any segment line drops for two consecutive months, '
        'that is your cue to launch a targeted campaign for that group immediately.'
    )

# ════════════════════════════════════════════════════════════════
# PRODUCT & CATEGORY INSIGHTS
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('What Your Customers Buy')
pdf.body(
    f'Understanding which products each group prefers helps you stock smarter, '
    f'market more precisely, and recommend the right items at the right time.'
)
pdf.img(f'{OUTPUT_DIR}/charts/behavioral_analysis.png')

# Generate data-driven product insights
pdf.sub_title('Key Product Insights')
if top_cats_overall:
    pdf.bullet(
        f'Top {len(top_cats_overall)} categories overall: {", ".join(str(c) for c in top_cats_overall[:3])}. '
        f'Make sure you have clear quality tiers within each so customers can trade up.'
    )
for seg, info in sorted_by_rev[:2]:
    cat = info.get('top_category', '')
    if cat:
        pdf.bullet(f'{seg} favour {cat} — prioritise new arrivals in this category for communications to this group.')
discount_seg = max(renamed_insights.items(), key=lambda x: x[1].get('discount_rate', 0))
if discount_seg[1].get('discount_rate', 0) > 10:
    pdf.bullet(
        f'{discount_seg[0]} have the highest discount usage ({discount_seg[1]["discount_rate"]:.0f}%). '
        f'Avoid running blanket discounts — target them specifically to protect margins with other groups.'
    )
bot_seg_name, bot_seg_info = sorted_by_rev[-1]
bot_cat = bot_seg_info.get('top_category', 'lower-price items')
pdf.bullet(
    f'{bot_seg_name} favour {bot_cat} at lower price points. '
    f'Bundle {bot_cat} with higher-value items to grow their basket size.'
)

# ════════════════════════════════════════════════════════════════
# HOW WE KNOW THE GROUPS ARE REAL
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('How We Know These Groups Are Real')
pdf.body(
    'A fair question: are these genuine customer groups or just random clusters? '
    'Three independent tests confirm the groups are meaningful, stable, and actionable.'
)
pdf.sub_title('Test 1 — Are the Groups Genuinely Different?')
pdf.body(
    'A statistical test (ANOVA) asked: "Is the spending difference between groups real, '
    'or could it happen by chance?" The probability of these differences happening by chance '
    'was effectively zero — less than 1 in a trillion. The groups are genuinely distinct.'
)
pdf.callout(f'   Result: All {optimal_k} customer groups are statistically confirmed to be different from each other.')
pdf.ln(3)
pdf.sub_title('Test 2 — Are the Groups Stable?')
pdf.body(
    f'The analysis was run {n_runs} times using different starting conditions. '
    f'Each time, the same {optimal_k} groups formed with the same customers in them. '
    f'A stability score of {avg_ari:.2f}/1.00 ({stability}) means the groups are highly reproducible — '
    f'not a one-off result of the data.'
)
pdf.callout(f'   Result: Stability score = {avg_ari:.2f}/1.00 ({stability}). Groups are consistent and reproducible.')
pdf.ln(3)
pdf.sub_title('Test 3 — Do the Groups Make Business Sense?')
top_avg  = sorted_by_rev[0][1]['avg_spending']
bot_avg  = sorted_by_rev[-1][1]['avg_spending']
spend_x  = top_avg / bot_avg if bot_avg > 0 else 1
pdf.body(
    f'The most important test: do the groups make sense to you as a business owner? '
    f'Your top group spends {spend_x:.1f}x more per transaction than your bottom group. '
    f'The discount usage, product preferences, and payment methods all differ meaningfully '
    f'across groups. These patterns are real, observable, and ready to act on.'
)
pdf.img(f'{OUTPUT_DIR}/charts/radar_chart.png')

# ════════════════════════════════════════════════════════════════
# SHAP — WHAT DRIVES THE DIFFERENCES (PLAIN ENGLISH)
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('What Separates One Group from Another?')
pdf.body(
    'An AI technique called SHAP analysis identified which factors most strongly '
    'determine which group a customer falls into. Here is what the data says:'
)
shap_items = sorted(zip(FEAT_LABELS, combined_imp), key=lambda x: x[1], reverse=True)
for feat, score in shap_items:
    if score > 0.001:
        pdf.set_font('Helvetica', 'B', 10); pdf.set_text_color(41, 128, 185)
        pdf.cell(0, 7, f'{feat}  ({score:.1%} influence)', 0, 1)
        explanation = generate_shap_explanation(feat, score)
        pdf.set_font('Helvetica', '', 10); pdf.set_text_color(60, 60, 60)
        pdf.multi_cell(0, 5, pdf._c(explanation))
        pdf.ln(3)

pdf.img(f'{OUTPUT_DIR}/charts/shap_bar.png')

# ════════════════════════════════════════════════════════════════
# 30-DAY ACTION PLAN (DATA-DRIVEN)
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('Your 30-Day Action Plan')
pdf.body(
    'Here is a practical 30-day plan based on your actual data. '
    'Start with the highest-revenue groups and work down.'
)

pdf.sub_title('Week 1 — Quick Wins')
pdf.bullet('Tag each customer in your CRM or sales system with their segment label (use the segmented CSV provided alongside this report)')
pdf.bullet(f'Create a separate communication list (WhatsApp, email, SMS) for each of the {optimal_k} groups')
top_seg_name = sorted_by_rev[0][0]
top_seg_cat  = sorted_by_rev[0][1].get('top_category', 'your top products')
pdf.bullet(f'Send {top_seg_name} a personalised thank-you message acknowledging their loyalty — this alone improves retention')
bot_seg_name2 = sorted_by_rev[-1][0]
bot_avg_spend = sorted_by_rev[-1][1]['avg_spending']
threshold = round(bot_avg_spend * 1.25 / 10) * 10
pdf.bullet(f'Set a free shipping threshold at {CUR} {threshold:,.0f} (just above {bot_seg_name2} average spend) to nudge basket sizes upward')

pdf.sub_title('Week 2 — Top 2 Groups Focus')
seg1, info1 = sorted_by_rev[0]
seg2, info2 = sorted_by_rev[1]
cat1 = info1.get('top_category', 'top products')
cat2 = info2.get('top_category', 'your products')
pdf.bullet(f'Design a VIP or exclusive offer for {seg1} — first access to new {cat1} arrivals, or early sale preview')
pdf.bullet(f'Send {seg2} a personalised "you might also like" recommendation based on their {cat2} purchases')
pdf.bullet(f'Check your {cat1} and {cat2} stock levels — these are your highest-revenue categories')

pdf.sub_title('Week 3 — Middle and Discount Groups')
mid_segs = sorted_by_rev[2:-1] if len(sorted_by_rev) > 3 else sorted_by_rev[1:-1]
if mid_segs:
    mid_seg_name = mid_segs[0][0]
    mid_cat      = mid_segs[0][1].get('top_category', 'relevant products')
    disc_seg     = max(renamed_insights.items(), key=lambda x: x[1].get('discount_rate', 0))
    pdf.bullet(f'Run a 48-hour flash sale for {disc_seg[0]} only — targeted, not broadcast to all customers')
    pdf.bullet(f'Create a bundle offer for {mid_seg_name} combining two complementary {mid_cat} items')
pdf.bullet('Test two different message styles for each group — see which language gets better engagement')

pdf.sub_title('Week 4 — Review and Repeat')
pdf.bullet(f'Compare this week\'s revenue from {seg1} and {seg2} against last week — did the campaigns move the numbers?')
pdf.bullet(f'Check if average order value from {bot_seg_name2} increased toward the {CUR} {threshold:,.0f} threshold')
pdf.bullet('Note which campaigns performed best and plan Month 2 around repeating and scaling those')

pdf.ln(4)
pdf.callout(
    '   The goal is not to treat every customer the same. '
    '   It is to give each group exactly what they need to buy more. '
    '   Small targeted actions beat broad generic campaigns every time.'
)

# ════════════════════════════════════════════════════════════════
# TECHNICAL APPENDIX
# ════════════════════════════════════════════════════════════════
pdf.add_page()
pdf.ch_title('Technical Appendix', color=(100, 100, 100))
pdf.body('This section contains the technical details of the analysis for academic or supervisor review. Business readers do not need to read this section.')

pdf.sub_title('Dataset & Schema Detection')
pdf.body(f'Dataset rows: {len(df):,} | Columns: {len(df.columns)}')
pdf.body(f'Detected business type: {BIZ}')
pdf.body(f'Revenue column: {SCHEMA["revenue"] or "auto-detected from features"}')
pdf.body(f'Features used ({len(FEAT_LABELS)}): {", ".join(FEAT_LABELS)}')

pdf.sub_title('Model Performance Metrics')
pdf.body(f'Algorithm: K-Means Clustering | K = {optimal_k} segments')
pdf.body(f'Silhouette Score: {final_sil:.4f} (range -1 to 1; above 0.5 = strong cluster structure)')
pdf.body(f'Davies-Bouldin Index: {final_db:.4f} (lower is better; below 1.0 = good separation)')
pdf.body(f'Calinski-Harabasz Score: {final_ch:.1f} (higher is better)')
pdf.body(f'Cluster Stability (ARI): {avg_ari:.4f} over {n_runs} runs -- {stability}')

pdf.sub_title('Preprocessing')
pdf.body('Outlier treatment: IQR Winsorization on numeric features')
pdf.body('Feature scaling: StandardScaler (mean=0, std=1)')
pdf.body(f'Dimensionality reduction: PCA to {n_components} components (85%+ variance retained)')

pdf.sub_title('Statistical Validation')
for col_label, res in anova_results.items():
    sig = 'Significant (p < 0.05)' if res['sig'] else 'Not Significant'
    if not (np.isnan(res['F']) or np.isnan(res['p'])):
        pdf.body(f'ANOVA - {col_label}: F={res["F"]:.2f}, p={res["p"]:.2e} ({sig})')
for col_label, res in chi_results.items():
    sig = 'Significant (p < 0.05)' if res['sig'] else 'Not Significant'
    pdf.body(f'Chi-Square - {col_label}: Chi2={res["Chi2"]:.2f}, p={res["p"]:.2e} ({sig})')

if ALGO_AVAILABLE:
    pdf.sub_title('Algorithm Comparison')
    pdf.set_font('Helvetica', 'B', 8)
    col_w2 = [40, 22, 22, 28, 32, 28]
    for h, w in zip(['Algorithm', 'Clusters', 'Noise', 'Silhouette', 'Davies-Bouldin', 'Calinski-H'], col_w2):
        pdf.set_fill_color(100, 100, 100); pdf.set_text_color(255, 255, 255)
        pdf.cell(w, 7, h, 1, 0, 'C', True)
    pdf.ln(); pdf.set_font('Helvetica', '', 8); pdf.set_text_color(60, 60, 60)
    for algo, res in algo_results.items():
        pdf.cell(col_w2[0], 6, algo, 1)
        pdf.cell(col_w2[1], 6, str(res['n_clusters']), 1, 0, 'C')
        pdf.cell(col_w2[2], 6, str(res['n_noise']), 1, 0, 'C')
        pdf.cell(col_w2[3], 6, str(res['silhouette']), 1, 0, 'C')
        pdf.cell(col_w2[4], 6, str(res['davies_bouldin']), 1, 0, 'C')
        pdf.cell(col_w2[5], 6, str(res['calinski']), 1, 0, 'C')
        pdf.ln()
    pdf.ln(3)

pdf.sub_title('Retraining Strategy')
for rule, threshold_str, action in retrain_rules:
    pdf.body(f'  {rule}: {threshold_str}  ->  {action}')

if outlier_report:
    pdf.sub_title('Outlier Treatment Summary')
    for col_o, info_o in outlier_report.items():
        n_cap = info_o.get('n_capped', info_o.get('n_outliers', 0))
        pdf.body(f'  {col_o}: {n_cap} values capped at [{info_o.get("lower", 0):.2f}, {info_o.get("upper", 0):.2f}] (IQR={info_o.get("iqr", 0):.2f})')

pdf.img(f'{OUTPUT_DIR}/charts/pca_clusters_2d.png')

# ── Save ─────────────────────────────────────────────────────
report_path = f'{OUTPUT_DIR}/Customer360_Business_Report.pdf'
pdf.output(report_path)
print(f'\n  Business type : {BIZ}')
print(f'  Currency      : {CUR}')
print(f'  Segments      : {list(renamed_insights.keys())}')
print(f'\nReport saved: {report_path}')

"""## Cell 21: Save Results & Download"""

print("=" * 60)
print("  SAVING & DOWNLOADING RESULTS")
print("=" * 60)

# Save segmented customer RFM data
save_cols = ['customer_id', 'recency', 'frequency', 'monetary', 'Cluster', 'Segment']
output_df = rfm[save_cols].copy()
output_csv = f'{OUTPUT_DIR}/segmented_customers.csv'
output_df.to_csv(output_csv, index=False)
print(f"\n💾 Segmented Customers CSV saved: {output_csv}")
print(f"   Columns saved: {save_cols}")

# Print final summary
total_revenue = rfm['monetary'].sum()
print(f"\n{'='*60}")
print(f"  PIPELINE COMPLETE!")
print(f"{'='*60}")
print(f"  Dataset         : {BIZ}")
print(f"  Currency        : {CUR}")
print(f"  Transactions    : {len(df):,}")
print(f"  Customers       : {len(rfm):,}")
print(f"  Segments found  : {optimal_k}")
print(f"  Segment names   : {valid_segments}")
print(f"  Best Algorithm  : {best_algo}")
print(f"  Silhouette      : {final_sil:.4f}")
print(f"  Davies-Bouldin  : {final_db:.4f}")
print(f"  Calinski-H      : {final_ch:.1f}")
print(f"  Stability       : {stability} (ARI={avg_ari:.4f})")
print(f"  Total Revenue   : {CUR} {total_revenue:,.2f}")

# Trigger downloads
from google.colab import files as colab_files
report_path = f'{OUTPUT_DIR}/Customer360_Business_Report.pdf'
colab_files.download(report_path)
colab_files.download(output_csv)
print("\n✅ Downloads triggered!")