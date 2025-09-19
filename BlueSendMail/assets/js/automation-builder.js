/**
 * BlueSendMail Automation Builder (v2.2.4)
 * Lida com a interatividade da página de criação/edição de automações.
 */
jQuery(function($) {
    'use strict';
// ... código existente ...
        populateTriggerSelector: function() {
            const $select = $('#bsm-trigger-id');
            const triggers = bsmBuilderData.definitions.triggers || {};

            for (const group in triggers) {
                const $optgroup = $(`<optgroup label="${group}"></optgroup>`);
                triggers[group].forEach(trigger => {
                    $optgroup.append(`<option value="${trigger.id}">${trigger.name}</option>`);
                });
                $select.append($optgroup);
            }
            
            if (bsmBuilderData.saved_trigger_id) {
                $select.val(bsmBuilderData.saved_trigger_id).trigger('change');
            }
        },

        renderInitialWorkflow: function() {
            if (bsmBuilderData.saved_steps && bsmBuilderData.saved_steps.length > 0) {
                bsmBuilderData.saved_steps.forEach(step => {
                    this.addStepToDOM(step.action_id, step.step_settings);
                });
            }
        },

        loadDynamicFields: function(componentType, componentId, $container, savedSettings = {}) {
// ... código existente ...
            $('#bsm-trigger-id').on('change', e => {
                const triggerId = $(e.currentTarget).val();
                const $container = $('#bsm-trigger-settings-container');
                if (triggerId) {
                    let settings = {};
                    if(bsmBuilderData.saved_trigger_settings && Object.keys(bsmBuilderData.saved_trigger_settings).length > 0){
                        settings = bsmBuilderData.saved_trigger_settings;
                        bsmBuilderData.saved_trigger_settings = {}; // Limpa para não usar novamente
                    }
                    this.loadDynamicFields('trigger', triggerId, $container, settings);
                } else {
                    $container.empty();
                }
            });

            $('#bsm-add-step-button').on('click', () => this.showAddStepModal());
            $body.on('click', '.bsm-modal-close, .bsm-modal-backdrop', (e) => {
// ... código existente ...
        showAddStepModal: function() {
            const $container = $('#bsm-action-selector-container');
            $container.empty();

            const actions = bsmBuilderData.definitions.actions || {};
            const actionItemTemplate = wp.template('bsm-action-item');

            for (const group in actions) {
                $container.append(`<h3>${group}</h3>`);
                const $groupContainer = $('<div class="bsm-action-group"></div>');
                actions[group].forEach(action => {
                    $groupContainer.append(actionItemTemplate(action));
                });
                $container.append($groupContainer);
            }
            $('#bsm-add-step-modal').fadeIn(200);
        },

        hideAddStepModal: function() {
// ... código existente ...
        addStepToDOM: function(actionId, savedSettings = {}) {
            const actions = bsmBuilderData.definitions.actions;
            let actionInfo = null;
            for (const group in actions) {
                const found = actions[group].find(a => a.id === actionId);
                if (found) { actionInfo = found; break; }
            }
            if (!actionInfo) { return; }

            const template = wp.template('bsm-step-card');
            const stepHtml = template({type: actionId, title: actionInfo.name});

            const $stepEl = $(stepHtml);
            $('#bsm-workflow-container').append($stepEl);
            
            const $fieldsContainer = $stepEl.find('.bsm-dynamic-fields-container');
            this.loadDynamicFields('action', actionId, $fieldsContainer, savedSettings);

            this.reindexAllSteps();
        },

        makeSortable: function() {
// ... código existente ...

