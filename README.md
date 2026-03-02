# 🏨 PMS Cloud — Property Management System

> Production-ready multi-tenant SaaS PMS built with Laravel 11, Vue 3, PostgreSQL & Docker.

---

## 🗂️ Project Structure

```
pms/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   │   ├── Admin/          ← Platform admin endpoints
│   │   │   │   ├── Auth/           ← Authentication
│   │   │   │   └── Properties/     ← Property operations
│   │   │   └── Auth/               ← Web auth controllers
│   │   ├── Middleware/             ← IdentifyTenant, EnsurePropertyAccess, etc.
│   │   ├── Requests/               ← Form request validation
│   │   └── Resources/              ← API response transformers
│   ├── Models/                     ← Eloquent models (all tenant-scoped)
│   ├── Policies/                   ← Authorization policies (RBAC)
│   ├── Services/                   ← Business logic services
│   └── Support/Traits/             ← BelongsToTenant, HasUlid
├── database/
│   ├── migrations/                 ← All 30+ table schemas
│   └── seeders/                    ← Plans, admin, demo property
├── docker/                         ← Nginx, PHP, Redis, Supervisor configs
├── resources/js/
│   ├── Components/                 ← Reusable Vue components
│   ├── Composables/                ← useApi, useReservationStatus, etc.
│   ├── Layouts/                    ← AppLayout
│   ├── Pages/                      ← Inertia page components
│   └── Stores/                     ← Pinia stores (auth, ui)
└── routes/
    ├── api.php                     ← Full versioned REST API
    └── web.php                     ← Inertia web routes
```

---

## 🚀 Quick Start (Docker)

### 1. Clone & Configure

```bash
git clone https://github.com/yourorg/pms-cloud.git
cd pms-cloud
cp .env.example .env
```

Edit `.env` with your values (see Configuration section below).

### 2. Build & Start

```bash
# Build images
docker compose build

# Start all services
docker compose up -d

# Check health
docker compose ps
```

### 3. Initialize Application

```bash
# Generate app key
docker compose exec php php artisan key:generate

# Run migrations
docker compose exec php php artisan migrate --seed

# Build frontend assets
docker compose exec php npm ci && npm run build

# Create storage link
docker compose exec php php artisan storage:link

# Cache config for production
docker compose exec php php artisan config:cache
docker compose exec php php artisan route:cache
docker compose exec php php artisan view:cache
```

### 4. Access

| Service        | URL                          |
|---------------|------------------------------|
| Application   | https://yourdomain.com        |
| WebSockets    | wss://yourdomain.com/app/     |
| Horizon       | https://yourdomain.com/horizon|

---

## 🔑 Default Credentials

After seeding, use these accounts:

| Role           | Email                              | Password        |
|----------------|------------------------------------|-----------------|
| Platform Admin | admin@pms.local                    | ChangeMeNow!123 |
| Owner          | owner@grandazure-demo.com          | DemoOwner123!   |
| Manager        | manager@grandazure-demo.com        | Demo123!        |
| Receptionist   | receptionist@grandazure-demo.com   | Demo123!        |
| Housekeeping   | housekeeping@grandazure-demo.com   | Demo123!        |
| Accountant     | accountant@grandazure-demo.com     | Demo123!        |

> ⚠️ **Change all default passwords immediately in production.**

---

## 🌐 API Reference

All API routes are prefixed `/api/v1/`.

### Authentication
```
POST   /api/v1/auth/login          # Login → returns Bearer token
POST   /api/v1/auth/logout         # Logout current session
GET    /api/v1/auth/me             # Current user info
PATCH  /api/v1/auth/profile        # Update profile
PATCH  /api/v1/auth/password       # Change password
GET    /api/v1/auth/sessions       # List active sessions
```

### Headers Required (Property-Scoped Endpoints)
```
Authorization: Bearer {token}
X-Property-ID: {property_ulid}
Content-Type: application/json
Accept: application/json
```

### Dashboard
```
GET    /api/v1/dashboard            # Full dashboard metrics
GET    /api/v1/dashboard/today      # Today's summary
GET    /api/v1/dashboard/arrivals   # Today's arrivals (paginated)
GET    /api/v1/dashboard/departures # Today's departures (paginated)
```

### Reservations (Phase 2)
```
GET    /api/v1/reservations         # List with filters
GET    /api/v1/reservations/calendar # Calendar view
POST   /api/v1/reservations         # Create reservation
GET    /api/v1/reservations/{id}    # Get reservation details
PATCH  /api/v1/reservations/{id}    # Update reservation
POST   /api/v1/reservations/{id}/confirm    # Confirm
POST   /api/v1/reservations/{id}/check-in   # Check in guest
POST   /api/v1/reservations/{id}/check-out  # Check out guest
POST   /api/v1/reservations/{id}/cancel     # Cancel
POST   /api/v1/reservations/{id}/no-show    # Mark no-show
```

---

## ⚙️ Configuration

### Required .env Variables

```bash
# App
APP_KEY=                    # Generate with: php artisan key:generate
APP_URL=https://yourdomain.com

# Database
DB_PASSWORD=                # Strong password
DB_DATABASE=pms_production

# Redis
REDIS_PASSWORD=             # Strong password

# Reverb (WebSockets)
REVERB_APP_KEY=             # Random string
REVERB_APP_SECRET=          # Random string

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_USERNAME=
MAIL_PASSWORD=

# Document Encryption
DOCUMENT_ENCRYPTION_KEY=    # 32 char random string
```

---

## 🏗️ Architecture

