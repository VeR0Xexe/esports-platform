<?php
namespace App\Controllers;

use App\Services\BracketGenerator;
use App\Database;
use PDO;

class TournamentController
{
    // 1. Генерация сетки и сохранение в БД
    public function generateAndSave(int $tournamentId): void
    {
        header('Content-Type: application/json');
        $pdo = Database::connect();

        // Получаем формат турнира (single_elimination или round_robin)
        $stmtFormat = $pdo->prepare("SELECT format FROM tournaments WHERE id = ?");
        $stmtFormat->execute([$tournamentId]);
        $format = $stmtFormat->fetchColumn();

        // Получаем участников из БД
        $stmt = $pdo->prepare("SELECT id FROM participants WHERE tournament_id = ? ORDER BY seed ASC");
        $stmt->execute([$tournamentId]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($participants) < 2) {
            echo json_encode(['error' => 'Недостаточно участников для генерации']);
            return;
        }

        $generator = new BracketGenerator();
        
        // Выбираем алгоритм в зависимости от формата
        if ($format === 'round_robin') {
            $generatedMatches = $generator->generateRoundRobin($participants);
        } else {
            $generatedMatches = $generator->generateSingleElimination($participants);
        }

        // Очищаем старые матчи для этого турнира
        $pdo->prepare("DELETE FROM matches WHERE tournament_id = ?")->execute([$tournamentId]);

        // Сохраняем новые матчи в БД
        $sql = "INSERT INTO matches (tournament_id, round_number, match_number, participant1_id, participant2_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($generatedMatches as $match) {
            $stmt->execute([
                $tournamentId,
                $match['round'],
                $match['match_number'],
                $match['participant1'],
                $match['participant2']
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Сетка успешно сгенерирована и сохранена в БД',
            'matches_created' => count($generatedMatches)
        ], JSON_UNESCAPED_UNICODE);
    }

    // 2. Получение матчей для отображения на фронтенде
    public function getMatches(int $tournamentId): void
    {
        header('Content-Type: application/json');
        $pdo = Database::connect();

        $sql = "SELECT m.id, m.round_number, m.match_number, m.status, m.winner_id,
                       p1.id as p1_id, u1.username as p1_name,
                       p2.id as p2_id, u2.username as p2_name
                FROM matches m
                LEFT JOIN participants p1 ON m.participant1_id = p1.id
                LEFT JOIN users u1 ON p1.user_id = u1.id
                LEFT JOIN participants p2 ON m.participant2_id = p2.id
                LEFT JOIN users u2 ON p2.user_id = u2.id
                WHERE m.tournament_id = ?
                ORDER BY m.round_number ASC, m.match_number ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tournamentId]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($matches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // 3. Ввод результата и автоматическое продвижение победителя
    public function submitResult(): void
    {
        header('Content-Type: application/json');
        $pdo = Database::connect();

        // Получаем JSON из запроса React
        $data = json_decode(file_get_contents('php://input'), true);
        $matchId = $data['match_id'] ?? null;
        $winnerId = $data['winner_id'] ?? null;

        if (!$matchId || !$winnerId) {
            echo json_encode(['error' => 'Не переданы ID матча или победителя']);
            return;
        }

        // Получаем информацию о текущем матче
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            echo json_encode(['error' => 'Матч не найден']);
            return;
        }

        // Записываем победителя в текущий матч
        $pdo->prepare("UPDATE matches SET winner_id = ?, status = 'confirmed' WHERE id = ?")
            ->execute([$winnerId, $matchId]);

        // Узнаем формат турнира
        $stmtFormat = $pdo->prepare("SELECT format FROM tournaments WHERE id = ?");
        $stmtFormat->execute([$match['tournament_id']]);
        $format = $stmtFormat->fetchColumn();

        // Логика продвижения работает ТОЛЬКО для олимпийской системы
        if ($format === 'single_elimination') {
            $currentRound = $match['round_number'];
            $currentMatchNum = $match['match_number'];
            $nextRound = $currentRound + 1;
            
            // Матч 1 и 2 идут в Матч 1 следующего раунда и т.д.
            $nextMatchNum = ceil($currentMatchNum / 2);

            // Проверяем, существует ли матч в следующем раунде
            $stmt = $pdo->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round_number = ? AND match_number = ?");
            $stmt->execute([$match['tournament_id'], $nextRound, $nextMatchNum]);
            $nextMatch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nextMatch) {
                // Определяем, в какое гнездо (1 или 2) ставить победителя
                $slot = ($currentMatchNum % 2 !== 0) ? 'participant1_id' : 'participant2_id';
                
                $sqlUpdate = "UPDATE matches SET {$slot} = ? WHERE id = ?";
                $pdo->prepare($sqlUpdate)->execute([$winnerId, $nextMatch['id']]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Результат сохранен']);
    }
}