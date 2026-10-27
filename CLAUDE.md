# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Static marketing site for Avilon.IT (IT outsourcing/outstaffing, Crimea). Plain HTML/CSS/JS — no framework, no bundler, no build step. Pages: `index.html` plus case studies in `cases/*.html`; `css/styles.css` and `js/main.js` are shared by all pages rather than duplicated per page. The request form is handled by a small PHP script in `api/` (see below). The frontend has no `package.json` of its own — `server/` (an older Node backend, not deployed) does.

**Hosting (production):** Timeweb virtual (shared) hosting, account `cr949641`, site directory `avilon-it/public_html` (PHP 8.2, Apache — no Node.js). Domain `avilon-it.ru` (bought 2026-10-24, registration was still pending that day; free Let's Encrypt SSL to be enabled once it resolves); technical domain `cr949641.tw1.ru`. There is no git/CI deploy: the user uploads files through the Timeweb file manager, usually as a zip Claude builds with Windows' `tar.exe -a -c -f x.zip ...` (Git Bash's `tar` produces a tar, not a zip). Upload only `index.html`, `.htaccess` (root, http→https redirect via `X-Forwarded-Proto`; only once the SSL cert is installed), `css/`, `js/`, `assets/`, `cases/`, `api/` (with the real `api/config.php`) — never `server/`, `CLAUDE.md`, `README.md`. Folder permissions on the host: 755, files 644. GitHub Pages still serves an old copy but is no longer the production site.

**Naming:** the company was renamed from TAVRIDA.IT to **Avilon.IT** (logo markup `Avilon<span>.IT</span>`, contact email `avilon-it@mail.ru`, Telegram `@AvilonIT`; form submissions go to the same mailbox). The GitHub repo is `legomario11113-star/avilon-it-site` (formerly `tavrida-it-site`), served by GitHub Pages at `https://legomario11113-star.github.io/avilon-it-site/` — the Pages origin `https://legomario11113-star.github.io` (used for `CORS_ORIGIN`) is unaffected by the rename. The local checkout lives at `Desktop/Avilon_IT/avilon-it-site` (renamed from `Tavrida_IT/tavrida-it-site`). Any "TAVRIDA" in old commits is the previous name, not something to restore. The `#testimonials` section was removed entirely — don't recreate it unless asked.

## Commands

The static site itself has no build/lint/test tooling (no root `package.json`).

- **Preview locally**: open `index.html` directly in a browser, or `npx serve .` from the repo root.
- **Verifying visual/behavioral changes on the frontend**: there's no permanent test suite. The established pattern for this repo is to temporarily install Playwright (`npm install --no-save playwright`, `npx playwright install chromium`), drive `file:///.../index.html` with a throwaway script to screenshot at desktop (1440px) and mobile (375px) widths and check `document.documentElement.scrollWidth` for horizontal overflow, then delete `node_modules`/`package.json`/`package-lock.json`/screenshots before committing. Keep this out of git — the repo root should stay dependency-free.
- **Form handler** (`api/request.php`, the one in production): a portable PHP 8.2 NTS (from `windows.php.net/downloads/releases/`, with `pdo_sqlite`, `openssl`, `mbstring` enabled in its `php.ini`) lives in `C:\php` since 2026-10-26 — not on PATH. The user runs it from the repo root: `C:\php\php.exe -c C:\php\php.ini -S 127.0.0.1:8099`, then opens `http://127.0.0.1:8099` (not `npx serve`/port 5500 — that can't execute PHP). From the repo root the default `db_path` resolves to `Desktop/Avilon_IT/data/submissions.sqlite`, outside the repo. With the real `api/config.php` present, local submissions send real email. Send Cyrillic test payloads as `\uXXXX` JSON escapes or `--data-binary @file` — Git Bash mangles raw Cyrillic in curl args. `api/.htaccess` isn't applied by `php -S`.
- **Old Node backend** (`server/`, not deployed — kept for a possible future VPS): `cd server && npm install && cp .env.example .env`, then `npm run dev` (nodemon) or `npm start`. See `server/README.md` for the full local-run and VPS-deploy checklist. `server/` has its own `.gitignore`-d `node_modules/`, `.env`, and `data/` (the SQLite file) — `package-lock.json` IS committed.
- **Running the full stack locally**: two processes — backend `cd server && npm run dev` (port 3000) and frontend `npx serve . -l 5500` from the repo root (pick a port other than 3000, `serve` defaults to it). Open the site via that `http://localhost:5500`, **not** `file://`: the backend's CORS allows exactly one origin (`CORS_ORIGIN` in `server/.env`), so for local testing set it to the exact address in the browser bar (`localhost` and `127.0.0.1` count as different origins), and set it back to `https://legomario11113-star.github.io` before deploying. `.env` is read only at startup — restart after editing it. Never re-run `cp .env.example .env` on a configured setup, it overwrites the real values. With `SMTP_*` empty, submissions are still saved and the API returns `{ ok: true, warning }`; there is no email/delivery log in the DB (only the `submissions` table). SMTP for the owner's mailbox is `smtp.mail.ru:465` with a Mail.ru "app password" (real credentials live only in the git-ignored `.env`).
- **Stopping stray processes** on Windows PowerShell: `Stop-Process -Id (Get-NetTCPConnection -LocalPort 3000 -State Listen).OwningProcess -Force` (same for 5500); an `ObjectNotFound` error just means nothing is listening.

## Architecture

### Design tokens

All colors/spacing/radii live as CSS custom properties at the top of `css/styles.css`. Key ones: `--color-accent` (terracotta `#C4622D`), `--color-secondary` (teal `#2E7D74`), `--color-dark-bg` (`#14151E`, used for header/hero/request-form/footer), `--color-peach-bg`/`--color-mint-bg` (pastel icon/card backgrounds), `--color-bg`/`--color-bg-card` (warm off-white light sections). Fonts are Manrope (body/headings) + IBM Plex Mono (labels/numbers), loaded from Google Fonts in `<head>`. `README.md`'s design-system section is stale (lists an old `#262B39` dark color) — treat `styles.css`'s `:root` block as the source of truth.

### Design source

The visual design was reverse-engineered from a PDF export of a Claude-Design mockup the user provided (not from the original text brief, which turned out to be a rougher approximation). If a future redesign request references "the mockup" again, ask for a fresh export/screenshot rather than assuming the old text brief still applies — the two disagreed significantly last time (card styles, section colors, hero illustration, etc.).

### Request form → PHP handler (api/)

The `#request` section's `<form id="request-form">` POSTs JSON to `api/request.php` (same origin, so no CORS) — submitted via `fetch` from the inline `<script>` at the bottom of `index.html` (not from `js/main.js`). Important non-obvious point: **the section id is `#request`, not `#request-form`** — the form element itself owns `id="request-form"`. This split is intentional: giving both the wrapping `<section>` and the `<form>` the same id would be invalid HTML and would break `document.getElementById('request-form')` (it would resolve to the section, not the form, since it's first in document order). All in-page anchors (`header`, hero CTA, footer CTA) point to `#request`.

The inline script's `API_URL` is the relative `'api/request.php'` (works because `index.html` sits at the site root; the form exists only there). The frontend sends JSON (`first_name`, `last_name`, `email`, `phone`, `message`, `website`) and expects `{ ok: true }` / `{ ok: true, warning }` / `{ ok: false, error }` back.

`api/request.php` (plain PHP 8.2, no Composer) mirrors what the Node backend did: honeypot `website` (filled → silent `{ ok: true }`), server-side validation, rate limit 5 submissions per IP per 10 minutes (counted from the `submissions` table), saves to SQLite via PDO at `db_path` (default `avilon-it/data/submissions.sqlite`, i.e. **outside** `public_html` so it can't be downloaded), and emails via a minimal built-in SMTP client (SSL 465 or STARTTLS 587, AUTH LOGIN) — Timeweb's panel has an "Отправка SMTP" switch that may need to be on for outbound SMTP. If only one of save/email succeeds it still answers `ok: true` with a `warning`, so the visitor doesn't resubmit. Settings (recipient, Mail.ru SMTP app password) live in git-ignored `api/config.php`, template `api/config.example.php`; `api/.htaccess` denies web access to both. Errors go to PHP `error_log` (Timeweb "Логи").

`server/` (Express + better-sqlite3 + nodemailer) is the previous implementation, written for a VPS. It isn't deployed — Timeweb shared hosting can't run a persistent Node process. Its `server/src/routes/request.js` has the same honeypot/validation/response contract.

`js/main.js`'s `initRequestForm()` function is dead code — it looks for a `[data-request-form]` attribute that doesn't exist on the form (removed back when the form was first wired to Formspree, and still absent now). It's harmless (returns early, no-op) but was intentionally left alone rather than removed, since form-related tasks have repeatedly been scoped to `index.html`/`api/` only. Don't be confused into thinking form submission is handled there; the real logic is the inline script in `index.html`, which talks to `api/request.php`.

### Form validation (client-side)

Field validation lives in its own inline `<script>` in `index.html`, placed **before** the submit/fetch script and after the IMask CDN tag (`https://cdn.jsdelivr.net/npm/imask@7/dist/imask.min.js`). Order matters: both scripts add a `submit` listener to `#request-form`, and listeners run in registration order, so the validation one must register first to call `stopImmediatePropagation()` and stop the fetch script from sending an invalid form. Neither script wraps its init in `DOMContentLoaded` for the same reason — don't add that, it would flip the order.

What it enforces: name/last name — letters (Cyrillic/Latin), hyphen, space only, blocked at keystroke via `beforeinput` (with an `input` fallback), 2–50 chars checked on blur; email — `maxlength=100` and a custom message on blur; phone — IMask `+7 (000) 000-00-00` with `lazy: false`, so the field is never natively empty and the `required` attribute cannot work there — phone validity is `phoneMask.unmaskedValue.length >= 10`, and phone deliberately has no `required` attribute; pasting an 11-digit `7…`/`8…` number is normalized in a `paste` handler; comment — `maxlength=500` with a live `N / 500` counter. The submit button is `disabled` (set by JS at runtime, never in the HTML, so the form still works natively if JS fails) until name, last name, email and phone are all valid.

Error/hint/counter elements are styled with inline `style` attributes (referencing `--color-accent`/`--color-text-muted`), not `css/styles.css` — that was a constraint of the task that added them, not a project rule; moving them into the stylesheet is fine. Each error slot has `min-height` and `line-height` set to the same px value so showing/hiding an error causes zero layout shift (measured 0px) — keep those two equal if you restyle.

### Markup ↔ script contract (read before any redesign)

The scripts find elements by id/attribute, so a UI overhaul must preserve these or the form/nav silently break: `#request-form`, `#request-submit`, `#request-status`; inputs `#first-name` (`name=first_name`), `#last-name` (`last_name`), `#email`, `#phone`, `#comment` (`name=message`), and the honeypot `name="website"`; error/counter slots `#first-name-error`, `#last-name-error`, `#email-error`, `#phone-error`, `#comment-counter`; `#footer-year`; and for `js/main.js`: `[data-site-header]`, `[data-nav-menu]`, `[data-nav-toggle]`. The `#request` section id is the target of the header/hero/footer CTAs.

### FAQ section

Rendered as always-expanded cards (no accordion/collapse JS) — this matches the reference mockup, which showed every answer visible at once rather than a click-to-expand pattern. Don't reintroduce accordion behavior unless asked.

### Icons

All icons are inline SVG (hand-written paths), no icon library/sprite sheet.

---

# Русская версия

Ниже — полный перевод этого файла на русский. Технически это дубликат
английской версии выше; правь обе части синхронно, если меняешь контент.

## Что это

Статический сайт-визитка Avilon.IT (IT-аутсорсинг/аутстаффинг, Крым). Чистые
HTML/CSS/JS — без фреймворка, без сборщика, без шага сборки. Страницы:
`index.html` и кейсы в `cases/*.html`; `css/styles.css` и `js/main.js` общие
для всех страниц, а не дублируются. Форму заявки обрабатывает небольшой
PHP-скрипт в `api/` (см. ниже). У самого фронтенда нет своего `package.json` —
он есть у `server/` (старый Node-бэкенд, не задеплоен).

**Хостинг (боевой):** виртуальный хостинг Timeweb, аккаунт `cr949641`, папка
сайта `avilon-it/public_html` (PHP 8.2, Apache — Node.js нет). Домен
`avilon-it.ru` (куплен 2026-10-24, в тот день ещё регистрировался; бесплатный
SSL Let's Encrypt включить, когда домен заработает); технический домен
`cr949641.tw1.ru`. Деплоя через git/CI нет: пользователь загружает файлы через
файловый менеджер Timeweb, обычно zip-архивом, который Claude собирает
Windows-овским `tar.exe -a -c -f x.zip ...` (`tar` из Git Bash делает tar, а
не zip). Загружать только `index.html`, `.htaccess` (в корне, редирект
http→https через `X-Forwarded-Proto`; только когда SSL-сертификат установлен),
`css/`, `js/`, `assets/`, `cases/`, `api/` (с настоящим `api/config.php`) — никогда `server/`, `CLAUDE.md`,
`README.md`. Права на хостинге: папки 755, файлы 644. На GitHub Pages ещё
лежит старая копия, но боевым сайтом она больше не является.

**Название:** компания переименована с TAVRIDA.IT на **Avilon.IT** (разметка логотипа
`Avilon<span>.IT</span>`, почта `avilon-it@mail.ru`, Telegram `@AvilonIT`; заявки с формы приходят на эту же почту). Репозиторий на GitHub —
`legomario11113-star/avilon-it-site` (раньше `tavrida-it-site`), сайт на GitHub
Pages — `https://legomario11113-star.github.io/avilon-it-site/`; origin Pages
`https://legomario11113-star.github.io` (он же `CORS_ORIGIN`) от переименования
не изменился. Локальная копия лежит в
`Desktop/Avilon_IT/avilon-it-site` (раньше `Tavrida_IT/tavrida-it-site`). «TAVRIDA» в старых коммитах — прежнее название, его не
возвращать. Секция `#testimonials` (отзывы) удалена полностью — не возвращать без
запроса.

## Команды

У статического сайта нет инструментов сборки/линта/тестов (нет `package.json`
в корне).

- **Локальный просмотр**: открыть `index.html` прямо в браузере, либо
  `npx serve .` из корня репозитория.
- **Проверка визуальных/поведенческих изменений во фронтенде**: постоянного
  набора тестов нет. Сложившийся для этого репозитория паттерн — временно
  установить Playwright (`npm install --no-save playwright`,
  `npx playwright install chromium`), прогнать `file:///.../index.html`
  одноразовым скриптом, сделать скриншоты на десктопной (1440px) и мобильной
  (375px) ширине, проверить `document.documentElement.scrollWidth` на
  горизонтальное переполнение, а затем удалить
  `node_modules`/`package.json`/`package-lock.json`/скриншоты перед коммитом.
  Всё это не должно попадать в git — корень репозитория должен оставаться без
  зависимостей.
- **Обработчик формы** (`api/request.php`, он и работает на сайте):
  портативный PHP 8.2 NTS (с `windows.php.net/downloads/releases/`, в его
  `php.ini` включены `pdo_sqlite`, `openssl`, `mbstring`) лежит в `C:\php` с
  2026-10-26 — не в PATH. Пользователь запускает его из корня репозитория:
  `C:\php\php.exe -c C:\php\php.ini -S 127.0.0.1:8099` и открывает
  `http://127.0.0.1:8099` (не `npx serve`/порт 5500 — тот не выполняет PHP).
  Из корня репозитория `db_path` по умолчанию указывает на
  `Desktop/Avilon_IT/data/submissions.sqlite`, то есть вне репозитория. С
  настоящим `api/config.php` локальные заявки отправляют реальные письма. Кириллицу в тестовых запросах слать как
  `\uXXXX`-экранирование в JSON или через `--data-binary @файл` — Git Bash
  портит кириллицу в аргументах curl. `api/.htaccess` в `php -S` не действует.
- **Старый Node-бэкенд** (`server/`, не задеплоен — оставлен на случай VPS):
  `cd server && npm install && cp .env.example .env`, затем `npm run dev` (nodemon) или `npm start`. Полный чек-лист локального
  запуска и деплоя на VPS — в `server/README.md`. У `server/` свой
  `.gitignore`-нутый `node_modules/`, `.env` и `data/` (файл SQLite) —
  `package-lock.json` при этом закоммичен.
- **Полный локальный запуск**: два процесса — backend `cd server && npm run dev`
  (порт 3000) и frontend `npx serve . -l 5500` из корня репозитория (порт
  выбирать не 3000 — `serve` по умолчанию занимает его). Сайт открывать по
  `http://localhost:5500`, **не** через `file://`: CORS backend'а пускает ровно
  один origin (`CORS_ORIGIN` в `server/.env`), поэтому для локального теста его
  нужно выставить точно как в адресной строке браузера (`localhost` и
  `127.0.0.1` — разные origin), а перед деплоем вернуть
  `https://legomario11113-star.github.io`. `.env` читается только при старте —
  после правки перезапускать. Никогда не повторять `cp .env.example .env` на
  уже настроенной установке — перезапишет реальные значения. При пустых `SMTP_*`
  заявки всё равно сохраняются, а API отвечает `{ ok: true, warning }`; лога
  доставки писем в БД нет (есть только таблица `submissions`). SMTP для почты
  владельца — `smtp.mail.ru:465` с «паролем для внешних приложений» Mail.ru
  (реальные данные лежат только в `.env`, который в git не попадает).
- **Остановка «застрявших» процессов** в Windows PowerShell:
  `Stop-Process -Id (Get-NetTCPConnection -LocalPort 3000 -State Listen).OwningProcess -Force`
  (аналогично для 5500); ошибка `ObjectNotFound` просто значит, что на порту
  никто не слушает.

## Архитектура

### Дизайн-токены

Все цвета/отступы/радиусы вынесены в CSS custom properties в начале
`css/styles.css`. Ключевые: `--color-accent` (терракотовый `#C4622D`),
`--color-secondary` (бирюзовый `#2E7D74`), `--color-dark-bg` (`#14151E`,
используется для шапки/hero/формы заявки/футера), `--color-peach-bg`/
`--color-mint-bg` (пастельные подложки иконок/карточек), `--color-bg`/
`--color-bg-card` (тёплые светлые секции). Шрифты — Manrope (текст/заголовки)
+ IBM Plex Mono (лейблы/цифры), подключены из Google Fonts в `<head>`.
Раздел «дизайн-система» в `README.md` устарел (там указан старый тёмный цвет
`#262B39`) — источником истины считать блок `:root` в `styles.css`.

### Источник дизайна

Визуальный дизайн был восстановлен по PDF-экспорту макета Claude Design,
который прислал пользователь (а не по исходному текстовому брифу — тот
оказался более грубым приближением). Если в будущем запросе на редизайн снова
упомянут «макет», нужно попросить свежий экспорт/скриншот, а не считать, что
старый текстовый бриф всё ещё актуален — в прошлый раз они заметно расходились
(стиль карточек, цвета секций, иллюстрация в hero и т.д.).

### Форма заявки → PHP-обработчик (api/)

Форма `<form id="request-form">` внутри секции `#request` отправляет JSON на
`api/request.php` (тот же origin, поэтому CORS не нужен) через `fetch` из инлайнового
`<script>` в конце `index.html` (а не из `js/main.js`). Важный неочевидный
момент: **id секции — `#request`, а не `#request-form`** — сам элемент
`<form>` владеет id `request-form`. Это разделение сделано намеренно: если и
обёртка `<section>`, и `<form>` получат одинаковый id, это будет невалидный
HTML и сломает `document.getElementById('request-form')` (он вернёт секцию, а
не форму, так как она первая в порядке документа). Все внутренние якоря
(шапка, кнопка в hero, кнопка в футере) ведут на `#request`.

Константа `API_URL` в инлайновом скрипте — относительный путь
`'api/request.php'` (работает, потому что `index.html` лежит в корне сайта;
форма есть только там). Фронтенд шлёт JSON (`first_name`, `last_name`,
`email`, `phone`, `message`, `website`) и ждёт в ответ `{ ok: true }` /
`{ ok: true, warning }` / `{ ok: false, error }`.

`api/request.php` (чистый PHP 8.2, без Composer) повторяет логику
Node-бэкенда: honeypot `website` (заполнено → тихий `{ ok: true }`), проверка
полей на сервере, лимит 5 заявок с IP за 10 минут (считается по таблице
`submissions`), сохранение в SQLite через PDO по пути `db_path` (по умолчанию
`avilon-it/data/submissions.sqlite`, то есть **вне** `public_html`, чтобы базу
нельзя было скачать) и письмо через встроенный минимальный SMTP-клиент (SSL 465
или STARTTLS 587, AUTH LOGIN) — в панели Timeweb есть переключатель «Отправка
SMTP», который может понадобиться включить. Если удалось только одно из двух
(сохранить или отправить), ответ всё равно `ok: true` с `warning`, чтобы
посетитель не отправлял заявку повторно. Настройки (получатель, пароль
приложения Mail.ru) — в `api/config.php`, который в git не попадает; шаблон —
`api/config.example.php`; `api/.htaccess` закрывает оба от браузера. Ошибки
пишутся в `error_log` PHP (раздел «Логи» в Timeweb).

`server/` (Express + better-sqlite3 + nodemailer) — предыдущая реализация,
написанная под VPS. Не задеплоена: виртуальный хостинг Timeweb не держит
постоянно работающий Node-процесс. Его `server/src/routes/request.js` имеет
тот же контракт honeypot/проверок/ответов.

Функция `initRequestForm()` в `js/main.js` — мёртвый код: она ищет атрибут
`[data-request-form]`, которого на форме нет (его убрали ещё когда форму
впервые подключали к Formspree, и его до сих пор нет). Функция безобидна
(просто выходит рано, ничего не делает), но её намеренно оставили как есть, а
не удалили, поскольку задачи по форме раз за разом ограничивались только
`index.html`/`api/`. Не путать: обработка отправки формы происходит не там,
а в инлайновом скрипте в `index.html`, который обращается к `api/request.php`.

### Валидация формы (на клиенте)

Валидация полей — отдельный инлайновый `<script>` в `index.html`, стоящий
**перед** скриптом отправки (fetch) и после тега CDN IMask
(`https://cdn.jsdelivr.net/npm/imask@7/dist/imask.min.js`). Порядок важен: оба
скрипта вешают `submit`-обработчик на `#request-form`, а обработчики
выполняются в порядке регистрации — валидация должна зарегистрироваться первой,
чтобы вызвать `stopImmediatePropagation()` и не дать скрипту отправки послать
невалидную форму. Поэтому ни один из скриптов не оборачивает инициализацию в
`DOMContentLoaded` — не добавлять, это перевернёт порядок.

Что проверяется: имя/фамилия — только буквы (кириллица/латиница), дефис,
пробел; лишние символы блокируются при вводе через `beforeinput` (с запасным
вариантом на `input`), длина 2–50 проверяется на blur; email — `maxlength=100`
и своё сообщение на blur; телефон — IMask `+7 (000) 000-00-00` с
`lazy: false`, поэтому поле никогда не бывает нативно пустым и атрибут
`required` там работать не может — валидность телефона это
`phoneMask.unmaskedValue.length >= 10`, а атрибута `required` у телефона
намеренно нет; вставка 11-значного номера `7…`/`8…` нормализуется в обработчике
`paste`; комментарий — `maxlength=500` и живой счётчик `N / 500`. Кнопка
отправки `disabled` (выставляется JS в рантайме, а не в HTML — чтобы форма
работала нативно, если JS не загрузился), пока имя, фамилия, email и телефон не
станут валидными.

Элементы ошибок/подсказки/счётчика оформлены инлайновыми `style` (ссылаются на
`--color-accent`/`--color-text-muted`), а не через `css/styles.css` — это было
ограничением задачи, которая их добавляла, а не правилом проекта; перенести их
в таблицу стилей можно. У каждого слота ошибки `min-height` и `line-height`
равны одному и тому же значению в px, поэтому показ/скрытие ошибки не даёт
сдвига раскладки (измерено: 0px) — при рестайлинге держать их равными.

### Контракт «разметка ↔ скрипты» (прочитать перед любым редизайном)

Скрипты ищут элементы по id/атрибутам, поэтому при переделке UI их нужно
сохранить, иначе форма/навигация молча сломаются: `#request-form`,
`#request-submit`, `#request-status`; поля `#first-name` (`name=first_name`),
`#last-name` (`last_name`), `#email`, `#phone`, `#comment` (`name=message`) и
honeypot `name="website"`; слоты ошибок/счётчика `#first-name-error`,
`#last-name-error`, `#email-error`, `#phone-error`, `#comment-counter`;
`#footer-year`; а для `js/main.js` — `[data-site-header]`, `[data-nav-menu]`,
`[data-nav-toggle]`. Id секции `#request` — цель CTA в шапке, hero и футере.

### Секция FAQ

Отрендерена как всегда развёрнутые карточки (без аккордеона/JS для
сворачивания) — это соответствует референсному макету, где все ответы были
видны одновременно, а не по клику. Не возвращать поведение аккордеона без
явного запроса.

### Иконки

Все иконки — инлайновый SVG (пути написаны руками), без библиотеки
иконок/спрайтов.
