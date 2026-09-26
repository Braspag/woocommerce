<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Braspag_Auth3ds_V3_Gate
 *
 * Lógica de "gate" (bloquear ou autorizar mesmo com falha/challenge não
 * resolvido) do 3DS, compartilhada entre crédito e débito. Extraída na
 * migração para MPI v3 para eliminar a duplicação que causava os bugs
 * 3DS-07/3DS-08/3DS-09 (auditoria docs/specs/integrations/3ds-auditoria-2026-09-07.md):
 *
 * - 3DS-07: `Authenticate` no builder de débito usava
 *   `($this->auth3ds20_mpi_is_active) ? true : false`, que é sempre `true`
 *   porque a string 'no' é truthy em PHP. Corrigido no chamador (builders)
 *   com `'yes' === $this->auth3ds20_mpi_is_active`.
 * - 3DS-08: o switch de `failure_type` do débito não tinha `default`
 *   (o crédito tinha). Aqui o `default` sempre bloqueia (fail-closed),
 *   igual para os dois métodos de pagamento.
 * - 3DS-09: o builder de débito não tinha o gate de bloqueio equivalente
 *   ao do crédito. Ambos os builders agora chamam este mesmo método.
 *
 * Os códigos de `failure_type` são preservados do fluxo v2 (mesma semântica
 * usada pelas configurações `auth3ds20_mpi_authorize_on_*` já existentes):
 *   '' ou '0' => sucesso/nenhuma falha
 *   '1'       => falha de autenticação
 *   '2'       => cartão não enrolado (unenrolled)
 *   '3'       => DataOnly / sem challenge (tratado à parte pelo chamador)
 *   '4'       => erro no MPI
 *   '5'       => bandeira não suportada
 *
 * @since 2.4.0
 */
class WC_Braspag_Auth3ds_V3_Gate
{
    /**
     * Monta o nó `Payment.ExternalAuthentication` enviado ao Pagador,
     * compartilhado entre crédito e débito.
     *
     * Nomes de campo conforme a documentação da Cielo (`ReferenceID` com "ID"
     * maiúsculo, `DataOnly` booleano) — ver
     * docs.cielo.com.br/ecommerce-cielo/docs/{autorizacao-autenticacao,data-only}.
     *
     * Quando a transação NÃO foi autenticada (a loja optou por prosseguir via
     * `auth3ds20_mpi_authorize_on_*`), o `Eci` continua sendo enviado — a
     * Cielo o devolve mesmo com `Status=0` e o adquirente usa esse indicador
     * (RN-3DS-008 / BDD-3DS-012) —, mas `Cavv` e `Xid` são omitidos: o Cavv é
     * o criptograma que comunica autenticação/liability shift, e enviá-lo numa
     * transação não autenticada afirmaria algo falso (BDD-3DS-013 / INV-007).
     *
     * @param array $values {
     *     @type string $cavv
     *     @type string $xid
     *     @type string $eci
     *     @type string $version
     *     @type string $reference_id
     *     @type bool   $data_only
     * }
     * @param bool $authenticated
     * @return array
     */
    public static function build_external_authentication(array $values, $authenticated)
    {
        $node = array(
            'Eci' => isset($values['eci']) ? $values['eci'] : '',
            'Version' => isset($values['version']) ? $values['version'] : '',
            'ReferenceID' => isset($values['reference_id']) ? $values['reference_id'] : '',
        );

        if ($authenticated) {
            $node['Cavv'] = isset($values['cavv']) ? $values['cavv'] : '';
            $node['Xid'] = isset($values['xid']) ? $values['xid'] : '';
        }

        if (!empty($values['data_only'])) {
            $node['DataOnly'] = true;
        }

        return $node;
    }

    /**
     * Decide se o pedido deve ser bloqueado (impedido de prosseguir sem
     * `ExternalAuthentication`/com falha) para um dado `failure_type`.
     *
     * `true`  => bloquear (falha real; se o chamador é `process_payment_validation()`,
     *            deve lançar `WC_Braspag_Exception`; se é o builder de payload,
     *            deve seguir e montar `ExternalAuthentication` normalmente).
     * `false` => autorizar mesmo assim, conforme a configuração `auth3ds20_mpi_authorize_on_*`
     *            correspondente (builder deve pular `ExternalAuthentication`).
     *
     * @param string $failure_type Código de falha (string; ver docblock da classe).
     * @param array $settings {
     *     @type string $authorize_on_error             'yes'/'no' (auth3ds20_mpi_authorize_on_error)
     *     @type string $authorize_on_failure           'yes'/'no' (auth3ds20_mpi_authorize_on_failure)
     *     @type string $authorize_on_unenrolled        'yes'/'no' (auth3ds20_mpi_authorize_on_unenrolled)
     *     @type string $authorize_on_unsupported_brand 'yes'/'no' (auth3ds20_mpi_authorize_on_unsupported_brand)
     *     @type bool   $test_mode                      Ambiente sandbox?
     *     @type bool   $is_cielo                       Provider da bandeira é Cielo?
     * }
     * @return bool
     */
    public static function should_block($failure_type, array $settings = array())
    {
        $failure_type = (string) $failure_type;

        if ('' === $failure_type || '0' === $failure_type) {
            return false;
        }

        $defaults = array(
            'authorize_on_error' => 'no',
            'authorize_on_failure' => 'no',
            'authorize_on_unenrolled' => 'no',
            'authorize_on_unsupported_brand' => 'no',
            'test_mode' => true,
            'is_cielo' => true,
        );
        $settings = array_merge($defaults, $settings);

        switch ($failure_type) {
            case '4':
                $block = ('no' === $settings['authorize_on_error']);
                break;
            case '1':
                $block = ('no' === $settings['authorize_on_failure']);
                break;
            case '2':
                $block = ('no' === $settings['authorize_on_unenrolled']);
                break;
            case '5':
                $block = ('no' === $settings['authorize_on_unsupported_brand']);
                break;
            case '3':
                // Data Only não é falha: é um fluxo frictionless concluído
                // com sucesso, só sem liability shift (RN-3DS-012). Sem este
                // case o código caía no `default` fail-closed e bloqueava
                // toda transação Data Only.
                $block = false;
                break;
            default:
                // 3DS-08: código de falha desconhecido/não mapeado — bloqueia
                // por padrão (fail-closed), em vez de deixar passar em silêncio.
                $block = true;
                break;
        }

        // Fora de sandbox, para provedores que não sejam Cielo (sem o mesmo
        // tratamento de liability shift), força o bloqueio — exceto no caso
        // '3' (DataOnly), que não é uma falha real.
        if (false === $block && false === $settings['test_mode'] && '3' !== $failure_type && false === $settings['is_cielo']) {
            $block = true;
        }

        return $block;
    }
}
