<?php

namespace NFePHP\NFSeEGoverne;

/**
 * Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeEGoverne
 * @copyright NFePHP Copyright (c) 2008-2019
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-egoverne for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Validator;
use NFePHP\NFSeEGoverne\Common\Tools as BaseTools;

class Tools extends BaseTools
{
    const ERRO_EMISSAO = 1;
    const SERVICO_NAO_CONCLUIDO = 2;

    protected $xsdpath;

    /**
     * Constructor
     * Configura variaveis basicas
     *
     * @param string      $config
     * @param Certificate $cert
     */
    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
        $path = realpath(__DIR__ . '/../storage/schemes');
        if (file_exists($this->xsdpath = $path . '/' . $this->config->cmun . '.xsd')) {
            $this->xsdpath = $path . '/' . $this->config->cmun . '.xsd';
        } else {
            $this->xsdpath = $path . '/nfse_v20_08_2015.xsd';
        }
    }

    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=CancelarNfse
     *
     * @param  int $numero
     * @param  int $codigo
     * @return string
     */
    public function cancelarNfse($numero, $codigo = self::ERRO_EMISSAO)
    {
        $pedido = $content = '';
        $operation = 'CancelarNfse';

        $pedido .= "<Pedido>";
        $pedido .=     "<InfPedidoCancelamento>";
        $pedido .=         "<IdentificacaoNfse>";
        $pedido .=             "<Numero>{$numero}</Numero>";
        $pedido .=             "<Cnpj>{$this->config->cnpj}</Cnpj>";
        $pedido .=             "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>";
        $pedido .=             "<CodigoMunicipio>{$this->config->cmun}</CodigoMunicipio>";
        $pedido .=         "</IdentificacaoNfse>";
        $pedido .=         "<CodigoCancelamento>{$codigo}</CodigoCancelamento>";
        $pedido .=     "</InfPedidoCancelamento>";
        $pedido .= "</Pedido>";

        $signed = $this->sign($pedido, 'InfPedidoCancelamento', '');

        $content .= "<CancelarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .= $signed;
        $content .= "</CancelarNfseEnvio>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado por vez.
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=ConsultarLoteRps
     *
     * @param  string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {
        $content = '';
        $operation = 'ConsultarLoteRps';

        $content .= "<ConsultarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     $this->prestador;
        $content .=     "<Protocolo>{$protocolo}</Protocolo>";
        $content .= "</ConsultarLoteRpsEnvio>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Consulta Situação do Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado por vez.
     *
     * ## Possiveis situações de retorno:
     * 1 – Não Recebido
     * 2 – Não Processado
     * 3 – Processado com Erro
     * 4 – Processado com Sucesso
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=ConsultarSituacaoLoteRps
     *
     * @param  string $protocolo
     * @return string
     */
    public function consultarSituacaoLoteRps($protocolo)
    {
        $content = '';
        $operation = 'ConsultarSituacaoLoteRps';

        $content .= "<ConsultarSituacaoLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     $this->prestador;
        $content .=     "<Protocolo>{$protocolo}</Protocolo>";
        $content .= "</ConsultarSituacaoLoteRpsEnvio>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Consulta NFSe emitidas em um periodo e por tomador (SINCRONO)
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=ConsultarNfse
     *
     * @param  $filtro
     *        #Obrigatorio (dataInicial e dataFinal)
     *        #Opcional (numeroNfse, tomador)
     * @return string
     */
    public function consultarNfse($filtro)
    {
        $content = '';
        $operation = 'ConsultarNfse';

        $content .= "<ConsultarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     $this->prestador;

        if (isset($filtro->numeroNfse) && $filtro->numeroNfse !== null) {
            $content .=     "<NumeroNfse>{$filtro->numeroNfse}</NumeroNfse>";
        }

        $content .= "<PeriodoEmissao>";
        $content .=     "<DataInicial>{$filtro->dataInicial}</DataInicial>";
        $content .=     "<DataFinal>{$filtro->dataFinal}</DataFinal>";
        $content .= "</PeriodoEmissao>";

        if (isset($filtro->tomador) && $filtro->tomador !== null) {
            if ($filtro->tomador->cnpj !== null || $filtro->tomador->cpf !== null) {
                $content .= "<Tomador>";
                $content .= "<CpfCnpj>";

                if (isset($filtro->tomador->cnpj)) {
                    $content .= "<Cnpj>{$filtro->tomador->cnpj}</Cnpj>";
                } else {
                    $content .= "<Cpf>{$filtro->tomador->cpf}</Cpf>";
                }

                $content .= "</CpfCnpj>";

                if (isset($filtro->tomador->InscricaoMunicipal) && $filtro->tomador->InscricaoMunicipal !== null) {
                    $content .= "<InscricaoMunicipal>";
                    $content .= $filtro->tomador->InscricaoMunicipal;
                    $content .= "</InscricaoMunicipal>";
                }

                $content .= "</Tomador>";
            }
        }

        $content .= "</ConsultarNfseEnvio>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Consulta NFSe por RPS (SINCRONO)
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=ConsultarNfsePorRps
     *
     * @param  integer $numero
     * @param  string  $serie
     * @param  integer $tipo
     * @return string
     */
    public function consultarNfsePorRps($numero, $serie, $tipo)
    {
        $content = '';
        $operation = "ConsultarNfsePorRps";

        $content .= "<ConsultarNfseRpsEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     "<IdentificacaoRps>";
        $content .=         "<Numero>{$numero}</Numero>";
        $content .=         "<Serie>{$serie}</Serie>";
        $content .=         "<Tipo>{$tipo}</Tipo>";
        $content .=     "</IdentificacaoRps>";
        $content .=     $this->prestador;
        $content .= "</ConsultarNfseRpsEnvio>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=RecepcionarLoteRps
     *
     * @param  array  $arps Array contendo de 1 a 50 RPS::class
     * @param  string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps, $lote)
    {
        $content = $listaRpsContent = '';
        $operation = 'RecepcionarLoteRps';

        $countRps = count($arps);
        if ($countRps > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }

        foreach ($arps as $rps) {
            $xml = $rps->render();
            $xmlsigned = $this->sign($xml, 'InfRps', '');
            $listaRpsContent .= $xmlsigned;
        }

        $content .= "<EnviarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     "<LoteRps>";
        $content .=         "<NumeroLote>{$lote}</NumeroLote>";
        $content .=         "<Cnpj>{$this->config->cnpj}</Cnpj>";
        $content .=         "<InscricaoMunicipal>";
        $content .=         $this->config->im;
        $content .=         "</InscricaoMunicipal>";
        $content .=         "<QuantidadeRps>{$countRps}</QuantidadeRps>";
        $content .=         "<ListaRps>";
        $content .=             $listaRpsContent;
        $content .=         "</ListaRps>";
        $content .=     "</LoteRps>";
        $content .= "</EnviarLoteRpsEnvio>";

        $content = $this->sign($content, 'LoteRps', '');
        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Buscar Usuario (SINCRONO)
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=BuscarUsuario
     *
     * @param  string $cnpj
     * @param  string $imu
     * @return string
     */
    public function buscarUsuario($cnpj, $imu)
    {
        $content = '';
        $operation = 'BuscarUsuario';

        $content .= "<BuscarUsuario xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     "<imu>{$imu}</imu>";
        $content .=     "<cnpj>{$cnpj}</cnpj>";
        $content .= "</BuscarUsuario>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Recepcionar Xml (SINCRONO)
     * Parâmentro (metodo) nome do metodo WS que será chamado.
     * Os valores podem ser : (RecepcionarLoteRps, ConsultarSituacaoLoteRps, ConsultarNfsePorRps,
     *                         ConsultarNfse, ConsultarLoteRps e CancelarNfse)
     * e o Parâmetro (xml) deve ser a mensagem xml a ser enviada.
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=RecepcionarXml
     *
     * @param  string $metodo
     * @param  string $xml
     * @return string
     */
    public function recepcionarXml($metodo, $xml)
    {
        $content = '';
        $operation = 'RecepcionarXml';

        $content .= "<RecepcionarXml xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     "<metodo>{$metodo}</metodo>";
        $content .=     "<xml>{$xml}</xml>";
        $content .= "</RecepcionarXml>";

        Validator::isValid($content, $this->xsdpath);

        return $this->send($content, $operation);
    }

    /**
     * Validar Xml (SINCRONO)
     * Realiza a validação básica de um xml de acordo com o schema xsd
     * https://isscuritiba.curitiba.pr.gov.br/Iss.NfseWebService/nfsews.asmx?op=ValidarXml
     *
     * @param  string $xml
     * @return string
     */
    public function validarXml($xml)
    {
        $content = '';
        $operation = 'ValidarXml';

        $content .= "<ValidarXml xmlns=\"{$this->wsobj->msgns}\">";
        $content .=     "<xml>$xml</xml>";
        $content .= "</ValidarXml>";

        return $this->send($content, $operation);
    }
}
