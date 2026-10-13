# Bitácora de uso de IA: Laboratorio 5

**Estudiante:** _(completar)_  **Herramienta:** Claude Code (modelo Claude Opus 5.5)  **Fecha:** 12/10/2026

> Esta bitácora registra qué se pidió a la IA, qué entregó, qué se verificó y qué decidí yo.
> Revísala y complétala con tus palabras: el docente evalúa tu criterio, no solo el código.

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 1 | Tienda de ropa virtual para el proyecto final, empezando por el Lab 5 (POO + JSON) | Estructura `public/ src/ plantillas/ datos/` siguiendo los labs 1–4; caso de la asociación «Manos del Oriente» | Comparé con la estructura de `lab04` | _(¿mantengo el nombre de la asociación o uso la real que entrevisté?)_ |
| 2 | Clases `Producto`, `Categoria`, `Catalogo` con promoción de propiedades | Clases `readonly`, enum `Talla`, método `con(...)` para editar sin mutar | Prueba con `ReflectionMethod::isPromoted()` en `verificar.php` | _(explicar por qué inmutable)_ |
| 3 | Validación y saneamiento del formulario | `validar_producto()` con coma decimal boliviana, `strip_tags`, tallas como arreglo | Pruebas de entradas inválidas (422) y de saneamiento | |
| 4 | Subida segura de la foto | Reutilizó la lógica de `lab04` (finfo, 2 MB, nombre aleatorio, fuera de `public/`) | Pruebas: PHP disfrazado de JPG, archivo de 2,2 MB, `../` | |
| 5 | Pruebas automáticas | `verificar.php` con 32 comprobaciones sobre copia temporal de datos | Ejecuté `php verificar.php` → 32/32 | |
| 6 | Error detectado | La IA calculó mal el valor esperado del inventario en 2 pruebas (10.983,50 y luego 14.163,50) | La prueba falló; recalculé a mano: 12.303,50 y 14.042,90 (la segunda edición vuelve a 289,90) | Lección: los valores esperados de los reportes se verifican a mano, no se copian de la IA |
| 7 | Informe APA 7 en Word y presentación | Borrador del informe (17 págs., 6 figuras, 4 tablas, 9 referencias) y presentación de 11 diapositivas con notas del orador, usando capturas reales de la app | Revisé cada página/diapositiva renderizada; comprobé que las cifras coincidan con `verificar.php` | _(revisar redacción, completar nombre y docente, verificar que las referencias abran)_ |

## Reflexión (completar)

- ¿Qué parte entendí mejor gracias a la IA?
- ¿Qué parte tuve que corregir o cuestionar?
- ¿Qué haría distinto sin la IA?
