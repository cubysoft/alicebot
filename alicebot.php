<?php
// Cargar el archivo .env
$dotenv = parse_ini_file('.env');

// Definir el token del bot y las credenciales de Amazon
$telegramToken = $dotenv['TELEGRAM_TOKEN'];
$amazonAccessKey = $dotenv['AMAZON_ACCESS_KEY'];
$amazonSecretKey = $dotenv['AMAZON_SECRET_KEY'];
$amazonTrackingId = $dotenv['AMAZON_TRACKING_ID'];

// URL de la API de Telegram
$telegramApiUrl = "https://api.telegram.org/bot$telegramToken/";

// Obtener los datos enviados al webhook de Telegram (lo que el usuario envía)
$update = json_decode(file_get_contents("php://input"), TRUE);

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $messageId = $update["message"]["message_id"];
    
    // Obtener el nombre y el nombre de usuario del remitente
    $firstName = isset($update["message"]["from"]["first_name"]) ? $update["message"]["from"]["first_name"] : "Usuario";
    $lastName = isset($update["message"]["from"]["last_name"]) ? $update["message"]["from"]["last_name"] : "";
    $fullName = trim($firstName . ' ' . $lastName);  // Concatenar nombre y apellido
    
    $messageText = $update["message"]["text"];

    // Verificar si el mensaje contiene una URL
    if (preg_match("/\b(http:\/\/|https:\/\/)[\S]+\b/i", $messageText, $matches)) {
        // Acortar la URL de Amazon si es una URL válida
        $shortenedUrl = $matches[0];
        if (strpos($shortenedUrl, 'amazon.') !== false) {
            $shortenedUrl = shortenAmazonUrl($shortenedUrl, $amazonAccessKey, $amazonSecretKey, $amazonTrackingId);
        }
        
        // Eliminar el mensaje original del usuario
        $deleteMessageUrl = $telegramApiUrl . "deleteMessage?chat_id=$chatId&message_id=$messageId";
        file_get_contents($deleteMessageUrl);

        // Responder con el formato deseado y emojis
        $responseText = "🌟 APORTE DE $fullName 🌟\n🔗 $shortenedUrl";  // Incluir el nombre completo y la URL con emojis
        $sendMessageUrl = $telegramApiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($responseText);
        file_get_contents($sendMessageUrl);
    } else {
        // Respuesta estándar cuando no es una URL
        $responseText = "🚫 Lo siento, solo puedo procesar URLs por ahora.";
        $sendMessageUrl = $telegramApiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($responseText);
        file_get_contents($sendMessageUrl);
    }
}

// Función para acortar URL de Amazon
function shortenAmazonUrl($url, $accessKey, $secretKey, $trackingId) {
    // Extraer el ID del producto de la URL
    preg_match('/\/dp\/([A-Z0-9]+)/', $url, $matches);
    $asin = $matches[1];

    // Crear la URL acortada
    return "https://amzn.eu/d/" . substr(md5($asin . $trackingId), 0, 8); // Generar un código hash para la URL
}
