"""
Clustering module for Customer360.
Implements K-Means, GMM, and Hierarchical clustering with evaluation metrics.
"""
import numpy as np
import pandas as pd
from typing import Tuple, Dict, Any, List, Optional
from sklearn.cluster import KMeans, AgglomerativeClustering
from sklearn.mixture import GaussianMixture
from sklearn.metrics import silhouette_score, calinski_harabasz_score, davies_bouldin_score
import logging

from ..config import DEFAULT_K_RANGE, RANDOM_STATE

logger = logging.getLogger(__name__)


def find_optimal_k(
    X: np.ndarray,
    k_range: Tuple[int, int] = DEFAULT_K_RANGE,
    method: str = 'kmeans'
) -> Dict[str, Any]:
    """
    Find optimal number of clusters using Elbow and Silhouette methods.
    
    Args:
        X: Normalized feature matrix (n_samples, n_features)
        k_range: Tuple of (min_k, max_k) to try
        method: 'kmeans' or 'gmm'
        
    Returns:
        Dictionary with optimal k and evaluation metrics
    """
    min_k, max_k = k_range
    k_values = list(range(min_k, max_k + 1))
    
    inertias = []  # For elbow method (K-Means)
    silhouette_scores = []
    calinski_scores = []
    davies_scores = []
    bic_scores = []  # For GMM
    
    for k in k_values:
        if method == 'kmeans':
            model = KMeans(n_clusters=k, random_state=RANDOM_STATE, n_init=10)
            labels = model.fit_predict(X)
            inertias.append(model.inertia_)
        elif method == 'gmm':
            model = GaussianMixture(n_components=k, random_state=RANDOM_STATE)
            labels = model.fit_predict(X)
            bic_scores.append(model.bic(X))
        else:
            raise ValueError(f"Unknown method: {method}")
        
        # Calculate clustering quality metrics
        if len(set(labels)) > 1:  # Need at least 2 clusters for silhouette
            silhouette_scores.append(silhouette_score(X, labels))
            calinski_scores.append(calinski_harabasz_score(X, labels))
            davies_scores.append(davies_bouldin_score(X, labels))
        else:
            silhouette_scores.append(0)
            calinski_scores.append(0)
            davies_scores.append(float('inf'))
    
    # Find optimal k using silhouette score (higher is better)
    optimal_k_silhouette = k_values[np.argmax(silhouette_scores)]
    
    # Find elbow point using the second derivative (for K-Means)
    optimal_k_elbow = optimal_k_silhouette
    if method == 'kmeans' and len(inertias) >= 3:
        # Calculate rate of change
        diffs = np.diff(inertias)
        diffs2 = np.diff(diffs)
        if len(diffs2) > 0:
            elbow_idx = np.argmax(diffs2) + 2  # +2 because of two diffs
            optimal_k_elbow = k_values[min(elbow_idx, len(k_values) - 1)]
    
    # For GMM, use BIC (lower is better)
    optimal_k_bic = optimal_k_silhouette
    if method == 'gmm' and bic_scores:
        optimal_k_bic = k_values[np.argmin(bic_scores)]
    
    # Use a consensus approach: prefer silhouette but consider elbow
    optimal_k = optimal_k_silhouette
    
    return {
        'optimal_k': optimal_k,
        'optimal_k_silhouette': optimal_k_silhouette,
        'optimal_k_elbow': optimal_k_elbow if method == 'kmeans' else None,
        'optimal_k_bic': optimal_k_bic if method == 'gmm' else None,
        'k_values': k_values,
        'inertias': inertias if method == 'kmeans' else None,
        'silhouette_scores': silhouette_scores,
        'calinski_scores': calinski_scores,
        'davies_scores': davies_scores,
        'bic_scores': bic_scores if method == 'gmm' else None
    }


def run_kmeans(
    X: np.ndarray,
    n_clusters: int,
    random_state: int = RANDOM_STATE
) -> Tuple[np.ndarray, Dict[str, Any]]:
    """
    Run K-Means clustering.
    
    Args:
        X: Feature matrix
        n_clusters: Number of clusters
        random_state: Random seed
        
    Returns:
        Tuple of (cluster labels, model info dict)
    """
    model = KMeans(
        n_clusters=n_clusters,
        random_state=random_state,
        n_init=10,
        max_iter=300
    )
    labels = model.fit_predict(X)
    
    info = {
        'method': 'kmeans',
        'n_clusters': n_clusters,
        'inertia': float(model.inertia_),
        'n_iter': model.n_iter_,
        'cluster_centers': model.cluster_centers_.tolist()
    }
    
    # Add evaluation metrics
    if len(set(labels)) > 1:
        info['silhouette_score'] = float(silhouette_score(X, labels))
        info['calinski_score'] = float(calinski_harabasz_score(X, labels))
        info['davies_bouldin_score'] = float(davies_bouldin_score(X, labels))
    
    logger.info(f"K-Means: {n_clusters} clusters, silhouette={info.get('silhouette_score', 'N/A'):.3f}")
    
    return labels, info


