# Avilon.IT — backend формы заявки

Node.js + Express сервис, принимающий `POST /api/request` с сайта, сохраняющий
заявку в SQLite и отправляющий письмо-уведомление на почту.

## 1. Локальный запуск

```bash
cd server
npm install
cp .env.example .env
```

Заполни `.env`. Для локальной проверки писем — не нужен реальный SMTP,
достаточно бесплатной песочницы [Mailtrap](https://mailtrap.io/): создай
инбокс → вкладка **SMTP Settings** → скопируй `Host`/`Port`/`Username`/
`Password` в `SMTP_HOST`/`SMTP_PORT`/`SMTP_USER`/`SMTP_PASS`. Письма будут
падать в инбокс Mailtrap, а не улетать реальным получателям.

`CORS_ORIGIN` на локальной машине можно временно указать как
`http://localhost:5500` (или адрес, с которого открываешь `index.html`), чтобы
браузер не блокировал запрос.

Запуск с автоперезагрузкой:

```bash
npm run dev
```

Обычный запуск (как в проде):

```bash
npm start
```

Проверить, что сервис жив: `GET http://localhost:3000/health` → `{"ok":true}`.

## 2. Деплой на VPS

Короткий чек-лист (Ubuntu/Debian):

1. **Node.js** — установить актуальную LTS-версию (например, через `nvm`
   или `apt`/`nodesource`).
2. **Код** — склонировать репозиторий на сервер, перейти в `server/`,
   `npm install --omit=dev`, создать `.env` с реальными значениями
   (`CORS_ORIGIN` — адрес сайта на GitHub Pages, реальные `SMTP_*` от
   почтового провайдера — Resend/Yandex/любой другой).
3. **PM2** — запустить процесс и держать его живым:
   ```bash
   npm install -g pm2
   pm2 start src/index.js --name avilon-it-backend
   pm2 save
   pm2 startup   # чтобы PM2 поднимался при перезагрузке сервера
   ```
4. **Nginx** — reverse proxy с домена/поддомена (например,
   `api.avilon.it`) на `127.0.0.1:3000`, только `POST /api/request` наружу
   действительно нужен.
5. **Certbot** — выпустить и подключить HTTPS-сертификат для домена Nginx:
   ```bash
   sudo certbot --nginx -d api.avilon.it
   ```
6. После получения адреса backend — обновить в `index.html` константу
   `API_URL` (сейчас там `http://localhost:3000/api/request`) на реальный
   `https://api.avilon.it/api/request`.
