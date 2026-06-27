param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,
    [string]$EnvFile = "backend\.env",
    [string]$TargetDatabase = "",
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
$dbName = Read-EnvValue $EnvFile "DB_NAME" "justraduz"
$encryptPass = Read-EnvValue $EnvFile "BACKUP_ENCRYPTION_PASSWORD" ""

if ($TargetDatabase -eq "") { $TargetDatabase = $dbName }
if ($TargetDatabase -notmatch '^[A-Za-z0-9_]+$') {
    throw "TargetDatabase invalido. Use apenas letras, numeros e underscore."
}

function Resolve-OpenSsl {
    $command = Get-Command openssl -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    foreach ($candidate in @("C:\xampp\apache\bin\openssl.exe", "C:\xampp\php\extras\openssl\openssl.exe")) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }

    throw "OpenSSL nao encontrado. Instale-o ou adicione o executavel ao PATH."
}

$restorePath = $BackupPath
if ($BackupPath.EndsWith(".enc")) {
    if ($encryptPass -eq "") {
        throw "BACKUP_ENCRYPTION_PASSWORD e obrigatorio para restaurar backup criptografado."
    }
    try {
        $openSsl = Resolve-OpenSsl
        $previousBackupPass = [Environment]::GetEnvironmentVariable("JTD_BACKUP_PASSWORD", "Process")
        [Environment]::SetEnvironmentVariable("JTD_BACKUP_PASSWORD", $encryptPass, "Process")

        $restorePath = [System.IO.Path]::GetTempFileName() + ".sql"
        & $openSsl enc -d -aes-256-cbc -pbkdf2 -in $BackupPath -out $restorePath -pass "env:JTD_BACKUP_PASSWORD"
        if ($LASTEXITCODE -ne 0) {
            throw "openssl falhou ao descriptografar o backup"
        }
    } finally {
        [Environment]::SetEnvironmentVariable("JTD_BACKUP_PASSWORD", $previousBackupPass, "Process")
    }
}

try {
    $previousMysqlPwd = [Environment]::GetEnvironmentVariable("MYSQL_PWD", "Process")
    if ($dbPass -ne "") {
        [Environment]::SetEnvironmentVariable("MYSQL_PWD", $dbPass, "Process")
    }

    $connectionArgs = @("--host=$hostName", "--port=$port", "--user=$dbUser")
    & $Mysql @connectionArgs --execute="CREATE DATABASE IF NOT EXISTS ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    if ($LASTEXITCODE -ne 0) {
        throw "mysql falhou ao preparar o banco de destino"
    }

    $restoreArgs = $connectionArgs + @("--database=$TargetDatabase")
    Get-Content -LiteralPath $restorePath -Raw | & $Mysql @restoreArgs
    if ($LASTEXITCODE -ne 0) {
        throw "mysql falhou ao restaurar o backup"
    }
} finally {
    [Environment]::SetEnvironmentVariable("MYSQL_PWD", $previousMysqlPwd, "Process")
}

if ($restorePath -ne $BackupPath) {
    Remove-Item -LiteralPath $restorePath -Force
}

Write-Output "Restore concluido em '$TargetDatabase' a partir de: $BackupPath"
