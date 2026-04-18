# Using the FormWizard component

The FormWizard (`/assets/js/form-wizard.js` + `/assets/css/form-wizard.css`)
is a drop-in multi-step wrapper for existing forms. It adds:

- A clickable step indicator at the top
- A progress bar showing how far the user has travelled
- Prev / Next navigation with step validation
- Automatic draft persistence to `localStorage` (every 30 s + on input)
- A "restore draft?" toast on the next visit
- Enter-key advances to the next step (instead of submitting)

## Minimal integration

1. Wrap the form body in `<div data-wizard data-wizard-key="my_form">`
2. Mark each logical section with `<div data-step="N" data-title="…">`
3. Load the CSS/JS and instantiate

```html
<form method="post" action="/save.php">
    <input type="hidden" name="csrf_token" value="…">

    <div data-wizard data-wizard-key="my_form">
        <div data-step="1" data-title="اطلاعات پایه">
            <label>نام<input type="text" name="name" required></label>
        </div>
        <div data-step="2" data-title="جزئیات">
            <label>شرح<textarea name="desc"></textarea></label>
        </div>
        <div data-step="3" data-title="مرور و ارسال" data-is-final="true">
            <p>بازبینی و تأیید</p>
        </div>
    </div>
</form>

<link rel="stylesheet" href="/assets/css/form-wizard.css">
<script type="module">
    import { FormWizard } from '/assets/js/form-wizard.js';
    new FormWizard(document.querySelector('[data-wizard]'));
</script>
```

## Auto-init

Add `data-wizard-autoinit` to skip the manual constructor call:

```html
<div data-wizard data-wizard-autoinit data-wizard-key="my_form">
    ...
</div>
```

## Options

`new FormWizard(el, options)` accepts:

| option              | default  | purpose                                     |
|---------------------|----------|---------------------------------------------|
| `autoSaveKey`       | auto     | override the localStorage key               |
| `autoSaveInterval`  | `30000`  | periodic save interval in ms (0 = disabled) |
| `onBeforeStep(i)`   | null     | return false to block advance from step i   |
| `onComplete()`      | null     | called on form submit (after clearDraft)    |

## Events

The container fires `ag:wizard-step` on every step change:

```js
document.querySelector('[data-wizard]').addEventListener('ag:wizard-step', (e) => {
    console.log('now at step', e.detail.index, 'isLast:', e.detail.isLast);
});
```

## Applying to existing large forms

For the `pardis/daily_report_form_ps.php` and
`pardis/meeting_minutes_form.php` forms, a full wizardisation is tracked
under TD-UX-002 in `docs/TECH_DEBT.md`. The suggested step breakdown is:

### Daily report → 7 steps
1. اطلاعات پایه (date, contractor, block, weather)
2. پرسنل
3. ماشین‌آلات
4. مصالح (in/out + docs)
5. فعالیت‌ها
6. تصاویر و مستندات
7. مرور و ارسال

### Meeting minutes → 4 steps
1. اطلاعات جلسه (date, location, attendees)
2. دستور جلسه (agenda + decisions)
3. بارگذاری مستندات
4. مرور و ثبت

The forms share a lot of inline state (rows added via `addMac()`, etc.)
that assumes a single-page layout. The conversion is safe but tedious;
it should be done in one sitting rather than piecemeal to avoid
half-converted pages.
