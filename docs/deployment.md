# Deploying Clairvoyance

How to put Clairvoyance online cheaply and keep it running: one shared hosting plan, deploys
from GitHub, daily backups and an uptime check. It takes about an hour the first time.

## What it runs on

Any host with **PHP 8.1+** (8.2 recommended), **MariaDB 10.4+**, Apache or LiteSpeed with
`.htaccess`, **SSH**, **cron**, and a mailbox you can send from over SMTP.

These instructions use **Hostinger's Premium shared hosting** (around US$3–4 a month on a
12-month plan, which includes a free domain for the first year, SSL certificates, mailboxes
and backups). Menu names in its panel (hPanel) change from time to time; the steps stay the
same on any similar host (cPanel works too).

- **MariaDB, not MySQL:** use MariaDB. The schema uses a MariaDB-style generated column that
  MySQL 8 rejects.
- **Avoid free hosts:** hosts such as InfinityFree put a bot check in front of every request,
  which breaks the app's API calls.

### How the files are laid out on the server

```
~/domains/example.com/
  public_html/          the web app (built pages) and /api, the API's public entry point
  clairvoyance/         the API's code, its .env, sessions, logs and backups
                        (outside public_html, so none of it can be downloaded)
```

The app and the API share one address (`https://example.com` and `https://example.com/api`),
so there's no cross-site setup.

## 1. Set up the hosting (once)

1. **Buy the plan and the domain.** Choose a 12-month term. The renewal price is higher, but
   a year is enough for a thesis.
2. **Set up SSL:** in hPanel, go to Security › SSL and install the free certificate for the
   domain. Wait until it shows as active.
3. **Pick the PHP version:** in Advanced › PHP Configuration, choose PHP 8.2.
4. **Create the database:** in Databases › MySQL Databases, create a database and a user, and
   write down all three names and the password. The names look like `u123456789_clairvoyance`.
5. **Create the mailbox:** in Emails, create `noreply@example.com` and give it a strong
   password.
   - Hostinger adds the SPF and DKIM records that keep its mail out of spam folders when the
     domain is registered with them.
   - The SMTP server is `smtp.hostinger.com`, port 465, SSL.
6. **Turn on SSH:** in Advanced › SSH Access, enable it and note the IP, port (usually
   `65002`) and username (`u123456789`).
7. **Write the server's settings.** Connect with `ssh -p 65002 u123456789@<ip>`, then run:

   ```bash
   mkdir -p ~/domains/example.com/clairvoyance
   nano ~/domains/example.com/clairvoyance/.env
   ```

   Paste this, fill it in, and save with Ctrl+O, Enter, Ctrl+X:

   ```ini
   DB_HOST=localhost
   DB_NAME=u123456789_clairvoyance
   DB_USER=u123456789_clairvoyance
   DB_PASS=the-database-password

   APP_URL=https://example.com
   ALLOWED_ORIGINS=

   MAIL_HOST=smtp.hostinger.com
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USER=noreply@example.com
   MAIL_PASS=the-mailbox-password
   MAIL_FROM=noreply@example.com
   MAIL_FROM_NAME=Clairvoyance
   ```

   Then run `chmod 600 ~/domains/example.com/clairvoyance/.env` so only your account can read
   it.

**If email doesn't arrive or lands in spam,** use Brevo's free plan (300 emails a day)
instead: set `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587` and `MAIL_ENCRYPTION=tls`, with
the SMTP login Brevo gives you, and verify the domain in Brevo.

## 2. Connect GitHub (once)

The workflow in `.github/workflows/ci.yml` does two things:

- **On every push:** runs all the tests.
- **When you start it by hand:** builds the app, uploads it over SSH, updates the database
  and checks that the site answers.

1. **Make a key pair just for deploying** (on your own computer):

   ```bash
   ssh-keygen -t ed25519 -f clairvoyance_deploy -N "" -C "github deploy"
   ```

2. **Add the public key to the server:** in hPanel › SSH Access › SSH keys, paste the
   contents of `clairvoyance_deploy.pub`.
