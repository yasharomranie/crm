/*
 * The app's one way to draw map pictures. The tile address comes from
 * window.crmMapTileUrl (set from config('services.maps.tile_url') by each page).
 * If the tile server cannot be reached, the markers and everything else on the
 * map keep working and a small notice explains why the picture is empty.
 */
window.crmTileLayer = function (map, extra) {
    var url = window.crmMapTileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    var options = { maxZoom: 18, attribution: '&copy; OpenStreetMap' };
    Object.keys(extra || {}).forEach(function (key) { options[key] = extra[key]; });

    var layer = L.tileLayer(url, options).addTo(map);
    var loaded = 0;
    var failed = 0;
    var notice = null;

    layer.on('tileload', function () {
        loaded++;
        if (notice) {
            notice.remove();
            notice = null;
        }
    });

    layer.on('tileerror', function () {
        failed++;
        if (loaded === 0 && failed >= 3 && !notice) {
            notice = document.createElement('div');
            notice.textContent = 'تصویر نقشه بارگذاری نشد (به سرور نقشه دسترسی نیست)؛ نشانگرها و مختصات درست‌اند.';
            notice.setAttribute('role', 'status');
            notice.style.cssText = 'position:absolute;z-index:1000;inset-inline:0.5rem;bottom:1.8rem;padding:0.35rem 0.6rem;border-radius:0.5rem;background:rgba(255,255,255,0.92);color:#7c2d12;font-size:12px;text-align:center;direction:rtl;pointer-events:none;';
            map.getContainer().appendChild(notice);
        }
    });

    return layer;
};
