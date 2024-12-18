<?php

namespace Uspdev\Votacao\View;

use Uspdev\Votacao\View\SessaoPhp as SS;

class Run
{
    // tela inicial para quem entra com o link encurtado
    public static function hashGet($hash)
    {
        $_SESSION['votacao'] = [];
        $sessao = SELF::obterSessao($hash, '');

        $tpl = new Template('token.html');
        $tpl->S = $sessao;

        if (!empty($_SESSION['msg'])) {
            //print_r(json_decode($_SESSION['msg']));exit;
            $tpl->M = json_decode($_SESSION['msg']);
            $tpl->block('block_M');
            unset($_SESSION['msg']);
        }

        $tpl->show();
    }

    // post do token
    public static function hashPost($hash, $data)
    {
        // vamos verificar o token enviado e obter os dados ou retornar
        // para a tela de solicitação do token com alguma mensagem
        if (!empty($data->token)) {
            $token = $data->token;
            SELF::hashToken($hash, $token);
        } else {
            header('Location: ' . getenv('WWWROOT') . '/' . $hash);
            exit;
        }
    }

    // tela inicial de quem entra com qrcode
    public static function hashToken($hash, $token)
    {
        $sessao = SELF::obterSessao($hash, $token);
        $perfil = $sessao->token->tipo;

        if ($perfil == 'fechada' || $perfil == 'aberta') {
            $perfil = 'votacao';
        }

        $token = [];
        $token[$sessao->token->tipo]['token'] = $sessao->token->token;

        SS::atribuir('hash', $hash);
        SS::atribuir($perfil, $token);
        // print_r($_SESSION);
        // exit;
        header('Location: ' . getenv('WWWROOT') . '/' . $perfil);
        exit;
    }

    public static function votacaoGet()
    {
        // vamos ver se o token já está na session
        list($hash, $token) = SS::verificaSessao('votacao');
        if (empty($token)) {
            header('Location:' . $hash);
            exit;
        }

        // vamos tentar votar com os tokens existentes
        foreach ($token as $t) {
            $sessao = SELF::obterSessao($hash, $t);
            if (empty($sessao->msg) && isset($sessao->votacoes)) {
                $token = $t;
                break;
            }
        }

        $tpl = new Template('votacao_index.html');
        $tpl->S = $sessao;

        // print_r($token);
        // exit;

        // se veio msg é porque houve algum problema
        if (!empty($sessao->msg)) {
            $tpl->msg = $sessao->msg;
            $tpl->block('block_msg');
            $tpl->show();
            exit;
        }

        // se não veio msg, vamos continuar
        $votacoes = $sessao->votacoes;

        if (!empty($msg = SS::getDel('votacao_msg'))) {
            // aqui trata o retorno do post
            $msg = json_decode($msg);


            if ($msg->status == 'ok') {
                $respostas = $msg->data;

                foreach ($respostas as $resposta) {
                    $tpl->resposta = $resposta;
                    $tpl->block('block_resposta');
                }

                $tpl->block('block_sucesso');
            } else {
                $tpl->error = $msg->msg;
                $tpl->block('block_erro');
            }
        } else {
            foreach ($votacoes as $votacao) {
                $tpl->votacao = $votacao;
                $alternativas = $votacao->alternativas;

                foreach ($alternativas as $alternativa) {
                    $tpl->alternativa = $alternativa;
                    $tpl->block('block_alternativa');
                }

                $tpl->block('block_votacao');
            }

            $tpl->block('block_votacoes');
            // aqui mostra o form de votacao
            // $tpl->V = $v;
            // $form = new Form($v);
            // $tpl->form = $form->render();
            // $tpl->block('block_form');
        }

        $tpl->show();
    }

    public static function votacaoPost($data)
    {
        list($hash, $token) = SS::verificaSessao('votacao');

        foreach ($token as $t) {
            $sessao = SELF::obterSessao($hash, $t);
            if (empty($sessao->msg) && isset($sessao->votacoes)) break;
        }

        //$sessao = SELF::obterSessao($hash, $token);

        // post de formulario
        if (isset($data->acao)) {
            $data = $_POST;
            $res = Api::post($hash, $sessao->token->token, $data);

            SS::set('votacao_msg', json_encode($res));
            //$_SESSION['msg'] = json_encode($res); //exit;
            header('Location:' .  getenv('WWWROOT') . '/votacao');
            exit;
        }
    }


