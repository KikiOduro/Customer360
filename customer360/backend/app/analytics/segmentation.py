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
        'label': 'Your Star Customers',
        'short_name': 'Your Star Customers',
        'emoji': '⭐',
        'description': 'These are your best customers right now. They bought recently, they come back often, and they spend strongly, so your main job is to keep them happy and make them feel valued.',
        'criteria': {'R': 'high', 'F': 'high', 'M': 'high'},
        'actions': [
            'Send a personal thank-you message and give them a small loyalty reward',
            'Let them see new products or services first before the general public',
            'Ask them for referrals, reviews, or recommendations to friends',
            'Give them a more personal service experience so they stay loyal'
        ]
    },
    'loyal_customers': {
        'label': 'Your Faithful Regulars',
        'short_name': 'Your Faithful Regulars',
        'emoji': '💙',
        'description': 'These customers keep returning and are already building a steady habit with your business. They may not be your biggest spenders yet, but they are reliable and worth growing.',
        'criteria': {'R': 'high', 'F': 'high', 'M': 'medium'},
        'actions': [
            'Offer bundle deals or slightly higher-value items they are likely to buy',
            'Give simple loyalty rewards that encourage them to keep coming back',
            'Send product suggestions based on what they usually buy',
            'Share a small customer-only discount to strengthen the relationship'
        ]
    },
    'potential_loyalists': {
        'label': 'Almost Regulars',
        'short_name': 'Almost Regulars',
        'emoji': '📈',
        'description': 'These customers are showing signs that they could become regular buyers. They have bought fairly recently, but they still need a little push to return more often.',
        'criteria': {'R': 'high', 'F': 'medium', 'M': 'medium'},
        'actions': [
            'Send a follow-up offer soon so they have a reason to buy again',
            'Give them very good service now so trust builds quickly',
            'Recommend products that match what they already bought',
            'Invite them into a simple loyalty or repeat-buyer offer'
        ]
    },
    'new_customers': {
        'label': 'Fresh Faces',
        'short_name': 'Fresh Faces',
        'emoji': '🌱',
        'description': 'These are new customers who have only just started buying from you. The most important thing is to give them a good first impression so they come back for a second purchase.',
        'criteria': {'R': 'high', 'F': 'low', 'M': 'low'},
        'actions': [
            'Send a warm welcome message and thank them for buying from you',
            'Make the next purchase easy by sharing what to buy next or how to order again',
            'Explain your products or services in simple terms if they are still new to your brand',
            'Offer a small second-purchase incentive to encourage a return'
        ]
    },
    'promising': {
        'label': 'Showing Interest',
        'short_name': 'Showing Interest',
        'emoji': '✨',
        'description': 'These customers are interested in your business and have bought recently, but they have not yet become steady repeat buyers. Keep them engaged before they lose interest.',
        'criteria': {'R': 'high', 'F': 'low', 'M': 'medium'},
        'actions': [
            'Send a quick promo or product reminder while your business is still fresh in their mind',
            'Show them more of your product range so they know what else they can buy',
            'Use a short time-bound offer to encourage another purchase',
            'Follow up through WhatsApp, SMS, or a personal call'
        ]
    },
    'need_attention': {
        'label': 'Slipping Away Slowly',
        'short_name': 'Slipping Away Slowly',
        'emoji': '📣',
        'description': 'These customers have started slowing down. They were buying before, but they are not returning as strongly now, so you should follow up before they fully disappear.',
        'criteria': {'R': 'medium', 'F': 'medium', 'M': 'medium'},
        'actions': [
            'Send a friendly check-in message and remind them what is new',
            'Ask simple feedback questions to understand why they slowed down',
            'Give a short comeback offer to encourage them to buy again',
            'Highlight improvements, new arrivals, or fresh stock'
        ]
    },
    'about_to_sleep': {
        'label': 'About to Forget You',
        'short_name': 'About to Forget You',
        'emoji': '🌙',
        'description': 'These customers have not bought in a while and may soon forget your business if you stay quiet. A timely reminder or comeback offer can help bring them back.',
        'criteria': {'R': 'medium', 'F': 'low', 'M': 'low'},
        'actions': [
            'Send a “we miss you” WhatsApp message with a clear comeback offer',
            'Remind them what has changed since their last purchase',
            'Use a limited-time deal so they feel a reason to respond now',
            'Keep the message short, warm, and easy to act on'
        ]
    },
    'at_risk': {
        'label': 'Danger Zone',
        'short_name': 'Danger Zone',
        'emoji': '⚠',
        'description': 'These customers used to be valuable, but they have stayed away for too long. This is an urgent group because you may lose meaningful revenue if you do not act quickly.',
        'criteria': {'R': 'low', 'F': 'high', 'M': 'high'},
        'actions': [
            'Reach out personally and give a strong reason to return this week',
            'Offer a serious win-back incentive if the numbers justify it',
            'Ask why they stopped buying and remove any service issue quickly',
            'Prioritize this group before spending too much effort on weaker inactive customers'
        ]
    },
    'hibernating': {
        'label': 'Sleeping Customers',
        'short_name': 'Sleeping Customers',
        'emoji': '💤',
        'description': 'These customers have been quiet for a long time and are not showing strong buying activity. Try low-cost reactivation, but do not spend too much money chasing them.',
        'criteria': {'R': 'low', 'F': 'low', 'M': 'low'},
        'actions': [
            'Send a low-cost reactivation message or comeback promo',
            'If they still do not respond, reduce how often you market to them',
            'Check this group from time to time instead of spending too much weekly effort',
            'Use simple broad offers instead of expensive one-on-one outreach'
        ]
    },
    'lost': {
        'label': 'Gone Customers',
        'short_name': 'Gone Customers',
        'emoji': '🧊',
        'description': 'These customers have been gone for a very long time and are unlikely to return easily. Try one final win-back message, then put more effort into the stronger active groups.',
        'criteria': {'R': 'very_low', 'F': 'low', 'M': 'low'},
        'actions': [
            'Try one final comeback message or offer',
            'If there is no response, stop spending regular campaign budget on this group',
            'Look for patterns that explain why they left so you can reduce future losses',
            'Shift most of your time and money toward active and growing customer groups'
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
            'segment_short_name': SEGMENT_DEFINITIONS[segment_key].get('short_name', segment_label),
            'segment_emoji': SEGMENT_DEFINITIONS[segment_key].get('emoji', '👥'),
            'description': SEGMENT_DEFINITIONS[segment_key]['description'],
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
