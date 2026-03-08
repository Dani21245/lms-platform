# LMS Platform

A production-ready Learning Management System built with Laravel 10, PostgreSQL, and Docker.

## Features

- **SMS OTP Authentication** - Passwordless login via phone number with OTP verification
- **Role-Based Access Control** - Admin, Instructor, and Student roles
- **Course Management** - Full CRUD for courses with categories, levels, and pricing
- **Video Lessons** - Ordered video lessons with progress tracking
- **Quiz System** - Multiple-choice quizzes with automatic grading and attempt tracking
- **Telebirr Payment Integration** - Ethiopian mobile money payment with webhook verification
- **Transaction Logging** - Full audit trail for all payment events
- **Instructor Dashboard** - Course management, student tracking, earnings analytics
- **Student Dashboard** - Enrolled courses, progress tracking, quiz results
- **Admin Panel** - User management, course oversight, payment monitoring, system stats
- **Queue-Based Background Jobs** - SMS sending, payment verification, enrollment processing
- **Docker Deployment** - Multi-container setup with Nginx, PostgreSQL, Redis

## Tech Stack

- **Backend:** Laravel 10 (PHP 8.1+)
- **Database:** PostgreSQL 15
- **Cache/Queue:** Redis 7
- **Auth:** Laravel Sanctum (token-based)
- **Payments:** Telebirr (Ethiopian mobile money)
- **Containerization:** Docker & Docker Compose

## Quick Start

### Using Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/Dani21245/lms-platform.git
cd lms-platform

# Copy environment file
cp .env.example .env

# Start all services
docker-compose up -d

# Run migrations and seed
docker-compose exec app php artisan migrate --seed

# Generate app key
docker-compose exec app php artisan key:generate
```

The application will be available at `http://localhost`.

### Local Development

```bash
# Install dependencies
composer install

# Copy environment file and configure
cp .env.example .env
php artisan key:generate

# Configure PostgreSQL and Redis in .env
# Run migrations
php artisan migrate --seed

# Start the development server
php artisan serve

# Start the queue worker (separate terminal)
php artisan queue:work redis
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/otp/request` | Request OTP code |
| POST | `/api/auth/otp/verify` | Verify OTP and get token |
| POST | `/api/auth/logout` | Logout (authenticated) |
| GET | `/api/auth/me` | Get current user |

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/categories` | List categories |
| GET | `/api/courses` | List published courses |
| GET | `/api/courses/{slug}` | Course details |
| GET | `/api/courses/{id}/lessons` | Course lessons |

### Payments
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/payments/initiate` | Initiate Telebirr payment |
| GET | `/api/payments/history` | Payment history |
| GET | `/api/payments/status/{ref}` | Check payment status |
| POST | `/api/payments/telebirr/webhook` | Telebirr webhook (public) |

### Instructor Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/instructor/dashboard` | Dashboard stats |
| GET | `/api/instructor/earnings` | Earnings overview |
| GET | `/api/instructor/students` | Student list |
| CRUD | `/api/instructor/courses` | Course management |
| CRUD | `/api/instructor/courses/{id}/lessons` | Lesson management |
| CRUD | `/api/instructor/courses/{id}/lessons/{id}/quiz` | Quiz management |

### Student Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/student/dashboard` | Dashboard stats |
| GET | `/api/student/courses` | Enrolled courses |
| GET | `/api/student/courses/{id}/progress` | Course progress |
| POST | `/api/student/courses/{id}/lessons/{id}/complete` | Mark lesson complete |
| POST | `/api/student/courses/{id}/lessons/{id}/quiz/{id}/submit` | Submit quiz |

### Admin Panel
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/dashboard` | System stats |
| CRUD | `/api/admin/users` | User management |
| CRUD | `/api/admin/courses` | Course management |
| CRUD | `/api/admin/categories` | Category management |
| GET | `/api/admin/payments` | Payment management |
| GET | `/api/admin/transaction-logs` | Transaction logs |

## Environment Variables

See `.env.example` for all configuration options including:
- Database (PostgreSQL)
- Redis (cache/queue)
- SMS provider settings
- Telebirr payment gateway credentials

## Architecture

```
app/
  Controllers/Api/
    Auth/           # OTP authentication
    Admin/          # Admin panel endpoints
    Instructor/     # Instructor dashboard endpoints
    Student/        # Student dashboard endpoints
  Enums/            # UserRole, CourseStatus, PaymentStatus, LessonType
  Jobs/             # SendOtpSms, ProcessPaymentVerification, ProcessEnrollment
  Models/           # Eloquent models with relationships
  Services/         # OtpService, SmsService, TelebirrService
  Http/
    Middleware/      # RoleMiddleware
    Resources/       # API Resources for JSON serialization
```

## License

MIT
