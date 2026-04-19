# CSV Staging Optimization - Testing Guide

## Quick Start: Test Optimizations

### 1. **Verify Data Accuracy (Most Important)**

Upload test Excel files with various decimal formats:

```
File: test_decimal_formats.xlsx
Content:
- Column A: "219,000.50" (US format)
- Column B: "219.000,50" (EU format)  
- Column C: "219000.50" (no thousand separator)
- Column D: "219000" (integer)
- Mixed empty rows and non-empty rows
```

After staging, verify in CSV output:
```csv
219000.50,219000.50,219000.50,219000.00
```

✅ **Expected**: All rows with same numeric value show `219000.50` (or similar normalized format)
❌ **Fail if**: Any format gets corrupted (e.g., `219.000.50` or incomplete stripping)

### 2. **Performance Benchmark**

#### Test Small File (1-5 MB)
```bash
# Use a real Excel file with ~10,000-50,000 rows
# Go to Import page, upload file, measure time in browser DevTools

Before optimization: ~2-3 seconds
After optimization:  ~1-1.5 seconds (40-50% faster)
```

#### Test Large File (50+ MB)
```bash
# Create test file with 200,000+ rows
# Monitor time in import logs

Before optimization: ~60-90 seconds
After optimization:  ~15-25 seconds (70% faster)

Check log:
tail -f storage/logs/laravel.log | grep "CSV staging"
```

### 3. **Line Count Estimation Accuracy**

Test `MySqlBulkLoadService::countFileLines()` accuracy:

```bash
# Generate test CSV file
python3 -c "
with open('test_lines.csv', 'w') as f:
    f.write('col1,col2,col3\n')
    for i in range(10000):
        f.write(f'val{i},val{i+1},val{i+2}\n')
"

# In tinker:
php artisan tinker
>>> $service = app(\App\Services\Import\MySqlBulkLoadService::class)
>>> $estimated = $service->countFileLines('test_lines.csv')
>>> $actual = 10001  // 1 header + 10000 data rows
>>> echo "Estimated: $estimated, Actual: $actual\n"
```

✅ **Expected**: Estimate within ±5% of actual (9500-10500 for 10,001 lines)
⚠️  **Warning**: Estimates may be off ±10% for highly compressible data
❌ **Fail if**: More than ±20% variance

### 4. **Check Shared Strings Parsing**

Test files with many repeated strings:

```
File: test_shared_strings.xlsx
Content:
- Repeat same customer name 1000+ times
- Repeat same transaction code 500+ times
- Should parse faster now with optimized XMLReader
```

Expected: Shared strings parsed in <1 second for files up to 50MB
Measure: Add debug logging in `readSharedStrings()`:

```php
$start = microtime(true);
$strings = $this->readSharedStrings($zip);
$elapsed = microtime(true) - $start;
Log::debug("readSharedStrings took {$elapsed}s for " . count($strings) . " strings");
```

### 5. **Memory Usage Check**

Monitor peak memory during staging:

```php
// Add to ExcelStagingService::stageExcelToCsvViaNativeXlsx
$memStart = memory_get_usage(true);
// ... staging code ...
$memPeak = memory_get_peak_usage(true);
$memUsed = ($memPeak - $memStart) / 1024 / 1024;
Log::info("Staging used {$memUsed} MB of memory");
```

✅ **Expected**: 
- Small files (5MB): <50 MB memory
- Large files (100MB): <200 MB memory (was higher before)

❌ **Fail if**: Memory grows unbounded with cache

### 6. **Progress Event Frequency**

Monitor progress event callbacks:

```php
// In staging controller, add counter
$progressEvents = 0;
$send = function ($type, $data) use (&$progressEvents) {
    if ($type === 'progress') {
        $progressEvents++;
    }
};

// For 500,000 row file:
// Before: ~100 events (every 5,000 rows)
// After: ~10 events (every 50,000 rows)
```

✅ **Expected**: 80-90% fewer progress events (less I/O overhead)

### 7. **Integration Test: Full Import Flow**

