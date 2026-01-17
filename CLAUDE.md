# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Cloud is a server management and deployment platform built with Laravel 12, Inertia.js v2, and Vue 3. It manages server provisioning, sites, domains, SSL certificates, databases, and deployments via SSH.

## Development Commands

```bash
# Full development environment (server, horizon, logs, vite)
composer run dev

# Run tests
php artisan test --compact                           # All tests
php artisan test --compact tests/Feature/File.php   # Single file
php artisan test --compact --filter=testName        # Filter by name

# Code formatting
vendor/bin/pint --dirty    # Format changed PHP files
npm run lint               # Fix ESLint issues
npm run format             # Format frontend with Prettier

# Build frontend
npm run build              # Production build
npm run dev                # Development server

# Generate TypeScript route types after adding/changing routes
php artisan wayfinder:generate
```

## Architecture

### Nip Modular Architecture

Business logic lives in `nip/` modules, not `app/`. Each module is self-contained:

```
nip/ModuleName/
├── Actions/           # Business logic classes with handle() method
├── Console/Commands/  # Artisan commands
├── Data/              # Spatie Laravel Data DTOs (transformation, NOT validation)
├── Database/
│   ├── Factories/
│   └── Migrations/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   ├── Requests/      # FormRequest validation classes
│   └── Resources/     # API Resources for frontend data
├── Jobs/
├── Models/
├── Policies/
├── Providers/         # ServiceProvider (registers routes, etc.)
├── Routes/            # web.php, api.php
└── Services/
```

**Key modules**: Server, Site, Domain, Database, Deployment, SshKey, UnixUser, Php, Network, Security, Scheduler, BackgroundProcess, SourceControl, Composer, Redirect

### Data Flow Pattern

1. **FormRequest** → validates input (never validate in controllers)
2. **Data object (DTO)** → transforms data (NOT validation)
3. **Resource::collection** → formats output for frontend

```php
// Controller pattern
public function store(StoreServerRequest $request): Response
{
    $serverData = ServerData::from($request->validated());
    $server = Server::create($serverData->toArray());
    return Inertia::render('servers/Show', [
        'server' => ServerData::from($server),
    ]);
}
```

### Frontend Structure

```
resources/
├── js/
│   ├── actions/       # Wayfinder-generated route functions
│   ├── components/    # Reusable Vue components
│   ├── composables/   # Vue 3 composition functions
│   ├── layouts/       # Page layouts
│   ├── pages/         # Inertia page components
│   ├── routes/        # Wayfinder-generated named routes
│   └── types/         # TypeScript types (generated + manual)
└── views/
    ├── app.blade.php  # Entry point
    └── provisioning/  # Server provisioning Blade scripts
```

### Key Integrations

- **Wayfinder**: Auto-generates TypeScript route functions. Import from `@/actions/Nip/...` or `@/routes/...`
- **Spatie Laravel Data**: DTOs with `#[TypeScript]` attribute generate frontend types
- **Laravel Horizon**: Queue management (started with `composer run dev`)
- **Laravel Reverb**: WebSocket server for real-time updates
- **Laravel Fortify**: Authentication (config in `config/fortify.php`)

## Conventions

### PHP
- Use PHP 8 constructor property promotion
- Always use explicit return types
- FormRequests for validation, Data objects for transformation
- Prefer `Model::query()` over `DB::`
- Avoid N+1: use eager loading

### Vue/TypeScript
- Composition API with `<script setup lang="ts">` only
- Use Wayfinder imports for routes: `import { show } from '@/actions/...'`
- Inertia `<Form>` component or `useForm` for forms

### Testing
- Pest only (not PHPUnit)
- Use model factories, not manual data
- Tests in `tests/Feature/` (most) and `tests/Unit/`

## Module ServiceProviders

Registered in `bootstrap/providers.php`. Each module's ServiceProvider:
- Loads routes from `Routes/web.php` and `Routes/api.php`
- Discovers migrations from `Database/Migrations/`
- Registers commands from `Console/Commands/`

## Middleware Configuration

Configured in `bootstrap/app.php` (Laravel 12 style), not Kernel.php:
- Custom middleware alias: `server.connected` → `EnsureServerConnected`
- CSRF exceptions for provisioning callbacks
