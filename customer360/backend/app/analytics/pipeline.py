"""
Main analytics pipeline for Customer360.
Orchestrates the full preprocessing -> RFM -> clustering -> segmentation flow.
"""
import json
import os
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, Optional
import pandas as pd
import numpy as np
import logging

from .preprocessing import preprocess_transaction_data, get_csv_preview
from .rfm import compute_rfm, normalize_rfm, get_rfm_statistics, get_rfm_distributions
from .clustering import run_clustering, run_comparison
from .segmentation import analyze_clusters, get_cluster_sizes, get_segment_summary

logger = logging.getLogger(__name__)


class SegmentationPipeline:
    """
    Main pipeline class for customer segmentation analysis.
    """
    
    def __init__(
        self,
        file_path: str,
        output_dir: str,
        job_id: str,
        column_mapping: Optional[Dict[str, str]] = None,
        clustering_method: str = 'kmeans',
        include_comparison: bool = False
    ):
        """
        Initialize the segmentation pipeline.
        
        Args:
            file_path: Path to input CSV file
            output_dir: Directory to save outputs
            job_id: Unique job identifier
            column_mapping: Optional column name mapping
            clustering_method: 'kmeans', 'gmm', or 'hierarchical'
            include_comparison: Whether to run all methods for comparison
        """
        self.file_path = file_path
        self.output_dir = Path(output_dir)
        self.job_id = job_id
        self.column_mapping = column_mapping
        self.clustering_method = clustering_method
        self.include_comparison = include_comparison
        
        # Create output directory
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Results storage
        self.results = {}
        self.df = None
        self.rfm = None
        self.labels = None
        self.segments = None
    
    def run(self) -> Dict[str, Any]:
        """
        Execute the full segmentation pipeline.
        
        Returns:
            Dictionary with all results and metadata
        """
        start_time = datetime.utcnow()
        logger.info(f"Starting segmentation pipeline for job {self.job_id}")
        
        try:
            # Step 1: Preprocess data
            logger.info("Step 1: Preprocessing data...")
            self.df, preprocessing_meta = preprocess_transaction_data(
                self.file_path,
                self.column_mapping
            )
            self.results['preprocessing'] = preprocessing_meta
            
            # Step 2: Compute RFM
            logger.info("Step 2: Computing RFM metrics...")
            self.rfm = compute_rfm(self.df)
            rfm_stats = get_rfm_statistics(self.rfm)
            rfm_distributions = get_rfm_distributions(self.rfm)
            self.results['rfm_statistics'] = rfm_stats
            self.results['rfm_distributions'] = rfm_distributions
            
            # Step 3: Normalize RFM features
            logger.info("Step 3: Normalizing RFM features...")
            rfm_normalized, scaler = normalize_rfm(self.rfm)
            
            # Get feature matrix for clustering
            feature_cols = ['recency_normalized', 'frequency_normalized', 'monetary_normalized']
            X = rfm_normalized[feature_cols].values
            
            # Step 4: Run clustering
            logger.info(f"Step 4: Running {self.clustering_method} clustering...")
            self.labels, clustering_info = run_clustering(
                X,
                method=self.clustering_method,
                auto_k=True
            )
            self.results['clustering'] = clustering_info
            
            # Step 4b: Run comparison if requested
            if self.include_comparison:
                logger.info("Step 4b: Running clustering comparison...")
                comparison_results = run_comparison(X)
                self.results['comparison'] = comparison_results
            
            # Step 5: Analyze segments
            logger.info("Step 5: Analyzing segments...")
            self.segments = analyze_clusters(self.rfm, self.labels)
            cluster_sizes = get_cluster_sizes(self.labels)
            segment_summary = get_segment_summary(self.segments)
            
            self.results['segments'] = self.segments
            self.results['cluster_sizes'] = cluster_sizes
            self.results['segment_summary'] = segment_summary
            
            # Step 6: Prepare customer-level output
            logger.info("Step 6: Preparing customer-level output...")
            customer_output = self._prepare_customer_output(rfm_normalized)
            
            # Step 7: Save all outputs
            logger.info("Step 7: Saving outputs...")
            self._save_outputs(customer_output)
            
            # Finalize results
            end_time = datetime.utcnow()
            self.results['meta'] = {
                'job_id': self.job_id,
                'status': 'completed',
                'start_time': start_time.isoformat(),
                'end_time': end_time.isoformat(),
                'duration_seconds': (end_time - start_time).total_seconds(),
                'num_customers': len(self.rfm),
                'num_transactions': len(self.df),
                'total_revenue': float(self.df['amount'].sum()),
                'num_clusters': int(clustering_info['n_clusters']),
                'silhouette_score': float(clustering_info.get('silhouette_score', 0)),
                'clustering_method': self.clustering_method
            }
            
            logger.info(f"Pipeline completed successfully in {self.results['meta']['duration_seconds']:.1f}s")
            
            return self.results
            
        except Exception as e:
            logger.error(f"Pipeline failed: {str(e)}")
            self.results['meta'] = {
                'job_id': self.job_id,
                'status': 'failed',
                'error': str(e)
            }
            raise
    
    def _prepare_customer_output(self, rfm_normalized: pd.DataFrame) -> pd.DataFrame:
        """
        Prepare customer-level output with all analysis results.
        """
        output = rfm_normalized[['customer_id', 'recency', 'frequency', 'monetary']].copy()
        output['cluster'] = self.labels
        
        # Add segment labels
        segment_map = {s['cluster_id']: s['segment_label'] for s in self.segments}
        output['segment'] = output['cluster'].map(segment_map)
        
        return output
    
    def _save_outputs(self, customer_output: pd.DataFrame):
        """
        Save all analysis outputs to files.
        """
        # Save customer-level results as CSV
        customer_csv_path = self.output_dir / f"{self.job_id}_customers.csv"
        customer_output.to_csv(customer_csv_path, index=False)
        
        # Save full results as JSON
        results_json_path = self.output_dir / f"{self.job_id}_results.json"
        
        # Convert numpy types for JSON serialization
        results_json = self._convert_to_serializable(self.results)
        
        with open(results_json_path, 'w') as f:
            json.dump(results_json, f, indent=2, default=str)
        
        # Save segment summary as JSON
        segments_json_path = self.output_dir / f"{self.job_id}_segments.json"
        with open(segments_json_path, 'w') as f:
            json.dump(self.segments, f, indent=2)
        
        self.results['output_files'] = {
            'customers_csv': str(customer_csv_path),
            'results_json': str(results_json_path),
            'segments_json': str(segments_json_path)
        }
    
    def _convert_to_serializable(self, obj):
        """
        Convert numpy/pandas types to JSON-serializable Python types.
        """
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
        else:
            return obj


def run_pipeline(
    file_path: str,
    output_dir: str,
    job_id: str,
    column_mapping: Optional[Dict[str, str]] = None,
    clustering_method: str = 'kmeans',
    include_comparison: bool = False
) -> Dict[str, Any]:
    """
    Convenience function to run the segmentation pipeline.
    
    Args:
        file_path: Path to input CSV file
        output_dir: Directory to save outputs
        job_id: Unique job identifier
        column_mapping: Optional column name mapping
        clustering_method: 'kmeans', 'gmm', or 'hierarchical'
        include_comparison: Whether to run all methods for comparison
        
    Returns:
        Dictionary with all results
    """
    pipeline = SegmentationPipeline(
        file_path=file_path,
        output_dir=output_dir,
        job_id=job_id,
        column_mapping=column_mapping,
        clustering_method=clustering_method,
        include_comparison=include_comparison
    )
    
    return pipeline.run()
