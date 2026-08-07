
        // ==========================================
        // PREVIEW NOTA — tooltip grande + confirmación
        // ==========================================

        (function() {
            let tip = null;
            let tipTimer = null;

            function posicionarTip(e) {
                if (!tip) return;
                const pad = 16;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                const r = tip.getBoundingClientRect();
                if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
                if (y + r.height > window.innerHeight - 8) y = e.clientY - r.height - pad;
                tip.style.left = Math.max(8, x) + 'px';
                tip.style.top = Math.max(8, y) + 'px';
            }

            document.addEventListener('mouseover', function(e) {
                const t = e.target.closest('[data-tooltip]');
                if (!t) return;
                if (tipTimer) window.clearTimeout(tipTimer);
                if (!tip) {
                    tip = document.createElement('div');
                    tip.className = 'jv-tooltip';
                    document.body.appendChild(tip);
                }
                tip.textContent = t.dataset.tooltip;
                tip.classList.add('jv-tooltip-visible');
                posicionarTip(e);
            });
            document.addEventListener('mousemove', function(e) {
                if (tip && tip.classList.contains('jv-tooltip-visible')) posicionarTip(e);
            });
            document.addEventListener('mouseout', function(e) {
                if (!e.target.closest('[data-tooltip]')) return;
                if (tipTimer) window.clearTimeout(tipTimer);
                tipTimer = window.setTimeout(function() {
                    if (tip) tip.classList.remove('jv-tooltip-visible');
                }, 80);
            });
        })();

        function confirmarRegistro(e) {
            e.preventDefault();
            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;
            btn.textContent = '⏳ REGISTRANDO...';
            document.querySelector('.buttons form').submit();
        }
    
