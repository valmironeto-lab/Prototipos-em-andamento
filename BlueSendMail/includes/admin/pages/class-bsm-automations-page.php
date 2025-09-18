<?php
/**
 * Gerencia a renderização da página de Automações.
 *
 * @package BlueSendMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Automations_Page extends BSM_Admin_Page {

    public function render() {
        echo '<div class="wrap bsm-wrap">';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

        if ( 'new' === $action || ( 'edit' === $action && ! empty( $_GET['automation'] ) ) ) {
            $this->render_add_edit_page();
        } else {
            $this->render_list_page();
        }
        echo '</div>';
    }

    private function render_list_page() {
        ?>
        <div class="wrap bsm-wrap">
            <?php
            $this->render_header(
                __( 'Automações', 'bluesendmail' ),
                array(
                    'url'   => admin_url( 'admin.php?page=bluesendmail-automations&action=new' ),
                    'label' => __( 'Criar Nova Automação', 'bluesendmail' ),
                    'icon'  => 'dashicons-plus',
                )
            );
            ?>
            <form method="post">
                <?php
                $automations_table = new BlueSendMail_Automations_List_Table();
                $automations_table->prepare_items();
                $automations_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    private function render_add_edit_page() {
        global $wpdb;
        $automation_id = isset( $_GET['automation'] ) ? absint( $_GET['automation'] ) : 0;
        $automation = null;
        $trigger_id_selected = '';
        $trigger_settings = array();
        $steps_tree = array();

        if ( $automation_id ) {
            $automation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automations WHERE automation_id = %d", $automation_id ) );
            if ( $automation ) {
                $trigger_id_selected = $automation->trigger_id;
                $trigger_settings = maybe_unserialize( $automation->trigger_settings );
            }
            $steps_tree = $this->plugin->automations->get_automation_steps_tree( $automation_id );
        }

        $all_triggers = $this->plugin->automations->get_triggers();
        $all_actions = $this->plugin->automations->get_actions();

        $js_data = array(
            'steps_tree' => $steps_tree,
            'actions'    => array_values(array_map(function($action){
                return [
                    'id' => $action->id, 
                    'name' => $action->name, 
                    'group' => $action->group,
                    'is_condition' => $action->is_condition,
                    'fields' => $action->get_fields()
                ];
            }, $all_actions)),
            'i18n'       => [
                'add_step' => __( '+ Adicionar Passo', 'bluesendmail' ),
                'yes'      => __( 'Sim', 'bluesendmail' ),
                'no'       => __( 'Não', 'bluesendmail' ),
            ],
        );
        wp_localize_script('bsm-automation-builder', 'bsmBuilderData', $js_data);
        ?>
        <div class="wrap bsm-wrap">
            <?php $this->render_header( $automation ? esc_html__( 'Editar Automação', 'bluesendmail' ) : esc_html__( 'Criar Nova Automação', 'bluesendmail' ) ); ?>
            
            <form method="post">
                <?php wp_nonce_field( 'bsm_save_automation_nonce_action', 'bsm_save_automation_nonce_field' ); ?>
                <input type="hidden" name="automation_id" value="<?php echo esc_attr( $automation_id ); ?>">

                <div class="bsm-card">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="bsm-name"><?php _e( 'Nome da Automação', 'bluesendmail' ); ?></label></th>
                                <td><input type="text" name="name" id="bsm-name" class="large-text" value="<?php echo esc_attr( $automation->name ?? '' ); ?>" required>
                                <p class="description"><?php _e( 'Para sua referência interna.', 'bluesendmail' ); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bsm-status"><?php _e( 'Status', 'bluesendmail' ); ?></label></th>
                                <td>
                                    <select name="status" id="bsm-status">
                                        <option value="active" <?php selected( $automation->status ?? 'inactive', 'active' ); ?>><?php _e( 'Ativo', 'bluesendmail' ); ?></option>
                                        <option value="inactive" <?php selected( $automation->status ?? 'inactive', 'inactive' ); ?>><?php _e( 'Inativo', 'bluesendmail' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bsm-trigger-id"><?php _e( 'Gatilho', 'bluesendmail' ); ?></label></th>
                                <td>
                                    <select name="trigger_id" id="bsm-trigger-id" required>
                                        <option value=""><?php _e( 'Selecione um gatilho...', 'bluesendmail' ); ?></option>
                                        <?php
                                        $grouped_triggers = [];
                                        foreach ($all_triggers as $trigger) {
                                            $grouped_triggers[$trigger->group][] = $trigger;
                                        }
                                        foreach ($grouped_triggers as $group => $triggers) : ?>
                                            <optgroup label="<?php echo esc_attr($group); ?>">
                                                <?php foreach ($triggers as $trigger) : ?>
                                                    <option value="<?php echo esc_attr($trigger->id); ?>" <?php selected($trigger_id_selected, $trigger->id); ?> data-fields='<?php echo esc_attr(json_encode($trigger->get_fields())); ?>'>
                                                        <?php echo esc_html($trigger->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="bsm-trigger-settings-container" style="margin-top: 15px;">
                                        <!-- Os campos do gatilho serão inseridos aqui via JS -->
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                var triggerSelect = document.getElementById('bsm-trigger-id');
                                                var savedSettings = <?php echo json_encode($trigger_settings); ?>;
                                                
                                                function populateTriggerFields() {
                                                    if (triggerSelect.value) {
                                                        var event = new Event('change');
                                                        triggerSelect.dispatchEvent(event);
                                                        
                                                        setTimeout(function() {
                                                            for (var key in savedSettings) {
                                                                var field = document.querySelector('[name="trigger_settings[' + key + ']"]');
                                                                if (field) {
                                                                    field.value = savedSettings[key];
                                                                }
                                                            }
                                                        }, 100);
                                                    }
                                                }
                                                
                                                if (triggerSelect.value) {
                                                   populateTriggerFields();
                                                }
                                            });
                                        </script>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="bsm-workflow-builder" class="bsm-card" style="margin-top: 24px;">
                    <h2 class="bsm-card-title"><?php _e( 'Fluxo de Trabalho', 'bluesendmail' ); ?></h2>
                    <p class="description"><?php _e( 'Adicione e reordene as ações que serão executadas quando o gatilho for disparado.', 'bluesendmail' ); ?></p>
                    <div id="bsm-workflow-container" class="bsm-step-container">
                        <!-- O construtor de fluxo será renderizado aqui pelo JavaScript -->
                    </div>
                </div>

                <div class="submit" style="padding-top: 20px;">
                    <?php submit_button( $automation ? __( 'Salvar Alterações', 'bluesendmail' ) : __( 'Criar Automação', 'bluesendmail' ), 'primary bsm-btn bsm-btn-primary', 'bsm_save_automation', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

