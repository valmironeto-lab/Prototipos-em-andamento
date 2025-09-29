<?php
/**
 * Gerencia a renderização da página de Automações.
 *
 * @package BlueSendMail
 * @version 2.3.0
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
        $automations_table = new BlueSendMail_Automations_List_Table();
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
			$automations_table->prepare_items();
			$automations_table->display();
			?>
		</form>
		<?php
	}

	private function render_add_edit_page() {
		$automation_id = isset( $_GET['automation'] ) ? absint( $_GET['automation'] ) : 0;
        $automation = $automation_id ? $this->plugin->db->get_automation_by_id($automation_id) : null;
		?>
		<div class="wrap bsm-wrap">
            <?php $this->render_header( $automation ? esc_html__( 'Editar Automação', 'bluesendmail' ) : esc_html__( 'Criar Nova Automação', 'bluesendmail' ) ); ?>
            
            <form method="post" id="bsm-automation-form">
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
                                    <div id="bsm-trigger-settings-container" class="bsm-dynamic-fields-container">
                                        <!-- Campos específicos do gatilho serão carregados aqui via AJAX -->
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

				<div id="bsm-workflow-builder" class="bsm-card" style="margin-top: 24px;">
					<h2 class="bsm-card-title"><?php _e( 'Fluxo de Trabalho', 'bluesendmail' ); ?></h2>
					<div id="bsm-workflow-container" class="bsm-step-container">
						<!-- Passos do fluxo de trabalho são renderizados aqui pelo JavaScript -->
					</div>
                    <div class="bsm-add-step-wrapper">
                        <button type="button" class="button" id="bsm-add-step-button"><span class="dashicons dashicons-plus"></span> <?php _e('Adicionar Passo', 'bluesendmail'); ?></button>
                    </div>
				</div>

                <div class="submit" style="padding-top: 20px;">
                    <?php submit_button( $automation ? __( 'Salvar Alterações', 'bluesendmail' ) : __( 'Criar Automação', 'bluesendmail' ), 'primary bsm-btn bsm-btn-primary', 'bsm_save_automation', false ); ?>
                </div>
            </form>
        </div>

        <?php $this->render_js_templates(); ?>
		<?php
	}

    private function render_js_templates() {
        ?>
        <!-- Template para o Modal de Adicionar Passo -->
        <div id="bsm-add-step-modal" class="bsm-modal-backdrop" style="display: none;">
            <div class="bsm-modal-content">
                <div class="bsm-modal-header">
                    <h2><?php _e('Adicionar Novo Passo', 'bluesendmail'); ?></h2>
                    <button type="button" class="bsm-modal-close">&times;</button>
                </div>
                <div class="bsm-modal-body" id="bsm-action-selector-container">
                    <!-- Grupos de ações serão renderizados aqui -->
                </div>
            </div>
        </div>

        <!-- Template para o item de ação no modal -->
        <script type="text/html" id="tmpl-bsm-action-item">
            <div class="bsm-action-item" data-action-id="{{ data.id }}">
                <h4>{{ data.name }}</h4>
            </div>
        </script>

		<!-- Template para o card de passo no fluxo de trabalho -->
		<script type="text/html" id="tmpl-bsm-step-card">
			<div class="bsm-workflow-step" data-step-type="{{ data.type }}">
				<input type="hidden" class="bsm-step-field bsm-step-type-input" data-field-name="action_id" value="{{ data.type }}">
				<div class="bsm-step-header">
					<div>
                        <span class="bsm-step-drag-handle dashicons dashicons-menu"></span>
                        <strong class="bsm-step-title">{{ data.title }}</strong>
                    </div>
                    <button type="button" class="bsm-step-remove dashicons dashicons-no-alt" title="<?php esc_attr_e('Remover Passo', 'bluesendmail'); ?>"></button>
				</div>
				<div class="bsm-step-body bsm-dynamic-fields-container">
                    <!-- Campos específicos da ação serão carregados aqui -->
				</div>
			</div>
		</script>
        <?php
    }
}

