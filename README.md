# GMF × ACSA — FLY FOR TWO platform

PHP 8.1+ / MySQL (MariaDB) platform for the George Music Festival website and the
FLY FOR TWO competition. There's no framework and no Composer, so it uploads to any cPanel host as-is.

| Page | What it is |
|---|---|
| `index.php` | Magazine-style festival website: cover, event spread, competition feature, line-up, mosaic gallery, videos, calendar, mailing list |
| `gallery.php` | Full photo gallery (albums + lightbox) and video section |
| `fly-for-two.php` | Competition landing page: Entry → Verify (email code) → Share → Confirmed |
| `dashboard.php` | Entrant dashboard: entries, personal referral link + QR, share buttons, ready-made messages, friends referred, leaderboard |
| `login.php` | Passwordless sign-in for entrants (email code) |
| `embed.php` | Entry form for ACSA's website (iframe) |
| `admin/` | Control room: reports, entrants + CSV export, draws, live draw screen, certification log, media, settings, team access |
| `api/entry.php` | Entry API (web, embed and ACSA server-to-server with `X-Api-Key`) |
| `api/report.php` | Read-only live reporting API for ACSA |
| `api/ussd.php` | USSD gateway callback (Africa's Talking format) |

## Deploy to a subdomain (cPanel)

1. **Create the subdomain** (e.g. `win.georgemusicalfestival.co.za`) in cPanel → Domains. Note its document root.
2. **Create a MySQL database and user** in cPanel → MySQL Databases, and give the user *All privileges* on it.
3. **Create a mailbox** such as `noreply@georgemusicalfestival.co.za`. It sends the verification codes.
4. **Upload** the contents of `gmf-flyfortwo-deploy.zip` into the subdomain's document root and extract it.
5. Visit `https://<subdomain>/install.php`. Enter the database details, set Mode to **Production**, choose **SMTP** for email (host `mail.georgemusicalfestival.co.za`, port 465 and the mailbox login), and create the first admin.
6. **Delete `install.php`** from the server.
7. Enable **AutoSSL/HTTPS** for the subdomain, then uncomment the two HTTPS lines at the top of `.htaccess`.
8. Sign in at `/admin/`. Check **Settings** (dates, prizes, terms) and send yourself a test entry.

`config/config.php` is created by the installer. It holds the DB login, the SMTP login, `partner_api_key`
(give it to ACSA for the reporting API), `cors_origins` (ACSA's domain for the embed), `ussd_secret`
(set this when a USSD gateway is contracted) and optional Cloudflare Turnstile keys.

## Tagging entries by channel

Every QR code should carry its source so reports can attribute entries:

```
/fly-for-two.php?src=qr_ground&pt=Garden+Route+Mall&town=George&slot=sat-am
/fly-for-two.php?src=qr_event&pt=Main+Gate
```

Use **Admin → Settings → QR link builder** to generate the link and a print-ready QR PNG.
Channels: `web`, `qr_ground`, `qr_event`, `ussd`, `acsa_embed`, `referral` (set automatically for share links).

## Competition rules as built (per the developer brief)

- One verified entry per phone number **and** per email address. The code is emailed, and entries don't count until verified.
- Share bonus: the first *other* person to open an entrant's link (`/r/CODE`) adds one bonus entry. The cap is configurable,
  defaulting to 1 as the brief requires. Opening your own link never counts.
- Referral entries per verified friend are available but **off by default** (Settings), because the brief caps bonus entries at one.
  The referral dashboard and leaderboard work either way.
- Rate limits on entries, codes and sign-ins; a honeypot plus a minimum fill time; optional Turnstile; and device fingerprints flagged in the admin as a secondary signal.
- Draws: automated, random and logged. Each run stores a random seed, a SHA-256 hash of the pool and a pool snapshot, so the winner
  can be recomputed independently. A redraw requires a recorded reason. **Certification log** prints to PDF or exports to CSV.
- The Event-Day prize, all dates and the close date, the terms, the privacy notice and the entry limits are all editable in the admin. None of them are hard-coded.

## Local development (XAMPP)

`http://localhost/gmf/`: with `mail.driver = 'log'` and `env = 'development'`, verification codes
appear on screen and in `storage/logs/mail.log`.
