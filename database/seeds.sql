-- =====================================================================
-- SIGPAT-OTIC (CAEN-EPG) - Datos iniciales
-- Importar DESPUÉS de schema.sql, sobre una base de datos recién creada.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';

START TRANSACTION;

-- ---------------------------------------------------------------------
-- Administrador inicial
--   usuario:    admin
--   contraseña: Cambiar123!
-- IMPORTANTE: contraseña temporal. debe_cambiar_password = 1 obliga a
-- cambiarla en el primer inicio de sesión. Cámbiala antes de producción.
-- ---------------------------------------------------------------------
INSERT INTO usuarios_sistema
    (usuario, password_hash, nombres, apellidos, cargo, email, rol, activo, debe_cambiar_password)
VALUES
    ('admin',
     '$2y$12$4i5I89d25nUXyw//w.zKEOsRVgz9BNsStbOo/CN19.1i5kwIIA4/W',
     'Administrador', 'del Sistema', 'Administrador SIGPAT-OTIC', NULL, 'ADMINISTRADOR', 1, 1);

-- ---------------------------------------------------------------------
-- Oficinas de ejemplo (jerarquía)
--   Dirección Académica
--     ├── Dpto. Diplomados
--     └── Dpto. Evaluación
--   OTIC
-- ---------------------------------------------------------------------
INSERT INTO oficinas (padre_id, nombre, siglas, tipo)
VALUES (NULL, 'Dirección Académica', 'DA', 'DIRECCION');
SET @id_direccion_academica = LAST_INSERT_ID();

INSERT INTO oficinas (padre_id, nombre, siglas, tipo) VALUES
    (@id_direccion_academica, 'Dpto. Diplomados', 'DD', 'DEPARTAMENTO'),
    (@id_direccion_academica, 'Dpto. Evaluación', 'DE', 'DEPARTAMENTO');

INSERT INTO oficinas (padre_id, nombre, siglas, tipo)
VALUES (NULL, 'Oficina de Tecnologías de la Información y Comunicación', 'OTIC', 'OFICINA');

-- ---------------------------------------------------------------------
-- Catálogo de software
-- ---------------------------------------------------------------------
INSERT INTO catalogo_software (nombre, orden) VALUES
    ('Sophos Antivirus',   10),
    ('Microsoft Office',   20),
    ('Google Drive',       30),
    ('AnyDesk',            40),
    ('Zoom',               50),
    ('Adobe Reader',       60),
    ('Epson iProjection',  70),
    ('Xerox SmartStart',   80),
    ('SIGA',               90),
    ('SIAF',              100),
    ('ReFirma',           110),
    ('CheckPoint VPN',    120),
    ('Java',              130);

-- ---------------------------------------------------------------------
-- Ítems de checklist (categoría y tipos de equipo a los que aplican)
-- ---------------------------------------------------------------------
INSERT INTO checklist_items (codigo, categoria, descripcion, aplica_a, orden) VALUES
    -- FISICO
    (NULL,               'FISICO', 'Remoción de polvo con aire comprimido',            'PC,LAPTOP,IMPRESORA', 10),
    (NULL,               'FISICO', 'Limpieza con brocha y alcohol isopropílico',       'PC,LAPTOP,IMPRESORA', 20),
    ('PASTA_TERMICA',    'FISICO', 'Cambio de pasta térmica',                          'PC,LAPTOP',           30),
    (NULL,               'FISICO', 'Aplicación de limpiacontactos en slots y conectores', 'PC,LAPTOP,IMPRESORA', 40),
    (NULL,               'FISICO', 'Revisión y ajuste de conexiones internas',         'PC,LAPTOP,IMPRESORA', 50),
    -- LOGICO
    ('FORMATEO_SO',      'LOGICO', 'Formateo y reinstalación de SO',                   'PC,LAPTOP',           10),
    (NULL,               'LOGICO', 'Respaldo de información',                          'PC,LAPTOP',           20),
    (NULL,               'LOGICO', 'Limpieza de temporales y caché',                   'PC,LAPTOP',           30),
    (NULL,               'LOGICO', 'Análisis y eliminación de virus',                  'PC,LAPTOP',           40),
    (NULL,               'LOGICO', 'Configuración y optimización del inicio',          'PC,LAPTOP',           50),
    (NULL,               'LOGICO', 'Instalación/actualización de drivers',             'PC,LAPTOP',           60),
    -- RED
    ('DOMINIO',          'RED',    'Incorporación al dominio CAEN-EPG',                'PC,LAPTOP',           10),
    ('HOSTNAME',         'RED',    'Asignación de hostname según inventario',          'PC,LAPTOP,IMPRESORA', 20),
    (NULL,               'RED',    'Configuración de impresora de red',                'PC,LAPTOP,IMPRESORA', 30),
    (NULL,               'RED',    'Configuración Wi-Fi',                              'PC,LAPTOP,IMPRESORA', 40);

-- ---------------------------------------------------------------------
-- Parámetros
-- ---------------------------------------------------------------------
INSERT INTO parametros (clave, valor, tipo, descripcion) VALUES
    ('periodicidad_mant_meses', '6', 'ENTERO',
     'Meses entre mantenimientos preventivos (valor por defecto si el equipo no define el suyo)'),
    ('vida_util_meses_default', '48', 'ENTERO',
     'Vida útil sugerida en meses para equipos nuevos'),
    ('jefe_otic_nombre', 'Registrar nombre del Jefe de la OTIC', 'TEXTO',
     'Nombre del Jefe de la OTIC (destinatario por defecto de los informes técnicos)'),
    ('jefe_otic_cargo', 'Jefe de la Oficina de Tecnologías de la Información y Comunicación', 'TEXTO',
     'Cargo del Jefe de la OTIC'),
    ('institucion_nombre', 'Centro de Altos Estudios Nacionales - Escuela de Posgrado (CAEN-EPG)', 'TEXTO',
     'Nombre de la institución para encabezados de documentos'),
    ('jefe_otic_email', '', 'TEXTO',
     'Correo de la Jefa de la OTIC: recibe copia de cada acta enviada por correo (vacío = sin copia)'),
    ('actas_envio_requiere_firma', '0', 'BOOLEANO',
     '1 = un acta solo puede enviarse después de subir el PDF firmado con ReFirma; 0 = se envía sin firma digital'),
    ('actas_enlace_dias_vigencia', '30', 'ENTERO',
     'Días de vigencia del enlace para confirmar la recepción de un acta enviada por correo');

-- ---------------------------------------------------------------------
-- Correlativos del año en curso
-- ---------------------------------------------------------------------
INSERT INTO correlativos (tipo, anio, ultimo) VALUES
    ('MANTENIMIENTO', YEAR(CURDATE()), 0),
    ('INFORME',       YEAR(CURDATE()), 0);

COMMIT;
