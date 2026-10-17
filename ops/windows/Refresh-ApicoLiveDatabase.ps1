[CmdletBinding(SupportsShouldProcess, ConfirmImpact = 'High')]
param(
    [string]$Server = '5.189.156.240',
    [string]$SshUser = 'apico',
    [string]$RemoteAppDirectory = '/var/www/apico',
    [string]$RemoteBackupDirectory = '/home/apico/backups',
    [string]$ProjectDirectory = (Resolve-Path "$PSScriptRoot\..\.."),
    [string]$LocalBackupDirectory = "$HOME\apico-backup",
    [string]$IdentityFile,
    [switch]$SkipRemoteBackup
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Read-DotEnvValue {
    param([string]$Path, [string]$Name)

    $line = Get-Content -LiteralPath $Path |
        Where-Object { $_ -match "^$([regex]::Escape($Name))=" } |
        Select-Object -Last 1

    if (-not $line) { return $null }
    $value = $line.Substring($line.IndexOf('=') + 1).Trim()
    if (($value.StartsWith('"') -and $value.EndsWith('"')) -or
        ($value.StartsWith("'") -and $value.EndsWith("'"))) {
        return $value.Substring(1, $value.Length - 2)
    }
    return $value
}

function Invoke-External {
    param([string]$Program, [string[]]$Arguments)

    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Program failed with exit code $LASTEXITCODE."
    }
}

function Expand-GzipFile {
    param([string]$Source, [string]$Destination)

    $sourceStream = [System.IO.File]::OpenRead($Source)
    try {
        $gzipStream = [System.IO.Compression.GZipStream]::new(
            $sourceStream,
            [System.IO.Compression.CompressionMode]::Decompress
        )
        try {
            $destinationStream = [System.IO.File]::Create($Destination)
            try {
                $gzipStream.CopyTo($destinationStream)
            } finally {
                $destinationStream.Dispose()
            }
        } finally {
            $gzipStream.Dispose()
        }
    } finally {
        $sourceStream.Dispose()
    }
}

function Import-MySqlFile {
    param(
        [string]$Program,
        [string[]]$Arguments,
        [string]$Database,
        [string]$InputFile
    )

    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $Program
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.Arguments = (($Arguments + @($Database)) | ForEach-Object {
        if ($_ -match '[\s"]') { '"' + ($_ -replace '"', '\"') + '"' } else { $_ }
    }) -join ' '

    $process = [System.Diagnostics.Process]::Start($startInfo)
    $inputStream = [System.IO.File]::OpenRead($InputFile)
    try {
        $inputStream.CopyTo($process.StandardInput.BaseStream)
        $process.StandardInput.Close()
        $errorOutput = $process.StandardError.ReadToEnd()
        $process.WaitForExit()
        $exitCode = $process.ExitCode
    } finally {
        $inputStream.Dispose()
        $process.Dispose()
    }

    if ($exitCode -ne 0) {
        throw "MySQL import failed: $errorOutput"
    }
}

$envFile = Join-Path $ProjectDirectory '.env'
if (-not (Test-Path -LiteralPath $envFile)) {
    throw "Laravel .env was not found at $envFile"
}

$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe'
foreach ($program in @($mysql, $mysqldump)) {
    if (-not (Test-Path -LiteralPath $program)) {
        throw "Required XAMPP executable was not found: $program"
    }
}

$dbHost = Read-DotEnvValue $envFile 'DB_HOST'
$dbPort = Read-DotEnvValue $envFile 'DB_PORT'
$dbName = Read-DotEnvValue $envFile 'DB_DATABASE'
$dbUser = Read-DotEnvValue $envFile 'DB_USERNAME'
$dbPassword = Read-DotEnvValue $envFile 'DB_PASSWORD'
$dbHost = if ($dbHost) { $dbHost } else { '127.0.0.1' }
$dbPort = if ($dbPort) { $dbPort } else { '3306' }

if (-not $dbName -or -not $dbUser) {
    throw 'DB_DATABASE and DB_USERNAME must be configured in the local .env.'
}

New-Item -ItemType Directory -Force -Path $LocalBackupDirectory | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$localBeforeRefresh = Join-Path $LocalBackupDirectory "apico-local-before-refresh-$timestamp.sql"
$downloadedGzip = Join-Path $LocalBackupDirectory "apico-live-$timestamp.sql.gz"
$downloadedSql = Join-Path $LocalBackupDirectory "apico-live-$timestamp.sql"

$sshOptions = @('-o', 'BatchMode=yes')
if ($IdentityFile) {
    $sshOptions += @('-i', $IdentityFile)
}

if (-not $SkipRemoteBackup) {
    Invoke-External 'ssh' ($sshOptions + @("$SshUser@$Server", "bash $RemoteAppDirectory/ops/server/backup-apico.sh daily"))
}

$remoteLatest = (& ssh @sshOptions "$SshUser@$Server" "ls -1t $RemoteBackupDirectory/daily/apico-daily-*.sql.gz | head -n 1").Trim()
if ($LASTEXITCODE -ne 0 -or -not $remoteLatest) {
    throw 'Could not find a verified remote APICO backup.'
}

Invoke-External 'scp' ($sshOptions + @("${SshUser}@${Server}:$remoteLatest", $downloadedGzip))
if (-not (Test-Path -LiteralPath $downloadedGzip) -or (Get-Item $downloadedGzip).Length -eq 0) {
    throw 'The downloaded live backup is empty.'
}

Expand-GzipFile -Source $downloadedGzip -Destination $downloadedSql
if ((Get-Item $downloadedSql).Length -eq 0) {
    throw 'The downloaded backup could not be extracted.'
}

$connectionArguments = @('--host', $dbHost, '--port', $dbPort, '--user', $dbUser)
$previousMySqlPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $dbPassword

try {
    Invoke-External $mysqldump ($connectionArguments + @('--single-transaction', '--quick', '--no-tablespaces', "--result-file=$localBeforeRefresh", $dbName))
    if ((Get-Item $localBeforeRefresh).Length -eq 0) {
        throw 'The safety backup of the current local database is empty.'
    }

    if (-not $PSCmdlet.ShouldProcess($dbName, "Replace the local database with $remoteLatest")) {
        Write-Host "Stopped before changing the local database. Download remains at $downloadedSql"
        exit 0
    }

    $dropAndCreate = "DROP DATABASE IF EXISTS ``$dbName``; CREATE DATABASE ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $dropAndCreate | & $mysql @connectionArguments
    if ($LASTEXITCODE -ne 0) { throw 'Could not recreate the local database.' }

    Import-MySqlFile -Program $mysql -Arguments $connectionArguments -Database $dbName -InputFile $downloadedSql
} finally {
    $env:MYSQL_PWD = $previousMySqlPassword
}

Push-Location $ProjectDirectory
try {
    Invoke-External 'php' @('artisan', 'optimize:clear')
    Invoke-External 'php' @('artisan', 'migrate:status')
} finally {
    Pop-Location
}

Write-Host "Local APICO database refreshed successfully."
Write-Host "Previous local database: $localBeforeRefresh"
Write-Host "Downloaded live database: $downloadedGzip"
