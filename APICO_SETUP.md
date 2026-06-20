# APICO Laravel Setup

This app was created for APICO Plastic Recycling Factory.

## Local run

Use the installed PHP path if `php` is not available until you restart the terminal:

```powershell
& "C:\Users\Owner\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan migrate --seed
& "C:\Users\Owner\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan serve
```

Then open `http://127.0.0.1:8000`.

## Reports

Customer statements accept `from` and `to` dates. The report calculates:

- opening balance before the `from` date
- opening weight before the `from` date
- all transactions inside the date range
- running balance after each transaction
- running weight difference after each recycle in/out transaction

Recycle-in entries are weight-only. Recycle-out entries split the outgoing kg into recycled out, waste, and non-recycled. Only recycled-out kg uses the recycle rate and creates a JOD amount; waste and non-recycled are zero-rate quantities for clearer statistics. Stock purchases carry purchase cost per kg.

## Backup

Run a daily SQLite backup manually with:

```powershell
php artisan apico:backup-database
```

Backups are saved in `storage/app/backups`.
