#### MySQLBkp

Crea y restaura backups desde MySQL, para facilitar las tareaas de respaldo de tu sistema

## Basado en https://github.com/daniloaz/myphp-backup

### Instalación
```
composer require sincco/mysqlbkp
```

### Uso
#### Backup
```
$backupDatabase = new \Sincco\Tools\MySQLBkp('localhost', 'user', 'password', 'dbname', './bkp');
$backupDatabase->backupTables('*');
```

#### Restore
```
$backupDatabase = new \Sincco\Tools\MySQLBkp('localhost', 'user', 'password', 'dbname', './bkp', 'file.sql');
$restoreDatabase->restoreDb();
```

#### NOTICE OF LICENSE
This source file is subject to the Open Software License (OSL 3.0) that is available through the world-wide-web at this URL:
http://opensource.org/licenses/osl-3.0.php

**Happy coding!**
- [ivan miranda](http://ivanmiranda.me)
