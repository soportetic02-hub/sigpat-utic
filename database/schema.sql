-- =====================================================================
-- SIGPAT-OTIC (CAEN-EPG) - Esquema de base de datos
-- Compatible con MariaDB 10.4+ (XAMPP). Motor InnoDB, utf8mb4_unicode_ci.
--
-- ATENCIÓN: este script ELIMINA y vuelve a crear todas las tablas del
-- sistema (DROP TABLE IF EXISTS). Úsalo solo para instalar desde cero o
-- en desarrollo. Nunca lo ejecutes sobre una base de datos con datos reales.
-- Orden de importación: 1) schema.sql  2) seeds.sql
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


DROP VIEW IF EXISTS v_equipos_estado;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS parametros;
DROP TABLE IF EXISTS correlativos;
DROP TABLE IF EXISTS informe_evidencias;
DROP TABLE IF EXISTS informe_equipos;
DROP TABLE IF EXISTS informes_tecnicos;
DROP TABLE IF EXISTS mantenimiento_envios;
DROP TABLE IF EXISTS mantenimiento_software;
DROP TABLE IF EXISTS mantenimiento_componentes;
DROP TABLE IF EXISTS mantenimiento_checklist;
DROP TABLE IF EXISTS mantenimientos;
DROP TABLE IF EXISTS checklist_items;
DROP TABLE IF EXISTS catalogo_software;
DROP TABLE IF EXISTS licencia_equipos;
DROP TABLE IF EXISTS licencias_office;
DROP TABLE IF EXISTS historial_asignaciones;
DROP TABLE IF EXISTS equipo_puntos_red;
DROP TABLE IF EXISTS equipo_codigos;
DROP TABLE IF EXISTS equipos;
DROP TABLE IF EXISTS personal;
DROP TABLE IF EXISTS oficinas;
DROP TABLE IF EXISTS usuarios_sistema;

