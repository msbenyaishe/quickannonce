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
import pandas as pd
from pymongo import MongoClient
from typing import List, Dict, Any

# Get MongoDB URI from environment
MONGO_URI = os.getenv("MONGO_URI")
if not MONGO_URI:
    raise ValueError("❌ MONGO_URI environment variable is required!")

def main():
    # Connect to MongoDB
    client = MongoClient(MONGO_URI)
    # Use the database name from the connection string or fallback to 'quickannonces'
    db_name = client.get_database().name or 'logs_db'
    db = client[db_name]
    col = db["logs"]  # Using 'logs' collection

    # --- Read logs.json ---
    if not os.path.exists("logs.json"):
        print("ℹ️ No logs.json file found.")
        return

    with open("logs.json", "r", encoding="utf-8") as f:
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