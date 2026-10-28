# Endoc Biotech HRMS — Live Deploy (MilesWeb)

**Live URL:** https://endoc-hrms.oceanhub.co.in/  
**GitHub:** https://github.com/SagarTuver7819/Endoc_HRMS

---

## 1) cPanel setup (one time)

1. **Subdomain:** cPanel → Domains → `endoc-hrms.oceanhub.co.in`
   - Document root note karo (e.g. `/home/CPANELUSER/endoc-hrms.oceanhub.co.in`)
2. **PHP:** cPanel → Select PHP Version / MultiPHP Manager → **PHP 8.1+**
   - Extensions on: `mysqli`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `curl`, `fileinfo`, `intl`
3. **Database:** cPanel → MySQL Databases
   - DB create: `CPANELUSER_Endoc_hrms`
   - User create + strong password
   - User ne DB ma **ALL PRIVILEGES** aapo
4. **SSL:** cPanel → SSL/TLS Status → subdomain mate AutoSSL run karo

---

## 2) Code upload (Terminal)

```bash
cd ~/endoc-hrms.oceanhub.co.in        # subdomain document root
ls -A                                  # khali hovu joiye (default files hoy to remove karo)
git clone https://github.com/SagarTuver7819/Endoc_HRMS.git .
```

---

## 3) Database import (Terminal)

Local export file: `C:\xampp\db_backups\endoc_new_hrms_27_10_26_hrms_live.sql`
→ cPanel File Manager thi home folder (`~`) ma upload karo, pachi:

```bash
mysql -u CPANELUSER_dbuser -p CPANELUSER_Endoc_hrms < ~/endoc_new_hrms_27_10_26_hrms_live.sql
rm ~/endoc_new_hrms_27_10_26_hrms_live.sql
```

(Alternative: phpMyAdmin → DB select → Import → file choose.)

---

## 4) Live `.env` (Terminal)

```bash
cd ~/endoc-hrms.oceanhub.co.in
cp .env.example .env
nano .env
```

Values:

```
APP_ENV=production
APP_URL=https://endoc-hrms.oceanhub.co.in
APP_BASE_PATH=
PUBLIC_APP_URL=https://endoc-hrms.oceanhub.co.in
DB_HOST=localhost
DB_PORT=3306
DB_NAME=CPANELUSER_Endoc_hrms
DB_USER=CPANELUSER_dbuser
DB_PASS=your_db_password
DB_PERSISTENT=0
APP_TIMEZONE=Asia/Kolkata
```

`APP_BASE_PATH=` **empty** j rakhvu (subdomain root).

---

## 5) Permissions (Terminal)

```bash
cd ~/endoc-hrms.oceanhub.co.in
chmod 600 .env
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 assets/uploads
```

---

## 6) Check

- https://endoc-hrms.oceanhub.co.in/ → login page
- Admin login karo → Dashboard, Employees, Masters check
- **Live par badha default/test passwords turat change karo.**

---

## 7) Future updates

Local:
```bash
git add .
git commit -m "message"
git push origin main
```

Server:
```bash
cd ~/endoc-hrms.oceanhub.co.in
git pull origin main
```

Live `.env` and `assets/uploads/` git thi overwrite nathi thata.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| CSS missing / links `/endoc_new_hrms_27_10_26/...` par jay | `.env` ma `APP_BASE_PATH=` empty, `APP_URL` live URL, pachi `Ctrl+F5` |
| "Database connection failed" | `.env` DB name/user ma cPanel prefix (`CPANELUSER_`) check karo |
| 500 error | cPanel → Errors log / `error_log` file jovo; PHP version 8.1+ |
| Logo/upload save nathi thatu | `chmod -R 775 assets/uploads` |
| Too many connections | `.env` ma `DB_PERSISTENT=0` |
