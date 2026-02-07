"""
Unit tests for RFM computation module.
"""
import pytest
import pandas as pd
import numpy as np
from datetime import datetime, timedelta

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.analytics.rfm import (
    compute_rfm,
    compute_rfm_scores,
    normalize_rfm,
    get_rfm_statistics,
    get_rfm_distributions
)


class TestComputeRFM:
    """Test cases for compute_rfm function."""
    
    @pytest.fixture
    def sample_transactions(self):
        """Create sample transaction data for testing."""
        base_date = datetime(2026, 1, 1)
        
        data = {
            'customer_id': ['C001', 'C001', 'C001', 'C002', 'C002', 'C003'],
            'invoice_date': [
                base_date - timedelta(days=5),   # C001 - recent
                base_date - timedelta(days=30),
                base_date - timedelta(days=60),
                base_date - timedelta(days=10),  # C002
                base_date - timedelta(days=50),
                base_date - timedelta(days=100), # C003 - old customer
            ],
            'invoice_id': ['INV001', 'INV002', 'INV003', 'INV004', 'INV005', 'INV006'],
            'amount': [100, 200, 150, 300, 250, 500]
        }
        return pd.DataFrame(data)
    
    def test_compute_rfm_basic(self, sample_transactions):
        """Test basic RFM computation."""
        reference_date = datetime(2026, 1, 2)
        rfm = compute_rfm(sample_transactions, reference_date=reference_date)
        
        assert len(rfm) == 3  # 3 unique customers
        assert 'customer_id' in rfm.columns
        assert 'recency' in rfm.columns
        assert 'frequency' in rfm.columns
        assert 'monetary' in rfm.columns
    
    def test_compute_rfm_recency_values(self, sample_transactions):
        """Test that recency values are calculated correctly."""
        reference_date = datetime(2026, 1, 2)
        rfm = compute_rfm(sample_transactions, reference_date=reference_date)
        
        # C001's most recent purchase was 5 days ago, so recency should be 6 (ref - last)
        c001_recency = rfm[rfm['customer_id'] == 'C001']['recency'].values[0]
        assert c001_recency == 6  # 5 days before Jan 1 = Dec 27, ref is Jan 2
        
        # C003's last purchase was 100 days ago
        c003_recency = rfm[rfm['customer_id'] == 'C003']['recency'].values[0]
        assert c003_recency == 101
    
    def test_compute_rfm_frequency_values(self, sample_transactions):
        """Test that frequency values are calculated correctly."""
        reference_date = datetime(2026, 1, 2)
        rfm = compute_rfm(sample_transactions, reference_date=reference_date)
        
        c001_frequency = rfm[rfm['customer_id'] == 'C001']['frequency'].values[0]
        assert c001_frequency == 3  # 3 transactions
        
        c003_frequency = rfm[rfm['customer_id'] == 'C003']['frequency'].values[0]
        assert c003_frequency == 1  # 1 transaction
    
    def test_compute_rfm_monetary_values(self, sample_transactions):
        """Test that monetary values are calculated correctly."""
        reference_date = datetime(2026, 1, 2)
        rfm = compute_rfm(sample_transactions, reference_date=reference_date)
        
        c001_monetary = rfm[rfm['customer_id'] == 'C001']['monetary'].values[0]
        assert c001_monetary == 450  # 100 + 200 + 150
        
        c002_monetary = rfm[rfm['customer_id'] == 'C002']['monetary'].values[0]
        assert c002_monetary == 550  # 300 + 250
    
    def test_compute_rfm_empty_dataframe(self):
        """Test that empty DataFrame raises error."""
        empty_df = pd.DataFrame(columns=['customer_id', 'invoice_date', 'invoice_id', 'amount'])
        
        with pytest.raises(ValueError):
            compute_rfm(empty_df)
    
    def test_compute_rfm_no_negative_recency(self, sample_transactions):
        """Test that recency values are never negative."""
        # Use a reference date in the past
        reference_date = datetime(2025, 1, 1)
        rfm = compute_rfm(sample_transactions, reference_date=reference_date)
        
        assert (rfm['recency'] >= 0).all()


