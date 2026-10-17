# APICO Backup and Local Refresh Runbook

## Safety rules

- Production application changes and migrations require explicit approval.
- Create a permanent `predeploy` database backup before every deployment.
- The Windows refresh is manual. It creates a local safety dump and asks for confirmation before replacing the local database.
- Never commit `.env`, database passwords, SSH private keys, or generated database dumps.

## Install daily server backups

Run these commands once on the server after the backup code has been deployed:

```bash
cd /var/www/apico
chmod 750 ops/server/backup-apico.sh
sudo mkdir -p /home/apico/backups/{daily,weekly,predeploy}
sudo chown -R apico:apico /home/apico/backups
sudo -u apico ./ops/server/backup-apico.sh daily
```

Verify the resulting archive before scheduling it:

```bash
latest=$(ls -1t /home/apico/backups/daily/apico-daily-*.sql.gz | head -n 1)
gzip -t "$latest"
ls -lh "$latest" "$latest.sha256"
```

Create `/etc/cron.d/apico-backup`:

```cron
17 2 * * * apico APICO_APP_DIR=/var/www/apico /var/www/apico/ops/server/backup-apico.sh daily >> /home/apico/backups/backup.log 2>&1
```

This runs at 02:17 server time. The script retains 30 daily backups and 12 Sunday weekly backups.

## Permanent pre-deployment backup

Run before every deployment:

```bash
sudo -u apico /var/www/apico/ops/server/backup-apico.sh predeploy
```

`predeploy` backups are not removed automatically.

## Manual Windows live-to-local refresh

Start XAMPP MySQL, open PowerShell, and run:

```powershell
cd "C:\Users\Owner\Documents\Codex\2026-05-30\files-mentioned-by-the-user-apico\outputs\apico-laravel"
powershell -ExecutionPolicy Bypass -File .\ops\windows\Refresh-ApicoLiveDatabase.ps1 -IdentityFile "C:\path\to\apico_key"
```

The script:

1. Creates and validates a fresh server backup.
2. Downloads it to `C:\Users\Owner\apico-backup`.
3. Backs up the current local database.
4. Asks for confirmation.
5. Replaces only the local database and displays migration status.

No Windows scheduled task is created and nothing runs in the background.
