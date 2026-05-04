# StudyMate Pro v2 — Laravel (Railway Deploy)

## Cara Deploy ke Railway

### LANGKAH 1 — Upload ke GitHub

1. Extract zip ini
2. Buat repo baru di GitHub
3. Push semua fail ke repo

```bash
git init
git add .
git commit -m "StudyMate Pro Laravel"
git remote add origin https://github.com/USERNAME/studymate.git
git push -u origin main
```

### LANGKAH 2 — Deploy di Railway

1. Log masuk railway.app → **New Project** → **Deploy from GitHub repo**
2. Pilih repo awak
3. Railway akan detect PHP secara automatik via `nixpacks.toml`

### LANGKAH 3 — Tambah MySQL Database

1. Dalam Railway project → klik **+ New** → **Database** → **MySQL**
2. Railway akan auto-set environment variables:
   - `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

### LANGKAH 4 — Set Environment Variables

Dalam Railway → Settings → Variables, tambah:

```
APP_KEY=   ← Railway akan auto-generate bila build
GEMINI_API_KEY=AIzaSyAYVdfxgPT8PeFUSRdqAYQ2_PHl7VpQSw0
```

> **Nota:** DB variables akan auto-link dari MySQL service. APP_KEY akan di-generate oleh build command.

### LANGKAH 5 — Deploy!

Railway akan auto-build dan jalankan:
1. `composer install`
2. `php artisan key:generate`
3. `php artisan migrate` (create tables)
4. `php artisan serve --host=0.0.0.0 --port=$PORT`

---

## Struktur Fail

```
studymate-laravel/
├── app/Http/Controllers/StudyMateController.php  ← Logic utama
├── app/helpers.php                                ← formatMD helper
├── resources/views/studymate.blade.php            ← UI template
├── routes/web.php                                 ← URL routes
├── database/migrations/                           ← DB schema
├── config/                                        ← Laravel configs
├── nixpacks.toml                                  ← Railway build config
├── railway.json                                   ← Railway deploy config
└── .env.example                                   ← Template variables
```
