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
from typing import List, Dict, Any, Optional

def verify_mongodb_connection(uri: str, db_name: str) -> tuple[Optional[MongoClient], Optional[Any], Optional[Any]]:
    """Verify MongoDB connection and return client, db, and collection objects."""
    try:
        # Test connection with a short timeout
        client = MongoClient(uri, serverSelectionTimeoutMS=5000)
        
        # Force connection to check if it's successful
        client.server_info()
        
        # Get database and collection
        db = client[db_name]
        col = db["logs"]
        
        # Test a simple operation
        col.count_documents({})
        
        print(f"✅ Successfully connected to MongoDB: {uri.split('@')[-1]}/{db_name}")
        return client, db, col
        
    except errors.ServerSelectionTimeoutError:
        print(f"❌ Failed to connect to MongoDB server: {uri}")
        print("Please check your MONGO_URI and ensure the server is accessible")
    except errors.ConfigurationError as e:
        print(f"❌ Invalid MongoDB URI: {e}")
    except Exception as e:
        print(f"❌ Unexpected error: {str(e)}")
    
    return None, None, None

# Get MongoDB configuration from environment
MONGO_URI = os.getenv("MONGO_URI")
if not MONGO_URI:
    print("❌ Error: MONGO_URI environment variable is required!")
    sys.exit(1)

# Get database name from environment or use default
MONGO_DB = os.getenv("MONGO_DB", "logs_db")

print(f"🔌 Attempting to connect to MongoDB database: {MONGO_DB}")

def parse_arguments():
    parser = argparse.ArgumentParser(description='Sync logs to MongoDB')
    parser.add_argument('--no-clear', action='store_true', 
                       help='Do not clear logs after processing')
    return parser.parse_args()

def main():
    args = parse_arguments()
    # Verify and establish MongoDB connection
    client, db, col = verify_mongodb_connection(MONGO_URI, MONGO_DB)
    if None in (client, db, col):
        print("❌ Exiting due to MongoDB connection issues")
        return

    # --- Read logs.json ---
    if not os.path.exists("logs.json"):
        print("ℹ️ No logs.json file found.")
        return

    print("📄 Reading logs.json...")
    with open("logs.json", "r+", encoding="utf-8") as f:
        try:
            data = json.load(f)
        except json.JSONDecodeError:
            print("❌ Error: logs.json is not valid JSON")
            return

    if not data:
        print("ℹ️ No logs to process.")
        return

    # Ensure data is a list
    if isinstance(data, dict):
        data = [data]

    # --- Insert into MongoDB without duplicates ---
    inserted_count = 0
    for log in data:
        # Generate unique ID based on log content
        raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
        uid = hashlib.md5(raw.encode("utf-8")).hexdigest()
        
        # Add ID to log
        log_with_id = {**log, "_id": uid}
        
        # Insert if not exists
        result = col.update_one(
            {"_id": uid},
            {"$setOnInsert": log_with_id},
            upsert=True
        )
        
        if result.upserted_id is not None:
            inserted_count += 1

    print(f"✅ {inserted_count} new logs inserted into MongoDB (no duplicates)")

    # Clear the file if --no-clear is not set
    if not args.no_clear:
        print("🧹 Clearing logs.json after successful processing")
        f.seek(0)
        f.truncate()
        json.dump([], f)

    # --- Generate stats ---
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline))

    # --- Export to CSV ---
    if stats:
        df = pd.DataFrame(stats).rename(columns={"_id": "action"})
        df.to_csv("stats_actions.csv", index=False)
        print("📊 Generated stats_actions.csv")
    else:
        print("ℹ️ No data to generate stats_actions.csv")
        
    # --- Export all logs to CSV ---
    all_logs = list(col.find({}, {"_id": 0}))  # Exclude _id field
    if all_logs:
        pd.DataFrame(all_logs).to_csv("logs.csv", index=False)
        print("📝 Generated logs.csv")
    else:
        print("ℹ️ No logs to export to CSV")

if __name__ == "__main__":
    main()