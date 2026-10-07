<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\CorrelativoModel;
use InvalidArgumentException;
use LogicException;

/**
 * Numeración anual de documentos (sección 7.5).
 *
 * siguiente() debe llamarse DENTRO de la transacción del documento: bloquea la
 * fila (tipo, año) con SELECT ... FOR UPDATE hasta el commit, de modo que dos
 * cierres simultáneos nunca obtienen el mismo número.
 */
final class CorrelativoService
{
    public const MANTENIMIENTO = 'MANTENIMIENTO';
    public const INFORME = 'INFORME';

    private const FORMATOS = [
        self::MANTENIMIENTO => '%03d-%d/OTIC-MANT',
        self::INFORME       => '%03d-%d/OTIC',
    ];

    private CorrelativoModel $correlativos;
    private Database $db;

    public function __construct()
    {
        $this->correlativos = new CorrelativoModel();
        $this->db = Database::getInstance();
    }

    /**
     * Reserva el siguiente número del año.
     *
     * @return array{anio: int, correlativo: int, numero: string}
     */
    public function siguiente(string $tipo, ?int $anio = null): array
    {
        if (!isset(self::FORMATOS[$tipo])) {
            throw new InvalidArgumentException('Tipo de correlativo no válido: ' . $tipo);
        }
        if (!$this->db->inTransaction()) {
            throw new LogicException('CorrelativoService::siguiente() debe ejecutarse dentro de una transacción.');
        }

        $anio ??= (int) date('Y');
        $this->correlativos->asegurar($tipo, $anio);
        $siguiente = $this->correlativos->ultimoParaActualizar($tipo, $anio) + 1;
        $this->correlativos->fijar($tipo, $anio, $siguiente);

        return [
            'anio'        => $anio,
            'correlativo' => $siguiente,
            'numero'      => self::formatear($tipo, $siguiente, $anio),
        ];
    }

    public static function formatear(string $tipo, int $correlativo, int $anio): string
    {
        return sprintf(self::FORMATOS[$tipo], $correlativo, $anio);
    }
}