    public static function apoioGet()
    {
        list($hash, $token) = SS::verificaSessao('apoio');

        $sessao = SELF::obterSessao($hash, $token);

        // acoes para a rota de apoio
        if (isset($_GET['acao'])) {
            $data = [
                'acao' => $_GET['acao']
            ];

            if (isset($_GET['votacao_id'])) {
                $data['votacao_id'] = $_GET['votacao_id'];
            }

            $res = Api::post($hash, $token, $data);
            // temos de devolver res de alguma forma se houver erro
            //print_r($res);exit;
            header('Location: ' . getenv('WWWROOT') . '/apoio');
            exit;
        }

        //print_r($sessao);exit;
        $tpl = new Template('apoio_index.html');

        $tpl->S = $sessao;

        foreach ($sessao->votacoes as $v) {
            $v->tipo = $v->tipo == 'aberta' ? 'Voto aberto' : 'Voto fechado';

            if ($v->estado === 'Fechada') {
                $tpl->v = $v;
                $tpl->block('block_votacao_fechada');
            }

            if ($v->estado === 'Em exibição') {
                $tpl->v = $v;
                $tpl->block('block_votacao_em_exibicao');
            }

            if ($v->estado === 'Em votação') {
                $tpl->v = $v;
                $tpl->block('block_em_votacao');
            }

            if ($v->estado === 'Em pausa') {
                $tpl->v = $v;
                $tpl->block('block_em_pausa');
            }

            if ($v->estado === 'Resultado') {
                $tpl->v = $v;
                $tpl->block('block_resultado');
            }

            if ($v->estado === 'Finalizado') {
                $tpl->v = $v;
                $tpl->block('block_finalizado');
            }

            $v->estadoclass = SELF::getEstadoClass($v->estado);
            $v->accordion = new \stdClass();
            if ($v->estado == 'Fechada' or $v->estado == 'Finalizado') {
                $v->accordion->mostrar = '';
                $v->accordion->disabled = 'disabled';
                $v->accordion->border = '';
            } else {
                $v->accordion->mostrar = 'show';
                $v->accordion->disabled = '';
                $v->accordion->border = 'border-primary mb-3';
            }
            $tpl->V = $v;

            foreach ($v->acoes as $acao) {
                $tpl->cod = $acao->cod;
                $tpl->acao = $acao->nome;
                $tpl->block('block_acao');
            }
            $tpl->block('block_votacao');
        }
        $tpl->show();
    }
    public static function apoioPost($dataObj)
    {
        list($hash, $token) = SS::verificaSessao('apoio');
        //$sessao = SELF::obterSessao($hash, $token);
        switch ($dataObj->acao) {
            case 'instantaneo':
                $data['acao'] = '9';
                $data['texto'] = $dataObj->texto;
                //print_r($data);

                $res = Api::post($hash, $token, $data);
                //var_dump($res);exit;
                break;
        }

        header('Location: ' . getenv('WWWROOT') . '/apoio');
        exit;
    }

    public static function painelGet()
    {
        list($hash, $token) = SS::verificaSessao('painel');

        $sessao = SELF::obterSessao($hash, $token);

        $tpl = new Template('painel_index.html');
        $tpl->block('block_topo_img');

        // se não houver votação
        $tpl->S = $sessao;
        if (!empty($sessao->msg)) {
            $tpl->msg = $sessao->msg;
            $tpl->block('block_msg');
            $tpl->show();
            exit;
        }

        $votacoes = $sessao->votacoes;

        foreach ($votacoes as &$votacao) {
            $tpl->votacao = $votacao;

            $votacao->tipo = $votacao->tipo == 'aberta' ? 'Voto aberto' : 'Voto fechado';

            if ($votacao->estado === 'Em votação') {
                $votacao->estado_class = self::getEstadoClass($votacao->estado);
                $tpl->block('block_estado');
            }

            // exibe os votos
            foreach ($votacao->votos as $voto) {
                $tpl->voto = $voto;
                $tpl->block('block_voto');
            }

            // exibe as respostas
            foreach ($votacao->respostas as $r) {
                $tpl->R = $r;
                $tpl->block('block_r');
            }

            $tpl->block('block_total_votos');
            $tpl->block('block_votacao');
        }

        $tpl->show();

        // $v = $sessao->em_tela;
        // $v->tipo = $v->tipo == 'aberta' ? 'Voto aberto' : 'Voto fechado';

        // // vamos formatar a apresentação do estado
        // $v->estado_class = SElF::getEstadoClass($v->estado);

        // $tpl->V = $v;

        // if ($v->estado == 'Resultado') {

        //     if (!empty($v->respostas)) {
        //         foreach ($v->respostas as $r) {
        //             $tpl->R = $r;
        //             $tpl->block('resultado_resposta');
        //         }
        //     }

        //     //vamos mostrar o total de votos computados
        //     $tpl->block('block_computados');

        //     if (!empty($v->votos) && $v->tipo == 'Voto aberto') {
        //         $i = 0;
        //         if (count($v->votos) > 10) {
        //             $dividir = intdiv(count($v->votos), 3);
        //         } else {
        //             $dividir = count($v->votos) - 1;
        //         }
        //         // ordenando por apelido
        //         // usort($v->votos, function ($a, $b) {
        //         //     return strcmp(strtoupper($a->apelido), strtoupper($b->apelido));
        //         // });
        //         foreach ($v->votos as $voto) {
        //             $tpl->voto = $voto;
        //             $tpl->block('resultado_voto');
        //             if ($dividir == $i or $dividir * 2 == $i) {
        //                 $tpl->block('block_coluna');
        //             }
        //             $i++;
        //         }
        //         $tpl->block('block_coluna');
        //     }

        //     $tpl->block('block_resultado');
        // } elseif ($v->estado == 'Em exibição' || $v->estado == 'Em votação' || $v->estado == 'Em pausa') {

        //     if ($v->estado == 'Em votação') {
        //         $tpl->block('block_em_votacao');
        //     }
        //     $tpl->block('block_computados');

        //     if (!empty($v->descricao)) {
        //         $tpl->block('exibicao_descricao');
        //     }

        //     if (!empty($v->alternativas)) {
        //         foreach ($v->alternativas as $a) {
        //             $tpl->alternativa = $a->texto;
        //             $tpl->block('exibicao_alternativa');
        //         }
        //     }
        //     $tpl->block('block_exibicao');
        // }

        // $tpl->show();
    }

