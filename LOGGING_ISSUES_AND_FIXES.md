# Logging System Issues and Fixes

## Problems Identified

### 1. **Empty logs.json File**
The `logs.json` file exists but is empty (`[]`), indicating that logs are not being written successfully.

### 2. **Missing Logging in admin-login.php**
- `admin-login.php` does NOT call `log_action()` when admin logs in
- `admin-login.php` does NOT set `$_SESSION['username']`, which `log_action()` needs

### 3. **Session Username Not Always Set**
- `log_action()` tries to access `$_SESSION['username']` but it may not be set in all cases
- If user logs in via `admin-login.php`, `$_SESSION['username']` is never set
- If user logs out, session might be destroyed before logging

### 4. **Silent Failures**
- `log_action.php` uses `@file_put_contents()` which suppresses errors
- Errors are only logged to PHP error_log, which may not be checked
- No visible feedback when logging fails

### 5. **Path Issues**
- Uses relative path `__DIR__ . '/../logs.json'` which might have issues on Windows
- No proper path normalization for cross-platform compatibility

### 6. **Permission Issues**
- No check if directory is writable before attempting to write
- No check if file is writable
- Directory creation might fail silently

### 7. **MongoDB Sync Not Automated**
- `sync_logs_to_mongo.py` must be run manually
- No CI/CD pipeline to automatically sync logs
- No scheduled task/cron job to sync logs

## Fixes Applied

### 1. **Enhanced log_action.php**
- ✅ Improved username detection (tries multiple session keys: `username`, `email`, `user_id`)
- ✅ Better path handling with `DIRECTORY_SEPARATOR` for cross-platform compatibility
- ✅ Added directory writability check before attempting to write
- ✅ Removed `@` suppression to see errors in development
- ✅ Enhanced error logging with more diagnostic information
- ✅ Early return if directory creation or permission check fails

### 2. **Fixed admin-login.php**
- ✅ Added `$_SESSION['username'] = $admin['email']` when admin logs in
- ✅ Added `log_action("connexion", ...)` call after successful admin login
- ✅ Added `session_regenerate_id(true)` for security

### 3. **Created Diagnostic Tool**
- ✅ Created `test_logging.php` to diagnose logging issues
- ✅ Tests function existence, file paths, permissions, and actual logging

## Remaining Issues to Check

### 1. **File Permissions**
Run `test_logging.php` in your browser to check:
- If `logs.json` is writable
- If the directory is writable
- If there are permission errors

### 2. **PHP Error Log**
Check your PHP error log (usually in `php.ini` or server logs) for messages like:
- "Failed to write to logs.json"
- Permission denied errors
- Directory creation failures

### 3. **MongoDB Sync**
The Python script `sync_logs_to_mongo.py` needs to be run manually or via:
- **Option A**: Manual execution: `python sync_logs_to_mongo.py`
- **Option B**: Create a cron job/scheduled task
- **Option C**: Set up CI/CD pipeline (GitHub Actions, etc.)

## How to Test

1. **Test Logging Functionality:**
   ```
   Open: http://your-domain/test_logging.php
   ```
   This will show you exactly what's working and what's not.

2. **Test Real Actions:**
   - Log in via `login.php` → should log "connexion"
   - Log out via `logout.php` → should log "deconnexion"
   - Create an ad via `post-user.php` or `post-admin.php` → should log "creation_annonce"
   - Delete an ad via `admin-delete-ad.php` → should log "suppression_annonce"

3. **Check logs.json:**
   ```bash
   cat logs.json
   # or on Windows:
   type logs.json
   ```
   Should see JSON array with log entries.

4. **Check PHP Error Log:**
   Look for error messages related to file writing.

## MongoDB Sync Setup

To automatically sync logs to MongoDB, you have several options:

### Option 1: Manual Sync (Current)
```bash
python sync_logs_to_mongo.py
```

### Option 2: Scheduled Task (Windows)
Create a scheduled task that runs:
```cmd
python C:\path\to\sync_logs_to_mongo.py
```

### Option 3: Cron Job (Linux/Mac)
```bash
# Run every hour
0 * * * * cd /path/to/project && python sync_logs_to_mongo.py
```

### Option 4: GitHub Actions CI/CD
Create `.github/workflows/sync-logs.yml`:
```yaml
name: Sync Logs to MongoDB
on:
  schedule:
    - cron: '0 * * * *'  # Every hour
  workflow_dispatch:  # Manual trigger
jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-python@v2
        with:
          python-version: '3.x'
      - run: pip install pymongo pandas python-dateutil
      - run: python sync_logs_to_mongo.py
        env:
          MONGO_URI: ${{ secrets.MONGO_URI }}
```

## Next Steps

1. ✅ Run `test_logging.php` to diagnose issues
2. ✅ Check PHP error logs for any errors
3. ✅ Test actual login/logout/ad creation to verify logging works
4. ✅ Set up MongoDB sync (choose one of the options above)
5. ✅ Monitor `logs.json` and MongoDB to ensure data is flowing

## Files Modified

- ✅ `includes/log_action.php` - Enhanced error handling and path management
- ✅ `admin-login.php` - Added logging and username session variable
- ✅ `test_logging.php` - New diagnostic tool

## Files to Review

- `logout.php` - Already calls `log_action()` but verify it works
- `post-user.php` - Already calls `log_action()` but verify it works
- `post-admin.php` - Already calls `log_action()` but verify it works
- `admin-delete-ad.php` - Already calls `log_action()` but verify it works

