document.addEventListener('DOMContentLoaded', () => {
  if (!window.SStoreSidebarMenu || !Array.isArray(SStoreSidebarMenu.tree)) return;

  const top = document.querySelector('#toplevel_page_s-store');
  if (!top) return;

  const submenu = top.querySelector('.wp-submenu');
  if (!submenu) return;

  // Remove previously injected grouped items.
  submenu.querySelectorAll('.s-store-sidebar-group').forEach(el => el.remove());

  const currentUrl = window.location.href;

  SStoreSidebarMenu.tree.forEach(group => {
    const li = document.createElement('li');
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
          pageUrl.searchParams.get('page') === current.searchParams.get('page') &&
          pageUrl.searchParams.get('post_type') === current.searchParams.get('post_type');

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
    };

    button.addEventListener('click', () => {
      setOpen(!li.classList.contains('is-open'));
    });

    setOpen(shouldOpen);

    li.appendChild(button);
    li.appendChild(children);
    submenu.appendChild(li);
  });
});
