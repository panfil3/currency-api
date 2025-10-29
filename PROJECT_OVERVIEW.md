# Currency Conversion API

## 📋 Overview

High-performance currency conversion API built with **Clean Architecture**, **DDD principles**, and **CQRS pattern**. The system provides real-time currency conversion with multi-level caching strategy and background synchronization.

### Key Features

- ✅ **Multi-level caching** (L1: Redis, L2: Database, L3: External APIs)
- ✅ **Background job synchronization** with Laravel Horizon
- ✅ **Rate limiting** (500 req/min per user, 1000 req/min per IP)
- ✅ **Circuit Breaker pattern** for external provider fault tolerance
- ✅ **Clean Architecture** with strict layer separation
- ✅ **CQRS pattern** for query handling
- ✅ **Health check endpoint** for operational monitoring
- ✅ **99% requests < 50ms** (with cache hit)

---

## 🏗 Architecture

### Layer Structure

```
app/
├── Domain/              # Business logic (entities, value objects)
│   ├── Currency/
│   │   ├── Entities/
│   │   │   └── ConversionResult.php
│   │   └── ValueObjects/
│   │       ├── CurrencyCode.php
│   │       ├── Money.php
│   │       └── ExchangeRate.php
│   └── Contracts/
│
├── Application/         # Use cases and services
│   ├── Services/
│   │   ├── CurrencyConversionService.php
│   │   └── RateAggregationService.php
│   ├── Queries/
│   │   └── ConvertCurrencyQuery.php
│   └── Handlers/
│       └── ConvertCurrencyQueryHandler.php
│
├── Infrastructure/      # Technical implementations
│   ├── Cache/
│   │   └── IgniteCacheAdapter.php
│   ├── Repositories/
│   │   └── CurrencyRateRepository.php
│   ├── ExternalProviders/
│   │   ├── ExchangeRateApiProvider.php
│   │   ├── CurrencyLayerProvider.php
│   │   └── FixerIoProvider.php
│   ├── Jobs/
│   │   └── SyncExchangeRatesJob.php
│   ├── CircuitBreaker/
│   │   └── CircuitBreaker.php
│   └── RateLimiting/
│       └── RedisRateLimiter.php
│
└── Presentation/        # HTTP layer
    └── Http/
        ├── Controllers/
        │   ├── CurrencyConversionController.php
        │   └── HealthCheckController.php
        └── Middleware/
            └── RateLimitMiddleware.php
```

### Data Flow

```
Request → Rate Limiter → Controller → Query Handler
                                          ↓
                                    Conversion Service
                                          ↓
                          ┌───────────────┴───────────────┐
                          ↓                               ↓
                    L1: Redis Cache                Circuit Breaker
                    (TTL: 1 sec)                         ↓
                          ↓                    L3: External Providers
                    L2: Database                  (3 providers)
                    (Max age: 1 hour)                    ↓
                          ↓                         Aggregation
                    Response ←────────────────── (Median calculation)
```

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.2+
- MySQL 8.0+
- Redis 7.0+
- Composer
- Nginx/Apache
- Node.js (for Horizon assets)

### Installation

```bash
# 1. Clone repository
git clone <repository-url>
cd currency-api

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
nano .env
```

**Required .env variables:**

```env
# Application
APP_NAME="Currency API"
APP_ENV=local
APP_KEY=                    # Run: php artisan key:generate
APP_DEBUG=true
APP_URL=http://currency-api.loc

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=currency_api
DB_USERNAME=currency_user
DB_PASSWORD=your_password

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Horizon
HORIZON_PREFIX=currency_api_horizon

# External API Keys
EXCHANGERATE_API_KEY=your_key_here
CURRENCYLAYER_API_KEY=your_key_here
FIXER_API_KEY=your_key_here
```

```bash
# 4. Generate application key
php artisan key:generate

# 5. Run migrations
php artisan migrate

# 6. Seed initial data (optional)
php artisan db:seed

# 7. Start services
php artisan horizon          # In separate terminal for queue processing
php artisan serve            # Development server
```

### Production Setup

```bash
# 1. Install Supervisor (for Horizon auto-restart)
# Ubuntu/Debian:
sudo apt-get install supervisor

# macOS:
brew install supervisor

# 2. Configure Supervisor
sudo nano /etc/supervisor/conf.d/horizon.conf
```

**Supervisor configuration:**

```ini
[program:horizon]
process_name=%(program_name)s
command=php /path/to/project/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
# 3. Start Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

# 4. Setup Cron for background sync
crontab -e
```

