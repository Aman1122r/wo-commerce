# Starts WordPress Playground with this plugin + WooCommerce for local testing.
# Usage: powershell -ExecutionPolicy Bypass -File .\start-local-dev.ps1

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "Starting WordPress Playground (plugin + WooCommerce)..." -ForegroundColor Cyan
Write-Host "Admin will open at: http://127.0.0.1:9400/wp-admin" -ForegroundColor Green
Write-Host "OminiFlow UI: Marketing -> Facebook -> Shops tab" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop the server." -ForegroundColor Yellow
Write-Host ""

npx --yes @wp-playground/cli@latest start `
  --path="$PSScriptRoot" `
  --blueprint="$PSScriptRoot\playground-blueprint.json" `
  --port=9400 `
  --php=8.2 `
  --login `
  --skip-browser
