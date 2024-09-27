<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// Cargar variables de entorno desde el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Inicializar Guzzle
$client = new Client();

// URL de la API de Telegram usando el token del archivo .env
$telegramApiUrl = "https://api.telegram.org/bot" . $_ENV['TELEGRAM_TOKEN'];

// Obtener información del bot (getMe)
$response = $client->get($telegramApiUrl . "/getMe");
$botInfo = json_decode($response->getBody(), true);

// Obtener información del bot
$botUsername = $botInfo['result']['username'];
$botName = $botInfo['result']['first_name'];
$botId = $botInfo['result']['id'];

// Obtener la foto de perfil del bot
$response = $client->get($telegramApiUrl . "/getUserProfilePhotos", [
    'query' => ['user_id' => $botId, 'limit' => 1]
]);
$profilePhotos = json_decode($response->getBody(), true);
$botPhotoUrl = null;

if (!empty($profilePhotos['result']['photos'])) {
    $fileId = $profilePhotos['result']['photos'][0][0]['file_id'];

    // Obtener la URL de la foto usando getFile
    $response = $client->get($telegramApiUrl . "/getFile", [
        'query' => ['file_id' => $fileId]
    ]);
    $fileData = json_decode($response->getBody(), true);
    $filePath = $fileData['result']['file_path'];
    $botPhotoUrl = "https://api.telegram.org/file/bot" . $_ENV['TELEGRAM_TOKEN'] . "/" . $filePath;
}

// Obtener estado del webhook
$response = $client->get($telegramApiUrl . "/getWebhookInfo");
$webhookInfo = json_decode($response->getBody(), true);

// Verificar si el bot está activo
$isActive = isset($webhookInfo['result']['url']) && !empty($webhookInfo['result']['url']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del Bot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .dashboard {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 900px;
        }

        .bot-info-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bot-info-container img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }

        .bot-details {
            flex-grow: 1;
            margin-left: 20px;
        }

        .bot-details h2 {
            margin: 0;
            font-size: 26px;
        }

        .bot-details p {
            margin: 5px 0;
            font-size: 18px;
            color: #555;
        }

        .status-container {
            text-align: center;
            margin-top: 20px;
        }

        .status {
            font-size: 24px;
            padding: 15px;
            border-radius: 10px;
            color: #fff;
        }

        .active {
            background-color: #4CAF50;
        }

        .inactive {
            background-color: #f44336;
        }

        /* Placeholder styling */
        .bot-photo-placeholder {
            width: 150px;
            height: 150px;
            background-color: #ccc;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="dashboard">
        <div class="bot-info-container">
            <?php if ($botPhotoUrl): ?>
                <img src="<?php echo $botPhotoUrl; ?>" alt="Bot Photo">
            <?php else: ?>
                <div class="bot-photo-placeholder">500x500</div>
            <?php endif; ?>

            <div class="bot-details">
                <h2>Bot: <?php echo $botName; ?></h2>
                <p><strong>Username:</strong> @<?php echo $botUsername; ?></p>
                <p><strong>ID del Bot:</strong> <?php echo $botId; ?></p>
            </div>
        </div>

        <div class="status-container">
            <?php if ($isActive): ?>
                <div class="status active">✅ El bot está <strong>activo</strong></div>
            <?php else: ?>
                <div class="status inactive">❌ El bot está <strong>inactivo</strong></div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
