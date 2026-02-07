"""
Segmentation module for Customer360.
Assigns segment labels and marketing recommendations based on cluster characteristics.
"""
import pandas as pd
import numpy as np
from typing import Dict, List, Any, Tuple
import logging

logger = logging.getLogger(__name__)


# Segment definitions based on RFM characteristics
SEGMENT_DEFINITIONS = {
    'champions': {
        'label': 'Champions',
        'description': 'Best customers - high recency, frequency, and monetary value',
        'criteria': {'R': 'high', 'F': 'high', 'M': 'high'},
        'actions': [
            'Reward with exclusive loyalty programs and VIP treatment',
            'Offer early access to new products or services',
            'Request referrals and testimonials',
            'Provide personalized premium experiences'
        ]
    },
    'loyal_customers': {
        'label': 'Loyal Customers',
        'description': 'Regular customers with good frequency and spending',
        'criteria': {'R': 'high', 'F': 'high', 'M': 'medium'},
        'actions': [
            'Upsell and cross-sell higher value products',
            'Implement loyalty reward programs',
            'Send personalized recommendations',
            'Offer bundle deals and exclusive discounts'
        ]
    },
    'potential_loyalists': {
        'label': 'Potential Loyalists',
        'description': 'Recent customers with moderate frequency showing promise',
        'criteria': {'R': 'high', 'F': 'medium', 'M': 'medium'},
        'actions': [
            'Offer membership programs and loyalty incentives',
            'Provide excellent customer service to build trust',
            'Send targeted product recommendations',
            'Create engagement through email campaigns'
        ]
    },
    'new_customers': {
        'label': 'New Customers',
        'description': 'Recently acquired customers with few transactions',
        'criteria': {'R': 'high', 'F': 'low', 'M': 'low'},
        'actions': [
            'Welcome with onboarding campaigns and first-purchase offers',
            'Provide excellent first experience to encourage repeat purchases',
            'Send educational content about products/services',
            'Offer incentives for second purchase'
        ]
    },
    'promising': {
        'label': 'Promising',
        'description': 'Recent shoppers with moderate spending potential',
        'criteria': {'R': 'high', 'F': 'low', 'M': 'medium'},
        'actions': [
            'Engage with targeted promotions to increase frequency',
            'Create awareness of full product range',
            'Implement re-engagement campaigns',
            'Offer time-limited discounts'
        ]
    },
    'need_attention': {
        'label': 'Need Attention',
        'description': 'Previously active customers showing declining engagement',
        'criteria': {'R': 'medium', 'F': 'medium', 'M': 'medium'},
        'actions': [
            'Send personalized win-back offers',
            'Reach out with feedback surveys to understand needs',
            'Offer limited-time incentives to re-engage',
            'Highlight new products or improvements'
        ]
    },
    'about_to_sleep': {
        'label': 'About to Sleep',
        'description': 'Customers who haven\'t purchased recently but had good history',
        'criteria': {'R': 'medium', 'F': 'low', 'M': 'low'},
        'actions': [
            'Send reactivation campaigns with urgency',
            'Offer special "we miss you" discounts',
            'Share new features or products since last visit',
            'Create FOMO with limited-time offers'
        ]
    },
    'at_risk': {
        'label': 'At Risk',
        'description': 'Previously valuable customers who haven\'t purchased in a while',
        'criteria': {'R': 'low', 'F': 'high', 'M': 'high'},
        'actions': [
            'Launch urgent win-back campaigns with strong incentives',
            'Personal outreach from customer service',
            'Offer significant discounts or free shipping',
            'Conduct churn analysis to understand reasons'
        ]
    },
    'hibernating': {
        'label': 'Hibernating',
        'description': 'Low activity customers who made purchases long ago',
        'criteria': {'R': 'low', 'F': 'low', 'M': 'low'},
        'actions': [
            'Send re-engagement emails with major incentives',
            'Consider removing from active marketing to save costs',
            'Run reactivation campaigns periodically',
            'Offer "comeback" special deals'
        ]
    },
    'lost': {
        'label': 'Lost Customers',
        'description': 'Customers who haven\'t engaged for a very long time',
        'criteria': {'R': 'very_low', 'F': 'low', 'M': 'low'},
        'actions': [
            'Attempt one final win-back campaign',
            'Consider for retention cost analysis',
            'Learn from their behavior for future prevention',
            'Remove from regular marketing to reduce costs'
        ]
    }
}


