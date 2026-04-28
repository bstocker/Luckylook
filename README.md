#Pour le mot de passe
Dans SSH
```
php -r "echo password_hash('nouveau-mot-de-passe', PASSWORD_DEFAULT);"
```
Le Hash sera enseuite à placer dans   
```
nano ~/www/admin/config.php
```
Et remplacez la ligne du hash par : define('ADMIN_PASSWORD_HASH', 'mon_nouveau_hash');