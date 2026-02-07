"""
Unit tests for clustering module.
"""
import pytest
import numpy as np
from sklearn.datasets import make_blobs

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.analytics.clustering import (
    find_optimal_k,
    run_kmeans,
    run_gmm,
    run_hierarchical,
    run_clustering,
    run_comparison
)


class TestFindOptimalK:
    """Test cases for finding optimal number of clusters."""
    
    @pytest.fixture
    def clustered_data(self):
        """Generate data with known cluster structure."""
        X, _ = make_blobs(n_samples=300, centers=4, random_state=42)
        return X
    
    def test_find_optimal_k_returns_valid_result(self, clustered_data):
        """Test that find_optimal_k returns valid result structure."""
        result = find_optimal_k(clustered_data, k_range=(2, 6))
        
        assert 'optimal_k' in result
        assert 'k_values' in result
        assert 'silhouette_scores' in result
        assert result['optimal_k'] >= 2
        assert result['optimal_k'] <= 6
    
    def test_find_optimal_k_detects_clusters(self, clustered_data):
        """Test that optimal k is close to actual number of clusters."""
        result = find_optimal_k(clustered_data, k_range=(2, 8))
        
        # Should find close to 4 clusters (the actual number)
        assert result['optimal_k'] >= 3
        assert result['optimal_k'] <= 5


class TestKMeans:
    """Test cases for K-Means clustering."""
    
    @pytest.fixture
    def sample_data(self):
        """Generate sample data."""
        X, _ = make_blobs(n_samples=100, centers=3, random_state=42)
        return X
    
    def test_run_kmeans_returns_labels(self, sample_data):
        """Test that K-Means returns cluster labels."""
        labels, info = run_kmeans(sample_data, n_clusters=3)
        
        assert len(labels) == len(sample_data)
        assert set(labels) == {0, 1, 2}
    
    def test_run_kmeans_returns_info(self, sample_data):
        """Test that K-Means returns clustering info."""
        labels, info = run_kmeans(sample_data, n_clusters=3)
        
        assert info['method'] == 'kmeans'
        assert info['n_clusters'] == 3
        assert 'silhouette_score' in info
        assert 'inertia' in info
    
    def test_run_kmeans_silhouette_positive(self, sample_data):
        """Test that silhouette score is positive for good clustering."""
        labels, info = run_kmeans(sample_data, n_clusters=3)
        
        # For well-separated clusters, silhouette should be positive
        assert info['silhouette_score'] > 0


class TestGMM:
    """Test cases for Gaussian Mixture Model clustering."""
    
    @pytest.fixture
    def sample_data(self):
        """Generate sample data."""
        X, _ = make_blobs(n_samples=100, centers=3, random_state=42)
        return X
    
    def test_run_gmm_returns_labels(self, sample_data):
        """Test that GMM returns cluster labels."""
        labels, info = run_gmm(sample_data, n_components=3)
        
        assert len(labels) == len(sample_data)
        assert len(set(labels)) <= 3
    
    def test_run_gmm_returns_info(self, sample_data):
        """Test that GMM returns clustering info."""
        labels, info = run_gmm(sample_data, n_components=3)
        
        assert info['method'] == 'gmm'
        assert info['n_clusters'] == 3
        assert 'bic' in info
        assert 'aic' in info


class TestHierarchical:
    """Test cases for Hierarchical clustering."""
    
    @pytest.fixture
    def sample_data(self):
        """Generate sample data."""
        X, _ = make_blobs(n_samples=100, centers=3, random_state=42)
        return X
    
    def test_run_hierarchical_returns_labels(self, sample_data):
        """Test that hierarchical clustering returns cluster labels."""
        labels, info = run_hierarchical(sample_data, n_clusters=3)
        
        assert len(labels) == len(sample_data)
        assert len(set(labels)) == 3
    
    def test_run_hierarchical_returns_info(self, sample_data):
        """Test that hierarchical clustering returns info."""
        labels, info = run_hierarchical(sample_data, n_clusters=3)
        
        assert info['method'] == 'hierarchical'
        assert info['n_clusters'] == 3
        assert 'linkage' in info


class TestRunClustering:
    """Test cases for general clustering runner."""
    
    @pytest.fixture
    def sample_data(self):
        """Generate sample data."""
        X, _ = make_blobs(n_samples=100, centers=4, random_state=42)
        return X
    
    def test_run_clustering_auto_k(self, sample_data):
        """Test clustering with automatic k selection."""
        labels, info = run_clustering(sample_data, method='kmeans', auto_k=True)
        
        assert len(labels) == len(sample_data)
        assert 'n_clusters' in info
        assert info['n_clusters'] >= 2
    
    def test_run_clustering_specified_k(self, sample_data):
        """Test clustering with specified k."""
        labels, info = run_clustering(sample_data, method='kmeans', n_clusters=5, auto_k=False)
        
        assert info['n_clusters'] == 5
        assert len(set(labels)) == 5


class TestRunComparison:
    """Test cases for method comparison."""
    
    @pytest.fixture
    def sample_data(self):
        """Generate sample data."""
        X, _ = make_blobs(n_samples=100, centers=3, random_state=42)
        return X
    
    def test_run_comparison_all_methods(self, sample_data):
        """Test that comparison runs all methods."""
        results = run_comparison(sample_data, n_clusters=3)
        
        assert 'kmeans' in results
        assert 'gmm' in results
        assert 'hierarchical' in results
        assert 'best_method' in results
    
    def test_run_comparison_consistent_labels(self, sample_data):
        """Test that all methods produce valid labels."""
        results = run_comparison(sample_data, n_clusters=3)
        
        for method in ['kmeans', 'gmm', 'hierarchical']:
            labels = results[method]['labels']
            assert len(labels) == len(sample_data)


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
