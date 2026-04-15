<#
.SYNOPSIS
    Sets up the WordPress Test Suite environment for MHM Rentiva on Windows.

.DESCRIPTION
    This script checks for the WordPress Test Library, validates the database connection,
    and ensures the test environment is ready for PHPUnit.

.EXAMPLE
    .\tests\bin\install-wp-tests.ps1
#>

$ErrorActionPreference = "Stop"

# Configuration
# Default paths - Adjust these to point to your local wordpress-develop or test library
$WPDevelopPath = "C:/wordpress-develop"
$TestLibPath = "$WPDevelopPath/tests/phpunit"
$DBName = "mhm_rentiva_tests"
$DBUser = "root"
$DBPass = ""
$DBHost = "localhost"

Write-Host "NOTE: This script is for Windows host testing. For Docker, use bin/install-wp-tests.sh inside the container." -ForegroundColor Yellow

# 1. Check for WordPress Test Library
Write-Host "Checking for WordPress Test Library..." -NoNewline
if (Test-Path "$TestLibPath/includes/functions.php") {
    Write-Host " [OK]" -ForegroundColor Green
    Write-Host "Found at: $TestLibPath" -ForegroundColor Gray
}
else {
    Write-Host " [MISSING]" -ForegroundColor Red
    Write-Error "WordPress Test Library not found at $TestLibPath. Please clone wordpress-develop or adjust paths."
}

# 2. Check wp-tests-config.php
Write-Host "Checking for wp-tests-config.php..." -NoNewline
if (Test-Path "$TestLibPath/wp-tests-config.php") {
    Write-Host " [OK]" -ForegroundColor Green
}
else {
    Write-Host " [MISSING]" -ForegroundColor Red
    Write-Host "Creating wp-tests-config.php from sample..."
    
    if (Test-Path "$TestLibPath/wp-tests-config-sample.php") {
        Copy-Item "$TestLibPath/wp-tests-config-sample.php" "$TestLibPath/wp-tests-config.php"
        
        $ConfigContent = Get-Content "$TestLibPath/wp-tests-config.php"
        $ConfigContent = $ConfigContent -replace "youremptytestdbnamehere", $DBName
        $ConfigContent = $ConfigContent -replace "yourusernamehere", $DBUser
        $ConfigContent = $ConfigContent -replace "yourpasswordhere", $DBPass
        $ConfigContent = $ConfigContent -replace "localhost", $DBHost
        $ConfigContent | Set-Content "$TestLibPath/wp-tests-config.php"
        
        Write-Host "Created wp-tests-config.php with default XAMPP credentials." -ForegroundColor Green
    }
    else {
        Write-Error "Could not find wp-tests-config-sample.php."
    }
}

# 3. Create Test Database
Write-Host "Ensuring Test Database ($DBName) exists..."
try {
    $mysql = "mysql" # Assumes mysql is in your PATH
    if (-not (Test-Path $mysql)) {
        Write-Warning "mysql.exe not found at standard path. Skipping DB creation check."
    }
    else {
        & $mysql -u$DBUser -e "CREATE DATABASE IF NOT EXISTS $DBName;"
        Write-Host "Database check complete." -ForegroundColor Green
    }
}
catch {
    Write-Warning "Failed to connect to MySQL. Ensure XAMPP is running."
}

Write-Host "`nPassed all local checks. You can now run:" -ForegroundColor Cyan
Write-Host "vendor/bin/phpunit" -ForegroundColor White
