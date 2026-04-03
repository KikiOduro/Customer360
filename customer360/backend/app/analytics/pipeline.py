"""
Main analytics pipeline for Customer360.
Orchestrates the full preprocessing -> RFM -> clustering -> segmentation flow.
Includes outlier treatment, PCA, optimal-K selection, SHAP, and chart generation.
"""
import json
import os
import warnings
warnings.filterwarnings('ignore')

from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Dict, Optional
import pandas as pd
import numpy as np
import logging
import joblib

from .preprocessing import preprocess_transaction_data, get_csv_preview
from .rfm import compute_rfm, normalize_rfm, get_rfm_statistics, get_rfm_distributions
from .clustering import run_clustering, run_comparison
from .segmentation import SEGMENT_DEFINITIONS, analyze_clusters, get_cluster_sizes, get_segment_summary
from ..config import MODELS_DIR, OPTIMAL_K_SUBSAMPLE, SHAP_MAX_SAMPLES, CHART_DPI

logger = logging.getLogger(__name__)


# ── Optional heavy imports (fail gracefully if not installed) ──────────────────

def _try_import(module_name):
    try:
        import importlib
        return importlib.import_module(module_name)
    except ImportError:
        logger.warning(f"Optional dependency '{module_name}' not installed — skipping.")
        return None


