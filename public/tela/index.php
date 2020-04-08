<?php
require_once __DIR__ . '/../../app/bootstrap.php';

session_start();

if (isset($_GET['hash'])) {
    $_SESSION['tela']['hash'] = $_GET['hash'];
    $_SESSION['tela']['token'] = $_GET['token'];
    header('Location:' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_SESSION['tela']['hash'])) {
    $hash = $_SESSION['tela']['hash'];
    $token = $_SESSION['tela']['token'];
} else {
    header('Location:../');
    exit;
}

use raelgc\view\Template;

// vamos obter a sessao pelo webservice
$sessao = obterSessao($hash, $token);
if (!is_object($sessao)) {
    echo 'Mensagem ao tentar obter sessão: ', $sessao;exit;
}

//print_r($sessao); //exit;

if (isset($sessao->status) && $sessao->status == 'erro') {
    $tpl = new Template(__DIR__ . '/../../template/erro.html');
    $tpl->msg = $sessao->msg;
    $tpl->block('block_msg');
    $tpl->show();
    exit;
}

$sessao->token = $token;
$tpl = new Template(__DIR__ . '/../../template/tela_index.html');

$tpl->S = $sessao;
if (!empty($sessao->msg)) {
    $tpl->msg = $sessao->msg;
    $tpl->block('block_msg');

} else {
    //print_r($sessao->em_tela); //exit;
    $tpl->V = $sessao->em_tela;
    if (!empty($sessao->em_tela->alternativas)) {
        foreach ($sessao->em_tela->alternativas as $a) {
            $tpl->alternativa = $a->texto;
            $tpl->block('block_alternativa');
        }
    }
    if (!empty($sessao->em_tela->respostas)) {
        foreach ($sessao->em_tela->respostas as $r) {
            $tpl->R = $r;
            $tpl->block('block_resposta');
        }
    }
    if ($sessao->em_tela->estado == 'Em votação' or
        $sessao->em_tela->estado == 'Em pausa' or
        $sessao->em_tela->estado == 'Resultado') {
        $tpl->block('block_computados');
    }

    $tpl->block('block_votacao');
}

$tpl->show();