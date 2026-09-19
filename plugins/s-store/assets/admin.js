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

/* AJAX plugin actions: install / activate / update without page refresh */
(function(){
  if (!window.SStoreAjax) return;

  const esc = value => String(value || '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[ch]));

  const setLabel = (btn, text) => {
    const label = btn.querySelector('.s-store-btn-label');
    if (label) label.textContent = text;
  };

  const post = async data => {
    const body = new URLSearchParams({
      action: 's_store_plugin_action',
      nonce: SStoreAjax.nonce,
      ...data
    });
    const response = await fetch(SStoreAjax.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body
    });
    let payload;
    try { payload = await response.json(); }
    catch (e) { throw new Error(SStoreAjax.i18n.error); }
    if (!payload.success) {
      throw new Error(payload?.data?.message || SStoreAjax.i18n.error);
    }
    return payload.data;
  };

  const updateCardVersion = (btn, version) => {
    if (!version) return;
    const card = btn.closest('.s-store-plugin-card');
    if (card) {
      const metaVersion = card.querySelector('.s-store-plugin-meta > span:first-child');
      if (metaVersion) metaVersion.textContent = 'v' + version;
    }
    const updateCard = btn.closest('.s-store-update-card');
    if (updateCard) {
      const small = updateCard.querySelector('small');
      if (small) small.textContent = 'v' + version + ' · بروز';
    }
  };

  const replaceAfterInstall = (btn, html) => {
    const compact = btn.dataset.compact === '1';
    if (!html) return;
    if (!compact) {
      const wrap = btn.closest('.s-store-plugin-actions');
      if (wrap) {
        wrap.innerHTML = html;
        return;
      }
    }
    btn.outerHTML = html;
  };

  document.addEventListener('click', async e => {
    const btn = e.target.closest('.s-store-ajax-action');
    if (!btn || btn.classList.contains('is-loading')) return;

    e.preventDefault();

    const operation = btn.dataset.action || '';
    const slug = btn.dataset.slug || '';
    const plugin = btn.dataset.plugin || '';
    const compact = btn.dataset.compact || '0';

    if (!operation || !slug) return;

    btn.classList.remove('is-success','is-error');
    btn.classList.add('is-loading');
    btn.setAttribute('aria-busy','true');

    const loadingText = operation === 'install'
      ? SStoreAjax.i18n.installing
      : operation === 'activate'
        ? SStoreAjax.i18n.activating
        : SStoreAjax.i18n.updating;

    setLabel(btn, loadingText);

    try {
      const data = await post({
        operation,
        slug,
        plugin,
        compact
      });

      updateCardVersion(btn, data.version);

      if (operation === 'install') {
        btn.classList.remove('is-loading');
        btn.removeAttribute('aria-busy');
        replaceAfterInstall(btn, data.action_html);
        return;
      }

      btn.classList.remove('is-loading');
      btn.classList.add('is-success');
      btn.removeAttribute('aria-busy');

      const icon = btn.querySelector('.dashicons');
      if (icon) icon.className = 'dashicons dashicons-yes-alt';

      if (operation === 'update') {
        setLabel(btn, 'بروزرسانی شد');
      } else if (operation === 'activate') {
        setLabel(btn, 'فعال شد');
        const card = btn.closest('.s-store-plugin-card');
        if (card) {
          const status = card.querySelector('.s-store-status');
          if (status) {
            status.className = 's-store-status active';
            status.textContent = 'فعال';
          }
        }
      }

      const updateCard = btn.closest('.s-store-update-card');
      if (operation === 'update' && updateCard) {
        updateCard.classList.add('is-updated');
      }
    } catch (error) {
      btn.classList.remove('is-loading');
      btn.classList.add('is-error');
      btn.removeAttribute('aria-busy');
      setLabel(btn, 'خطا — تلاش دوباره');
      btn.title = error.message || SStoreAjax.i18n.error;

      let notice = btn.parentElement?.querySelector('.s-store-ajax-error');
      if (!notice && btn.parentElement) {
        notice = document.createElement('small');
        notice.className = 's-store-ajax-error';
        btn.parentElement.appendChild(notice);
      }
      if (notice) notice.textContent = error.message || SStoreAjax.i18n.error;
    }
  });
})();


/* Open grouped plugin accordion when navigated with a hash */
(function(){
  const openHashTarget = () => {
    if (!location.hash || !location.hash.startsWith('#s-store-pages-')) return;
    const target = document.querySelector(location.hash);
    if (target && target.tagName === 'DETAILS') {
      target.open = true;
      setTimeout(() => target.scrollIntoView({behavior:'smooth', block:'start'}), 80);
    }
  };
  document.addEventListener('DOMContentLoaded', openHashTarget);
  window.addEventListener('hashchange', openHashTarget);
})();