-- ---------------------------------------------------------------------
-- 1. Usuarios del sistema (inician sesión)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios_sistema (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    usuario               VARCHAR(50)      NOT NULL,
    password_hash         VARCHAR(255)     NOT NULL,
    nombres               VARCHAR(100)     NOT NULL,
    apellidos             VARCHAR(100)     NOT NULL,
    dni                   CHAR(8)          NULL,
    cargo                 VARCHAR(150)     NULL,
    email                 VARCHAR(150)     NULL,
    rol                   ENUM('ADMINISTRADOR','TECNICO') NOT NULL DEFAULT 'TECNICO',
    puede_firmar          TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = puede firmar actas con su DNIe (Jefe de la OTIC)',
    activo                TINYINT(1)       NOT NULL DEFAULT 1,
    intentos_fallidos     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta       DATETIME         NULL,
    debe_cambiar_password TINYINT(1)       NOT NULL DEFAULT 0,
    ultimo_acceso         DATETIME         NULL,
    created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_usuarios_sistema_usuario UNIQUE (usuario),
    CONSTRAINT uq_usuarios_sistema_email   UNIQUE (email),
    CONSTRAINT uq_usuarios_sistema_dni     UNIQUE (dni),
    KEY idx_usuarios_sistema_rol_activo (rol, activo),
    CONSTRAINT chk_usuarios_sistema_activo CHECK (activo IN (0, 1)),
    CONSTRAINT chk_usuarios_sistema_debe_cambiar CHECK (debe_cambiar_password IN (0, 1)),
    CONSTRAINT chk_usuarios_sistema_puede_firmar CHECK (puede_firmar IN (0, 1)),
    CONSTRAINT chk_usuarios_sistema_dni CHECK (dni IS NULL OR dni REGEXP '^[0-9]{8}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios que inician sesión (ADMINISTRADOR / TECNICO)';

-- ---------------------------------------------------------------------
-- 2. Oficinas (jerárquicas)
-- ---------------------------------------------------------------------
CREATE TABLE oficinas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    padre_id    INT UNSIGNED NULL,
    nombre      VARCHAR(150) NOT NULL,
    siglas      VARCHAR(20)  NULL,
    tipo        ENUM('DIRECCION','DEPARTAMENTO','OFICINA') NOT NULL DEFAULT 'OFICINA',
    ubicacion   VARCHAR(150) NULL COMMENT 'Pabellón, piso o ambiente',
    telefono    VARCHAR(30)  NULL COMMENT 'Anexo o teléfono',
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_oficinas_padre_nombre UNIQUE (padre_id, nombre),
    KEY idx_oficinas_nombre (nombre),
    KEY idx_oficinas_activo (activo),
    CONSTRAINT fk_oficinas_padre_id FOREIGN KEY (padre_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_oficinas_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Direcciones, departamentos y oficinas (árbol mediante padre_id)';

-- ---------------------------------------------------------------------
-- 3. Personal (usa equipos, no inicia sesión)
-- ---------------------------------------------------------------------
CREATE TABLE personal (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombres     VARCHAR(100) NOT NULL,
    apellidos   VARCHAR(100) NOT NULL,
    dni         CHAR(8)      NULL,
    cargo       VARCHAR(150) NULL,
    email       VARCHAR(150) NULL,
    telefono    VARCHAR(30)  NULL,
    oficina_id  INT UNSIGNED NOT NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_personal_dni UNIQUE (dni),
    KEY idx_personal_oficina_id (oficina_id),
    KEY idx_personal_apellidos_nombres (apellidos, nombres),
    KEY idx_personal_email (email),
    KEY idx_personal_activo (activo),
    CONSTRAINT fk_personal_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_personal_activo CHECK (activo IN (0, 1)),
    CONSTRAINT chk_personal_dni CHECK (dni IS NULL OR dni REGEXP '^[0-9]{8}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Trabajadores institucionales responsables de equipos';

-- ---------------------------------------------------------------------
-- 4. Equipos (inventario patrimonial)
-- ---------------------------------------------------------------------
CREATE TABLE equipos (
    id                      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    tipo                    ENUM('PC','LAPTOP','IMPRESORA') NOT NULL,
    marca                   VARCHAR(60)       NOT NULL,
    modelo                  VARCHAR(100)      NOT NULL,
    nro_serie               VARCHAR(80)       NULL,
    codigo_patrimonial      VARCHAR(30)       NULL,
    codigo_interno          VARCHAR(30)       NULL,
    -- Hardware (NULL para impresoras)
    procesador              VARCHAR(120)      NULL,
    ram_gb                  SMALLINT UNSIGNED NULL,
    disco_tipo              ENUM('HDD','SSD','NVME') NULL,
    disco_capacidad_gb      INT UNSIGNED      NULL,
    sistema_operativo       VARCHAR(80)       NULL,
    -- Red
    hostname                VARCHAR(63)       NULL,
    mac_lan                 CHAR(17)          NULL,
    ip_lan                  VARCHAR(45)       NULL,
    mac_wifi                CHAR(17)          NULL,
    -- Ubicación y responsable
    oficina_id              INT UNSIGNED      NOT NULL COMMENT 'Ubicación física (obligatoria)',
    personal_id             INT UNSIGNED      NULL     COMMENT 'Responsable; NULL = sin responsable (p. ej. impresora compartida)',
    -- Estado
    estado_operativo        ENUM('OPERATIVO','EN_MANTENIMIENTO','INOPERATIVO','DE_BAJA') NOT NULL DEFAULT 'OPERATIVO',
    condicion_fisica        ENUM('BUENO','REGULAR','MALO') NOT NULL DEFAULT 'BUENO',
    recomendado_baja        TINYINT(1)        NOT NULL DEFAULT 0 COMMENT '1 = un informe EMITIDO recomienda la baja',
    fecha_baja              DATE              NULL,
    -- Ciclo de vida / control patrimonial
    fecha_adquisicion       DATE              NULL,
    vida_util_meses         SMALLINT UNSIGNED NOT NULL DEFAULT 48,
    periodicidad_mant_meses TINYINT UNSIGNED  NULL COMMENT 'NULL = usar parametros.periodicidad_mant_meses',
    valor_adquisicion       DECIMAL(12,2)     NULL,
    orden_compra            VARCHAR(50)       NULL,
    proveedor               VARCHAR(150)      NULL,
    garantia_hasta          DATE              NULL,
    observaciones           TEXT              NULL,
    created_by              INT UNSIGNED      NULL,
    updated_by              INT UNSIGNED      NULL,
    created_at              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_equipos_nro_serie          UNIQUE (nro_serie),
    CONSTRAINT uq_equipos_codigo_patrimonial UNIQUE (codigo_patrimonial),
    CONSTRAINT uq_equipos_codigo_interno     UNIQUE (codigo_interno),
    CONSTRAINT uq_equipos_hostname           UNIQUE (hostname),
    CONSTRAINT uq_equipos_mac_lan            UNIQUE (mac_lan),
    CONSTRAINT uq_equipos_ip_lan             UNIQUE (ip_lan),
    CONSTRAINT uq_equipos_mac_wifi           UNIQUE (mac_wifi),
    KEY idx_equipos_oficina_id (oficina_id),
    KEY idx_equipos_personal_id (personal_id),
    KEY idx_equipos_tipo_estado (tipo, estado_operativo),
    KEY idx_equipos_estado_operativo (estado_operativo),
    KEY idx_equipos_condicion_fisica (condicion_fisica),
    KEY idx_equipos_recomendado_baja (recomendado_baja),
    KEY idx_equipos_marca_modelo (marca, modelo),
    KEY idx_equipos_fecha_adquisicion (fecha_adquisicion),
    KEY idx_equipos_created_by (created_by),
    KEY idx_equipos_updated_by (updated_by),
    CONSTRAINT fk_equipos_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_equipos_personal_id FOREIGN KEY (personal_id)
        REFERENCES personal (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_equipos_created_by FOREIGN KEY (created_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_equipos_updated_by FOREIGN KEY (updated_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_equipos_hardware_impresora CHECK (
        tipo <> 'IMPRESORA'
        OR (procesador IS NULL AND ram_gb IS NULL AND disco_tipo IS NULL AND disco_capacidad_gb IS NULL)
    ),
    CONSTRAINT chk_equipos_mac_lan  CHECK (mac_lan  IS NULL OR mac_lan  REGEXP '^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$'),
    CONSTRAINT chk_equipos_mac_wifi CHECK (mac_wifi IS NULL OR mac_wifi REGEXP '^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$'),
    CONSTRAINT chk_equipos_recomendado_baja CHECK (recomendado_baja IN (0, 1)),
    CONSTRAINT chk_equipos_vida_util CHECK (vida_util_meses BETWEEN 1 AND 600),
    CONSTRAINT chk_equipos_periodicidad CHECK (periodicidad_mant_meses IS NULL OR periodicidad_mant_meses BETWEEN 1 AND 60),
    CONSTRAINT chk_equipos_valor CHECK (valor_adquisicion IS NULL OR valor_adquisicion >= 0),
    CONSTRAINT chk_equipos_fecha_baja CHECK (fecha_baja IS NULL OR estado_operativo = 'DE_BAJA')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inventario patrimonial de PC, laptops e impresoras';

-- ---------------------------------------------------------------------
-- 5. Códigos anuales de inventario
-- ---------------------------------------------------------------------
CREATE TABLE equipo_codigos (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    equipo_id   INT UNSIGNED      NOT NULL,
    anio        SMALLINT UNSIGNED NOT NULL,
    codigo      VARCHAR(40)       NOT NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_equipo_codigos_equipo_anio UNIQUE (equipo_id, anio),
    CONSTRAINT uq_equipo_codigos_anio_codigo UNIQUE (anio, codigo),
    KEY idx_equipo_codigos_codigo (codigo),
    CONSTRAINT fk_equipo_codigos_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_equipo_codigos_anio CHECK (anio BETWEEN 1990 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Código de inventario asignado a cada equipo por año';

-- ---------------------------------------------------------------------
-- 6. Puntos de red por equipo
-- ---------------------------------------------------------------------
CREATE TABLE equipo_puntos_red (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipo_id     INT UNSIGNED NOT NULL,
    codigo_punto  VARCHAR(30)  NOT NULL COMMENT 'Rotulado del punto de red (p. ej. P2-D-015)',
    switch_puerto VARCHAR(60)  NULL,
    vlan          VARCHAR(20)  NULL,
    observacion   VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_equipo_puntos_red_equipo_punto UNIQUE (equipo_id, codigo_punto),
    KEY idx_equipo_puntos_red_codigo_punto (codigo_punto),
    CONSTRAINT fk_equipo_puntos_red_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Puntos de red a los que se conecta cada equipo';

-- ---------------------------------------------------------------------
-- 7. Historial de asignaciones (responsable / oficina)
-- ---------------------------------------------------------------------
CREATE TABLE historial_asignaciones (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipo_id       INT UNSIGNED NOT NULL,
    oficina_id      INT UNSIGNED NOT NULL,
    personal_id     INT UNSIGNED NULL,
    fecha_inicio    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin       DATETIME     NULL,
    motivo          ENUM('ALTA','ASIGNACION','DESVINCULACION','TRASLADO','ROTACION','BAJA') NOT NULL DEFAULT 'ASIGNACION',
    observacion     VARCHAR(255) NULL,
    usuario_id      INT UNSIGNED NULL COMMENT 'Usuario del sistema que registró el movimiento',
    equipo_vigente  INT UNSIGNED AS (IF(fecha_fin IS NULL, equipo_id, NULL)) PERSISTENT
                    COMMENT 'Generada: garantiza un solo registro vigente por equipo',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_historial_asignaciones_equipo_vigente UNIQUE (equipo_vigente),
    KEY idx_historial_asignaciones_equipo_fecha (equipo_id, fecha_inicio),
    KEY idx_historial_asignaciones_oficina_id (oficina_id),
    KEY idx_historial_asignaciones_personal_id (personal_id),
    KEY idx_historial_asignaciones_usuario_id (usuario_id),
    CONSTRAINT fk_historial_asignaciones_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_historial_asignaciones_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historial_asignaciones_personal_id FOREIGN KEY (personal_id)
        REFERENCES personal (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historial_asignaciones_usuario_id FOREIGN KEY (usuario_id)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_historial_asignaciones_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de ubicación y responsable de cada equipo';

-- ---------------------------------------------------------------------
-- 8. Cuentas Microsoft 365 (cada una permite instalar Office en 5 dispositivos)
-- ---------------------------------------------------------------------
CREATE TABLE licencias_office (
    id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    codigo             VARCHAR(20)      NOT NULL COMMENT 'Identificador interno (p. ej. licencia01)',
    correo             VARCHAR(150)     NOT NULL COMMENT 'Cuenta Microsoft 365 (nombre de usuario)',
    plan               VARCHAR(80)      NOT NULL DEFAULT 'Microsoft 365 Empresa Estándar',
    contrasena_cifrada TEXT             NULL COMMENT 'AES-256-GCM con APP_KEY; nunca en texto plano',
    fecha_alta         DATE             NULL,
    fecha_vencimiento  DATE             NULL,
    estado             ENUM('ACTIVA','SUSPENDIDA','VENCIDA','BAJA') NOT NULL DEFAULT 'ACTIVA',
    max_instalaciones  TINYINT UNSIGNED NOT NULL DEFAULT 5,
    observaciones      TEXT             NULL,
    activo             TINYINT(1)       NOT NULL DEFAULT 1,
    created_by         INT UNSIGNED     NULL,
    created_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_licencias_office_codigo UNIQUE (codigo),
    CONSTRAINT uq_licencias_office_correo UNIQUE (correo),
    KEY idx_licencias_office_activo (activo),
    KEY idx_licencias_office_estado (estado, activo),
    KEY idx_licencias_office_created_by (created_by),
    CONSTRAINT fk_licencias_office_created_by FOREIGN KEY (created_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_licencias_office_activo CHECK (activo IN (0, 1)),
    CONSTRAINT chk_licencias_office_correo CHECK (correo REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$'),
    CONSTRAINT chk_licencias_office_max CHECK (max_instalaciones BETWEEN 1 AND 5),
    CONSTRAINT chk_licencias_office_fechas CHECK (fecha_vencimiento IS NULL OR fecha_alta IS NULL OR fecha_vencimiento >= fecha_alta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cuentas Microsoft 365 (cada una permite instalar Office en hasta 5 dispositivos)';

-- ---------------------------------------------------------------------
-- 9. Instalaciones de cada cuenta (slots 1-5)
-- ---------------------------------------------------------------------
CREATE TABLE licencia_equipos (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    licencia_id         INT UNSIGNED     NOT NULL,
    equipo_id           INT UNSIGNED     NULL COMMENT 'Equipo del inventario; NULL si solo se conoce equipo_texto',
    equipo_texto        VARCHAR(100)     NULL COMMENT 'Hostname o descripción de un equipo no inventariado (tablet, etc.)',
    personal_id         INT UNSIGNED     NULL,
    usuario_texto       VARCHAR(150)     NULL COMMENT 'Nombre de una persona no registrada en Personal',
    oficina_id          INT UNSIGNED     NULL,
    estado_verificacion ENUM('OK','POR_VERIFICAR') NOT NULL DEFAULT 'OK',
    slot                TINYINT UNSIGNED NOT NULL,
    fecha_asignacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    asignado_por      INT UNSIGNED     NULL,
    fecha_liberacion  DATETIME         NULL,
    motivo_liberacion ENUM('FORMATEO','BAJA','REASIGNACION','OTRO') NULL,
    liberado_por      INT UNSIGNED     NULL,
    observacion       VARCHAR(255)     NULL,
    slot_activo       TINYINT UNSIGNED AS (IF(fecha_liberacion IS NULL, slot, NULL)) PERSISTENT
                      COMMENT 'Generada: slot ocupado (NULL si fue liberado)',
    equipo_activo     INT UNSIGNED     AS (IF(fecha_liberacion IS NULL, equipo_id, NULL)) PERSISTENT
                      COMMENT 'Generada: equipo con licencia vigente (NULL si fue liberado)',
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_licencia_equipos_slot_activo   UNIQUE (licencia_id, slot_activo),
    CONSTRAINT uq_licencia_equipos_equipo_activo UNIQUE (equipo_activo),
    KEY idx_licencia_equipos_equipo_id (equipo_id),
    KEY idx_licencia_equipos_licencia_liberacion (licencia_id, fecha_liberacion),
    KEY idx_licencia_equipos_asignado_por (asignado_por),
    KEY idx_licencia_equipos_liberado_por (liberado_por),
    KEY idx_licencia_equipos_personal_id (personal_id),
    KEY idx_licencia_equipos_oficina_id (oficina_id),
    KEY idx_licencia_equipos_verificacion (estado_verificacion),
    CONSTRAINT fk_licencia_equipos_licencia_id FOREIGN KEY (licencia_id)
        REFERENCES licencias_office (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- ON UPDATE RESTRICT: equipo_id se usa en chk_licencia_equipos_equipo y en la columna generada equipo_activo.
    CONSTRAINT fk_licencia_equipos_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_licencia_equipos_personal_id FOREIGN KEY (personal_id)
        REFERENCES personal (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_licencia_equipos_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_licencia_equipos_asignado_por FOREIGN KEY (asignado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- ON UPDATE RESTRICT: MariaDB (error 1901) no admite en un CHECK una columna con FK en CASCADE/SET NULL,
    -- y chk_licencia_equipos_liberacion usa liberado_por.
    CONSTRAINT fk_licencia_equipos_liberado_por FOREIGN KEY (liberado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_licencia_equipos_slot CHECK (slot BETWEEN 1 AND 5),
    CONSTRAINT chk_licencia_equipos_equipo CHECK (equipo_id IS NOT NULL OR (equipo_texto IS NOT NULL AND equipo_texto <> '')),
    CONSTRAINT chk_licencia_equipos_liberacion CHECK (
        (fecha_liberacion IS NULL AND motivo_liberacion IS NULL AND liberado_por IS NULL)
        OR (fecha_liberacion IS NOT NULL AND motivo_liberacion IS NOT NULL)
    ),
    CONSTRAINT chk_licencia_equipos_fechas CHECK (fecha_liberacion IS NULL OR fecha_liberacion >= fecha_asignacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Instalaciones (slots 1-5) de cada cuenta Microsoft 365, con historial de liberaciones';

-- ---------------------------------------------------------------------
-- 10. Catálogo de software
-- ---------------------------------------------------------------------
CREATE TABLE catalogo_software (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)      NOT NULL,
    descripcion VARCHAR(255)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_catalogo_software_nombre UNIQUE (nombre),
    KEY idx_catalogo_software_activo_orden (activo, orden),
    CONSTRAINT chk_catalogo_software_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo administrable de software instalable en mantenimientos';

-- ---------------------------------------------------------------------
-- 11. Catálogo de ítems de checklist
-- ---------------------------------------------------------------------
CREATE TABLE checklist_items (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(40)       NULL COMMENT 'Identificador estable para ítems con lógica asociada (p. ej. FORMATEO_SO)',
    categoria   ENUM('FISICO','LOGICO','RED') NOT NULL,
    descripcion VARCHAR(150)      NOT NULL,
    aplica_a    SET('PC','LAPTOP','IMPRESORA') NOT NULL DEFAULT 'PC,LAPTOP,IMPRESORA',
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_checklist_items_codigo UNIQUE (codigo),
    CONSTRAINT uq_checklist_items_categoria_descripcion UNIQUE (categoria, descripcion),
    KEY idx_checklist_items_categoria_orden (categoria, activo, orden),
    CONSTRAINT chk_checklist_items_activo CHECK (activo IN (0, 1)),
    CONSTRAINT chk_checklist_items_aplica_a CHECK (aplica_a <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Actividades de mantenimiento (FISICO, LOGICO, RED) filtradas por tipo de equipo';

-- ---------------------------------------------------------------------
-- 12. Actas de mantenimiento
-- ---------------------------------------------------------------------
CREATE TABLE mantenimientos (
    id                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    numero              VARCHAR(30)       NULL COMMENT 'N° 001-2026/OTIC-MANT; se asigna al cerrar',
    anio                SMALLINT UNSIGNED NULL,
    correlativo         INT UNSIGNED      NULL,
    equipo_id           INT UNSIGNED      NOT NULL,
    personal_id         INT UNSIGNED      NULL     COMMENT 'Snapshot del responsable al momento del servicio',
    oficina_id          INT UNSIGNED      NOT NULL COMMENT 'Snapshot de la oficina al momento del servicio',
    tecnico_id          INT UNSIGNED      NOT NULL,
    tipo                ENUM('PREVENTIVO','CORRECTIVO') NOT NULL DEFAULT 'PREVENTIVO',
    estado              ENUM('BORRADOR','CERRADA') NOT NULL DEFAULT 'BORRADOR',
    fecha_ingreso       DATETIME          NOT NULL,
    fecha_salida        DATETIME          NULL,
    problema_reportado  TEXT              NULL,
    observaciones       TEXT              NULL,
    recomendaciones     TEXT              NULL,
    estado_equipo_previo ENUM('OPERATIVO','EN_MANTENIMIENTO','INOPERATIVO','DE_BAJA') NOT NULL DEFAULT 'OPERATIVO'
                        COMMENT 'Estado del equipo antes de abrir el acta',
    estado_equipo_final ENUM('OPERATIVO','EN_MANTENIMIENTO','INOPERATIVO','DE_BAJA') NULL
                        COMMENT 'Estado elegido por el técnico al cerrar',
    condicion_final     ENUM('BUENO','REGULAR','MALO') NULL,
    pdf_ruta            VARCHAR(255)      NULL COMMENT 'Ruta relativa en storage/reports',
    pdf_firmado_ruta    VARCHAR(255)      NULL COMMENT 'PDF firmado digitalmente (ruta relativa en storage/reports)',
    firmado_por         INT UNSIGNED      NULL COMMENT 'Usuario del sistema que subió el PDF firmado',
    firmado_at          DATETIME          NULL,
    firma_titular       VARCHAR(200)      NULL COMMENT 'Titular del certificado (CN)',
    firma_dni           CHAR(8)           NULL COMMENT 'DNI del certificado de firma',
    firma_sha256        CHAR(64)          NULL COMMENT 'Huella SHA-256 del PDF firmado',
    estado_envio        ENUM('NO_ENVIADO','ENVIADO','RECIBIDO','ERROR') NOT NULL DEFAULT 'NO_ENVIADO'
                        COMMENT 'Estado del último envío por correo',
    cerrado_por        INT UNSIGNED      NULL,
    cerrado_at          DATETIME          NULL,
    created_by          INT UNSIGNED      NOT NULL,
    borrador_equipo     INT UNSIGNED      AS (IF(estado = 'BORRADOR', equipo_id, NULL)) PERSISTENT
                        COMMENT 'Generada: un solo borrador abierto por equipo',
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_mantenimientos_numero UNIQUE (numero),
    CONSTRAINT uq_mantenimientos_anio_correlativo UNIQUE (anio, correlativo),
    CONSTRAINT uq_mantenimientos_borrador_equipo UNIQUE (borrador_equipo),
    KEY idx_mantenimientos_equipo_estado_salida (equipo_id, estado, fecha_salida),
    KEY idx_mantenimientos_personal_id (personal_id),
    KEY idx_mantenimientos_oficina_id (oficina_id),
    KEY idx_mantenimientos_tecnico_id (tecnico_id),
    KEY idx_mantenimientos_estado_ingreso (estado, fecha_ingreso),
    KEY idx_mantenimientos_tipo (tipo),
    KEY idx_mantenimientos_cerrado_por (cerrado_por),
    KEY idx_mantenimientos_created_by (created_by),
    KEY idx_mantenimientos_firmado_por (firmado_por),
    KEY idx_mantenimientos_estado_envio (estado_envio),
    CONSTRAINT fk_mantenimientos_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_mantenimientos_personal_id FOREIGN KEY (personal_id)
        REFERENCES personal (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimientos_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimientos_tecnico_id FOREIGN KEY (tecnico_id)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimientos_cerrado_por FOREIGN KEY (cerrado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimientos_created_by FOREIGN KEY (created_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimientos_firmado_por FOREIGN KEY (firmado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_mantenimientos_firma CHECK (
        pdf_firmado_ruta IS NULL OR (estado = 'CERRADA' AND firmado_at IS NOT NULL AND firma_sha256 IS NOT NULL)
    ),
    CONSTRAINT chk_mantenimientos_envio CHECK (estado_envio = 'NO_ENVIADO' OR estado = 'CERRADA'),
    CONSTRAINT chk_mantenimientos_fechas CHECK (fecha_salida IS NULL OR fecha_salida >= fecha_ingreso),
    CONSTRAINT chk_mantenimientos_cerrada CHECK (
        estado = 'BORRADOR'
        OR (numero IS NOT NULL AND anio IS NOT NULL AND correlativo IS NOT NULL
            AND fecha_salida IS NOT NULL AND estado_equipo_final IS NOT NULL AND cerrado_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Actas de mantenimiento preventivo/correctivo (BORRADOR -> CERRADA)';

CREATE TABLE mantenimiento_checklist (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id  INT UNSIGNED NOT NULL,
    checklist_item_id INT UNSIGNED NOT NULL,
    realizado         TINYINT(1)   NOT NULL DEFAULT 0,
    observacion       VARCHAR(255) NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_mantenimiento_checklist_mant_item UNIQUE (mantenimiento_id, checklist_item_id),
    KEY idx_mantenimiento_checklist_item_id (checklist_item_id),
    CONSTRAINT fk_mantenimiento_checklist_mantenimiento_id FOREIGN KEY (mantenimiento_id)
        REFERENCES mantenimientos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimiento_checklist_checklist_item_id FOREIGN KEY (checklist_item_id)
        REFERENCES checklist_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_mantenimiento_checklist_realizado CHECK (realizado IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Actividades de checklist marcadas en cada acta';

CREATE TABLE mantenimiento_componentes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id INT UNSIGNED NOT NULL,
    componente       ENUM('HDD','SSD','RAM','FUENTE','PROCESADOR') NOT NULL,
    accion           ENUM('REEMPLAZO','INSTALACION','NO_APLICA') NOT NULL DEFAULT 'NO_APLICA',
    detalle          VARCHAR(150) NULL COMMENT 'Serie o capacidad nueva',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_mantenimiento_componentes_mant_comp UNIQUE (mantenimiento_id, componente),
    CONSTRAINT fk_mantenimiento_componentes_mantenimiento_id FOREIGN KEY (mantenimiento_id)
        REFERENCES mantenimientos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Componentes reemplazados o instalados en cada acta';

CREATE TABLE mantenimiento_software (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id INT UNSIGNED NOT NULL,
    software_id      INT UNSIGNED NOT NULL,
    version          VARCHAR(40)  NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_mantenimiento_software_mant_soft UNIQUE (mantenimiento_id, software_id),
    KEY idx_mantenimiento_software_software_id (software_id),
    CONSTRAINT fk_mantenimiento_software_mantenimiento_id FOREIGN KEY (mantenimiento_id)
        REFERENCES mantenimientos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mantenimiento_software_software_id FOREIGN KEY (software_id)
        REFERENCES catalogo_software (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Software instalado en cada acta';

-- Envíos de actas por correo (migración 002). Solo se guarda el hash del token del enlace.
CREATE TABLE mantenimiento_envios (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id    INT UNSIGNED NOT NULL,
    destinatario        VARCHAR(150) NOT NULL,
    copia               VARCHAR(150) NULL COMMENT 'Copia (CC) a la Jefa de la OTIC',
    token_hash         CHAR(64)     NOT NULL COMMENT 'SHA-256 del token del enlace; el token nunca se guarda',
    estado              ENUM('ENVIADO','RECIBIDO','ERROR') NOT NULL,
    adjunto_firmado     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = se adjuntó el PDF firmado',
    error               VARCHAR(500) NULL,
    enviado_por         INT UNSIGNED NOT NULL,
    enviado_at          DATETIME     NOT NULL,
    expira_at           DATETIME     NOT NULL,
    visto_at            DATETIME     NULL COMMENT 'Primera apertura del enlace (informativo)',
    recibido_at         DATETIME     NULL COMMENT 'Confirmación de recepción por el usuario',
    recibido_ip         VARCHAR(45)  NULL,
    recibido_user_agent VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_mantenimiento_envios_token UNIQUE (token_hash),
    KEY idx_mantenimiento_envios_acta (mantenimiento_id, id),
    KEY idx_mantenimiento_envios_enviado_por (enviado_por),
    CONSTRAINT fk_mantenimiento_envios_mantenimiento_id FOREIGN KEY (mantenimiento_id)
        REFERENCES mantenimientos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_mantenimiento_envios_enviado_por FOREIGN KEY (enviado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_mantenimiento_envios_adjunto CHECK (adjunto_firmado IN (0, 1)),
    CONSTRAINT chk_mantenimiento_envios_recibido CHECK (estado <> 'RECIBIDO' OR recibido_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Envíos de actas por correo y confirmación de recepción';

-- ---------------------------------------------------------------------
-- 13. Informes técnicos
-- ---------------------------------------------------------------------
CREATE TABLE informes_tecnicos (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    numero           VARCHAR(30)       NULL COMMENT 'N° 001-2026/OTIC; se asigna al emitir',
    anio             SMALLINT UNSIGNED NULL,
    correlativo      INT UNSIGNED      NULL,
    para_nombre      VARCHAR(150)      NOT NULL,
    para_cargo       VARCHAR(150)      NOT NULL,
    de_usuario_id    INT UNSIGNED      NOT NULL,
    de_nombre        VARCHAR(150)      NOT NULL COMMENT 'Snapshot del nombre del técnico',
    de_cargo         VARCHAR(150)      NULL     COMMENT 'Snapshot del cargo del técnico',
    asunto           VARCHAR(255)      NOT NULL,
    fecha            DATE              NOT NULL,
    antecedentes     TEXT              NULL,
    accion_requerida ENUM('REPARACION','INOPERATIVIDAD','BAJA_DEFINITIVA','REEMPLAZO') NOT NULL,
    conclusiones     TEXT              NULL,
    recomendaciones  TEXT              NULL,
    estado           ENUM('BORRADOR','EMITIDO') NOT NULL DEFAULT 'BORRADOR',
    emitido_por      INT UNSIGNED      NULL,
    emitido_at       DATETIME          NULL,
    pdf_ruta         VARCHAR(255)      NULL,
    docx_ruta        VARCHAR(255)      NULL,
    created_by       INT UNSIGNED      NOT NULL,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_informes_tecnicos_numero UNIQUE (numero),
    CONSTRAINT uq_informes_tecnicos_anio_correlativo UNIQUE (anio, correlativo),
    KEY idx_informes_tecnicos_estado_fecha (estado, fecha),
    KEY idx_informes_tecnicos_accion (accion_requerida),
    KEY idx_informes_tecnicos_de_usuario_id (de_usuario_id),
    KEY idx_informes_tecnicos_emitido_por (emitido_por),
    KEY idx_informes_tecnicos_created_by (created_by),
    CONSTRAINT fk_informes_tecnicos_de_usuario_id FOREIGN KEY (de_usuario_id)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_informes_tecnicos_emitido_por FOREIGN KEY (emitido_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_informes_tecnicos_created_by FOREIGN KEY (created_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_informes_tecnicos_emitido CHECK (
        estado = 'BORRADOR'
        OR (numero IS NOT NULL AND anio IS NOT NULL AND correlativo IS NOT NULL AND emitido_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Informes técnicos de evaluación, inoperatividad y baja (BORRADOR -> EMITIDO)';

CREATE TABLE informe_equipos (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    informe_id       INT UNSIGNED      NOT NULL,
    equipo_id        INT UNSIGNED      NOT NULL,
    caracteristicas  TEXT              NULL,
    estado_funcional TEXT              NULL,
    diagnostico      TEXT              NULL,
    orden            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_informe_equipos_informe_equipo UNIQUE (informe_id, equipo_id),
    KEY idx_informe_equipos_equipo_id (equipo_id),
    CONSTRAINT fk_informe_equipos_informe_id FOREIGN KEY (informe_id)
        REFERENCES informes_tecnicos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_informe_equipos_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Equipos evaluados en cada informe técnico';

CREATE TABLE informe_evidencias (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    informe_id      INT UNSIGNED      NOT NULL,
    equipo_id       INT UNSIGNED      NULL COMMENT 'Equipo al que corresponde la foto (opcional)',
    archivo         VARCHAR(100)      NOT NULL COMMENT 'Nombre aleatorio dentro de storage/evidencias',
    nombre_original VARCHAR(255)      NOT NULL,
    mime            VARCHAR(20)       NOT NULL,
    tamanio_bytes   INT UNSIGNED      NOT NULL,
    descripcion     VARCHAR(255)      NULL,
    orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    subido_por      INT UNSIGNED      NOT NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_informe_evidencias_archivo UNIQUE (archivo),
    KEY idx_informe_evidencias_informe_orden (informe_id, orden),
    KEY idx_informe_evidencias_equipo_id (equipo_id),
    KEY idx_informe_evidencias_subido_por (subido_por),
    CONSTRAINT fk_informe_evidencias_informe_id FOREIGN KEY (informe_id)
        REFERENCES informes_tecnicos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_informe_evidencias_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_informe_evidencias_subido_por FOREIGN KEY (subido_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_informe_evidencias_mime CHECK (mime IN ('image/jpeg', 'image/png')),
    CONSTRAINT chk_informe_evidencias_tamanio CHECK (tamanio_bytes BETWEEN 1 AND 5242880)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Evidencias fotográficas (JPG/PNG, máx. 5 MB) de los informes técnicos';

-- ---------------------------------------------------------------------
-- 14. Correlativos anuales
-- ---------------------------------------------------------------------
CREATE TABLE correlativos (
    tipo       ENUM('MANTENIMIENTO','INFORME') NOT NULL,
    anio       SMALLINT UNSIGNED NOT NULL,
    ultimo     INT UNSIGNED      NOT NULL DEFAULT 0,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tipo, anio),
    CONSTRAINT chk_correlativos_anio CHECK (anio BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Último número emitido por tipo de documento y año (se bloquea con FOR UPDATE)';

-- ---------------------------------------------------------------------
-- 15. Parámetros del sistema
-- ---------------------------------------------------------------------
CREATE TABLE parametros (
    clave       VARCHAR(60)  NOT NULL,
    valor       TEXT         NOT NULL,
    tipo        ENUM('TEXTO','ENTERO','DECIMAL','BOOLEANO','FECHA') NOT NULL DEFAULT 'TEXTO',
    descripcion VARCHAR(255) NULL,
    editable    TINYINT(1)   NOT NULL DEFAULT 1,
    updated_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clave),
    KEY idx_parametros_updated_by (updated_by),
    CONSTRAINT fk_parametros_updated_by FOREIGN KEY (updated_by)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_parametros_editable CHECK (editable IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parámetros configurables del sistema (clave/valor)';

-- ---------------------------------------------------------------------
-- 16. Auditoría
-- ---------------------------------------------------------------------
CREATE TABLE auditoria (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id     INT UNSIGNED    NULL COMMENT 'NULL en intentos de login con usuario inexistente',
    usuario_login  VARCHAR(50)     NULL COMMENT 'Nombre de usuario usado (snapshot)',
    accion         ENUM('CREAR','EDITAR','ELIMINAR','LOGIN','LOGIN_FALLIDO','LOGOUT',
                        'ASIGNAR','ASIGNAR_SLOT','LIBERAR_SLOT','CERRAR','EMITIR','BAJA',
                        'VER_CONTRASENA','FIRMAR','ENVIAR','CONFIRMAR_RECEPCION') NOT NULL,
    tabla          VARCHAR(64)     NULL,
    registro_id    VARCHAR(64)     NULL COMMENT 'PK afectada (texto para admitir claves no numéricas)',
    datos_antes    LONGTEXT        NULL,
    datos_despues  LONGTEXT        NULL,
    ip             VARCHAR(45)     NULL,
    user_agent     VARCHAR(255)    NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_usuario_fecha (usuario_id, created_at),
    KEY idx_auditoria_accion_fecha (accion, created_at),
    KEY idx_auditoria_tabla_registro (tabla, registro_id),
    KEY idx_auditoria_created_at (created_at),
    CONSTRAINT fk_auditoria_usuario_id FOREIGN KEY (usuario_id)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_auditoria_datos_antes   CHECK (datos_antes   IS NULL OR JSON_VALID(datos_antes)),
    CONSTRAINT chk_auditoria_datos_despues CHECK (datos_despues IS NULL OR JSON_VALID(datos_despues))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de auditoría de acciones de los usuarios del sistema';

-- ---------------------------------------------------------------------
-- 17. Vista de estado calculado de equipos
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_equipos_estado AS
SELECT
    b.*,
    DATE_ADD(COALESCE(DATE(b.fecha_ultimo_mantenimiento), b.fecha_adquisicion),
             INTERVAL b.periodicidad_efectiva_meses MONTH) AS fecha_proximo_mantenimiento,
    CASE
        WHEN COALESCE(b.fecha_ultimo_mantenimiento, b.fecha_adquisicion) IS NULL THEN NULL
        ELSE DATEDIFF(CURDATE(),
                      DATE_ADD(COALESCE(DATE(b.fecha_ultimo_mantenimiento), b.fecha_adquisicion),
                               INTERVAL b.periodicidad_efectiva_meses MONTH))
    END AS dias_retraso_mantenimiento
FROM (
    SELECT
        e.id,
        e.tipo,
        e.marca,
        e.modelo,
        e.nro_serie,
        e.codigo_patrimonial,
        e.codigo_interno,
        e.procesador,
        e.ram_gb,
        e.disco_tipo,
        e.disco_capacidad_gb,
        e.sistema_operativo,
        e.hostname,
        e.mac_lan,
        e.ip_lan,
        e.mac_wifi,
        e.oficina_id,
        o.nombre                                        AS oficina_nombre,
        o.siglas                                        AS oficina_siglas,
        e.personal_id,
        CASE WHEN p.id IS NULL THEN NULL
             ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre,
        p.cargo                                         AS personal_cargo,
        p.dni                                           AS personal_dni,
        IF(e.personal_id IS NOT NULL, 1, 0)             AS en_uso,
        e.estado_operativo,
        e.condicion_fisica,
        e.recomendado_baja,
        e.fecha_baja,
        e.fecha_adquisicion,
        e.vida_util_meses,
        e.valor_adquisicion,
        e.orden_compra,
        e.proveedor,
        e.garantia_hasta,
        e.observaciones,
        e.created_at,
        e.updated_at,
        (SELECT ec.codigo FROM equipo_codigos ec
          WHERE ec.equipo_id = e.id AND ec.anio = YEAR(CURDATE()))              AS codigo_inventario_anio,
        DATE_ADD(e.fecha_adquisicion, INTERVAL e.vida_util_meses MONTH)         AS fecha_sugerida_baja,
        TIMESTAMPDIFF(YEAR, e.fecha_adquisicion, CURDATE())                     AS anios_antiguedad,
        um.fecha_ultimo_mantenimiento,
        COALESCE(e.periodicidad_mant_meses,
                 (SELECT CAST(pa.valor AS UNSIGNED) FROM parametros pa
                   WHERE pa.clave = 'periodicidad_mant_meses'),
                 6)                                                             AS periodicidad_efectiva_meses,
        le.licencia_id,
        le.slot                                                                 AS licencia_slot
    FROM equipos e
    INNER JOIN oficinas o ON o.id = e.oficina_id
    LEFT JOIN personal p ON p.id = e.personal_id
    LEFT JOIN (
        SELECT m.equipo_id, MAX(m.fecha_salida) AS fecha_ultimo_mantenimiento
          FROM mantenimientos m
         WHERE m.estado = 'CERRADA'
         GROUP BY m.equipo_id
    ) um ON um.equipo_id = e.id
    LEFT JOIN licencia_equipos le ON le.equipo_activo = e.id
) b;

SET FOREIGN_KEY_CHECKS = 1;
