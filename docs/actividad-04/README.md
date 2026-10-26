# Actividad 4 · Servicios de intercambio de datos, API REST e interfaz AJAX

Laboratorios **12, 13 y 14** aplicados al sistema de la Asociación «Manos del Oriente», siguiendo la
[Guía integral de la Actividad 4](Guia_Integral_Actividad_4_Tecnologia_Web_II.pdf).

| Producto | Dónde |
|---|---|
| Código de los laboratorios | [`laboratorios/lab12`](../../laboratorios/lab12), [`lab13`](../../laboratorios/lab13), [`lab14`](../../laboratorios/lab14) (caso del docente, resueltos) |
| Lo mismo en Manos del Oriente | `src/Intercambio.php`, `src/Pedidos.php`, `src/Api.php`, `public/js/*.js` |
| API documentada | [endpoints.md](endpoints.md) (ficha por endpoint) · [openapi.yaml](../openapi.yaml) (OpenAPI 3.1) |
| Ejemplos de petición | [ejemplos/](ejemplos/) (`producto.json`, `actualizacion.json`, pedidos válidos e inválidos) |
| Evidencias | [evidencias/](evidencias/) (E01–E20) |
| Informe APA 7 | [Informe_Actividad04_APA.pdf](Informe_Actividad04_APA.pdf) (y `.docx`) |
| Presentación | [Presentacion_Actividad04.pptx](Presentacion_Actividad04.pptx) |
| Bitácora de IA | [../BITACORA_IA.md](../BITACORA_IA.md#actividad-4) |

## Cómo reproducir las evidencias

```powershell
php bd/instalar.php                                   # base con los datos de demostración
php -S 127.0.0.1:8000 -t public public/enrutador.php  # en otra consola
php verificar.php                                     # 136 de 136 (incluye los labs 12-14 aplicados)
php herramientas/evidencias_api.php http://127.0.0.1:8000   # E01–E12: peticiones reales, tokens ocultos y revocados
php herramientas/soap_cliente.php POL-001             # tabla SOAP vs REST medida en tu equipo
php herramientas/consumir_publico.php                 # servicio público con cURL (requiere Internet)
```

## Matriz de evidencias

| Código | Evidencia | Estado | Archivo |
|---|---|---|---|
| E01 | Versiones y entorno | ✅ | [E01_versiones_entorno.png](evidencias/E01_versiones_entorno.png) |
| E02 | Catálogo JSON (ISO 8601, precios como texto) | ✅ | [E02_catalogo_json.png](evidencias/E02_catalogo_json.png) |
| E03 | XML y escape (`&amp;`, `&lt;`) | ✅ | [E03_xml_escape.png](evidencias/E03_xml_escape.png) |
| E04 | Validación XSD/JSON (correcta e incorrecta) | ✅ | [E04_validacion_xsd_json.png](evidencias/E04_validacion_xsd_json.png) · [web](evidencias/E04_validacion_xsd_rechazada.png) |
| E05 | RSS 2.0 | ✅ | [E05_rss.png](evidencias/E05_rss.png) |
| E06 | SOAP/REST medidos y cURL | ✅ | [E06_soap_rest_curl.png](evidencias/E06_soap_rest_curl.png) |
| E07 | GET paginado + sugerencias | ✅ | [E07_get_paginado.png](evidencias/E07_get_paginado.png) |
| E08 | POST 201 + Location | ✅ | [E08_post_201.png](evidencias/E08_post_201.png) |
| E09 | PUT 200 | ✅ | [E09_put_200.png](evidencias/E09_put_200.png) |
| E10 | DELETE 204 (y 403 del operador) | ✅ | [E10_delete_204.png](evidencias/E10_delete_204.png) |
| E11 | Errores 400/401/403/404/405/409/415/422 | ✅ | [E11_errores_401_403_422.png](evidencias/E11_errores_401_403_422.png) |
| E12 | OpenAPI, ETag/304, CORS y tokens SHA-256 | ✅ | [E12_openapi_etag_cors.png](evidencias/E12_openapi_etag_cors.png) · [tokens](evidencias/E12_tokens_api.png) |
| E13 | Grilla AJAX | ✅ | [E13_grilla_ajax.png](evidencias/E13_grilla_ajax.png) |
| E14 | Paginación y orden sin recargar | ✅ | [E14_paginacion_y_orden.png](evidencias/E14_paginacion_y_orden.png) |
| E15 | Autocompletar accesible | ✅ | [E15_autocompletar_accesible.png](evidencias/E15_autocompletar_accesible.png) |
| E16 | Cancelaciones con red lenta | ✅ | [E16_busqueda_ultima_prevalece.png](evidencias/E16_busqueda_ultima_prevalece.png) |
| E17 | Validación en vivo | ✅ | [ocupado](evidencias/E17_validacion_en_vivo_ocupado.png) · [disponible](evidencias/E17_validacion_en_vivo_disponible.png) |
| E18 | RSS en la interfaz | ✅ | [E18_rss_en_interfaz.png](evidencias/E18_rss_en_interfaz.png) |
| E19 | Verificadores finales | ✅ | [verificar.php 136/136](evidencias/E19_verificar.png) · [navegador 12/12](evidencias/E19_navegador.png) |
| E20 | GitHub e informe | ✅ | <https://github.com/TecnologiaWeb2/Tecnologiaweb2> · informe y presentación en esta carpeta |

E13–E18 se capturaron con Microsoft Edge controlado por un script (`puppeteer-core`) que además **comprueba** cada
comportamiento: 3 búsquedas canceladas con red lenta, `aria-activedescendant` = opción con `aria-selected=true`,
200 = código ocupado / 404 = disponible y ningún error de JavaScript ni violación de la CSP.

## Lista final

- [x] Laboratorio 12 verificado y documentado
- [x] Laboratorio 13: CRUD, autenticación y errores comprobados
- [x] Laboratorio 14: fetch, grilla, autocompletar, RSS y validación probados
- [x] Capturas propias con resultados reales
- [x] Todas las rutas documentadas con peticiones y respuestas
- [x] Informe técnico final con estructura APA 7
- [ ] Bitácora de IA: **cada integrante completa su reflexión** en `docs/BITACORA_IA.md`
- [x] Repositorio GitHub funcional y presentación incluida
- [x] Sin secretos, tokens ni solucionarios docentes publicados
