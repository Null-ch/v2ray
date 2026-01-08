// Cockpit Admin Panel JavaScript - перенесено из Python проекта

document.addEventListener('DOMContentLoaded', function () {
    // CSRF helper
    function getCsrfToken(){
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Programmatic toast API
    window.showToast = function(category, message, delay){
        try{
            const cont = document.getElementById('toast-container');
            if (!cont) return;
            const el = document.createElement('div');
            const cat = (category === 'danger' ? 'danger' : (category === 'success' ? 'success' : (category === 'warning' ? 'warning' : 'secondary')));
            el.className = 'toast fade align-items-center text-bg-' + cat;
            el.setAttribute('role','alert');
            el.setAttribute('aria-live','assertive');
            el.setAttribute('aria-atomic','true');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">'+ (message||'') +'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
            cont.appendChild(el);
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                new bootstrap.Toast(el, { delay: Math.max(2000, delay||4000), autohide: true }).show();
            }
        }catch(_){ }
    };

    // Global partial refresh by container id
    window.refreshContainerById = async function(id){
        const node = document.getElementById(id);
        if (!node) return;
        const url = node.getAttribute('data-fetch-url');
        if (!url) return;
        try {
            const resp = await fetch(url, {
                headers: { 'Accept': 'text/html' },
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (resp.redirected) {
                window.location.href = resp.url;
                return;
            }
            if (resp.status === 401 || resp.status === 403) {
                window.location.href = '/cockpit/login';
                return;
            }
            if (!resp.ok) return;
            const html = await resp.text();
            if (html && html !== node.innerHTML) {
                const prevH = node.offsetHeight;
                if (prevH > 0) node.style.minHeight = prevH + 'px';
                node.classList.add('is-swapping');
                node.innerHTML = html;
                try {
                    node.classList.add('flash');
                    setTimeout(()=> node.classList.remove('flash'), 600);
                } catch(_){ }
                setTimeout(()=>{
                    node.style.minHeight = '';
                    node.classList.remove('is-swapping');
                }, 260);
            }
        } catch(_){ }
    };

    // Attach CSRF token to all POST forms
    function initializeCsrfForForms() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const token = meta ? meta.getAttribute('content') : null;
        if (!token) return;

        document.querySelectorAll('form').forEach(form => {
            const method = (form.getAttribute('method') || '').toLowerCase();
            if (method !== 'post') return;
            form.addEventListener('submit', function () {
                if (form.querySelector('input[name="_token"]')) return;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = token;
                form.appendChild(input);
            });
        });
    }

    // Theme toggle
    function initializeThemeToggle() {
        const THEME_KEY = 'ui_theme';
        const root = document.documentElement;
        const btn = document.getElementById('theme-toggle');
        const label = btn ? btn.querySelector('.theme-label') : null;

        function applyTheme(theme) {
            const next = (theme === 'light' || theme === 'dark') ? theme : 'dark';
            root.setAttribute('data-bs-theme', next);
            try { localStorage.setItem(THEME_KEY, next); } catch (_) {}
            if (label) label.textContent = next === 'dark' ? 'Тёмная' : 'Светлая';
        }

        let saved = 'dark';
        try { saved = localStorage.getItem(THEME_KEY) || 'dark'; } catch (_) {}
        applyTheme(saved);

        if (btn) {
            btn.addEventListener('click', function () {
                const current = root.getAttribute('data-bs-theme') || 'dark';
                const next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        }
    }

    // Auto refresh для элементов с data-fetch-url
    function initializeSoftAutoUpdate() {
        const nodes = Array.from(document.querySelectorAll('[data-fetch-url]'));
        if (!nodes.length) return;
        nodes.forEach(node => {
            const url = node.getAttribute('data-fetch-url');
            const interval = Number(node.getAttribute('data-fetch-interval')||'8000');
            if (!url) return;
            let timer = null;
            async function tick(){
                try{
                    const resp = await fetch(url, {
                        headers: { 'Accept': 'text/html' },
                        cache: 'no-store',
                        credentials: 'same-origin'
                    });
                    if (resp.redirected) {
                        window.location.href = resp.url;
                        return;
                    }
                    if (resp.status === 401 || resp.status === 403) {
                        window.location.href = '/cockpit/login';
                        return;
                    }
                    if (!resp.ok) return;
                    const html = await resp.text();
                    if (html && html !== node.innerHTML) {
                        const prevH = node.offsetHeight;
                        if (prevH > 0) node.style.minHeight = prevH + 'px';
                        node.classList.add('is-swapping');
                        node.innerHTML = html;
                        try {
                            node.classList.add('flash');
                            setTimeout(()=> node.classList.remove('flash'), 600);
                        } catch(_){ }
                        setTimeout(()=>{
                            node.style.minHeight = '';
                            node.classList.remove('is-swapping');
                        }, 260);
                    }
                }catch(_){}
            }
            tick();
            timer = setInterval(tick, Math.max(4000, interval));
            window.addEventListener('beforeunload', ()=>{ if (timer){ clearInterval(timer); timer=null; } });
        });
    }

    // Setup confirmation forms
    function setupConfirmationForms(root) {
        const scope = root || document;
        const forms = scope.querySelectorAll('form[data-confirm]');
        forms.forEach(form => {
            form.addEventListener('submit', async function (event) {
                const message = form.getAttribute('data-confirm');
                if (!confirm(message)) {
                    event.preventDefault();
                    return;
                }
                // AJAX delete
                if (form.getAttribute('data-ajax') === 'delete') {
                    event.preventDefault();
                    try {
                        const fd = new FormData(form);
                        if (!fd.get('_token')){
                            const t = getCsrfToken();
                            if (t) fd.append('_token', t);
                        }
                        const resp = await fetch(form.action, {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        });
                        if (resp.ok) {
                            const action = form.getAttribute('data-action');
                            const msg = action === 'revoke-keys' ? 'Ключи отозваны' : 'Удалено';
                            try { window.showToast('success', msg); } catch(_){ }
                            const targetId = form.getAttribute('data-refresh-target');
                            if (targetId) {
                                try { await window.refreshContainerById(targetId); } catch(_){ }
                            }
                        } else {
                            try { window.showToast('danger', 'Не удалось удалить'); } catch(_){ }
                        }
                    } catch(_){
                        try { window.showToast('danger', 'Ошибка удаления'); } catch(__){}
                    }
                }
            });
        });
    }

    // Mobile navigation toggle
    function initializeMobileNav() {
        const navToggle = document.getElementById('nav-toggle');
        const navigation = document.getElementById('main-navigation');
        const navOverlay = document.getElementById('nav-overlay');
        
        if (!navToggle || !navigation) return;
        
        function closeMenu() {
            navigation.classList.remove('nav-open');
            navToggle.classList.remove('nav-open');
            if (navOverlay) navOverlay.classList.remove('nav-open');
            document.body.classList.remove('nav-menu-open');
        }
        
        function openMenu() {
            navigation.classList.add('nav-open');
            navToggle.classList.add('nav-open');
            if (navOverlay) navOverlay.classList.add('nav-open');
            document.body.classList.add('nav-menu-open');
        }
        
        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = navigation.classList.contains('nav-open');
            
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        });
        
        // Close menu when clicking on overlay
        if (navOverlay) {
            navOverlay.addEventListener('click', function() {
                closeMenu();
            });
        }
        
        // Close menu when clicking on a link
        navigation.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 767.98) {
                    closeMenu();
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 767.98) {
                    closeMenu();
                }
            }, 250);
        });
    }

    // Initialize
    initializeCsrfForForms();
    initializeThemeToggle();
    initializeSoftAutoUpdate();
    setupConfirmationForms();
    initializeMobileNav();
});

