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

def verify_mongodb_connection(uri: str, db_name: str, collection_name: str) -> Tuple[Optional[MongoClient], Optional[Any], Optional[Any]]:
    """Verify MongoDB connection and return client, db, and collection objects."""
    try:
        client = MongoClient(uri, serverSelectionTimeoutMS=5000)
        client.server_info()  # Force connection
        db = client[db_name]
        col = db[collection_name]
        col.count_documents({})  # Simple test
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

    MONGO_DB = os.getenv("MONGO_DB", "logs_db")
    MONGO_COLLECTION = os.getenv("MONGO_COLLECTION", "logs")

    print(f"🔌 Connecting to MongoDB: {MONGO_DB}.{MONGO_COLLECTION}")

    client, db, col = verify_mongodb_connection(MONGO_URI, MONGO_DB, MONGO_COLLECTION)
    if not client:
        sys.exit(1)

    # Read logs.json
    if not os.path.exists("logs.json"):
        print("ℹ️ logs.json does not exist. Creating database anyway...")
        col.insert_one({"_init": True})
        col.delete_many({"_init": True})
        return

    print("📄 Reading logs.json...")
    with open("logs.json", "r+", encoding="utf-8") as f:
        try:
            data = json.load(f)
        except json.JSONDecodeError:
            print("❌ logs.json is not valid JSON")
            sys.exit(1)

        # If logs.json is empty, still force DB creation
        if not data:
            print("ℹ️ logs.json is empty. Ensuring database/collection exists...")
            col.insert_one({"_init": True})
            col.delete_many({"_init": True})
            return

        if isinstance(data, dict):
            data = [data]

        # Insert logs
        inserted_count = 0
        for log in data:
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

    # Generate stats
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline))

    if stats:
        df = pd.DataFrame(stats).rename(columns={"_id": "action"})
        df.to_csv("stats_actions.csv", index=False)
        print("📊 Generated stats_actions.csv")
    else:
        print("ℹ️ No stats to export")

    # Export full logs
    all_logs = list(col.find({}, {"_id": 0}))
    if all_logs:
        pd.DataFrame(all_logs).to_csv("logs.csv", index=False)
        print("📝 Generated logs.csv")
    else:
        print("ℹ️ No logs to export")


if __name__ == "__main__":
    main()
