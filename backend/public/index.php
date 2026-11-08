<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controllers\HealthController;
use App\Controllers\TournamentController;

// Загружаем переменные окружения
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Получаем путь запроса
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Настройки CORS для React
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Получаем ID турнира из ссылки (например: ?tournament_id=2), по умолчанию 1
$tournamentId = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 1;

// МАРШРУТЫ
if ($path === '/api/health') {
    (new HealthController())->check();
} 
elseif ($path === '/api/generate-bracket') {
    (new TournamentController())->generateAndSave($tournamentId);
} 
elseif ($path === '/api/matches') {
    (new TournamentController())->getMatches($tournamentId);
} 
elseif ($path === '/api/match-result') {
    (new TournamentController())->submitResult();
} 
else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}