jQuery(function($) {
    'use strict';

    if (typeof bsmBuilderData === 'undefined') {
        console.error('BlueSendMail Error: Automation builder data is missing.');
        return;
    }

    const app = {
        init: function() {
            this.renderInitialWorkflow();
            this.bindGlobalEvents();
            this.initTriggerFields();
        },

        renderInitialWorkflow: function() {
            const $mainContainer = $('#bsm-workflow-container');
            $mainContainer.empty();
            this.renderSteps(bsmBuilderData.steps_tree || [], $mainContainer, 'steps', 0, null);
            this.appendAddButtons($mainContainer);
            this.makeAllSortable();
        },

        renderSteps: function(steps, $container, namePrefix, parentId, branch) {
            steps.forEach((step, index) => {
                const settings = step.step_settings || {};
                const $stepEl = this.createStepElement(step.action_id, settings);
                $container.append($stepEl);
        
                const action = bsmBuilderData.actions.find(a => a.id === step.action_id);
                if (action && action.is_condition) {
                    const $yesContainer = $stepEl.find('.bsm-branch-container[data-branch="yes"]');
                    const $noContainer = $stepEl.find('.bsm-branch-container[data-branch="no"]');
                    
                    this.renderSteps(step.yes_branch || [], $yesContainer, `steps[${index}][yes_branch]`, step.step_id, 'yes');
                    this.renderSteps(step.no_branch || [], $noContainer, `steps[${index}][no_branch]`, step.step_id, 'no');
        
                    this.appendAddButtons($yesContainer);
                    this.appendAddButtons($noContainer);
                }
            });
        },

        createStepElement: function(actionId, settings) {
            const action = bsmBuilderData.actions.find(a => a.id === actionId);
            if (!action) return $();

            const $step = $(`
                <div class="bsm-workflow-step" data-action-id="${action.id}">
                    <div class="bsm-step-header">
                        <span class="bsm-step-drag-handle dashicons dashicons-menu"></span>
                        <strong class="bsm-step-title">${action.name}</strong>
                        <button type="button" class="bsm-step-remove dashicons dashicons-no-alt"></button>
                    </div>
                    <div class="bsm-step-body"></div>
                </div>
            `);

            action.fields.forEach(field => {
                let inputHtml = '';
                const value = settings[field.id] || field.default || '';

                if (field.type === 'select') {
                    const options = Object.entries(field.options).map(([val, label]) =>
                        `<option value="${val}" ${val == value ? 'selected' : ''}>${label}</option>`
                    ).join('');
                    inputHtml = `<select class="bsm-step-field" data-field-name="${field.id}">${options}</select>`;
                } else {
                    inputHtml = `<input type="${field.type}" class="bsm-step-field" data-field-name="${field.id}" value="${value}" placeholder="${field.placeholder || ''}">`;
                }

                $step.find('.bsm-step-body').append(`
                    <div class="bsm-step-field-group">
                        <label>${field.label}</label>
                        ${inputHtml}
                    </div>
                `);
            });

            if (action.is_condition) {
                $step.append(`
                    <div class="bsm-step-branches">
                        <div class="bsm-branch-col">
                            <div class="bsm-branch-header bsm-branch-yes">${bsmBuilderData.i18n.yes}</div>
                            <div class="bsm-branch-container bsm-step-container" data-branch="yes"></div>
                        </div>
                        <div class="bsm-branch-col">
                            <div class="bsm-branch-header bsm-branch-no">${bsmBuilderData.i18n.no}</div>
                            <div class="bsm-branch-container bsm-step-container" data-branch="no"></div>
                        </div>
                    </div>
                `);
            }

            return $step;
        },

        appendAddButtons: function($container) {
            if ($container.children('.bsm-add-step-container').length > 0) return;
        
            const groupedActions = bsmBuilderData.actions.reduce((acc, action) => {
                acc[action.group] = acc[action.group] || [];
                acc[action.group].push(action);
                return acc;
            }, {});
        
            let optionsHtml = '';
            for (const group in groupedActions) {
                optionsHtml += `<optgroup label="${group}">`;
                groupedActions[group].forEach(action => {
                    optionsHtml += `<option value="${action.id}">${action.name}</option>`;
                });
                optionsHtml += `</optgroup>`;
            }
        
            const buttonsHtml = `
                <div class="bsm-add-step-container">
                     <div class="bsm-add-step-line"></div>
                     <div class="bsm-add-step-menu">
                        <select class="bsm-action-select">
                            <option value="">${bsmBuilderData.i18n.add_step}</option>
                            ${optionsHtml}
                        </select>
                    </div>
                </div>`;
            $container.append(buttonsHtml);
        },

        bindGlobalEvents: function() {
            $(document).on('change', '.bsm-action-select', e => this.addStepHandler(e.currentTarget));
            $(document).on('click', '.bsm-step-remove', e => {
                $(e.currentTarget).closest('.bsm-workflow-step').fadeOut(300, function() {
                    $(this).remove();
                    app.reindexAll();
                });
            });
        },

        addStepHandler: function(select) {
            const $select = $(select);
            const actionId = $select.val();
            if (!actionId) return;

            const $stepEl = this.createStepElement(actionId, {});
            $select.closest('.bsm-add-step-container').before($stepEl);
            $select.val(''); // Reset select
            this.makeAllSortable();
            this.reindexAll();
        },

        makeAllSortable: function() {
            $('.bsm-step-container').sortable({
                handle: '.bsm-step-drag-handle',
                items: '> .bsm-workflow-step',
                connectWith: '.bsm-step-container',
                axis: 'y',
                placeholder: 'bsm-step-placeholder',
                forcePlaceholderSize: true,
                start: (event, ui) => ui.placeholder.height(ui.item.height()),
                stop: () => this.reindexAll()
            }).disableSelection();
        },

        reindexAll: function() {
            this.recursiveReindex($('#bsm-workflow-container'), 'steps');
        },

        recursiveReindex: function($container, namePrefix) {
            $container.children('.bsm-workflow-step').each((index, step) => {
                const $step = $(step);
                const newNamePrefix = `${namePrefix}[${index}]`;

                $step.find('.bsm-step-field').each((i, field) => {
                    const $field = $(field);
                    const fieldName = $field.data('field-name');
                    $field.attr('name', `${newNamePrefix}[settings][${fieldName}]`);
                });
                 $step.append(`<input type="hidden" name="${newNamePrefix}[action_id]" value="${$step.data('action-id')}">`);

                const action = bsmBuilderData.actions.find(a => a.id === $step.data('action-id'));
                if (action && action.is_condition) {
                    this.recursiveReindex($step.find('.bsm-branch-container[data-branch="yes"]'), `${newNamePrefix}[yes_branch]`);
                    this.recursiveReindex($step.find('.bsm-branch-container[data-branch="no"]'), `${newNamePrefix}[no_branch]`);
                }
            });
        },

        initTriggerFields: function() {
            $('#bsm-trigger-id').on('change', function() {
                const $selectedOption = $(this).find('option:selected');
                const fields = $selectedOption.data('fields');
                const $container = $('#bsm-trigger-settings-container');
                $container.empty();

                if (fields && Array.isArray(fields)) {
                    fields.forEach(field => {
                        let inputHtml = '';
                        if (field.type === 'select') {
                            const options = Object.entries(field.options).map(([val, label]) =>
                                `<option value="${val}">${label}</option>`
                            ).join('');
                            inputHtml = `<select name="trigger_settings[${field.id}]">${options}</select>`;
                        } else {
                            inputHtml = `<input type="${field.type}" name="trigger_settings[${field.id}]" class="regular-text" placeholder="${field.placeholder || ''}">`;
                        }
                        $container.append(`<div class="bsm-trigger-field-group"><label>${field.label}</label><br>${inputHtml}</div>`);
                    });
                }
            }).trigger('change');
        }
    };

    app.init();
});

