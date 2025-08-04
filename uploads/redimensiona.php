<?php
// Verifica se a URL solicitada contém '/uploads/' e se tem o parâmetro 'redimensiona'
$request_uri = $_SERVER['REQUEST_URI'];
$uploads_pos = strpos($request_uri, '/uploads/');

if ($uploads_pos !== false) {
    // Extrai o nome do arquivo e parâmetros da URL
    $path = parse_url($request_uri, PHP_URL_PATH);
    $query = parse_url($request_uri, PHP_URL_QUERY);
    
    // Verifica se há parâmetro 'redimensiona'
    if ($query && strpos($query, 'redimensiona=') !== false) {
        parse_str($query, $params);
        
        if (isset($params['redimensiona'])) {
            $maxSize = intval($params['redimensiona']);
            
            // Extrai o nome do arquivo da URL
            $arquivo = substr($path, strpos($path, '/uploads/') + 9); // +9 para pular '/uploads/'
            
            // Caminho completo da imagem
            $imagePath = '/home/centralj/api.centraljbl.com.br/uploads/produtos/' . $arquivo;
            
            // Verifica se o arquivo existe
            if (file_exists($imagePath)) {
                // Seu código de redimensionamento aqui (o mesmo que você já tem)
                $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
                
                if ($ext == 'png') {
                    $image = imagecreatefrompng($imagePath);
                } elseif ($ext == 'jpg' || $ext == 'jpeg') {
                    $image = imagecreatefromjpeg($imagePath);
                } else {
                    header('HTTP/1.1 400 Bad Request');
                    echo 'Tipo de imagem não suportado!';
                    exit;
                }

                $originalWidth = imagesx($image);
                $originalHeight = imagesy($image);
                $aspectRatio = $originalWidth / $originalHeight;

                if ($originalWidth > $originalHeight) {
                    $newWidth = $maxSize;
                    $newHeight = round($maxSize / $aspectRatio);
                } else {
                    $newHeight = $maxSize;
                    $newWidth = round($maxSize * $aspectRatio);
                }

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                if ($ext == 'png') {
                    imagesavealpha($resizedImage, true);
                    $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                    imagefill($resizedImage, 0, 0, $transparent);
                }

                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

                if ($ext == 'png') {
                    header('Content-Type: image/png');
                    imagepng($resizedImage);
                } elseif ($ext == 'jpg' || $ext == 'jpeg') {
                    header('Content-Type: image/jpeg');
                    imagejpeg($resizedImage);
                }

                imagedestroy($image);
                imagedestroy($resizedImage);
                exit;
            } else {
                header('HTTP/1.1 404 Not Found');
                echo 'Imagem não encontrada!';
                exit;
            }
        }
    }
}

// Se não for uma requisição de redimensionamento, continue normalmente
// (pode servir o arquivo original ou fazer outra coisa)
?>