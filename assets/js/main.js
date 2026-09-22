// =========================================================
// VISZ Closet — JS geral do site
// =========================================================

function showToast(message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
}

function removeFavoriteCard(card) {
    const grid = card.closest('.product-grid');
    card.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
    card.style.opacity = '0';
    card.style.transform = 'scale(0.94)';
    setTimeout(() => {
        card.remove();
        if (grid && grid.children.length === 0) {
            grid.outerHTML = '<div class="empty-state"><p>Você ainda não favoritou nenhuma peça.</p>' +
                '<a href="' + (window.BASE_URL || '') + '/roupas.php" class="btn btn-primary btn-sm">Explorar catálogo</a></div>';
        }
    }, 200);
}

document.addEventListener('DOMContentLoaded', () => {

    // ---------- Drawer de filtros (mobile) ----------
    const filterTrigger = document.querySelector('.mobile-filter-trigger');
    const filtersPanel = document.querySelector('.filters-panel');
    if (filterTrigger && filtersPanel) {
        filterTrigger.addEventListener('click', () => filtersPanel.classList.add('open'));
        const closeBtn = filtersPanel.querySelector('.filters-close');
        if (closeBtn) closeBtn.addEventListener('click', () => filtersPanel.classList.remove('open'));
    }

    // ---------- Aplicar filtros automaticamente ao alterar ----------
    if (filtersPanel && filtersPanel.tagName === 'FORM') {
        const submitFilters = () => {
            // Guarda a posição de rolagem do painel para restaurar após recarregar
            try { sessionStorage.setItem('viszFilterScroll', filtersPanel.scrollTop); } catch (e) {}
            filtersPanel.submit();
        };

        // Checkboxes, radios, selects e datas aplicam na hora
        filtersPanel.querySelectorAll('input[type="checkbox"], input[type="radio"], input[type="date"], select')
            .forEach(el => el.addEventListener('change', submitFilters));

        // Campos de texto/número aguardam o usuário parar de digitar
        let typingTimer;
        filtersPanel.querySelectorAll('input[type="text"], input[type="number"]').forEach(el => {
            el.addEventListener('input', () => {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(submitFilters, 700);
            });
        });

        // Restaura a rolagem do painel de filtros após o recarregamento
        try {
            const savedScroll = sessionStorage.getItem('viszFilterScroll');
            if (savedScroll !== null) {
                filtersPanel.scrollTop = parseInt(savedScroll, 10);
                sessionStorage.removeItem('viszFilterScroll');
            }
        } catch (e) {}
    }

    // ---------- Favoritar via AJAX ----------
    document.querySelectorAll('.fav-toggle').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            const productId = btn.dataset.productId;
            try {
                const res = await fetch((window.BASE_URL || '') + '/api/favoritar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + encodeURIComponent(productId) + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
                });
                const data = await res.json();
                if (data.status === 'login_required') {
                    window.location.href = (window.BASE_URL || '') + '/login.php';
                    return;
                }
                if (data.status === 'ok') {
                    btn.classList.toggle('active', data.favorited);
                    showToast(data.favorited ? 'Adicionado aos favoritos.' : 'Removido dos favoritos.');

                    if (!data.favorited && window.REMOVE_ON_UNFAVORITE) {
                        const card = btn.closest('.product-card');
                        if (card) removeFavoriteCard(card);
                    }
                }
            } catch (e) {
                showToast('Não foi possível atualizar seus favoritos agora.');
            }
        });
    });

    // ---------- Galeria de produto ----------
    const galleryMain = document.querySelector('.gallery-main img');
    document.querySelectorAll('.gallery-thumbs button').forEach(thumb => {
        thumb.addEventListener('click', () => {
            document.querySelectorAll('.gallery-thumbs button').forEach(b => b.classList.remove('active'));
            thumb.classList.add('active');
            if (galleryMain) galleryMain.src = thumb.dataset.full;
        });
    });

    // ---------- Seleção de tamanho ----------
    document.querySelectorAll('.size-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            if (pill.hasAttribute('disabled')) return;
            document.querySelectorAll('.size-pill').forEach(p => p.classList.remove('selected'));
            pill.classList.add('selected');
            const input = document.getElementById('selected_size');
            if (input) input.value = pill.dataset.size;
        });
    });

    // ---------- Validação simples de datas de aluguel ----------
    const startInput = document.querySelector('input[name="start_date"]');
    const endInput = document.querySelector('input[name="end_date"]');
    if (startInput && endInput) {
        const today = new Date().toISOString().split('T')[0];
        startInput.min = today;
        startInput.addEventListener('change', () => {
            endInput.min = startInput.value;
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = startInput.value;
            }
        });
    }

    // ---------- Copiar código do pedido ----------
    const copyBtn = document.querySelector('.copy-code-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const code = copyBtn.dataset.code;
            navigator.clipboard.writeText(code).then(() => showToast('Código copiado!'));
        });
    }

    // ---------- Scroll reveal sutil (uma vez, discreto) ----------
    const revealTargets = document.querySelectorAll('.section-header, .product-card');
    if ('IntersectionObserver' in window && revealTargets.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'none';
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        revealTargets.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(12px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            io.observe(el);
        });
    }
});
