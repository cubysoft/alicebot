<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// Cargar variables de entorno desde el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Inicializar Guzzle
$client = new Client();

// URL de la API de Telegram usando la variable del .env
$telegramApiUrl = "https://api.telegram.org/bot" . $_ENV['TELEGRAM_TOKEN'];

// Obtener los datos enviados al webhook de Telegram
$update = json_decode(file_get_contents("php://input"), TRUE);

// Verificar si el update contiene un mensaje
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $messageId = $update["message"]["message_id"];
    $chatType = $update["message"]["chat"]["type"]; // Tipo de chat (private, group, supergroup, channel)

    // Obtener el nombre del remitente
    $firstName = isset($update["message"]["from"]["first_name"]) ? $update["message"]["from"]["first_name"] : "Usuario";
    $lastName = isset($update["message"]["from"]["last_name"]) ? $update["message"]["from"]["last_name"] : "";
    $fullName = trim($firstName . ' ' . $lastName);

    // Obtener el texto del mensaje y eliminar caracteres adicionales
    $messageText = isset($update["message"]["text"]) ? trim($update["message"]["text"]) : "";

    // Inicializar arrays para URLs
    $amazonUrls = [];
    $aliexpressUrls = [];
    
    // Verificar si el mensaje contiene una URL de Amazon
    if (preg_match_all("/\b(http:\/\/|https:\/\/)(www\.)?amazon\.[a-z\.]{2,6}\/[^\s]*/i", $messageText, $amazonMatches)) {
        $amazonUrls = $amazonMatches[0]; // Obtener todas las URLs de Amazon
    }
    
    // Verificar si el mensaje contiene una URL de AliExpress
    if (preg_match_all("/\b(http:\/\/|https:\/\/)([a-z]{2,3}\.)?aliexpress\.com\/item\/([0-9]+)(\.html)?(\?.*)?/i", $messageText, $aliexpressMatches)) {
        $aliexpressUrls = $aliexpressMatches[0]; // Obtener todas las URLs de AliExpress
    }

    // Procesar URLs de Amazon
    if (!empty($amazonUrls)) {
        $shortUrls = [];
        foreach ($amazonUrls as $url) {
            try {
                // Generar el enlace acortado real
                $shortUrl2 = createAmazonShortLinkOption2($url);
                $shortUrls[] = "🔗 $shortUrl2"; // Mostrar solo la URL sin formato de enlace
            } catch (Exception $e) {
                $shortUrls[] = "🚫 **Error:** " . $e->getMessage();
            }
        }

        $responseText = "🌟 APORTE DE @$fullName 🌟\n\n" . implode("\n\n", $shortUrls);
        sendMessage($client, $telegramApiUrl, $chatId, $responseText);
        deleteMessage($client, $telegramApiUrl, $chatId, $messageId);
    }

    // Procesar URLs de AliExpress
    if (!empty($aliexpressUrls)) {
        $shortUrls = [];
        foreach ($aliexpressUrls as $url) {
            try {
                // Generar el enlace acortado real
                $shortUrl = createAliExpressShortLink($url);
                $shortUrls[] = "🔗 $shortUrl"; // Mostrar solo la URL sin formato de enlace
            } catch (Exception $e) {
                $shortUrls[] = "🚫 **Error:** " . $e->getMessage();
            }
        }

        $responseText = "🌟 APORTE DE @$fullName 🌟\n\n" . implode("\n\n", $shortUrls);
        sendMessage($client, $telegramApiUrl, $chatId, $responseText);
        deleteMessage($client, $telegramApiUrl, $chatId, $messageId);
    }

    // Respuesta estándar cuando no hay URLs
    if (empty($amazonUrls) && empty($aliexpressUrls)) {
        $responseText = "🚫 **Lo siento, solo puedo procesar URLs de Amazon y AliExpress.**";
        sendMessage($client, $telegramApiUrl, $chatId, $responseText);
    }
}

// Función para generar la URL acortada de Amazon (Opción 2: Extraer ASIN)
function createAmazonShortLinkOption2($url) {
    $asin = extractAsinFromUrl($url);
    if ($asin) {
        return "www.amzn.com/dp/$asin";
    } else {
        throw new Exception("No se pudo extraer el ASIN de la URL.");
    }
}

// Función para extraer el ASIN de la URL de Amazon
function extractAsinFromUrl($url) {
    $parsed = parse_url($url);
    $path = $parsed['path'];
    $pattern = '/\/dp\/([A-Z0-9]+)/';
    if (preg_match($pattern, $path, $matches)) {
        return $matches[1]; // Devuelve el ASIN encontrado
    } else {
        return null;
    }
}

// Función para generar la URL acortada de AliExpress
function createAliExpressShortLink($url) {
    // Extraer el subdominio
    $parsed = parse_url($url);
    $hostParts = explode('.', $parsed['host']); // Separar el host en partes
    $subdomain = (count($hostParts) > 2) ? $hostParts[0] . '.' : ''; // Capturar el subdominio si existe
    
    $itemId = extractAliExpressIdFromUrl($url);
    if ($itemId) {
        return $subdomain . "aliexpress.com/item/$itemId.html";
    } else {
        throw new Exception("No se pudo extraer el ID de AliExpress de la URL.");
    }
}

// Función para extraer el ID de la URL de AliExpress
function extractAliExpressIdFromUrl($url) {
    $parsed = parse_url($url);
    $path = $parsed['path'];
    $pattern = '/\/item\/([0-9]+)/';
    if (preg_match($pattern, $path, $matches)) {
        return $matches[1]; // Devuelve el ID encontrado
    } else {
        return null;
    }
}

// Función para enviar un mensaje a Telegram
function sendMessage($client, $telegramApiUrl, $chatId, $text) {
    $sendMessageUrl = $telegramApiUrl . "/sendMessage?chat_id=$chatId&text=" . urlencode($text);
    $client->get($sendMessageUrl);
}

// Función para eliminar un mensaje en Telegram
function deleteMessage($client, $telegramApiUrl, $chatId, $messageId) {
    try {
        $deleteMessageUrl = $telegramApiUrl . "/deleteMessage?chat_id=$chatId&message_id=$messageId";
        $response = $client->get($deleteMessageUrl);
        $responseBody = json_decode($response->getBody(), true);
        if (!$responseBody['ok']) {
            error_log("Error eliminando el mensaje: " . $responseBody['description']);
        }
    } catch (Exception $e) {
        error_log("No se pudo eliminar el mensaje: " . $e->getMessage());
    }
}
?>
