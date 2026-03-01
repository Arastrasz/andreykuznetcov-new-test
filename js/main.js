/* ============================================================
   THE ARCHIVES OF CLAN LAR — Interactions v2.0
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  // --- Loading screen ---
  const loader = document.getElementById('loader');
  if (loader) {
    requestAnimationFrame(() => {
      loader.classList.add('loaded');
      setTimeout(() => { loader.remove(); }, 1200);
    });
  }

  // --- Navigation scroll effect ---
  const nav = document.getElementById('nav');
  if (nav) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 80) nav.classList.add('scrolled');
      else nav.classList.remove('scrolled');
    }, { passive: true });
  }
  
  // --- Mobile nav toggle ---
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const spans = navToggle.querySelectorAll('span');
      if (navLinks.classList.contains('open')) {
        spans[0].style.transform = 'rotate(45deg) translate(4px, 4px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(4px, -4px)';
      } else {
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
      }
    });
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        const spans = navToggle.querySelectorAll('span');
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
      });
    });
  }
  
  // --- Fade-in on scroll ---
  const fadeEls = document.querySelectorAll('.fade-in');
  if (fadeEls.length > 0) {
    const obs = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    fadeEls.forEach(el => obs.observe(el));
  }
  
  // --- Smooth scroll for anchor links ---
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
      const id = this.getAttribute('href'); if (id === '#') return;
      const t = document.querySelector(id);
      if (t) { e.preventDefault(); window.scrollTo({ top: t.getBoundingClientRect().top + window.scrollY - (nav ? nav.offsetHeight : 0) - 20, behavior: 'smooth' }); }
    });
  });
  
  // --- Hero parallax ---
  const hero = document.querySelector('.hero, .house-hero');
  if (hero) {
    window.addEventListener('scroll', () => {
      const s = window.scrollY, h = hero.offsetHeight;
      if (s < h) { const hc = hero.querySelector('.hero-content'); if (hc) { hc.style.transform = `translateY(${s*0.15}px)`; hc.style.opacity = 1-(s/h)*0.6; } }
    }, { passive: true });
  }

  // --- Scroll progress bar ---
  const prog = document.createElement('div');
  prog.className = 'scroll-progress';
  document.body.appendChild(prog);
  
  // --- Back to top ---
  const btt = document.createElement('button');
  btt.className = 'back-to-top'; btt.innerHTML = '\u2191'; btt.setAttribute('aria-label','Back to top');
  document.body.appendChild(btt);
  btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  window.addEventListener('scroll', () => {
    const s = window.scrollY, t = document.documentElement.scrollHeight - window.innerHeight;
    prog.style.width = (t > 0 ? (s/t)*100 : 0) + '%';
    s > 600 ? btt.classList.add('visible') : btt.classList.remove('visible');
  }, { passive: true });

  // --- Showcase parallax (index page) ---
  const scImgs = document.querySelectorAll('.showcase__image img');
  if (scImgs.length > 0) {
    window.addEventListener('scroll', () => {
      scImgs.forEach(img => {
        const r = img.parentElement.getBoundingClientRect(), vh = window.innerHeight;
        if (r.top < vh && r.bottom > 0) {
          const p = (vh - r.top) / (vh + r.height);
          img.style.transform = `translateY(${(p-0.5)*30}px) scale(1.08)`;
        }
      });
    }, { passive: true });
  }

  // --- Search overlay ---
  const searchBtn = document.getElementById('searchBtn');
  const searchOv = document.getElementById('searchOverlay');
  if (searchBtn && searchOv) {
    const sIn = searchOv.querySelector('.search__input');
    const sRes = searchOv.querySelector('.search__results');
    const pages = [
      { t:'Bastion Sanguinaris', s:'The Crimson Bastion — Vampire Stronghold in Blackreach', u:'bastion', k:'bastion crimson vampire blackreach feast hall scarlet archive chimney' },
      { t:'Abagarlas', s:'Notes on the Passage Beyond — Ayleid Ruin', u:'abagarlas', k:'abagarlas ayleid coldharbour throne soul gem forge passage veil' },
      { t:'Creature-From-Beyond', s:'Sword-Singer Redoubt — Where the void grows thin', u:'creature', k:'creature beyond sword singer redoubt void wyrd' },
      { t:'New-Sheoth Palace', s:'Sheogorath\u2019s Palace — Under Construction', u:'/', k:'new sheoth palace sheogorath mania dementia shivering isles' },
      { t:'The Craft Behind the Stone', s:'Environmental Storytelling \u00b7 Structural Intent', u:'/#craft', k:'craft storytelling structural intent builder' },
      { t:'Visit the Archive', s:'Lore documents \u2014 illustrated, themed, PDFs', u:'/#archive', k:'archive lore documents pdf' },
      { t:'News', s:'Latest announcements from the Archives', u:'news', k:'news announcements updates posts' },
      { t:'Login / Register', s:'Enter the Archives \u2014 join the community', u:'login', k:'login register account sign up enter' },
      { t:'Contact Andrey', s:'Reviews, problems, visual creations', u:'contact', k:'contact andrey message email support' },
    ];
    function openS(){ searchOv.classList.add('active'); sIn.value=''; sIn.focus(); sRes.innerHTML='<div class="search__hint">Type to search houses, lore, and archives</div>'; document.body.style.overflow='hidden'; }
    function closeS(){ searchOv.classList.remove('active'); document.body.style.overflow=''; }
    function doS(q){ q=q.toLowerCase().trim(); if(!q){ sRes.innerHTML='<div class="search__hint">Type to search houses, lore, and archives</div>'; return; }
      const m=pages.filter(p=>p.t.toLowerCase().includes(q)||p.s.toLowerCase().includes(q)||p.k.includes(q));
      sRes.innerHTML = m.length ? m.map(p=>`<a href="${p.u}" class="search__result"><div class="search__result-title">${p.t}</div><div class="search__result-sub">${p.s}</div></a>`).join('') : '<div class="search__hint">No results found</div>';
    }
    searchBtn.addEventListener('click', openS);
    searchOv.addEventListener('click', e=>{ if(e.target===searchOv) closeS(); });
    sIn.addEventListener('input', ()=>doS(sIn.value));
    document.addEventListener('keydown', e=>{
      if((e.metaKey||e.ctrlKey) && e.key==='k'){ e.preventDefault(); openS(); }
      if(e.key==='Escape' && searchOv.classList.contains('active')) closeS();
    });
  }

  // --- Reader mode ---
  const rBtn = document.getElementById('readerToggle');
  if (rBtn) {
    let on = false;
    rBtn.addEventListener('click', () => {
      on = !on;
      document.body.classList.toggle('reader-mode', on);
      rBtn.textContent = on ? '\u25c6 Dark' : '\u25c7 Read';
      rBtn.setAttribute('aria-pressed', on);
    });
  }

});

/* ============================================================
   SHARED: Enhanced Lightbox — pinch-zoom + preload + counter
   ============================================================ */
