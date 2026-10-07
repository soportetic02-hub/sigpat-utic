-- =====================================================================
-- SIGPAT-OTIC - Migración 003: copia de las actas a la Jefa de la OTIC
-- Compatible con MariaDB 10.4+ (XAMPP y hosting cPanel).
--
-- 1. Parámetro jefe_otic_email: correo de la Jefa de la OTIC, que recibe
--    copia (CC) de cada acta enviada al usuario. Vacío = sin copia.
-- 2. La firma digital con ReFirma deja de ser obligatoria para enviar
--    (actas_envio_requiere_firma = 0; puede reactivarse en Parámetros).
-- 3. mantenimiento_envios.copia: dirección que recibió la copia en cada envío.
--
-- Importe este archivo UNA sola vez en phpMyAdmin con la base seleccionada.
-- No usa CREATE DATABASE ni USE. Conserva los datos existentes.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';

INSERT IGNORE INTO parametros (clave, valor, tipo, descripcion) VALUES
    ('jefe_otic_email', '', 'TEXTO',
     'Correo de la Jefa de la OTIC: recibe copia de cada acta enviada por correo (vacío = sin copia)');

UPDATE parametros
   SET valor = '0',
       descripcion = '1 = un acta solo puede enviarse después de subir el PDF firmado con ReFirma; 0 = se envía sin firma digital'
 WHERE clave = 'actas_envio_requiere_firma';

ALTER TABLE mantenimiento_envios
    ADD COLUMN copia VARCHAR(150) NULL COMMENT 'Copia (CC) a la Jefa de la OTIC' AFTER destinatario;
