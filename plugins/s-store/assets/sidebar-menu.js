document.addEventListener('DOMContentLoaded', () => {
  if (!window.SStoreSidebarMenu || !Array.isArray(SStoreSidebarMenu.tree)) return;

  const top = document.querySelector('#toplevel_page_s-store');
  if (!top) return;

  const submenu = top.querySelector('.wp-submenu');
  if (!submenu) return;

  submenu.querySelectorAll('.s-store-sidebar-group,.s-store-sidebar-master').forEach(el => el.remove());

  const currentUrl = window.location.href;
  const coreSlugs = new Set([
    's-store','s-store-all','s-store-installed','s-store-pages','s-store-updates',
    's-store-health','s-store-autofix','s-store-backups','s-store-settings','s-store-about'
  ]);

  // Hide native plugin submenu rows visually, but DO NOT remove them from WordPress.
  [...submenu.querySelectorAll(':scope > li')].forEach(li => {
    const a = li.querySelector(':scope > a');
    if (!a) return;

    try {
      const url = new URL(a.href, window.location.origin);
      const page = url.searchParams.get('page') || '';
      const postType = url.searchParams.get('post_type') || '';

      let isPluginPage = false;
      SStoreSidebarMenu.tree.forEach(group => {
        group.pages.forEach(pageItem => {
          try {
            const pUrl = new URL(pageItem.url, window.location.origin);
            const same =
              pUrl.pathname === url.pathname &&
              (pUrl.searchParams.get('page') || '') === page &&
              (pUrl.searchParams.get('post_type') || '') === postType;
            if (same) isPluginPage = true;
          } catch (e) {}
        });
      });

      if (isPluginPage && !coreSlugs.has(page)) {
        li.classList.add('s-store-native-plugin-submenu');
        li.hidden = true;
      }
    } catch (e) {}
  });

  const master = document.createElement('li');
  master.className = 's-store-sidebar-master';

  const masterButton = document.createElement('button');
  masterButton.type = 'button';
  masterButton.className = 's-store-sidebar-master-toggle';
  masterButton.innerHTML =
    '<span><span class="dashicons dashicons-admin-plugins"></span> افزونه‌ها</span>' +
    '<span class="s-store-sidebar-master-actions">' +
      '<small class="s-store-open-all">باز کردن همه</small>' +
      '<span class="dashicons dashicons-arrow-down-alt2"></span>' +
    '</span>';

  const groupWrap = document.createElement('div');
  groupWrap.className = 's-store-sidebar-groups';

  let anyOpen = false;
  const groupControls = [];

  const saveState = (slug, open) => {
    try { localStorage.setItem('s-store-menu-' + slug, open ? '1' : '0'); } catch (e) {}
  };
  const loadState = slug => {
    try { return localStorage.getItem('s-store-menu-' + slug); } catch (e) { return null; }
  };

  SStoreSidebarMenu.tree.forEach(group => {
    const li = document.createElement('div');
    li.className = 's-store-sidebar-group';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 's-store-sidebar-group-toggle';
    button.setAttribute('aria-expanded', 'false');

    const icon = group.icon
      ? '<img src="' + group.icon + '" alt="">'
      : '<span class="dashicons dashicons-admin-plugins"></span>';

    button.innerHTML =
      '<span class="s-store-sidebar-group-main">' +
        icon +
        '<span class="s-store-sidebar-group-title">' + group.title + '</span>' +
      '</span>' +
      '<span class="dashicons dashicons-arrow-down-alt2 s-store-sidebar-chevron"></span>';

    const children = document.createElement('ul');
    children.className = 's-store-sidebar-children';

    let shouldOpen = false;

    group.pages.forEach(page => {
      const child = document.createElement('li');
      child.className = 's-store-sidebar-child';

      const a = document.createElement('a');
      a.href = page.url;
      a.textContent = page.title;

      try {
        const pageUrl = new URL(page.url, window.location.origin);
        const current = new URL(currentUrl);
        const samePage =
          pageUrl.pathname === current.pathname &&
          (pageUrl.searchParams.get('page') || '') === (current.searchParams.get('page') || '') &&
          (pageUrl.searchParams.get('post_type') || '') === (current.searchParams.get('post_type') || '');

        if (samePage) {
          a.classList.add('is-current');
          child.classList.add('is-current');
          shouldOpen = true;
        }
      } catch (e) {}

      child.appendChild(a);
      children.appendChild(child);
    });

    const setOpen = open => {
      li.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      children.hidden = !open;
      saveState(group.slug, open);
    };

    button.addEventListener('click', () => {
      setOpen(!li.classList.contains('is-open'));
    });

    const remembered = loadState(group.slug);
    setOpen(shouldOpen || remembered === '1');
    if (shouldOpen || remembered === '1') anyOpen = true;

    groupControls.push({setOpen, li});
    li.appendChild(button);
    li.appendChild(children);
    groupWrap.appendChild(li);
  });

  const setMasterOpen = open => {
    master.classList.toggle('is-open', open);
    groupWrap.hidden = !open;
    const chevron = masterButton.querySelector('.dashicons:last-child');
    if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
    try { localStorage.setItem('s-store-menu-master', open ? '1' : '0'); } catch (e) {}
  };

  const rememberedMaster = (() => {
    try { return localStorage.getItem('s-store-menu-master'); } catch (e) { return null; }
  })();

  setMasterOpen(anyOpen || rememberedMaster !== '0');

  masterButton.addEventListener('click', e => {
    if (e.target.closest('.s-store-open-all')) return;
    setMasterOpen(!master.classList.contains('is-open'));
  });

  const openAll = masterButton.querySelector('.s-store-open-all');
  openAll.addEventListener('click', e => {
    e.stopPropagation();
    const shouldExpand = groupControls.some(item => !item.li.classList.contains('is-open'));
    groupControls.forEach(item => item.setOpen(shouldExpand));
    openAll.textContent = shouldExpand ? 'بستن همه' : 'باز کردن همه';
    setMasterOpen(true);
  });

  master.appendChild(masterButton);
  master.appendChild(groupWrap);

  const pagesItem = [...submenu.querySelectorAll(':scope > li')].find(li => {
    const a = li.querySelector(':scope > a');
    if (!a) return false;
    try {
      return new URL(a.href, window.location.origin).searchParams.get('page') === 's-store-pages';
    } catch (e) { return false; }
  });

  if (pagesItem) pagesItem.insertAdjacentElement('afterend', master);
  else submenu.appendChild(master);
});
