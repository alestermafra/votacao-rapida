<?php

namespace Uspdev\Votacao\Controller;

use \RedBeanPHP\R as R;
use \Uspdev\Votacao\Model\Token;
use \Uspdev\Votacao\Model\Votacao;
use \Uspdev\Votacao\Model\Sessao;

class Run
{
    public static function run($hash, $token = '')
    {
        //$query = \Flight::request()->query;
        //$files = \Flight::request()->files;
        R::selectDatabase('votacao');

        // verifica o hash e carrega os dados da sessão
        $sessao = Sessao::obterPorHash($hash);
        if (empty($sessao)) {
            return ['status' => 'erro', 'msg' => 'Esta sessão de votação não existe.'];
        }

        // se nao foi enviado token é porque vai digitar manualmente.
        // vamos manda as informações básicas da sessão
        if (empty($token)) {
            return $sessao;
        }

        // se o tamanho do token == 25, então é ticket
        if (strlen($token) == 25) {
            $sessao->token = R::findOne('token', 'sessao_id = ? and ticket = ?', [$sessao->id, $token]);
            return SELF::ticket($sessao);
        }

        $sessao->token = R::findOne('token', 'sessao_id = ? and token = ?', [$sessao->id, $token]);
        // verifica se o token pertence à sessão
        if (empty($sessao->token)) {
            return ['status' => 'erro', 'msg' => 'Token inválido para essa sessão'];
        }

        $method = \Flight::request()->method;

        // tudo verificado, podemos carregar os dados de acordo com o token
        switch ([$sessao->token->tipo, $method]) {
            case ['aberta', 'GET']:
            case ['fechada', 'GET']:
                return SELF::votacaoGet($sessao);
                break;
            case ['aberta', 'POST']:
            case ['fechada', 'POST']:
                return SELF::votacaoPOST($sessao);
                break;
            case ['apoio', 'GET']:
                return SELF::apoioGet($sessao);
                break;
            case ['apoio', 'POST']:
                return SELF::apoioPost($sessao);
                break;
            case ['painel', 'GET']:
                return SELF::painel($sessao);
                break;
            case ['recepcao', 'GET']:
                return SELF::recepcao($sessao);
                break;
        }
        return false;
    }

    protected static function ticket($sessao)
    {
        $method = \Flight::request()->method;
        if ($method == 'POST') {
            $data = \Flight::request()->data;
            if ($data->acao == 'obterTokenFechado' && $data->agree == 'sim') {
                $token_fechado = Token::adicionarTokenFechado($sessao);

                // invalidando o ticket
                $token = $sessao->token;
                $token->ticket = '';
                R::store($token);

                return $token_fechado;
            }
            if ($data->acao == 'obterPdf') {
                $token = R::findOne('token', 'sessao_id = ? and token = ?', [$sessao->id, $data->token]);
                $ret['pdf'] = base64_encode(Token::pdfTokenFechado($sessao, $token));
                return $ret;
            }
        } else {
            return $sessao;
        }
    }

    protected static function votacaoGet($sessao)
    {
        // primeiro vamos ver se tem alguma votação com estado 'Em votação'
        $ret = Votacao::obterEmVotacao($sessao);

        $sessao->msg = $ret['msg'];
        $sessao->votacoes = $ret['votacoes'];

        return SELF::limparSaida($sessao);
    }

    protected static function votacaoPost($sessao)
    {
        // primeiro vamos ver se tem alguma votação com estado 'Em votação'
        $ret = Votacao::obterEmVotacao($sessao);
        $msg = $ret['msg'];
        $votacao = $ret['votacao'];
        $data = \Flight::request()->data;

        if ($votacao == null) {
            $ret = ['status' => 'erro', 'msg' => $msg . ', acao=' . $data->acao];
            return $ret;
        };

        switch (intval($data->acao)) {
            case '8':
                //vamos ver se o voto veio para votação correta
                if (
                    $votacao->id == $data->votacao_id &&
                    !empty($data->alternativa_id) &&
                    in_array($data->alternativa_id, array_column($votacao->ownAlternativaList, 'id'))
                ) {
                    $data->user_agent = \Flight::request()->user_agent;
                    $resposta = Votacao::computarVoto($sessao, $votacao, $data);
                    return ['status' => 'ok', 'data' => $resposta];
                }

                return ['status' => 'erro', 'msg' => 'Voto mal formado para ação ' . $data['acao']];
                break;
        }

        return ['status' => 'erro', 'msg' => 'Ação inválida: ' . $data['acao']];
    }

    protected static function apoioGet($sessao)
    {
        // vamos carregar as votações
        $sessao->votacoes = $sessao->with('ORDER BY ordem ASC')->ownVotacaoList;
        foreach ($sessao->votacoes as &$votacao) {

            // vamos pegar o nome do estado
            $estado = R::findOne('estado', 'cod = ?', [$votacao->estado]);
            $votacao->estado = $estado->nome;

            // vamos obter as ações possíveis para esse estado
            $sql = 'SELECT cod, nome FROM acao WHERE cod IN (';
            foreach (explode(',', $estado->acoes) as $l) {
                $sql .= intval($l) . ',';
            }
            $sql = substr($sql, 0, -1) . ')';

            $votacao->acoes = R::getAll($sql);
        }
        return SELF::limparSaida($sessao);
    }

