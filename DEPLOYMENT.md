# 🚀 ការណែនាំអំពីការ Deploy គម្រោង Inventory Management System
# (Comprehensive Deployment Guide)

ឯកសារនេះផ្ដល់នូវការណែនាំលម្អិតជា **ភាសាខ្មែរ** និង **English** សម្រាប់ការ Deploy គម្រោង Inventory System ទៅកាន់ Cloud, Web Hosting, Serverless, VPS, និង Docker យ៉ាងងាយស្រួលបំផុត។

---

## 📋 តារាងជម្រើសនៃការ Deploy (Deployment Options Summary)

| Platform | Difficulty | Database | Best For | Cost |
|---|---|---|---|---|
| **Render / Railway** | ⭐️ ងាយស្រួលបំផុត | MySQL Cloud / Container | Production Cloud App | Free Tier / Low Cost |
| **Vercel** | ⭐️⭐️ ងាយស្រួល | TiDB / Aiven / Managed MySQL | Serverless Hosting | Free Tier |
| **Docker Compose** | ⭐️ ងាយស្រួលបំផុត | Docker MySQL | Local Dev / Portainer / VPS | Free |
| **Shared Hosting / cPanel** | ⭐️⭐️ មធ្យម | MySQL / phpMyAdmin | Hostinger, Local Hosts | Paid Hosting |
| **Linux VPS (Ubuntu)** | ⭐️⭐️⭐️ ខ្ពស់ | MySQL Server | Self-Hosted Enterprise | VPS ($4-$10/mo) |

---

## 1️⃣ ជម្រើសទី 1 — Render ឬ Railway (1-Click Cloud Docker Deploy) - ណែនាំខ្ពស់បំផុត ⭐

 Render និង Railway អាចអាន `Dockerfile` នៅ Root directory ដោយស្វ័យប្រវត្តិ។

