<?php

use PHPUnit\Framework\TestCase;

/**
 * WC_Braspag_Pagador_API::extract_error_messages() — leitura do corpo de erro
 * que o Pagador devolve em resposta não-2xx.
 *
 * Antes desta correção, o caminho de erro da autorização lia
 * `$response->error->type` (objeto singular, forma que o Pagador nunca
 * devolve). No PHP 8 isso emitia warnings que vazavam como HTML na resposta
 * JSON do checkout, e a mensagem chegava vazia ao comprador
 * (`{"result":"failure","messages":""}`) — observado no log de 26/09, com o
 * 3DS já concluído com sucesso.
 */
class WC_Braspag_Pagador_API_Errors_Test extends TestCase
{
    /** Formato real: lista de {Code, Message}. */
    public function test_lista_de_code_message()
    {
        $errors = json_decode('[{"Code":126,"Message":"Card Number length exceeded"}]');

        $this->assertSame(
            array('[126] Card Number length exceeded'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    public function test_varios_erros_preserva_a_ordem()
    {
        $errors = json_decode('[{"Code":126,"Message":"Primeiro"},{"Code":127,"Message":"Segundo"}]');

        $this->assertSame(
            array('[126] Primeiro', '[127] Segundo'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    /** Objeto único (não embrulhado em lista). */
    public function test_objeto_unico()
    {
        $errors = json_decode('{"Code":234,"Message":"Merchant inválido"}');

        $this->assertSame(
            array('[234] Merchant inválido'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    public function test_corpo_em_texto_puro()
    {
        $this->assertSame(
            array('Service Unavailable'),
            WC_Braspag_Pagador_API::extract_error_messages('  Service Unavailable  ')
        );
    }

    public function test_mensagem_sem_codigo()
    {
        $errors = json_decode('[{"Message":"Sem código"}]');

        $this->assertSame(
            array('Sem código'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    public function test_codigo_sem_mensagem()
    {
        $errors = json_decode('[{"Code":999}]');

        $this->assertSame(
            array('[999]'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    public function test_entradas_vazias_sao_ignoradas()
    {
        $errors = json_decode('[{"Code":126,"Message":"Válido"},{},null]');

        $this->assertSame(
            array('[126] Válido'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }

    /**
     * @dataProvider vazios
     */
    public function test_sem_erros_devolve_lista_vazia($errors)
    {
        $this->assertSame(array(), WC_Braspag_Pagador_API::extract_error_messages($errors));
    }

    public function vazios()
    {
        return array(
            'null' => array(null),
            'string vazia' => array(''),
            'array vazio' => array(array()),
            'false' => array(false),
        );
    }

    /** Array associativo (json_decode com assoc=true) também é aceito. */
    public function test_array_associativo()
    {
        $errors = array(array('Code' => 126, 'Message' => 'Assoc'));

        $this->assertSame(
            array('[126] Assoc'),
            WC_Braspag_Pagador_API::extract_error_messages($errors)
        );
    }
}