class SegmentationPipeline:
    """
    Main pipeline class for customer segmentation analysis.
    Implements the full universal notebook flow:
      preprocessing → outlier treatment → RFM → normalise → PCA →
      optimal-K (majority vote) → K-Means → segmentation → SHAP → charts → PDF
    """

    def __init__(
        self,
        file_path: str,
        output_dir: str,
        job_id: str,
        column_mapping: Optional[Dict[str, str]] = None,
        clustering_method: str = 'kmeans',
        include_comparison: bool = False,
        progress_callback: Optional[Callable[[int, str, str], None]] = None
    ):
        self.file_path      = file_path
        self.output_dir     = Path(output_dir)
        self.job_id         = job_id
        self.column_mapping = column_mapping
        self.clustering_method   = clustering_method
        self.include_comparison  = include_comparison
        self.progress_callback = progress_callback

        self.output_dir.mkdir(parents=True, exist_ok=True)

        self.results  = {}
        self.df       = None
        self.rfm      = None
        self.labels   = None
        self.segments = None
        self.model_artifacts = self._load_model_artifacts()
        self.chosen_algorithm = clustering_method

    # ── Public entry point ─────────────────────────────────────────────────────

    def run(self) -> Dict[str, Any]:
        """Execute the full segmentation pipeline."""
        start_time = datetime.utcnow()
        logger.info(f"Starting segmentation pipeline for job {self.job_id}")

        try:
            # 1. Preprocess
            self._emit_progress(12, 'validating', 'Validating your file structure and preparing mapped columns.')
            logger.info("Step 1: Preprocessing data...")
            self.df, preprocessing_meta = preprocess_transaction_data(
                self.file_path, self.column_mapping
            )
            preprocessing_meta['business_type'] = self._infer_business_type(self.df)
            preprocessing_meta['eda'] = self._build_eda_summary(self.df, preprocessing_meta)
            self.results['preprocessing'] = preprocessing_meta

            # 2. Outlier treatment (IQR Winsorization on the amount column)
            self._emit_progress(22, 'cleaning', 'Cleaning transaction values and reducing the effect of outliers.')
            logger.info("Step 2: Outlier treatment...")
            self.df, outlier_meta = self._winsorise(self.df)
            self.results['outlier_treatment'] = outlier_meta

            # 3. RFM
            self._emit_progress(34, 'rfm', 'Computing recency, frequency, and monetary features for each customer.')
            logger.info("Step 3: Computing RFM metrics...")
            self.rfm = compute_rfm(self.df)
            self.results['rfm_statistics']   = get_rfm_statistics(self.rfm)
            self.results['rfm_distributions'] = get_rfm_distributions(self.rfm)

            # 4. Normalise
            self._emit_progress(46, 'normalizing', 'Scaling RFM features so customers can be compared fairly before clustering.')
            logger.info("Step 4: Normalising RFM features...")
            scaler_override = self._get_compatible_scaler()
            rfm_normalised, scaler = normalize_rfm(self.rfm, scaler_override=scaler_override)
            feature_cols = ['recency_normalized', 'frequency_normalized', 'monetary_normalized']
            X = rfm_normalised[feature_cols].values

            # 5. PCA (for visualisation only — clustering uses the 3 RFM features)
            self._emit_progress(54, 'projection', 'Projecting customer patterns into PCA space for chart generation.')
            logger.info("Step 5: PCA projection...")
            pca_result = self._run_pca(X)
            self.results['pca'] = pca_result

            # 6. Optimal K — majority vote across 4 metrics
            self._emit_progress(62, 'selecting_k', 'Evaluating the best number of customer segments for this dataset.')
            logger.info("Step 6: Finding optimal K...")
            optimal_k, k_meta = self._find_optimal_k_majority_vote(X)
            self.results['optimal_k'] = k_meta

            # 7. Clustering
            self._emit_progress(72, 'clustering', f'Running clustering models and comparing segment quality with k={optimal_k}.')
            logger.info(f"Step 7: Running {self.clustering_method} clustering (k={optimal_k})...")
            logger.info("Step 7b: Running clustering comparison...")
            comparison_results = run_comparison(X, n_clusters=optimal_k)
            self.results['comparison'] = comparison_results
            self.labels, clustering_info = self._select_clustering_result(
                X=X,
                optimal_k=optimal_k,
                comparison_results=comparison_results,
            )
            self.results['clustering'] = clustering_info

            # 8. Segmentation
            self._emit_progress(82, 'segmenting', 'Building segment profiles and generating recommended actions.')
            logger.info("Step 8: Analysing segments...")
            self.segments = analyze_clusters(self.rfm, self.labels)
            self._apply_artifact_segment_labels()
            self.results['segments']        = self.segments
            self.results['cluster_sizes']   = get_cluster_sizes(self.labels)
            self.results['segment_summary'] = get_segment_summary(self.segments)

            # 9. Customer-level output
            self._emit_progress(88, 'customer_output', 'Preparing customer-level output tables and recent customer snapshots.')
            logger.info("Step 9: Preparing customer output...")
            customer_output = self._prepare_customer_output(rfm_normalised)
            self.results['customer_table'] = self._build_customer_rows(customer_output)
            self.results['recent_customers'] = self._build_recent_customers(customer_output)

            # 10. SHAP explainability
            self._emit_progress(92, 'explainability', 'Estimating which RFM features most strongly drive segment assignment.')
            logger.info("Step 10: SHAP explainability...")
            shap_meta = self._run_shap(X, self.labels, feature_cols)
            self.results['shap'] = shap_meta

            # 11. Charts
            self._emit_progress(96, 'charts', 'Rendering segment charts and visual summaries.')
            logger.info("Step 11: Generating charts...")
            chart_paths = self._generate_charts(rfm_normalised, pca_result)
            self.results['charts'] = chart_paths

            end_time = datetime.utcnow()
            self.results['meta'] = {
                'job_id':            self.job_id,
                'status':            'completed',
                'start_time':        start_time.isoformat(),
                'end_time':          end_time.isoformat(),
                'duration_seconds':  (end_time - start_time).total_seconds(),
                'num_customers':     len(self.rfm),
                'num_transactions':  len(self.df),
                'total_revenue':     float(self.df['amount'].sum()),
                'num_clusters':      int(clustering_info['n_clusters']),
                'silhouette_score':  float(clustering_info.get('silhouette_score', 0)),
                'clustering_method': self.chosen_algorithm,
                'currency': 'GHS',
                'model_artifacts_used': self._artifacts_used_summary(scaler_override),
            }
            self.results['story_summary'] = self._generate_story_summary()

            # 12. Save outputs after meta is available so reports and JSON exports stay consistent.
            self._emit_progress(98, 'saving_outputs', 'Saving analysis JSON, chart outputs, and customer exports to the server.')
            logger.info("Step 12: Saving outputs...")
            self._save_outputs(customer_output)

            logger.info(
                f"Pipeline completed in {self.results['meta']['duration_seconds']:.1f}s"
            )
            self._emit_progress(100, 'completed', 'Analysis completed successfully. Your report and segment dashboard are ready.')
            return self.results

        except Exception as e:
            logger.error(f"Pipeline failed: {str(e)}", exc_info=True)
            self.results['meta'] = {
                'job_id': self.job_id,
                'status': 'failed',
                'error':  str(e)
            }
            raise

    def _emit_progress(self, percent: int, stage: str, message: str) -> None:
        """Send live stage telemetry to the jobs table."""
        if self.progress_callback is None:
            return

        try:
            safe_percent = max(0, min(100, int(percent)))
            self.progress_callback(safe_percent, stage, message)
        except Exception as exc:
            logger.warning("Progress callback failed for job %s: %s", self.job_id, exc)

    def _load_model_artifacts(self) -> Dict[str, Any]:
        assets = {
            'scaler': None,
            'segment_map': {},
            'cluster_profile': None,
        }

        scaler_path = MODELS_DIR / 'scaler.pkl'
        segment_map_path = MODELS_DIR / 'segment_map.json'
        cluster_profile_path = MODELS_DIR / 'cluster_rfm_profile.csv'

        if scaler_path.exists():
            try:
                assets['scaler'] = joblib.load(scaler_path)
            except Exception as exc:
                logger.warning("Failed to load scaler artifact from %s: %s", scaler_path, exc)

        if segment_map_path.exists():
            try:
                with open(segment_map_path, 'r') as handle:
                    assets['segment_map'] = json.load(handle)
            except Exception as exc:
                logger.warning("Failed to load segment map artifact from %s: %s", segment_map_path, exc)

        if cluster_profile_path.exists():
            try:
                assets['cluster_profile'] = pd.read_csv(cluster_profile_path)
            except Exception as exc:
                logger.warning("Failed to load cluster profile artifact from %s: %s", cluster_profile_path, exc)

        return assets

    def _infer_business_type(self, df: pd.DataFrame) -> str:
        category_col = 'category' if 'category' in df.columns else None
        if not category_col:
            return 'General Retail'

        cats = ' '.join(df[category_col].dropna().astype(str).str.lower().unique()[:50])
        biz_map = [
            (['shirt', 'dress', 'shoe', 'bag', 'fashion', 'cloth', 'apparel'], 'Fashion Retail'),
            (['phone', 'laptop', 'electronic', 'gadget', 'computer'], 'Electronics Retail'),
            (['food', 'grocery', 'beverage', 'snack', 'fruit', 'vegetable'], 'Food & Grocery'),
            (['beauty', 'cosmetic', 'skincare', 'makeup', 'hair'], 'Beauty & Personal Care'),
            (['furniture', 'home', 'kitchen', 'decor', 'sofa'], 'Home & Furniture'),
        ]
        for keywords, business_type in biz_map:
            if any(keyword in cats for keyword in keywords):
                return business_type
        return 'General Retail'

    def _build_eda_summary(self, df: pd.DataFrame, preprocessing_meta: Dict[str, Any]) -> Dict[str, Any]:
        summary = {
            'rows': int(len(df)),
            'columns': int(len(df.columns)),
            'duplicates': int(df.duplicated().sum()),
            'missing_values': int(df.isna().sum().sum()),
            'numeric_summary': {},
            'top_categories': [],
            'top_products': [],
            'top_payments': [],
        }

        for col in ['amount', 'qty', 'price', 'discount']:
            if col in df.columns and pd.api.types.is_numeric_dtype(df[col]):
                series = pd.to_numeric(df[col], errors='coerce').dropna()
                if not series.empty:
                    summary['numeric_summary'][col] = {
                        'mean': float(series.mean()),
                        'median': float(series.median()),
                        'std': float(series.std()) if len(series) > 1 else 0.0,
                        'min': float(series.min()),
                        'max': float(series.max()),
                    }

        for src, key in [('category', 'top_categories'), ('product', 'top_products'), ('payment', 'top_payments')]:
            if src in df.columns:
                summary[key] = [
                    {'label': str(label), 'count': int(count)}
                    for label, count in df[src].astype(str).value_counts().head(10).items()
                ]

        summary['warnings'] = preprocessing_meta.get('warnings', [])
        return summary

    def _get_compatible_scaler(self):
        scaler = self.model_artifacts.get('scaler')
        if scaler is None:
            return None

        expected_feature_count = 3
        if getattr(scaler, 'n_features_in_', expected_feature_count) != expected_feature_count:
            logger.warning("Skipping scaler artifact because expected %s features but got %s", expected_feature_count, getattr(scaler, 'n_features_in_', None))
            return None

        if not hasattr(scaler, 'transform'):
            logger.warning("Skipping scaler artifact because it does not implement transform()")
            return None

        logger.info("Using fitted scaler artifact from %s", MODELS_DIR / 'scaler.pkl')
        return scaler

    def _select_clustering_result(self, X: np.ndarray, optimal_k: int, comparison_results: Dict[str, Any]):
        methods = {
            'kmeans': comparison_results.get('kmeans'),
            'gmm': comparison_results.get('gmm'),
            'hierarchical': comparison_results.get('hierarchical'),
        }

        selected_method = self.clustering_method if self.clustering_method in methods else 'kmeans'
        selected_result = methods.get(selected_method)

        if self.include_comparison:
            scored_methods = {
                name: details for name, details in methods.items()
                if details and details.get('info', {}).get('silhouette_score') is not None
            }
            if scored_methods:
                selected_method = max(
                    scored_methods.keys(),
                    key=lambda name: scored_methods[name].get('info', {}).get('silhouette_score', float('-inf'))
                )
                selected_result = scored_methods[selected_method]

        if selected_result and selected_result.get('labels') is not None and selected_result.get('info'):
            labels = np.array(selected_result['labels'])
            selected_info = selected_result['info']
            clustering_info = {
                'method': selected_method,
                'n_clusters': optimal_k,
                'silhouette_score': float(selected_info.get('silhouette_score', 0) or 0),
                'davies_bouldin_score': float(selected_info.get('davies_bouldin_score', 0) or 0),
                'calinski_harabasz_score': float(selected_info.get('calinski_score', 0) or 0),
                'selected_via': 'comparison' if self.include_comparison else 'requested_method',
            }
            self.chosen_algorithm = selected_method
            return labels, clustering_info

        labels, clustering_info = run_clustering(
            X,
            method=self.clustering_method,
            n_clusters=optimal_k,
            auto_k=False
        )
        self.chosen_algorithm = self.clustering_method
        return labels, clustering_info

    def _apply_artifact_segment_labels(self) -> None:
        if not self.segments:
            return

        segment_map = self.model_artifacts.get('segment_map') or {}
        cluster_profile = self.model_artifacts.get('cluster_profile')

        for segment in self.segments:
            cluster_id = segment.get('cluster_id')
            mapped_name = segment_map.get(str(cluster_id))
            if mapped_name:
                segment['segment_key'] = str(mapped_name).lower().replace(' ', '_')
                segment['segment_label'] = mapped_name

            if isinstance(cluster_profile, pd.DataFrame) and not cluster_profile.empty and 'Cluster' in cluster_profile.columns:
                match = cluster_profile[cluster_profile['Cluster'] == cluster_id]
                if not match.empty:
                    row = match.iloc[0]
                    if pd.notna(row.get('SegmentName')):
                        segment['segment_label'] = str(row['SegmentName'])
                    segment['artifact_profile'] = {
                        'R_score': float(row['R_score']) if pd.notna(row.get('R_score')) else None,
                        'F_score': float(row['F_score']) if pd.notna(row.get('F_score')) else None,
                        'M_score': float(row['M_score']) if pd.notna(row.get('M_score')) else None,
                        'RFM_total': float(row['RFM_total']) if pd.notna(row.get('RFM_total')) else None,
                    }

    def _artifacts_used_summary(self, scaler_override) -> Dict[str, Any]:
        cluster_profile = self.model_artifacts.get('cluster_profile')
        return {
            'scaler': bool(scaler_override is not None),
            'segment_map': bool(self.model_artifacts.get('segment_map')),
            'cluster_profile': bool(isinstance(cluster_profile, pd.DataFrame) and not cluster_profile.empty),
        }

    # ── Step implementations ───────────────────────────────────────────────────

    def _winsorise(self, df: pd.DataFrame) -> tuple:
        """
        IQR Winsorization on the amount column.
        Caps outliers at [Q1 - 1.5*IQR, Q3 + 1.5*IQR] instead of removing them.
        """
        df = df.copy()
        col = 'amount'

        q1  = df[col].quantile(0.25)
        q3  = df[col].quantile(0.75)
        iqr = q3 - q1
        lower = q1 - 1.5 * iqr
        upper = q3 + 1.5 * iqr

        n_below = (df[col] < lower).sum()
        n_above = (df[col] > upper).sum()

        df[col] = df[col].clip(lower=lower, upper=upper)

        meta = {
            'column':       col,
            'q1':           float(q1),
            'q3':           float(q3),
            'iqr':          float(iqr),
            'lower_bound':  float(lower),
            'upper_bound':  float(upper),
            'capped_below': int(n_below),
            'capped_above': int(n_above)
        }
        logger.info(f"Winsorization: capped {n_below} low, {n_above} high outliers")
        return df, meta

    def _run_pca(self, X: np.ndarray) -> Dict[str, Any]:
        """Reduce 3D RFM features to 2D for visualisation."""
        try:
            from sklearn.decomposition import PCA
            pca = PCA(n_components=2, random_state=42)
            X_pca = pca.fit_transform(X)
            return {
                'components':           X_pca.tolist(),
                'explained_variance':   pca.explained_variance_ratio_.tolist(),
                'total_variance_explained': float(pca.explained_variance_ratio_.sum())
            }
        except Exception as e:
            logger.warning(f"PCA skipped: {e}")
            return {}

    def _find_optimal_k_majority_vote(
        self, X: np.ndarray, k_min: int = 2, k_max: int = 8
    ) -> tuple:
        """
        Majority vote across 4 metrics to choose optimal K:
          - Elbow (inertia inflection)
          - Silhouette (highest score)
          - Calinski-Harabasz (highest score)
          - Davies-Bouldin (lowest score)
        Falls back to 4 if vote is inconclusive.

        Uses MiniBatchKMeans and subsampling for speed on large datasets.
        """
        from sklearn.cluster import MiniBatchKMeans
        from sklearn.metrics import (
            silhouette_score, calinski_harabasz_score, davies_bouldin_score
        )

        # Subsample for speed — optimal K is stable at ~5k points
        if len(X) > OPTIMAL_K_SUBSAMPLE:
            idx = np.random.RandomState(42).choice(len(X), OPTIMAL_K_SUBSAMPLE, replace=False)
            X_sample = X[idx]
            logger.info(f"Subsampled {len(X)} → {OPTIMAL_K_SUBSAMPLE} for optimal-K search")
        else:
            X_sample = X

        k_range   = range(k_min, k_max + 1)
        inertias  = []
        sil_scores, ch_scores, db_scores = [], [], []

        for k in k_range:
            km     = MiniBatchKMeans(n_clusters=k, random_state=42, n_init=3, batch_size=1024)
            labels = km.fit_predict(X_sample)
            inertias.append(km.inertia_)
            if len(set(labels)) > 1:
                sil_scores.append(silhouette_score(X_sample, labels))
                ch_scores.append(calinski_harabasz_score(X_sample, labels))
                db_scores.append(davies_bouldin_score(X_sample, labels))
            else:
                sil_scores.append(0)
                ch_scores.append(0)
                db_scores.append(float('inf'))

        k_list = list(k_range)

        # Elbow: largest second derivative of inertia
        if len(inertias) >= 3:
            d2     = np.diff(np.diff(inertias))
            k_elbow = k_list[int(np.argmax(d2)) + 2]
        else:
            k_elbow = k_list[0]

        k_sil = k_list[int(np.argmax(sil_scores))]
        k_ch  = k_list[int(np.argmax(ch_scores))]
        k_db  = k_list[int(np.argmin(db_scores))]

        votes = [k_elbow, k_sil, k_ch, k_db]
        # Pick the value with most votes; ties broken by silhouette winner
        from collections import Counter
        vote_counts = Counter(votes)
        max_votes   = max(vote_counts.values())
        candidates  = [k for k, v in vote_counts.items() if v == max_votes]
        optimal_k   = k_sil if k_sil in candidates else candidates[0]

        meta = {
            'k_range':        [k_min, k_max],
            'votes':          {'elbow': k_elbow, 'silhouette': k_sil,
                               'calinski_harabasz': k_ch, 'davies_bouldin': k_db},
            'optimal_k':      optimal_k,
            'inertias':       inertias,
            'silhouette_scores': sil_scores,
            'ch_scores':      ch_scores,
            'db_scores':      [float('inf') if v == float('inf') else v for v in db_scores]
        }
        logger.info(f"Optimal K = {optimal_k} (votes: elbow={k_elbow}, sil={k_sil}, CH={k_ch}, DB={k_db})")
        return optimal_k, meta

    def _run_shap(
        self, X: np.ndarray, labels: np.ndarray, feature_names: list
    ) -> Dict[str, Any]:
        """
        Train a lightweight Random Forest to predict cluster labels,
        then compute SHAP values to explain feature importance.
        Uses subsampling for speed on large datasets.
        """
        shap = _try_import('shap')
        if shap is None:
            return {'available': False, 'reason': 'shap not installed'}

        try:
            from sklearn.ensemble import RandomForestClassifier

            # Subsample for SHAP — feature importance is stable at ~2k points
            if len(X) > SHAP_MAX_SAMPLES:
                idx = np.random.RandomState(42).choice(len(X), SHAP_MAX_SAMPLES, replace=False)
                X_shap, labels_shap = X[idx], labels[idx]
                logger.info(f"Subsampled {len(X)} → {SHAP_MAX_SAMPLES} for SHAP")
            else:
                X_shap, labels_shap = X, labels

            clf = RandomForestClassifier(n_estimators=30, random_state=42, n_jobs=1)
            clf.fit(X_shap, labels_shap)

            explainer   = shap.TreeExplainer(clf)
            shap_values = explainer.shap_values(X_shap)

            # Mean absolute SHAP per feature (average across classes)
            if isinstance(shap_values, list):
                mean_abs = np.mean([np.abs(sv).mean(axis=0) for sv in shap_values], axis=0)
            else:
                mean_abs = np.abs(shap_values).mean(axis=0)

            # Clean feature names for display
            display_names = [f.replace('_normalized', '') for f in feature_names]

            importance = {
                display_names[i]: float(mean_abs[i])
                for i in range(len(display_names))
            }
            ranked = sorted(importance.items(), key=lambda x: x[1], reverse=True)

            return {
                'available':        True,
                'feature_importance': importance,
                'ranked_features':  [{'feature': k, 'importance': v} for k, v in ranked]
            }

        except Exception as e:
            logger.warning(f"SHAP skipped: {e}")
            return {'available': False, 'reason': str(e)}

    def _generate_charts(
        self, rfm_normalised: pd.DataFrame, pca_result: Dict
    ) -> Dict[str, str]:
        """
        Generate and save analysis charts as PNG files.
        Returns a dict mapping chart name -> file path (relative to output_dir).
        """
        mpl = _try_import('matplotlib')
        if mpl is None:
            return {}

        import matplotlib
        matplotlib.use('Agg')  # non-interactive backend
        import matplotlib.pyplot as plt
        import matplotlib.cm as cm

        charts = {}
        n_clusters = len(set(self.labels))
        palette    = cm.get_cmap('tab10', n_clusters)
        colors     = [palette(i) for i in range(n_clusters)]

        # ── Chart 1: Cluster scatter (PCA 2D) ────────────────────────────────
        if pca_result.get('components'):
            try:
                comps = np.array(pca_result['components'])
                fig, ax = plt.subplots(figsize=(8, 6))
                for i in range(n_clusters):
                    mask = self.labels == i
                    seg  = next(
                        (s for s in self.segments if s['cluster_id'] == i),
                        {'segment_label': f'Cluster {i}'}
                    )
                    ax.scatter(
                        comps[mask, 0], comps[mask, 1],
                        c=[colors[i]], label=seg['segment_label'],
                        alpha=0.6, s=20
                    )
                var = pca_result.get('explained_variance', [0, 0])
                ax.set_xlabel(f'PC1 ({var[0]*100:.1f}% variance)')
                ax.set_ylabel(f'PC2 ({var[1]*100:.1f}% variance)')
                ax.set_title('Customer Segments — PCA Projection')
                ax.legend(loc='best', fontsize=8)
                path = self.output_dir / f"{self.job_id}_chart_pca.png"
                fig.savefig(path, dpi=CHART_DPI, bbox_inches='tight')
                plt.close(fig)
                charts['pca_scatter'] = str(path)
            except Exception as e:
                logger.warning(f"PCA chart skipped: {e}")

        # ── Chart 2: Segment size bar chart ──────────────────────────────────
        try:
            labels_list = [s['segment_label'] for s in self.segments]
            counts      = [s['num_customers'] for s in self.segments]
            fig, ax = plt.subplots(figsize=(9, 5))
            bars = ax.barh(labels_list, counts, color=[colors[i % n_clusters] for i in range(len(counts))])
            ax.bar_label(bars, padding=4, fontsize=9)
            ax.set_xlabel('Number of Customers')
            ax.set_title('Segment Sizes')
            ax.invert_yaxis()
            path = self.output_dir / f"{self.job_id}_chart_segments.png"
            fig.savefig(path, dpi=CHART_DPI, bbox_inches='tight')
            plt.close(fig)
            charts['segment_sizes'] = str(path)
        except Exception as e:
            logger.warning(f"Segment size chart skipped: {e}")

        # ── Chart 3: RFM distributions ───────────────────────────────────────
        try:
            sns = _try_import('seaborn')
            if sns:
                fig, axes = plt.subplots(1, 3, figsize=(14, 4))
                for ax, col, title in zip(
                    axes,
                    ['recency', 'frequency', 'monetary'],
                    ['Recency (days)', 'Frequency (txns)', 'Monetary (revenue)']
                ):
                    sns.histplot(self.rfm[col], ax=ax, bins=30, kde=True, color='steelblue')
                    ax.set_title(title)
                    ax.set_xlabel('')
                plt.tight_layout()
                path = self.output_dir / f"{self.job_id}_chart_rfm_dist.png"
                fig.savefig(path, dpi=120, bbox_inches='tight')
                plt.close(fig)
                charts['rfm_distributions'] = str(path)
        except Exception as e:
            logger.warning(f"RFM distribution chart skipped: {e}")

        # ── Chart 4: Revenue by segment (Pareto-style) ───────────────────────
        try:
            segs_sorted = sorted(self.segments, key=lambda x: x['total_revenue'], reverse=True)
            seg_labels  = [s['segment_label'] for s in segs_sorted]
            revenues    = [s['total_revenue'] for s in segs_sorted]
            total_rev   = sum(revenues)
            cumulative  = np.cumsum(revenues) / total_rev * 100

            fig, ax1 = plt.subplots(figsize=(9, 5))
            ax2 = ax1.twinx()
            ax1.bar(seg_labels, revenues,
                    color=[colors[i % n_clusters] for i in range(len(seg_labels))],
                    alpha=0.8)
            ax2.plot(seg_labels, cumulative, 'k-o', linewidth=2, markersize=5)
            ax2.axhline(80, color='red', linestyle='--', linewidth=1, label='80%')
            ax1.set_ylabel('Total Revenue')
            ax2.set_ylabel('Cumulative %')
            ax1.set_title('Revenue by Segment (Pareto)')
            plt.xticks(rotation=25, ha='right')
            path = self.output_dir / f"{self.job_id}_chart_pareto.png"
            fig.savefig(path, dpi=120, bbox_inches='tight')
            plt.close(fig)
            charts['pareto'] = str(path)
        except Exception as e:
            logger.warning(f"Pareto chart skipped: {e}")

        # ── Chart 5: Algorithm comparison ────────────────────────────────────
        try:
            comparison = self.results.get('comparison', {})
            if comparison:
                rows = []
                for method in ['kmeans', 'gmm', 'hierarchical']:
                    values = comparison.get(method)
                    if not values:
                        continue
                    info = values.get('info', {})
                    rows.append({
                        'method': method.upper(),
                        'silhouette': info.get('silhouette_score', 0),
                        'davies_bouldin': info.get('davies_bouldin_score', 0),
                        'calinski_harabasz': info.get('calinski_score', 0),
                    })
                comparison_df = pd.DataFrame(rows)
                if not comparison_df.empty:
                    fig, axes = plt.subplots(1, 3, figsize=(14, 4))
                    metrics = [
                        ('silhouette', 'Silhouette', False),
                        ('davies_bouldin', 'Davies-Bouldin', True),
                        ('calinski_harabasz', 'Calinski-Harabasz', False),
                    ]
                    for ax, (col, title, ascending) in zip(axes, metrics):
                        plot_df = comparison_df.sort_values(by=col, ascending=ascending)
                        bars = ax.bar(plot_df['method'], plot_df[col], color='steelblue')
                        if not plot_df.empty:
                            best_method = plot_df.iloc[0]['method'] if ascending else plot_df.iloc[-1]['method']
                            for bar, method in zip(bars, plot_df['method']):
                                if method == best_method:
                                    bar.set_color('#e8b031')
                        ax.set_title(title)
                    plt.tight_layout()
                    path = self.output_dir / f"{self.job_id}_chart_algorithm_comparison.png"
                    fig.savefig(path, dpi=120, bbox_inches='tight')
                    plt.close(fig)
                    charts['algorithm_comparison'] = str(path)
        except Exception as e:
            logger.warning(f"Algorithm comparison chart skipped: {e}")

        # ── Chart 6: RFM radar chart ────────────────────────────────────────
        try:
            metrics = ['avg_recency', 'avg_frequency', 'avg_monetary']
            if self.segments:
                radar_df = pd.DataFrame([
                    {
                        'segment': s['segment_label'],
                        'avg_recency': float(s.get('avg_recency', 0) or 0),
                        'avg_frequency': float(s.get('avg_frequency', 0) or 0),
                        'avg_monetary': float(s.get('avg_monetary', 0) or 0),
                    }
                    for s in self.segments
                ])
                radar_scaled = radar_df.copy()
                for metric in metrics:
                    max_val = radar_scaled[metric].max() or 1
                    radar_scaled[metric] = radar_scaled[metric] / max_val

                angles = np.linspace(0, 2 * np.pi, len(metrics), endpoint=False).tolist()
                angles += angles[:1]
                fig, ax = plt.subplots(figsize=(7, 7), subplot_kw={'polar': True})
                for i, row in radar_scaled.iterrows():
                    values = [row[m] for m in metrics] + [row[metrics[0]]]
                    ax.plot(angles, values, linewidth=2, label=row['segment'])
                    ax.fill(angles, values, alpha=0.08)
                ax.set_xticks(angles[:-1])
                ax.set_xticklabels(['Recency', 'Frequency', 'Monetary'])
                ax.set_title('RFM Radar Comparison', pad=20)
                ax.legend(loc='upper right', bbox_to_anchor=(1.3, 1.1), fontsize=8)
                path = self.output_dir / f"{self.job_id}_chart_radar.png"
                fig.savefig(path, dpi=120, bbox_inches='tight')
                plt.close(fig)
                charts['radar_chart'] = str(path)
        except Exception as e:
            logger.warning(f"Radar chart skipped: {e}")

        # ── Chart 7: RFM violin plots ───────────────────────────────────────
        try:
            sns = _try_import('seaborn')
            if sns and self.rfm is not None:
                violin_df = self.rfm.copy()
                violin_df['cluster_label'] = violin_df['customer_id'].map(
                    pd.DataFrame({
                        'customer_id': self.rfm['customer_id'].astype(str),
                        'cluster_id': self.labels,
                    }).assign(
                        cluster_label=lambda d: d['cluster_id'].map({s['cluster_id']: s['segment_label'] for s in self.segments})
                    ).set_index('customer_id')['cluster_label']
                )
                fig, axes = plt.subplots(1, 3, figsize=(16, 5))
                for ax, metric, title in zip(axes, ['recency', 'frequency', 'monetary'], ['Recency', 'Frequency', 'Monetary']):
                    sns.violinplot(data=violin_df, x='cluster_label', y=metric, ax=ax, inner='quartile')
                    ax.set_title(title)
                    ax.tick_params(axis='x', rotation=30)
                    ax.set_xlabel('')
                plt.tight_layout()
                path = self.output_dir / f"{self.job_id}_chart_violin.png"
                fig.savefig(path, dpi=120, bbox_inches='tight')
                plt.close(fig)
                charts['rfm_violin_plots'] = str(path)
        except Exception as e:
            logger.warning(f"Violin plot chart skipped: {e}")

        logger.info(f"Generated {len(charts)} charts")
        return charts

    # ── Output helpers ─────────────────────────────────────────────────────────

    def _prepare_customer_output(self, rfm_normalised: pd.DataFrame) -> pd.DataFrame:
        output = rfm_normalised[['customer_id', 'recency', 'frequency', 'monetary']].copy()
        output['cluster'] = self.labels
        segment_label_map = {s['cluster_id']: s['segment_label'] for s in self.segments}
        segment_key_map = {s['cluster_id']: s['segment_key'] for s in self.segments}
        description_map = {s['segment_label']: s.get('description', '') for s in self.segments}
        action_map = {
            s['segment_label']: (s.get('recommended_actions') or [''])[0]
            for s in self.segments
        }

        output['segment'] = output['cluster'].map(segment_label_map).fillna('Unknown')
        output['segment_description'] = output['segment'].map(description_map).fillna('')
        output['recommended_action'] = output['segment'].map(action_map).fillna('')
        output['risk_level'] = output['cluster'].map(segment_key_map).map(self._risk_level_for_segment_key).fillna('Medium')
        output['status'] = np.where(output['recency'] <= 30, 'Active', 'Inactive')
        output['customer_name'] = output['customer_id'].astype(str)
        output['rfm_score'] = (
            output['recency'].rank(method='average', ascending=False, pct=True) * 100
            + output['frequency'].rank(method='average', ascending=True, pct=True) * 100
            + output['monetary'].rank(method='average', ascending=True, pct=True) * 100
        ).round(0).astype(int)
        output['estimated_lifetime_value'] = (
            output['monetary'].astype(float) * np.maximum(output['frequency'].astype(float), 1.0)
        ).round(2)

        customer_dates = self._build_customer_dates()
        if not customer_dates.empty:
            output = output.merge(customer_dates, on='customer_id', how='left')
        else:
            output['last_purchase_date'] = pd.NaT
            output['first_purchase_date'] = pd.NaT
            output['customer_tenure_days'] = None

        output = output.rename(columns={
            'recency': 'recency_days',
            'frequency': 'frequency_count',
            'monetary': 'total_spend_ghs'
        })
        return output

    def _risk_level_for_segment_key(self, segment_key: str) -> str:
        if segment_key in {'at_risk', 'lost', 'hibernating', 'about_to_sleep'}:
            return 'High'
        if segment_key in {'need_attention', 'promising', 'new_customers'}:
            return 'Medium'
        return 'Low'

    def _build_customer_dates(self) -> pd.DataFrame:
        if self.df is None or self.df.empty:
            return pd.DataFrame()

        try:
            grouped = (
                self.df.groupby('customer_id')
                .agg(
                    first_purchase_date=('invoice_date', 'min'),
                    last_purchase_date=('invoice_date', 'max')
                )
                .reset_index()
            )
            grouped['customer_tenure_days'] = (
                pd.to_datetime(grouped['last_purchase_date']) - pd.to_datetime(grouped['first_purchase_date'])
            ).dt.days.clip(lower=0)
            return grouped
        except Exception as exc:
            logger.warning("Could not build customer lifecycle dates for job %s: %s", self.job_id, exc)
            return pd.DataFrame()

    def _save_outputs(self, customer_output: pd.DataFrame):
        # Customers CSV
        customer_csv_path = self.output_dir / f"{self.job_id}_customers.csv"
        customer_output.to_csv(customer_csv_path, index=False)

        # Track output artifacts before serializing the main results payload.
        self.results['output_files'] = {
            'customers_csv':  str(customer_csv_path),
            'results_json':   str(self.output_dir / f"{self.job_id}_results.json"),
            'segments_json':  str(self.output_dir / f"{self.job_id}_segments.json")
        }

        # Full results JSON
        results_json_path = self.output_dir / f"{self.job_id}_results.json"
        with open(results_json_path, 'w') as f:
            json.dump(self._convert_to_serializable(self.results), f, indent=2, default=str)

        # Segments JSON (kept for backwards compat with jobs.py)
        segments_json_path = self.output_dir / f"{self.job_id}_segments.json"
        with open(segments_json_path, 'w') as f:
            json.dump(self._convert_to_serializable(self.segments), f, indent=2, default=str)

    def _build_customer_rows(self, customer_output: pd.DataFrame):
        """Prepare all customer rows for the analytics dashboard table."""
        if customer_output.empty:
            return []

        rows = []
        sorted_output = customer_output.sort_values(
            by=['last_purchase_date', 'total_spend_ghs'],
            ascending=[False, False],
            na_position='last'
        )

        for _, row in sorted_output.iterrows():
            customer_name = str(row.get('customer_name') or row['customer_id'])
            initials = ''.join(part[:1].upper() for part in customer_name.split()[:2])[:2] or customer_name[:2].upper()
            rows.append({
                'customer_id': str(row['customer_id']),
                'customer_name': customer_name,
                'customer_email': '',
                'initials': initials,
                'segment': row.get('segment', 'Unknown'),
                'segment_description': row.get('segment_description', ''),
                'recommended_action': row.get('recommended_action', ''),
                'risk_level': row.get('risk_level', 'Medium'),
                'recency_days': int(row.get('recency_days', 0) or 0),
                'frequency_count': int(row.get('frequency_count', 0) or 0),
                'total_spend': float(row.get('total_spend_ghs', 0) or 0),
                'rfm_score': int(row.get('rfm_score', 0) or 0),
                'estimated_lifetime_value': float(row.get('estimated_lifetime_value', 0) or 0),
                'first_purchase_date': row['first_purchase_date'].isoformat() if pd.notna(row.get('first_purchase_date')) else None,
                'last_purchase_date': row['last_purchase_date'].isoformat() if pd.notna(row.get('last_purchase_date')) else None,
                'customer_tenure_days': int(row.get('customer_tenure_days', 0) or 0),
                'status': row.get('status', 'Active'),
            })
        return rows

    def _build_recent_customers(self, customer_output: pd.DataFrame, limit: int = 8):
        """Prepare a lightweight recent-customer table for the frontend."""
        return self._build_customer_rows(customer_output)[:limit]

    def _generate_story_summary(self) -> Dict[str, Any]:
        """Create a plain-language summary that the frontend and PDF can present directly."""
        summary = self.results.get('segment_summary', {})
        meta = self.results.get('meta', {})
        segments = self.results.get('segments', []) or []
        if not summary or not segments:
            return {
                'headline': 'Customer segmentation is ready.',
                'narrative': 'Your customer analysis completed successfully and the segment dashboard is available.',
                'health_score': 0,
                'revenue_concentration': 0,
                'quality_rating': 0,
            }

        growth_segments = {'Champions', 'Loyal Customers', 'Potential Loyalists', 'Promising'}
        growth_customers = sum(
            int(segment.get('num_customers', 0) or 0)
            for segment in segments
            if segment.get('segment_label') in growth_segments
        )
        total_customers = int(summary.get('total_customers', 0) or 0)
        health_score = round((growth_customers / total_customers * 100), 1) if total_customers else 0.0

        revenue_sorted = sorted(segments, key=lambda item: float(item.get('total_revenue', 0) or 0), reverse=True)
        total_revenue = float(summary.get('total_revenue', 0) or 0)
        top_two_revenue = sum(float(segment.get('total_revenue', 0) or 0) for segment in revenue_sorted[:2])
        revenue_concentration = round((top_two_revenue / total_revenue * 100), 1) if total_revenue else 0.0

        silhouette = float(meta.get('silhouette_score', 0) or 0)
        quality_rating = max(1, min(5, int(round(1 + silhouette * 4)))) if silhouette > 0 else 1

        best_segment = summary.get('highest_value_segment', {}) or {}
        risk_count = int(summary.get('at_risk_customers', 0) or 0)
        headline = (
            f"{best_segment.get('label', 'Your top segment')} is driving the strongest customer value."
        )
        narrative = (
            f"You have {total_customers:,} customers across {summary.get('num_segments', len(segments))} segments. "
            f"{growth_customers:,} customers ({health_score:.1f}%) are in your healthier growth segments, while "
            f"{risk_count:,} customers need urgent retention attention. Your top 2 segments contribute "
            f"{revenue_concentration:.1f}% of recorded revenue, and the selected {str(meta.get('clustering_method', 'clustering')).upper()} model scored "
            f"{silhouette:.3f} on silhouette quality."
        )

        return {
            'headline': headline,
            'narrative': narrative,
            'health_score': health_score,
            'revenue_concentration': revenue_concentration,
            'quality_rating': quality_rating,
            'silhouette_score': silhouette,
        }

    def _convert_to_serializable(self, obj):
        if isinstance(obj, dict):
            return {k: self._convert_to_serializable(v) for k, v in obj.items()}
        elif isinstance(obj, list):
            return [self._convert_to_serializable(v) for v in obj]
        elif isinstance(obj, np.ndarray):
            return obj.tolist()
        elif isinstance(obj, (np.integer, np.int64, np.int32)):
            return int(obj)
        elif isinstance(obj, (np.floating, np.float64, np.float32)):
            return float(obj)
        elif isinstance(obj, np.bool_):
            return bool(obj)
        elif isinstance(obj, pd.Timestamp):
            return obj.isoformat()
        return obj


# ── Convenience function (called by jobs.py — signature unchanged) ─────────────

def run_pipeline(
    file_path: str,
    output_dir: str,
    job_id: str,
    column_mapping: Optional[Dict[str, str]] = None,
    clustering_method: str = 'kmeans',
    include_comparison: bool = True,
    progress_callback: Optional[Callable[[int, str, str], None]] = None
) -> Dict[str, Any]:
    """
    Convenience function to run the segmentation pipeline.
    Signature is identical to the previous version — jobs.py needs no changes.
    """
    pipeline = SegmentationPipeline(
        file_path=file_path,
        output_dir=output_dir,
        job_id=job_id,
        column_mapping=column_mapping,
        clustering_method=clustering_method,
        include_comparison=include_comparison,
        progress_callback=progress_callback
    )
    return pipeline.run()
