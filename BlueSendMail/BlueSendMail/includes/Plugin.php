<?php
/**
 * Classe principal do Plugin. Padrão Singleton.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail;

use BlueSendMail\Core\DB;
use BlueSendMail\Core\Cron;
use BlueSendMail\Core\Admin;
use BlueSendMail\Core\Automation_Engine;
use BlueSendMail\Interfaces\Mailer_Interface;
use BlueSendMail\Mailers\WPMail_Mailer;
use BlueSendMail\Mailers\SMTP_Mailer;
use BlueSendMail\Mailers\SendGrid_Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static $_instance = null;
	public $options = array();
	public $mail_error = '';
	private $current_queue_id_for_tracking = 0;
	private ?Mailer_Interface $mailer = null;
	public ?DB $db = null;
	public ?Cron $cron = null;
    public ?Automation_Engine $automations = null;
	public ?Admin $admin = null;

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) { self::$_instance = new self(); }
		return self::$_instance;
	}

	private function __construct() {
		$this->load_options();
		$this->instantiate_classes();
		$this->register_hooks();
	}

	private function load_options() {
		$this->options = get_option( 'bluesendmail_settings', array() );
	}

	private function instantiate_classes() {
		$this->db   = new DB();
		$this->cron = new Cron( $this );
        $this->automations = new Automation_Engine( $this );
		if ( is_admin() ) { $this->admin = new Admin( $this ); }
	}

	private function register_hooks() {
		add_action( 'init', array( $this, 'handle_public_actions' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_shortcode( 'bluesendmail_form', array( $this, 'handle_form_shortcode' ) );
        add_action( 'init', array( $this, 'start_session' ), 1 );
	}

    public function start_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style( 'bluesendmail-frontend', BLUESENDMAIL_PLUGIN_URL . 'assets/css/frontend.css', array(), BLUESENDMAIL_VERSION );
    }

	public function get_mailer() {
        if ( ! is_null( $this->mailer ) ) { return $this->mailer; }
		$mailer_type = $this->options['mailer_type'] ?? 'wp_mail';
		switch ( $mailer_type ) {
			case 'smtp': $this->mailer = new SMTP_Mailer( $this->options ); break;
			case 'sendgrid': $this->mailer = new SendGrid_Mailer( $this->options ); break;
			case 'wp_mail': default: $this->mailer = new WPMail_Mailer(); break;
		}
		return $this->mailer;
    }

	public function send_email( $to_email, $subject, $body, $contact, $queue_id ) {
        $mailer = $this->get_mailer();
		$site_name = get_bloginfo( 'name' );
		$site_url = home_url();
		$subject = str_replace( array('{{site.name}}', '{{contact.first_name}}', '{{contact.last_name}}', '{{contact.email}}'), array($site_name, $contact->first_name ?? '', $contact->last_name ?? '', $contact->email), $subject );
		$body = str_replace( array('{{site.name}}', '{{site.url}}', '{{contact.first_name}}', '{{contact.last_name}}', '{{contact.email}}'), array($site_name, esc_url($site_url), $contact->first_name ?? '', $contact->last_name ?? '', $contact->email), $body );
		$token = hash( 'sha256', $contact->email . AUTH_KEY );
		$unsubscribe_url = add_query_arg( array( 'bsm_action' => 'unsubscribe', 'email' => rawurlencode( $contact->email ), 'token' => $token ), home_url() );
		$body = str_replace( '{{unsubscribe_link}}', esc_url( $unsubscribe_url ), $body );
		if ( ! empty( $this->options['enable_click_tracking'] ) && $queue_id > 0 ) {
			$this->current_queue_id_for_tracking = $queue_id;
			$body = preg_replace_callback( '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/', array( $this, '_replace_links_callback' ), $body );
		}
		if ( ! empty( $this->options['enable_open_tracking'] ) && $queue_id > 0 ) {
			$tracking_token = hash( 'sha256', $queue_id . NONCE_KEY );
			$tracking_url = add_query_arg( array( 'bsm_action' => 'track_open', 'queue_id' => $queue_id, 'token' => $tracking_token ), home_url() );
			$tracking_pixel = '<img src="' . esc_url( $tracking_url ) . '" width="1" height="1" style="display:none;" alt="">';
			$body .= $tracking_pixel;
		}
		$from_name = $this->options['from_name'] ?? get_bloginfo( 'name' );
		$from_email = $this->options['from_email'] ?? get_bloginfo( 'admin_email' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8', "From: {$from_name} <{$from_email}>" );
		$result = $mailer->send( $to_email, $subject, $body, $headers );
		if ( ! $result ) { $this->mail_error = $mailer->get_last_error(); }
		return $result;
    }

	private function _replace_links_callback( $matches ) {
        $original_url = $matches[2];
		if ( strpos( $original_url, '#' ) === 0 || strpos( $original_url, 'mailto:' ) === 0 || strpos( $original_url, 'bsm_action=unsubscribe' ) !== false ) { return $matches[0]; }
		$queue_id = $this->current_queue_id_for_tracking;
		$encoded_url = rtrim( strtr( base64_encode( $original_url ), '+/', '-_' ), '=' );
		$token = hash( 'sha256', $queue_id . $original_url . NONCE_KEY );
		$tracking_url = add_query_arg( array( 'bsm_action' => 'track_click', 'qid' => $queue_id, 'url' => $encoded_url, 'token' => $token ), home_url() );
		return str_replace( $original_url, esc_url( $tracking_url ), $matches[0] );
    }

	public function handle_public_actions() {
        if ( isset( $_GET['bsm_action'] ) ) {
			switch ( $_GET['bsm_action'] ) {
				case 'unsubscribe': $this->handle_unsubscribe_request(); break;
				case 'track_open': $this->handle_tracking_pixel(); break;
				case 'track_click': $this->handle_click_tracking(); break;
			}
		}
        if ( isset( $_POST['bsm_form_submit_action'] ) ) {
            $this->handle_form_submission();
        }
    }

	private function handle_click_tracking() {
        if ( ! isset( $_GET['qid'], $_GET['url'], $_GET['token'] ) ) return;
		$queue_id = absint( $_GET['qid'] );
		$original_url = base64_decode( strtr( sanitize_text_field( $_GET['url'] ), '-_', '+/' ) );
		if ( ! filter_var($original_url, FILTER_VALIDATE_URL) ) return;
		if ( ! hash_equals( hash( 'sha256', $queue_id . $original_url . NONCE_KEY ), sanitize_text_field( $_GET['token'] ) ) ) wp_die( esc_html__( 'Link inválido ou expirado.', 'bluesendmail' ), esc_html__( 'Erro de Segurança', 'bluesendmail' ), 403 );
		global $wpdb;
		$queue_item = $wpdb->get_row( $wpdb->prepare( "SELECT contact_id, campaign_id FROM {$wpdb->prefix}bluesendmail_queue WHERE queue_id = %d", $queue_id ) );
		if ( $queue_item ) $wpdb->insert( "{$wpdb->prefix}bluesendmail_email_clicks", array( 'queue_id' => $queue_id, 'campaign_id' => $queue_item->campaign_id, 'contact_id' => $queue_item->contact_id, 'original_url' => $original_url, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		wp_redirect( esc_url_raw( $original_url ) );
		exit;
    }

	private function handle_tracking_pixel() {
        if ( ! isset( $_GET['queue_id'], $_GET['token'] ) ) return;
		$queue_id = absint( $_GET['queue_id'] );
		if ( ! hash_equals( hash( 'sha256', $queue_id . NONCE_KEY ), sanitize_text_field( $_GET['token'] ) ) ) return;
		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}bluesendmail_email_opens", array( 'queue_id' => $queue_id, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		header( 'Content-Type: image/gif' );
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
    }

	private function handle_unsubscribe_request() {
        if ( ! isset( $_GET['email'], $_GET['token'] ) ) wp_die( esc_html__( 'Link inválido. Faltam parâmetros.', 'bluesendmail' ), esc_html__( 'Erro', 'bluesendmail' ), 400 );
		$email = sanitize_email( rawurldecode( $_GET['email'] ) );
		if ( ! is_email( $email ) ) wp_die( esc_html__( 'Formato de e-mail inválido.', 'bluesendmail' ), esc_html__( 'Erro', 'bluesendmail' ), 400 );
		if ( ! hash_equals( hash( 'sha256', $email . AUTH_KEY ), sanitize_text_field( $_GET['token'] ) ) ) wp_die( esc_html__( 'A verificação de segurança falhou. O link pode ter sido alterado ou é inválido.', 'bluesendmail' ), esc_html__( 'Erro de Segurança', 'bluesendmail' ), 403 );
		global $wpdb;
		if ( false !== $wpdb->update( "{$wpdb->prefix}bluesendmail_contacts", array( 'status' => 'unsubscribed' ), array( 'email' => $email ) ) ) {
			wp_die( esc_html__( 'O seu e-mail foi removido da nossa lista com sucesso. Você não receberá mais comunicações.', 'bluesendmail' ), esc_html__( 'Remoção Concluída', 'bluesendmail' ), array( 'response' => 200 ) );
		} else {
			wp_die( esc_html__( 'Ocorreu um erro ao tentar processar o seu pedido. Por favor, tente novamente mais tarde ou contacte o administrador do site.', 'bluesendmail' ), esc_html__( 'Erro na Base de Dados', 'bluesendmail' ), 500 );
		}
    }

	public function log_event( $type, $source, $message, $details = '' ) {
        global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}bluesendmail_logs", array( 'type' => $type, 'source' => $source, 'message' => $message, 'details' => is_string( $details ) ? $details : serialize( $details ) ) );
    }

	public function bsm_get_timezone() {
        if ( function_exists( 'wp_timezone' ) ) return wp_timezone();
		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) return new \DateTimeZone( $timezone_string );
		$offset = (float) get_option( 'gmt_offset' );
		return new \DateTimeZone( sprintf( '%+03d:%02d', (int) $offset, ( $offset - floor( $offset ) ) * 60 ) );
    }

	public function enqueue_campaign_recipients( $campaign_id ) {
        global $wpdb;
		$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_campaigns WHERE campaign_id = %d", $campaign_id ) );
		if ( ! $campaign ) return;
		$lists = ! empty( $campaign->lists ) ? unserialize( $campaign->lists ) : array();
		if ( ! empty( $lists ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $lists ), '%d' ) );
			$sql = $wpdb->prepare( "SELECT DISTINCT c.contact_id FROM {$wpdb->prefix}bluesendmail_contacts c JOIN {$wpdb->prefix}bluesendmail_contact_lists cl ON c.contact_id = cl.contact_id WHERE cl.list_id IN ($placeholders) AND c.status = %s", array_merge( $lists, array( 'subscribed' ) ) );
		} else {
			$sql = "SELECT contact_id FROM {$wpdb->prefix}bluesendmail_contacts WHERE status = 'subscribed'";
		}
		$contact_ids = $wpdb->get_col( $sql );
		if ( ! empty( $contact_ids ) ) {
			$queued = 0;
			foreach ( $contact_ids as $cid ) {
				$wpdb->insert( "{$wpdb->prefix}bluesendmail_queue", array( 'campaign_id' => $campaign_id, 'contact_id' => (int) $cid, 'status' => 'pending', 'attempts' => 0, 'added_at' => current_time( 'mysql', 1 ) ) );
				$queued++;
			}
			$this->log_event( 'info', 'scheduler', "Campanha #{$campaign_id} colocada na fila para {$queued} destinatários." );
		} else {
			$this->log_event( 'warning', 'scheduler', "A campanha #{$campaign_id} não encontrou destinatários." );
		}
    }

    public function handle_form_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bluesendmail_form' );
        $form_id = absint( $atts['id'] );
        if ( ! $form_id ) return '';

        global $wpdb;
        $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_forms WHERE form_id = %d", $form_id ) );
        if ( ! $form ) return '';

        $enabled_fields = $form->fields ? maybe_unserialize( $form->fields ) : array();
        $fields_html = '';
        
        $available_fields = array(
            'first_name' => array( 'label' => __( 'Primeiro Nome', 'bluesendmail' ), 'type' => 'text' ),
            'last_name'  => array( 'label' => __( 'Apelido', 'bluesendmail' ), 'type' => 'text' ),
            'phone'      => array( 'label' => __( 'Telefone/WhatsApp', 'bluesendmail' ), 'type' => 'tel' ),
            'company'    => array( 'label' => __( 'Empresa', 'bluesendmail' ), 'type' => 'text' ),
            'job_title'  => array( 'label' => __( 'Cargo', 'bluesendmail' ), 'type' => 'text' ),
            'segment'    => array( 'label' => __( 'Segmento', 'bluesendmail' ), 'type' => 'text' ),
        );

        foreach ( $available_fields as $key => $props ) {
            if ( in_array( $key, $enabled_fields ) ) {
                $fields_html .= sprintf(
                    '<div class="bsm-form-field"><label for="bsm-%1$s-%2$s">%3$s</label><input type="%4$s" name="%1$s" id="bsm-%1$s-%2$s" /></div>',
                    esc_attr( $key ), esc_attr( $form_id ), esc_html( $props['label'] ), esc_attr( $props['type'] )
                );
            }
        }
        
        $form_message = '';
        if ( isset( $_SESSION['bsm_form_message_' . $form_id] ) ) {
            $message_data = $_SESSION['bsm_form_message_' . $form_id];
            $form_message = sprintf('<div class="bsm-form-message %s">%s</div>', esc_attr($message_data['type']), esc_html($message_data['message']));
            unset( $_SESSION['bsm_form_message_' . $form_id] );
        }
        
        $button_style = '';
        if ( ! empty( $form->button_color ) ) {
            $button_style = 'style="background-color: ' . esc_attr( $form->button_color ) . ';"';
        }

        ob_start();
        ?>
        <div class="bsm-form-container">
            <h3><?php echo esc_html( $form->title ); ?></h3>
            <?php if ( ! empty( $form->content ) ) : ?>
                <p><?php echo nl2br( esc_html( $form->content ) ); ?></p>
            <?php endif; ?>

            <?php echo $form_message; ?>

            <form method="post">
                <input type="hidden" name="bsm_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php wp_nonce_field( 'bsm_submit_form_action_' . $form_id, 'bsm_submit_form_nonce' ); ?>
                <div class="bsm-form-field">
                    <label for="bsm-email-<?php echo esc_attr( $form_id ); ?>"><?php _e( 'E-mail', 'bluesendmail' ); ?>*</label>
                    <input type="email" name="email" id="bsm-email-<?php echo esc_attr( $form_id ); ?>" required />
                </div>
                <?php echo $fields_html; ?>
                <div class="bsm-form-submit">
                    <input type="submit" name="bsm_form_submit_action" value="<?php echo esc_attr( $form->button_text ); ?>" <?php echo $button_style; ?> />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_form_submission() {
        if ( session_status() == PHP_SESSION_NONE ) {
            session_start();
        }

        $form_id = isset( $_POST['bsm_form_id'] ) ? absint( $_POST['bsm_form_id'] ) : 0;
        if ( ! $form_id ) return;
        if ( ! isset( $_POST['bsm_submit_form_nonce'] ) || ! wp_verify_nonce( $_POST['bsm_submit_form_nonce'], 'bsm_submit_form_action_' . $form_id ) ) return;
        
        global $wpdb;
        $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_forms WHERE form_id = %d", $form_id ) );
        if ( ! $form ) return;

        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        if ( ! is_email( $email ) ) {
            $_SESSION['bsm_form_message_' . $form_id] = ['type' => 'error', 'message' => $form->error_message ];
            wp_redirect( esc_url_raw( $_SERVER['HTTP_REFERER'] ?? home_url() ) );
            exit;
        }

        $contact_data = array(
            'email'      => $email,
            'status'     => 'subscribed',
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : null,
            'last_name'  => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : null,
            'phone'      => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : null,
            'company'    => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : null,
            'job_title'  => isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : null,
            'segment'    => isset($_POST['segment']) ? sanitize_text_field($_POST['segment']) : null,
        );
        $contact_data = array_filter( $contact_data, function($value) { return !is_null($value) && $value !== ''; } );

        $existing_contact_id = $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM {$wpdb->prefix}bluesendmail_contacts WHERE email = %s", $email ) );
        $current_time = current_time( 'mysql', 1 );

        if ( $existing_contact_id ) {
            $contact_data['updated_at'] = $current_time;
            $wpdb->update( "{$wpdb->prefix}bluesendmail_contacts", $contact_data, array( 'contact_id' => $existing_contact_id ) );
            $contact_id = $existing_contact_id;
        } else {
            $contact_data['created_at'] = $current_time;
            $contact_data['updated_at'] = $current_time;
            $wpdb->insert( "{$wpdb->prefix}bluesendmail_contacts", $contact_data );
            $contact_id = $wpdb->insert_id;
        }

        if ( $contact_id ) {
            $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}bluesendmail_contact_lists (contact_id, list_id) VALUES (%d, %d)", $contact_id, $form->list_id ) );
            do_action('bsm_contact_added_to_list', $contact_id, $form->list_id);
            $_SESSION['bsm_form_message_' . $form_id] = ['type' => 'success', 'message' => $form->success_message ];
        } else {
            $_SESSION['bsm_form_message_' . $form_id] = ['type' => 'error', 'message' => $form->error_message ];
        }
        
        wp_redirect( esc_url_raw( $_SERVER['HTTP_REFERER'] ?? home_url() ) );
		exit;
    }
}

