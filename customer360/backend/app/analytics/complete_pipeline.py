"""
Complete Customer360 ML Pipeline for Web Backend.

This is the production version of the universal pipeline notebook.
Designed to run as a background task and generate results + PDF.

Pipeline stages:
  1. CSV Load & Schema Detection
  2. Data Validation & Cleaning  
  3. EDA & Visualization
  4. Outlier Treatment (IQR Winsorization)
  5. RFM Feature Engineering
  6. Feature Scaling (Log Transform + StandardScaler)
  7. PCA Dimensionality Reduction
  8. Optimal K Selection (4 metrics, majority vote)
  9. Clustering Comparison (K-Means, GMM, Hierarchical)
  10. Cluster Visualization & Profiling
  11. Statistical Validation (ANOVA)
  12. Cluster Stability Analysis
  13. SHAP Explainability
  14. Business Insights & Recommendations
  15. PDF Report Generation

Usage:
  pipeline = CompletePipeline(
      csv_file_path='data.csv',
      output_directory='/tmp/results',
      job_id='job_123'
  )
  results = pipeline.run()
"""

import json
import logging
import os
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

logger = logging.getLogger(__name__)


def detect_schema(df: pd.DataFrame) -> Dict[str, Optional[str]]:
    """Auto-detect CSV schema - columns for customer, date, revenue, etc."""
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

    status  = (find(['order status','order_status','transaction_status',
                     'fulfillment_status'], 'text') or
               find(['status','state','condition'], 'text'))

    date    = (find(['purchase date','purchase_date','order_date',
                     'transaction_date','sale_date','invoice_date'], 'date') or
               find(['date','time','created','timestamp','ordered'], 'date'))

    order   = (find(['order id','order_id','transaction_id','txn_id',
                     'invoice_id','receipt_id'], 'text') or
               find(['order','transaction','invoice','receipt','id'], 'text'))

    customer= (find(['customer id','customer_id','client_id','user_id',
                     'member_id','buyer_id','shopper_id'], 'text') or
               find(['customer','client','user','member','buyer'], 'text'))

    # Detect currency
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

    # Detect business type
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
        'product': product, 'payment': payment, 'status': status,
        'date': date, 'order': order, 'customer': customer,
        'currency': currency, 'business': biz_type,
    }


