/**
 * AlumGlass FormWizard — progressive-enhancement multi-step form.
 *
 * Usage (opt-in, no build step required):
 *
 *   <form>
 *     <div data-wizard data-wizard-key="daily_report">
 *       <div data-step="1" data-title="اطلاعات پایه">…</div>
 *       <div data-step="2" data-title="پرسنل">…</div>
 *       …
 *       <div data-step="N" data-title="مرور و ارسال"
 *            data-is-final="true">…</div>
 *     </div>
 *   </form>
 *   <script type="module">
 *     import { FormWizard } from '/assets/js/form-wizard.js';
 *     new FormWizard(document.querySelector('[data-wizard]'));
 *   </script>
 *
 * Behaviour:
 * - Builds a progress bar + step indicator above the wizard container
 * - Only one step is visible at a time
 * - Prev/Next buttons (enter key advances, shift+enter skips)
 * - Auto-save drafts to localStorage every 30s + on blur
 *   (key: `form_<wizard-key>__<pathname>`)
 * - Restore prompt with "Restore draft?" toast on load
 * - Step completion tracked; users can only click on steps
 *   that are <= farthest-visited step
 * - On submit, clears the draft
 */

export class FormWizard {
    constructor(container, opts = {}) {
        this.container = container;
        this.form = container.closest('form') || container;
        this.steps = [...container.querySelectorAll(':scope > [data-step]')];
        if (!this.steps.length) return;

        this.currentStep = 0;
        this.farthest = 0;

        const key = container.dataset.wizardKey || 'form';
        this.autoSaveKey = opts.autoSaveKey || `form_${key}__${location.pathname}`;
        this.autoSaveMs = opts.autoSaveInterval ?? 30000;
        this.onBeforeStep = opts.onBeforeStep || null;
        this.onComplete = opts.onComplete || null;

        this.buildUI();
        this.restoreDraftIfAny();
        this.show(0);
        this.startAutoSave();
        this.bindFormHooks();
    }

    // ── UI ──

    buildUI() {
        this.progressEl = document.createElement('div');
        this.progressEl.className = 'ag-wizard-progress';
        this.progressEl.setAttribute('role', 'tablist');

        this.steps.forEach((s, i) => {
            const title = s.dataset.title || `مرحله ${i + 1}`;
            const step = document.createElement('button');
            step.type = 'button';
            step.className = 'ag-wizard-step';
            step.dataset.stepIdx = i;
            step.innerHTML = `
                <span class="ag-wizard-step__num" aria-hidden="true">${i + 1}</span>
                <span class="ag-wizard-step__title">${this.escape(title)}</span>`;
            step.addEventListener('click', () => {
                if (i <= this.farthest) this.goTo(i);
            });
            this.progressEl.appendChild(step);
        });

        this.bar = document.createElement('div');
        this.bar.className = 'ag-wizard-bar';
        this.barFill = document.createElement('span');
        this.barFill.className = 'ag-wizard-bar__fill';
        this.bar.appendChild(this.barFill);

        this.container.prepend(this.bar);
        this.container.prepend(this.progressEl);

        // Nav buttons
        this.nav = document.createElement('div');
        this.nav.className = 'ag-wizard-nav';
        this.nav.innerHTML = `
            <button type="button" class="ag-btn ag-wizard-nav__prev">مرحله قبل</button>
            <span class="ag-wizard-nav__spacer" aria-hidden="true"></span>
            <button type="button" class="ag-btn ag-btn-primary ag-wizard-nav__next">مرحله بعد</button>
            <button type="submit" class="ag-btn ag-btn-primary ag-wizard-nav__submit" hidden>ارسال</button>`;
        this.container.appendChild(this.nav);

        this.prevBtn = this.nav.querySelector('.ag-wizard-nav__prev');
        this.nextBtn = this.nav.querySelector('.ag-wizard-nav__next');
        this.submitBtn = this.nav.querySelector('.ag-wizard-nav__submit');

        this.prevBtn.addEventListener('click', () => this.goTo(this.currentStep - 1));
        this.nextBtn.addEventListener('click', () => this.advance());
    }

    show(idx) {
        this.steps.forEach((s, i) => {
            s.hidden = i !== idx;
            s.classList.toggle('is-active', i === idx);
        });
        [...this.progressEl.children].forEach((c, i) => {
            c.classList.toggle('is-active', i === idx);
            c.classList.toggle('is-complete', i < this.farthest);
            c.setAttribute('aria-current', i === idx ? 'step' : 'false');
        });
        this.barFill.style.width = (((idx + 1) / this.steps.length) * 100) + '%';

        const isLast = idx === this.steps.length - 1;
        this.prevBtn.disabled = idx === 0;
        this.nextBtn.hidden = isLast;
        this.submitBtn.hidden = !isLast;
        this.currentStep = idx;
        if (idx > this.farthest) this.farthest = idx;

        this.container.dispatchEvent(new CustomEvent('ag:wizard-step', {
            detail: { index: idx, isLast },
            bubbles: true,
        }));
    }

