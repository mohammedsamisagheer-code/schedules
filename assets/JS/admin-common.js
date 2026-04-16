function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    var style = document.createElement('style');
    style.textContent = 'select { background-position: left 0.5rem center !important; padding-left: 2.5rem !important; padding-right: 0.75rem !important; }';
    document.head.appendChild(style);
});
