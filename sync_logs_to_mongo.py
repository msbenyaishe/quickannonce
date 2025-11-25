#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export to CSV.
Handles the full data cycle:
1. Reads logs from logs.json
2. Inserts new logs into MongoDB
3. Exports all logs to CSV
4. Generates statistics
"""

import os
import json
import hashlib
import sys
import argparse
import pandas as pd
from pymongo import MongoClient, errors
from typing import Any, Optional, Tuple
from datetime import datetime
from dateutil import tz  # Added for timezone conversion

# Morocco timezone
morocco_tz = tz.gettz("Africa/Casablanca")
server_tz = tz.gettz("UTC")  # fallback if timestamp has no timezone

def convert_to_morocco(ts_string: str) -> str:
    """Convert ISO timestamp string to Morocco timezone."""
    try:
        dt = datetime.fromisoformat(ts_string)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=server_tz)
        dt_ma = dt.astimezone(morocco_tz)
        return dt_ma.isoformat()
    except Exception:
        return ts_string  # if parsing fails, keep original


def verify_mongodb_connection(uri: str, db_name: str, collection_name: str) -> Tuple[Optional[MongoClient], Optional[Any], Optional[Any]]:
    try:
        client = MongoClient(uri, serverSelectionTimeoutMS=5000)
        client.server_info()
        db = client[db_name]
        col = db[collection_name]
        col.count_documents({})
        print(f"✅ Successfully connected to MongoDB: {db_name}.{collection_name}")
        return client, db, col

    except Exception as e:
        print(f"❌ MongoDB connection error: {e}")
        return None, None, None


def parse_arguments():
    parser = argparse.ArgumentParser(description='Sync logs to MongoDB')
    parser.add_argument('--no-clear', action='store_true', help='Do not clear logs after processing')
    return parser.parse_args()


def main():
    args = parse_arguments()

    # Load MongoDB configuration
    MONGO_URI = os.getenv("MONGO_URI")
    if not MONGO_URI:
        print("❌ Error: MONGO_URI environment variable is required!")
        sys.exit(1)

    MONGO_DB = os.getenv("MONGO_DB")
    if not MONGO_DB or MONGO_DB.strip() == "":
        print("⚠️ MONGO_DB is empty. Using default: logs_db")
        MONGO_DB = "logs_db"

    MONGO_COLLECTION = os.getenv("MONGO_COLLECTION", "logs")

    print(f"🔌 Connecting to MongoDB: {MONGO_DB}.{MONGO_COLLECTION}")

    client, db, col = verify_mongodb_connection(MONGO_URI, MONGO_DB, MONGO_COLLECTION)
    if not client:
        sys.exit(1)

    # Read logs.json and insert into DB
    if os.path.exists("logs.json"):
        print("📄 Reading logs.json...")
        with open("logs.json", "r+", encoding="utf-8") as f:
            try:
                data = json.load(f)
            except json.JSONDecodeError:
                print("❌ logs.json is not valid JSON")
                sys.exit(1)

            if not data:
                print("ℹ️ logs.json is empty.")
                data = []

            if isinstance(data, dict):
                data = [data]

            inserted_count = 0
            for log in data:
                # Convert timestamp to Morocco time if present
                if "timestamp" in log:
                    log["timestamp"] = convert_to_morocco(log["timestamp"])

                raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
                uid = hashlib.md5(raw.encode("utf-8")).hexdigest()
                log_with_id = {**log, "_id": uid}

                result = col.update_one(
                    {"_id": uid},
                    {"$setOnInsert": log_with_id},
                    upsert=True
                )

                if result.upserted_id:
                    inserted_count += 1

            print(f"✅ {inserted_count} new logs inserted (no duplicates)")

            # Clear logs.json after processing
            if not args.no_clear:
                print("🧹 Clearing logs.json...")
                f.seek(0)
                f.truncate()
                json.dump([], f)
    else:
        print("ℹ️ logs.json does not exist. Skipping insert.")

    # --- FIXED CSV EXPORT: always from MongoDB ---
    # Export full logs from MongoDB
    all_logs = list(col.find({}, {"_id": 0}))
    if all_logs:
        pd.DataFrame(all_logs).to_csv("logs.csv", index=False)
        print(f"📝 Exported {len(all_logs)} logs from DB to logs.csv")
    else:
        print("ℹ️ No logs in database to export")

    # Export stats from MongoDB
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline))

    if stats:
        df = pd.DataFrame(stats).rename(columns={"_id": "action"})
        df.to_csv("stats_actions.csv", index=False)
        print(f"📊 Exported {len(stats)} stats from DB to stats_actions.csv")
    else:
        print("ℹ️ No stats in database to export")


if __name__ == "__main__":
    main()