    goTo(idx) {
        if (idx < 0 || idx >= this.steps.length) return;
        this.show(idx);
        this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    advance() {
        if (this.onBeforeStep && !this.onBeforeStep(this.currentStep)) return;
        if (!this.validateCurrentStep()) return;
        this.saveDraft();
        this.goTo(this.currentStep + 1);
    }

    validateCurrentStep() {
        const step = this.steps[this.currentStep];
        const required = step.querySelectorAll('[required]');
        for (const el of required) {
            if (el.disabled || el.hidden) continue;
            if (el.type === 'checkbox' && !el.checked) { el.focus(); el.reportValidity?.(); return false; }
            if (el.type === 'radio') continue; // handled by groups
            if (!el.value || !el.checkValidity?.()) { el.focus(); el.reportValidity?.(); return false; }
        }
        return true;
    }

    // ── Draft persistence ──

    startAutoSave() {
        if (!this.autoSaveMs) return;
        this._autoTimer = setInterval(() => this.saveDraft(), this.autoSaveMs);
        this.form.addEventListener('input', this.debouncedSave.bind(this));
    }

    debouncedSave() {
        clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => this.saveDraft(), 800);
    }

    saveDraft() {
        try {
            const fd = new FormData(this.form);
            const obj = {};
            for (const [k, v] of fd.entries()) {
                if (v instanceof File) continue;
                if (obj[k] !== undefined) {
                    if (!Array.isArray(obj[k])) obj[k] = [obj[k]];
                    obj[k].push(v);
                } else {
                    obj[k] = v;
                }
            }
            localStorage.setItem(this.autoSaveKey, JSON.stringify({
                _step: this.currentStep,
                _farthest: this.farthest,
                _savedAt: new Date().toISOString(),
                data: obj,
            }));
            this.announce(`پیش‌نویس ذخیره شد (${new Date().toLocaleTimeString('fa-IR')})`);
        } catch (_) { /* quota / private mode */ }
    }

    restoreDraftIfAny() {
        try {
            const raw = localStorage.getItem(this.autoSaveKey);
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (!draft || !draft.data) return;
            // Prompt user (lightweight toast with action)
            if (window.AG?.toast) {
                window.AG.toast('یک پیش‌نویس ذخیره‌شده از این فرم پیدا شد.', 'info', {
                    action: { label: 'بازیابی', onClick: () => this.applyDraft(draft) },
                    duration: 10000,
                });
            } else {
                if (confirm('پیش‌نویس ذخیره‌شده پیدا شد. بازیابی شود؟')) this.applyDraft(draft);
            }
        } catch (_) { /* ignore */ }
    }

    applyDraft(draft) {
        for (const [k, v] of Object.entries(draft.data || {})) {
            const values = Array.isArray(v) ? v : [v];
            const fields = this.form.querySelectorAll(`[name="${CSS.escape(k)}"]`);
            fields.forEach((f, i) => {
                if (f.type === 'file') return;
                if (f.type === 'checkbox') { f.checked = values.includes(f.value); return; }
                if (f.type === 'radio')    { f.checked = f.value === values[0]; return; }
                f.value = values[i] ?? values[0] ?? '';
                f.dispatchEvent(new Event('input', { bubbles: true }));
                f.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        this.farthest = Math.max(this.farthest, draft._farthest || 0);
        this.goTo(draft._step || 0);
    }

    clearDraft() {
        try { localStorage.removeItem(this.autoSaveKey); } catch (_) { /* ignore */ }
    }

    bindFormHooks() {
        this.form.addEventListener('submit', () => {
            this.clearDraft();
            if (this._autoTimer) clearInterval(this._autoTimer);
            if (this.onComplete) this.onComplete();
        });
        // Enter in single-line inputs advances the step instead of submitting
        this.form.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            const el = e.target;
            if (el.tagName === 'TEXTAREA') return;
            if (el.type === 'submit' || el.type === 'button') return;
            const isLast = this.currentStep === this.steps.length - 1;
            if (!isLast) { e.preventDefault(); this.advance(); }
        });
    }

    // ── helpers ──

    escape(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    announce(message) {
        let el = document.getElementById('ag-wizard-live');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ag-wizard-live';
            el.className = 'ag-visually-hidden';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        el.textContent = message;
    }
}

// Auto-init any wizard that opts-in via a data attribute on DOMContentLoaded.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-wizard][data-wizard-autoinit]')
        .forEach((el) => { try { new FormWizard(el); } catch (e) { console.error('[wizard]', e); } });
});
