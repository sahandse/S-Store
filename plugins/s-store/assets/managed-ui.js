document.addEventListener('DOMContentLoaded',function(){
  if(!document.body.classList.contains('s-store-managed-page')) return;
  const cfg=window.SStoreManagedUI||{};
  const wrap=document.querySelector('.wrap');
  if(!wrap || wrap.classList.contains('s-store-wrap')) return;

  const existingTitle=wrap.querySelector('h1');
  const pageTitle=(existingTitle?.textContent||cfg.title||'افزونه S Store').trim();

  const shell=document.createElement('section');
  shell.className='ssui-shell-head';
  shell.innerHTML=
    '<div class="ssui-shell-main">'+
      '<div class="ssui-shell-icon"><span class="dashicons dashicons-admin-plugins"></span></div>'+
      '<div class="ssui-shell-copy">'+
        '<span class="ssui-eyebrow">S STORE PLUGIN SUITE</span>'+
        '<h2></h2>'+
        '<div class="ssui-badges">'+
          '<span class="ssui-badge success"><i></i>فعال</span>'+
          '<span class="ssui-badge">مدیریت یکپارچه</span>'+
          (cfg.version?'<span class="ssui-badge">v'+cfg.version+'</span>':'')+
        '</div>'+
      '</div>'+
    '</div>'+
    '<div class="ssui-shell-actions">'+
      (cfg.detailUrl?'<a class="ssui-action" href="'+cfg.detailUrl+'"><span class="dashicons dashicons-info-outline"></span>جزئیات افزونه</a>':'')+
      '<a class="ssui-action" href="'+(cfg.storeUrl||'#')+'"><span class="dashicons dashicons-store"></span>S Store</a>'+
    '</div>';
  shell.querySelector('h2').textContent=pageTitle;

  const strip=document.querySelector('.s-store-managed-strip');
  if(strip) strip.after(shell); else wrap.before(shell);

  const form=[...wrap.querySelectorAll('form')].find(f=>{
    const a=(f.getAttribute('action')||'').toLowerCase();
    return a.includes('options.php') || f.querySelector('.button-primary, input[type="submit"], button[type="submit"]');
  });

  if(form){
    let dirty=false;
    const markDirty=()=>{dirty=true;document.body.classList.add('ssui-dirty')};
    form.querySelectorAll('input,select,textarea').forEach(el=>{
      if(el.type==='hidden' || el.type==='submit') return;
      el.addEventListener('change',markDirty);
      if(el.tagName==='TEXTAREA' || ['text','number','url','email','tel','password'].includes(el.type)) el.addEventListener('input',markDirty);
    });

    const bar=document.createElement('div');
    bar.className='ssui-savebar';
    bar.innerHTML=
      '<div class="ssui-save-state"><span class="dashicons dashicons-yes-alt"></span><div><strong>تنظیمات این افزونه</strong><small>تغییرات ذخیره‌نشده‌ای وجود ندارد.</small></div></div>'+
      '<div class="ssui-save-actions"><button type="button" class="button ssui-reset-view">بازگشت بالا</button><button type="button" class="button button-primary ssui-save">ذخیره تغییرات</button></div>';
    document.body.appendChild(bar);

    const stateIcon=bar.querySelector('.ssui-save-state .dashicons');
    const stateText=bar.querySelector('.ssui-save-state small');
    const observer=new MutationObserver(()=>{
      if(document.body.classList.contains('ssui-dirty')){
        stateIcon.className='dashicons dashicons-warning';
        stateText.textContent='تغییراتی دارید که هنوز ذخیره نشده است.';
      }
    });
    observer.observe(document.body,{attributes:true,attributeFilter:['class']});

    bar.querySelector('.ssui-save').addEventListener('click',()=>{
      const real=form.querySelector('input[type="submit"],button[type="submit"],.button-primary');
      if(real && typeof real.click==='function') real.click(); else form.submit();
    });
    bar.querySelector('.ssui-reset-view').addEventListener('click',()=>window.scrollTo({top:0,behavior:'smooth'}));

    window.addEventListener('beforeunload',e=>{
      if(!dirty) return;
      e.preventDefault();
      e.returnValue='';
    });
    form.addEventListener('submit',()=>{dirty=false;document.body.classList.remove('ssui-dirty')});
  }

  const cards=[...wrap.querySelectorAll('[class$="-card"],[class*="-card "]')].filter(x=>x.offsetParent!==null);
  if(cards.length){
    const nav=document.createElement('nav');
    nav.className='ssui-section-nav';
    let count=0;
    cards.slice(0,8).forEach((card,i)=>{
      const h=card.querySelector('h2,h3');
      if(!h) return;
      const id='ssui-section-'+(++count);
      card.id=card.id||id;
      const a=document.createElement('a');
      a.href='#'+card.id;
      a.textContent=h.textContent.trim();
      a.addEventListener('click',e=>{
        e.preventDefault();
        card.scrollIntoView({behavior:'smooth',block:'start'});
      });
      nav.appendChild(a);
    });
    if(nav.children.length>1) shell.after(nav);
  }
});