    protected static function apoioPost($sessao)
    {
        $data = \Flight::request()->data;

        $acao = $data['acao'] ?? null;

        if (in_array($acao, ['iniciar', 'pausar', 'retomar', 'mostrar_resultado', 'finalizar'])) {
            switch ($acao) {
                case 'iniciar':
                    R::exec('update votacao set estado = 2 where sessao_id = :sessao_id and estado = 1', [':sessao_id' => $sessao->id]);
                    return ['msg' => 'Votações iniciadas'];

                case 'pausar':
                    R::exec('update votacao set estado = 3 where sessao_id = :sessao_id and estado = 2', [':sessao_id' => $sessao->id]);
                    return ['msg' => 'Votações pausadas'];

                case 'retomar':
                    R::exec('update votacao set estado = 2 where sessao_id = :sessao_id and estado = 3', [':sessao_id' => $sessao->id]);
                    return ['msg' => 'Votações retomadas'];

                case 'mostrar_resultado':
                    R::exec('update votacao set estado = 4 where sessao_id = :sessao_id and estado = 3', [':sessao_id' => $sessao->id]);
                    return ['msg' => 'Mostrando resultado'];

                case 'finalizar':
                    R::exec('update votacao set estado = 5 where sessao_id = :sessao_id and estado = 4', [':sessao_id' => $sessao->id]);
                    return ['msg' => 'Votações finalizadas'];
            }
        } else {
            // se ação não estiver dentro das ações predefinidas, vamos abortar
            if (!$acao = R::findOne('acao', "cod = ?", [$data['acao']])) {
                return ['msg' => 'Ação inválida: ' . $data['acao']];
            }

            // vmos carregar a votação
            $votacao = Votacao::obter($data->votacao_id);

            switch (intval($data['acao'])) {
                case '0': // mostrar na tela
                    $votacao->estado = $acao->estado;
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '1': // fechar
                    $votacao->estado = $acao->estado;
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '2': // iniciar votação
                    // limpar votos existentes, se houver
                    Votacao::limparVotosExistentes($votacao);

                    $votacao->estado = $acao->estado;
                    $votacao->data_ini = date('Y-m-d H:i:s');
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '3': // pausar
                    $votacao->estado = $acao->estado;
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '4': //Mostrar resultado
                    $votacao->estado = $acao->estado;
                    if (empty($votacao->data_fim)) {
                        $votacao->data_fim = date('Y-m-d H:i:s');
                        // vamos exportar para um arquivo externo somente da primeira vez
                        Votacao::exportar($votacao);
                    }

                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '5': // continuar
                    $votacao->estado = $acao->estado;
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '6': //Reiniciar
                    if ($votacao->estado == 5) {
                        return ['msg' => 'Impossível reiniciar depois de encerrada'];
                    }

                    // limpar votos existentes, se houver
                    Votacao::limparVotosExistentes($votacao);

                    $votacao->estado = $acao->estado;
                    $votacao->data_ini = date('Y-m-d H:i:s');
                    R::store($votacao);
                    return ['msg' => $acao->msg];
                    break;

                case '7': // finalizar
                    $votacao->estado = $acao->estado;
                    R::store($votacao);

                    return ['msg' => $acao->msg];
                    break;

                case '9': // criar instantaneo
                    // aqui não precisa de $votacao, pois vai criar uma nova
                    $votacao = Votacao::novoInstantaneo($sessao, trim($data['texto']));
                    //$votacao->sessao_id = $sessao->id;
                    //R::store($votacao);

                    return ['status' => 'ok', 'msg' => 'Votação adicionada com sucesso'];
                    break;

                case '10':
                    R::trash($votacao);
                    return ['status' => 'ok', 'msg' => 'Votação excluída com sucesso'];
                    break;
            }
        }
    }

    // todo:-------------
    protected static function recepcao($sessao)
    {
        unset($sessao->link);
        return $sessao;
    }

    protected static function painel($sessao)
    {
        // vamos ver se tem alguma votação para ser exibida na tela
        $votacoes = $sessao->withCondition(' estado in (1, 2, 3, 4) ')->ownVotacaoList;

        foreach ($votacoes as &$votacao) {
            $votacao->alternativas = $votacao->ownAlternativaList;
            $votacao->respostas = Votacao::listarAlternativa($votacao);
            $votacao->votos = Votacao::listarResposta($votacao->id);
            $votacao->computados = Votacao::contarResposta($votacao);
            $votacao->estado = R::getCell('SELECT nome FROM estado WHERE cod = :cod', [':cod' => $votacao->estado]);
        }

        if (count($votacoes) == 0) {
            $sessao->msg = 'Sem votação aberta';
            SELF::limparSaida($sessao);
            return SELF::limparSaida($sessao);
        }

        $sessao->votacoes = $votacoes;
        return SELF::limparSaida($sessao);
    }

    protected static function limparSaida($sessao)
    {
        unset($sessao->id);
        //unset($sessao->hash);
        unset($sessao->estado);
        unset($sessao->tipo_votacao);
        unset($sessao->link);
        unset($sessao->nomes_json);
        unset($sessao->link_qrcode);
        //unset($sessao->token);
        unset($sessao->ownVotacao);

        //unset($sessao->render_form->id);
        unset($sessao->render_form->estado);
        unset($sessao->render_form->sessao_id);
        unset($sessao->render_form->ownAlternativa);

        unset($sessao->em_tela->id);
        //unset($sessao->em_tela->estado);
        unset($sessao->em_tela->sessao_id);
        unset($sessao->em_tela->ownAlternativa);
        return $sessao;
    }
}
