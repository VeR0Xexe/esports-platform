<?php
namespace App\Services;

class BracketGenerator
{
    /**
     * Генерация ПОЛНОЙ сетки Single Elimination (Олимпийская система)
     */
    public function generateSingleElimination(array $participants): array
    {
        $count = count($participants);
        if ($count < 2) return [];

        // Находим ближайшую степень двойки (2, 4, 8, 16...)
        $powerOfTwo = 1;
        while ($powerOfTwo < $count) {
            $powerOfTwo *= 2;
        }

        // Количество пустых слотов (Bye)
        $byes = $powerOfTwo - $count;

        $slots = $participants;
        for ($i = 0; $i < $byes; $i++) {
            $slots[] = null;
        }

        $matches = [];
        $totalRounds = log($powerOfTwo, 2);

        // Генерируем матчи для ВСЕХ раундов
        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchesInRound = $powerOfTwo / pow(2, $round);
            
            for ($matchNum = 1; $matchNum <= $matchesInRound; $matchNum++) {
                // В первом раунде заполняем участниками, в остальных - пусто (null)
                if ($round === 1) {
                    $p1 = $slots[($matchNum - 1) * 2];
                    $p2 = $slots[($matchNum - 1) * 2 + 1];
                } else {
                    $p1 = null;
                    $p2 = null;
                }

                $matches[] = [
                    'round' => $round,
                    'match_number' => $matchNum,
                    'participant1' => $p1,
                    'participant2' => $p2
                ];
            }
        }

        return $matches;
    }

    /**
     * Генерация сетки Round Robin (Круговая система)
     */
    public function generateRoundRobin(array $participants): array
    {
        $count = count($participants);
        if ($count < 2) return [];

        // Если нечетное количество, добавляем фиктивного игрока (Bye)
        if ($count % 2 != 0) {
            $participants[] = null;
            $count++;
        }

        $rounds = $count - 1;
        $matchesPerRound = $count / 2;
        $matches = [];
        $matchNumber = 1;

        // Алгоритм круговой системы (метод вращения)
        for ($round = 0; $round < $rounds; $round++) {
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = ($round + $match) % ($count - 1);
                $away = ($count - 1 - $match + $round) % ($count - 1);

                if ($match == 0) {
                    $away = $count - 1;
                }

                $p1 = $participants[$home];
                $p2 = $participants[$away];

                // Пропускаем матчи с фиктивным игроком (Bye)
                if ($p1 !== null && $p2 !== null) {
                    $matches[] = [
                        'round' => $round + 1,
                        'match_number' => $matchNumber++,
                        'participant1' => $p1,
                        'participant2' => $p2
                    ];
                }
            }
        }

        return $matches;
    }
}