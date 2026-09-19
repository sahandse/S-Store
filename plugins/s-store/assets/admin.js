document.addEventListener('DOMContentLoaded',function(){
  const search=document.getElementById('s-store-search');
  const filters=[...document.querySelectorAll('.s-store-filter')];
  const cards=[...document.querySelectorAll('[data-s-store-plugin]')];
  const empty=document.querySelector('.s-store-empty');
  let category='all';

  function apply(){
    const q=(search?.value||'').trim().toLowerCase();
    let visible=0;
    cards.forEach(card=>{
      const okCat=category==='all'||card.dataset.category===category;
      const okQ=!q||(card.dataset.search||'').toLowerCase().includes(q);
      const show=okCat&&okQ;
      card.hidden=!show;
      if(show) visible++;
    });
    if(empty) empty.hidden=visible!==0;
  }

  if(search) search.addEventListener('input',apply);
  filters.forEach(btn=>btn.addEventListener('click',()=>{
    filters.forEach(x=>x.classList.remove('is-active'));
    btn.classList.add('is-active');
    category=btn.dataset.category||'all';
    apply();
  }));

  document.querySelectorAll('.s-store-detail-tabs a[href^="#"]').forEach(link=>{
    link.addEventListener('click',function(e){
      const target=document.querySelector(this.getAttribute('href'));
      if(!target) return;
      e.preventDefault();
      document.querySelectorAll('.s-store-detail-tabs a').forEach(x=>x.classList.remove('is-active'));
      this.classList.add('is-active');
      target.scrollIntoView({behavior:'smooth',block:'start'});
    });
  });
});