### Multi-Tenancy
- Each property is a tenant identified by `property_id`
- `BelongsToTenant` trait automatically scopes all queries
- `IdentifyTenant` middleware resolves tenant from `X-Property-ID` header or session
- Platform admins bypass all tenant scopes

### RBAC
| Role           | Reservations | Billing | Housekeeping | Settings |
|---------------|:---:|:---:|:---:|:---:|
| Platform Admin | ✅ | ✅ | ✅ | ✅ |
| Owner          | ✅ | ✅ | ✅ | ✅ |
| Manager        | ✅ | ✅ | ✅ | ✅ |
| Accountant     | Read | ✅ | ❌ | ❌ |
| Receptionist   | ✅ | View | ❌ | ❌ |
| Housekeeping   | ❌ | ❌ | ✅ | ❌ |

### Queue Architecture
| Queue        | Workers | Use For                            |
|-------------|--------|------------------------------------|
| default     | 2       | Emails, auth events                |
| invoices    | 1       | PDF generation                     |
| reports     | 1       | Heavy reporting jobs               |
| webhooks    | 2       | OTA channel sync, Stripe events    |
| housekeeping| 1       | Real-time room status broadcasts   |

---

## 🚢 Production Deployment (VPS)

### Prerequisites
- Ubuntu 22.04+ VPS (min 4GB RAM, 2 vCPU)
- Docker & Docker Compose installed
- Domain with SSL (Let's Encrypt recommended)

### SSL Setup
```bash
# Install Certbot
apt install certbot

# Generate certificate
certbot certonly --standalone -d yourdomain.com

# Copy to nginx ssl directory
cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem docker/nginx/ssl/
cp /etc/letsencrypt/live/yourdomain.com/privkey.pem docker/nginx/ssl/

# Auto-renewal (add to cron)
0 12 * * * certbot renew --quiet
```

### Production Optimizations
```bash
# Enable OPcache timestamp validation off (done in php.ini)
# Redis persistence enabled (done in redis.conf)
# Nginx gzip enabled (done in nginx.conf)

# Laravel optimizations
php artisan optimize
php artisan event:cache
```

---

## 🔧 CI/CD (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/pms
            git pull origin main
            docker compose exec -T php composer install --no-dev --optimize-autoloader
            docker compose exec -T php php artisan migrate --force
            docker compose exec -T php php artisan optimize
            docker compose exec -T php npm ci && npm run build
            docker compose restart php queue
```

---

## ✅ Production Hardening Checklist

- [ ] Change all default passwords
- [ ] Set `APP_DEBUG=false`
- [ ] Generate strong `APP_KEY` and `DOCUMENT_ENCRYPTION_KEY`
- [ ] Enable SSL/TLS with HSTS
- [ ] Configure rate limiting in Nginx
- [ ] Set up database backups (daily pg_dump to S3)
- [ ] Configure log aggregation (Papertrail / CloudWatch)
- [ ] Enable Redis authentication (`requirepass`)
- [ ] Set up monitoring (Uptime Robot, Sentry)
- [ ] Review CORS configuration
- [ ] Disable Telescope in production (`TELESCOPE_ENABLED=false`)
- [ ] Set `SESSION_SECURE_COOKIE=true`
- [ ] Configure firewall (UFW: allow 80, 443 only)
- [ ] Set up Laravel Horizon for queue monitoring
- [ ] Enable PHP OPcache (`opcache.validate_timestamps=0`)
- [ ] Configure `max_upload_size` in php.ini and Nginx
- [ ] Run `php artisan config:cache && route:cache && view:cache`
- [ ] Test WebSocket connections (Reverb)
- [ ] Verify email delivery (send test email)
- [ ] Load test with at least 100 concurrent users

---

## 📊 Database Schema Overview

### Core Tables
| Table | Purpose |
|---|---|
| `properties` | Tenants (hotels) |
| `users` | Platform & property users |
| `property_users` | User-property pivot with role |
| `subscription_plans` | SaaS plans (Trial/Starter/Pro/Enterprise) |

### Property Operations
| Table | Purpose |
|---|---|
| `room_types` | Room categories (Standard, Deluxe, Suite) |
| `rooms` | Physical rooms with HK status |
| `rate_plans` | Pricing plans (BAR, BB, Corporate) |
| `room_rates` | Daily rate calendar |
| `guests` | Guest profiles (encrypted sensitive fields) |
| `reservations` | Core reservation records |
| `reservation_guests` | Additional guests per reservation |
| `reservation_status_history` | Audit trail of status changes |

### Finance
| Table | Purpose |
|---|---|
| `folios` | Billing accounts per reservation |
| `folio_items` | Individual charges/payments |
| `invoices` | Issued tax invoices |
| `tax_configs` | VAT/tax rules per property |

### Operations
| Table | Purpose |
|---|---|
| `housekeeping_tasks` | Room cleaning assignments |
| `registration_cards` | Digital check-in cards |
| `night_audits` | Daily audit summaries |
| `booking_sources` | OTA/direct booking channels |
| `channel_connections` | OTA integration configs |
| `webhook_events` | Incoming webhook processing queue |

### Platform
| Table | Purpose |
|---|---|
| `feature_flags` | Plan-based feature toggles |
| `audit_logs` | Security & operation audit trail |
| `activity_log` | Spatie activity log |
| `usage_metrics` | Per-property usage tracking |

---

*Phase 1 complete. Phase 2 adds: Reservation system, Front Desk, Housekeeping, Billing.*
*Phase 3 adds: Reporting, Channel Manager, CI/CD, production hardening.*
