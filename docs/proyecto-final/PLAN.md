# PLAN · Roles y cronograma

Repositorio: <https://github.com/TecnologiaWeb2/Tecnologiaweb2> · Tablero: <https://github.com/orgs/TecnologiaWeb2/projects/1>

## Equipo

| Integrante | GitHub | Responsabilidad principal |
|---|---|---|
| Daniela Cayo Mamani | dah0404 | Coordinación, frontend (vitrina AJAX, modales), integración y publicación |
| Juan José Vicente Cossio | JUANJOVICO2021 | Backend: ventas, acopios y reportes; demostración de inyección SQL |
| Yordy Tolavi Ruiz | tolavi | Backend: API REST y laboratorio 14; defensa |
| Ronald Salvatierra Flores | RonSalvet | Módulo de pedidos con reserva; video |
| Alejandro Cadario Lopez | leinn04 | Módulo de liquidaciones; informe técnico y bitácora |

> Las responsabilidades se ajustan en el tablero. Cada integrante debe tener **commits propios** (la rúbrica lo revisa).

## Siete fases (clase 15)

| Semana | Fase | Entregable | Estado |
|---|---|---|---|
| 15 | 1 Organización | [BRIEF.md](BRIEF.md), [KICKOFF.md](KICKOFF.md) | ✅ |
| 15 | 2 Requerimientos y modelo | [REQUERIMIENTOS.md](REQUERIMIENTOS.md), [MODELO_DATOS.md](MODELO_DATOS.md) | ✅ |
| 15 | 3 Sistema base + repositorio | Actividades 1–4 integradas, auditoría local 18/18 | ✅ |
| 16 | 4 Módulo 1 con pruebas | Liquidación y pagos · `pruebas/trabajo_final.php` | ✅ |
| 16 | 5 Módulo 2 + API + interfaz asíncrona | Pedidos con reserva · `/api/v1/pedidos` | ✅ |
| 17 | 6 Seguridad 100 % y publicación HTTPS | [DESPLIEGUE_INFINITYFREE.md](DESPLIEGUE_INFINITYFREE.md) · paquete listo | ⏳ falta subirlo y auditar la URL publicada |
| 17–18 | 7 Manual, informe, bitácora, defensa 15 min | Manual, informe técnico, presentación | ✅ borradores · ⏳ reflexiones personales en la bitácora |

## Guion de la defensa (15 minutos)

| Min | Quién | Qué |
|---|---|---|
| 0–2 | Daniela | Problema, objetivos medibles y arquitectura |
| 2–5 | Alejandro | Módulo 1 en vivo: acopio con rechazos → liquidación → pago → la socia entra y ve solo lo suyo |
| 5–8 | Ronald | Módulo 2 en vivo: pedido que reserva → venta que se rechaza por reserva → entregar → stock |
| 8–11 | Yordy | API: POST 201 con token, 401/403/422, ETag 304, OpenAPI; vitrina con fetch |
| 11–13 | Juan José | Seguridad: CSRF, inyección SQL, auditoría 100 % (local y publicada) |
| 13–15 | Todos | Preguntas |
