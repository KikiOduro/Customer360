#!/usr/bin/env python3
"""
Test script to verify the complete pipeline works end-to-end.
"""

import sys
import tempfile
from pathlib import Path

# Add backend to path
backend_path = Path(__file__).parent / "backend"
sys.path.insert(0, str(backend_path / "app"))

from analytics.complete_pipeline import run_full_pipeline


def test_pipeline():
    """Test the complete pipeline with sample data."""
    
    # Sample data path - in backend data folder
    sample_csv = Path(__file__).parent / "backend" / "data" / "sample_data.csv"
    
    if not sample_csv.exists():
        print(f"❌ Sample data not found: {sample_csv}")
        return False
    
    print(f"📂 Using sample data: {sample_csv}")
    
    # Create temporary output directory
    with tempfile.TemporaryDirectory() as temp_dir:
        print(f"📁 Output directory: {temp_dir}")
        
        try:
            # Run pipeline
            print("\n🚀 Starting pipeline...")
            results = run_full_pipeline(
                csv_file_path=str(sample_csv),
                output_directory=temp_dir,
                job_id="test_job_001"
            )
            
            print("\n✅ Pipeline completed successfully!")
            
            # Print results summary
            print("\n📊 Results Summary:")
            print(f"  Data shape: {results.get('data_shape')}")
            print(f"  Clusters found: {results.get('optimal_k')}")
            print(f"  RFM customers: {results.get('rfm', {}).get('num_customers')}")
            print(f"  Total revenue: {results.get('rfm', {}).get('total_revenue')}")
            
            if 'clustering' in results:
                print(f"  Silhouette score: {results['clustering'].get('silhouette_score')}")
                print(f"  Davies-Bouldin: {results['clustering'].get('davies_bouldin_score')}")
            
            if 'validation' in results:
                print(f"  Cluster stability: {results['validation'].get('stability')}")
            
            # Check output files
            output_path = Path(temp_dir)
            files = list(output_path.rglob('*'))
            print(f"\n📄 Generated files: {len(files)}")
            for f in sorted(files):
                if f.is_file():
                    print(f"  - {f.relative_to(output_path)}")
            
            return True
            
        except Exception as e:
            print(f"\n❌ Pipeline failed: {str(e)}")
            import traceback
            traceback.print_exc()
            return False


if __name__ == "__main__":
    success = test_pipeline()
    sys.exit(0 if success else 1)
