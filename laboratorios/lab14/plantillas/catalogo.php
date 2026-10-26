<?php /** plantillas/catalogo.php — Página "cascarón": los datos llegan después, con fetch, desde la API /api/v1. */ ?>
<div class="titulo-pagina">
  <div><h2>Catálogo en línea</h2><p class="tenue">La tabla se actualiza sin recargar la página (fetch + API REST)</p></div>
</div>
<div class="tarjeta buscador">
  <label for="buscar">Buscar producto</label>
  <input id="buscar" type="search" role="combobox" autocomplete="off" placeholder="Escribe al menos 2 letras…"
         aria-autocomplete="list" aria-controls="sugerencias" aria-expanded="false">
  <ul id="sugerencias" role="listbox" aria-label="Sugerencias" hidden></ul>
  <p id="estado-sugerencias" class="sr-only" aria-live="polite"></p>
</div>
<p id="aviso" class="alerta" role="alert" hidden></p>
<div class="tabla-contenedor">
<table id="grilla" aria-busy="true" aria-describedby="resumen">
  <thead><tr>
    <th scope="col">Código</th>
    <th scope="col" aria-sort="ascending"><button type="button" data-orden="nombre">Producto</button></th>
    <th scope="col">Categoría</th>
    <th scope="col" class="num"><button type="button" data-orden="precio">Precio</button></th>
    <th scope="col" class="num"><button type="button" data-orden="stock">Stock</button></th>
    <th scope="col">Socio</th>
  </tr></thead>
  <tbody></tbody>
</table>
</div>
<nav class="paginacion" aria-label="Paginación">
  <button type="button" id="anterior" class="btn btn-secundario" disabled>← Anterior</button>
  <span id="resumen" aria-live="polite">Cargando…</span>
  <button type="button" id="siguiente" class="btn btn-secundario" disabled>Siguiente →</button>
</nav>
<aside class="tarjeta novedades" aria-labelledby="titulo-novedades">
  <h3 id="titulo-novedades" class="sin-margen">Recién llegados (RSS)</h3>
  <ul id="novedades" class="lista-novedades"><li>Cargando…</li></ul>
  <p><a href="/noticias.rss">Suscribirse al canal RSS</a></p>
</aside>
<script type="module" src="/js/catalogo.js"></script>
