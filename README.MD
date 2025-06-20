SQL-Upgrade
=======
执行指定目录下所有文件内的sql

Installation
------------
```bash
composer require meioa/sql-upgrade
```

Example
-------
```php

$dbConfig = [
            'hostname'        =>  '127.0.0.1',            
            'database'        =>  'admin',          // 数据库名
            'username'        =>  'root',           // 用户名
            'password'        =>  '123123'          // 密码
            'hostport'        =>  '3306',           // 端口
            'charset'         =>  'utf8',           // 数据库编码
            ];
$sqlDir = "./data/sql";        
$sqlRes = (new SqlUpgrade($config))->run($sqlDie);