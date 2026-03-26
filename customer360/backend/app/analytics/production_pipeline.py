# -*- coding: utf-8 -*-
"""
Customer360 Production Pipeline
Refactored from Jupyter notebook for FastAPI integration
"""

import os
import pandas as pd
import numpy as np
import matplotlib
matplotlib.use('Agg')  # Use non-interactive backend
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime
from pathlib import Path
import logging
import json
from typing import Dict, Tuple, List, Any

from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score, davies_bouldin_score, calinski_harabasz_score
from sklearn.ensemble import RandomForestClassifier
from collections import Counter
import shap
from fpdf import FPDF
import warnings

warnings.filterwarnings('ignore')

logger = logging.getLogger(__name__)

# ══════════════════════════════════════════════════════════════════════════════
# UTILITIES
# ══════════════════════════════════════════════════════════════════════════════

def detect_schema(df: pd.DataFrame) -> Dict[str, str]:
    """Auto-detect column roles regardless of naming."""
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

    revenue = (find(['total line amount','total_line','line_amount','total_amount',
                     'order_total','order total','sale_amount','sales_amount',
                     'transaction_amount','invoice_amount','net_amount'], 'numeric') or
               find(['total','revenue','sales','amount','spend','value','gmv'], 'numeric'))

    price = (find(['unit price','unit_price','item_price','selling_price',
                   'product_price','sale_price','retail_price'], 'numeric') or
             find(['price','cost','rate','fee'], 'numeric'))

    qty = (find(['quantity','qty','units','items_sold','item_count',
                 'num_items','units_sold'], 'numeric') or
           find(['count','number','volume'], 'numeric'))

    discount = (find(['discount amount','discount_amount','disc_amount',
                      'discount_value','savings_amount'], 'numeric') or
                find(['discount','markdown','reduction','saving'], 'numeric'))

    promo = (find(['promo code','promo_code','coupon_code','voucher_code',
                   'discount_code','offer_code'], 'text') or
             find(['promo','coupon','voucher','code','offer'], 'text'))

    category = (find(['categor','product_type','item_type','department',
                      'product_category','item_category','sub_category'], 'text') or
                find(['type','class','group','division','segment_cat'], 'text'))

    product = (find(['product name','product_name','item_name','item name',
                     'product_title','item_title','sku_name','product_desc'], 'text') or
               find(['product','item','name','title','description','sku'], 'text'))

    payment = (find(['payment method','payment_method','pay_method',
                     'payment_type','tender_type','pay_type'], 'text') or
               find(['payment','pay','tender','method','channel'], 'text'))

    shipping = (find(['shipping method','shipping_method','ship_method',
                      'delivery_method','fulfillment_method'], 'text') or
                find(['shipping','delivery','fulfillment','dispatch'], 'text'))

    status = (find(['order status','order_status','transaction_status',
                    'fulfillment_status'], 'text') or
              find(['status','state','condition'], 'text'))

    date = (find(['purchase date','purchase_date','order_date',
                  'transaction_date','sale_date','invoice_date'], 'date') or
            find(['date','time','created','timestamp','ordered'], 'date'))

    order = (find(['order id','order_id','transaction_id','txn_id',
                   'invoice_id','receipt_id'], 'text') or
             find(['order','transaction','invoice','receipt','id'], 'text'))

    customer = (find(['customer id','customer_id','client_id','user_id',
                      'member_id','buyer_id','shopper_id'], 'text') or
                find(['customer','client','user','member','buyer'], 'text'))

    # Detect currency
    currency = 'GHS'
    all_text = ' '.join(orig + [str(df.iloc[0].tolist())]).lower()
    for kw, cur in [('ghs','GHS'), ('usd','USD'), ('gbp','GBP'), ('eur','EUR'),
                    ('ngn','NGN'), ('kes','KES'), ('zar','ZAR')]:
        if kw in all_text:
            currency = cur
            break

    # Detect business type
    biz_type = 'Retail'
    if category:
        cats = ' '.join(df[category].dropna().astype(str).str.lower().unique()[:50])
        for keywords, btype in [
            (['shirt','dress','trouser','jean','jacket','shoe','bag','fashion'], 'Fashion Retail'),
            (['phone','laptop','tablet','electronic','gadget','tech'], 'Electronics Retail'),
            (['food','grocery','beverage','snack'], 'Food & Grocery'),
            (['beauty','cosmetic','skincare','makeup'], 'Beauty & Personal Care'),
        ]:
            if any(k in cats for k in keywords):
                biz_type = btype
                break

    return {
        'revenue': revenue, 'price': price, 'qty': qty, 'discount': discount,
        'promo': promo, 'category': category, 'product': product,
        'payment': payment, 'shipping': shipping, 'status': status,
        'date': date, 'order': order, 'customer': customer,
        'currency': currency, 'business': biz_type,
    }


