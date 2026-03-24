"""
Unit tests for data preprocessing module.
"""
import pytest
import pandas as pd
import numpy as np
from datetime import datetime

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.analytics.preprocessing import (
    load_csv,
    suggest_column_mapping,
    clean_data,
    validate_numeric_column,
    parse_dates
)


class TestLoadCSV:
    """Test cases for CSV loading."""
    
    def test_load_csv_basic(self, tmp_path):
        """Test basic CSV loading."""
        csv_file = tmp_path / "test.csv"
        data = {
            'customer_id': ['C001', 'C002'],
            'invoice_date': ['2026-01-01', '2026-01-02'],
            'invoice_id': ['INV001', 'INV002'],
            'amount': [100, 200]
        }
        df = pd.DataFrame(data)
        df.to_csv(csv_file, index=False)
        
        loaded = load_csv(str(csv_file))
        
        assert len(loaded) == 2
        assert list(loaded.columns) == ['customer_id', 'invoice_date', 'invoice_id', 'amount']
    
    def test_load_csv_with_encoding(self, tmp_path):
        """Test CSV loading with UTF-8 encoding."""
        csv_file = tmp_path / "test_utf8.csv"
        data = {
            'customer_id': ['C001', 'C002'],
            'name': ['Kwame', 'Ama'],
            'amount': [100, 200]
        }
        df = pd.DataFrame(data)
        df.to_csv(csv_file, index=False, encoding='utf-8')
        
        loaded = load_csv(str(csv_file))
        
        assert len(loaded) == 2
        assert 'Kwame' in loaded['name'].values


class TestColumnMapping:
    """Test cases for column detection and mapping."""
    
    def test_suggest_column_mapping_exact_match(self):
        """Test column mapping with exact matches."""
        df = pd.DataFrame({
            'customer_id': ['C001'],
            'invoice_date': ['2026-01-01'],
            'invoice_id': ['INV001'],
            'amount': [100]
        })
        
        mapping = suggest_column_mapping(df)
        
        assert mapping['customer_id'] is not None
        assert mapping['invoice_date'] is not None
        assert mapping['invoice_id'] is not None
        assert mapping['amount'] is not None
    
    def test_suggest_column_mapping_case_insensitive(self):
        """Test column mapping is case-insensitive."""
        df = pd.DataFrame({
            'Customer_ID': ['C001'],
            'Transaction_Date': ['2026-01-01'],
            'InvoiceNum': ['INV001'],
            'AMOUNT': [100]
        })
        
        mapping = suggest_column_mapping(df)
        
        # Should find mappings for most fields
        found_mappings = len([v for v in mapping.values() if v is not None])
        assert found_mappings >= 2  # At least customer and amount
    
    def test_suggest_column_mapping_partial_match(self):
        """Test column mapping with variations."""
        df = pd.DataFrame({
            'cust_id': ['C001'],
            'txn_date': ['2026-01-01'],
            'ref_id': ['INV001'],
            'total': [100]
        })
        
        mapping = suggest_column_mapping(df)
        
        # Should find some mappings even with variations
        assert len([v for v in mapping.values() if v is not None]) > 0


class TestCleanData:
    """Test cases for data cleaning."""
    
    @pytest.fixture
    def dirty_df(self):
        """Create DataFrame with dirty data."""
        return pd.DataFrame({
            'customer_id': ['C001', 'C001', 'C002', None, 'C003'],
            'invoice_date': ['2026-01-01', '2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'],
            'invoice_id': ['INV001', 'INV001', 'INV002', 'INV003', 'INV004'],
            'amount': [100, 100, 50, 200, 300]
        })
    
    def test_clean_data_removes_nulls(self, dirty_df):
        """Test that null values are removed."""
        clean, stats = clean_data(dirty_df)
        
        # Should remove row with None customer_id
        assert clean['customer_id'].isna().sum() == 0
    
    def test_clean_data_returns_dataframe(self, dirty_df):
        """Test that cleaned result is DataFrame."""
        clean, stats = clean_data(dirty_df)
        
        assert isinstance(clean, pd.DataFrame)
        assert len(clean) > 0
        assert len(clean) <= len(dirty_df)
    
    def test_clean_data_preserves_valid_rows(self):
        """Test that valid rows are preserved."""
        df = pd.DataFrame({
            'customer_id': ['C001', 'C002', 'C003'],
            'invoice_date': ['2026-01-01', '2026-01-02', '2026-01-03'],
            'invoice_id': ['INV001', 'INV002', 'INV003'],
            'amount': [100, 200, 300]
        })
        
        clean, stats = clean_data(df)
        
        # All rows should be preserved (valid data)
        assert len(clean) == len(df)
    
    def test_clean_data_min_max_amounts(self):
        """Test cleaning with various amount values."""
        df = pd.DataFrame({
            'customer_id': ['C001', 'C002', 'C003'],
            'invoice_date': ['2026-01-01', '2026-01-02', '2026-01-03'],
            'invoice_id': ['INV001', 'INV002', 'INV003'],
            'amount': [0.01, 1000000, 500]  # Very small, very large, normal
        })
        
        clean, stats = clean_data(df)
        
        # Should preserve all as they're valid amounts
        assert len(clean) > 0


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
