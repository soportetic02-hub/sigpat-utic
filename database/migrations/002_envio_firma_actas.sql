-- =====================================================================
-- SIGPAT-OTIC - Migración 002: firma digital y envío de actas por correo
-- Compatible con MariaDB 10.4+ (XAMPP y hosting cPanel).
--
-- 1. usuarios_sistema.puede_firmar: usuario autorizado a firmar actas
--    (Jefe de la OTIC) con su DNIe (ReFirma PDF / Firma Perú).
-- 2. mantenimientos: datos del PDF firmado digitalmente y estado del envío
--    (NO_ENVIADO -> ENVIADO -> RECIBIDO, o ERROR).
-- 3. mantenimiento_envios: cada envío por correo, con su enlace de
--    confirmación (solo se guarda el hash SHA-256 del token).
-- 4. Parámetros del envío y nuevas acciones de auditoría.
--
-- Importe este archivo UNA sola vez en phpMyAdmin con la base seleccionada.
-- No usa CREATE DATABASE ni USE. Conserva los datos existentes.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';

-- ---------------------------------------------------------------------
-- 1. Usuarios que pueden firmar actas
-- ---------------------------------------------------------------------
ALTER TABLE usuarios_sistema
    ADD COLUMN puede_firmar TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = puede firmar actas con su DNIe (Jefe de la OTIC)' AFTER rol,
    ADD CONSTRAINT chk_usuarios_sistema_puede_firmar CHECK (puede_firmar IN (0, 1));

-- ---------------------------------------------------------------------
-- 2. Firma y estado de envío del acta
-- ---------------------------------------------------------------------
ALTER TABLE mantenimientos
    ADD COLUMN pdf_firmado_ruta VARCHAR(255) NULL COMMENT 'PDF firmado digitalmente (ruta relativa en storage/reports)' AFTER pdf_ruta,
    ADD COLUMN firmado_por      INT UNSIGNED NULL COMMENT 'Usuario del sistema que subió el PDF firmado' AFTER pdf_firmado_ruta,
    ADD COLUMN firmado_at       DATETIME     NULL AFTER firmado_por,
    ADD COLUMN firma_titular    VARCHAR(200) NULL COMMENT 'Titular del certificado (CN)' AFTER firmado_at,
    ADD COLUMN firma_dni        CHAR(8)      NULL COMMENT 'DNI del certificado de firma' AFTER firma_titular,
    ADD COLUMN firma_sha256     CHAR(64)     NULL COMMENT 'Huella SHA-256 del PDF firmado' AFTER firma_dni,
    ADD COLUMN estado_envio     ENUM('NO_ENVIADO','ENVIADO','RECIBIDO','ERROR') NOT NULL DEFAULT 'NO_ENVIADO'
        COMMENT 'Estado del último envío por correo' AFTER firma_sha256,
    ADD KEY idx_mantenimientos_firmado_por (firmado_por),
    ADD KEY idx_mantenimientos_estado_envio (estado_envio),
    ADD CONSTRAINT fk_mantenimientos_firmado_por FOREIGN KEY (firmado_por)
        REFERENCES usuarios_sistema (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT chk_mantenimientos_firma CHECK (
        pdf_firmado_ruta IS NULL OR (estado = 'CERRADA' AND firmado_at IS NOT NULL AND firma_sha256 IS NOT NULL)
    ),
    ADD CONSTRAINT chk_mantenimientos_envio CHECK (estado_envio = 'NO_ENVIADO' OR estado = 'CERRADA');

-- ---------------------------------------------------------------------
-- 3. Envíos por correo
-- ---------------------------------------------------------------------
CREATE TABLE mantenimiento_envios (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id    INT UNSIGNED NOT NULL,
    destinatario        VARCHAR(150) NOT NULL,
    token_hash          CHAR(64)     NOT NULL COMMENT 'SHA-256 del token del enlace; el token nunca se guarda',
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
-- 4. Parámetros y auditoría
-- ---------------------------------------------------------------------
INSERT IGNORE INTO parametros (clave, valor, tipo, descripcion) VALUES
    ('actas_envio_requiere_firma', '1', 'BOOLEANO',
     '1 = un acta solo puede enviarse por correo después de ser firmada digitalmente por el Jefe de la OTIC'),
    ('actas_enlace_dias_vigencia', '30', 'ENTERO',
     'Días de vigencia del enlace para confirmar la recepción de un acta enviada por correo');

ALTER TABLE auditoria
    MODIFY accion ENUM('CREAR','EDITAR','ELIMINAR','LOGIN','LOGIN_FALLIDO','LOGOUT',
                       'ASIGNAR','ASIGNAR_SLOT','LIBERAR_SLOT','CERRAR','EMITIR','BAJA',
                       'VER_CONTRASENA','FIRMAR','ENVIAR','CONFIRMAR_RECEPCION') NOT NULL;
