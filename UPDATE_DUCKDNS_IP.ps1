# ============================================
# DuckDNS IP Update Script (Manual Immediate Update)
# ============================================
# Script ini LANGSUNG update IP ke DuckDNS tanpa menunggu polling
# Run: powershell -ExecutionPolicy Bypass -File UPDATE_DUCKDNS_IP.ps1

# CONFIGURATION - EDIT INI DENGAN DATA ANDA!
# ============================================
$DUCKDNS_DOMAIN = "asixdashboard"  # Nama domain di DuckDNS (tanpa .duckdns.org)
$DUCKDNS_TOKEN = "2c7b9832-a39d-4e8d-93de-6a58ecc6a77d"  # Get dari: https://www.duckdns.org/ → account
$LOG_FILE = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log"

# ============================================

# Ensure log directory exists
$logDir = [System.IO.Path]::GetDirectoryName($LOG_FILE)
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

function Log {
    param([string]$message, [string]$type = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$type] $message"
    Write-Host $logEntry
    Add-Content $LOG_FILE -Value $logEntry
}

function Get-PublicIP {
    try {
        $response = Invoke-WebRequest -Uri "https://api.ipify.org" -UseBasicParsing -TimeoutSec 5
        return $response.Content.Trim()
    } catch {
        Log "Failed to get public IP: $_" "ERROR"
        return $null
    }
}

function Update-DuckDNS {
    param([string]$ip, [string]$domain, [string]$token)

    try {
        $url = "https://www.duckdns.org/update?domains=$domain&token=$token&ip=$ip"
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
        $content = $response.Content.Trim()

        if ($content -eq "OK") {
            return $true, "DuckDNS update successful"
        } else {
            return $false, "DuckDNS returned: $content"
        }
    } catch {
        return $false, "DuckDNS update failed: $_"
    }
}

function Get-DNSResolvedIP {
    param([string]$domain)
    try {
        $fullDomain = "$domain.duckdns.org"
        $ip = [System.Net.Dns]::GetHostAddresses($fullDomain) | Select-Object -First 1 -ExpandProperty IPAddressToString
        return $ip
    } catch {
        Log "Failed to resolve DNS for $domain.duckdns.org: $_" "ERROR"
        return $null
    }
}

# ============================================
# MAIN EXECUTION
# ============================================

Write-Host "=========================================="
Write-Host "  DuckDNS IP Update Script"
Write-Host "  Domain: $DUCKDNS_DOMAIN.duckdns.org"
Write-Host "  Time: $(Get-Date)"
Write-Host "=========================================="
Write-Host ""

Log "=== DuckDNS IP Update Started ===" "START"

# Check if token is configured
if ($DUCKDNS_TOKEN -eq "YOUR_TOKEN_HERE") {
    Write-Host ""
    Write-Host "❌ ERROR: DUCKDNS_TOKEN not configured!"
    Write-Host ""
    Write-Host "CARA SETUP:"
    Write-Host "1. Buka: https://www.duckdns.org/"
    Write-Host "2. Login dengan akun Anda"
    Write-Host "3. Di dashboard, cari domain: $DUCKDNS_DOMAIN"
    Write-Host "4. Copy TOKEN dari tab Docs"
    Write-Host "5. Edit script ini, ganti 'YOUR_TOKEN_HERE' dengan TOKEN Anda"
    Write-Host "6. Save dan jalankan lagi"
    Write-Host ""
    Log "Token not configured - aborting" "ERROR"
    exit 1
}

# Get current public IP
Write-Host "Getting current public IP..."
$currentIP = Get-PublicIP

if ($null -eq $currentIP) {
    Write-Host "❌ Failed to get public IP. Check internet connection."
    Log "Failed to get public IP" "ERROR"
    exit 1
}

Write-Host "✓ Current Public IP: $currentIP"
Log "Current IP: $currentIP" "INFO"
Write-Host ""

# Get currently resolved DNS IP
Write-Host "Checking currently resolved DNS IP..."
$dnsIP = Get-DNSResolvedIP $DUCKDNS_DOMAIN

if ($null -ne $dnsIP) {
    Write-Host "✓ DNS currently resolves to: $dnsIP"
    Log "DNS currently resolves to: $dnsIP" "INFO"

    if ($dnsIP -eq $currentIP) {
        Write-Host "✓ DNS is ALREADY UP-TO-DATE!"
        Write-Host ""
        Log "DNS is already up-to-date - no action needed" "SUCCESS"
        exit 0
    }
} else {
    Write-Host "⚠ Could not resolve DNS (first time setup?)"
    Log "Could not resolve DNS" "WARNING"
}

Write-Host ""
Write-Host "⚠ IP MISMATCH DETECTED!"
Write-Host "  Current IP:  $currentIP"
Write-Host "  DNS Points:  $dnsIP"
Write-Host ""
Write-Host "Updating DuckDNS with new IP..."

# Update DuckDNS
$success, $message = Update-DuckDNS -ip $currentIP -domain $DUCKDNS_DOMAIN -token $DUCKDNS_TOKEN

if ($success) {
    Write-Host "✓ DuckDNS Updated: $message"
    Log "DuckDNS updated successfully: $message" "SUCCESS"

    Write-Host ""
    Write-Host "Waiting for DNS propagation (30 seconds)..."
    Start-Sleep -Seconds 30

    Write-Host "Verifying DNS resolution..."
    $newDnsIP = Get-DNSResolvedIP $DUCKDNS_DOMAIN

    if ($null -ne $newDnsIP) {
        if ($newDnsIP -eq $currentIP) {
            Write-Host "✅ SUCCESS! DNS is now updated:"
            Write-Host "   $DUCKDNS_DOMAIN.duckdns.org → $newDnsIP"
            Log "DNS verified updated to: $newDnsIP" "SUCCESS"
        } else {
            Write-Host "⚠ DNS update pending. Current: $newDnsIP (expected: $currentIP)"
            Write-Host "   Wait a few minutes for full propagation"
            Log "DNS update in progress. New IP: $newDnsIP" "INFO"
        }
    } else {
        Write-Host "⚠ Could not verify DNS update. Check again in 5 minutes."
        Log "Could not verify DNS update" "WARNING"
    }
} else {
    Write-Host "❌ Failed to update DuckDNS: $message"
    Log "Failed to update DuckDNS: $message" "ERROR"
    exit 1
}

Write-Host ""
Write-Host "=========================================="
Write-Host "  Update Complete"
Write-Host "=========================================="
Log "=== DuckDNS IP Update Completed ===" "END"
