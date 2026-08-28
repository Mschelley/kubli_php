const API = 'api';

/* Small fetch helpers — every request carries the PHP session cookie. */
async function apiGet(path){
  const res = await fetch(`${API}/${path}`, { credentials: 'same-origin' });
  const data = await res.json().catch(()=>({}));
  if(!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}
async function apiPostJSON(path, body){
  const res = await fetch(`${API}/${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const data = await res.json().catch(()=>({}));
  if(!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}
async function apiPostForm(path, formData){
  const res = await fetch(`${API}/${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  });
  const data = await res.json().catch(()=>({}));
  if(!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}

/* ---------------- STATE ---------------- */
const LEVEL_INFO = {
  1:{label:"Low", color:"lvl1", action:"Logged for monitoring — no urgent routing.", sla:"Periodic monitoring"},
  2:{label:"Moderate", color:"lvl2", action:"Routed to Manager for scheduled field validation.", sla:"5–7 business days"},
  3:{label:"High", color:"lvl3", action:"Priority routing to CENRO — expedited TCP track.", sla:"2–3 business days"},
  4:{label:"Severe / Emergency", color:"lvl4", action:"Flagged for Special Tree Cutting Permit (STCP) — LGU/Barangay disaster office auto-notified.", sla:"Immediate / same-day"}
};
const PERMIT_TABLE = {
  "Branch/Limb Trimming":"Simplified pruning clearance (subject to DAO 2021-11 thresholds)",
  "Trunk-Level Cutting":"TCP — standard processing",
  "Full Tree Removal":"TCP (standard), or STCP if imminent hazard present",
  "Emergency Removal":"STCP — expedited; may allow removal ahead of full paperwork"
};
const ROLE_TABS = {
  User: ['dashboard','user'],
  Manager: ['dashboard','manager'],
  Admin: ['dashboard','user','manager','admin']
};

let currentUser = null; // { id, name, email, role }
let reports = [];       // populated from the PHP API on login / refresh
let authMode = "signin";
let signupRole = "User"; // which role the signup form will register as

/* ---------------- AUTH MODE (sign in vs create account) ---------------- */
function setAuthMode(mode){
  authMode = mode;
  const signup = mode === "signup";
  document.getElementById("tab-signin").classList.toggle("active", !signup);
  document.getElementById("tab-signup").classList.toggle("active", signup);
  document.getElementById("signup-only").hidden = !signup;
  document.getElementById("signup-pass2").hidden = !signup;
  document.getElementById("signin-role").hidden = signup;
  document.getElementById("li-submit").textContent = signup ? "Create account" : "Sign in";
  document.getElementById("switch-auth").textContent = signup
    ? "Already have an account? Sign in"
    : "Don't have an account yet? Create one";
  document.getElementById("auth-sub").textContent = signup
    ? "Create an account and pick citizen, manager, or admin below."
    : "Sign in with your CENRO Manolo Fortich account, or create an account below.";
  document.getElementById("li-error").textContent = "";
  document.getElementById("li-password").autocomplete = signup ? "new-password" : "current-password";

  if(signup){
    // reset the signup role picker back to Citizen every time we enter signup mode
    signupRole = "User";
    document.querySelectorAll('#signup-role-pick .role-pick-btn').forEach(b=>
      b.classList.toggle('active', b.dataset.role === "User"));
  }
}

document.getElementById("tab-signin").addEventListener("click", ()=>setAuthMode("signin"));
document.getElementById("tab-signup").addEventListener("click", ()=>setAuthMode("signup"));
document.getElementById("switch-auth").addEventListener("click", ()=>setAuthMode(authMode==="signin"?"signup":"signin"));

document.querySelectorAll('#signup-role-pick .role-pick-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('#signup-role-pick .role-pick-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    signupRole = btn.dataset.role;
  });
});

const SIGNIN_EMAIL_PLACEHOLDER = {
  User: "citizen@example.com",
  Manager: "manager@example.com",
  Admin: "admin@example.com"
};

