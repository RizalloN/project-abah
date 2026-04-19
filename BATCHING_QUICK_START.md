# Snapshot Batching - Quick Start Guide

## TL;DR - 5 Menit untuk Setup

### 1. Clear Pending Jobs (Once)
```bash
# Proses semua jobs yang tertumpuk
php artisan queue:work --queue=default,imports-high --stop-when-empty
# Tunggu sampai selesai (~2-5 menit)
```

### 2. Start Queue Worker (Production)
```bash
# Pilih SALAH SATU:

# Option A: Simple (Terminal 1)
php artisan queue:work --queue=default,imports-high --timeout=120

# Option B: With Auto-Restart (Terminal 1)  
php artisan queue:ensure-running --check-interval=60

# Option C: Best (Linux/Mac - add to crontab)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Check Status
```bash
php artisan snapshot:manage-batches status
```

**Done!** Sistem now:
- ✅ Automatically batches import jobs
- ✅ Auto-flushes batches setiap minute
- ✅ Adapts to your import volume
- ✅ Prevents queue from stacking up

---

## Common Commands

```bash
# Check queue & batch health
php artisan snapshot:manage-batches status

# View current configuration
php artisan snapshot:manage-batches config

# Manually flush a batch (if needed)
php artisan snapshot:manage-batches flush-due

# Emergency: clear all batches
php artisan snapshot:manage-batches reset --force

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## How It Works

When user uploads file:
```
1. Import starts → triggers snapshot sync
2. Sync request added to BATCH (not immediately dispatched)
3. Batch waits max 12 seconds (or collects 10 requests)
4. When ready → ExecuteBatchedSnapshotJob dispatched (1 job for N imports)
5. Queue worker processes the batch job
6. All N snapshot syncs happen in one job execution
```

**Result**: Instead of 10 jobs in queue → 1 job in queue ✅

---

## Troubleshooting

### "Why are jobs still pending?"
```bash
# Check if worker is running
ps aux | grep queue:work

# If not, start it:
php artisan queue:work --queue=default,imports-high --timeout=120
```

### "How do I disable batching?"
```php
// Edit: app/Support/SnapshotBatchConfig.php
const ENABLED = false;  // Batching OFF
// Restart queue worker
```

### "Batches not flushing automatically?"
```bash
# Check if scheduler is running
ps aux | grep schedule:run

# Or manually flush:
php artisan snapshot:manage-batches flush-due
```

### "How much faster is it?"
```
Before: 10 separate jobs queued
After: 1 batched job queued
Reduction: 90% fewer jobs!
```

---

## For Production

**Use supervisor or systemd to manage queue worker**:

### Supervisor (Linux/Mac)
Create `/etc/supervisor/conf.d/queue-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=default,imports-high --timeout=120
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Systemd (Modern Linux)
Create `/etc/systemd/system/laravel-queue.service`:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php artisan queue:work --queue=default,imports-high --timeout=120
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue
```

### Docker
```dockerfile
# In Dockerfile
FROM php:8.2-cli
# ... setup ...

# Queue worker entrypoint
CMD ["php", "artisan", "queue:work", "--queue=default,imports-high", "--timeout=120"]
```

---

## Monitoring Tips

### Watch queue in real-time
```bash
watch -n 2 'php artisan snapshot:manage-batches status'
```

### Check logs for batching events
```bash
tail -f storage/logs/laravel.log | grep -i batch
```

### Monitor failed jobs
```bash
php artisan queue:failed

# Clear old failures
php artisan queue:flush
```

---

## Performance Notes

### Batching Metrics
- **Default batch size**: 10 requests
- **Default timeout**: 12 seconds
- **Adaptive to load**: Adjusts based on queue size
- **Cache overhead**: < 1ms per request
- **Dispatch overhead**: < 5ms per batch

### When Batching Works Best
✅ High volume imports (10+ files per minute)  
✅ Similar table imports (better aggregation)  
✅ Small snapshot tables  

### When Batching Might Not Help
❌ Single import (no aggregation benefit)  
❌ Very different tables (less efficient grouping)  
❌ Snapshot sync is bottleneck (use SQL optimization instead)

---

## Still Questions?

See detailed guide: `SNAPSHOT_BATCHING_OPTIMIZATION_v2.md`

Or check implementation:
- `app/Support/SnapshotBatchConfig.php` - Configuration
- `app/Support/SnapshotBatchAggregator.php` - Batching logic
- `app/Jobs/ExecuteBatchedSnapshotJob.php` - Job executor
- `app/Console/Commands/ManageSnapshotBatches.php` - CLI tools
- `app/Console/Kernel.php` - Scheduler

---

**Enjoy your faster snapshot syncing!** 🚀
