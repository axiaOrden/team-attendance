# Employee Attendance

A mobile-first employee attendance application built with Laravel, Laravel Breeze,
MySQL/MariaDB and a Material 3 Expressive interface.

**Who checked in → when → where → photo evidence → device information.**

There is no geofencing and no location restrictions: employees can check in from the
office, a market, a customer site, a depot or anywhere else. The GPS coordinates, the
server timestamp, the watermarked photo, the resolved address and the device
information are recorded so that an administrator can audit attendance afterwards.

## Features

**Employee (mobile-first, bottom navigation)**

- Register with full name, unique employee ID, email and password
- Check in / check out with a single large button
- GPS is read from the device only - coordinates can never be typed or edited
- Live accuracy readout and reverse-geocoded address before the photo is taken
- Camera capture (live preview, with a native camera fallback on non-HTTPS pages)
- Watermarked photo: name / employee ID, date • time, latitude, longitude, accuracy,
  address and device, drawn on a semi-transparent panel at the bottom of the image
- Check-out is only offered once checked in; a day is complete after check-out
- Personal attendance history grouped by day with the stored photos

**Administrator (responsive sidebar on desktop)**

- Dashboard with filters: single date, date range, employee, employee ID, check in / out
- Records as a list and as a map of OpenStreetMap markers
- Employee trail map: all attendance points for one employee on one day, numbered in
  chronological order and connected with a dashed line
- Marker popups with employee, employee ID, type, timestamp, GPS accuracy, address,
  device information and the attendance photo (click the photo for the full image)
- Employee directory with role management (employee / administrator)

The trail line only shows the chronological relationship between the recorded
attendance events - it is not a claim about the route actually travelled.

## Requirements

- PHP 8.2 or newer (tested on PHP 8.5)
- Composer
- MySQL or MariaDB
- Node.js (only to build the front-end assets)
- HTTPS is strongly recommended in production: browsers only expose the GPS and the
  live camera preview on secure origins. On plain HTTP the app falls back to the
  native camera app, but location permission is usually refused by the browser.

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, then:

```bash
php artisan migrate --seed
php artisan serve
```

The seeder creates the first administrator and (when `APP_ENV=local`) a small demo
team so the dashboard and trail map have something to show:

| Account | Email | Password | Role |
| --- | --- | --- | --- |
| Administrator | `admin@euro-mega.com` | `password` | Administrator |
| John Doe (`EMP001`) | `john@euro-mega.com` | `password` | Employee |
| Amaka Obi (`EMP002`) | `amaka@euro-mega.com` | `password` | Employee |

Change those passwords before going live. Deleting the demo seeder call from
`database/seeders/DatabaseSeeder.php` keeps production data clean.

## Deploying under a subdirectory (XAMPP / Apache)

The application never assumes it is installed at the domain root. The example used
throughout is `https://app.euro-mega.com/attendance`.

1. Copy the whole project into `htdocs/attendance` (the project root, not `public`).
   The `.htaccess` in the project root rewrites every request into `public/`, so
   `https://app.euro-mega.com/attendance` is the application entry point and the
   application code stays outside the web root.
2. Point both URL settings at the subdirectory in `.env`:

   ```dotenv
   APP_URL=https://app.euro-mega.com/attendance
   ASSET_URL=/attendance
   ```

   `APP_URL` drives routes, redirects and form actions; `ASSET_URL` drives CSS,
   JavaScript, Leaflet and every other asset (Vite resolves its build files through
   the same asset base).
3. Set the timezone used for "today", daily grouping and every displayed time:

   ```dotenv
   APP_TIMEZONE=Africa/Lagos
   ```

   Attendance is stored with the authoritative server timestamp and displayed in this
   timezone, so "today" matches the employees' working day.
4. Make sure `mod_rewrite` is enabled in Apache (`LoadModule rewrite_module`) and that
   `AllowOverride All` applies to `htdocs/attendance`.
5. Run the database migrations and create the administrator:

   ```bash
   php artisan migrate --force --seed
   php artisan optimize:clear
   ```

6. Recommended PHP settings on XAMPP (`php.ini`): `upload_max_filesize = 12M`,
   `post_max_size = 16M`, `max_execution_time = 60`. Attendance photos are resized and
   re-encoded in the browser (longest edge 1440 px, JPEG quality 0.85), so uploads stay
   well below those limits.

If `mod_rewrite` is not available, the application is still reachable through
`https://app.euro-mega.com/attendance/public/`, and `APP_URL`/`ASSET_URL` should then
include `/public`.

### Alternative: public/ as the document root

If you configure the virtual host with `DocumentRoot ".../htdocs/attendance/public"`
(so the app is served at `/attendance` through an alias or at the root of a
subdomain), leave `ASSET_URL` empty and set `APP_URL` to the exact base URL -
for example `APP_URL=https://attendance.euro-mega.com`. No rewrite rule is needed in
that layout.

## How attendance is stored

`attendance_records` keeps both photos and every piece of evidence for one event:

`user_id`, `employee_id`, `type` (`check_in` / `check_out`), `attendance_date`,
`recorded_at` (server timestamp), `latitude`, `longitude`, `accuracy`, `address`,
`city`, `state`, `country`, `photo` (original capture), `watermarked_photo`,
`device_information` (JSON), `user_agent`, `ip_address`, timestamps.

- Photos are stored on the private `local` disk
  (`storage/app/private/attendance/{user}/{date}/`) and are streamed through
  `GET /attendance/{record}/photo/{watermarked|original}` to the owner and to
  administrators only.
- The watermark is generated in the browser for the employee's own copy of the image;
  the database record - not the watermark - is the attendance record of truth.
- The server decides whether a check in or a check out is possible, so a duplicate or
  out-of-order event is rejected with HTTP 409.
- Addresses are resolved with the OpenStreetMap Nominatim reverse geocoding API and
  cached for 30 days. If geocoding fails, attendance is still recorded (address fields
  stay empty). Configure `NOMINATIM_USER_AGENT` with a contact address in production.

## Maps

OpenStreetMap tiles are loaded by Leaflet, which is bundled locally in
`public/vendor/leaflet` (no CDN dependency). The administrator dashboard plots the
filtered records and the trail page plots one employee's day. Map data is passed to the
front end as JSON through `#admin-map-data` and `#trail-map-data`.

## Tests

```bash
php artisan test
```

Covers registration (including unique employee IDs), authentication, check in / check
out rules, required GPS and photo, private photo access, employee history isolation,
administrator access control, dashboard filters and the entry-point redirects.

## Front-end assets

```bash
npm run dev     # development server
npm run build   # production build into public/build
```

`resources/css/app.css` contains the Material 3 Expressive design system (colour,
shape, elevation and motion tokens) plus the employee and administrator components.
Material icons are inline SVGs (`resources/views/components/md-icon.blade.php`), so no
icon font or CDN is required.
