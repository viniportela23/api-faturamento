<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar se o parâmetro 'redimensiona' está presente na URL
if (isset($_GET['tamanho'])) {
    $maxSize = intval($_GET['tamanho']); // Converter para inteiro
} else {
    $maxSize = 1000;  // Tamanho padrão caso não seja informado
}

if (isset($_GET['arquivo'])) {
    $arquivo = urldecode($_GET['arquivo']); // Decodifica o nome do arquivo
} else {
    echo 'Arquivo não especificado!';
    exit;
}


// Caminho da imagem original no servidor
$imagePath = '/home/centralj/api.centraljbl.com.br/uploads/produtos/' . $arquivo;

// Verificar se o arquivo de imagem existe
if (file_exists($imagePath)) {

    // Obter a extensão do arquivo
    $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
    
    // Verificar o tipo de imagem (PNG, JPG ou JPEG)
    if ($ext == 'png') {
        $image = imagecreatefrompng($imagePath);
    } elseif ($ext == 'jpg' || $ext == 'jpeg') {
        $image = imagecreatefromjpeg($imagePath);
    } else {
        echo 'Tipo de imagem não suportado!';
        exit;
    }

    // Obter as dimensões da imagem original
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    // Calcular a proporção para redimensionar sem distorcer
    $aspectRatio = $originalWidth / $originalHeight;

    if ($originalWidth > $originalHeight) {
        // Definir largura máxima e calcular altura proporcional
        $newWidth = $maxSize;
        $newHeight = round($maxSize / $aspectRatio);
    } else {
        // Definir altura máxima e calcular largura proporcional
        $newHeight = $maxSize;
        $newWidth = round($maxSize * $aspectRatio);
    }

    // Criar a imagem redimensionada
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

    // Manter a transparência para PNGs
    if ($ext == 'png') {
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
        imagefill($resizedImage, 0, 0, $transparent);
    }

    // Redimensionar a imagem para a nova largura e altura
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // Definir o tipo de conteúdo de acordo com a extensão da imagem
    if ($ext == 'png') {
        header('Content-Type: image/png');
        imagepng($resizedImage);
    } elseif ($ext == 'jpg' || $ext == 'jpeg') {
        header('Content-Type: image/jpeg');
        imagejpeg($resizedImage);
    }

    // Liberar a memória
    imagedestroy($image);
    imagedestroy($resizedImage);
} else {
    echo 'Imagem não encontrada! ';
}
?>
