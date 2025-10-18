<?php
/**
 * Gerencia o processamento de ações de Configurações.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-settings' === $page && isset( $_POST['bsm_send_test_email'] ) ) {
            $this->handle_send_test_email();
        }
    }

    private function handle_send_test_email() {
        if ( ! isset( $_POST['bsm_send_test_email_nonce'] ) || ! wp_verify_nonce( $_POST['bsm_send_test_email_nonce'], 'bsm_send_test_email_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_settings' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
        
        $recipient = sanitize_email( $_POST['bsm_test_email_recipient'] );
        if ( ! is_email( $recipient ) ) {
            set_transient( 'bsm_test_email_result', array( 'success' => false, 'message' => __( 'O endereço de e-mail fornecido é inválido.', 'bluesendmail' ) ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-settings' ) );
            exit;
        }

        $subject = '[' . get_bloginfo( 'name' ) . '] ' . __( 'E-mail de Teste do BlueSendMail', 'bluesendmail' );
		$body    = '<h1>🎉 ' . __( 'Teste de Envio Bem-sucedido!', 'bluesendmail' ) . '</h1><p>' . __( 'Se você está recebendo este e-mail, suas configurações de envio estão funcionando corretamente.', 'bluesendmail' ) . '</p>';
		$mock_contact = (object) array( 'email' => $recipient, 'first_name' => 'Teste', 'last_name' => 'Usuário' );
		$result = $this->plugin->send_email( $recipient, $subject, $body, $mock_contact, 0 );

		if ( $result ) {
			set_transient( 'bsm_test_email_result', array( 'success' => true, 'message' => __( 'E-mail de teste enviado com sucesso!', 'bluesendmail' ) ), 30 );
			$this->plugin->log_event( 'success', 'test_email', "E-mail de teste enviado para {$recipient}." );
		} else {
			$message = __( 'Falha ao enviar o e-mail de teste.', 'bluesendmail' );
			if ( ! empty( $this->plugin->mail_error ) ) {
                $message .= '<br><strong>' . __( 'Erro retornado:', 'bluesendmail' ) . '</strong> <pre>' . esc_html( $this->plugin->mail_error ) . '</pre>';
            }
			set_transient( 'bsm_test_email_result', array( 'success' => false, 'message' => $message ), 30 );
			$this->plugin->log_event( 'error', 'test_email', "Falha ao enviar e-mail de teste para {$recipient}.", $this->plugin->mail_error );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-settings' ) );
		exit;
    }
}
