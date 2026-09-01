(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.classList.toggle('is-visible', shouldShow);
            button.setAttribute('aria-pressed', String(shouldShow));
            button.setAttribute('aria-label', shouldShow ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
            button.title = shouldShow ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور';
            input.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    document.querySelectorAll('[data-submit-on-change]').forEach((control) => {
        control.addEventListener('change', () => {
            // A year or grade change invalidates the currently selected term.
            // Omit that stale value so the server resolves the first valid term
            // for the newly selected academic context.
            if (['year', 'grade'].includes(control.name)) {
                const term = control.form?.querySelector('select[name="term"]');
                if (term) term.disabled = true;
            }
            control.form?.requestSubmit();
        });
    });
    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
    document.querySelectorAll('[data-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal());
    });
    document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    document.querySelectorAll('dialog.admin-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });
    document.querySelectorAll('dialog[data-dialog-auto-open]').forEach((dialog) => dialog.showModal());
    document.querySelectorAll('.quiz-builder').forEach((builder, index) => {
        const summary = builder.querySelector('summary');
        const surface = builder.querySelector('.quiz-detail-surface');
        const list = builder.closest('.quiz-list');
        const dock = list?.querySelector('[data-quiz-detail-dock]');
        if (!summary || !surface || !dock) return;

        const surfaceId = surface.id || `quiz-detail-surface-${index}`;
        surface.id = surfaceId;
        summary.setAttribute('aria-controls', surfaceId);
        summary.setAttribute('aria-expanded', 'false');

        summary.addEventListener('click', (event) => {
            event.preventDefault();
            const isCurrent = dock.firstElementChild === surface && !dock.hidden;

            list.querySelectorAll('.quiz-builder summary[aria-expanded="true"]').forEach((openSummary) => {
                openSummary.setAttribute('aria-expanded', 'false');
                openSummary.classList.remove('is-active');
            });

            if (isCurrent) {
                builder.append(surface);
                dock.hidden = true;
                return;
            }

            const currentSurface = dock.firstElementChild;
            if (currentSurface) {
                const owner = list.querySelector(`[aria-controls="${currentSurface.id}"]`)?.closest('.quiz-builder');
                owner?.append(currentSurface);
            }

            dock.replaceChildren(surface);
            dock.hidden = false;
            summary.setAttribute('aria-expanded', 'true');
            summary.classList.add('is-active');
            dock.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    document.querySelectorAll('[data-question-picker]').forEach((picker) => {
        const cards = [...picker.querySelectorAll('[data-picker-card]')];
        const count = picker.querySelector('[data-picker-count]');
        const submit = picker.querySelector('[data-picker-submit]');
        const submitLabel = picker.querySelector('[data-picker-submit-label]');
        const selectAll = picker.querySelector('[data-picker-select-all]');
        const filterButtons = [...picker.querySelectorAll('[data-picker-filter]')];
        let currentFilter = 'available';
        const update = () => {
            const selected = cards.filter((card) => card.querySelector('input[type="checkbox"]')?.checked);
            cards.forEach((card) => card.classList.toggle('is-selected', card.querySelector('input[type="checkbox"]')?.checked));
            if (count) count.textContent = String(selected.length);
            if (submit) {
                submit.disabled = selected.length === 0;
                if (submitLabel) submitLabel.textContent = selected.length ? `إضافة ${selected.length} أسئلة` : 'إضافة الأسئلة';
            }
            if (selectAll) {
                const available = cards
                    .filter((card) => !card.hidden)
                    .map((card) => card.querySelector('input[type="checkbox"]'))
                    .filter((input) => input && !input.disabled);
                selectAll.checked = available.length > 0 && available.every((input) => input.checked);
                selectAll.indeterminate = available.some((input) => input.checked) && !selectAll.checked;
            }
        };
        const applyFilter = () => {
            cards.forEach((card) => {
                card.hidden = currentFilter !== 'all' && card.dataset.pickerState !== currentFilter;
            });
            update();
        };
        cards.forEach((card) => {
            const checkbox = card.querySelector('input[type="checkbox"]');
            if (!checkbox || checkbox.disabled) return;
            card.addEventListener('click', (event) => {
                if (event.target.closest('input, details, summary, button, a')) return;
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
            checkbox.addEventListener('change', update);
        });
        selectAll?.addEventListener('change', () => {
            cards.forEach((card) => {
                if (card.hidden) return;
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.disabled) checkbox.checked = selectAll.checked;
            });
            update();
        });
        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                currentFilter = button.dataset.pickerFilter || 'available';
                filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                applyFilter();
            });
        });
        picker.addEventListener('close', () => {
            cards.forEach((card) => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.disabled) checkbox.checked = false;
            });
            update();
        });
        applyFilter();
    });
    const setInlineFormOpen = (panel, open, focus = false) => {
        if (!panel) return;
        panel.hidden = !open;
        document.querySelectorAll(`[data-inline-form-toggle="${panel.id}"]`).forEach((button) => button.setAttribute('aria-expanded', String(open)));
        if (open && focus) window.requestAnimationFrame(() => panel.querySelector('input, select, textarea')?.focus());
    };
    document.querySelectorAll('[data-inline-form-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = document.getElementById(button.dataset.inlineFormToggle);
            setInlineFormOpen(panel, panel?.hidden ?? false, true);
        });
    });
    document.querySelectorAll('[data-inline-form-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = document.getElementById(button.dataset.inlineFormClose);
            setInlineFormOpen(panel, false);
            document.querySelector(`[data-inline-form-toggle="${button.dataset.inlineFormClose}"]`)?.focus();
        });
    });
    document.querySelectorAll('.curriculum-inline-create').forEach((panel) => {
        panel.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            setInlineFormOpen(panel, false);
            document.querySelector(`[data-inline-form-toggle="${panel.id}"]`)?.focus();
        });
    });
    document.querySelectorAll('form[data-whatsapp-open]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.processing === 'true') return;

            const submitter = event.submitter || form.querySelector('button[type="submit"]');
            const error = form.querySelector('[data-whatsapp-error]');
            const originalText = submitter?.textContent || '';
            const popup = window.open('about:blank', '_blank');

            if (!popup) {
                if (error) error.textContent = 'اسمح بالنوافذ المنبثقة لفتح WhatsApp Web.';
                return;
            }
            popup.opener = null;

            form.dataset.processing = 'true';
            if (error) error.textContent = '';
            if (submitter) {
                submitter.disabled = true;
                submitter.textContent = form.dataset.processingText || 'جارٍ تجهيز الرسالة...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.url) throw new Error(payload.message || 'تعذر تجهيز رسالة WhatsApp.');

                popup.location.replace(payload.url);
                const audience = form.dataset.whatsappOpen;
                const confirmation = document.querySelector(`[data-whatsapp-confirm-for="${audience}"]`);
                const status = document.querySelector(`[data-whatsapp-status="${audience}"]`);
                if (confirmation) confirmation.hidden = false;
                if (status) {
                    status.classList.remove('is-sent');
                    status.textContent = audience === 'student'
                        ? 'WhatsApp الطالب: قيد الانتظار'
                        : 'WhatsApp ولي الأمر: قيد الانتظار';
                }
            } catch (exception) {
                popup.close();
                if (error) error.textContent = exception.message || 'تعذر تجهيز رسالة WhatsApp.';
            } finally {
                form.dataset.processing = 'false';
                if (submitter) {
                    submitter.disabled = false;
                    submitter.textContent = originalText;
                }
            }
        });
    });

    document.querySelectorAll('form[data-processing]:not([data-whatsapp-open])').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
            if (!submitter) return;
            // Disabled submit buttons are omitted from the request. Preserve consequential
            // action values (for example `decision=approved`) before locking the form.
            if (submitter.name && !form.querySelector(`input[data-submitter-value="${submitter.name}"]`)) {
                const value = document.createElement('input');
                value.type = 'hidden';
                value.name = submitter.name;
                value.value = submitter.value;
                value.dataset.submitterValue = submitter.name;
                form.append(value);
            }
            submitter.disabled = true;
            submitter.textContent = form.dataset.processingText || 'جارٍ الحفظ...';
        });
    });

    const closeProfessionalSelects = (except = null) => {
        document.querySelectorAll('.professional-select.is-open').forEach((root) => {
            if (root === except) return;
            root.classList.remove('is-open');
            root.querySelector('[role="combobox"]')?.setAttribute('aria-expanded', 'false');
        });
        if (!except) {
            document.querySelectorAll('.professional-select-surface-open').forEach((surface) => {
                surface.classList.remove('professional-select-surface-open');
            });
        }
    };

    document.querySelectorAll('select:not([multiple]):not([data-native-select])').forEach((select, index) => {
        if (select.dataset.professionalSelect === 'ready') return;
        select.dataset.professionalSelect = 'ready';

        const root = document.createElement('div');
        root.className = 'professional-select';
        const listId = `professional-select-list-${index}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'professional-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', listId);
        trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.closest('label')?.childNodes[0]?.textContent?.trim() || 'اختر قيمة');
        trigger.innerHTML = '<span></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>';

        const menu = document.createElement('div');
        menu.className = 'professional-select-menu';
        const searchable = select.options.length > 7;
        let search = null;
        if (searchable) {
            search = document.createElement('input');
            search.type = 'search';
            search.className = 'professional-select-search';
            search.placeholder = 'ابحث في الخيارات...';
            search.setAttribute('aria-label', 'البحث في الخيارات');
            menu.append(search);
        }
        const list = document.createElement('div');
        list.id = listId;
        list.className = 'professional-select-options';
        list.setAttribute('role', 'listbox');
        menu.append(list);
        root.append(trigger, menu);
        select.insertAdjacentElement('afterend', root);
        select.classList.add('professional-select-native');

        const render = (query = '') => {
            const normalized = query.trim().toLocaleLowerCase('ar');
            list.replaceChildren();
            [...select.options].forEach((option) => {
                if (normalized && !option.text.toLocaleLowerCase('ar').includes(normalized)) return;
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'professional-select-option';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(option.selected));
                item.dataset.value = option.value;
                item.disabled = option.disabled;
                item.textContent = option.text;
                if (option.selected) item.classList.add('is-selected');
                item.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    closeProfessionalSelects();
                    trigger.focus();
                });
                list.append(item);
            });
        };
        const sync = () => {
            const selected = select.selectedOptions[0];
            trigger.querySelector('span').textContent = selected?.text || 'اختر قيمة';
            trigger.disabled = select.disabled;
            render(search?.value || '');
            root.classList.remove('is-invalid');
        };
        const open = () => {
            closeProfessionalSelects();
            root.classList.add('is-open');
            root.closest('form')?.classList.add('professional-select-surface-open');
            root.closest('.card, .table-action-panel, .admin-dialog, dialog')?.classList.add('professional-select-surface-open');
            trigger.setAttribute('aria-expanded', 'true');
            render();
            window.requestAnimationFrame(() => (search || list.querySelector('.is-selected') || list.querySelector('button'))?.focus());
        };
        trigger.addEventListener('click', () => root.classList.contains('is-open') ? closeProfessionalSelects() : open());
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) { event.preventDefault(); open(); }
        });
        menu.addEventListener('keydown', (event) => {
            const items = [...list.querySelectorAll('button:not(:disabled)')];
            const current = items.indexOf(document.activeElement);
            if (event.key === 'Escape') { closeProfessionalSelects(); trigger.focus(); }
            if (event.key === 'ArrowDown') { event.preventDefault(); items[Math.min(current + 1, items.length - 1)]?.focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); items[Math.max(current - 1, 0)]?.focus(); }
        });
        search?.addEventListener('input', () => render(search.value));
        select.addEventListener('change', sync);
        select.addEventListener('invalid', () => {
            root.classList.add('is-invalid');
            trigger.focus({ preventScroll: true });
        });
        select.form?.addEventListener('reset', () => window.setTimeout(sync));
        new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true });
        sync();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.professional-select')) closeProfessionalSelects();
    });

    const learningTree = document.querySelector('.learning-path-tree, .learning-course-catalog');
    if (learningTree) {
        const search = document.querySelector('[data-learning-path-search]');
        const status = document.querySelector('[data-learning-path-status]');
        const subject = document.querySelector('[data-learning-path-subject]');
        const count = document.querySelector('[data-learning-results-count]');
        const empty = document.querySelector('[data-learning-filter-empty]');
        const emptyTitle = empty?.querySelector('[data-learning-empty-title]');
        const emptyCopy = empty?.querySelector('[data-learning-empty-copy]');
        const courses = [...learningTree.querySelectorAll('[data-learning-course]')];
        const subjects = [...learningTree.querySelectorAll('[data-learning-subject]')];
        const storageKey = `admin-learning-path:${learningTree.dataset.learningFilterKey || 'default'}`;
        const normalize = (value) => String(value || '').trim().toLocaleLowerCase('ar');
        const saveLearningFilters = () => {
            try { sessionStorage.setItem(storageKey, JSON.stringify({ search: search?.value || '', status: status?.value || 'all', subject: subject?.value || 'all' })); } catch (_) { /* Storage may be unavailable. */ }
        };
        const applyLearningFilters = () => {
            const query = normalize(search?.value);
            const selectedStatus = status?.value || 'all';
            const selectedSubject = subject?.value || 'all';
            let visible = 0;
            courses.forEach((course) => {
                const matchesText = !query || normalize(course.textContent).includes(query);
                const matchesStatus = selectedStatus === 'all' || course.dataset.status === selectedStatus;
                const matchesSubject = selectedSubject === 'all' || course.dataset.subject === selectedSubject;
                const show = matchesText && matchesStatus && matchesSubject;
                course.hidden = !show;
                if (show) visible += 1;
            });
            subjects.forEach((subject) => {
                const subjectCourses = [...subject.querySelectorAll(':scope > .learning-subject-body > [data-learning-course]')];
                const subjectMatches = !query || normalize(subject.querySelector(':scope > summary')?.textContent).includes(query);
                if (subjectMatches && selectedStatus === 'all') subjectCourses.forEach((course) => { course.hidden = false; });
                subject.hidden = subjectCourses.length > 0 && !subjectCourses.some((course) => !course.hidden);
            });
            visible = courses.filter((course) => !course.hidden).length;
            if (count) count.textContent = `${visible} دورة ظاهرة`;
            if (empty) empty.hidden = visible > 0 || courses.length === 0;
            if (!visible && courses.length) {
                if (query) {
                    if (emptyTitle) emptyTitle.textContent = 'لا توجد دورة تطابق البحث';
                    if (emptyCopy) emptyCopy.textContent = 'جرّب اسم دورة أو مادة أقصر، أو امسح البحث.';
                } else if (selectedSubject !== 'all') {
                    if (emptyTitle) emptyTitle.textContent = 'لا توجد دورات لهذه المادة';
                    if (emptyCopy) emptyCopy.textContent = 'اختر مادة أخرى أو اعرض كل المواد.';
                } else {
                    if (emptyTitle) emptyTitle.textContent = 'لا توجد دورات بهذه الحالة';
                    if (emptyCopy) emptyCopy.textContent = 'غيّر حالة النشر أو اعرض كل الحالات.';
                }
            }
            saveLearningFilters();
        };
        search?.addEventListener('input', applyLearningFilters);
        status?.addEventListener('change', applyLearningFilters);
        subject?.addEventListener('change', applyLearningFilters);
        document.querySelector('[data-learning-tree-expand]')?.addEventListener('click', () => learningTree.querySelectorAll('details:not([hidden])').forEach((item) => { item.open = true; }));
        document.querySelector('[data-learning-tree-collapse]')?.addEventListener('click', () => learningTree.querySelectorAll('details').forEach((item) => { item.open = false; }));
        document.querySelector('[data-learning-filter-reset]')?.addEventListener('click', () => {
            if (search) search.value = '';
            if (status) { status.value = 'all'; status.dispatchEvent(new Event('change', { bubbles: true })); }
            if (subject) { subject.value = 'all'; subject.dispatchEvent(new Event('change', { bubbles: true })); }
            applyLearningFilters();
            search?.focus();
        });
        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
            if (search && typeof saved.search === 'string') search.value = saved.search;
            if (status && [...status.options].some((option) => option.value === saved.status)) { status.value = saved.status; status.dispatchEvent(new Event('change', { bubbles: true })); }
            if (subject && [...subject.options].some((option) => option.value === saved.subject)) { subject.value = saved.subject; subject.dispatchEvent(new Event('change', { bubbles: true })); }
        } catch (_) { /* Ignore invalid or unavailable session storage. */ }
        applyLearningFilters();
    }

    const courseWorkspace = document.querySelector('[data-course-workspace]');
    if (courseWorkspace && !courseWorkspace.dataset.courseWorkspaceReady) {
        courseWorkspace.dataset.courseWorkspaceReady = 'true';
        const courseTabs = [...courseWorkspace.querySelectorAll('[data-course-tab]')];
        const coursePanels = [...courseWorkspace.querySelectorAll('[data-course-panel]')];
        const courseHeaderSave = courseWorkspace.querySelector('[data-course-save]');
        const activateCoursePanel = (name, updateHash = true) => {
            if (!coursePanels.some((panel) => panel.dataset.coursePanel === name)) return;
            courseTabs.forEach((tab) => {
                const active = tab.dataset.courseTab === name;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
            });
            coursePanels.forEach((panel) => { panel.hidden = panel.dataset.coursePanel !== name; });
            if (courseHeaderSave) courseHeaderSave.hidden = !['overview', 'settings'].includes(name);
            if (updateHash) history.replaceState(null, '', `#${name}`);
        };
        courseTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activateCoursePanel(tab.dataset.courseTab));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowRight', 'ArrowLeft'].includes(event.key)) return;
                event.preventDefault();
                const direction = event.key === 'ArrowRight' ? -1 : 1;
                const next = courseTabs[(index + direction + courseTabs.length) % courseTabs.length];
                next.focus();
                activateCoursePanel(next.dataset.courseTab);
            });
        });
        const requestedCoursePanel = location.hash.slice(1);
        activateCoursePanel(coursePanels.some((panel) => panel.dataset.coursePanel === requestedCoursePanel) ? requestedCoursePanel : 'overview', false);

        courseWorkspace.querySelectorAll('[data-toggle-workspace-create]').forEach((button) => button.addEventListener('click', () => {
            const panel = document.getElementById(button.dataset.toggleWorkspaceCreate);
            if (!panel) return;
            panel.hidden = false;
            panel.querySelector('input,select,textarea,button')?.focus();
        }));
        courseWorkspace.querySelectorAll('[data-close-workspace-create]').forEach((button) => button.addEventListener('click', () => {
            const panel = button.closest('.workspace-inline-create');
            if (panel) panel.hidden = true;
        }));

        const courseForm = document.getElementById('course-data-form');
        const courseFields = [...courseWorkspace.querySelectorAll('[form="course-data-form"]')];
        let courseDirty = false;
        courseFields.forEach((field) => {
            field.addEventListener('input', () => { courseDirty = true; });
            field.addEventListener('change', () => { courseDirty = true; });
            field.addEventListener('invalid', () => {
                const panel = field.closest('[data-course-panel]');
                if (panel) activateCoursePanel(panel.dataset.coursePanel);
            }, true);
        });
        courseForm?.addEventListener('submit', () => { courseDirty = false; });
        window.addEventListener('beforeunload', (event) => {
            if (!courseDirty) return;
            event.preventDefault();
            event.returnValue = '';
        });

        const scopeInputs = [...courseWorkspace.querySelectorAll('input[name="scope"]')];
        const academicContext = courseWorkspace.querySelector('.workspace-academic-context');
        const academicSelect = academicContext?.querySelector('select');
        const syncCourseScope = () => {
            const general = scopeInputs.find((input) => input.checked)?.value === 'general';
            if (academicContext) academicContext.hidden = general;
            if (academicSelect) academicSelect.disabled = general;
        };
        scopeInputs.forEach((input) => input.addEventListener('change', syncCourseScope));
        syncCourseScope();
    }

    const sidebar = document.querySelector('#admin-sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closers = document.querySelectorAll('[data-sidebar-close]');
    if (!sidebar || !toggle) return;

    const setOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        if (!open) toggle.focus({ preventScroll: true });
    };

    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('sidebar-open')));
    closers.forEach((closer) => closer.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) setOpen(false);
    });
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 860px)').matches) setOpen(false);
    }));
})();
