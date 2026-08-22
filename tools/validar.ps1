[CmdletBinding()]
param(
    [string]$BaseUrl = 'http://localhost/JV3000_db',
    [string]$Database = 'jv3000_db_test',
    [string]$MySql = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$Php = 'php',
    [string]$Node = 'node'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$failures = [System.Collections.Generic.List[string]]::new()

function Assert-Equal([string]$Name, [string]$Expected, [string]$Actual) {
    if ($Expected -ne $Actual) { $failures.Add("$Name esperado [$Expected], recibido [$Actual]") }
    else { Write-Host "[OK] $Name = $Actual" }
}

Write-Host '== Sintaxis PHP =='
Get-ChildItem $root -Filter '*.php' -Recurse | Where-Object { $_.FullName -notmatch '\\backups\\' } | ForEach-Object {
    & $Php -l $_.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { $failures.Add("PHP: $($_.FullName)") }
}

Write-Host '== Sintaxis JavaScript propio =='
Get-ChildItem (Join-Path $root 'assets') -Filter '*.js' -Recurse | Where-Object { $_.Name -notlike '*.min.js' } | ForEach-Object {
    & $Node --check $_.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { $failures.Add("JavaScript: $($_.FullName)") }
}

Write-Host '== Rutas HTTP internas =='
$internalPaths = @('backups/jv3000_db_2026-08-13_153655.sql', 'db/jv3000_portable_v4.sql', 'models/Producto.php', 'controllers/SalidasController.php', 'views/productos/index.php', 'config/config.php', 'includes/config.php', 'core/Router.php')
foreach ($path in $internalPaths) {
    $status = & curl.exe -s -o NUL -w '%{http_code}' "$BaseUrl/$path"
    Assert-Equal "Bloqueo $path" '403' ([string]$status)
}

Write-Host '== Integridad de base de datos =='
$query = @"
SELECT COUNT(*) FROM (SELECT p.id_producto,p.stock_actual,COALESCE(SUM(l.cantidad_restante),0) AS lotes FROM productos p LEFT JOIN lotes l ON l.id_producto=p.id_producto GROUP BY p.id_producto,p.stock_actual HAVING COUNT(l.id_lote)>0 AND p.stock_actual<>COALESCE(SUM(l.cantidad_restante),0)) x;
SELECT COUNT(*) FROM solicitudes_compra s LEFT JOIN compras c ON c.id_compra=s.id_compra WHERE s.estado='Atendida' AND (s.id_compra IS NULL OR c.id_compra IS NULL);
SELECT COUNT(*) FROM detalle_compras d LEFT JOIN compras c ON c.id_compra=d.id_compra LEFT JOIN productos p ON p.id_producto=d.id_producto WHERE c.id_compra IS NULL OR p.id_producto IS NULL;
SELECT COUNT(*) FROM detalle_salidas d LEFT JOIN salidas s ON s.id_salida=d.id_salida LEFT JOIN productos p ON p.id_producto=d.id_producto WHERE s.id_salida IS NULL OR p.id_producto IS NULL;
SELECT COUNT(*) FROM salidas s JOIN movimientos m ON m.id_referencia=s.id_salida AND m.tipo_referencia='venta' WHERE s.status='Anulada' AND m.status<>'Anulado';
"@
$counts = $query | & $MySql -uroot -N -B -D $Database
$names = @('stock contra lotes', 'solicitudes sin compra', 'detalles de compra huerfanos', 'detalles de salida huerfanos', 'movimientos de venta anulada')
for ($index = 0; $index -lt $names.Count; $index++) { Assert-Equal $names[$index] '0' ([string]$counts[$index]) }

Write-Host '== Configuracion de despliegue =='
$configText = Get-Content (Join-Path $root 'includes/config.php') -Raw
$backupText = Get-Content (Join-Path $root 'backups/backup.bat') -Raw
$environmentFile = Join-Path $root 'config/.env'
$environmentText = if (Test-Path $environmentFile) { Get-Content $environmentFile -Raw } else { '' }
$applicationFallbackDatabase = [regex]::Match($configText, "DB_NAME', getenv\('JV_DB_NAME'\) \?: '([^']+)'").Groups[1].Value
$backupDefaultDatabase = [regex]::Match($backupText, 'if not defined JV_DB_NAME set JV_DB_NAME=(.+)').Groups[1].Value.Trim()
$environmentDatabase = [regex]::Match($environmentText, '(?m)^JV_DB_NAME=(.+)$').Groups[1].Value.Trim()
$applicationDatabase = if ($env:JV_DB_NAME) { $env:JV_DB_NAME } elseif ($environmentDatabase) { $environmentDatabase } else { $applicationFallbackDatabase }
$backupDatabase = if ($env:JV_DB_NAME) { $env:JV_DB_NAME } elseif ($environmentDatabase) { $environmentDatabase } else { $backupDefaultDatabase }
Write-Host "[INFO] Base de la aplicacion = $applicationDatabase"
Write-Host "[INFO] Base del backup = $backupDatabase"
Write-Host "[INFO] Variable JV_DB_NAME definida = $([bool]$env:JV_DB_NAME)"
Write-Host "[INFO] config/.env existe = $(Test-Path $environmentFile)"
Write-Host "[INFO] mysqldump existe = $(Test-Path $MySql.Replace('mysql.exe', 'mysqldump.exe'))"
if ($applicationDatabase -ne $backupDatabase) {
    Write-Warning 'La base configurada y la base del backup no coinciden; revisar antes de produccion.'
}

Write-Host '== Permisos de carpetas internas =='
foreach ($directory in @('backups', 'db', 'models', 'controllers', 'views', 'config', 'includes', 'core')) {
    $broadWriteRules = (Get-Acl (Join-Path $root $directory)).Access | Where-Object {
        $_.IdentityReference -match 'Everyone|Users|Authenticated Users' -and
        $_.FileSystemRights.ToString() -match 'Modify|FullControl|Write'
    }
    if ($broadWriteRules) {
        Write-Warning "$directory tiene permisos de escritura amplios; revisar antes de produccion."
    }
    else {
        Write-Host "[OK] ACL $directory sin escritura amplia"
    }
}

if ($failures.Count -gt 0) { Write-Error ($failures -join [Environment]::NewLine); exit 1 }
Write-Host '[OK] Validacion completada'
