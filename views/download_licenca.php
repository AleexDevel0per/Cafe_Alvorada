<?php

// Inclui o script de proteção de autenticação
include("../auth/protect.php");
// Inclui o arquivo de conexão com o banco de dados
include("../config/conexao.php");

// Verifica se o ID da licença foi passado na URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID da licença não fornecido.');
}

$licenca_id = intval($_GET['id']); // Converte para inteiro para segurança

// Prepara a consulta para buscar o caminho do documento
$sql_documento = "SELECT caminho_documento FROM licencas WHERE licenca_id = ?";
$stmt_documento = $mysqli->prepare($sql_documento);

if ($stmt_documento === false) {
    die('Falha na preparação da consulta de documento: ' . $mysqli->error);
}

$stmt_documento->bind_param('i', $licenca_id);
$stmt_documento->execute();
$result_documento = $stmt_documento->get_result();

if ($result_documento->num_rows === 0) {
    die('Licença não encontrada ou documento não associado.');
}

$licenca = $result_documento->fetch_assoc();
$caminho_documento = $licenca['caminho_documento'];

// Verifica se o caminho do documento está vazio
if (empty($caminho_documento)) {
    die('Nenhum documento anexado para esta licença.');
}

// Constrói o caminho absoluto do arquivo
// IMPORTANTE: Certifique-se de que este caminho reflita a estrutura de pastas do seu servidor.
// Se '../uploads/licencas/' for o diretório, o realpath é crucial para a segurança.
$filepath = realpath($caminho_documento);

// Verifica se o arquivo realmente existe no servidor
if (!file_exists($filepath)) {
    die('Arquivo não encontrado no servidor.'); // Mensagem mais genérica para segurança
}

// Obtém o nome do arquivo original para o download
$filename = basename($filepath);
$file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Define o tipo de conteúdo (MIME Type) baseado na extensão do arquivo
$mime_type = 'application/octet-stream'; // Default para tipos desconhecidos
switch ($file_extension) {
    case 'pdf':
        $mime_type = 'application/pdf';
        break;
    case 'jpg':
    case 'jpeg':
        $mime_type = 'image/jpeg';
        break;
    case 'png':
        $mime_type = 'image/png';
        break;
    // Adicione mais tipos MIME se necessário (ex: docx, xlsx, etc.)
}

// Define os cabeçalhos HTTP para forçar o download do arquivo
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Limpa o buffer de saída para evitar problemas de corrupção do arquivo
ob_clean();
flush();

// Lê o arquivo e o envia para o navegador
readfile($filepath);

// Fecha a conexão com o banco de dados
$stmt_documento->close();
$mysqli->close();
exit;

?>