### 🔹 របៀប Deploy លើ Render:
1. Push Code របស់អ្នកទៅកាន់ GitHub repository។
2. ចូលទៅកាន់ [Render Dashboard](https://dashboard.render.com/) ➡️ ចុច **New +** ➡️ ជ្រើសរើស **Web Service**។
3. ភ្ជាប់ GitHub Repository របស់អ្នក។
4. Render នឹងស្គាល់ `Dockerfile` ដោយស្វ័យប្រវត្តិ!
5. នៅក្នុង **Environment Variables** បន្ថែម៖
   - `DB_HOST`: Host នៃ MySQL របស់អ្នក (ឧទាហរណ៍ TiDB, Aiven, ឬ Render MySQL)
   - `DB_PORT`: `3306`
   - `DB_NAME`: `inventory_db`
   - `DB_USER`: `your_db_user`
   - `DB_PASS`: `your_db_password`
   - *(ឬប្រើ `DATABASE_URL` = `mysql://user:pass@host:3306/dbname`)*
6. ចុច **Deploy Web Service**។
7. បន្ទាប់ពី Deploy រួច បើកទៅកាន់ `https://your-app.onrender.com/setup.php` ដើមី្បបង្កើត Tables និង ដំឡើង البيانات DB ដោយស្វ័យប្រវត្តិ!

---

## 2️⃣ ជម្រើសទី 2 — Deploy លើ Vercel + TiDB Cloud (Serverless Free Tier)

Vercel សម្រាប់ Web App + TiDB Cloud / Aiven សម្រាប់ Free Managed MySQL Database។

### 🔹 របៀប Deploy:
1. បង្កើត MySQL Database ឥតគិតថ្លៃលើ [TiDB Cloud Serverless](https://tidbcloud.com/) ឬ [Aiven.io](https://aiven.io/)។
2. ទាញយក MySQL Connection Details (Host, Port, User, Password, Database)។
3. Push Code ទៅ GitHub ➡️ ចូលទៅកាន់ [Vercel Dashboard](https://vercel.com/) ➡️ **Add New Project** ➡️ Import GitHub Repo។
4. នៅក្នុងផ្នែក **Environment Variables** លើ Vercel បញ្ចូល៖
   ```env
   DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
   DB_PORT=4000
   DB_NAME=inventory_db
   DB_USER=xxxxxx.root
   DB_PASS=your_password
   APP_ENV=production
   APP_DEBUG=false
   ```
5. ចុច **Deploy**។
6. បន្ទាប់ពី Deploy រួច បើក Link App របស់អ្នកបន្ថែម `/setup.php` (ឧទាហរណ៍ `https://your-project.vercel.app/setup.php`) ដើមី្បបង្កើត Tables ដោយស្វ័យប្រវត្តិ!

---

## 3️⃣ ជម្រើសទី 3 — Docker & Docker Compose (Local & Self-Hosted Container)

រត់ Web Server (Apache + PHP 8.2) និង MySQL 8.0 ក្នុង Container តែមួយកញ្ចប់។

### 🔹 Command សម្រាប់រត់៖
```bash
# 1. រត់ Docker Compose ក្នុង background
docker compose up -d

# 2. បើក Browser ចូលទៅកាន់
http://localhost:8080
```

*(Database Schema & Seed នឹងត្រូវ Import ចូល MySQL ដោយស្វ័យប្រវត្តក្នុងពេល Container ចាប់ផ្តើម!)*

---

## 4️⃣ ជម្រើសទី 4 — Shared Hosting / cPanel (Hostinger, Khmer Web Hosts)

### 🔹 របៀបដំឡើង៖
1. **បង្កើត Database លើ cPanel:**
   - ចូល cPanel ➡️ **MySQL Databases** ➡️ បង្កើត Database (ឧ. `username_inventory`) និង User។
   - ផ្តល់ **ALL PRIVILEGES** ដល់ User លើ Database នោះ។
2. **Upload Code:**
   - ZIP គម្រោងនេះ (មិនបាច់រាប់បញ្ចូល `.git`) ➡️ Upload ទៅក្នុង `public_html/` ឬ Subdomain លើ cPanel ➡️ Extract ZIP។
3. **កែសម្រួល `.env`:**
   - បង្កើត File `.env` នៅក្នុង root folder ដោយចម្លងពី `.env.example`៖
     ```env
     APP_ENV=production
     APP_DEBUG=false
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=username_inventory
     DB_USER=username_dbuser
     DB_PASS=YourStrongPassword
     ```
4. **រត់ Setup Auto-Migrator:**
   - បើក Web Browser ទៅកាន់៖ `http://your-domain.com/setup.php`
   - ប្រព័ន្ធនឹងបង្កើត Tables និង បញ្ចូល User Admin ដោយស្វ័យប្រវត្តិ!
5. **លុប ឬ ការពារ `setup.php`:**
   - បន្ទាប់ពី Setup រួចរាល់ សូមលុប File `setup.php` ចោលដើមី្ប សុវត្ថិភាព។

---

## 5️⃣ ជម្រើសទី 5 — Linux VPS Deployment (Ubuntu / Debian + Apache / Nginx)

### 🔹 របៀបដំឡើងតាម Script ស្វ័យប្រវត្តិ (`deploy.sh`)៖

1. **Clone Code ទៅកាន់ VPS:**
   ```bash
   cd /var/www
   git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git inventory-app
   cd inventory-app
   ```
2. **កំណត់ `.env`:**
   ```bash
   cp .env.example .env
   nano .env
   ```
3. **រត់ Deployment Script:**
   ```bash
   chmod +x deploy.sh
   ./deploy.sh
   ```
4. **កំណត់ VirtualHost លើ Apache (`/etc/apache2/sites-available/inventory.conf`):**
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       DocumentRoot /var/www/inventory-app

       <Directory /var/www/inventory-app>
           AllowOverride All
           Require all granted
       </Directory>

       ErrorLog ${APACHE_LOG_DIR}/inventory_error.log
       CustomLog ${APACHE_LOG_DIR}/inventory_access.log combined
   </VirtualHost>
   ```
5. **Enable Site និង Restart Apache:**
   ```bash
   sudo a2enmod rewrite
   sudo a2ensite inventory.conf
   sudo systemctl restart apache2
   ```

---

## 🔑 Default Login Credentials

| Role | Email | Password | Notes |
|---|---|---|---|
| **Admin** | `admin@inventory.com` | `admin123` | សិទ្ធិគ្រប់គ្រងពេញលេញ (Admin Access) |
| **Staff (User)** | `staff@inventory.com` | `user123` | សិទ្ធិប្រតិបត្តិការទូទៅ |

---

## 🛡️ Security Checklists For Production

- [ ] កំណត់ `APP_DEBUG=false` ក្នុង `.env`
- [ ] ផ្លាស់ប្តូរ ពាក្យសម្ងាត់ Admin ដើម (`admin123`) ភ្លាមៗបន្ទាប់ពីដំឡើងរួច
- [ ] ផ្ទៀងផ្ទាត់ថា File `.env` មិនអាចទាញយកតាម Web Browser បានទេ (ត្រូវបានការពារដោយ `.htaccess`)
- [ ] លុប `setup.php` ចោលបន្ទាប់ពី Setup រួចរាល់លើ Production Server
