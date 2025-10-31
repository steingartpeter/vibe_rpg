<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/functions.php'; // Függvények betöltése
require $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/db.php'; // Adatbázis kapcsolat betöltése



header('Content-Type: application/json'); // Minden válasz JSON formátumú lesz
header('Access-Control-Allow-Origin: *'); // Fejlesztéshez engedélyezzük a CORS-t

// 1. Kérés feldolgozása
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$nev = $input['nev'] ?? '';

$response = ['status' => 'error', 'message' => 'Ismeretlen hiba történt.'];

// 2. Akciók kezelése (SWITCH)
switch ($action) {
  case 'load_character':
    $response = loadCharacter($pdo, $nev);
    break;

  case 'do_activity':
    $tevekenyseg = $input['type'] ?? ''; // Itt type van, mert a JS-ben is azt használtuk
    $nev = $input['nev'] ?? '';
    $response = doActivity($pdo, $nev, $tevekenyseg);
    break;

  // ... Később: save_character, rest, stb.
  case 'allocate_skill_point':
    $skill_type = $input['skill_type'] ?? '';
    $nev = $input['nev'] ?? '';
    $response = allocateSkillPoint($pdo, $nev, $skill_type);
    break;
  case 'move_character':
    $delta_x = $input['delta_x'] ?? 0;
    $delta_y = $input['delta_y'] ?? 0;
    $nev = $input['nev'] ?? '';
    $response = moveCharacter($pdo, $nev, (int)$delta_x, (int)$delta_y);
    break;
  default:
    $response = ['status' => 'error', 'message' => 'Érvénytelen akció.'];
    break;
}

// 3. Válasz küldése a kliensnek
echo json_encode($response);
exit;

// 4. KARAKTER BETÖLTÉSE FÜGGVÉNY
function loadCharacter(PDO $pdo, string $nev): array
{
  $sql = "SELECT * FROM karakter WHERE nev = :nev";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':nev', $nev, PDO::PARAM_STR);
  $stmt->execute();
  $karakter = $stmt->fetch();

  if ($karakter) {
    // 2. Inventory lekérése a karakter ID alapján
    $karakter['inventory'] = getInventory($pdo, $karakter['id']);

    // Fontos: Az ID-t ne küldjük vissza a frontendre, ha nem muszáj.
    unset($karakter['id']);
    return ['status' => 'ok', 'karakter' => $karakter];
  } else {
    return ['status' => 'error', 'message' => 'A megadott nevű karakter nem található.'];
  }
}

