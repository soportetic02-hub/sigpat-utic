<?php

declare(strict_types=1);

/*
 * Definición de rutas. Toda ruta privada va dentro del grupo 'auth' y, si
 * corresponde, con 'rol:...'. Las rutas POST validan CSRF automáticamente.
 */

use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\ConfirmacionActaController;
use App\Controllers\DashboardController;
use App\Controllers\EquipoController;
use App\Controllers\EvidenciaController;
use App\Controllers\InformeController;
use App\Controllers\LicenciaController;
use App\Controllers\MantenimientoController;
use App\Controllers\OficinaController;
use App\Controllers\ParametroController;
use App\Controllers\PerfilController;
use App\Controllers\PersonalController;
use App\Controllers\UsuarioController;
use App\Core\Router;

return static function (Router $router): void {
    // Públicas
    $router->get('login', [AuthController::class, 'mostrarLogin']);
    $router->post('login', [AuthController::class, 'login']);

    // Enlace del correo del acta: el usuario (personal, sin cuenta) ve el acta y confirma su recepción.
    $router->get('actas/confirmar/{token}', [ConfirmacionActaController::class, 'mostrar']);
    $router->get('actas/confirmar/{token}/pdf', [ConfirmacionActaController::class, 'pdf']);
    $router->post('actas/confirmar/{token}', [ConfirmacionActaController::class, 'confirmar']);

    // Autenticadas (ADMINISTRADOR y TECNICO)
    $router->group(['middleware' => ['auth']], static function (Router $router): void {
        // Cerrar sesión solo exige sesión: cualquier sesión debe poder cerrarse siempre.
        $router->post('logout', [AuthController::class, 'logout']);

        $router->group(['middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [DashboardController::class, 'index']);
            $router->get('perfil/password', [PerfilController::class, 'mostrarPassword']);
            $router->post('perfil/password', [PerfilController::class, 'cambiarPassword']);
        });

        // Oficinas: ADMINISTRADOR y TECNICO; desactivar/activar solo ADMINISTRADOR
        $router->group(['prefix' => 'oficinas', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [OficinaController::class, 'index']);
            $router->get('crear', [OficinaController::class, 'crear']);
            $router->post('/', [OficinaController::class, 'guardar']);
            $router->get('{id}/editar', [OficinaController::class, 'editar']);
            $router->post('{id}', [OficinaController::class, 'actualizar']);
            $router->post('{id}/activo', [OficinaController::class, 'alternarActivo'], ['rol:ADMINISTRADOR']);
        });

        // Personal: ADMINISTRADOR y TECNICO; desactivar/activar solo ADMINISTRADOR
        $router->group(['prefix' => 'personal', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [PersonalController::class, 'index']);
            $router->get('autocompletar', [PersonalController::class, 'autocompletar']);
            $router->get('crear', [PersonalController::class, 'crear']);
            $router->post('/', [PersonalController::class, 'guardar']);
            $router->get('{id}', [PersonalController::class, 'mostrar']);
            $router->get('{id}/editar', [PersonalController::class, 'editar']);
            $router->post('{id}', [PersonalController::class, 'actualizar']);
            $router->post('{id}/activo', [PersonalController::class, 'alternarActivo'], ['rol:ADMINISTRADOR']);
            $router->get('{id}/equipos-disponibles', [PersonalController::class, 'equiposDisponibles']);
            $router->post('{id}/equipos', [PersonalController::class, 'asignarEquipo']);
            $router->post('{id}/equipos/{equipoId}/desvincular', [PersonalController::class, 'desvincularEquipo']);
        });

        // Inventario: ADMINISTRADOR y TECNICO; eliminar y confirmar baja solo ADMINISTRADOR
        $router->group(['prefix' => 'equipos', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [EquipoController::class, 'index']);
            $router->get('exportar', [EquipoController::class, 'exportar']);
            $router->get('crear', [EquipoController::class, 'crear']);
            $router->post('/', [EquipoController::class, 'guardar']);
            $router->get('{id}', [EquipoController::class, 'mostrar']);
            $router->get('{id}/editar', [EquipoController::class, 'editar']);
            $router->post('{id}', [EquipoController::class, 'actualizar']);
            $router->post('{id}/eliminar', [EquipoController::class, 'eliminar'], ['rol:ADMINISTRADOR']);
            $router->post('{id}/baja', [EquipoController::class, 'confirmarBaja'], ['rol:ADMINISTRADOR']);
        });

        // Licencias Microsoft 365: ver y operar instalaciones ADMINISTRADOR y TECNICO;
        // crear/editar/desactivar cuentas y ver contraseñas solo ADMINISTRADOR
        $router->group(['prefix' => 'licencias', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [LicenciaController::class, 'index']);
            $router->get('crear', [LicenciaController::class, 'crear'], ['rol:ADMINISTRADOR']);
            $router->post('/', [LicenciaController::class, 'guardar'], ['rol:ADMINISTRADOR']);
            $router->get('{id}', [LicenciaController::class, 'mostrar']);
            $router->get('{id}/editar', [LicenciaController::class, 'editar'], ['rol:ADMINISTRADOR']);
            $router->post('{id}', [LicenciaController::class, 'actualizar'], ['rol:ADMINISTRADOR']);
            $router->post('{id}/activo', [LicenciaController::class, 'alternarActivo'], ['rol:ADMINISTRADOR']);
            $router->post('{id}/contrasena', [LicenciaController::class, 'contrasena'], ['rol:ADMINISTRADOR']);
            $router->get('{id}/equipos-elegibles', [LicenciaController::class, 'equiposElegibles']);
            $router->post('{id}/slots', [LicenciaController::class, 'asignar']);
            $router->post('{id}/slots/{slotId}/liberar', [LicenciaController::class, 'liberar']);
            $router->post('{id}/slots/{slotId}/reasignar', [LicenciaController::class, 'reasignar']);
        });

        // Actas de mantenimiento: ADMINISTRADOR y TECNICO
        $router->group(['prefix' => 'mantenimientos', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [MantenimientoController::class, 'index']);
            $router->get('crear', [MantenimientoController::class, 'crear']);
            $router->post('/', [MantenimientoController::class, 'guardar']);
            $router->get('personal/{id}/equipos', [MantenimientoController::class, 'equiposDePersonal']);
            $router->get('{id}', [MantenimientoController::class, 'mostrar']);
            $router->get('{id}/editar', [MantenimientoController::class, 'editar']);
            $router->post('{id}', [MantenimientoController::class, 'actualizar']);
            $router->post('{id}/anular', [MantenimientoController::class, 'anular']);
            $router->post('{id}/eliminar', [MantenimientoController::class, 'eliminar'], ['rol:ADMINISTRADOR']);
            $router->get('{id}/pdf', [MantenimientoController::class, 'verPdf']);
            $router->get('{id}/descargar', [MantenimientoController::class, 'descargarPdf']);
            // Firma (el servicio exige usuarios_sistema.puede_firmar = 1) y envío por correo
            $router->post('{id}/firmar', [MantenimientoController::class, 'firmar']);
            $router->post('{id}/enviar', [MantenimientoController::class, 'enviar']);
        });

        // Informes técnicos: ADMINISTRADOR y TECNICO
        $router->group(['prefix' => 'informes', 'middleware' => ['rol:ADMINISTRADOR,TECNICO']], static function (Router $router): void {
            $router->get('/', [InformeController::class, 'index']);
            $router->get('crear', [InformeController::class, 'crear']);
            $router->post('/', [InformeController::class, 'guardar']);
            $router->get('buscar-equipos', [InformeController::class, 'buscarEquipos']);
            $router->get('{id}', [InformeController::class, 'mostrar']);
            $router->get('{id}/editar', [InformeController::class, 'editar']);
            $router->post('{id}', [InformeController::class, 'actualizar']);
            $router->post('{id}/emitir', [InformeController::class, 'emitir']);
            $router->post('{id}/anular', [InformeController::class, 'anular']);
            $router->post('{id}/evidencias', [InformeController::class, 'subirEvidencias']);
            $router->post('{id}/evidencias/{evidenciaId}/eliminar', [InformeController::class, 'eliminarEvidencia']);
            $router->get('{id}/pdf', [InformeController::class, 'verPdf']);
            $router->get('{id}/descargar', [InformeController::class, 'descargarPdf']);
            $router->get('{id}/word', [InformeController::class, 'descargarWord']);
        });
        $router->get('evidencias/{id}', [EvidenciaController::class, 'mostrar'], ['rol:ADMINISTRADOR,TECNICO']);

        // Auditoría: solo ADMINISTRADOR (lectura)
        $router->group(['prefix' => 'auditoria', 'middleware' => ['rol:ADMINISTRADOR']], static function (Router $router): void {
            $router->get('/', [AuditoriaController::class, 'index']);
            $router->get('{id}', [AuditoriaController::class, 'mostrar']);
        });

        // Parámetros y catálogos: solo ADMINISTRADOR
        $router->group(['prefix' => 'parametros', 'middleware' => ['rol:ADMINISTRADOR']], static function (Router $router): void {
            $router->get('/', [ParametroController::class, 'index']);
            $router->post('/', [ParametroController::class, 'guardarParametros']);
            $router->post('software', [ParametroController::class, 'crearSoftware']);
            $router->post('software/{id}', [ParametroController::class, 'actualizarSoftware']);
            $router->post('software/{id}/activo', [ParametroController::class, 'alternarSoftware']);
            $router->post('checklist', [ParametroController::class, 'crearChecklist']);
            $router->post('checklist/{id}', [ParametroController::class, 'actualizarChecklist']);
            $router->post('checklist/{id}/activo', [ParametroController::class, 'alternarChecklist']);
        });

        // Solo ADMINISTRADOR
        $router->group(['prefix' => 'usuarios', 'middleware' => ['rol:ADMINISTRADOR']], static function (Router $router): void {
            $router->get('/', [UsuarioController::class, 'index']);
            $router->get('crear', [UsuarioController::class, 'crear']);
            $router->post('/', [UsuarioController::class, 'guardar']);
            $router->get('{id}/editar', [UsuarioController::class, 'editar']);
            $router->post('{id}', [UsuarioController::class, 'actualizar']);
            $router->get('{id}/password', [UsuarioController::class, 'mostrarPassword']);
            $router->post('{id}/password', [UsuarioController::class, 'cambiarPassword']);
            $router->post('{id}/activo', [UsuarioController::class, 'alternarActivo']);
        });
    });
};