def classify_rfm_level(value: float, percentiles: Dict[str, float]) -> str:
    """
    Classify a value into low/medium/high based on percentiles.
    
    Args:
        value: The value to classify
        percentiles: Dict with 'p25', 'p50', 'p75' percentile values
        
    Returns:
        'very_low', 'low', 'medium', or 'high'
    """
    if value <= percentiles['p25']:
        return 'very_low' if value <= percentiles['p25'] / 2 else 'low'
    elif value <= percentiles['p50']:
        return 'low'
    elif value <= percentiles['p75']:
        return 'medium'
    else:
        return 'high'


def label_segment(
    avg_recency: float,
    avg_frequency: float,
    avg_monetary: float,
    recency_percentiles: Dict[str, float],
    frequency_percentiles: Dict[str, float],
    monetary_percentiles: Dict[str, float]
) -> Tuple[str, str, List[str]]:
    """
    Assign a segment label based on cluster's average RFM values.
    
    Args:
        avg_recency: Cluster's average recency
        avg_frequency: Cluster's average frequency
        avg_monetary: Cluster's average monetary value
        *_percentiles: Population percentiles for comparison
        
    Returns:
        Tuple of (segment_key, segment_label, recommended_actions)
    """
    # Note: For recency, lower is better, so we invert the classification
    r_level = classify_rfm_level(avg_recency, recency_percentiles)
    # Invert recency level (low recency = high engagement)
    r_inverted = {'very_low': 'high', 'low': 'high', 'medium': 'medium', 'high': 'low'}[r_level]
    
    f_level = classify_rfm_level(avg_frequency, frequency_percentiles)
    m_level = classify_rfm_level(avg_monetary, monetary_percentiles)
    
    # Match to segment definitions
    if r_inverted == 'high' and f_level == 'high' and m_level == 'high':
        segment_key = 'champions'
    elif r_inverted == 'high' and f_level == 'high':
        segment_key = 'loyal_customers'
    elif r_inverted == 'high' and f_level in ['medium', 'low'] and m_level in ['medium', 'high']:
        segment_key = 'potential_loyalists'
    elif r_inverted == 'high' and f_level in ['very_low', 'low'] and m_level in ['very_low', 'low']:
        segment_key = 'new_customers'
    elif r_inverted == 'high' and f_level == 'low':
        segment_key = 'promising'
    elif r_inverted == 'medium' and f_level == 'medium':
        segment_key = 'need_attention'
    elif r_inverted == 'medium' and f_level == 'low':
        segment_key = 'about_to_sleep'
    elif r_inverted == 'low' and (f_level == 'high' or m_level == 'high'):
        segment_key = 'at_risk'
    elif r_inverted == 'low' and f_level == 'low' and m_level in ['very_low', 'low']:
        segment_key = 'hibernating'
    else:
        segment_key = 'need_attention'  # Default
    
    segment = SEGMENT_DEFINITIONS[segment_key]
    return segment_key, segment['label'], segment['actions']


