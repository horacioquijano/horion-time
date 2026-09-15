<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
            Calendario de Turnos
        </h2>
        <div style="display: flex; gap: 12px;">
            <select id="selectUsuario" style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px;">
                <option value="">Seleccionar empleado...</option>
                <option value="1">Juan Pérez</option>
                <option value="2">María García</option>
            </select>
            <button class="btn btn-primary" onclick="copiarSemanaAnterior()">
                <i class="fas fa-copy"></i> Copiar Semana Anterior
            </button>
        </div>
    </div>

    <div style="padding: 32px;">
        <div style="display: grid; grid-template-columns: 250px 1fr; gap: 24px;">
            
            <!-- Panel de Turnos Disponibles -->
            <div class="card-3d" style="height: fit-content;">
                <h3 style="font-weight: 700; margin-bottom: 16px; font-size: 1rem;">
                    <i class="fas fa-list" style="color: var(--primary);"></i>
                    Turnos Disponibles
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                    Arrastra los turnos al calendario
                </p>
                
                <div id="turnosDisponibles" style="display: flex; flex-direction: column; gap: 8px;">
                    <div class="turno-draggable" data-turno-id="1" style="background: #03a950; color: white; padding: 12px; border-radius: 8px; cursor: move; font-weight: 600;">
                        🟢 Mañana (08:00 - 17:00)
                    </div>
                    <div class="turno-draggable" data-turno-id="2" style="background: #2196f3; color: white; padding: 12px; border-radius: 8px; cursor: move; font-weight: 600;">
                        🔵 Tarde (14:00 - 22:00)
                    </div>
                    <div class="turno-draggable" data-turno-id="3" style="background: #6c757d; color: white; padding: 12px; border-radius: 8px; cursor: move; font-weight: 600;">
                        ⚫ Noche (22:00 - 06:00)
                    </div>
                    <div class="turno-draggable" data-turno-id="4" style="background: #ff9800; color: white; padding: 12px; border-radius: 8px; cursor: move; font-weight: 600;">
                        🟠 Turno A (06:00 - 14:00)
                    </div>
                </div>
            </div>

            <!-- Calendario FullCalendar -->
            <div class="card-3d">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</main>

<!-- FullCalendar CSS y JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

<style>
.turno-draggable {
    transition: transform 0.2s, box-shadow 0.2s;
    user-select: none;
}

.turno-draggable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.turno-draggable.dragging {
    opacity: 0.5;
}

/* Estilos personalizados para FullCalendar */
.fc {
    font-family: 'Inter', sans-serif;
}

.fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: var(--text-dark) !important;
}

.fc-button-primary {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
}

.fc-button-primary:hover {
    background: var(--primary-dark) !important;
}

.fc-daygrid-day-number {
    font-weight: 600 !important;
    color: var(--text-dark) !important;
}

.fc-event {
    border-radius: 6px !important;
    padding: 4px 8px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}

.fc-event:hover {
    opacity: 0.8;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var csrfToken = '<?= $csrf_token ?>';

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridWeek,dayGridMonth'
        },
        locale: 'es',
        editable: true,
        droppable: true,
        selectable: true,
        height: 'auto',
        
        // Cargar eventos existentes
        events: function(info, successCallback, failureCallback) {
            var usuarioId = document.getElementById('selectUsuario').value;
            if (!usuarioId) {
                successCallback([]);
                return;
            }
            
            fetch(`/horion-time/public/horarios/getEventos?usuario_id=${usuarioId}&start=${info.startStr}&end=${info.endStr}`)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(error => failureCallback(error));
        },

        // Cuando se arrastra un turno al calendario
        drop: function(info) {
            var turnoId = info.draggedEl.getAttribute('data-turno-id');
            var fecha = info.dateStr;
            var usuarioId = document.getElementById('selectUsuario').value;

            if (!usuarioId) {
                alert('⚠️ Primero selecciona un empleado');
                info.revert();
                return;
            }

            // Enviar al servidor
            fetch('/horion-time/public/horarios/asignar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${csrfToken}&usuario_id=${usuarioId}&turno_id=${turnoId}&fecha=${fecha}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    calendar.refetchEvents();
                } else {
                    alert('Error: ' + data.error);
                    info.revert();
                }
            });
        },

        // Cuando se hace clic en un evento
        eventClick: function(info) {
            if (confirm('¿Deseas eliminar este turno asignado?')) {
                // Lógica para eliminar
                info.event.remove();
            }
        },

        // Cuando se selecciona un rango de fechas
        select: function(info) {
            var turnoId = prompt('Ingresa el ID del turno a asignar:');
            if (turnoId) {
                var usuarioId = document.getElementById('selectUsuario').value;
                if (!usuarioId) {
                    alert('⚠️ Primero selecciona un empleado');
                    return;
                }

                fetch('/horion-time/public/horarios/asignar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf_token=${csrfToken}&usuario_id=${usuarioId}&turno_id=${turnoId}&fecha=${info.startStr}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendar.refetchEvents();
                    }
                });
            }
        }
    });

    calendar.render();

    // Hacer elementos arrastrables
    var draggables = document.querySelectorAll('.turno-draggable');
    draggables.forEach(function(el) {
        new FullCalendar.Draggable(el, {
            itemSelector: '.turno-draggable',
            eventData: function(eventEl) {
                return {
                    title: eventEl.innerText,
                    backgroundColor: window.getComputedStyle(eventEl).backgroundColor
                };
            }
        });
    });

    // Recargar calendario al cambiar usuario
    document.getElementById('selectUsuario').addEventListener('change', function() {
        calendar.refetchEvents();
    });
});

function copiarSemanaAnterior() {
    var usuarioId = document.getElementById('selectUsuario').value;
    if (!usuarioId) {
        alert('️ Primero selecciona un empleado');
        return;
    }

    if (confirm('¿Copiar los turnos de la semana anterior a esta semana?')) {
        var csrfToken = '<?= $csrf_token ?>';
        var hoy = new Date();
        var semanaDestino = hoy.toISOString().split('T')[0];
        
        fetch('/horion-time/public/horarios/copiarSemana', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${csrfToken}&usuario_id=${usuarioId}&semana_origen=${semanaDestino}&semana_destino=${semanaDestino}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Semana copiada exitosamente');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    }
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>