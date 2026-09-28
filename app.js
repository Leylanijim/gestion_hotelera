
const app = document.getElementById("app");

const navItems = [
  ["dashboard","▦","Tablero"],["reservations","▣","Reservaciones"],["checkin","↪","Check-in / Check-out"],
  ["consumos","▤","Consumos"],["rooms","▰","Habitaciones"],["maintenance","⚒","Mantenimiento"],
  ["users","♙","Usuarios"],["reports","▥","Reportes"],["settings","⚙","Configuración"]
];

const data = {
  rooms:[
    ["101","Individual Simple","Piso 1 · 1 persona","Disponible","Limpia","$1,450"],
    ["102","Individual Simple","Piso 1 · 1 persona","Ocupada","Limpia","$1,450","Tomás Ahau Méndez","25 sep 2026"],
    ["103","Individual Simple","Piso 1 · 1 persona","Disponible","En limpieza","$1,450"],
    ["201","Doble","Piso 2 · 2 personas","Disponible","Limpia","$2,100"],
    ["202","Doble","Piso 2 · 2 personas","Ocupada","Limpia","$2,100","Nadia Itzá López","28 sep 2026"],
    ["203","Doble","Piso 2 · 2 personas","Mantenimiento","Inspección pendiente","$2,100","Revisión preventiva de aire acondicionado.","25 sep 2026"],
    ["301","Familiar","Piso 3 · 4 personas","Ocupada","Limpia","$3,200","Mariana Pech Canul","27 sep 2026"],
    ["302","Familiar","Piso 3 · 4 personas","Disponible","Limpia","$3,200"],
    ["303","Familiar","Piso 3 · 4 personas","Disponible","En limpieza","$3,200"],
    ["401","Suite","Piso 4 · 4 personas","Ocupada","Limpia","$5,800","Renata Balam Ruiz","28 sep 2026",true],
    ["402","Suite","Piso 4 · 4 personas","Fuera de servicio","Inspección pendiente","$5,800","Actualización programada de mobiliario premium.","28 sep 2026",true],
    ["403","Suite","Piso 4 · 4 personas","Disponible","Limpia","$5,800",null,null,true]
  ],
  users:[
    ["Ixchel Cocom","@icocom","ixchel.cocom@kiinsaasil.mx","999 000 0101","Administrador","Administración","Activo","24 sep 2026, 03:45 am.","30 ago 2026, 03:20 pm."],
    ["Emiliano Balam","@ebalam","emiliano.balam@kiinsaasil.mx","999 000 0102","Recepción","Recepción","Activo","23 sep 2026, 09:15 pm.","18 jul 2026, 02:00 pm."],
    ["Itzel May","@imay","itzel.may@kiinsaasil.mx","999 000 0106","Recepción","Recepción","Activo","24 sep 2026, 01:35 am.","25 ago 2026, 10:15 am."],
    ["Mauricio Pech","@mpech","mauricio.pech@kiinsaasil.mx","999 000 0107","Administrador","Administración","Activo","22 sep 2026, 07:00 pm.","05 sep 2026, 09:10 am."],
    ["Yatzil Canul","@ycanul","yatzil.canul@kiinsaasil.mx","999 000 0108","Mantenimiento","Mantenimiento","Inactivo","Sin acceso","21 may 2026, 12:00 pm."],
    ["Ahtziri Kú","@aku","ahtziri.ku@kiinsaasil.mx","999 000 0104","Mantenimiento","Mantenimiento","Inactivo","15 ago 2026, 04:10 pm.","10 jun 2026, 12:00 pm."],
    ["Noemí Poot","@npoot","noemi.poot@kiinsaasil.mx","999 000 0103","Ama de llaves","Operación","Activo","23 sep 2026, 06:40 pm.","01 sep 2026, 01:30 pm."],
    ["Jacinto Dzib","@jdzib","jacinto.dzib@kiinsaasil.mx","999 000 0105","Gerencia","Gerencia","Activo","22 sep 2026, 05:25 pm.","12 ago 2026, 11:00 am."]
  ],
  reservations:[
    ["KS-12041","Citlali Cocom","101","Estándar Cenote","24 sep 2026","27 sep 2026","2","En estancia","$6,438"],
    ["KS-12042","Gael Poot","201","Deluxe Selva","26 sep 2026","29 sep 2026","2","Confirmada","$9,222"]
  ],
  checkins:[
    ["KS-260924-01","Itzel Balam","201","Deluxe Selva","11:30","3","2","Confirmada","Saldo pendiente"],
    ["KS-260924-02","Emiliano Poot","101","Estándar Cenote","14:00","2","2","Lista para check-in","Saldo pendiente"],
    ["KS-260924-03","Ximena Cocom","301","Suite Jade","16:30","4","4","En revisión","Saldo pendiente"],
    ["KS-260924-04","Mauricio Dzib","102","Estándar Cenote","18:00","1","1","Bloqueada","Saldo pendiente"]
  ],
  consumptions:[
    ["24 sep 2026","KS-","Renata Balam","102","Transporte","1","$150","$150","Pendiente"],
    ["24 sep 2026","KS-","Renata Balam","102","Estacionamiento","1","$300","$300","Pendiente"],
    ["24 sep 2026","KS-","Nadia Poot","301","Lavandería","1","$280","$280","Confirmado"],
    ["24 sep 2026","KS-","Nadia Poot","301","Desayuno buffet","2","$280","$560","Confirmado"],
    ["23 sep 2026","KS-","María Dzib","204","Minibar","1","$220","$220","Anulado"]
  ]
};

