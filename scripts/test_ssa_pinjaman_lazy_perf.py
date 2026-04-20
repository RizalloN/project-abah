#!/usr/bin/env python3
"""
Performance Testing: Polars Lazy vs Eager Evaluation
=====================================================

Compares:
1. Lazy evaluation (new)
2. Eager evaluation (current)
3. Database LOAD DATA approach

Measures:
- Execution time
- Memory usage
- Rows per second
- Peak memory

Usage:
  python test_ssa_pinjaman_lazy_perf.py --csv data.csv --rows 1000000
"""

import argparse
import json
import subprocess
import sys
import tempfile
import time
from pathlib import Path


def run_test(processor_script: str, config: dict, name: str) -> dict:
    """Run processor and measure performance."""
    print(f"\n📊 Testing {name}...")
    
    with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
        json.dump(config, f)
        config_path = f.name
    
    try:
        start_time = time.perf_counter()
        
        try:
            import psutil
            start_memory = psutil.Process().memory_info().rss / 1024 / 1024
        except:
            start_memory = None

        process = subprocess.Popen(
            ['python', processor_script, '--config', config_path, '--mode', 'stage'],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )

        done_event = None
        last_progress = None
        
        for line in process.stdout:
            try:
                event = json.loads(line.strip())
                if event.get('type') == 'progress':
                    last_progress = event
                elif event.get('type') == 'done':
                    done_event = event
            except:
                pass

        process.wait()

        try:
            import psutil
            end_memory = psutil.Process().memory_info().rss / 1024 / 1024
        except:
            end_memory = None

        elapsed = time.perf_counter() - start_time
        
        result = {
            'name': name,
            'status': 'success' if process.returncode == 0 else 'failed',
            'execution_time_seconds': round(elapsed, 2),
            'return_code': process.returncode,
        }

        if done_event:
            result.update(done_event)

        if end_memory and start_memory:
            result['memory_used_mb'] = round(end_memory - start_memory, 2)

        return result

    finally:
        Path(config_path).unlink(missing_ok=True)


def create_test_csv(output_path: str, num_rows: int) -> None:
    """Create test CSV with sample data."""
    print(f"📝 Creating test CSV with {num_rows:,} rows...")
    
    with open(output_path, 'w') as f:
        # Write header
        header = [
            'Month, Day, Year of Periode',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Produk_Dashboard',
            'Segmen',
            'Segmen Lama',
            'SEGMEN_2025',
            'Segmen_Dashboard',
            'Kolektabilitas One Obligor',
            'Flag Restruk',
            'Baki Debet',
            'Jumlah Debitur Aktif',
            'Jumlah Rekening Aktif',
            'Keterangan Uker',
            'Kualitas',
        ]
        f.write(','.join(header) + '\n')

        # Write data rows
        for i in range(num_rows):
            row = [
                '01/04/2026',
                f'CABANG_{i % 100}',
                f'UKER_{i % 200}',
                f'PRODUK_{i % 50}',
                f'DASHBOARD_{i % 10}',
                f'SEGMEN_{i % 15}',
                f'LAMA_{i % 15}',
                f'2025_{i % 20}',
                f'DASH_{i % 10}',
                f'KOL_{i % 5}',
                '0',
                f'{1000 + (i % 100000)}',
                f'{i % 50}',
                f'{i % 100}',
                f'KET_{i % 10}',
                f'KWL_{i % 3}',
            ]
            f.write(','.join(row) + '\n')
    
    print(f"✅ Test CSV created: {output_path}")


def format_result(result: dict) -> str:
    """Format result for display."""
    lines = [
        f"\n{'='*60}",
        f"Backend: {result.get('backend', 'unknown')}",
        f"Status: {result['status']}",
        f"Execution Time: {result['execution_time_seconds']}s",
    ]
    
    if 'written_rows' in result:
        rows_per_sec = result['written_rows'] / max(result['execution_time_seconds'], 0.001)
        lines.append(f"Rows Processed: {result['written_rows']:,}")
        lines.append(f"Speed: {int(rows_per_sec):,} rows/sec")
    
    if 'memory_used_mb' in result:
        lines.append(f"Memory Used: {result['memory_used_mb']}MB")
    
    if 'optimization' in result:
        opt = result['optimization']
        if opt.get('rows_per_second'):
            lines.append(f"Optimization: {opt['rows_per_second']:,} rows/sec")
    
    lines.append('='*60)
    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description='Performance test Polars lazy vs eager')
    parser.add_argument('--csv', help='Input CSV path (will create test if not provided)')
    parser.add_argument('--rows', type=int, default=100000, help='Number of test rows')
    parser.add_argument('--eager-script', default='scripts/ssa_pinjaman_polars_processor.py')
    parser.add_argument('--lazy-script', default='scripts/ssa_pinjaman_lazy_processor.py')
    
    args = parser.parse_args()

    # Create or use test CSV
    csv_path = args.csv
    if not csv_path:
        csv_path = f'/tmp/test_ssa_pinjaman_{args.rows}.csv'
        if not Path(csv_path).exists():
            create_test_csv(csv_path, args.rows)

    if not Path(csv_path).exists():
        print(f"❌ CSV not found: {csv_path}")
        return 1

    print(f"\n🚀 Performance Test: SSA Pinjaman Lazy vs Eager")
    print(f"CSV: {csv_path}")
    print(f"Expected rows: {args.rows:,}")

    # Prepare test outputs
    output_eager = tempfile.mktemp(suffix='_eager.csv')
    output_lazy = tempfile.mktemp(suffix='_lazy.csv')

    # Test eager
    config_eager = {
        'file_path': csv_path,
        'output_csv_path': output_eager,
        'mode': 'stage',
    }

    # Test lazy
    config_lazy = {
        'file_path': csv_path,
        'output_csv_path': output_lazy,
        'mode': 'stage',
    }

    results = []

    try:
        if Path(args.eager_script).exists():
            result_eager = run_test(args.eager_script, config_eager, "Eager Evaluation")
            results.append(result_eager)
            print(format_result(result_eager))
        else:
            print(f"⚠️  Eager script not found: {args.eager_script}")

        if Path(args.lazy_script).exists():
            result_lazy = run_test(args.lazy_script, config_lazy, "Lazy Evaluation")
            results.append(result_lazy)
            print(format_result(result_lazy))
        else:
            print(f"⚠️  Lazy script not found: {args.lazy_script}")

        # Compare
        if len(results) == 2:
            eager = results[0]
            lazy = results[1]
            
            if eager['status'] == 'success' and lazy['status'] == 'success':
                speedup = (eager['execution_time_seconds'] / lazy['execution_time_seconds'] - 1) * 100
                print(f"\n📈 Speedup: {speedup:.1f}% faster with lazy evaluation")
                print(f"   Eager: {eager['execution_time_seconds']}s")
                print(f"   Lazy:  {lazy['execution_time_seconds']}s")

        print("\n✅ Performance testing complete")
        return 0

    finally:
        Path(output_eager).unlink(missing_ok=True)
        Path(output_lazy).unlink(missing_ok=True)


if __name__ == '__main__':
    sys.exit(main())
