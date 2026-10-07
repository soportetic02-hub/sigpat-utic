# SIGPAT-OTIC

**Sistema de Gestión Patrimonial, Licencias, Mantenimiento e Informes Técnicos**
Oficina de Tecnologías de la Información y Comunicación (OTIC) — Centro de Altos Estudios Nacionales, Escuela de Posgrado (CAEN-EPG)

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.4%2B-003545?logo=mariadb&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-PSR--4-885630?logo=composer&logoColor=white)
![Licencia](https://img.shields.io/badge/licencia-propietaria-lightgrey)

SIGPAT-OTIC es una aplicación web interna para que la OTIC administre en un solo lugar:

- el **inventario patrimonial** de equipos (PC, laptops e impresoras) y a quién está asignado cada uno;
- las **cuentas Microsoft 365** y sus 5 instalaciones de Office por cuenta;
- las **actas de mantenimiento** preventivo/correctivo en PDF, con **firma digital (DNIe)** y **envío por correo** con confirmación de recepción;
- los **informes técnicos** de evaluación, inoperatividad y baja, en PDF y Word;
- un **dashboard** con indicadores y alertas, y una **auditoría** completa de cada cambio.

---

## Tabla de contenidos

1. [Funcionalidades](#funcionalidades)
2. [Roles y permisos](#roles-y-permisos)
3. [Stack tecnológico](#stack-tecnológico)
4. [Arquitectura](#arquitectura)
5. [Estructura del proyecto](#estructura-del-proyecto)
6. [Requisitos](#requisitos)
7. [Instalación local (XAMPP)](#instalación-local-xampp)
8. [Configuración (`.env`)](#configuración-env)
9. [Base de datos y migraciones](#base-de-datos-y-migraciones)
10. [Herramientas de línea de comandos](#herramientas-de-línea-de-comandos)
11. [Despliegue en hosting cPanel](#despliegue-en-hosting-cpanel)
12. [Reglas de negocio principales](#reglas-de-negocio-principales)
13. [Seguridad](#seguridad)
14. [Generación de documentos (PDF y Word)](#generación-de-documentos-pdf-y-word)
15. [Solución de problemas](#solución-de-problemas)
16. [Convenciones para contribuir](#convenciones-para-contribuir)
17. [Licencia](#licencia)

---

## Funcionalidades

### Dashboard
- 4 KPIs: **equipos** (por tipo), **mantenimientos del año** (actas cerradas y equipos pendientes), **licencias Microsoft 365** y **equipos en riesgo**. Los equipos dados de baja no se cuentan.
- Gráfico de dona por tipo de equipo y barras apiladas de mantenimientos preventivos/correctivos por mes (Chart.js).
- Accesos rápidos para crear una nueva acta o un nuevo informe.
- Alertas de **mantenimientos pendientes** (equipos nunca mantenidos o con fecha de próximo mantenimiento vencida) y de **equipos recomendados para baja**.

### Oficinas y personal
- Oficinas **jerárquicas** (dirección → departamento → oficina), con vista de árbol y de lista.
- Registro del personal institucional (las personas que usan los equipos; no inician sesión).
- **Rotación de personal**: al cambiar a una persona de oficina, el sistema pregunta qué hacer con cada uno de sus equipos (trasladarlo con ella o dejarlo en la oficina original sin responsable).
- Ficha de cada persona con sus equipos e historial.

### Inventario de equipos
- Alta, edición y ficha detallada en pestañas: datos patrimoniales, hardware, red y asignación.
- **Códigos de inventario por año** (2024, 2025, 2026…) y **varios puntos de red** por equipo.
- Estado operativo (`OPERATIVO`, `EN_MANTENIMIENTO`, `INOPERATIVO`, `DE_BAJA`) y condición física (`BUENO`, `REGULAR`, `MALO`).
- Valores calculados en la vista SQL `v_equipos_estado`: antigüedad, fecha sugerida de baja, último y próximo mantenimiento.
- **Historial de asignaciones** (responsable y oficina) que se mantiene automáticamente.
- **Baja definitiva** (solo administrador), que libera automáticamente sus licencias de Office.
- Exportación a **CSV**.

### Licencias Microsoft 365
- Cuentas M365 (correo, plan, fechas, estado) con **contraseña cifrada con AES-256-GCM**; verla queda registrado en la auditoría.
- Vista de los **5 slots de instalación** de cada cuenta: asignar, liberar y reasignar por AJAX.
- Una instalación puede ser un equipo del inventario o un dispositivo libre (por ejemplo, una tablet), y una persona registrada o un nombre libre.
- La regla de 5 instalaciones se garantiza **en la base de datos y en el backend**, incluso con dos usuarios asignando el último slot a la vez.

### Actas de mantenimiento
- Flujo guiado: **persona → sus equipos → equipo**, con datos precargados.
- Checklist configurable (físico, lógico, red) filtrado por tipo de equipo, componentes reemplazados/instalados y software instalado (catálogo administrable).
- Estados **BORRADOR → CERRADA**: al abrir el acta el equipo pasa a `EN_MANTENIMIENTO`; al cerrarla se genera el **PDF A4** con el formato institucional.
- Numeración correlativa anual: `N° 001-2026/OTIC-MANT`.
- **Envío por correo** al correo que se indique, con el PDF adjunto y un enlace para **confirmar la recepción**: al confirmarla, el acta pasa a `RECIBIDO` (estados `NO_ENVIADO → ENVIADO → RECIBIDO`).
- **Copia a la Jefa de la OTIC**: cada envío va con copia (CC) al correo configurado en Parámetros (`jefe_otic_email`).
- **Firma digital opcional**: si se activa `actas_envio_requiere_firma`, un usuario autorizado descarga el acta, la firma con **ReFirma PDF (DNIe)** y la sube antes del envío; el sistema verifica la firma, el DNI y que el PDF sea el del acta.
- **Eliminar actas** (solo administrador): borradores y actas cerradas cuya recepción aún no fue confirmada. Se borran el acta, sus envíos y su PDF; queda en la auditoría y el número no se reutiliza.

### Informes técnicos
- Un informe puede evaluar **varios equipos**, cada uno con características, estado funcional y diagnóstico.
- Acción requerida: `REPARACION`, `INOPERATIVIDAD`, `BAJA_DEFINITIVA`, `REEMPLAZO`.
- **Evidencias fotográficas** (JPG/PNG, máx. 5 MB): se valida el tipo real, se re-codifican sin metadatos y se sirven solo a usuarios autenticados.
- Estados **BORRADOR → EMITIDO**; vista previa del borrador con marca de agua.
- Descarga en **PDF** y en **Word (.docx)**. Numeración anual: `N° 001-2026/OTIC`.

### Administración
- **Usuarios del sistema** (administradores y técnicos), con bloqueo por intentos fallidos y cambio obligatorio de contraseña.
- **Parámetros** (periodicidad de mantenimiento, vida útil, datos del Jefe de la OTIC, reglas de envío de actas) y **catálogos** (software y checklist) editables.
- **Auditoría** con visor del antes/después de cada cambio.

---

## Roles y permisos

| Acción | ADMINISTRADOR | TÉCNICO |
|---|:---:|:---:|
| Gestionar usuarios del sistema | ✅ | ❌ |
| Crear/editar oficinas y personal | ✅ | ✅ (sin desactivar) |
| Crear/editar equipos | ✅ | ✅ (sin eliminar) |
| Confirmar baja definitiva de un equipo | ✅ | ❌ |
| Crear/editar cuentas Microsoft 365 | ✅ | ❌ |
| Ver la contraseña de una cuenta M365 (queda auditado) | ✅ | ❌ |
| Asignar / liberar / reasignar instalaciones de Office | ✅ | ✅ |
| Registrar actas de mantenimiento | ✅ | ✅ |
| Eliminar actas no recibidas | ✅ | ❌ |
| Firmar actas con ReFirma (si está activado) | Solo usuarios con `puede_firmar = 1` | |
| Redactar y emitir informes técnicos | ✅ | ✅ |
| Ver auditoría, editar parámetros y catálogos | ✅ | ❌ |

Toda ruta privada pasa por `AuthMiddleware` y, si corresponde, por `RolMiddleware`. Entrar por URL a una pantalla sin permiso devuelve **403**.

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.2 con `declare(strict_types=1)` en todos los archivos |
| Base de datos | MariaDB 10.4+ (compatible con MySQL 8), InnoDB, `utf8mb4_unicode_ci` |
| Acceso a datos | PDO con sentencias preparadas reales (sin emulación) |
| Dependencias | Composer, autoload PSR-4 (`App\` → `app/`) |
| PDF | [dompdf/dompdf](https://github.com/dompdf/dompdf) ^3.0 |
| Word | [phpoffice/phpword](https://github.com/PHPOffice/PHPWord) ^1.2 |
| Correo | [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer) 6.9 (SMTP) |
| Firma digital | Verificación con OpenSSL (`openssl_cms_verify`) de PDFs firmados con ReFirma |
| Frontend | Bootstrap 5.3, Font Awesome 6, JavaScript vanilla, Chart.js 4 |
| Servidor | Apache con `mod_rewrite` |

Extensiones PHP requeridas: `pdo_mysql`, `mbstring`, `dom`, `gd`, `zip`, `fileinfo` y `openssl`.

---

## Arquitectura

MVC propio, sin framework, con una capa de servicios para la lógica de negocio:

```
Navegador ──► public/index.php (front controller)
                 │
                 ▼
              Router ──► Middleware (auth, rol, csrf)
                 │
                 ▼
            Controller ──► Service (reglas de negocio, transacciones, auditoría)
                 │              │
                 │              ▼
                 │           Model ──► Database (singleton PDO) ──► MariaDB
                 ▼
               View (layouts + partials, salida escapada con e())
```

- **Controllers**: solo orquestan HTTP (leer la petición, llamar al servicio, responder).
- **Services**: reglas de negocio, transacciones y auditoría. Lanzan `ValidationException` (campo → mensaje) que el controlador devuelve al formulario.
- **Models**: acceso a datos con lista blanca de columnas (`$fillable`); las vistas nunca consultan la base.
- **Database**: transacciones con anidamiento lógico (solo la más externa es real) y traducción de errores PDO a `DatabaseException` (`esDuplicado()`, `getConstraint()`…).

---

## Estructura del proyecto

```
sigpat-otic/
├── app/
│   ├── Config/        config.php, Database.php, routes.php, paleta.php
│   ├── Core/          Router, Controller, Model, View, Session, Csrf, Auth, Cifrado, Logger, helpers.php…
│   ├── Middleware/    AuthMiddleware, RolMiddleware, CsrfMiddleware
│   ├── Controllers/   Dashboard, Oficina, Personal, Equipo, Licencia, Mantenimiento, Informe, …
│   ├── Models/        un modelo por tabla + DashboardModel (consultas agregadas)
│   ├── Services/      Licencia, Mantenimiento, Informe, Correlativo, Asignacion, Pdf, Word,
│   │                  Correo, FirmaPdf, ActaEnvio, Auditoria, Evidencia, …
│   └── Views/
│       ├── layouts/   main.php (con menú lateral), auth.php (login y errores)
│       ├── partials/  navbar, sidebar, flash, pagination
│       ├── <módulo>/  una carpeta por módulo
│       ├── emails/    plantilla del correo del acta
│       └── pdf/       plantillas Dompdf: acta_mantenimiento.php, informe_tecnico.php
├── database/
│   ├── schema.sql     estructura completa (tablas, índices, restricciones y vista)
│   ├── seeds.sql      datos iniciales
│   ├── migrations/    actualizaciones para bases ya instaladas
│   └── *.php          herramientas CLI (importadores, APP_KEY, prueba de correo)
├── public/            ÚNICA carpeta expuesta por Apache
│   ├── index.php      front controller
│   ├── .htaccess
│   └── assets/        css/app.css, js/app.js, img/ (logos)
├── storage/           fuera de public/ (no se sirve directamente)
│   ├── evidencias/    fotos de los informes
│   ├── reports/       PDFs y DOCX generados (actas, actas_firmadas, informes, tmp)
│   ├── certificados/  (opcional, créala si la usas) certificados raíz PEM para validar la
│   │                  cadena de confianza de las firmas; si no existe, solo se verifica la integridad
│   └── logs/          app.log
├── .htaccess          redirige todo a public/ cuando el proyecto vive dentro de htdocs
├── .env.example
├── composer.json
└── composer.lock
```

---

## Requisitos

- PHP **8.2** o superior con las extensiones `pdo_mysql`, `mbstring`, `dom`, `gd`, `zip`, `fileinfo` y `openssl`.
- MariaDB **10.4+** (o MySQL 8).
- Apache con `mod_rewrite` (y opcionalmente `mod_headers`).
- [Composer](https://getcomposer.org/) 2.
- Para el envío de actas: una cuenta SMTP (por ejemplo, Google Workspace con contraseña de aplicación).

> **XAMPP en Windows:** las extensiones `gd` y `zip` vienen comentadas en `C:\xampp\php\php.ini`. Quita el `;` de `extension=gd` y `extension=zip` y reinicia Apache.

---

## Instalación local (XAMPP)

1. **Clonar el repositorio** dentro de `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone <url-del-repositorio> sigpat-otic
   cd sigpat-otic
   ```

2. **Instalar dependencias**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Crear el archivo de entorno**:
   ```bash
   copy .env.example .env
   ```
   Edita `.env` con los datos de tu base de datos (ver [Configuración](#configuración-env)).

4. **Generar la clave de cifrado** (`APP_KEY`):
   ```bash
   C:\xampp\php\php.exe database\generar_app_key.php --escribir
   ```

5. **Crear la base de datos** e importar estructura y datos iniciales:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE sigpat_otic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:\xampp\mysql\bin\mysql.exe -u root sigpat_otic < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root sigpat_otic < database\seeds.sql
   ```
   (También puedes hacerlo desde phpMyAdmin: crea la base, selecciónala e importa los dos archivos en ese orden.)

6. **Abrir el sistema** en `http://localhost/sigpat-otic/public` e ingresar con el administrador inicial:

   | Usuario | Contraseña temporal |
   |---|---|
   | `admin` | `Cambiar123!` |

   El sistema **obliga a cambiar la contraseña** en el primer inicio de sesión.

---

## Configuración (`.env`)

El archivo `.env` **nunca se versiona** (está en `.gitignore`). Variables disponibles:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_URL` | URL base **sin barra final** | `http://localhost/sigpat-otic/public` |
| `APP_ENV` | `local` muestra errores detallados; `production` solo los registra en `storage/logs/app.log` | `production` |
| `APP_KEY` | Clave AES-256-GCM para cifrar las contraseñas de las cuentas M365 | *(generada por `generar_app_key.php`)* |
| `DB_HOST` / `DB_PORT` | Servidor de base de datos | `127.0.0.1` / `3306` |
| `DB_NAME` / `DB_USER` / `DB_PASS` | Base de datos y credenciales | `sigpat_otic` / `root` / *(vacío)* |
| `MAIL_HOST` / `MAIL_PORT` | Servidor SMTP | `smtp.gmail.com` / `587` |
| `MAIL_ENCRYPTION` | `tls` (587), `ssl` (465) o `none` | `tls` |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | Cuenta remitente y **contraseña de aplicación** | `otic@caen.edu.pe` |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Remitente visible | `"OTIC - CAEN-EPG"` |
| `MAIL_REPLY_TO` | Dirección de respuesta (opcional) | |

> ⚠️ **`APP_KEY` es crítica.** Todos los servidores que compartan la misma base de datos deben usar **la misma clave**. Si se cambia o se pierde, las contraseñas de las cuentas Microsoft 365 guardadas **ya no se pueden descifrar**. Guárdala en un lugar seguro, fuera del repositorio.

> ℹ️ Los enlaces de confirmación de actas se construyen con `APP_URL`. Si vale `localhost`, no funcionarán fuera de tu PC.

### Correo con Google Workspace
1. En la cuenta remitente activa la **verificación en dos pasos**.
2. Crea una **contraseña de aplicación** en <https://myaccount.google.com/apppasswords>.
3. Ponla en `MAIL_PASSWORD` (16 caracteres, sin espacios). No uses la contraseña normal.
4. Prueba el envío con `php database/probar_correo.php destino@caen.edu.pe`.

### Logos
Los PDF incluyen logos opcionales si existen en `public/assets/img/`: `logo-caen.png` (o `.jpg`) y `logo-otic.png` (o `.jpg`).

---

## Base de datos y migraciones

- **Instalación nueva:** importa solo `schema.sql` y luego `seeds.sql`. El esquema ya incluye todos los cambios de las migraciones.
- **Base ya instalada de una versión anterior:** importa las migraciones pendientes **en orden y una sola vez**. Conservan los datos existentes.

| Migración | Contenido |
|---|---|
| `001_licencias_m365.sql` | Convierte las licencias (claves de producto) en cuentas Microsoft 365 con contraseña cifrada; amplía las instalaciones para admitir equipos y personas no registrados. |
| `002_envio_firma_actas.sql` | Firma digital de actas (`puede_firmar`), PDF firmado, estado de envío, tabla `mantenimiento_envios` y parámetros del envío. |
| `003_copia_jefa_actas.sql` | Parámetro `jefe_otic_email` (copia de cada acta a la Jefa de la OTIC), columna `mantenimiento_envios.copia` y firma con ReFirma opcional (`actas_envio_requiere_firma = 0`). |

Los archivos SQL **no contienen** `CREATE DATABASE` ni `USE`, para poder importarlos en cPanel. Las tablas principales son:

`usuarios_sistema`, `oficinas`, `personal`, `equipos`, `equipo_codigos`, `equipo_puntos_red`, `historial_asignaciones`, `licencias_office`, `licencia_equipos`, `catalogo_software`, `checklist_items`, `mantenimientos`, `mantenimiento_checklist`, `mantenimiento_componentes`, `mantenimiento_software`, `mantenimiento_envios`, `informes_tecnicos`, `informe_equipos`, `informe_evidencias`, `correlativos`, `parametros`, `auditoria`, y la vista `v_equipos_estado`.

> ⚠️ **Zona horaria:** la aplicación fija `SET time_zone = '-05:00'` (America/Lima) en cada conexión. Si ejecutas SQL a mano que use `NOW()` o `CURRENT_TIMESTAMP`, ejecuta antes `SET time_zone = '-05:00';`.

> ⚠️ **Nunca reimportes `schema.sql` ni `seeds.sql` sobre una base con datos reales:** recrea las tablas desde cero.

---

## Herramientas de línea de comandos

Todas se ejecutan desde la carpeta del proyecto (en Windows: `C:\xampp\php\php.exe database\...`).

| Comando | Qué hace |
|---|---|
| `php database/generar_app_key.php` | Muestra una `APP_KEY` nueva. Con `--escribir` la agrega al `.env` si aún no tiene una. |
| `php database/probar_correo.php destino@dominio` | Envía un correo de prueba con la configuración `MAIL_*` y muestra el error del servidor si falla. |
| `php database/importar_excel.php "INVENTARIO 2026.xlsx"` | Importa oficinas, personal y equipos desde la hoja de inventario. **Por defecto solo simula**; agrega `--ejecutar` para guardar. Opciones: `--usuario=admin`, `--forzar`. |
| `php database/generar_sql_equipos.php "INVENTARIO 2026.xlsx" [salida.sql]` | Genera un `.sql` con los equipos para importarlo en phpMyAdmin (hosting sin acceso a PHP por consola). |
| `php database/importar_licencias.php "Licencias Office 2026.xlsx"` | Importa las cuentas Microsoft 365 y sus instalaciones. Usa `--dry-run` para simular. Requiere `APP_KEY`. |

Los importadores usan los mismos servicios que la interfaz web (mismas validaciones, historial y auditoría) y trabajan en **una sola transacción**: se importa todo o nada. Dejan un reporte en `storage/logs/`.

---

## Despliegue en hosting cPanel

1. **Prepara las dependencias** en tu PC: `composer install --no-dev --optimize-autoloader`.
2. **Crea la base de datos** en *cPanel → Bases de datos MySQL* (quedará con el prefijo de tu cuenta, por ejemplo `micuenta_sigpat_otic`), crea un usuario y asígnale **todos los privilegios**.
3. En **phpMyAdmin**, selecciona la base e importa `database/schema.sql` y luego `database/seeds.sql`.
4. **Sube los archivos** (incluida la carpeta `vendor/`). Lo ideal es que el *document root* del dominio o subdominio apunte a `public/`. Si no es posible, el `.htaccess` de la raíz redirige todo a `public/` y bloquea el acceso a `app/`, `storage/`, `database/`, `vendor/` y `.env`.
5. **Crea el `.env`** en el servidor con `APP_ENV=production`, la URL pública en `APP_URL`, los datos de la base y **la misma `APP_KEY`** que uses en local si compartes datos cifrados.
6. Da **permisos de escritura** a `storage/` y sus subcarpetas (normalmente `755`).
7. En *Seleccionar versión de PHP*, elige **PHP 8.2+** y activa `pdo_mysql`, `mbstring`, `dom`, `gd`, `zip`, `fileinfo` y `openssl`.
8. Activa **SSL** (AutoSSL / Let's Encrypt) y usa `https://` en `APP_URL`.
9. Inicia sesión con `admin`, cambia la contraseña y completa los **Parámetros** (nombre del Jefe de la OTIC, etc.).

> Si el hosting usa una versión reciente de MariaDB y aparece el error **#1901**, revisa que estés usando el `schema.sql` de este repositorio: ya está adaptado a esa restricción.

---

## Reglas de negocio principales

- **"En uso" no es un estado:** un equipo está en uso si tiene responsable (`personal_id`). La ubicación (`oficina_id`) es obligatoria; el responsable, opcional (una impresora compartida no tiene).
- Todo cambio de responsable u oficina **cierra el registro vigente** del historial y abre uno nuevo, en una transacción.
- Las impresoras no llevan datos de procesador, RAM ni disco (lo garantiza también un `CHECK`).
- **Licencias M365:** máximo 5 instalaciones por cuenta (`CHECK slot BETWEEN 1 AND 5` + índice único sobre el slot activo) y un equipo solo puede consumir una licencia activa. Liberar un slot **no borra** el registro: guarda fecha, motivo (`FORMATEO`, `BAJA`, `REASIGNACION`, `OTRO`) y quién lo liberó.
- Solo reciben licencias del inventario los equipos **PC o LAPTOP** que no estén de baja.
- **Actas:** solo puede haber un acta en borrador por equipo; el acta cerrada es inmutable. Si el formateo incluye liberar la licencia, se libera con motivo `FORMATEO`.
- **Informes:** al emitir uno con `BAJA_DEFINITIVA`, los equipos quedan marcados como *recomendados para baja*; el paso a `DE_BAJA` lo confirma un administrador.
- **Correlativos** anuales generados con `SELECT … FOR UPDATE` dentro de la transacción del documento: nunca se duplican.
- **Integridad referencial:** nada de `ON DELETE CASCADE` sobre equipos, licencias, actas, informes ni historial; solo en tablas hijas puramente dependientes. Catálogos, personal, oficinas, licencias y usuarios usan **borrado lógico** (`activo`).

---

## Seguridad

- Contraseñas con `password_hash()` (bcrypt), `password_verify()` y rehash automático.
- Sesión con cookies `HttpOnly` y `SameSite=Lax`, `session_regenerate_id()` al iniciar sesión y **cierre por 30 minutos de inactividad**.
- **Bloqueo de la cuenta durante 15 minutos** tras 5 intentos fallidos.
- **Token CSRF** en todos los formularios POST y en las peticiones AJAX (validado por `CsrfMiddleware`).
- Todas las consultas con **sentencias preparadas**; las columnas dinámicas pasan por listas blancas.
- Toda salida en las vistas se escapa con `e()`.
- Contraseñas de cuentas M365 cifradas con **AES-256-GCM**; nunca aparecen en listados ni en la auditoría.
- Evidencias: se valida el **MIME real** con `finfo`, se re-codifican con GD (sin metadatos EXIF), se guardan con nombre aleatorio fuera de `public/` y se sirven solo a usuarios autenticados.
- Enlaces de confirmación de actas con token de 64 caracteres hexadecimales, del que **solo se guarda el hash SHA-256**, y vigencia configurable. Abrir el enlace no confirma la recepción (los antispam abren enlaces): hace falta pulsar el botón.
- En producción los errores se registran en `storage/logs/app.log` y el usuario solo ve un mensaje genérico.
- **Auditoría** de cada acción (crear, editar, eliminar, login, login fallido, asignar/liberar slot, ver contraseña, emitir, cerrar, firmar, enviar, confirmar recepción…) con usuario, IP, navegador y datos antes/después.

---

## Generación de documentos (PDF y Word)

- Plantillas en `app/Views/pdf/`, maquetadas con **tablas y CSS 2.1** (Dompdf no soporta flexbox ni grid; no se usa Bootstrap dentro del PDF).
- Fuente **DejaVu Sans** (tildes y ñ), A4 vertical y pie "Página X de Y".
- Imágenes incrustadas en base64, `isRemoteEnabled = false` y *chroot* en la carpeta del proyecto: el PDF no puede leer recursos externos.
- Los colores institucionales están centralizados en `app/Config/paleta.php` (PDF, Word y correos) y en `public/assets/css/app.css` (interfaz).
- El acta firmada digitalmente se guarda aparte (`storage/reports/actas_firmadas/`) y **nunca se regenera**.

---

## Solución de problemas

| Problema | Causa probable y solución |
|---|---|
| Error 404 en todas las rutas | `mod_rewrite` desactivado o `AllowOverride None`. Actívalo y permite `.htaccess`. |
| Los enlaces llevan a una ruta incorrecta | `APP_URL` mal configurada (debe ir sin barra final y apuntar a `public/`). |
| Error al generar PDF o Word | Faltan las extensiones `gd`, `zip` o `dom`. Revisa `php.ini` y reinicia Apache. |
| "No se puede descifrar la contraseña" | La `APP_KEY` del `.env` no es la misma con la que se guardaron los datos. |
| Fechas u horas desfasadas | SQL manual sin `SET time_zone = '-05:00';`. |
| El correo no sale | Usa una contraseña de aplicación, revisa puerto/cifrado y prueba con `database/probar_correo.php`. |
| Error #1901 al importar en el hosting | Usa el `schema.sql` actual de este repositorio. |
| Pantalla de error genérica | Revisa `storage/logs/app.log` (con `APP_ENV=production` el detalle solo se registra ahí). |

---

## Convenciones para contribuir

- Dominio en **español** (tablas, columnas, clases y métodos): `EquipoModel`, `LicenciaService::asignarSlot()`.
- Tablas y columnas en `snake_case`, clases en `PascalCase`, métodos y variables en `camelCase`; estilo **PSR-12** y tipado estricto.
- Archivos UTF-8 sin BOM y sin `?>` final en archivos que solo contienen PHP.
- Lógica de negocio en *Services*, acceso a datos en *Models*, orquestación HTTP en *Controllers*. Las vistas no consultan la base.
- Las rutas se declaran en `app/Config/routes.php`; las rutas POST validan CSRF automáticamente.
- No escribas colores HEX fuera de `app.css` y `paleta.php`.
- Para probar con una base que tiene datos reales, usa datos con prefijo `QA` y un usuario temporal, y bórralos al terminar.

---

## Licencia

Software **propietario** de uso interno del Centro de Altos Estudios Nacionales — Escuela de Posgrado (CAEN-EPG). Prohibida su distribución sin autorización de la OTIC.

Las librerías de terceros (Dompdf, PhpWord, PHPMailer y sus dependencias) mantienen sus propias licencias, incluidas en `vendor/` tras ejecutar `composer install`.
