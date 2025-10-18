<?php
/**
 * Gerencia o processamento de importação de contatos.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Import_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_post_bsm_import_contacts', array( $this, 'handle_import_contacts' ) );
    }

    public function handle_import_contacts() {
        if ( ! isset( $_POST['bsm_import_nonce_field_step2'] ) || ! wp_verify_nonce( $_POST['bsm_import_nonce_field_step2'], 'bsm_import_nonce_action_step2' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_contacts' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
    
        set_time_limit(300);
    
        $file_path = sanitize_text_field( $_POST['bsm_import_file_path'] );
        $list_id   = absint( $_POST['bsm_import_list_id'] );
        $map       = (array) ( $_POST['column_map'] ?? [] );
    
        if ( ! file_exists( $file_path ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Arquivo de importação não encontrado. Por favor, tente novamente desde o passo 1.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-import' ) );
            exit;
        }
    
        if ( ! $list_id ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Nenhuma lista foi selecionada para a importação.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-import' ) );
            exit;
        }
    
        $email_column_index = array_search( 'email', $map, true );
        if ( false === $email_column_index ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('É obrigatório associar uma coluna ao campo "E-mail".', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-import' ) );
            exit;
        }
    
        global $wpdb;
        $this->plugin->log_event('info', 'import', "Iniciando importação do arquivo: {$file_path}");
        
        $imported_count = 0; $skipped_count = 0; $row_count = 0;
    
        if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
            while ( ( $data = fgetcsv( $handle ) ) !== false ) {
                $row_count++;
                if ( 1 === $row_count ) continue;
    
                $email = isset($data[ $email_column_index ]) ? sanitize_email( $data[ $email_column_index ] ) : '';
                if ( ! is_email( $email ) ) { $skipped_count++; continue; }
    
                $current_time = current_time( 'mysql', 1 );
                $contact_data = [ 'email' => $email, 'status' => 'subscribed', 'updated_at' => $current_time ];
    
                foreach ( $map as $index => $field ) {
                    if ( ! empty( $field ) && isset( $data[ $index ] ) ) { $contact_data[ $field ] = sanitize_text_field( $data[ $index ] ); }
                }
    
                $existing_contact_id = $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM {$wpdb->prefix}bluesendmail_contacts WHERE email = %s", $email ) );
    
                if ( $existing_contact_id ) {
                    unset( $contact_data['email'] );
                    $wpdb->update( "{$wpdb->prefix}bluesendmail_contacts", $contact_data, array( 'contact_id' => $existing_contact_id ) );
                    $contact_id = $existing_contact_id;
                } else {
                    $contact_data['created_at'] = $current_time;
                    $wpdb->insert( "{$wpdb->prefix}bluesendmail_contacts", $contact_data );
                    $contact_id = $wpdb->insert_id;
                }
    
                if ( $contact_id ) {
                    $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}bluesendmail_contact_lists (contact_id, list_id) VALUES (%d, %d)", $contact_id, $list_id ) );
                    do_action('bsm_contact_added_to_list', $contact_id, $list_id);
                    $imported_count++;
                } else { $skipped_count++; }
            }
            fclose( $handle );
        }
    
        @unlink( $file_path );
        $this->plugin->log_event('info', 'import', "Importação concluída. {$imported_count} importados, {$skipped_count} ignorados.");
    
        $message = sprintf( __( 'Importação concluída! %d contatos importados/atualizados e %d linhas ignoradas.', 'bluesendmail' ), $imported_count, $skipped_count );
        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => $message], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts' ) );
        exit;
    }
}