3. **Add the repository secrets:** in GitHub, open the repository's Settings › Secrets and
   variables › Actions, and add:

   | Secret | Value |
   |---|---|
   | `SSH_HOST` | the server IP |
   | `SSH_PORT` | `65002` (or what hPanel shows) |
   | `SSH_USER` | `u123456789` |
   | `SSH_KEY` | the whole contents of `clairvoyance_deploy`, the private key |
   | `REMOTE_DIR` | `/home/u123456789/domains/example.com` |

4. **Delete `clairvoyance_deploy` from your computer** once it's saved in GitHub, or store it
   somewhere safe.

## 3. Deploy

1. Push your changes, then go to the repository's **Actions** tab, select **Test and
   deploy**, then **Run workflow**. Pick the branch and leave **deploy** ticked.
2. When it's green, open `https://example.com/api/health`. It should show `"ok":true`.
3. Open `https://example.com`, sign up, confirm your email from the message you receive,
   and go through onboarding.

The first run creates the database tables, and later runs apply new migrations. Each deploy
leaves alone the server's `.env`, sessions, logs, backups and the certificate files in
`public_html/.well-known`.

### Deploying without SSH or GitHub

If your plan has no SSH:

1. Run `bash scripts/release.sh` locally. It produces `release/` and, if `zip` is installed,
   `release.zip`.
2. Upload the zip in hPanel's File Manager to `domains/example.com/` and extract it. It holds
   `public_html/` and `clairvoyance/`.
3. Create `clairvoyance/.env` as in step 1.7.
4. Create the tables: add a cron job (step 4) with the command
   `php ~/domains/example.com/clairvoyance/scripts/migrate.php`, let it run once, then delete
   it.

## 4. Backups and cron

In hPanel › Advanced › Cron Jobs, add a job that runs once a day, for example at 03:00:

```
cd ~/domains/example.com/clairvoyance && php scripts/daily.php
```

It writes a compressed copy of the database to `clairvoyance/storage/backups/`, keeping the
last 14. It also clears out expired links and old sign-in throttling counters. If a backup
fails, the job exits with an error and the cron email tells you.

Hostinger's own weekly backups are a second safety net. Before an important demo, also
download the newest file from `storage/backups/` to your computer.

To restore a backup (this replaces everything in the database):

```bash
cd ~/domains/example.com/clairvoyance
gunzip -c storage/backups/u123456789_clairvoyance-2026-10-19.sql.gz | mysql -u <DB_USER> -p <DB_NAME>
```

## 5. Uptime monitoring

Create a free [UptimeRobot](https://uptimerobot.com) account and add an HTTP(s) monitor for
`https://example.com/api/health` every 5 minutes. That URL only answers `ok` when PHP and the
database both work, and UptimeRobot emails you when it stops.

## Sample data for a presentation

To load the sample dataset (6 KPIs, 14 units, a year of monthly figures):

1. Sign up and create an organisation.
2. Run this over SSH:

   ```bash
   cd ~/domains/example.com/clairvoyance && php scripts/seed_demo.php you@example.com
   ```

It loads into the organisation that account administers, and it's safe to run twice.

## Rolling back

- **Code:** revert the bad commit (`git revert <commit>`), push, and run the workflow again.
- **Database:** migrations only move forward. If a release changed the database, restore the
  backup taken before it (see step 4).

## When something goes wrong

| Symptom | Look at |
|---|---|
| "Could not reach the server" in the app | `https://example.com/api/health`; then `clairvoyance/storage/logs/api.log` |
| "Something went wrong on the server" | `clairvoyance/storage/logs/api.log` has the real error |
| Emails never arrive | The log shows the SMTP error. Check `MAIL_*` in `.env`, and the mailbox's sending limits |
| Emails go to spam | Make sure SPF and DKIM are set for the domain (hPanel › Emails › DNS records), or switch to Brevo |
| Pages 404 on refresh | `public_html/.htaccess` is missing; deploy again |
| Signed out on every page | `APP_URL` must start with `https://` exactly as people open the site |
| Someone is locked out | After 5 wrong passwords an email is locked for 15 minutes. "Forgot password?" still works |

## Before demo day

- [ ] `/api/health` is ok and UptimeRobot is green
- [ ] Sign-up, email confirmation, invitation email and password reset all work on the live
      site, from a phone too
- [ ] The cron job ran today: there's a new file in `storage/backups/`
- [ ] A fresh backup is downloaded to your computer
- [ ] The presentation account and its data are ready; the password is written down offline
