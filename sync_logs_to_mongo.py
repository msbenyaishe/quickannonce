#!/usr/bin/env python3
"""
Synchronize logs.json with MongoDB and export full logs.

All logs are inserted, duplicates allowed. Mongo URI must be provided via the MONGO_URI environment variable.
"""

import csv
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List

from pymongo import MongoClient
from pymongo.collection import Collection

BASE_DIR = Path(_file_).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_EXPORT = BASE_DIR / "logs.csv"

def get_mongo_client() -> MongoClient:
    """Connect to MongoDB using MONGO_URI environment variable."""
    uri = os.getenv("MONGO_URI")
    if not uri:
        raise ValueError("MONGO_URI not provided!")
    return MongoClient(uri)

def load_logs() -> List[Dict[str, Any]]:
    """Load logs.json into a list of dicts."""
    if not LOG_FILE.exists():
        print("⚠ No logs.json file found.")
        sys.exit(0)

    with LOG_FILE.open("r", encoding="utf-8") as f:
        data = json.load(f)

    if not data:
        print("⚠ logs.json is empty.")
        sys.exit(0)

    if isinstance(data, dict):
        data = [data]

    if not isinstance(data, list) or not all(isinstance(item, dict) for item in data):
        raise ValueError("logs.json must contain a list of JSON objects.")

    return data

def insert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    """Insert all logs into MongoDB (duplicates allowed)."""
    inserted_count = 0
    for log in logs:
        try:
            collection.insert_one(log)
            inserted_count += 1
        except Exception as exc:
            print(f"⚠ Failed to insert log: {exc}")
    return inserted_count

def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    """Compute CSV headers from all fields in records."""
    seen: List[str] = []
    for record in records:
        for key in record.keys():
            if key not in seen:
                seen.append(key)
    return seen

def normalize_value(value: Any) -> Any:
    """Prepare value for CSV output."""
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        return json.dumps(value, ensure_ascii=False)
    return value

def export_full_collection(collection: Collection) -> None:
    """Export all MongoDB documents to logs.csv."""
    docs = list(collection.find())
    if not docs:
        print("⚠ No documents found in MongoDB to export.")
        return

    headers = compute_fieldnames(docs)
    with CSV_EXPORT.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=headers)
        writer.writeheader()
        for doc in docs:
            row = {field: normalize_value(doc.get(field)) for field in headers}
            writer.writerow(row)
    print(f"✅ Exported all logs to {CSV_EXPORT.resolve()}")

def main() -> None:
    logs = load_logs()
    client = get_mongo_client()
    db = client["logs_db"]
    col = db["logs"]

    inserted = insert_logs(col, logs)
    print(f"✅ Inserted {inserted} logs into MongoDB (duplicates allowed).")

    export_full_collection(col)

    client.close()
    print("🔌 MongoDB connection closed.")

if _name_ == "_main_":
    main()