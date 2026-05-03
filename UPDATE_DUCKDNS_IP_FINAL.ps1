# DuckDNS IP Update - Final Version
$DUCKDNS_DOMAIN = "asixdashboard"
$DUCKDNS_TOKEN = "2c7b9832-a39d-4e8d-93de-6a58ecc6a77d"
$LOG_FILE = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log"

# Create log dir
$logDir = Split-Path -Parent $LOG_FILE
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }

# Disable progress preference for cleaner output
$ProgressPreference = "SilentlyContinue"
$ErrorActionPreference = "SilentlyContinue"

# Set TLS for HTTPS
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12, [System.Net.SecurityProtocolType]::Tls11, [System.Net.SecurityProtocolType]::Tls

# Simple logging
function Log {
    param([string]$msg)
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -LiteralPath $LOG_FILE -Value "[$ts] $msg" -ErrorAction SilentlyContinue
}

function GetPublicIP {
    # Try ipify
    try {
        $response = Invoke-WebRequest -Uri "https://api.ipify.org?format=text" -UseBasicParsing -TimeoutSec 5
        $ip = $response.Content.Trim()
        if ($ip -match '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') { return $ip }
    } catch { }

    # Try dyndns
    try {
        $response = Invoke-WebRequest -Uri "http://checkip.dyndns.org" -UseBasicParsing -TimeoutSec 5
        $html = $response.Content
        if ($html -match '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})') { return $matches[1] }
    } catch { }

    return $null
}

function GetDNSIP {
    try {
        $result = nslookup "$DUCKDNS_DOMAIN.duckdns.org" 2>$null | Select-String "Address" | Select-Object -Last 1
        if ($result) {
            $ip = ($result -split "\s+")[-1]
            if ($ip -match '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') { return $ip }
        }
    } catch { }
    return $null
}

function UpdateDuckDNS {
    param([string]$IP)
    try {
        $url = "https://www.duckdns.org/update?domains=$DUCKDNS_DOMAIN&token=$DUCKDNS_TOKEN&ip=$IP"
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
        # Content can be string or byte array - convert to string if needed
        if ($response.Content -is [byte[]]) {
            $content = [System.Text.Encoding]::ASCII.GetString($response.Content).Trim()
        } else {
            $content = $response.Content.Trim()
        }
        Log "DuckDNS response: $content"
        return ($content -eq "OK")
    } catch {
        Log "UpdateDuckDNS error: $_"
    }
    return $false
}

# Main logic
Log "START Update check at $(Get-Date -Format 'HH:mm:ss')"

# Get current IP
$currentIP = GetPublicIP
if ($null -eq $currentIP) {
    Log "ERROR: Could not get public IP"
    exit 1
}
Log "Current IP: $currentIP"

# Get DNS resolved IP
$dnsIP = GetDNSIP
Log "DNS IP: $dnsIP"

# Compare and update if needed
if ($dnsIP -ne $currentIP) {
    Log "MISMATCH: Current=$currentIP, DNS=$dnsIP - Updating DuckDNS..."
    $success = UpdateDuckDNS -IP $currentIP
    if ($success) {
        Log "SUCCESS: DuckDNS updated with IP $currentIP"
        Start-Sleep -Seconds 30
        $newDNS = GetDNSIP
        Log "Verified DNS: $newDNS"
    } else {
        Log "ERROR: Failed to update DuckDNS"
        exit 1
    }
} else {
    Log "OK: DNS already has correct IP ($currentIP)"
}

Log "END Update completed"
exit 0
