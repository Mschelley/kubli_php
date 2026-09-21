<?php header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KUBLI — Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div id="login-screen">
    <div class="login-card">
      <div class="brand" style="justify-content:center;margin-bottom:6px;">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C9 6 5 9 5 13a7 7 0 0014 0c0-4-4-7-7-11z" fill="#16281F"/><path d="M12 13v9" stroke="#16281F" stroke-width="1.5"/></svg>
        </div>
        <h1 style="color:var(--forest-deep);">KUBLI</h1>
      </div>

      <div class="auth-tabs">
        <button type="button" class="auth-tab active" id="tab-signin" data-mode="signin">Sign in</button>
        <button type="button" class="auth-tab" id="tab-signup" data-mode="signup">Create account</button>
      </div>

      <p class="login-sub" id="auth-sub">Sign in to your KUBLI account.</p>

      <div id="signup-only" hidden>
        <label for="li-name">Full name</label>
        <input type="text" id="li-name" autocomplete="name">
      </div>

      <label for="li-email">Email address</label>
      <input type="email" id="li-email" autocomplete="username">

      <label for="li-password">Password</label>
      <input type="password" id="li-password" autocomplete="current-password">

      <div id="signup-pass2" hidden>
        <label for="li-password2">Confirm password</label>
        <input type="password" id="li-password2" autocomplete="new-password">
        <p class="hint">New accounts start as Citizen.</p>
      </div>

      <div class="btn-row" style="margin-top:20px;">
        <button class="btn" id="li-submit" style="flex:1;">Sign in</button>
      </div>
      <p class="hint" id="li-error" style="color:var(--hazard);min-height:14px;margin-top:10px;"></p>
      <p class="hint" style="text-align:center;margin-top:12px;">
        <button type="button" class="text-link" id="switch-auth">Don't have an account yet? Create one</button>
      </p>
    </div>
</div>

<div id="app-shell" style="display:none;">
<header>
  <div class="header-top">
    <div class="brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C9 6 5 9 5 13a7 7 0 0014 0c0-4-4-7-7-11z" fill="#16281F"/><path d="M12 13v9" stroke="#16281F" stroke-width="1.5"/></svg>
      </div>
      <div>
        <h1>KUBLI</h1>
        <span>CENRO Manolo Fortich · TCP</span>
      </div>
    </div>
    <div class="user-badge">
      <div>
        <div id="hdr-name">—</div>
        <div id="hdr-role" class="mono">—</div>
      </div>
      <button class="btn btn-outline btn-sm" id="logout-btn" style="border-color:rgba(239,232,214,0.4);color:var(--parchment);">Log out</button>
    </div>
  </div>
  <nav class="roles">
    <button class="active" data-role="dashboard">Dashboard</button>
    <button data-role="user">Citizen Reporter</button>
    <button data-role="reportslog">Reports Log</button>
    <button data-role="manager">Report Queue &amp; Permit Routing</button>
    <button data-role="admin">User Accounts</button>
    <button data-role="about">About</button>
  </nav>
</header>

