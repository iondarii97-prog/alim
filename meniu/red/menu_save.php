<script>
function saveMenu(){
  const data = {
    meals: {},
    items: {}
  };

  document.querySelectorAll('input[name^="meals"]').forEach(i=>{
    const id = i.name.match(/\[(\d+)\]/)[1];
    data.meals[id] = i.value;
  });

  document.querySelectorAll('input[name^="items"]').forEach(i=>{
    const id = i.name.match(/\[(\d+)\]/)[1];
    data.items[id] = i.value;
  });

  fetch('/asset/api/menu_update.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  })
  .then(r=>r.json())
  .then(j=>{
    alert(j.message);
  });
}
</script>