function initLightbox() {
  const lb=document.getElementById('lightbox'); if(!lb) return;
  const lbImg=document.getElementById('lbImg'), lbCap=document.getElementById('lbCaption'), lbCnt=document.getElementById('lbCounter');
  const items=document.querySelectorAll('.gallery-item[data-lb]');
  const imgs=[]; items.forEach(it=>{ const i=it.querySelector('img'),c=it.querySelector('.gallery-item__caption'); imgs.push({src:i.src,alt:i.alt,cap:c?c.textContent:''}); });
  if(!imgs.length) return;
  let idx=0,scale=1,px=0,py=0,drag=false,sx=0,sy=0;
  function upd(){ lbImg.style.transform=`scale(${scale}) translate(${px}px,${py}px)`; }
  function cnt(){ if(lbCnt) lbCnt.textContent=`${idx+1} / ${imgs.length}`; }
  function preload(i){ [1,-1].forEach(d=>{ const img=new Image(); img.src=imgs[(i+d+imgs.length)%imgs.length].src; }); }
  function open(i){ idx=i; lbImg.src=imgs[i].src; lbImg.alt=imgs[i].alt; lbCap.textContent=imgs[i].cap; scale=1; px=0; py=0; upd(); cnt(); lb.classList.add('active'); document.body.style.overflow='hidden'; preload(i); }
  function close(){ lb.classList.remove('active'); document.body.style.overflow=''; }
  function nav(d){ idx=(idx+d+imgs.length)%imgs.length; scale=1; px=0; py=0; lbImg.src=imgs[idx].src; lbImg.alt=imgs[idx].alt; lbCap.textContent=imgs[idx].cap; upd(); cnt(); preload(idx); }
  items.forEach(it=>{ it.style.cursor='pointer'; it.addEventListener('click',()=>open(+it.dataset.lb)); });
  document.getElementById('lbClose').addEventListener('click',close);
  lb.addEventListener('click',e=>{ if(e.target===lb)close(); });
  document.addEventListener('keydown',e=>{ if(!lb.classList.contains('active'))return; if(e.key==='Escape')close(); if(e.key==='ArrowLeft')nav(-1); if(e.key==='ArrowRight')nav(1); if(e.key==='+'||e.key==='='){scale=Math.min(scale+0.3,5);upd();} if(e.key==='-'){scale=Math.max(scale-0.3,0.5);upd();} });
  document.getElementById('lbPrev').addEventListener('click',e=>{e.stopPropagation();nav(-1);});
  document.getElementById('lbNext').addEventListener('click',e=>{e.stopPropagation();nav(1);});
  document.getElementById('lbZoomIn').addEventListener('click',e=>{e.stopPropagation();scale=Math.min(scale+0.5,5);upd();});
  document.getElementById('lbZoomOut').addEventListener('click',e=>{e.stopPropagation();scale=Math.max(scale-0.5,0.5);upd();});
  document.getElementById('lbReset').addEventListener('click',e=>{e.stopPropagation();scale=1;px=0;py=0;upd();});
  lb.addEventListener('wheel',e=>{e.preventDefault();scale=Math.min(Math.max(scale+(e.deltaY>0?-0.2:0.2),0.5),5);upd();},{passive:false});
  // Mouse drag
  lbImg.addEventListener('mousedown',e=>{if(scale<=1)return;drag=true;sx=e.clientX-px*scale;sy=e.clientY-py*scale;lb.classList.add('dragging');e.preventDefault();});
  document.addEventListener('mousemove',e=>{if(!drag)return;px=(e.clientX-sx)/scale;py=(e.clientY-sy)/scale;upd();});
  document.addEventListener('mouseup',()=>{drag=false;lb.classList.remove('dragging');});
  // Touch: pan + pinch-to-zoom
  let ts=null,pinchStart=0,pinchScale=1;
  function getDist(t){ const dx=t[0].clientX-t[1].clientX,dy=t[0].clientY-t[1].clientY; return Math.sqrt(dx*dx+dy*dy); }
  lbImg.addEventListener('touchstart',e=>{
    if(e.touches.length===2){e.preventDefault();pinchStart=getDist(e.touches);pinchScale=scale;}
    else if(e.touches.length===1&&scale>1){ts={x:e.touches[0].clientX-px*scale,y:e.touches[0].clientY-py*scale};}
  },{passive:false});
  lbImg.addEventListener('touchmove',e=>{
    if(e.touches.length===2&&pinchStart>0){e.preventDefault();scale=Math.min(Math.max(pinchScale*(getDist(e.touches)/pinchStart),0.5),5);upd();}
    else if(e.touches.length===1&&ts){px=(e.touches[0].clientX-ts.x)/scale;py=(e.touches[0].clientY-ts.y)/scale;upd();}
  },{passive:false});
  lbImg.addEventListener('touchend',e=>{if(e.touches.length<2)pinchStart=0;if(e.touches.length===0)ts=null;});
  // Swipe nav when not zoomed
  let swX=0;
  lb.addEventListener('touchstart',e=>{if(scale<=1&&e.touches.length===1)swX=e.touches[0].clientX;},{passive:true});
  lb.addEventListener('touchend',e=>{if(scale>1||!swX)return;const d=(e.changedTouches[0]?.clientX||0)-swX;if(Math.abs(d)>60){d>0?nav(-1):nav(1);}swX=0;},{passive:true});
}

/* ============================================================
   SHARED: Clipboard with HTTP fallback
   ============================================================ */
function copyPort(el) {
  const text=el.getAttribute('data-port'), hint=el.querySelector('.copy-hint');
  const accent=getComputedStyle(document.documentElement).getPropertyValue('--house-accent').trim()||'var(--text-secondary)';
  function ok(){ hint.textContent='Copied!'; hint.style.color=accent; setTimeout(()=>{hint.textContent='Click to copy';hint.style.color='';},2000); }
  function no(){ hint.textContent='Select & copy manually'; hint.style.color='#c47040';
    const r=document.createRange(); r.selectNodeContents(el.firstChild||el);
    const s=window.getSelection(); s.removeAllRanges(); s.addRange(r);
    setTimeout(()=>{hint.textContent='Click to copy';hint.style.color='';},3000);
  }
  if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(ok).catch(no);}
  else{try{const ta=document.createElement('textarea');ta.value=text;ta.style.cssText='position:fixed;opacity:0';document.body.appendChild(ta);ta.select();document.execCommand('copy')?ok():no();document.body.removeChild(ta);}catch(e){no();}}
}
