<!-- 1. jQuery ПЕРВЫМ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- 2. Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- 3. DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- 4. Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>



<!-- ТВОЙ JS ПОТОМ -->
<script>
/* =========================
   GLOBAL FLAGS
========================= */
if(typeof window.isDesktopHover === 'undefined'){
  window.isDesktopHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

document.addEventListener('DOMContentLoaded', ()=>{

  /* ===== THEME ===== */
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon   = document.getElementById('themeIcon');

  function applyTheme(theme){
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    if(themeIcon){
      themeIcon.className = theme === 'dark'
        ? 'fa-solid fa-sun'
        : 'fa-solid fa-moon';
    }
  }

  const saved = localStorage.getItem('theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  applyTheme(saved || (prefersDark ? 'dark' : 'light'));

  if(themeToggle){
    themeToggle.addEventListener('click', ()=>{
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      applyTheme(current === 'dark' ? 'light' : 'dark');
    });
  }

});
</script>

<script>
/* =========================
   PRODUCTS SEARCH
========================= */
document.addEventListener('DOMContentLoaded', ()=>{
  const search = document.getElementById('productSearch');
  if(!search) return;

  search.addEventListener('input', function(){
    const val = this.value.toLowerCase();
    document.querySelectorAll('.product-link').forEach(link=>{
      const name = link.querySelector('.product-name')?.innerText.toLowerCase() || '';
      link.style.display = name.includes(val) ? '' : 'none';
    });
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.tab;

      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

      document.getElementById(id).classList.add('active');
      btn.classList.add('active');
    });
  });
});
</script>


