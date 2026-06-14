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

$args = @("--host=$hostName", "--port=$port", "--user=$dbUser", "--single-transaction", "--routines", "--triggers", "--databases", $dbName)
if ($dbPass -ne "") {
    $args = @("--password=$dbPass") + $args
}

& $MysqlDump @args | Out-File -LiteralPath $sqlPath -Encoding utf8
if ($LASTEXITCODE -ne 0) {
    throw "mysqldump falhou com codigo $LASTEXITCODE"
}

if ($encryptPass -ne "") {
    $encryptedPath = "$sqlPath.enc"
    & openssl enc -aes-256-cbc -salt -pbkdf2 -in $sqlPath -out $encryptedPath -pass "pass:$encryptPass"
    if ($LASTEXITCODE -ne 0) {
        throw "openssl falhou ao criptografar o backup"
    }
    Remove-Item -LiteralPath $sqlPath
    $finalPath = $encryptedPath
}

$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -LiteralPath $OutputDir -File |
    Where-Object { $_.LastWriteTime -lt $cutoff -and ($_.Name -like "$dbName-*.sql" -or $_.Name -like "$dbName-*.sql.enc") } |
    Remove-Item

Write-Output "Backup criado: $finalPath"
