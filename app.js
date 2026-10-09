
"use strict";

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

const data = {
  dashboard: null,
  rooms: [],
  reservations: [],
  guests: [],
  maintenance: [],
  session: null
};

const filters = {
  rooms: "",
  reservations: "",
  guests: ""
};

function escapeHTML(value) {
  return String(value ?? "").replace(/[&<>"']/g, character => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[character]);
}

function money(value) {
  return Number(value || 0).toLocaleString("es-MX", {
    style: "currency",
    currency: "MXN"
  });
}

function today() {
  const now = new Date();
  return [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, "0"),
    String(now.getDate()).padStart(2, "0")
  ].join("-");
}

function fullName(person) {
  if (person.nombre_completo) return person.nombre_completo;

  return [
    person.nombre,
    person.apellido_paterno,
    person.apellido_materno
  ].filter(Boolean).join(" ") || "Sin nombre";
}

function notify(message) {
  if (!toastEl) return alert(message);

  toastEl.textContent = message;
  toastEl.classList.add("show");

  setTimeout(() => {
    toastEl.classList.remove("show");
  }, 3000);
}

function go(section) {
  location.hash = section;
}

async function request(endpoint) {
  const response = await fetch(API + endpoint, {
    method: "GET",
    credentials: "same-origin",
    cache: "no-store"
  });

  if (response.status === 401 || response.status === 403) {
    location.replace("../login.html");
    throw new Error("Sesión no autorizada.");
  }

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error("La API no devolvió JSON válido.");
  }

  if (!response.ok || result.success === false) {
    throw new Error(
      result.message || "No se pudo consultar la información."
    );
  }

  return result.data ?? result;
}

function asArray(value, keys = []) {
  if (Array.isArray(value)) return value;

  for (const key of keys) {
    if (Array.isArray(value?.[key])) {
      return value[key];
    }
  }

  return [];
}

function receptionistName() {
  const user = data.session || {};

  return escapeHTML(
    user.nombre || user.usuario || "Recepcionista"
  );
}

function emptyState(message = "No hay registros disponibles.") {
  return `
    <div class="empty">
      <p>${escapeHTML(message)}</p>
    </div>
  `;
}

function metric(label, value, description = "", color = "") {
  return `
    <div class="card ${color}">
      <div class="label">${escapeHTML(label)}</div>
      <div class="num">${escapeHTML(value)}</div>
      <div class="muted">${escapeHTML(description)}</div>
    </div>
  `;
}

function badge(value) {
  const state = String(value || "Sin estado");

  const positive = [
    "Disponible", "Confirmada", "Activo",
    "Finalizada", "Finalizado"
  ];

  const occupied = [
    "Ocupada", "En estancia", "Activa"
  ];

  const pending = [
    "Pendiente", "Reportado", "En proceso"
  ];

  let color = "danger";

  if (positive.includes(state)) color = "avail";
  if (occupied.includes(state)) color = "occ";
  if (pending.includes(state)) color = "clean";

  return `<span class="badge ${color}">
    ${escapeHTML(state)}
  </span>`;
}

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

