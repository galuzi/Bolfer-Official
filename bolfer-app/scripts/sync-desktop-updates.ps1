param(
  [string]$ReleaseDir = (Join-Path $PSScriptRoot '..\release'),
  [string]$TargetDir = (Join-Path $PSScriptRoot '..\..\build\desktop-updates')
)

$rawReleaseDir = Join-Path $PSScriptRoot '..\release'
if ($PSBoundParameters.ContainsKey('ReleaseDir')) {
  $rawReleaseDir = $ReleaseDir
}

if (-not (Test-Path $rawReleaseDir)) {
  throw "Pasta de release nao encontrada: $ReleaseDir"
}

$resolvedReleaseDir = (Resolve-Path $rawReleaseDir).Path

$latestFile = Join-Path $resolvedReleaseDir 'latest.yml'
if (-not (Test-Path $latestFile)) {
  throw 'Arquivo latest.yml nao encontrado. Gere a build com npm run dist:win antes de publicar as atualizacoes.'
}

$setupInstaller = Get-ChildItem -Path $resolvedReleaseDir -Filter '*Setup*.exe' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $setupInstaller) {
  throw 'Instalador Setup nao encontrado. A atualizacao automatica usa a build NSIS.'
}

$artifacts = @($latestFile, $setupInstaller.FullName)
$blockmapPath = "$($setupInstaller.FullName).blockmap"
if (Test-Path $blockmapPath) {
  $artifacts += $blockmapPath
}

New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null

foreach ($artifact in $artifacts) {
  Copy-Item -Path $artifact -Destination $TargetDir -Force
}

Write-Host 'Atualizacoes publicadas em:' $TargetDir
Write-Host 'Arquivos enviados:'
foreach ($artifact in $artifacts) {
  Write-Host '-' (Split-Path $artifact -Leaf)
}
