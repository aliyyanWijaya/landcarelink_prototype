/* LandcareLink frontend — vanilla JS + Fetch + Leaflet */

// Point this at your running PHP API.
const API_BASE = "http://localhost:8000/api/groups";

const NAME_MAX = 255;

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

const helpBtn = document.getElementById("help-btn");
const welcomeOverlay = document.getElementById("welcome-overlay");
const welcomeClose = document.getElementById("welcome-close");
const welcomeGotIt = document.getElementById("welcome-got-it");

const searchInput = document.getElementById("search-input");
const typeFilter = document.getElementById("type-filter");
const regionFilter = document.getElementById("region-filter");
const clearFiltersBtn = document.getElementById("clear-filters");
const filterCount = document.getElementById("filter-count");

// Full loaded dataset (filtering happens client-side against this).
let groupsCache = [];
// Which existing row (if any) is currently being edited inline.
let editingId = null;
// Active client-side filters.
let filters = { search: "", type: "", region: "" };

// Draft for the always-present "add" row, so typed input survives re-renders
// triggered by editing other rows or changing filters.
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

// --- Filtering --------------------------------------------------------------
function getFilteredGroups() {
  const q = filters.search.trim().toLowerCase();
  return groupsCache.filter((g) => {
    if (filters.type && g.type !== filters.type) return false;
    if (filters.region && g.region !== filters.region) return false;
    if (q) {
      const haystack = `${g.name} ${g.region} ${g.contact_email}`.toLowerCase();
      if (!haystack.includes(q)) return false;
    }
    return true;
  });
}

// Re-derive everything the filters affect: table, map, and the count.
function applyView() {
  const filtered = getFilteredGroups();
  renderTable(filtered);
  renderMap(filtered);
  filterCount.textContent = `Showing ${filtered.length} of ${groupsCache.length} groups`;
}

function populateRegionFilter() {
  const previous = regionFilter.value;
  const regions = [...new Set(groupsCache.map((g) => g.region))].sort((a, b) =>
    a.localeCompare(b)
  );
  regionFilter.innerHTML =
    `<option value="">All regions</option>` +
    regions
      .map((r) => `<option value="${escapeHtml(r)}">${escapeHtml(r)}</option>`)
      .join("");
  // Keep the prior selection if it still exists in the data.
  regionFilter.value = regions.includes(previous) ? previous : "";
  filters.region = regionFilter.value;
}

