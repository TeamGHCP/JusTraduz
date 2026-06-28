param(
    [string]$EnvFile = "backend\.env",
    [string]$OutputDir = "backups",
    [int]$RetentionDays = 14
)

$ErrorActionPreference = "Stop"

function Read-EnvValue([string]$Path, [string]$Key, [string]$Default = "") {
    if (-not (Test-Path -LiteralPath $Path)) { return $Default }
    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line.Trim().StartsWith("#") -or -not $line.Contains("=")) { continue }
        $parts = $line.Split("=", 2)
        if ($parts[0].Trim() -eq $Key) {
            return $parts[1].Trim().Trim('"').Trim("'")
        }
    }
    return $Default
}

function Resolve-ProjectPath([string]$Path) {
    if ($Path -eq "") { return "" }
    if ([System.IO.Path]::IsPathRooted($Path)) { return $Path }
    return Join-Path (Get-Location) $Path
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$archivePath = Join-Path $OutputDir "justraduz-storage-$timestamp.zip"
$stagingPath = Join-Path $env:TEMP "justraduz-storage-$timestamp"

$paths = [ordered]@{
    documents = Resolve-ProjectPath (Read-EnvValue $EnvFile "DOCUMENT_STORAGE_PATH" "storage-private/documents")
    attachments = Resolve-ProjectPath (Read-EnvValue $EnvFile "ATTACHMENT_STORAGE_PATH" "storage-private/message-attachments")
}

try {
    New-Item -ItemType Directory -Force -Path $stagingPath | Out-Null

    foreach ($entry in $paths.GetEnumerator()) {
        if ($entry.Value -eq "" -or -not (Test-Path -LiteralPath $entry.Value)) { continue }
        $target = Join-Path $stagingPath $entry.Key
        Copy-Item -LiteralPath $entry.Value -Destination $target -Recurse -Force
    }

    Compress-Archive -LiteralPath (Join-Path $stagingPath "*") -DestinationPath $archivePath -Force
} finally {
    if (Test-Path -LiteralPath $stagingPath) {
        Remove-Item -LiteralPath $stagingPath -Recurse -Force
    }
}

$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -LiteralPath $OutputDir -File |
    Where-Object { $_.LastWriteTime -lt $cutoff -and $_.Name -like "justraduz-storage-*.zip" } |
    Remove-Item

Write-Output "Backup de storage criado: $archivePath"