document.querySelectorAll("#li-role-pick .role-pick-btn").forEach(btn=>{
  btn.addEventListener("click", ()=>{
    document.querySelectorAll("#li-role-pick .role-pick-btn").forEach(item=>item.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById("li-email").placeholder = SIGNIN_EMAIL_PLACEHOLDER[btn.dataset.role];
  });
});

/* ---------------- ENTER / EXIT APP ---------------- */
async function enterAppFromProfile(profile){
  currentUser = profile;

  document.getElementById('login-screen').style.display = 'none';
  document.getElementById('app-shell').style.display = 'block';
  document.getElementById('hdr-name').textContent = currentUser.name;
  document.getElementById('hdr-role').textContent = currentUser.role;

  document.querySelectorAll('nav.roles button').forEach(btn=>{
    btn.style.display = ROLE_TABS[currentUser.role].includes(btn.dataset.role) ? '' : 'none';
    btn.classList.toggle('active', btn.dataset.role === 'dashboard');
  });
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  document.getElementById('view-dashboard').classList.add('active');

  await refreshAll();
}

function exitApp(){
  currentUser = null;
  reports = [];
  stopCamera();
  document.getElementById('app-shell').style.display = 'none';
  document.getElementById('login-screen').style.display = 'flex';
  document.getElementById('li-name').value = '';
  document.getElementById('li-email').value = '';
  document.getElementById('li-password').value = '';
  document.getElementById('li-password2').value = '';
  document.getElementById('li-error').textContent = '';
  setAuthMode("signin");
}

/* ---------------- SIGN IN / SIGN UP ---------------- */
document.getElementById('li-submit').addEventListener('click', async ()=>{
  const submitBtn = document.getElementById('li-submit');
  const name = document.getElementById('li-name').value.trim();
  const email = document.getElementById('li-email').value.trim();
  const pass = document.getElementById('li-password').value;
  const pass2 = document.getElementById('li-password2').value;
  const errEl = document.getElementById('li-error');

  if(!email || !pass){ errEl.textContent = "Enter email and password."; return; }

  submitBtn.disabled = true;
  const originalLabel = submitBtn.textContent;
  submitBtn.textContent = authMode === "signup" ? "Creating…" : "Signing in…";
  errEl.textContent = "";

  try{
    if(authMode === "signup"){
      if(!name){ errEl.textContent = "Enter your full name."; return; }
      if(pass.length < 6){ errEl.textContent = "Password must be at least 6 characters."; return; }
      if(pass !== pass2){ errEl.textContent = "Passwords do not match."; return; }

      const { profile } = await apiPostJSON('signup.php', { name, email, password: pass, role: signupRole });
      await enterAppFromProfile(profile);
      return;
    }

    // sign in
    const { profile } = await apiPostJSON('signin.php', { email, password: pass });
    await enterAppFromProfile(profile);

  }catch(err){
    errEl.textContent = err.message || "Something went wrong. Please try again.";
  }finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalLabel;
  }
});

document.getElementById('logout-btn').addEventListener('click', async ()=>{
  try{ await apiPostJSON('signout.php', {}); }catch(err){ /* ignore */ }
  exitApp();
});

// Restore an existing session on page load (refresh-safe login)
(async ()=>{
  try{
    const { profile } = await apiGet('session.php');
    if(profile) await enterAppFromProfile(profile);
  }catch(err){
    // No session yet, or the server isn't reachable — stay on the login screen.
  }
})();

/* ---------------- ROLE SWITCHING (tab nav, post-login) ---------------- */
document.querySelectorAll('nav.roles button').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('nav.roles button').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
    document.getElementById('view-'+btn.dataset.role).classList.add('active');
    if(btn.dataset.role==='manager') renderManager();
    if(btn.dataset.role==='admin') renderAdmin();
    if(btn.dataset.role==='user'){ renderUserReports(); startCamera(); ensureLocationMap(); }
    if(btn.dataset.role==='dashboard') renderDashboard();
    if(btn.dataset.role!=='user') stopCamera();
  });
});

/* ---------------- CLASSIFICATION ENGINE ---------------- */
function classify(symptoms, context){
  let base = 1;
  symptoms.forEach(s=>{
    const box = document.querySelector(`.check-row input[value="${s}"]`);
    const lvl = parseInt(box?.dataset.lvl || 1);
    if(lvl > base) base = lvl;
  });
  let level = base;

  // Sidewalk / pathway consequence multiplier (Section 3.6)
  if(context === "Sidewalk/Pathway" && (symptoms.includes("root_heaving") || symptoms.includes("cavities"))){
    level = Math.max(level, 3);
  } else if(context === "Sidewalk/Pathway" && level < 2 && symptoms.length){
    level = Math.max(level, 2); // elevated foot-traffic consequence score
  }

  // School / Hospital auto-flag: +1 tier regardless of score (Section 3.4)
  if(context === "School Zone" || context === "Hospital Zone"){
    level = Math.min(level + 1, 4);
  }
  return {base, level};
}

