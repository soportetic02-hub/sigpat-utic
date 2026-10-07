<?php

declare(strict_types=1);

/*
 * Paleta institucional CAEN-EPG para salidas sin variables CSS (PDF con Dompdf, Word y correos).
 * Las claves de marca deben coincidir con los tokens --caen-* de public/assets/css/app.css.
 * Colores medidos en caen.edu.pe (⚠️ confirmar con el manual de identidad del CAEN).
 * El dorado no alcanza AA como texto sobre blanco (2.1:1): usarlo solo en filetes, bordes o sobre guinda.
 * El rojo es decorativo; nunca se usa para alertas.
 * Uso: paleta('guinda') en vistas y servicios (helper de app/Core/helpers.php).
 */

return [
    // Marca
    'guinda'        => '#7a1334',
    'guinda_oscuro' => '#5a0e26',
    'rojo'          => '#c10e36',
    'dorado'        => '#d9ad4f',
    'fondo'         => '#f2f2f2',
    'superficie'    => '#ffffff',
    'texto'         => '#1a1a1a',
    // Neutros de documentos impresos (PDF y Word)
    'celda'         => '#f2e8eb',
    'borde'         => '#8a8a8a',
    'borde_claro'   => '#e3dcde',
    'texto_suave'   => '#555555',
    'marca_agua'    => '#e6e6e6',
];
