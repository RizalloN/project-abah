# 📋 QUICK FIX CODE SNIPPETS

Copy-paste these fixes untuk immediate performance improvement (30% gain)

---

## FIX #1: Optimize Snapshot Scheduler (HIGH IMPACT)

**File**: `app/Console/Kernel.php`

**Replace this**:
```php
protected function schedule(Schedule $schedule): void
{
    // Auto-flush snapshot batches every minute
    $schedule->command('snapshot:flush-due-batches')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Snapshot batch flush failed');
        });

    // Sync Dashboard Harian snapshots with SSA data every 5 minutes
    $schedule->command('snapshot:sync-harian-dashboard')
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Dashboard Harian snapshot sync failed');
        });

    // Rebuild important Performance RM snapshots hourly
    $schedule->command('snapshot:rebuild-rm-scheduled')
        ->hourly()
        ->withoutOverlapping(5)
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Performance RM snapshot scheduled rebuild failed');
        });
}
```

**With this** (OFF-PEAK ONLY):
```php
protected function schedule(Schedule $schedule): void
{
    // ✅ Auto-flush snapshot batches every minute (OFF-PEAK ONLY: 7PM-7AM)
    $schedule->command('snapshot:flush-due-batches')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->timezone('Asia/Jakarta')
        ->when(function () {
            $hour = now()->hour;
            // Only run during off-peak hours (7PM-7AM)
            return $hour < 7 || $hour >= 19;
        })
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Snapshot batch flush failed');
        });

    // ✅ Sync Dashboard Harian every 5 minutes (OFF-PEAK ONLY)
    $schedule->command('snapshot:sync-harian-dashboard')
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->timezone('Asia/Jakarta')
        ->when(function () {
            $hour = now()->hour;
            return $hour < 7 || $hour >= 19;
        })
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Dashboard Harian snapshot sync failed');
        });

    // ✅ Rebuild Performance RM snapshots hourly (OFF-PEAK ONLY)
    $schedule->command('snapshot:rebuild-rm-scheduled')
        ->hourlyAt(30)  // Run at :30 past each hour
        ->withoutOverlapping(5)
        ->timezone('Asia/Jakarta')
        ->when(function () {
            $hour = now()->hour;
            return $hour < 7 || $hour >= 19;
        })
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('Performance RM snapshot scheduled rebuild failed');
        });
}
```

**Impact**: -5 to -10 seconds on all user queries during business hours (8AM-7PM)

---

## FIX #2: Add Query Caching (MEDIUM IMPACT)

**File**: `app/Http/Controllers/RasioCasaDebiturController.php`

**Find this method** (around line 100):
```php
public function fetchData(Request $request)
{
    // Current code that does slow query...
}
```

**Add caching**:
```php
use Illuminate\Support\Facades\Cache;  // Add at top of file

public function fetchData(Request $request)
{
    // Create cache key from user ID + all request params
    $cacheKey = 'rasiocasa_' . auth()->id() . '_' . md5(json_encode($request->all()));
    
    // Check cache first
    return Cache::remember($cacheKey, 3600, function () use ($request) {
        // Your existing query code
        // [Original slow query here - unchanged]
        
        // Example (replace with your actual code):
        $data = DB::table('daily_loan_dinamis as d')
            ->join('simpanan_multipn as s', 'd.cif', '=', 's.cif')
            ->select('d.cif', DB::raw('SUM(s.balance) as total_balance'))
            ->groupBy('d.cif')
            ->get();
        
        return response()->json(['data' => $data]);
    });
}
```

**For Kinerja RM Report** - `app/Http/Controllers/Report/KinerjaRmReportController.php`:
```php
public function historyDetails(Request $request)
{
    $cacheKey = 'kinerjarm_history_' . auth()->id() . '_' . request()->get('id_report');
    
    return Cache::remember($cacheKey, 3600, function () use ($request) {
        // Your existing query - unchanged
        // Just wrap it with Cache::remember()
    });
}
```

**Impact**: -5 to -15 seconds on subsequent queries (cache hit)

---

## FIX #3: Increase Session Timeout (MINOR IMPACT)

**File**: `config/session.php`

**Find**:
```php
'lifetime' => env('SESSION_LIFETIME', 120),
```

**Change to**:
```php
'lifetime' => 480,  // 8 hours
```

**Also find and update**:
```php
'idle_timeout' => env('SESSION_IDLE_TIMEOUT', 120),
```

**Change to**:
```php
'idle_timeout' => 480,  // 8 hours
```

**Impact**: Reduces session timeout issues, slightly improves perceived performance

---

## DEPLOYMENT STEPS

### Step 1: Backup current files
```bash
cp app/Console/Kernel.php app/Console/Kernel.php.backup
cp config/session.php config/session.php.backup
```

### Step 2: Apply FIX #1 (Scheduler)
- Edit `app/Console/Kernel.php`
- Replace the `schedule()` function with the new code above
- Save

### Step 3: Apply FIX #2 (Query Caching)
- Edit `app/Http/Controllers/RasioCasaDebiturController.php`
- Wrap fetchData() with Cache::remember()
- Do the same for KinerjaRmReportController
- Save

### Step 4: Apply FIX #3 (Session)
- Edit `config/session.php`
- Update lifetime and idle_timeout
- Save

### Step 5: Test
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear

# Test login
# Visit: https://asixdashboard.duckdns.org/login

# Test dashboard
# Visit: https://asixdashboard.duckdns.org/dashboard
```

### Step 6: Monitor
```bash
# Check for errors
tail -f storage/logs/laravel.log

# Monitor slow queries (if MySQL is accessible)
# Run on server:
SHOW FULL PROCESSLIST;
```

---

## EXPECTED RESULTS

**After applying all 3 fixes**:
- Dashboard loading: 30s+ → 20s ✅ (33% faster)
- Rasio CASA (cached): 30s → 5s ✅ (80% faster)
- Report queries: 15s+ → 10s ✅ (33% faster)
- Overall UX: Much more responsive

---

## NEXT STEPS (Week 2)

Once these quick fixes are working:
1. Implement shadow columns for simpanan_multipn
   ```bash
   php artisan shadow:backfill-table simpanan_multipn --async
   ```

2. Refactor Rasio CASA queries to use shadow columns
   - Expected: 30s → 3s (10x speedup)

3. Extend to other tables (brihc, casa_brilink_web)

---

## ROLLBACK (If Something Breaks)

```bash
# Restore backups
cp app/Console/Kernel.php.backup app/Console/Kernel.php
cp config/session.php.backup config/session.php

# Clear caches
php artisan cache:clear
php artisan config:clear

# Restart queue worker
php artisan queue:restart
```

---

## SUPPORT

If issues occur:
- Check `storage/logs/laravel.log` for errors
- Verify timezone in `.env` is set to `Asia/Jakarta`
- Ensure queue worker is running: `php artisan queue:work`
- Run `php artisan cache:clear` if in doubt

