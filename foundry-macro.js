//
// V 1.1
//
// if PlayerChar == "Krark" { set AlwaysWIN = "true" };

// --- USER KONFIGURATION ---
const activeTheme = "Gold"; 

// URL zum aufruf der Karten
const apiUrl = "https://www.9ps.eu/shine-snatch/shine-snatch.php";
// Backup URL
// const apiUrl = "https://www.typimkilt.de/shine-snatch/shine-snatch.php";

// --- LOGIK ZUM AUSLESEN DES INVENTARS ---
const actor = canvas.tokens.controlled[0]?.actor || game.user.character;

// LOG: Welchen Actor hat das Script gefunden?
console.log("Shine-Snatch DEBUG | Gefundener Actor:", actor?.name, actor);

if (!actor) {
    ui.notifications.warn("Bitte Token auswählen! Viel Glück.");
}

// Wir filtern die Items und extrahieren die Nummer
const myOwnedCards = actor ? actor.items
    .filter(item => item.name.includes("Shine-Snatch"))
    .map(item => {
        const match = item.name.match(/\d+/); 
        return match ? parseInt(match[0]) : null;
    })
    .filter(id => id !== null) : [];

// LOG: Welche Karten-IDs wurden extrahiert?
console.log("Shine-Snatch DEBUG | Übergebene IDs an API:", myOwnedCards);

// --- API LOGIK ---
(async () => {
    // LOG: Das komplette Objekt, das an den Server geht
    const payload = { theme: activeTheme, ownedCards: myOwnedCards };
    console.log("Shine-Snatch DEBUG | JSON Payload:", JSON.stringify(payload));

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        console.log("Shine-Snatch DEBUG | Server Antwort:", result);

        if (result.html) {
            ChatMessage.create({
                author: game.user.id,
                speaker: ChatMessage.getSpeaker({actor: actor}),
                content: result.html
            });
        } else {
            ui.notifications.error("Shine-Snatch: Keine Daten erhalten.");
        }
    } catch (e) {
        ui.notifications.error("Shine-Snatch: Verbindungsfehler.");
        console.error("Shine-Snatch DEBUG | Fehler:", e);
    }
})();
startSnatch();