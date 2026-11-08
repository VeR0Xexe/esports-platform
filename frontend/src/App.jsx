import { useEffect, useState } from 'react';
import './App.css';

function App() {
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [tournamentId, setTournamentId] = useState(1);

  const fetchMatches = () => {
    fetch(`http://localhost:8000/api/matches?tournament_id=${tournamentId}`)
      .then((res) => res.json())
      .then((data) => {
        if (Array.isArray(data)) setMatches(data);
      })
      .catch((err) => setError(err.message));
  };

  useEffect(() => {
    fetchMatches();
  }, [tournamentId]);

  const handleGenerate = () => {
    setLoading(true);
    fetch(`http://localhost:8000/api/generate-bracket?tournament_id=${tournamentId}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success) fetchMatches();
        else setError(data.error);
      })
      .finally(() => setLoading(false));
  };

  const handleWin = (matchId, winnerId) => {
    if (!winnerId) return;
    fetch('http://localhost:8000/api/match-result', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ match_id: matchId, winner_id: winnerId })
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) fetchMatches();
      });
  };

  const groupedMatches = matches.reduce((acc, match) => {
    const round = match.round_number;
    if (!acc[round]) acc[round] = [];
    acc[round].push(match);
    return acc;
  }, {});

  // Подсчет очков (для Round Robin)
  const standings = {};
  if (tournamentId === 2) {
    matches.forEach((m) => {
      if (m.p1_name) standings[m.p1_name] = standings[m.p1_name] || 0;
      if (m.p2_name) standings[m.p2_name] = standings[m.p2_name] || 0;
      if (m.winner_id === m.p1_id) standings[m.p1_name] += 3;
      if (m.winner_id === m.p2_id) standings[m.p2_name] += 3;
    });
  }

  // Определение чемпиона
  let champion = null;
  if (tournamentId === 1 && matches.length > 0) {
    const maxRound = Math.max(...matches.map(m => m.round_number));
    const finalMatch = matches.find(m => m.round_number === maxRound);
    if (finalMatch && finalMatch.winner_id) {
      champion = finalMatch.winner_id === finalMatch.p1_id ? finalMatch.p1_name : finalMatch.p2_name;
    }
  }

  return (
    <div className="main-layout">
      <main className="main-content">
        {/* Верхнее меню (вкладки) */}
        <div className="top-nav">
          <div className="tournament-title">
            Организовано <span className="highlight">ESports Platform</span>
          </div>
          <div className="tabs">
            <span className="tab active">СЕТКА</span>
          </div>
        </div>

        {/* Панель управления */}
        <div className="controls">
          <select 
            value={tournamentId} 
            onChange={(e) => setTournamentId(Number(e.target.value))}
            className="tournament-select"
          >
            <option value={1}>Турнир 1 (Single Elimination)</option>
            <option value={2}>Турнир 2 (Round Robin)</option>
          </select>
          <button onClick={handleGenerate} disabled={loading}>
            {loading ? 'Генерация...' : 'Сгенерировать сетку'}
          </button>
        </div>

        {error && <p className="error">Ошибка: {error}</p>}

        {/* Баннер победителя */}
        {champion && (
          <div className="champion-banner">
            <h2>ПОБЕДИТЕЛЬ ТУРНИРА</h2>
            <div className="champion-name">{champion}</div>
          </div>
        )}

        {/* Таблица лидеров (Round Robin) */}
        {tournamentId === 2 && matches.length > 0 && (
          <div className="standings-table">
            <h3>Турнирная таблица</h3>
            <div className="standings-list">
              {Object.entries(standings).sort((a, b) => b[1] - a[1]).map(([name, points], index) => (
                <div key={name} className="standings-item">
                  <span>{index + 1}. {name}</span>
                  <span className="points">{points} очков</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Сетка матчей */}
        {matches.length > 0 && (
          <div className="bracket-container">
            {Object.keys(groupedMatches).map((roundNumber) => (
              <div key={roundNumber} className="round-column">
                <div className="round-header">
                  <h3>Раунд {roundNumber}</h3>
                  <span className="round-sub">{groupedMatches[roundNumber].length} матчей</span>
                </div>
                
                <div className="matches-wrapper">
                  {groupedMatches[roundNumber].map((match) => (
                    <div key={match.id} className="match-card">
                      {/* Игрок 1 */}
                      <div 
                        className={`team-row ${match.winner_id === match.p1_id ? 'winner' : ''}`}
                        onClick={() => !match.winner_id && handleWin(match.id, match.p1_id)}
                      >
                        <span className="team-name">{match.p1_name || 'Ожидание'}</span>
                        <span className="team-score">{match.winner_id === match.p1_id ? '1' : ''}</span>
                      </div>
                      
                      {/* Игрок 2 */}
                      <div 
                        className={`team-row ${match.winner_id === match.p2_id ? 'winner' : ''}`}
                        onClick={() => !match.winner_id && handleWin(match.id, match.p2_id)}
                      >
                        <span className="team-name">{match.p2_name || 'Ожидание'}</span>
                        <span className="team-score">{match.winner_id === match.p2_id ? '1' : ''}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

export default App;