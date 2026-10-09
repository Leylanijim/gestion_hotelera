"use strict";

/* ==========================================
   KIIN SAASIL - PANEL DE RECEPCIÓN
   Archivo: Recepcionista/app.js
========================================== */

const app = document.querySelector("#app");
const toastEl = document.querySelector("#toast");
const API = "../api/";

const nav = [
  ["dashboard", "▦", "Dashboard"],
  ["rooms", "▥", "Matriz de habitaciones"],
  ["reservations", "✓", "Gestión de Reservaciones"],
  ["guests", "♧", "Directorio de huéspedes"],
  ["maintenance", "⚒", "Mantenimiento e Incidencias"],
  ["reports", "▥", "Monitoreo y Reportes"],
  ["settings", "⚙", "Configuración"]
];

const state = {
  user: null,
  dashboard: {},
  rooms: [],
  reservations: [],
  guests: [],
  maintenance: [],
  reservationFilter: ""
};

/* ==========================================
   UTILIDADES
========================================== */

function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[char]);
}

function num(value) {
  return Number(value || 0);
}

function dateOnly(value) {
  return String(value || "").slice(0, 10);
}

function today() {
  const d = new Date();

  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, "0"),
    String(d.getDate()).padStart(2, "0")
  ].join("-");
}

function fullName(person) {
  return person.nombre_completo || [
    person.nombre,
    person.apellido_paterno,
    person.apellido_materno
  ].filter(Boolean).join(" ") || "Sin nombre";
}

function receptionistName() {
  return esc(
    state.user?.nombre ||
    state.user?.usuario ||
    "Recepcionista"
  );
}

function toast(message) {
  if (!toastEl) {
    alert(message);
    return;
  }

  toastEl.textContent = message;
  toastEl.classList.add("show");

  clearTimeout(toast.timer);

  toast.timer = setTimeout(() => {
    toastEl.classList.remove("show");
  }, 3500);
}

function go(page) {
  location.hash = page;
}

function emptyState(message = "No hay información registrada.") {
  return `
    <div class="empty">
      <p>${esc(message)}</p>
    </div>
  `;
}

function metric(label, value, description = "", color = "") {
  return `
    <div class="card ${color}">
      <div class="label">${esc(label)}</div>
      <div class="num">${esc(value)}</div>
      <div class="muted">${esc(description)}</div>
    </div>
  `;
}

function badge(value) {
  const status = String(value || "Sin estado");

  let color = "danger";

  if ([
    "Disponible",
    "Confirmada",
    "Activo",
    "Finalizada",
    "Finalizado"
  ].includes(status)) {
    color = "avail";
  } else if ([
    "Ocupada",
    "En estancia",
    "Activa"
  ].includes(status)) {
    color = "occ";
  } else if ([
    "Pendiente",
    "Reportado",
    "En proceso"
  ].includes(status)) {
    color = "clean";
  }

  return `<span class="badge ${color}">${esc(status)}</span>`;
}

function getList(value, keys = []) {
  if (Array.isArray(value)) return value;

  for (const key of keys) {
    if (Array.isArray(value?.[key])) {
      return value[key];
    }
  }

  return [];
}

async function api(endpoint, options = {}) {
  const response = await fetch(API + endpoint, {
    credentials: "same-origin",
    cache: "no-store",
    ...options
  });

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error(
      "La API no devolvió una respuesta JSON válida."
    );
  }

  if (response.status === 401 || response.status === 403) {
    window.location.replace("../login.html");
    throw new Error("Sesión no autorizada.");
  }

  if (!response.ok || result.success === false) {
    throw new Error(
      result.message || "No se pudo completar la solicitud."
    );
  }

  return result.data ?? result;
}

/* ==========================================
   ESTRUCTURA VISUAL
========================================== */

function layout(active, content) {
  app.innerHTML = `
    <aside class="side">
      <div class="brand">Kiin Saasil</div>
      <p class="sub">Sistema hotelero</p>

      <div class="role">▣ Rol: Recepcionista</div>

      <div class="current-user">
        <strong>${receptionistName()}</strong>
        <span>Recepcionista</span>
      </div>

      <nav class="nav">
        ${nav.map(item => `
          <button
            class="${active === item[0] ? "active" : ""}"
            onclick="go('${item[0]}')">
            ${item[1]}　${item[2]}
          </button>
        `).join("")}
      </nav>
    </aside>

    <main class="main">${content}</main>
  `;

  setClock();
}

