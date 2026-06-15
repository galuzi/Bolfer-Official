param(
  [string]$OutputPath = ""
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($OutputPath)) {
  $OutputPath = Join-Path $projectRoot "deploy_patch_products_api"
}

$outputRoot = [System.IO.Path]::GetFullPath($OutputPath)
if (Test-Path -LiteralPath $outputRoot) {
  Remove-Item -LiteralPath $outputRoot -Recurse -Force
}

$files = @(
  "index.php",
  ".htaccess",
  "public_html/index.php",
  "app/Controllers/Api/Desktop/CategoriesController.php",
  "app/Controllers/Api/Desktop/ProductsController.php",
  "app/Repositories/ProductRepository.php",
  "app/Services/ProductAccountMediaService.php",
  "app/Support/DesktopApiPresenter.php",
  "sql/changes_only.sql"
)

foreach ($relativePath in $files) {
  $source = Join-Path $projectRoot $relativePath
  if (-not (Test-Path -LiteralPath $source)) {
    throw "Arquivo nao encontrado: $relativePath"
  }

  $destination = Join-Path $outputRoot $relativePath
  $destinationDir = Split-Path -Parent $destination
  if (-not (Test-Path -LiteralPath $destinationDir)) {
    New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
  }

  Copy-Item -LiteralPath $source -Destination $destination -Force
}

$readme = @"
Patch minimo para liberar a API de produtos do desktop no host.

Envie estes arquivos preservando a mesma estrutura:

- index.php
- .htaccess
- public_html/index.php
- app/Controllers/Api/Desktop/CategoriesController.php
- app/Controllers/Api/Desktop/ProductsController.php
- app/Repositories/ProductRepository.php
- app/Services/ProductAccountMediaService.php
- app/Support/DesktopApiPresenter.php
- sql/changes_only.sql

Antes de testar compra minima:
1. Envie os arquivos para o host preservando a estrutura.
2. Execute o SQL em sql/changes_only.sql no banco online.

Depois de subir, teste:
https://example.com/api/desktop/products
https://example.com/api/desktop/categories

Resposta esperada sem token:
401 Nao autenticado.
"@

Set-Content -LiteralPath (Join-Path $outputRoot "README.txt") -Value $readme -Encoding UTF8

Write-Host ""
Write-Host "Patch da API de produtos pronto em:" -ForegroundColor Green
Write-Host $outputRoot -ForegroundColor Cyan
Write-Host ""
Write-Host "Arquivos incluidos:" -ForegroundColor Green
foreach ($relativePath in $files) {
  Write-Host " - $relativePath" -ForegroundColor DarkGray
}