function doActivity(PDO $pdo, string $nev, string $type): array
{
  // 1. Karakter betöltése
  $charResult = loadCharacter($pdo, $nev);
  if ($charResult['status'] !== 'ok') {
    return ['status' => 'error', 'message' => 'Karakter nem található.'];
  }
  // Ezt kell megtenned, mivel a loadCharacter eltávolítja az ID-t a visszatérés előtt, 
  // de az adatbázishoz mégis szükségünk van rá:
  $temp_char_id = $charResult['karakter']['id'];
  $char = $charResult['karakter'];
  $char = $charResult['karakter'];

  // 2. Energia ellenőrzés
  $energia_kell = 10;
  if ($char['energia'] < $energia_kell) {
    return ['status' => 'error', 'message' => 'Nincs elég energia ehhez a tevékenységhez. Pihenj!'];
  }

  // 3. Tevékenység végrehajtása (egyszerű logika)
  $xp_nyerese = 0;
  $log_message = '';
  $nyersanyag_nev = null;
  $nyersanyag_mennyiseg = 0;

  if ($type === 'collect') {
    // Példa: Minden gyűjtés ad 1-3 nyersanyagot
    $nyersanyag_mennyiseg = rand(1, 3);
    $nyersanyag_nev = "Fa"; // Később ezt a cellatípushoz kötjük!
    // Gyűjtés: XP nyerés a skill szint alapján
    $xp_nyerese = $char['gyujto_skill'] * 2;
    $log_message = "Sikeresen gyűjtöttél nyersanyagot. ";
  } elseif ($type === 'combat') {
    // Harc: XP nyerés a harc skill szint alapján
    $xp_nyerese = $char['harc_skill'] * 3;
    $log_message = "Megküzdöttél egy szörnnyel. ";
  } elseif ($type === 'rest') {
    // Pihenés: Energia növelése, XP nyerés nulla
    $energia_vissza = 30;
    $char['energia'] = min(100, $char['energia'] + $energia_vissza);
    $log_message = "Pihentél. Visszatöltöttél {$energia_vissza} energiát.";

    $energia_kell = 0; // Pihenés nem fogyaszt energiát
    $xp_nyerese = 0;
  } else {
    return ['status' => 'error', 'message' => 'Ismeretlen tevékenység típus.'];
  }

  // ÚJ LOGIKA: Inventory frissítése a gyűjtés után
  if ($nyersanyag_mennyiseg > 0 && $nyersanyag_nev !== null) {
    // 1. Inventory frissítése az adatbázisban
    updateInventory($pdo, $charResult['karakter']['id'], $nyersanyag_nev, $nyersanyag_mennyiseg);
    $log_message .= " +{$nyersanyag_mennyiseg} {$nyersanyag_nev} gyűjtve!";

    // 2. Frissítjük a karakter objektumot is, hogy visszaküldjük a frontendnek
    if (!isset($char['inventory'][$nyersanyag_nev])) {
      $char['inventory'][$nyersanyag_nev] = 0;
    }
    $char['inventory'][$nyersanyag_nev] += $nyersanyag_mennyiseg;
  }

  // 4. Statisztikák frissítése
  $char['energia'] -= $energia_kell;
  $char['xp'] += $xp_nyerese;
  $log_message .= "+{$xp_nyerese} XP-t szereztél.";

  // ÚJ LOGIKA: SZINTLÉPÉS ELLENŐRZÉSE
  $level_up_occurred = false;
  $level_up_message = '';

  // Szintlépés feltétel: (Szint * 100) XP kell a következő szinthez
  // Pl. 1. szinthez 100 XP, 2. szinthez 200 XP, stb.
  while ($char['xp'] >= ($char['szint'] * 100)) {
    $xp_for_next_level = $char['szint'] * 100;
    $char['xp'] -= $xp_for_next_level; // XP maradvány
    $char['szint'] += 1; // Új szint
    $char['skill_pontok'] += 1; // +1 Skill pont
    $level_up_occurred = true;
  }

  if ($level_up_occurred) {
    $level_up_message = " *** SZINTET LÉPTÉL! Új szint: {$char['szint']}! +1 Skill Pontot kaptál. ***";
  }
  $log_message .= $level_up_message;

  // 5. Adatbázis frissítése (mentés)
  $sql_update = "UPDATE karakter SET xp = :xp, energia = :energia, szint = :szint, skill_pontok = :skill_pontok WHERE nev = :nev";
  $stmt_update = $pdo->prepare($sql_update);
  $stmt_update->execute([
    ':xp' => $char['xp'],
    ':energia' => $char['energia'],
    ':szint' => $char['szint'], // ÚJ MEZŐ
    ':skill_pontok' => $char['skill_pontok'], // ÚJ MEZŐ
    ':nev' => $nev
  ]);

  // 6. Válasz küldése
  return [
    'status' => 'ok',
    'karakter' => $char, // A frissített statisztikákat visszaküldjük
    'message' => $log_message
  ];
}

