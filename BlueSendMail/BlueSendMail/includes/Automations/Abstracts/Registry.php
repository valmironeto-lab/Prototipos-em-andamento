<?php
/**
 * Classe Abstrata para Registos de Componentes (Gatilhos, Ações, etc.).
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Automations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Registry {
    protected static $includes = [];
    protected static $loaded = [];

    public static function init() {
        static::load_includes();
    }

    abstract protected static function load_includes();

    public static function get_includes() {
        if ( empty( static::$includes ) ) {
            static::load_includes();
        }
        return static::$includes;
    }

    /**
     * Obtém uma instância de um componente específico pelo seu ID.
     *
     * @param string $id O ID do componente.
     * @param mixed ...$args Argumentos para passar ao construtor da classe.
     * @return object|null A instância do componente ou null se não for encontrado.
     */
    public static function get( $id, ...$args ) {
        // Para componentes sem argumentos (como Triggers), podemos armazenar em cache.
        if ( empty($args) && isset( static::$loaded[ $id ] ) ) {
            return static::$loaded[ $id ];
        }

        $includes = static::get_includes();
        $class_name = $includes[ $id ] ?? null;

        if ( $class_name && class_exists( $class_name ) ) {
            $instance = new $class_name( ...$args );
            if ( empty($args) ) {
                static::$loaded[ $id ] = $instance;
            }
            return $instance;
        }

        return null;
    }

    public static function get_all() {
        foreach ( static::get_includes() as $id => $class_name ) {
            if ( ! isset( static::$loaded[ $id ] ) ) {
                static::get( $id );
            }
        }
        return static::$loaded;
    }
}