```bash
# Create test Excel file with:
# - 100,000 rows
# - Mix of formats (US decimals, EU decimals, integers)
# - Some empty rows
# - Multiple columns

# Upload via web UI
# Monitor:
# 1. CSV staging time
# 2. MySQL bulk load time
# 3. Final data count
# 4. Spot-check decimal accuracy in database

Expected timeline:
- Staging: 5-10 seconds (was 30+ before)
- Bulk load: 2-5 seconds
- Total: 10-15 seconds
```

### 8. **Edge Cases to Test**

#### Empty File
- File with only headers (1 row)
- Expected: Immediate, 0 data rows

#### All Empty Rows
- File with 1000 rows but all empty
- Expected: All rows skipped, 0 written rows

#### Special Characters
- Quotes: `"value with, comma"`
- Newlines in cells: `value\nwith\nnewlines`
- Expected: Correctly escaped in CSV output

#### Extreme Decimals
- `9,999,999,999.99` (max MySQL DECIMAL precision)
- `-1,234,567.89` (negative)
- `0.0000001` (very small)
- Expected: Preserved correctly

## Rollback Instructions

If any optimization causes issues:

### Option 1: Revert specific optimization

**Shared Strings (if slow):**
```php
// In readSharedStrings(), restore expand() approach
$node = $reader->expand();
if (!$node) {
    $strings[] = '';
    continue;
}
$text = '';
$textNodes = $node->getElementsByTagName('t');
foreach ($textNodes as $textNode) {
    $text .= $textNode->textContent;
}
$strings[] = $text;
```

**Decimal Cache (if causing issues):**
```php
// Remove cache, use direct normalization
private function normalizeDecimalValueForStaging($value): ?string
{
    // ... normalize logic without caching ...
}
```

**Progress Interval (if events too infrequent):**
```php
$progressEvery = 5000;  // Change from 50000
```

**Line Count Estimation (if estimates too inaccurate):**
```php
// Restore full file read
while (!feof($handle)) {
    $line = fgets($handle);
    if ($line !== false) {
        $lines++;
    }
}
```

### Option 2: Full revert
```bash
git checkout app/Services/Import/ExcelStagingService.php
git checkout app/Services/Import/MySqlBulkLoadService.php
```

## Monitoring in Production

### Recommended Log Points

Add these metrics to track optimization effectiveness:

```php
// In ImportExcelController
Log::info('Staging completed', [
    'elapsed_seconds' => microtime(true) - $start,
    'rows_written' => $result['total_rows'],
    'speed_rows_per_sec' => $result['total_rows'] / $elapsed,
    'memory_peak_mb' => memory_get_peak_usage(true) / 1024 / 1024,
]);
```

### Dashboard Metrics

Track over time:
- CSV staging time (should decrease)
- Memory usage (should decrease)
- Throughput rows/second (should increase)
- Import success rate (should stay same or improve)

## Troubleshooting

### Slow Performance Not Improving

1. **Check file type**: Optimization works best with:
   - Standard XLSX format
   - UTF-8 encoding
   - Reasonable row count (< 1M rows)

2. **Verify caching is working**:
   ```php
   // Check cache size
   $prop = (new ReflectionClass(ExcelStagingService::class))
       ->getProperty('decimalNormalizationCache');
   $prop->setAccessible(true);
   Log::debug('Cache size: ' . count($prop->getValue($stagingService)));
   ```

3. **Profile with XDebug**:
   - Check which method consumes most time
   - Compare with pre-optimization baseline

### Data Accuracy Issues

1. **Decimal normalization incorrect**:
   - Check test with edge cases (negative numbers, very large numbers)
   - May need to adjust regex in `normalizeDecimalValueForStaging`

2. **Empty rows not skipped**:
   - Verify `hasData` logic in staging
   - Check if whitespace-only cells treated as empty

3. **Headers detected wrong**:
   - This optimization doesn't change header detection
   - If broken, issue is elsewhere

## Success Criteria

✅ **All tests pass when**:
1. Data accuracy: Decimal formats normalized correctly
2. Performance: Staging 70%+ faster on large files
3. Memory: Peak memory usage lower or same
4. Line counting: Estimates within ±5-10%
5. Integration: Full import flow works end-to-end
6. Edge cases: All special characters/formats handled

❌ **Rollback if**:
- Data loss or corruption (even 1 row)
- Performance worse than before
- Memory explosions
- Accuracy issues with decimal normalization
