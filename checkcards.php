<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Snatch Balance - 5 Card Mode</title>
    <style>
        body { background: #050705; color: #e2e8f0; font-family: 'Signika', sans-serif; padding: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: rgba(20, 25, 20, 0.7); padding: 20px; border-radius: 15px; border: 1px solid #2cb24c; backdrop-filter: blur(10px); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: #2cb24c; font-size: 0.8rem; text-transform: uppercase; }
        .prob-tag { padding: 4px 8px; border-radius: 5px; font-weight: bold; font-size: 0.85rem; }
        .rare { background: rgba(239, 68, 68, 0.2); color: #ef4444; }   /* < 0.5% */
        .common { background: rgba(44, 178, 76, 0.2); color: #2cb24c; } /* > 5% */
        h1 { color: #a855f7; }
        .highlight { color: #2cb24c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>📊 Balance Check (5 Handkarten)</h1>
    <p>Analyse der Wahrscheinlichkeit, eine Kombination direkt beim Austeilen von 5 Karten zu erhalten.</p>
    
    <div class="grid">
        <div class="card">
            <h2>Karten-Verteilung</h2>
            <table id="cardsStats">
                <thead><tr><th>Karte</th><th>Menge</th><th>Chance pro Zug</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="card">
            <h2>Kombinationen</h2>
            <table id="comboStats">
                <thead><tr><th>Name</th><th>Punkte</th><th>Chance</th><th>Effizienz</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

<script>
        const HAND_SIZE = 5;

        // Stabile nCr Funktion für große Decks
        function nCr(n, r) {
            if (r < 0 || r > n) return 0;
            if (r === 0 || r === n) return 1;
            if (r > n / 2) r = n - r;
            let res = 1;
            for (let i = 1; i <= r; i++) {
                res = res * (n - i + 1) / i;
            }
            return res;
        }

        fetch('game_data.json')
            .then(response => response.json())
            .then(data => {
                analyze(data);
            });

        function analyze(data) {
            const totalCards = data.cardTypes.reduce((sum, c) => sum + (c.count || 0), 0);
            const cardMap = {};
            data.cardTypes.forEach(c => cardMap[c.id] = c);
            const totalCombinations = nCr(totalCards, HAND_SIZE);

            // --- 1. KARTEN-VERTEILUNG (Links) ---
            const cardsBody = document.querySelector('#cardsStats tbody');
            cardsBody.innerHTML = ''; 
            data.cardTypes.sort((a,b) => b.count - a.count).forEach(c => {
                const singleDrawChance = ((c.count / totalCards) * 100).toFixed(1);
                cardsBody.innerHTML += `
                    <tr>
                        <td>${c.emoji} <b>${c.id}</b></td>
                        <td>${c.count}x</td>
                        <td>${singleDrawChance}%</td>
                    </tr>`;
            });

            // --- 2. KOMBINATIONEN (Rechts) ---
            const comboBody = document.querySelector('#comboStats tbody');
            comboBody.innerHTML = ''; 

data.combos.forEach(cb => {
        const kRequired = cb.needs.length;
        let finalProb = 0;

        // Prüfen, ob alle Needs identisch sind (Pool-Logik) oder unterschiedlich (Spezifisch)
        const allSame = cb.needs.every(val => val === cb.needs[0]);

        if (allSame) {
            // LOGIK A: Beliebige Karten aus einem Pool (z.B. 4x "Abenteurer-Gruppe")
            let poolSize = 0;
            const need = cb.needs[0];
            const group = data.groups.find(g => g.id === need);
            if (group) {
                group.cards.forEach(id => poolSize += (cardMap[id]?.count || 0));
            } else {
                poolSize = (cardMap[need]?.count || 0);
            }

            let favorable = nCr(poolSize, kRequired) * nCr(totalCards - poolSize, HAND_SIZE - kRequired);
            finalProb = (favorable / totalCombinations) * 100;

        } else {
            // LOGIK B: Spezifische Karten (z.B. 1x MYS AND 1x SCT AND 1x KRG AND 1x MAG)
            // Wir berechnen die Wege, genau 1 von jeder Sorte zu ziehen
            let favorableWays = 1;
            let usedCardsCount = 0;

            cb.needs.forEach(need => {
                let currentNeedCount = 0;
                const group = data.groups.find(g => g.id === need);
                if (group) {
                    group.cards.forEach(id => currentNeedCount += (cardMap[id]?.count || 0));
                } else {
                    currentNeedCount = (cardMap[need]?.count || 0);
                }
                
                // Wir nehmen 1 Karte aus diesem spezifischen Bedarf
                favorableWays *= nCr(currentNeedCount, 1);
                usedCardsCount += 1;
            });

            // Die restlichen Karten auf der Hand (HAND_SIZE - kRequired)
            favorableWays *= nCr(totalCards - usedCardsCount, HAND_SIZE - usedCardsCount);
            finalProb = (favorableWays / totalCombinations) * 100;
        }

        const efficiency = (finalProb > 0) ? (cb.points * finalProb).toFixed(0) : 0;
        let probClass = "prob-tag " + (finalProb < 0.1 ? 'rare' : (finalProb > 2 ? 'common' : ''));

        comboBody.innerHTML += `
            <tr>
                <td>${cb.emoji} ${cb.name}</td>
                <td class="highlight">${cb.points}</td>
                <td><span class="${probClass}">${finalProb.toFixed(4)}%</span></td>
                <td>${efficiency}</td>
            </tr>`;
    });
        }
    </script>
</body>
</html>