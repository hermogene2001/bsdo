<#
PowerShell script to create a keystore (optional) and build signed release APK/AAB.
Usage: run in project root `.rom C:\xampp\htdocs\bsdo\android-app` in PowerShell
#>
param(
    [string]$keystorePath = "keystore.jks",
    [string]$storePassword = "changeit",
    [string]$keyAlias = "bsdo_key",
    [string]$keyPassword = "changeit",
    [string]$validityDays = "3650"
)

function Create-KeystoreIfMissing {
    param($path, $storePassword, $keyAlias, $keyPassword, $validityDays)
    if (-Not (Test-Path $path)) {
        Write-Host "Keystore not found. Creating new keystore at $path"
        $keytool = "keytool"
        $cmd = "$keytool -genkeypair -alias $keyAlias -keyalg RSA -keysize 2048 -validity $validityDays -keystore $path -storepass $storePassword -keypass $keyPassword -dname \"CN=BSDO,O=BSDO,C=US\""
        Write-Host "Running: $cmd"
        iex $cmd
    } else {
        Write-Host "Keystore already exists at $path"
    }
}

# Create keystore if missing
Create-KeystoreIfMissing -path $keystorePath -storePassword $storePassword -keyAlias $keyAlias -keyPassword $keyPassword -validityDays $validityDays

# Write keystore.properties
$props = @"
storeFile=$keystorePath
storePassword=$storePassword
keyAlias=$keyAlias
keyPassword=$keyPassword
"@
Set-Content -Path "keystore.properties" -Value $props -Encoding UTF8
Write-Host "Wrote keystore.properties (do NOT commit this file to version control)."

# Run Gradle assembleRelease
Write-Host "Building release APK/AAB..."
if (Test-Path ".\gradlew") {
    .\gradlew assembleRelease
} else {
    Write-Host "gradlew not found. Attempting to run gradle (system installed)."
    gradle assembleRelease
}

Write-Host "Build finished. Check app/build/outputs for artifacts."