Add this line:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📡 API Documentation

### 1. Currency Conversion

**Endpoint:** `GET /api/v1/convert`

**Parameters:**

| Parameter | Type   | Required | Description                    |
|-----------|--------|----------|--------------------------------|
| from      | string | Yes      | Source currency (ISO 4217)     |
| to        | string | Yes      | Target currency (ISO 4217)     |
| amount    | number | Yes      | Amount to convert (max: 1B)    |

**Example Request:**

```bash
curl "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100"
```

**Response:**

```json
{
  "data": {
    "from": "USD",
    "to": "EUR",
    "amount": "100.00",
    "result": "93.15",
    "rate": "0.9315",
    "last_updated": "2025-10-29T12:34:56Z"
  },
  "meta": {
    "source": "cache",
    "execution_time_ms": 3.45
  }
}
```

**Response Sources:**

- `cache` - L1 Redis cache (fastest, < 10ms)
- `local_db` - L2 Database (fast, 10-30ms)
- `external_fallback_1` - ExchangeRateApi provider
- `external_fallback_2` - CurrencyLayer provider
- `external_fallback_3` - Fixer.io provider

**Error Response:**

```json
{
  "message": "The from field is invalid.",
  "errors": {
    "from": ["The selected from is invalid."]
  }
}
```

**Status Codes:**

- `200` - Success
- `400` - Validation error
- `429` - Rate limit exceeded
- `500` - Internal server error

---

### 2. Health Check

**Endpoint:** `GET /health`

**Example Request:**

```bash
curl "http://currency-api.loc/health"
```

**Response:**

```json
{
  "status": "healthy",
  "timestamp": "2025-10-29T20:00:00Z",
  "checks": {
    "database": {
      "status": true,
      "message": "Database is accessible"
    },
    "cache": {
      "status": true,
      "message": "Cache is working"
    },
    "external_providers": {
      "ExchangeRateApiProvider": {
        "status": true,
        "message": "Available"
      },
      "CurrencyLayerProvider": {
        "status": true,
        "message": "Available"
      },
      "FixerIoProvider": {
        "status": true,
        "message": "Available"
      }
    }
  }
}
```

**Status Values:**

- `healthy` - All systems operational
- `degraded` - Some non-critical issues
- `unhealthy` - Critical system failure

---

## 🧪 Testing Guide

### Manual Testing

#### 1. Test L1 Cache (Redis)

```bash
# Clear cache
redis-cli FLUSHALL

# First request (miss - goes to DB or external)
time curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100" | jq

# Second request (hit - from cache, should be < 10ms)
time curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100" | jq '.meta'
```

**Expected:**
- First request: `"source": "local_db"`, ~10-50ms
- Second request: `"source": "cache"`, < 10ms

#### 2. Test L2 Database

```bash
# Check database contents
mysql -u currency_user -p currency_api -e "SELECT * FROM currency_rates LIMIT 5;"

# Check data freshness (should be < 1 hour old)
mysql -u currency_user -p currency_api -e "
SELECT from_code, to_code, rate, 
       TIMESTAMPDIFF(MINUTE, last_updated, NOW()) as age_minutes
FROM currency_rates 
WHERE TIMESTAMPDIFF(MINUTE, last_updated, NOW()) > 60;
"
```

#### 3. Test L3 External Providers

```bash
# Clear both cache and database
redis-cli FLUSHALL
mysql -u currency_user -p currency_api -e "TRUNCATE TABLE currency_rates;"

# Request should go to external providers
curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=JPY&amount=100" | jq '.meta.source'
```

**Expected:** `"external_fallback_1"` or `"external_fallback_2"`

#### 4. Test Circuit Breaker

```bash
# Check logs during request
tail -f storage/logs/laravel.log | grep -i "circuit\|provider"

# In another terminal, make multiple requests
for i in {1..5}; do
  curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100" > /dev/null
done
```

**Expected logs:**
```
[2025-10-29 20:00:01] local.INFO: Trying external provider: ExchangeRateApiProvider
[2025-10-29 20:00:01] local.INFO: External provider SUCCESS: ExchangeRateApiProvider
[2025-10-29 20:00:02] local.WARNING: Circuit breaker OPEN for FailedProvider, skipping
```

#### 5. Test Rate Limiting

```bash
# Test IP rate limit (1000 req/min)
for i in {1..510}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=$i"
done | grep "429"
```

**Expected:** After 500 requests, should return `429 Too Many Requests`

#### 6. Test Background Synchronization