function updatePreview(){
  const checked = [...document.querySelectorAll('.check-row input:checked')].map(i=>i.value);
  const context = document.getElementById('u-context').value;
  const out = document.getElementById('p-level');
  if(!checked.length){
    out.innerHTML = "— select at least one condition —";
    return;
  }
  const {base, level} = classify(checked, context);
  const info = LEVEL_INFO[level];
  let flagNote = "";
  if(level > base) flagNote = ` <span style="color:var(--ink-soft);">(raised from L${base} — ${context})</span>`;
  out.innerHTML = `<span class="lvl-tag ${info.color}">L${level}</span>${info.label}${flagNote}`;
}
document.querySelectorAll('.check-row input, #u-context').forEach(el=>el.addEventListener('change', updatePreview));

/* ---------------- CAMERA ---------------- */
let camStream = null;
let capturedPhoto = null;     // Blob or File, uploaded to the PHP backend on submit
let capturedPhotoUrl = null;  // local object URL, just for the <img> preview

function setCamStatus(msg){
  const el = document.getElementById("cam-status");
  if(el) el.textContent = msg;
}

function releaseCapturedPhoto(){
  if(capturedPhotoUrl) URL.revokeObjectURL(capturedPhotoUrl);
  capturedPhoto = null;
  capturedPhotoUrl = null;
}

async function startCamera(){
  const video = document.getElementById("cam-video");
  const preview = document.getElementById("cam-preview");
  if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
    setCamStatus("Camera not supported — use Upload");
    return;
  }
  try{
    camStream = await navigator.mediaDevices.getUserMedia({
      video:{ facingMode:{ ideal:"environment" }, width:{ ideal:1280 }, height:{ ideal:720 } },
      audio:false
    });
    video.srcObject = camStream;
    video.hidden = false;
    preview.hidden = true;
    releaseCapturedPhoto();
    document.getElementById("cam-snap").disabled = false;
    document.getElementById("cam-retake").disabled = true;
    setCamStatus("LIVE · line up the tree");
  }catch(err){
    setCamStatus("Camera blocked — use Upload instead");
  }
}

function stopCamera(){
  if(camStream){
    camStream.getTracks().forEach(t=>t.stop());
    camStream = null;
  }
  const video = document.getElementById("cam-video");
  if(video) video.srcObject = null;
}

function snapPhoto(){
  const video = document.getElementById("cam-video");
  const canvas = document.getElementById("cam-canvas");
  const preview = document.getElementById("cam-preview");
  if(!video || !video.videoWidth){ setCamStatus("Wait for camera…"); return; }
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext("2d").drawImage(video, 0, 0);
  canvas.toBlob(blob=>{
    if(!blob) return;
    if(capturedPhotoUrl) URL.revokeObjectURL(capturedPhotoUrl);
    capturedPhoto = blob;
    capturedPhotoUrl = URL.createObjectURL(blob);
    preview.src = capturedPhotoUrl;
    preview.hidden = false;
    video.hidden = true;
    document.getElementById("cam-retake").disabled = false;
    setCamStatus("CAPTURED · retake if blurry");
  }, "image/jpeg", 0.82);
}

function retakePhoto(){
  releaseCapturedPhoto();
  document.getElementById("cam-preview").hidden = true;
  document.getElementById("cam-video").hidden = false;
  document.getElementById("cam-retake").disabled = true;
  if(!camStream) startCamera();
  else setCamStatus("LIVE · line up the tree");
}