// --- Render -----------------------------------------------------------------
function renderTable(rows) {
  tableBody.innerHTML = "";

  // The add-row always sits directly below the header.
  tableBody.appendChild(buildAddRow());

  emptyMsg.classList.toggle("hidden", rows.length > 0);
  if (rows.length === 0) {
    emptyMsg.textContent =
      groupsCache.length === 0
        ? "No groups yet — add one in the row above."
        : "No groups match your filters. Try clearing filters or add a new group in the row above.";
  }

  for (const g of rows) {
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

// A table cell containing a field input plus its own inline error slot.
function fieldCell(inputHtml, field) {
  return `<td>${inputHtml}<div class="cell-error" data-error-for="${field}"></div></td>`;
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
  tr.innerHTML =
    fieldCell(`<input type="text" class="cell-input" data-field="name" maxlength="255" value="${escapeHtml(g.name)}" />`, "name") +
    fieldCell(`<select class="cell-input" data-field="type">${typeOptionsHtml(g.type)}</select>`, "type") +
    fieldCell(`<input type="text" class="cell-input" data-field="region" value="${escapeHtml(g.region)}" />`, "region") +
    fieldCell(`<input type="email" class="cell-input" data-field="contact_email" value="${escapeHtml(g.contact_email)}" />`, "contact_email") +
    fieldCell(`<input type="number" step="any" min="-90" max="90" class="cell-input" data-field="latitude" title="Decimal degrees, e.g. -37.9 (range -90 to 90)" value="${g.latitude}" />`, "latitude") +
    fieldCell(`<input type="number" step="any" min="-180" max="180" class="cell-input" data-field="longitude" title="Decimal degrees, e.g. 176.0 (range -180 to 180)" value="${g.longitude}" />`, "longitude") +
    `<td>
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
  tr.innerHTML =
    fieldCell(`<input type="text" class="cell-input" data-field="name" maxlength="255" placeholder="Name" />`, "name") +
    fieldCell(`<select class="cell-input" data-field="type">${typeOptionsHtml(addDraft.type)}</select>`, "type") +
    fieldCell(`<input type="text" class="cell-input" data-field="region" placeholder="Region" />`, "region") +
    fieldCell(`<input type="email" class="cell-input" data-field="contact_email" placeholder="email@example.org" />`, "contact_email") +
    fieldCell(`<input type="number" step="any" min="-90" max="90" class="cell-input" data-field="latitude" placeholder="e.g. -37.9" title="Decimal degrees, e.g. -37.9 (range -90 to 90)" />`, "latitude") +
    fieldCell(`<input type="number" step="any" min="-180" max="180" class="cell-input" data-field="longitude" placeholder="e.g. 176.0" title="Decimal degrees, e.g. 176.0 (range -180 to 180)" />`, "longitude") +
    `<td>
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

// --- Validation -------------------------------------------------------------
function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

// Client-side mirror of the server rules. Returns a map of field -> message.
function validateGroup(p) {
  const errors = {};

  const name = String(p.name ?? "").trim();
  if (!name) errors.name = "Name is required.";
  else if (name.length > NAME_MAX) errors.name = `Name must be ${NAME_MAX} characters or fewer.`;

  if (!TYPE_LABELS[p.type]) errors.type = "Select a valid type.";

  if (!String(p.region ?? "").trim()) errors.region = "Region is required.";

  const email = String(p.contact_email ?? "").trim();
  if (!email) errors.contact_email = "Contact email is required.";
  else if (!isValidEmail(email)) errors.contact_email = "Enter a valid email address.";

  if (p.latitude === "" || p.latitude === null || Number.isNaN(p.latitude)) {
    errors.latitude = "Latitude is required.";
  } else if (p.latitude < -90 || p.latitude > 90) {
    errors.latitude = "Latitude must be between -90 and 90.";
  }

  if (p.longitude === "" || p.longitude === null || Number.isNaN(p.longitude)) {
    errors.longitude = "Longitude is required.";
  } else if (p.longitude < -180 || p.longitude > 180) {
    errors.longitude = "Longitude must be between -180 and 180.";
  }

  return errors;
}

// name + region duplicate detection against loaded data.
function isDuplicate(p, excludeId = null) {
  const name = String(p.name ?? "").trim().toLowerCase();
  const region = String(p.region ?? "").trim().toLowerCase();
  return groupsCache.some(
    (g) =>
      g.id !== excludeId &&
      g.name.trim().toLowerCase() === name &&
      g.region.trim().toLowerCase() === region
  );
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

function clearRowErrors(tr) {
  tr.querySelectorAll(".cell-error").forEach((el) => (el.textContent = ""));
}

function showRowErrors(tr, errors) {
  clearRowErrors(tr);
  for (const [field, msg] of Object.entries(errors)) {
    const slot = tr.querySelector(`.cell-error[data-error-for="${field}"]`);
    if (slot) slot.textContent = msg;
  }
}

function showError(e) {
  const detail = Object.values(e.fieldErrors || {}).join(" ");
  formError.textContent = (e.message + (detail ? " — " + detail : "")).trim();
}

// Route an API error to per-field slots when possible, else the banner.
function handleApiError(e, tr) {
  if (tr && e.fieldErrors && Object.keys(e.fieldErrors).length) {
    showRowErrors(tr, e.fieldErrors);
  } else {
    showError(e);
  }
}

// --- Actions ----------------------------------------------------------------
async function loadGroups() {
  try {
    const { data } = await apiRequest(API_BASE);
    groupsCache = data;
    populateRegionFilter();
    applyView();
  } catch (e) {
    formError.textContent = "Could not load groups: " + e.message;
  }
}

// Inline create via the always-present add-row.
async function addGroup(tr) {
  formError.textContent = "";
  clearRowErrors(tr);

  const payload = readRow(tr);
  const errors = validateGroup(payload);
  if (Object.keys(errors).length) {
    showRowErrors(tr, errors);
    return;
  }
  if (
    isDuplicate(payload) &&
    !confirm(
      `A group named "${payload.name.trim()}" already exists in ${payload.region.trim()}. Add it anyway?`
    )
  ) {
    return;
  }

  try {
    await apiRequest(API_BASE, { method: "POST", body: JSON.stringify(payload) });
    addDraft = blankDraft(); // reset the empty row back to blank
    await loadGroups();
  } catch (e) {
    handleApiError(e, tr);
  }
}

// Inline editing: only one existing row editable at a time. Switching rows
// cancels the previous edit by simply re-rendering with a new editingId.
function startEdit(id) {
  editingId = id;
  formError.textContent = "";
  applyView();
}

function cancelEdit() {
  editingId = null;
  applyView();
}

async function saveEdit(id, tr) {
  formError.textContent = "";
  clearRowErrors(tr);

  const payload = readRow(tr);
  const errors = validateGroup(payload);
  if (Object.keys(errors).length) {
    showRowErrors(tr, errors);
    return;
  }
  if (
    isDuplicate(payload, id) &&
    !confirm(
      `Another group named "${payload.name.trim()}" already exists in ${payload.region.trim()}. Save anyway?`
    )
  ) {
    return;
  }

  try {
    await apiRequest(`${API_BASE}/${id}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });
    editingId = null;
    await loadGroups();
  } catch (e) {
    handleApiError(e, tr);
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

// --- Onboarding / help guidance --------------------------------------------
// Persisted flag so the welcome guidance only auto-shows on the first visit.
const WELCOME_SEEN_KEY = "landcarelink_welcome_seen";

// localStorage can throw (private mode, disabled storage); degrade gracefully.
function hasSeenWelcome() {
  try {
    return localStorage.getItem(WELCOME_SEEN_KEY) === "1";
  } catch {
    return false;
  }
}

function markWelcomeSeen() {
  try {
    localStorage.setItem(WELCOME_SEEN_KEY, "1");
  } catch {
    /* ignore — guidance just shows again next time */
  }
}

function openWelcome() {
  welcomeOverlay.classList.remove("hidden");
}

// Close + permanently dismiss (the "Got it" / "×" path).
function dismissWelcome() {
  welcomeOverlay.classList.add("hidden");
  markWelcomeSeen();
}

helpBtn.addEventListener("click", openWelcome);
welcomeGotIt.addEventListener("click", dismissWelcome);
welcomeClose.addEventListener("click", dismissWelcome);
// Click outside the modal (on the dim backdrop) also dismisses it.
welcomeOverlay.addEventListener("click", (e) => {
  if (e.target === welcomeOverlay) dismissWelcome();
});
// Esc closes it too.
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && !welcomeOverlay.classList.contains("hidden")) {
    dismissWelcome();
  }
});

if (!hasSeenWelcome()) openWelcome();

// --- Wire up ----------------------------------------------------------------
searchInput.addEventListener("input", () => {
  filters.search = searchInput.value;
  applyView();
});
typeFilter.addEventListener("change", () => {
  filters.type = typeFilter.value;
  applyView();
});
regionFilter.addEventListener("change", () => {
  filters.region = regionFilter.value;
  applyView();
});
clearFiltersBtn.addEventListener("click", () => {
  filters = { search: "", type: "", region: "" };
  searchInput.value = "";
  typeFilter.value = "";
  regionFilter.value = "";
  applyView();
});

loadGroups();
