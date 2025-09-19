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
        $steps = array();
		
		if ( $automation_id ) {
			$automation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automations WHERE automation_id = %d", $automation_id ) );
			if( $automation ) {
                $trigger_id_selected = $automation->trigger_id;
				$trigger_settings = maybe_unserialize( $automation->trigger_settings );
			}
            $raw_steps = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d ORDER BY step_order ASC", $automation_id ) );
            foreach ($raw_steps as $step) {
                $step->step_settings = maybe_unserialize($step->step_settings);
                $steps[] = $step;
            }
		}

        $definitions = $this->plugin->automations->get_definitions();
        
		$js_data = array(
			'steps'      => $steps,
            'definitions' => $definitions,
            'saved_trigger_id' => $trigger_id_selected,
            'saved_trigger_settings' => $trigger_settings,
			'i18n'       => [
				'removeStep'    => __( 'Remover Passo', 'bluesendmail' ),
                'sendCampaign'  => __( 'Enviar Campanha', 'bluesendmail' ),
                'wait'          => __( 'Esperar (Delay)', 'bluesendmail' ),
                'selectCampaign'=> __( 'Selecione uma Campanha...', 'bluesendmail' ),
                'minute'        => __( 'Minuto(s)', 'bluesendmail' ),
                'hour'          => __( 'Hora(s)', 'bluesendmail' ),
                'day'           => __( 'Dia(s)', 'bluesendmail' ),
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
                                <td><input type="text" name="name" id="bsm-name" class="large-text" value="<?php echo esc_attr( $automation->name ?? '' ); ?>" required></td>
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
                                    </select>
                                    <div id="bsm-trigger-settings-container" style="margin-top: 15px; min-height: 40px;">
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

				<div id="bsm-workflow-builder" class="bsm-card" style="margin-top: 24px;">
					<h2 class="bsm-card-title"><?php _e( 'Fluxo de Trabalho', 'bluesendmail' ); ?></h2>
					<div id="bsm-workflow-container" class="bsm-step-container">
					</div>
                    <div class="bsm-add-step-container">
                        <button type="button" class="button bsm-add-action-btn"><span class="dashicons dashicons-plus"></span> <?php _e('Adicionar Ação de Envio', 'bluesendmail'); ?></button>
                        <button type="button" class="button bsm-add-delay-btn"><span class="dashicons dashicons-plus"></span> <?php _e('Adicionar Espera', 'bluesendmail'); ?></button>
                    </div>
				</div>

                <div class="submit" style="padding-top: 20px;">
                    <?php submit_button( $automation ? __( 'Salvar Alterações', 'bluesendmail' ) : __( 'Criar Automação', 'bluesendmail' ), 'primary', 'bsm_save_automation', false ); ?>
                </div>
            </form>
        </div>

		<script type="text/html" id="tmpl-bsm-step-card">
			<div class="bsm-workflow-step">
				<input type="hidden" class="bsm-step-field bsm-step-type-input" data-field-name="type" value="">
				<div class="bsm-step-header">
					<span class="bsm-step-drag-handle dashicons dashicons-menu"></span>
					<strong class="bsm-step-title"></strong>
					<button type="button" class="bsm-step-remove dashicons dashicons-no-alt" title="<?php esc_attr_e('Remover Passo', 'bluesendmail'); ?>"></button>
				</div>
				<div class="bsm-step-body">
                    <div class="bsm-step-content-action" style="display: none;">
						<p class="description" style="margin-bottom: 8px;"><?php _e('Selecione a campanha a ser enviada.', 'bluesendmail'); ?></p>
						<select class="bsm-step-field bsm-campaign-select-action" data-field-name="campaign_id" style="width: 100%;"></select>
					</div>
					<div class="bsm-step-content-delay" style="display: none;">
						<p class="description" style="margin-bottom: 8px;"><?php _e('Aguardar por um período antes de executar o próximo passo.', 'bluesendmail'); ?></p>
						<input type="number" class="bsm-step-field bsm-delay-value" data-field-name="value" min="1" value="1" style="width: 80px;">
						<select class="bsm-step-field bsm-delay-unit" data-field-name="unit">
							<option value="minute"><?php _e('Minuto(s)', 'bluesendmail'); ?></option>
							<option value="hour"><?php _e('Hora(s)', 'bluesendmail'); ?></option>
							<option value="day" selected><?php _e('Dia(s)', 'bluesendmail'); ?></option>
						</select>
					</div>
				</div>
			</div>
		</script>
		<?php
	}
}

