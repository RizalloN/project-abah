# DuckDNS IP Update Script v2 - Improved and Robust
# Configuration
$DUCKDNS_DOMAIN = "asixdashboard"
$DUCKDNS_TOKEN = "2c7b9832-a39d-4e8d-93de-6a58ecc6a77d"
$LOG_FILE = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log"

# Ensure log directory exists
$logDir = Split-Path -Parent $LOG_FILE
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

function Log-Message {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $entry = "[$timestamp] [$Level] $Message"
    Add-Content -LiteralPath $LOG_FILE -Value $entry -ErrorAction SilentlyContinue
    Write-Output $entry
}

function Get-PublicIP {
    try {
        Log-Message "Getting public IP..." "INFO"

        # Disable SSL certificate validation for better compatibility
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12, [System.Net.SecurityProtocolType]::Tls11, [System.Net.SecurityProtocolType]::Tls

        # Try multiple IP services for redundancy
        $services = @(
            @{url = "https://api.ipify.org?format=text"; name = "ipify"},
            @{url = "http://checkip.dyndns.org"; name = "dyndns"},
            @{url = "https://wtfismyip.com/text"; name = "wtfismyip"}
        )

        foreach ($service in $services) {
            try {
                Log-Message "Trying $($service.name)..." "DEBUG"
                $ProgressPreference = "SilentlyContinue"

                # Use Invoke-WebRequest with better compatibility
                $response = Invoke-WebRequest -Uri $service.url -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
                $ip = $response.Content.Trim()

                # Extract IP if response contains HTML (dyndns case)
                if ($ip -like "*<*") {
                    $ip = $ip -replace ".*?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*", '$1'
                }

                $ip = $ip.Trim()

                if ($ip -match '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') {
                    Log-Message "Got public IP: $ip (from $($service.name))" "SUCCESS"
                    return $ip
                }
            } catch {
                Log-Message "Failed with $($service.name): $($_.Exception.Message)" "DEBUG"
                continue
            }
        }
    } catch {
        Log-Message "Error in Get-PublicIP: $_" "ERROR"
    }

    Log-Message "Failed to get public IP after trying all services" "ERROR"
    return $null
}

function Get-DNSResolvedIP {
    param([string]$Domain)
    try {
        Log-Message "Resolving DNS for $Domain.duckdns.org..." "INFO"
        $fullDomain = "$Domain.duckdns.org"

        # Use nslookup as fallback if .Net DNS fails
        $nslookupResult = nslookup $fullDomain 2>$null | Select-String "Address" | Select-Object -First 1

        if ($nslookupResult) {
            $ip = ($nslookupResult -split "\s+")[-1]
            if ($ip -match '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') {
                Log-Message "DNS resolved to: $ip" "SUCCESS"
                return $ip
            }
        }

        # Fallback: try .Net DNS
        $addresses = [System.Net.Dns]::GetHostAddresses($fullDomain) 2>$null
        if ($addresses -and $addresses.Count -gt 0) {
            $ip = $addresses[0].IPAddressToString
            if ($ip -match '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') {
                Log-Message "DNS resolved to: $ip" "SUCCESS"
                return $ip
            }
        }
    } catch {
        Log-Message "Failed to resolve DNS: $_" "WARNING"
    }

    Log-Message "Could not resolve DNS (domain may not be set up yet)" "INFO"
    return $null
}

function Update-DuckDNS {
    param([string]$IP, [string]$Domain, [string]$Token)

    try {
        Log-Message "Updating DuckDNS with IP: $IP" "INFO"

        # Set TLS protocol for compatibility
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12, [System.Net.SecurityProtocolType]::Tls11, [System.Net.SecurityProtocolType]::Tls

        $url = "https://www.duckdns.org/update?domains=$Domain&token=$Token&ip=$IP"
        $ProgressPreference = "SilentlyContinue"

        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
        $content = $response.Content.Trim()

        Log-Message "DuckDNS API Response: '$content'" "INFO"

        if ($content -eq "OK") {
            Log-Message "DuckDNS update successful" "SUCCESS"
            return $true
        } else {
            Log-Message "DuckDNS returned: '$content'" "ERROR"
            return $false
        }
    } catch {
        Log-Message "DuckDNS update failed: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# ============================================
# MAIN EXECUTION
# ============================================

Log-Message "========================================" "START"
Log-Message "DuckDNS IP Update Started" "START"
Log-Message "Domain: $DUCKDNS_DOMAIN" "START"
Log-Message "========================================" "START"

# Check token
if (-not $DUCKDNS_TOKEN -or $DUCKDNS_TOKEN -eq "YOUR_TOKEN_HERE") {
    Log-Message "ERROR: DuckDNS token not configured!" "ERROR"
    exit 1
}

# Get current public IP
$currentIP = Get-PublicIP
if (-not $currentIP) {
    Log-Message "Failed to determine public IP - aborting" "ERROR"
    exit 1
}

# Get DNS resolved IP
$dnsIP = Get-DNSResolvedIP $DUCKDNS_DOMAIN

# Check if update is needed
if ($dnsIP -eq $currentIP) {
    Log-Message "DNS is already up-to-date (both are $currentIP)" "INFO"
    exit 0
}

Log-Message "IP mismatch detected! Current: $currentIP, DNS: $dnsIP" "INFO"

# Update DuckDNS
$success = Update-DuckDNS -IP $currentIP -Domain $DUCKDNS_DOMAIN -Token $DUCKDNS_TOKEN

if ($success) {
    Log-Message "Waiting 30 seconds for DNS propagation..." "INFO"
    Start-Sleep -Seconds 30

    $newDnsIP = Get-DNSResolvedIP $DUCKDNS_DOMAIN
    if ($newDnsIP -eq $currentIP) {
        Log-Message "DNS verified updated successfully!" "SUCCESS"
        Log-Message "========================================" "END"
        exit 0
    } else {
        Log-Message "DNS update in progress (current: $newDnsIP, expected: $currentIP)" "INFO"
        exit 0
    }
} else {
    Log-Message "Failed to update DuckDNS" "ERROR"
    Log-Message "========================================" "END"
    exit 1
}
