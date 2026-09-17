/**
 * OpenStreetMap views for the administrator pages.
 *
 * The trail line only expresses the chronological order of the recorded
 * attendance events - it is never presented as the exact travelled route.
 */

const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

function ready(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

ready(() => {
    initAttendanceMap('admin-map', 'admin-map-data');
    initAttendanceMap('trail-map', 'trail-map-data', { numbered: true, connect: true });
});

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isUsableCoordinate(point) {
    return Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng));
}

function initAttendanceMap(containerId, dataId, options = {}) {
    const container = document.getElementById(containerId);
    const dataElement = document.getElementById(dataId);

    if (!container || !dataElement || typeof window.L === 'undefined') {
        return;
    }

    const points = JSON.parse(dataElement.textContent || '[]').filter(isUsableCoordinate);

    if (!points.length) {
        container.innerHTML =
            '<div class="map__empty"><div><strong>No attendance locations yet</strong>' +
            '<p class="m3-help" style="margin-top:6px">Adjust the filters above to see recorded GPS points.</p></div></div>';
        return;
    }

    const map = window.L.map(container, {
        scrollWheelZoom: false,
        attributionControl: true,
    });

    window.L
        .tileLayer(OSM_TILE_URL, { maxZoom: 19, attribution: OSM_ATTRIBUTION })
        .addTo(map);

    const markers = points.map((point, index) => {
        const marker = window.L
            .marker([Number(point.lat), Number(point.lng)], { icon: pinIcon(point, index, options.numbered) })
            .addTo(map);

        marker.bindPopup(popupHtml(point), { maxWidth: 280 });

        return marker;
    });

    if (options.connect && markers.length > 1) {
        window.L
            .polyline(points.map((point) => [Number(point.lat), Number(point.lng)]), {
                color: '#6750a4',
                weight: 3,
                opacity: 0.8,
                dashArray: '8 8',
                lineCap: 'round',
            })
            .addTo(map);
    }

    map.fitBounds(window.L.latLngBounds(points.map((point) => [Number(point.lat), Number(point.lng)])).pad(0.25), {
        maxZoom: 16,
    });

    if (markers.length === 1) {
        map.setZoom(16);
    }

    window.setTimeout(() => map.invalidateSize(), 200);

    // The timeline on the trail page drives the map.
    document.querySelectorAll('[data-point-index]').forEach((element) => {
        element.addEventListener('click', () => {
            const marker = markers[Number(element.dataset.pointIndex)];

            if (!marker) {
                return;
            }

            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15));
            marker.openPopup();
        });
    });
}

function pinIcon(point, index, numbered) {
    const variant = point.type === 'check_in' ? 'map-pin--in' : 'map-pin--out';
    const label = numbered ? index + 1 : point.type === 'check_in' ? '↓' : '↑';

    return window.L.divIcon({
        className: '',
        html: `<div class="map-pin ${variant}"><span>${label}</span></div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -28],
    });
}

function popupHtml(point) {
    const facts = [
        ['Time', `${escapeHtml(point.time_long)} · ${escapeHtml(point.date)}`],
        ['Accuracy', escapeHtml(point.accuracy)],
        ['Coordinates', escapeHtml(point.coordinates)],
    ];

    const photo = point.photo
        ? `<a class="map-popup__photo" href="${escapeHtml(point.photo)}" target="_blank" rel="noopener">
                <img src="${escapeHtml(point.photo)}" alt="Watermarked attendance photo" loading="lazy">
           </a>
           ${
               point.photo_original
                   ? `<a class="map-popup__link" href="${escapeHtml(point.photo_original)}" target="_blank" rel="noopener">View original photo</a>`
                   : ''
           }`
        : '<p class="m3-help" style="margin-top:8px">No photo stored for this record.</p>';

    return `
        <div class="map-popup">
            <div class="map-popup__title">${escapeHtml(point.employee || 'Employee')}</div>
            <div class="map-popup__sub">${escapeHtml(point.employee_id)} · ${escapeHtml(point.type_label)}</div>

            <div class="m3-facts" style="font-size:.8125rem">
                ${facts
                    .map(
                        ([label, value]) =>
                            `<div class="m3-fact"><span class="m3-fact__label">${label}</span><span class="m3-fact__value">${value}</span></div>`
                    )
                    .join('')}
                <div class="m3-fact"><span class="m3-fact__label">Address</span><span class="m3-fact__value">${escapeHtml(
                    point.address || point.location || 'Not available'
                )}</span></div>
                <div class="m3-fact"><span class="m3-fact__label">Device</span><span class="m3-fact__value">${escapeHtml(
                    point.device
                )}</span></div>
            </div>

            ${photo}
        </div>
    `;
}