function head(title, description, actions = "") {
  return `
    <div class="top">
      <div>
        <h1>${escapeHTML(title)}</h1>
        <p class="muted">${escapeHTML(description)}</p>

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

      <div class="actions">${actions}</div>
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

  const dateElement = document.querySelector("#currentDate");
  const timeElement = document.querySelector("#lastUpdate");

  if (dateElement) dateElement.textContent = `${date}, ${time}`;
  if (timeElement) timeElement.textContent = time;
}

function refreshButton() {
  return `
    <button class="btn" onclick="render()">
      ⟳ Actualizar datos
    </button>
  `;
}

function loadingPage(section, title) {
  layout(
    section,
    head(title, "Consultando información del sistema...") +
    `<div class="panel"><p class="muted">
      Cargando datos...
    </p></div>`
  );
}

function errorPage(section, title, error) {
  layout(
    section,
    head(title, "No se pudieron cargar los registros.") +
    `
      <div class="panel">
        <h2>Error al consultar MySQL</h2>
        <p>${escapeHTML(error.message)}</p>
        <button class="btn primary" onclick="render()">
          Reintentar
        </button>
      </div>
    `
  );
}

/* =========================
   DASHBOARD
========================= */

async function dashboard() {
  loadingPage("dashboard", "Dashboard");

  try {
    const result = await request("dashboard.php");
    data.dashboard = result;

    const rooms = result.habitaciones || {};
    const reservations = result.reservaciones || {};
    const stays = result.estancias || {};
    const maintenance = result.mantenimientos || {};

    const available = Number(rooms.disponibles || 0);
    const occupied = Number(rooms.ocupadas || 0);
    const maintenanceCount = Number(
      rooms.mantenimiento || 0
    );

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
            Información actual obtenida de MySQL.
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
            "Reservaciones pendientes",
            reservations.pendientes ?? 0,
            "Pendientes de confirmación",
            "gold"
          )}

          ${metric(
            "Mantenimiento",
            maintenanceCount,
            "Habitaciones en mantenimiento",
            "red"
          )}

          ${metric(
            "Estancias activas",
            stays.activas ?? 0,
            "Huéspedes en estancia"
          )}

          ${metric(
            "Reportes de mantenimiento",
            maintenance.pendientes ?? 0,
            "Reportes abiertos",
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
          </div>
        </div>
      `
    );

  } catch (error) {
    errorPage("dashboard", "Dashboard", error);
  }
}

/* =========================
   HABITACIONES
========================= */