    public static function recepcao()
    {
        list($hash, $token) = SS::verificaSessao('recepcao');

        $sessao = SELF::obterSessao($hash, $token);

        //print_r($sessao);exit;
        $tpl = new Template('recepcao_index.html');
        $tpl->S = $sessao;
        $tpl->show();
    }

    public static function hashTicket($hash, $ticket)
    {
        SS::set('hash', $hash);
        // se o novo ticket for diferente do existente (ou se não houver existente)
        if ($ticket != SS::get('ticket')) {
            SS::set('ticket', $ticket);
            SS::unset('token_fechado');
        }
        header('Location: ' . getenv('WWWROOT') . '/ticket');
        exit;
    }

    public function ticket()
    {
        $hash = SS::get('hash');
        $ticket = SS::get('ticket');

        // se limpou a session vamos mostrar uma mensagem
        if (empty($hash) || empty($ticket)) {
            $tpl = new Template('ticket_erro.html');
            $tpl->show();
            exit;
        }

        $sessao = Api::obterSessao($hash, $ticket);
        // a sessao está correta mas o ticket já foi utilizado
        if (empty($sessao->token) && empty(SS::get('token_fechado'))) {
            $tpl = new Template('ticket_erro.html');
            $tpl->S = $sessao;
            $tpl->block('block_sessao');
            $tpl->show();
            exit;
        }

        $endpoint = '/run/' . $hash . '/' . $ticket;

        // acoes post
        if ($this->method == 'POST') {
            $token = API::send($endpoint, $this->data->getData());
            if (!empty($token)) {
                SS::set('token_fechado', $token);
            }
            header('Location:' . $_SERVER['REDIRECT_URL']);
            exit;
        }

        // acoes get
        if (!empty($this->query->acao)) {
            switch ($this->query->acao) {
                case 'obterPdf':
                    $data['acao'] = $this->query->acao;
                    $token = SS::get('token_fechado');
                    $data['token'] = $token->token;
                    $ret = API::send($endpoint, $data);
                    //print_r($token_base64);exit;
                    $token_pdf = base64_decode($ret->pdf);
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: inline; filename="token_fechado.pdf"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . strlen($token_pdf));
                    echo $token_pdf;
                    exit;
                    break;

                case 'finalizar':
                    SS::destroy();
                    header('Location:' . $_SERVER['REDIRECT_URL']);
                    exit;
                    break;
            }
        }

        // tudo certo, vamos motrar as telas
        $tpl = new Template('ticket_index.html');
        $tpl->S = $sessao;
        if ($token = SS::get('token_fechado')) {
            // se já pegou o token vamos mostrar
            $tpl->T = json_decode(json_encode($token));
            $tpl->block('block_token');
        } else {
            // se nao pegou o token vamos pedir a confirmação
            $tpl->block('block_confirm');
        }
        $tpl->show();
    }

    protected static function getEstadoClass($estado)
    {
        switch ($estado) {
            case 'Em exibição':
                return 'badge-primary';
                break;
            case 'Em votação':
                return 'badge-success';
                break;
            case 'Em pausa':
                return 'badge-warning';
                break;
            case 'Resultado':
                return 'badge-primary';
                break;
            case 'Finalizado':
                return 'badge-danger';
                break;
            case 'Fechada':
                return 'badge-success';
                break;
        }
    }

    protected static function obterSessao($hash, $token)
    {
        $sessao = Api::obterSessao($hash, $token);

        // se a sessão não existir e vir erro fatal
        if (isset($sessao->status) && $sessao->status == 'erro') {
            $tpl = new Template('erro.html');
            $tpl->msg = $sessao->msg;
            $tpl->show();
            exit;
        }
        return $sessao;
    }

    // protected static function template($addFile)
    // {
    //     $tpl = new Template(TPL . '/main_template.html');
    //     $tpl->wwwroot = getenv('WWWROOT');

    //     $tpl->addFile('corpo', TPL . '/' . $addFile);
    //     return $tpl;
    // }

    // protected static function verificaSessao($tipo)
    // {
    //     if (isset($_SESSION[$tipo]['hash'])) {
    //         $hash = $_SESSION[$tipo]['hash'];
    //         $token = $_SESSION[$tipo]['token'];
    //         return [$hash, $token];
    //     } else {
    //         // vamos voltar ao inicio
    //         $tpl = new Template('erro_sem_sessao.html');
    //         $tpl->show();
    //         exit;
    //     }
    // }
}
