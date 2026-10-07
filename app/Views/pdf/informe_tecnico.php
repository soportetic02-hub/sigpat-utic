<?php

declare(strict_types=1);

use App\Services\PdfService;

/**
 * Plantilla Dompdf del informe técnico (sección 10: tablas + CSS 2.1, DejaVu Sans,
 * A4 vertical, imágenes en base64, sin recursos remotos).
 *
 * @var array<string, mixed> $informe
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $evidencias  cada una con 'ruta' absoluta
 * @var bool $borrador
 * @var array{institucional: ?string, otic: ?string} $logos
 * @var string $institucion
 */
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$ts = strtotime((string) $informe['fecha']);
$fechaLarga = $ts === false ? (string) $informe['fecha'] : sprintf('%d de %s de %s', (int) date('j', $ts), $meses[(int) date('n', $ts) - 1], date('Y', $ts));
$parrafo = static fn (?string $t): string => $t === null || trim($t) === '' ? '—' : nl2br(e(trim($t)));
$numero = $borrador ? '(BORRADOR)' : 'N° ' . $informe['numero'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe técnico <?= e($numero) ?></title>
<style>
    @page { margin: 15mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; color: <?= paleta('texto') ?>; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    td, th { vertical-align: top; }
    .enc td { vertical-align: middle; }
    .enc .logo { width: 20%; }
    .enc .logo img { max-height: 50px; max-width: 150px; }
    .enc .titulo { text-align: center; }
    .enc .inst { font-size: 7.5pt; color: <?= paleta('texto_suave') ?>; }
    .enc .ofi { font-size: 7.5pt; font-weight: bold; }
    .enc h1 { font-size: 13pt; color: <?= paleta('guinda') ?>; margin: 5px 0 0 0; }
    .linea { border-bottom: 2px solid <?= paleta('guinda') ?>; margin: 6px 0 1px 0; }
    .linea2 { border-bottom: 1px solid <?= paleta('dorado') ?>; margin: 0 0 8px 0; }
    .dest td { padding: 2px 4px; }
    .dest .et { width: 14%; font-weight: bold; }
    .dest .dp { width: 2%; }
    .dest .cargo { font-size: 7.8pt; color: <?= paleta('texto_suave') ?>; }
    .separador { border-bottom: 0.8px solid <?= paleta('borde') ?>; margin: 6px 0 4px 0; }
    .sec { background: <?= paleta('guinda') ?>; color: <?= paleta('superficie') ?>; font-weight: bold; padding: 2px 6px; margin: 9px 0 4px 0; }
    .texto { text-align: justify; padding: 0 2px; }
    .grilla td, .grilla th { border: 0.6px solid <?= paleta('borde') ?>; padding: 3px 4px; }
    .grilla th { background: <?= paleta('celda') ?>; text-align: left; }
    .eval { margin-bottom: 6px; page-break-inside: avoid; }
    .eval .cab { background: <?= paleta('celda') ?>; font-weight: bold; border: 0.6px solid <?= paleta('borde') ?>; padding: 3px 5px; }
    .eval td { border: 0.6px solid <?= paleta('borde') ?>; padding: 3px 5px; }
    .eval .et { width: 22%; font-weight: bold; background: <?= paleta('fondo') ?>; }
    .accion { font-weight: bold; font-size: 10pt; color: <?= paleta('guinda') ?>; padding: 2px 4px; }
    .cierre { page-break-inside: avoid; }
    .firmas { margin-top: 12px; page-break-inside: avoid; }
    .firmas td.caja { width: 46%; text-align: center; padding-top: 30px; }
    .firmas td.sep { width: 8%; }
    .firmas .raya { border-top: 0.8px solid <?= paleta('texto') ?>; margin: 0 20px 3px 20px; }
    .firmas .nombre { font-weight: bold; }
    .firmas .cargo { font-size: 7.6pt; }
    .firmas .rol { font-size: 7pt; color: <?= paleta('texto_suave') ?>; }
    .anexo { page-break-before: always; }
    .fotos td { width: 50%; border: 0.6px solid <?= paleta('borde') ?>; padding: 5px; text-align: center; vertical-align: top; }
    .fotos img { max-width: 86mm; max-height: 72mm; }
    .fotos .leyenda { font-size: 7.6pt; margin-top: 3px; }
    .fotos tr { page-break-inside: avoid; }
    .marca-agua { position: fixed; top: 38%; left: 8%; font-size: 68pt; color: <?= paleta('marca_agua') ?>; transform: rotate(-32deg); z-index: -1; }
</style>
</head>
<body>

<?php if ($borrador): ?>
    <div class="marca-agua">BORRADOR</div>
<?php endif; ?>

<table class="enc">
    <tr>
        <td class="logo">
            <?php if ($logos['institucional'] !== null): ?>
                <img src="<?= e($logos['institucional']) ?>" alt="Logo institucional">
            <?php else: ?>
                <strong style="font-size: 11pt; color: <?= paleta('guinda') ?>;">CAEN-EPG</strong><br><span style="font-size: 7pt;">OTIC</span>
            <?php endif; ?>
        </td>
        <td class="titulo">
            <div class="inst"><?= e($institucion) ?></div>
            <div class="ofi">OFICINA DE TECNOLOGÍAS DE LA INFORMACIÓN Y COMUNICACIÓN</div>
            <h1>INFORME TÉCNICO <?= e($numero) ?></h1>
        </td>
        <td style="width: 20%;"></td>
    </tr>
</table>
<div class="linea"></div>
<div class="linea2"></div>

<table class="dest">
    <tr><td class="et">PARA</td><td class="dp">:</td><td><strong><?= e($informe['para_nombre']) ?></strong><br><span class="cargo"><?= e($informe['para_cargo']) ?></span></td></tr>
    <tr><td class="et">DE</td><td class="dp">:</td><td><strong><?= e($informe['de_nombre']) ?></strong><?php if ($informe['de_cargo'] !== null): ?><br><span class="cargo"><?= e($informe['de_cargo']) ?></span><?php endif; ?></td></tr>
    <tr><td class="et">ASUNTO</td><td class="dp">:</td><td><?= e($informe['asunto']) ?></td></tr>
    <tr><td class="et">FECHA</td><td class="dp">:</td><td><?= e($fechaLarga) ?></td></tr>
</table>
<div class="separador"></div>

<div class="sec">I. ANTECEDENTES</div>
<div class="texto"><?= $parrafo($informe['antecedentes']) ?></div>

<div class="sec">II. EQUIPOS EVALUADOS</div>
<table class="grilla">
    <thead>
        <tr><th style="width: 5%;">N°</th><th style="width: 12%;">Tipo</th><th style="width: 14%;">Marca</th><th>Modelo</th><th style="width: 18%;">N° de serie</th><th style="width: 18%;">Cód. patrimonial</th></tr>
    </thead>
    <tbody>
    <?php foreach ($equipos as $n => $eq): ?>
        <tr>
            <td><?= e($n + 1) ?></td>
            <td><?= e(etiqueta((string) $eq['tipo'])) ?></td>
            <td><?= e($eq['marca']) ?></td>
            <td><?= e($eq['modelo']) ?></td>
            <td><?= e($eq['nro_serie'] ?? '—') ?></td>
            <td><?= e($eq['codigo_patrimonial'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="sec">III. EVALUACIÓN Y DIAGNÓSTICO TÉCNICO</div>
<?php foreach ($equipos as $n => $eq): ?>
    <table class="eval">
        <tr><td colspan="2" class="cab"><?= e(($n + 1) . '. ' . etiqueta((string) $eq['tipo']) . ' ' . $eq['marca'] . ' ' . $eq['modelo']) ?> — Serie: <?= e($eq['nro_serie'] ?? '—') ?> — Patrimonial: <?= e($eq['codigo_patrimonial'] ?? '—') ?></td></tr>
        <tr><td class="et">Características</td><td><?= $parrafo($eq['caracteristicas']) ?></td></tr>
        <tr><td class="et">Estado funcional</td><td><?= $parrafo($eq['estado_funcional']) ?></td></tr>
        <tr><td class="et">Diagnóstico técnico</td><td><?= $parrafo($eq['diagnostico']) ?></td></tr>
    </table>
<?php endforeach; ?>

<div class="sec">IV. ACCIÓN REQUERIDA</div>
<div class="accion"><?= e(mb_strtoupper(etiqueta((string) $informe['accion_requerida']))) ?></div>

<div class="sec">V. CONCLUSIONES</div>
<div class="texto"><?= $parrafo($informe['conclusiones']) ?></div>

<!-- Recomendaciones y firmas van juntas: las firmas nunca quedan solas en una página. -->
<div class="cierre">
<div class="sec">VI. RECOMENDACIONES</div>
<div class="texto"><?= $parrafo($informe['recomendaciones']) ?></div>

<table class="firmas">
    <tr>
        <td class="caja">
            <div class="raya"></div>
            <div class="nombre"><?= e($informe['de_nombre']) ?></div>
            <div class="cargo"><?= e($informe['de_cargo'] ?? '') ?></div>
            <div class="rol">Elaborado por</div>
        </td>
        <td class="sep"></td>
        <td class="caja">
            <div class="raya"></div>
            <div class="nombre"><?= e($informe['para_nombre']) ?></div>
            <div class="cargo"><?= e($informe['para_cargo']) ?></div>
            <div class="rol">Recibido / V°B°</div>
        </td>
    </tr>
</table>
</div>

<?php if ($evidencias !== []): ?>
    <div class="anexo">
        <div class="sec" style="margin-top: 0;">ANEXO: EVIDENCIAS FOTOGRÁFICAS</div>
        <table class="fotos">
            <?php foreach (array_chunk($evidencias, 2) as $par): ?>
                <tr>
                    <?php foreach ($par as $n => $foto):
                        $imagen = PdfService::imagenBase64((string) $foto['ruta']); ?>
                        <td>
                            <?php if ($imagen !== null): ?><img src="<?= e($imagen) ?>" alt="Evidencia"><?php endif; ?>
                            <div class="leyenda">
                                <?= e($foto['descripcion'] ?? 'Evidencia') ?>
                                <?php if ($foto['marca'] !== null): ?><br><span style="color: <?= paleta('texto_suave') ?>;"><?= e($foto['marca'] . ' ' . $foto['modelo'] . ($foto['nro_serie'] !== null ? ' · ' . $foto['nro_serie'] : '')) ?></span><?php endif; ?>
                            </div>
                        </td>
                    <?php endforeach; ?>
                    <?php if (count($par) === 1): ?><td style="border: none;"></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

</body>
</html>
