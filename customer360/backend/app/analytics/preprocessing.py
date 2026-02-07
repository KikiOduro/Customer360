"""
Data preprocessing module for Customer360.
Handles CSV loading, validation, date parsing, and data cleaning.
"""
import pandas as pd
import numpy as np
from datetime import datetime
from typing import Tuple, List, Dict, Optional, Any
import logging

logger = logging.getLogger(__name__)

# Common date formats to try when parsing dates
DATE_FORMATS = [
    "%Y-%m-%d",
    "%d-%m-%Y",
    "%m-%d-%Y",
    "%Y/%m/%d",
    "%d/%m/%Y",
    "%m/%d/%Y",
    "%Y-%m-%d %H:%M:%S",
    "%d-%m-%Y %H:%M:%S",
    "%m-%d-%Y %H:%M:%S",
    "%Y/%m/%d %H:%M:%S",
    "%d/%m/%Y %H:%M:%S",
    "%m/%d/%Y %H:%M:%S",
    "%d %b %Y",
    "%d %B %Y",
    "%b %d, %Y",
    "%B %d, %Y",
]


def load_csv(file_path: str) -> pd.DataFrame:
    """
    Load a CSV file into a pandas DataFrame.
    
    Args:
        file_path: Path to the CSV file
        
    Returns:
        DataFrame containing the CSV data
        
    Raises:
        ValueError: If file cannot be loaded or is empty
    """
    try:
        # Try reading with different encodings
        for encoding in ['utf-8', 'latin-1', 'cp1252']:
            try:
                df = pd.read_csv(file_path, encoding=encoding)
                break
            except UnicodeDecodeError:
                continue
        else:
            raise ValueError("Could not decode file with any standard encoding")
        
        if df.empty:
            raise ValueError("The uploaded file is empty")
        
        logger.info(f"Loaded CSV with {len(df)} rows and {len(df.columns)} columns")
        return df
        
    except pd.errors.EmptyDataError:
        raise ValueError("The uploaded file is empty or has no valid data")
    except pd.errors.ParserError as e:
        raise ValueError(f"Error parsing CSV file: {str(e)}")


def suggest_column_mapping(df: pd.DataFrame) -> Dict[str, Optional[str]]:
    """
    Attempt to automatically map CSV columns to required fields.
    Uses common column name patterns to suggest mappings.
    
    Args:
        df: DataFrame to analyze
        
    Returns:
        Dictionary with suggested mappings for each required field
    """
    columns = [col.lower().strip() for col in df.columns]
    original_columns = list(df.columns)
    
    mapping = {
        'customer_id': None,
        'invoice_date': None,
        'invoice_id': None,
        'amount': None,
        'product': None,
        'category': None
    }
    
    # Patterns for each field
    patterns = {
        'customer_id': ['customer_id', 'customerid', 'customer', 'cust_id', 'custid', 
                        'client_id', 'clientid', 'buyer_id', 'user_id', 'userid'],
        'invoice_date': ['invoice_date', 'invoicedate', 'date', 'transaction_date', 
                         'order_date', 'orderdate', 'purchase_date', 'txn_date'],
        'invoice_id': ['invoice_id', 'invoiceid', 'invoice', 'invoice_no', 'invoiceno',
                       'transaction_id', 'order_id', 'orderid', 'receipt_no', 'txn_id'],
        'amount': ['amount', 'total', 'price', 'value', 'revenue', 'sales', 
                   'transaction_amount', 'order_total', 'payment', 'sum'],
        'product': ['product', 'product_name', 'productname', 'item', 'item_name',
                    'description', 'product_description', 'sku'],
        'category': ['category', 'product_category', 'productcategory', 'type',
                     'product_type', 'class', 'group', 'segment']
    }
    
    for field, field_patterns in patterns.items():
        for pattern in field_patterns:
            for i, col in enumerate(columns):
                if pattern in col or col in pattern:
                    mapping[field] = original_columns[i]
                    break
            if mapping[field]:
                break
    
    return mapping


