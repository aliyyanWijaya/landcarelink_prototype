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
const form = document.getElementById("group-form");
const formTitle = document.getElementById("form-title");
const formError = document.getElementById("form-error");
const cancelBtn = document.getElementById("cancel-btn");
const tableBody = document.getElementById("groups-body");
const emptyMsg = document.getElementById("empty-msg");

const fields = ["name", "type", "region", "contact_email", "latitude", "longitude"];

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
  tableBody.innerHTML = "";
  emptyMsg.classList.toggle("hidden", groups.length > 0);

  for (const g of groups) {
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
    tr.querySelector(".edit").addEventListener("click", () => startEdit(g));
    tr.querySelector(".danger").addEventListener("click", () => removeGroup(g.id));
    tableBody.appendChild(tr);
  }
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

async function submitForm(event) {
  event.preventDefault();
  formError.textContent = "";

  const id = document.getElementById("group-id").value;
  const payload = {
    name: document.getElementById("name").value,
    type: document.getElementById("type").value,
    region: document.getElementById("region").value,
    contact_email: document.getElementById("contact_email").value,
    latitude: parseFloat(document.getElementById("latitude").value),
    longitude: parseFloat(document.getElementById("longitude").value),
  };

  try {
    if (id) {
      await apiRequest(`${API_BASE}/${id}`, { method: "PUT", body: JSON.stringify(payload) });
    } else {
      await apiRequest(API_BASE, { method: "POST", body: JSON.stringify(payload) });
    }
    resetForm();
    await loadGroups();
  } catch (e) {
    const detail = Object.values(e.fieldErrors || {}).join(" ");
    formError.textContent = (e.message + (detail ? " — " + detail : "")).trim();
  }
}

function startEdit(g) {
  document.getElementById("group-id").value = g.id;
  for (const f of fields) document.getElementById(f).value = g[f];
  formTitle.textContent = "Edit group";
  cancelBtn.classList.remove("hidden");
  formError.textContent = "";
  window.scrollTo({ top: 0, behavior: "smooth" });
}

async function removeGroup(id) {
  if (!confirm("Delete this group?")) return;
  try {
    await apiRequest(`${API_BASE}/${id}`, { method: "DELETE" });
    await loadGroups();
  } catch (e) {
    formError.textContent = "Delete failed: " + e.message;
  }
}

function resetForm() {
  form.reset();
  document.getElementById("group-id").value = "";
  formTitle.textContent = "Add a group";
  cancelBtn.classList.add("hidden");
  formError.textContent = "";
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

// --- Wire up ----------------------------------------------------------------
form.addEventListener("submit", submitForm);
cancelBtn.addEventListener("click", resetForm);
loadGroups();