function head(title, description, buttons = "") {
  return `
    <div class="top">
      <div>
        <h1>${esc(title)}</h1>
        <p class="muted">${esc(description)}</p>

        <div class="meta">
          <span class="pill">
            ▦ <span id="currentDate"></span>
          </span>

          <span class="pill">
            ⟳ Última actualización:
            <b id="lastUpdate"></b>
          </span>
        </div>
      </div>

      <div class="actions">${buttons}</div>
    </div>
  `;
}

function setClock() {
  const now = new Date();

  const date = now.toLocaleDateString("es-MX", {
    weekday: "long",
    day: "2-digit",
    month: "long",
    year: "numeric"
  });

  const time = now.toLocaleTimeString("es-MX", {
    hour: "2-digit",
    minute: "2-digit"
  });

  const dateEl = document.querySelector("#currentDate");
  const timeEl = document.querySelector("#lastUpdate");

  if (dateEl) dateEl.textContent = `${date}, ${time}`;
  if (timeEl) timeEl.textContent = time;
}

function refreshButton() {
  return `
    <button class="btn" onclick="render()">
      ⟳ Actualizar datos
    </button>
  `;
}

function loading(section, title) {
  layout(
    section,
    head(title, "Consultando información...") +
    `<div class="panel"><p>Cargando datos...</p></div>`
  );
}

function showError(section, title, error) {
  layout(
    section,
    head(title, "No se pudo cargar la información.") +
    `
      <div class="panel">
        <h2>Error al consultar el sistema</h2>
        <p>${esc(error.message)}</p>
        <button class="btn primary" onclick="render()">
          Reintentar
        </button>
      </div>
    `
  );
}

/* ==========================================
   DASHBOARD
========================================== */

async function dashboard() {
  loading("dashboard", "Dashboard");

  try {
    const [dashboardResult, roomsResult, reservationsResult] =
      await Promise.all([
        api("dashboard.php"),
        api("habitaciones.php"),
        api("reservaciones.php")
      ]);

    state.dashboard = dashboardResult;
    state.rooms = getList(roomsResult, [
      "habitaciones", "registros", "rooms"
    ]);
    state.reservations = getList(reservationsResult, [
      "reservaciones", "registros"
    ]);

    const available = state.rooms.filter(
      r => r.estado === "Disponible"
    ).length;

    const occupied = state.rooms.filter(
      r => r.estado === "Ocupada"
    ).length;

    const maintenanceCount = state.rooms.filter(
      r => ["Mantenimiento", "Fuera de servicio"]
        .includes(r.estado)
    ).length;

    const arrivals = state.reservations.filter(
      r => dateOnly(r.fecha_entrada) === today() &&
        ["Pendiente", "Confirmada"].includes(r.estado)
    ).length;

    layout(
      "dashboard",
      head(
        "Dashboard",
        "Resumen general de la operación del hotel",
        refreshButton()
      ) +
      `
        <div class="welcome panel">
          <h2>Bienvenido, ${receptionistName()}</h2>
          <p class="muted">
            Información actual de la operación hotelera.
          </p>
        </div>

        <div class="cards">
          ${metric(
            "Habitaciones disponibles",
            available,
            "Listas para asignar",
            "green"
          )}

          ${metric(
            "Habitaciones ocupadas",
            occupied,
            "Actualmente ocupadas"
          )}

          ${metric(
            "Check-ins pendientes",
            arrivals,
            "Llegadas programadas para hoy",
            "gold"
          )}

          ${metric(
            "Mantenimiento / fuera de servicio",
            maintenanceCount,
            "Habitaciones que requieren atención",
            "red"
          )}
        </div>

        <div class="panel">
          <h2>Accesos rápidos</h2>

          <div class="quick-actions">
            <button class="btn primary"
              onclick="go('reservations')">
              Gestión de Reservaciones
            </button>

            <button class="btn" onclick="go('rooms')">
              Matriz de Habitaciones
            </button>

            <button class="btn" onclick="go('guests')">
              Directorio de Huéspedes
            </button>

            <button class="btn" onclick="go('maintenance')">
              Mantenimiento e Incidencias
            </button>

            <button class="btn" onclick="go('reports')">
              Monitoreo y Reportes
            </button>
          </div>
        </div>
      `
    );

  } catch (error) {
    showError("dashboard", "Dashboard", error);
  }
}

