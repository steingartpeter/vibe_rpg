const API_URL = "/vibe_rpg/backend/api.php"; // A PHP API végpontod elérési útja
let activeCharacter = null;

// 1. API hívás funkció (változatlan)
async function callApi(action, data = {}) {
  const payload = { action, ...data };

  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    return response.json();
  } catch (error) {
    console.error("API hiba:", error);
    document.getElementById("message").textContent = "Kommunikációs hiba a szerverrel.";
    return { status: "error", message: "Hálózati hiba." };
  }
}

// 2. Játék betöltése és inicializálása (változatlan)
async function loadGame() {
  const nev = document.getElementById("characterName").value.trim();
  if (!nev) {
    document.getElementById("message").textContent = "Kérlek adj meg egy nevet.";
    return;
  }

  // Töröljük a korábbi hibaüzenetet
  document.getElementById("message").textContent = "";

  const result = await callApi("load_character", { nev: nev });

  if (result.status === "ok") {
    activeCharacter = result.karakter;
    updateGameUI();
    document.getElementById("login").style.display = "none";
    document.getElementById("game").style.display = "block";
    addToLog(`Üdvözöllek ${activeCharacter.nev}! Sikeresen betöltve. Energia: ${activeCharacter.energia}.`);
  } else {
    document.getElementById("message").textContent = result.message;
  }
}

// 3. Felület frissítése (változatlan)
function updateGameUI() {
  if (activeCharacter) {
    const xpNeeded = activeCharacter.szint * 100;

    document.getElementById("stats").textContent =
      `Név: ${activeCharacter.nev}\n` +
      `Szint: ${activeCharacter.szint}\n` +
      `XP: ${activeCharacter.xp} / ${xpNeeded} (következő szintig)\n` +
      `Energia: ${activeCharacter.energia} / 100\n` +
      `Kiosztható Skill Pontok: ${activeCharacter.skill_pontok}\n` + // ÚJ
      `Gyűjtő Skill: ${activeCharacter.gyujto_skill}\n` +
      `Harc Skill: ${activeCharacter.harc_skill}\n` +
      `Pozíció: (${activeCharacter.pos_x}, ${activeCharacter.pos_y})`;

    // Megjelenítjük a skill kiosztó felületet, ha van pont
    updateSkillAllocationUI(activeCharacter.skill_pontok);
  }
}

// 4. Eseménynaplóhoz adás (változatlan)
function addToLog(text) {
  const logDiv = document.getElementById("log");
  logDiv.innerHTML = `<div>[${new Date().toLocaleTimeString()}] ${text}</div>` + logDiv.innerHTML;
}

// 5. TEVÉKENYSÉG INDÍTÁSA (FRISSÍTÉS!)
async function doActivity(type) {
  if (!activeCharacter) return;

  // A gombokat letiltjuk, amíg a kérésre várunk
  toggleActivityButtons(true);

  const result = await callApi("do_activity", {
    nev: activeCharacter.nev, // Fontos: el kell küldeni a karakter nevét
    type: type, // collect, combat, vagy rest
  });

  if (result.status === "ok") {
    // Frissítjük a karaktert a szerverről kapott adatokkal
    activeCharacter = result.karakter;

    // Üzenet a logba
    addToLog(`**${type.toUpperCase()}** - ${result.message}`);

    // Frissítjük a kijelzőt
    updateGameUI();

    // Szintlépés ellenőrzése
    checkLevelUp(activeCharacter.szint);
  } else {
    // Hiba esetén (pl. kevés energia)
    addToLog(`Hiba: ${result.message}`);
  }

  // Engedélyezzük a gombokat
  toggleActivityButtons(false);
}

// 6. SZINTLÉPÉS LOGIKA (frontend oldali megjelenítés)
function checkLevelUp(newLevel) {
  const currentLevel = activeCharacter.szint;
  // Bár a PHP-ban még nincs implementálva a szintlépés (csak az XP nő),
  // érdemes előkészíteni a logikát:

  // Ha az XP meghalad egy bizonyos küszöböt, a szerver növeli a szintet.
  // Tegyük fel, hogy a szerver már megnövelte a szintet, és mi csak ellenőrizzük.
  if (newLevel > currentLevel) {
    addToLog(`*** GRATULÁLOK! ${activeCharacter.nev} szintet lépett! Új szint: ${newLevel} ***`);
    // Itt jönne a pontelosztás UI, amit később hozzáadhatunk
  }
}

// 7. Gombok letiltása/engedélyezése a kérés ideje alatt
function toggleActivityButtons(disable) {
  document.querySelector("button[onclick=\"doActivity('collect')\"]").disabled = disable;
  document.querySelector("button[onclick=\"doActivity('combat')\"]").disabled = disable;
  document.querySelector("button[onclick=\"doActivity('rest')\"]").disabled = disable;
}

// game.js - a többi függvény után
function updateSkillAllocationUI(points) {
  const allocationDiv = document.getElementById("skillAllocation");
  document.getElementById("skillPointsRemaining").textContent = points;

  if (points > 0) {
    allocationDiv.style.display = "block";
  } else {
    allocationDiv.style.display = "none";
  }
}

// game.js - új függvény a skill pont kiosztására
async function allocatePoint(skillType) {
  if (!activeCharacter || activeCharacter.skill_pontok < 1) {
    addToLog("Nincs elég skill pont a kiosztáshoz.");
    return;
  }

  // Gombok letiltása, amíg a kérésre várunk
  toggleActivityButtons(true);

  const result = await callApi("allocate_skill_point", {
    nev: activeCharacter.nev,
    skill_type: skillType,
  });

  if (result.status === "ok") {
    activeCharacter = result.karakter;
    updateGameUI();
    addToLog(result.message);
  } else {
    addToLog(`Hiba a skill kiosztáskor: ${result.message}`);
  }

  toggleActivityButtons(false);
}

// Bővítsd ki a toggleActivityButtons függvényt, hogy a skill gombokat is kezelje
function toggleActivityButtons(disable) {
  // ... meglévő aktivitás gombok letiltása
  document.querySelector("button[onclick=\"doActivity('collect')\"]").disabled = disable;
  document.querySelector("button[onclick=\"doActivity('combat')\"]").disabled = disable;
  document.querySelector("button[onclick=\"doActivity('rest')\"]").disabled = disable;

  // ÚJ: Skill kiosztó gombok letiltása is
  document.querySelector("button[onclick=\"allocatePoint('gyujto')\"]").disabled = disable;
  document.querySelector("button[onclick=\"allocatePoint('harc')\"]").disabled = disable;
}

// Megjegyzés: A szintlépéshez szükséges XP kiszámítását és az adatbázis frissítését
// mindenképpen a BIZTONSÁGOS PHP oldalon kell implementálni!
