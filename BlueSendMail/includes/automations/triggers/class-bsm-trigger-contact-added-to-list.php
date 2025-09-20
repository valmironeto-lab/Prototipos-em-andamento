<?php
/**
 * Gatilho: Contato é adicionado a uma lista.
 *
 * @package BlueSendMail
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Trigger_Contact_Added_To_List extends BSM_Abstract_Trigger {

    /**
     * Define as propriedades do gatilho.
     */
    public function init() {
        $this->id    = 'contact_added_to_list';
        $this->name  = __( 'Contato Adicionado à Lista', 'bluesendmail' );
        $this->group = __( 'Contatos', 'bluesendmail' );
    }

    /**
     * Registra o gancho do WordPress que este gatilho escuta.
     */
    public function register_hooks() {
        add_action( 'bsm_contact_added_to_list', [ $this, 'handle_event' ], 10, 2 );
    }

    /**
     * Retorna os campos de configuração para a interface do administrador.
     */
    public function get_fields() {
        global $wpdb;
        $all_lists = $wpdb->get_results( "SELECT list_id, name FROM {$wpdb->prefix}bluesendmail_lists ORDER BY name ASC", ARRAY_A );
        $options = [ '' => __( 'Qualquer Lista', 'bluesendmail' ) ];
        if ( $all_lists ) {
            foreach ( $all_lists as $list ) {
                $options[ $list['list_id'] ] = $list['name'];
            }
        }

        return [
            [
                'id' => 'list_id',
                'type' => 'select',
                'label' => __( 'Selecione a Lista Específica', 'bluesendmail' ),
                'options' => $options,
                'description' => __( 'A automação será acionada quando um contato for adicionado a esta lista. Deixe como "Qualquer Lista" para acionar sempre.', 'bluesendmail' )
            ]
        ];
    }

    /**
     * Manipula o evento quando ele é disparado pelo WordPress.
     *
     * @param int $contact_id ID do contato adicionado.
     * @param int $list_id ID da lista à qual ele foi adicionado.
     */
    public function handle_event( $contact_id, $list_id ) {
        global $wpdb;

        $contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_contacts WHERE contact_id = %d", $contact_id ) );
        
        if ( $contact ) {
            // Cria a camada de dados com as informações do evento.
            $data_layer = new BSM_Data_Layer( [
                'contact' => $contact,
                'list_id' => $list_id
            ] );

            // Chama o motor de automação, informando qual gatilho foi acionado e passando os dados.
            $this->automations_engine->trigger_event( $this->id, $data_layer );
        }
    }

    /**
     * Valida se as condições da automação específica foram atendidas.
     *
     * @param array $trigger_settings Configurações salvas para a automação.
     * @param BSM_Data_Layer $data_layer Dados do evento.
     * @return bool
     */
    public function validate_options( $trigger_settings, $data_layer ) {
        $required_list_id = absint( $trigger_settings['list_id'] ?? 0 );
        
        // Se for 0, significa que a automação deve rodar para "Qualquer Lista".
        if ( $required_list_id === 0 ) {
            return true;
        }

        $event_list_id = $data_layer->get_item('list_id');

        return $event_list_id === $required_list_id;
    }
}
