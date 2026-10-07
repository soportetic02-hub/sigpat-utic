-- =====================================================================
-- SIGPAT-OTIC - Migración 001: licencias Office -> cuentas Microsoft 365
-- Compatible con MariaDB 10.4+ (XAMPP y hosting cPanel).
--
-- Convierte licencias_office (claves de producto) en cuentas Microsoft 365
-- y amplía licencia_equipos (instalaciones) para admitir equipos, personas y
-- oficinas que no están registrados en el sistema.
--
-- Importe este archivo UNA sola vez en phpMyAdmin con la base seleccionada.
-- Conserva los datos existentes: cada licencia antigua pasa a ser una cuenta
-- con código OFFICE-<id> y un correo provisional que debe corregirse.
-- No usa CREATE DATABASE ni USE.
--
-- Regla MariaDB (error #1901): una columna usada en un CHECK no puede tener
-- una FK con CASCADE o SET NULL; por eso equipo_id y liberado_por usan
-- ON UPDATE RESTRICT ON DELETE RESTRICT (los id son AUTO_INCREMENT y nunca cambian).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. licencias_office: cuentas Microsoft 365
-- ---------------------------------------------------------------------
ALTER TABLE licencias_office
    ADD COLUMN codigo             VARCHAR(20)  NULL AFTER id,
    ADD COLUMN correo             VARCHAR(150) NULL AFTER codigo,
    ADD COLUMN plan               VARCHAR(80)  NOT NULL DEFAULT 'Microsoft 365 Empresa Estándar' AFTER correo,
    ADD COLUMN contrasena_cifrada TEXT         NULL COMMENT 'AES-256-GCM con APP_KEY; nunca en texto plano' AFTER plan,
    ADD COLUMN fecha_alta         DATE         NULL AFTER contrasena_cifrada,
    ADD COLUMN fecha_vencimiento  DATE         NULL AFTER fecha_alta,
    ADD COLUMN estado             ENUM('ACTIVA','SUSPENDIDA','VENCIDA','BAJA') NOT NULL DEFAULT 'ACTIVA' AFTER fecha_vencimiento,
    ADD COLUMN max_instalaciones  TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER estado;

-- Licencias antiguas: se conservan como cuentas pendientes de completar.
UPDATE licencias_office
   SET codigo = CONCAT('OFFICE-', id),
       correo = CONCAT('office-', id, '@pendiente.invalid'),
       fecha_alta = fecha_adquisicion,
       estado = IF(activo = 1, 'ACTIVA', 'BAJA'),
       observaciones = CONCAT_WS('\n',
           CONCAT('[Migrada desde clave de producto] Office ', version,
                  IFNULL(CONCAT(' ', edicion), ''), ' · ', tipo_licencia,
                  ' · clave terminada en ', RIGHT(REPLACE(clave, '-', ''), 5),
                  IFNULL(CONCAT(' · OC ', orden_compra), ''),
                  ' · complete el correo de la cuenta'),
           observaciones);

ALTER TABLE licencias_office
    DROP INDEX uq_licencias_office_clave,
    DROP INDEX idx_licencias_office_version,
    DROP COLUMN clave,
    DROP COLUMN version,
    DROP COLUMN edicion,
    DROP COLUMN tipo_licencia,
    DROP COLUMN orden_compra,
    DROP COLUMN fecha_adquisicion,
    MODIFY codigo VARCHAR(20)  NOT NULL COMMENT 'Identificador interno (p. ej. licencia01)',
    MODIFY correo VARCHAR(150) NOT NULL COMMENT 'Cuenta Microsoft 365 (nombre de usuario)',
    ADD CONSTRAINT uq_licencias_office_codigo UNIQUE (codigo),
    ADD CONSTRAINT uq_licencias_office_correo UNIQUE (correo),
    ADD KEY idx_licencias_office_estado (estado, activo),
    ADD CONSTRAINT chk_licencias_office_correo CHECK (correo REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$'),
    ADD CONSTRAINT chk_licencias_office_max CHECK (max_instalaciones BETWEEN 1 AND 5),
    ADD CONSTRAINT chk_licencias_office_fechas CHECK (fecha_vencimiento IS NULL OR fecha_alta IS NULL OR fecha_vencimiento >= fecha_alta),
    COMMENT = 'Cuentas Microsoft 365 (cada una permite instalar Office en hasta 5 dispositivos)';

-- ---------------------------------------------------------------------
-- 2. licencia_equipos: instalaciones (slots 1-5) con equipo, persona y oficina
-- ---------------------------------------------------------------------
ALTER TABLE licencia_equipos
    DROP FOREIGN KEY fk_licencia_equipos_equipo_id,
    DROP FOREIGN KEY fk_licencia_equipos_liberado_por;

ALTER TABLE licencia_equipos
    MODIFY equipo_id INT UNSIGNED NULL COMMENT 'Equipo del inventario; NULL si solo se conoce equipo_texto',
    ADD COLUMN equipo_texto        VARCHAR(100) NULL COMMENT 'Hostname o descripción de un equipo no inventariado (tablet, etc.)' AFTER equipo_id,
    ADD COLUMN personal_id         INT UNSIGNED NULL AFTER equipo_texto,
    ADD COLUMN usuario_texto       VARCHAR(150) NULL COMMENT 'Nombre de una persona no registrada en Personal' AFTER personal_id,
    ADD COLUMN oficina_id          INT UNSIGNED NULL AFTER usuario_texto,
    ADD COLUMN estado_verificacion ENUM('OK','POR_VERIFICAR') NOT NULL DEFAULT 'OK' AFTER oficina_id,
    ADD KEY idx_licencia_equipos_personal_id (personal_id),
    ADD KEY idx_licencia_equipos_oficina_id (oficina_id),
    ADD KEY idx_licencia_equipos_verificacion (estado_verificacion),
    ADD CONSTRAINT fk_licencia_equipos_equipo_id FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_licencia_equipos_personal_id FOREIGN KEY (personal_id)
        REFERENCES personal (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_licencia_equipos_oficina_id FOREIGN KEY (oficina_id)
        REFERENCES oficinas (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_licencia_equipos_liberado_por FOREIGN KEY (liberado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT chk_licencia_equipos_equipo CHECK (equipo_id IS NOT NULL OR (equipo_texto IS NOT NULL AND equipo_texto <> '')),
    COMMENT = 'Instalaciones (slots 1-5) de cada cuenta Microsoft 365, con historial de liberaciones';

-- Instalaciones existentes: toman la persona y la oficina actuales de su equipo.
UPDATE licencia_equipos le
    INNER JOIN equipos e ON e.id = le.equipo_id
   SET le.oficina_id = e.oficina_id,
       le.personal_id = e.personal_id;

-- ---------------------------------------------------------------------
-- 3. Auditoría: nueva acción para cada visualización de una contraseña
-- ---------------------------------------------------------------------
ALTER TABLE auditoria
    MODIFY accion ENUM('CREAR','EDITAR','ELIMINAR','LOGIN','LOGIN_FALLIDO','LOGOUT',
                       'ASIGNAR','ASIGNAR_SLOT','LIBERAR_SLOT','CERRAR','EMITIR','BAJA',
                       'VER_CONTRASENA') NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
