#!/usr/bin/env python3
"""
Test the new production pipeline end-to-end
"""
import sys
import tempfile
from pathlib import Path

# Add backend to path
sys.path.insert(0, str(Path(__file__).parent / 'customer360' / 'backend'))

from app.analytics.production_pipeline import ProductionPipeline
from app.data.sample_data import generate_sample_csv

def test_pipeline():
    """Test the complete pipeline."""
    print("\n" + "="*70)
    print("Testing Production Pipeline End-to-End")
    print("="*70)
    
    with tempfile.TemporaryDirectory() as tmpdir:
        # Generate sample data
        sample_csv = Path(tmpdir) / "sample.csv"
        print(f"\n📄 Generating sample CSV: {sample_csv}")
        generate_sample_csv(sample_csv, num_rows=100)
        print(f"✅ Sample CSV created with 100 transactions")
        
        # Create pipeline
        output_dir = Path(tmpdir) / "output"
        output_dir.mkdir()
        
        pipeline = ProductionPipeline(output_dir=str(output_dir))
        
        # Run pipeline
        print(f"\n🚀 Running pipeline...")
        results = pipeline.run(csv_path=str(sample_csv))
        
        # Print results
        print(f"\n✅ Pipeline completed successfully!")
        print(f"\n📊 Results Summary:")
        print(f"   Transactions: {results['num_transactions']}")
        print(f"   Customers: {results['num_customers']}")
        print(f"   Total Revenue: ${results['total_revenue']:,.2f}")
        print(f"   Clusters Found: {results['num_clusters']}")
        print(f"   Silhouette Score: {results['silhouette_score']:.4f}")
        print(f"   Davies-Bouldin: {results['davies_bouldin']:.4f}")
        
        # Verify output files
        print(f"\n📁 Generated Files:")
        report_path = Path(results['report_path'])
        csv_path = Path(results['csv_path'])
        
        if report_path.exists():
            size_mb = report_path.stat().st_size / 1024 / 1024
            print(f"   ✅ PDF Report: {report_path.name} ({size_mb:.1f} MB)")
        else:
            print(f"   ❌ PDF Report NOT found")
        
        if csv_path.exists():
            size_kb = csv_path.stat().st_size / 1024
            print(f"   ✅ Segmented CSV: {csv_path.name} ({size_kb:.1f} KB)")
        else:
            print(f"   ❌ CSV NOT found")
        
        # Verify segment insights
        insights = results.get('segment_insights', {})
        print(f"\n👥 Customer Segments ({len(insights)} total):")
        for seg_name, info in insights.items():
            print(f"   {seg_name}:")
            print(f"      Customers: {info['count']}")
            print(f"      Revenue: ${info['total_revenue']:,.2f}")
            print(f"      Avg Spend: ${info['avg_spending']:,.2f}")
        
        print(f"\n{'='*70}")
        print("✅ ALL TESTS PASSED")
        print(f"{'='*70}\n")
        
        return True

if __name__ == '__main__':
    try:
        test_pipeline()
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
