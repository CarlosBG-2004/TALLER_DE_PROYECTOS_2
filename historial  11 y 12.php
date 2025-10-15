<?php
// raptravel_logistica.php
// Módulo Logística - Rap Travel
// PHP + SQLite, simple, bonito y "humanizado".
// Guardar en servidor con PHP (ej. XAMPP, LAMP). 

$dbFile = __DIR__ . '/raptravel_logistica.sqlite';
$dsn = 'sqlite:' . $dbFile;
if (!file_exists($dbFile)) {
    // crear DB y tablas
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE materials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE,
        name TEXT,
        qty INTEGER,
        location TEXT,
        state TEXT,
        notes TEXT,
        created_at TEXT
    )");
    $pdo->exec("CREATE TABLE maints (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_id INTEGER,
        asset_name TEXT,
        type TEXT,
        date TEXT,
        responsible TEXT,
        notes TEXT,
        created_at TEXT
    )");
} else {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// Helper: JSON response
function json_res($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// Handle AJAX actions
$action = $_REQUEST['action'] ?? null;

if ($action === 'list_materials') {
    $stmt = $pdo->query("SELECT * FROM materials ORDER BY id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_res(['ok'=>true, 'data'=>$rows]);
}

if ($action === 'save_material') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $qty = intval($_POST['qty'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $state = trim($_POST['state'] ?? 'Operativo');
    $notes = trim($_POST['notes'] ?? '');
    $now = date('c');
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE materials SET code=?,name=?,qty=?,location=?,state=?,notes=? WHERE id=?");
        $stmt->execute([$code,$name,$qty,$location,$state,$notes,$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO materials (code,name,qty,location,state,notes,created_at) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$code,$name,$qty,$location,$state,$notes,$now]);
    }
    json_res(['ok'=>true,'msg'=>'Material guardado']);
}

if ($action === 'delete_material') {
    $id = intval($_POST['id'] ?? 0);
    if ($id>0) {
        $pdo->prepare("DELETE FROM materials WHERE id=?")->execute([$id]);
        json_res(['ok'=>true,'msg'=>'Material eliminado']);
    }
    json_res(['ok'=>false,'msg'=>'ID inválido']);
}

if ($action === 'list_maints') {
    $stmt = $pdo->query("SELECT m.*, (SELECT name FROM materials WHERE id=m.asset_id) as linked_asset FROM maints m ORDER BY date ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_res(['ok'=>true,'data'=>$rows]);
}

if ($action === 'save_maint') {
    $asset_id = isset($_POST['asset_id']) && $_POST['asset_id'] !== '' ? intval($_POST['asset_id']) : null;
    $asset_name = trim($_POST['asset_name'] ?? '');
    $type = trim($_POST['type'] ?? 'Preventivo');
    $date = trim($_POST['date'] ?? '');
    $resp = trim($_POST['responsible'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $now = date('c');
    $stmt = $pdo->prepare("INSERT INTO maints (asset_id,asset_name,type,date,responsible,notes,created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$asset_id,$asset_name,$type,$date,$resp,$notes,$now]);
    json_res(['ok'=>true,'msg'=>'Mantenimiento programado']);
}

if ($action === 'delete_maint') {
    $id = intval($_POST['id'] ?? 0);
    if ($id>0) {
        $pdo->prepare("DELETE FROM maints WHERE id=?")->execute([$id]);
        json_res(['ok'=>true,'msg'=>'Mantenimiento eliminado']);
    }
    json_res(['ok'=>false,'msg'=>'ID inválido']);
}

// Alertas de vencimiento: devuelve mantenimientos en los próximos N días (por defecto 7)
if ($action === 'alerts') {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
    $today = new DateTimeImmutable();
    $limit = $today->add(new DateInterval('P'.$days.'D'))->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT m.*, (SELECT name FROM materials WHERE id=m.asset_id) as linked_asset FROM maints m WHERE date BETWEEN ? AND ? ORDER BY date ASC");
    $stmt->execute([$today->format('Y-m-d'), $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_res(['ok'=>true,'data'=>$rows]);
}

// Si no hubo acción AJAX: mostrar HTML
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rap Travel — Módulo Logística</title>
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root{
    --brand:#c8102e; --brand-dark:#8b0000; --bg:#f6f7f9; --card:#ffffff; --muted:#667085;
    --radius:12px;
  }
  *{box-sizing:border-box}
  body{font-family:Inter,Arial,sans-serif;margin:0;background:var(--bg);color:#111}
  header{background:linear-gradient(90deg,var(--brand),var(--brand-dark));color:#fff;padding:18px 20px}
  header h1{margin:0;font-size:1.2rem}
  .wrap{max-width:1200px;margin:20px auto;padding:18px;display:grid;grid-template-columns:1fr 340px;gap:18px}
  .card{background:var(--card);border-radius:var(--radius);padding:16px;box-shadow:0 6px 24px rgba(2,6,23,0.06)}
  h2{color:var(--brand);margin:0 0 10px}
  .muted{color:var(--muted);font-size:0.95rem}
  .kpis{display:flex;gap:10px;margin-bottom:12px}
  .kpi{flex:1;background:#fff5f5;border-left:6px solid var(--brand);padding:12px;border-radius:10px}
  label{display:block;font-weight:600;margin-bottom:6px}
  input,select,textarea,button{font-family:inherit}
  input,select,textarea{width:100%;padding:8px;border-radius:8px;border:1px solid #e6e9ee}
  button{background:var(--brand);color:#fff;padding:10px 12px;border:0;border-radius:8px;cursor:pointer}
  button.ghost{background:#f3f4f6;color:#111}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{padding:8px;border:1px solid #eef2f6;font-size:0.95rem;text-align:left}
  th{background:#fafafa}
  .right{display:flex;justify-content:flex-end;gap:8px;align-items:center}
  .small{font-size:0.9rem;color:var(--muted)}
  /* responsive */
  @media (max-width:980px){ .wrap{grid-template-columns:1fr; padding:12px} .kpis{flex-direction:column} }
</style>
</head>
<body>
<header>
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <h1>Rap Travel — Módulo de Logística</h1>
      <div class="small">Registro de materiales e programación de mantenimientos — Sprint 12 / 13</div>
    </div>
    <div style="text-align:right;color:#fff">
      <div style="font-weight:700">Encargado: Área de Logística</div>
      <div style="font-size:0.9rem;margin-top:4px">Fecha: <?= date('Y-m-d') ?></div>
    </div>
  </div>
</header>

<div class="wrap">
  <main class="card">
    <h2>Inventario de Materiales</h2>
    <p class="muted">Registra y actualiza materiales (código, cantidad, ubicación y estado). Los cambios son instantáneos y auditables.</p>

    <div style="display:flex;gap:12px;margin-top:8px" class="kpis">
      <div class="kpi"><div class="small">Total materiales</div><div style="font-weight:800;font-size:1.2rem" id="k_total">0</div></div>
      <div class="kpi"><div class="small">Mantenimientos próximos (7d)</div><div style="font-weight:800;font-size:1.2rem" id="k_maint_7">0</div></div>
      <div class="kpi"><div class="small">Activos fuera de servicio</div><div style="font-weight:800;font-size:1.2rem" id="k_oos">0</div></div>
    </div>

    <!-- Form material -->
    <div class="card" style="margin-top:10px">
      <h3 style="margin:0 0 8px">Agregar / Editar material</h3>
      <form id="materialForm">
        <input type="hidden" id="mat_id" value="">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div>
            <label>Código</label>
            <input id="mat_code" placeholder="Ej: MAT-001">
          </div>
          <div>
            <label>Nombre</label>
            <input id="mat_name" placeholder="Ej: Radio portátil">
          </div>
          <div>
            <label>Cantidad</label>
            <input id="mat_qty" type="number" min="0" value="1">
          </div>
          <div>
            <label>Ubicación</label>
            <input id="mat_loc" placeholder="Ej: Bodega Cusco">
          </div>
          <div>
            <label>Estado</label>
            <select id="mat_state">
              <option>Operativo</option><option>En mantenimiento</option><option>Fuera de servicio</option>
            </select>
          </div>
          <div>
            <label>Notas</label>
            <input id="mat_notes" placeholder="Observaciones">
          </div>
        </div>
        <div style="margin-top:8px" class="right">
          <button type="button" onclick="saveMaterial()">Guardar</button>
          <button type="button" class="ghost" onclick="resetMaterialForm()">Limpiar</button>
        </div>
      </form>
    </div>

    <!-- Tabla materiales -->
    <div style="margin-top:12px">
      <table id="materialsTable">
        <thead>
          <tr><th>Código</th><th>Nombre</th><th>Cantidad</th><th>Ubicación</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- Mantenimientos -->
    <div style="margin-top:18px" class="card">
      <h3 style="margin:0 0 8px">Programar Mantenimiento</h3>
      <form id="maintForm" style="margin-bottom:8px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div>
            <label>Activo (opcional)</label>
            <select id="maint_asset"><option value="">-- Seleccionar --</option></select>
          </div>
          <div>
            <label>Tipo</label>
            <select id="maint_type"><option>Preventivo</option><option>Correctivo</option></select>
          </div>
          <div>
            <label>Fecha</label>
            <input id="maint_date" type="date">
          </div>
          <div>
            <label>Responsable</label>
            <input id="maint_resp" placeholder="Ej: Técnico Juan">
          </div>
        </div>
        <div style="margin-top:8px">
          <label>Notas</label>
          <input id="maint_notes" placeholder="Ej: cambio de aceite">
        </div>
        <div class="right" style="margin-top:8px">
          <button type="button" onclick="saveMaint()">Programar</button>
          <button type="button" class="ghost" onclick="resetMaintForm()">Limpiar</button>
        </div>
      </form>

      <table id="maintsTable">
        <thead><tr><th>Fecha</th><th>Activo</th><th>Tipo</th><th>Responsable</th><th>Notas</th><th>Acciones</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>

  </main>

  <aside class="card">
    <h3 style="margin-top:0">Panel Rápido</h3>
    <p class="muted">Alertas y tareas rápidas de logística</p>
    <div style="margin-top:10px">
      <button class="ghost" style="width:100%;padding:10px" onclick="fetchAlerts()">Ver alertas próximas (7 días)</button>
    </div>

    <div style="margin-top:12px">
      <h4 style="margin:0 0 8px">Alertas</h4>
      <div id="alertsList" class="small">No hay alertas.</div>
    </div>

    <div style="margin-top:12px">
      <h4 style="margin:0 0 8px">Exportar</h4>
      <button class="ghost" style="width:100%;padding:10px;margin-bottom:8px" onclick="exportCSV('materials')">Exportar Inventario (CSV)</button>
      <button class="ghost" style="width:100%;padding:10px" onclick="exportCSV('maints')">Exportar Mantenimientos (CSV)</button>
    </div>
  </aside>
</div>

<script>
/* Minimal JS: Fetch API calls to this same PHP file */
const api = (params = {}) => {
  const url = new URL(window.location.href);
  url.searchParams.set('action', params.action);
  return fetch(url.toString(), {
    method: params.method || 'GET',
    headers: params.headers || {},
    body: params.body || null
  }).then(r => r.json());
};

function refreshMaterials(){
  api({action:'list_materials'}).then(res=>{
    if(!res.ok) return;
    const tbody = document.querySelector('#materialsTable tbody');
    tbody.innerHTML = '';
    res.data.forEach(m=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${escapeHtml(m.code)}</td>
        <td>${escapeHtml(m.name)}</td>
        <td>${m.qty}</td>
        <td>${escapeHtml(m.location)}</td>
        <td>${escapeHtml(m.state)}</td>
        <td>
          <button onclick="editMaterial(${m.id})" class="ghost">Editar</button>
          <button onclick="deleteMaterial(${m.id})" style="background:#ff6b6b;color:white">Eliminar</button>
        </td>`;
      tbody.appendChild(tr);
    });
    // fill asset <select>
    const sel = document.getElementById('maint_asset');
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
    res.data.forEach(m=>{
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.text = `${m.name} (${m.code})`;
      sel.add(opt);
    });
    document.getElementById('k_total').textContent = res.data.length;
    // count out of service
    const oos = res.data.filter(x=> (x.state||'').toLowerCase().includes('fuera')).length;
    document.getElementById('k_oos').textContent = oos;
  });
}

function saveMaterial(){
  const id = document.getElementById('mat_id').value;
  const form = new FormData();
  form.append('action','save_material');
  if(id) form.append('id', id);
  form.append('code', document.getElementById('mat_code').value);
  form.append('name', document.getElementById('mat_name').value);
  form.append('qty', document.getElementById('mat_qty').value);
  form.append('location', document.getElementById('mat_loc').value);
  form.append('state', document.getElementById('mat_state').value);
  form.append('notes', document.getElementById('mat_notes').value);
  fetch(window.location.href + '?action=save_material', {method:'POST', body: form})
    .then(r=>r.json()).then(res=>{
      alert(res.msg || 'Guardado');
      resetMaterialForm();
      refreshMaterials();
    });
}

function deleteMaterial(id){
  if(!confirm('Eliminar material?')) return;
  const form = new FormData(); form.append('action','delete_material'); form.append('id',id);
  fetch(window.location.href + '?action=delete_material', {method:'POST', body: form}).then(r=>r.json()).then(res=>{
    alert(res.msg || 'Eliminado');
    refreshMaterials();
    refreshMaints();
  });
}

function editMaterial(id){
  // fetch list and find
  api({action:'list_materials'}).then(res=>{
    const m = res.data.find(x=>x.id==id);
    if(!m) return alert('Material no encontrado');
    document.getElementById('mat_id').value = m.id;
    document.getElementById('mat_code').value = m.code;
    document.getElementById('mat_name').value = m.name;
    document.getElementById('mat_qty').value = m.qty;
    document.getElementById('mat_loc').value = m.location;
    document.getElementById('mat_state').value = m.state;
    document.getElementById('mat_notes').value = m.notes;
    window.scrollTo({top:0,behavior:'smooth'});
  });
}

function resetMaterialForm(){
  document.getElementById('mat_id').value='';
  document.getElementById('mat_code').value='';
  document.getElementById('mat_name').value='';
  document.getElementById('mat_qty').value='1';
  document.getElementById('mat_loc').value='';
  document.getElementById('mat_state').value='Operativo';
  document.getElementById('mat_notes').value='';
}

/* Mantenimientos */
function refreshMaints(){
  api({action:'list_maints'}).then(res=>{
    if(!res.ok) return;
    const tbody = document.querySelector('#maintsTable tbody');
    tbody.innerHTML = '';
    res.data.forEach(m=>{
      const asset = m.linked_asset || m.asset_name || '—';
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${m.date}</td><td>${escapeHtml(asset)}</td><td>${escapeHtml(m.type)}</td><td>${escapeHtml(m.responsible)}</td><td>${escapeHtml(m.notes)}</td>
        <td><button onclick="deleteMaint(${m.id})" style="background:#ff6b6b;color:white">Eliminar</button></td>`;
      tbody.appendChild(tr);
    });
    // KPIs: next 7 days count
    fetch(window.location.href + '?action=alerts&days=7').then(r=>r.json()).then(a=>{
      document.getElementById('k_maint_7').textContent = a.data.length || 0;
    });
  });
}

function saveMaint(){
  const fd = new FormData();
  fd.append('action','save_maint');
  const assetId = document.getElementById('maint_asset').value;
  const assetName = assetId? '' : (document.getElementById('mat_name').value || '');
  fd.append('asset_id', assetId);
  fd.append('asset_name', assetName);
  fd.append('type', document.getElementById('maint_type').value);
  fd.append('date', document.getElementById('maint_date').value);
  fd.append('responsible', document.getElementById('maint_resp').value);
  fd.append('notes', document.getElementById('maint_notes').value);
  fetch(window.location.href + '?action=save_maint', {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
    alert(res.msg || 'Programado');
    resetMaintForm();
    refreshMaints();
  });
}

function deleteMaint(id){
  if(!confirm('Eliminar mantenimiento?')) return;
  const fd = new FormData(); fd.append('action','delete_maint'); fd.append('id', id);
  fetch(window.location.href + '?action=delete_maint', {method:'POST', body: fd}).then(r=>r.json()).then(res=>{
    alert(res.msg || 'Eliminado');
    refreshMaints();
  });
}

function resetMaintForm(){
  document.getElementById('maint_asset').value='';
  document.getElementById('maint_type').value='Preventivo';
  document.getElementById('maint_date').value='';
  document.getElementById('maint_resp').value='';
  document.getElementById('maint_notes').value='';
}

/* Alerts */
function fetchAlerts(){
  fetch(window.location.href + '?action=alerts&days=7').then(r=>r.json()).then(res=>{
    const el = document.getElementById('alertsList');
    if(!res.data.length){ el.textContent = 'No hay mantenimientos próximos en 7 días.'; return; }
    el.innerHTML = '';
    res.data.forEach(m=>{
      const d = document.createElement('div');
      d.style.marginBottom='8px';
      d.innerHTML = `<strong>${m.date}</strong> — ${(m.linked_asset||m.asset_name||'—')} <div class="small">${m.type} · Resp: ${m.responsible}</div>`;
      el.appendChild(d);
    });
  });
}

/* Export CSV (materials / maints) */
function exportCSV(kind){
  if(kind==='materials'){
    api({action:'list_materials'}).then(res=>{
      const rows = res.data;
      if(!rows.length) return alert('No hay materiales');
      let csv = 'Código,Nombre,Cantidad,Ubicación,Estado,Notas\\n';
      rows.forEach(r => csv += `"${r.code}","${r.name}",${r.qty},"${r.location}","${r.state}","${(r.notes||'').replace(/"/g,'""')}"\\n`);
      downloadBlob(csv, 'inventario_materiales.csv');
    });
  } else {
    api({action:'list_maints'}).then(res=>{
      const rows = res.data;
      if(!rows.length) return alert('No hay mantenimientos');
      let csv = 'Fecha,Activo,Tipo,Responsable,Notas\\n';
      rows.forEach(r => csv += `"${r.date}","${(r.linked_asset||r.asset_name||'') }","${r.type}","${r.responsible}","${(r.notes||'').replace(/"/g,'""')}"\\n`);
      downloadBlob(csv, 'mantenimientos.csv');
    });
  }
}
function downloadBlob(text, filename){
  const blob = new Blob([text], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}
function escapeHtml(s){ if(!s && s!==0) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* init */
refreshMaterials();
refreshMaints();
fetchAlerts();
</script>
</body>
</html>