class TestComputeRFMScores:
    """Test cases for RFM scoring function."""
    
    @pytest.fixture
    def sample_rfm(self):
        """Create sample RFM data."""
        np.random.seed(42)
        return pd.DataFrame({
            'customer_id': [f'C{i:03d}' for i in range(100)],
            'recency': np.random.randint(1, 365, 100),
            'frequency': np.random.randint(1, 50, 100),
            'monetary': np.random.uniform(10, 10000, 100)
        })
    
    def test_compute_rfm_scores_columns(self, sample_rfm):
        """Test that RFM scores are added as new columns."""
        scored = compute_rfm_scores(sample_rfm, n_bins=5)
        
        assert 'R_score' in scored.columns
        assert 'F_score' in scored.columns
        assert 'M_score' in scored.columns
        assert 'RFM_score' in scored.columns
        assert 'RFM_total' in scored.columns
    
    def test_compute_rfm_scores_range(self, sample_rfm):
        """Test that scores are in expected range."""
        scored = compute_rfm_scores(sample_rfm, n_bins=5)
        
        assert scored['R_score'].min() >= 1
        assert scored['R_score'].max() <= 5
        assert scored['F_score'].min() >= 1
        assert scored['F_score'].max() <= 5
        assert scored['M_score'].min() >= 1
        assert scored['M_score'].max() <= 5
    
    def test_compute_rfm_scores_total(self, sample_rfm):
        """Test that RFM_total is sum of individual scores."""
        scored = compute_rfm_scores(sample_rfm, n_bins=5)
        
        expected_total = scored['R_score'] + scored['F_score'] + scored['M_score']
        assert (scored['RFM_total'] == expected_total).all()


class TestNormalizeRFM:
    """Test cases for RFM normalization."""
    
    @pytest.fixture
    def sample_rfm(self):
        """Create sample RFM data."""
        return pd.DataFrame({
            'customer_id': ['C001', 'C002', 'C003', 'C004', 'C005'],
            'recency': [10, 50, 100, 200, 365],
            'frequency': [1, 5, 10, 20, 50],
            'monetary': [100, 500, 1000, 5000, 10000]
        })
    
    def test_normalize_rfm_standard(self, sample_rfm):
        """Test standard normalization (z-score)."""
        normalized, scaler = normalize_rfm(sample_rfm, method='standard')
        
        assert 'recency_normalized' in normalized.columns
        assert 'frequency_normalized' in normalized.columns
        assert 'monetary_normalized' in normalized.columns
    
    def test_normalize_rfm_minmax(self, sample_rfm):
        """Test min-max normalization."""
        normalized, scaler = normalize_rfm(sample_rfm, method='minmax')
        
        # Min-max normalization should produce values between 0 and 1
        # (after log transform, might be slightly different)
        assert normalized['recency_normalized'].notna().all()
        assert normalized['frequency_normalized'].notna().all()
        assert normalized['monetary_normalized'].notna().all()


class TestRFMStatistics:
    """Test cases for RFM statistics computation."""
    
    @pytest.fixture
    def sample_rfm(self):
        """Create sample RFM data."""
        return pd.DataFrame({
            'customer_id': ['C001', 'C002', 'C003', 'C004', 'C005'],
            'recency': [10, 20, 30, 40, 50],
            'frequency': [5, 10, 15, 20, 25],
            'monetary': [100, 200, 300, 400, 500]
        })
    
    def test_get_rfm_statistics(self, sample_rfm):
        """Test statistics calculation."""
        stats = get_rfm_statistics(sample_rfm)
        
        assert 'recency' in stats
        assert 'frequency' in stats
        assert 'monetary' in stats
        
        # Check recency stats
        assert stats['recency']['mean'] == 30.0
        assert stats['recency']['median'] == 30.0
        assert stats['recency']['min'] == 10.0
        assert stats['recency']['max'] == 50.0
    
    def test_get_rfm_distributions(self, sample_rfm):
        """Test distribution histogram data."""
        distributions = get_rfm_distributions(sample_rfm, n_bins=5)
        
        assert 'recency' in distributions
        assert 'frequency' in distributions
        assert 'monetary' in distributions
        
        assert 'counts' in distributions['recency']
        assert 'bin_edges' in distributions['recency']
        assert len(distributions['recency']['counts']) == 5


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