document.getElementById("cam-start").addEventListener("click", startCamera);
document.getElementById("cam-snap").addEventListener("click", snapPhoto);
document.getElementById("cam-retake").addEventListener("click", retakePhoto);
document.getElementById("cam-file").addEventListener("change", e=>{
  const file = e.target.files && e.target.files[0];
  if(!file) return;
  if(capturedPhotoUrl) URL.revokeObjectURL(capturedPhotoUrl);
  capturedPhoto = file;
  capturedPhotoUrl = URL.createObjectURL(file);
  const preview = document.getElementById("cam-preview");
  const video = document.getElementById("cam-video");
  preview.src = capturedPhotoUrl;
  preview.hidden = false;
  video.hidden = true;
  document.getElementById("cam-retake").disabled = false;
  setCamStatus("UPLOADED from device");
});

/* ---------------- GEOLOCATION (GPS + digital map picker) ---------------- */
const DEFAULT_CENTER = [8.3667, 124.8667]; // Manolo Fortich, Bukidnon — fallback map center
let locationMap = null;
let locationMarker = null;
let pickedLat = null;
let pickedLng = null;

function setPickedLocation(lat, lng, sourceLabel){
  pickedLat = lat;
  pickedLng = lng;
  document.getElementById('u-coords').textContent =
    `${lat.toFixed(5)}° N, ${lng.toFixed(5)}° E — ${sourceLabel}.`;
}

function resetLocationPicker(){
  pickedLat = null;
  pickedLng = null;
  document.getElementById('u-coords').textContent = "No coordinates captured yet.";
  if(locationMap && locationMarker){
    locationMarker.setLatLng(DEFAULT_CENTER);
    locationMap.setView(DEFAULT_CENTER, 14);
  }
}

// Lazily initialize the map the first time the Citizen Reporter tab is opened —
// Leaflet needs the container to actually be visible/sized to render correctly.
function ensureLocationMap(){
  if(locationMap){ setTimeout(()=>locationMap.invalidateSize(), 0); return; }
  if(typeof L === 'undefined'){
    document.getElementById('u-map').innerHTML =
      '<p class="hint" style="padding:12px;">Map library failed to load — you can still use GPS above.</p>';
    return;
  }
  locationMap = L.map('u-map').setView(DEFAULT_CENTER, 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
  }).addTo(locationMap);
  locationMarker = L.marker(DEFAULT_CENTER, { draggable:true }).addTo(locationMap);
  locationMarker.on('dragend', ()=>{
    const pos = locationMarker.getLatLng();
    setPickedLocation(pos.lat, pos.lng, 'pinned on map');
  });
  locationMap.on('click', e=>{
    locationMarker.setLatLng(e.latlng);
    setPickedLocation(e.latlng.lat, e.latlng.lng, 'pinned on map');
  });
  setTimeout(()=>locationMap.invalidateSize(), 0);
}

document.getElementById('u-locate').addEventListener('click', ()=>{
  const out = document.getElementById('u-coords');
  if(!navigator.geolocation){ out.textContent = "Geolocation not supported by this browser."; return; }
  out.textContent = "Locating…";
  navigator.geolocation.getCurrentPosition(
    pos=>{
      const { latitude, longitude } = pos.coords;
      setPickedLocation(latitude, longitude, 'from device GPS');
      if(locationMap && locationMarker){
        locationMarker.setLatLng([latitude, longitude]);
        locationMap.setView([latitude, longitude], 16);
      }
    },
    err=>{
      out.textContent = "Location permission denied — tap the map instead to drop a pin.";
    }
  );
});

