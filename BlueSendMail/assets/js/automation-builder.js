jQuery(function($) {
    'use strict';

    if (typeof bsmBuilderData === 'undefined') {
        return;
    }

    const app = {
        init: function() {
            this.populateTriggerSelector();
            this.renderInitialWorkflow();
            this.bindGlobalEvents();
            this.makeSortable();
        },

        populateTriggerSelector: function() {
            const $select = $('#bsm-trigger-id');
            const triggers = bsmBuilderData.definitions.triggers;
            
            for (const group in triggers) {
                const $optgroup = $(`<optgroup label="${group}"></optgroup>`);
                triggers[group].forEach(trigger => {
                    $optgroup.append(`<option value="${trigger.id}">${trigger.name}</option>`);
                });
                $select.append($optgroup);
            }
            
            if (bsmBuilderData.saved_trigger_id) {
                $select.val(bsmBuilderData.saved_trigger_id);
            }
        },

        renderInitialWorkflow: function() {
            const $mainContainer = $('#bsm-workflow-container');
            $mainContainer.empty();
            
            bsmBuilderData.steps.forEach(step => {
                const $stepEl = this.createStepElement(step.step_type, step.step_settings);
                $mainContainer.append($stepEl);
            });
            this.reindexAll();
        },

        createStepElement: function(type, settings) {
            const template = $('#tmpl-bsm-step-card').html();
            const $step = $(template);
            const i18n = bsmBuilderData.i18n;

            $step.find('.bsm-step-type-input').val(type);

            if (type === 'send_campaign') {
                $step.find('.bsm-step-title').text(i18n.sendCampaign);
                $step.find('.bsm-step-content-action').show();
                this.populateCampaignSelect($step.find('.bsm-campaign-select-action'), settings.campaign_id);
            } else if (type === 'delay') {
                $step.find('.bsm-step-title').text(i18n.wait);
                $step.find('.bsm-step-content-delay').show();
                if (settings.value) $step.find('.bsm-delay-value').val(settings.value);
                if (settings.unit) $step.find('.bsm-delay-unit').val(settings.unit);
            }
            return $step;
        },

        populateCampaignSelect: function($select, selectedId) {
            const campaigns = bsmBuilderData.definitions.campaigns || [];
            $select.append(`<option value="">${bsmBuilderData.i18n.selectCampaign}</option>`);
            campaigns.forEach(campaign => {
                const selected = campaign.id == selectedId ? 'selected' : '';
                $select.append(`<option value="${campaign.id}" ${selected}>${campaign.name}</option>`);
            });
        },
        
        loadTriggerFields: function(triggerId) {
            const $container = $('#bsm-trigger-settings-container');
            $container.html('<span class="spinner is-active"></span>');

            $.post(bsm_admin_data.ajax_url, {
                action: 'bsm_get_trigger_fields',
                nonce: bsm_admin_data.nonce,
                trigger_id: triggerId
            }, response => {
                if (response.success) {
                    $container.html(response.data.html);
                    if (bsmBuilderData.saved_trigger_settings) {
                        for(const key in bsmBuilderData.saved_trigger_settings) {
                            $container.find(`[name="trigger_settings[${key}]"]`).val(bsmBuilderData.saved_trigger_settings[key]);
                        }
                    }
                } else {
                    $container.html('<p style="color:red;">Erro ao carregar campos.</p>');
                }
            });
        },

        bindGlobalEvents: function() {
            const initialTriggerId = $('#bsm-trigger-id').val();
            if(initialTriggerId) {
                this.loadTriggerFields(initialTriggerId);
            }

            $('#bsm-trigger-id').on('change', e => {
                const triggerId = $(e.currentTarget).val();
                if(triggerId) this.loadTriggerFields(triggerId);
                else $('#bsm-trigger-settings-container').empty();
            });

            $('.bsm-add-action-btn').on('click', () => {
                const $stepEl = this.createStepElement('send_campaign', {});
                $('#bsm-workflow-container').append($stepEl);
                this.reindexAll();
            });

             $('.bsm-add-delay-btn').on('click', () => {
                const $stepEl = this.createStepElement('delay', {});
                $('#bsm-workflow-container').append($stepEl);
                this.reindexAll();
            });

            $('#bsm-workflow-container').on('click', '.bsm-step-remove', e => {
                $(e.currentTarget).closest('.bsm-workflow-step').fadeOut(300, function() {
                    $(this).remove();
                    app.reindexAll();
                });
            });
        },

        makeSortable: function() {
            $('#bsm-workflow-container').sortable({
                handle: '.bsm-step-drag-handle',
                axis: 'y',
                placeholder: 'bsm-step-placeholder',
                update: () => this.reindexAll()
            }).disableSelection();
        },

        reindexAll: function() {
            $('#bsm-workflow-container .bsm-workflow-step').each((index, step) => {
                const $step = $(step);
                const newNamePrefix = `steps[${index}]`;

                $step.find('.bsm-step-field').each((i, field) => {
                    const $field = $(field);
                    const fieldName = $field.data('field-name');
                    $field.attr('name', `${newNamePrefix}[${fieldName}]`);
                });
            });
        }
    };

    app.init();
});

