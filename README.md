# AgapAlert

Isang flood early-warning system para sa Barangay Dalongue, Santa Barbara, Pangasinan.

## Mga Tampok

- **Live na antas ng baha** — ina-update ng admin/opisyal, nakikita agad ng lahat ng residente
- **Mga evacuation center** — live na occupancy at ruta mula sa iyong lokasyon (MapLibre GL, Google-style UI + OSRM)
- **Pag-check-in/pag-check-out** — humihiling ang residente; inaaprubahan o tinatanggihan ng admin at awtomatikong ina-update ang occupancy
- **SOS** — hold-to-send, nai-save sa database, nakikita ng mga opisyal
- **Mga ulat ng komunidad** — residente at opisyal ay maaaring mag-ulat ng insidente
- **Panahon** — live weather mula sa Open-Meteo (walang API key)
- **Admin panel** — pamahalaan ang mga opisyal, invite code, antas ng baha, at occupancy ng shelters

## Pag-setup

### Kinakailangan

- XAMPP (Apache + MySQL, PHP 8.x)

### Mga Hakbang

1. Ilagay ang folder sa `C:\xampp\htdocs\agapalert`
2. I-start ang Apache at MySQL sa XAMPP Control Panel
3. Pumunta sa `http://localhost/phpmyadmin`, gumawa ng database na `agapalert`
4. I-run ang `http://localhost/agapalert/config/setup.php` sa browser
   - Sa kasalukuyang database, i-run din ang `migrate_shelter_requests.sql` para idagdag ang pila ng mga kahilingan sa evacuation center.
5. Pumunta sa `http://localhost/agapalert/login.html`

### Default Admin Account

```
Email:    admin@agapalert.ph
Password: admin123
```

> **Palitan ang password pagkatapos mag-login.**

## Istruktura ng Proyekto

```
agapalert/
├── api/                  PHP API endpoints
│   ├── admin/            Admin-only endpoints (pending, officials, codes, shelters)
│   ├── official/         Official-only endpoints (evacuation request review)
│   ├── profile/          Profile update, avatar, password
│   ├── flood.php         GET/POST flood level
│   ├── reports.php       GET/POST/PATCH incident reports
│   ├── shelters.php      GET shelter data
│   └── weather.php       GET live weather (Open-Meteo)
├── assets/
│   ├── css/              Stylesheets
│   ├── js/               Frontend JavaScript
├── config/               DB config, auth, mail + helpers, setup script
├── uploads/avatars/      Profile photos
├── database.sql          Schema + seed data
├── index.html            Dashboard + Admin panel (pangunahing app)
├── lib/phpmailer/        PHPMailer (para sa email ng password reset)
├── login.html            Login page
├── register.html         Registration page
├── forgot-password.html  Password reset (email code)
└── profile.html          Profile page
```

## Teknolohiya

| Bahagi | Gamit |
|--------|-------|
| Backend | PHP 8 + PDO (MySQL) |
| Frontend | Vanilla JS, walang framework |
| Mapa | MapLibre GL (Google-style UI) + OpenFreeMap tiles |
| Ruta | OSRM public routing API |
| Panahon | Open-Meteo API (libre, walang key) |
| Auth | PHP sessions + bcrypt |

## Mga API Endpoint

| Endpoint | Paraan | Paglalarawan |
|----------|--------|--------------|
| `api/login.php` | POST | Mag-sign in |
| `api/logout.php` | POST | Mag-sign out |
| `api/register.php` | POST | Gumawa ng account |
| `api/me.php` | GET | Kasalukuyang user |
| `api/forgot-password.php` | POST | Humiling ng reset code (email) |
| `api/reset-password.php` | POST | Ilagay ang code at bagong password |
| `api/flood.php` | GET/POST | Antas ng baha |
| `api/reports.php` | GET/POST/PATCH | Mga ulat at SOS |
| `api/shelters.php` | GET | Mga likasan |
| `api/checkins.php` | GET/POST | Status at mga kahilingan ng residente sa check-in/check-out |
| `api/weather.php` | GET | Live na panahon |
| `api/admin/pending.php` | GET/POST | Apruba/tanggihan ang opisyal |
| `api/admin/codes.php` | GET/POST/DELETE | Mga invite code |
| `api/admin/officials.php` | GET/POST | Mga opisyal |
| `api/admin/shelters.php` | GET/PATCH | Occupancy ng likasan |
| `api/official/checkins.php` | GET/POST | Apruba/tanggihan ng opisyal ang mga kahilingan sa check-in/check-out |

## Password reset sa pamamagitan ng email (libre)

Ang "Forgot password?" ay tumatanggap ng **email o numero ng mobile** at nagpapadala ng 6-digit na code na valid sa loob ng 10 minuto. Gumagamit ito ng libreng Gmail account (hindi kailangan ng bayad na SMS).

1. Gumawa ng Gmail account para sa sistema.
2. I-on ang 2-Step Verification sa account na iyon.
3. Google Account → Security → **App passwords** → gumawa ng isa na pinangalanang "AgapAlert" (16 na letra).
4. Buksan ang `config/mail.php`: ilagay ang Gmail sa `username`, ang app password sa `password`, at gawing `'enabled' => true`.
5. Siguraduhing naka-on ang `extension=openssl` sa `php.ini` (naka-on na ito sa XAMPP bilang default).

Kahit email o numero ng mobile ang inilagay ng user, sa **email ng account** laging ipinapadala ang code (hindi na SMS ang gamit sa password reset). Kinakailangan na ang email sa pag-sign up. Ang mga lumang account na walang email ay dapat magdagdag nito sa Profile. Ang PHPMailer (LGPL) ay nasa `lib/phpmailer/`. Naka-`.gitignore` ang `config/mail.php`; may kopya ng template sa `config/mail.example.php`. Huwag i-publish ang `config/mail.php` na may totoong password.

---

*AgapAlert — Dalongue, Santa Barbara, Pangasinan*
