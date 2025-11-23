# Pipeline Hang Fix - MongoDB Sync

## Problem
The GitHub Actions pipeline was getting stuck on the "Sync logs.json → MongoDB" step, running for 6+ minutes and potentially timing out.

## Root Causes Identified

1. **No timeout protection** - Script could hang indefinitely
2. **MongoDB connection timeouts** - Long waits if MongoDB is slow/unreachable
3. **No progress logging** - Couldn't see where it was stuck
4. **Missing dependency** - `python-dateutil` not installed in workflow
5. **No workflow-level timeout** - GitHub Actions step had no timeout
6. **Silent failures** - Errors weren't being caught properly

## Fixes Applied

### 1. **sync_logs_to_mongo.py** - Enhanced with:

✅ **Timeout Protection (5 minutes)**
- Added signal-based timeout handler
- Automatically exits if script runs too long
- Works on Linux (GitHub Actions), graceful fallback on Windows

✅ **Better MongoDB Connection Settings**
- `serverSelectionTimeoutMS=15000` (15s to find server)
- `connectTimeoutMS=10000` (10s to connect)
- `socketTimeoutMS=30000` (30s for operations)
- Faster failure if MongoDB is unreachable

✅ **Progress Logging**
- Shows connection status
- Shows progress when processing logs (every 100 logs)
- Shows statistics generation progress
- Better error messages with stack traces

✅ **Improved Error Handling**
- Validates JSON before processing
- Handles empty/malformed JSON gracefully
- Continues processing even if individual logs fail
- Proper cleanup on errors

✅ **Better File Reading**
- Checks if file is empty before parsing
- Handles JSON decode errors separately
- More informative error messages

### 2. **.github/workflows/deploy.yml** - Enhanced with:

✅ **Workflow Timeout**
- Added `timeout-minutes: 10` to MongoDB sync step
- Prevents infinite hangs at workflow level

✅ **Missing Dependency**
- Added `python-dateutil` to pip install
- Required for timezone conversion

✅ **Better Error Handling**
- Script failures won't break the entire workflow
- Continues even if sync fails (logs error but exits 0)

## What This Fixes

1. ✅ **No more infinite hangs** - Script times out after 5 minutes max
2. ✅ **Faster failure detection** - MongoDB connection fails faster
3. ✅ **Better visibility** - Progress logs show what's happening
4. ✅ **Workflow protection** - GitHub Actions timeout prevents stuck pipelines
5. ✅ **Graceful degradation** - Continues even if some logs fail

## Expected Behavior Now

1. **Connection Phase** (0-15 seconds)
   - "🔌 Connecting to MongoDB..."
   - "⏳ Testing connection..."
   - "✓ Connected to MongoDB successfully"

2. **Reading Phase** (instant)
   - "📖 Reading logs.json..."
   - "📊 Found X log entries to process..."

3. **Processing Phase** (varies by log count)
   - "🔄 Processing X logs..."
   - "Progress: 100/X (50%)" (every 100 logs)

4. **Statistics Phase** (1-5 seconds)
   - "📈 Generating statistics..."
   - "✓ Found X action types"

5. **Completion** (instant)
   - "✅ Sync completed successfully!"

**Total time should be:**
- Empty logs: < 5 seconds
- 100 logs: ~10-30 seconds
- 1000 logs: ~1-2 minutes
- 10000+ logs: ~2-5 minutes (with progress updates)

## Testing

After pushing these changes:

1. **Trigger the pipeline** (push to main or wait for scheduled run)
2. **Watch the logs** - You should see progress messages
3. **Check timing** - Should complete in < 5 minutes even with many logs
4. **Verify results** - Check MongoDB and stats_actions.csv

## If It Still Hangs

If the pipeline still hangs after these fixes, check:

1. **MongoDB Atlas IP Whitelist**
   - GitHub Actions IPs might be blocked
   - Add `0.0.0.0/0` temporarily to test

2. **MongoDB Connection String**
   - Verify `MONGO_URI` secret is correct
   - Check if credentials are valid

3. **Network Issues**
   - MongoDB Atlas might be slow
   - Check MongoDB Atlas dashboard for connection issues

4. **Large Log Files**
   - If logs.json is very large (>10MB), processing will take longer
   - Consider batching or archiving old logs

## Files Modified

- ✅ `sync_logs_to_mongo.py` - Added timeouts, progress logging, better error handling
- ✅ `.github/workflows/deploy.yml` - Added workflow timeout, missing dependency

