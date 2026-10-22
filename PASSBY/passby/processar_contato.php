<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

function redirectWithStatus($status)
{
    header('Location: contato.html?status=' . rawurlencode($status), true, 303);
    exit;
}

if (!empty($_POST['honeypot'])) {
    redirectWithStatus('sent');
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$assunto = trim($_POST['assunto'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');

if (
    $nome === '' || strlen($nome) > 120 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
    $assunto === '' || strlen($assunto) > 160 ||
    $mensagem === '' || strlen($mensagem) > 5000 ||
    preg_match('/[\r\n]/', $email)
) {
    redirectWithStatus('error');
}

$recipient = 'geral@passby.pt';
$mailSubject = 'Contacto PassBy: ' . str_replace(["\r", "\n"], '', $assunto);
$body = '<h2>Nova mensagem através do PassBy</h2>'
    . '<p><strong>Nome:</strong> ' . htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
    . '<p><strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
    . '<p><strong>Assunto:</strong> ' . htmlspecialchars($assunto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
    . '<p><strong>Mensagem:</strong><br>' . nl2br(htmlspecialchars($mensagem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
$headers = [
    'From: PassBy <geral@passby.pt>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8'
];

if (!mail($recipient, $mailSubject, $body, implode("\r\n", $headers))) {
    redirectWithStatus('unavailable');
}

redirectWithStatus('sent');
