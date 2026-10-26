# BRIEF · Trabajo Final Integrador

## La organización

**Asociación de Confeccionistas Textiles «Manos del Oriente»** — Concepción, San Javier, San Ignacio de Velasco,
San Rafael y Santa Ana de Velasco (Chiquitanía, Santa Cruz, Bolivia).

> **Caso verosímil declarado.** No se consiguió a tiempo una entrevista con una asociación real, por eso se construyó un
> caso verosímil (lo permite la consigna). Los nombres de las socias, sus CI y sus teléfonos son **ficticios**. Si el
> docente lo pide, el mensaje de [KICKOFF.md](KICKOFF.md) sirve para validar el sistema con una asociación real.

La asociación reúne a 6 socias y socios (5 activos) que confeccionan ropa con telas y bordados de la región. La
secretaría de ventas vende en ferias y a boutiques, hoteles y tiendas de Santa Cruz.

## El problema (palabras de la directiva, entrevista simulada)

- «Cuando una socia trae prendas, revisamos, pero no queda anotado cuántas rechazamos ni por qué. Al final del mes
  **dice que le pagamos menos prendas de las que trajo** y no tenemos cómo mostrarle.» → reclamos de pago.
- «Una boutique nos encarga 10 poleras, mientras tanto vendemos 4 en la feria y **cuando vamos a entregar no alcanzan**.»
  → stock comprometido que nadie ve.
- «El informe trimestral para la asamblea lo armamos a mano en 4 o 5 días.»

## Objetivos medibles

| # | Objetivo | Indicador | Hoy (caso) | Meta |
|---|---|---|---|---|
| 1 | Liquidar a cada socia sin discusiones | Reclamos de pago por trimestre | 6 | 0 |
| 2 | No comprometer prendas que ya se reservaron | Pedidos que no se pueden entregar completos | 3 por mes | 0 |
| 3 | Informe trimestral automático | Tiempo para armarlo | 4–5 días | 10 minutos (reporte + CSV) |
| 4 | Cada socia ve su saldo sin pedirlo | Consultas por WhatsApp a la directiva | ~20 por mes | Acceso propio, solo a lo suyo |

## Los 2 módulos nuevos

1. **Liquidación y pagos a socias con control de calidad.** Cada entrega (acopio) registra prendas entregadas,
   aprobadas y el motivo del rechazo. Se paga solo lo aprobado: `liquidado = Σ aprobadas × precio por prenda`;
   `saldo = liquidado − pagos`. El saldo se calcula, nunca se guarda. La socia ve solo su liquidación.
2. **Pedidos de compradores con reserva de stock.** Un pedido (formulario, archivo JSON/XML o API) **reserva** las
   prendas; ya no se pueden vender a otro. Al entregarlo se convierte en venta en **una transacción**; al cancelarlo se
   libera la reserva. La base lo garantiza: `CHECK (reservado <= stock)`.

## Alcance

Incluye: catálogo público, gestión de prendas y socias, usuarios y roles, ventas, acopios, reportes, intercambio
JSON/XML/RSS/SOAP, API REST y los dos módulos. No incluye: pagos en línea, facturación electrónica (SIAT) ni envíos.

ODS 8 (trabajo decente y crecimiento económico): ingresos claros y a tiempo para las socias artesanas.