def clean_data(df: pd.DataFrame, schema: Dict) -> Tuple[pd.DataFrame, Dict]:
    """Clean and validate data."""
    report = {'original_rows': len(df)}
    
    # Remove duplicates
    before = len(df)
    df = df.drop_duplicates()
    report['duplicates_removed'] = before - len(df)
    
    # Parse date
    if schema['date']:
        df[schema['date']] = pd.to_datetime(df[schema['date']], errors='coerce', utc=True)
        before = len(df)
        df = df.dropna(subset=[schema['date']])
        report['invalid_dates'] = before - len(df)
    
    # Validate revenue
    if schema['revenue']:
        df[schema['revenue']] = pd.to_numeric(df[schema['revenue']], errors='coerce')
        before = len(df)
        df = df.dropna(subset=[schema['revenue']])
        df = df[df[schema['revenue']] > 0]
        report['invalid_revenue'] = before - len(df)
    
    # Validate numeric columns
    for col_key in ['qty', 'price']:
        col = schema[col_key]
        if col:
            df[col] = pd.to_numeric(df[col], errors='coerce')
            before = len(df)
            df = df.dropna(subset=[col])
            df = df[df[col] >= 0]
    
    # Fill categorical nulls
    for col in [schema['category'], schema['payment'], schema['shipping'],
                schema['promo'], schema['discount']]:
        if col and col in df.columns:
            if df[col].dtype == 'object':
                df[col] = df[col].fillna('Unknown')
            else:
                df[col] = df[col].fillna(0)
    
    report['final_rows'] = len(df)
    return df, report


# ══════════════════════════════════════════════════════════════════════════════
# MAIN PIPELINE
# ══════════════════════════════════════════════════════════════════════════════

