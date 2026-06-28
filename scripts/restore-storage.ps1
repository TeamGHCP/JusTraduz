param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,
    [string]$EnvFile = "backend\.env",
    [switch]$ClearTarget
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

if (-not (Test-Path -LiteralPath $BackupPath)) {
    throw "BackupPath nao encontrado: $BackupPath"
}

$extractPath = Join-Path $env:TEMP ("justraduz-storage-restore-" + [guid]::NewGuid().ToString("N"))
$targets = [ordered]@{
    documents = Resolve-ProjectPath (Read-EnvValue $EnvFile "DOCUMENT_STORAGE_PATH" "storage-private/documents")
    attachments = Resolve-ProjectPath (Read-EnvValue $EnvFile "ATTACHMENT_STORAGE_PATH" "storage-private/message-attachments")
}

try {
    Expand-Archive -LiteralPath $BackupPath -DestinationPath $extractPath -Force

    foreach ($entry in $targets.GetEnumerator()) {
        $source = Join-Path $extractPath $entry.Key
        if (-not (Test-Path -LiteralPath $source)) { continue }
        if ($entry.Value -eq "") { continue }

        if ($ClearTarget -and (Test-Path -LiteralPath $entry.Value)) {
            Remove-Item -LiteralPath $entry.Value -Recurse -Force
        }

        New-Item -ItemType Directory -Force -Path $entry.Value | Out-Null
        Copy-Item -LiteralPath (Join-Path $source "*") -Destination $entry.Value -Recurse -Force
    }
} finally {
    if (Test-Path -LiteralPath $extractPath) {
        Remove-Item -LiteralPath $extractPath -Recurse -Force
    }
}

Write-Output "Restore de storage concluido a partir de: $BackupPath"
