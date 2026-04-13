(function () {
    // analytics.php injects aggregate-only segment data into these globals. This
    // script then adds an optional "Get AI Profile" button to each rendered card.
    const segmentCards = document.querySelectorAll('[data-segment-card]');
    const segments = Array.isArray(window.__C360_SEGMENTS) ? window.__C360_SEGMENTS : [];
    const meta = window.__C360_META || {};

    if (!segmentCards.length || !segments.length) {
        return;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[char]));
    }

    function renderList(items) {
        const rows = (Array.isArray(items) ? items : [])
            .filter((item) => String(item ?? '').trim() !== '')
            .map((item) => `<li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-amber-500"></span><span>${escapeHtml(item)}</span></li>`)
            .join('');

        return rows || '<li class="text-xs text-slate-500">No suggestion returned.</li>';
    }

    async function requestAiProfile(segmentIndex, outputPanel, button) {
        const segment = segments[segmentIndex];
        if (!segment || !outputPanel || !button) {
            return;
        }

        outputPanel.classList.remove('hidden');
        outputPanel.innerHTML = `
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-primary"></span>
                Building a plain-language profile for this customer group...
            </div>
        `;

        button.disabled = true;
        button.classList.add('opacity-70', 'cursor-wait');

        try {
            // Send only aggregate segment statistics through the PHP proxy. Raw
            // customer identifiers remain on the server and are not sent to Groq.
            const response = await fetch('api/groq-insight.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    segment: {
                        ...segment,
                        customer_pct: segment.pct,
                        business_meta: meta,
                    },
                }),
            });

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.error || 'Could not generate this customer-group profile.');
            }

            const profile = payload.profile || {};
            const riskLevel = profile.churn_risk?.level || 'Not stated';
            const riskReason = profile.churn_risk?.reason || '';
            const sourceLabel = payload.source === 'groq' ? 'AI-generated' : 'Fallback summary';

            outputPanel.innerHTML = `
                <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4 text-xs text-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-primary">${escapeHtml(profile.headline || `${segment.name} Profile`)}</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-700">${escapeHtml(sourceLabel)}</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600" data-ai-profile-close aria-label="Hide AI profile">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <p><span class="font-semibold text-primary">Who they are:</span> ${escapeHtml(profile.lifestyle || segment.description || 'No description returned.')}</p>
                        <p><span class="font-semibold text-primary">How they buy:</span> ${escapeHtml(profile.buying_personality || 'No buying summary returned.')}</p>
                        <p><span class="font-semibold text-primary">Leaving risk:</span> ${escapeHtml(riskLevel)}${riskReason ? ` — ${escapeHtml(riskReason)}` : ''}</p>

                        <div>
                            <p class="font-semibold text-primary">What to say to them</p>
                            <ul class="mt-2 space-y-2">${renderList(profile.messaging_angles)}</ul>
                        </div>

                        <div>
                            <p class="font-semibold text-primary">Best channels</p>
                            <p class="mt-1">${escapeHtml((profile.channels || []).join(', ') || 'Not stated')}</p>
                        </div>

                        <div>
                            <p class="font-semibold text-primary">Offer ideas</p>
                            <ul class="mt-2 space-y-2">${renderList(profile.offers)}</ul>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            outputPanel.innerHTML = `
                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-xs text-red-700">
                    ${escapeHtml(error.message || 'Could not generate this customer-group profile right now.')}
                </div>
            `;
        } finally {
            button.disabled = false;
            button.classList.remove('opacity-70', 'cursor-wait');
        }
    }

    segmentCards.forEach((card) => {
        const segmentIndex = Number(card.dataset.segmentIndex);
        if (!Number.isInteger(segmentIndex) || !segments[segmentIndex]) {
            return;
        }

        if (card.querySelector('[data-ai-profile-trigger]')) {
            return;
        }

        // The injected controls are appended after the original card content so the
        // existing Tailwind layout stays unchanged.
        const controls = document.createElement('div');
        controls.className = 'mt-4 space-y-3';
        controls.innerHTML = `
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-primary transition-colors hover:bg-amber-100"
                data-ai-profile-trigger
            >
                <span class="material-symbols-outlined text-[16px]">auto_awesome</span>
                Get AI Profile
            </button>
            <div class="hidden" data-ai-profile-output></div>
        `;

        card.appendChild(controls);

        const button = controls.querySelector('[data-ai-profile-trigger]');
        const outputPanel = controls.querySelector('[data-ai-profile-output]');

        button?.addEventListener('click', () => requestAiProfile(segmentIndex, outputPanel, button));
        outputPanel?.addEventListener('click', (event) => {
            if (event.target?.closest('[data-ai-profile-close]')) {
                outputPanel.classList.add('hidden');
                outputPanel.innerHTML = '';
            }
        });
    });
})();