/* ---------------- SUBMIT REPORT ---------------- */
document.getElementById('u-submit').addEventListener('click', async ()=>{
  const checked = [...document.querySelectorAll('.check-row input:checked')].map(i=>i.value);
  const context = document.getElementById('u-context').value;
  const desc = document.getElementById('u-desc').value.trim();
  const species = document.getElementById('u-species').value.trim() || "Unidentified species";

  if(!checked.length){ alert("Select at least one observed condition so KUBLI can classify the risk."); return; }
  if(!desc){ alert("Add a short description of what you observed."); return; }
  if(!capturedPhoto){ alert("Take or upload a photo of the tree first."); return; }
  if(pickedLat === null || pickedLng === null){ alert("Set the tree's location — use GPS or tap the map."); return; }
  if(!currentUser){ alert("Your session expired — please sign in again."); return; }

  const {base, level} = classify(checked, context);
  const coords = `${pickedLat.toFixed(5)}° N, ${pickedLng.toFixed(5)}° E`;

  const btn = document.getElementById('u-submit');
  btn.disabled = true;
  btn.textContent = "Submitting…";

  try{
    const form = new FormData();
    form.append('species', species);
    form.append('description', desc);
    form.append('context', context);
    form.append('symptoms', JSON.stringify(checked));
    form.append('base_level', base);
    form.append('level', level);
    form.append('coords', coords);
    form.append('lat', pickedLat);
    form.append('lng', pickedLng);
    form.append('photo', capturedPhoto, 'report.jpg');

    await apiPostForm('reports.php', form);

    document.getElementById('u-desc').value = "";
    document.getElementById('u-species').value = "";
    releaseCapturedPhoto();
    document.getElementById("cam-preview").hidden = true;
    document.getElementById("cam-preview").removeAttribute("src");
    document.getElementById("cam-video").hidden = false;
    document.getElementById("cam-file").value = "";
    setCamStatus(camStream ? "LIVE · line up the tree" : "Camera off");
    document.querySelectorAll('.check-row input').forEach(i=>i.checked=false);
    resetLocationPicker();
    updatePreview();

    await refreshAll();
    alert(`Report submitted — classified as Level ${level} (${LEVEL_INFO[level].label}).`);
  }catch(err){
    alert("Could not submit report: " + (err.message || err));
  }finally{
    btn.disabled = false;
    btn.textContent = "Submit report";
  }
});

document.getElementById('u-clear').addEventListener('click', ()=>{
  document.getElementById('u-desc').value = "";
  document.getElementById('u-species').value = "";
  releaseCapturedPhoto();
  document.getElementById("cam-preview").hidden = true;
  document.getElementById("cam-preview").removeAttribute("src");
  document.getElementById("cam-video").hidden = false;
  document.getElementById("cam-file").value = "";
  setCamStatus(camStream ? "LIVE · line up the tree" : "Camera off");
  document.querySelectorAll('.check-row input').forEach(i=>i.checked=false);
  resetLocationPicker();
  updatePreview();
});

/* ---------------- DATA FETCH ---------------- */
async function fetchReports(){
  try{
    const data = await apiGet('reports.php');
    reports = data.map(r=>({
      id: 'KB-' + (1000 + r.id),
      dbId: r.id,
      species: r.species,
      desc: r.description,
      context: r.context,
      symptoms: r.symptoms || [],
      baseLevel: r.base_level,
      level: r.level,
      coords: r.coords,
      lat: r.lat,
      lng: r.lng,
      photo: r.photo_url,
      status: r.status,
      scope: r.scope,
      submittedBy: r.submitted_by_name,
      submittedById: r.submitted_by,
      ts: new Date(r.created_at.replace(' ', 'T')).toLocaleDateString('en-US', { month:'short', day:'numeric' })
    }));
  }catch(err){
    console.error('fetchReports failed:', err);
    reports = [];
  }
}

async function refreshAll(){
  await fetchReports();
  renderDashboard();
  renderUserReports();
  renderManager();
  if(currentUser && currentUser.role === 'Admin') await renderAdmin();
}

/* ---------------- RENDER: DASHBOARD ---------------- */
const LEVEL_COLOR = {1:"var(--canopy)",2:"var(--amber)",3:"var(--rust)",4:"var(--hazard)"};