def validate_and_map_columns(
    df: pd.DataFrame, 
    mapping: Dict[str, str]
) -> Tuple[pd.DataFrame, List[str]]:
    """
    Validate that required columns exist and rename them to standard names.
    
    Args:
        df: Input DataFrame
        mapping: Dictionary mapping standard names to actual column names
        
    Returns:
        Tuple of (processed DataFrame, list of warning messages)
        
    Raises:
        ValueError: If required columns are missing
    """
    required_fields = ['customer_id', 'invoice_date', 'invoice_id', 'amount']
    warnings = []
    
    # Check for missing required columns
    missing = []
    for field in required_fields:
        if field not in mapping or mapping[field] is None:
            missing.append(field)
        elif mapping[field] not in df.columns:
            missing.append(f"{field} (mapped to '{mapping[field]}' which doesn't exist)")
    
    if missing:
        raise ValueError(f"Missing required columns: {', '.join(missing)}")
    
    # Create a new DataFrame with standardized column names
    rename_map = {mapping[field]: field for field in mapping if mapping.get(field)}
    df_processed = df.rename(columns=rename_map)
    
    # Select only the columns we need
    cols_to_keep = [field for field in mapping if mapping.get(field)]
    df_processed = df_processed[cols_to_keep]
    
    return df_processed, warnings


def parse_dates(df: pd.DataFrame, date_column: str = 'invoice_date') -> pd.DataFrame:
    """
    Parse date column with robust format detection.
    
    Args:
        df: DataFrame with date column
        date_column: Name of the date column
        
    Returns:
        DataFrame with parsed dates
        
    Raises:
        ValueError: If dates cannot be parsed
    """
    df = df.copy()
    
    # First try pandas automatic parsing
    try:
        df[date_column] = pd.to_datetime(df[date_column], infer_datetime_format=True)
        return df
    except Exception:
        pass
    
    # Try each format explicitly
    for date_format in DATE_FORMATS:
        try:
            df[date_column] = pd.to_datetime(df[date_column], format=date_format)
            logger.info(f"Successfully parsed dates using format: {date_format}")
            return df
        except Exception:
            continue
    
    # Last resort: try with errors='coerce' and check success rate
    df[date_column] = pd.to_datetime(df[date_column], errors='coerce')
    valid_dates = df[date_column].notna().sum()
    total_dates = len(df)
    success_rate = valid_dates / total_dates if total_dates > 0 else 0
    
    if success_rate < 0.5:
        raise ValueError(
            f"Could not parse dates in column '{date_column}'. "
            f"Only {valid_dates}/{total_dates} dates were parseable. "
            f"Please ensure dates are in a standard format (e.g., YYYY-MM-DD)."
        )
    
    logger.warning(f"Parsed {valid_dates}/{total_dates} dates successfully")
    return df


def validate_numeric_column(df: pd.DataFrame, column: str) -> pd.DataFrame:
    """
    Ensure a column contains numeric values.
    
    Args:
        df: DataFrame
        column: Column name to validate
        
    Returns:
        DataFrame with validated numeric column
        
    Raises:
        ValueError: If column cannot be converted to numeric
    """
    df = df.copy()
    
    # Try to convert to numeric
    df[column] = pd.to_numeric(df[column], errors='coerce')
    
    # Check for too many invalid values
    valid_count = df[column].notna().sum()
    total_count = len(df)
    
    if valid_count / total_count < 0.5:
        raise ValueError(
            f"Column '{column}' contains too many non-numeric values. "
            f"Only {valid_count}/{total_count} values are valid numbers."
        )
    
    return df


