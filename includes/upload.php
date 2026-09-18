<?php
declare(strict_types=1);

/**
 * Faz upload seguro de uma imagem, redimensiona e salva em $destDir.
 *
 * @param array  $file     Elemento de $_FILES['campo']
 * @param string $destDir  Diretório de destino dentro de /uploads/ (sem barra final)
 * @param int    $maxPx    Largura máxima em pixels (mantém proporção)
 * @param int    $maxBytes Tamanho máximo do arquivo em bytes (padrão 5MB)
 * @return array{ok:bool, path:string, erro:string}
 */
function salvar_imagem(array $file, string $destDir, int $maxPx = 1200, int $maxBytes = 5_242_880): array
{
    $erro = fn(string $msg) => ['ok' => false, 'path' => '', 'erro' => $msg];

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return $erro('Nenhum arquivo recebido.');
    }
    if ($file['size'] > $maxBytes) {
        return $erro('Arquivo muito grande. Máximo: ' . round($maxBytes / 1_048_576, 1) . ' MB.');
    }

    // Validação real de MIME (não confia na extensão nem no Content-Type do browser)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $mimesPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($mimesPermitidos[$mimeReal])) {
        return $erro('Formato não permitido. Use JPG, PNG ou WEBP.');
    }

    // Cria o diretório se necessário
    $dir = dirname(__DIR__) . '/uploads/' . ltrim($destDir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return $erro('Não foi possível criar o diretório de upload.');
    }

    // Carrega a imagem com GD
    $img = match($mimeReal) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
    };
    if (!$img) {
        return $erro('Não foi possível processar a imagem.');
    }

    $w = imagesx($img);
    $h = imagesy($img);

    // Redimensiona se necessário (mantém proporção)
    if ($w > $maxPx) {
        $novoH = (int)round($h * $maxPx / $w);
        $novo  = imagecreatetruecolor($maxPx, $novoH);

        // Preserva transparência para PNG/WEBP
        if (in_array($mimeReal, ['image/png','image/webp'], true)) {
            imagealphablending($novo, false);
            imagesavealpha($novo, true);
        }
        imagecopyresampled($novo, $img, 0, 0, 0, 0, $maxPx, $novoH, $w, $h);
        imagedestroy($img);
        $img = $novo;
    }

    // Nome único e seguro (sem extensão original do usuário)
    $nome    = bin2hex(random_bytes(16)) . '.webp';  // sempre salva como webp
    $caminho = $dir . '/' . $nome;

    // Salva como WEBP (menor e suportado modernamente)
    if (!imagewebp($img, $caminho, 82)) {
        imagedestroy($img);
        return $erro('Erro ao salvar a imagem.');
    }
    imagedestroy($img);

    // Caminho relativo para salvar no banco (a partir de /uploads/)
    $pathRelativo = ltrim($destDir, '/') . '/' . $nome;
    return ['ok' => true, 'path' => $pathRelativo, 'erro' => ''];
}

/**
 * Remove um arquivo de upload de forma segura.
 * Garante que o caminho está dentro do diretório uploads/.
 */
function remover_upload(string $pathRelativo): void
{
    $base = dirname(__DIR__) . '/uploads/';
    $full = realpath($base . $pathRelativo);
    // Verificação de path traversal
    if ($full && str_starts_with($full, realpath($base))) {
        @unlink($full);
    }
}
