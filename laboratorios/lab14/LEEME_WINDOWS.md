# Como obtener 18 de 18 en Windows

El codigo del laboratorio NO cambio. Se agrego `verificar-windows.ps1`, que prepara y ejecuta todo.

## Una sola vez (PowerShell, luego cierra y abre PowerShell)
    winget install OpenJS.NodeJS.LTS      # Node 18+ (si `node -v` es menor a 18)
    winget install PHP.PHP.8.4            # PHP en Windows
    # Tambien necesitas Docker Desktop y Google Chrome

## Cada vez
    cd D:\TW2\lab14
    powershell -ExecutionPolicy Bypass -File .\verificar-windows.ps1

Resultado esperado: `Resultado: 18 de 18 comprobaciones`

## Notas
- NO uses `docker compose exec php php verificar.php`: dentro del contenedor no hay Node ni Chrome (solo 8 de 8).
- NO necesitas `composer` en Windows: el contenedor ya ejecuta `composer dump-autoload` y `vendor/` viene incluido.
- `docker compose dump-autoload` no existe; el comando correcto (si lo quisieras) es `docker compose exec php composer dump-autoload`.
- Si Chrome no se detecta: `$env:CHROME_RUTA="C:\Program Files\Google\Chrome\Application\chrome.exe"`