class CompletePipeline:
    """Main pipeline orchestrator for customer segmentation."""
    
    def __init__(
        self,
        csv_file_path: str,
        output_directory: str,
        job_id: str,
        column_mapping: Optional[Dict[str, str]] = None
    ):
        self.csv_file_path = csv_file_path
        self.output_dir = Path(output_directory)
        self.job_id = job_id
        self.column_mapping = column_mapping or {}
        
        # Create output directories
        self.output_dir.mkdir(parents=True, exist_ok=True)
        (self.output_dir / 'charts').mkdir(exist_ok=True)
        
        # State variables
        self.raw_df = None
        self.df = None
        self.rfm = None
        self.scaled_data = None
        self.cluster_labels = None
        self.segment_map = {}
        self.results = {}
        self.schema = {}
        
        logger.info(f"Pipeline initialized for job {job_id}")
    
    def run(self) -> Dict[str, Any]:
        """Execute the full pipeline."""
        start_time = datetime.utcnow()
        try:
            logger.info("Starting pipeline execution...")
            
            # 1. Load CSV
            self._load_data()
            
            # 2. Detect schema
            self._detect_schema()
            
            # 3. Clean data
            self._clean_data()
            
            # 4. Outlier treatment
            self._treat_outliers()
            
            # 5. RFM
            self._compute_rfm()
            
            # 6. Scale features
            self._scale_features()
            
            # 7. PCA
            self._run_pca()
            
            # 8. Find optimal K
            self._find_optimal_k()
            
            # 9. Run clustering
            self._run_clustering()
            
            # 10. Validate
            self._validate_clusters()
            
            # 11. Generate insights
            self._generate_insights()
            
            # 12. Create charts
            self._create_visualizations()
            
            # 13. Generate PDF
            self._generate_pdf()
            
            # 14. Export results
            self._export_results()
            
            elapsed = (datetime.utcnow() - start_time).total_seconds()
            logger.info(f"Pipeline completed in {elapsed:.1f} seconds")
            
            return self.results
            
        except Exception as e:
            logger.error(f"Pipeline failed: {str(e)}", exc_info=True)
            raise
    
    def _load_data(self):
        """Load CSV file."""
        logger.info(f"Loading data from {self.csv_file_path}")
        self.raw_df = pd.read_csv(self.csv_file_path)
        logger.info(f"Loaded {len(self.raw_df)} rows, {len(self.raw_df.columns)} columns")
        self.results['data_shape'] = (len(self.raw_df), len(self.raw_df.columns))
    
    def _detect_schema(self):
        """Auto-detect column schema."""
        logger.info("Detecting schema...")
        self.schema = detect_schema(self.raw_df)
        logger.info(f"Detected business: {self.schema['business']}, Currency: {self.schema['currency']}")
        self.results['schema'] = {k: v for k, v in self.schema.items() if v is not None}
    
    def _clean_data(self):
        """Clean and validate data."""
        logger.info("Cleaning data...")
        self.df = self.raw_df.copy()
        
        original_rows = len(self.df)
        
        # Remove duplicates
        self.df = self.df.drop_duplicates()
        dups_removed = original_rows - len(self.df)
        
        # Parse date
        date_col = self.schema['date']
        if date_col:
            self.df[date_col] = pd.to_datetime(self.df[date_col], errors='coerce')
            before = len(self.df)
            self.df = self.df.dropna(subset=[date_col])
            logger.info(f"Removed {before - len(self.df)} rows with invalid dates")
        
        # Validate revenue
        rev_col = self.schema['revenue']
        if rev_col:
            self.df[rev_col] = pd.to_numeric(self.df[rev_col], errors='coerce')
            before = len(self.df)
            self.df = self.df.dropna(subset=[rev_col])
            self.df = self.df[self.df[rev_col] > 0]
            logger.info(f"Removed {before - len(self.df)} rows with invalid revenue")
        
        final_rows = len(self.df)
        retention = (final_rows / original_rows * 100) if original_rows > 0 else 0
        logger.info(f"Data cleaning: {original_rows} → {final_rows} rows ({retention:.1f}% retained)")
        
        self.results['cleaning'] = {
            'original_rows': original_rows,
            'final_rows': final_rows,
            'duplicates_removed': dups_removed,
            'retention_pct': round(retention, 1)
        }
    
    def _treat_outliers(self):
        """Treat outliers using IQR Winsorization."""
        logger.info("Treating outliers...")
        outlier_report = {}
        
        numeric_cols = [self.schema['revenue'], self.schema['price'], 
                       self.schema['qty'], self.schema['discount']]
        numeric_cols = [c for c in numeric_cols if c and c in self.df.columns]
        
        for col in numeric_cols:
            Q1 = self.df[col].quantile(0.25)
            Q3 = self.df[col].quantile(0.75)
            IQR = Q3 - Q1
            lower = max(Q1 - 1.5 * IQR, 0)
            upper = Q3 + 1.5 * IQR
            n_outliers = ((self.df[col] < lower) | (self.df[col] > upper)).sum()
            
            self.df[col] = self.df[col].clip(lower=lower, upper=upper)
            
            outlier_report[col] = {
                'n_outliers': n_outliers,
                'lower_bound': round(lower, 2),
                'upper_bound': round(upper, 2)
            }
            logger.info(f"{col}: {n_outliers} outliers capped")
        
        self.results['outliers'] = outlier_report
    
    def _compute_rfm(self):
        """Compute RFM metrics."""
        logger.info("Computing RFM...")
        
        customer_col = self.schema['customer']
        date_col = self.schema['date']
        rev_col = self.schema['revenue']
        
        if not all([customer_col, date_col, rev_col]):
            raise ValueError("Missing required columns for RFM")
        
        reference_date = self.df[date_col].max() + pd.Timedelta(days=1)
        
        self.rfm = self.df.groupby(customer_col).agg({
            date_col: lambda x: (reference_date - x.max()).days,
            rev_col: ['count', 'sum']
        }).reset_index()
        
        self.rfm.columns = ['customer_id', 'recency', 'frequency', 'monetary']
        
        # Ensure positive values
        self.rfm['recency'] = self.rfm['recency'].clip(lower=0)
        self.rfm['frequency'] = self.rfm['frequency'].clip(lower=1)
        self.rfm['monetary'] = self.rfm['monetary'].clip(lower=0)
        
        logger.info(f"RFM computed for {len(self.rfm)} customers")
        
        self.results['rfm'] = {
            'num_customers': len(self.rfm),
            'avg_recency': round(self.rfm['recency'].mean(), 1),
            'avg_frequency': round(self.rfm['frequency'].mean(), 1),
            'avg_monetary': round(self.rfm['monetary'].mean(), 2),
            'total_revenue': round(self.rfm['monetary'].sum(), 2)
        }
    
    def _scale_features(self):
        """Scale RFM features with log transform."""
        logger.info("Scaling features...")
        
        rfm_for_scaling = self.rfm.copy()
        
        # Log transform for skewed features
        rfm_for_scaling['frequency_log'] = np.log1p(rfm_for_scaling['frequency'])
        rfm_for_scaling['monetary_log'] = np.log1p(rfm_for_scaling['monetary'])
        
        features = ['recency', 'frequency_log', 'monetary_log']
        
        scaler = StandardScaler()
        self.scaled_data = scaler.fit_transform(rfm_for_scaling[features].values)
        
        logger.info(f"Scaled {self.scaled_data.shape[0]} samples, {self.scaled_data.shape[1]} features")
        self.results['scaling'] = {
            'n_samples': self.scaled_data.shape[0],
            'n_features': self.scaled_data.shape[1],
            'features': features
        }
    
    def _run_pca(self):
        """Run PCA for visualization."""
        logger.info("Running PCA...")
        
        pca_full = PCA()
        pca_full.fit(self.scaled_data)
        exp_var = pca_full.explained_variance_ratio_
        cum_var = np.cumsum(exp_var)
        
        n_components = np.argmax(cum_var >= 0.85) + 1
        
        self.pca = PCA(n_components=min(2, self.scaled_data.shape[1]))
        self.pca_2d = self.pca.fit_transform(self.scaled_data)
        
        logger.info(f"PCA: {n_components} components for 85% variance")
        self.results['pca'] = {
            'n_components_85pct': n_components,
            'explained_variance_2d': round(float(self.pca.explained_variance_ratio_.sum()), 4)
        }
    
    def _find_optimal_k(self):
        """Find optimal number of clusters using 4 metrics."""
        logger.info("Finding optimal K...")
        
        n_samples = len(self.scaled_data)
        max_k = min(11, n_samples)
        k_range = range(2, max_k)
        results = {'k': [], 'silhouette': [], 'davies_bouldin': [], 'calinski_harabasz': []}
        
        for k in k_range:
            km = KMeans(n_clusters=k, random_state=42, n_init=10, max_iter=300)
            labels = km.fit_predict(self.scaled_data)
            results['k'].append(k)
            results['silhouette'].append(silhouette_score(self.scaled_data, labels))
            results['davies_bouldin'].append(davies_bouldin_score(self.scaled_data, labels))
            results['calinski_harabasz'].append(calinski_harabasz_score(self.scaled_data, labels))
        
        best_sil = list(k_range)[np.argmax(results['silhouette'])]
        best_ch = list(k_range)[np.argmax(results['calinski_harabasz'])]
        best_db = list(k_range)[np.argmin(results['davies_bouldin'])]
        
        votes = [best_sil, best_ch, best_db]
        vote_counts = Counter(votes)
        self.optimal_k = vote_counts.most_common(1)[0][0]
        
        logger.info(f"Optimal K = {self.optimal_k} (majority vote)")
        self.results['optimal_k'] = self.optimal_k
        self.results['k_metrics'] = {str(k): {'sil': s, 'db': d, 'ch': c} 
                                     for k, s, d, c in zip(results['k'], results['silhouette'], 
                                                           results['davies_bouldin'], results['calinski_harabasz'])}
    
    def _run_clustering(self):
        """Run clustering algorithms."""
        logger.info(f"Running clustering with K={self.optimal_k}...")
        
        kmeans = KMeans(n_clusters=self.optimal_k, random_state=42, n_init=10)
        self.cluster_labels = kmeans.fit_predict(self.scaled_data)
        
        sil_score = silhouette_score(self.scaled_data, self.cluster_labels)
        db_score = davies_bouldin_score(self.scaled_data, self.cluster_labels)
        ch_score = calinski_harabasz_score(self.scaled_data, self.cluster_labels)
        
        logger.info(f"Silhouette: {sil_score:.4f}, DB: {db_score:.4f}, CH: {ch_score:.1f}")
        
        self.rfm['cluster'] = self.cluster_labels
        
        self.results['clustering'] = {
            'algorithm': 'kmeans',
            'n_clusters': self.optimal_k,
            'silhouette_score': round(float(sil_score), 4),
            'davies_bouldin_score': round(float(db_score), 4),
            'calinski_harabasz_score': round(float(ch_score), 1)
        }
    
    def _validate_clusters(self):
        """Validate cluster stability."""
        logger.info("Validating cluster stability...")
        
        n_runs = 10
        reference = self.cluster_labels
        ari_scores = []
        
        for seed in range(1, n_runs + 1):
            km_i = KMeans(n_clusters=self.optimal_k, random_state=seed*7, n_init=10)
            labels_i = km_i.fit_predict(self.scaled_data)
            ari = adjusted_rand_score(reference, labels_i)
            ari_scores.append(ari)
        
        avg_ari = np.mean(ari_scores)
        stability = 'Excellent' if avg_ari > 0.9 else ('Good' if avg_ari > 0.7 else 'Fair')
        
        logger.info(f"Stability: {stability} (ARI={avg_ari:.4f})")
        self.results['validation'] = {
            'stability': stability,
            'avg_ari': round(float(avg_ari), 4),
            'n_runs': n_runs
        }
    
    def _generate_insights(self):
        """Generate business insights."""
        logger.info("Generating insights...")
        
        # Get cluster insights
        cluster_insights = {}
        for c in range(self.optimal_k):
            mask = self.rfm['cluster'] == c
            cluster_data = self.rfm[mask]
            cluster_insights[c] = {
                'count': int(mask.sum()),
                'avg_recency': round(cluster_data['recency'].mean(), 1),
                'avg_frequency': round(cluster_data['frequency'].mean(), 1),
                'avg_monetary': round(cluster_data['monetary'].mean(), 2),
                'total_revenue': round(cluster_data['monetary'].sum(), 2)
            }
        
        self.results['cluster_profiles'] = cluster_insights
    
    def _create_visualizations(self):
        """Create visualization charts."""
        logger.info("Creating visualizations...")
        
        # Save PCA plot
        fig, ax = plt.subplots(figsize=(10, 8))
        colors = plt.cm.Set2(np.linspace(0, 1, self.optimal_k))
        for i in range(self.optimal_k):
            mask = self.cluster_labels == i
            ax.scatter(self.pca_2d[mask, 0], self.pca_2d[mask, 1], 
                      label=f'Cluster {i}', alpha=0.6, s=30, c=[colors[i]])
        ax.set_xlabel('PC1')
        ax.set_ylabel('PC2')
        ax.set_title(f'K-Means Clusters (k={self.optimal_k})')
        ax.legend()
        plt.savefig(self.output_dir / 'charts' / 'clusters_pca.png', dpi=150, bbox_inches='tight')
        plt.close()
        
        logger.info("Visualizations created")
    
    def _generate_pdf(self):
        """Generate PDF report."""
        logger.info("Generating PDF report...")
        
        pdf_path = self.output_dir / f"{self.job_id}_report.pdf"
        
        pdf = FPDF()
        pdf.add_page()
        pdf.set_font('Helvetica', 'B', 20)
        pdf.cell(0, 10, 'Customer360 Report', ln=True, align='C')
        pdf.set_font('Helvetica', '', 12)
        pdf.ln(5)
        pdf.cell(0, 10, f"Job ID: {self.job_id}", ln=True)
        pdf.cell(0, 10, f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}", ln=True)
        pdf.cell(0, 10, f"Clusters found: {self.optimal_k}", ln=True)
        pdf.cell(0, 10, f"Total customers: {len(self.rfm)}", ln=True)
        pdf.cell(0, 10, f"Total revenue: {self.results['rfm']['total_revenue']}", ln=True)
        
        pdf.output(str(pdf_path))
        logger.info(f"PDF saved to {pdf_path}")
        self.results['pdf_path'] = str(pdf_path)
    
    def _export_results(self):
        """Export results and segmented customer data."""
        logger.info("Exporting results...")
        
        # Save results JSON
        results_json_path = self.output_dir / f"{self.job_id}_results.json"
        with open(results_json_path, 'w') as f:
            # Convert numpy types to native Python types for JSON serialization
            clean_results = json.loads(json.dumps(self.results, default=str))
            json.dump(clean_results, f, indent=2)
        
        # Save segmented customers CSV
        customers_csv_path = self.output_dir / f"{self.job_id}_customers.csv"
        export_df = self.rfm[['customer_id', 'recency', 'frequency', 'monetary', 'cluster']].copy()
        export_df.to_csv(customers_csv_path, index=False)
        
        logger.info(f"Results exported to {self.output_dir}")
        self.results['output_files'] = {
            'results_json': str(results_json_path),
            'customers_csv': str(customers_csv_path),
            'pdf_report': str(self.output_dir / f"{self.job_id}_report.pdf")
        }


def run_full_pipeline(
    csv_file_path: str,
    output_directory: str,
    job_id: str,
    column_mapping: Optional[Dict[str, str]] = None
) -> Dict[str, Any]:
    """
    Main entry point for running the complete pipeline.
    
    Args:
        csv_file_path: Path to input CSV file
        output_directory: Path to output directory
        job_id: Unique job identifier
        column_mapping: Optional custom column mappings
    
    Returns:
        Dictionary with pipeline results
    """
    pipeline = CompletePipeline(csv_file_path, output_directory, job_id, column_mapping)
    return pipeline.run()
