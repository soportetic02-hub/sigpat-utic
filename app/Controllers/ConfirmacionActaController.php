<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\ActaEnvioService;

/**
 * Página pública (sin inicio de sesión) del enlace que recibe el usuario por correo:
 * ver el acta y confirmar su recepción. El token de 64 caracteres es la credencial.
 */
final class ConfirmacionActaController extends Controller
{
    public function mostrar(string $token): void
    {
        $envio = (new ActaEnvioService())->paraConfirmar($token);
        header('X-Robots-Tag: noindex, nofollow');

        $this->view('confirmacion/acta', [
            'titulo' => 'Acta de mantenimiento N° ' . $envio['numero'],
            'envio'  => $envio,
            'token'  => $token,
        ], 'auth');
    }

    public function pdf(string $token): void
    {
        $pdf = (new ActaEnvioService())->pdfDeEnlace($token);
        Response::download($pdf['ruta'], $pdf['nombre'], true, 'application/pdf');
    }

    public function confirmar(string $token): void
    {
        try {
            $nueva = (new ActaEnvioService())->confirmar($token, $this->request->ip(), $this->request->userAgent());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('actas/confirmar/' . $token);
        }

        Session::flash('success', $nueva ? 'Gracias. Su confirmación de recepción quedó registrada.' : 'La recepción de esta acta ya estaba confirmada.');
        $this->redirect('actas/confirmar/' . $token);
    }
}
