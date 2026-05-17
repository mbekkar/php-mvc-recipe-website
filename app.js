/**
 * app.js — Minimal vanilla JS
 * Author: Mounir Bekkar
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Auto-dismiss flash messages after 5s ─────────────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ── Image preview before upload ───────────────────────────────────────────
    const imgInput = document.getElementById('image');
    if (imgInput) {
        imgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            // Size validation (5 MB)
            if (file.size > 5 * 1024 * 1024) {
                alert("L'image ne doit pas dépasser 5 Mo.");
                this.value = '';
                return;
            }

            // Preview
            let preview = document.getElementById('img-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.id            = 'img-preview';
                preview.style.cssText = 'max-width:200px;border-radius:8px;margin-top:.5rem;border:1px solid #e2e8f0';
                this.parentElement.appendChild(preview);
            }
            preview.src = URL.createObjectURL(file);
        });
    }

    // ── Confirm delete ────────────────────────────────────────────────────────
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ── Ingredient + step counter ─────────────────────────────────────────────
    const ingArea  = document.getElementById('ingredients_text');
    const stepArea = document.getElementById('steps_text');

    function updateCounter(textarea, unit) {
        if (!textarea) return;
        let counter = textarea.parentElement.querySelector('.line-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.className = 'line-counter';
            counter.style.cssText = 'color:#94a3b8;display:block;margin-top:.2rem';
            textarea.parentElement.appendChild(counter);
        }
        const count = textarea.value.split('\n').filter(l => l.trim()).length;
        counter.textContent = `${count} ${unit}${count > 1 ? 's' : ''}`;
    }

    if (ingArea) {
        ingArea.addEventListener('input',  () => updateCounter(ingArea,  'ingrédient'));
        updateCounter(ingArea, 'ingrédient');
    }
    if (stepArea) {
        stepArea.addEventListener('input', () => updateCounter(stepArea, 'étape'));
        updateCounter(stepArea, 'étape');
    }

    // ── Slug preview for recipe title ─────────────────────────────────────────
    const titleInput = document.getElementById('title');
    if (titleInput) {
        titleInput.addEventListener('input', function () {
            const charCount = this.value.length;
            let hint = this.parentElement.querySelector('.char-count');
            if (!hint) {
                hint = document.createElement('small');
                hint.className = 'char-count';
                hint.style.cssText = 'color:#94a3b8;display:block;margin-top:.2rem';
                this.parentElement.appendChild(hint);
            }
            hint.textContent = `${charCount}/200 caractères`;
            hint.style.color  = charCount > 180 ? '#e74c3c' : '#94a3b8';
        });
    }
});