function icon(s){return `<span class="icon">${s}</span>`}
function badge(text){
  const t=text.toLowerCase();
  let c=t.includes("activo")||t.includes("confirm")||t.includes("dispon")||t.includes("limpia")||t.includes("resuelta")||t.includes("revisado")||t.includes("en estancia") ? "green" :
        t.includes("pend")||t.includes("limpieza")||t.includes("revisión")||t.includes("inspección")||t.includes("apart") ? "yellow" :
        t.includes("cancel")||t.includes("bloque")||t.includes("inactivo")||t.includes("anulado")||t.includes("mantenimiento") ? "red" :
        t.includes("check-in")||t.includes("ocup")||t.includes("proceso") ? "blue" : "gray";
  return `<span class="badge ${c}">● ${text}</span>`;
}
function layout(page, title, body){
  return `<div class="app">
    <aside class="sidebar">
      <div class="brand"><div class="brand-mark">K</div><div><strong>KIIN SAASIL</strong><small>Operación hotelera</small></div></div>
      <nav class="nav">${navItems.map(n=>`<button class="${page===n[0]?'active':''}" onclick="go('${n[0]}')">${icon(n[1])}<span>${n[2]}</span></button>`).join("")}</nav>
      <div class="session"><b>Sesión activa</b>Administración hotelera</div>
    </aside>
    <main class="main">
      <header class="topbar"><h1>${title}</h1><input class="top-search" placeholder="Buscar folio, huésped o usuario"><div class="top-spacer"></div><span>●</span><div class="user"><div class="avatar">A</div><div><b>Administrador</b><small>Kiin Saasil</small></div></div></header>
      <div class="content">${body}</div>
    </main>
  </div>`;
}

