<?php require __DIR__.'/guard.php'; ?>
<!doctype html><html lang="nl"><meta charset="utf-8">
<title>Admin – Modellen</title>
<style>
body{font-family:sans-serif;max-width:1100px;margin:24px auto;padding:0 12px}
table{border-collapse:collapse;width:100%;font-size:14px}
th,td{border:1px solid #ddd;padding:8px;vertical-align:top}
th{background:#f7f7f7;position:sticky;top:0}
input,select,textarea{font-size:14px}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
pre{margin:0;padding:6px;background:#f5f5f5;border:1px solid #e0e0e0}
.small{font-size:12px;color:#555}
.badge{display:inline-block;padding:2px 6px;border-radius:6px;background:#eef;border:1px solid #ccd}
button{cursor:pointer}
</style>

<h1>Modellen beheren</h1>
<p class="small">Zoek, bewerk of voeg nieuwe modellen toe. <a href="/admin/">← prijzen</a> • <a href="/admin/import_models.php">CSV import</a> • <a href="/admin/logout.php">Uitloggen</a></p>

<div class="row">
  <label>Merk:
    <input id="f-brand" placeholder="bv. Lenovo">
  </label>
  <label>Zoek:
    <input id="f-q" placeholder="model/notes">
  </label>
  <button onclick="loadModels()">Zoeken</button>
  <button onclick="resetFilters()">Reset</button>
</div>

<table id="tbl">
  <thead>
    <tr>
      <th>ID</th>
      <th>Merk</th>
      <th>Model</th>
      <th>Regex (JSON)</th>
      <th>Max RAM</th>
      <th>W11</th>
      <th>Opslag</th>
      <th>CPU-arch</th>
      <th>Notes</th>
      <th>Actief</th>
      <th>Acties</th>
    </tr>
    <tr>
      <td>—</td>
      <td><input id="n-brand" style="width:120px"></td>
      <td><input id="n-model" style="width:160px" placeholder="display_model"></td>
      <td><input id="n-regex" style="width:220px" placeholder='["thinkpad\\s*t430"]'></td>
      <td><input id="n-maxram" type="number" min="0" step="1" style="width:70px"></td>
      <td>
        <select id="n-w11" style="width:70px">
          <option value="">?</option>
          <option value="1">Ja</option>
          <option value="0">Nee</option>
        </select>
      </td>
      <td><input id="n-storage" style="width:160px" placeholder='2.5" SATA + M.2 NVMe'></td>
      <td><input id="n-cpu" style="width:80px" placeholder="x86-64"></td>
      <td><input id="n-notes" style="width:220px"></td>
      <td>
        <select id="n-active" style="width:70px">
          <option value="1" selected>Ja</option>
          <option value="0">Nee</option>
        </select>
      </td>
      <td><button onclick="createModel()">+ Toevoegen</button></td>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<script>
async function loadModels(){
  const brand = document.getElementById('f-brand').value.trim();
  const q     = document.getElementById('f-q').value.trim();
  const url = new URL('/api/models_admin.php', location.origin);
  if (brand) url.searchParams.set('brand', brand);
  if (q)     url.searchParams.set('q', q);
  const res = await fetch(url.toString(), {credentials:'include'});
  const j = await res.json();
  const tb = document.querySelector('#tbl tbody');
  tb.innerHTML = '';
  if (!j.ok) { tb.innerHTML = `<tr><td colspan="11">Mislukt: ${j.error||res.status}</td></tr>`; return; }
  for (const r of j.models) tb.appendChild(renderRow(r));
}

function renderRow(r){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${r.id}</td>
    <td><input value="${esc(r.brand||'')}" style="width:120px"></td>
    <td><input value="${esc(r.display_model||'')}" style="width:160px"></td>
    <td><input value="${esc(r.model_regex||'')}" style="width:220px" placeholder='["regex1","regex2"]'></td>
    <td><input type="number" value="${r.max_ram_gb??''}" style="width:70px"></td>
    <td>
      <select style="width:70px">
        <option value="" ${r.supports_w11===null?'selected':''}>?</option>
        <option value="1" ${r.supports_w11==1?'selected':''}>Ja</option>
        <option value="0" ${r.supports_w11==0?'selected':''}>Nee</option>
      </select>
    </td>
    <td><input value="${esc(r.storage||'')}" style="width:160px"></td>
    <td><input value="${esc(r.cpu_arch||'')}" style="width:80px"></td>
    <td><input value="${esc(r.notes||'')}" style="width:220px"></td>
    <td>
      <select style="width:70px">
        <option value="1" ${r.active==1?'selected':''}>Ja</option>
        <option value="0" ${r.active==0?'selected':''}>Nee</option>
      </select>
    </td>
    <td>
      <button onclick="saveRow(this, ${r.id})">Opslaan</button>
      <button onclick="delRow(${r.id})" style="color:#b00">Verwijder</button>
    </td>
  `;
  return tr;
}
function esc(s){ return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;'); }

async function saveRow(btn, id){
  const tds = btn.closest('tr').querySelectorAll('td');
  const payload = {
    brand: tds[1].querySelector('input').value.trim(),
    display_model: tds[2].querySelector('input').value.trim(),
    model_regex: parseRegexJson(tds[3].querySelector('input').value.trim()),
    max_ram_gb: toIntOrNull(tds[4].querySelector('input').value),
    supports_w11: toIntOrNull(tds[5].querySelector('select').value),
    storage: tds[6].querySelector('input').value.trim() || null,
    cpu_arch: tds[7].querySelector('input').value.trim() || null,
    notes: tds[8].querySelector('input').value.trim() || null,
    active: toIntOrNull(tds[9].querySelector('select').value) ?? 1
  };
  const res = await fetch('/api/models_admin.php?id='+id, {
    method:'PUT', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload), credentials:'include'
  });
  const j = await res.json();
  alert(j.ok ? 'Opgeslagen' : ('Mislukt: '+(j.error||res.status)));
  if (j.ok) loadModels();
}

async function delRow(id){
  if (!confirm('Weet je zeker dat je dit model wil verwijderen?')) return;
  const res = await fetch('/api/models_admin.php?id='+id, {method:'DELETE', credentials:'include'});
  const j = await res.json();
  alert(j.ok ? 'Verwijderd' : ('Mislukt: '+(j.error||res.status)));
  if (j.ok) loadModels();
}

async function createModel(){
  const p = {
    brand:       document.getElementById('n-brand').value.trim(),
    display_model: document.getElementById('n-model').value.trim(),
    model_regex: parseRegexJson(document.getElementById('n-regex').value.trim()),
    max_ram_gb:  toIntOrNull(document.getElementById('n-maxram').value),
    supports_w11:toIntOrNull(document.getElementById('n-w11').value),
    storage:     (document.getElementById('n-storage').value.trim()) || null,
    cpu_arch:    (document.getElementById('n-cpu').value.trim()) || null,
    notes:       (document.getElementById('n-notes').value.trim()) || null,
    active:      toIntOrNull(document.getElementById('n-active').value) ?? 1
  };
  if (!p.brand || !p.display_model) { alert('Merk en display_model zijn verplicht'); return; }
  const res = await fetch('/api/models_admin.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(p), credentials:'include'
  });
  const j = await res.json();
  alert(j.ok ? 'Toegevoegd (id '+j.id+')' : ('Mislukt: '+(j.error||res.status)));
  if (j.ok) { resetNewRow(); loadModels(); }
}

function resetNewRow(){
  ['n-brand','n-model','n-regex','n-maxram','n-storage','n-cpu','n-notes'].forEach(id => document.getElementById(id).value='');
  document.getElementById('n-w11').value='';
  document.getElementById('n-active').value='1';
}

function parseRegexJson(s){
  if (!s) return null;
  try {
    const arr = JSON.parse(s);
    if (Array.isArray(arr)) return arr;
  } catch(e){}
  // 1 regel → maak array
  return [s];
}
function toIntOrNull(v){ v=String(v).trim(); if(v==='') return null; return parseInt(v,10); }
function resetFilters(){ document.getElementById('f-brand').value=''; document.getElementById('f-q').value=''; loadModels(); }

loadModels();
</script>
