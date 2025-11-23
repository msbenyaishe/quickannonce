from pymongo import MongoClient 
import os, json, pandas as pd 
import hashlib 
import sys
from datetime import datetime
from dateutil import tz

# Try to get MONGO_URI from environment variable first
MONGO_URI = os.getenv("MONGO_URI")

# If not set, use hardcoded URI as fallback
if not MONGO_URI:
    MONGO_URI = "mongodb+srv://quick_user:said2005@cluster0.kr90782.mongodb.net/"
    print("⚠️ Using hardcoded MONGO_URI (environment variable not set)")
else:
    print("✓ Using MONGO_URI from environment variable")

try:
    client = MongoClient(MONGO_URI, serverSelectionTimeoutMS=10000, connectTimeoutMS=10000)
    client.admin.command('ping')
    print("✓ Connected to MongoDB successfully")
except Exception as e:
    print(f"❌ Failed to connect to MongoDB: {e}")
    sys.exit(1)

db = client["quickannonce"]
col = db["users"]

if not os.path.exists("logs.json"):
    print("Aucun fichier logs.json trouvé.")
    sys.exit(0)

with open("logs.json", "r", encoding="utf-8") as f:
    data = json.load(f)

if not data:
    print("Aucun log à insérer.")
    sys.exit(0)

if not isinstance(data, list):
    data = [data]

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
    for log in data:
        if "timestamp" in log:
            log["timestamp"] = convert_timestamp(log["timestamp"])

        raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
        uid = hashlib.md5(raw.encode("utf-8")).hexdigest()

        log_with_id = {**log, "_id": uid}

        result = col.update_one(
            {"_id": uid},
            {"$setOnInsert": log_with_id},
            upsert=True
        )

        if result.upserted_id is not None:
            nb_inserts += 1

    print(f"{nb_inserts} nouveaux logs insérés dans MongoDB Atlas (sans doublons).")
except Exception as e:
    print(f"❌ Error inserting logs: {e}")
    sys.exit(1)

try:
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline))
except Exception as e:
    print(f"❌ Error aggregating stats: {e}")
    sys.exit(1)

if stats:
    df = pd.DataFrame(stats).rename(columns={"_id": "action"})
    df.to_csv("stats_actions.csv", index=False)
    print("Rapport d'analyse généré : stats_actions.csv")
else:
    print("Aucune donnée pour générer le rapport stats_actions.csv.")
