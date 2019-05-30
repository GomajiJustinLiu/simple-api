<?php

use Google\Cloud\AutoMl\V1beta1\ExamplePayload;
use Google\Cloud\AutoMl\V1beta1\PredictionServiceClient;
use Predis\Client;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Google\Cloud\AutoMl\V1beta1\AutoMlClient;

return function (App $app) {
    $displayMapping = [
        '2' => '中式',
        '3' => '美食餐廳',
        '4' => '美式',
        '5' => '日式',
        '6' => '西式/牛排',
        '7' => '異國',
        '8' => '咖啡輕食',
        '9' => '義式',
        '10' => '甜點冰飲',
        '11' => '複合式餐飲',
        '12' => '火鍋',
        '13' => '燒烤/居酒屋',
        '14' => '主題特色餐廳',
        '15' => '吃到飽',
        '16' => '養生蔬食',
        '21' => '泰式',
        '25' => '港式',
        '97' => '五星飯店',
        '98' => '素蔬食',
        '99' => '小吃',
        '100' => '韓式',
        '101' => '熱炒 ',
        '102' => '居酒屋',
        '103' => '烘焙點心',
        '104' => '早午餐',
        '105' => '下午茶',
        '106' => '宵夜',
        '117' => '鐵板燒',
        '138' => '燒肉燒烤',
    ];

    $container = $app->getContainer();

    $app->get('/[{name}]', function (Request $request, Response $response, array $args) use ($container) {
        // Sample log message
        $container->get('logger')->info("Slim-Skeleton '/' route");

        // Render index view
        return $container->get('renderer')->render($response, 'index.phtml', $args);
    });
    $app->group('/api', function () use ($app, $container, $displayMapping) {
        $app->post('/predict-text', function (Request $request, Response $response) use ($container, $displayMapping) {
            $description = $request->getParam('description');
            $description = trim($description);
            $client = $container['redis'];
            $client->lpush('predict_history', $description);
            $client->disconnect();

            $predictionServiceClient = new PredictionServiceClient();
            try {
                $formattedName = $predictionServiceClient->modelName('workshop-automl', 'us-central1', 'TCN1798894091365837886');
                $payload = new ExamplePayload();
                $textSnippet = new \Google\Cloud\AutoMl\V1beta1\TextSnippet();
                $textSnippet->setContent($description);
                $textSnippet->setMimeType('text/plain');
                $payload->setTextSnippet($textSnippet);
                $predictResponse = $predictionServiceClient->predict($formattedName, $payload);
                $res = $predictResponse->serializeToJsonString();
                $predictResult = json_decode($res, true);
            } finally {
                $predictionServiceClient->close();
            }

            $ret = [];
            if (!empty($predictResult) && isset($predictResult['payload'])) {
                $data = $predictResult['payload'];
                foreach ($data as $item) {
                    $display = $item['displayName'];
                    if (isset($item['classification']) && isset($item['classification']['score'])) {
                        $score = $item['classification']['score'];
                    }
                    $ret[] = [
                        'display' => $displayMapping[$display],
                        'score' => $score,
                    ];
                }
            }

            $serverIP = $_SERVER['SERVER_ADDR'];
            $response = $response->withAddedHeader('HOST', $serverIP);
            return $response->withJson($ret);
        });

        $app->get('/predict-history', function (Request $request, Response $response) use ($container) {
            $client = $container['redis'];
            $result = $client->lrange('predict_history', 0, -1);
            $client->disconnect();
            $serverIP = $_SERVER['SERVER_ADDR'];
            $response = $response->withAddedHeader('HOST', $serverIP);
            return $response->withJson($result);
        });

        $app->get('/health', function (Request $request, Response $response) use ($container) {
            return $response->write('OK');
        });

        $app->get('/readiness', function (Request $request, Response $response) use ($container) {
            $client = $container['redis'];
            $pong = $client->ping();
            $result = !empty($pong) ? 'OK' : 'FAIL';
            return $response->withStatus(500)->write($result);
        });
    });
};