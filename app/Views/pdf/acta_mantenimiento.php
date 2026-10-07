<?php

declare(strict_types=1);

/**
 * Plantilla Dompdf del acta de mantenimiento (sección 10: tablas + CSS 2.1,
 * DejaVu Sans, A4 vertical, imágenes en base64, sin recursos remotos).
 * Estructura del formato institucional de la OTIC ("Mantenimiento preventivo de equipos de cómputo"):
 * datos del usuario, hardware, trabajos realizados (SÍ / NO / NO APLICA), componentes, software,
 * configuraciones, observaciones y firmas. Colores de app/Config/paleta.php.
 *
 * @var array<string, mixed> $acta
 * @var array<string, list<array<string, mixed>>> $checklist
 * @var array<string, array{accion: string, detalle: ?string}> $componentes
 * @var list<array<string, mixed>> $software
 * @var list<array<string, mixed>> $catalogoSoftware
 * @var array{institucional: ?string, otic: ?string} $logos
 * @var string $institucion
 * @var array{nombre: ?string, cargo: ?string} $jefe
 */
$x = static fn (bool $v): string => $v ? 'X' : '';
$usuario = $acta['personal_nombres'] !== null ? $acta['personal_nombres'] . ' ' . $acta['personal_apellidos'] : 'Sin responsable asignado';
$tecnico = $acta['tecnico_nombres'] . ' ' . $acta['tecnico_apellidos'];
$instalados = [];
foreach ($software as $s) {
    $instalados[(int) $s['software_id']] = $s['version'];
}
$mitad = (int) ceil(count($catalogoSoftware) / 2);
$columnasSoftware = [array_slice($catalogoSoftware, 0, $mitad), array_slice($catalogoSoftware, $mitad)];
$nombresComponentes = [
    'HDD'        => 'Disco duro mecánico HDD',
    'SSD'        => 'Disco de estado sólido SSD',
    'RAM'        => 'Memoria RAM',
    'FUENTE'     => 'Fuente de poder',
    'PROCESADOR' => 'Procesador',
];
$bloquesChecklist = ['FISICO' => 'MANTENIMIENTO FÍSICO', 'LOGICO' => 'MANTENIMIENTO LÓGICO'];
$fechaIngreso = (string) $acta['fecha_ingreso'];
$fechaSalida = $acta['fecha_salida'] !== null ? (string) $acta['fecha_salida'] : null;
$hora = static fn (string $fecha): string => date('H:i', (int) strtotime($fecha));
$esImpresora = $acta['equipo_tipo'] === 'IMPRESORA';
$hardware = [];
if ($acta['procesador'] !== null) {
    $hardware[] = $acta['procesador'];
}
if ($acta['ram_gb'] !== null) {
    $hardware[] = $acta['ram_gb'] . ' GB RAM';
}
if ($acta['disco_capacidad_gb'] !== null) {
    $hardware[] = trim(($acta['disco_tipo'] ?? '') . ' ' . $acta['disco_capacidad_gb'] . ' GB');
}
if ($acta['sistema_operativo'] !== null) {
    $hardware[] = $acta['sistema_operativo'];
}
$red = array_values(array_filter([
    $acta['hostname'] !== null ? 'Hostname ' . $acta['hostname'] : null,
    $acta['ip_lan'] !== null ? 'IP ' . $acta['ip_lan'] : null,
    $acta['mac_lan'] !== null ? 'MAC ' . $acta['mac_lan'] : null,
    $acta['mac_wifi'] !== null ? 'MAC Wi-Fi ' . $acta['mac_wifi'] : null,
]));
$oficina = $acta['oficina_nombre'] . ($acta['oficina_siglas'] !== null ? ' (' . $acta['oficina_siglas'] . ')' : '');
$dependencia = $acta['dependencia_nombre'] !== null
    ? $acta['dependencia_nombre'] . ($acta['dependencia_siglas'] !== null ? ' (' . $acta['dependencia_siglas'] . ')' : '')
    : $oficina;
