# DuckDNS IP Change Monitor
# Monitoring script untuk track IP changes dan verify DuckDNS update
# Run dengan PowerShell (Administrator)

# Configuration
$logDir = "D:\XAMPP\htdocs\project-ABAH\logs"
$logFile = "$logDir\ip_change_log.txt"
$healthLogFile = "$logDir\health_check_log.txt"
$domainName = "asixdashboard.duckdns.org"
$lastIP = ""
$checkInterval = 60  # Check every 60 seconds

# Create log directory if not exists
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

# Function to get current IP
function Get-CurrentIP {
    try {
        $response = Invoke-WebRequest -Uri "https://api.ipify.org" -UseBasicParsing -TimeoutSec 5
        return $response.Content.Trim()
    } catch {
        Write-Host "[ERROR] Failed to get IP: $_"
        return $null
    }
}

# Function to get DNS resolved IP
function Get-DNSResolvedIP {
    try {
        [System.Net.Dns]::GetHostAddresses($domainName) | Select-Object -First 1 -ExpandProperty IPAddressToString
    } catch {
        Write-Host "[ERROR] Failed to resolve domain: $_"
        return $null
    }
}

# Function to log message
function Log-Message {
    param([string]$message, [string]$type = "INFO")

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$type] $message"

    Write-Host $logEntry
    Add-Content $logFile -Value $logEntry
}

# Function to verify project accessibility
function Test-ProjectHealth {
    try {
        $response = Invoke-WebRequest -Uri "http://$domainName" -TimeoutSec 5 -ErrorAction Stop
        $statusCode = $response.StatusCode
        return $true, $statusCode
    } catch {
        return $false, $_.Exception.Message
    }
}

# Main monitoring loop
Write-Host "=========================================="
Write-Host "  DuckDNS IP Change Monitor"
Write-Host "  Domain: $domainName"
Write-Host "  Started: $(Get-Date)"
Write-Host "=========================================="
Write-Host ""

Log-Message "Monitor started. Domain: $domainName" "START"

while ($true) {
    try {
        # Get current public IP
        $currentIP = Get-CurrentIP

        if ($null -ne $currentIP) {
            # Check if IP changed
            if ($currentIP -ne $lastIP) {
                Log-Message "IP CHANGED: $lastIP → $currentIP" "CHANGE"

                # Wait for DuckDNS to update
                Write-Host "Waiting for DuckDNS to update DNS record..."
                Start-Sleep -Seconds 30

                # Check DNS resolved IP
                $dnsIP = Get-DNSResolvedIP
                if ($null -ne $dnsIP) {
                    Log-Message "DNS Resolved IP: $dnsIP" "DNS"

                    # Wait for DNS propagation
                    if ($dnsIP -ne $currentIP) {
                        Write-Host "Waiting for DNS propagation..."
                        Start-Sleep -Seconds 60
                        $dnsIP = Get-DNSResolvedIP
                    }
                }

                # Test project health
                $isHealthy, $result = Test-ProjectHealth
                if ($isHealthy) {
                    Log-Message "Project Health: ✓ OK (HTTP $result)" "HEALTH"
                } else {
                    Log-Message "Project Health: ✗ FAIL ($result)" "HEALTH"
                }

                $lastIP = $currentIP
            } else {
                # Log every 5 checks (every 5 minutes)
                $checkCount = [math]::Floor((Get-Random -Minimum 1 -Maximum 300) / 60)
                if ($checkCount % 5 -eq 0) {
                    $isHealthy, $status = Test-ProjectHealth
                    if ($isHealthy) {
                        Log-Message "Health check: ✓ OK (IP: $currentIP, HTTP: $status)" "PERIODIC"
                    } else {
                        Log-Message "Health check: ✗ FAIL (IP: $currentIP)" "PERIODIC"
                    }
                }
            }
        }

        # Wait before next check
        Start-Sleep -Seconds $checkInterval

    } catch {
        Log-Message "Monitor error: $_" "ERROR"
        Start-Sleep -Seconds $checkInterval
    }
}
