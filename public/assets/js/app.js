/* SIGPAT-OTIC - comportamiento común (JavaScript vanilla) */
(function () {
    'use strict';

    // Mostrar / ocultar contraseña: <button data-toggle-password="#idInput">
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-toggle-password]');
        if (!boton) {
            return;
        }
        var input = document.querySelector(boton.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }
        var mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        var icono = boton.querySelector('i');
        if (icono) {
            icono.classList.toggle('fa-eye', !mostrar);
            icono.classList.toggle('fa-eye-slash', mostrar);
        }
    });

    // Confirmación antes de enviar: <form data-confirm="¿Seguro?">
    document.addEventListener('submit', function (evento) {
        if (evento.defaultPrevented) {
            return;
        }
        var formulario = evento.target;
        // Evita envíos dobles sin deshabilitar el botón (un botón deshabilitado no envía su name/value).
        if (formulario.dataset.enviando === '1') {
            evento.preventDefault();
            return;
        }
        var mensaje = formulario.getAttribute('data-confirm');
        if (mensaje && !window.confirm(mensaje)) {
            evento.preventDefault();
            return;
        }
        formulario.dataset.enviando = '1';
        formulario.querySelectorAll('button[type="submit"]').forEach(function (b) {
            b.classList.add('disabled');
            b.setAttribute('aria-disabled', 'true');
        });
        // Si la página no cambia (p. ej. una descarga), se permite volver a enviar.
        setTimeout(function () {
            delete formulario.dataset.enviando;
            formulario.querySelectorAll('button[type="submit"]').forEach(function (b) {
                b.classList.remove('disabled');
                b.removeAttribute('aria-disabled');
            });
        }, 8000);
    });

    // Helper para peticiones AJAX con token CSRF: SIGPAT.fetchJson(url, {method, body})
    var metaToken = document.querySelector('meta[name="csrf-token"]');
    window.SIGPAT = {
        csrfToken: metaToken ? metaToken.getAttribute('content') : '',
        fetchJson: function (url, opciones) {
            opciones = opciones || {};
            var cabeceras = Object.assign({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken
            }, opciones.headers || {});
            return fetch(url, Object.assign({}, opciones, {
                headers: cabeceras,
                credentials: 'same-origin'
            })).then(function (respuesta) {
                return respuesta.json().then(function (datos) {
                    if (!respuesta.ok) {
                        var error = new Error(datos.mensaje || 'Error en la solicitud');
                        error.datos = datos;
                        error.status = respuesta.status;
                        throw error;
                    }
                    return datos;
                });
            });
        }
    };
})();
