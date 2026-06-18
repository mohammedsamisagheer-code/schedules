<style>
@media (width >= 40rem) {
  .sm\\:block { display: block; }
  .sm\\:hidden { display: none; }
}
</style>
<!-- BEGIN: PublicHeader -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-50 relative" data-purpose="navigation-header">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16 items-center">
      <div class="flex items-center gap-2">
        <div class="flex-shrink-0 flex items-center">
          <img src="assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain ml-2">
          <span class="font-bold text-xl tracking-tight hidden sm:block">هندسة تقنيات الحاسوب</span>
          <span class="font-bold text-lg tracking-tight sm:hidden">هندسة تقنيات الحاسوب</span>
        </div>
      </div>
      <button id="mobileMenuBtn" onclick="toggleMobileMenu()"
        class="p-2 rounded-lg hover:bg-gray-100 relative">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
    </div>
    <div id="navMenu" class="hidden border-t border-gray-200 pb-4 pt-4">
      <nav class="flex flex-col gap-2">
        <a href="schedule.php"
          class="block px-2 py-2 rounded-custom text-sm font-medium transition-colors <?php echo $active_page === 'schedule' ? 'text-primary bg-primary/5' : 'text-gray-600 hover:text-primary hover:bg-gray-50'; ?>">جدول المحاضرات</a>
        <a href="exams.php"
          class="block px-2 py-2 rounded-custom text-sm font-medium transition-colors <?php echo $active_page === 'exams' ? 'text-primary bg-primary/5' : 'text-gray-600 hover:text-primary hover:bg-gray-50'; ?>">جدول الإمتحانات</a>
      </nav>
    </div>
  </div>
</header>
<!-- END: PublicHeader -->
<script>
function toggleMobileMenu() {
  var menu = document.getElementById('navMenu');
  if (menu) menu.classList.toggle('hidden');
}
</script>