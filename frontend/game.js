const API_URL = "/vibe_rpg/backend/api.php"; // A PHP API végpontod elérési útja
let activeCharacter = null;

const MAP_SIZE = 12; // 12x12-es térkép
const TILE_TYPES = {
  erdo: { color: "#228B22", resource: "Fa", combat_chance: 30 },
  mezo: { color: "#8FBC8F", resource: "Gyógynövény", combat_chance: 15 },
  homok: { color: "#F0E68C", resource: "Kavics", combat_chance: 5 },
  tisztas: { color: "#90EE90", resource: "Bogyó", combat_chance: 10 },
  sziklas: { color: "#A9A9A9", resource: "Kő", combat_chance: 40 },
  mocsár: { color: "#556B2F", resource: "Iszap", combat_chance: 50 },
  viz: { color: "#4682B4", resource: null, combat_chance: 0 }, // Nem járható
};
const TILE_KEYS = Object.keys(TILE_TYPES); // ['erdo', 'mezo', ...]

let currentMap = [];

function generateRandomMap() {
  let map = [];
  for (let y = 0; y < MAP_SIZE; y++) {
    let row = [];
    for (let x = 0; x < MAP_SIZE; x++) {
      // Véletlen cellatípus kiválasztása
      const randomType =
        TILE_KEYS[Math.floor(Math.random() * TILE_KEYS.length)];
      row.push(randomType);
    }
    map.push(row);
  }
  // A kezdőcella mindig 'tisztás' legyen a biztonság kedvéért (pl. [0, 0])
  map[activeCharacter.pos_y][activeCharacter.pos_x] = "tisztas";
  currentMap = map;
}

function renderMap() {
  const container = document.getElementById("main-map");
  container.innerHTML = ""; // Előző térkép törlése

  currentMap.forEach((row, y) => {
    row.forEach((type, x) => {
      const cell = document.createElement("div");
      cell.className = "map-cell";

      // CSS beállítás a TILE_TYPES alapján
      cell.style.backgroundColor = TILE_TYPES[type].color;
      cell.style.width = "30px";
      cell.style.height = "30px";

      // Karakter ikon megjelenítése
      if (activeCharacter.pos_x === x && activeCharacter.pos_y === y) {
        cell.textContent = "🚶"; // Karakter ikon
        cell.style.fontSize = "20px";
        cell.style.textAlign = "center";
      }

      // Járhatóság jelzése (pl. víz)
      if (type === "viz") {
        cell.style.opacity = 0.5;
      }

      container.appendChild(cell);
    });
  });
}

// 1. API hívás funkció (változatlan)
async function callApi(action, data = {}) {
  const payload = { action, ...data };

  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    console.log(response);
    return response.json();
  } catch (error) {
    console.error("API hiba:", error);
    document.getElementById("message").textContent =
      "Kommunikációs hiba a szerverrel.";
    return { status: "error", message: "Hálózati hiba." };
  }
}

// 2. Játék betöltése és inicializálása (változatlan)
async function loadGame() {
  const nev = document.getElementById("characterName").value.trim();
  if (!nev) {
    document.getElementById("message").textContent =
      "Kérlek adj meg egy nevet.";
    return;
  }

  // Töröljük a korábbi hibaüzenetet
  document.getElementById("message").textContent = "";

  const result = await callApi("load_character", { nev: nev });

  if (result.status === "ok") {
    activeCharacter = result.karakter;
    generateRandomMap();
    renderMap();
    updateGameUI();
    document.getElementById("login").style.display = "none";
    document.getElementById("game").style.display = "block";
    addToLog(
      `Üdvözöllek ${activeCharacter.nev}! Sikeresen betöltve. Energia: ${activeCharacter.energia}.`
    );
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

    updateInventoryDisplay();

    // Megjelenítjük a skill kiosztó felületet, ha van pont
    updateSkillAllocationUI(activeCharacter.skill_pontok);
  }
}

