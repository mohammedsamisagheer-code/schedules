let currentZoom = 0.7;
const ZOOM_STEP = 0.1;
const MIN_ZOOM = 0.3;
const MAX_ZOOM = 1.4;

function applyZoom() {
  const table = document.getElementById('scheduleZoomTable');
  const label = document.getElementById('zoomLevel');
  if (!table || !label) return;
  if ('zoom' in table.style) {
    table.style.zoom = String(currentZoom);
    table.style.transform = '';
    table.style.transformOrigin = '';
  } else {
    table.style.transform = 'scale(' + currentZoom + ')';
    table.style.transformOrigin = 'top right';
  }
  label.textContent = Math.round(currentZoom * 100) + '%';
}

function zoomIn() {
  currentZoom = Math.min(MAX_ZOOM, +(currentZoom + ZOOM_STEP).toFixed(2));
  applyZoom();
}

function zoomOut() {
  currentZoom = Math.max(MIN_ZOOM, +(currentZoom - ZOOM_STEP).toFixed(2));
  applyZoom();
}

applyZoom();
