from pymongo import MongoClient 
import os, json, pandas as pd 
import hashlib 
import sys
import signal
from datetime import datetime
from dateutil import tz

# Set timeout handler to prevent infinite hangs (Unix/Linux only)
TIMEOUT_ENABLED = False
try:
    def timeout_handler(signum, frame):
        print("❌ Script timeout - taking too long!")
        sys.exit(1)
    
    if hasattr(signal, 'SIGALRM'):
        signal.signal(signal.SIGALRM, timeout_handler)
        signal.alarm(300)  # 5 minutes max
        TIMEOUT_ENABLED = True
        print("⏱️ Timeout protection enabled (5 minutes)")
except (AttributeError, OSError):
    print("⚠️ Timeout protection not available on this platform")
    TIMEOUT_ENABLED = False

# Try to get MONGO_URI from environment variable first
MONGO_URI = os.getenv("MONGO_URI")

# If not set, use hardcoded URI as fallback
if not MONGO_URI:
    MONGO_URI = "mongodb+srv://quick_user_second:DfhuouESRXAuoJ1d@cluster0.kr90782.mongodb.net/"
    print("⚠️ Using hardcoded MONGO_URI (environment variable not set)")
else:
    print("✓ Using MONGO_URI from environment variable")

print("🔌 Connecting to MongoDB...")
try:
    # Reduced timeouts to fail faster
    client = MongoClient(
        MONGO_URI, 
        serverSelectionTimeoutMS=15000,  # 15 seconds to find server
        connectTimeoutMS=10000,  # 10 seconds to connect
        socketTimeoutMS=30000,  # 30 seconds for operations
        maxPoolSize=10
    )
    # Test connection with timeout
    print("⏳ Testing connection...")
    client.admin.command('ping')
    print("✓ Connected to MongoDB successfully")
except Exception as e:
    print(f"❌ Failed to connect to MongoDB: {e}")
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(1)

db = client["quickannonce_second"]
col = db["users_second"]

if not os.path.exists("logs.json"):
    print("Aucun fichier logs.json trouvé.")
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(0)

print("📖 Reading logs.json...")
try:
    with open("logs.json", "r", encoding="utf-8") as f:
        content = f.read().strip()
        if not content or content == '[]':
            print("Aucun log à insérer (fichier vide).")
            if TIMEOUT_ENABLED:
                signal.alarm(0)  # Cancel timeout
            sys.exit(0)
        data = json.loads(content)
except json.JSONDecodeError as e:
    print(f"❌ Erreur: logs.json n'est pas un JSON valide: {e}")
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(1)
except Exception as e:
    print(f"❌ Erreur lors de la lecture de logs.json: {e}")
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(1)

if not data:
    print("Aucun log à insérer.")
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(0)

if not isinstance(data, list):
    data = [data]

print(f"📊 Found {len(data)} log entries to process...")

# Timezone objects
server_tz = tz.gettz("UTC")       # fallback if timestamp has no timezone
morocco_tz = tz.gettz("Africa/Casablanca")

nb_inserts = 0

def convert_timestamp(ts_string):
    """Convert timestamp from server timezone to Morocco timezone."""
    try:
        dt = datetime.fromisoformat(ts_string)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=server_tz)

        dt_ma = dt.astimezone(morocco_tz)
        return dt_ma.isoformat()

    except Exception:
        return ts_string  # if parsing fails, keep original

try:
    total = len(data)
    print(f"🔄 Processing {total} logs...")
    for idx, log in enumerate(data, 1):
        if idx % 100 == 0 or idx == total:
            print(f"  Progress: {idx}/{total} ({idx*100//total}%)")
        
        if "timestamp" in log:
            log["timestamp"] = convert_timestamp(log["timestamp"])

        raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
        uid = hashlib.md5(raw.encode("utf-8")).hexdigest()

        log_with_id = {**log, "_id": uid}

        try:
            result = col.update_one(
                {"_id": uid},
                {"$setOnInsert": log_with_id},
                upsert=True
            )

            if result.upserted_id is not None:
                nb_inserts += 1
        except Exception as e:
            print(f"⚠️ Warning: Failed to insert log {idx}: {e}")
            continue  # Skip this log and continue

    print(f"✅ {nb_inserts} nouveaux logs insérés dans MongoDB Atlas (sans doublons).")
except Exception as e:
    print(f"❌ Error inserting logs: {e}")
    import traceback
    traceback.print_exc()
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(1)

print("📈 Generating statistics...")
try:
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline, allowDiskUse=True))
    print(f"✓ Found {len(stats)} action types")
except Exception as e:
    print(f"❌ Error aggregating stats: {e}")
    import traceback
    traceback.print_exc()
    if TIMEOUT_ENABLED:
        signal.alarm(0)  # Cancel timeout
    sys.exit(1)

if stats:
    try:
        df = pd.DataFrame(stats).rename(columns={"_id": "action"})
        df.to_csv("stats_actions.csv", index=False)
        print("✅ Rapport d'analyse généré : logs.csv")
    except Exception as e:
        print(f"⚠️ Warning: Failed to generate CSV: {e}")
else:
    print("⚠️ Aucune donnée pour générer le rapport logs.csv")

# Cancel timeout on successful completion
if TIMEOUT_ENABLED:
    signal.alarm(0)
print("✅ Sync completed successfully!")
