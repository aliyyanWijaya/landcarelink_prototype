/* LandcareLink frontend — vanilla JS + Fetch + Leaflet */

// Point this at your running PHP API.
const API_BASE = "http://localhost:8000/api/groups";

const TYPE_COLORS = {
  environmental_group: "#2e7d32",
  catchment_collective: "#1565c0",
  catchment_group: "#ef6c00",
};

const TYPE_LABELS = {
  environmental_group: "Environmental group",
  catchment_collective: "Catchment collective",
  catchment_group: "Catchment group",
};

// --- DOM refs ---------------------------------------------------------------
const formError = document.getElementById("form-error");
const tableBody = document.getElementById("groups-body");
const emptyMsg = document.getElementById("empty-msg");

// Last-loaded groups + which row (if any) is currently being edited inline.
let groupsCache = [];
let editingId = null;

// Draft for the always-present "add" row, so typed input survives re-renders
// triggered by editing other rows.
function blankDraft() {
  return {
    name: "",
    type: "environmental_group",
    region: "",
    contact_email: "",
    latitude: "",
    longitude: "",
  };
}
let addDraft = blankDraft();

// --- Map --------------------------------------------------------------------
const map = L.map("map").setView([-37.9, 176.0], 8); // Waikato / Bay of Plenty
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  maxZoom: 19,
  attribution: "&copy; OpenStreetMap contributors",
}).addTo(map);

let markerLayer = L.layerGroup().addTo(map);

// --- API helpers ------------------------------------------------------------
async function apiRequest(url, options = {}) {
  const res = await fetch(url, {
    headers: { "Content-Type": "application/json" },
    ...options,
  });
  const body = res.status === 204 ? {} : await res.json();
  if (!res.ok) {
    const err = new Error(body.error || "Request failed");
    err.fieldErrors = body.errors || {};
    throw err;
  }
  return body;
}

// --- Render -----------------------------------------------------------------
function renderTable(groups) {
  groupsCache = groups;
  tableBody.innerHTML = "";

  // The add-row always sits directly below the header.
  tableBody.appendChild(buildAddRow());

  emptyMsg.classList.toggle("hidden", groups.length > 0);

  for (const g of groups) {
    const tr = g.id === editingId ? buildEditRow(g) : buildDisplayRow(g);
    tableBody.appendChild(tr);
  }
}

function typeOptionsHtml(selected) {
  return Object.keys(TYPE_LABELS)
    .map(
      (t) =>
        `<option value="${t}"${t === selected ? " selected" : ""}>${TYPE_LABELS[t]}</option>`
    )
    .join("");
}

