"""
Data preprocessing module for Customer360.
Handles CSV loading, validation, date parsing, and data cleaning.

This is the first pipeline stage after upload. It turns SME CSV files with different
column names, date formats, money formats, and blank rows into a canonical table the
RFM and clustering stages can safely use.
"""
import pandas as pd
import numpy as np
from typing import Tuple, List, Dict, Optional, Any
import logging

from ..config import MAX_ROWS

logger = logging.getLogger(__name__)

# Common date formats to try when parsing dates from SME exports and manual sheets.
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
                df = pd.read_csv(file_path, encoding=encoding, low_memory=False)
                break
            except UnicodeDecodeError:
                continue
        else:
            raise ValueError("Could not decode file with any standard encoding")

        raw_rows = len(df)
        df = df.dropna(how='all')
        removed_blank_rows = raw_rows - len(df)
        df.attrs['raw_rows'] = raw_rows
        df.attrs['removed_blank_rows'] = removed_blank_rows

        if df.empty:
            raise ValueError("The uploaded file is empty")

        if len(df) > MAX_ROWS:
            raise ValueError(
                f"File has {len(df):,} rows which exceeds the maximum of {MAX_ROWS:,}. "
                f"Please reduce the file size or contact support."
            )

        if removed_blank_rows:
            logger.info(f"Removed {removed_blank_rows:,} fully blank rows before validation")

        logger.info(f"Loaded CSV with {len(df):,} usable rows and {len(df.columns)} columns")
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
        'quantity': None,
        'unit_price': None,
        'product': None,
        'category': None
    }

    # Patterns for each field
    patterns = {
        'customer_id': ['customer_id', 'customerid', 'customer', 'cust_id', 'custid',
                        'client_id', 'clientid', 'buyer_id', 'user_id', 'userid',
                        'member_id', 'memberid'],
        'invoice_date': ['invoice_date', 'invoicedate', 'date', 'transaction_date',
                         'order_date', 'orderdate', 'purchase_date', 'txn_date'],
        'invoice_id':   ['invoice_id', 'invoiceid', 'invoice', 'invoice_no', 'invoiceno',
                         'transaction_id', 'order_id', 'orderid', 'receipt_no', 'txn_id'],
        'amount':       ['total line amount', 'total_line_amount', 'line_total', 'order_total',
                         'invoice_total', 'amount', 'total_amount', 'value', 'revenue',
                         'sales', 'transaction_amount', 'sum'],
        'quantity':     ['quantity', 'qty', 'units', 'unit_qty', 'order_qty'],
        'unit_price':   ['unit_price', 'unitprice', 'price', 'rate', 'item_price', 'selling_price'],
        'product':      ['product', 'product_name', 'productname', 'item', 'item_name',
                         'description', 'product_description', 'sku'],
        'category':     ['category', 'product_category', 'productcategory', 'type',
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


def _guess_column_type(series: pd.Series) -> Dict[str, Any]:
    sample = series.dropna().astype(str).head(100)
    if sample.empty:
        return {"detected_type": "empty", "confidence": 0.0, "date_formats": []}

    numeric_guess = pd.to_numeric(
        sample.str.replace(r"[\s,$€£₵GHS]", "", regex=True)
              .str.replace(",", "", regex=False),
        errors="coerce",
    )
    numeric_rate = float(numeric_guess.notna().mean())

    date_formats = []
    best_date_rate = 0.0
    for date_format in DATE_FORMATS:
        parsed = pd.to_datetime(sample, format=date_format, errors="coerce")
        parse_rate = float(parsed.notna().mean())
        if parse_rate >= 0.6:
            date_formats.append({"format": date_format, "confidence": round(parse_rate, 3)})
        best_date_rate = max(best_date_rate, parse_rate)

    if best_date_rate >= 0.8:
        return {
            "detected_type": "date",
            "confidence": round(best_date_rate, 3),
            "date_formats": sorted(date_formats, key=lambda item: item["confidence"], reverse=True)[:5],
        }

    if numeric_rate >= 0.8:
        return {
            "detected_type": "numeric",
            "confidence": round(numeric_rate, 3),
            "date_formats": [],
        }

    unique_ratio = float(series.nunique(dropna=True) / max(len(series.dropna()), 1))
    return {
        "detected_type": "categorical" if unique_ratio < 0.7 else "text_or_id",
        "confidence": round(max(1 - unique_ratio, unique_ratio), 3),
        "date_formats": [],
    }


def profile_dataframe(df: pd.DataFrame) -> List[Dict[str, Any]]:
    """
    Build per-column profile metadata for the mapping UI.
    """
    suggested = suggest_column_mapping(df)
    semantic_by_column = {column: field for field, column in suggested.items() if column}
    profiles = []

    for column in df.columns:
        series = df[column]
        non_null = series.dropna()
        type_guess = _guess_column_type(series)
        semantic_guess = semantic_by_column.get(column)
        confidence = 0.95 if semantic_guess else type_guess["confidence"]

        profiles.append({
            "column_name": column,
            "sample_values": non_null.astype(str).head(5).tolist(),
            "null_count": int(series.isna().sum()),
            "null_rate": round(float(series.isna().mean()), 4),
            "unique_count": int(series.nunique(dropna=True)),
            "unique_ratio": round(float(series.nunique(dropna=True) / max(len(non_null), 1)), 4),
            "detected_type": type_guess["detected_type"],
            "type_confidence": type_guess["confidence"],
            "semantic_guess": semantic_guess,
            "semantic_confidence": confidence if semantic_guess else 0.0,
            "date_format_candidates": type_guess.get("date_formats", []),
        })

    return profiles


def build_mapping_validation_report(file_path: str, column_mapping: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Run preprocessing only and return a validation payload for the mapping UI.
    """
    df, metadata = preprocess_transaction_data(file_path, column_mapping)
    cleaning_stats = metadata.get("cleaning_stats", {})

    direct_amount = metadata.get("parser_options", {}).get("amount_source_mode", "direct") == "direct"
    amount_explanation = (
        f"Amount was read directly from '{metadata['applied_mapping'].get('amount')}'."
        if direct_amount
        else "Amount was derived as Quantity × Unit Price during preprocessing."
    )

    notices = []
    removed_blank_rows = int(metadata.get("removed_blank_rows", 0))
    removed_null_customer = int(cleaning_stats.get("removed_null_customer", 0))
    removed_negative_amount = int(cleaning_stats.get("removed_negative_amount", 0))

    if removed_blank_rows:
        notices.append(f"Removed {removed_blank_rows:,} fully blank rows before applying the dataset size limit.")
    if removed_null_customer:
        notices.append(f"Excluded {removed_null_customer:,} rows with missing customer IDs from customer-level RFM.")
    if removed_negative_amount:
        notices.append(f"Excluded {removed_negative_amount:,} rows with negative amounts based on your refund policy.")
    if cleaning_stats.get("synthesised_customer_id"):
        notices.append("Synthetic customer IDs were generated. This is not true repeat-customer RFM and should be treated as a fallback.")
    if cleaning_stats.get("synthesised_invoice_date"):
        notices.append("Synthetic invoice dates were generated. Recency is approximate and not based on actual transaction dates.")

    return {
        "success": True,
        "validation": {
            "status": "passed",
            "rows_raw": int(metadata.get("raw_rows", len(df))),
            "rows_usable": int(cleaning_stats.get("final_rows", len(df))),
            "rows_removed": int(cleaning_stats.get("rows_removed", 0)),
            "removed_blank_rows": removed_blank_rows,
            "customer_count": int(metadata.get("summary", {}).get("num_customers", 0)),
            "invoice_count": int(metadata.get("summary", {}).get("num_invoices") or 0),
            "total_revenue": float(metadata.get("summary", {}).get("total_revenue", 0) or 0),
            "avg_transaction": float(metadata.get("summary", {}).get("avg_transaction", 0) or 0),
            "date_range": metadata.get("date_range", {}),
            "amount_explanation": amount_explanation,
            "notices": notices,
            "warnings": metadata.get("warnings", []),
            "cleaning_stats": cleaning_stats,
            "canonical_preview": df.head(10).to_dict(orient="records"),
        },
    }


def _coerce_field_mapping(column_mapping: Optional[Dict[str, Any]]) -> Tuple[Dict[str, str], Dict[str, Any]]:
    """
    Split a mixed mapping payload into a plain column map and parser options.

    Supports both the existing simple form:
        {"customer_id": "CustomerID", "invoice_date": "InvoiceDate", ...}

    and a richer payload:
        {
            "customer_id": "CustomerID",
            "invoice_date": "InvoiceDate",
            "quantity": "Quantity",
            "unit_price": "UnitPrice",
            "amount_source_mode": "formula",
            "invoice_date_format": "%m/%d/%Y %H:%M",
            "decimal_separator": ",",
            "thousands_separator": ".",
            "currency_symbol": "€",
            "allow_synthetic_customer_id": false,
            "allow_synthetic_invoice_date": false
        }
    """
    column_mapping = column_mapping or {}
    parser_options = {
        'amount_source_mode': column_mapping.get('amount_source_mode', 'direct'),
        'invoice_date_format': column_mapping.get('invoice_date_format') or None,
        'dayfirst': bool(column_mapping.get('dayfirst', False)),
        'decimal_separator': column_mapping.get('decimal_separator', '.'),
        'thousands_separator': column_mapping.get('thousands_separator', ','),
        'currency_symbol': column_mapping.get('currency_symbol', ''),
        'negative_amount_policy': column_mapping.get('negative_amount_policy', 'exclude'),
        'allow_synthetic_customer_id': bool(column_mapping.get('allow_synthetic_customer_id', False)),
        'allow_synthetic_invoice_date': bool(column_mapping.get('allow_synthetic_invoice_date', False)),
    }

    field_mapping: Dict[str, str] = {}
    for field in ['customer_id', 'invoice_date', 'invoice_id', 'amount', 'quantity', 'unit_price', 'product', 'category']:
        value = column_mapping.get(field)
        if isinstance(value, str) and value.strip():
            field_mapping[field] = value.strip()
        elif isinstance(value, dict) and value.get('source_column'):
            field_mapping[field] = str(value['source_column']).strip()

    return field_mapping, parser_options


def validate_and_map_columns(
    df: pd.DataFrame,
    mapping: Dict[str, str],
    parser_options: Optional[Dict[str, Any]] = None,
) -> Tuple[pd.DataFrame, List[str]]:
    """
    Validate that required columns exist and rename them to standard names.

    Only `amount` is strictly required. `customer_id` and `invoice_date` are
    optional — the pipeline will generate synthetic values when they are absent.

    Args:
        df: Input DataFrame
        mapping: Dictionary mapping standard names to actual column names

    Returns:
        Tuple of (processed DataFrame, list of warning messages)

    Raises:
        ValueError: If the amount column is missing
    """
    parser_options = parser_options or {}
    amount_source_mode = parser_options.get('amount_source_mode', 'direct')
    allow_synthetic_customer_id = bool(parser_options.get('allow_synthetic_customer_id', False))
    allow_synthetic_invoice_date = bool(parser_options.get('allow_synthetic_invoice_date', False))

    required_fields = []
    if not allow_synthetic_customer_id:
        required_fields.append('customer_id')
    if not allow_synthetic_invoice_date:
        required_fields.append('invoice_date')
    if amount_source_mode == 'formula':
        required_fields.extend(['quantity', 'unit_price'])
    else:
        required_fields.append('amount')
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

    # Warn about optional columns that are absent
    optional_important = ['customer_id', 'invoice_date', 'invoice_id', 'quantity', 'unit_price']
    for field in optional_important:
        if field not in mapping or mapping[field] is None:
            warnings.append(
                f"Optional column '{field}' not found — a synthetic value will be generated."
            )
            logger.warning(f"Column '{field}' not mapped; will be synthesised.")

    # Build rename map for columns that are actually present
    rename_map = {
        mapping[field]: field
        for field in mapping
        if mapping.get(field) and mapping[field] in df.columns
    }
    df_processed = df.rename(columns=rename_map)

    # Keep only the standard columns that now exist in the frame
    cols_to_keep = [field for field in mapping if field in df_processed.columns]
    df_processed = df_processed[cols_to_keep]

    return df_processed, warnings


def parse_dates(
    df: pd.DataFrame,
    date_column: str = 'invoice_date',
    date_format: Optional[str] = None,
    dayfirst: bool = False,
) -> pd.DataFrame:
    """
    Parse date column with robust format detection.

    If the date column is absent the DataFrame is returned unchanged; the
    calling pipeline will synthesise recency values in that case.

    Args:
        df: DataFrame with (optional) date column
        date_column: Name of the date column

    Returns:
        DataFrame with parsed dates (or unchanged if column is absent)

    Raises:
        ValueError: If the column exists but dates cannot be parsed
    """
    if date_column not in df.columns:
        logger.warning(
            f"Date column '{date_column}' not present — skipping date parsing. "
            "Recency will be estimated from row order."
        )
        return df

    df = df.copy()

    if date_format:
        try:
            df[date_column] = pd.to_datetime(df[date_column], format=date_format, errors='raise')
            logger.info(f"Successfully parsed dates using user-selected format: {date_format}")
            return df
        except Exception:
            logger.warning(f"Could not parse all dates with user-selected format {date_format}; falling back to auto detection")

    # First try pandas automatic parsing
    try:
        df[date_column] = pd.to_datetime(df[date_column], dayfirst=dayfirst)
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

    # Last resort: coerce and check success rate
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


def _parse_locale_numeric_series(
    series: pd.Series,
    *,
    decimal_separator: str = '.',
    thousands_separator: str = ',',
    currency_symbol: str = '',
) -> pd.Series:
    text = series.astype(str).str.strip()
    text = text.replace({'': np.nan, 'nan': np.nan, 'None': np.nan})

    if currency_symbol:
        text = text.str.replace(currency_symbol, '', regex=False)

    text = text.str.replace(r'[\s\u00A0]', '', regex=True)
    text = text.str.replace(r'^\((.*)\)$', r'-\1', regex=True)

    if thousands_separator:
        text = text.str.replace(thousands_separator, '', regex=False)

    if decimal_separator and decimal_separator != '.':
        text = text.str.replace(decimal_separator, '.', regex=False)

    text = text.str.replace(r'[^0-9.\-+]', '', regex=True)
    return pd.to_numeric(text, errors='coerce')


def validate_numeric_column(
    df: pd.DataFrame,
    column: str,
    *,
    decimal_separator: str = '.',
    thousands_separator: str = ',',
    currency_symbol: str = '',
) -> pd.DataFrame:
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
    df[column] = _parse_locale_numeric_series(
        df[column],
        decimal_separator=decimal_separator,
        thousands_separator=thousands_separator,
        currency_symbol=currency_symbol,
    )

    # Check for too many invalid values
    valid_count = df[column].notna().sum()
    total_count = len(df)

    if valid_count / total_count < 0.5:
        raise ValueError(
            f"Column '{column}' contains too many non-numeric values. "
            f"Only {valid_count}/{total_count} values are valid numbers."
        )

    return df


def clean_data(df: pd.DataFrame, parser_options: Optional[Dict[str, Any]] = None) -> Tuple[pd.DataFrame, Dict[str, Any]]:
    """
    Clean and prepare transaction data for analysis.

    `customer_id` and `invoice_date` are handled gracefully when absent:
    - Missing customer_id  → synthetic IDs are generated from the row index
    - Missing invoice_date → recency will be estimated upstream by the RFM module

    Args:
        df: DataFrame with transaction data

    Returns:
        Tuple of (cleaned DataFrame, cleaning statistics)
    """
    parser_options = parser_options or {}
    allow_synthetic_customer_id = bool(parser_options.get('allow_synthetic_customer_id', False))
    allow_synthetic_invoice_date = bool(parser_options.get('allow_synthetic_invoice_date', False))
    negative_amount_policy = parser_options.get('negative_amount_policy', 'exclude')
    initial_rows = len(df)
    stats = {
        'initial_rows': initial_rows,
        'removed_null_customer': 0,
        'removed_null_date': 0,
        'removed_null_amount': 0,
        'removed_negative_amount': 0,
        'removed_zero_amount': 0,
        'synthesised_customer_id': False,
        'synthesised_invoice_date': False,
        'final_rows': 0
    }

    # ── customer_id ───────────────────────────────────────────
    if 'customer_id' in df.columns:
        null_customers = df['customer_id'].isna().sum()
        df = df.dropna(subset=['customer_id'])
        stats['removed_null_customer'] = int(null_customers)
    else:
        if not allow_synthetic_customer_id:
            raise ValueError(
                "Customer ID was not mapped. Customer-level RFM requires a stable customer identifier. "
                "Map a customer column or explicitly allow synthetic customer IDs for an experimental fallback."
            )
        # No customer column — treat every row as its own customer
        # (transaction-level segmentation; RFM module groups by this key)
        df = df.copy()
        df['customer_id'] = df.index.astype(str)
        stats['synthesised_customer_id'] = True
        logger.info(
            "No customer_id column found — synthesised unique IDs from row index. "
            "Each transaction will be treated as a distinct customer."
        )

    # ── invoice_date ──────────────────────────────────────────
    if 'invoice_date' in df.columns:
        null_dates = df['invoice_date'].isna().sum()
        df = df.dropna(subset=['invoice_date'])
        stats['removed_null_date'] = int(null_dates)
    else:
        if not allow_synthetic_invoice_date:
            raise ValueError(
                "Invoice date was not mapped. Recency-based RFM requires a transaction date column. "
                "Map a date column or explicitly allow synthetic invoice dates for an experimental fallback."
            )
        # Synthesise sequential dates so RFM recency can be computed
        # Spread rows evenly across the last 365 days
        n = len(df)
        end_date   = pd.Timestamp.now().normalize()
        start_date = end_date - pd.Timedelta(days=365)
        synthetic_dates = pd.date_range(start=start_date, end=end_date, periods=n)
        df = df.copy()
        df['invoice_date'] = synthetic_dates
        stats['synthesised_invoice_date'] = True
        logger.info(
            "No invoice_date column found — synthesised sequential dates spanning "
            "the last 365 days so recency can be estimated."
        )

    # ── amount ────────────────────────────────────────────────
    null_amounts = df['amount'].isna().sum()
    df = df.dropna(subset=['amount'])
    stats['removed_null_amount'] = int(null_amounts)

    negative_amounts = int((df['amount'] < 0).sum())
    if negative_amounts and negative_amount_policy == 'exclude':
        df = df[df['amount'] >= 0]
        stats['removed_negative_amount'] = negative_amounts
    elif negative_amounts and negative_amount_policy == 'absolute':
        df = df.copy()
        df['amount'] = df['amount'].abs()
        stats['removed_negative_amount'] = 0
    else:
        stats['removed_negative_amount'] = 0

    stats['final_rows'] = len(df)
    stats['rows_removed'] = initial_rows - len(df)
    stats['retention_rate'] = len(df) / initial_rows if initial_rows > 0 else 0

    # Ensure customer_id is string for consistency
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
        'raw_rows': 0,
        'removed_blank_rows': 0,
        'original_columns': [],
        'suggested_mapping': {},
        'applied_mapping': {},
        'parser_options': {},
        'cleaning_stats': {},
        'date_range': {},
        'summary': {}
    }

    # Load CSV
    df = load_csv(file_path)
    metadata['raw_rows'] = int(df.attrs.get('raw_rows', len(df)))
    metadata['removed_blank_rows'] = int(df.attrs.get('removed_blank_rows', 0))
    metadata['original_columns'] = list(df.columns)

    # Get column mapping
    suggested_mapping = suggest_column_mapping(df)
    metadata['suggested_mapping'] = suggested_mapping

    if column_mapping is None:
        column_mapping = suggested_mapping

    field_mapping, parser_options = _coerce_field_mapping(column_mapping)

    metadata['applied_mapping'] = field_mapping
    metadata['parser_options'] = parser_options

    # Validate and map columns
    df, warnings = validate_and_map_columns(df, field_mapping, parser_options)
    if warnings:
        metadata['warnings'] = warnings

    amount_mode = parser_options.get('amount_source_mode', 'direct')
    if amount_mode == 'formula':
        df = validate_numeric_column(
            df,
            'quantity',
            decimal_separator=parser_options.get('decimal_separator', '.'),
            thousands_separator=parser_options.get('thousands_separator', ','),
            currency_symbol='',
        )
        df = validate_numeric_column(
            df,
            'unit_price',
            decimal_separator=parser_options.get('decimal_separator', '.'),
            thousands_separator=parser_options.get('thousands_separator', ','),
            currency_symbol=parser_options.get('currency_symbol', ''),
        )
        df = df.copy()
        df['amount'] = df['quantity'] * df['unit_price']
        metadata['derived_amount_formula'] = 'quantity * unit_price'

    # Parse dates (no-op if column absent)
    df = parse_dates(
        df,
        date_format=parser_options.get('invoice_date_format'),
        dayfirst=bool(parser_options.get('dayfirst', False)),
    )

    # Validate numeric amount column
    df = validate_numeric_column(
        df,
        'amount',
        decimal_separator=parser_options.get('decimal_separator', '.'),
        thousands_separator=parser_options.get('thousands_separator', ','),
        currency_symbol=parser_options.get('currency_symbol', ''),
    )

    # Clean data (synthesises missing customer_id / invoice_date internally)
    df, cleaning_stats = clean_data(df, parser_options)
    metadata['cleaning_stats'] = cleaning_stats

    # Calculate summary statistics
    metadata['date_range'] = {
        'start': df['invoice_date'].min().isoformat(),
        'end':   df['invoice_date'].max().isoformat()
    }

    metadata['summary'] = {
        'num_transactions': len(df),
        'num_customers':    df['customer_id'].nunique(),
        'num_invoices':     df['invoice_id'].nunique() if 'invoice_id' in df.columns else None,
        'total_revenue':    float(df['amount'].sum()),
        'avg_transaction':  float(df['amount'].mean()),
        'median_transaction': float(df['amount'].median()),
        'synthesised_customer_id':  cleaning_stats.get('synthesised_customer_id', False),
        'synthesised_invoice_date': cleaning_stats.get('synthesised_invoice_date', False),
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
        'columns':          list(df.columns),
        'sample_rows':      df.head(num_rows).to_dict(orient='records'),
        'suggested_mapping': suggest_column_mapping(df),
        'column_profiles':  profile_dataframe(df),
        'total_rows':       len(df),
        'raw_rows':         int(df.attrs.get('raw_rows', len(df))),
        'removed_blank_rows': int(df.attrs.get('removed_blank_rows', 0)),
    }
