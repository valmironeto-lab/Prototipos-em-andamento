/**
 * BlueSendMail Automation Builder (v2.4.0)
 * Lida com a interatividade da página de criação/edição de automações.
 */
jQuery(function($) {
    'use strict';

    if (typeof bsmBuilderData === 'undefined') {
        console.error('BlueSendMail Error: Dados do construtor de automação não encontrados.');
        return;
    }

    const app = {
        savedAutomationData: bsmBuilderData.saved_automation,

        init: function() {
            this.populateTriggerSelector();
            this.renderInitialState();
            this.bindGlobalEvents();
            this.makeSortable();
        },

        /**
         * Preenche o seletor de gatilhos com os dados localizados.
         */
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
        },
        
        /**
         * Define o estado inicial do formulário ao carregar a página (se for uma edição).
         * Isso inclui selecionar o gatilho, carregar seus campos e preenchê-los com os dados salvos.
         */
        renderInitialState: function() {
            if (!this.savedAutomationData) {
                return;
            }

            // Define o gatilho salvo e carrega seus campos com os dados.
            if (this.savedAutomationData.trigger_id) {
                $('#bsm-trigger-id').val(this.savedAutomationData.trigger_id);
                const $container = $('#bsm-trigger-settings-container');
                this.loadDynamicFields(
                    'trigger', 
                    this.savedAutomationData.trigger_id, 
                    $container, 
                    this.savedAutomationData.trigger_settings
                );
            }

            // Renderiza os passos salvos do fluxo de trabalho.
            if (this.savedAutomationData.steps && this.savedAutomationData.steps.length > 0) {
                this.savedAutomationData.steps.forEach(step => {
                    this.addStepToDOM(step.action_id, step.step_settings);
                });
            }
        },

        /**
         * Carrega dinamicamente os campos de configuração para um gatilho ou ação via AJAX.
         * @param {string} componentType - 'trigger' ou 'action'.
         * @param {string} componentId - O ID do componente (ex: 'contact_added_to_list').
         * @param {jQuery} $container - O elemento jQuery onde os campos serão inseridos.
         * @param {object} savedSettings - As configurações salvas para preencher os campos.
         */
        loadDynamicFields: function(componentType, componentId, $container, savedSettings = {}) {
            $container.html('<span class="spinner is-active" style="float: left; margin-top: 5px;"></span>');

            $.post(bsmBuilderData.ajax_url, {
                action: 'bsm_get_component_fields',
                nonce: bsmBuilderData.nonce,
                component_type: componentType,
                component_id: componentId
            }, response => {
                if (response.success) {
                    $container.html(response.data.html);
                    // Preenche os campos com os valores salvos
                    if (savedSettings && typeof savedSettings === 'object' && Object.keys(savedSettings).length > 0) {
                        for (const key in savedSettings) {
                            if (Object.prototype.hasOwnProperty.call(savedSettings, key)) {
                                $container.find(`[name$="[${key}]"]`).val(savedSettings[key]);
                            }
                        }
                    }
                } else {
                    $container.html(`<p style="color:red;">${response.data.message || 'Erro ao carregar campos.'}</p>`);
                }
            }).fail(() => {
                $container.html('<p style="color:red;">Erro de comunicação com o servidor.</p>');
            });
        },

        /**
         * Associa todos os eventos de clique, mudança, etc., aos elementos da página.
         */
        bindGlobalEvents: function() {
            const $body = $('body');

            // Evento para quando o usuário MUDA o gatilho manualmente.
            $('#bsm-trigger-id').on('change', e => {
                const triggerId = $(e.currentTarget).val();
                const $container = $('#bsm-trigger-settings-container');
                
                if (!triggerId) {
                    $container.empty();
                    return;
                }
                
                let settingsToLoad = {};
                // Se o usuário selecionar o gatilho que estava salvo originalmente, recarregamos seus dados.
                if (this.savedAutomationData && this.savedAutomationData.trigger_id === triggerId) {
                    settingsToLoad = this.savedAutomationData.trigger_settings;
                }
                
                this.loadDynamicFields('trigger', triggerId, $container, settingsToLoad);
            });

            // Eventos do modal e dos passos (sem alterações)
            $('#bsm-add-step-button').on('click', () => this.showAddStepModal());
            $body.on('click', '.bsm-modal-close, .bsm-modal-backdrop', (e) => {
                 if ($(e.target).is('.bsm-modal-close, .bsm-modal-backdrop')) {
                    this.hideAddStepModal();
                }
            });

            $body.on('click', '.bsm-action-item', e => {
                const actionId = $(e.currentTarget).data('action-id');
                this.addStepToDOM(actionId);
                this.hideAddStepModal();
            });

            $('#bsm-workflow-container').on('click', '.bsm-step-remove', e => {
                $(e.currentTarget).closest('.bsm-workflow-step').fadeOut(300, function() {
                    $(this).remove();
                    app.reindexAllSteps();
                });
            });
        },

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
            $('#bsm-add-step-modal').fadeOut(200);
        },

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
            $('#bsm-workflow-container').sortable({
                handle: '.bsm-step-drag-handle',
                axis: 'y',
                placeholder: 'bsm-step-placeholder',
                update: () => this.reindexAllSteps()
            }).disableSelection();
        },

        reindexAllSteps: function() {
            $('#bsm-workflow-container .bsm-workflow-step').each((index, step) => {
                const $step = $(step);
                const newNamePrefix = `steps[${index}]`;

                $step.find('.bsm-component-field').each((i, field) => {
                    const $field = $(field);
                    const fieldNameMatch = $field.attr('name').match(/\[([^\][]*)]$/);
                    if (fieldNameMatch && fieldNameMatch[1]) {
                        const fieldName = fieldNameMatch[1];
                        $field.attr('name', `${newNamePrefix}[settings][${fieldName}]`);
                    }
                });

                $step.find('.bsm-step-type-input').attr('name', `${newNamePrefix}[action_id]`);
            });
        }
    };

    app.init();
});
