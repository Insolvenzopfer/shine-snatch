<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shine Snatch Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Signika:wght@300;600&display=swap" rel="stylesheet">
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

        /* --- SIDEBAR AREA --- */
        .sidebar {
            width:50%;
            max-width: 450px;
            background: var(--panel-bg);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            padding: 40px 20px;
            z-index: 10;
        }

        .brand {
            text-align: center;
            margin-bottom: 50px;
        }

        .logo {
            width: 300px;
            filter: drop-shadow(0 0 20px rgba(44, 178, 76, 0.3));
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .logo:hover { transform: rotate(5deg) scale(1.1); }

        h1 {
            font-size: 1.8em;
            letter-spacing: 4px;
            margin: 15px 0 5px;
            background: linear-gradient(to bottom, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 18px 25px;
            margin-bottom: 12px;
            text-decoration: none;
            color: var(--text-dim);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
        }

        .nav-item .icon {
            font-size: 2.2em; /* Die gewünschten großen Icons */
            margin-right: 20px;
            filter: grayscale(0.5);
            transition: 0.3s;
        }

        .nav-item span:not(.icon) {
            font-weight: 600;
            font-size: 1.1em;
        }

        .nav-item:hover {
            background: rgba(44, 178, 76, 0.1);
            border-color: var(--primary);
            color: #fff;
            transform: translateX(10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        .nav-item:hover .icon {
            filter: grayscale(0) drop-shadow(0 0 8px var(--primary));
            transform: scale(1.2);
        }


.main-view {
    flex-grow: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    background: #000;
    overflow: hidden;
}

/* Ein Pseudo-Element für das Hintergrund-Logo, damit wir es drehen können */
.main-view::before {
    content: "";
    position: absolute;
    width: 120%;
    height: 120%;
    background: url('https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp') no-repeat center;
    background-size: 60%;
    opacity: 0.2; /* Extrem dunkel / dezent */
    transform: rotate(-10deg); /* Leichte Drehung für Dynamik */
    pointer-events: none; /* Klicks gehen durch das Logo durch zum Iframe */
}

        .preview-window {
            position: relative;
            width: 350px;
            height: 550px;
            
            background: #000;
            border-radius: 30px;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 50px rgba(0, 0, 0, 1), 0 0 30px rgba(44, 178, 76, 0.1);
        }

        .preview-window::before {
            content: "";
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(45deg, var(--primary), transparent, var(--accent), transparent);
            z-index: -1;
            border-radius: 32px;
            opacity: 0.3;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 20px;
        }

        .refresh-trigger {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(44, 178, 76, 0.4);
            transition: 0.3s;
            font-family: inherit;
        }

        .refresh-trigger:hover {
            background: var(--primary-bright);
            box-shadow: 0 0 25px var(--primary);
            transform: translateX(-50%) translateY(-3px);
        }

        /* Schöne Scrollbars */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <img src="https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp" alt="Logo" class="logo">
        <h1>SNATCH</h1>
        <p style="color: var(--accent); font-size: 0.8em; letter-spacing: 2px; margin-top: -5px;">COMMAND CENTER</p>
    </div>

    <div class="nav-list">
        <a href="themes.php" class="nav-item">
            <span class="icon">🎨</span>
            <span>Themes</span>
        </a>
        <a href="shine-snatch_rules.html" class="nav-item">
            <span class="icon">📜</span>
            <span>Regeln</span>
        </a>
        <a href="test.php" class="nav-item">
            <span class="icon">🧪</span>
            <span>Test-Tool</span>
        </a>
        <a href="debug.php" class="nav-item">
            <span class="icon">🛠️</span>
            <span>Debug</span>
        </a>
    </div>

    <div style="margin-top: auto; font-size: 0.7em; color: #333; text-align: center;">
        SYSTEM VERSION 1.0.0 - STABLE
    </div>
</div>

<div class="main-view">
    <div class="preview-window">
    <button class="refresh-trigger" onclick="refreshPreview()">ZUFALLS-PULL GENERIEREN</button>    
    <iframe id="previewFrame" src="about:blank"></iframe>
        
    </div>
</div>

<script>
async function refreshPreview() {
    const frame = document.getElementById('previewFrame');
    const apiUrl = 'shine-snatch.php'; 
    
    const payload = {
        actorName: "Dashboard-User",
        theme: "zufall",
        world: "Dashboard",
        ownedCards: []
    };

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        
        if (data.html) {
            const doc = frame.contentWindow.document;
            
            // Wir bauen ein komplettes HTML-Dokument MIT DOCTYPE zusammen
            const fullHtml = `<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Signika:wght@300..700&display=swap" rel="stylesheet">
    <style>
        body { 
            margin: 0; 
            padding: 10px; 
            background: #000; 
            display: flex; 
            justify-content: center; 
            overflow: hidden; 
        }
        /* Container-Fix gegen Quirks-Layout Verschiebungen */
        * { box-sizing: border-box; }
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 400px;">
        ${data.html}
    </div>
</body>
</html>`;

            doc.open();
            doc.write(fullHtml);
            doc.close();
        }
    } catch (err) {
        console.error("Dashboard Preview Error:", err);
    }
}

// Start beim Laden
document.addEventListener('DOMContentLoaded', refreshPreview);
</script>

</body>
</html>