<main>

  <section class="view active" id="view-dashboard">
    <div class="panel-head">
      <div>
        <span class="eyebrow">System overview</span>
        <h2>CENRO Manolo Fortich — hazard &amp; permit overview</h2>
        <p>Live snapshot of reported hazards, processing status, and locations.</p>
      </div>
    </div>

    <div class="stats-strip" id="dash-stats"></div>

    <div class="dash-grid">
      <div class="card">
        <h3>Risk level distribution</h3>
        <div class="donut-wrap" id="dash-donut"></div>
      </div>

      <div class="card">
        <h3>Reports by status</h3>
        <div id="dash-status-bars"></div>
      </div>

      <div class="card">
        <h3>Reports by location context</h3>
        <div id="dash-context-bars"></div>
      </div>

      <div class="card">
        <h3>Recent activity</h3>
        <div class="tags-wrap" id="dash-recent"></div>
      </div>
    </div>
  </section>

  <section class="view" id="view-user">
    <div class="panel-head">
      <div>
        <span class="eyebrow">Citizen reporting</span>
        <h2>Report a hazardous tree</h2>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h3>New field report</h3>

        <label>Photo evidence</label>
        <div class="camera-booth">
          <div class="viewfinder">
            <span class="vf-corner tl"></span>
            <span class="vf-corner tr"></span>
            <span class="vf-corner bl"></span>
            <span class="vf-corner br"></span>
            <video id="cam-video" autoplay playsinline muted></video>
            <canvas id="cam-canvas" hidden></canvas>
            <img id="cam-preview" alt="Captured tree photo" hidden>
            <div class="cam-flash" id="cam-flash"></div>
            <p class="cam-status" id="cam-status">Requesting camera access…</p>
          </div>
          <div class="cam-actions">
            <button type="button" class="shutter-btn capture" id="cam-capture" title="Capture photo" disabled>Capture</button>
            <button type="button" class="btn btn-outline btn-sm" id="cam-retake" disabled>Retake</button>
            <label class="btn btn-outline btn-sm cam-upload">Upload<input type="file" id="cam-file" accept="image/*" capture="environment" hidden></label>
          </div>
        </div>

        <div class="label-row">
          <label for="u-desc">Description</label>
          <span class="ai-status" id="u-ai-status"></span>
        </div>
        <textarea id="u-desc"></textarea>

        <div class="field-row">
          <div class="species-field">
            <label for="u-species">Tree / species</label>
            <input type="text" id="u-species" autocomplete="off" placeholder="Search common tree name..." aria-autocomplete="list" aria-controls="u-species-results" aria-expanded="false">
            <div class="species-results" id="u-species-results" role="listbox" hidden></div>
            <p class="hint" id="u-species-status">Search by a common tree name.</p>
          </div>
          <div>
            <label for="u-context">Location context</label>
            <select id="u-context">
              <option value="Sidewalk/Pathway">Sidewalk / Pathway</option>
              <option value="School Zone">School Zone</option>
              <option value="Hospital Zone">Hospital Zone</option>
              <option value="Park">Park</option>
              <option value="Residential Area">Residential Area</option>
              <option value="Roadside">Roadside</option>
            </select>
          </div>
        </div>

        <label>GPS location</label>
        <button type="button" class="locate-btn" id="u-locate">📍 Use my current location</button>
        <div class="coords-out" id="u-coords">No coordinates captured yet.</div>
        <div id="u-map" class="location-map" aria-label="Choose tree location on map"></div>

        <fieldset style="margin-top:16px;">
          <legend>Observed condition</legend>
          <div class="check-row check-all"><input type="checkbox" id="u-all-conditions"><span><b>All</b></span></div>
          <div class="check-row"><input type="checkbox" data-lvl="1" value="dead_branches"><span><span class="lvl-tag lvl1">L1</span>Small dead branches or minor leaf discoloration</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="2" value="cracks"><span><span class="lvl-tag lvl2">L2</span>Visible cracks or minor lean</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="2" value="canopy_dieback"><span><span class="lvl-tag lvl2">L2</span>Partial canopy dieback</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="3" value="root_heaving"><span><span class="lvl-tag lvl3">L3</span>Root heaving / lifted pavement</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="3" value="cavities"><span><span class="lvl-tag lvl3">L3</span>Large trunk cavities</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="3" value="sig_lean"><span><span class="lvl-tag lvl3">L3</span>Significant lean</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="4" value="rupture"><span><span class="lvl-tag lvl4">L4</span>Visible trunk rupture</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="4" value="uprooting"><span><span class="lvl-tag lvl4">L4</span>Uprooting in progress</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="4" value="leaning_structure"><span><span class="lvl-tag lvl4">L4</span>Leaning on a structure or power lines</span></div>
          <div class="check-row"><input type="checkbox" data-lvl="4" value="storm"><span><span class="lvl-tag lvl4">L4</span>Storm-damaged</span></div>
        </fieldset>

        <div class="preview-classify" id="u-preview">
          <div class="row"><span>Computed risk level</span><b id="p-level">— select at least one condition —</b></div>
        </div>

        <div class="btn-row">
          <button class="btn" id="u-submit">Submit report</button>
          <button class="btn btn-outline" id="u-clear">Clear form</button>
        </div>
      </div>

      <div>
        <h3 class="display" style="margin:0 0 12px 0;color:var(--forest-deep);">My submitted reports</h3>
        <div class="tags-wrap" id="u-reports-list"></div>
      </div>
    </div>
  </section>

  <section class="view" id="view-reportslog">
    <div class="panel-head">
      <div>
        <span class="eyebrow">System oversight</span>
        <h2>Reports log</h2>
        <p>Full record of hazard reports submitted through KUBLI.</p>
      </div>
    </div>

    <div class="card">
      <div class="table-head-row">
        <h3>All reports</h3>
        <button class="btn btn-outline btn-sm" id="log-export">Export CSV</button>
      </div>
      <table>
        <thead><tr><th>Timestamp</th><th>Risk Level</th><th>Location</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="log-table"></tbody>
      </table>
    </div>
  </section>

  <section class="view" id="view-manager">
    <div class="panel-head">
      <div>
        <span class="eyebrow">Barangay / DENR officer</span>
        <h2>Report queue &amp; permit routing</h2>
        <p>Review reports by risk, validate cases, and assign permit scope.</p>
      </div>
    </div>

    <div class="stats-strip" id="mgr-stats"></div>

    <div class="filters">
      <select id="f-level">
        <option value="all">All risk levels</option>
        <option value="4">Level 4 — Severe/Emergency</option>
        <option value="3">Level 3 — High</option>
        <option value="2">Level 2 — Moderate</option>
        <option value="1">Level 1 — Low</option>
      </select>
      <select id="f-status">
        <option value="all">All statuses</option>
        <option value="Pending Review">Pending Review</option>
        <option value="Field Validation">Field Validation</option>
        <option value="Permit Routed">Permit Routed</option>
        <option value="Resolved">Resolved</option>
      </select>
      <button class="btn btn-outline btn-sm" id="mgr-print">Print PDF</button>
    </div>

    <div id="mgr-list"></div>
  </section>

  <section class="view" id="view-admin">
    <div class="panel-head">
      <div>
        <span class="eyebrow">Account management</span>
        <h2>User accounts</h2>
        <p>Manage citizen and staff accounts.</p>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h3>Add account</h3>
        <label for="a-name">Full name</label>
        <input type="text" id="a-name">
        <label for="a-email">Email address</label>
        <input type="text" id="a-email">
        <label for="a-role">Role</label>
        <select id="a-role">
          <option>User</option>
          <option>Manager</option>
          <option>Admin</option>
        </select>
        <div class="btn-row"><button class="btn" id="a-add">Create account</button></div>
      </div>

      <div class="card">
        <h3>Registered accounts</h3>
        <table>
          <thead><tr><th>Name</th><th>Role</th><th>Status</th><th></th></tr></thead>
          <tbody id="a-table"></tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="view" id="view-about">
    <div class="panel-head">
      <div>
        <span class="eyebrow">About</span>
        <h2>About KUBLI</h2>
        <p>Community-reported tree hazard data routed into the DENR permitting process.</p>
      </div>
    </div>

    <div class="card">
      <h3>How it works</h3>
      <p style="font-size:0.88rem;color:var(--ink);line-height:1.6;">
        Citizens photograph and pin a hazardous tree from the field. KUBLI
        classifies the reported conditions and surrounding context, then routes
        the report for field validation and permit processing. Admin oversees
        the reporting and permit workflow.
      </p>
    </div>
  </section>

</main>

<div class="report-modal" id="report-modal" hidden>
  <div class="report-modal-backdrop" data-close-report></div>
  <div class="report-modal-card" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
    <div class="report-modal-head">
      <div>
        <span class="eyebrow">Report details</span>
        <h2 id="report-modal-title">Report</h2>
      </div>
      <button class="btn btn-outline btn-sm" id="report-modal-close" type="button">Close</button>
    </div>
    <div id="report-modal-body"></div>
  </div>
</div>

<footer>KUBLI prototype — in-session data only.</footer>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="assets/js/app.js"></script>
</body>
</html>
