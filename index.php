<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="/dnd/items/Krark/shine-snatch.webp">
    <title>Shine Snatch Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Signika:wght@300;600&display=swap" rel="stylesheet">
        <?php if($_SERVER['HTTP_HOST']=="www.9ps.eu") { ?>
        <link rel="stylesheet" href="/css/style_dragon.css">
        <script src="/js/script_dragon.js" defer></script>
    <?php } ?>
    <style>
        :root {
            --bg-dark: #050705;
            --panel-bg: rgba(20, 25, 20, 0.8);
            --primary: #2cb24c;
            --primary-bright: #46d366;
            --accent: #a855f7;
            --text: #e2e8f0;
            --text-dim: #94a3b8;
        }

        body {
            font-family: 'Signika', sans-serif;
            background: var(--bg-dark);
            background-image:
                radial-gradient(circle at 20% 30%, rgba(44, 178, 76, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(168, 85, 247, 0.05) 0%, transparent 40%);
            color: var(--text);
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 350px;
            background: var(--panel-bg);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            padding: 40px 20px;
            z-index: 10;
        }

        .brand { text-align: center; margin-bottom: 50px; cursor: pointer; }
        .logo {
            width: 300px;
            filter: drop-shadow(0 0 20px rgba(44, 178, 76, 0.3));
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .logo:hover { transform: rotate(5deg) scale(1.1); }
        h1 { font-size: 1.8em; letter-spacing: 4px; margin: 10px 0; background: linear-gradient(to bottom, #fff, var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .nav-list { list-style: none; padding: 0; margin: 0; }

        /* Navigation Buttons */
        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            margin-bottom: 10px;
            text-decoration: none;
            color: var(--text-dim);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(44, 178, 76, 0.1);
            border-color: var(--primary);
            color: #fff;
            transform: translateX(8px);
        }

        .nav-item .icon { font-size: 1.8em; margin-right: 15px; }

        /* --- MAIN CONTENT AREA --- */
        .main-view {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            background: #000;
            overflow: hidden;
        }

        /* Wasserzeichen Hintergrund */
        .main-view::before {
            content: "";
            position: absolute;
            width: 100%; height: 100%;
            background: url('/dnd/items/Krark/shine-snatch.webp') no-repeat center;
            background-size: 60%;
            opacity: 0.15;
            transform: rotate(-10deg);
            pointer-events: none;
        }

/* Container für das Iframe */
.content-container {
    position: relative;
    width: 90%;
    height: 85%; /* Standardhöhe für Themes/Regeln */
    z-index: 5;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

/* Spezial-Regel für die Vorschau */
.content-container.preview-mode {
    width: 420px;
    height: auto; /* Höhe soll sich dem Inhalt anpassen */
    max-height: 90vh; /* Aber nicht über den Bildschirm hinausgehen */
}

#mainFrame {
    width: 100%;
    height: 100%; /* Füllt den Container aus */
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    background: rgba(0, 0, 0, 0.5);
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    transition: height 0.4s ease;
}

        #mainFrame {
            width: 100%;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .btn-refresh {
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            border: none; color: white;
            padding: 10px 25px; border-radius: 20px;
            cursor: pointer; font-family: inherit; font-weight: bold;
            display: none; /* Nur im Vorschau-Modus zeigen */
        }

        /* --- MOBILE NAVIGATION --- */
.mobile-header {
    display: none; /* Standardmäßig aus */
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 60px;
    background: var(--panel-bg);
    backdrop-filter: blur(15px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 100;
    padding: 0 20px;
    align-items: center;
    justify-content: space-between;
}

.mobile-brand { display: flex; align-items: center; gap: 10px; }
.mobile-brand img { width: 35px; }
.mobile-brand span { font-weight: bold; letter-spacing: 2px; color: var(--primary); }

.burger-menu {
    background: none; border: none; cursor: pointer;
    display: flex; flex-direction: column; gap: 6px; padding: 10px;
}
.burger-menu span {
    display: block; width: 25px; height: 2px;
    background: var(--text); transition: 0.3s;
}

/* --- RESPONSIVE DESIGN --- */
@media (max-width: 900px) {
    body { flex-direction: column; }

    .mobile-header { display: flex; }

    .sidebar {
        position: fixed;
        top: 60px; left: -100%; /* Versteckt links */
        width: 100%; height: calc(100vh - 60px);
        transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        background: rgba(5, 7, 5, 0.98);
        padding: 20px;
    }

    .sidebar.open { left: 0; }

    .brand { display: none; } /* In Sidebar auf Handy verstecken */

    .main-view {
        padding-top: 60px;
        height: 100vh;
    }

    .content-container { width: 95%; height: 80%; }
    
    .content-container.preview-mode {
        width: 100%;
        transform: scale(0.9); /* Ein bisschen verkleinern damit es passt */
    }
}

/* Burger Animation wenn offen */
.burger-menu.open span:nth-child(1) { transform: translateY(8px) rotate(45deg); }
.burger-menu.open span:nth-child(2) { opacity: 0; }
.burger-menu.open span:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
    </style>
</head>
<body>
    <!-- Mobile Header -->
<header class="mobile-header">
    <div class="mobile-brand" onclick="loadPreview()">
        <img src="https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp" alt="Logo">
        <span>SNATCH</span>
    </div>
    <button class="burger-menu" id="burgerBtn" onclick="toggleMenu()">
        <span></span>
        <span></span>
        <span></span>
    </button>
</header>

<div class="sidebar">
    <div class="brand" onclick="loadPreview()">
        <img src="https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp" alt="Logo" class="logo">
        <h1>SNATCH</h1>
        <p style="color: var(--accent); font-size: 0.7em; letter-spacing: 2px;">Kommando Zentrale</p>
    </div>

    <nav class="nav-list">
        <div class="nav-item" onclick="loadPreview(this)">
            <span class="icon">🎲</span><span>Zufalls-Vorschau</span>
        </div>
        <div class="nav-item" onclick="loadPage('themes.php', this)">
            <span class="icon">🎨</span><span>Themes</span>
        </div>
        <div class="nav-item" onclick="loadPage('shine-snatch_rules.php', this)">
            <span class="icon">📜</span><span>Regeln</span>
        </div>
        <div class="nav-item" onclick="loadPage('statistik.php', this)">
            <span class="icon">📊</span><span>Statistik</span>
        </div>
        <div class="nav-item" onclick="loadPage('card_edit.php', this)">
            <span class="icon">📝</span><span>Edit-Tool</span>
        </div>
        <div class="nav-item" onclick="loadPage('test.php', this)">
            <span class="icon">🧪</span><span>Test und Debug Tool</span>
        </div>
    </nav>
</div>

<div class="main-view">
    <div id="wrapper" class="content-container preview-mode">
        <iframe id="mainFrame" name="mainFrame"></iframe>
        <button id="refreshBtn" class="btn-refresh" onclick="loadPreview()">NEU WÜRFELN</button>
    </div>
</div>

<script>
const frame = document.getElementById('mainFrame');
const wrapper = document.getElementById('wrapper');
const refreshBtn = document.getElementById('refreshBtn');

// Funktion 1: Normale Seiten laden (Themes, Regeln, etc.)
function loadPage(url, element) {
    setActive(element);
    wrapper.classList.remove('preview-mode'); // Breite auf 90% setzen
    refreshBtn.style.display = 'none';
    frame.src = url;
}

// Funktion 2: Die spezielle API-Vorschau (POST Request)
async function loadPreview(element) {
    if(element) setActive(element);
    
    wrapper.classList.add('preview-mode'); 
    refreshBtn.style.display = 'block';

    const payload = {
        actorName: "Dashboard-Admin",
        theme: "zufall",
        world: "Dashboard-EyeCatcher",
        ownedCards: []
    };

    try {
        const response = await fetch('shine-snatch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        
        if (data.html) {
            const doc = frame.contentWindow.document;
            doc.open();
            doc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <link href="https://fonts.googleapis.com/css2?family=Signika:wght@300..700&display=swap" rel="stylesheet">
                    <style>
                        body { 
                            margin: 0; background: #000; 
                            display: inline-block; width: 400px; 
                            padding: 20px; color: white;
                            overflow: hidden;
                        }
                        * { box-sizing: border-box; }
                    </style>
                </head>
                <body><div id="realsize">${data.html}</div></body>
                </html>
            `);
            doc.close();

            // Warte einen Moment, bis das DOM im Iframe berechnet wurde
            setTimeout(() => {
                const contentHeight = doc.getElementById('realsize').scrollHeight + 40;
                // Setze die Höhe des Iframes UND des Wrappers
                frame.style.height = contentHeight + 'px';
                wrapper.style.height = contentHeight + 'px';
            }, 150);
        }
    } catch (e) { console.error(e); }
}

// Wichtig: In der loadPage Funktion müssen wir die feste Höhe wiederherstellen
function loadPage(url, element) {
    setActive(element);
    wrapper.classList.remove('preview-mode');
    wrapper.style.height = '85%'; // Zurück zur Standardhöhe
    frame.style.height = '100%';  // Iframe füllt 85% wieder aus
    refreshBtn.style.display = 'none';
    frame.src = url;
}

function setActive(el) {
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    if(el) el.classList.add('active');
}

// Initial beim Start die Vorschau laden
document.addEventListener('DOMContentLoaded', () => loadPreview());

const sidebar = document.querySelector('.sidebar');
const burgerBtn = document.getElementById('burgerBtn');

function toggleMenu() {
    sidebar.classList.toggle('open');
    burgerBtn.classList.toggle('open');
}

// Angepasste loadPage/loadPreview damit das Menü auf dem Handy schließt
const originalLoadPage = loadPage;
loadPage = function(url, element) {
    originalLoadPage(url, element);
    if(window.innerWidth <= 900) toggleMenu();
}

const originalLoadPreview = loadPreview;
loadPreview = function(element) {
    originalLoadPreview(element);
    // Nur schließen, wenn es durch einen Klick im Menü ausgelöst wurde
    if(element && window.innerWidth <= 900) toggleMenu();
}
</script>

</body>
</html>
