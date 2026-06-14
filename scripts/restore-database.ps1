param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,
    [string]$EnvFile = "backend\.env",
    [string]$Mysql = "C:\xampp\mysql\bin\mysql.exe"
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

$hostName = Read-EnvValue $EnvFile "DB_HOST" "localhost"
$port = Read-EnvValue $EnvFile "DB_PORT" "3306"
$dbUser = Read-EnvValue $EnvFile "DB_USER" "root"
$dbPass = Read-EnvValue $EnvFile "DB_PASS" ""
$encryptPass = Read-EnvValue $EnvFile "BACKUP_ENCRYPTION_PASSWORD" ""

$restorePath = $BackupPath
if ($BackupPath.EndsWith(".enc")) {
    if ($encryptPass -eq "") {
        throw "BACKUP_ENCRYPTION_PASSWORD e obrigatorio para restaurar backup criptografado."
    }
    $restorePath = [System.IO.Path]::GetTempFileName() + ".sql"
    & openssl enc -d -aes-256-cbc -pbkdf2 -in $BackupPath -out $restorePath -pass "pass:$encryptPass"
    if ($LASTEXITCODE -ne 0) {
        throw "openssl falhou ao descriptografar o backup"
    }
}

$args = @("--host=$hostName", "--port=$port", "--user=$dbUser")
if ($dbPass -ne "") {
    $args = @("--password=$dbPass") + $args
}

Get-Content -LiteralPath $restorePath -Raw | & $Mysql @args
if ($LASTEXITCODE -ne 0) {
    throw "mysql falhou ao restaurar o backup"
}

if ($restorePath -ne $BackupPath) {
    Remove-Item -LiteralPath $restorePath -Force
}

Write-Output "Restore concluido a partir de: $BackupPath"