def analyze_clusters(
    rfm: pd.DataFrame,
    labels: np.ndarray
) -> List[Dict[str, Any]]:
    """
    Analyze clusters and assign segment labels with recommendations.
    
    Args:
        rfm: DataFrame with RFM values and customer IDs
        labels: Cluster labels for each customer
        
    Returns:
        List of cluster analysis dictionaries
    """
    rfm = rfm.copy()
    rfm['cluster'] = labels
    
    # Calculate population percentiles for reference
    recency_percentiles = {
        'p25': rfm['recency'].quantile(0.25),
        'p50': rfm['recency'].quantile(0.50),
        'p75': rfm['recency'].quantile(0.75)
    }
    frequency_percentiles = {
        'p25': rfm['frequency'].quantile(0.25),
        'p50': rfm['frequency'].quantile(0.50),
        'p75': rfm['frequency'].quantile(0.75)
    }
    monetary_percentiles = {
        'p25': rfm['monetary'].quantile(0.25),
        'p50': rfm['monetary'].quantile(0.50),
        'p75': rfm['monetary'].quantile(0.75)
    }
    
    total_customers = len(rfm)
    clusters = []
    
    for cluster_id in sorted(rfm['cluster'].unique()):
        cluster_data = rfm[rfm['cluster'] == cluster_id]
        
        # Calculate cluster statistics
        num_customers = len(cluster_data)
        avg_recency = cluster_data['recency'].mean()
        avg_frequency = cluster_data['frequency'].mean()
        avg_monetary = cluster_data['monetary'].mean()
        
        # Get segment label and actions
        segment_key, segment_label, actions = label_segment(
            avg_recency, avg_frequency, avg_monetary,
            recency_percentiles, frequency_percentiles, monetary_percentiles
        )
        
        cluster_info = {
            'cluster_id': int(cluster_id),
            'segment_key': segment_key,
            'segment_label': segment_label,
            'num_customers': num_customers,
            'percentage': round(num_customers / total_customers * 100, 1),
            'avg_recency': round(avg_recency, 1),
            'avg_frequency': round(avg_frequency, 1),
            'avg_monetary': round(avg_monetary, 2),
            'total_revenue': round(cluster_data['monetary'].sum(), 2),
            'std_recency': round(cluster_data['recency'].std(), 1),
            'std_frequency': round(cluster_data['frequency'].std(), 1),
            'std_monetary': round(cluster_data['monetary'].std(), 2),
            'min_recency': int(cluster_data['recency'].min()),
            'max_recency': int(cluster_data['recency'].max()),
            'min_frequency': int(cluster_data['frequency'].min()),
            'max_frequency': int(cluster_data['frequency'].max()),
            'min_monetary': round(cluster_data['monetary'].min(), 2),
            'max_monetary': round(cluster_data['monetary'].max(), 2),
            'recommended_actions': actions
        }
        
        clusters.append(cluster_info)
    
    # Sort by total revenue (descending) for business relevance
    clusters.sort(key=lambda x: x['total_revenue'], reverse=True)
    
    return clusters


def get_cluster_sizes(labels: np.ndarray) -> Dict[str, int]:
    """
    Get the size of each cluster.
    
    Args:
        labels: Cluster labels array
        
    Returns:
        Dictionary mapping cluster ID to size
    """
    unique, counts = np.unique(labels, return_counts=True)
    return {f"Cluster {int(k)}": int(v) for k, v in zip(unique, counts)}


def get_segment_summary(segments: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Generate a high-level summary of all segments.
    
    Args:
        segments: List of segment analysis dictionaries
        
    Returns:
        Summary dictionary with key insights
    """
    total_customers = sum(s['num_customers'] for s in segments)
    total_revenue = sum(s['total_revenue'] for s in segments)
    
    # Find key segments
    highest_value = max(segments, key=lambda x: x['avg_monetary'])
    most_loyal = max(segments, key=lambda x: x['avg_frequency'])
    at_risk = [s for s in segments if 'risk' in s['segment_key'].lower() or 'lost' in s['segment_key'].lower()]
    
    summary = {
        'total_customers': total_customers,
        'total_revenue': round(total_revenue, 2),
        'num_segments': len(segments),
        'highest_value_segment': {
            'label': highest_value['segment_label'],
            'avg_monetary': highest_value['avg_monetary'],
            'percentage': highest_value['percentage']
        },
        'most_loyal_segment': {
            'label': most_loyal['segment_label'],
            'avg_frequency': most_loyal['avg_frequency'],
            'percentage': most_loyal['percentage']
        },
        'at_risk_customers': sum(s['num_customers'] for s in at_risk),
        'at_risk_revenue': sum(s['total_revenue'] for s in at_risk),
        'segments_overview': [
            {
                'label': s['segment_label'],
                'customers': s['num_customers'],
                'percentage': s['percentage'],
                'revenue': s['total_revenue']
            }
            for s in segments
        ]
    }
    
    return summary
