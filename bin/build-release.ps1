<#
.SYNOPSIS
    Builds a release zip for MHM Rentiva.
.DESCRIPTION
    Creates a clean build in build/, installs production dependencies, removing dev files, and zips the result.
.EXAMPLE
    .\bin\build-release.ps1
#>

$ErrorActionPreference = "Stop"

# Configuration
$PluginSlug = "mhm-rentiva"
$SourceDir = Resolve-Path "$PSScriptRoot\.."
$BuildDir = "$SourceDir\build"
$DistIgnoreFile = "$SourceDir\.distignore"
$LogFile = "$SourceDir\build_debug.txt"

function Log-Debug {
    param($Message)
    Add-Content -Path $LogFile -Value "$(Get-Date): $Message"
}

if (Test-Path $LogFile) { Remove-Item $LogFile }
Log-Debug "Starting build script"
Log-Debug "Source: $SourceDir"
Log-Debug "Build: $BuildDir"

# Get Version
if (Test-Path "$SourceDir\mhm-rentiva.php") {
    $Content = Get-Content "$SourceDir\mhm-rentiva.php" -Raw
    if ($Content -match "define\('MHM_RENTIVA_VERSION', '([^']+)'\);") {
        $Version = $matches[1]
        Log-Debug "Detected Version: $Version"
    }
    else {
        Write-Error "Could not detect version from mhm-rentiva.php"
    }
}
else {
    Write-Error "mhm-rentiva.php not found."
}

# Pre-flight Checks (Hard Gates)
Write-Host "[INFO] Running Quality Gates (PHPCS, Plugin Check, Tests)..." -ForegroundColor Cyan

# Check Release Command
# We use cmd /c because composer is a batch file on Windows and PowerShell needs output propagation
# We run it directly here.
cmd /c "composer run check-release"
if ($LASTEXITCODE -ne 0) {
    Write-Error "[ERROR] Quality Gates Failed! Aborting release build."
    exit 1
}
Write-Host "[SUCCESS] Quality Gates Passed!" -ForegroundColor Green

Write-Host "[INFO] Starting build for $PluginSlug v$Version..." -ForegroundColor Cyan

# Clean Build Dir
if (Test-Path $BuildDir) {
    # Remove contents but keep dir, or verify removal
    Remove-Item $BuildDir -Recurse -Force
}
New-Item -ItemType Directory -Path $BuildDir -Force | Out-Null

$TargetDir = "$BuildDir\$PluginSlug"
New-Item -ItemType Directory -Path $TargetDir | Out-Null

# Copy Files
Write-Host "[INFO] Copying files to build directory..." -ForegroundColor Green

# Read .distignore to build exclusion list for Get-ChildItem if possible, or post-cleanup.
# PowerShell copy is slow for many files. Let's use git archive if available.
if (Get-Command git -ErrorAction SilentlyContinue) {
    Write-Host "[INFO] Using git archive for clean export..." -ForegroundColor Magenta
    git archive HEAD --output="$BuildDir\source.zip"
    Expand-Archive "$BuildDir\source.zip" -DestinationPath $TargetDir
    Remove-Item "$BuildDir\source.zip"
}
else {
    Write-Host "[WARN] Git not found, falling back to file copy..." -ForegroundColor Yellow
    Copy-Item "$SourceDir\*" "$TargetDir" -Recurse -Force
}

# Install Production Dependencies
if (Test-Path "$TargetDir\composer.json") {
    Write-Host "[INFO] Installing production dependencies..." -ForegroundColor Green
    Push-Location $TargetDir
    composer install --no-dev --optimize-autoloader --no-progress
    Pop-Location
}

# Apply .distignore (Post-processing to remove things that might have been copied/installed but strictly shouldn't be there)
# Note: git archive excludes .gitignore files, but .distignore might have more.
if (Test-Path $DistIgnoreFile) {
    Write-Host "[INFO] Applying .distignore rules..." -ForegroundColor Green
    $IgnorePatterns = Get-Content $DistIgnoreFile | Where-Object { $_ -notmatch "^#" -and $_.Trim() -ne "" }
    foreach ($Pattern in $IgnorePatterns) {
        $CleanPattern = $Pattern.Trim("/")
        # Recursive removal for directories
        if (Test-Path "$TargetDir\$CleanPattern") {
            Remove-Item "$TargetDir\$CleanPattern" -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    # Explicit cleanup of .distignore itself from the build
    if (Test-Path "$TargetDir\.distignore") { Remove-Item "$TargetDir\.distignore" -Force }
}

# Create Zip
$ZipName = "mhm-rentiva.$Version.zip"
$ZipPath = Join-Path $BuildDir $ZipName
Log-Debug "Zip Path: $ZipPath"

try {
    Log-Debug "Compressing..."
    Compress-Archive -Path $TargetDir -DestinationPath $ZipPath -Force -ErrorAction Stop
    Log-Debug "Compression done."
}
catch {
    Log-Debug "Compression Error: $_"
    Write-Error "Compression Failed: $_"
}

# Verify
if (Test-Path $ZipPath) {
    Log-Debug "Zip verified at $ZipPath"
    $Size = (Get-Item $ZipPath).Length / 1MB
    Write-Host "[SUCCESS] Build Complete! Artifact size: $(" {0:N2} " -f $Size) MB" -ForegroundColor Green
    Write-Host "Path: $ZipPath"
}
else {
    Log-Debug "Zip not found at $ZipPath"
    Write-Error "Build failed to create zip file."
}
