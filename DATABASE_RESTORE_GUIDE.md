# Database Restore Guide - Compressed Backups (.sql.gz)

## 🔄 Restoring from Optimized Backups

All new backups created with the optimization are compressed as `.sql.gz` files. This guide shows how to restore them.

---

## Method 1: Restore Using Gzip Pipe (Recommended)

### Linux/macOS
```bash
# Direct restore without decompressing intermediate file
gunzip < /path/to/backup.sql.gz | mysql -u username -p database_name
```

### Windows (Git Bash or WSL)
```bash
# Same command works in Git Bash
gunzip < C:\path\to\backup.sql.gz | mysql -u username -p database_name
```

### Windows (Command Prompt - PowerShell)
```powershell
# Using PowerShell
$file = 'C:\path\to\backup.sql.gz'
$gzip = 'C:\xampp\php\gzip.exe'  # or where gzip is installed
& $gzip -dc $file | mysql -u username -p database_name
```

**Advantages:**
- ✅ No intermediate uncompressed file needed
- ✅ Fast streaming restore
- ✅ Memory efficient

---

## Method 2: Decompress Then Restore

### Step 1: Decompress
```bash
# Linux/macOS
gunzip backup.sql.gz
# Creates: backup.sql

# Windows (Git Bash)
gunzip backup.sql.gz

# Windows (PowerShell)
$gzip = 'C:\xampp\php\gzip.exe'
& $gzip -d C:\path\to\backup.sql.gz
```

### Step 2: Import
```bash
# All platforms
mysql -u username -p database_name < backup.sql
```

**Use When:**
- You want to verify SQL before importing
- Need to edit backup content
- Prefer two-step process

---

## Method 3: Decompress Keep Original

### Using gzip -k (keep) flag
```bash
# Decompresses but keeps original .gz file
gunzip -k backup.sql.gz
# Result: backup.sql (new) + backup.sql.gz (original)

# Then import
mysql -u username -p database_name < backup.sql

# Clean up when done
rm backup.sql
```

---

## Method 4: Via Docker (If Available)

```bash
# Mount backup file to container
docker run --rm \
  -v /host/path/backups:/backups \
  -e MYSQL_PWD=password \
  mysql:8.0 \
  sh -c 'gunzip < /backups/backup.sql.gz | mysql -h host.docker.internal -u user database_name'
```

---

## 🔍 Verify Backup Integrity Before Restore

### Check File Signature
```bash
# File should identify as gzip
file backup.sql.gz
# Output: gzip compressed data

# Linux/macOS
gunzip -t backup.sql.gz
# Output: (no error = file OK)

# Windows (PowerShell)
$gzip = 'C:\xampp\php\gzip.exe'
& $gzip -t C:\path\to\backup.sql.gz
```

### Preview SQL Content (First 1000 Lines)
```bash
# See what's in backup without full decompress
gunzip -dc backup.sql.gz | head -1000

# Windows PowerShell
$gzip = 'C:\xampp\php\gzip.exe'
& $gzip -dc backup.sql.gz | Select-Object -First 1000
```

### Check Database Structure Only
```bash
# Restore schema without data
gunzip -dc backup.sql.gz | grep '^CREATE TABLE' | head -5
```

---

## ⚠️ Restore Safety Precautions

### 1. Backup Current Database First
```bash
mysqldump -u root -p current_database > backup_before_restore.sql
```

### 2. Test Restore to Separate Database
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE database_name_test;"

# Restore to test database first
gunzip < backup.sql.gz | mysql -u root -p database_name_test
```

### 3. Verify Data After Restore
```bash
# Check table counts
mysql -u root -p -e "USE database_name_test; SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='database_name_test';"

# Compare with original
mysql -u root -p -e "USE original_database; SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='original_database';"
```

---

## 🚨 Troubleshooting Restore Issues

### Issue: "gunzip: command not found"
**Solution:** Install gzip or use fallback
```bash
# Install on Linux
sudo apt-get install gzip  # Debian/Ubuntu
sudo yum install gzip      # RHEL/CentOS

# Install on Windows
# Download from: https://sourceforge.net/projects/gzip/
# Or install Git for Windows (includes gzip)
```

### Issue: "mysql: command not found"
**Solution:** Install MySQL client
```bash
# Linux
sudo apt-get install mysql-client

# macOS
brew install mysql-client

# Windows
# Add MySQL bin directory to PATH or use full path:
"C:\xampp\mysql\bin\mysql.exe" -u user -p database < backup.sql
```

### Issue: "Access denied for user"
**Solution:** Verify credentials
```bash
# Test connection first
mysql -u username -p -h localhost -e "SELECT 1;"

# Then try restore with explicit parameters
gunzip < backup.sql.gz | mysql -h localhost -u username -p --default-character-set=utf8mb4 database_name
```

### Issue: "Error: Unknown database"
**Solution:** Create database first
```bash
# If database doesn't exist
mysql -u root -p -e "CREATE DATABASE database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Then restore
gunzip < backup.sql.gz | mysql -u root -p database_name
```

### Issue: "Out of memory"
**Solution:** Use alternative restore method
```bash
# Method 1: Restore with temp table option
gunzip -dc backup.sql.gz | mysql -u user -p --max_allowed_packet=512M database_name

# Method 2: Split and restore in chunks
gunzip < backup.sql.gz > backup.sql
split -l 100000 backup.sql backup_chunk_
for file in backup_chunk_*; do
    mysql -u user -p database_name < $file
done
```

### Issue: "Syntax error at line X"
**Solution:** May be corrupted, re-verify
```bash
# Check integrity
gunzip -t backup.sql.gz