/* ==========================================
   MATRIZ DE HABITACIONES
========================================== */

async function roomsPage() {
  loading("rooms", "Matriz de Habitaciones");

  try {
    const result = await api("habitaciones.php");

    state.rooms = getList(result, [
      "habitaciones", "registros", "rooms"
    ]);

    const floors = [
      ...new Set(state.rooms.map(r => String(r.piso)))
    ].filter(x => x && x !== "undefined");

    const types = [
      ...new Set(state.rooms.map(r =>
        r.tipo_habitacion ||
        r.nombre_tipo ||
        r.tipo ||
        ""
      ))
    ].filter(Boolean);

    layout(
      "rooms",
      head(
        "Matriz de Habitaciones",
        "Consulta el estado y disponibilidad de las habitaciones",
        refreshButton()
      ) +
      `
        <div class="panel">
          <h2>Filtros de habitaciones</h2>

          <div class="filters">
            <div class="field">
              <label>Buscar</label>
              <input id="rq"
                placeholder="Número o tipo de habitación"
                oninput="filterRooms()">
            </div>

            <div class="field">
              <label>Planta / Piso</label>
              <select id="roomFloor" onchange="filterRooms()">
                <option value="">Todas las plantas</option>
                ${floors.map(f => `
                  <option value="${esc(f)}">${esc(f)}</option>
                `).join("")}
              </select>
            </div>

            <div class="field">
              <label>Tipo</label>
              <select id="roomType" onchange="filterRooms()">
                <option value="">Todos los tipos</option>
                ${types.map(t => `
                  <option value="${esc(t)}">${esc(t)}</option>
                `).join("")}
              </select>
            </div>

            <div class="field">
              <label>Estado</label>
              <select id="roomStatus"
                onchange="filterRooms()">
                <option value="">Todos los estados</option>
                <option>Disponible</option>
                <option>Ocupada</option>
                <option>Mantenimiento</option>
                <option>Fuera de servicio</option>
              </select>
            </div>

            <button class="btn" onclick="clearRoomFilters()">
              Limpiar
            </button>
          </div>

          <p id="rn" class="muted"></p>
        </div>

        <div class="panel">
          <h2>Habitaciones</h2>
          <div id="rg" class="grid"></div>
        </div>
      `
    );

    filterRooms();

  } catch (error) {
    showError("rooms", "Matriz de Habitaciones", error);
  }
}

function roomType(room) {
  return room.tipo_habitacion ||
    room.nombre_tipo ||
    room.tipo ||
    "Sin tipo especificado";
}

function filterRooms() {
  const query = (
    document.querySelector("#rq")?.value || ""
  ).toLowerCase();

  const floor = document.querySelector(
    "#roomFloor"
  )?.value || "";

  const type = document.querySelector(
    "#roomType"
  )?.value || "";

  const status = document.querySelector(
    "#roomStatus"
  )?.value || "";

  const records = state.rooms.filter(room => {
    const text = [
      room.numero,
      roomType(room),
      room.piso,
      room.estado
    ].join(" ").toLowerCase();

    return text.includes(query) &&
      (!floor || String(room.piso) === floor) &&
      (!type || roomType(room) === type) &&
      (!status || room.estado === status);
  });

  const grid = document.querySelector("#rg");
  const counter = document.querySelector("#rn");

  if (!grid) return;

  grid.innerHTML = records.length
    ? records.map(room => `
      <div class="room ${
        room.estado === "Ocupada" ? "occ" :
        room.estado === "Mantenimiento" ? "maint" : ""
      }">
        <div class="row">
          <h3>Hab. ${esc(room.numero)}</h3>
          ${badge(room.estado)}
        </div>

        <p>
          ${esc(roomType(room))}
          · Piso ${esc(room.piso)}
        </p>

        <p>
          ${esc(room.observaciones || "Sin observaciones")}
        </p>

        <button class="link"
          onclick="roomDetails(${num(room.id_habitacion)})">
          ◉ Ver detalle
        </button>
      </div>
    `).join("")
    : emptyState("No se encontraron habitaciones.");

  if (counter) {
    counter.textContent =
      `Mostrando ${records.length} de ${state.rooms.length} habitaciones`;
  }
}

