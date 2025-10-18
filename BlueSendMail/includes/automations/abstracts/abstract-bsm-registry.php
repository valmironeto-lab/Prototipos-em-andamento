<?php
/**
 * Classe Abstrata para Registos de Componentes (Gatilhos, Ações, etc.).
 * Define o padrão para carregar e gerir componentes de automação.
 *
 * @package BlueSendMail
 * @version 2.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BSM_Registry {

    /**
     * Armazena as definições de classe dos componentes (ex: 'id' => 'NomeDaClasse').
     * @var array
     */
    protected static $includes = [];

    /**
     * Armazena as instâncias já carregadas dos componentes.
     * @var array
     */
    protected static $loaded = [];

    /**
     * Ponto de entrada para inicializar o registo.
     */
    public static function init() {
        static::load_includes();
    }

    /**
     * Carrega as definições de classe no array $includes.
     * Deve ser implementado pelas classes filhas.
     */
    abstract protected static function load_includes();

    /**
     * Retorna o array com as definições de classe dos componentes.
     * Garante que as definições sejam carregadas apenas uma vez.
     *
     * @return array
     */
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
     * @return object|null A instância do componente ou null se não for encontrado.
     */
    public static function get( $id ) {
        if ( isset( static::$loaded[ $id ] ) ) {
            return static::$loaded[ $id ];
        }

        $includes = static::get_includes();
        $class_name = $includes[ $id ] ?? null;

        if ( $class_name && class_exists( $class_name ) ) {
            static::$loaded[ $id ] = new $class_name();
            return static::$loaded[ $id ];
        }

        return null;
    }

    /**
     * Obtém todas as instâncias de todos os componentes registados.
     *
     * @return array
     */
    public static function get_all() {
        foreach ( static::get_includes() as $id => $class_name ) {
            if ( ! isset( static::$loaded[ $id ] ) ) {
                static::get( $id );
            }
        }
        return static::$loaded;
    }
}