async function roomsPage() {
  loadingPage("rooms", "Matriz de Habitaciones");

  try {
    const result = await request("habitaciones.php");

    data.rooms = asArray(result, [
      "habitaciones", "rooms", "registros"
    ]);

    layout(
      "rooms",
      head(
        "Matriz de Habitaciones",
        "Estado y disponibilidad real de habitaciones",
        refreshButton()
      ) +
      `
        <div class="panel">
          <h2>Filtros de habitaciones</h2>

          <div class="filters">
            <div class="field">
              <label>Buscar</label>
              <input
                id="rq"
                placeholder="Número, tipo o piso"
                oninput="filterRooms()">
            </div>

            <div class="field">
              <label>Estado</label>
              <select id="roomStatus"
                onchange="filterRooms()">
                <option value="">Todos</option>
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
    errorPage("rooms", "Matriz de Habitaciones", error);
  }
}

function clearRoomFilters() {
  document.querySelector("#rq").value = "";
  document.querySelector("#roomStatus").value = "";
  filterRooms();
}

function filterRooms() {
  const query = (
    document.querySelector("#rq")?.value || ""
  ).toLowerCase();

  const status = document.querySelector(
    "#roomStatus"
  )?.value || "";

  const records = data.rooms.filter(room => {
    const text = [
      room.numero,
      room.tipo_habitacion,
      room.nombre_tipo,
      room.piso,
      room.estado
    ].join(" ").toLowerCase();

    return text.includes(query) &&
      (!status || room.estado === status);
  });

  const container = document.querySelector("#rg");
  const counter = document.querySelector("#rn");

  if (!container) return;

  container.innerHTML = records.length
    ? records.map(room => `
        <div class="room ${
          room.estado === "Ocupada" ? "occ" :
          room.estado === "Mantenimiento" ? "maint" : ""
        }">
          <div class="row">
            <h3>Hab. ${escapeHTML(room.numero)}</h3>
            ${badge(room.estado)}
          </div>

          <p>
            ${escapeHTML(
              room.tipo_habitacion ||
              room.nombre_tipo ||
              "Tipo no especificado"
            )}
            · Piso ${escapeHTML(room.piso)}
          </p>

          <p>
            ${escapeHTML(
              room.observaciones || "Sin observaciones"
            )}
          </p>

          <button class="link"
            onclick="roomDetails(${Number(room.id_habitacion)})">
            ◉ Ver detalle
          </button>
        </div>
      `).join("")
    : emptyState("No se encontraron habitaciones.");

  if (counter) {
    counter.textContent =
      `Mostrando ${records.length} de ${data.rooms.length} habitaciones`;
  }
}

function roomDetails(id) {
  const room = data.rooms.find(
    item => Number(item.id_habitacion) === id
  );

  if (!room) return;

  alert(
    `Habitación: ${room.numero}\n` +
    `Estado: ${room.estado}\n` +
    `Piso: ${room.piso}\n` +
    `Observaciones: ${room.observaciones || "Ninguna"}`
  );
}

/* =========================
   RESERVACIONES
========================= */

async function reservations() {
  loadingPage("reservations", "Gestión de Reservaciones");

  try {
    const result = await request("reservaciones.php");

    data.reservations = asArray(result, [
      "reservaciones", "registros"
    ]);

    const active = data.reservations.filter(item =>
      ["Pendiente", "Confirmada", "En estancia"]
        .includes(item.estado)
    ).length;

    const arrivals = data.reservations.filter(item =>
      String(item.fecha_entrada).slice(0, 10) === today() &&
      ["Pendiente", "Confirmada"].includes(item.estado)
    ).length;

    const staying = data.reservations.filter(item =>
      item.estado === "En estancia"
    ).length;

    layout(
      "reservations",
      head(
        "Gestión de Reservaciones",
        "Consulta y seguimiento de reservaciones",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric("Reservaciones activas", active)}
          ${metric("Llegadas hoy", arrivals)}
          ${metric("En estancia", staying)}
          ${metric(
            "Total de reservaciones",
            data.reservations.length
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
    errorPage("reservations", "Gestión de Reservaciones", error);
  }
}

function setFilter(status) {
  filters.reservations = status;
  filterRes();
}

function filterRes() {
  const query = (
    document.querySelector("#qres")?.value || ""
  ).toLowerCase();

  const records = data.reservations.filter(item => {
    const text = [
      item.id_reservacion,
      item.huesped,
      item.habitacion,
      item.estado
    ].join(" ").toLowerCase();

    return text.includes(query) &&
      (!filters.reservations ||
        item.estado === filters.reservations);
  });

  const body = document.querySelector("#resbody");
  if (!body) return;

  body.innerHTML = records.length
    ? records.map(item => `
        <tr>
          <td>
            <b>RES-${String(
              item.id_reservacion
            ).padStart(4, "0")}</b>
          </td>

          <td>
            ${escapeHTML(item.huesped || "Sin nombre")}
          </td>

          <td>
            ${escapeHTML(
              item.habitacion || "Sin habitación"
            )}
          </td>

          <td>
            Entrada: ${escapeHTML(item.fecha_entrada)}
            <br>
            Salida: ${escapeHTML(item.fecha_salida)}
          </td>

          <td>${badge(item.estado)}</td>

          <td>
            <button class="icon"
              onclick="reservationDetails(${
                Number(item.id_reservacion)
              })"
              title="Ver detalle">
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
  const item = data.reservations.find(
    record => Number(record.id_reservacion) === id
  );

  if (!item) return;

  alert(
    `Reservación: RES-${id}\n` +
    `Huésped: ${item.huesped || "Sin nombre"}\n` +
    `Habitación: ${item.habitacion || "Sin asignar"}\n` +
    `Entrada: ${item.fecha_entrada}\n` +
    `Salida: ${item.fecha_salida}\n` +
    `Estado: ${item.estado}\n` +
    `Observaciones: ${item.observaciones || "Ninguna"}`
  );
}

/* =========================
   HUÉSPEDES
========================= */