function renderDashboard(){
  const total = reports.length;
  const counts = {1:0,2:0,3:0,4:0};
  reports.forEach(r=>counts[r.level]++);
  const highSevere = counts[3]+counts[4];
  const resolved = reports.filter(r=>r.status==="Resolved").length;
  const awaitingPermit = reports.filter(r=>r.status!=="Resolved" && !r.scope).length;

  document.getElementById('dash-stats').innerHTML = `
    <div class="stat-box"><div class="num" style="color:var(--forest-deep);">${total}</div><div class="lbl">Total reports</div></div>
    <div class="stat-box"><div class="num" style="color:var(--hazard);">${highSevere}</div><div class="lbl">High + Severe (L3–L4)</div></div>
    <div class="stat-box"><div class="num" style="color:var(--amber);">${awaitingPermit}</div><div class="lbl">Awaiting permit scope</div></div>
    <div class="stat-box"><div class="num" style="color:var(--canopy);">${resolved}</div><div class="lbl">Resolved</div></div>
  `;

  // --- donut chart (risk distribution) ---
  const donut = document.getElementById('dash-donut');
  const r = 52, c = 2*Math.PI*r;
  let offset = 0;
  let segments = "";
  [4,3,2,1].forEach(lvl=>{
    const frac = total ? counts[lvl]/total : 0;
    const len = frac * c;
    segments += `<circle cx="70" cy="70" r="${r}" fill="none" stroke="${LEVEL_COLOR[lvl]}" stroke-width="18"
      stroke-dasharray="${len} ${c-len}" stroke-dashoffset="${-offset}" transform="rotate(-90 70 70)"/>`;
    offset += len;
  });
  const svg = total ? `
    <svg width="140" height="140" viewBox="0 0 140 140">
      ${segments}
      <circle cx="70" cy="70" r="${r-9}" fill="#FBF8EF"/>
      <text x="70" y="66" text-anchor="middle" font-family="Fraunces, serif" font-size="22" font-weight="600" fill="var(--forest-deep)">${total}</text>
      <text x="70" y="82" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="8" fill="var(--ink-soft)">REPORTS</text>
    </svg>` : `<svg width="140" height="140" viewBox="0 0 140 140"><circle cx="70" cy="70" r="${r}" fill="none" stroke="var(--parchment-dark)" stroke-width="18"/></svg>`;

  const legend = [4,3,2,1].map(lvl=>`
    <div class="legend-item">
      <span class="legend-swatch" style="background:${LEVEL_COLOR[lvl]};"></span>
      L${lvl} — ${LEVEL_INFO[lvl].label} <b>${counts[lvl]}</b>
    </div>`).join("");

  donut.innerHTML = `${svg}<div class="donut-legend">${legend}</div>`;

  // --- status breakdown bars ---
  const statuses = ["Pending Review","Field Validation","Permit Routed","Resolved"];
  const statusCounts = statuses.map(s=>reports.filter(r=>r.status===s).length);
  const maxStatus = Math.max(1, ...statusCounts);
  document.getElementById('dash-status-bars').innerHTML = statuses.map((s,i)=>`
    <div class="bar-row">
      <div class="bar-label"><span>${s}</span><b>${statusCounts[i]}</b></div>
      <div class="bar-track"><div class="bar-fill" style="width:${(statusCounts[i]/maxStatus*100)}%;background:var(--canopy);"></div></div>
    </div>`).join("");

  // --- location context bars ---
  const contexts = [...new Set(reports.map(r=>r.context))];
  const ctxCounts = contexts.map(c=>reports.filter(r=>r.context===c).length);
  const maxCtx = Math.max(1, ...ctxCounts);
  document.getElementById('dash-context-bars').innerHTML = contexts.length ? contexts.map((c,i)=>`
    <div class="bar-row">
      <div class="bar-label"><span>${c}</span><b>${ctxCounts[i]}</b></div>
      <div class="bar-track"><div class="bar-fill" style="width:${(ctxCounts[i]/maxCtx*100)}%;background:var(--bark);"></div></div>
    </div>`).join("") : `<div class="empty">No reports yet.</div>`;

  // --- recent activity ---
  const recent = [...reports].slice(0,3);
  document.getElementById('dash-recent').innerHTML = recent.length
    ? recent.map(r=>tagCard(r)).join("")
    : `<div class="empty">No activity yet.</div>`;
}

/* ---------------- RENDER: USER REPORTS ---------------- */
function renderUserReports(){
  const wrap = document.getElementById('u-reports-list');
  const mine = reports.filter(r=>currentUser && r.submittedById===currentUser.id);
  if(!mine.length){
    wrap.innerHTML = `<div class="empty">You haven't submitted a report yet. Fill out the form to see it tracked here.</div>`;
    return;
  }
  wrap.innerHTML = mine.map(r=>tagCard(r)).join("");
}