// 4. Eseménynaplóhoz adás (változatlan)
function addToLog(text) {
  const logDiv = document.getElementById("log");
  logDiv.innerHTML =
    `<div>[${new Date().toLocaleTimeString()}] ${text}</div>` +
    logDiv.innerHTML;
}

// 5. TEVÉKENYSÉG INDÍTÁSA (FRISSÍTÉS!)
async function doActivity(type) {
  if (!activeCharacter) return;
  console.log("ACTIVITY: " + type);
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
    addToLog(
      `*** GRATULÁLOK! ${activeCharacter.nev} szintet lépett! Új szint: ${newLevel} ***`
    );
    // Itt jönne a pontelosztás UI, amit később hozzáadhatunk
  }
}

// 7. Gombok letiltása/engedélyezése a kérés ideje alatt
function toggleActivityButtons(disable) {
  document.querySelector("button[onclick=\"doActivity('collect')\"]").disabled =
    disable;
  document.querySelector("button[onclick=\"doActivity('combat')\"]").disabled =
    disable;
  document.querySelector("button[onclick=\"doActivity('rest')\"]").disabled =
    disable;
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
  document.querySelector("button[onclick=\"doActivity('collect')\"]").disabled =
    disable;
  document.querySelector("button[onclick=\"doActivity('combat')\"]").disabled =
    disable;
  document.querySelector("button[onclick=\"doActivity('rest')\"]").disabled =
    disable;

  // ÚJ: Skill kiosztó gombok letiltása is
  document.querySelector(
    "button[onclick=\"allocatePoint('gyujto')\"]"
  ).disabled = disable;
  document.querySelector("button[onclick=\"allocatePoint('harc')\"]").disabled =
    disable;
}

// game.js
async function moveCharacter(deltaX, deltaY) {
  if (!activeCharacter) return;

  toggleActivityButtons(true);

  const result = await callApi("move_character", {
    nev: activeCharacter.nev,
    delta_x: deltaX,
    delta_y: deltaY,
  });

  if (result.status === "ok") {
    activeCharacter = result.karakter;

    // Frissítjük a kijelzőt és a térképet
    updateGameUI();
    renderMap();

    // Mivel új helyre érkeztünk, az adott cella típusa is kell:
    const currentTileType =
      currentMap[activeCharacter.pos_y][activeCharacter.pos_x];
    addToLog(result.message + ` Az aktuális cella: **${currentTileType}**.`);

    // Itt lehetne ellenőrizni, hogy a cella nem járható (pl. víz)
    if (currentTileType === "viz") {
      addToLog("Figyelem: Ezt a cellát nem járhatod át!");
      // Később ezt a logikát a PHP-ban is ellenőrizni kell,
      // és vissza kell vonni a mozgást, ha nem járható.
    }
  } else {
    addToLog(`Hiba a mozgáskor: ${result.message}`);
  }

  toggleActivityButtons(false);
}

function updateInventoryDisplay() {
  // Használjuk az új ID-t a MODÁLON belül: 'inventoryDisplayContent'
  const invDiv = document.getElementById('inventoryDisplayContent');
  let invText = '';
  
  const inventory = activeCharacter.inventory;
  const items = Object.keys(inventory);

  if (items.length > 0) {
      items.forEach(item => {
          if (inventory[item] > 0) {
              invText += `${item}: ${inventory[item]} db\n`;
          }
      });
  } else {
      invText = 'A készleted üres.';
  }
  
  invDiv.textContent = invText;
}

function toggleInventoryModal() {
  const modal = document.getElementById('inventoryModal');
  
  if (modal.style.display === 'block') {
      // Elrejtés
      modal.style.display = 'none';
  } else {
      // Megjelenítés és a tartalom frissítése (ha szükséges)
      updateInventoryDisplay(); // Biztosítjuk, hogy a legfrissebb adatok jelenjenek meg
      modal.style.display = 'block';
  }
}