async function guestsPage() {
  loadingPage("guests", "Directorio de Huéspedes");

  try {
    const result = await request("huespedes.php");

    data.guests = asArray(result, [
      "huespedes", "registros"
    ]);

    const active = data.guests.filter(
      item => item.estado === "Activo"
    ).length;

    layout(
      "guests",
      head(
        "Directorio de Huéspedes",
        "Información de huéspedes registrada en MySQL",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric(
            "Huéspedes registrados",
            data.guests.length
          )}

          ${metric(
            "Huéspedes activos",
            active
          )}

          ${metric(
            "Huéspedes inactivos",
            data.guests.length - active
          )}
        </div>

        <div class="panel">
          <div class="field">
            <input
              id="qg"
              placeholder="Buscar nombre, correo, teléfono o identificación"
              oninput="filterGuests()">
          </div>
        </div>

        <div class="panel">
          <h2>Listado de huéspedes</h2>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre completo</th>
                  <th>Identificación</th>
                  <th>Contacto</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody id="gb"></tbody>
            </table>
          </div>
        </div>
      `
    );

    filterGuests();

  } catch (error) {
    errorPage("guests", "Directorio de Huéspedes", error);
  }
}

function filterGuests() {
  const query = (
    document.querySelector("#qg")?.value || ""
  ).toLowerCase();

  const records = data.guests.filter(item =>
    [
      fullName(item),
      item.identificacion,
      item.correo,
      item.telefono
    ].join(" ").toLowerCase().includes(query)
  );

  const body = document.querySelector("#gb");
  if (!body) return;

  body.innerHTML = records.length
    ? records.map(item => `
        <tr>
          <td>${escapeHTML(item.id_huesped)}</td>

          <td>
            <b>${escapeHTML(fullName(item))}</b>
          </td>

          <td>
            ${escapeHTML(item.identificacion)}
          </td>

          <td>
            ✉ ${escapeHTML(item.correo || "Sin correo")}
            <br>
            ☎ ${escapeHTML(item.telefono || "Sin teléfono")}
          </td>

          <td>${badge(item.estado)}</td>

          <td>
            <button class="icon"
              onclick="guestDetails(${Number(
                item.id_huesped
              )})"
              title="Ver detalle">
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
  const item = data.guests.find(
    record => Number(record.id_huesped) === id
  );

  if (!item) return;

  alert(
    `Nombre: ${fullName(item)}\n` +
    `Identificación: ${item.identificacion || ""}\n` +
    `Teléfono: ${item.telefono || ""}\n` +
    `Correo: ${item.correo || ""}\n` +
    `Dirección: ${item.direccion || ""}\n` +
    `Estado: ${item.estado || ""}`
  );
}

/* =========================
   MANTENIMIENTO
========================= */