class ProductionPipeline:
    """Production-grade customer segmentation pipeline."""
    
    def __init__(self, output_dir: str = None):
        self.output_dir = Path(output_dir or '/tmp/customer360')
        self.output_dir.mkdir(parents=True, exist_ok=True)
        (self.output_dir / 'charts').mkdir(exist_ok=True)
        
        self.df = None
        self.schema = None
        self.features = None
        self.scaled_data = None
        self.kmeans = None
        self.cluster_labels = None
        self.results = {}
        
    def run(self, csv_path: str) -> Dict[str, Any]:
        """Execute full pipeline."""
        logger.info(f"Loading CSV: {csv_path}")
        self.df = pd.read_csv(csv_path)
        
        # Detect schema
        logger.info("Detecting schema...")
        self.schema = detect_schema(self.df)
        
        # Clean data
        logger.info("Cleaning data...")
        self.df, clean_report = clean_data(self.df, self.schema)
        logger.info(f"Cleaned: {clean_report['final_rows']} rows remain")
        
        # Feature engineering
        logger.info("Engineering features...")
        self._engineer_features()
        
        # Scaling
        logger.info("Scaling features...")
        self._scale_features()
        
        # Find optimal K
        logger.info("Finding optimal K...")
        optimal_k = self._find_optimal_k()
        
        # Clustering
        logger.info(f"Running K-Means with K={optimal_k}...")
        self._run_clustering(optimal_k)
        
        # Segment analysis
        logger.info("Analyzing segments...")
        segment_insights = self._analyze_segments()
        
        # Generate visualizations
        logger.info("Generating visualizations...")
        self._create_charts()
        
        # Generate report
        logger.info("Generating PDF report...")
        report_path = self._generate_pdf(segment_insights)
        
        # Save results
        logger.info("Saving results...")
        results_path = self._save_results(segment_insights)
        csv_path = self._save_csv()
        
        self.results = {
            'status': 'completed',
            'num_customers': len(self.df[self.schema['customer']].unique()) if self.schema['customer'] else len(self.df),
            'num_clusters': optimal_k,
            'num_transactions': len(self.df),
            'total_revenue': float(self.df[self.schema['revenue']].sum()) if self.schema['revenue'] else 0,
            'silhouette_score': float(silhouette_score(self.scaled_data, self.cluster_labels)),
            'davies_bouldin': float(davies_bouldin_score(self.scaled_data, self.cluster_labels)),
            'calinski_harabasz': float(calinski_harabasz_score(self.scaled_data, self.cluster_labels)),
            'report_path': str(report_path),
            'results_path': str(results_path),
            'csv_path': str(csv_path),
            'segment_insights': segment_insights,
        }
        
        return self.results
    
    def _engineer_features(self):
        """Create feature matrix."""
        feat_cols = []
        
        for col_key in ['revenue', 'price', 'qty', 'discount']:
            col = self.schema[col_key]
            if col and col in self.df.columns:
                self.df[col] = pd.to_numeric(self.df[col], errors='coerce').fillna(0)
                feat_cols.append(col)
        
        # Binary flags
        if self.schema['discount']:
            self.df['HasDiscount'] = (self.df[self.schema['discount']] > 0).astype(int)
            feat_cols.append('HasDiscount')
        
        if self.schema['promo']:
            promo_none = ['none', 'nan', '', 'null', 'no', '0', 'n/a']
            self.df['HasPromo'] = (~self.df[self.schema['promo']].astype(str).str.lower().isin(promo_none)).astype(int)
            feat_cols.append('HasPromo')
        
        # Encode categoricals
        for col_key in ['category', 'payment', 'shipping']:
            col = self.schema[col_key]
            if col and col in self.df.columns:
                enc_col = f'{col_key}_Enc'
                le = LabelEncoder()
                self.df[enc_col] = le.fit_transform(self.df[col].astype(str))
                feat_cols.append(enc_col)
        
        self.features = pd.DataFrame(self.df[feat_cols])
    
    def _scale_features(self):
        """Scale features to mean=0, std=1."""
        scaler = StandardScaler()
        self.scaled_data = scaler.fit_transform(self.features.values)
    
    def _find_optimal_k(self) -> int:
        """Find optimal number of clusters."""
        max_k = min(11, len(self.df))
        results = {'k': [], 'silhouette': [], 'davies_bouldin': [], 'calinski': []}
        
        for k in range(2, max_k):
            km = KMeans(n_clusters=k, random_state=42, n_init=10, max_iter=300)
            labels = km.fit_predict(self.scaled_data)
            results['k'].append(k)
            results['silhouette'].append(silhouette_score(self.scaled_data, labels))
            results['davies_bouldin'].append(davies_bouldin_score(self.scaled_data, labels))
            results['calinski'].append(calinski_harabasz_score(self.scaled_data, labels))
        
        # Voting
        best_sil = results['k'][np.argmax(results['silhouette'])]
        best_ch = results['k'][np.argmax(results['calinski'])]
        best_db = results['k'][np.argmin(results['davies_bouldin'])]
        
        votes = [best_sil, best_ch, best_db]
        optimal_k = Counter(votes).most_common(1)[0][0]
        
        return optimal_k
    
    def _run_clustering(self, k: int):
        """Run K-Means clustering."""
        self.kmeans = KMeans(n_clusters=k, random_state=42, n_init=10, max_iter=300)
        self.cluster_labels = self.kmeans.fit_predict(self.scaled_data)
        self.df['Cluster'] = self.cluster_labels
    
    def _analyze_segments(self) -> Dict:
        """Analyze each segment."""
        insights = {}
        rev_col = self.schema['revenue']
        
        for cluster_id in np.unique(self.cluster_labels):
            mask = self.cluster_labels == cluster_id
            segment_df = self.df[mask]
            
            insights[f'Cluster {cluster_id}'] = {
                'count': int(mask.sum()),
                'pct': float(mask.sum() / len(self.df) * 100),
                'avg_spending': float(segment_df[rev_col].mean()) if rev_col else 0,
                'total_revenue': float(segment_df[rev_col].sum()) if rev_col else 0,
                'top_category': segment_df[self.schema['category']].mode().values[0] if self.schema['category'] else 'N/A',
                'top_product': segment_df[self.schema['product']].mode().values[0] if self.schema['product'] else 'N/A',
            }
        
        return insights
    
    def _create_charts(self):
        """Generate visualization charts."""
        # Cluster PCA plot
        pca = PCA(n_components=2)
        pca_data = pca.fit_transform(self.scaled_data)
        
        fig, ax = plt.subplots(figsize=(10, 8))
        colors = plt.cm.Set2(np.linspace(0, 1, len(np.unique(self.cluster_labels))))
        for cluster_id, color in zip(np.unique(self.cluster_labels), colors):
            mask = self.cluster_labels == cluster_id
            ax.scatter(pca_data[mask, 0], pca_data[mask, 1], c=[color], 
                      label=f'Cluster {cluster_id}', alpha=0.6, s=30)
        ax.set_title('Clusters in PCA Space')
        ax.set_xlabel(f'PC1 ({pca.explained_variance_ratio_[0]:.1%})')
        ax.set_ylabel(f'PC2 ({pca.explained_variance_ratio_[1]:.1%})')
        ax.legend()
        plt.tight_layout()
        plt.savefig(self.output_dir / 'charts' / 'clusters_pca.png', dpi=150, bbox_inches='tight')
        plt.close()
        
        logger.info("Charts saved")
    
    def _generate_pdf(self, segment_insights: Dict) -> Path:
        """Generate PDF report."""
        pdf = FPDF()
        pdf.add_page()
        pdf.set_font("Helvetica", "B", 20)
        pdf.cell(0, 10, "Customer360 - Segmentation Report", 0, 1, "C")
        pdf.set_font("Helvetica", "", 12)
        pdf.cell(0, 10, f"Date: {datetime.now().strftime('%Y-%m-%d')}", 0, 1, "C")
        pdf.cell(0, 10, f"Business Type: {self.schema['business']}", 0, 1, "C")
        pdf.cell(0, 10, f"Currency: {self.schema['currency']}", 0, 1, "C")
        pdf.ln(10)
        
        # Summary table
        pdf.set_font("Helvetica", "B", 12)
        pdf.cell(0, 10, "Segment Summary", 0, 1)
        pdf.set_font("Helvetica", "", 10)
        
        for seg_name, info in segment_insights.items():
            pdf.cell(0, 8, 
                    f"{seg_name}: {info['count']} transactions, "
                    f"{self.schema['currency']} {info['total_revenue']:,.0f} revenue", 0, 1)
        
        pdf.ln(10)
        pdf.set_font("Helvetica", "B", 12)
        pdf.cell(0, 10, "Metrics", 0, 1)
        pdf.set_font("Helvetica", "", 10)
        
        sil = silhouette_score(self.scaled_data, self.cluster_labels)
        db = davies_bouldin_score(self.scaled_data, self.cluster_labels)
        ch = calinski_harabasz_score(self.scaled_data, self.cluster_labels)
        
        pdf.cell(0, 8, f"Silhouette Score: {sil:.4f}", 0, 1)
        pdf.cell(0, 8, f"Davies-Bouldin Index: {db:.4f}", 0, 1)
        pdf.cell(0, 8, f"Calinski-Harabasz Score: {ch:.1f}", 0, 1)
        
        # Add chart image if exists
        chart_path = self.output_dir / 'charts' / 'clusters_pca.png'
        if chart_path.exists():
            pdf.ln(10)
            try:
                pdf.image(str(chart_path), x=20, w=170)
            except Exception as e:
                logger.warning(f"Could not embed chart: {e}")
        
        report_path = self.output_dir / 'report.pdf'
        pdf.output(str(report_path))
        return report_path
    
    def _save_results(self, segment_insights: Dict) -> Path:
        """Save results as JSON."""
        results_path = self.output_dir / 'results.json'
        
        results_data = {
            'schema': self.schema,
            'segments': segment_insights,
            'metrics': {
                'silhouette': float(silhouette_score(self.scaled_data, self.cluster_labels)),
                'davies_bouldin': float(davies_bouldin_score(self.scaled_data, self.cluster_labels)),
                'calinski_harabasz': float(calinski_harabasz_score(self.scaled_data, self.cluster_labels)),
            }
        }
        
        with open(results_path, 'w') as f:
            json.dump(results_data, f, indent=2)
        
        return results_path
    
    def _save_csv(self) -> Path:
        """Save segmented data as CSV."""
        csv_path = self.output_dir / 'segmented_customers.csv'
        
        save_cols = ['Cluster']
        for col_key in ['customer', 'order', 'date', 'product', 'category',
                        'qty', 'price', 'revenue', 'discount']:
            col = self.schema.get(col_key)
            if col and col in self.df.columns:
                if col not in save_cols:
                    save_cols.insert(0, col)
        
        self.df[save_cols].to_csv(csv_path, index=False)
        return csv_path