function clearRoomFilters() {
  ["rq", "roomFloor", "roomType", "roomStatus"]
    .forEach(id => {
      const element = document.getElementById(id);
      if (element) element.value = "";
    });

  filterRooms();
}

function roomDetails(id) {
  const room = state.rooms.find(
    r => num(r.id_habitacion) === id
  );

  if (!room) return;

  alert(
    `Habitación: ${room.numero}\n` +
    `Tipo: ${roomType(room)}\n` +
    `Piso: ${room.piso}\n` +
    `Estado: ${room.estado}\n` +
    `Observaciones: ${room.observaciones || "Ninguna"}`
  );
}

/* ==========================================
   GESTIÓN DE RESERVACIONES
========================================== */

async function reservations() {
  loading("reservations", "Gestión de Reservaciones");

  try {
    const result = await api("reservaciones.php");

    state.reservations = getList(result, [
      "reservaciones", "registros"
    ]);

    const active = state.reservations.filter(r =>
      ["Pendiente", "Confirmada", "En estancia"]
        .includes(r.estado)
    ).length;

    const arrivals = state.reservations.filter(r =>
      dateOnly(r.fecha_entrada) === today()
    ).length;

    const staying = state.reservations.filter(r =>
      r.estado === "En estancia"
    ).length;

    layout(
      "reservations",
      head(
        "Gestión de Reservaciones",
        "Consulta las reservas y llegadas del hotel",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric("Reservaciones activas", active)}
          ${metric("Llegadas hoy", arrivals)}
          ${metric("En estancia", staying)}
          ${metric(
            "Reservaciones registradas",
            state.reservations.length
          )}
        </div>

        <div class="panel">
          <div class="field">
            <input id="qres"
              placeholder="Buscar folio, huésped o habitación"
              oninput="filterRes()">
          </div>

          <div class="meta">
            <button class="btn"
              onclick="setFilter('')">Todas</button>

            <button class="btn"
              onclick="setFilter('Pendiente')">
              Pendientes
            </button>

            <button class="btn"
              onclick="setFilter('Confirmada')">
              Confirmadas
            </button>

            <button class="btn"
              onclick="setFilter('En estancia')">
              En estancia
            </button>

            <button class="btn"
              onclick="setFilter('Finalizada')">
              Finalizadas
            </button>

            <button class="btn"
              onclick="setFilter('Cancelada')">
              Canceladas
            </button>
          </div>
        </div>

        <div class="panel">
          <h2>Listado de Reservaciones</h2>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Folio</th>
                  <th>Huésped</th>
                  <th>Habitación</th>
                  <th>Fechas</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody id="resbody"></tbody>
            </table>
          </div>
        </div>
      `
    );

    filterRes();

  } catch (error) {
    showError("reservations", "Gestión de Reservaciones", error);
  }
}

function setFilter(value) {
  state.reservationFilter = value;
  filterRes();
}

function reservationGuest(record) {
  return record.huesped ||
    record.nombre_huesped ||
    record.huesped_nombre ||
    "Sin nombre";
}

function reservationRoom(record) {
  return record.habitacion ||
    record.numero_habitacion ||
    record.numero ||
    record.id_habitacion ||
    "Sin habitación";
}

function filterRes() {
  const query = (
    document.querySelector("#qres")?.value || ""
  ).toLowerCase();

  const records = state.reservations.filter(r => {
    const text = [
      r.id_reservacion,
      reservationGuest(r),
      reservationRoom(r),
      r.estado
    ].join(" ").toLowerCase();

    return text.includes(query) &&
      (!state.reservationFilter ||
        r.estado === state.reservationFilter);
  });

  const body = document.querySelector("#resbody");

  if (!body) return;

  body.innerHTML = records.length
    ? records.map(r => `
      <tr>
        <td>
          <b>RES-${String(r.id_reservacion).padStart(4, "0")}</b>
        </td>

        <td>${esc(reservationGuest(r))}</td>
        <td>${esc(reservationRoom(r))}</td>

        <td>
          ▣ Check-in: ${esc(r.fecha_entrada)}
          <br>
          ▣ Check-out: ${esc(r.fecha_salida)}
        </td>

        <td>${badge(r.estado)}</td>

        <td>
          <button class="icon"
            title="Ver reservación"
            onclick="reservationDetails(${
              num(r.id_reservacion)
            })">
            ◉
          </button>
        </td>
      </tr>
    `).join("")
    : `
      <tr>
        <td colspan="6">
          ${emptyState("No se encontraron reservaciones.")}
        </td>
      </tr>
    `;
}

function reservationDetails(id) {
  const r = state.reservations.find(
    x => num(x.id_reservacion) === id
  );

  if (!r) return;

  alert(
    `Folio: RES-${id}\n` +
    `Huésped: ${reservationGuest(r)}\n` +
    `Habitación: ${reservationRoom(r)}\n` +
    `Entrada: ${r.fecha_entrada}\n` +
    `Salida: ${r.fecha_salida}\n` +
    `Estado: ${r.estado}\n` +
    `Observaciones: ${r.observaciones || "Ninguna"}`
  );
}

/* ==========================================
   DIRECTORIO DE HUÉSPEDES
========================================== */


async function newGuest() {
  document.getElementById("guestModal")?.remove();

  const modal = document.createElement("div");
  modal.id = "guestModal";

  modal.style.cssText = `
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:20px;
  `;

  modal.innerHTML = `
    <div class="panel" style="
      width:100%;
      max-width:650px;
      max-height:90vh;
      overflow:auto;
    ">
      <h2>Registrar nuevo huésped</h2>
      <p class="muted">
        Completa la información del huésped.
      </p>

      <form id="guestForm">
        <div class="formgrid">
          <div class="field">
            <label>Nombre *</label>
            <input name="nombre" required maxlength="100">
          </div>

          <div class="field">
            <label>Apellido paterno *</label>
            <input name="apellido_paterno"
              required maxlength="100">
          </div>

          <div class="field">
            <label>Apellido materno</label>
            <input name="apellido_materno" maxlength="100">
          </div>

          <div class="field">
            <label>Identificación *</label>
            <input name="identificacion"
              required maxlength="100">
          </div>

          <div class="field">
            <label>Teléfono</label>
            <input name="telefono" maxlength="30">
          </div>

          <div class="field">
            <label>Correo electrónico</label>
            <input name="correo" type="email" maxlength="150">
          </div>
        </div>

        <div class="field">
          <label>Dirección</label>
          <textarea name="direccion" rows="3"></textarea>
        </div>

        <div class="actions" style="margin-top:20px">
          <button type="button" class="btn"
            id="cancelGuest">
            Cancelar
          </button>

          <button type="submit" class="btn primary"
            id="saveGuest">
            Guardar huésped
          </button>
        </div>
      </form>
    </div>
  `;

  document.body.appendChild(modal);

  modal.querySelector("#cancelGuest").onclick = () => {
    modal.remove();
  };

  modal.querySelector("#guestForm").onsubmit =
    async event => {
      event.preventDefault();

      const form = event.currentTarget;
      const button = modal.querySelector("#saveGuest");

      const data = Object.fromEntries(
        new FormData(form).entries()
      );

      for (const key in data) {
        data[key] = data[key].trim();
      }

      if (!data.nombre ||
          !data.apellido_paterno ||
          !data.identificacion) {
        toast("Completa los campos obligatorios.");
        return;
      }

      if (!confirm(
        "¿Confirmas el registro de este huésped?"
      )) {
        return;
      }

      button.disabled = true;
      button.textContent = "Guardando...";

      try {
        await api("huespedes.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(data)
        });

        modal.remove();
        toast("Huésped registrado correctamente.");

        await guestsPage();

      } catch (error) {
        toast(error.message);
        button.disabled = false;
        button.textContent = "Guardar huésped";
      }
    };
}


function filterGuests() {
  const query = (
    document.querySelector("#qg")?.value || ""
  ).toLowerCase();

  const records = state.guests.filter(g =>
    [
      fullName(g),
      g.identificacion,
      g.correo,
      g.telefono
    ].join(" ").toLowerCase().includes(query)
  );

  const body = document.querySelector("#gb");

  if (!body) return;

  body.innerHTML = records.length
    ? records.map(g => `
      <tr>
        <td>${esc(g.id_huesped)}</td>

        <td>
          <b>${esc(fullName(g))}</b>
        </td>

        <td>${esc(g.identificacion)}</td>

        <td>
          ✉ ${esc(g.correo || "Sin correo")}
          <br>
          ☎ ${esc(g.telefono || "Sin teléfono")}
        </td>

        <td>${badge(g.estado)}</td>

        <td>
          <button class="icon"
            title="Ver huésped"
            onclick="guestDetails(${num(g.id_huesped)})">
            ◉
          </button>
        </td>
      </tr>
    `).join("")
    : `
      <tr>
        <td colspan="6">
          ${emptyState("No se encontraron huéspedes.")}
        </td>
      </tr>
    `;
}

function guestDetails(id) {
  const g = state.guests.find(
    x => num(x.id_huesped) === id
  );

  if (!g) return;

  alert(
    `Nombre: ${fullName(g)}\n` +
    `Identificación: ${g.identificacion || ""}\n` +
    `Teléfono: ${g.telefono || ""}\n` +
    `Correo: ${g.correo || ""}\n` +
    `Dirección: ${g.direccion || ""}\n` +
    `Estado: ${g.estado || ""}`
  );
}

/* ==========================================
   MANTENIMIENTO E INCIDENCIAS
========================================== */

async function maintenance() {
  loading("maintenance", "Mantenimiento e Incidencias");

  try {
    const [maintenanceResult, roomsResult] =
      await Promise.all([
        api("mantenimientos.php"),
        api("habitaciones.php")
      ]);

    state.maintenance = getList(maintenanceResult, [
      "mantenimientos", "registros"
    ]);

    state.rooms = getList(roomsResult, [
      "habitaciones", "registros", "rooms"
    ]);

    const available = state.rooms.filter(
      r => r.estado === "Disponible"
    ).length;

    const blocked = state.rooms.filter(
      r => r.estado === "Mantenimiento"
    ).length;

    const pending = state.maintenance.filter(
      m => ["Reportado", "En proceso"].includes(m.estado)
    ).length;

    const finished = state.maintenance.filter(
      m => m.estado === "Finalizado"
    ).length;

    layout(
      "maintenance",
      head(
        "Control de Mantenimiento y Limpieza",
        "Supervisa las incidencias y el estado de las habitaciones",
        `
          <button class="btn" onclick="maintenance()">
            ⟳ Actualizar tablero
          </button>

          <button class="btn primary"
            onclick="reportarAveria()">
            ⚒＋ Reportar Nueva Avería / Bloqueo
          </button>
        `
      ) +
      `
        <div class="cards">
          ${metric(
            "Habitaciones disponibles",
            available,
            "Listas para asignar",
            "green"
          )}

          ${metric(
            "Habitaciones en mantenimiento",
            blocked,
            "Fuera de operación",
            "red"
          )}

          ${metric(
            "Reportes pendientes",
            pending,
            "Reportados o en proceso",
            "gold"
          )}

          ${metric(
            "Mantenimientos finalizados",
            finished,
            "Reportes atendidos",
            "green"
          )}
        </div>

        <div class="panel">
          <h2>Control operativo de habitaciones</h2>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Habitación</th>
                  <th>Ubicación / planta</th>
                  <th>Estado</th>
                  <th>Incidencia / avería</th>
                  <th>Fecha de inicio</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>
                ${
                  state.maintenance.length
                    ? state.maintenance.map(m => `
                      <tr>
                        <td>
                          <b>Hab. ${esc(m.habitacion)}</b>
                        </td>

                        <td>
                          Piso ${esc(m.piso ?? "No especificado")}
                        </td>

                        <td>${badge(m.estado)}</td>

                        <td>${esc(m.motivo)}</td>

                        <td>${esc(m.fecha_inicio)}</td>

                        <td>
                          <button class="icon"
                            title="Ver incidencia"
                            onclick="maintenanceDetails(${
                              num(m.id_mantenimiento)
                            })">
                            ◉
                          </button>
                        </td>
                      </tr>
                    `).join("")
                    : `
                      <tr>
                        <td colspan="6">
                          ${emptyState(
                            "No hay incidencias registradas."
                          )}
                        </td>
                      </tr>
                    `
                }
              </tbody>
            </table>
          </div>
        </div>
      `
    );

  } catch (error) {
    showError(
      "maintenance",
      "Mantenimiento e Incidencias",
      error
    );
  }
}

function maintenanceDetails(id) {
  const m = state.maintenance.find(
    x => num(x.id_mantenimiento) === id
  );

  if (!m) return;

  alert(
    `Reporte: ${m.id_mantenimiento}\n` +
    `Habitación: ${m.habitacion}\n` +
    `Motivo: ${m.motivo}\n` +
    `Estado: ${m.estado}\n` +
    `Inicio: ${m.fecha_inicio}\n` +
    `Fin: ${m.fecha_fin || "Pendiente"}\n` +
    `Observaciones: ${m.observaciones || "Ninguna"}`
  );
}

/* ==========================================
   REPORTAR NUEVA AVERÍA
========================================== */

async function reportarAveria() {
  try {
    const result = await api("habitaciones.php");

    const rooms = getList(result, [
      "habitaciones", "registros", "rooms"
    ]);

    const available = rooms.filter(
      r => r.estado === "Disponible"
    );

    if (!available.length) {
      toast(
        "No hay habitaciones disponibles para reportar."
      );
      return;
    }

    document.querySelector("#modalAveria")?.remove();

    const modal = document.createElement("div");
    modal.id = "modalAveria";

    modal.style.cssText = `
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.55);
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
      z-index:9999;
    `;

    modal.innerHTML = `
      <div class="panel" style="
        width:100%;
        max-width:520px;
        max-height:90vh;
        overflow:auto;
      ">
        <h2>⚒ Reportar nueva avería</h2>

        <p class="muted">
          Registra una incidencia para bloquear
          temporalmente una habitación.
        </p>

        <form id="formAveria">
          <div class="field">
            <label>Habitación *</label>

            <select name="id_habitacion" required>
              <option value="">
                Selecciona una habitación
              </option>

              ${available.map(r => `
                <option value="${num(r.id_habitacion)}">
                  Habitación ${esc(r.numero)}
                  - Piso ${esc(r.piso)}
                </option>
              `).join("")}
            </select>
          </div>

          <div class="field">
            <label>Motivo de la avería *</label>

            <input
              name="motivo"
              maxlength="500"
              placeholder="Ej. Fuga de agua en el baño"
              required>
          </div>

          <div class="field">
            <label>Observaciones</label>

            <textarea
              name="observaciones"
              rows="4"
              maxlength="2000"
              placeholder="Describe el problema encontrado"></textarea>
          </div>

          <p class="muted">
            Al guardar, la habitación cambiará
            al estado Mantenimiento.
          </p>

          <div class="actions"
            style="margin-top:20px;display:flex;gap:10px">
            <button type="button"
              class="btn"
              id="cancelarAveria">
              Cancelar
            </button>

            <button type="submit"
              class="btn primary"
              id="guardarAveria">
              Guardar reporte
            </button>
          </div>
        </form>
      </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector("#cancelarAveria")
      .addEventListener("click", () => {
        modal.remove();
      });

    modal.addEventListener("click", event => {
      if (event.target === modal) {
        modal.remove();
      }
    });

    modal.querySelector("#formAveria")
      .addEventListener("submit", async event => {
        event.preventDefault();

        const form = event.currentTarget;
        const button = modal.querySelector("#guardarAveria");

        const payload = {
          id_habitacion: Number(
            form.elements.id_habitacion.value
          ),
          motivo: form.elements.motivo.value.trim(),
          observaciones:
            form.elements.observaciones.value.trim()
        };

        if (!payload.id_habitacion || !payload.motivo) {
          toast("Completa los campos obligatorios.");
          return;
        }

        const confirmed = confirm(
          "¿Deseas registrar esta avería?\n\n" +
          "La habitación cambiará a Mantenimiento."
        );

        if (!confirmed) return;

        button.disabled = true;
        button.textContent = "Guardando...";

        try {
          await api("mantenimientos.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
          });

          modal.remove();

          toast("Avería registrada correctamente.");

          await maintenance();

        } catch (error) {
          toast(error.message);

          button.disabled = false;
          button.textContent = "Guardar reporte";
        }
      });

  } catch (error) {
    toast(
      "No se pudieron cargar las habitaciones: " +
      error.message
    );
  }
}

