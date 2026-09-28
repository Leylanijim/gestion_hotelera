
const app = document.getElementById("app");

const navItems = [
  ["dashboard","","Tablero"],["reservations","","Reservaciones"],["checkin","","Check-in / Check-out"],
  ["consumos","","Consumos"],["rooms","","Habitaciones"],["maintenance","","Mantenimiento"],
  ["users","","Usuarios"],["reports","","Reportes"],["settings","","Configuración"]
];

const data = {
  rooms: [],
  users: [],
  reservations: [],
  checkins: [],
  consumptions: []
};

function icon(){return ""}
function stat(label,value,note,tone=""){
  return `<div class="card stat ${tone}"><div class="stat-label">${label}</div><div class="stat-value">${value}</div><div class="stat-note">${note||""}</div></div>`;
}
function table(headers,rows){
  if(!rows || !rows.length) return `<div class="empty"><strong>No hay registros cargados</strong>Los datos aparecerán cuando el backend consulte la base de datos.</div>`;
  return `<div class="table-wrap"><table><thead><tr>${headers.map(h=>`<th>${h}</th>`).join("")}</tr></thead><tbody>${rows.map(r=>`<tr>${r.map(c=>`<td>${c??""}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
}
function badge(text){
  const t=text.toLowerCase();
  let c=t.includes("activo")||t.includes("confirm")||t.includes("dispon")||t.includes("limpia")||t.includes("resuelta")||t.includes("revisado")||t.includes("en estancia") ? "green" :
        t.includes("pend")||t.includes("limpieza")||t.includes("revisión")||t.includes("inspección")||t.includes("apart") ? "yellow" :
        t.includes("cancel")||t.includes("bloque")||t.includes("inactivo")||t.includes("anulado")||t.includes("mantenimiento") ? "red" :
        t.includes("check-in")||t.includes("ocup")||t.includes("proceso") ? "blue" : "gray";
  return `<span class="badge ${c}">${text}</span>`;
}
function layout(page, title, body){
  return `<div class="app">
    <aside class="sidebar">
      <div class="brand"><div class="brand-mark">K</div><div><strong>KIIN SAASIL</strong><small>Operación hotelera</small></div></div>
      <nav class="nav">${navItems.map(n=>`<button class="${page===n[0]?'active':''}" onclick="go('${n[0]}')">${icon(n[1])}<span>${n[2]}</span></button>`).join("")}</nav>
      <div class="session"><b>Sesión activa</b>Administración hotelera</div>
    </aside>
    <main class="main">
      <header class="topbar"><h1>${title}</h1><input class="top-search" placeholder="Buscar folio, huésped o usuario"><div class="top-spacer"></div><button class="user user-control" title="Cuenta de administrador" aria-label="Cuenta de administrador"><div class="avatar">U</div><div><b>Usuario actual</b><small>Sesión</small></div></button></header>
      <div class="content">${body}</div>
    </main>
  </div>`;
}


function dashboardReservationView(guest,room,entry,exitDate,status,total){
  modal("Detalle de reservación",`<div class="detail-grid">
    <div><small>Huésped</small><strong>${guest}</strong></div>
    <div><small>Habitación</small><strong>${room}</strong></div>
    <div><small>Entrada</small><strong>${entry}</strong></div>
    <div><small>Salida</small><strong>${exitDate}</strong></div>
    <div><small>Estado</small>${badge(status)}</div>
    <div><small>Total</small><strong>${total}</strong></div>
  </div>`,"Cerrar",()=>closeModal());
}
function dashboardConfirmReservation(guest){ toast(`Reservación de ${guest} marcada como confirmada.`); }
function dashboardCancelReservation(guest){
  modal("Cancelar reservación",`<p>¿Deseas cancelar la reservación de <strong>${guest}</strong>?</p>
    <div class="field"><label>Motivo</label><input id="cancelReason" placeholder="Motivo de cancelación"></div>`,
    "Confirmar cancelación",()=>{
      const reason=document.getElementById("cancelReason")?.value.trim();
      if(!reason){toast("Indica un motivo para cancelar.","error");return;}
      toast(`Reservación de ${guest} cancelada en la interfaz.`);
      closeModal();
    });
}
function dashboardRoomState(room,current){
  modal("Cambiar estado de habitación",`<p>Habitación <strong>${room}</strong> · Estado actual: <strong>${current}</strong></p>
    <div class="field"><label>Nuevo estado</label><select id="roomState">
      <option>Disponible</option><option>Ocupada</option><option>Limpieza</option><option>Mantenimiento</option><option>Fuera de servicio</option>
    </select></div>`,"Guardar estado",()=>{
      const state=document.getElementById("roomState")?.value;
      toast(`Habitación ${room}: estado cambiado a ${state}.`);
      closeModal();
    });
}

function pageDashboard(){
 const occupied=data.rooms.filter(r=>String(r[3]||"").toLowerCase().includes("ocup")).length;
 const totalRooms=data.rooms.length;
 const reservations=data.reservations;
 return layout("dashboard","Tablero de administración",`
  <div class="eyebrow">OPERACIÓN DIARIA</div><h2 class="page-title">Panel operativo</h2>
  <p class="page-sub">Información cargada desde el sistema. Los registros aparecerán cuando el backend los entregue.</p>
  <div class="actions"><button class="btn" onclick="openWalkin()">Registrar Walk-in</button><button class="btn primary" onclick="openReservation()">Nueva reservación</button></div>
  <div class="grid cards-4">
   ${stat("Ocupación actual",totalRooms?Math.round(occupied/totalRooms*100)+"%":"0%",`${occupied} de ${totalRooms} habitaciones ocupadas`,"green")}
   ${stat("Llegadas de hoy","0","Sin datos cargados","green")}
   ${stat("Salidas de hoy","0","Sin datos cargados","yellow")}
   ${stat("Ingresos del mes","$0","Sin datos cargados","green")}
  </div>
  <section class="card section"><h2>Matriz de habitaciones</h2><div class="sub">Estado operativo, limpieza y disponibilidad por habitación.</div>
   ${data.rooms.length ? `<div class="room-grid">${data.rooms.map(r=>`<div class="room"><div class="room-head"><h3>${r[0]}</h3>${badge(r[3]||"Sin estado")}</div><b style="display:block;margin-top:12px;font-size:12px">${r[1]||""}</b><p style="font-size:10px;color:#778986">${r[2]||""}</p><small>Datos del sistema</small> <button class="btn btn-small" onclick="dashboardRoomState('${r[0]}','${r[3]||"Sin estado"}')">Cambiar estado</button></div>`).join("")}</div>` : `<div class="empty"><strong>No hay habitaciones cargadas</strong>Las habitaciones aparecerán cuando se carguen desde la base de datos.</div>`}
  </section>
  <section class="card section"><h2>Reservaciones de hoy</h2><div class="sub">Consulta, confirma y procesa llegadas de forma centralizada.</div>
   ${reservations.length ? table(["Folio / huésped","Hab.","Entrada","Salida","Estado","Total","Acciones"],reservations.map(r=>[r[0],r[1],r[4],r[5],badge(r[7]||"Sin estado"),r[8]||"$0",`<button class="btn btn-small" onclick="dashboardReservationView('${r[1]}','${r[2]}','${r[4]}','${r[5]}','${r[7]||"Sin estado"}','${r[8]||"$0"}')">Ver</button>`])) : `<div class="empty"><strong>No hay reservaciones cargadas</strong>Las reservaciones aparecerán cuando el backend las consulte desde la base de datos.</div>`}
  </section>
  <div class="two">
   <section class="card section"><h2>Llegadas y salidas</h2><div class="sub">Check-ins pendientes</div>
    <div class="empty" style="padding:25px 10px">No hay llegadas cargadas.</div>
    <div class="sub" style="margin-top:20px">Check-outs pendientes</div><div class="empty" style="padding:25px 10px">No hay salidas cargadas.</div>
   </section>
   <section class="card section"><h2>Ingresos del periodo</h2><div class="sub">Ingresos acumulados del periodo · MXN</div>
    <div class="empty" style="padding:35px 10px">No hay datos de ingresos cargados.</div>
   </section>
  </div>
  <section class="card section"><h2>Incidencias y mantenimiento</h2><div class="sub">Incidencias operativas y bloqueos de habitaciones.</div><div class="empty" style="padding:25px 10px">No hay incidencias cargadas.</div></section>
 `);
}

function pageReservations(){
 return layout("reservations","Reservaciones",`
  <div class="eyebrow">ADMINISTRACIÓN HOTELERA</div><h2 class="page-title">Reservaciones</h2><p class="page-sub">Consulta disponibilidad, administra estancias y da seguimiento a cada huésped.</p>
  <div class="actions"><button class="btn">Filtrar</button><button class="btn primary">Nueva reserva</button></div>
  <section class="card section" style="margin-top:5px"><div class="filters" style="grid-template-columns:1fr 1fr 1.3fr 1.2fr"><div class="field"><label>Rango de fechas</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>&nbsp;</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Buscar por folio o huésped</label><input placeholder="Folio o huésped"></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div></div>
   <div class="filters" style="grid-template-columns:1fr 1fr 1fr;margin-top:10px"><div class="field"><label>Tipo de habitación</label><select><option>Todos los tipos</option></select></div><div class="field"><label>Piso</label><select><option>Todos los pisos</option></select></div><div class="field"><label>Canal de reserva</label><select><option>Todos los canales</option></select></div></div></section>
  <div class="grid cards-5" style="margin-top:22px">${stat("ACTIVAS","0","Datos cargados","green")}${stat("PENDIENTES","0","Datos cargados","yellow")}${stat("CANCELADAS","0","Datos cargados","red")}${stat("APARTADAS","0","Datos cargados","yellow")}${stat("LIBRES","0","Datos cargados","")}</div>
  <section class="card section">${table(["FOLIO","HUÉSPED","HABITACIÓN","TIPO","ENTRADA","SALIDA","HUÉSPEDES","ESTADO","TOTAL MXN","ACCIONES"],data.reservations.map(r=>[r[0],r[1],r[2],r[3],r[4],r[5],r[6],badge(r[7]),`<strong>${r[8]}</strong>`,`"<button class=\"btn\">Ver</button> <button class=\"btn\">Editar</button> <button class=\"btn\" onclick=\"openCheckout()\">Check-out</button>"`]))}</section>
 `);
}


function viewCheckinDetails(folio, guest, room, type, entry){
  modal("Detalle de llegada",`
    <div class="detail-grid">
      <div><small>Folio</small><strong>${folio}</strong></div>
      <div><small>Huésped</small><strong>${guest}</strong></div>
      <div><small>Habitación</small><strong>${room}</strong></div>
      <div><small>Tipo</small><strong>${type}</strong></div>
      <div><small>Entrada prevista</small><strong>${entry}</strong></div>
      <div><small>Estado</small>${badge("Confirmada")}</div>
    </div>
  `,"Cerrar",()=>closeModal());
}
function switchCheckinTab(tab){
  const section=document.getElementById("checkin-workspace");
  if(!section)return;
  section.querySelectorAll(".op-tab").forEach(b=>b.classList.toggle("active",b.dataset.tab===tab));
  const title=section.querySelector("#op-title"), sub=section.querySelector("#op-sub"), body=section.querySelector("#op-body");
  if(tab==="checkin"){
    title.textContent="Llegadas de hoy";
    sub.textContent="Llegadas previstas para la fecha operativa seleccionada.";
    body.innerHTML=checkinRows();
  } else if(tab==="checkout"){
    title.textContent="Salidas pendientes";
    sub.textContent="Estancias que requieren cierre de cuenta y liberación de habitación.";
    body.innerHTML=checkoutRows();
  } else {
    title.textContent="Historial";
    sub.textContent="Movimientos recientes de entradas y salidas.";
    body.innerHTML=historyRows();
  }
}
function checkinRows(){
 return `<div class="filters" style="grid-template-columns:repeat(5,1fr)">
  ${["Estado de reservación","Tipo de habitación","Canal","Pago","Rango horario"].map(x=>`<div class="field"><label>${x}</label><select><option>Todos</option></select></div>`).join("")}
 </div>
 <div style="margin-top:18px">${table(["FOLIO","HUÉSPED","HABITACIÓN","TIPO","ENTRADA PREVISTA","NOCHES","HUÉSPEDES","RESERVACIÓN","PAGO","ACCIONES"],
 data.checkins.map(r=>[r[0],`<strong>${r[1]}</strong>`,r[2],r[3],r[4],r[5],r[6],badge(r[7]),badge(r[8]),
 `<button class="btn" onclick="openCheckin()">Check-in</button>
  <button class="btn" onclick="viewCheckinDetails('${r[0]}','${r[1]}','${r[2]}','${r[3]}','${r[4]}')">Ver</button>`]))}</div>`;
}
function checkoutRows(){
 const rows=[
  ["KS-260923-05","Mariana Solís","101","Estándar Cenote","12:00","3","2"],
  ["KS-260923-06","Diego Carrillo","201","Deluxe Selva","12:00","3","2"]
 ];
 return `<div style="margin-top:18px">${table(["FOLIO","HUÉSPED","HABITACIÓN","TIPO","SALIDA","NOCHES","HUÉSPEDES","ESTADO","ACCIONES"],
 rows.map(r=>[r[0],`<strong>${r[1]}</strong>`,r[2],r[3],r[4],r[5],r[6],badge("Pendiente"),
 `<button class="btn primary" onclick="openCheckout()">Check-out</button>
  <button class="btn" onclick="viewCheckinDetails('${r[0]}','${r[1]}','${r[2]}','${r[3]}','${r[4]}')">Ver</button>`]))}</div>`;
}
function historyRows(){
 return `<div class="empty" style="margin-top:18px"><strong>No hay historial cargado</strong>El historial aparecerá cuando el backend entregue los movimientos registrados.</div>`;
}

function pageCheckin(){
 return layout("checkin","Check-in / Check-out",`
  <div class="eyebrow">OPERACIÓN</div>
  <h2 class="page-title">Control de llegadas y salidas</h2>
  <p class="page-sub">Centraliza las validaciones de recepción, los cobros y el estado operativo de cada habitación en una sola vista.</p>
  <div class="actions"><button class="btn primary" onclick="openWalkin()">Registrar Walk-in</button></div>
  <div class="grid cards-4">
    ${stat("LLEGADAS PENDIENTES",String(data.checkins.length),"Datos cargados","green")}
    ${stat("CHECK-INS PROCESADOS HOY","0","Sin datos cargados","yellow")}
    ${stat("SALIDAS PENDIENTES","0","Sin datos cargados","green")}
    ${stat("ESTANCIAS ACTIVAS","0","Sin datos cargados","green")}
  </div>

  <section class="card section" id="checkin-workspace">
    <div class="tabs" style="margin:0 0 20px">
      <button class="op-tab active" data-tab="checkin" onclick="switchCheckinTab('checkin')">Check-in</button>
      <button class="op-tab" data-tab="checkout" onclick="switchCheckinTab('checkout')">Check-out</button>
      <button class="op-tab" data-tab="history" onclick="switchCheckinTab('history')">Historial</button>
    </div>
    <h2 id="op-title">Llegadas de hoy</h2>
    <div class="sub" id="op-sub">Llegadas previstas para la fecha operativa seleccionada.</div>
    <div id="op-body">${checkinRows()}</div>
  </section>
 `);
}

function pageConsumptions(){
 return layout("consumos","Consumos",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Consumos y cargos</h2><p class="page-sub">Registra, revisa y controla los servicios adicionales asociados a cada estancia.</p>
  <div class="actions"><button class="btn">Gestionar servicios</button><button class="btn primary">Registrar consumo</button></div>
  <div class="grid cards-4">${stat("Consumos del periodo","$0","Datos cargados","green")}${stat("Cargos pendientes","0","Datos cargados","yellow")}${stat("Consumos de hoy","$0","Datos cargados","green")}${stat("Ticket promedio","$0","Sin datos cargados","yellow")}</div>
  <section class="card section"><div class="grid" style="grid-template-columns:repeat(4,1fr);padding-bottom:5px"><div><small>TOTAL DE CONSUMOS</small><h2>$0</h2></div><div><small>ALOJAMIENTO RELACIONADO</small><h2>$0 MXN</h2></div><div><small>IMPUESTOS ESTIMADOS</small><h2>$0</h2></div><div><small>CARGOS CONFIRMADOS</small><h2>$0</h2></div></div></section>
  <section class="card section"><h2>Registro de consumos</h2><div class="sub">Consulta los cargos registrados y aplica filtros para localizar una estancia.</div><div class="filters" style="grid-template-columns:1fr 1fr 1.4fr 1fr 1fr"><div class="field"><label>Desde</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Hasta</label><input placeholder="mm/dd/yyyy"></div><div class="field"><label>Buscar</label><input placeholder="Folio, huésped o habitación"></div><div class="field"><label>Servicio</label><select><option>Todos los servicios</option></select></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div></div>
   <div style="margin-top:18px">${table(["FECHA","FOLIO","HUÉSPED","HABITACIÓN","CONCEPTO","CANTIDAD","PRECIO APLICADO","IMPORTE TOTAL","ESTADO","ACCIONES"],data.consumptions.map(r=>[r[0],r[1],`<strong>${r[2]}</strong>`,r[3],r[4],r[5],r[6],`<strong>${r[7]}</strong>`,badge(r[8]),"<button class=\"btn btn-small\" onclick=\"genericAction(\'Ver consumo\')\">Ver</button> <button class=\"btn btn-small\" onclick=\"genericAction(\'Editar consumo\')\">Editar</button> <button class=\"btn btn-small danger\" onclick=\"genericAction(\'Anular consumo\')\">Anular</button>"]))}</div>
  </section>
  <div class="two"><section class="card section"><h2>Estancias activas</h2><div class="sub">Agrega cargos directamente a las estancias actualmente en operación.</div><div class="empty" style="padding:30px 10px">No hay estancias cargadas.</div></section><section class="card section"><h2>Actividad reciente</h2><div class="empty" style="padding:30px 10px">No hay actividad cargada.</div></section></div>
 `);
}

function pageRooms(){
 return layout("rooms","Habitaciones",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Panel de habitaciones</h2><p class="page-sub">Supervisa disponibilidad, ocupación, limpieza y bloqueos operativos.</p>
  <div class="actions"><button class="btn">Gestionar tipos</button><button class="btn primary">Agregar habitación</button></div>
  <div class="grid cards-5">${stat("Habitaciones totales",String(data.rooms.length),"Datos cargados","blue")}${stat("Disponibles",String(data.rooms.filter(r=>r[3]==="Disponible").length),"Datos cargados","green")}${stat("Ocupadas",String(data.rooms.filter(r=>r[3]==="Ocupada").length),"Datos cargados","blue")}${stat("En limpieza",String(data.rooms.filter(r=>r[3]==="En limpieza").length),"Datos cargados","yellow")}${stat("Fuera de servicio",String(data.rooms.filter(r=>r[3]==="Fuera de servicio").length),"Datos cargados","red")}</div>
  <section class="card section"><h2>Disponibilidad de habitaciones</h2><div class="sub">Consulta el inventario y utiliza filtros para localizar una habitación.</div><div class="filters" style="grid-template-columns:1.2fr 1fr 1fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Número, huésped o tipo"></div><div class="field"><label>Tipo</label><select><option>Todos los tipos</option></select></div><div class="field"><label>Estado operativo</label><select><option>Todos los estados</option></select></div><div class="field"><label>Piso</label><select><option>Todos los pisos</option></select></div><div class="field"><label>Limpieza</label><select><option>Todas</option></select></div></div>
   <div class="room-grid" style="margin-top:18px">${data.rooms.map(r=>`<div class="room ${r[9]?'premium':''}"><div class="room-head"><div><h3>${r[0]}</h3><div class="type">${r[1]}</div><small>${r[2]}</small></div></div><div style="margin-top:10px">${badge(r[3])} ${badge(r[4])}</div><div class="price"><span>Tarifa por noche</span><strong>${r[5]}</strong></div>${r[6]?`<div class="guest"><b>${r[6]}</b><br><small>${r[7]||""}</small></div>`:""}<div class="quick"><button onclick="genericAction('Ver habitación')">Ver</button><button onclick="genericAction('Cambiar estado')">Estado</button><button onclick="genericAction('Limpieza')">Limpieza</button><button onclick="openMaintenance()">Mantenimiento</button></div></div>`).join("")}</div>
  </section>
  <section class="section" style="padding:0;background:transparent;border:0;box-shadow:none"><h2 style="margin:0 0 14px">Resumen por tipo</h2><div class="grid cards-4">${data.rooms.length ? `<div class="grid cards-4">${Object.entries(data.rooms.reduce((a,r)=>{a[r[1]||"Sin tipo"]=(a[r[1]||"Sin tipo"]||0)+1;return a},{})).map(([type,count])=>`<div class="card mini-kpi"><b>${type}</b><div style="margin-top:15px;font-size:10px">Total <b>${count}</b></div></div>`).join("")}</div>` : `<div class="empty">No hay habitaciones cargadas.</div>`}</div></section>
 `);
}

function pageMaintenance(){
 return layout("maintenance","Mantenimiento",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Mantenimiento e incidencias</h2><p class="page-sub">Programa, controla y da seguimiento a los trabajos de mantenimiento del hotel.</p>
  <div class="actions"><button class="btn">Ver bitácora</button><button class="btn primary">Registrar mantenimiento</button></div>
  <div class="grid cards-4">${stat("Mantenimientos activos","0","Trabajos en curso","blue")}${stat("Agendados","0","En próximos días","yellow")}${stat("Completados este mes","0","Resultado operativo","green")}${stat("Habitaciones bloqueadas","0","Afectan reservaciones","red")}</div>
  <section class="card section"><h2>Estados de mantenimiento</h2><div class="sub">Cada estado combina texto, icono y color para una operación accesible.</div><div style="display:flex;gap:10px;flex-wrap:wrap">${badge("Agendado")}${badge("En proceso")}${badge("Completado")}${badge("Cancelado")}</div></section>
  <div class="card section"><h2>Registro de mantenimiento</h2><div class="sub">Consulta, filtra y administra los trabajos programados y las incidencias del hotel.</div><div class="filters" style="grid-template-columns:1.3fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Folio, habitación o responsable"></div><div class="field"><label>Estado</label><select><option>Todos los estados</option></select></div><div class="field"><label>Prioridad</label><select><option>Todas las prioridades</option></select></div></div><div class="empty"><strong>No hay mantenimientos que coincidan</strong>Prueba con otros filtros o registra un nuevo mantenimiento.</div></section>
  <div class="two"><section class="card section"><h2>Próximos mantenimientos</h2><div class="empty" style="padding:25px 10px">No hay mantenimientos próximos.</div></section><section class="card section"><h2>Habitaciones bloqueadas</h2><div class="empty" style="padding:25px 10px">No hay habitaciones bloqueadas en este momento.</div></section></div>
  <section class="card section"><h2>Actividad reciente</h2><div class="empty" style="padding:28px">Aún no hay actividad registrada.</div></section>
 `);
}

function pageUsers(){
 return layout("users","Usuarios",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Usuarios del sistema</h2><p class="page-sub">Gestiona accesos, roles y estado de las cuentas del personal.</p>
  <div class="actions"><button class="btn">Exportar</button><button class="btn primary">Nuevo usuario</button></div>
  <div class="grid cards-4">${stat("Usuarios totales",String(data.users.length),"Datos cargados","blue")}${stat("Activos",String(data.users.filter(r=>r[6]==="Activo").length),"Datos cargados","green")}${stat("Inactivos",String(data.users.filter(r=>r[6]==="Inactivo").length),"Datos cargados","red")}${stat("Administradores",String(data.users.filter(r=>r[4]==="Administrador").length),"Datos cargados","yellow")}</div>
  <div class="notice">Las contraseñas nunca se muestran. Usa el restablecimiento seguro para recuperar el acceso.</div>
  <section class="card section"><h2>Directorio de usuarios</h2><div class="sub">Consulta las cuentas, aplica filtros y administra acciones seguras de acceso.</div><div class="filters" style="grid-template-columns:1.2fr 1fr 1fr 1fr 1fr"><div class="field"><label>Buscar</label><input placeholder="Nombre, usuario o correo"></div><div class="field"><label>Estado</label><select><option>Todos</option></select></div><div class="field"><label>Rol</label><select><option>Todos</option></select></div><div class="field"><label>Área</label><select><option>Todas</option></select></div><div class="field"><label>Último acceso</label><select><option>Cualquier fecha</option></select></div></div>
  <div style="margin-top:18px">${table(["NOMBRE COMPLETO","USUARIO","CORREO","TELÉFONO","ROL","ÁREA","ESTADO","ÚLTIMO ACCESO","ÚLTIMO CAMBIO DE CONTRASEÑA"],data.users.map(r=>[r[0],r[1],r[2],r[3],r[4],r[5],badge(r[6]),r[7],r[8]]))}</div></section>
  <section class="card section"><h2>Actividad reciente</h2><div class="empty" style="padding:25px">Aún no hay actividad registrada.</div></section>
 `);
}

function pageReports(){
 return layout("reports","Reportes",`
  <div class="eyebrow">ADMINISTRACIÓN</div><h2 class="page-title">Reportes mensuales</h2><p class="page-sub">Analiza el desempeño operativo con información proveniente de la base de datos.</p>
  <section class="card section"><h2>Periodo y filtros</h2><div class="sub">Selecciona el periodo y los filtros para consultar información real.</div><div class="filters" style="grid-template-columns:repeat(7,1fr)">${["Mes","Año","Desde","Hasta","Recepcionista","Turno","Estado"].map((x,i)=>`<div class="field"><label>${x}</label>${i<2||i>3?`<select><option>${i<2?"Seleccionar":"Todos"}</option></select>`:`<input placeholder="mm/dd/yyyy">`}</div>`).join("")}</div></section>
  <div class="grid cards-5">${stat("Check-ins atendidos","0","Sin datos cargados","green")}${stat("Check-outs procesados","0","Sin datos cargados","blue")}${stat("Reservaciones gestionadas","0","Sin datos cargados","yellow")}${stat("Incidencias resueltas","0","Sin datos cargados","red")}${stat("Calificación promedio","—","Sin datos cargados","yellow")}</div>
  <div class="report-grid">
   <section class="card section"><h2>Actividad por recepcionista</h2><div class="sub">Datos agrupados desde la base de datos.</div><div class="empty">No hay datos cargados.</div></section>
   <section class="card section chart-box"><h2>Tendencia mensual</h2><div class="sub">Evolución agregada de actividad.</div><div class="empty">No hay datos cargados.</div></section>
  </div>
  <div class="two"><section class="card section"><h2>Incidencias por categoría</h2><div class="empty">No hay datos cargados.</div></section><section class="card section"><h2>Desempeño por turno</h2><div class="empty">No hay datos cargados.</div></section></div>
  <section class="card section"><h2>Reporte de recepcionistas</h2><div class="empty" style="margin-top:14px">No hay registros cargados.</div></section>
 `);
}


function toast(message, type="ok"){
  let el=document.getElementById("toast");
  if(!el){
    el=document.createElement("div");
    el.id="toast";
    el.style.cssText="position:fixed;right:24px;bottom:24px;z-index:100;background:#075b58;color:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.18);font-size:13px;font-weight:700;opacity:0;transform:translateY(10px);transition:.2s";
    document.body.appendChild(el);
  }
  el.textContent=message; el.style.background=type==="error"?"#b94b4b":"#075b58";
  requestAnimationFrame(()=>{el.style.opacity="1";el.style.transform="translateY(0)"});
  clearTimeout(window.__toastTimer);
  window.__toastTimer=setTimeout(()=>{el.style.opacity="0";el.style.transform="translateY(10px)"},2600);
}
function settingsTab(name){
  document.querySelectorAll(".settings-tabs button").forEach(b=>b.classList.toggle("active",b.dataset.tab===name));
  document.querySelectorAll(".settings-panel").forEach(p=>p.style.display=p.dataset.panel===name?"block":"none");
}
function changePassword(){
  const f=[...document.querySelectorAll("#passwordForm input[type=password]")];
  if(f.some(x=>!x.value)) return toast("Completa todos los campos.","error");
  const [current,nueva,confirm]=f;
  if(nueva.value.length<12) return toast("La nueva contraseña debe tener al menos 12 caracteres.","error");
  if(!/[A-Z]/.test(nueva.value)||!/[a-z]/.test(nueva.value)||!/[0-9]/.test(nueva.value)||!/[^A-Za-z0-9]/.test(nueva.value))
    return toast("Debe incluir mayúscula, minúscula, número y símbolo.","error");
  if(nueva.value!==confirm.value) return toast("Las contraseñas nuevas no coinciden.","error");
  toast("Contraseña validada. El guardado real lo hará PHP/MySQL.");
  f.forEach(x=>x.value="");
}
function securityAction(action){
  if(action==="sessions") toast("Sesiones marcadas para cierre. PHP deberá ejecutar el cierre real.");
  if(action==="2fa"){
    const b=document.getElementById("twofaBtn"); b.classList.toggle("primary");
    b.querySelector("strong").textContent=b.classList.contains("primary")?"Autenticación de dos factores activada":"Activar autenticación de dos factores";
    toast("Estado de 2FA cambiado en la interfaz.");
  }
  if(action==="force"){
    const b=document.getElementById("forceBtn"); b.classList.toggle("primary");
    b.querySelector("strong").textContent=b.classList.contains("primary")?"Cambio obligatorio activado":"Forzar cambio de contraseña al próximo inicio de sesión";
    toast("Estado del cambio obligatorio cambiado.");
  }
}


function modal(title, content, submitText="Guardar", onSubmit=null){
  document.getElementById("app-modal")?.remove();
  const m=document.createElement("div");
  m.id="app-modal";
  m.innerHTML=`<div class="modal-backdrop" onclick="if(event.target===this)closeModal()">
    <div class="modal-card">
      <div class="modal-head"><h2>${title}</h2><button class="modal-x" onclick="closeModal()">×</button></div>
      <div class="modal-body">${content}</div>
      <div class="modal-foot"><button class="btn" onclick="closeModal()">Cancelar</button><button class="btn primary" id="modal-submit">${submitText}</button></div>
    </div></div>`;
  document.body.appendChild(m);
  document.getElementById("modal-submit").onclick=()=>{ if(onSubmit) onSubmit(m); else {toast("Formulario validado. El guardado real se conectará con PHP."); closeModal();} };
}
function closeModal(){document.getElementById("app-modal")?.remove()}
function formFields(fields){
  return `<div class="modal-form">${fields.map(f=>`<div class="field"><label>${f[0]}</label>${f[2]==="select"?`<select>${(f[3]||["Seleccionar"]).map(x=>`<option>${x}</option>`).join("")}</select>`:`<input type="${f[2]||"text"}" placeholder="${f[1]||""}">`}</div>`).join("")}</div>`;
}
function openWalkin(){
  modal("Registrar Walk-in",formFields([
    ["Nombre del huésped","Nombre completo"],["Correo","correo@ejemplo.com","email"],["Teléfono","999 000 0000"],["Habitación","", "select",[]],["Entrada","", "date"],["Salida","", "date"],["Huéspedes","2","number"]
  ]),"Registrar Walk-in");
}
function openReservation(){
  modal("Nueva reservación",formFields([
    ["Nombre del huésped","Nombre completo"],["Correo","correo@ejemplo.com","email"],["Teléfono","999 000 0000"],["Habitación","", "select",[]],["Fecha de entrada","", "date"],["Fecha de salida","", "date"],["Huéspedes","2","number"],["Canal de reserva","", "select",["Directo","Booking","WhatsApp","Agencia"]]
  ]),"Crear reservación");
}
function openCheckin(){
  modal("Procesar Check-in",formFields([
    ["Folio",""],["Huésped","Nombre completo"],["Habitación","", "select",[]],["Documento de identidad","INE / Pasaporte"],["Forma de pago","", "select",["Tarjeta","Efectivo","Transferencia","Pendiente"]]
  ]),"Confirmar Check-in");
}
function openCheckout(){
  modal("Procesar Check-out",formFields([
    ["Folio",""],["Huésped","Nombre del huésped"],["Habitación",""],["Método de pago","", "select",["Tarjeta","Efectivo","Transferencia"]],["Observaciones","Opcional"]
  ]),"Confirmar Check-out");
}
function openConsumption(){
  modal("Registrar consumo",formFields([
    ["Huésped","", "select",[]],["Habitación","", "select",[]],["Servicio","", "select",["Transporte","Estacionamiento","Lavandería","Desayuno buffet","Minibar"]],["Cantidad","1","number"],["Precio","$0"]
  ]),"Registrar consumo");
}
function openMaintenance(){
  modal("Registrar mantenimiento",formFields([
    ["Habitación o área","Ej. 202 / Alberca"],["Tipo","", "select",["Preventivo","Correctivo","Inspección"]],["Prioridad","", "select",["Baja","Media","Alta"]],["Responsable","Nombre del responsable"],["Fecha programada","", "date"],["Descripción","Describe el trabajo"]
  ]),"Registrar mantenimiento");
}
function openUser(){
  modal("Nuevo usuario",formFields([
    ["Nombre completo",""],["Usuario",""],["Correo","","email"],["Teléfono",""],["Rol","", "select",["Administrador","Gerencia","Recepción","Mantenimiento","Ama de llaves"]],["Área","", "select",["Administración","Recepción","Mantenimiento","Operación"]]
  ]),"Crear usuario");
}
function openRoom(){
  modal("Agregar habitación",formFields([
    ["Número","Ej. 404"],["Tipo","", "select",["Individual Simple","Doble","Familiar","Suite"]],["Piso","", "select",["1","2","3","4"]],["Tarifa por noche","$0"],["Estado","", "select",["Disponible","Fuera de servicio"]]
  ]),"Agregar habitación");
}
function genericAction(label){
  toast(label+" seleccionado. La operación real se conectará al backend PHP.");
}
function setupGlobalButtons(){} 
if(!window.__buttonsReady){window.__buttonsReady=true;setupGlobalButtons();}

if(!window.__buttonsReady){window.__buttonsReady=true;setupGlobalButtons();}

function pageSettings(){
 return layout("settings","Configuración",`
  <div class="eyebrow">ADMINISTRACIÓN</div>
  <h2 class="page-title">Configuración del sistema</h2>
  <p class="page-sub">Administra la seguridad, las preferencias operativas y las funciones del hotel.</p>

  <div class="tabs settings-tabs">
    <button class="active" data-tab="security" onclick="settingsTab('security')">Cuenta y seguridad</button>
    <button data-tab="operation" onclick="settingsTab('operation')">Operación del hotel</button>
    <button data-tab="notifications" onclick="settingsTab('notifications')">Notificaciones</button>
    <button data-tab="permissions" onclick="settingsTab('permissions')">Usuarios y permisos</button>
    <button data-tab="integrations" onclick="settingsTab('integrations')">Integraciones</button>
    <button data-tab="log" onclick="settingsTab('log')">Bitácora</button>
  </div>

  <div class="settings-panel" data-panel="security">
    <div class="two">
      <section class="card section">
        <div style="display:flex;align-items:center;gap:13px">
          <div class="avatar">A</div>
          <div><h2>Cuenta actual</h2><div class="sub" style="margin:3px 0">Datos proporcionados por el backend</div></div>
          <span class="badge green" style="margin-left:auto">Sesión activa</span>
        </div>
        <p style="margin-top:28px;font-size:12px"><b>Correo de cuenta</b><span style="float:right">Se cargará desde el backend</span></p>
        <p style="font-size:12px"><b>Último acceso</b><span style="float:right">Se cargará desde el backend</span></p>
        <p style="font-size:12px"><b>Sesiones activas</b><span style="float:right">Se cargará desde el backend</span></p>
        <div class="notice">Las contraseñas nunca son visibles para los administradores. Se almacenan de forma segura y sólo pueden restablecerse mediante un flujo de recuperación.</div>
      </section>

      <section class="card password">
        <h2>Cambiar contraseña</h2>
        <div class="sub">Actualiza tus credenciales sin revelar información sensible.</div>
        <form id="passwordForm" onsubmit="event.preventDefault();changePassword()">
          <div class="field"><label>Contraseña actual</label><input type="password"></div>
          <div class="field"><label>Nueva contraseña</label><input type="password"><small style="display:block;color:#7b8c89;margin-top:5px">Ingresa una contraseña nueva.</small></div>
          <div class="field"><label>Confirmar nueva contraseña</label><input type="password"></div>
          <ul style="font-size:11px;color:#71817d;line-height:1.8;padding-left:18px">
            <li>Al menos 12 caracteres</li><li>Una mayúscula, una minúscula y un número</li><li>Un símbolo especial</li>
          </ul>
          <label style="display:block;background:#f2f7f5;border-radius:10px;padding:13px;font-size:12px">
            <input type="checkbox"> <b>Invalidar sesiones activas</b><br>
            <span style="margin-left:24px;color:#71817d">Solicitará iniciar sesión nuevamente en otros dispositivos.</span>
          </label>
          <button class="btn primary" type="submit" style="margin-top:16px">Actualizar contraseña</button>
        </form>
      </section>
    </div>

    <section class="card section">
      <h2>Acciones de seguridad</h2>
      <div class="security-actions" style="margin-top:15px">
        <button class="security-card" onclick="securityAction('sessions')"><strong>Cerrar todas las sesiones</strong><small>Finaliza los accesos activos.</small></button>
        <button class="security-card" id="twofaBtn" onclick="securityAction('2fa')"><strong>Activar autenticación de dos factores</strong><small>Agrega verificación al inicio.</small></button>
        <button class="security-card" id="forceBtn" onclick="securityAction('force')"><strong>Forzar cambio de contraseña al próximo inicio de sesión</strong><small>Estado: sin solicitud pendiente.</small></button>
      </div>
    </section>
  </div>

  <div class="settings-panel" data-panel="operation" style="display:none">
    <section class="card section"><h2>Operación del hotel</h2><div class="sub">Preferencias generales de la operación.</div>
      <div class="three">
        <div class="security-card"><strong>Hora de check-in</strong><input type="time" value="15:00"></div>
        <div class="security-card"><strong>Hora de check-out</strong><input type="time" value="12:00"></div>
        <div class="security-card"><strong>Moneda</strong><select><option>MXN · Peso mexicano</option><option>USD · Dólar</option></select></div>
      </div>
      <button class="btn primary" style="margin-top:15px" onclick="toast('Preferencias guardadas en la interfaz.')">Guardar cambios</button>
    </section>
  </div>

  <div class="settings-panel" data-panel="notifications" style="display:none">
    <section class="card section"><h2>Notificaciones</h2><div class="sub">Controla los avisos que recibe la administración.</div>
      ${["Nuevas reservaciones","Check-in pendiente","Check-out pendiente","Incidencias de mantenimiento","Cargos pendientes"].map(x=>`<label style="display:block;padding:14px;border-bottom:1px solid #e7efec;font-size:13px"><input type="checkbox" checked> <b>${x}</b></label>`).join("")}
      <button class="btn primary" style="margin-top:15px" onclick="toast('Preferencias de notificaciones guardadas.')">Guardar preferencias</button>
    </section>
  </div>

  <div class="settings-panel" data-panel="permissions" style="display:none">
    <section class="card section"><h2>Usuarios y permisos</h2><div class="sub">Configuración de acceso por rol.</div>
      ${["Administrador","Gerencia","Recepción","Mantenimiento","Ama de llaves"].map(x=>`<div class="security-card" style="margin-bottom:10px;display:flex;justify-content:space-between;align-items:center"><div><strong>${x}</strong><small>Configuración de acceso</small></div><button class="btn" onclick="toast('Configuración de ${x} seleccionada.')">Configurar</button></div>`).join("")}
    </section>
  </div>

  <div class="settings-panel" data-panel="integrations" style="display:none">
    <section class="card section"><h2>Integraciones</h2><div class="sub">Servicios externos conectados al sistema.</div>
      <div class="security-card"><strong>Correo electrónico</strong><small>SMTP · Sin configurar</small><button class="btn" style="float:right" onclick="toast('Configuración de correo abierta.')">Configurar</button></div>
      <div class="security-card" style="margin-top:10px"><strong>Pagos</strong><small>Pasarela de pagos · Sin configurar</small><button class="btn" style="float:right" onclick="toast('Configuración de pagos abierta.')">Configurar</button></div>
    </section>
  </div>

  <div class="settings-panel" data-panel="log" style="display:none">
    <section class="card section"><h2>Bitácora</h2><div class="sub">Registro de acciones administrativas.</div>
      ${["Inicio de sesión del administrador","Reporte mensual generado","Configuración consultada"].map(x=>`<button class="activity-item activity-control" onclick="showControlInfo('Actividad','Detalle de actividad disponible en el frontend.')" style="margin-bottom:10px"><b>${x}</b><br><small>28 sep 2026 · Administrador</small></button>`).join("")}
    </section>
  </div>
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


function activateUnwiredButtons(){
  document.addEventListener("click",function(e){
    const b=e.target.closest("button");
    if(!b || b.id==="modal-submit" || b.hasAttribute("onclick")) return;
    const text=b.innerText.replace(/\s+/g," ").trim();
    if(!text) return;
    if(text==="Limpiar filtros"){
      const root=b.closest(".content")||document;
      root.querySelectorAll("input").forEach(x=>x.value="");
      root.querySelectorAll("select").forEach(x=>x.selectedIndex=0);
      toast("Filtros limpiados.");
      return;
    }
    if(text==="Registrar Walk-in") return openWalkin();
    if(text==="Nueva reservación") return openReservation();
    if(text.includes("Registrar consumo")) return openConsumption();
    if(text.includes("Registrar mantenimiento")) return openMaintenance();
    if(text.includes("Agregar habitación")) return openRoom();
    if(text==="Configurar") return toast("Configuración seleccionada.");
    if(text==="Guardar cambios" || text==="Guardar preferencias") return toast("Cambios guardados en la interfaz.");
    toast(text+" seleccionado. Esta acción está lista para conectarse con PHP.");
  },true);
}
if(!window.__unwiredReady){window.__unwiredReady=true;activateUnwiredButtons();}


function showControlInfo(title, message){
  modal(title, `<div class="control-info"><p>${message}</p></div>`, "Cerrar", ()=>closeModal());
}
function setupAllControlInteractions(){
  document.addEventListener("click", function(e){
    const el=e.target.closest("button, a");
    if(!el) return;

    // Never override an explicitly programmed action.
    if(el.hasAttribute("onclick")) return;
    if(el.closest("#app-modal")) return;

    const text=(el.innerText||el.getAttribute("aria-label")||el.title||"").replace(/\s+/g," ").trim();
    const hash=(location.hash||"").toLowerCase();

    // Header/profile controls.
    if(text==="Administrador" || text==="Kiin Saasil"){
      return showControlInfo("Cuenta de administrador",
        "Cuenta activa: Administrador. Los datos de sesión y permisos reales se conectarán al backend PHP.");
    }

    // Generic icon-only controls: give them a visible action instead of doing nothing.
    if(!text){
      const label=el.getAttribute("aria-label") || el.title;
      if(label) return showControlInfo(label, "Esta acción está preparada en el frontend y queda lista para conectarse con PHP.");
      return;
    }

    // Common text-like controls throughout the dashboard.
    const actions = {
      "Ver":"Ver detalles",
      "Editar":"Editar registro",
      "Eliminar":"Eliminar registro",
      "Anular":"Anular registro",
      "Confirmar":"Confirmar operación",
      "Cancelar":"Cancelar operación",
      "Configurar":"Configurar elemento",
      "Guardar":"Guardar cambios",
      "Aplicar":"Aplicar filtros",
      "Restablecer":"Restablecer filtros",
      "Descargar":"Descargar reporte",
      "Exportar":"Exportar datos",
      "Imprimir":"Imprimir reporte",
      "Limpiar":"Limpiar filtros",
      "Cerrar":"Cerrar ventana",
      "Gestionar":"Abrir gestión",
      "Cambiar estado":"Cambiar estado",
      "Limpieza":"Registrar limpieza",
      "Mantenimiento":"Registrar mantenimiento",
      "Agregar consumo":"Agregar consumo",
      "Check-in":"Procesar check-in",
      "Check-out":"Procesar check-out",
      "Registrar":"Registrar operación"
    }

    // Exact matches first.
    if(actions[text]){
      return showControlInfo(actions[text],
        "La acción responde correctamente en el frontend. La operación sobre los datos reales se conectará posteriormente al backend PHP/MySQL.");
    }

    // Buttons containing a meaningful action word.
    for(const key of Object.keys(actions)){
      if(text.toLowerCase().includes(key.toLowerCase())){
        return showControlInfo(actions[key],
          "Esta acción está disponible en la interfaz. La persistencia o consulta de datos reales corresponde al backend.");
      }
    }
  }, true);
}
if(!window.__allControlsReady){
  window.__allControlsReady=true;
  setupAllControlInteractions();
}
