<?php

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do cliente HTTP server-to-server MPI v3
 * (WC_Braspag_Mpi_V3_Client), cobrindo Épico 0 (helper de endpoint) e
 * Épico 1 (auth/init/enroll/validate, token por sessão 3DS sem cache,
 * erros 400/401, e ausência de segredos em log — Story 1.2/1.3).
 */
class WC_Braspag_Mpi_V3_Client_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WC_Braspag_Test_Http_Mock::reset();
        WC_Braspag_Test_Transients::reset();
        WC_Logger_Fake::reset();
    }

    protected function settings($overrides = array())
    {
        return array_merge(
            array(
                'test_mode' => 'yes',
                'auth3ds20_oauth_authentication_client_id' => 'client-id-123',
                'auth3ds20_oauth_authentication_client_secret' => 'super-secret-value',
                'establishment_code' => '1111111111',
                'merchant_name' => 'LOJA DE TESTE',
                'mcc' => '5999',
            ),
            $overrides
        );
    }

    protected function json_response($status, $body_array, $headers = array())
    {
        return array(
            'response' => array('code' => $status, 'message' => ''),
            'body' => json_encode($body_array),
            'headers' => $headers,
        );
    }

    /** ---------------------------------------------------------------
     * Épico 0 — helper de endpoint
     * --------------------------------------------------------------- */

    public function test_endpoint_base_retorna_sandbox_quando_test_mode_yes()
    {
        $this->assertSame(
            'https://mpisandbox.braspag.com.br/v3/',
            WC_Braspag_Mpi_V3_Client::get_endpoint_base('yes')
        );
    }

    public function test_endpoint_base_retorna_producao_quando_test_mode_no()
    {
        $this->assertSame(
            'https://mpi.braspag.com.br/v3/',
            WC_Braspag_Mpi_V3_Client::get_endpoint_base('no')
        );
    }

    /** ---------------------------------------------------------------
     * auth/token
     * --------------------------------------------------------------- */

    public function test_create_access_token_sucesso_chama_endpoint_correto_com_basic_auth()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function ($url, $args) {
            $this->assertSame('https://mpisandbox.braspag.com.br/v3/auth/token', $url);
            $this->assertSame('POST', $args['method']);
            $this->assertStringStartsWith('Basic ', $args['headers']['Authorization']);
            return $this->json_response(200, array('access_token' => 'tok_abcdef123456', 'expires_in' => 1200));
        });

        $token = WC_Braspag_Mpi_V3_Client::create_access_token($this->settings());

        $this->assertSame('tok_abcdef123456', $token);
        $this->assertCount(1, WC_Braspag_Test_Http_Mock::$requests);
    }

    public function test_create_access_token_envia_json_com_dados_do_estabelecimento()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function ($url, $args) {
            $this->assertSame('application/json; charset=UTF-8', $args['headers']['Content-Type']);
            $this->assertIsString($args['body'], 'Corpo deveria ser JSON serializado, não array form-urlencoded.');

            $decoded = json_decode($args['body'], true);
            $this->assertSame('1111111111', $decoded['EstablishmentCode']);
            $this->assertSame('LOJA DE TESTE', $decoded['MerchantName']);
            $this->assertSame('5999', $decoded['MCC']);
            $this->assertArrayNotHasKey('grant_type', $decoded, 'auth/token da v3 não usa grant_type (isso é do OAuth2 client_credentials do v2).');

            return $this->json_response(200, array('access_token' => 'tok_json_ok', 'expires_in' => 1200));
        });

        $token = WC_Braspag_Mpi_V3_Client::create_access_token($this->settings());

        $this->assertSame('tok_json_ok', $token);
    }

    public function test_create_access_token_lanca_excecao_quando_dados_do_estabelecimento_ausentes()
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_V3_Client::create_access_token($this->settings(array(
            'establishment_code' => '',
        )));
    }

    /**
     * O token do MPI v3 é vinculado a uma única sessão 3DS (o JWT carrega o
     * ReferenceId), então NÃO pode ser cacheado/reaproveitado: reusar um
     * token num segundo `3ds/init` faz a Cielo responder HTTP 409. Este
     * teste trava esse comportamento — cada chamada precisa bater na API.
     */
    public function test_create_access_token_nao_cacheia_token_entre_chamadas()
    {
        $call_count = 0;
        WC_Braspag_Test_Http_Mock::set_handler(function () use (&$call_count) {
            $call_count++;
            return $this->json_response(200, array('access_token' => 'tok_' . $call_count, 'expires_in' => 86399));
        });

        $settings = $this->settings();

        $first = WC_Braspag_Mpi_V3_Client::create_access_token($settings);
        $second = WC_Braspag_Mpi_V3_Client::create_access_token($settings);

        $this->assertNotSame($first, $second, 'Cada chamada deve criar um token novo (um token = uma sessão 3DS).');
        $this->assertCount(2, WC_Braspag_Test_Http_Mock::$requests, 'Não deve haver cache: cada chamada bate em auth/token.');
    }

    public function test_create_access_token_lanca_excecao_quando_credenciais_ausentes()
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_V3_Client::create_access_token($this->settings(array(
            'auth3ds20_oauth_authentication_client_secret' => '',
        )));
    }

    public function test_create_access_token_erro_400_lanca_excecao_amigavel()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return $this->json_response(400, array(
                array('Code' => 123, 'Message' => 'establishmentCode is required'),
            ));
        });

        try {
            WC_Braspag_Mpi_V3_Client::create_access_token($this->settings());
            $this->fail('Esperava WC_Braspag_Exception para HTTP 400.');
        } catch (WC_Braspag_Exception $e) {
            $this->assertStringContainsString('establishmentCode is required', $e->getLocalizedMessage());
            $this->assertStringNotContainsString('super-secret-value', $e->getMessage());
            $this->assertStringNotContainsString('super-secret-value', $e->getLocalizedMessage());
        }
    }

    public function test_create_access_token_erro_401_lanca_excecao_amigavel()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return array(
                'response' => array('code' => 401, 'message' => 'Unauthorized'),
                'body' => '',
                'headers' => array(),
            );
        });

        try {
            WC_Braspag_Mpi_V3_Client::create_access_token($this->settings());
            $this->fail('Esperava WC_Braspag_Exception para HTTP 401.');
        } catch (WC_Braspag_Exception $e) {
            $this->assertStringContainsString('invalid or has expired', $e->getLocalizedMessage());
        }
    }

    /** ---------------------------------------------------------------
     * init / enroll / validate
     * --------------------------------------------------------------- */

    /**
     * O token da sessão 3DS é criado por `create_access_token()` e passado
     * explicitamente para init/enroll/validate (não há mais cache interno).
     */
    const SESSION_TOKEN = 'session-bound-token';

    public function test_init_sucesso_retorna_reference_id_e_token()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function ($url, $args) {
            $this->assertSame('https://mpisandbox.braspag.com.br/v3/3ds/init', $url);
            $this->assertSame('Bearer ' . self::SESSION_TOKEN, $args['headers']['Authorization']);
            $body = json_decode($args['body'], true);
            $this->assertSame('12345', $body['orderNumber']);
            // A resposta real da Cielo usa PascalCase (confirmado no sandbox).
            return $this->json_response(200, array('ReferenceId' => 'ref-1', 'Token' => 'jwt-session-token'));
        });

        $result = WC_Braspag_Mpi_V3_Client::init('12345', $this->settings(), self::SESSION_TOKEN);

        $this->assertSame('ref-1', $result->ReferenceId);
        $this->assertSame('jwt-session-token', $result->Token);
    }

    public function test_init_erro_400_lanca_excecao_sem_expor_payload_cru()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return $this->json_response(400, array(
                array('Code' => 1, 'Message' => 'orderNumber is required'),
            ));
        });

        try {
            WC_Braspag_Mpi_V3_Client::init('', $this->settings(), self::SESSION_TOKEN);
            $this->fail('Esperava WC_Braspag_Exception para HTTP 400 no init.');
        } catch (WC_Braspag_Exception $e) {
            $this->assertStringContainsString('orderNumber is required', $e->getLocalizedMessage());
        }
    }

    public function test_enroll_usa_o_token_da_sessao_e_retorna_status()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function ($url, $args) {
            $this->assertSame('https://mpisandbox.braspag.com.br/v3/3ds/enroll', $url);
            // Precisa ser o MESMO token do init: um token novo aqui faz a
            // Cielo responder 409 (comprovado no sandbox).
            $this->assertSame('Bearer ' . self::SESSION_TOKEN, $args['headers']['Authorization']);
            return $this->json_response(200, array('Status' => 2, 'Challenge' => array('TransactionId' => 'txn-2')));
        });

        $payload = array(
            'orderNumber' => '12345',
            'currency' => 'BRL',
            'card' => array('cardNumber' => '4111111111111111'),
        );

        $result = WC_Braspag_Mpi_V3_Client::enroll($payload, $this->settings(), self::SESSION_TOKEN);

        $this->assertSame(2, $result->Status);
        $this->assertSame('txn-2', $result->Challenge->TransactionId);
    }

    public function test_enroll_erro_401_lanca_excecao()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return array(
                'response' => array('code' => 401, 'message' => 'Unauthorized'),
                'body' => '',
                'headers' => array(),
            );
        });

        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_V3_Client::enroll(array('orderNumber' => '12345'), $this->settings(), self::SESSION_TOKEN);
    }

    public function test_validate_sucesso_retorna_resultado()
    {

        $payload = array(
            'orderNumber' => 'order-3',
            'currency' => 'BRL',
            'totalAmount' => 1000,
            'transactionId' => 'txn-3',
            'card' => array('cardNumber' => '4000000000002503', 'expirationMonth' => '03', 'expirationYear' => '2029'),
        );

        WC_Braspag_Test_Http_Mock::set_handler(function ($url, $args) use ($payload) {
            $this->assertSame('https://mpisandbox.braspag.com.br/v3/3ds/validate', $url);
            $body = json_decode($args['body'], true);
            $this->assertSame($payload['transactionId'], $body['transactionId']);
            $this->assertSame($payload['orderNumber'], $body['orderNumber']);
            return $this->json_response(200, array('Status' => 1, 'Authentication' => array('Eci' => '05', 'Cavv' => 'AAABBBCCC')));
        });

        $result = WC_Braspag_Mpi_V3_Client::validate($payload, $this->settings(), self::SESSION_TOKEN);

        $this->assertSame(1, $result->Status);
        $this->assertSame('AAABBBCCC', $result->Authentication->Cavv);
    }

    public function test_validate_erro_400_lanca_excecao()
    {

        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return $this->json_response(400, array(
                array('Code' => 2, 'Message' => 'transactionId is invalid'),
            ));
        });

        try {
            WC_Braspag_Mpi_V3_Client::validate(array('orderNumber' => 'order-3', 'transactionId' => 'txn-invalid'), $this->settings(), self::SESSION_TOKEN);
            $this->fail('Esperava WC_Braspag_Exception para HTTP 400 no validate.');
        } catch (WC_Braspag_Exception $e) {
            $this->assertStringContainsString('transactionId is invalid', $e->getLocalizedMessage());
        }
    }

    /** ---------------------------------------------------------------
     * Classificação de códigos de retorno (BDD-3DS-028 a 034)
     * --------------------------------------------------------------- */

    /**
     * @dataProvider provider_codigos_de_retorno
     */
    public function test_classify_error_mapeia_codigos_documentados($code, $expected)
    {
        $this->assertSame($expected, WC_Braspag_Mpi_V3_Client::classify_error($code));
    }

    public function provider_codigos_de_retorno()
    {
        return array(
            'campo obrigatorio ausente' => array('101', 'invalid_request'),
            'campo invalido' => array('102', 'invalid_field'),
            'falha tecnica 150' => array('150', 'technical'),
            'falha tecnica 151' => array('151', 'technical'),
            'timeout 152' => array('152', 'technical'),
            'merchant invalido' => array('234', 'configuration'),
            'bandeira sem autenticacao' => array('MPI600', 'not_supported'),
            'challenge obrigatorio nao executado' => array('MPI601', 'not_authenticated'),
            'erro inesperado 900' => array('MPI900', 'unexpected'),
            'erro inesperado 901' => array('MPI901', 'unexpected'),
            'erro inesperado 902' => array('MPI902', 'unexpected'),
            'codigo desconhecido cai em unexpected' => array('9999', 'unexpected'),
            'case-insensitive' => array('mpi601', 'not_authenticated'),
        );
    }

    public function test_classify_error_body_extrai_e_classifica_a_lista_de_erros()
    {
        $body = json_decode(json_encode(array(
            array('Code' => '101', 'Message' => 'orderNumber is required'),
            array('Code' => 'MPI600', 'Message' => 'brand not supported'),
        )));

        $classified = WC_Braspag_Mpi_V3_Client::classify_error_body($body);

        $this->assertCount(2, $classified);
        $this->assertSame('101', $classified[0]['code']);
        $this->assertSame('invalid_request', $classified[0]['classification']);
        $this->assertSame('MPI600', $classified[1]['code']);
        $this->assertSame('not_supported', $classified[1]['classification']);
    }

    /** ---------------------------------------------------------------
     * Story 1.2 — logging seguro (3DS-10/3DS-11)
     * --------------------------------------------------------------- */

    public function test_log_nao_contem_client_secret_nem_access_token_em_texto_puro()
    {
        WC_Braspag_Test_Http_Mock::set_handler(function () {
            return $this->json_response(200, array('access_token' => 'tok_should_be_masked_value', 'expires_in' => 1200));
        });

        WC_Braspag_Mpi_V3_Client::create_access_token($this->settings(array(
            'auth3ds20_oauth_authentication_client_secret' => 'plain-text-secret-should-never-appear',
        )));

        $this->assertNotEmpty(WC_Logger_Fake::$entries, 'Esperava que ao menos uma entrada tivesse sido logada.');

        $full_log = implode("\n", WC_Logger_Fake::$entries);

        $this->assertStringNotContainsString('plain-text-secret-should-never-appear', $full_log);
        $this->assertStringNotContainsString('tok_should_be_masked_value', $full_log);

        // O access_token mascarado deve aparecer só com os 4 primeiros chars + '***'.
        $this->assertStringContainsString('tok_***', $full_log);
    }

    public function test_redact_sensitive_mascara_campos_de_cartao_e_credenciais()
    {
        $redacted = WC_Braspag_Mpi_V3_Client::redact_sensitive(array(
            'card' => array(
                'cardNumber' => '4111111111111111',
                'cvv' => '123',
            ),
            'client_secret' => 'super-secret',
            'orderNumber' => '12345',
        ));

        $this->assertSame('***', $redacted['card']['cardNumber']);
        $this->assertSame('***', $redacted['card']['cvv']);
        $this->assertSame('***', $redacted['client_secret']);
        $this->assertSame('12345', $redacted['orderNumber']);
    }
}
