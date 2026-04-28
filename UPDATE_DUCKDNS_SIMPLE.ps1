# DuckDNS IP Update - Simple Version (No Encoding Issues)
# Usage: powershell -ExecutionPolicy Bypass -File UPDATE_DUCKDNS_SIMPLE.ps1

# CONFIG
$DOMAIN = "asixdashboard"
$TOKEN = "2c7b9832-a39d-4e8d-93de-6a58ecc6a77d"
$LOG = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log"

# Ensure log directory exists
$logDir = Split-Path $LOG
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }

function LogMsg($msg) {
    $time = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $entry = "[$time] $msg"
    Write-Host $entry
    Add-Content $LOG -Value $entry
}

Write-Host "=========================================="
Write-Host "DuckDNS IP Update Script"
Write-Host "Domain: $DOMAIN.duckdns.org"
Write-Host "=========================================="
Write-Host ""

LogMsg "UPDATE STARTED"

# Step 1: Get current public IP
Write-Host "STEP 1: Getting current public IP..."
try {
    $ip = (Invoke-WebRequest -Uri "https://api.ipify.org" -UseBasicParsing -TimeoutSec 5).Content.Trim()
    Write-Host "Current IP: $ip"
    LogMsg "Current IP: $ip"
} catch {
    Write-Host "ERROR: Cannot get public IP!"
    LogMsg "ERROR: Cannot get public IP: $_"
    exit 1
}

Write-Host ""
Write-Host "STEP 2: Updating DuckDNS..."
Write-Host "Sending: domain=$DOMAIN, token=*****, ip=$ip"

# Step 2: Call DuckDNS API
try {
    $url = "https://www.duckdns.org/update?domains=$DOMAIN&token=$TOKEN&ip=$ip"
    $response = [System.Text.Encoding]::UTF8.GetString((Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5).Content).Trim()
    Write-Host "DuckDNS Response: $response"
    LogMsg "DuckDNS Response: $response"

    if ($response -eq "OK") {
        Write-Host ""
        Write-Host "SUCCESS! IP updated to DuckDNS"
        LogMsg "SUCCESS! IP $ip sent to DuckDNS"
        Write-Host ""
        Write-Host "Waiting 30 seconds for DNS propagation..."
        Start-Sleep -Seconds 30

        # Step 3: Verify
        Write-Host ""
        Write-Host "STEP 3: Verifying DNS resolution..."
        try {
            $dnsIP = [System.Net.Dns]::GetHostAddresses("$DOMAIN.duckdns.org") | Select-Object -First 1 -ExpandProperty IPAddressToString
            Write-Host "DNS now resolves to: $dnsIP"
            LogMsg "DNS now resolves to: $dnsIP"

            if ($dnsIP -eq $ip) {
                Write-Host ""
                Write-Host "[OK] Domain is UPDATED and accessible!"
                LogMsg "VERIFIED: Domain points to correct IP"
            } else {
                Write-Host ""
                Write-Host "[WAIT] DNS update in progress"
                Write-Host "Expected: $ip"
                Write-Host "Current:  $dnsIP"
                LogMsg "DNS update in progress: expecting $ip, currently $dnsIP"
            }
        } catch {
            Write-Host "[INFO] Could not verify DNS (try again in 5 min)"
            LogMsg "Could not verify DNS: $_"
        }
    } else {
        Write-Host ""
        Write-Host "ERROR from DuckDNS: $response"
        LogMsg "ERROR from DuckDNS: $response"

        if ($response -eq "FAIL") {
            Write-Host ""
            Write-Host "Possible causes:"
            Write-Host "1. Token is INVALID or EXPIRED"
            Write-Host "2. Domain name is wrong"
            Write-Host "3. DuckDNS server issue"
            Write-Host ""
            Write-Host "Check your token at: https://www.duckdns.org/"
        }
        exit 1
    }
} catch {
    Write-Host "ERROR: Update failed: $_"
    LogMsg "ERROR: Update failed: $_"
    exit 1
}

Write-Host ""
Write-Host "=========================================="
Write-Host "Complete!"
Write-Host "=========================================="
LogMsg "UPDATE COMPLETED"
