<?php

// Panel → Reportes (Excel y PDF)
return [
    // Las fechas se guardan en UTC; el reporte las muestra y filtra con esta zona
    'zona_horaria' => 'America/Guatemala',

    // Máximo de filas por informe (el PDF se arma en memoria: necesita un límite menor)
    'limite_excel' => (int) env('REPORTES_LIMITE_EXCEL', 10000),
    'limite_pdf' => (int) env('REPORTES_LIMITE_PDF', 500),
];
