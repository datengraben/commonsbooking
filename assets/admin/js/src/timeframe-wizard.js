/**
 * Timeframe creation wizard (MVP).
 *
 * A pure front-end presentation layer that groups the *existing* CMB2 timeframe
 * fields into ordered, human-friendly steps. It moves the rendered `.cmb-row`
 * elements into step panels within the same metabox form - it never creates,
 * renames or re-saves any field, so the data model and the existing conditional
 * show/hide logic in `timeframe.js` keep working untouched.
 *
 * Config is provided via `wp_localize_script` as `cb_timeframe_wizard`
 * (see includes/Admin.php and Timeframe::getWizardSteps()).
 */
(function ($) {
    'use strict';

    $(function () {
        const config = window.cb_timeframe_wizard;

        // Only run when the wizard is configured and active (new-timeframe screen).
        if (!config || !config.active || !Array.isArray(config.steps) || !config.steps.length) {
            return;
        }

        const $metabox = $('#' + config.metaboxId);
        if (!$metabox.length || $metabox.data('cbWizardInit')) {
            return;
        }
        $metabox.data('cbWizardInit', true);

        const i18n = config.i18n || {};
        const sprintf = function (template, a, b) {
            return String(template).replace('%1$d', a).replace('%2$d', b);
        };

        /**
         * Resolve the `.cmb-row` element for a given CMB2 field id.
         *
         * Primary strategy uses the `cmb2-id-<id>` class CMB2 adds to every row
         * (underscores become hyphens). Falls back to an element whose DOM id
         * equals the field id (true for most input-bearing fields).
         *
         * @param {string} fieldId
         * @returns {HTMLElement|null}
         */
        const findRow = function (fieldId) {
            const cssId = fieldId.replace(/_/g, '-');
            let row = $metabox[0].querySelector('.cmb2-id-' + cssId);
            if (row) {
                return row.classList.contains('cmb-row') ? row : row.closest('.cmb-row');
            }
            const byId = $metabox[0].querySelector(
                '#' + (window.CSS && CSS.escape ? CSS.escape(fieldId) : fieldId),
            );
            return byId ? byId.closest('.cmb-row') : null;
        };

        // Build the wizard shell.
        const $wizard = $('<div class="cb-wizard" />');
        const $progress = $('<ol class="cb-wizard__progress" />');
        const $panels = $('<div class="cb-wizard__panels" />');

        const stepPanels = [];
        const claimed = new Set();

        config.steps.forEach(function (step, index) {
            const $panel = $('<section class="cb-wizard__step" />').attr('data-cb-step', index);
            $panel.append(
                $('<header class="cb-wizard__step-header" />')
                    .append($('<h3 class="cb-wizard__step-title" />').text(step.label || ''))
                    .append(
                        step.desc ? $('<p class="cb-wizard__step-desc" />').text(step.desc) : '',
                    ),
            );

            (step.fields || []).forEach(function (fieldId) {
                const row = findRow(fieldId);
                if (row && !claimed.has(row)) {
                    claimed.add(row);
                    $panel.append(row); // moves the row into the panel
                }
            });

            stepPanels.push($panel);
            $panels.append($panel);

            // Progress indicator (clickable).
            const $dot = $('<li class="cb-wizard__progress-item" />')
                .attr('data-cb-goto', index)
                .append($('<span class="cb-wizard__progress-num" />').text(index + 1))
                .append($('<span class="cb-wizard__progress-label" />').text(step.label || ''));
            $progress.append($dot);
        });

        // Catch-all: any field not assigned to a step goes into the last panel,
        // so nothing can ever be hidden by accident.
        const lastPanel = stepPanels[stepPanels.length - 1];
        $metabox.children('.cmb-row').each(function () {
            if (!claimed.has(this)) {
                claimed.add(this);
                lastPanel.append(this);
            }
        });

        // Navigation controls.
        const $nav = $('<div class="cb-wizard__nav" />');
        const $back = $('<button type="button" class="button cb-wizard__back" />').text(
            i18n.back || 'Back',
        );
        const $next = $(
            '<button type="button" class="button button-primary cb-wizard__next" />',
        ).text(i18n.next || 'Next');
        const $status = $('<span class="cb-wizard__status" />');
        const $finish = $('<span class="cb-wizard__finish" />').text(i18n.finish || '');
        $nav.append($back, $next, $status, $finish);

        // Expert toggle.
        const $expertToggle = $(
            '<button type="button" class="button-link cb-wizard__expert-toggle" />',
        ).text(i18n.expert || 'Expert view');

        $wizard.append(
            $('<div class="cb-wizard__topbar" />').append($progress).append($expertToggle),
        );
        $wizard.append($panels);
        $wizard.append($nav);

        // Insert the wizard at the top of the metabox and render.
        $metabox.prepend($wizard);

        let current = 0;
        const total = stepPanels.length;
        let expert = false;

        const render = function () {
            stepPanels.forEach(function ($panel, index) {
                $panel.toggleClass('is-active', index === current);
            });
            $progress.children().each(function (index) {
                $(this)
                    .toggleClass('is-current', index === current)
                    .toggleClass('is-done', index < current);
            });
            $back.prop('disabled', current === 0);
            const onLast = current === total - 1;
            $next.toggle(!onLast);
            $finish.toggle(onLast);
            $status.text(sprintf(i18n.stepOf || 'Step %1$d of %2$d', current + 1, total));
        };

        const goTo = function (index) {
            current = Math.max(0, Math.min(total - 1, index));
            render();
        };

        $next.on('click', function () {
            goTo(current + 1);
        });
        $back.on('click', function () {
            goTo(current - 1);
        });
        $progress.on('click', '[data-cb-goto]', function () {
            if (!expert) {
                goTo(parseInt($(this).attr('data-cb-goto'), 10));
            }
        });

        $expertToggle.on('click', function () {
            expert = !expert;
            $wizard.toggleClass('cb-wizard--expert', expert);
            $expertToggle.text(
                expert ? i18n.guided || 'Guided view' : i18n.expert || 'Expert view',
            );
            if (!expert) {
                render();
            }
        });

        render();
    });
})(jQuery);