function tagCard(r){
  const info = LEVEL_INFO[r.level];
  return `
  <div class="field-tag">
    <div class="punch"></div>
    <div class="body">
      <div class="top-row">
        <span class="id mono">${r.id} · ${r.ts}</span>
        <span class="stamp ${info.color}">RISK L${r.level}</span>
      </div>
      <h4>${r.species}</h4>
      ${r.photo ? `<img class="report-photo" src="${r.photo}" alt="Evidence photo for ${r.id}">` : ""}
      <div class="meta">📍 ${r.coords} &nbsp;·&nbsp; ${r.context}${(r.lat!=null && r.lng!=null) ? ` &nbsp;·&nbsp; <a class="text-link" href="https://www.google.com/maps?q=${r.lat},${r.lng}" target="_blank" rel="noopener">View on map ↗</a>` : ""}</div>
      <div class="desc">${r.desc}</div>
      <span class="status-pill">${r.status}</span>
      ${r.scope ? `<span class="status-pill" style="margin-left:6px;">${r.scope}</span>` : ""}
      <div class="action-note">${info.action}<br><span class="mono" style="font-size:0.7rem;">SLA: ${info.sla}</span></div>
    </div>
  </div>`;
}

/* ---------------- RENDER: MANAGER ---------------- */
function renderManager(){
  const stats = document.getElementById('mgr-stats');
  const counts = {1:0,2:0,3:0,4:0};
  reports.forEach(r=>counts[r.level]++);
  stats.innerHTML = [4,3,2,1].map(l=>`
    <div class="stat-box">
      <div class="num" style="color:var(--${l===4?'hazard':l===3?'rust':l===2?'amber':'canopy'});">${counts[l]}</div>
      <div class="lbl">Level ${l} — ${LEVEL_INFO[l].label}</div>
    </div>`).join("");

  document.getElementById('f-level').onchange = renderManagerList;
  document.getElementById('f-status').onchange = renderManagerList;
  renderManagerList();
}

function renderManagerList(){
  const fLevel = document.getElementById('f-level').value;
  const fStatus = document.getElementById('f-status').value;
  let list = [...reports].sort((a,b)=>b.level-a.level);
  if(fLevel!=='all') list = list.filter(r=>String(r.level)===fLevel);
  if(fStatus!=='all') list = list.filter(r=>r.status===fStatus);

  const container = document.getElementById('mgr-list');
  if(!list.length){
    container.innerHTML = `<div class="empty">No reports match this filter.</div>`;
    return;
  }
  container.innerHTML = list.map(r=>{
    const info = LEVEL_INFO[r.level];
    const isFast = r.context === "School Zone" || r.context === "Hospital Zone";
    return `
    <div class="mgr-card">
      <div class="mgr-top">
        <div>
          <h4>${r.species} <span class="stamp ${info.color}" style="font-size:0.6rem;">L${r.level}</span></h4>
          <div class="meta mono" style="font-size:0.74rem;color:var(--ink-soft);">${r.id} · 📍 ${r.coords} · ${r.context}${isFast ? " · ⚡ fast-track (24–48hr)" : ""}${(r.lat!=null && r.lng!=null) ? ` · <a class="text-link" href="https://www.google.com/maps?q=${r.lat},${r.lng}" target="_blank" rel="noopener">map ↗</a>` : ""}</div>
        </div>
        <span class="status-pill">${r.status}</span>
      </div>
      <div class="desc" style="margin-top:8px;font-size:0.85rem;">${r.desc}</div>
      <div class="action-note">${info.action}</div>

      <div class="mgr-grid">
        <div class="mgr-field">
          <label>Status</label>
          <select data-id="${r.id}" class="mgr-status">
            ${["Pending Review","Field Validation","Permit Routed","Resolved"].map(s=>`<option ${s===r.status?"selected":""}>${s}</option>`).join("")}
          </select>
        </div>
        <div class="mgr-field">
          <label>Permit scope classification</label>
          <select data-id="${r.id}" class="mgr-scope">
            <option value="">— not yet classified —</option>
            ${Object.keys(PERMIT_TABLE).map(s=>`<option value="${s}" ${r.scope===s?"selected":""}>${s}</option>`).join("")}
          </select>
        </div>
      </div>
      ${r.scope ? `<div class="hint" style="margin-top:8px;"><b>Permit track:</b> ${PERMIT_TABLE[r.scope]}</div>` : ""}
    </div>`;
  }).join("");

  container.querySelectorAll('.mgr-status').forEach(sel=>{
    sel.addEventListener('change', async e=>{
      const rpt = reports.find(x=>x.id===e.target.dataset.id);
      if(!rpt) return;
      const prev = rpt.status;
      rpt.status = e.target.value; // optimistic
      try{
        await apiPostJSON('report_update.php', { id: rpt.dbId, status: e.target.value });
      }catch(err){
        alert('Could not update status: ' + err.message);
        rpt.status = prev;
      }
      renderManagerList();
      renderDashboard();
    });
  });
  container.querySelectorAll('.mgr-scope').forEach(sel=>{
    sel.addEventListener('change', async e=>{
      const rpt = reports.find(x=>x.id===e.target.dataset.id);
      if(!rpt) return;
      const prev = rpt.scope;
      rpt.scope = e.target.value || null; // optimistic
      try{
        await apiPostJSON('report_update.php', { id: rpt.dbId, scope: rpt.scope });
      }catch(err){
        alert('Could not update permit scope: ' + err.message);
        rpt.scope = prev;
      }
      renderManagerList();
      renderDashboard();
    });
  });
}