function buildDisplayRow(g) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${escapeHtml(g.name)}</td>
    <td>${TYPE_LABELS[g.type] || g.type}</td>
    <td>${escapeHtml(g.region)}</td>
    <td>${escapeHtml(g.contact_email)}</td>
    <td>${Number(g.latitude).toFixed(4)}</td>
    <td>${Number(g.longitude).toFixed(4)}</td>
    <td>
      <div class="actions">
        <button class="edit" data-id="${g.id}">Edit</button>
        <button class="danger" data-id="${g.id}">Delete</button>
      </div>
    </td>`;
  tr.querySelector(".edit").addEventListener("click", () => startEdit(g.id));
  tr.querySelector(".danger").addEventListener("click", () => removeGroup(g.id));
  return tr;
}

function buildEditRow(g) {
  const tr = document.createElement("tr");
  tr.className = "editing";
  tr.innerHTML = `
    <td><input type="text" class="cell-input" data-field="name" value="${escapeHtml(g.name)}" /></td>
    <td><select class="cell-input" data-field="type">${typeOptionsHtml(g.type)}</select></td>
    <td><input type="text" class="cell-input" data-field="region" value="${escapeHtml(g.region)}" /></td>
    <td><input type="email" class="cell-input" data-field="contact_email" value="${escapeHtml(g.contact_email)}" /></td>
    <td><input type="number" step="any" min="-90" max="90" class="cell-input" data-field="latitude" value="${g.latitude}" /></td>
    <td><input type="number" step="any" min="-180" max="180" class="cell-input" data-field="longitude" value="${g.longitude}" /></td>
    <td>
      <div class="actions">
        <button class="save">Save</button>
        <button class="secondary cancel">Cancel</button>
      </div>
    </td>`;
  tr.querySelector(".save").addEventListener("click", () => saveEdit(g.id, tr));
  tr.querySelector(".cancel").addEventListener("click", cancelEdit);
  return tr;
}

function buildAddRow() {
  const tr = document.createElement("tr");
  tr.className = "add-row";
  tr.innerHTML = `
    <td><input type="text" class="cell-input" data-field="name" placeholder="Name" /></td>
    <td><select class="cell-input" data-field="type">${typeOptionsHtml(addDraft.type)}</select></td>
    <td><input type="text" class="cell-input" data-field="region" placeholder="Region" /></td>
    <td><input type="email" class="cell-input" data-field="contact_email" placeholder="email@example.org" /></td>
    <td><input type="number" step="any" min="-90" max="90" class="cell-input" data-field="latitude" placeholder="Lat" /></td>
    <td><input type="number" step="any" min="-180" max="180" class="cell-input" data-field="longitude" placeholder="Long" /></td>
    <td>
      <div class="actions">
        <button class="add-btn">Add</button>
      </div>
    </td>`;

  // Pre-fill from the draft + keep it in sync so re-renders don't lose input.
  tr.querySelectorAll(".cell-input").forEach((el) => {
    const field = el.dataset.field;
    if (addDraft[field] !== "") el.value = addDraft[field];
    el.addEventListener("input", () => {
      addDraft[field] = el.value;
    });
  });

  tr.querySelector(".add-btn").addEventListener("click", () => addGroup(tr));
  return tr;
}

function renderMap(groups) {
  markerLayer.clearLayers();
  for (const g of groups) {
    const marker = L.circleMarker([Number(g.latitude), Number(g.longitude)], {
      radius: 9,
      color: "#fff",
      weight: 2,
      fillColor: TYPE_COLORS[g.type] || "#555",
      fillOpacity: 0.9,
    });
    marker.bindPopup(
      `<strong>${escapeHtml(g.name)}</strong><br>` +
        `${TYPE_LABELS[g.type] || g.type}<br>` +
        `${escapeHtml(g.region)}<br>` +
        `<a href="mailto:${escapeHtml(g.contact_email)}">${escapeHtml(g.contact_email)}</a>`
    );
    marker.addTo(markerLayer);
  }
}

// --- Helpers ----------------------------------------------------------------
// Read the field inputs of a row into an API payload.
function readRow(tr) {
  const payload = {};
  tr.querySelectorAll(".cell-input").forEach((el) => {
    const field = el.dataset.field;
    payload[field] =
      field === "latitude" || field === "longitude"
        ? parseFloat(el.value)
        : el.value;
  });
  return payload;
}

function showError(e) {
  const detail = Object.values(e.fieldErrors || {}).join(" ");
  formError.textContent = (e.message + (detail ? " — " + detail : "")).trim();
}

// --- Actions ----------------------------------------------------------------
async function loadGroups() {
  try {
    const { data } = await apiRequest(API_BASE);
    renderTable(data);
    renderMap(data);
  } catch (e) {
    formError.textContent = "Could not load groups: " + e.message;
  }
}

// Inline create via the always-present add-row.
async function addGroup(tr) {
  formError.textContent = "";
  try {
    await apiRequest(API_BASE, {
      method: "POST",
      body: JSON.stringify(readRow(tr)),
    });
    addDraft = blankDraft(); // reset the empty row back to blank
    await loadGroups();
  } catch (e) {
    showError(e);
  }
}

// Inline editing: only one existing row editable at a time. Switching rows
// cancels the previous edit by simply re-rendering with a new editingId.
function startEdit(id) {
  editingId = id;
  formError.textContent = "";
  renderTable(groupsCache);
}

function cancelEdit() {
  editingId = null;
  renderTable(groupsCache);
}

async function saveEdit(id, tr) {
  formError.textContent = "";
  try {
    await apiRequest(`${API_BASE}/${id}`, {
      method: "PUT",
      body: JSON.stringify(readRow(tr)),
    });
    editingId = null;
    await loadGroups();
  } catch (e) {
    showError(e);
  }
}

async function removeGroup(id) {
  if (!confirm("Delete this group?")) return;
  try {
    await apiRequest(`${API_BASE}/${id}`, { method: "DELETE" });
    if (editingId === id) editingId = null;
    await loadGroups();
  } catch (e) {
    formError.textContent = "Delete failed: " + e.message;
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

// --- Wire up ----------------------------------------------------------------
loadGroups();
