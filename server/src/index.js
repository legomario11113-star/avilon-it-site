require('dotenv').config();

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');

require('./db'); // инициализирует файл БД и таблицу submissions при старте
const requestRouter = require('./routes/request');

const app = express();

// За реверс-прокси (Nginx) стоит явно доверять X-Forwarded-For,
// иначе req.ip всегда будет адресом прокси — и лимитер, и лог IP
// в базе будут бессмысленны.
app.set('trust proxy', 1);

app.use(helmet());

const corsOrigin = process.env.CORS_ORIGIN;
app.use(cors({
  origin: corsOrigin || false, // без CORS_ORIGIN в .env — запросы блокируются, никаких wildcard
  methods: ['POST', 'OPTIONS']
}));

app.use(express.json());

app.use('/api', requestRouter);

app.get('/health', (req, res) => res.json({ ok: true }));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Avilon.IT backend слушает порт ${PORT}`);
});
