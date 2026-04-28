<?php
// 1. Copiez ce fichier en "config.php" dans le même répertoire (admin/)
// 2. Générez votre hash de mot de passe via SSH sur Alwaysdata :
//      php -r "echo password_hash('votre-mot-de-passe', PASSWORD_DEFAULT);"
// 3. Collez le résultat ci-dessous à la place de REMPLACEZ_PAR_VOTRE_HASH

define('ADMIN_PASSWORD_HASH', 'REMPLACEZ_PAR_VOTRE_HASH');