/* ---------------- RENDER: ADMIN ---------------- */
async function renderAdmin(){
  const tbody = document.getElementById('a-table');
  tbody.innerHTML = `<tr><td colspan="4" class="hint">Loading accounts…</td></tr>`;

  let profiles;
  try{
    profiles = await apiGet('users.php');
  }catch(err){
    tbody.innerHTML = `<tr><td colspan="4" class="hint" style="color:var(--hazard);">Couldn't load accounts: ${err.message}</td></tr>`;
    return;
  }

  tbody.innerHTML = profiles.map(p=>`
    <tr>
      <td><b>${p.name}</b><br><span class="hint">${p.email}</span></td>
      <td>
        <select class="a-role-select" data-id="${p.id}" ${p.id===currentUser.id ? 'disabled title="You can\'t change your own role"' : ''}>
          ${['User','Manager','Admin'].map(r=>`<option value="${r}" ${r===p.role?'selected':''}>${r}</option>`).join('')}
        </select>
      </td>
      <td><span class="${p.status==='Active'?'status-active':'status-pending'}">${p.status}</span></td>
      <td>
        <button class="btn btn-outline btn-sm a-toggle-status" data-id="${p.id}" data-next="${p.status==='Active'?'Suspended':'Active'}" ${p.id===currentUser.id ? 'disabled title="You can\'t suspend yourself"' : ''}>
          ${p.status==='Active' ? 'Suspend' : 'Reactivate'}
        </button>
      </td>
    </tr>`).join("");

  tbody.querySelectorAll('.a-role-select').forEach(sel=>{
    sel.addEventListener('change', async e=>{
      try{
        await apiPostJSON('user_update.php', { id: Number(e.target.dataset.id), role: e.target.value });
      }catch(err){
        alert('Could not update role: ' + err.message);
      }
      renderAdmin();
    });
  });
  tbody.querySelectorAll('.a-toggle-status').forEach(btn=>{
    btn.addEventListener('click', async e=>{
      const id = Number(e.target.dataset.id);
      const next = e.target.dataset.next;
      try{
        await apiPostJSON('user_update.php', { id, status: next });
      }catch(err){
        alert('Could not update status: ' + err.message);
      }
      renderAdmin();
    });
  });
}

/* ---------------- ADMIN: ADD ACCOUNT ---------------- */
document.getElementById('a-add').addEventListener('click', async ()=>{
  const nameEl = document.getElementById('a-name');
  const emailEl = document.getElementById('a-email');
  const roleEl = document.getElementById('a-role');
  const name = nameEl.value.trim();
  const email = emailEl.value.trim();
  const role = roleEl.value;

  if(!name || !email){ alert('Enter a name and email address.'); return; }

  const btn = document.getElementById('a-add');
  btn.disabled = true;
  btn.textContent = 'Creating…';
  try{
    const { temp_password } = await apiPostJSON('user_create.php', { name, email, role });
    nameEl.value = '';
    emailEl.value = '';
    roleEl.value = 'User';
    await renderAdmin();
    alert(`Account created for ${name}.\n\nTemporary password: ${temp_password}\n\nShare this with them securely — they should sign in and it's up to them whether to keep it (there's no in-app password change yet).`);
  }catch(err){
    alert('Could not create account: ' + err.message);
  }finally{
    btn.disabled = false;
    btn.textContent = 'Create account';
  }
});
