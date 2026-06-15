param(
    [string]$OutputPath = ""
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $projectRoot "build\\ftp_upload"
}

$outputRoot = [System.IO.Path]::GetFullPath($OutputPath)
$publicTarget = Join-Path $outputRoot "public_html"
$storageLogs = Join-Path $outputRoot "storage\\logs"

if (Test-Path -LiteralPath $outputRoot) {
    Remove-Item -LiteralPath $outputRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $outputRoot | Out-Null
New-Item -ItemType Directory -Path $publicTarget | Out-Null
New-Item -ItemType Directory -Path $storageLogs -Force | Out-Null

foreach ($folder in @("app", "deploy", "sql")) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $folder) -Destination (Join-Path $outputRoot $folder) -Recurse -Force
}

Copy-Item -LiteralPath (Join-Path $projectRoot "index.php") -Destination (Join-Path $outputRoot "index.php") -Force
Copy-Item -LiteralPath (Join-Path $projectRoot ".editorconfig") -Destination (Join-Path $outputRoot ".editorconfig") -Force
Copy-Item -LiteralPath (Join-Path $projectRoot "deploy\\.env.kinghost.example") -Destination (Join-Path $outputRoot ".env") -Force

Get-ChildItem -LiteralPath (Join-Path $projectRoot "public") -Force | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $publicTarget -Recurse -Force
}

Set-Content -LiteralPath (Join-Path $storageLogs ".gitkeep") -Value "" -NoNewline

Write-Host ""
Write-Host "Pacote FTP pronto em:" -ForegroundColor Green
Write-Host $outputRoot -ForegroundColor Cyan
Write-Host ""
Write-Host "Estrutura gerada:" -ForegroundColor Green
Write-Host "  app/" -ForegroundColor DarkGray
Write-Host "  storage/" -ForegroundColor DarkGray
Write-Host "  deploy/" -ForegroundColor DarkGray
Write-Host "  sql/" -ForegroundColor DarkGray
Write-Host "  .env" -ForegroundColor DarkGray
Write-Host "  index.php" -ForegroundColor DarkGray
Write-Host "  public_html/" -ForegroundColor DarkGray
