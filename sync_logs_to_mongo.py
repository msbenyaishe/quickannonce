#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export all MongoDB documents to logs.csv.

Usage:
    python3 sync_logs_to_mongo.py
"""

import csv
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

from pymongo import MongoClient
from pymongo.collection import Collection
from pymongo.errors import BulkWriteError, PyMongoError

BASE_DIR = Path(__file__).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
DEFAULT_URI = "mongodb+srv://quick_user_second:DfhuouESRXAuoJ1d@cluster0.kr90782.mongodb.net/"

MONGO_URI = os.getenv("MONGO_URI", DEFAULT_URI)
DB_NAME = "logs_db"
COLLECTION_NAME = "logs"


def load_logs(path: Path) -> List[Dict[str, Any]]:
    """Load log entries from logs.json."""
    if not path.exists():
        raise FileNotFoundError("logs.json not found in the current directory.")

    try:
        with path.open("r", encoding="utf-8") as handle:
            payload = json.load(handle)
    except json.JSONDecodeError as exc:
        raise ValueError(f"logs.json contains invalid JSON: {exc}") from exc

    if isinstance(payload, dict):
        payload = [payload]

    if not isinstance(payload, list) or not all(isinstance(item, dict) for item in payload):
        raise ValueError("logs.json must contain an array of JSON objects.")

    if not payload:
        raise ValueError("logs.json does not contain any log entries.")

    return payload


def create_client(uri: str) -> MongoClient:
    """Create and validate a MongoDB client."""
    try:
        client = MongoClient(
            uri,
            serverSelectionTimeoutMS=15000,
            connectTimeoutMS=10000,
            socketTimeoutMS=30000,
        )
        client.admin.command("ping")
        return client
    except PyMongoError as exc:
        raise ConnectionError(f"Unable to connect to MongoDB: {exc}") from exc


def insert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    """Insert log documents into MongoDB."""
    if not logs:
        return 0

    try:
        result = collection.insert_many(logs, ordered=False)
        return len(result.inserted_ids)
    except BulkWriteError as exc:
        # Most common reason is duplicate keys; report partial success.
        inserted = exc.details.get("nInserted", 0) if exc.details else 0
        print(f"⚠️ Partial insert: {inserted} documents inserted, reason: {exc.details.get('writeErrors') if exc.details else exc}")
        return inserted
    except PyMongoError as exc:
        raise RuntimeError(f"Failed to insert logs into MongoDB: {exc}") from exc


def fetch_documents(collection: Collection) -> List[Dict[str, Any]]:
    """Fetch every document from the MongoDB collection."""
    try:
        return list(collection.find())
    except PyMongoError as exc:
        raise RuntimeError(f"Failed to read documents from MongoDB: {exc}") from exc


def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    """Determine CSV headers by preserving their first-seen order."""
    seen: List[str] = []
    for record in records:
        for key in record.keys():
            if key not in seen:
                seen.append(key)
    return seen


def normalize_value(value: Any) -> Any:
    """Convert complex values into CSV-friendly strings."""
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        return json.dumps(value, ensure_ascii=False)
    if isinstance(value, (str, int, float, bool)):
        return value
    return str(value)


def write_csv(records: List[Dict[str, Any]], path: Path) -> None:
    """Write MongoDB documents to logs.csv with UTF-8 encoding."""
    fieldnames = compute_fieldnames(records)
    if not fieldnames:
        # Fallback to a minimal header so the CSV is still valid.
        fieldnames = ["_id"]

    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for record in records:
            row = {field: normalize_value(record.get(field)) for field in fieldnames}
            writer.writerow(row)


def main() -> None:
    print("🔄 Starting log sync...")
    client: Optional[MongoClient] = None

    try:
        logs = load_logs(LOG_FILE)
        print(f"📄 Loaded {len(logs)} log entries from logs.json")

        client = create_client(MONGO_URI)
        collection = client[DB_NAME][COLLECTION_NAME]
        inserted = insert_logs(collection, logs)
        print(f"✅ Inserted {inserted} new documents into MongoDB collection '{COLLECTION_NAME}'")

        documents = fetch_documents(collection)
        print(f"📥 Retrieved {len(documents)} documents from MongoDB")

        write_csv(documents, CSV_FILE)
        print(f"📁 Exported data to {CSV_FILE.resolve()}")
    except Exception as exc:
        print(f"❌ {exc}")
        sys.exit(1)
    finally:
        if client is not None:
            client.close()
            print("🔌 MongoDB connection closed.")


if __name__ == "__main__":
    main()
