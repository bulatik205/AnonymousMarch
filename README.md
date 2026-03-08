# 🌷 Обмен поздравлениями в честь 8 марта

> <i>С 8 марта, девочки!</i>

<div align="center">
<img src="source/images/start.png" width="300">
</div>

## Как поднять бота?
1. Нужно поднять хостинг или купить SHARED с php 8.0+ 
2. Разместить все файлы
3. Можно подключить SFTP и выполнить 
```
git clone https://github.com/bulatik205/AnonymousMarch.git
```
4. Заменить `.config.php` на `config.php` (убать точку в начале) 
5. Добавить заполнить все поля в `config.php`
6. Поднять таблицы MySQL в зависимости от версии (⚠️ отличаются кодировки `COLLATE=utf8mb4_unicode_ci` для `5.7`, `COLLATE=utf8mb4_0900_ai_ci` для `8.0`)
6. Настроить `webhook` к `index.php`
7. Готово!  

> 🚧 Код в целом рабочий, но нужно переписывать `index.php`