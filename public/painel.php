<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (isset($_GET['acao']) && $_GET['acao'] == 'nuke') {
    require_once __DIR__ . '/../test/mock_data.php';

    echo '<A href="' . $_SERVER['PHP_SELF'] . '">Clique aqui para retornar</a>';
    exit;
}

if (isset($_GET['acao']) && $_GET['acao'] == 'votar') {
    require_once __DIR__ . '/../test/mock_votacao.php';

    echo '<A href="' . $_SERVER['PHP_SELF'] . '">Clique aqui para retornar</a>';
    exit;
}

use raelgc\view\Template;
use \RedBeanPHP\R as R;

R::selectDatabase('votacao');
R::useFeatureSet('latest');

$sessoes = R::findAll('sessao');

//print_r(R::exportAll($sessoes));

$tpl = new Template(__DIR__ . '/../template/painel.html');
foreach ($sessoes as $sessao) {
    $tokens = $sessao->ownTokenList;
    $counta = $countf = 1;
    foreach ($tokens as $token) {
        switch ($token->tipo) {
            case 'apoio':
                $tpl->token_apoio = $token->token;
                break;
            case 'tela':
                $tpl->token_tela = $token->token;
                break;
            case 'recepcao':
                $tpl->token_recepcao = $token->token;
                break;
            case 'fechada':
                $tpl->token_votacao = $token->token;
                $tpl->count = $countf;
                $countf++;
                $tpl->block('block_fechada');
                break;
            case 'aberta':
                $tpl->token_votacao = $token->token;
                $tpl->count = $counta;
                $counta++;
                $tpl->block('block_aberta');
                break;
        }
    }
    $tpl->S = $sessao;
    $tpl->block('block_sessao');
}

// vamos mostrar as relações entre estados e ações
$estados = R::findAll('estado');
foreach ($estados as $e) {
    $acao_nome = '';

    // vamos expandir as acoes de cada estado
    foreach (explode(',', $e->acoes) as $acao_cod) {
        $acao = R::findOne('acao', 'cod = ?', [intval($acao_cod)]);
        $e_nome = R::getCell('SELECT nome FROM estado WHERE cod = ' . $acao->estado);
        $acao_nome .= $acao->nome . ' (-> ' . $e_nome . ') | ';
    }
    $e->acao_nome = substr($acao_nome, 0, -2);
    $tpl->E = $e;
    $tpl->block('block_estado');
}

$acoes = R::find('acao', "escopo = 'apoio'");
foreach ($acoes as $a) {
    $a->estado = R::getCell('SELECT nome FROM estado WHERE cod = ' . $a->estado);
    $ini = R::getAll('SELECT nome FROM estado WHERE acoes LIKE ?', ["%$a->cod%"]);
    if (count($ini) == 1) {
        $a->estado_ini = $ini[0]['nome'];
    } else {
        $a->estado_ini = '';
        foreach ($ini as $i) {
            $a->estado_ini .= $i['nome'] . ', ';
        }
        $a->estado_ini = substr($a->estado_ini, 0, -2);
    }

    $tpl->A = $a;
    $tpl->block('block_acao');
}

$tpl->show();