def clean_data(df: pd.DataFrame) -> Tuple[pd.DataFrame, Dict[str, Any]]:
    """
    Clean and prepare transaction data for analysis.
    
    Args:
        df: DataFrame with transaction data
        
    Returns:
        Tuple of (cleaned DataFrame, cleaning statistics)
    """
    initial_rows = len(df)
    stats = {
        'initial_rows': initial_rows,
        'removed_null_customer': 0,
        'removed_null_date': 0,
        'removed_null_amount': 0,
        'removed_negative_amount': 0,
        'removed_zero_amount': 0,
        'final_rows': 0
    }
    
    # Remove rows with null customer_id
    null_customers = df['customer_id'].isna().sum()
    df = df.dropna(subset=['customer_id'])
    stats['removed_null_customer'] = null_customers
    
    # Remove rows with null dates
    null_dates = df['invoice_date'].isna().sum()
    df = df.dropna(subset=['invoice_date'])
    stats['removed_null_date'] = null_dates
    
    # Remove rows with null amounts
    null_amounts = df['amount'].isna().sum()
    df = df.dropna(subset=['amount'])
    stats['removed_null_amount'] = null_amounts
    
    # Remove negative amounts
    negative_amounts = (df['amount'] < 0).sum()
    df = df[df['amount'] >= 0]
    stats['removed_negative_amount'] = negative_amounts
    
    # Optionally remove zero amounts (configurable)
    # For now, we keep zero amounts as they might be valid (e.g., free samples)
    
    stats['final_rows'] = len(df)
    stats['rows_removed'] = initial_rows - len(df)
    stats['retention_rate'] = len(df) / initial_rows if initial_rows > 0 else 0
    
    # Convert customer_id to string for consistency
    df['customer_id'] = df['customer_id'].astype(str)
    
    # Sort by date
    df = df.sort_values('invoice_date')
    
    return df, stats


def preprocess_transaction_data(
    file_path: str,
    column_mapping: Optional[Dict[str, str]] = None
) -> Tuple[pd.DataFrame, Dict[str, Any]]:
    """
    Full preprocessing pipeline for transaction data.
    
    Args:
        file_path: Path to CSV file
        column_mapping: Optional manual column mapping
        
    Returns:
        Tuple of (processed DataFrame, metadata dict)
    """
    metadata = {
        'original_columns': [],
        'suggested_mapping': {},
        'applied_mapping': {},
        'cleaning_stats': {},
        'date_range': {},
        'summary': {}
    }
    
    # Load CSV
    df = load_csv(file_path)
    metadata['original_columns'] = list(df.columns)
    
    # Get column mapping
    suggested_mapping = suggest_column_mapping(df)
    metadata['suggested_mapping'] = suggested_mapping
    
    if column_mapping is None:
        column_mapping = suggested_mapping
    
    metadata['applied_mapping'] = column_mapping
    
    # Validate and map columns
    df, warnings = validate_and_map_columns(df, column_mapping)
    
    # Parse dates
    df = parse_dates(df)
    
    # Validate numeric amount column
    df = validate_numeric_column(df, 'amount')
    
    # Clean data
    df, cleaning_stats = clean_data(df)
    metadata['cleaning_stats'] = cleaning_stats
    
    # Calculate summary statistics
    metadata['date_range'] = {
        'start': df['invoice_date'].min().isoformat(),
        'end': df['invoice_date'].max().isoformat()
    }
    
    metadata['summary'] = {
        'num_transactions': len(df),
        'num_customers': df['customer_id'].nunique(),
        'num_invoices': df['invoice_id'].nunique() if 'invoice_id' in df.columns else None,
        'total_revenue': float(df['amount'].sum()),
        'avg_transaction': float(df['amount'].mean()),
        'median_transaction': float(df['amount'].median())
    }
    
    return df, metadata


def get_csv_preview(file_path: str, num_rows: int = 5) -> Dict[str, Any]:
    """
    Get a preview of a CSV file for column mapping UI.
    
    Args:
        file_path: Path to CSV file
        num_rows: Number of sample rows to return
        
    Returns:
        Dictionary with columns, sample data, and suggested mapping
    """
    df = load_csv(file_path)
    
    return {
        'columns': list(df.columns),
        'sample_rows': df.head(num_rows).to_dict(orient='records'),
        'suggested_mapping': suggest_column_mapping(df),
        'total_rows': len(df)
    }
