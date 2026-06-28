param(
    [string]$EnvFile = "backend\.env",
    [string]$OutputDir = "backups",
    [string]$MysqlDump = "C:\xampp\mysql\bin\mysqldump.exe",
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

function Resolve-OpenSsl {
    $command = Get-Command openssl -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    foreach ($candidate in @("C:\xampp\apache\bin\openssl.exe", "C:\xampp\php\extras\openssl\openssl.exe")) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }

    throw "OpenSSL nao encontrado. Instale-o ou adicione o executavel ao PATH."
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$hostName = Read-EnvValue $EnvFile "DB_HOST" "localhost"
$port = Read-EnvValue $EnvFile "DB_PORT" "3306"
$dbName = Read-EnvValue $EnvFile "DB_NAME" "justraduz"
$dbUser = Read-EnvValue $EnvFile "DB_USER" "root"
$dbPass = Read-EnvValue $EnvFile "DB_PASS" ""
$encryptPass = Read-EnvValue $EnvFile "BACKUP_ENCRYPTION_PASSWORD" ""

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$sqlPath = Join-Path $OutputDir "$dbName-$timestamp.sql"
$finalPath = $sqlPath

try {
    $previousMysqlPwd = [Environment]::GetEnvironmentVariable("MYSQL_PWD", "Process")
    if ($dbPass -ne "") {
        [Environment]::SetEnvironmentVariable("MYSQL_PWD", $dbPass, "Process")
    }

    $args = @("--host=$hostName", "--port=$port", "--user=$dbUser", "--single-transaction", "--routines", "--triggers", $dbName)
    & $MysqlDump @args | Out-File -LiteralPath $sqlPath -Encoding utf8
    if ($LASTEXITCODE -ne 0) {
        throw "mysqldump falhou com codigo $LASTEXITCODE"
    }
} finally {
    [Environment]::SetEnvironmentVariable("MYSQL_PWD", $previousMysqlPwd, "Process")
}

if ($encryptPass -ne "") {
    try {
        $openSsl = Resolve-OpenSsl
        $previousBackupPass = [Environment]::GetEnvironmentVariable("JTD_BACKUP_PASSWORD", "Process")
        [Environment]::SetEnvironmentVariable("JTD_BACKUP_PASSWORD", $encryptPass, "Process")

        $encryptedPath = "$sqlPath.enc"
        & $openSsl enc -aes-256-cbc -salt -pbkdf2 -in $sqlPath -out $encryptedPath -pass "env:JTD_BACKUP_PASSWORD"
        if ($LASTEXITCODE -ne 0) {
            throw "openssl falhou ao criptografar o backup"
        }
        Remove-Item -LiteralPath $sqlPath
        $finalPath = $encryptedPath
    } finally {
        [Environment]::SetEnvironmentVariable("JTD_BACKUP_PASSWORD", $previousBackupPass, "Process")
    }
}

$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -LiteralPath $OutputDir -File |
    Where-Object { $_.LastWriteTime -lt $cutoff -and ($_.Name -like "$dbName-*.sql" -or $_.Name -like "$dbName-*.sql.enc") } |
    Remove-Item

Write-Output "Backup criado: $finalPath"
