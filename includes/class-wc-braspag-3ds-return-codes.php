<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classifica os return codes documentados pela Cielo para autenticação 3DS
 * (docs.cielo.com.br/gateway/docs/return-codes-3ds) em categorias acionáveis,
 * para que o log/suporte não precise adivinhar se vale a pena reenviar a
 * transação, corrigir dados, ou escalar para configuração/suporte.
 */
class WC_Braspag_3ds_Return_Codes
{
    const SUCCESS = 'success';
    const VALIDATION = 'validation';
    const SYSTEM = 'system';
    const CONFIGURATION = 'configuration';
    const AUTHENTICATION_REQUIRED = 'authentication_required';
    const MPI_ERROR = 'mpi_error';
    const UNKNOWN = 'unknown';

    /**
     * @var array<string, string>
     */
    protected static $map = array(
        '100' => self::SUCCESS,
        '101' => self::VALIDATION,
        '102' => self::VALIDATION,
        '150' => self::SYSTEM,
        '151' => self::SYSTEM,
        '152' => self::SYSTEM,
        '234' => self::CONFIGURATION,
        '475' => self::AUTHENTICATION_REQUIRED,
        '476' => self::AUTHENTICATION_REQUIRED,
        'MPI600' => self::MPI_ERROR,
        'MPI601' => self::MPI_ERROR,
        'MPI900' => self::MPI_ERROR,
        'MPI901' => self::MPI_ERROR,
        'MPI902' => self::MPI_ERROR,
    );

    /**
     * @param string|int $code
     * @return string Uma das constantes de categoria desta classe.
     */
    public static function classify($code)
    {
        $code = strtoupper(trim((string) $code));

        return isset(self::$map[$code]) ? self::$map[$code] : self::UNKNOWN;
    }

    /**
     * Indica se, segundo a categoria, vale a pena reenviar a transação
     * automaticamente (códigos sistêmicos/timeout). Erros de validação,
     * configuração e autenticação exigem intervenção antes de reenviar.
     *
     * @param string|int $code
     * @return bool
     */
    public static function is_retryable($code)
    {
        return self::SYSTEM === self::classify($code);
    }
}