# If OK, try restoring to test database and inspect error
gunzip -dc backup.sql.gz | head -10000 | mysql -u user -p test_db 2>&1 | grep -i error
```

---

## 📊 Restore Performance Tips

### Optimize for Speed
```bash
# Disable indexes/constraints during import, rebuild after
mysql -u user -p database_name << EOF
SET UNIQUE_CHECKS=0;
SET FOREIGN_KEY_CHECKS=0;
EOF

# Then import
gunzip < backup.sql.gz | mysql -u user -p database_name

# Re-enable and rebuild
mysql -u user -p database_name << EOF
SET UNIQUE_CHECKS=1;
SET FOREIGN_KEY_CHECKS=1;
ANALYZE TABLE table_name;
EOF
```

### Monitor Restore Progress
```bash
# Linux: Watch import in real-time
tail -f /var/log/mysql/general_query.log | grep -i "INSERT\|UPDATE"

# Check database size during restore
watch -n 1 'du -sh /var/lib/mysql/database_name/'
```

---

## 🔐 Backup Encryption & Security

### Encrypting Backup Before Transfer
```bash
# Create encrypted backup (gzip + GPG)
gunzip -dc backup.sql.gz | gpg --encrypt --recipient your@email.com > backup.sql.gpg

# Decrypt and restore
gpg --decrypt backup.sql.gpg | mysql -u user -p database_name

# Or keep both encrypted and compressed
gpg --decrypt backup.sql.gpg | gzip > backup.sql.gz.gpg
```

### Secure Transfer
```bash
# Via SSH
scp backup.sql.gz user@remote-server:/backup/

# Via SFTP
sftp user@remote-server
put backup.sql.gz /backup/

# Via secure pipe
cat backup.sql.gz | ssh user@remote-server 'cat > /backup/backup.sql.gz'
```

---

## 📋 Restore Checklist

Before restoring production database:
- [ ] Backup current database to file
- [ ] Verify backup file integrity (`gunzip -t`)
- [ ] Test restore to separate database first
- [ ] Compare row counts before/after restore
- [ ] Check for MySQL errors in restore log
- [ ] Verify critical tables after restore
- [ ] Test application connection to restored database
- [ ] Confirm all foreign keys intact

---

## 🔄 Automated Restore Script

### Bash Script
```bash
#!/bin/bash
# restore_backup.sh

BACKUP_FILE="$1"
DATABASE="$2"
USER="$3"
PASSWORD="$4"

if [ -z "$BACKUP_FILE" ] || [ -z "$DATABASE" ]; then
    echo "Usage: $0 <backup_file> <database> [user] [password]"
    exit 1
fi

USER=${USER:-root}
PASS_OPT=""
if [ ! -z "$PASSWORD" ]; then
    PASS_OPT="-p$PASSWORD"
fi

echo "Starting restore from $BACKUP_FILE to $DATABASE..."
gunzip < "$BACKUP_FILE" | mysql -u "$USER" $PASS_OPT -h localhost "$DATABASE"

if [ $? -eq 0 ]; then
    echo "✅ Restore completed successfully"
else
    echo "❌ Restore failed"
    exit 1
fi
```

### Usage
```bash
chmod +x restore_backup.sh
./restore_backup.sh backup.sql.gz database_name root password
```

### PowerShell Script
```powershell
# restore_backup.ps1
param(
    [string]$BackupFile,
    [string]$Database,
    [string]$Username = "root",
    [string]$Password,
    [string]$MysqlPath = "C:\xampp\mysql\bin\mysql.exe"
)

if (-not (Test-Path $BackupFile)) {
    Write-Host "Backup file not found: $BackupFile"
    exit 1
}

$gzip = "C:\xampp\php\gzip.exe"
if (-not (Test-Path $gzip)) {
    $gzip = "gzip"
}

$passwordArg = ""
if ($Password) {
    $passwordArg = "--password=$Password"
}

Write-Host "Restoring from $BackupFile to $Database..."
& $gzip -dc $BackupFile | & $MysqlPath --user=$Username $passwordArg $Database

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Restore completed successfully"
} else {
    Write-Host "❌ Restore failed (exit code: $LASTEXITCODE)"
}
```

### Usage
```powershell
.\restore_backup.ps1 -BackupFile "C:\backup.sql.gz" -Database "mydb" -Username "root" -Password "pass"
```

---

## 📚 Format Reference

### .sql.gz File Properties
- **Format:** Gzip-compressed SQL text
- **Compression Level:** 9 (default gzip max)
- **Character Set:** UTF-8 (with BOM handling)
- **Size:** ~70-85% smaller than uncompressed
- **Integrity:** Verifiable with `gunzip -t`

### Compatibility
- ✅ Works with all MySQL versions 5.7+
- ✅ Works with MariaDB 10.0+
- ✅ Portable across Windows/Linux/macOS
- ✅ Standard gzip format (not proprietary)

---

## 🎯 Quick Restore Commands

### Fastest (Direct Pipe)
```bash
gunzip < backup.sql.gz | mysql -u user -p database
```

### Safest (Test First)
```bash
# Test database
gunzip < backup.sql.gz | mysql -u user -p test_db
# Production (after verification)
gunzip < backup.sql.gz | mysql -u user -p prod_db
```

### Most Compatible (Manual Steps)
```bash
gunzip backup.sql.gz           # Creates backup.sql
mysql -u user -p db < backup.sql  # Restore from .sql
rm backup.sql                  # Cleanup
```

---

**Last Updated:** 2026-04-28  
**Backup Format:** .sql.gz (gzip compressed)  
**Status:** ✅ Production Ready
