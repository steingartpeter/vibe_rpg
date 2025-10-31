CREATE SCHEMA `vibe_rpg` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ;
USE vibe_rpg;
CREATE TABLE karakter (
    -- Alapvető azonosítók
    id INT(11) NOT NULL AUTO_INCREMENT,
    nev VARCHAR(100) NOT NULL UNIQUE, 

    -- Progresszió és Skill-ek
    szint INT(11) NOT NULL DEFAULT 1,
    xp INT(11) NOT NULL DEFAULT 0,
    
    -- Két fő skill, amit a felhasználó fejleszthet (Pl. 1-100 között)
    gyujto_skill INT(11) NOT NULL DEFAULT 1, 
    harc_skill INT(11) NOT NULL DEFAULT 1,
    
    -- Játékállapot (Pihenési Mutató / Energia)
    energia INT(11) NOT NULL DEFAULT 100, -- Maximum legyen 100, minimum 0
    
    -- Térkép Pozíció
    pos_x INT(11) NOT NULL DEFAULT 0,
    pos_y INT(11) NOT NULL DEFAULT 0,

    -- Létrehozási adatok (opcionális, de ajánlott)
    utolso_mentes TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
);
CREATE TABLE terkep_cella (
    id INT(11) NOT NULL AUTO_INCREMENT,
    karakter_id INT(11) NOT NULL, -- Melyik karakter térképe
    x_koordinata INT(11) NOT NULL,
    y_koordinata INT(11) NOT NULL,
    cella_tipus VARCHAR(50) NOT NULL, -- Pl. 'erdo', 'hegy', 'sik'
    
    PRIMARY KEY (id),
    UNIQUE KEY (karakter_id, x_koordinata, y_koordinata), -- Egy karakternek csak egy bejegyzése lehet egy adott koordinátán
    FOREIGN KEY (karakter_id) REFERENCES karakter(id) ON DELETE CASCADE
);

ALTER TABLE karakter
ADD skill_pontok INT(11) NOT NULL DEFAULT 0;