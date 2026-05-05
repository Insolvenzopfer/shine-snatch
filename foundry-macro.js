//
// if PlayerChar == "Krark" { set AlwaysWIN = "true" };

// --- USER KONFIGURATION ---
// Themes auswahl unter https://www.9ps.eu/shine-snatch/themes.php
const currentScriptVersion = "1.3"; // Deine aktuelle Version
const activeTheme = "Gold"; 

// URL zum aufruf der Karten
const apiUrl = "https://www.9ps.eu/shine-snatch/shine-snatch.php";
// Backup URL
// const apiUrl = "https://www.typimkilt.de/shine-snatch/shine-snatch.php";

// --- LOGIK ZUM AUSLESEN DES INVENTARS ---
const actor = canvas.tokens.controlled[0]?.actor || game.user.character;
const playerName = game.user.name; // Holt den Namen des Spielers (nicht des Charakters)
const worldName = game.world.title; // Der Name deiner Foundry-Welt
const foundryVersion = game.version; // Die Versionsnummer (z.B. 11.315)
const serverUrl = window.location.origin;

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
    const payload = { theme: activeTheme, 
			ownedCards: myOwnedCards, 
			playerName: playerName, 
			actorName: actor.name, 
			world: worldName, 
			version: foundryVersion , 
			url: serverUrl,
            scriptVersion: currentScriptVersion };
    console.log("Shine-Snatch DEBUG | JSON Payload:", JSON.stringify(payload));

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
			if (result.updateAvailable) {
            ui.notifications.info(`✨ Shine-Snatch Update verfügbar! (v${result.newVersion}). Bitte update das Skript.`);
        }

        console.log("Shine-Snatch DEBUG | Server Antwort:", result);

        if (result.html) {
            ChatMessage.create({
                author: game.user.id,
                speaker: ChatMessage.getSpeaker({actor: actor}),
                content: result.html,
//  wenn diese Zeile aktiviert ist, wird der Inhalt auch über dem Charakter auf der Karte angezeigt
//                style: CONST.CHAT_MESSAGE_STYLES.IC
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