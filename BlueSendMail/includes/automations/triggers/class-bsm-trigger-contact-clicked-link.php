<?php
/**
 * Gatilho: Contato clica em um link de uma campanha.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Trigger_Contact_Clicked_Link extends BSM_Abstract_Trigger {

    public function init() {
        $this->id    = 'contact_clicked_link';
        $this->name  = __( 'Contato Clica em um Link', 'bluesendmail' );
        $this->group = __( 'Engajamento', 'bluesendmail' );
    }

    public function register_hooks() {
        add_action( 'bsm_contact_clicked_link', array( $this, 'handle_event' ), 10, 3 );
    }

    public function get_fields() {
        return [
            [
                'id' => 'link_url',
                'type' => 'text',
                'label' => __( 'URL do Link (Opcional)', 'bluesendmail' ),
                'placeholder' => __( 'Deixe em branco para qualquer link', 'bluesendmail' ),
            ]
        ];
    }

    public function handle_event( $contact_id, $campaign_id, $url ) {
        $data_layer = new BSM_Data_Layer();
        $data_layer->add_item( 'contact_id', $contact_id );
        $data_layer->add_item( 'campaign_id', $campaign_id );
        $data_layer->add_item( 'url', $url );

        BlueSendMail::get_instance()->automations->maybe_trigger_automations( $this, $data_layer );
    }

    public function validate_options( $trigger_settings, $data_layer ) {
        $required_url = trim( $trigger_settings['link_url'] ?? '' );
        if ( empty( $required_url ) ) {
            return true; // Passa se nenhum link específico for necessário.
        }

        $event_url = $data_layer->get_item('url');
        return $event_url === $required_url;
    }
}