/* ==========================================
   MONITOREO Y REPORTES
========================================== */

async function reports() {
  loading("reports", "Monitoreo y Reportes");

  try {
    const [roomsResult, reservationsResult, maintenanceResult] =
      await Promise.all([
        api("habitaciones.php"),
        api("reservaciones.php"),
        api("mantenimientos.php")
      ]);

    const rooms = getList(roomsResult, [
      "habitaciones", "registros", "rooms"
    ]);

    const reservationsList = getList(
      reservationsResult,
      ["reservaciones", "registros"]
    );

    const maintenanceList = getList(
      maintenanceResult,
      ["mantenimientos", "registros"]
    );

    const occupied = rooms.filter(
      r => r.estado === "Ocupada"
    ).length;

    const occupancy = rooms.length
      ? ((occupied / rooms.length) * 100).toFixed(1) + "%"
      : "0%";

    const finished = reservationsList.filter(
      r => r.estado === "Finalizada"
    ).length;

    const attended = maintenanceList.filter(
      m => m.estado === "Finalizado"
    ).length;

    layout(
      "reports",
      head(
        "Monitoreo Operativo y Reportes",
        "Indicadores actuales del sistema hotelero",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric(
            "Ocupación actual",
            occupancy,
            "Porcentaje de habitaciones ocupadas"
          )}

          ${metric(
            "Habitaciones registradas",
            rooms.length,
            "Total de habitaciones"
          )}

          ${metric(
            "Reservaciones finalizadas",
            finished,
            "Historial de reservaciones"
          )}

          ${metric(
            "Mantenimientos atendidos",
            attended,
            "Incidencias finalizadas",
            "green"
          )}
        </div>

        <div class="panel">
          <h2>Información del reporte</h2>

          <p class="muted">
            Los indicadores muestran la información
            disponible actualmente en MySQL.
          </p>

          <p class="muted">
            Los reportes financieros y por rango de fechas
            requieren consultas adicionales en el servidor.
          </p>
        </div>
      `
    );

  } catch (error) {
    showError("reports", "Monitoreo y Reportes", error);
  }
}

/* ==========================================
   CONFIGURACIÓN DE CUENTA
========================================== */

function settings() {
  const user = state.user || {};

  layout(
    "settings",
    head(
      "Configuración de Cuenta y Preferencias de Operación",
      "Información de tu cuenta como recepcionista"
    ) +
    `
      <div class="profile">
        <div class="panel">
          <h2>Información personal y credenciales</h2>

          <p class="muted">
            Consulta la información asociada a tu sesión.
          </p>

          <div class="formgrid">
            <div class="field">
              <label>Nombre completo</label>
              <input
                value="${esc(user.nombre || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Usuario</label>
              <input
                value="${esc(user.usuario || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Correo electrónico</label>
              <input
                value="${esc(user.correo || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Rol</label>
              <input value="Recepcionista" readonly>
            </div>

            <div class="field">
              <label>Sede o propiedad</label>
              <input value="Kiin Saasil Hotel" readonly>
            </div>
          </div>

          <div class="access">
            <h3>◉ Datos de acceso</h3>
            <p class="muted">
              Tu rol determina los permisos disponibles
              dentro del sistema.
            </p>
          </div>
        </div>

        <div class="panel profile-side">
          <div class="avatar">R</div>

          <h2>${receptionistName()}</h2>
          <p class="muted">Recepcionista</p>

          ${badge(user.estado || "Activo")}

          <div class="details">
            <div>
              <span>Correo:</span>
              <b>${esc(user.correo || "Sin registrar")}</b>
            </div>

            <div>
              <span>Rol:</span>
              <b>Recepcionista</b>
            </div>

            <div>
              <span>Estado:</span>
              <b>Activo</b>
            </div>
          </div>
        </div>
      </div>
    `
  );
}

/* ==========================================
   INICIO Y NAVEGACIÓN
========================================== */

async function render() {
  const section = location.hash.slice(1) || "dashboard";

  const pages = {
    dashboard,
    rooms: roomsPage,
    reservations,
    guests: guestsPage,
    maintenance,
    reports,
    settings
  };

  await (pages[section] || dashboard)();
}

async function init() {
  try {
    const session = await api("session.php");

    if (session.rol !== "Recepcionista") {
      window.location.replace(
        session.rol === "Administrador"
          ? "../index.html"
          : "../login.html"
      );
      return;
    }

    state.user = session;

    window.addEventListener("hashchange", render);

    await render();

    setInterval(setClock, 30000);

  } catch (error) {
    console.error("Error al iniciar Recepción:", error);

    if (app) {
      app.innerHTML = `
        <div style="padding:40px;text-align:center">
          <h2>No se pudo iniciar el panel</h2>
          <p>${esc(error.message)}</p>

          <button class="btn"
            onclick="location.reload()">
            Reintentar
          </button>
        </div>
      `;
    }
  }
}

init();