```bash
# Start Horizon (if not running)
php artisan horizon

# In another terminal, trigger sync
php artisan currency:sync

# Check Horizon dashboard
open http://currency-api.loc/horizon

# Check logs for aggregation
tail -50 storage/logs/laravel.log | grep -i "aggregat\|median\|provider"
```

**Expected logs:**
```
[2025-10-29 20:00:01] local.INFO: Aggregating rates for USD -> EUR
[2025-10-29 20:00:01] local.INFO: Provider ExchangeRateApiProvider returned: 0.9315
[2025-10-29 20:00:02] local.INFO: Provider CurrencyLayerProvider returned: 0.9320
[2025-10-29 20:00:02] local.INFO: Rates collected: [0.9315, 0.9320]
[2025-10-29 20:00:02] local.INFO: Calculated median: 0.93175
```

#### 7. Test Validation

```bash
# Invalid currency code
curl -s "http://currency-api.loc/api/v1/convert?from=XXX&to=EUR&amount=100" | jq

# Negative amount
curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=-100" | jq

# Amount too large
curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=99999999999" | jq
```

**Expected:** Each returns validation error with appropriate message

#### 8. Performance Test

```bash
# Install Apache Bench
# Ubuntu: sudo apt-get install apache2-utils
# macOS: brew install httpd

# Warm up cache
curl -s "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100" > /dev/null

# Run load test (1000 requests, 10 concurrent)
ab -n 1000 -c 10 "http://currency-api.loc/api/v1/convert?from=USD&to=EUR&amount=100"
```

**Expected results:**
```
Percentage of the requests served within a certain time (ms)
  50%      5
  66%      7
  75%      9
  80%     10
  90%     15
  95%     20
  98%     30
  99%     45    ← Should be < 50ms
 100%     80
```

---

### Automated Testing

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

---

## 🔧 Configuration

### Supported Currencies

The system supports major world currencies (ISO 4217):

- USD (US Dollar)
- EUR (Euro)
- GBP (British Pound)
- JPY (Japanese Yen)
- CHF (Swiss Franc)
- CAD (Canadian Dollar)
- AUD (Australian Dollar)
- NZD (New Zealand Dollar)
- CNY (Chinese Yuan)
- And more...

See `App\Domain\Currency\ValueObjects\CurrencyCode` enum for full list.

### Cache Configuration

**L1 Cache (Redis):**
- TTL: 1 second
- Used for high-frequency requests
- Key format: `currency:rate:{from}:{to}`

**L2 Cache (Database):**
- Max age: 1 hour (configurable in `CurrencyConversionService::MAX_RATE_AGE_MINUTES`)
- Automatically refreshed by background job
- Table: `currency_rates`

### Rate Limiting

Configure in `app/Infrastructure/RateLimiting/RedisRateLimiter.php`:

```php
private const RATE_LIMIT_PER_MINUTE = 500;  // Per user
private const IP_RATE_LIMIT_PER_MINUTE = 1000;  // Per IP
```

### Circuit Breaker

Configure in `app/Infrastructure/CircuitBreaker/CircuitBreaker.php`:

```php
private const FAILURE_THRESHOLD = 5;        // Failures before opening
private const TIMEOUT_SECONDS = 60;         // Open duration
private const SUCCESS_THRESHOLD = 2;        // Successes to close
```

**States:**
- **Closed** - Normal operation
- **Open** - Provider disabled (after 5 failures)
- **Half-Open** - Testing recovery (after 60 seconds)

---

## 📊 Monitoring

### Horizon Dashboard

Access at: `http://currency-api.loc/horizon`

**Features:**
- Real-time job monitoring
- Failed job inspection
- Performance metrics
- Queue workload distribution
- Job retry interface

### Logs

**Application logs:**
```bash
tail -f storage/logs/laravel.log
```

**Horizon logs:**
```bash
tail -f storage/logs/horizon.log
```

**Key log patterns:**
- `local.INFO` - Normal operations
- `local.WARNING` - Non-critical issues (provider failures, circuit breaker)
- `local.ERROR` - Critical errors

### Metrics (Optional)

For Prometheus integration, see `docs/prometheus-setup.md` (if implemented).

---

## 🐛 Troubleshooting

### Common Issues

#### 1. "Circuit breaker OPEN" in logs

**Cause:** External provider failed 5+ times

