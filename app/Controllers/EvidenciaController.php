<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\InformeEvidenciaModel;
use App\Services\EvidenciaService;

/**
 * Sirve las evidencias fotográficas desde storage/evidencias (fuera de public)
 * solo a usuarios autenticados con rol permitido (la ruta aplica auth + rol).
 */
final class EvidenciaController extends Controller
{
    public function mostrar(int $id): void
    {
        $evidencia = (new InformeEvidenciaModel())->find($id);
        if ($evidencia === null) {
            throw new HttpException(404, 'La evidencia no existe.');
        }
        $ruta = (new EvidenciaService())->ruta((string) $evidencia['archivo']);
        if ($ruta === null) {
            throw new HttpException(404, 'El archivo de la evidencia no está disponible.');
        }

        $extension = $evidencia['mime'] === 'image/png' ? 'png' : 'jpg';
        Response::download($ruta, 'evidencia_' . $id . '.' . $extension, true, (string) $evidencia['mime']);
    }
}