def run_gmm(
    X: np.ndarray,
    n_components: int,
    random_state: int = RANDOM_STATE
) -> Tuple[np.ndarray, Dict[str, Any]]:
    """
    Run Gaussian Mixture Model clustering.
    
    Args:
        X: Feature matrix
        n_components: Number of components/clusters
        random_state: Random seed
        
    Returns:
        Tuple of (cluster labels, model info dict)
    """
    model = GaussianMixture(
        n_components=n_components,
        random_state=random_state,
        covariance_type='full',
        n_init=3,
        max_iter=200
    )
    labels = model.fit_predict(X)
    probabilities = model.predict_proba(X)
    
    info = {
        'method': 'gmm',
        'n_clusters': n_components,
        'bic': float(model.bic(X)),
        'aic': float(model.aic(X)),
        'n_iter': model.n_iter_,
        'converged': model.converged_,
        'weights': model.weights_.tolist()
    }
    
    # Add evaluation metrics
    if len(set(labels)) > 1:
        info['silhouette_score'] = float(silhouette_score(X, labels))
        info['calinski_score'] = float(calinski_harabasz_score(X, labels))
        info['davies_bouldin_score'] = float(davies_bouldin_score(X, labels))
    
    logger.info(f"GMM: {n_components} components, silhouette={info.get('silhouette_score', 'N/A'):.3f}")
    
    return labels, info


def run_hierarchical(
    X: np.ndarray,
    n_clusters: int,
    linkage: str = 'ward'
) -> Tuple[np.ndarray, Dict[str, Any]]:
    """
    Run Hierarchical/Agglomerative clustering.
    
    Args:
        X: Feature matrix
        n_clusters: Number of clusters
        linkage: Linkage type ('ward', 'complete', 'average', 'single')
        
    Returns:
        Tuple of (cluster labels, model info dict)
    """
    model = AgglomerativeClustering(
        n_clusters=n_clusters,
        linkage=linkage
    )
    labels = model.fit_predict(X)
    
    info = {
        'method': 'hierarchical',
        'n_clusters': n_clusters,
        'linkage': linkage,
        'n_leaves': model.n_leaves_,
        'n_connected_components': model.n_connected_components_
    }
    
    # Add evaluation metrics
    if len(set(labels)) > 1:
        info['silhouette_score'] = float(silhouette_score(X, labels))
        info['calinski_score'] = float(calinski_harabasz_score(X, labels))
        info['davies_bouldin_score'] = float(davies_bouldin_score(X, labels))
    
    logger.info(f"Hierarchical: {n_clusters} clusters, silhouette={info.get('silhouette_score', 'N/A'):.3f}")
    
    return labels, info


def run_clustering(
    X: np.ndarray,
    method: str = 'kmeans',
    n_clusters: Optional[int] = None,
    auto_k: bool = True
) -> Tuple[np.ndarray, Dict[str, Any]]:
    """
    Run clustering with automatic or specified number of clusters.
    
    Args:
        X: Normalized feature matrix
        method: 'kmeans', 'gmm', or 'hierarchical'
        n_clusters: Number of clusters (if None, determined automatically)
        auto_k: Whether to automatically find optimal k
        
    Returns:
        Tuple of (cluster labels, full info dict)
    """
    info = {'method': method}
    
    # Find optimal k if not specified
    if n_clusters is None and auto_k:
        k_analysis = find_optimal_k(X, method=method if method != 'hierarchical' else 'kmeans')
        n_clusters = k_analysis['optimal_k']
        info['k_analysis'] = k_analysis
    elif n_clusters is None:
        n_clusters = 4  # Default
    
    info['n_clusters'] = n_clusters
    
    # Run the selected clustering method
    if method == 'kmeans':
        labels, model_info = run_kmeans(X, n_clusters)
    elif method == 'gmm':
        labels, model_info = run_gmm(X, n_clusters)
    elif method == 'hierarchical':
        labels, model_info = run_hierarchical(X, n_clusters)
    else:
        raise ValueError(f"Unknown clustering method: {method}")
    
    info.update(model_info)
    
    return labels, info


def run_comparison(
    X: np.ndarray,
    n_clusters: Optional[int] = None
) -> Dict[str, Dict[str, Any]]:
    """
    Run all clustering methods and compare results.
    
    Args:
        X: Normalized feature matrix
        n_clusters: Number of clusters (if None, use optimal for each)
        
    Returns:
        Dictionary with results for each method
    """
    results = {}
    
    # Find optimal k using K-Means analysis
    k_analysis = find_optimal_k(X, method='kmeans')
    optimal_k = n_clusters or k_analysis['optimal_k']
    
    results['k_analysis'] = k_analysis
    
    # Run each method
    for method in ['kmeans', 'gmm', 'hierarchical']:
        labels, info = run_clustering(X, method=method, n_clusters=optimal_k, auto_k=False)
        results[method] = {
            'labels': labels.tolist(),
            'info': info
        }
    
    # Determine best method based on silhouette score
    best_method = max(
        ['kmeans', 'gmm', 'hierarchical'],
        key=lambda m: results[m]['info'].get('silhouette_score', 0)
    )
    results['best_method'] = best_method
    
    return results