$dniCargo = static fn (?string $dni, ?string $cargo): string => trim(($dni ?? '') . ($dni !== null && $cargo !== null ? ' / ' : '') . ($cargo ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acta de mantenimiento N° <?= e($acta['numero']) ?></title>
<style>
    @page { margin: 15mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 6.8pt; color: <?= paleta('texto') ?>; line-height: 1.1; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    td, th { vertical-align: middle; }
    .enc .logo { width: 22%; }
    .enc .logo img { max-height: 46px; max-width: 150px; }
    .enc .titulo { text-align: center; }
    .enc .titulo .inst { font-size: 6.8pt; color: <?= paleta('texto_suave') ?>; }
    .enc .titulo h1 { font-size: 9.5pt; margin: 2px 0 1px 0; color: <?= paleta('guinda') ?>; }
    .enc .titulo .ofi { font-size: 7pt; font-weight: bold; }
    .enc .num { width: 24%; text-align: center; border: 1.5px solid <?= paleta('guinda') ?>; padding: 3px 2px; }
    .enc .num .n { font-size: 8.5pt; font-weight: bold; color: <?= paleta('guinda') ?>; white-space: nowrap; }
    .linea { border-bottom: 2px solid <?= paleta('guinda') ?>; margin-top: 4px; }
    .linea2 { border-bottom: 1px solid <?= paleta('dorado') ?>; margin: 1px 0 3px 0; }
    .banda { background: <?= paleta('guinda') ?>; color: <?= paleta('superficie') ?>; font-weight: bold; text-align: center; font-size: 7.5pt; padding: 1.5px 4px; margin-top: 3px; }
    .g td, .g th { border: 0.6px solid <?= paleta('borde') ?>; padding: 0.6px 4px; }
    .g th { background: <?= paleta('celda') ?>; font-weight: bold; text-align: center; font-size: 6.6pt; }
    .g .et { background: <?= paleta('celda') ?>; font-weight: bold; font-size: 6.6pt; }
    .g .sub { background: <?= paleta('celda') ?>; font-weight: bold; text-align: center; color: <?= paleta('guinda') ?>; }
    .g .valor { text-align: center; font-weight: bold; font-size: 8pt; }
    .g .item { padding-left: 8px; }
    .g .m { width: 9%; text-align: center; font-weight: bold; }
    .c { text-align: center; }
    .suave { color: <?= paleta('texto_suave') ?>; }
    .texto { border: 0.6px solid <?= paleta('borde') ?>; padding: 3px 5px; min-height: 22px; }
    .evitar { page-break-inside: avoid; }
    .firmas { margin-top: 5px; }
    .firmas th { background: <?= paleta('guinda_oscuro') ?>; color: <?= paleta('superficie') ?>; font-size: 7pt; padding: 1.5px 4px; border: 0.6px solid <?= paleta('guinda_oscuro') ?>; }
    .firmas td { border: 0.6px solid <?= paleta('borde') ?>; padding: 3px 6px; vertical-align: top; width: 33.3%; }
    .firmas .espacio { height: 36px; }
    .firmas .dato { font-size: 6.6pt; padding: 0.5px 0; }
</style>
</head>
<body>

<table class="enc">
    <tr>
        <td class="logo">
            <?php if ($logos['institucional'] !== null): ?>
                <img src="<?= e($logos['institucional']) ?>" alt="Logo institucional">
            <?php else: ?>
                <strong style="font-size: 10pt; color: <?= paleta('guinda') ?>;">CAEN-EPG</strong><br><span style="font-size: 6.5pt;">OTIC</span>
            <?php endif; ?>
        </td>
        <td class="titulo">
            <div class="inst"><?= e($institucion) ?></div>
            <h1>MANTENIMIENTO <?= e($acta['tipo']) ?> DE EQUIPOS DE CÓMPUTO</h1>
            <div class="ofi">OFICINA DE TECNOLOGÍAS DE LA INFORMACIÓN Y COMUNICACIÓN</div>
        </td>
        <td class="num">
            <div class="n">N° <?= e($acta['numero']) ?></div>
            <div>Fecha: <?= e(fecha($fechaSalida ?? $fechaIngreso)) ?></div>
        </td>
    </tr>
</table>
<div class="linea"></div>
<div class="linea2"></div>

<div class="banda">DATOS DEL USUARIO</div>
<table class="g">
    <tr>
        <td class="et" style="width: 18%;">NOMBRES Y APELLIDOS</td>
        <td class="valor" colspan="5"><?= e(mb_strtoupper($usuario)) ?></td>
    </tr>
    <tr>
        <td class="et">OFICINA</td>
        <td class="c" colspan="2" style="font-weight: bold;"><?= e($oficina) ?></td>
        <td class="et" style="width: 14%;">DEPENDENCIA</td>
        <td class="c" colspan="2" style="font-weight: bold;"><?= e($dependencia) ?></td>
    </tr>
    <tr>
        <th colspan="3">TÉCNICO ASIGNADO</th>
        <th colspan="3">FECHA Y HORA</th>
    </tr>
    <tr>
        <td class="valor" colspan="3" rowspan="2"><?= e(mb_strtoupper($tecnico)) ?></td>
        <th style="width: 18%;">INGRESO</th>
        <th colspan="2">SALIDA</th>
    </tr>
    <tr>
        <td class="c"><?= e(fecha($fechaIngreso)) ?> · <?= e($hora($fechaIngreso)) ?></td>
        <td class="c" colspan="2"><?= $fechaSalida !== null ? e(fecha($fechaSalida)) . ' · ' . e($hora($fechaSalida)) : '—' ?></td>
    </tr>
</table>

<div class="banda">DESCRIPCIÓN DEL HARDWARE</div>
<table class="g">
    <tr>
        <th style="width: 12%;">DISPOSITIVO</th>
        <th style="width: 13%;">MARCA</th>
        <th style="width: 17%;">MODELO</th>
        <th style="width: 16%;">N° SERIE</th>
        <th style="width: 13%;"><?= $acta['codigo_anual'] !== null ? 'CÓD. ' . e($acta['codigo_anual_anio']) : 'CÓD. INTERNO' ?></th>
        <th style="width: 14%;">CÓD. PATRIMONIAL</th>
        <th>CONDICIÓN FÍSICA</th>
    </tr>
    <tr>
        <td class="c"><?= e(etiqueta($acta['equipo_tipo'])) ?></td>
        <td class="c"><?= e($acta['marca']) ?></td>
        <td class="c"><?= e($acta['modelo']) ?></td>
        <td class="c"><?= e($acta['nro_serie'] ?? '—') ?></td>
        <td class="c"><?= e($acta['codigo_anual'] ?? $acta['codigo_interno'] ?? '—') ?></td>
        <td class="c"><?= e($acta['codigo_patrimonial'] ?? '—') ?></td>
        <td class="c"><?= e(etiqueta($acta['condicion_final'])) ?></td>
    </tr>
    <?php if (!$esImpresora && $hardware !== []): ?>
        <tr><td class="et">CARACTERÍSTICAS</td><td colspan="6"><?= e(implode(' · ', $hardware)) ?></td></tr>
    <?php endif; ?>
    <?php if ($red !== []): ?>
        <tr><td class="et">RED</td><td colspan="6"><?= e(implode(' · ', $red)) ?></td></tr>
    <?php endif; ?>
    <tr><td class="et">OBSERVACIONES</td><td colspan="6"><?= $acta['problema_reportado'] !== null ? nl2br(e($acta['problema_reportado'])) : '' ?></td></tr>
</table>

<div class="banda">TRABAJOS REALIZADOS</div>
<table class="g">
    <?php foreach ($bloquesChecklist as $cat => $titulo): ?>
        <tr class="evitar">
            <td class="sub"><?= e($titulo) ?></td>
            <th class="m">SÍ</th><th class="m">NO</th><th class="m">NO APLICA</th>
        </tr>
        <?php if ($checklist[$cat] === []): ?>
            <tr><td class="item suave">No aplica a este tipo de equipo.</td><td class="m"></td><td class="m"></td><td class="m">X</td></tr>
        <?php endif; ?>
        <?php foreach ($checklist[$cat] as $item):
            $hecho = (int) $item['realizado'] === 1; ?>
            <tr>
                <td class="item"><?= e($item['descripcion']) ?></td>
                <td class="m"><?= $x($hecho) ?></td><td class="m"><?= $x(!$hecho) ?></td><td class="m"></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <tr class="evitar">
        <td class="sub">SUSTITUCIÓN DE COMPONENTES</td>
        <th class="m">REEMPLAZO</th><th class="m">INSTALACIÓN</th><th class="m">NO APLICA</th>
    </tr>
    <?php foreach ($componentes as $clave => $c): ?>
        <tr>
            <td class="item"><?= e($nombresComponentes[$clave] ?? $clave) ?><?= $c['detalle'] !== null && $c['detalle'] !== '' ? ' <span class="suave">— ' . e($c['detalle']) . '</span>' : '' ?></td>
            <td class="m"><?= $x($c['accion'] === 'REEMPLAZO') ?></td>
            <td class="m"><?= $x($c['accion'] === 'INSTALACION') ?></td>
            <td class="m"><?= $x($c['accion'] === 'NO_APLICA') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (!$esImpresora && $catalogoSoftware !== []): ?>
    <div class="banda">SOFTWARE INSTALADO</div>
    <table class="g evitar">
        <?php for ($i = 0; $i < $mitad; $i++): ?>
            <tr>
                <?php foreach ($columnasSoftware as $columna):
                    $s = $columna[$i] ?? null;
                    if ($s === null): ?>
                        <td style="width: 41%;"></td><td class="m"></td>
                    <?php else:
                        $sid = (int) $s['id'];
                        $ok = array_key_exists($sid, $instalados); ?>
                        <td class="item" style="width: 41%;"><?= e($s['nombre']) ?><?= $ok && $instalados[$sid] !== null ? ' <span class="suave">' . e($instalados[$sid]) . '</span>' : '' ?></td>
                        <td class="m"><?= $ok ? 'X' : '<span class="suave">—</span>' ?></td>
                    <?php endif;
                endforeach; ?>
            </tr>
        <?php endfor; ?>
    </table>
<?php endif; ?>

<table class="g evitar" style="margin-top: 3px;">
    <tr>
        <td class="sub">CONFIGURACIONES</td>
        <th class="m">SÍ</th><th class="m">NO</th><th class="m">NO APLICA</th>
    </tr>
    <?php if ($checklist['RED'] === []): ?>
        <tr><td class="item suave">No aplica a este tipo de equipo.</td><td class="m"></td><td class="m"></td><td class="m">X</td></tr>
    <?php endif; ?>
    <?php foreach ($checklist['RED'] as $item):
        $hecho = (int) $item['realizado'] === 1; ?>
        <tr>
            <td class="item"><?= e($item['descripcion']) ?></td>
            <td class="m"><?= $x($hecho) ?></td><td class="m"><?= $x(!$hecho) ?></td><td class="m"></td>
        </tr>
    <?php endforeach; ?>
</table>

<div class="evitar">
    <div class="banda">OBSERVACIONES Y RECOMENDACIONES</div>
    <div class="texto">
        <?php if ($acta['observaciones'] !== null): ?><div><strong>Observaciones:</strong> <?= nl2br(e($acta['observaciones'])) ?></div><?php endif; ?>
        <?php if ($acta['recomendaciones'] !== null): ?><div><strong>Recomendaciones:</strong> <?= nl2br(e($acta['recomendaciones'])) ?></div><?php endif; ?>
        <div class="suave">Estado final del equipo: <?= e(etiqueta($acta['estado_equipo_final'])) ?> · Condición <?= e(mb_strtolower(etiqueta($acta['condicion_final']))) ?></div>
    </div>

    <table class="firmas">
        <tr>
            <th>OTIC</th>
            <th>ÁREA USUARIA</th>
            <th>VISTO BUENO</th>
        </tr>
        <tr>
            <td>
                <div class="espacio"></div>
                <div class="dato"><strong><?= e($tecnico) ?></strong></div>
                <div class="dato"><?= e($dniCargo($acta['tecnico_dni'], $acta['tecnico_cargo']) ?: 'Técnico OTIC') ?></div>
            </td>
            <td>
                <div class="espacio"></div>
                <div class="dato"><strong><?= e($acta['personal_nombres'] !== null ? $usuario : '') ?></strong></div>
                <div class="dato"><?= e($dniCargo($acta['personal_dni'], $acta['personal_cargo']) ?: 'Usuario') ?></div>
            </td>
            <td>
                <div class="espacio"></div>
                <div class="dato"><strong><?= e($jefe['nombre'] ?? '') ?></strong></div>
                <div class="dato"><?= e($jefe['cargo'] ?? 'Jefe de la OTIC') ?> · firma digital (DNIe)</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
