<?php
/*
 * Шаблон настроек для api/request.php.
 * Скопируйте в api/config.php и впишите реальные значения.
 * config.php в git не попадает (см. .gitignore) и закрыт от браузера (см. api/.htaccess).
 */
return [
    // Куда приходят заявки
    'to_email' => 'you@example.com',

    // SMTP почтового ящика, от имени которого уходят письма.
    // Mail.ru: smtp.mail.ru, порт 465, пароль — «пароль для внешних приложений».
    'smtp_host' => 'smtp.mail.ru',
    'smtp_port' => 465,
    'smtp_user' => 'you@example.com',
    'smtp_pass' => '',

    // Отправитель. У Mail.ru адрес должен совпадать со smtp_user.
    'from_email' => 'you@example.com',
    'from_name'  => 'Сайт Avilon.IT',

    // Файл базы заявок. По умолчанию — папка data/ на уровень выше public_html,
    // чтобы базу нельзя было скачать через браузер.
    'db_path' => dirname(__DIR__, 2) . '/data/submissions.sqlite',
];
