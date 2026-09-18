const CARTO_STYLE = 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json';
const TRAIL_COLORS = ['#6750a4', '#006c4c', '#9c4146', '#00658d', '#795900', '#8c4a60'];

function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
    else callback();
}

ready(() => {
    initAttendanceMap('admin-map', 'admin-map-data');
    initAttendanceMap('trail-map', 'trail-map-data', { numbered: true });
});

function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function initAttendanceMap(containerId, dataId, options = {}) {
    const container = document.getElementById(containerId);
    const dataElement = document.getElementById(dataId);
    if (!container || !dataElement) return;

    const points = JSON.parse(dataElement.textContent || '[]').filter((point) => Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng)));
    if (!points.length) {
        container.innerHTML = '<div class="map__empty"><div><strong>No attendance locations</strong><p>Adjust the date or filters to view activity.</p></div></div>';
        return;
    }

    const timer = window.setInterval(() => {
        if (!window.maplibregl) return;
        window.clearInterval(timer);
        buildMap(container, points, options);
    }, 30);
}

function buildMap(container, points, options) {
    const map = new window.maplibregl.Map({ container, style: CARTO_STYLE, center: [Number(points[0].lng), Number(points[0].lat)], zoom: 13 });
    map.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    const grouped = points.reduce((result, point) => {
        const key = String(point.employee_id || point.employee || 'employee');
        result[key] = [...(result[key] || []), point];
        return result;
    }, {});
    const employeeColors = Object.fromEntries(Object.keys(grouped).map((key, index) => [key, TRAIL_COLORS[index % TRAIL_COLORS.length]]));
    const pointFeatures = points.map((point, index) => ({
        type: 'Feature', geometry: { type: 'Point', coordinates: [Number(point.lng), Number(point.lat)] }, properties: {
            ...point,
            sequence: index + 1,
            employee_color: employeeColors[String(point.employee_id || point.employee || 'employee')],
        },
    }));
    const trailFeatures = Object.entries(grouped).filter(([, values]) => values.length > 1).map(([employeeId, values], index) => ({
        type: 'Feature', geometry: { type: 'LineString', coordinates: values.map((point) => [Number(point.lng), Number(point.lat)]) }, properties: { employeeId, color: TRAIL_COLORS[index % TRAIL_COLORS.length] },
    }));

    map.on('load', () => {
        if (options.numbered) {
            map.addSource('trails', { type: 'geojson', data: { type: 'FeatureCollection', features: trailFeatures } });
            map.addLayer({ id: 'trails', type: 'line', source: 'trails', paint: { 'line-color': ['get', 'color'], 'line-width': 3, 'line-opacity': 0.72 }, layout: { 'line-cap': 'round', 'line-join': 'round' } });
        }

        map.addSource('attendance', { type: 'geojson', data: { type: 'FeatureCollection', features: pointFeatures } });

        map.addLayer({ id: 'activities', type: 'circle', source: 'attendance', filter: ['!', ['has', 'point_count']], paint: {
            'circle-color': ['get', 'employee_color'],
            'circle-radius': options.numbered ? 10 : 8, 'circle-stroke-width': 3, 'circle-stroke-color': '#fff',
        } });
        if (options.numbered) map.addLayer({ id: 'activity-labels', type: 'symbol', source: 'attendance', layout: { 'text-field': ['to-string', ['get', 'sequence']], 'text-size': 11 }, paint: { 'text-color': '#fff' } });

        map.on('click', 'activities', (event) => {
            const feature = event.features[0];
            new window.maplibregl.Popup({ offset: 14, maxWidth: '300px' }).setLngLat(feature.geometry.coordinates).setHTML(popupHtml(feature.properties)).addTo(map);
            document.querySelectorAll('[data-point-index]').forEach((item) => item.classList.remove('is-active'));
            document.querySelector(`[data-point-index="${Number(feature.properties.sequence) - 1}"]`)?.classList.add('is-active');
        });
        ['activities'].forEach((layer) => {
            if (!map.getLayer(layer)) return;
            map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
        });
        fitToPoints(map, points);
    });

    document.querySelectorAll('[data-point-index]').forEach((element) => element.addEventListener('click', () => {
        const point = points[Number(element.dataset.pointIndex)];
        map.flyTo({ center: [Number(point.lng), Number(point.lat)], zoom: 16 });
    }));

    document.querySelectorAll('[data-employee-code]').forEach((row) => row.addEventListener('click', (event) => {
        if (event.target.closest('a, button')) return;
        fitToPoints(map, points.filter((point) => String(point.employee_id) === row.dataset.employeeCode));
        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
}

function fitToPoints(map, points) {
    if (!points.length) return;
    const bounds = points.reduce((box, point) => box.extend([Number(point.lng), Number(point.lat)]), new window.maplibregl.LngLatBounds());
    map.fitBounds(bounds, { padding: 56, maxZoom: 16, duration: 700 });
}

function popupHtml(point) {
    const photo = point.photo ? `<a class="m3-btn m3-btn--text m3-btn--sm" href="${escapeHtml(point.photo)}" target="_blank" rel="noopener">View photo</a>` : '';
    const activity = point.activity_url ? `<a class="m3-btn m3-btn--tonal m3-btn--sm" href="${escapeHtml(point.activity_url)}">View activity</a>` : '';
    const total = point.checkpoint_total ? `<div><strong>${escapeHtml(point.checkpoint_total)}</strong> checkpoints in selection</div>` : '';
    return `<div class="map-popup"><strong>${escapeHtml(point.employee)}</strong><div class="map-popup__sub">${escapeHtml(point.employee_id)} · Latest checkpoint</div><div>${escapeHtml(point.time_long)} · ${escapeHtml(point.date)}</div><div>${escapeHtml(point.coordinates)} · ${escapeHtml(point.accuracy)}</div><div>${escapeHtml(point.device)}</div><p>${escapeHtml(point.address || point.location || 'Address unavailable')}</p>${total}<div class="row-wrap">${activity}${photo}</div></div>`;
}