function allocateSkillPoint(PDO $pdo, string $nev, string $skill_type): array
{
  // 1. Karakter betöltése és ellenőrzés
  $charResult = loadCharacter($pdo, $nev);
  if ($charResult['status'] !== 'ok') {
    return ['status' => 'error', 'message' => 'Karakter nem található.'];
  }
  $char = $charResult['karakter'];

  if ($char['skill_pontok'] < 1) {
    return ['status' => 'error', 'message' => 'Nincs felhasználható skill pontod.'];
  }

  // 2. Skill frissítése
  $target_skill = '';

  if ($skill_type === 'gyujto') {
    $char['gyujto_skill'] += 1;
    $target_skill = 'gyujto_skill';
  } elseif ($skill_type === 'harc') {
    $char['harc_skill'] += 1;
    $target_skill = 'harc_skill';
  } else {
    return ['status' => 'error', 'message' => 'Érvénytelen skill típus.'];
  }

  $char['skill_pontok'] -= 1; // Felhasználtuk a pontot

  // 3. Adatbázis frissítése
  $sql_update = "UPDATE karakter SET {$target_skill} = :skill_ertek, skill_pontok = :pontok WHERE nev = :nev";
  $stmt_update = $pdo->prepare($sql_update);
  $stmt_update->execute([
    ':skill_ertek' => $char[$target_skill],
    ':pontok' => $char['skill_pontok'],
    ':nev' => $nev
  ]);

  return [
    'status' => 'ok',
    'karakter' => $char,
    'message' => "Sikeresen elköltöttél egy pontot a(z) **{$skill_type}** skillre. Új szint: {$char[$target_skill]}."
  ];
}

// api.php - az API fájl aljára
function moveCharacter(PDO $pdo, string $nev, int $delta_x, int $delta_y): array
{
  $charResult = loadCharacter($pdo, $nev);
  if ($charResult['status'] !== 'ok') {
    return ['status' => 'error', 'message' => 'Karakter nem található.'];
  }
  $char = $charResult['karakter'];

  $new_x = $char['pos_x'] + $delta_x;
  $new_y = $char['pos_y'] + $delta_y;

  // Alapvető határ ellenőrzés (12x12)
  if ($new_x < 0 || $new_x >= 12 || $new_y < 0 || $new_y >= 12) {
    return ['status' => 'error', 'message' => 'Nem mehetsz ki a térkép határán!'];
  }

  // 2. Adatbázis frissítése (mentés)
  $sql_update = "UPDATE karakter SET pos_x = :x, pos_y = :y WHERE nev = :nev";
  $stmt_update = $pdo->prepare($sql_update);
  $stmt_update->execute([
    ':x' => $new_x,
    ':y' => $new_y,
    ':nev' => $nev
  ]);

  $char['pos_x'] = $new_x;
  $char['pos_y'] = $new_y;

  return [
    'status' => 'ok',
    'karakter' => $char,
    'message' => "Sikeres mozgás a(z) ({$new_x}, {$new_y}) koordinátára."
  ];
}

// api.php
function getInventory(PDO $pdo, int $karakter_id): array
{
  $sql = "SELECT targy_nev, mennyiseg FROM inventory WHERE karakter_id = :id AND mennyiseg > 0";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id', $karakter_id, PDO::PARAM_INT);
  $stmt->execute();

  // Asszociatív tömbbé alakítjuk: ['Fa' => 15, 'Kő' => 5]
  $inventory = [];
  while ($row = $stmt->fetch()) {
    $inventory[$row['targy_nev']] = (int)$row['mennyiseg'];
  }
  return $inventory;
}

/**
 * Növeli a tárgy mennyiségét, ha van, vagy beszúr egy új sort, ha nincs.
 * Ezt nevezik UPSERT műveletnek.
 */
function updateInventory(PDO $pdo, int $karakter_id, string $targy_nev, int $mennyiseg): void
{
  $sql = "INSERT INTO inventory (karakter_id, targy_nev, mennyiseg) 
          VALUES (:karakter_id, :targy_nev, :mennyiseg)
          ON DUPLICATE KEY UPDATE mennyiseg = mennyiseg + :mennyiseg";
  // Ha már létezik a bejegyzés (PRIMARY KEY konfliktus), frissítjük a mennyiséget

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':karakter_id' => $karakter_id,
    ':targy_nev' => $targy_nev,
    ':mennyiseg' => $mennyiseg
  ]);
}
