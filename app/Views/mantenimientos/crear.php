<?php

declare(strict_types=1);

/** Paso 1: buscar al personal y elegir uno de sus equipos. */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-screwdriver-wrench me-2 text-primary"></i>Nueva acta de mantenimiento</h1>
    <a href="<?= e(url('mantenimientos')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><span class="badge text-bg-primary me-2">1</span>Buscar personal</h2>
                <label for="buscar-personal" class="form-label">Nombre, apellidos o DNI</label>
                <input type="search" id="buscar-personal" class="form-control mb-3" autocomplete="off" autofocus
                       placeholder="Escriba al menos 2 caracteres" data-url="<?= e(url('personal/autocompletar')) ?>">
                <div id="lista-personal" class="list-group"></div>
                <p class="small text-body-secondary mt-3 mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>¿Equipo sin responsable (p. ej. una impresora compartida)?
                    Ábralo desde su <a href="<?= e(url('equipos')) ?>">ficha en el inventario</a> con el botón "Registrar acta".
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><span class="badge text-bg-primary me-2">2</span>Elegir equipo</h2>
                <div id="equipos-persona" data-url-base="<?= e(url('mantenimientos/personal')) ?>" data-url-crear="<?= e(url('mantenimientos/crear')) ?>"
                     data-url-acta="<?= e(url('mantenimientos')) ?>">
                    <p class="text-body-secondary small mb-0">Seleccione primero a la persona.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('buscar-personal');
    var lista = document.getElementById('lista-personal');
    var panel = document.getElementById('equipos-persona');
    var temporizador = null;

    function texto(el, clase, contenido) {
        var nodo = document.createElement(el);
        if (clase) { nodo.className = clase; }
        nodo.textContent = contenido;
        return nodo;
    }

    function mostrarEquipos(persona) {
        panel.innerHTML = '';
        panel.appendChild(texto('p', 'small mb-2', 'Equipos a cargo de ' + persona.nombres + ' ' + persona.apellidos + ':'));
        SIGPAT.fetchJson(panel.dataset.urlBase + '/' + persona.id + '/equipos').then(function (datos) {
            if (datos.equipos.length === 0) {
                panel.appendChild(texto('div', 'alert alert-warning small mb-0', 'Esta persona no tiene equipos asignados (o todos están dados de baja).'));
                return;
            }
            var grupo = document.createElement('div');
            grupo.className = 'list-group';
            datos.equipos.forEach(function (eq) {
                var enlace = document.createElement('a');
                enlace.className = 'list-group-item list-group-item-action';
                enlace.href = eq.borrador_id ? panel.dataset.urlActa + '/' + eq.borrador_id + '/editar' : panel.dataset.urlCrear + '?equipo_id=' + eq.id;
                var fila = document.createElement('div');
                fila.className = 'd-flex justify-content-between align-items-start gap-2';
                var cuerpo = document.createElement('div');
                cuerpo.appendChild(texto('div', 'fw-semibold', eq.tipo + ' ' + eq.marca + ' ' + eq.modelo));
                cuerpo.appendChild(texto('div', 'small text-body-secondary',
                    'Serie: ' + (eq.nro_serie || '—') + ' · Patrimonial: ' + (eq.codigo_patrimonial || '—') + ' · ' +
                    (eq.hostname || '') + ' ' + (eq.ip_lan || '') + ' · ' + (eq.oficina_siglas || eq.oficina_nombre)));
                fila.appendChild(cuerpo);
                fila.appendChild(texto('span', 'badge ' + (eq.borrador_id ? 'text-bg-warning' : 'text-bg-primary'),
                    eq.borrador_id ? 'Borrador abierto' : 'Iniciar acta'));
                enlace.appendChild(fila);
                grupo.appendChild(enlace);
            });
            panel.appendChild(grupo);
        }).catch(function (error) {
            panel.appendChild(texto('div', 'alert alert-danger small mb-0', error.message));
        });
    }

    input.addEventListener('input', function () {
        clearTimeout(temporizador);
        var q = input.value.trim();
        lista.innerHTML = '';
        if (q.length < 2) {
            return;
        }
        temporizador = setTimeout(function () {
            SIGPAT.fetchJson(input.dataset.url + '?q=' + encodeURIComponent(q)).then(function (datos) {
                lista.innerHTML = '';
                if (datos.personal.length === 0) {
                    lista.appendChild(texto('div', 'list-group-item small text-body-secondary', 'Sin resultados.'));
                    return;
                }
                datos.personal.forEach(function (p) {
                    var boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'list-group-item list-group-item-action';
                    boton.appendChild(texto('div', 'fw-semibold', p.apellidos + ', ' + p.nombres));
                    boton.appendChild(texto('div', 'small text-body-secondary', (p.dni ? 'DNI ' + p.dni + ' · ' : '') + (p.cargo || '') + ' · ' + p.oficina_nombre));
                    boton.addEventListener('click', function () {
                        lista.querySelectorAll('.active').forEach(function (b) { b.classList.remove('active'); });
                        boton.classList.add('active');
                        mostrarEquipos(p);
                    });
                    lista.appendChild(boton);
                });
            }).catch(function (error) {
                lista.appendChild(texto('div', 'list-group-item small text-danger', error.message));
            });
        }, 300);
    });
});
</script>