function pageDashboard(){
 return layout("dashboard","Tablero de administración",`
  <div class="eyebrow">OPERACIÓN DIARIA</div><h2 class="page-title">Buenos días, Administrador</h2><p class="page-sub">Aquí tienes el pulso operativo de Kiin Saasil para tomar decisiones con claridad.</p>
  <div class="actions"><button class="btn">Registrar Walk-in</button><button class="btn primary" onclick="go('reservations')">Nueva reservación</button></div>
  <div class="grid cards-4">
   ${stat("Ocupación actual","33%","2 de 6 habitaciones ocupadas","green")}
   ${stat("Llegadas de hoy","2","↑ 12% vs. periodo anterior","green")}
   ${stat("Salidas de hoy","0","3 cuentas por cerrar","yellow")}
   ${stat("Ingresos del mes","$43,550","↑ 8.4% vs. periodo anterior","green")}
  </div>
  <section class="card section"><h2>Matriz de habitaciones</h2><div class="sub">Estado operativo, limpieza y disponibilidad por habitación.</div><div class="room-grid">${[
   ["108","Vista Jardín","Ocupada"],["204","Deluxe","Ocupada"],["301","Suite Mar ★","Limpieza"],["302","Suite Mar ★","Disponible"],["405","Premium","Mantenimiento"],["406","Premium","Disponible"]
  ].map(r=>`<div class="room"><div class="room-head"><h3>${r[0]}</h3>${badge(r[2])}</div><b style="display:block;margin-top:12px;font-size:12px">${r[1]}</b><p style="font-size:10px;color:#778986">Piso ${r[0].startsWith("4")?"4":"2"} · 2 huéspedes · Tarifa estándar</p><small>Operación hotelera</small> <a style="color:#079b6c;font-size:10px;font-weight:700">Cambiar estado</a></div>`).join("")}</div></section>
  <section class="card section"><h2>Reservaciones de hoy</h2><div class="sub">Consulta, confirma y procesa llegadas de forma centralizada.</div>${table(["Folio / huésped","Hab.","Entrada","Salida","Estado","Total","Acciones"],[
  ["KS-12043<br><strong>Itzel Perez</strong>","Suite Mar 301","2026-09-24","2026-09-27",badge("Pendiente"),"$12,600","Ver · Confirmar · Cancelar"],
  ["KS-12044<br><strong>Emiliano Poot</strong>","Deluxe 204","2026-09-24","2026-09-26",badge("Confirmada"),"$6,800","Ver · Check-in"],
  ["KS-12045<br><strong>Ximena Cocom</strong>","Vista Jardín 108","2026-09-22","2026-09-25",badge("En curso"),"$7,350","Ver"],
  ["KS-12046<br><strong>Mauricio Guerrero</strong>","Suite Mar 302","2026-09-25","2026-09-29",badge("Confirmada"),"$16,800","Ver · Check-in"]
  ])}</section>
  <div class="two">
  <section class="card section"><h2>Llegadas y salidas</h2><div class="sub">Check-ins pendientes</div><div class="activity-item"><b>Emiliano Poot</b><br><small>KS-12044 · Deluxe 204</small><span style="float:right;color:#079b6c;font-weight:700">Check-in</span></div><div class="sub" style="margin-top:20px">Check-outs pendientes</div><p style="color:#71817d;font-size:12px">Sin salidas pendientes.</p></section>
   <section class="card section"><h2>Ingresos del periodo</h2><div class="sub">Ingresos acumulados del periodo · MXN</div><div class="bars">${[38,58,48,80,62,88,70].map((h,i)=>`<div class="bar" style="height:${h}%"><b></b></div>`).join("")}</div><div class="bar-labels"><span>Alojamiento<br><b>$0</b></span><span>Servicios<br><b>$0</b></span><span>Recargos<br><b>$0</b></span><span>Descuentos<br><b>-$0</b></span></div></section>
  </div>
  <section class="card section"><h2>Incidencias y mantenimiento</h2><div class="sub">Incidencias operativas y bloqueos de habitaciones.</div><p style="color:#71817d;font-size:12px">Sin incidencias activas.</p></section>
 `);
}
function stat(a,b,c,cls=""){return `<div class="card stat"><div class="stat-label">${a}</div><div class="stat-value ${cls}">${b}</div><div class="stat-note">${c}</div></div>`}
function table(headers,rows){
 return `<div class="table-wrap"><table><thead><tr>${headers.map(h=>`<th>${h}</th>`).join("")}</tr></thead><tbody>${rows.map(r=>`<tr>${r.map(x=>`<td>${x}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
}

function pageReservations(){
 return layout("reservations","Reservaciones",`
  <div class="eyebrow">ADMINISTRACIÓN HOTELERA</div><h2 class="page-title">Reservaciones</h2><p class="page-sub">Consulta disponibilidad, administra estancias y da seguimiento a cada huésped.</p>
  <div class="actions"><button class="btn">◫</button><button class="btn primary">＋</button></div>
  <section class="card section" style="margin-top:5px"><div class="filters" style="grid-template-columns:1fr 1fr 1.3fr 1.2fr"><div class="field"><label>Rango de fechas</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>&nbsp;</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Buscar por folio o huésped</label><input placeholder="Ej. KS-10245 o Ixchel Cocom"></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div></div>
   <div class="filters" style="grid-template-columns:1fr 1fr 1fr;margin-top:10px"><div class="field"><label>Tipo de habitación</label><select><option>Todos los tipos</option></select></div><div class="field"><label>Piso</label><select><option>Todos los pisos</option></select></div><div class="field"><label>Canal de reserva</label><select><option>Todos los canales</option></select></div></div></section>
  <div class="grid cards-5" style="margin-top:22px">${stat("ACTIVAS","2","Confirmadas o en estancia","green")}${stat("PENDIENTES","1","Por confirmar","yellow")}${stat("CANCELADAS","1","Conservadas en historial","red")}${stat("APARTADAS","1","Bloqueos temporales","yellow")}${stat("LIBRES","1","Habitaciones operativas disponibles","")}</div>
  <section class="card section">${table(["FOLIO","HUÉSPED","HABITACIÓN","TIPO","ENTRADA","SALIDA","HUÉSPEDES","ESTADO","TOTAL MXN","ACCIONES"],data.reservations.map(r=>[r[0],r[1],r[2],r[3],r[4],r[5],r[6],badge(r[7]),`<strong>${r[8]}</strong>`,"◉  ✎  ↪"]))}</section>
 `);
}

function pageCheckin(){
 return layout("checkin","Check-in / Check-out",`
  <div class="eyebrow">OPERACIÓN</div><h2 class="page-title">Control de llegadas y salidas</h2><p class="page-sub">Centraliza las validaciones de recepción, los cobros y el estado operativo de cada habitación en una sola vista.</p>
  <div class="actions"><button class="btn primary">▥ Registrar Walk-in</button></div>
  <div class="grid cards-4">${stat("LLEGADAS PENDIENTES","4","↑ 12% vs. jornada anterior","green")}${stat("CHECK-INS PROCESADOS HOY","0","↑ 8% progreso de hoy","yellow")}${stat("SALIDAS PENDIENTES","2","requieren cierre de cuenta","green")}${stat("ESTANCIAS ACTIVAS","2","↑ 6% ocupación operativa","green")}</div>
  <section class="card section"><div class="tabs" style="margin:0 0 15px"><button class="active">Check-in 4</button><button>Check-out 2</button><button>Historial 3</button></div><h2>Llegadas de hoy</h2><div class="sub">Llegadas previstas para la fecha operativa seleccionada.</div>
   <div class="filters" style="grid-template-columns:repeat(5,1fr)">${["Estado de reservación","Tipo de habitación","Canal","Pago","Rango horario"].map(x=>`<div class="field"><label>${x}</label><select><option>Todos</option></select></div>`).join("")}</div>
   <div style="margin-top:18px">${table(["FOLIO","HUÉSPED","HABITACIÓN","TIPO","ENTRADA PREVISTA","NOCHES","HUÉSPEDES","RESERVACIÓN","PAGO","ACCIONES"],data.checkins.map(r=>[r[0],`<strong>${r[1]}</strong>`,r[2],r[3],r[4],r[5],r[6],badge(r[7]),badge(r[8]),"◉  ✓"]))}</div>
  </section>
 `);
}

function pageConsumptions(){
 return layout("consumos","Consumos",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Consumos y cargos</h2><p class="page-sub">Registra, revisa y controla los servicios adicionales asociados a cada estancia.</p>
  <div class="actions"><button class="btn">☷ Gestionar servicios</button><button class="btn primary">＋ Registrar consumo</button></div>
  <div class="grid cards-4">${stat("Consumos del periodo","$1,290","↗ 8.4% vs. periodo anterior","green")}${stat("Cargos pendientes","2","Por confirmar hoy","yellow")}${stat("Consumos de hoy","$1,290","↗ 12.0% vs. ayer","green")}${stat("Ticket promedio","$323","Estable vs. periodo anterior","yellow")}</div>
  <section class="card section"><div class="grid" style="grid-template-columns:repeat(4,1fr);padding-bottom:5px"><div><small>TOTAL DE CONSUMOS</small><h2>$1,290</h2></div><div><small>ALOJAMIENTO RELACIONADO</small><h2>$8,800 MXN</h2></div><div><small>IMPUESTOS ESTIMADOS</small><h2>$0</h2></div><div><small>CARGOS CONFIRMADOS</small><h2>$840</h2></div></div></section>
  <section class="card section"><h2>Registro de consumos</h2><div class="sub">Consulta los cargos registrados y aplica filtros para localizar una estancia.</div><div class="filters" style="grid-template-columns:1fr 1fr 1.4fr 1fr 1fr"><div class="field"><label>Desde</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Hasta</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Buscar</label><input placeholder="Folio, huésped o habitación"></div><div class="field"><label>Servicio</label><select><option>Todos los servicios</option></select></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div></div>
   <div style="margin-top:18px">${table(["FECHA","FOLIO","HUÉSPED","HABITACIÓN","CONCEPTO","CANTIDAD","PRECIO APLICADO","IMPORTE TOTAL","ESTADO","ACCIONES"],data.consumptions.map(r=>[r[0],r[1],`<strong>${r[2]}</strong>`,r[3],r[4],r[5],r[6],`<strong>${r[7]}</strong>`,badge(r[8]),"◉  ✎  ⊘  🗑"]))}</div>
  </section>
  <div class="two"><section class="card section"><h2>Estancias activas</h2><div class="sub">Agrega cargos directamente a las estancias actualmente en operación.</div><div class="two"><div class="activity-item"><b>Nadia Itzá López</b><br><small>Folio KS-260921-08 · Habitación 301</small><h3>$5,600 MXN</h3><b class="green">＋ Agregar consumo</b></div><div class="activity-item"><b>Tomás Ahau Méndez</b><br><small>Folio KS-260922-11 · Habitación 102</small><h3>$3,200 MXN</h3><b class="green">＋ Agregar consumo</b></div></div></section><section class="card section"><h2>Actividad reciente</h2><div class="activity">${["Cargo confirmado","Consumo registrado","Cargo anulado","Servicio actualizado"].map(x=>`<div class="activity-item">◉ &nbsp; <b>${x}</b><br><small>24 sep 2026 · Administrador</small></div>`).join("")}</div></section></div>
 `);
}

function pageRooms(){
 return layout("rooms","Habitaciones",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Panel de habitaciones</h2><p class="page-sub">Supervisa disponibilidad, ocupación, limpieza y bloqueos operativos.</p>
  <div class="actions"><button class="btn">☷ Gestionar tipos</button><button class="btn primary">＋ Agregar habitación</button></div>
  <div class="grid cards-5">${stat("Habitaciones totales","12","","blue")}${stat("Disponibles","6","","green")}${stat("Ocupadas","4","","blue")}${stat("En limpieza","2","","yellow")}${stat("Fuera de servicio","1","","red")}</div>
  <section class="card section"><h2>Disponibilidad de habitaciones</h2><div class="sub">Consulta el inventario y utiliza filtros para localizar una habitación.</div><div class="filters" style="grid-template-columns:1.2fr 1fr 1fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Número, huésped o tipo"></div><div class="field"><label>Tipo</label><select><option>Todos los tipos</option></select></div><div class="field"><label>Estado operativo</label><select><option>Todos los estados</option></select></div><div class="field"><label>Piso</label><select><option>Todos los pisos</option></select></div><div class="field"><label>Limpieza</label><select><option>Todas</option></select></div></div>
   <div class="room-grid" style="margin-top:18px">${data.rooms.map(r=>`<div class="room ${r[9]?'premium':''}"><div class="room-head"><div><h3>${r[0]}</h3><div class="type">${r[1]}</div><small>${r[2]}</small></div><span style="font-size:20px">▰</span></div><div style="margin-top:10px">${badge(r[3])} ${badge(r[4])}</div><div class="price"><span>Tarifa por noche</span><strong>${r[5]}</strong></div>${r[6]?`<div class="guest"><b>${r[6]}</b><br><small>${r[7]||""}</small></div>`:""}<div class="quick"><span>◉</span><span>↗</span><span>♨</span><span>⚒</span></div></div>`).join("")}</div>
  </section>
  <section class="section" style="padding:0;background:transparent;border:0;box-shadow:none"><h2 style="margin:0 0 14px">Resumen por tipo</h2><div class="grid cards-4">${[
    ["Individual Simple","3","2","1","33%","$1,450"],["Doble","3","2","1","33%","$2,100"],["Familiar","3","2","1","33%","$3,200"],["Suite","3","1","1","33%","$5,800"]
  ].map(x=>`<div class="card mini-kpi"><b>${x[0]}</b><small style="display:block;color:#71817d;margin-top:5px">Capacidad máxima</small><div style="margin-top:15px;font-size:10px">Total <b>${x[1]}</b> &nbsp; Disponibles <b>${x[2]}</b></div><div class="num">${x[5]}</div><small>Tarifa base</small></div>`).join("")}</div></section>
 `);
}

function pageMaintenance(){
 return layout("maintenance","Mantenimiento",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Mantenimiento e incidencias</h2><p class="page-sub">Programa, controla y da seguimiento a los trabajos de mantenimiento del hotel.</p>
  <div class="actions"><button class="btn">↶ Ver bitácora</button><button class="btn primary">＋ Registrar mantenimiento</button></div>
  <div class="grid cards-4">${stat("Mantenimientos activos","0","Trabajos en curso","blue")}${stat("Agendados","0","En próximos días","yellow")}${stat("Completados este mes","0","Resultado operativo","green")}${stat("Habitaciones bloqueadas","0","Afectan reservaciones","red")}</div>
  <section class="card section"><h2>Estados de mantenimiento</h2><div class="sub">Cada estado combina texto, icono y color para una operación accesible.</div><div style="display:flex;gap:10px;flex-wrap:wrap">${badge("Agendado")}${badge("En proceso")}${badge("Completado")}${badge("Cancelado")}</div></section>
  <section class="card section"><h2>Registro de mantenimiento</h2><div class="sub">Consulta, filtra y administra los trabajos programados y las incidencias del hotel.</div><div class="filters" style="grid-template-columns:1.3fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Folio, habitación o responsable"></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div><div class="field"><label>Prioridad</label><select><option>Todas las prioridades</option></select></div></div><div class="empty">⌕<strong>No hay mantenimientos que coincidan</strong>Prueba con otros filtros o registra un nuevo mantenimiento.</div></section>
  <div class="two"><section class="card section"><h2>Próximos mantenimientos</h2><div class="empty" style="padding:25px 10px">No hay mantenimientos próximos.</div></section><section class="card section"><h2>Habitaciones bloqueadas</h2><div class="empty" style="padding:25px 10px">No hay habitaciones bloqueadas en este momento.</div></section></div>
  <section class="card section"><h2>Actividad reciente</h2><div class="empty" style="padding:28px">Aún no hay actividad registrada.</div></section>
 `);
}

function pageUsers(){
 return layout("users","Usuarios",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Usuarios del sistema</h2><p class="page-sub">Gestiona accesos, roles y estado de las cuentas del personal.</p>
  <div class="actions"><button class="btn">⇩</button><button class="btn primary">＋</button></div>
  <div class="grid cards-4">${stat("Usuarios totales","8","","blue")}${stat("Activos","6","","green")}${stat("Inactivos","2","","red")}${stat("Administradores","2","","yellow")}</div>
  <div class="notice">🔒 &nbsp; Las contraseñas nunca se muestran. Usa el restablecimiento seguro para recuperar el acceso.</div>
  <section class="card section"><h2>Directorio de usuarios</h2><div class="sub">Consulta las cuentas, aplica filtros y administra acciones seguras de acceso.</div><div class="filters" style="grid-template-columns:1.2fr 1fr 1fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Nombre, usuario o correo"></div><div class="field"><label>Estado</label><select><option>Todos</option></select></div><div class="field"><label>Rol</label><select><option>Todos</option></select></div><div class="field"><label>Área</label><select><option>Todas</option></select></div><div class="field"><label>Último acceso</label><select><option>Cualquier fecha</option></select></div></div>
  <div style="margin-top:18px">${table(["NOMBRE COMPLETO","USUARIO","CORREO","TELÉFONO","ROL","ÁREA","ESTADO","ÚLTIMO ACCESO","ÚLTIMO CAMBIO DE CONTRASEÑA"],data.users.map(r=>[r[0],r[1],r[2],r[3],r[4],r[5],badge(r[6]),r[7],r[8]]))}</div></section>
  <section class="card section"><h2>Actividad reciente</h2><div class="empty" style="padding:25px">Aún no hay actividad registrada.</div></section>
 `);
}

function pageReports(){
 return layout("reports","Reportes",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Reportes mensuales</h2><p class="page-sub">Analiza el desempeño operativo de recepción y la actividad del hotel.</p>
  <section class="card section"><h2>Periodo y filtros</h2><div class="sub">Consulta consolidada de la operación del hotel.</div><div class="filters" style="grid-template-columns:repeat(7,1fr)">${["Mes","Año","Desde","Hasta","Recepcionista","Turno","Estado"].map((x,i)=>`<div class="field"><label>${x}</label>${i<2||i>3?`<select><option>${i===0?"Septiembre":i===1?"2026":"Todos"}</option></select>`:`<input placeholder="mm/dd/yyyy">`}</div>`).join("")}</div></section>
  <div class="grid cards-5">${stat("Check-ins atendidos","186","↑ 8% vs. mes anterior","green")}${stat("Check-outs procesados","172","↑ 5% vs. mes anterior","blue")}${stat("Reservaciones gestionadas","248","↑ 12% vs. mes anterior","yellow")}${stat("Incidencias resueltas","34","3 casos vs. mes anterior","red")}${stat("Calificación promedio","4.7/5","↑ 0.2 puntos","yellow")}</div>
  <div class="report-grid">
  <section class="card section"><h2>Actividad por recepcionista</h2><div class="sub">Check-ins, check-outs y reservaciones gestionadas.</div>${[["Ixchel Cocom",175],["Emiliano Balam",159],["Itzel May",122],["Mauricio Pech",150]].map(r=>`<div class="progress-row"><div class="progress-top"><span>${r[0]}</span><span>${r[1]} gestiones</span></div><div class="progress"><i style="width:${r[1]/1.9}%"></i></div></div>`).join("")}</section>
   <section class="card section chart-box"><h2>Tendencia mensual</h2><div class="sub">Evolución agregada de actividad disponible.</div><div class="bars">${[482,536,606].map((x,i)=>`<div class="bar" style="height:${[80,89,100][i]}%"><b>${x}</b></div>`).join("")}</div><div class="bar-labels"><span>Jul</span><span>Ago</span><span>Sep</span></div></section>
  </div>
  <div class="two"><section class="card section"><h2>Incidencias por categoría</h2>${[["Reservaciones",12],["Habitaciones",9],["Cobros",6],["Mantenimiento",5],["Huéspedes",4]].map(r=>`<div class="progress-row"><div class="progress-top"><span>${r[0]}</span><span>${r[1]}</span></div><div class="progress"><i style="width:${r[1]*8}%"></i></div></div>`).join("")}</section><section class="card section"><h2>Desempeño por turno</h2>${[["Matutino",221],["Vespertino",243],["Nocturno",142]].map(r=>`<div class="progress-row"><div class="progress-top"><span>${r[0]}</span><span>${r[1]} gestiones</span></div><div class="progress"><i style="width:${r[1]/2.5}%"></i></div></div>`).join("")}</section></div>
  <section class="card section"><h2>Reporte de recepcionistas</h2><div style="margin-top:14px">${table(["RECEPCIONISTA","TURNO","CHECK-INS","CHECK-OUTS","RESERVACIONES","INCIDENCIAS RESUELTAS","TIEMPO PROMEDIO","SERVICIO","ASISTENCIA","ESTADO"],[
    ["Leilany Marin","Matutino","54","49","72","9","6 min","4.8","100%",badge("Revisado")],
    ["Gabriel Montero","Matutino","54","49","72","9","6 min","4.8","100%",badge("Revisado")],
    ["Pedro Acosta","Vespertino","48","46","65","8","7 min","4.6","98%",badge("Pendiente")],
    ["Christopher Meib","Matutino","54","49","72","9","6 min","4.8","100%",badge("Revisado")],

  ])}</div></section>
 `);
}

function pageSettings(){
 return layout("settings","Configuración",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Configuración del sistema</h2><p class="page-sub">Administra la seguridad, las preferencias operativas y las funciones del hotel.</p>
  <div class="tabs"><button class="active">Cuenta y seguridad</button><button>Operación del hotel</button><button>Notificaciones</button><button>Usuarios y permisos</button><button>Integraciones</button><button>Bitácora</button></div>
  <div class="two">
   <section class="card section"><div style="display:flex;align-items:center;gap:13px"><div class="avatar">A</div><div><h2>Administrador</h2><div class="sub" style="margin:3px 0">Administrador del sistema</div></div><span class="badge green" style="margin-left:auto">Protegida</span></div><p style="margin-top:28px;font-size:12px"><b>Correo de cuenta</b><span style="float:right">admin@kiinsaasil.mx</span></p><p style="font-size:12px"><b>Último acceso</b><span style="float:right">Hoy, 08:15 h</span></p><p style="font-size:12px"><b>Sesiones activas</b><span style="float:right">2 sesiones</span></p><div class="notice">Las contraseñas nunca son visibles para los administradores. Se almacenan de forma segura y sólo pueden restablecerse mediante un flujo de recuperación.</div></section>
   <section class="card password"><h2>Cambiar contraseña</h2><div class="sub">Actualiza tus credenciales sin revelar información sensible.</div>${["Contraseña actual","Nueva contraseña","Confirmar nueva contraseña"].map((x,i)=>`<div class="field"><label>${x}</label><input type="password">${i===1?'<small style="display:block;color:#7b8c89;margin-top:5px">Ingresa una contraseña nueva.</small>':""}</div>`).join("")}<ul style="font-size:11px;color:#71817d;line-height:1.8;padding-left:18px"><li>Al menos 12 caracteres</li><li>Una mayúscula, una minúscula y un número</li><li>Un símbolo especial</li></ul><label style="display:block;background:#f2f7f5;border-radius:10px;padding:13px;font-size:12px"><input type="checkbox"> <b>Invalidar sesiones activas</b><br><span style="margin-left:24px;color:#71817d">Solicitará iniciar sesión nuevamente en otros dispositivos.</span></label><button class="btn primary" style="margin-top:16px">Actualizar contraseña</button></section>
  </div>
  <section class="card section"><h2>Acciones de seguridad</h2><div class="security-actions" style="margin-top:15px"><div class="security-card"><strong>Cerrar todas las sesiones</strong><small>Finaliza los accesos activos.</small></div><div class="security-card"><strong>Activar autenticación de dos factores</strong><small>Agrega verificación al inicio.</small></div><div class="security-card"><strong>Forzar cambio de contraseña al próximo inicio de sesión</strong><small>Estado: sin solicitud pendiente.</small></div></div></section>
 `);
}

function page(){
 const p=(location.hash.slice(1)||"dashboard");
 const pages={dashboard:pageDashboard,reservations:pageReservations,checkin:pageCheckin,consumos:pageConsumptions,rooms:pageRooms,maintenance:pageMaintenance,users:pageUsers,reports:pageReports,settings:pageSettings};
 app.innerHTML=pages[p]?pages[p]():pageDashboard();
 window.scrollTo(0,0);
}
function go(p){location.hash=p}
window.addEventListener("hashchange",page);
page();