**Solution:**
```bash
# Check provider status
curl -s http://currency-api.loc/health | jq '.checks.external_providers'

# Reset circuit breaker (in tinker)
php artisan tinker
>>> use App\Infrastructure\CircuitBreaker\CircuitBreaker;
>>> $cb = new CircuitBreaker('ExchangeRateApiProvider');
>>> Redis::del('circuit_breaker:ExchangeRateApiProvider:state');
>>> exit
```

#### 2. "Rate limit exceeded" (429 error)

**Cause:** Too many requests from same IP/user

**Solution:**
```bash
# Clear rate limiter (in Redis)
redis-cli KEYS "rate_limit:*" | xargs redis-cli DEL
```

#### 3. Horizon not processing jobs

**Cause:** Horizon not running or crashed

**Solution:**
```bash
# Check if running
ps aux | grep horizon

# Restart Horizon
supervisorctl restart horizon

# Or manually
php artisan horizon:terminate
php artisan horizon
```

#### 4. Slow response times (> 50ms)

**Causes:**
- Cache miss
- Database not populated
- External API timeout

**Solution:**
```bash
# Warm up cache
php artisan currency:sync

# Check cache hit rate
redis-cli INFO stats | grep keyspace_hits
```

#### 5. External provider "No response"

**Cause:** Invalid API key or provider down

**Solution:**
```bash
# Test provider directly
curl "https://api.exchangerate-api.com/v4/latest/USD"

# Check API key in .env
grep EXCHANGERATE_API_KEY .env

# Test in code
php artisan tinker
>>> config('services.exchangerate_api.key')
```

---

## 📈 Performance Optimization

### Tips for Production

1. **Enable OPcache:**
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
```

2. **Redis optimization:**
```bash
# redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

3. **Database indexing:**
```sql
CREATE INDEX idx_currency_pair ON currency_rates(from_code, to_code);
CREATE INDEX idx_last_updated ON currency_rates(last_updated);
```

4. **Horizon workers:**
```php
// config/horizon.php
'production' => [
    'supervisor-1' => [
        'maxProcesses' => 10,  // Increase for high load
        'tries' => 3,
    ],
],
```

---

## 🔐 Security Considerations

1. **API Keys:** Store in `.env`, never commit to repository
2. **Rate Limiting:** Prevents abuse and DoS attacks
3. **Input Validation:** All inputs validated before processing
4. **SQL Injection:** Uses Eloquent ORM with parameter binding
5. **XSS Protection:** JSON responses only, no HTML rendering

---

## 📝 Development Notes

### Adding New Currency Provider

1. Create provider class implementing `ExternalProviderInterface`
2. Register in `CurrencyServiceProvider`
3. Add configuration to `config/services.php`
4. Add API key to `.env.example`

**Example:**

```php
<?php

namespace App\Infrastructure\ExternalProviders;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;

final class NewProvider implements ExternalProviderInterface
{
    public function __construct(private string $apiKey) {}

    public function getRate(
        CurrencyCode $from,
        CurrencyCode $to,
        int $timeout = 1000
    ): ?ExchangeRate {
        // Implementation
    }
}
```

### Running in Debug Mode

```bash
# Enable query logging
DB::enableQueryLog();

# After requests
dd(DB::getQueryLog());

# Enable detailed error messages
APP_DEBUG=true  # in .env
```

---

## 📚 Additional Resources

- **Laravel Documentation:** https://laravel.com/docs
- **Horizon Documentation:** https://laravel.com/docs/horizon
- **Redis Documentation:** https://redis.io/documentation
- **Clean Architecture:** https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html

---

## 👥 Team & Support

**Developed by:** [Your Name]  
**Date:** October 2025  
**Version:** 1.0.0

For questions or issues, please contact: [your-email@example.com]

---

## 📄 License

This project is proprietary software developed for [Company Name].

---

## ✅ Requirements Checklist

### Core Requirements

- [x] High-Performance API (< 50ms for 99% requests)
- [x] GET /api/v1/convert endpoint
- [x] Multi-level caching (L1: Redis, L2: DB, L3: External)
- [x] Background synchronization with Laravel Queues + Horizon
- [x] Parallel provider polling with aggregation (median)
- [x] Retry logic with exponential backoff

### Architecture

- [x] Clean Architecture (4 layers)
- [x] DDD (Value Objects, Entities)
- [x] CQRS pattern
- [x] Rate Limiting (500/min user, 1000/min IP)
- [x] Custom validation rules
- [x] Circuit Breaker pattern

### Operations

- [x] Health check endpoint
- [x] Database connectivity check
- [x] Cache availability check
- [x] External provider monitoring
- [x] Structured logging
- [ ] Prometheus metrics (optional)

---

**End of Documentation**