async function maintenance() {
  loadingPage("maintenance", "Mantenimiento e Incidencias");

  try {
    const result = await request("mantenimientos.php");

    data.maintenance = asArray(result, [
      "mantenimientos", "registros"
    ]);

    const pending = data.maintenance.filter(item =>
      ["Reportado", "En proceso"].includes(item.estado)
    ).length;

    const finished = data.maintenance.filter(
      item => item.estado === "Finalizado"
    ).length;

    layout(
      "maintenance",
      head(
        "Control de Mantenimiento y Limpieza",
        "Seguimiento de incidencias registradas",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric(
            "Reportes registrados",
            data.maintenance.length
          )}

          ${metric(
            "Reportes pendientes",
            pending,
            "Requieren seguimiento",
            "gold"
          )}

          ${metric(
            "Reportes finalizados",
            finished,
            "Atendidos",
            "green"
          )}
        </div>

        <div class="panel">
          <h2>Incidencias y mantenimientos</h2>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Habitación</th>
                  <th>Motivo</th>
                  <th>Fecha de inicio</th>
                  <th>Estado</th>
                </tr>
              </thead>

              <tbody>
                ${
                  data.maintenance.length
                    ? data.maintenance.map(item => `
                      <tr>
                        <td>${escapeHTML(
                          item.id_mantenimiento
                        )}</td>

                        <td>${escapeHTML(
                          item.habitacion ||
                          item.numero ||
                          item.id_habitacion
                        )}</td>

                        <td>${escapeHTML(item.motivo)}</td>

                        <td>${escapeHTML(
                          item.fecha_inicio
                        )}</td>

                        <td>${badge(item.estado)}</td>
                      </tr>
                    `).join("")
                    : `
                      <tr>
                        <td colspan="5">
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
    errorPage(
      "maintenance",
      "Mantenimiento e Incidencias",
      error
    );
  }
}

/* =========================
   REPORTES
========================= */

async function reports() {
  loadingPage("reports", "Monitoreo y Reportes");

  try {
    const result = await request("dashboard.php");

    const rooms = result.habitaciones || {};
    const reservations = result.reservaciones || {};
    const stays = result.estancias || {};

    const total = Number(rooms.total || 0);
    const occupied = Number(rooms.ocupadas || 0);

    const occupancy = total > 0
      ? `${((occupied / total) * 100).toFixed(1)}%`
      : "0%";

    layout(
      "reports",
      head(
        "Monitoreo Operativo y Reportes",
        "Indicadores generales obtenidos de MySQL",
        refreshButton()
      ) +
      `
        <div class="cards">
          ${metric(
            "Ocupación actual",
            occupancy,
            "Habitaciones ocupadas respecto al total"
          )}

          ${metric(
            "Total de habitaciones",
            total
          )}

          ${metric(
            "Reservaciones registradas",
            reservations.total ?? 0
          )}

          ${metric(
            "Estancias finalizadas",
            stays.finalizadas ?? 0
          )}
        </div>

        <div class="panel">
          <h2>Información del reporte</h2>

          <p class="muted">
            Estos indicadores representan el estado
            general actual del hotel.
          </p>

          <p class="muted">
            Los reportes financieros y por períodos
            necesitan consultas adicionales.
          </p>
        </div>
      `
    );

  } catch (error) {
    errorPage("reports", "Monitoreo y Reportes", error);
  }
}

/* =========================
   CONFIGURACIÓN
========================= */

function settings() {
  const user = data.session || {};

  layout(
    "settings",
    head(
      "Configuración de Cuenta",
      "Información de la sesión del recepcionista"
    ) +
    `
      <div class="profile">
        <div class="panel">
          <h2>Información personal</h2>

          <div class="formgrid">
            <div class="field">
              <label>Nombre completo</label>
              <input
                value="${escapeHTML(user.nombre || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Usuario</label>
              <input
                value="${escapeHTML(user.usuario || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Correo electrónico</label>
              <input
                value="${escapeHTML(user.correo || "")}"
                readonly>
            </div>

            <div class="field">
              <label>Rol</label>
              <input value="Recepcionista" readonly>
            </div>

            <div class="field">
              <label>Sede</label>
              <input value="Kiin Saasil Hotel" readonly>
            </div>
          </div>

          <p class="muted">
            Los cambios de cuenta deben realizarse
            mediante una API autorizada.
          </p>
        </div>

        <div class="panel profile-side">
          <div class="avatar">R</div>

          <h2>${receptionistName()}</h2>
          <p class="muted">Recepcionista</p>

          ${badge(user.estado || "Activo")}

          <div class="details">
            <div>
              <span>Correo:</span>
              <b>${escapeHTML(
                user.correo || "Sin registrar"
              )}</b>
            </div>

            <div>
              <span>Rol:</span>
              <b>Recepcionista</b>
            </div>
          </div>
        </div>
      </div>
    `
  );
}

/* =========================
   SESIÓN Y NAVEGACIÓN
========================= */

async function loadSession() {
  const user = await request("session.php");

  if (user.rol !== "Recepcionista") {
    location.replace(
      user.rol === "Administrador"
        ? "../index.html"
        : "../login.html"
    );

    throw new Error("Acceso no autorizado.");
  }

  data.session = user;
}

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

  const selected = pages[section] || dashboard;

  await selected();
}

async function init() {
  try {
    await loadSession();

    window.addEventListener("hashchange", render);

    await render();

    setInterval(setClock, 30000);

  } catch (error) {
    console.error("Error de inicio:", error);

    if (!location.href.includes("login.html")) {
      notify(error.message);
    }
  }
}

init();
