<?php

declare(strict_types=1);

/**
 * Correo con el acta de mantenimiento adjunta y el enlace de confirmación.
 * Maquetado con tablas y estilos en línea (clientes de correo).
 *
 * @var array<string, mixed> $acta
 * @var string $usuario
 * @var string $enlace
 * @var string $expira
 * @var bool $firmado
 */
$equipo = etiqueta((string) $acta['equipo_tipo']) . ' ' . $acta['marca'] . ' ' . $acta['modelo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acta de mantenimiento N° <?= e($acta['numero']) ?></title>
</head>
<body style="margin:0; padding:0; background:<?= paleta('fondo') ?>; font-family:Arial, Helvetica, sans-serif; color:<?= paleta('texto') ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= paleta('fondo') ?>; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:<?= paleta('superficie') ?>; border-radius:8px; overflow:hidden; border:1px solid <?= paleta('borde_claro') ?>;">
                <tr>
                    <td style="background:<?= paleta('guinda') ?>; color:<?= paleta('superficie') ?>; padding:18px 24px; border-bottom:3px solid <?= paleta('dorado') ?>;">
                        <div style="font-size:12px; opacity:0.85;">OTIC · CAEN-EPG</div>
                        <div style="font-size:19px; font-weight:bold; margin-top:4px;">Acta de mantenimiento N° <?= e($acta['numero']) ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 24px 6px 24px; font-size:14px; line-height:1.5;">
                        <p style="margin:0 0 12px 0;">Estimado(a) <?= e($usuario !== '' ? $usuario : 'usuario') ?>:</p>
                        <p style="margin:0 0 16px 0;">
                            La Oficina de Tecnologías de la Información y Comunicación le remite el acta del
                            mantenimiento <?= e(mb_strtolower(etiqueta((string) $acta['tipo']))) ?> realizado al equipo a su cargo<?= $firmado ? ', firmada digitalmente por el Jefe de la OTIC' : '' ?>.
                            El acta va <strong>adjunta en PDF</strong>.
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; border:1px solid <?= paleta('borde_claro') ?>; border-radius:6px;">
                            <tr><td style="padding:7px 12px; color:<?= paleta('texto_suave') ?>; width:32%;">Equipo</td><td style="padding:7px 12px;"><?= e($equipo) ?></td></tr>
                            <?php if ($acta['nro_serie'] !== null): ?>
                                <tr><td style="padding:7px 12px; color:<?= paleta('texto_suave') ?>; border-top:1px solid <?= paleta('borde_claro') ?>;">Serie</td><td style="padding:7px 12px; border-top:1px solid <?= paleta('borde_claro') ?>;"><?= e($acta['nro_serie']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td style="padding:7px 12px; color:<?= paleta('texto_suave') ?>; border-top:1px solid <?= paleta('borde_claro') ?>;">Fecha</td><td style="padding:7px 12px; border-top:1px solid <?= paleta('borde_claro') ?>;"><?= e(fecha((string) $acta['fecha_salida'], true)) ?></td></tr>
                            <tr><td style="padding:7px 12px; color:<?= paleta('texto_suave') ?>; border-top:1px solid <?= paleta('borde_claro') ?>;">Técnico</td><td style="padding:7px 12px; border-top:1px solid <?= paleta('borde_claro') ?>;"><?= e($acta['tecnico_nombres'] . ' ' . $acta['tecnico_apellidos']) ?></td></tr>
                        </table>
                        <p style="margin:20px 0 8px 0;">Después de revisarla, confirme que la recibió:</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:4px 24px 20px 24px;">
                        <a href="<?= e($enlace) ?>" style="display:inline-block; background:<?= paleta('guinda') ?>; color:<?= paleta('superficie') ?>; text-decoration:none; font-weight:bold; font-size:15px; padding:12px 26px; border-radius:6px;">Revisar y confirmar recepción</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 22px 24px; font-size:12px; color:<?= paleta('texto_suave') ?>; line-height:1.5;">
                        Si el botón no funciona, copie este enlace en su navegador:<br>
                        <a href="<?= e($enlace) ?>" style="color:<?= paleta('guinda') ?>; word-break:break-all;"><?= e($enlace) ?></a><br>
                        El enlace vence el <?= e(fecha($expira, true)) ?>. Es personal: no lo reenvíe.
                    </td>
                </tr>
                <tr>
                    <td style="background:<?= paleta('fondo') ?>; padding:12px 24px; font-size:11px; color:<?= paleta('texto_suave') ?>; border-top:1px solid <?= paleta('borde_claro') ?>;">
                        Mensaje enviado por SIGPAT-OTIC. Si no reconoce este mantenimiento, comuníquese con la OTIC.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
