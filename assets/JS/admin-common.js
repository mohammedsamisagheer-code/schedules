function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
    var style = document.createElement('style');
    style.textContent = 'select { background-position: left 0.5rem center !important; padding-left: 2.5rem !important; padding-right: 0.75rem !important; }';
    document.head.appendChild(style);

    // Strip banner-related GET params so banners don't reappear on refresh
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        var bannerParams = ['success', 'auto', 'count', 'unassigned', 'cleared', 'iterations', 'conflicts'];
        var changed = false;
        bannerParams.forEach(function (p) {
            if (url.searchParams.has(p)) { url.searchParams.delete(p); changed = true; }
        });
        if (changed) window.history.replaceState(null, '', url.toString());
    }
});
