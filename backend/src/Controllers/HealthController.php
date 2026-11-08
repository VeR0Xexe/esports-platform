<?php
namespace App\Controllers;

use App\Database;

class HealthController
{
    public function check(): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->query('SELECT 1 AS ok');
        $row = $stmt->fetch();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'db' => $row['ok'] === 1 ? 'connected' : 'failed',
            'time' => date('c'),
        ]);
    }
}